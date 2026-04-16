<?php
/**
 * Dynamic shortcode handler.
 *
 * Registers `[carouseldesktop_{slug}]` and `[carouselmobile_{slug}]`
 * for every campaign on `init`, with a 1-hour transient cache of slugs.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Shortcode;

use Univer\SmartCarousel\Database\Campaign_Repository;
use Univer\SmartCarousel\Frontend\Carousel_Renderer;

defined( 'ABSPATH' ) || exit;

final class Shortcode_Handler {

	private const PREFIX_DESKTOP = 'carouseldesktop_';
	private const PREFIX_MOBILE  = 'carouselmobile_';

	public function __construct() {
		add_action( 'init', [ $this, 'register_shortcodes' ], 20 );
	}

	public function register_shortcodes(): void {
		$slugs = Campaign_Repository::all_slugs();
		foreach ( $slugs as $slug ) {
			add_shortcode(
				self::PREFIX_DESKTOP . $slug,
				function () use ( $slug ) {
					return $this->render( $slug, Campaign_Repository::DEVICE_DESKTOP );
				}
			);
			add_shortcode(
				self::PREFIX_MOBILE . $slug,
				function () use ( $slug ) {
					return $this->render( $slug, Campaign_Repository::DEVICE_MOBILE );
				}
			);
		}
	}

	private function render( string $slug, string $device ): string {
		$campaign = Campaign_Repository::get_campaign_by_slug( $slug, true );
		if ( ! $campaign ) {
			return '';
		}
		if ( ! Campaign_Repository::is_live( $campaign ) ) {
			// If the user is admin, surface a hint; otherwise, render nothing.
			if ( current_user_can( USC_ADMIN_CAPABILITY ) ) {
				return sprintf(
					'<div style="font:13px/1.4 system-ui;color:#a16207;background:#fef3c7;border:1px solid #fde68a;padding:10px 14px;border-radius:8px;">%s</div>',
					esc_html__( 'Univer Smart Carousel: this campaign is not currently live (status, start_date, or end_date).', 'univer-smart-carousel' )
				);
			}
			return '';
		}

		Carousel_Renderer::mark_in_use();
		return Carousel_Renderer::render( $campaign, $device );
	}
}
