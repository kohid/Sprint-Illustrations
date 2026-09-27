<?php
/**
 * Immutable manifest entry for one SVG piece.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * A single reusable SVG piece.
 */
final class Piece {

	public const CATEGORIES = [ 'characters', 'objects', 'backgrounds', 'decor' ];

	/**
	 * Constructor.
	 *
	 * @param string                                        $id        Unique ID.
	 * @param string                                        $label     Human label (used in <desc>).
	 * @param string                                        $category  One of CATEGORIES.
	 * @param string                                        $path      Absolute path to the SVG file.
	 * @param array<string>                                 $tags      Lower-case tags.
	 * @param array{0: float, 1: float, 2: float, 3: float} $view_box  [x, y, width, height].
	 * @param int                                           $z         Default z-layer.
	 * @param array<string, array{0: float, 1: float}>      $anchors   Anchor name => [x, y] in viewBox units.
	 * @param array<string, string>                         $accepts   Anchor name => attachment type it accepts.
	 * @param array<string, string>                         $mounts    Attachment type => own anchor name used to mount.
	 * @param array<string>                                 $slots     Colour slot names used.
	 * @param string                                        $hash      Content hash of the cleaned SVG.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $category,
		public readonly string $path,
		public readonly array $tags,
		public readonly array $view_box,
		public readonly int $z,
		public readonly array $anchors,
		public readonly array $accepts,
		public readonly array $mounts,
		public readonly array $slots,
		public readonly string $hash,
	) {}

	/**
	 * Build from a manifest entry.
	 *
	 * @param array<string, mixed> $data     Manifest entry.
	 * @param string               $base_dir Directory the entry's "file" is relative to.
	 * @return self
	 * @throws LibraryException When required fields are missing or invalid.
	 */
	public static function from_array( array $data, string $base_dir ): self {
		foreach ( [ 'id', 'category', 'file', 'viewBox' ] as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				throw new LibraryException( sprintf( 'Piece entry is missing "%s".', $key ) );
			}
		}

		if ( ! in_array( $data['category'], self::CATEGORIES, true ) ) {
			throw new LibraryException( sprintf( 'Piece "%s" has unknown category "%s".', $data['id'], $data['category'] ) );
		}

		$view_box = array_map( 'floatval', array_values( (array) $data['viewBox'] ) );
		if ( 4 !== count( $view_box ) || $view_box[2] <= 0 || $view_box[3] <= 0 ) {
			throw new LibraryException( sprintf( 'Piece "%s" has an invalid viewBox.', $data['id'] ) );
		}

		$anchors = [];
		foreach ( (array) ( $data['anchors'] ?? [] ) as $name => $point ) {
			$point = array_values( (array) $point );
			if ( 2 === count( $point ) ) {
				$anchors[ (string) $name ] = [ (float) $point[0], (float) $point[1] ];
			}
		}

		return new self(
			(string) $data['id'],
			(string) ( $data['label'] ?? $data['id'] ),
			(string) $data['category'],
			rtrim( $base_dir, '/\\' ) . '/' . ltrim( (string) $data['file'], '/\\' ),
			array_values( array_map( 'strtolower', array_map( 'strval', (array) ( $data['tags'] ?? [] ) ) ) ),
			$view_box,
			(int) ( $data['z'] ?? 10 ),
			$anchors,
			array_map( 'strval', (array) ( $data['accepts'] ?? [] ) ),
			array_map( 'strval', (array) ( $data['mounts'] ?? [] ) ),
			array_values( array_map( 'strval', (array) ( $data['slots'] ?? [] ) ) ),
			(string) ( $data['hash'] ?? '' )
		);
	}

	/**
	 * ViewBox width.
	 *
	 * @return float
	 */
	public function width(): float {
		return $this->view_box[2];
	}

	/**
	 * ViewBox height.
	 *
	 * @return float
	 */
	public function height(): float {
		return $this->view_box[3];
	}

	/**
	 * Anchor point in viewBox units, or null.
	 *
	 * @param string $name Anchor name.
	 * @return array{0: float, 1: float}|null
	 */
	public function anchor( string $name ): ?array {
		return $this->anchors[ $name ] ?? null;
	}

	/**
	 * Whether the piece carries a tag.
	 *
	 * @param string $tag Tag.
	 * @return bool
	 */
	public function has_tag( string $tag ): bool {
		return in_array( strtolower( $tag ), $this->tags, true );
	}
}
