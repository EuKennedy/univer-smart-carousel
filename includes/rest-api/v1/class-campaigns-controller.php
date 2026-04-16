<?php
/**
 * Campaigns controller (v1).
 *
 * Routes (namespace: usc/v1):
 *   GET    /campaigns                       List campaigns.
 *   POST   /campaigns                       Create campaign (settings + banners).
 *   GET    /campaigns/{id}                  Read campaign (with banners).
 *   PUT    /campaigns/{id}                  Update campaign (settings + banners).
 *   DELETE /campaigns/{id}                  Delete campaign.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Database\Campaign_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Campaigns_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'campaigns';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_items' ],
					'permission_callback' => [ $this, 'admin_permission' ],
					'args'                => [
						'search' => [ 'type' => 'string' ],
						'status' => [ 'type' => 'string' ],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'admin_permission' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
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
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'admin_permission' ],
				],
			]
		);
	}

	public function admin_permission(): bool {
		return current_user_can( USC_ADMIN_CAPABILITY );
	}

	/* -------------------------- HANDLERS -------------------------- */

	public function list_items( WP_REST_Request $request ) {
		$campaigns = Campaign_Repository::list_campaigns(
			[
				'search' => (string) $request->get_param( 'search' ),
				'status' => (string) $request->get_param( 'status' ),
			]
		);
		return new WP_REST_Response( $campaigns, 200 );
	}

	public function get_item( WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$campaign = Campaign_Repository::get_campaign( $id, true );
		if ( ! $campaign ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $campaign, 200 );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $this->extract_payload( $request );
		$id      = Campaign_Repository::create_campaign( $payload );
		if ( $id <= 0 ) {
			return new WP_Error( 'usc_create_failed', __( 'Failed to create campaign.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}

		$this->save_banners( $id, $payload );
		Campaign_Repository::flush_slug_cache();

		return new WP_REST_Response( Campaign_Repository::get_campaign( $id, true ), 201 );
	}

	/**
	 * PUT /campaigns/{id} is a partial update: any field absent from the
	 * payload is left untouched. This matters most for `banners` — passing
	 * just `{status:'active'}` must NOT wipe the banners or rename the
	 * campaign to "Untitled".
	 */
	public function update_item( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->extract_payload( $request );

		$ok = Campaign_Repository::update_campaign( $id, $payload );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}

		$this->save_banners( $id, $payload );
		Campaign_Repository::flush_slug_cache();

		return new WP_REST_Response( Campaign_Repository::get_campaign( $id, true ), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$ok = Campaign_Repository::delete_campaign( $id );
		if ( ! $ok ) {
			return new WP_Error( 'usc_delete_failed', __( 'Failed to delete campaign.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}

	/* -------------------------- HELPERS -------------------------- */

	private function extract_payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = $request->get_params();
		}
		return is_array( $json ) ? $json : [];
	}

	private function save_banners( int $campaign_id, array $payload ): void {
		// Banners may be passed grouped (banners.desktop / banners.mobile) or flat with a `device` field.
		$banners = $payload['banners'] ?? [];

		if ( isset( $banners['desktop'] ) && is_array( $banners['desktop'] ) ) {
			Campaign_Repository::replace_banners( $campaign_id, Campaign_Repository::DEVICE_DESKTOP, $banners['desktop'] );
		}
		if ( isset( $banners['mobile'] ) && is_array( $banners['mobile'] ) ) {
			Campaign_Repository::replace_banners( $campaign_id, Campaign_Repository::DEVICE_MOBILE, $banners['mobile'] );
		}
	}
}
