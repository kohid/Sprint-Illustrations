<?php
/**
 * WP-CLI: manage custom pieces in the site library.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Library\ManifestEditor;
use SprintIllustrations\Plugin;

/**
 * Only pieces under uploads/sprint-illustrations can be changed; bundled pieces are read-only.
 */
final class PieceCommand {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Remove a custom piece (manifest entry, built file and inbox source).
	 *
	 * ## OPTIONS
	 *
	 * <piece-id>
	 * : Piece ID, e.g. obj-taxi.
	 *
	 * [--yes]
	 * : Skip the confirmation.
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function remove( array $args, array $assoc_args ): void {
		$id      = (string) ( $args[0] ?? '' );
		$library = rtrim( wp_normalize_path( $this->plugin->user_library_dir() ), '/' );
		$piece   = $this->plugin->services()->manifest->get( $id );

		if ( null === $piece ) {
			\WP_CLI::error( sprintf( 'Piece "%s" is not in the library.', $id ) );
		}
		if ( ! str_starts_with( wp_normalize_path( $piece->path ), $library . '/' ) ) {
			\WP_CLI::error( sprintf( '"%s" is a bundled piece; only custom pieces can be removed.', $id ) );
		}

		\WP_CLI::confirm( sprintf( 'Remove custom piece "%s" (%s)? Saved designs that use it will pick another piece.', $id, $piece->label ), $assoc_args );

		$manifest_file = $library . '/manifest.json';
		$manifest      = json_decode( (string) file_get_contents( $manifest_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local library file.
		$result        = ManifestEditor::remove( is_array( $manifest ) ? $manifest : [], $id );

		if ( null === $result['file'] ) {
			\WP_CLI::error( sprintf( '"%s" was not found in %s.', $id, $manifest_file ) );
		}

		file_put_contents( $manifest_file, wp_json_encode( $result['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local library file.

		$removed = [];
		foreach ( [ $library . '/' . ltrim( $result['file'], '/' ), $library . '/inbox/' . $piece->category . '/' . basename( $result['file'] ) ] as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
				$removed[] = $file;
			}
		}

		\WP_CLI::success( sprintf( 'Removed %s (manifest version %d). Deleted: %s', $id, (int) $result['manifest']['version'], $removed ? implode( ', ', $removed ) : 'no files' ) );
	}
}
