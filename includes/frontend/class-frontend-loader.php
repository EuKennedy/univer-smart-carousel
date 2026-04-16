<?php
/**
 * Frontend loader — registers (but does not enqueue) the carousel assets.
 *
 * The renderer flips a flag when a shortcode actually runs, and we conditionally
 * enqueue in `wp_footer` so pages without the shortcode pay zero asset cost.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Frontend;

defined( 'ABSPATH' ) || exit;

final class Frontend_Loader {

	public const HANDLE_JS  = 'usc-frontend';
	public const HANDLE_CSS = 'usc-frontend';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 5 );
		add_action( 'wp_footer', [ $this, 'maybe_enqueue' ], 5 );
	}

	public function register_assets(): void {
		wp_register_style(
			self::HANDLE_CSS,
			USC_PLUGIN_URL . 'dist/frontend/index.css',
			[],
			USC_VERSION
		);

		wp_register_script(
			self::HANDLE_JS,
			USC_PLUGIN_URL . 'dist/frontend/index.js',
			[],
			USC_VERSION,
			true
		);
	}

	public function maybe_enqueue(): void {
		if ( ! Carousel_Renderer::is_in_use() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE_CSS );
		wp_enqueue_script( self::HANDLE_JS );
	}
}
