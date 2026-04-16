<?php
/**
 * Server-side HTML renderer for a single carousel.
 *
 * Outputs:
 *   - Semantic <section role="region" aria-roledescription="carousel">
 *   - A native <picture><img loading="lazy" decoding="async"> per slide
 *   - data-* attributes carrying the JS configuration (no inline JS)
 *   - First slide eager-loaded for LCP
 *
 * The bundled JS picks up `[data-usc-carousel]` containers on DOMContentLoaded
 * and wires up Embla. Without JS, the first slide still renders correctly.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Frontend;

use Univer\SmartCarousel\Database\Campaign_Repository;

defined( 'ABSPATH' ) || exit;

final class Carousel_Renderer {

	private static bool $in_use = false;

	public static function mark_in_use(): void {
		self::$in_use = true;
	}

	public static function is_in_use(): bool {
		return self::$in_use;
	}

	public static function render( array $campaign, string $device ): string {
		$device = Campaign_Repository::sanitize_device( $device );

		// Build the index of active groups for this device. Banners whose
		// group is paused (or whose own is_active is false) are skipped.
		// Legacy banners with group_id = 0 (pre-1.2.0 migration not yet
		// run) are kept active by default — fail open, never silent.
		$groups = $campaign['groups'] ?? [];
		$active_group_ids = [];
		foreach ( $groups as $g ) {
			if ( ( $g['device'] ?? '' ) !== $device ) {
				continue;
			}
			if ( ! empty( $g['is_active'] ) ) {
				$active_group_ids[ (int) $g['id'] ] = true;
			}
		}

		$banners = array_values( array_filter( $campaign['banners'] ?? [], static function ( $b ) use ( $device, $active_group_ids ) {
			if ( ( $b['device'] ?? '' ) !== $device ) {
				return false;
			}
			if ( empty( $b['image'] ) ) {
				return false;
			}
			if ( array_key_exists( 'is_active', $b ) && ! $b['is_active'] ) {
				return false;
			}
			$group_id = (int) ( $b['group_id'] ?? 0 );
			if ( $group_id > 0 && empty( $active_group_ids[ $group_id ] ) ) {
				return false;
			}
			return true;
		} ) );

		if ( empty( $banners ) ) {
			return '';
		}

		$settings    = $campaign['settings'];
		$is_desktop  = Campaign_Repository::DEVICE_DESKTOP === $device;
		$slides_pv   = $is_desktop ? (float) $settings['slides_per_view_desktop'] : (float) $settings['slides_per_view_mobile'];
		$aspect      = (string) ( $is_desktop ? $settings['aspect_ratio_desktop'] : $settings['aspect_ratio_mobile'] );
		$is_auto     = 'auto' === $aspect;
		$dom_id      = sprintf( 'usc-%s-%s-%s', $device, sanitize_html_class( $campaign['slug'] ), wp_generate_uuid4() );

		// Replace each banner's image payload with an optimized one. The
		// optimizer returns the same shape + webp_url / webp_srcset, or
		// the original shape unchanged if optimization is disabled on
		// this carousel or the host can't encode WebP.
		foreach ( $banners as &$banner ) {
			if ( ! empty( $banner['image_id'] ) ) {
				$optimized = Image_Optimizer::payload_for( (int) $banner['image_id'], $settings, $device );
				if ( $optimized ) {
					$banner['image'] = $optimized;
				}
			}
		}
		unset( $banner );

		// Preload the first image for LCP (only if we're rendering one carousel above the fold).
		// We add a hint via <link rel="preload"> in the markup; safe even multiple times.
		$first       = $banners[0]['image'];
		$navigation  = (string) $settings['navigation'];
		$show_arrows = 'arrows' === $navigation;
		$show_dots   = 'dots' === $navigation;

		$config = [
			'spv'           => $slides_pv,
			'gap'           => (int) $settings['gap'],
			'autoplay'      => (bool) $settings['autoplay'],
			'autoplayDelay' => (int) $settings['autoplay_delay'],
			'loop'          => (bool) $settings['loop'],
			'navigation'    => $navigation,
			'showDots'      => $show_dots,
			'showArrows'    => $show_arrows,
			'showProgress'  => (bool) $settings['show_progress'],
			'pauseOnHover'  => (bool) $settings['pause_on_hover'],
			'transition'    => (string) $settings['transition'],
			'device'        => $device,
			'autoAspect'    => $is_auto,
		];

		// Only set --usc-aspect when we have a real ratio. In auto mode the
		// CSS skips ::before entirely, so feeding it "auto" as a CSS value
		// would just sit there unused — and trip up validators.
		$style_parts = [
			'--usc-gap: ' . (int) $settings['gap'] . 'px',
			'--usc-radius:' . (int) $settings['border_radius'] . 'px',
			'--usc-spv:' . (string) $slides_pv,
		];
		if ( ! $is_auto ) {
			$style_parts[] = '--usc-aspect:' . str_replace( '/', ' / ', $aspect );
		}
		$style_attr = esc_attr( implode( ';', $style_parts ) . ';' );

		$root_classes = [ 'usc-carousel', 'usc-carousel--' . $device ];
		if ( $is_auto ) {
			$root_classes[] = 'is-auto-aspect';
		}

		ob_start();
		?>
<section
	id="<?php echo esc_attr( $dom_id ); ?>"
	class="<?php echo esc_attr( implode( ' ', $root_classes ) ); ?>"
	role="region"
	aria-roledescription="carousel"
	aria-label="<?php echo esc_attr( $campaign['name'] ); ?>"
	data-usc-carousel
	data-usc-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
	style="<?php echo $style_attr; ?>"
>
	<?php
	// LCP accelerator. Emit two preloads when a WebP variant exists —
	// browsers ignore the type they don't support, so only the right
	// one actually fetches. imagetype="" helps Chrome skip the JPEG
	// preload when it's going to grab the WebP from the <picture>.
	$preload_jpeg_attrs = '';
	if ( ! empty( $first['srcset'] ) ) {
		$preload_jpeg_attrs .= ' imagesrcset="' . esc_attr( $first['srcset'] ) . '"';
	}
	if ( ! empty( $first['sizes'] ) ) {
		$preload_jpeg_attrs .= ' imagesizes="' . esc_attr( $first['sizes'] ) . '"';
	}
	?>
	<?php if ( ! empty( $first['webp_url'] ) ) : ?>
		<link rel="preload" as="image" type="image/webp" href="<?php echo esc_url( $first['webp_url'] ); ?>"<?php
			if ( ! empty( $first['webp_srcset'] ) ) {
				echo ' imagesrcset="' . esc_attr( $first['webp_srcset'] ) . '"';
			}
			if ( ! empty( $first['sizes'] ) ) {
				echo ' imagesizes="' . esc_attr( $first['sizes'] ) . '"';
			}
		?> />
	<?php else : ?>
		<link rel="preload" as="image" href="<?php echo esc_url( $first['url'] ); ?>"<?php echo $preload_jpeg_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
	<?php endif; ?>

	<div class="usc-viewport" data-usc-viewport>
		<div class="usc-track" data-usc-track>
			<?php foreach ( $banners as $index => $banner ) : ?>
				<?php echo self::render_slide( $banner, $index, count( $banners ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( $config['showArrows'] && count( $banners ) > 1 ) : ?>
		<button class="usc-arrow usc-arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Previous slide', 'univer-smart-carousel' ); ?>" data-usc-prev>
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
		</button>
		<button class="usc-arrow usc-arrow--next" type="button" aria-label="<?php esc_attr_e( 'Next slide', 'univer-smart-carousel' ); ?>" data-usc-next>
			<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8.59 16.59 10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg>
		</button>
	<?php endif; ?>

	<?php if ( $config['showDots'] && count( $banners ) > 1 ) : ?>
		<div class="usc-dots" role="tablist" data-usc-dots aria-label="<?php esc_attr_e( 'Choose slide', 'univer-smart-carousel' ); ?>"></div>
	<?php endif; ?>

	<?php if ( $config['showProgress'] && count( $banners ) > 1 ) : ?>
		<div class="usc-progress" data-usc-progress aria-hidden="true"><span></span></div>
	<?php endif; ?>
</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_slide( array $banner, int $index, int $total ): string {
		$image = $banner['image'];
		if ( ! $image ) {
			return '';
		}

		$alt = $banner['alt_text'] !== '' ? $banner['alt_text'] : ( $image['alt'] ?: '' );

		// LCP: first slide eager + high priority. Others lazy.
		$loading_attr  = 0 === $index ? 'eager' : 'lazy';
		$fetchpriority = 0 === $index ? 'high' : 'auto';

		$img_attrs = sprintf(
			'src="%s" width="%d" height="%d" alt="%s" loading="%s" decoding="async" fetchpriority="%s"',
			esc_url( $image['url'] ),
			(int) $image['width'],
			(int) $image['height'],
			esc_attr( $alt ),
			esc_attr( $loading_attr ),
			esc_attr( $fetchpriority )
		);

		if ( ! empty( $image['srcset'] ) ) {
			$img_attrs .= ' srcset="' . esc_attr( $image['srcset'] ) . '"';
		}
		if ( ! empty( $image['sizes'] ) ) {
			$img_attrs .= ' sizes="' . esc_attr( $image['sizes'] ) . '"';
		}

		$img_html = '<img class="usc-image" ' . $img_attrs . ' />';

		// When the optimizer produced a WebP sibling, wrap the <img> in a
		// <picture> and offer WebP first. Browsers that understand it use
		// the <source>; everyone else falls back to the <img>.
		if ( ! empty( $image['webp_url'] ) || ! empty( $image['webp_srcset'] ) ) {
			$source_attrs = 'type="image/webp"';
			if ( ! empty( $image['webp_srcset'] ) ) {
				$source_attrs .= ' srcset="' . esc_attr( $image['webp_srcset'] ) . '"';
			} elseif ( ! empty( $image['webp_url'] ) ) {
				$source_attrs .= ' srcset="' . esc_url( $image['webp_url'] ) . '"';
			}
			if ( ! empty( $image['sizes'] ) ) {
				$source_attrs .= ' sizes="' . esc_attr( $image['sizes'] ) . '"';
			}
			$img_html = '<picture><source ' . $source_attrs . ' />' . $img_html . '</picture>';
		}

		$inner = $img_html;

		if ( ! empty( $banner['link_url'] ) ) {
			$rel  = $banner['link_rel'] !== '' ? $banner['link_rel'] : ( '_blank' === $banner['link_target'] ? 'noopener noreferrer' : '' );
			$attr = sprintf(
				'href="%s" target="%s"%s aria-label="%s"',
				esc_url( $banner['link_url'] ),
				esc_attr( $banner['link_target'] ),
				$rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
				esc_attr( $alt ?: __( 'Open promotion', 'univer-smart-carousel' ) )
			);
			$inner = '<a class="usc-link" ' . $attr . '>' . $img_html . '</a>';
		}

		return sprintf(
			'<div class="usc-slide" role="group" aria-roledescription="slide" aria-label="%s">%s</div>',
			esc_attr( sprintf( /* translators: 1: current slide, 2: total slides */ __( '%1$d of %2$d', 'univer-smart-carousel' ), $index + 1, $total ) ),
			$inner
		);
	}
}
