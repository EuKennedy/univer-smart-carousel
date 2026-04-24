<?php
/**
 * Dynamic [mosaic_{slug}] shortcode handler.
 *
 * Mirrors Shortcode_Handler: registers one shortcode per mosaic on
 * `init` by walking a transient-cached slug list. Unlike the carousel
 * shortcodes (which split desktop / mobile), mosaics use a single
 * shortcode — the responsive behavior is built into the grid via
 * `cols_desktop` and `cols_mobile` CSS custom properties.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Shortcode;

use Univer\SmartCarousel\Database\Mosaic_Repository;
use Univer\SmartCarousel\Frontend\Mosaic_Renderer;

defined( 'ABSPATH' ) || exit;

final class Mosaic_Shortcode_Handler {

	private const PREFIX = 'mosaic_';

	public function __construct() {
		add_action( 'init', [ $this, 'register_shortcodes' ], 20 );
	}

	public function register_shortcodes(): void {
		$slugs = Mosaic_Repository::all_slugs();
		foreach ( $slugs as $slug ) {
			add_shortcode(
				self::PREFIX . $slug,
				function () use ( $slug ) {
					return $this->render( $slug );
				}
			);
		}
	}

	private function render( string $slug ): string {
		$mosaic = Mosaic_Repository::get_by_slug( $slug, true );
		if ( ! $mosaic ) {
			return '';
		}
		if ( ! Mosaic_Repository::is_live( $mosaic ) ) {
			// Admin sees a yellow "not live" hint, public sees nothing.
			if ( current_user_can( USC_ADMIN_CAPABILITY ) ) {
				return sprintf(
					'<div style="font:13px/1.4 system-ui;color:#a16207;background:#fef3c7;border:1px solid #fde68a;padding:10px 14px;border-radius:8px;">%s</div>',
					esc_html__( 'Univer Smart Carousel: this mosaic is not currently live (status is not "active").', 'univer-smart-carousel' )
				);
			}
			return '';
		}

		Mosaic_Renderer::mark_in_use();
		return Mosaic_Renderer::render( $mosaic );
	}
}
