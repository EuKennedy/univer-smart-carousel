<?php
/**
 * Per-banner REST controller (v1).
 *
 *   PUT    /usc/v1/banners/{bid}   Partial update — toggle is_active,
 *                                  change link, alt, target, sort, group.
 *   DELETE /usc/v1/banners/{bid}   Remove the banner.
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

final class Banners_Controller {

	private string $namespace = USC_REST_NAMESPACE;

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/banners/(?P<bid>\d+)',
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
	}

	public function permission_write( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'write' );
	}

	public function update_item( WP_REST_Request $request ) {
		$bid     = (int) $request['bid'];
		$payload = $this->payload( $request );
		$ok      = Campaign_Repository::update_banner( $bid, $payload );
		if ( ! $ok ) {
			return new WP_Error( 'usc_update_failed', __( 'Failed to update banner.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( [ 'updated' => true, 'id' => $bid ], 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$bid = (int) $request['bid'];
		$ok  = Campaign_Repository::delete_banner( $bid );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Banner not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $bid ], 200 );
	}

	private function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = $request->get_params();
		}
		return is_array( $json ) ? $json : [];
	}
}
