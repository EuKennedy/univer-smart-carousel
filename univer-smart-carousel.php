<?php
/**
 * Plugin Name:       Univer Smart Carousel
 * Plugin URI:        https://github.com/EuKennedy/univer-smart-carousel
 * Description:       Lightweight, premium, web-vitals-friendly banner carousel for WordPress and WooCommerce. Marketing teams create campaigns and ship banners with a single shortcode — desktop and mobile, fully separated.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kennedy Rodrigues Gomes Teixeira
 * Author URI:        https://github.com/EuKennedy
 * License:           MIT with Commons Clause
 * License URI:       https://github.com/EuKennedy/univer-smart-carousel/blob/main/LICENSE
 * Text Domain:       univer-smart-carousel
 * Domain Path:       /languages
 *
 * Copyright (c) 2026 Kennedy Rodrigues Gomes Teixeira. All rights reserved.
 * Free to use, modify, and distribute. Selling, sublicensing, or repackaging
 * for commercial resale is strictly prohibited. See LICENSE for full terms.
 *
 * @package Univer\SmartCarousel
 */

defined( 'ABSPATH' ) || exit;

define( 'USC_VERSION', '1.0.0' );
define( 'USC_PLUGIN_FILE', __FILE__ );
define( 'USC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'USC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'USC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'USC_REST_NAMESPACE', 'usc/v1' );
define( 'USC_DB_VERSION', '1.0.0' );
define( 'USC_TABLE_CAMPAIGNS', 'usc_campaigns' );
define( 'USC_TABLE_BANNERS', 'usc_banners' );
define( 'USC_CACHE_GROUP', 'usc_cache' );
define( 'USC_TRANSIENT_SLUGS', 'usc_campaign_slugs_v1' );
define( 'USC_ADMIN_CAPABILITY', 'manage_options' );

require_once USC_PLUGIN_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, [ \Univer\SmartCarousel\Database\Database_Installer::class, 'install' ] );
register_deactivation_hook( __FILE__, [ \Univer\SmartCarousel\Database\Database_Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
	\Univer\SmartCarousel\Plugin::instance();
}, 5 );
