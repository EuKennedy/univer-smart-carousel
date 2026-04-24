<?php
/**
 * Mosaics controller (v1).
 *
 * Routes (namespace: usc/v1):
 *   GET    /mosaics                          List mosaics.
 *   POST   /mosaics                          Create.
 *   GET    /mosaics/{id}                     Read with items.
 *   PUT    /mosaics/{id}                     Partial update.
 *   DELETE /mosaics/{id}                     Delete + cascade items.
 *   GET    /mosaics/by-slug/{slug}           AI-friendly: look up by slug.
 *   PUT    /mosaics/by-slug/{slug}           Partial update by slug.
 *   DELETE /mosaics/by-slug/{slug}           Delete by slug.
 *   POST   /mosaics/{id}/activate            status = active.
 *   POST   /mosaics/{id}/deactivate          status = paused.
 *   GET    /mosaics/{id}/items               List items.
 *   POST   /mosaics/{id}/items               Append an item.
 *   POST   /mosaics/{id}/items/reorder       Bulk reorder. { order: [ids] }
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Rest_Api\V1;

use Univer\SmartCarousel\Api\Api_Auth_Middleware;
use Univer\SmartCarousel\Database\Mosaic_Repository;
use Univer\SmartCarousel\Database\Mosaic_Item_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Mosaics_Controller {

	private string $namespace = USC_REST_NAMESPACE;
	private string $rest_base = 'mosaics';

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/items',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_items_for_mosaic' ],
					'permission_callback' => [ $this, 'permission_read' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_item' ],
					'permission_callback' => [ $this, 'permission_write' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/items/reorder',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reorder_items' ],
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

	/* -------------------------- HANDLERS -------------------------- */

	public function list_items( WP_REST_Request $request ) {
		$mosaics = Mosaic_Repository::list_mosaics(
			[
				'search' => (string) $request->get_param( 'search' ),
				'status' => (string) $request->get_param( 'status' ),
			]
		);
		return new WP_REST_Response( $mosaics, 200 );
	}

	public function get_item( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$mosaic = Mosaic_Repository::get( $id, true );
		if ( ! $mosaic ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $mosaic, 200 );
	}

	public function create_item( WP_REST_Request $request ) {
		$payload = $this->payload( $request );
		$id      = Mosaic_Repository::create( $payload );
		if ( $id <= 0 ) {
			return new WP_Error( 'usc_create_failed', __( 'Failed to create mosaic.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( Mosaic_Repository::get( $id, true ), 201 );
	}

	public function update_item( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->payload( $request );
		$ok      = Mosaic_Repository::update( $id, $payload );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( Mosaic_Repository::get( $id, true ), 200 );
	}

	public function delete_item( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$ok = Mosaic_Repository::delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'usc_delete_failed', __( 'Failed to delete mosaic.', 'univer-smart-carousel' ), [ 'status' => 500 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}

	public function get_by_slug( WP_REST_Request $request ) {
		$slug   = (string) $request['slug'];
		$mosaic = Mosaic_Repository::get_by_slug( $slug, true );
		if ( ! $mosaic ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $mosaic, 200 );
	}

	public function update_by_slug( WP_REST_Request $request ) {
		$slug   = (string) $request['slug'];
		$mosaic = Mosaic_Repository::get_by_slug( $slug );
		if ( ! $mosaic ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		$request->set_param( 'id', $mosaic['id'] );
		return $this->update_item( $request );
	}

	public function delete_by_slug( WP_REST_Request $request ) {
		$slug   = (string) $request['slug'];
		$mosaic = Mosaic_Repository::get_by_slug( $slug );
		if ( ! $mosaic ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		$request->set_param( 'id', $mosaic['id'] );
		return $this->delete_item( $request );
	}

	public function activate( WP_REST_Request $request ) {
		return $this->set_status( (int) $request['id'], Mosaic_Repository::STATUS_ACTIVE );
	}

	public function deactivate( WP_REST_Request $request ) {
		return $this->set_status( (int) $request['id'], Mosaic_Repository::STATUS_PAUSED );
	}

	private function set_status( int $id, string $status ) {
		$ok = Mosaic_Repository::update( $id, [ 'status' => $status ] );
		if ( ! $ok ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( Mosaic_Repository::get( $id, true ), 200 );
	}

	public function list_items_for_mosaic( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return new WP_REST_Response( Mosaic_Item_Repository::list_for_mosaic( $id ), 200 );
	}

	public function add_item( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->payload( $request );
		$item_id = Mosaic_Item_Repository::add( $id, $payload );
		if ( ! $item_id ) {
			return new WP_Error(
				'usc_create_failed',
				__( 'Failed to add item. Check the image_id and that the mosaic exists.', 'univer-smart-carousel' ),
				[ 'status' => 400 ]
			);
		}
		return new WP_REST_Response( Mosaic_Repository::get( $id, true ), 201 );
	}

	public function reorder_items( WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = $this->payload( $request );
		$mosaic  = Mosaic_Repository::get( $id );
		if ( ! $mosaic ) {
			return new WP_Error( 'usc_not_found', __( 'Mosaic not found.', 'univer-smart-carousel' ), [ 'status' => 404 ] );
		}
		$order = isset( $payload['order'] ) && is_array( $payload['order'] ) ? $payload['order'] : [];
		$ids   = array_values(
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
		Mosaic_Item_Repository::reorder( $id, $ids );
		return new WP_REST_Response( Mosaic_Repository::get( $id, true ), 200 );
	}

	private function payload( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = $request->get_params();
		}
		return is_array( $json ) ? $json : [];
	}
}
