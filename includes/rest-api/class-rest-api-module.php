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
	}
}
