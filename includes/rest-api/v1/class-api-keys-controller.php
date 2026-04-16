<?php
/**
 * API keys controller (v1).
 *
 * GET    /usc/v1/api-keys           List all keys (without secrets).
 * POST   /usc/v1/api-keys           Create a new key. Plain key returned ONCE.
 * POST   /usc/v1/api-keys/{id}/revoke   Mark a key as revoked.
 * DELETE /usc/v1/api-keys/{id}      Permanently delete a key.
 *
 * Note: management of keys is intentionally cookie-only (admin in
 * wp-admin). A Bearer token CAN'T mint new keys — that would let a
 * compromised integration self-extend.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Key_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Api_Keys_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'api-keys';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_items' ],
					'permission_callback' => [ $this, 'cookie_only' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'cookie_only' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/revoke',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'revoke' ],
				'permission_callback' => [ $this, 'cookie_only' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'cookie_only' ],
			]
		);
	}

	/**
	 * Locked to cookie-authenticated admins. Bearer tokens deliberately
	 * cannot manage keys — preventing key escalation if a token leaks.
	 */
	public function cookie_only(): bool {
		return current_user_can( USC_ADMIN_CAPABILITY ) && ! \Univer\SmartCarousel\Api\Api_Auth_Middleware::is_bearer_authenticated();
	}

	public function list_items() {
		return new WP_REST_Response( Api_Key_Repository::list_all(), 200 );
	}

	public function create_item( WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}

		$name  = isset( $body['name'] ) ? (string) $body['name'] : '';
		$scope = isset( $body['scope'] ) ? (string) $body['scope'] : Api_Key_Repository::SCOPE_READ;

		$result = Api_Key_Repository::create( $name, $scope, get_current_user_id() );
		if ( ! $result ) {
			return new WP_Error( 'usc_create_failed', __( 'Failed to create API key.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}

		// 201 with the plain key included exactly once. After this response
		// is sent, the secret is unrecoverable.
		return new WP_REST_Response(
			[
				'key'    => $result['key'],
				'record' => $result['record'],
				'notice' => __( 'Copy this key now — it will not be shown again.', 'univer-smart-carousel' ),
			],
			201
		);
	}

	public function revoke( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$ok = Api_Key_Repository::revoke( $id );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'API key not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( Api_Key_Repository::get( $id ), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$ok = Api_Key_Repository::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'API key not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}
}
