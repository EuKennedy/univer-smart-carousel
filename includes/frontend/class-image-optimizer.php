<?php
/**
 * On-demand image variant generator.
 *
 * Given a WP attachment ID and a carousel's settings, returns URLs for
 * a resized / recompressed JPEG and (optionally) a WebP sibling, plus
 * a srcset covering common device pixel densities. Variants are written
 * once into the same uploads/YYYY/MM/ directory as the original, using
 * a deterministic filename that encodes the transform — so the cache is
 * self-invalidating: change a setting, you get a different filename.
 *
 * Filename convention:
 *   original: uploads/2026/04/banner.jpg
 *   variants: uploads/2026/04/banner-usc-w1920-q82.jpg
 *             uploads/2026/04/banner-usc-w1920-q82.webp
 *             uploads/2026/04/banner-usc-w3840-q82.jpg  (2x retina)
 *
 * Design notes:
 *  - We deliberately don't register WP image sizes. Registering one
 *    size per (width, quality) combination pollutes the global size
 *    registry across every upload, not just ours.
 *  - `wp_get_image_editor` is fairly heavy on first call but cached.
 *    We only invoke it when a variant file doesn't yet exist on disk.
 *  - If ensure_variant() fails at any step (permissions, bad image,
 *    unsupported format), we fall back to the original URL. Never
 *    let a broken thumbnail break the page.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Frontend;

use Univer\SmartCarousel\Database\Campaign_Repository;
use WP_Image_Editor;

defined( 'ABSPATH' ) || exit;

final class Image_Optimizer {

	/** Density multipliers we generate srcset entries for. */
	private const DENSITIES = [ 1.0, 2.0 ];

	/**
	 * Cached answer to "can this host encode WebP?" — expensive enough
	 * to want memoizing across the request.
	 *
	 * @var bool|null
	 */
	private static $webp_supported = null;

	/**
	 * Build the frontend image payload for a banner, applying the
	 * campaign's optimization settings.
	 *
	 * Returns the same shape as Campaign_Repository::image_payload() so
	 * the renderer can drop it in without caring whether optimization
	 * ran — with the extra `webp_url` and `webp_srcset` keys when WebP
	 * is available.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function payload_for( int $attachment_id, array $settings, string $device ): ?array {
		$base = Campaign_Repository::image_payload( $attachment_id );
		if ( ! $base ) {
			return null;
		}

		// Opt-out: optimization disabled on this carousel → ship the
		// original untouched, same as pre-1.4.0 behavior.
		if ( empty( $settings['image_optimization'] ) ) {
			return $base;
		}

		$max_width = (int) ( 'desktop' === $device
			? ( $settings['image_max_width_desktop'] ?? 1920 )
			: ( $settings['image_max_width_mobile'] ?? 750 ) );

		if ( (int) $base['width'] <= 0 ) {
			return $base;
		}

		$quality  = (int) ( $settings['image_quality'] ?? 82 );
		$emit_webp = ! empty( $settings['image_webp'] ) && self::server_supports_webp();

		// Build a srcset: one variant per density, capped at the original's
		// natural size (no upscaling). Note: we ALWAYS generate variants,
		// even when the original is already at (or below) max_width — the
		// point is to recompress at the configured quality. Skipping the
		// generation there used to ship the untouched, WP-default-quality
		// original as the JPEG fallback, which was ~60-70% larger than
		// the intended output. WebP-less browsers were paying that cost.
		// Keyed by target_width so that 2x density clamping down to the
		// original's natural width doesn't duplicate an entry in the srcset.
		$jpeg_parts       = []; // [ width => url ]
		$webp_parts       = []; // [ width => url ]
		$primary_url      = null;
		$primary_webp_url = null;

		foreach ( self::DENSITIES as $density ) {
			$target_width = (int) round( $max_width * $density );
			if ( $target_width > (int) $base['width'] ) {
				$target_width = (int) $base['width'];
			}

			if ( ! isset( $jpeg_parts[ $target_width ] ) ) {
				$jpeg_url = self::ensure_variant( $attachment_id, $target_width, $quality, 'jpeg' );
				if ( $jpeg_url ) {
					$jpeg_parts[ $target_width ] = $jpeg_url;
					if ( null === $primary_url || 1.0 === $density ) {
						$primary_url = $jpeg_url;
					}
				}
			}

			if ( $emit_webp && ! isset( $webp_parts[ $target_width ] ) ) {
				$webp_url = self::ensure_variant( $attachment_id, $target_width, $quality, 'webp' );
				if ( $webp_url ) {
					$webp_parts[ $target_width ] = $webp_url;
					if ( null === $primary_webp_url || 1.0 === $density ) {
						$primary_webp_url = $webp_url;
					}
				}
			}
		}

		$jpeg_srcset_parts = [];
		foreach ( $jpeg_parts as $w => $u ) {
			$jpeg_srcset_parts[] = $u . ' ' . $w . 'w';
		}
		$webp_srcset_parts = [];
		foreach ( $webp_parts as $w => $u ) {
			$webp_srcset_parts[] = $u . ' ' . $w . 'w';
		}

		return [
			'id'          => $base['id'],
			'url'         => $primary_url ?: $base['url'],
			'srcset'      => $jpeg_srcset_parts ? implode( ', ', $jpeg_srcset_parts ) : $base['srcset'],
			'sizes'       => '(max-width: 640px) 100vw, ' . $max_width . 'px',
			'width'       => $max_width,
			'height'      => (int) round( $base['height'] * ( $max_width / max( 1, $base['width'] ) ) ),
			'alt'         => $base['alt'] ?? '',
			'webp_url'    => $primary_webp_url,
			'webp_srcset' => $webp_srcset_parts ? implode( ', ', $webp_srcset_parts ) : null,
		];
	}

	/**
	 * Ensures a (width, quality, format) variant exists on disk for a
	 * given attachment. Returns its public URL or null on failure.
	 */
	private static function ensure_variant( int $attachment_id, int $width, int $quality, string $format ): ?string {
		$original_path = get_attached_file( $attachment_id );
		if ( ! $original_path || ! file_exists( $original_path ) ) {
			return null;
		}

		$info         = pathinfo( $original_path );
		$ext          = 'webp' === $format ? 'webp' : 'jpg';
		$variant_name = $info['filename'] . '-usc-w' . $width . '-q' . $quality . '.' . $ext;
		$variant_path = $info['dirname'] . '/' . $variant_name;

		if ( file_exists( $variant_path ) ) {
			return self::path_to_url( $variant_path );
		}

		$editor = wp_get_image_editor( $original_path );
		if ( is_wp_error( $editor ) ) {
			return null;
		}

		$current_size = $editor->get_size();
		$current_w    = (int) ( $current_size['width'] ?? 0 );
		if ( $current_w > $width ) {
			$resized = $editor->resize( $width, null, false );
			if ( is_wp_error( $resized ) ) {
				return null;
			}
		}

		$editor->set_quality( $quality );

		$mime       = 'webp' === $format ? 'image/webp' : 'image/jpeg';
		$save_result = $editor->save( $variant_path, $mime );
		if ( is_wp_error( $save_result ) ) {
			return null;
		}

		return self::path_to_url( $variant_path );
	}

	/**
	 * Is the image editor on this host capable of encoding WebP?
	 * WordPress exposes this via WP_Image_Editor::supports_mime_type(),
	 * which checks whichever editor (GD or Imagick) is active.
	 */
	public static function server_supports_webp(): bool {
		if ( null !== self::$webp_supported ) {
			return self::$webp_supported;
		}
		$editor_class = function_exists( '_wp_image_editor_choose' )
			? _wp_image_editor_choose( [ 'mime_type' => 'image/webp' ] )
			: false;

		self::$webp_supported = is_string( $editor_class ) && class_exists( $editor_class )
			&& method_exists( $editor_class, 'supports_mime_type' )
			&& call_user_func( [ $editor_class, 'supports_mime_type' ], 'image/webp' );

		return self::$webp_supported;
	}

	private static function path_to_url( string $path ): string {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['basedir'] ) && 0 === strpos( $path, $uploads['basedir'] ) ) {
			return $uploads['baseurl'] . substr( $path, strlen( $uploads['basedir'] ) );
		}
		return '';
	}

}
