<?php
/**
 * Plugin orchestrator (singleton).
 *
 * @package Univer\SmartCarousel
 */

namespace Univer\SmartCarousel;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Subsystem references (kept to avoid GC and to expose for tests/hooks).
	 *
	 * @var array<string,object>
	 */
	private $subsystems = [];

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all subsystems.
	 */
	private function __construct() {
		// Run database upgrades opportunistically (cheap, idempotent).
		add_action( 'init', [ Database\Database_Installer::class, 'maybe_upgrade' ], 1 );

		$this->subsystems['admin']     = new Admin\Admin_Loader();
		$this->subsystems['frontend']  = new Frontend\Frontend_Loader();
		$this->subsystems['shortcode'] = new Shortcode\Shortcode_Handler();
		$this->subsystems['rest']      = new Rest_Api\Rest_Api_Module();

		load_plugin_textdomain( 'univer-smart-carousel', false, dirname( USC_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Subsystem accessor.
	 */
	public function get( string $key ) {
		return $this->subsystems[ $key ] ?? null;
	}

	private function __clone() {}
	public function __wakeup() {
		throw new \RuntimeException( 'Cannot unserialize Plugin singleton.' );
	}
}
