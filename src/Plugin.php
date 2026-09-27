<?php
/**
 * WordPress entry point and hook wiring.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations;

use SprintIllustrations\Admin\Menu;
use SprintIllustrations\Cli\Command;

/**
 * Singleton that owns the service container and registers hooks.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Lazily built services.
	 *
	 * @var Services|null
	 */
	private ?Services $services = null;

	/**
	 * Boot on plugins_loaded.
	 *
	 * @return self
	 */
	public static function boot(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/**
	 * Current instance (boots if needed).
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::boot();
	}

	/**
	 * Service container, built on first use.
	 *
	 * @return Services
	 */
	public function services(): Services {
		if ( null === $this->services ) {
			$this->services = Services::create( $this->dir(), $this->user_manifests() );
		}

		return $this->services;
	}

	/**
	 * Plugin directory without trailing slash.
	 *
	 * @return string
	 */
	public function dir(): string {
		return untrailingslashit( plugin_dir_path( SPRINT_ILLUSTRATIONS_FILE ) );
	}

	/**
	 * User library root: wp-content/uploads/sprint-illustrations.
	 *
	 * @return string
	 */
	public function user_library_dir(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'sprint-illustrations';
	}

	/**
	 * Extra manifest files, filterable.
	 *
	 * @return array<string>
	 */
	private function user_manifests(): array {
		/**
		 * Filter the list of additional manifest.json files merged after the bundled library.
		 *
		 * @param array<string> $paths Absolute paths.
		 */
		$paths = apply_filters( 'sprint_illustrations_library_paths', [ $this->user_library_dir() . '/manifest.json' ] );

		return array_values( array_filter( (array) $paths, 'is_string' ) );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		if ( is_admin() ) {
			( new Menu( $this ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'sprint-illustrations', new Command( $this ) );
		}
	}
}
