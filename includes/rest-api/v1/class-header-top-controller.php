<?php
/**
 * Header Top controller (v1).
 *
 *   GET    /usc/v1/header-top                 → { settings, slides }
 *   PUT    /usc/v1/header-top/settings        → partial settings update
 *   POST   /usc/v1/header-top/slides          → create slide
 *   PUT    /usc/v1/header-top/slides/{id}     → partial slide update
 *   DELETE /usc/v1/header-top/slides/{id}     → remove slide
 *   POST   /usc/v1/header-top/slides/{id}/duplicate
 *   POST   /usc/v1/header-top/slides/reorder  → { order: [id, ...] }
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
use Univer\SmartCarousel\Database\Header_Top_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Header_Top_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'header-top';

	public function register_routes(): void {
		// GET /header-top — full state (settings + slides).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => [ $this, 'permission_read' ],
			]
		);

		// PUT /header-top/settings — partial settings update.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/settings',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);

		// POST /header-top/slides — create.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slides',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_slide' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);

		// POST /header-top/slides/reorder.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slides/reorder',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reorder_slides' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);

		// PUT/DELETE /header-top/slides/{id}.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slides/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_slide' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_slide' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		// POST /header-top/slides/{id}/duplicate.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slides/(?P<id>\d+)/duplicate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'duplicate_slide' ],
				'permission_callback' => [ $this, 'permission_write' ],
			]
		);
	}

	/* -------------------------- PERMISSIONS -------------------------- */

	public function permission_read( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'read' );
	}

	public function permission_write( WP_REST_Request $request ) {
		return Api_Auth_Middleware::can_access( $request, 'write' );
	}

	/* -------------------------- HANDLERS -------------------------- */

	public function get_state() {
		return new WP_REST_Response(
			[
				'settings' => Header_Top_Repository::get_settings(),
				'slides'   => Header_Top_Repository::list_all(),
			],
			200
		);
	}

	public function update_settings( WP_REST_Request $request ) {
		$saved = Header_Top_Repository::update_settings( $this->payload( $request ) );
		return new WP_REST_Response( $saved, 200 );
	}

	public function create_slide( WP_REST_Request $request ) {
		$id = Header_Top_Repository::create( $this->payload( $request ) );
		if ( null === $id ) {
			return new WP_Error(
				'usc_header_top_create_failed',
				__( 'Could not create slide. Make sure the image_id points to a valid attachment.', 'univer-smart-carousel' ),
				[ 'status' => 400 ]
			);
		}
		return new WP_REST_Response( Header_Top_Repository::get( $id ), 201 );
	}

	public function update_slide( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! Header_Top_Repository::get( $id ) ) {
			return new WP_Error(
				'usc_not_found',
				__( 'Slide not found.', 'univer-smart-carousel' ),
				[ 'status' => 404 ]
			);
		}
		Header_Top_Repository::update( $id, $this->payload( $request ) );
		return new WP_REST_Response( Header_Top_Repository::get( $id ), 200 );
	}

	public function delete_slide( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! Header_Top_Repository::get( $id ) ) {
			return new WP_Error(
				'usc_not_found',
				__( 'Slide not found.', 'univer-smart-carousel' ),
				[ 'status' => 404 ]
			);
		}
		Header_Top_Repository::delete( $id );
		return new WP_REST_Response( [ 'deleted' => $id ], 200 );
	}

	public function duplicate_slide( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$new = Header_Top_Repository::duplicate( $id );
		if ( null === $new ) {
			return new WP_Error(
				'usc_not_found',
				__( 'Slide not found.', 'univer-smart-carousel' ),
				[ 'status' => 404 ]
			);
		}
		return new WP_REST_Response(
			[
				'duplicated' => true,
				'id'         => $new,
				'source_id'  => $id,
			],
			201
		);
	}

	public function reorder_slides( WP_REST_Request $request ) {
		$payload = $this->payload( $request );
		$order   = isset( $payload['order'] ) && is_array( $payload['order'] ) ? $payload['order'] : [];
		$ids     = array_values(
			array_filter(
				array_map(
					static function ( $v ) {
						return (int) $v;
					},
					$order
				),
				static function ( $v ) {
					return $v > 0;
				}
			)
		);
		Header_Top_Repository::reorder( $ids );
		return new WP_REST_Response( Header_Top_Repository::list_all(), 200 );
	}

	/* -------------------------- HELPERS -------------------------- */

	private function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = $request->get_params();
		}
		return is_array( $json ) ? $json : [];
	}
}
