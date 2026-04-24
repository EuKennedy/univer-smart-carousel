<?php
/**
 * Mosaic items controller (v1).
 *
 *   PUT    /usc/v1/mosaic-items/{id}            Partial update of one item
 *                                               (name, image_id, link,
 *                                               target, alt, col_span,
 *                                               row_span, aspect_ratio,
 *                                               is_active, sort_order).
 *   DELETE /usc/v1/mosaic-items/{id}            Delete a single item.
 *   POST   /usc/v1/mosaic-items/{id}/duplicate  Clone into the same mosaic.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
use Univer\SmartCarousel\Database\Mosaic_Item_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Mosaic_Items_Controller {

	private string $namespace = USC_REST_NAMESPACE;

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/mosaic-items/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mosaic-items/(?P<id>\d+)/duplicate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'duplicate_item' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);
	}

	public function permission_write( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'write' );
	}

	public function update_item( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->payload( $request );
		$ok      = Mosaic_Item_Repository::update( $id, $payload );
		if ( ! $ok ) {
			return new WP_Error( 'usc_update_failed', __( 'Failed to update item.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( [ 'updated' => true, 'id' => $id ], 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$ok = Mosaic_Item_Repository::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Item not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}

	public function duplicate_item( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$new_id = Mosaic_Item_Repository::duplicate( $id );
		if ( ! $new_id ) {
			return new WP_Error( 'usc_not_found', __( 'Item not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'duplicated' => true, 'id' => $new_id, 'source_id' => $id ], 201 );
	}

	private function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = $request->get_params();
		}
		return is_array( $json ) ? $json : [];
	}
}
