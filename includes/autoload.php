<?php
/**
 * PSR-4-style autoloader using WordPress class file naming.
 *
 * Maps `Univer\SmartCarousel\Admin\Admin_Loader` ->
 *      `includes/admin/class-admin-loader.php`.
 *
 * @package Univer\SmartCarousel
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register( static function ( $class ) {
	$prefix = 'Univer\\SmartCarousel\\';

	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$parts    = explode( '\\', $relative );
	$class_n  = array_pop( $parts );

	$dir_segments = array_map( static function ( $segment ) {
		$segment = strtolower( $segment );
		$segment = str_replace( '_', '-', $segment );
		return $segment;
	}, $parts );

	$filename = 'class-' . str_replace( '_', '-', strtolower( $class_n ) ) . '.php';

	$path = __DIR__ . '/';
	if ( ! empty( $dir_segments ) ) {
		$path .= implode( '/', $dir_segments ) . '/';
	}
	$path .= $filename;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );
