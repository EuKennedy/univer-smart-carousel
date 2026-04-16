<?php
/**
 * Repository for campaigns + banners.
 *
 * All DB access for these two entities flows through this class.
 * Handles sanitization at the boundary, returns clean associative arrays.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Campaign_Repository {

	public const STATUS_DRAFT  = 'draft';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_PAUSED = 'paused';

	public const DEVICE_DESKTOP = 'desktop';
	public const DEVICE_MOBILE  = 'mobile';

	private const ALLOWED_STATUSES        = [ self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_PAUSED ];
	private const ALLOWED_DEVICES         = [ self::DEVICE_DESKTOP, self::DEVICE_MOBILE ];
	private const ALLOWED_LINK_TARGETS    = [ '_self', '_blank' ];
	private const ALLOWED_SLIDES_PER_VIEW = [ 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5 ];

	/**
	 * Default carousel settings. Stored as JSON in `settings` column.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings(): array {
		return [
			'slides_per_view_desktop' => 1,
			'slides_per_view_mobile'  => 1,
			'gap'                     => 16,
			'autoplay'                => true,
			'autoplay_delay'          => 5000,
			'loop'                    => true,
			'navigation'              => 'arrows', // none|dots|arrows
			'show_progress'           => false,
			'pause_on_hover'          => true,
			// 0 = sharp corners (the right default for full-bleed hero
			// banners). Bump it up only when the carousel sits inside a
			// padded card layout where rounded corners read better.
			'border_radius'           => 0,
			'transition'              => 'slide', // slide|fade
			// Default 'auto' = the image's intrinsic ratio dictates the slide
			// height. No cropping, no whitespace. Switch to a fixed ratio
			// only when running multi-slide product carousels where every
			// card needs the same height regardless of source dimensions.
			'aspect_ratio_desktop'    => 'auto',
			'aspect_ratio_mobile'     => 'auto',
		];
	}

	/* ---------------------------------------------------------------------
	 * SANITIZATION
	 * ------------------------------------------------------------------ */

	public static function sanitize_settings( $input ): array {
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : [];
		$out      = [];

		$desktop_raw = isset( $input['slides_per_view_desktop'] ) ? (float) $input['slides_per_view_desktop'] : $defaults['slides_per_view_desktop'];
		$mobile_raw  = isset( $input['slides_per_view_mobile'] ) ? (float) $input['slides_per_view_mobile'] : $defaults['slides_per_view_mobile'];

		$out['slides_per_view_desktop'] = in_array( $desktop_raw, self::ALLOWED_SLIDES_PER_VIEW, true ) ? $desktop_raw : $defaults['slides_per_view_desktop'];
		$out['slides_per_view_mobile']  = in_array( $mobile_raw, self::ALLOWED_SLIDES_PER_VIEW, true ) ? $mobile_raw : $defaults['slides_per_view_mobile'];

		$out['gap']            = max( 0, min( 80, (int) ( $input['gap'] ?? $defaults['gap'] ) ) );
		$out['autoplay']       = ! empty( $input['autoplay'] );
		$out['autoplay_delay'] = max( 1000, min( 30000, (int) ( $input['autoplay_delay'] ?? $defaults['autoplay_delay'] ) ) );
		$out['loop']           = ! empty( $input['loop'] );

		// Navigation: single source of truth replacing the old show_arrows /
		// show_dots pair. Legacy campaigns (saved before navigation existed)
		// migrate to 'none' so the user can opt back into a style on purpose.
		$out['navigation']    = self::sanitize_navigation( $input );
		$out['show_progress'] = ! empty( $input['show_progress'] );
		$out['pause_on_hover'] = ! empty( $input['pause_on_hover'] );
		$out['border_radius']  = max( 0, min( 64, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) );
		$out['transition']     = in_array( ( $input['transition'] ?? '' ), [ 'slide', 'fade' ], true ) ? $input['transition'] : 'slide';

		// Aspect ratios accept "W/H" pattern with reasonable bounds; fallback to defaults.
		$out['aspect_ratio_desktop'] = self::sanitize_ratio( $input['aspect_ratio_desktop'] ?? '', $defaults['aspect_ratio_desktop'] );
		$out['aspect_ratio_mobile']  = self::sanitize_ratio( $input['aspect_ratio_mobile'] ?? '', $defaults['aspect_ratio_mobile'] );

		return $out;
	}

	private const ALLOWED_NAVIGATION = [ 'none', 'dots', 'arrows' ];

	private static function sanitize_navigation( array $input ): string {
		if ( array_key_exists( 'navigation', $input ) ) {
			$nav = is_string( $input['navigation'] ) ? strtolower( trim( $input['navigation'] ) ) : '';
			if ( in_array( $nav, self::ALLOWED_NAVIGATION, true ) ) {
				return $nav;
			}
		}

		// No `navigation` key. If the legacy show_arrows/show_dots fields
		// are present (campaign was saved before this field existed), the
		// product call is to migrate to 'none' so the user re-picks
		// intentionally. If neither is present, this is a fresh sanitize
		// for a brand-new campaign — fall through to the default.
		if ( array_key_exists( 'show_arrows', $input ) || array_key_exists( 'show_dots', $input ) ) {
			return 'none';
		}

		return 'arrows';
	}

	/**
	 * Accepts:
	 *   - "auto"           → auto (image's intrinsic ratio)
	 *   - "16/9", "21/9"   → preset-style ratio
	 *   - "1560x1080"      → "WxH" pixel dimensions, normalized to "1560/1080"
	 *   - "16:9"           → colon-separated, normalized to "16/9"
	 *
	 * Width and height each capped at 9999 to keep the resulting CSS
	 * `aspect-ratio` value sane and avoid pathological inputs.
	 */
	private static function sanitize_ratio( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}
		$value = strtolower( trim( $value ) );
		if ( '' === $value ) {
			return $fallback;
		}
		if ( 'auto' === $value ) {
			return 'auto';
		}

		// Strip a trailing "px" if the user pasted dimensions like "1920px x 650px".
		$value = preg_replace( '/\s*px\b/', '', $value );
		$value = preg_replace( '/\s+/', '', $value );

		if ( preg_match( '#^([1-9]\d{0,3})\s*[/x:]\s*([1-9]\d{0,3})$#', $value, $m ) ) {
			return $m[1] . '/' . $m[2];
		}

		return $fallback;
	}

	public static function sanitize_status( $value ): string {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, self::ALLOWED_STATUSES, true ) ? $value : self::STATUS_DRAFT;
	}

	public static function sanitize_device( $value ): string {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, self::ALLOWED_DEVICES, true ) ? $value : self::DEVICE_DESKTOP;
	}

	public static function sanitize_link_target( $value ): string {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::ALLOWED_LINK_TARGETS, true ) ? $value : '_self';
	}

	/**
	 * Generates a URL-friendly slug, ensuring uniqueness in the table.
	 */
	public static function generate_unique_slug( string $name, ?int $exclude_id = null ): string {
		$base = sanitize_title( $name );
		if ( '' === $base ) {
			$base = 'campaign-' . wp_generate_password( 6, false );
		}

		global $wpdb;
		$table = Database_Installer::table_campaigns();
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

	/* ---------------------------------------------------------------------
	 * READ
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_campaigns( array $args = [] ): array {
		global $wpdb;
		$table = Database_Installer::table_campaigns();

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

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) // phpcs:ignore
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore

		return array_map( [ self::class, 'hydrate_campaign_row' ], $rows ?: [] );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_campaign( int $id, bool $with_banners = false ): ?array {
		global $wpdb;
		$table = Database_Installer::table_campaigns();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore

		if ( ! $row ) {
			return null;
		}

		$campaign = self::hydrate_campaign_row( $row );

		if ( $with_banners ) {
			$campaign['banners'] = self::list_banners( $id );
			$campaign['groups']  = Banner_Group_Repository::list_for_campaign( $id );
		}

		return $campaign;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_campaign_by_slug( string $slug, bool $with_banners = false ): ?array {
		global $wpdb;
		$table = Database_Installer::table_campaigns();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return null;
		}
		$campaign = self::hydrate_campaign_row( $row );
		if ( $with_banners ) {
			$campaign['banners'] = self::list_banners( (int) $campaign['id'] );
			$campaign['groups']  = Banner_Group_Repository::list_for_campaign( (int) $campaign['id'] );
		}
		return $campaign;
	}

	/**
	 * Returns all campaign slugs that should be addressable via shortcode.
	 * Cached in a transient for fast `init` registration.
	 *
	 * @return string[]
	 */
	public static function all_slugs(): array {
		$cached = get_transient( USC_TRANSIENT_SLUGS );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = Database_Installer::table_campaigns();
		$rows  = $wpdb->get_col( "SELECT slug FROM {$table}" ); // phpcs:ignore
		$slugs = array_values( array_filter( array_map( 'strval', $rows ?: [] ) ) );

		set_transient( USC_TRANSIENT_SLUGS, $slugs, HOUR_IN_SECONDS );
		return $slugs;
	}

	public static function flush_slug_cache(): void {
		delete_transient( USC_TRANSIENT_SLUGS );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_banners( int $campaign_id, ?string $device = null ): array {
		global $wpdb;
		$table = Database_Installer::table_banners();

		if ( $device ) {
			$device = self::sanitize_device( $device );
			$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE campaign_id = %d AND device = %s ORDER BY sort_order ASC, id ASC", $campaign_id, $device ), ARRAY_A ); // phpcs:ignore
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY device ASC, sort_order ASC, id ASC", $campaign_id ), ARRAY_A ); // phpcs:ignore
		}

		return array_map( [ self::class, 'hydrate_banner_row' ], $rows ?: [] );
	}

	/* ---------------------------------------------------------------------
	 * WRITE
	 * ------------------------------------------------------------------ */

	/**
	 * Create a new campaign. Missing fields fall back to safe defaults.
	 *
	 * @return int The new campaign id (0 on failure).
	 */
	public static function create_campaign( array $input ): int {
		global $wpdb;
		$table = Database_Installer::table_campaigns();
		$now   = current_time( 'mysql', true );

		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		if ( '' === $name ) {
			$name = 'Untitled Campaign';
		}

		$slug_raw = isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '';
		$slug     = $slug_raw !== '' ? $slug_raw : $name;
		$slug     = self::generate_unique_slug( $slug );

		$data = [
			'name'       => $name,
			'slug'       => $slug,
			'status'     => self::sanitize_status( $input['status'] ?? self::STATUS_DRAFT ),
			'settings'   => wp_json_encode( self::sanitize_settings( $input['settings'] ?? [] ) ),
			'start_date' => self::sanitize_datetime( $input['start_date'] ?? null ),
			'end_date'   => self::sanitize_datetime( $input['end_date'] ?? null ),
			'created_at' => $now,
			'updated_at' => $now,
		];

		$wpdb->insert( $table, $data, [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] ); // phpcs:ignore

		self::flush_slug_cache();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Partial update: only fields present in $input are touched. Anything
	 * else stays as it is — including name, slug, banners, scheduling.
	 *
	 * Settings are merged shallowly with the current settings so a PUT with
	 * just one toggle doesn't reset the rest.
	 *
	 * @return bool true on success, false if the campaign doesn't exist.
	 */
	public static function update_campaign( int $id, array $input ): bool {
		$current = self::get_campaign( $id );
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
			$merged             = array_merge( $current['settings'], $input['settings'] );
			$data['settings']   = wp_json_encode( self::sanitize_settings( $merged ) );
			$formats['settings'] = '%s';
		}

		if ( array_key_exists( 'start_date', $input ) ) {
			$data['start_date']    = self::sanitize_datetime( $input['start_date'] );
			$formats['start_date'] = '%s';
		}

		if ( array_key_exists( 'end_date', $input ) ) {
			$data['end_date']    = self::sanitize_datetime( $input['end_date'] );
			$formats['end_date'] = '%s';
		}

		if ( empty( $data ) ) {
			return true; // Nothing to update is still a success.
		}

		$data['updated_at']    = current_time( 'mysql', true );
		$formats['updated_at'] = '%s';

		global $wpdb;
		$table = Database_Installer::table_campaigns();
		$wpdb->update( $table, $data, [ 'id' => $id ], array_values( $formats ), [ '%d' ] ); // phpcs:ignore

		self::flush_slug_cache();

		return true;
	}

	public static function delete_campaign( int $id ): bool {
		global $wpdb;
		$campaigns = Database_Installer::table_campaigns();
		$banners   = Database_Installer::table_banners();
		$groups    = Database_Installer::table_banner_groups();

		$wpdb->delete( $banners, [ 'campaign_id' => $id ], [ '%d' ] ); // phpcs:ignore
		$wpdb->delete( $groups, [ 'campaign_id' => $id ], [ '%d' ] ); // phpcs:ignore
		$ok = (bool) $wpdb->delete( $campaigns, [ 'id' => $id ], [ '%d' ] ); // phpcs:ignore

		self::flush_slug_cache();
		return $ok;
	}

	/**
	 * Replace banners for a campaign+device with the supplied ordered list.
	 *
	 * Banners can carry a `group_id` to land in a specific group; if absent
	 * (legacy callers), they get bucketed into the campaign+device's first
	 * group, creating a "Banners" group on the fly if none exists.
	 *
	 * @param array<int,array<string,mixed>> $banners
	 */
	public static function replace_banners( int $campaign_id, string $device, array $banners ): void {
		global $wpdb;
		$device = self::sanitize_device( $device );
		$table  = Database_Installer::table_banners();
		$now    = current_time( 'mysql', true );

		$wpdb->delete( $table, [ 'campaign_id' => $campaign_id, 'device' => $device ], [ '%d', '%s' ] ); // phpcs:ignore

		$default_group_id = null;
		$order            = 0;
		foreach ( $banners as $banner ) {
			$image_id = isset( $banner['image_id'] ) ? (int) $banner['image_id'] : 0;
			if ( $image_id <= 0 ) {
				continue;
			}

			$link_url  = isset( $banner['link_url'] ) ? esc_url_raw( $banner['link_url'] ) : '';
			$alt_text  = isset( $banner['alt_text'] ) ? sanitize_text_field( $banner['alt_text'] ) : '';
			$link_rel  = isset( $banner['link_rel'] ) ? sanitize_text_field( $banner['link_rel'] ) : '';
			$link_targ = self::sanitize_link_target( $banner['link_target'] ?? '_self' );
			$is_active = array_key_exists( 'is_active', $banner ) ? ( ! empty( $banner['is_active'] ) ? 1 : 0 ) : 1;

			$group_id = isset( $banner['group_id'] ) && (int) $banner['group_id'] > 0 ? (int) $banner['group_id'] : null;
			if ( ! $group_id ) {
				if ( null === $default_group_id ) {
					$default_group_id = self::ensure_default_group( $campaign_id, $device );
				}
				$group_id = $default_group_id;
			}

			$wpdb->insert( // phpcs:ignore
				$table,
				[
					'campaign_id' => $campaign_id,
					'group_id'    => $group_id,
					'device'      => $device,
					'image_id'    => $image_id,
					'link_url'    => $link_url,
					'link_target' => $link_targ,
					'link_rel'    => $link_rel,
					'alt_text'    => $alt_text,
					'sort_order'  => $order,
					'is_active'   => $is_active,
					'created_at'  => $now,
				],
				[ '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
			);
			$order++;
		}
	}

	/**
	 * Add ONE banner to an existing group. Returns the new banner id.
	 */
	public static function add_banner_to_group( int $group_id, array $input ): ?int {
		$group = Banner_Group_Repository::get( $group_id );
		if ( ! $group ) {
			return null;
		}
		$image_id = isset( $input['image_id'] ) ? (int) $input['image_id'] : 0;
		if ( $image_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$table = Database_Installer::table_banners();
		$now   = current_time( 'mysql', true );

		$next_order = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$table} WHERE group_id = %d",
				$group_id
			)
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'campaign_id' => (int) $group['campaign_id'],
				'group_id'    => $group_id,
				'device'      => (string) $group['device'],
				'image_id'    => $image_id,
				'link_url'    => isset( $input['link_url'] ) ? esc_url_raw( $input['link_url'] ) : '',
				'link_target' => self::sanitize_link_target( $input['link_target'] ?? '_self' ),
				'link_rel'    => isset( $input['link_rel'] ) ? sanitize_text_field( $input['link_rel'] ) : '',
				'alt_text'    => isset( $input['alt_text'] ) ? sanitize_text_field( $input['alt_text'] ) : '',
				'sort_order'  => $next_order,
				'is_active'   => array_key_exists( 'is_active', $input ) ? ( ! empty( $input['is_active'] ) ? 1 : 0 ) : 1,
				'created_at'  => $now,
			],
			[ '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Partial update of a single banner row. Only fields present in
	 * $input are touched.
	 */
	public static function update_banner( int $banner_id, array $input ): bool {
		$data    = [];
		$formats = [];

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
		if ( array_key_exists( 'group_id', $input ) ) {
			$data['group_id']    = (int) $input['group_id'];
			$formats['group_id'] = '%d';
		}

		if ( empty( $data ) ) {
			return true;
		}

		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB
			Database_Installer::table_banners(),
			$data,
			[ 'id' => $banner_id ],
			array_values( $formats ),
			[ '%d' ]
		);
		return false !== $updated;
	}

	public static function delete_banner( int $banner_id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB
			Database_Installer::table_banners(),
			[ 'id' => $banner_id ],
			[ '%d' ]
		);
	}

	/**
	 * Find or create the catch-all "Banners" group for a (campaign, device).
	 */
	private static function ensure_default_group( int $campaign_id, string $device ): int {
		$existing = Banner_Group_Repository::list_for_campaign( $campaign_id, $device );
		if ( ! empty( $existing ) ) {
			return (int) $existing[0]['id'];
		}
		$id = Banner_Group_Repository::create( $campaign_id, $device, 'Banners' );
		return (int) $id;
	}

	/* ---------------------------------------------------------------------
	 * HYDRATION (DB row -> clean array)
	 * ------------------------------------------------------------------ */

	private static function hydrate_campaign_row( array $row ): array {
		$settings = json_decode( $row['settings'] ?? '', true );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$settings = self::sanitize_settings( $settings );

		$id   = (int) $row['id'];
		$slug = (string) $row['slug'];

		return [
			'id'                 => $id,
			'name'               => (string) $row['name'],
			'slug'               => $slug,
			'status'             => self::sanitize_status( $row['status'] ?? '' ),
			'settings'           => $settings,
			'start_date'         => self::nullable_string( $row['start_date'] ?? null ),
			'end_date'           => self::nullable_string( $row['end_date'] ?? null ),
			'created_at'         => (string) ( $row['created_at'] ?? '' ),
			'updated_at'         => (string) ( $row['updated_at'] ?? '' ),
			'shortcode_desktop'  => "[carouseldesktop_{$slug}]",
			'shortcode_mobile'   => "[carouselmobile_{$slug}]",
		];
	}

	private static function hydrate_banner_row( array $row ): array {
		$image_id = (int) $row['image_id'];
		$image    = self::image_payload( $image_id );

		return [
			'id'          => (int) $row['id'],
			'campaign_id' => (int) $row['campaign_id'],
			'group_id'    => isset( $row['group_id'] ) ? (int) $row['group_id'] : 0,
			'device'      => self::sanitize_device( $row['device'] ?? '' ),
			'image_id'    => $image_id,
			'image'       => $image,
			'link_url'    => (string) ( $row['link_url'] ?? '' ),
			'link_target' => self::sanitize_link_target( $row['link_target'] ?? '_self' ),
			'link_rel'    => (string) ( $row['link_rel'] ?? '' ),
			'alt_text'    => (string) ( $row['alt_text'] ?? '' ),
			'sort_order'  => (int) ( $row['sort_order'] ?? 0 ),
			'is_active'   => array_key_exists( 'is_active', $row ) ? ( (int) $row['is_active'] === 1 ) : true,
			'created_at'  => (string) ( $row['created_at'] ?? '' ),
		];
	}

	/**
	 * Build a frontend-friendly image payload from the WP attachment ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function image_payload( int $image_id ): ?array {
		if ( $image_id <= 0 ) {
			return null;
		}
		$full = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $full ) {
			return null;
		}
		$srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
		$sizes  = wp_get_attachment_image_sizes( $image_id, 'full' );
		$alt    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return [
			'id'     => $image_id,
			'url'    => $full[0],
			'width'  => (int) $full[1],
			'height' => (int) $full[2],
			'srcset' => $srcset ?: '',
			'sizes'  => $sizes ?: '',
			'alt'    => is_string( $alt ) ? $alt : '',
		];
	}

	private static function nullable_string( $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$value = (string) $value;
		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}
		return $value;
	}

	private static function sanitize_datetime( $value ): ?string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Decide if a campaign is currently live (status active + within date window).
	 */
	public static function is_live( array $campaign ): bool {
		if ( self::STATUS_ACTIVE !== ( $campaign['status'] ?? '' ) ) {
			return false;
		}
		$now = time();
		if ( ! empty( $campaign['start_date'] ) && strtotime( $campaign['start_date'] . ' UTC' ) > $now ) {
			return false;
		}
		if ( ! empty( $campaign['end_date'] ) && strtotime( $campaign['end_date'] . ' UTC' ) < $now ) {
			return false;
		}
		return true;
	}
}
