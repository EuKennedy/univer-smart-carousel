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
		update_option( self::VERSION_OPTION, USC_DB_VERSION, false );
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
			start_date DATETIME NULL,
			end_date DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY date_window (start_date, end_date)
		) {$charset_collate};";

		$sql_banners = "CREATE TABLE {$banners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			device VARCHAR(10) NOT NULL,
			image_id BIGINT UNSIGNED NOT NULL,
			link_url VARCHAR(2048) NULL,
			link_target VARCHAR(10) NOT NULL DEFAULT '_self',
			link_rel VARCHAR(64) NULL,
			alt_text VARCHAR(255) NULL,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY campaign_device (campaign_id, device, sort_order),
			KEY image_id (image_id)
		) {$charset_collate};";

		dbDelta( $sql_campaigns );
		dbDelta( $sql_banners );
	}
}
