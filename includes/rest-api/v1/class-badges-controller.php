<?php
/**
 * Badges controller — thin wrapper around a WooCommerce attribute
 * taxonomy so marketing can rename + re-swatch from inside our admin
 * instead of walking into term.php every time.
 *
 * Endpoints:
 *   GET  /usc/v1/badges?taxonomy=pa_badge-ofertas
 *     → [ { id, name, slug, count, swatch: {meta_key, image:{…}} } ]
 *
 *   PUT  /usc/v1/badges/{term_id}
 *     body: { taxonomy?, name?, image_id? }
 *     → updated term row
 *
 * How the swatch meta key is discovered:
 *   Different swatch plugins use different meta keys. To stay
 *   compatible without hard-coding them all, we inspect every meta
 *   value on the term and pick the one that looks like an attachment
 *   id (numeric + `wp_attachment_is_image()` returns true). If nothing
 *   matches we fall back to `product_attribute_image`, which is the
 *   most common default. The caller can also pass `meta_key` explicitly
 *   on update to force a specific key.
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
	 * Ordered fallback list. Discovery walks these in order when no
	 * term meta actually resolves to an image — the first one wins.
	 */
	private const KNOWN_IMAGE_META_KEYS = [
		'product_attribute_image',
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

		// Swatch image
		if ( array_key_exists( 'image_id', $body ) ) {
			$image_id = (int) $body['image_id'];
			if ( $image_id > 0 && ! wp_attachment_is_image( $image_id ) ) {
				return new WP_Error(
					'usc_invalid_image',
					__( 'The selected attachment is not an image.', 'univer-smart-carousel' ),
					[ 'status' => 400 ]
				);
			}

			// Explicit key wins. Otherwise discover from existing term
			// metas, or fall back to the first known key.
			$meta_key = isset( $body['meta_key'] ) ? sanitize_key( $body['meta_key'] ) : $this->discover_image_meta_key( $term_id );

			if ( $image_id > 0 ) {
				update_term_meta( $term_id, $meta_key, $image_id );
			} else {
				// image_id === 0 means "remove swatch".
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
	 * @return array<string,mixed>
	 */
	private function hydrate( $term, string $taxonomy ): array {
		$swatch_meta_key = $this->discover_image_meta_key( (int) $term->term_id );
		$image_id        = (int) get_term_meta( (int) $term->term_id, $swatch_meta_key, true );
		$image_payload   = $image_id > 0 ? Campaign_Repository::image_payload( $image_id ) : null;

		return [
			'id'            => (int) $term->term_id,
			'name'          => (string) $term->name,
			'slug'          => (string) $term->slug,
			'taxonomy'      => $taxonomy,
			'count'         => (int) $term->count,
			'edit_url'      => esc_url_raw( admin_url( 'term.php?taxonomy=' . $taxonomy . '&tag_ID=' . (int) $term->term_id ) ),
			'swatch'        => [
				'meta_key' => $swatch_meta_key,
				'image_id' => $image_id,
				'image'    => $image_payload,
			],
		];
	}

	/**
	 * Walk the term's meta map looking for a value that resolves to an
	 * image attachment. First hit wins. Falls back to the first known
	 * key so caller-side update writes to something sensible even on a
	 * term that currently has no swatch.
	 */
	private function discover_image_meta_key( int $term_id ): string {
		$all = get_term_meta( $term_id );
		if ( is_array( $all ) ) {
			foreach ( $all as $key => $values ) {
				$value = is_array( $values ) ? reset( $values ) : $values;
				$maybe_id = (int) $value;
				if ( $maybe_id > 0 && wp_attachment_is_image( $maybe_id ) ) {
					return (string) $key;
				}
			}
		}
		return self::KNOWN_IMAGE_META_KEYS[0];
	}
}
