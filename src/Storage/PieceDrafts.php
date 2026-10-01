<?php
/**
 * Draft pieces awaiting the requester's Keep / Discard.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Library\LibraryException;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Library\PieceRequest;
use SprintIllustrations\Plugin;
use SprintIllustrations\Rest\PiecePreviews;
use SprintIllustrations\Services;

/**
 * Drafts live in uploads/sprint-illustrations/drafts/{src,build}/<request-id>/ and never reach the library until kept.
 */
final class PieceDrafts {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Library root.
	 *
	 * @return string
	 */
	private function library(): string {
		return rtrim( wp_normalize_path( $this->plugin->user_library_dir() ), '/' );
	}

	/**
	 * Draft source folder for a request.
	 *
	 * @param int $request_id Request ID.
	 * @return string
	 */
	private function src_dir( int $request_id ): string {
		return $this->library() . '/drafts/src/' . $request_id;
	}

	/**
	 * Draft build folder for a request.
	 *
	 * @param int $request_id Request ID.
	 * @return string
	 */
	private function build_dir( int $request_id ): string {
		return $this->library() . '/drafts/build/' . $request_id;
	}

	/**
	 * File name → piece name (as ManifestBuilder derives it).
	 *
	 * @param string $file SVG path.
	 * @return string
	 */
	public static function name( string $file ): string {
		return trim( strtolower( (string) preg_replace( '/[^a-z0-9-]+/i', '-', basename( $file, '.svg' ) ) ), '-' );
	}

	/**
	 * Piece ID a draft will get.
	 *
	 * @param string $category Category.
	 * @param string $name     Piece name.
	 * @return string
	 */
	public static function piece_id( string $category, string $name ): string {
		return ( ManifestBuilder::CATEGORIES[ $category ] ?? 'obj' ) . '-' . $name;
	}

	/**
	 * Validate and store a draft for a request (replacing any earlier draft).
	 *
	 * @param array<string, mixed> $request Request row.
	 * @param string               $file    Source SVG.
	 * @return array{ok: bool, piece: string, messages: array<string>}
	 */
	public function submit( array $request, string $file ): array {
		$category = (string) $request['category'];
		$name     = self::name( $file );
		$piece    = self::piece_id( $category, $name );

		if ( ! is_readable( $file ) || '' === $name ) {
			return $this->fail( [ sprintf( 'Cannot read %s.', $file ) ] );
		}
		if ( null !== $this->plugin->services()->manifest->get( $piece ) || is_file( $this->library() . '/inbox/' . $category . '/' . $name . '.svg' ) ) {
			return $this->fail( [ sprintf( '"%s" already exists in the library. Choose another file name.', $piece ) ] );
		}
		foreach ( (array) glob( $this->library() . '/drafts/src/*/' . $category . '/' . $name . '.svg' ) as $other ) {
			if ( basename( dirname( (string) $other, 2 ) ) !== (string) $request['id'] ) {
				return $this->fail( [ sprintf( 'Another request already has a draft named "%s". Choose another file name.', $piece ) ] );
			}
		}

		$this->discard( $request );
		wp_mkdir_p( $this->src_dir( (int) $request['id'] ) . '/' . $category );
		copy( $file, $this->src_dir( (int) $request['id'] ) . '/' . $category . '/' . $name . '.svg' );

		$report = $this->builder()->build( $this->src_dir( (int) $request['id'] ), $this->build_dir( (int) $request['id'] ) );
		if ( $report->errors || $report->warnings ) {
			$this->discard( $request );
			return $this->fail( array_merge( $report->errors, $report->warnings ) );
		}

		return [
			'ok'       => true,
			'piece'    => $piece,
			'messages' => [],
		];
	}

	/**
	 * The draft piece, or null.
	 *
	 * @param array<string, mixed> $request Request row.
	 * @return Piece|null
	 */
	public function piece( array $request ): ?Piece {
		$manifest = $this->build_dir( (int) $request['id'] ) . '/manifest.json';
		if ( '' === (string) $request['draft'] || ! is_readable( $manifest ) ) {
			return null;
		}

		try {
			return Manifest::from_files( [ $manifest ] )->get( self::piece_id( (string) $request['category'], (string) $request['draft'] ) );
		} catch ( LibraryException $e ) {
			return null;
		}
	}

	/**
	 * Preview SVGs: the piece alone and in a sample scene (either may be '').
	 *
	 * @param array<string, mixed> $request Request row.
	 * @return array{piece: string, scene: string}
	 */
	public function preview( array $request ): array {
		$piece = $this->piece( $request );
		if ( null === $piece ) {
			return [
				'piece' => '',
				'scene' => '',
			];
		}

		$palette  = $this->plugin->site_palette()->palette();
		$services = Services::create( $this->plugin->dir(), array_merge( $this->plugin->user_manifests(), [ $this->build_dir( (int) $request['id'] ) . '/manifest.json' ] ) );
		$sample   = PieceRequest::sample( (string) $request['category'], $piece->id );
		$scene    = '';

		try {
			$scene = $services->composer->compose(
				SceneSpec::from_array(
					[
						'template'   => $sample['template'],
						'seed'       => 3,
						'picks'      => $sample['picks'],
						'decorative' => true,
					]
				),
				$palette
			)->with_instance_id( 'si-draft-' . (int) $request['id'] );
		} catch ( CompositionException $e ) {
			$scene = '';
		}

		return [
			'piece' => ( new PiecePreviews( new PieceLoader( $services->sanitizer ), $services->sanitizer ) )->svg( $piece, $palette ),
			'scene' => $scene,
		];
	}

	/**
	 * Move the draft into the library and rebuild the site manifest.
	 *
	 * @param array<string, mixed> $request Request row.
	 * @return array{ok: bool, piece: string, where?: string, messages: array<string>}
	 */
	public function keep( array $request ): array {
		$category = (string) $request['category'];
		$name     = (string) $request['draft'];
		$source   = $this->src_dir( (int) $request['id'] ) . '/' . $category . '/' . $name . '.svg';

		if ( '' === $name || ! is_readable( $source ) ) {
			return $this->fail( [ 'The draft is missing. Ask Claude Code to draw it again.' ] );
		}

		$result = $this->plugin->piece_installer()->install( $category, $name, $source );
		if ( $result['ok'] ) {
			$this->discard( $request );
		}

		return $result;
	}

	/**
	 * Accept a draft: it joins the library, and the request and its reference image are closed.
	 *
	 * @param array<string, mixed> $request Request row (state review).
	 * @return array{ok: bool, piece: string, where?: string, messages: array<string>}
	 */
	public function accept( array $request ): array {
		$result = $this->keep( $request );
		if ( $result['ok'] ) {
			$this->plugin->piece_requests()->keep( (int) $request['id'], $result['piece'] );
			$this->plugin->reference_images()->delete( (string) $request['reference'] );
		}

		return $result;
	}

	/**
	 * Reject a draft: it is deleted and the request is marked discarded (Try again can re-queue it).
	 *
	 * @param array<string, mixed> $request Request row (state review).
	 */
	public function reject( array $request ): void {
		$this->discard( $request );
		$this->plugin->piece_requests()->discard( (int) $request['id'] );
	}

	/**
	 * Delete a request's draft folders.
	 *
	 * @param array<string, mixed> $request Request row.
	 */
	public function discard( array $request ): void {
		foreach ( [ $this->src_dir( (int) $request['id'] ), $this->build_dir( (int) $request['id'] ) ] as $dir ) {
			$this->remove_tree( $dir );
		}
	}

	/**
	 * Manifest builder with the plugin's sanitizer, never prompting.
	 *
	 * @return ManifestBuilder
	 */
	private function builder(): ManifestBuilder {
		return new ManifestBuilder( $this->plugin->services()->sanitizer, new NullPrompter() );
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

	/**
	 * Delete a folder tree inside the drafts area.
	 *
	 * @param string $dir Folder.
	 */
	private function remove_tree( string $dir ): void {
		if ( ! is_dir( $dir ) || ! str_starts_with( wp_normalize_path( $dir ), $this->library() . '/drafts/' ) ) {
			return;
		}

		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) ) {
				$this->remove_tree( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Plugin-owned drafts folder.
	}
}
