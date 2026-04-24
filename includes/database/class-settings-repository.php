<?php
/**
 * Plugin-wide settings repository.
 *
 * Persisted as a single autoloaded wp_option (`usc_global_settings`) holding
 * a JSON-decoded associative array. Use this class as the only path in/out —
 * sanitization and defaults live here so they can't drift between callers.
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel\Database;

defined( 'ABSPATH' ) || exit;

final class Settings_Repository {

	private const OPTION_KEY = 'usc_global_settings';

	public const LANGUAGE_EN    = 'en';
	public const LANGUAGE_PT_BR = 'pt-br';

	private const ALLOWED_LANGUAGES = [ self::LANGUAGE_EN, self::LANGUAGE_PT_BR ];

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'language' => self::detect_default_language(),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return self::sanitize( array_merge( self::defaults(), $stored ) );
	}

	/**
	 * Partial save — fields absent from $input are preserved.
	 */
	public static function update_settings( array $input ): array {
		$current = self::get_settings();
		$merged  = array_merge( $current, array_intersect_key( $input, $current ) );
		$clean   = self::sanitize( $merged );
		update_option( self::OPTION_KEY, $clean, true );
		do_action( 'usc_content_changed' );
		return $clean;
	}

	/**
	 * Convenience accessor.
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::get_settings();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	private static function sanitize( array $input ): array {
		$out             = [];
		$out['language'] = self::sanitize_language( $input['language'] ?? '' );
		return $out;
	}

	private static function sanitize_language( $value ): string {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, self::ALLOWED_LANGUAGES, true ) ) {
				return $value;
			}
		}
		return self::detect_default_language();
	}

	private static function detect_default_language(): string {
		$site = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		return ( 0 === strpos( $site, 'pt' ) ) ? self::LANGUAGE_PT_BR : self::LANGUAGE_EN;
	}
}
