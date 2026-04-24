<?php
/**
 * Banner group repository.
 *
 * A group sits between a campaign and a set of banners. Groups are
 * scoped per device — `(campaign_id, device)` is the natural key for
 * "what groups belong to this tab in the editor".
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Banner_Group_Repository {

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_campaign( int $campaign_id, ?string $device = null ): array {
		global $wpdb;
		$table = Database_Installer::table_banner_groups();

		if ( $device ) {
			$device = Campaign_Repository::sanitize_device( $device );
			$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE campaign_id = %d AND device = %s ORDER BY sort_order ASC, id ASC",
					$campaign_id,
					$device
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY device ASC, sort_order ASC, id ASC",
					$campaign_id
				),
				ARRAY_A
			);
		}

		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database_Installer::table_banner_groups();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	public static function create( int $campaign_id, string $device, string $name, ?int $sort_order = null ): ?int {
		global $wpdb;
		$table = Database_Installer::table_banner_groups();
		$now   = current_time( 'mysql', true );

		$device = Campaign_Repository::sanitize_device( $device );
		$name   = sanitize_text_field( $name );
		if ( '' === $name ) {
			$name = 'Group';
		}

		if ( null === $sort_order ) {
			$sort_order = self::next_sort_order( $campaign_id, $device );
		}

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'campaign_id' => $campaign_id,
				'device'      => $device,
				'name'        => $name,
				'is_active'   => 1,
				'sort_order'  => (int) $sort_order,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%d', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		if ( $ok ) {
			do_action( 'usc_content_changed' );
			return (int) $wpdb->insert_id;
		}
		return null;
	}

	/**
	 * Partial update — only fields present in $input are touched.
	 */
	public static function update( int $id, array $input ): bool {
		$existing = self::get( $id );
		if ( ! $existing ) {
			return false;
		}

		$data    = [];
		$formats = [];

		if ( array_key_exists( 'name', $input ) ) {
			$name = sanitize_text_field( (string) $input['name'] );
			if ( '' !== $name ) {
				$data['name']    = $name;
				$formats['name'] = '%s';
			}
		}
		if ( array_key_exists( 'is_active', $input ) ) {
			$data['is_active']    = ! empty( $input['is_active'] ) ? 1 : 0;
			$formats['is_active'] = '%d';
		}
		if ( array_key_exists( 'sort_order', $input ) ) {
			$data['sort_order']    = (int) $input['sort_order'];
			$formats['sort_order'] = '%d';
		}

		if ( empty( $data ) ) {
			return true;
		}

		$data['updated_at']    = current_time( 'mysql', true );
		$formats['updated_at'] = '%s';

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Database_Installer::table_banner_groups(),
			$data,
			[ 'id' => $id ],
			array_values( $formats ),
			[ '%d' ]
		);
		do_action( 'usc_content_changed' );
		return true;
	}

	/**
	 * Delete a group AND all of its banners (cascade).
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_banners(),
			[ 'group_id' => $id ],
			[ '%d' ]
		);
		$ok = $wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_banner_groups(),
			[ 'id' => $id ],
			[ '%d' ]
		);
		do_action( 'usc_content_changed' );
		return (bool) $ok;
	}

	/**
	 * Reorder groups within a (campaign, device). Order = order of $ids.
	 */
	public static function reorder( int $campaign_id, string $device, array $ids ): void {
		$device = Campaign_Repository::sanitize_device( $device );
		global $wpdb;
		$table = Database_Installer::table_banner_groups();
		foreach ( array_values( $ids ) as $i => $id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				[ 'sort_order' => $i, 'updated_at' => current_time( 'mysql', true ) ],
				[ 'id' => (int) $id, 'campaign_id' => $campaign_id, 'device' => $device ],
				[ '%d', '%s' ],
				[ '%d', '%d', '%s' ]
			);
		}
		do_action( 'usc_content_changed' );
	}

	private static function next_sort_order( int $campaign_id, string $device ): int {
		global $wpdb;
		$table = Database_Installer::table_banner_groups();
		$max   = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$table} WHERE campaign_id = %d AND device = %s",
				$campaign_id,
				$device
			)
		);
		return null === $max ? 0 : ( (int) $max + 1 );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function hydrate( array $row ): array {
		return [
			'id'          => (int) $row['id'],
			'campaign_id' => (int) $row['campaign_id'],
			'device'      => Campaign_Repository::sanitize_device( $row['device'] ?? '' ),
			'name'        => (string) $row['name'],
			'is_active'   => (int) $row['is_active'] === 1,
			'sort_order'  => (int) $row['sort_order'],
			'created_at'  => (string) ( $row['created_at'] ?? '' ),
			'updated_at'  => (string) ( $row['updated_at'] ?? '' ),
		];
	}
}
