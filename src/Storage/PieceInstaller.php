<?php
/**
 * Puts a finished piece into the library.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Plugin;

/**
 * One place for what "kept" means: the piece joins the plugin's own library (so it ships with the plugin)
 * when that is writable, otherwise this site's library where plugin updates can't remove it. Used by Keep
 * on the Library page and by the Character Builder's Save.
 */
final class PieceInstaller {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Whether a piece with this category and name is already in the library.
	 *
	 * @param string $category Category.
	 * @param string $name     Piece name.
	 * @return bool
	 */
	public function exists( string $category, string $name ): bool {
		return is_file( $this->library() . '/inbox/' . $category . '/' . $name . '.svg' ) || null !== $this->plugin->services()->manifest->get( PieceDrafts::piece_id( $category, $name ) );
	}

	/**
	 * Install a piece.
	 *
	 * @param string $category Category.
	 * @param string $name     File name without .svg (becomes the piece name).
	 * @param string $source   Source SVG.
	 * @return array{ok: bool, piece: string, where?: string, messages: array<string>}
	 */
	public function install( string $category, string $name, string $source ): array {
		$piece  = PieceDrafts::piece_id( $category, $name );
		$target = $this->library() . '/inbox/' . $category . '/' . $name . '.svg';

		if ( '' === $name || ! is_readable( $source ) ) {
			return $this->fail( [ 'The piece file is missing.' ] );
		}
		if ( $this->exists( $category, $name ) ) {
			return $this->fail( [ sprintf( '"%s" already exists in the library.', $piece ) ] );
		}

		// Kept pieces join the plugin's own library, so they ship with the plugin to other sites.
		$bundled = $this->plugin->bundled_library();
		if ( $bundled->writable() ) {
			$report = $bundled->add_piece( $category, $name, $source );
			if ( is_string( $report ) ) {
				return $this->fail( [ $report ] );
			}

			return [
				'ok'       => true,
				'piece'    => $piece,
				'where'    => 'plugin',
				'messages' => $report->warnings,
			];
		}

		wp_mkdir_p( dirname( $target ) );
		copy( $source, $target );

		$builder = new ManifestBuilder( $this->plugin->services()->sanitizer, new NullPrompter() );
		$report  = $builder->build( $this->library() . '/inbox', $this->library() );
		if ( ! in_array( $piece, $report->built, true ) ) {
			wp_delete_file( $target );

			return $this->fail( array_merge( [ 'The piece could not be added.' ], $report->errors ) );
		}

		return [
			'ok'       => true,
			'piece'    => $piece,
			'where'    => 'site',
			'messages' => $report->warnings,
		];
	}

	/**
	 * Library root.
	 *
	 * @return string
	 */
	private function library(): string {
		return rtrim( wp_normalize_path( $this->plugin->user_library_dir() ), '/' );
	}

	/**
	 * Failure result.
	 *
	 * @param array<string> $messages Messages.
	 * @return array{ok: false, piece: string, messages: array<string>}
	 */
	private function fail( array $messages ): array {
		return [
			'ok'       => false,
			'piece'    => '',
			'messages' => $messages,
		];
	}
}
