<?php
/**
 * The plugin's own library (assets/): kept pieces and saved templates are written here so they ship
 * with the plugin to every site it is installed on.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Cli\BuildReport;
use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Plugin;

/**
 * Writes only when the plugin folder is writable (callers fall back to the site library otherwise).
 * Files written here are part of the plugin's git project and need committing to reach a release.
 */
final class BundledLibrary {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Whether pieces can be added to the plugin.
	 *
	 * @return bool
	 */
	public function writable(): bool {
		$assets = $this->assets();

		return $this->enabled() && wp_is_writable( $assets . '/pieces-src' ) && wp_is_writable( $assets . '/pieces' ) && wp_is_writable( $assets . '/manifest.json' );
	}

	/**
	 * Whether templates can be added to the plugin.
	 *
	 * @return bool
	 */
	public function templates_writable(): bool {
		return $this->enabled() && wp_is_writable( $this->templates_dir() );
	}

	/**
	 * Only a development copy of the plugin takes new pieces and templates: on a live site a plugin
	 * update would delete them, so they stay in the site library (uploads) there.
	 *
	 * Enabled when SPRINT_ILLUSTRATIONS_BUNDLE_TO_PLUGIN is true, or — without that constant — when the
	 * plugin folder is a git checkout or the environment is local/development. DISALLOW_FILE_MODS wins.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return false;
		}
		if ( defined( 'SPRINT_ILLUSTRATIONS_BUNDLE_TO_PLUGIN' ) ) {
			return (bool) SPRINT_ILLUSTRATIONS_BUNDLE_TO_PLUGIN;
		}

		return is_dir( rtrim( wp_normalize_path( $this->plugin->dir() ), '/' ) . '/.git' )
			|| in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}

	/**
	 * Add a piece source and rebuild the bundled manifest; the source is removed again if it does not build.
	 *
	 * @param string $category Category folder.
	 * @param string $name     File name without .svg.
	 * @param string $source   Source SVG to copy.
	 * @return BuildReport|string Report, or why it failed.
	 */
	public function add_piece( string $category, string $name, string $source ): BuildReport|string {
		$target = $this->assets() . '/pieces-src/' . $category . '/' . $name . '.svg';
		if ( is_file( $target ) ) {
			return sprintf( '"%s" is already in the plugin library.', $name );
		}

		wp_mkdir_p( dirname( $target ) );
		if ( ! copy( $source, $target ) ) {
			return 'The piece could not be copied into the plugin.';
		}

		$report = $this->rebuild();
		if ( $report->errors ) {
			wp_delete_file( $target );
			$this->rebuild();
			return implode( ' ', $report->errors );
		}

		return $report;
	}

	/**
	 * Rebuild assets/pieces and assets/manifest.json from assets/pieces-src (same as bin/build-manifest.php).
	 *
	 * @return BuildReport
	 */
	public function rebuild(): BuildReport {
		return ( new ManifestBuilder( $this->plugin->services()->sanitizer, new NullPrompter() ) )->build( $this->assets() . '/pieces-src', $this->assets() );
	}

	/**
	 * Folder of bundled templates.
	 *
	 * @return string
	 */
	public function templates_dir(): string {
		return $this->assets() . '/templates';
	}

	/**
	 * The plugin's assets folder.
	 *
	 * @return string
	 */
	private function assets(): string {
		return rtrim( wp_normalize_path( $this->plugin->dir() ), '/' ) . '/assets';
	}
}
