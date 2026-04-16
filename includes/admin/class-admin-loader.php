<?php
/**
 * Admin loader — registers the menu page, enqueues the React admin app,
 * and bootstraps the WP media library uploader.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Admin;

defined( 'ABSPATH' ) || exit;

final class Admin_Loader {

	public const MENU_SLUG    = 'univer-smart-carousel';
	public const SCRIPT_HANDLE = 'usc-admin';
	public const STYLE_HANDLE  = 'usc-admin';

	private string $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'plugin_action_links_' . USC_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	public function register_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'Smart Carousel', 'univer-smart-carousel' ),
			__( 'Smart Carousel', 'univer-smart-carousel' ),
			USC_ADMIN_CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			$this->menu_icon_data_uri(),
			58 // just below WooCommerce
		);
	}

	public function render_page(): void {
		echo '<div id="usc-admin-root" class="usc-admin-root"></div>';
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		// Required for the WP media library picker (wp.media).
		wp_enqueue_media();

		wp_enqueue_style(
			self::STYLE_HANDLE,
			USC_PLUGIN_URL . 'dist/admin/index.css',
			[],
			USC_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			USC_PLUGIN_URL . 'dist/admin/index.js',
			[ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			USC_VERSION,
			true
		);

		$config = [
			'restUrl'    => esc_url_raw( rest_url( USC_REST_NAMESPACE . '/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'pluginUrl'  => USC_PLUGIN_URL,
			'pluginVer'  => USC_VERSION,
			'adminUrl'   => esc_url_raw( admin_url() ),
			'siteUrl'   => esc_url_raw( home_url() ),
			'capability' => USC_ADMIN_CAPABILITY,
			'spvOptions' => [ 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5 ],
		];

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.USC_CFG = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'univer-smart-carousel' );
	}

	public function plugin_action_links( array $links ): array {
		$url            = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$settings_link  = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'univer-smart-carousel' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	private function menu_icon_data_uri(): string {
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10v4M17 10v4"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
