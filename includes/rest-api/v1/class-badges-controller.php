<?php
/**
 * Badges controller — renames + re-swatches terms of a WooCommerce
 * attribute taxonomy, intended as a shortcut over walking into
 * /wp-admin/term.php every rotation.
 *
 * Endpoints:
 *   GET  /usc/v1/badges?taxonomy=pa_badge-ofertas
 *   PUT  /usc/v1/badges/{term_id}   body: { taxonomy?, name?, image_id?, meta_key? }
 *
 * Storage-format awareness:
 *
 *   Different swatch plugins / themes write the attached image under
 *   different meta keys AND in different value shapes. The three that
 *   show up in the wild on WooCommerce installs:
 *
 *     (a) an integer attachment ID         →  `product_attribute_image`, `thumbnail_id`
 *     (b) an array with `id` + `url`       →  Woodmart / XTS themes store under `image`
 *     (c) just a URL string                →  older / custom plugins
 *
 *   discover_image() walks every meta row on the term and returns
 *   whichever one actually resolves to an image attachment, plus the
 *   shape it was stored in. update_item() preserves that shape on
 *   write — if the site's swatches plugin reads an array, we write
 *   an array; if it reads an int, we write an int. That's the only
 *   way the change actually reflects on the live site without
 *   guessing which plugin is active.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
use Univer\SmartCarousel\Database\Campaign_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Badges_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'badges';

	/**
	 * Shape constants for how the attachment is stored in term meta.
	 */
	private const SHAPE_INT   = 'int';   // meta value IS the attachment ID.
	private const SHAPE_ARRAY = 'array'; // meta value is { id: N, url: "..." }.
	private const SHAPE_URL   = 'url';   // meta value is just a URL string.

	/**
	 * Ordered guess list when no existing meta resolves to an image —
	 * used for brand-new terms that don't have a swatch yet.
	 */
	private const KNOWN_IMAGE_META_KEYS = [
		'image',                   // Woodmart / XTS themes
		'product_attribute_image', // popular swatch plugins
		'thumbnail_id',
		'swatch_image',
		'image_id',
	];

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_items' ],
					'permission_callback' => [ $this, 'permission_read' ],
					'args'                => [
						'taxonomy' => [
							'type'        => 'string',
							'description' => 'Taxonomy slug to list terms from. Defaults to pa_badge-ofertas.',
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);
	}

	public function permission_read( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'read' );
	}
	public function permission_write( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'write' );
	}

	public function list_items( WP_REST_Request $request ) {
		$taxonomy = $this->taxonomy_from( $request );
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return new WP_Error(
				'usc_invalid_taxonomy',
				__( 'Only product attribute taxonomies (pa_*) are allowed.', 'univer-smart-carousel' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error(
				'usc_unknown_taxonomy',
				sprintf( /* translators: %s: taxonomy slug */ __( 'Taxonomy "%s" does not exist on this site.', 'univer-smart-carousel' ), $taxonomy ),
				[ 'status' => 404 ]
			);
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$out = array_map(
			function ( $term ) use ( $taxonomy ) {
				return $this->hydrate( $term, $taxonomy );
			},
			$terms
		);

		return new WP_REST_Response( $out, 200 );
	}

	public function update_item( WP_REST_Request $request ) {
		$term_id = (int) $request['id'];
		$body    = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}

		$taxonomy = isset( $body['taxonomy'] ) ? sanitize_key( $body['taxonomy'] ) : 'pa_badge-ofertas';
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return new WP_Error(
				'usc_invalid_taxonomy',
				__( 'Only product attribute taxonomies (pa_*) are allowed.', 'univer-smart-carousel' ),
				[ 'status' => 403 ]
			);
		}

		$term     = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'usc_not_found', __( 'Badge not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}

		// Rename
		if ( array_key_exists( 'name', $body ) ) {
			$new_name = sanitize_text_field( (string) $body['name'] );
			if ( '' !== $new_name && $new_name !== $term->name ) {
				$updated = wp_update_term( $term_id, $taxonomy, [ 'name' => $new_name ] );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}
		}

		// Swatch image — has to match whatever format the site's plugin reads.
		if ( array_key_exists( 'image_id', $body ) ) {
			$image_id = (int) $body['image_id'];
			if ( $image_id > 0 && ! wp_attachment_is_image( $image_id ) ) {
				return new WP_Error(
					'usc_invalid_image',
					__( 'The selected attachment is not an image.', 'univer-smart-carousel' ),
					[ 'status' => 400 ]
				);
			}

			$discovered = $this->discover_image( $term_id );

			// If caller sent explicit meta_key / shape, honor that. Otherwise
			// preserve whatever was there before. If nothing was there before,
			// default to the Woodmart-compatible "image" + array shape because
			// that's what Kennedy's site (and any Woodmart/XTS theme) reads.
			$meta_key = isset( $body['meta_key'] ) && '' !== $body['meta_key']
				? sanitize_key( $body['meta_key'] )
				: ( $discovered ? $discovered['meta_key'] : self::KNOWN_IMAGE_META_KEYS[0] );

			$shape = $discovered ? $discovered['shape'] : self::SHAPE_ARRAY;

			if ( $image_id > 0 ) {
				$value = $this->build_meta_value( $image_id, $shape );
				update_term_meta( $term_id, $meta_key, $value );
			} else {
				// image_id === 0 means "remove swatch" — remove only from
				// the key we know about, don't touch anything else.
				delete_term_meta( $term_id, $meta_key );
			}
		}

		$fresh = get_term( $term_id, $taxonomy );
		return new WP_REST_Response( $this->hydrate( $fresh, $taxonomy ), 200 );
	}

	/* --------------------------------------------------------------- */

	private function taxonomy_from( WP_REST_Request $request ): string {
		$raw = $request->get_param( 'taxonomy' );
		return $raw ? sanitize_key( (string) $raw ) : 'pa_badge-ofertas';
	}

	/**
	 * Hydrated term row for the UI.
	 *
	 * @return array<string,mixed>
	 */
	private function hydrate( $term, string $taxonomy ): array {
		$discovered    = $this->discover_image( (int) $term->term_id );
		$image_id      = $discovered['image_id'] ?? 0;
		$image_payload = $image_id > 0 ? Campaign_Repository::image_payload( $image_id ) : null;

		$out = [
			'id'       => (int) $term->term_id,
			'name'     => (string) $term->name,
			'slug'     => (string) $term->slug,
			'taxonomy' => $taxonomy,
			'count'    => (int) $term->count,
			'edit_url' => esc_url_raw( admin_url( 'term.php?taxonomy=' . $taxonomy . '&tag_ID=' . (int) $term->term_id ) ),
			'swatch'   => [
				'meta_key' => $discovered['meta_key'] ?? self::KNOWN_IMAGE_META_KEYS[0],
				'shape'    => $discovered['shape'] ?? self::SHAPE_ARRAY,
				'image_id' => $image_id,
				'image'    => $image_payload,
			],
		];

		// Raw meta dump as a debug aid. Admin UI can render this in an
		// "advanced" disclosure so the user (or we) can see what the
		// site's actually storing under the hood. Only expose to full admins,
		// not arbitrary read-scope API keys.
		if ( current_user_can( USC_ADMIN_CAPABILITY ) ) {
			$raw_meta = get_term_meta( (int) $term->term_id );
			if ( ! is_array( $raw_meta ) ) {
				$raw_meta = [];
			}
			// Flatten: get_term_meta without a key returns each as array.
			$flat_meta = [];
			foreach ( $raw_meta as $k => $v ) {
				$flat_meta[ $k ] = is_array( $v ) && count( $v ) === 1 ? $v[0] : $v;
			}
			$out['raw_meta'] = $flat_meta;
		}

		return $out;
	}

	/**
	 * Walk every term meta value and return the first one that resolves
	 * to an image attachment — along with the SHAPE it was stored in,
	 * so update_item() can write back in the same shape.
	 *
	 * @return array{meta_key:string, image_id:int, shape:string}|null
	 */
	private function discover_image( int $term_id ): ?array {
		$all = get_term_meta( $term_id );
		if ( ! is_array( $all ) ) {
			return null;
		}

		foreach ( $all as $key => $values ) {
			$value = is_array( $values ) ? reset( $values ) : $values;
			$resolved = $this->resolve_value( $value );
			if ( $resolved && $resolved['image_id'] > 0 && wp_attachment_is_image( $resolved['image_id'] ) ) {
				return [
					'meta_key' => (string) $key,
					'image_id' => $resolved['image_id'],
					'shape'    => $resolved['shape'],
				];
			}
		}

		return null;
	}

	/**
	 * Figure out what a single meta value actually represents. Covers:
	 *   - integer or numeric string    → attachment ID
	 *   - serialized array             → unserialize + look for id/url
	 *   - url string                   → attachment_url_to_postid()
	 *
	 * @return array{image_id:int, shape:string}|null
	 */
	private function resolve_value( $value ): ?array {
		if ( is_numeric( $value ) ) {
			$id = (int) $value;
			if ( $id > 0 ) {
				return [ 'image_id' => $id, 'shape' => self::SHAPE_INT ];
			}
			return null;
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['id'] ) && (int) $value['id'] > 0 ) {
				return [ 'image_id' => (int) $value['id'], 'shape' => self::SHAPE_ARRAY ];
			}
			if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
				$id = attachment_url_to_postid( $value['url'] );
				if ( $id > 0 ) {
					return [ 'image_id' => $id, 'shape' => self::SHAPE_ARRAY ];
				}
			}
			return null;
		}

		if ( is_string( $value ) ) {
			// Some plugins pre-serialize even the simple shapes, try that.
			$maybe = maybe_unserialize( $value );
			if ( $maybe !== $value ) {
				return $this->resolve_value( $maybe );
			}

			// Plain URL string fallback.
			if ( 0 === strpos( $value, 'http' ) ) {
				$id = attachment_url_to_postid( $value );
				if ( $id > 0 ) {
					return [ 'image_id' => $id, 'shape' => self::SHAPE_URL ];
				}
			}
		}

		return null;
	}

	/**
	 * Convert an attachment id into the shape that will be written to
	 * the meta row, preserving whatever the site was using before.
	 */
	private function build_meta_value( int $image_id, string $shape ) {
		switch ( $shape ) {
			case self::SHAPE_ARRAY:
				$full = wp_get_attachment_image_src( $image_id, 'full' );
				return [
					'id'  => $image_id,
					'url' => $full ? $full[0] : '',
				];
			case self::SHAPE_URL:
				$full = wp_get_attachment_image_src( $image_id, 'full' );
				return $full ? $full[0] : '';
			case self::SHAPE_INT:
			default:
				return $image_id;
		}
	}
}
