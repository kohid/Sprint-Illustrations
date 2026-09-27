<?php
/**
 * One slot in a scene template.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * A slot is placed either in a box or attached to another slot's anchor.
 */
final class TemplateSlot {

	/**
	 * Constructor.
	 *
	 * @param string                                               $name          Unique slot name.
	 * @param string                                               $category      Piece category.
	 * @param array{0: float, 1: float, 2: float, 3: float}|null   $box        [x, y, w, h] in canvas units (box slots).
	 * @param string                                               $fit           "natural" (template unit scale, capped to box) or "contain".
	 * @param string                                               $align         Vertical alignment in box: "bottom", "center", "top".
	 * @param array{0: float, 1: float}                            $scale         Scale factor range.
	 * @param int|null                                             $z             Z override (null = piece default).
	 * @param bool                                                 $required      Whether composition fails if unfilled.
	 * @param string|null                                          $attach_to     Parent slot name (attached slots).
	 * @param string|null                                          $attach_anchor Parent anchor name (attached slots).
	 * @param bool                                                 $behind        Attached piece renders behind its parent.
	 * @param array{0: int, 1: int}                                $count         Piece count range.
	 * @param bool                                                 $scatter       Scatter pieces inside the box.
	 * @param array<array{0: float, 1: float, 2: float, 3: float}> $avoid    Boxes scattered piece centres must avoid.
	 * @param bool                                                 $flip          Mirror horizontally.
	 * @param array<string>                                        $prefer        Tags that add a score bonus.
	 * @param array<string>                                        $require_tags  Tags a piece must have.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $category,
		public readonly ?array $box,
		public readonly string $fit,
		public readonly string $align,
		public readonly array $scale,
		public readonly ?int $z,
		public readonly bool $required,
		public readonly ?string $attach_to,
		public readonly ?string $attach_anchor,
		public readonly bool $behind,
		public readonly array $count,
		public readonly bool $scatter,
		public readonly array $avoid,
		public readonly bool $flip,
		public readonly array $prefer,
		public readonly array $require_tags,
	) {}

	/**
	 * Build from template JSON.
	 *
	 * @param array<string, mixed> $data        Slot data.
	 * @param string               $template_id Owning template (for error messages).
	 * @return self
	 * @throws LibraryException When invalid.
	 */
	public static function from_array( array $data, string $template_id ): self {
		$name = (string) ( $data['name'] ?? '' );
		if ( ! preg_match( '/^[a-z][a-z0-9_-]*$/D', $name ) ) {
			throw new LibraryException( sprintf( 'Template "%s" has a slot with an invalid name.', $template_id ) );
		}

		$category = (string) ( $data['category'] ?? '' );
		if ( ! in_array( $category, Piece::CATEGORIES, true ) ) {
			throw new LibraryException( sprintf( 'Slot "%s.%s" has unknown category "%s".', $template_id, $name, $category ) );
		}

		$box    = isset( $data['box'] ) ? self::rect( $data['box'], $template_id, $name ) : null;
		$attach = isset( $data['attach'] ) && is_array( $data['attach'] ) ? $data['attach'] : null;

		if ( ( null === $box ) === ( null === $attach ) ) {
			throw new LibraryException( sprintf( 'Slot "%s.%s" needs exactly one of "box" or "attach".', $template_id, $name ) );
		}
		if ( null !== $attach && ( empty( $attach['to'] ) || empty( $attach['anchor'] ) ) ) {
			throw new LibraryException( sprintf( 'Slot "%s.%s" attach needs "to" and "anchor".', $template_id, $name ) );
		}

		$fit   = in_array( $data['fit'] ?? 'natural', [ 'natural', 'contain' ], true ) ? ( $data['fit'] ?? 'natural' ) : 'natural';
		$align = in_array( $data['align'] ?? 'bottom', [ 'bottom', 'center', 'top' ], true ) ? ( $data['align'] ?? 'bottom' ) : 'bottom';
		$scale = self::range( $data['scale'] ?? [ 1, 1 ] );
		$count = array_map( 'intval', self::range( $data['count'] ?? [ 1, 1 ] ) );

		return new self(
			$name,
			$category,
			$box,
			$fit,
			$align,
			$scale,
			isset( $data['z'] ) ? (int) $data['z'] : null,
			(bool) ( $data['required'] ?? false ),
			null !== $attach ? (string) $attach['to'] : null,
			null !== $attach ? (string) $attach['anchor'] : null,
			(bool) ( $data['behind'] ?? false ),
			[ max( 0, $count[0] ), max( 0, $count[1] ) ],
			(bool) ( $data['scatter'] ?? false ),
			array_map( fn( $rect ) => self::rect( $rect, $template_id, $name ), (array) ( $data['avoid'] ?? [] ) ),
			(bool) ( $data['flip'] ?? false ),
			array_map( 'strtolower', array_map( 'strval', (array) ( $data['prefer'] ?? [] ) ) ),
			array_map( 'strtolower', array_map( 'strval', (array) ( $data['require_tags'] ?? [] ) ) )
		);
	}

	/**
	 * Whether this slot can hold more than one piece.
	 *
	 * @return bool
	 */
	public function is_multiple(): bool {
		return $this->count[1] > 1;
	}

	/**
	 * Validate a rectangle.
	 *
	 * @param mixed  $value       Candidate.
	 * @param string $template_id Template ID.
	 * @param string $name        Slot name.
	 * @return array{0: float, 1: float, 2: float, 3: float}
	 * @throws LibraryException When invalid.
	 */
	private static function rect( mixed $value, string $template_id, string $name ): array {
		$rect = array_map( 'floatval', array_values( (array) $value ) );
		if ( 4 !== count( $rect ) || $rect[2] <= 0 || $rect[3] <= 0 ) {
			throw new LibraryException( sprintf( 'Slot "%s.%s" has an invalid rectangle.', $template_id, $name ) );
		}
		return $rect;
	}

	/**
	 * Normalize a [min, max] range (swaps if reversed; a scalar means [v, v]).
	 *
	 * @param mixed $value Candidate.
	 * @return array{0: float, 1: float}
	 */
	private static function range( mixed $value ): array {
		$values = array_map( 'floatval', array_values( (array) $value ) );
		$min    = $values[0] ?? 1.0;
		$max    = $values[1] ?? $min;

		return [ min( $min, $max ), max( $min, $max ) ];
	}
}
