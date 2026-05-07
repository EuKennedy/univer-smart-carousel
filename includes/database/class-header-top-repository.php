<?php
/**
 * Header Top repository — global vertical-swiper slides.
 *
 * One row per slide. There is exactly one Header Top strip per site
 * (no slugs, no multi-instance), so all queries are unscoped — every
 * read returns the entire ordered slide list.
 *
 * Mutations fire `usc_content_changed` so the global cache purger
 * (Cache_Purger) flushes WP Rocket / LiteSpeed / OPcache / etc.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Header_Top_Repository {

	private const ALLOWED_LINK_TARGETS = [ '_self', '_blank' ];

	/* -------------------------- READ -------------------------- */

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_all(): array {
		global $wpdb;
		$table = Database_Installer::table_header_top_slides();
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB
			"SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC",
			ARRAY_A
		);
		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$table = Database_Installer::table_header_top_slides();
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	public static function has_active(): bool {
		global $wpdb;
		$table = Database_Installer::table_header_top_slides();
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			"SELECT COUNT(*) FROM {$table} WHERE is_active = 1"
		);
		return $count > 0;
	}

	/* -------------------------- WRITE -------------------------- */

	public static function create( array $input ): ?int {
		$image_id = isset( $input['image_id'] ) ? (int) $input['image_id'] : 0;
		if ( $image_id <= 0 || ! wp_attachment_is_image( $image_id ) ) {
			return null;
		}

		global $wpdb;
		$table = Database_Installer::table_header_top_slides();
		$now   = current_time( 'mysql', true );

		$next_order = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			"SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table}"
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'image_id'    => $image_id,
				'link_url'    => isset( $input['link_url'] ) ? esc_url_raw( $input['link_url'] ) : '',
				'link_target' => self::sanitize_link_target( $input['link_target'] ?? '_self' ),
				'link_rel'    => isset( $input['link_rel'] ) ? sanitize_text_field( $input['link_rel'] ) : '',
				'alt_text'    => isset( $input['alt_text'] ) ? sanitize_text_field( $input['alt_text'] ) : '',
				'sort_order'  => $next_order,
				'is_active'   => array_key_exists( 'is_active', $input ) ? ( ! empty( $input['is_active'] ) ? 1 : 0 ) : 1,
				'created_at'  => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
		);

		do_action( 'usc_content_changed' );
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $input ): bool {
		$data    = [];
		$formats = [];

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
			Database_Installer::table_header_top_slides(),
			$data,
			[ 'id' => $id ],
			array_values( $formats ),
			[ '%d' ]
		);
		do_action( 'usc_content_changed' );
		return false !== $updated;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$ok = (bool) $wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_header_top_slides(),
			[ 'id' => $id ],
			[ '%d' ]
		);
		do_action( 'usc_content_changed' );
		return $ok;
	}

	/**
	 * Bulk reorder. $ids is the new order, in order. Slides not in
	 * $ids keep their existing sort_order.
	 */
	public static function reorder( array $ids ): void {
		global $wpdb;
		$table = Database_Installer::table_header_top_slides();
		foreach ( array_values( $ids ) as $i => $id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				[ 'sort_order' => $i ],
				[ 'id' => (int) $id ],
				[ '%d' ],
				[ '%d' ]
			);
		}
		do_action( 'usc_content_changed' );
	}

	public static function duplicate( int $id ): ?int {
		global $wpdb;
		$table  = Database_Installer::table_header_top_slides();
		$source = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		if ( ! $source ) {
			return null;
		}

		$next_order = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			"SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table}"
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'image_id'    => (int) $source['image_id'],
				'link_url'    => (string) $source['link_url'],
				'link_target' => (string) $source['link_target'],
				'link_rel'    => (string) $source['link_rel'],
				'alt_text'    => (string) $source['alt_text'],
				'sort_order'  => $next_order,
				'is_active'   => (int) $source['is_active'],
				'created_at'  => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
		);
		do_action( 'usc_content_changed' );
		return (int) $wpdb->insert_id;
	}

	/* -------------------------- SETTINGS -------------------------- */

	/**
	 * Per-site settings (autoplay delay, transition speed, height).
	 * Stored in wp_options under USC_OPTION_HEADER_TOP_SETTINGS.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return [
			'is_enabled'     => true,
			'autoplay_delay' => 4000,
			'transition_ms'  => 600,
			'height_px'      => 36,
			// Inline background fallback before images load — avoids a
			// flash of white at the top of the page.
			'background'     => '#000000',
		];
	}

	public static function get_settings(): array {
		$stored = get_option( USC_OPTION_HEADER_TOP_SETTINGS, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return self::sanitize_settings( array_merge( self::default_settings(), $stored ) );
	}

	public static function update_settings( array $input ): array {
		$current = self::get_settings();
		$merged  = array_merge( $current, array_intersect_key( $input, $current ) );
		$clean   = self::sanitize_settings( $merged );
		update_option( USC_OPTION_HEADER_TOP_SETTINGS, $clean, true );
		do_action( 'usc_content_changed' );
		return $clean;
	}

	public static function sanitize_settings( array $input ): array {
		$defaults = self::default_settings();
		return [
			'is_enabled'     => array_key_exists( 'is_enabled', $input ) ? ! empty( $input['is_enabled'] ) : $defaults['is_enabled'],
			'autoplay_delay' => max( 1000, min( 30000, (int) ( $input['autoplay_delay'] ?? $defaults['autoplay_delay'] ) ) ),
			'transition_ms'  => max( 100, min( 3000, (int) ( $input['transition_ms'] ?? $defaults['transition_ms'] ) ) ),
			'height_px'      => max( 20, min( 200, (int) ( $input['height_px'] ?? $defaults['height_px'] ) ) ),
			'background'     => self::sanitize_color( $input['background'] ?? $defaults['background'] ),
		];
	}

	private static function sanitize_color( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $value ) ) {
			return $value;
		}
		return '#000000';
	}

	/* -------------------------- HELPERS -------------------------- */

	public static function sanitize_link_target( $value ): string {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::ALLOWED_LINK_TARGETS, true ) ? $value : '_self';
	}

	private static function hydrate( array $row ): array {
		$image_id = (int) $row['image_id'];
		$image    = self::image_payload( $image_id );

		return [
			'id'          => (int) $row['id'],
			'image_id'    => $image_id,
			'image'       => $image,
			'link_url'    => (string) ( $row['link_url'] ?? '' ),
			'link_target' => self::sanitize_link_target( $row['link_target'] ?? '_self' ),
			'link_rel'    => (string) ( $row['link_rel'] ?? '' ),
			'alt_text'    => (string) ( $row['alt_text'] ?? '' ),
			'sort_order'  => (int) ( $row['sort_order'] ?? 0 ),
			'is_active'   => (int) ( $row['is_active'] ?? 1 ) === 1,
			'created_at'  => (string) ( $row['created_at'] ?? '' ),
		];
	}

	private static function image_payload( int $image_id ): ?array {
		if ( $image_id <= 0 ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $src ) {
			return null;
		}
		[ $url, $w, $h ] = $src;
		$alt             = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return [
			'id'     => $image_id,
			'url'    => $url,
			'width'  => (int) $w,
			'height' => (int) $h,
			'alt'    => $alt,
		];
	}
}
