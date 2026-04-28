<?php
/**
 * Mosaic repository.
 *
 * All DB access for mosaics goes through this class — same pattern as
 * Campaign_Repository. Sanitization lives at the boundary, queries use
 * $wpdb->prepare(), mutators flush the shortcode slug cache.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Mosaic_Repository {

	public const STATUS_DRAFT  = 'draft';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_PAUSED = 'paused';

	private const ALLOWED_STATUSES = [ self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_PAUSED ];

	/**
	 * Layout presets. Each preset computes col_span/row_span per cell
	 * from its position in the grid, freeing the user from having to
	 * do bento math. `custom` is the escape hatch that keeps the old
	 * per-item controls.
	 *
	 * Shape of each cell is resolved in Mosaic_Renderer::resolve_spans().
	 */
	public const LAYOUT_HERO_TOP       = 'hero-top';
	public const LAYOUT_HERO_BOTTOM    = 'hero-bottom';
	public const LAYOUT_FEATURED_LEFT  = 'featured-left';
	public const LAYOUT_FEATURED_RIGHT = 'featured-right';
	public const LAYOUT_ALTERNATING    = 'alternating';
	public const LAYOUT_UNIFORM        = 'uniform';
	// Same 1x1 spans as uniform, plus a CSS-level aspect lock on
	// every cell so cards stay perfectly aligned even when the
	// underlying images have different aspect ratios.
	public const LAYOUT_ALIGNED_ROW    = 'aligned-row';
	public const LAYOUT_CUSTOM         = 'custom';

	private const ALLOWED_LAYOUTS = [
		self::LAYOUT_HERO_TOP,
		self::LAYOUT_HERO_BOTTOM,
		self::LAYOUT_FEATURED_LEFT,
		self::LAYOUT_FEATURED_RIGHT,
		self::LAYOUT_ALTERNATING,
		self::LAYOUT_UNIFORM,
		self::LAYOUT_ALIGNED_ROW,
		self::LAYOUT_CUSTOM,
	];

	/**
	 * Default settings for a brand-new mosaic. Kept intentionally close
	 * in shape to campaign.settings so Image_Optimizer can be reused
	 * without a different call path.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return [
			// Layout presets are independent per breakpoint so a mosaic
			// can read as "3 side-by-side cards" on desktop while still
			// showing a tall hero + 2 cards on mobile (the common case).
			'layout_desktop'          => self::LAYOUT_HERO_TOP,
			'layout_mobile'           => self::LAYOUT_HERO_TOP,
			'cols_desktop'            => 3,
			'cols_mobile'             => 2,
			'gap'                     => 12,
			'border_radius'           => 12,
			// Image optimization (same flags as campaigns — reused by
			// Image_Optimizer::payload_for()).
			'image_optimization'      => true,
			'image_quality'           => 82,
			'image_webp'              => true,
			'image_max_width_desktop' => 1600,
			'image_max_width_mobile'  => 750,
		];
	}

	public static function sanitize_settings( $input ): array {
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : [];
		$out      = [];

		// Backwards compat: mosaics saved before 1.8.2 used a single
		// `layout` key that applied to both breakpoints. Mirror it into
		// both new keys so upgrades don't visually change existing sites
		// until the user picks something different.
		$legacy_layout = isset( $input['layout'] ) ? sanitize_key( (string) $input['layout'] ) : '';
		$legacy_layout = in_array( $legacy_layout, self::ALLOWED_LAYOUTS, true ) ? $legacy_layout : '';

		$layout_d              = isset( $input['layout_desktop'] ) ? sanitize_key( (string) $input['layout_desktop'] ) : $legacy_layout;
		$layout_m              = isset( $input['layout_mobile'] ) ? sanitize_key( (string) $input['layout_mobile'] ) : $legacy_layout;
		$out['layout_desktop'] = in_array( $layout_d, self::ALLOWED_LAYOUTS, true ) ? $layout_d : $defaults['layout_desktop'];
		$out['layout_mobile']  = in_array( $layout_m, self::ALLOWED_LAYOUTS, true ) ? $layout_m : $defaults['layout_mobile'];

		$out['cols_desktop'] = max( 1, min( 6, (int) ( $input['cols_desktop'] ?? $defaults['cols_desktop'] ) ) );
		$out['cols_mobile']  = max( 1, min( 4, (int) ( $input['cols_mobile'] ?? $defaults['cols_mobile'] ) ) );
		$out['gap']          = max( 0, min( 80, (int) ( $input['gap'] ?? $defaults['gap'] ) ) );
		$out['border_radius'] = max( 0, min( 64, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) );

		// Image optimization — same bounds + defaults as Campaign_Repository.
		$out['image_optimization']      = array_key_exists( 'image_optimization', $input )
			? ! empty( $input['image_optimization'] )
			: $defaults['image_optimization'];
		$out['image_webp']              = array_key_exists( 'image_webp', $input )
			? ! empty( $input['image_webp'] )
			: $defaults['image_webp'];
		$out['image_quality']           = max( 40, min( 95, (int) ( $input['image_quality'] ?? $defaults['image_quality'] ) ) );
		$out['image_max_width_desktop'] = max( 400, min( 3840, (int) ( $input['image_max_width_desktop'] ?? $defaults['image_max_width_desktop'] ) ) );
		$out['image_max_width_mobile']  = max( 320, min( 1536, (int) ( $input['image_max_width_mobile'] ?? $defaults['image_max_width_mobile'] ) ) );

		return $out;
	}

	public static function sanitize_status( $value ): string {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, self::ALLOWED_STATUSES, true ) ? $value : self::STATUS_DRAFT;
	}

	/**
	 * Produce a URL-friendly, unique slug. Same logic as Campaign_Repository
	 * but scoped to the mosaics table — a slug can be shared between a
	 * carousel and a mosaic without conflict since the shortcodes use
	 * different prefixes ([carouseldesktop_…] vs [mosaic_…]).
	 */
	public static function generate_unique_slug( string $name, ?int $exclude_id = null ): string {
		$base = sanitize_title( $name );
		if ( '' === $base ) {
			$base = 'mosaic-' . wp_generate_password( 6, false );
		}

		global $wpdb;
		$table = Database_Installer::table_mosaics();
		$slug  = $base;
		$i     = 2;

		while ( true ) {
			$query = $exclude_id
				? $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id <> %d LIMIT 1", $slug, $exclude_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				: $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( ! $exists ) {
				return $slug;
			}
			$slug = $base . '-' . $i;
			$i++;
		}
	}

	/* -------------------------- READ -------------------------- */

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_mosaics( array $args = [] ): array {
		global $wpdb;
		$table = Database_Installer::table_mosaics();

		$defaults = [
			'status'  => null,
			'search'  => '',
			'orderby' => 'updated_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		];
		$args     = wp_parse_args( $args, $defaults );

		$where  = [ '1=1' ];
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = self::sanitize_status( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR slug LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$orderby_allowed = [ 'updated_at', 'created_at', 'name', 'status' ];
		$orderby         = in_array( $args['orderby'], $orderby_allowed, true ) ? $args['orderby'] : 'updated_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$limit           = max( 1, min( 500, (int) $args['limit'] ) );
		$offset          = max( 0, (int) $args['offset'] );

		$sql  = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";
		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) // phpcs:ignore
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

		return array_map( [ self::class, 'hydrate' ], $rows ?: [] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id, bool $with_items = false ): ?array {
		global $wpdb;
		$table = Database_Installer::table_mosaics();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		$mosaic = self::hydrate( $row );
		if ( $with_items ) {
			$mosaic['items'] = Mosaic_Item_Repository::list_for_mosaic( $id );
		}
		return $mosaic;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_by_slug( string $slug, bool $with_items = false ): ?array {
		global $wpdb;
		$table = Database_Installer::table_mosaics();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		$mosaic = self::hydrate( $row );
		if ( $with_items ) {
			$mosaic['items'] = Mosaic_Item_Repository::list_for_mosaic( (int) $mosaic['id'] );
		}
		return $mosaic;
	}

	/**
	 * Slug list, transient-cached for 1 hour. Used by the shortcode handler
	 * to register every mosaic's shortcode on `init` without a DB hit on
	 * each request.
	 *
	 * @return string[]
	 */
	public static function all_slugs(): array {
		$cached = get_transient( USC_TRANSIENT_MOSAIC_SLUGS );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table = Database_Installer::table_mosaics();
		$rows  = $wpdb->get_col( "SELECT slug FROM {$table}" ); // phpcs:ignore
		$slugs = array_values( array_filter( array_map( 'strval', $rows ?: [] ) ) );
		set_transient( USC_TRANSIENT_MOSAIC_SLUGS, $slugs, HOUR_IN_SECONDS );
		return $slugs;
	}

	public static function flush_slug_cache(): void {
		delete_transient( USC_TRANSIENT_MOSAIC_SLUGS );
	}

	/* -------------------------- WRITE -------------------------- */

	public static function create( array $input ): int {
		global $wpdb;
		$table = Database_Installer::table_mosaics();
		$now   = current_time( 'mysql', true );

		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		if ( '' === $name ) {
			$name = 'Untitled mosaic';
		}
		$slug_raw = isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '';
		$slug     = $slug_raw !== '' ? $slug_raw : $name;
		$slug     = self::generate_unique_slug( $slug );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'name'       => $name,
				'slug'       => $slug,
				'status'     => self::sanitize_status( $input['status'] ?? self::STATUS_DRAFT ),
				'settings'   => wp_json_encode( self::sanitize_settings( $input['settings'] ?? [] ) ),
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		self::flush_slug_cache();
		do_action( 'usc_content_changed' );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Partial update — any field not present in $input is left untouched.
	 * Same contract as Campaign_Repository::update_campaign(). Settings
	 * are merged shallowly with the current settings so toggling one
	 * knob doesn't reset the rest.
	 */
	public static function update( int $id, array $input ): bool {
		$current = self::get( $id );
		if ( ! $current ) {
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
		if ( array_key_exists( 'slug', $input ) ) {
			$slug = sanitize_title( (string) $input['slug'] );
			if ( '' !== $slug && $slug !== $current['slug'] ) {
				$data['slug']    = self::generate_unique_slug( $slug, $id );
				$formats['slug'] = '%s';
			}
		}
		if ( array_key_exists( 'status', $input ) ) {
			$data['status']    = self::sanitize_status( $input['status'] );
			$formats['status'] = '%s';
		}
		if ( array_key_exists( 'settings', $input ) && is_array( $input['settings'] ) ) {
			$merged              = array_merge( $current['settings'], $input['settings'] );
			$data['settings']    = wp_json_encode( self::sanitize_settings( $merged ) );
			$formats['settings'] = '%s';
		}

		if ( empty( $data ) ) {
			return true;
		}

		$data['updated_at']    = current_time( 'mysql', true );
		$formats['updated_at'] = '%s';

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Database_Installer::table_mosaics(),
			$data,
			[ 'id' => $id ],
			array_values( $formats ),
			[ '%d' ]
		);

		self::flush_slug_cache();
		do_action( 'usc_content_changed' );
		return true;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_mosaic_items(),
			[ 'mosaic_id' => $id ],
			[ '%d' ]
		);
		$ok = (bool) $wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_mosaics(),
			[ 'id' => $id ],
			[ '%d' ]
		);
		self::flush_slug_cache();
		do_action( 'usc_content_changed' );
		return $ok;
	}

	/* -------------------------- HYDRATE -------------------------- */

	private static function hydrate( array $row ): array {
		$settings = json_decode( $row['settings'] ?? '', true );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$settings = self::sanitize_settings( $settings );
		$slug     = (string) $row['slug'];

		return [
			'id'         => (int) $row['id'],
			'name'       => (string) $row['name'],
			'slug'       => $slug,
			'status'     => self::sanitize_status( $row['status'] ?? '' ),
			'settings'   => $settings,
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'updated_at' => (string) ( $row['updated_at'] ?? '' ),
			'shortcode'  => "[mosaic_{$slug}]",
		];
	}

	public static function is_live( array $mosaic ): bool {
		return self::STATUS_ACTIVE === ( $mosaic['status'] ?? '' );
	}
}
