<?php
/**
 * API key repository.
 *
 * Storage model:
 *   - The full plain-text key (e.g. `usc_live_aBcD1234…`) is generated
 *     once, returned to the caller, and **never persisted**.
 *   - We store a bcrypt hash of the full key (via wp_hash_password) so a
 *     database leak can't be replayed against the API.
 *   - We also store the first 12 characters of the key (the "prefix")
 *     in plain text. It's not a secret on its own — it just lets us
 *     index lookups: when a request arrives, we compare the prefix to
 *     narrow the row count to ~1, then do the expensive hash check.
 *
 * Key format: `usc_<env>_<32 random base62 chars>` where <env> is `live`
 * for now (room for `test` later if we want a sandbox flow).
 *
 * Scopes:
 *   - `read`  — GET / HEAD only
 *   - `write` — read + POST / PUT / PATCH / DELETE
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Api;

use Univer\SmartCarousel\Database\Database_Installer;

defined( 'ABSPATH' ) || exit;

final class Api_Key_Repository {

	public const SCOPE_READ  = 'read';
	public const SCOPE_WRITE = 'write';

	private const ALLOWED_SCOPES = [ self::SCOPE_READ, self::SCOPE_WRITE ];
	private const PREFIX_LENGTH  = 12;
	private const KEY_PREFIX     = 'usc_live_';
	private const RANDOM_LENGTH  = 32;

	/**
	 * Create a new API key. Returns the row PLUS the plain key (only time
	 * the caller will ever see it).
	 *
	 * @return array{key:string, record:array<string,mixed>}|null
	 */
	public static function create( string $name, string $scope, int $created_by ): ?array {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$name = 'Untitled key';
		}

		$scope = self::sanitize_scope( $scope );

		$plain  = self::generate_plain_key();
		$prefix = substr( $plain, 0, self::PREFIX_LENGTH );
		$hash   = wp_hash_password( $plain );

		global $wpdb;
		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB
			Database_Installer::table_api_keys(),
			[
				'name'       => $name,
				'key_prefix' => $prefix,
				'key_hash'   => $hash,
				'scope'      => $scope,
				'created_by' => $created_by,
				'created_at' => $now,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		if ( ! $inserted ) {
			return null;
		}

		$id     = (int) $wpdb->insert_id;
		$record = self::get( $id );

		return [
			'key'    => $plain,
			'record' => $record,
		];
	}

	/**
	 * Look up a key by ID. Never returns the hash field.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'SELECT * FROM ' . Database_Installer::table_api_keys() . ' WHERE id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			'SELECT * FROM ' . Database_Installer::table_api_keys() . ' ORDER BY revoked_at IS NULL DESC, id DESC',
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	public static function revoke( int $id ): bool {
		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB
			Database_Installer::table_api_keys(),
			[ 'revoked_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
		return false !== $updated;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_api_keys(),
			[ 'id' => $id ],
			[ '%d' ]
		);
		return (bool) $deleted;
	}

	/**
	 * Validate a plain-text token. Returns the matching active record if
	 * the token is valid; null otherwise. Side-effect: bumps last_used_at.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function authenticate( string $token, ?string $client_ip = null ): ?array {
		$token = trim( $token );
		if ( strlen( $token ) <= self::PREFIX_LENGTH ) {
			return null;
		}
		if ( 0 !== strpos( $token, self::KEY_PREFIX ) ) {
			return null;
		}

		$prefix = substr( $token, 0, self::PREFIX_LENGTH );

		global $wpdb;
		$candidates = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				'SELECT * FROM ' . Database_Installer::table_api_keys() . ' WHERE key_prefix = %s AND revoked_at IS NULL',
				$prefix
			),
			ARRAY_A
		);

		if ( ! $candidates ) {
			return null;
		}

		foreach ( $candidates as $row ) {
			if ( ! wp_check_password( $token, $row['key_hash'] ) ) {
				continue;
			}

			// Defense in depth: if the admin that minted this key has
			// since been deleted from WP, treat the key as invalid. A
			// deleted user can't log in anymore, so no credential they
			// created should keep working either. Protects against an
			// offboarded admin's token continuing to drive writes.
			$creator = (int) $row['created_by'];
			if ( $creator <= 0 || ! get_userdata( $creator ) ) {
				return null;
			}

			self::touch( (int) $row['id'], $client_ip );
			return self::hydrate( $row );
		}

		return null;
	}

	private static function touch( int $id, ?string $client_ip ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Database_Installer::table_api_keys(),
			[
				'last_used_at' => current_time( 'mysql', true ),
				'last_used_ip' => $client_ip ? substr( $client_ip, 0, 45 ) : null,
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public static function sanitize_scope( $value ): string {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::ALLOWED_SCOPES, true ) ? $value : self::SCOPE_READ;
	}

	private static function generate_plain_key(): string {
		// 32 random base62 chars ≈ 190 bits of entropy. wp_generate_password
		// without special chars gives us [a-zA-Z0-9].
		return self::KEY_PREFIX . wp_generate_password( self::RANDOM_LENGTH, false, false );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function hydrate( array $row ): array {
		return [
			'id'           => (int) $row['id'],
			'name'         => (string) $row['name'],
			'key_prefix'   => (string) $row['key_prefix'],
			'masked'       => $row['key_prefix'] . str_repeat( '•', 24 ),
			'scope'        => self::sanitize_scope( $row['scope'] ?? '' ),
			'last_used_at' => self::nullable_string( $row['last_used_at'] ?? null ),
			'last_used_ip' => self::nullable_string( $row['last_used_ip'] ?? null ),
			'revoked_at'   => self::nullable_string( $row['revoked_at'] ?? null ),
			'is_active'    => empty( $row['revoked_at'] ),
			'created_by'   => (int) ( $row['created_by'] ?? 0 ),
			'created_at'   => (string) ( $row['created_at'] ?? '' ),
		];
	}

	private static function nullable_string( $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return (string) $value;
	}
}
