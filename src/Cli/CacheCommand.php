<?php
/**
 * WP-CLI cache commands.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Plugin;

/**
 * Inspect and clear the illustration cache.
 */
final class CacheCommand {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Show the number and total size of cached illustrations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations cache stats
	 */
	public function stats(): void {
		$cache = $this->plugin->cache();
		$stats = $cache->stats();

		\WP_CLI::line( sprintf( '%d files, %s, in %s', $stats['files'], size_format( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : '0 B', $cache->directory() ) );

		if ( ! $cache->writable() ) {
			\WP_CLI::warning( 'The cache folder is not writable, so illustrations are recomposed on every request.' );
		}
	}

	/**
	 * Delete every cached illustration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sprint-illustrations cache purge
	 */
	public function purge(): void {
		\WP_CLI::success( sprintf( 'Removed %d cached illustrations.', $this->plugin->cache()->purge() ) );
	}
}
