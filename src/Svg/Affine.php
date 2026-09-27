<?php
/**
 * 2D affine matrix used to resolve SVG transform attributes.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Svg;

/**
 * Matrix [a c e; b d f; 0 0 1], matching SVG's matrix(a b c d e f).
 */
final class Affine {

	/**
	 * Constructor.
	 *
	 * @param float $a Scale X / cos.
	 * @param float $b Skew Y / sin.
	 * @param float $c Skew X / -sin.
	 * @param float $d Scale Y / cos.
	 * @param float $e Translate X.
	 * @param float $f Translate Y.
	 */
	public function __construct(
		public readonly float $a = 1.0,
		public readonly float $b = 0.0,
		public readonly float $c = 0.0,
		public readonly float $d = 1.0,
		public readonly float $e = 0.0,
		public readonly float $f = 0.0,
	) {}

	/**
	 * Parse an SVG transform list, e.g. "translate(10 5) rotate(45 0 0)".
	 * Unsupported functions (skewX, skewY) are ignored.
	 *
	 * @param string $transform Transform attribute value.
	 * @return self
	 */
	public static function parse( string $transform ): self {
		$result = new self();

		if ( ! preg_match_all( '/(matrix|translate|scale|rotate)\s*\(([^)]*)\)/i', $transform, $matches, PREG_SET_ORDER ) ) {
			return $result;
		}

		foreach ( $matches as $match ) {
			$args   = array_map( 'floatval', preg_split( '/[\s,]+/', trim( $match[2] ), -1, PREG_SPLIT_NO_EMPTY ) );
			$result = $result->multiply( self::from_function( strtolower( $match[1] ), $args ) );
		}

		return $result;
	}

	/**
	 * Combined transform from the root <svg> (exclusive) down to and including $element.
	 *
	 * @param \DOMElement $element Element.
	 * @return self
	 */
	public static function for_element( \DOMElement $element ): self {
		$chain = [];
		$root  = $element->ownerDocument?->documentElement;

		for ( $node = $element; $node instanceof \DOMElement && $node !== $root; $node = $node->parentNode ) {
			array_unshift( $chain, $node->getAttribute( 'transform' ) );
		}

		$result = new self();
		foreach ( $chain as $transform ) {
			if ( '' !== $transform ) {
				$result = $result->multiply( self::parse( $transform ) );
			}
		}

		return $result;
	}

	/**
	 * Return $this × $other.
	 *
	 * @param self $other Right-hand matrix.
	 * @return self
	 */
	public function multiply( self $other ): self {
		return new self(
			$this->a * $other->a + $this->c * $other->b,
			$this->b * $other->a + $this->d * $other->b,
			$this->a * $other->c + $this->c * $other->d,
			$this->b * $other->c + $this->d * $other->d,
			$this->a * $other->e + $this->c * $other->f + $this->e,
			$this->b * $other->e + $this->d * $other->f + $this->f,
		);
	}

	/**
	 * Transform a point.
	 *
	 * @param float $x X.
	 * @param float $y Y.
	 * @return array{0: float, 1: float}
	 */
	public function apply( float $x, float $y ): array {
		return [
			$this->a * $x + $this->c * $y + $this->e,
			$this->b * $x + $this->d * $y + $this->f,
		];
	}

	/**
	 * Build a matrix for a single transform function.
	 *
	 * @param string       $name Function name.
	 * @param array<float> $args Numeric arguments.
	 * @return self
	 */
	private static function from_function( string $name, array $args ): self {
		switch ( $name ) {
			case 'matrix':
				return 6 === count( $args ) ? new self( ...$args ) : new self();
			case 'translate':
				return new self( 1.0, 0.0, 0.0, 1.0, $args[0] ?? 0.0, $args[1] ?? 0.0 );
			case 'scale':
				$sx = $args[0] ?? 1.0;
				return new self( $sx, 0.0, 0.0, $args[1] ?? $sx );
			case 'rotate':
				$rad      = deg2rad( $args[0] ?? 0.0 );
				$rotation = new self( cos( $rad ), sin( $rad ), -sin( $rad ), cos( $rad ) );
				if ( 3 === count( $args ) ) {
					return ( new self( 1.0, 0.0, 0.0, 1.0, $args[1], $args[2] ) )
						->multiply( $rotation )
						->multiply( new self( 1.0, 0.0, 0.0, 1.0, -$args[1], -$args[2] ) );
				}
				return $rotation;
			default:
				return new self();
		}
	}
}
