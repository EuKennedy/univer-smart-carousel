<?php
/**
 * Server-side HTML for the global Header Top vertical swiper.
 *
 * Output is a single <section> with [data-usc-header-top] that the
 * frontend JS picks up at DOMContentLoaded and wires Embla's vertical
 * mode + autoplay. Width is 100% of the parent (theme decides), height
 * is configurable in settings.
 *
 * Single image (no <picture> / WebP variants here) keeps the bundle
 * small — strip slides are typically tiny PNG/SVG announcements and
 * the optimizer pipeline isn't worth the per-slide variant overhead.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Frontend;

defined( 'ABSPATH' ) || exit;

final class Header_Top_Renderer {

	private static bool $in_use            = false;
	private static bool $critical_css_done = false;

	public static function mark_in_use(): void {
		self::$in_use = true;
	}

	public static function is_in_use(): bool {
		return self::$in_use;
	}

	/**
	 * Critical CSS shipped inline at render time so the strip clamps to
	 * its configured height on the very first paint, before the main
	 * stylesheet (enqueued in wp_footer) has a chance to load.
	 *
	 * Without this, the strip — being at the very top of the page —
	 * renders raw HTML for a beat: every slide image stacked at its
	 * intrinsic size, the section pushed to thousands of pixels tall,
	 * and the rest of the page shoved down. Then the stylesheet kicks
	 * in and everything snaps to 36px. Classic FOUC.
	 *
	 * The rules here only need to cover the booting state. The full
	 * stylesheet still owns transitions, hover, etc. once it loads
	 * and the JS adds `usc-ready`, after which these `:not(.usc-ready)`
	 * selectors stop matching.
	 */
	private static function critical_css(): string {
		if ( self::$critical_css_done ) {
			return '';
		}
		self::$critical_css_done = true;
		// Minified by hand. Targets only `.usc-header-top:not(.usc-ready)`
		// so the live stylesheet wins once Embla finishes booting.
		return '<style id="usc-header-top-critical">'
			. '.usc-header-top{display:block;width:100%;height:var(--usc-ht-height,36px);background:var(--usc-ht-bg,#000);overflow:hidden;position:relative;box-sizing:border-box}'
			. '.usc-header-top:not(.usc-ready) .usc-header-top__viewport{overflow:hidden;width:100%;height:100%}'
			. '.usc-header-top:not(.usc-ready) .usc-header-top__container{display:block;height:100%;width:100%}'
			. '.usc-header-top:not(.usc-ready) .usc-header-top__slide{display:flex;align-items:center;justify-content:center;width:100%;height:100%;overflow:hidden}'
			. '.usc-header-top:not(.usc-ready) .usc-header-top__slide:not(:first-child){display:none !important}'
			. '.usc-header-top:not(.usc-ready) .usc-header-top__image{display:block;max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain}'
			. '</style>';
	}

	/**
	 * @param array<string,mixed>          $settings
	 * @param array<int,array<string,mixed>> $slides
	 */
	public static function render( array $settings, array $slides ): string {
		$dom_id = 'usc-header-top-' . substr( wp_generate_uuid4(), 0, 8 );

		$config = wp_json_encode( [
			'autoplayDelay' => (int) $settings['autoplay_delay'],
			'transitionMs'  => (int) $settings['transition_ms'],
			'slideCount'    => count( $slides ),
		] );

		$style_parts = [
			'--usc-ht-height:' . (int) $settings['height_px'] . 'px',
			'--usc-ht-bg:' . $settings['background'],
		];
		$style_attr = esc_attr( implode( ';', $style_parts ) );

		$critical = self::critical_css();

		ob_start();
		echo $critical; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
<section
	id="<?php echo esc_attr( $dom_id ); ?>"
	class="usc-header-top"
	data-usc-header-top
	data-usc-config="<?php echo esc_attr( $config ); ?>"
	style="<?php echo $style_attr; ?>"
	aria-label="<?php esc_attr_e( 'Site announcements', 'univer-smart-carousel' ); ?>"
>
	<div class="usc-header-top__viewport" data-usc-ht-viewport>
		<div class="usc-header-top__container" data-usc-ht-container>
			<?php foreach ( $slides as $index => $slide ) : ?>
				<?php echo self::render_slide( $slide, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_slide( array $slide, int $index ): string {
		$image = $slide['image'];
		if ( empty( $image ) ) {
			return '';
		}
		$alt = $slide['alt_text'] !== '' ? $slide['alt_text'] : ( $image['alt'] ?? '' );

		// First slide is eager + high priority for LCP, rest are lazy.
		$loading       = 0 === $index ? 'eager' : 'lazy';
		$fetchpriority = 0 === $index ? 'high' : 'auto';

		$img_html = sprintf(
			'<img class="usc-header-top__image" src="%s" width="%d" height="%d" alt="%s" loading="%s" decoding="async" fetchpriority="%s" />',
			esc_url( $image['url'] ),
			(int) ( $image['width'] ?? 0 ),
			(int) ( $image['height'] ?? 0 ),
			esc_attr( $alt ),
			esc_attr( $loading ),
			esc_attr( $fetchpriority )
		);

		if ( ! empty( $slide['link_url'] ) ) {
			$rel  = $slide['link_rel'] !== ''
				? $slide['link_rel']
				: ( '_blank' === $slide['link_target'] ? 'noopener noreferrer' : '' );
			return sprintf(
				'<div class="usc-header-top__slide"><a href="%s" target="%s"%s aria-label="%s">%s</a></div>',
				esc_url( $slide['link_url'] ),
				esc_attr( $slide['link_target'] ),
				$rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
				esc_attr( $alt ?: __( 'Open announcement', 'univer-smart-carousel' ) ),
				$img_html
			);
		}

		return sprintf( '<div class="usc-header-top__slide">%s</div>', $img_html );
	}
}
