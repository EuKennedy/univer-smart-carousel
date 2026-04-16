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
					'permission_callback' => [ $this, 'admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'admin_permission' ],
				],
			]
		);
	}

	public function admin_permission(): bool {
		return current_user_can( USC_ADMIN_CAPABILITY );
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
