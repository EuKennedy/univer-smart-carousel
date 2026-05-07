<?php
/**
 * [header_top] shortcode — global vertical-swiper announcement strip.
 *
 * Single shortcode (no slugs), since there's only one Header Top per
 * site. Drops nothing on the page if the strip is disabled in
 * settings or has no active slides.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Shortcode;

use Univer\SmartCarousel\Database\Header_Top_Repository;
use Univer\SmartCarousel\Frontend\Header_Top_Renderer;

defined( 'ABSPATH' ) || exit;

final class Header_Top_Shortcode_Handler {

	private const SHORTCODE = 'header_top';

	public function __construct() {
		add_action( 'init', [ $this, 'register_shortcode' ], 20 );
	}

	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render' ] );
	}

	public function render(): string {
		$settings = Header_Top_Repository::get_settings();
		if ( empty( $settings['is_enabled'] ) ) {
			return '';
		}

		$slides = array_values( array_filter(
			Header_Top_Repository::list_all(),
			static function ( $s ) {
				return ! empty( $s['is_active'] ) && ! empty( $s['image'] );
			}
		) );

		if ( empty( $slides ) ) {
			// Admins see a yellow hint, public sees nothing.
			if ( current_user_can( USC_ADMIN_CAPABILITY ) ) {
				return sprintf(
					'<div style="font:13px/1.4 system-ui;color:#a16207;background:#fef3c7;border:1px solid #fde68a;padding:10px 14px;border-radius:8px;">%s</div>',
					esc_html__( 'Univer Smart Carousel: the Header Top has no active slides.', 'univer-smart-carousel' )
				);
			}
			return '';
		}

		Header_Top_Renderer::mark_in_use();
		return Header_Top_Renderer::render( $settings, $slides );
	}
}
