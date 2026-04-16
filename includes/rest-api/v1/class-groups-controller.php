<?php
/**
 * Banner groups REST controller (v1).
 *
 *   GET    /usc/v1/campaigns/{id}/groups          List groups (?device filter).
 *   POST   /usc/v1/campaigns/{id}/groups          Create a group inside a campaign.
 *   GET    /usc/v1/groups/{gid}                   Get one group.
 *   PUT    /usc/v1/groups/{gid}                   Partial update (name, is_active, sort_order).
 *   DELETE /usc/v1/groups/{gid}                   Delete + cascade banners.
 *   POST   /usc/v1/groups/{gid}/banners           Append one banner to the group.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
use Univer\SmartCarousel\Database\Banner_Group_Repository;
use Univer\SmartCarousel\Database\Campaign_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Groups_Controller {

	private string $namespace = USC_REST_NAMESPACE;

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/campaigns/(?P<id>\d+)/groups',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_in_campaign' ],
					'permission_callback' => [ $this, 'permission_read' ],
					'args'                => [ 'device' => [ 'type' => 'string' ] ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/groups/(?P<gid>\d+)',
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

		register_rest_route(
			$this->namespace,
			'/groups/(?P<gid>\d+)/banners',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_banner' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);
	}

	public function permission_read( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'read' );
	}
	public function permission_write( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'write' );
	}

	public function list_in_campaign( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$device = (string) $request->get_param( 'device' );
		$device = '' !== $device ? $device : null;
		return new WP_REST_Response( Banner_Group_Repository::list_for_campaign( $id, $device ), 200 );
	}

	public function create( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->payload( $request );

		$device = Campaign_Repository::sanitize_device( $payload['device'] ?? 'desktop' );
		$name   = (string) ( $payload['name'] ?? '' );

		$gid = Banner_Group_Repository::create( $id, $device, $name );
		if ( ! $gid ) {
			return new WP_Error( 'usc_create_failed', __( 'Failed to create group.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( Banner_Group_Repository::get( $gid ), 201 );
	}

	public function get_item( WP_REST_Request $request ) {
		$gid   = (int) $request['gid'];
		$group = Banner_Group_Repository::get( $gid );
		if ( ! $group ) {
			return new WP_Error( 'usc_not_found', __( 'Group not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $group, 200 );
	}

	public function update_item( WP_REST_Request $request ) {
		$gid     = (int) $request['gid'];
		$payload = $this->payload( $request );
		$ok      = Banner_Group_Repository::update( $gid, $payload );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Group not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( Banner_Group_Repository::get( $gid ), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$gid = (int) $request['gid'];
		$ok  = Banner_Group_Repository::delete( $gid );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Group not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $gid ], 200 );
	}

	public function add_banner( WP_REST_Request $request ) {
		$gid     = (int) $request['gid'];
		$payload = $this->payload( $request );

		$bid = Campaign_Repository::add_banner_to_group( $gid, $payload );
		if ( ! $bid ) {
			return new WP_Error( 'usc_create_failed', __( 'Failed to add banner. Make sure the group exists and image_id is valid.', 'univer-smart-carousel' ), [ 'status' => 400 ] );
		}

		$group = Banner_Group_Repository::get( $gid );
		return new WP_REST_Response( Campaign_Repository::get_campaign( (int) $group['campaign_id'], true ), 201 );
	}

	private function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = $request->get_params();
		}
		return is_array( $json ) ? $json : [];
	}
}
