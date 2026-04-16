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

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
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
					'permission_callback' => [ $this, 'permission_read' ],
					'args'                => [
						'search' => [ 'type' => 'string' ],
						'status' => [ 'type' => 'string' ],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permission_write' ],
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
					'permission_callback' => [ $this, 'permission_read' ],
				],
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

		// AI-friendly verbose endpoints. Same data, different shape — these
		// are the ones IAs and integrations should reach for first.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/by-slug/(?P<slug>[a-z0-9-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_by_slug' ],
					'permission_callback' => [ $this, 'permission_read' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_by_slug' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_by_slug' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/activate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'activate' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/deactivate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'deactivate' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);

		// Per-banner CRUD so callers can append/replace one banner without
		// having to re-send the whole banner list.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/banners',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_banners' ],
					'permission_callback' => [ $this, 'permission_read' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_banner' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/banners/(?P<bid>\d+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_banner' ],
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

	/* ------------------- AI-FRIENDLY VERBOSE ENDPOINTS ------------------ */

	public function get_by_slug( WP_REST_Request $request ) {
		$slug     = (string) $request['slug'];
		$campaign = Campaign_Repository::get_campaign_by_slug( $slug, true );
		if ( ! $campaign ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $campaign, 200 );
	}

	public function update_by_slug( WP_REST_Request $request ) {
		$slug     = (string) $request['slug'];
		$campaign = Campaign_Repository::get_campaign_by_slug( $slug );
		if ( ! $campaign ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		$request->set_param( 'id', $campaign['id'] );
		return $this->update_item( $request );
	}

	public function delete_by_slug( WP_REST_Request $request ) {
		$slug     = (string) $request['slug'];
		$campaign = Campaign_Repository::get_campaign_by_slug( $slug );
		if ( ! $campaign ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		$request->set_param( 'id', $campaign['id'] );
		return $this->delete_item( $request );
	}

	public function activate( WP_REST_Request $request ) {
		return $this->set_status( (int) $request['id'], Campaign_Repository::STATUS_ACTIVE );
	}

	public function deactivate( WP_REST_Request $request ) {
		return $this->set_status( (int) $request['id'], Campaign_Repository::STATUS_PAUSED );
	}

	private function set_status( int $id, string $status ) {
		$ok = Campaign_Repository::update_campaign( $id, [ 'status' => $status ] );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( Campaign_Repository::get_campaign( $id, true ), 200 );
	}

	public function list_banners( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$device = (string) $request->get_param( 'device' );
		if ( '' !== $device ) {
			$banners = Campaign_Repository::list_banners( $id, $device );
		} else {
			$banners = Campaign_Repository::list_banners( $id );
		}
		return new WP_REST_Response( $banners, 200 );
	}

	public function add_banner( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->extract_payload( $request );

		$campaign = Campaign_Repository::get_campaign( $id, true );
		if ( ! $campaign ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}

		$device   = Campaign_Repository::sanitize_device( $payload['device'] ?? 'desktop' );
		$existing = array_values(
			array_filter(
				$campaign['banners'] ?? [],
				static function ( $b ) use ( $device ) {
					return ( $b['device'] ?? '' ) === $device;
				}
			)
		);

		$existing[] = [
			'image_id'    => isset( $payload['image_id'] ) ? (int) $payload['image_id'] : 0,
			'link_url'    => $payload['link_url'] ?? '',
			'link_target' => $payload['link_target'] ?? '_self',
			'link_rel'    => $payload['link_rel'] ?? '',
			'alt_text'    => $payload['alt_text'] ?? '',
		];

		Campaign_Repository::replace_banners( $id, $device, $existing );

		return new WP_REST_Response( Campaign_Repository::get_campaign( $id, true ), 201 );
	}

	public function delete_banner( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$bid = (int) $request['bid'];

		$campaign = Campaign_Repository::get_campaign( $id, true );
		if ( ! $campaign ) {
			return new WP_Error( 'usc_not_found', __( 'Campaign not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}

		// Group remaining banners back by device, then re-write the device
		// whose list actually changed.
		$by_device = [ 'desktop' => [], 'mobile' => [] ];
		$found     = false;
		foreach ( $campaign['banners'] ?? [] as $b ) {
			if ( (int) $b['id'] === $bid ) {
				$found = true;
				continue;
			}
			$by_device[ $b['device'] ][] = $b;
		}

		if ( ! $found ) {
			return new WP_Error( 'usc_not_found', __( 'Banner not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}

		Campaign_Repository::replace_banners( $id, 'desktop', $by_device['desktop'] );
		Campaign_Repository::replace_banners( $id, 'mobile', $by_device['mobile'] );

		return new WP_REST_Response( Campaign_Repository::get_campaign( $id, true ), 200 );
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
