<?php
/**
 * Bearer-token auth middleware for the /usc/v1 namespace.
 *
 * How it composes with WordPress's existing auth:
 *   - Cookie + nonce auth (logged-in admin browsing wp-admin) keeps
 *     working untouched.
 *   - When a request arrives with `Authorization: Bearer usc_…` and
 *     hits a route under our namespace, we validate the token, attach
 *     the matching API-key record to a static context, and tell WP
 *     "yes, this is authenticated as the user who created the key".
 *   - Routes' `permission_callback` should call
 *     `Api_Auth_Middleware::can_access($request, 'read'|'write')`
 *     instead of `current_user_can(...)` directly. That helper handles
 *     both auth paths and enforces scope.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Api;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Api_Auth_Middleware {

	private const NAMESPACE_PREFIX = '/wp-json/' . USC_REST_NAMESPACE;

	/**
	 * The currently-authenticated API key record (if any). Reset per request.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $current_key = null;

	public function __construct() {
		// Run very early so we can plant the user identity before
		// permission_callback fires.
		add_filter( 'determine_current_user', [ $this, 'maybe_resolve_user' ], 30 );

		// Reset per request — important under FastCGI / FPM where the
		// process can be reused.
		add_filter( 'rest_pre_dispatch', [ $this, 'reset_context' ], 1, 3 );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function current_key(): ?array {
		return self::$current_key;
	}

	public static function is_bearer_authenticated(): bool {
		return null !== self::$current_key;
	}

	public function reset_context( $result, $server, $request ) {
		// Only blow away the context for requests that aren't ours;
		// for ours, maybe_resolve_user will set it again.
		if ( ! self::is_usc_request() ) {
			self::$current_key = null;
		}
		return $result;
	}

	/**
	 * If the incoming request carries a Bearer token AND it's targeting
	 * our namespace AND the token is valid, sign the corresponding WP
	 * user in for the duration of this request.
	 */
	public function maybe_resolve_user( $user_id ) {
		// Don't override an already-authenticated user (cookie auth winning
		// is fine; both paths get the same access via can_access).
		if ( $user_id ) {
			return $user_id;
		}

		if ( ! self::is_usc_request() ) {
			return $user_id;
		}

		$token = self::extract_bearer_token();
		if ( ! $token ) {
			return $user_id;
		}

		$key = Api_Key_Repository::authenticate( $token, self::client_ip() );
		if ( ! $key ) {
			return $user_id;
		}

		self::$current_key = $key;

		// Sign the request in as the WP user who created the key.
		// permission_callback can still gate by scope on top of this.
		return (int) $key['created_by'];
	}

	/**
	 * Single permission check for every /usc/v1 route. Pass either 'read'
	 * (default) or 'write' depending on what the route is doing.
	 *
	 * @return true|WP_Error
	 */
	public static function can_access( WP_REST_Request $request, string $required = 'read' ) {
		$required = Api_Key_Repository::sanitize_scope( $required );

		// Cookie / capability path — preserve existing behaviour for the
		// React admin app and any other in-WP caller.
		if ( current_user_can( USC_ADMIN_CAPABILITY ) ) {
			// If they ALSO sent a Bearer token, still enforce the scope on
			// it — defense in depth against a too-broad cookie session.
			if ( self::is_bearer_authenticated() ) {
				return self::scope_allows( self::$current_key['scope'], $required );
			}
			return true;
		}

		// Bearer-only path
		if ( ! self::is_bearer_authenticated() ) {
			return new WP_Error(
				'usc_unauthenticated',
				__( 'Authentication required. Send an Authorization: Bearer token, or sign in as an administrator.', 'univer-smart-carousel' ),
				[ 'status' => 401 ]
			);
		}

		return self::scope_allows( self::$current_key['scope'], $required );
	}

	/**
	 * @return true|WP_Error
	 */
	private static function scope_allows( string $key_scope, string $required ) {
		// 'write' implies 'read'.
		if ( Api_Key_Repository::SCOPE_WRITE === $key_scope ) {
			return true;
		}
		if ( Api_Key_Repository::SCOPE_READ === $key_scope && Api_Key_Repository::SCOPE_READ === $required ) {
			return true;
		}
		return new WP_Error(
			'usc_insufficient_scope',
			sprintf(
				/* translators: 1: required scope */
				__( 'This API key does not have %s scope.', 'univer-smart-carousel' ),
				$required
			),
			[ 'status' => 403 ]
		);
	}

	private static function is_usc_request(): bool {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return false !== strpos( $uri, self::NAMESPACE_PREFIX );
	}

	private static function extract_bearer_token(): ?string {
		$header = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// Some CGI / Apache setups stash it here.
			$header = (string) wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		} elseif ( function_exists( 'getallheaders' ) ) {
			$all = getallheaders();
			if ( isset( $all['Authorization'] ) ) {
				$header = (string) $all['Authorization'];
			} elseif ( isset( $all['authorization'] ) ) {
				$header = (string) $all['authorization'];
			}
		}

		if ( '' === $header ) {
			return null;
		}

		if ( ! preg_match( '/^Bearer\s+(.+)$/i', trim( $header ), $m ) ) {
			return null;
		}

		return trim( $m[1] );
	}

	private static function client_ip(): ?string {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return (string) wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}
		return null;
	}

	/**
	 * Convenience: maps HTTP method to the scope it requires.
	 */
	public static function scope_for_method( string $method ): string {
		$method = strtoupper( trim( $method ) );
		return in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ], true )
			? Api_Key_Repository::SCOPE_READ
			: Api_Key_Repository::SCOPE_WRITE;
	}
}
