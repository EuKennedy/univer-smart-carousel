<?php
/**
 * Mosaic item repository.
 *
 * One row per cell in the grid. Handles CRUD, reorder, duplicate.
 * Same partial-update discipline as the banner repository: fields
 * absent from the input are preserved, not wiped.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Mosaic_Item_Repository {

	private const ALLOWED_LINK_TARGETS = [ '_self', '_blank' ];

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_mosaic( int $mosaic_id ): array {
		global $wpdb;
		$table = Database_Installer::table_mosaic_items();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE mosaic_id = %d ORDER BY sort_order ASC, id ASC",
				$mosaic_id
			),
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database_Installer::table_mosaic_items();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Append a single item to a mosaic. sort_order lands at the end
	 * (MAX + 1 scoped to the mosaic). Returns the new id, or null on
	 * invalid input.
	 */
	public static function add( int $mosaic_id, array $input ): ?int {
		$mosaic = Mosaic_Repository::get( $mosaic_id );
		if ( ! $mosaic ) {
			return null;
		}
		$image_id = isset( $input['image_id'] ) ? (int) $input['image_id'] : 0;
		if ( $image_id <= 0 || ! wp_attachment_is_image( $image_id ) ) {
			return null;
		}

		global $wpdb;
		$table = Database_Installer::table_mosaic_items();
		$now   = current_time( 'mysql', true );

		$next_order = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table} WHERE mosaic_id = %d",
				$mosaic_id
			)
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'mosaic_id'    => $mosaic_id,
				'image_id'     => $image_id,
				'name'         => isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : null,
				'link_url'     => isset( $input['link_url'] ) ? esc_url_raw( $input['link_url'] ) : '',
				'link_target'  => self::sanitize_link_target( $input['link_target'] ?? '_self' ),
				'link_rel'     => isset( $input['link_rel'] ) ? sanitize_text_field( $input['link_rel'] ) : '',
				'alt_text'     => isset( $input['alt_text'] ) ? sanitize_text_field( $input['alt_text'] ) : '',
				'col_span'     => self::sanitize_span( $input['col_span'] ?? 1 ),
				'row_span'     => self::sanitize_span( $input['row_span'] ?? 1 ),
				'aspect_ratio' => self::sanitize_aspect( $input['aspect_ratio'] ?? 'auto' ),
				'sort_order'   => $next_order,
				'is_active'    => array_key_exists( 'is_active', $input ) ? ( ! empty( $input['is_active'] ) ? 1 : 0 ) : 1,
				'created_at'   => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Partial update.
	 */
	public static function update( int $id, array $input ): bool {
		$data    = [];
		$formats = [];

		if ( array_key_exists( 'name', $input ) ) {
			$name            = sanitize_text_field( (string) $input['name'] );
			$data['name']    = '' === $name ? null : $name;
			$formats['name'] = '%s';
		}
		if ( array_key_exists( 'image_id', $input ) ) {
			$candidate = (int) $input['image_id'];
			if ( $candidate > 0 && wp_attachment_is_image( $candidate ) ) {
				$data['image_id']    = $candidate;
				$formats['image_id'] = '%d';
			}
		}
		if ( array_key_exists( 'link_url', $input ) ) {
			$data['link_url']    = esc_url_raw( (string) $input['link_url'] );
			$formats['link_url'] = '%s';
		}
		if ( array_key_exists( 'link_target', $input ) ) {
			$data['link_target']    = self::sanitize_link_target( $input['link_target'] );
			$formats['link_target'] = '%s';
		}
		if ( array_key_exists( 'link_rel', $input ) ) {
			$data['link_rel']    = sanitize_text_field( (string) $input['link_rel'] );
			$formats['link_rel'] = '%s';
		}
		if ( array_key_exists( 'alt_text', $input ) ) {
			$data['alt_text']    = sanitize_text_field( (string) $input['alt_text'] );
			$formats['alt_text'] = '%s';
		}
		if ( array_key_exists( 'col_span', $input ) ) {
			$data['col_span']    = self::sanitize_span( $input['col_span'] );
			$formats['col_span'] = '%d';
		}
		if ( array_key_exists( 'row_span', $input ) ) {
			$data['row_span']    = self::sanitize_span( $input['row_span'] );
			$formats['row_span'] = '%d';
		}
		if ( array_key_exists( 'aspect_ratio', $input ) ) {
			$data['aspect_ratio']    = self::sanitize_aspect( $input['aspect_ratio'] );
			$formats['aspect_ratio'] = '%s';
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

		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB
			Database_Installer::table_mosaic_items(),
			$data,
			[ 'id' => $id ],
			array_values( $formats ),
			[ '%d' ]
		);
		return false !== $updated;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_mosaic_items(),
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/**
	 * Reorder all items inside a mosaic in one write.
	 * Scoped by mosaic_id as a safety net — ids that don't belong to
	 * the named mosaic are silently ignored.
	 */
	public static function reorder( int $mosaic_id, array $ids ): void {
		global $wpdb;
		$table = Database_Installer::table_mosaic_items();
		foreach ( array_values( $ids ) as $i => $id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				[ 'sort_order' => $i ],
				[ 'id' => (int) $id, 'mosaic_id' => $mosaic_id ],
				[ '%d' ],
				[ '%d', '%d' ]
			);
		}
	}

	/**
	 * Clone an item into the same mosaic. Name (if present) gets a
	 * " (copy)" suffix so the admin can see both in the list.
	 * Returns the new id, or null if the source doesn't exist.
	 */
	public static function duplicate( int $item_id ): ?int {
		global $wpdb;
		$table  = Database_Installer::table_mosaic_items();
		$source = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $item_id ),
			ARRAY_A
		);
		if ( ! $source ) {
			return null;
		}

		$mosaic_id  = (int) $source['mosaic_id'];
		$next_order = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table} WHERE mosaic_id = %d",
				$mosaic_id
			)
		);
		$name = ! empty( $source['name'] ) ? $source['name'] . ' (copy)' : null;

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'mosaic_id'    => $mosaic_id,
				'image_id'     => (int) $source['image_id'],
				'name'         => $name,
				'link_url'     => (string) $source['link_url'],
				'link_target'  => (string) $source['link_target'],
				'link_rel'     => (string) $source['link_rel'],
				'alt_text'     => (string) $source['alt_text'],
				'col_span'     => (int) $source['col_span'],
				'row_span'     => (int) $source['row_span'],
				'aspect_ratio' => (string) $source['aspect_ratio'],
				'sort_order'   => $next_order,
				'is_active'    => (int) $source['is_active'],
				'created_at'   => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s' ]
		);
		return (int) $wpdb->insert_id;
	}

	/* -------------------------- SANITIZE -------------------------- */

	public static function sanitize_link_target( $value ): string {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::ALLOWED_LINK_TARGETS, true ) ? $value : '_self';
	}

	/**
	 * Bento spans cap at 6 — matches the upper bound of cols_desktop.
	 * 1 is the floor (anything smaller would hide the item).
	 */
	private static function sanitize_span( $value ): int {
		$v = (int) $value;
		return max( 1, min( 6, $v > 0 ? $v : 1 ) );
	}

	/**
	 * Accepts 'auto', 'W/H', 'WxH', 'W:H'. Same grammar as
	 * Campaign_Repository::sanitize_ratio() — letting an admin
	 * paste "1560x1080" straight from their asset's filename.
	 */
	private static function sanitize_aspect( $value ): string {
		if ( ! is_string( $value ) ) {
			return 'auto';
		}
		$value = strtolower( trim( $value ) );
		if ( '' === $value || 'auto' === $value ) {
			return 'auto';
		}
		$value = preg_replace( '/\s*px\b/', '', $value );
		$value = preg_replace( '/\s+/', '', $value );
		if ( preg_match( '#^([1-9]\d{0,3})\s*[/x:]\s*([1-9]\d{0,3})$#', $value, $m ) ) {
			return $m[1] . '/' . $m[2];
		}
		return 'auto';
	}

	/* -------------------------- HYDRATE -------------------------- */

	private static function hydrate( array $row ): array {
		$image_id = (int) $row['image_id'];
		return [
			'id'           => (int) $row['id'],
			'mosaic_id'    => (int) $row['mosaic_id'],
			'image_id'     => $image_id,
			'image'        => $image_id > 0 ? \Univer\SmartCarousel\Database\Campaign_Repository::image_payload( $image_id ) : null,
			'name'         => isset( $row['name'] ) ? (string) $row['name'] : '',
			'link_url'     => (string) ( $row['link_url'] ?? '' ),
			'link_target'  => self::sanitize_link_target( $row['link_target'] ?? '_self' ),
			'link_rel'     => (string) ( $row['link_rel'] ?? '' ),
			'alt_text'     => (string) ( $row['alt_text'] ?? '' ),
			'col_span'     => (int) ( $row['col_span'] ?? 1 ),
			'row_span'     => (int) ( $row['row_span'] ?? 1 ),
			'aspect_ratio' => (string) ( $row['aspect_ratio'] ?? 'auto' ),
			'sort_order'   => (int) ( $row['sort_order'] ?? 0 ),
			'is_active'    => (int) ( $row['is_active'] ?? 1 ) === 1,
			'created_at'   => (string) ( $row['created_at'] ?? '' ),
		];
	}
}
