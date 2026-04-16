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
			'show_dots'               => true,
			'show_arrows'             => true,
			'show_progress'           => false,
			'pause_on_hover'          => true,
			'border_radius'           => 16,
			'transition'              => 'slide', // slide|fade
			'aspect_ratio_desktop'    => '21/9',
			'aspect_ratio_mobile'     => '4/5',
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
		$out['show_dots']      = ! empty( $input['show_dots'] );
		$out['show_arrows']    = ! empty( $input['show_arrows'] );
		$out['show_progress']  = ! empty( $input['show_progress'] );
		$out['pause_on_hover'] = ! empty( $input['pause_on_hover'] );
		$out['border_radius']  = max( 0, min( 64, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) );
		$out['transition']     = in_array( ( $input['transition'] ?? '' ), [ 'slide', 'fade' ], true ) ? $input['transition'] : 'slide';

		// Aspect ratios accept "W/H" pattern with reasonable bounds; fallback to defaults.
		$out['aspect_ratio_desktop'] = self::sanitize_ratio( $input['aspect_ratio_desktop'] ?? '', $defaults['aspect_ratio_desktop'] );
		$out['aspect_ratio_mobile']  = self::sanitize_ratio( $input['aspect_ratio_mobile'] ?? '', $defaults['aspect_ratio_mobile'] );

		return $out;
	}

	private static function sanitize_ratio( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}
		$value = trim( $value );
		if ( ! preg_match( '#^([1-9]\d{0,2})/([1-9]\d{0,2})$#', $value ) ) {
			return $fallback;
		}
		return $value;
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
	 * Insert or update a campaign. Returns the persisted ID.
	 */
	public static function upsert_campaign( array $input, ?int $id = null ): int {
		global $wpdb;
		$table = Database_Installer::table_campaigns();
		$now   = current_time( 'mysql', true );

		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		if ( '' === $name ) {
			$name = 'Untitled Campaign';
		}

		$slug_raw = isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '';
		$slug     = $slug_raw !== '' ? $slug_raw : $name;
		$slug     = self::generate_unique_slug( $slug, $id );

		$data = [
			'name'       => $name,
			'slug'       => $slug,
			'status'     => self::sanitize_status( $input['status'] ?? self::STATUS_DRAFT ),
			'settings'   => wp_json_encode( self::sanitize_settings( $input['settings'] ?? [] ) ),
			'start_date' => self::sanitize_datetime( $input['start_date'] ?? null ),
			'end_date'   => self::sanitize_datetime( $input['end_date'] ?? null ),
			'updated_at' => $now,
		];

		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		if ( $id ) {
			$wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] ); // phpcs:ignore
			$persisted_id = $id;
		} else {
			$data['created_at'] = $now;
			$formats[]          = '%s';
			$wpdb->insert( $table, $data, $formats ); // phpcs:ignore
			$persisted_id = (int) $wpdb->insert_id;
		}

		self::flush_slug_cache();

		return $persisted_id;
	}

	public static function delete_campaign( int $id ): bool {
		global $wpdb;
		$campaigns = Database_Installer::table_campaigns();
		$banners   = Database_Installer::table_banners();

		$wpdb->delete( $banners, [ 'campaign_id' => $id ], [ '%d' ] ); // phpcs:ignore
		$ok = (bool) $wpdb->delete( $campaigns, [ 'id' => $id ], [ '%d' ] ); // phpcs:ignore

		self::flush_slug_cache();
		return $ok;
	}

	/**
	 * Replace banners for a campaign+device with the supplied ordered list.
	 *
	 * @param array<int,array<string,mixed>> $banners
	 */
	public static function replace_banners( int $campaign_id, string $device, array $banners ): void {
		global $wpdb;
		$device = self::sanitize_device( $device );
		$table  = Database_Installer::table_banners();
		$now    = current_time( 'mysql', true );

		$wpdb->delete( $table, [ 'campaign_id' => $campaign_id, 'device' => $device ], [ '%d', '%s' ] ); // phpcs:ignore

		$order = 0;
		foreach ( $banners as $banner ) {
			$image_id = isset( $banner['image_id'] ) ? (int) $banner['image_id'] : 0;
			if ( $image_id <= 0 ) {
				continue;
			}

			$link_url   = isset( $banner['link_url'] ) ? esc_url_raw( $banner['link_url'] ) : '';
			$alt_text   = isset( $banner['alt_text'] ) ? sanitize_text_field( $banner['alt_text'] ) : '';
			$link_rel   = isset( $banner['link_rel'] ) ? sanitize_text_field( $banner['link_rel'] ) : '';
			$link_targ  = self::sanitize_link_target( $banner['link_target'] ?? '_self' );

			$wpdb->insert( // phpcs:ignore
				$table,
				[
					'campaign_id' => $campaign_id,
					'device'      => $device,
					'image_id'    => $image_id,
					'link_url'    => $link_url,
					'link_target' => $link_targ,
					'link_rel'    => $link_rel,
					'alt_text'    => $alt_text,
					'sort_order'  => $order,
					'created_at'  => $now,
				],
				[ '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
			);
			$order++;
		}
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
			'device'      => self::sanitize_device( $row['device'] ?? '' ),
			'image_id'    => $image_id,
			'image'       => $image,
			'link_url'    => (string) ( $row['link_url'] ?? '' ),
			'link_target' => self::sanitize_link_target( $row['link_target'] ?? '_self' ),
			'link_rel'    => (string) ( $row['link_rel'] ?? '' ),
			'alt_text'    => (string) ( $row['alt_text'] ?? '' ),
			'sort_order'  => (int) ( $row['sort_order'] ?? 0 ),
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
