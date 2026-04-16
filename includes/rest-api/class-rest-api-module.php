<?php
/**
 * REST API module — registers all controllers.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api;

defined( 'ABSPATH' ) || exit;

final class Rest_Api_Module {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new V1\Campaigns_Controller() )->register_routes();
		( new V1\Settings_Controller() )->register_routes();
		( new V1\Api_Keys_Controller() )->register_routes();
		( new V1\Discover_Controller() )->register_routes();
		( new V1\Groups_Controller() )->register_routes();
		( new V1\Banners_Controller() )->register_routes();

		// Friendly alias: /carousels mirrors /campaigns. The product calls
		// them carousels in the UI; the data layer kept the original
		// "campaign" name for backwards compatibility. Both work.
		$this->register_carousels_alias();
	}

	private function register_carousels_alias(): void {
		$base = USC_REST_NAMESPACE;
		add_filter(
			'rest_pre_dispatch',
			static function ( $result, $server, $request ) use ( $base ) {
				$route = $request->get_route();
				if ( 0 !== strpos( $route, '/' . $base . '/carousels' ) ) {
					return $result;
				}
				// Rewrite the route to /campaigns/* and dispatch again.
				$rewritten = '/' . $base . '/campaigns' . substr( $route, strlen( '/' . $base . '/carousels' ) );
				$request->set_route( $rewritten );
				return $server->dispatch( $request );
			},
			10,
			3
		);
	}
}
