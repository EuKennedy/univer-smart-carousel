<?php
/**
 * Database installer / migrator.
 *
 * Tables:
 *   wp_usc_campaigns - campaign metadata + carousel settings.
 *   wp_usc_banners   - per-campaign banners (desktop or mobile), with link + sort order.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Database_Installer {

	private const VERSION_OPTION = 'usc_db_version';

	/**
	 * Returns prefixed table name for campaigns.
	 */
	public static function table_campaigns(): string {
		global $wpdb;
		return $wpdb->prefix . USC_TABLE_CAMPAIGNS;
	}

	/**
	 * Returns prefixed table name for banners.
	 */
	public static function table_banners(): string {
		global $wpdb;
		return $wpdb->prefix . USC_TABLE_BANNERS;
	}

	/**
	 * Returns prefixed table name for API keys.
	 */
	public static function table_api_keys(): string {
		global $wpdb;
		return $wpdb->prefix . USC_TABLE_API_KEYS;
	}

	/**
	 * Returns prefixed table name for banner groups.
	 */
	public static function table_banner_groups(): string {
		global $wpdb;
		return $wpdb->prefix . USC_TABLE_BANNER_GROUPS;
	}

	/**
	 * Run on activation. Creates tables if missing, stores version.
	 */
	public static function install(): void {
		self::create_tables();
		update_option( self::VERSION_OPTION, USC_DB_VERSION, false );
	}

	/**
	 * Run on deactivation. We intentionally DO NOT drop tables to preserve data.
	 */
	public static function deactivate(): void {
		// Clear caches; data stays.
		delete_transient( USC_TRANSIENT_SLUGS );
	}

	/**
	 * Compare stored version vs current version and re-run dbDelta if needed.
	 * Called cheaply on `init`.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::VERSION_OPTION );
		if ( $installed === USC_DB_VERSION ) {
			return;
		}
		self::create_tables();
		self::run_pending_migrations( $installed );
		update_option( self::VERSION_OPTION, USC_DB_VERSION, false );
	}

	/**
	 * Schema migrations that dbDelta can't express on its own — adding
	 * columns to existing tables and back-filling data. Each migration
	 * should be safe to run multiple times (idempotent).
	 *
	 * @param string|false $previous_version The version stored before this upgrade run.
	 */
	private static function run_pending_migrations( $previous_version ): void {
		global $wpdb;
		$banners = self::table_banners();

		// 1.2.0 — Banner groups feature. Banners pick up group_id +
		// is_active columns; existing rows get bucketed into a default
		// group (one per campaign+device pair) so nothing disappears.
		if ( ! self::column_exists( $banners, 'group_id' ) ) {
			$wpdb->query( "ALTER TABLE {$banners} ADD COLUMN group_id BIGINT UNSIGNED NULL AFTER campaign_id" ); // phpcs:ignore WordPress.DB
			$wpdb->query( "ALTER TABLE {$banners} ADD INDEX group_id (group_id)" ); // phpcs:ignore WordPress.DB
		}
		if ( ! self::column_exists( $banners, 'is_active' ) ) {
			$wpdb->query( "ALTER TABLE {$banners} ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order" ); // phpcs:ignore WordPress.DB
		}

		// 1.3.0 — Optional per-banner label, so admins can tag a banner
		// with an internal name ("Promo Black Friday 50off") without
		// touching alt_text (which is user-facing / SEO).
		if ( ! self::column_exists( $banners, 'name' ) ) {
			$wpdb->query( "ALTER TABLE {$banners} ADD COLUMN name VARCHAR(191) NULL DEFAULT NULL AFTER image_id" ); // phpcs:ignore WordPress.DB
		}

		// Backfill: any banner that still has NULL group_id needs a group.
		// Group banners by (campaign_id, device) — those become "Banners"
		// groups that the user can later rename / split.
		$orphans = $wpdb->get_results( // phpcs:ignore WordPress.DB
			"SELECT DISTINCT campaign_id, device FROM {$banners} WHERE group_id IS NULL"
		);

		if ( $orphans ) {
			$groups_table = self::table_banner_groups();
			$now          = current_time( 'mysql', true );

			foreach ( $orphans as $pair ) {
				$campaign_id = (int) $pair->campaign_id;
				$device      = (string) $pair->device;

				$wpdb->insert( // phpcs:ignore WordPress.DB
					$groups_table,
					[
						'campaign_id' => $campaign_id,
						'device'      => $device,
						'name'        => 'Banners',
						'is_active'   => 1,
						'sort_order'  => 0,
						'created_at'  => $now,
						'updated_at'  => $now,
					],
					[ '%d', '%s', '%s', '%d', '%d', '%s', '%s' ]
				);
				$group_id = (int) $wpdb->insert_id;

				$wpdb->query( // phpcs:ignore WordPress.DB
					$wpdb->prepare(
						"UPDATE {$banners} SET group_id = %d WHERE campaign_id = %d AND device = %s AND group_id IS NULL",
						$group_id,
						$campaign_id,
						$device
					)
				);
			}
		}
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;
		$result = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				$table,
				$column
			)
		);
		return (int) $result > 0;
	}

	/**
	 * dbDelta is extremely picky about formatting. Do NOT reformat the SQL below.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$campaigns       = self::table_campaigns();
		$banners         = self::table_banners();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_campaigns = "CREATE TABLE {$campaigns} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			settings LONGTEXT NULL,
			start_date DATETIME NULL DEFAULT NULL,
			end_date DATETIME NULL DEFAULT NULL,
			created_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY date_window (start_date, end_date)
		) {$charset_collate};";

		$sql_banners = "CREATE TABLE {$banners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			group_id BIGINT UNSIGNED NULL,
			device VARCHAR(10) NOT NULL,
			image_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(191) NULL DEFAULT NULL,
			link_url VARCHAR(2048) NULL,
			link_target VARCHAR(10) NOT NULL DEFAULT '_self',
			link_rel VARCHAR(64) NULL,
			alt_text VARCHAR(255) NULL,
			sort_order INT NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY campaign_device (campaign_id, device, sort_order),
			KEY group_id (group_id),
			KEY image_id (image_id)
		) {$charset_collate};";

		$api_keys = self::table_api_keys();

		// API keys table. We store the bcrypt-hashed full token alongside a
		// short prefix that lets us narrow lookups by index before doing the
		// (expensive) hash comparison. The full plain-text key is shown to
		// the admin once on creation and never persisted.
		$sql_api_keys = "CREATE TABLE {$api_keys} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			key_prefix VARCHAR(20) NOT NULL,
			key_hash VARCHAR(255) NOT NULL,
			scope VARCHAR(20) NOT NULL DEFAULT 'read',
			last_used_at DATETIME NULL DEFAULT NULL,
			last_used_ip VARCHAR(45) NULL DEFAULT NULL,
			revoked_at DATETIME NULL DEFAULT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY key_prefix (key_prefix),
			KEY active (revoked_at)
		) {$charset_collate};";

		$banner_groups = self::table_banner_groups();

		// Banner groups: a layer between campaigns and banners. Lets the
		// user organize banners by sub-campaign ("Black Friday", "Mother's
		// Day") and toggle whole groups on/off without losing the banners.
		$sql_banner_groups = "CREATE TABLE {$banner_groups} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			device VARCHAR(10) NOT NULL,
			name VARCHAR(191) NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY campaign_device (campaign_id, device, sort_order),
			KEY active (campaign_id, is_active)
		) {$charset_collate};";

		dbDelta( $sql_campaigns );
		dbDelta( $sql_banners );
		dbDelta( $sql_api_keys );
		dbDelta( $sql_banner_groups );
	}
}
