<?php
/**
 * Settings controller (v1).
 *
 * GET /usc/v1/settings   - read plugin-wide settings
 * PUT /usc/v1/settings   - partial update
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
use Univer\SmartCarousel\Cache\Cache_Purger;
use Univer\SmartCarousel\Database\Settings_Repository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Settings_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'settings';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'permission_read' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		// Manual "flush every cache we know about" button, driven from
		// the Settings tab. Admin-only via cookie auth — a leaked
		// Bearer API key should not be able to nuke a site's cache.
		register_rest_route(
			$this->namespace,
			'/cache/flush',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'flush_cache' ],
				'permission_callback' => [ $this, 'permission_admin_cookie' ],
			]
		);
	}

	public function permission_read( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'read' );
	}

	public function permission_write( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'write' );
	}

	/**
	 * Cookie-only gate. Same reasoning as the API-keys endpoints: a
	 * cache flush is a destructive site-wide action and shouldn't be
	 * reachable via a leaked Bearer token.
	 */
	public function permission_admin_cookie( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'usc_unauthenticated',
				__( 'You must be signed in to flush caches.', 'univer-smart-carousel' ),
				[ 'status' => 401 ]
			);
		}
		if ( ! current_user_can( USC_ADMIN_CAPABILITY ) ) {
			return new \WP_Error(
				'usc_forbidden',
				__( 'Only administrators can flush caches.', 'univer-smart-carousel' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	public function flush_cache() {
		Cache_Purger::purge_all( true );
		return new WP_REST_Response(
			[
				'flushed' => true,
				'at'      => current_time( 'mysql', true ),
			],
			200
		);
	}

	public function get_item() {
		return new WP_REST_Response( Settings_Repository::get_settings(), 200 );
	}

	public function update_item( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		$saved = Settings_Repository::update_settings( is_array( $body ) ? $body : [] );
		return new WP_REST_Response( $saved, 200 );
	}
}
