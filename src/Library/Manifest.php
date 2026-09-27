<?php
/**
 * Merged view over one or more piece manifests.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Loads manifest.json files; earlier files win on duplicate IDs.
 */
final class Manifest {

	/**
	 * Pieces keyed by ID, in load order.
	 *
	 * @var array<string, Piece>
	 */
	private array $pieces = [];

	/**
	 * Non-fatal load problems (duplicates).
	 *
	 * @var array<string>
	 */
	private array $errors = [];

	/**
	 * Version strings of each loaded manifest.
	 *
	 * @var array<string>
	 */
	private array $versions = [];

	/**
	 * Load manifests in order. Files that do not exist are skipped silently (optional user libraries).
	 *
	 * @param array<string> $manifest_files Absolute manifest.json paths.
	 * @return self
	 * @throws LibraryException When an existing manifest is unreadable or invalid.
	 */
	public static function from_files( array $manifest_files ): self {
		$manifest = new self();

		foreach ( $manifest_files as $file ) {
			if ( file_exists( $file ) ) {
				$manifest->load_file( $file );
			}
		}

		return $manifest;
	}

	/**
	 * Piece by ID.
	 *
	 * @param string $id Piece ID.
	 * @return Piece|null
	 */
	public function get( string $id ): ?Piece {
		return $this->pieces[ $id ] ?? null;
	}

	/**
	 * All pieces in load order.
	 *
	 * @return array<Piece>
	 */
	public function all(): array {
		return array_values( $this->pieces );
	}

	/**
	 * Pieces in a category, in load order.
	 *
	 * @param string $category Category.
	 * @return array<Piece>
	 */
	public function by_category( string $category ): array {
		return array_values( array_filter( $this->pieces, static fn( Piece $piece ) => $piece->category === $category ) );
	}

	/**
	 * Combined version of all loaded manifests (changes whenever any manifest is rebuilt).
	 *
	 * @return string
	 */
	public function version(): string {
		return implode( '.', $this->versions );
	}

	/**
	 * Non-fatal load errors.
	 *
	 * @return array<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Load one manifest file.
	 *
	 * @param string $file Manifest path.
	 * @throws LibraryException When invalid.
	 */
	private function load_file( string $file ): void {
		$data = json_decode( (string) file_get_contents( $file ), true );

		if ( ! is_array( $data ) || ! isset( $data['pieces'] ) || ! is_array( $data['pieces'] ) ) {
			throw new LibraryException( sprintf( 'Invalid manifest: %s', $file ) );
		}

		$this->versions[] = (string) ( $data['version'] ?? 0 );
		$base_dir         = dirname( $file );

		foreach ( $data['pieces'] as $entry ) {
			$piece = Piece::from_array( (array) $entry, $base_dir );

			if ( isset( $this->pieces[ $piece->id ] ) ) {
				$this->errors[] = sprintf( 'Duplicate piece ID "%s" in %s was skipped.', $piece->id, $file );
				continue;
			}

			$this->pieces[ $piece->id ] = $piece;
		}
	}
}
