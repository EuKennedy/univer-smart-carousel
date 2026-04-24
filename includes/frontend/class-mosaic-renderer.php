<?php
/**
 * Server-side HTML renderer for a single mosaic.
 *
 * Output:
 *   <section class="usc-mosaic" style="--usc-cols:N;--usc-cols-m:M;--usc-gap:Ypx;--usc-radius:Rpx">
 *     <a class="usc-mosaic__item" style="--span-col:2;--span-row:1;--aspect:16/9" href="...">
 *       <picture>
 *         <source type="image/webp" ... />
 *         <img src="..." srcset="..." sizes="..." alt="..." />
 *       </picture>
 *     </a>
 *     ...
 *   </section>
 *
 * Items without a link render as <div> instead of <a>, so anchors are
 * only created when they actually navigate somewhere.
 *
 * CSS does the layout via CSS Grid with `grid-column: span N` /
 * `grid-row: span N` driven by the per-item CSS custom properties.
 * Zero JavaScript on the public side for the mosaic itself.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Frontend;

defined( 'ABSPATH' ) || exit;

final class Mosaic_Renderer {

	private static bool $in_use = false;

	public static function mark_in_use(): void {
		self::$in_use = true;
	}

	public static function is_in_use(): bool {
		return self::$in_use;
	}

	public static function render( array $mosaic ): string {
		$items = array_values(
			array_filter(
				$mosaic['items'] ?? [],
				static function ( $i ) {
					if ( empty( $i['image_id'] ) || empty( $i['image'] ) ) {
						return false;
					}
					if ( array_key_exists( 'is_active', $i ) && ! $i['is_active'] ) {
						return false;
					}
					return true;
				}
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		$settings = $mosaic['settings'];

		// Run each item through the shared Image_Optimizer so mosaics
		// benefit from the same resize + WebP + quality pipeline as
		// carousels. We pass 'desktop' because mosaics don't split by
		// device — the single width cap is image_max_width_desktop.
		foreach ( $items as &$item ) {
			$optimized = Image_Optimizer::payload_for( (int) $item['image_id'], $settings, 'desktop' );
			if ( $optimized ) {
				$item['image'] = $optimized;
			}
		}
		unset( $item );

		$dom_id = 'usc-mosaic-' . sanitize_html_class( $mosaic['slug'] ) . '-' . substr( wp_generate_uuid4(), 0, 8 );

		$style_parts = [
			'--usc-cols:' . (int) $settings['cols_desktop'],
			'--usc-cols-m:' . (int) $settings['cols_mobile'],
			'--usc-gap:' . (int) $settings['gap'] . 'px',
			'--usc-radius:' . (int) $settings['border_radius'] . 'px',
		];
		$style_attr = esc_attr( implode( ';', $style_parts ) );

		ob_start();
		?>
<section
	id="<?php echo esc_attr( $dom_id ); ?>"
	class="usc-mosaic"
	data-usc-mosaic
	style="<?php echo $style_attr; ?>"
	aria-label="<?php echo esc_attr( $mosaic['name'] ); ?>"
>
	<?php foreach ( $items as $index => $item ) : ?>
		<?php echo self::render_item( $item, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endforeach; ?>
</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_item( array $item, int $index ): string {
		$image = $item['image'];
		if ( ! $image ) {
			return '';
		}

		$alt = $item['alt_text'] !== '' ? $item['alt_text'] : ( $image['alt'] ?? '' );

		// Only the first item is eager-loaded. Mosaics often sit
		// below the fold (they're a "discover more" surface), so we
		// don't want every cell competing for network at first paint.
		$loading       = 0 === $index ? 'eager' : 'lazy';
		$fetchpriority = 0 === $index ? 'high' : 'auto';

		$img_attrs = sprintf(
			'src="%s" width="%d" height="%d" alt="%s" loading="%s" decoding="async" fetchpriority="%s"',
			esc_url( $image['url'] ),
			(int) ( $image['width'] ?? 0 ),
			(int) ( $image['height'] ?? 0 ),
			esc_attr( $alt ),
			esc_attr( $loading ),
			esc_attr( $fetchpriority )
		);
		if ( ! empty( $image['srcset'] ) ) {
			$img_attrs .= ' srcset="' . esc_attr( $image['srcset'] ) . '"';
		}
		if ( ! empty( $image['sizes'] ) ) {
			$img_attrs .= ' sizes="' . esc_attr( $image['sizes'] ) . '"';
		}

		$img_html = '<img class="usc-mosaic__image" ' . $img_attrs . ' />';

		// Wrap in <picture> when a WebP variant exists.
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

		$aspect_css = 'auto' === $item['aspect_ratio']
			? 'auto'
			: str_replace( '/', ' / ', (string) $item['aspect_ratio'] );

		$cell_style = sprintf(
			'--span-col:%d;--span-row:%d;--aspect:%s;',
			(int) $item['col_span'],
			(int) $item['row_span'],
			esc_attr( $aspect_css )
		);

		$aspect_class = 'auto' === $item['aspect_ratio'] ? 'usc-mosaic__item--auto-aspect' : '';

		if ( ! empty( $item['link_url'] ) ) {
			$rel  = $item['link_rel'] !== ''
				? $item['link_rel']
				: ( '_blank' === $item['link_target'] ? 'noopener noreferrer' : '' );
			$link = sprintf(
				'<a class="usc-mosaic__item %s" style="%s" href="%s" target="%s"%s aria-label="%s">%s</a>',
				esc_attr( $aspect_class ),
				$cell_style, // already safe
				esc_url( $item['link_url'] ),
				esc_attr( $item['link_target'] ),
				$rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
				esc_attr( $alt ?: __( 'Open item', 'univer-smart-carousel' ) ),
				$img_html
			);
			return $link;
		}

		return sprintf(
			'<div class="usc-mosaic__item %s" style="%s">%s</div>',
			esc_attr( $aspect_class ),
			$cell_style,
			$img_html
		);
	}
}
