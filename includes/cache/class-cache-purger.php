<?php
/**
 * Cache purge orchestrator.
 *
 * Every plugin update changes what shortcodes render. Without this
 * class, site owners have to manually purge WP Rocket / LiteSpeed /
 * Cloudflare / whatever-else they run after every upload — which
 * nobody actually remembers to do, so they serve stale HTML for
 * hours or days.
 *
 * `purge_all()` fires a broadside at every cache layer we know how
 * to reach:
 *   - PHP OPcache (fixes stale class files lingering in memory
 *     after a ZIP upload — the "update doesn't take effect" bug).
 *   - WordPress object cache (memcached/redis/etc.).
 *   - Plugin's own transient caches (slug lookups).
 *   - 10+ popular WP page-cache plugins (see purge_third_party()).
 *   - A `usc_cache_purge` action so integrators can hook Cloudflare
 *     or any CDN with API-based invalidation.
 *
 * Every third-party call is gated by function_exists / class_exists
 * so absent plugins are silent no-ops — there's no performance cost
 * when nothing is installed.
 *
 * Fired automatically on:
 *   - Plugin activation.
 *   - First `init` after the plugin version string changes (so
 *     uploading a new ZIP purges the cache on the next pageview
 *     without anyone having to click anything).
 *   - Every create / update / delete on mosaics, campaigns, banners,
 *     groups, and mosaic items.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Cache;

defined( 'ABSPATH' ) || exit;

final class Cache_Purger {

	/**
	 * Soft cap on how many times `purge_all()` runs per request.
	 *
	 * Saving a carousel triggers a dozen repository writes (banners,
	 * groups, settings…) and every one of them would otherwise flush
	 * the same caches. After the first purge in a request, the others
	 * are no-ops — the cache is already empty.
	 *
	 * @var bool
	 */
	private static bool $already_purged_this_request = false;

	/**
	 * Purge every cache layer we know about.
	 *
	 * @param bool $force Bypass the per-request guard. Use for explicit
	 *                    user-facing actions (like the manual flush
	 *                    button) where you want the purge to happen
	 *                    every time regardless of what else ran.
	 */
	public static function purge_all( bool $force = false ): void {
		if ( self::$already_purged_this_request && ! $force ) {
			return;
		}
		self::$already_purged_this_request = true;

		/**
		 * Fires before the plugin purges any caches. Use this to run
		 * pre-purge cleanup — e.g. flushing your own dependent caches.
		 */
		do_action( 'usc_before_cache_purge' );

		self::purge_plugin_transients();
		self::purge_wp_object_cache();
		self::purge_opcache();
		self::purge_third_party();

		/**
		 * Fires after the plugin has purged every cache it knows about.
		 * Hook here to add CDN / Cloudflare purging that needs API
		 * credentials — e.g. `add_action( 'usc_cache_purge', 'my_cf_purge' )`.
		 */
		do_action( 'usc_cache_purge' );
	}

	/**
	 * Reset the per-request guard. Intended for use in tests where a
	 * single PHP process runs multiple logical requests.
	 */
	public static function reset_guard(): void {
		self::$already_purged_this_request = false;
	}

	/**
	 * Detect a plugin version change and fire an automatic purge.
	 *
	 * Hooked to `init` very early. Compares the last-seen version
	 * stored in wp_options against the current USC_VERSION constant.
	 * On mismatch, purges everything and stores the new version.
	 *
	 * This is the critical path for "update the plugin, old HTML
	 * stays cached" — the next pageview after upload clears it.
	 */
	public static function maybe_purge_on_upgrade(): void {
		$stored = get_option( 'usc_plugin_version' );
		if ( $stored === USC_VERSION ) {
			return;
		}
		self::purge_all( true );
		update_option( 'usc_plugin_version', USC_VERSION, false );
	}

	/* =============================================================
	 * Layer-by-layer purge helpers
	 * ============================================================= */

	private static function purge_plugin_transients(): void {
		delete_transient( USC_TRANSIENT_SLUGS );
		delete_transient( USC_TRANSIENT_MOSAIC_SLUGS );
		// Any future site-transient-scoped caches go here too.
	}

	private static function purge_wp_object_cache(): void {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Reset OPcache. Without this, uploading a new version of the
	 * plugin leaves the old PHP class bytecode in memory — the site
	 * runs the _old_ code even though the files on disk are new.
	 *
	 * Silenced because some hosts (and CLI) have OPcache disabled
	 * and would warn otherwise.
	 */
	private static function purge_opcache(): void {
		if ( function_exists( 'opcache_reset' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors
			@opcache_reset();
		}
	}

	/**
	 * Call every known page-cache plugin's flush function. Each
	 * branch is gated — plugin not installed, branch is a no-op.
	 */
	private static function purge_third_party(): void {
		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// LiteSpeed Cache (modern PHP namespace API)
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		} elseif ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clean_cache' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix, true );
		}

		// Autoptimize
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}

		// Cache Enabler (KeyCDN)
		if ( class_exists( '\KeyCDN\CacheEnabler\Cache_Enabler' ) && method_exists( '\KeyCDN\CacheEnabler\Cache_Enabler', 'clear_complete_cache' ) ) {
			\KeyCDN\CacheEnabler\Cache_Enabler::clear_complete_cache();
		} elseif ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_total_cache' ) ) {
			\Cache_Enabler::clear_total_cache();
		}

		// Breeze (Cloudways)
		if ( function_exists( 'breeze_clear_all_cache' ) ) {
			breeze_clear_all_cache();
		}

		// SiteGround Optimizer
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// NGINX Helper (rtCamp)
		if ( class_exists( 'Nginx_Helper' ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
		}

		// Hummingbird (WPMU DEV)
		if ( class_exists( '\Hummingbird\WP_Hummingbird' ) ) {
			do_action( 'wphb_clear_page_cache' );
		}

		// Swift Performance
		if ( class_exists( 'Swift_Performance_Cache' ) && method_exists( 'Swift_Performance_Cache', 'clear_all_cache' ) ) {
			\Swift_Performance_Cache::clear_all_cache();
		}

		// Kinsta (MU plugin — has its own cache layer on top of NGINX)
		if ( class_exists( '\Kinsta\Cache' ) ) {
			global $kinsta_cache;
			if ( is_object( $kinsta_cache ) && method_exists( $kinsta_cache, 'kinsta_cache_purge' ) ) {
				$kinsta_cache->kinsta_cache_purge->purge_complete_caches();
			}
		}

		// WP Fastest Cache
		if ( class_exists( 'WpFastestCache' ) && function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
		}

		// Comet Cache
		if ( class_exists( 'comet_cache' ) && method_exists( 'comet_cache', 'clear' ) ) {
			\comet_cache::clear();
		}
	}
}
