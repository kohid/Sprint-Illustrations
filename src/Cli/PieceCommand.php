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

		$removed = $this->remove_custom( $id, $piece->category );
		\WP_CLI::success( sprintf( 'Removed %s. Deleted: %s', $id, $removed ? implode( ', ', $removed ) : 'no files' ) );
	}

	/**
	 * Move custom pieces into the plugin's own library, so they ship with the plugin.
	 *
	 * The source SVG goes to assets/pieces-src/<category>/, the bundled manifest is rebuilt, and the
	 * site copy is removed. Commit the new files to include them in a release.
	 *
	 * ## OPTIONS
	 *
	 * [<piece-id>...]
	 * : Custom piece IDs, e.g. obj-taxi.
	 *
	 * [--all]
	 * : Every custom piece.
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function bundle( array $args, array $assoc_args ): void {
		$bundled = $this->plugin->bundled_library();
		if ( ! $bundled->writable() ) {
			\WP_CLI::error( 'Pieces can only be added to a development copy of the plugin (a git checkout, a local/development environment, or SPRINT_ILLUSTRATIONS_BUNDLE_TO_PLUGIN), and its folder must be writable.' );
		}

		$library = rtrim( wp_normalize_path( $this->plugin->user_library_dir() ), '/' );
		$custom  = array_filter( $this->plugin->services()->manifest->all(), static fn( $piece ) => str_starts_with( wp_normalize_path( $piece->path ), $library . '/' ) );
		$ids     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ? array_map( static fn( $piece ) => $piece->id, $custom ) : $args;
		if ( ! $ids ) {
			\WP_CLI::error( 'Name the pieces to bundle, or use --all.' );
		}

		$moved = 0;
		foreach ( $ids as $id ) {
			$piece = $this->plugin->services()->manifest->get( (string) $id );
			if ( null === $piece || ! str_starts_with( wp_normalize_path( $piece->path ), $library . '/' ) ) {
				\WP_CLI::warning( sprintf( '"%s" is not a custom piece; skipped.', $id ) );
				continue;
			}

			$name   = basename( $piece->path, '.svg' );
			$source = $library . '/inbox/' . $piece->category . '/' . $name . '.svg';
			if ( ! is_readable( $source ) ) {
				\WP_CLI::warning( sprintf( '"%s" has no source in the inbox; skipped.', $id ) );
				continue;
			}

			$report = $bundled->add_piece( $piece->category, $name, $source );
			if ( is_string( $report ) ) {
				\WP_CLI::warning( sprintf( '%s: %s', $id, $report ) );
				continue;
			}
			foreach ( $report->warnings as $warning ) {
				\WP_CLI::warning( $warning );
			}

			$this->remove_custom( (string) $id, $piece->category );
			\WP_CLI::log( sprintf( 'Bundled %s.', $id ) );
			++$moved;
		}//end foreach

		\WP_CLI::success( sprintf( '%d piece(s) moved into the plugin library. Commit assets/ to include them in a release.', $moved ) );
	}

	/**
	 * Remove a custom piece's manifest entry, built file and inbox source.
	 *
	 * @param string $id       Piece ID.
	 * @param string $category Category.
	 * @return array<string> Deleted files.
	 */
	private function remove_custom( string $id, string $category ): array {
		$library       = rtrim( wp_normalize_path( $this->plugin->user_library_dir() ), '/' );
		$manifest_file = $library . '/manifest.json';
		$manifest      = json_decode( (string) file_get_contents( $manifest_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local library file.
		$result        = ManifestEditor::remove( is_array( $manifest ) ? $manifest : [], $id );

		if ( null === $result['file'] ) {
			\WP_CLI::error( sprintf( '"%s" was not found in %s.', $id, $manifest_file ) );
		}

		file_put_contents( $manifest_file, wp_json_encode( $result['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local library file.

		$removed = [];
		foreach ( [ $library . '/' . ltrim( $result['file'], '/' ), $library . '/inbox/' . $category . '/' . basename( $result['file'] ) ] as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
				$removed[] = $file;
			}
		}

		return $removed;
	}
}
