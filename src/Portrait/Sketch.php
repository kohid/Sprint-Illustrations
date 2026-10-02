<?php
/**
 * Hand-drawn line helpers: slightly wobbly outlines that are the same every time for the same drawing.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Portrait;

use SprintIllustrations\Character\Geometry as G;
use SprintIllustrations\Compose\Seed;

/**
 * Every shape is a smooth closed curve through its points, nudged by a small seeded wobble and outlined
 * twice (a firm line and a faint second pass), which reads as pencil work without any filter or texture.
 */
final class Sketch {

	/**
	 * Constructor.
	 *
	 * @param string $seed What this drawing's wobble is derived from.
	 */
	public function __construct( private string $seed ) {}

	/**
	 * Points nudged by up to $amp units in each direction.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Points.
	 * @param float                                         $amp    Largest nudge.
	 * @param string                                        $key    Names the stroke, so each one wobbles differently.
	 * @return array<int, array{0: float, 1: float}>
	 */
	public function wobble( array $points, float $amp, string $key ): array {
		$random = Seed::from_string( $this->seed . '|' . $key );
		$out    = [];
		foreach ( $points as $p ) {
			$out[] = [
				(float) $p[0] + ( $random->next() - 0.5 ) * 2 * $amp,
				(float) $p[1] + ( $random->next() - 0.5 ) * 2 * $amp,
			];
		}

		return $out;
	}

	/**
	 * A filled, outlined closed shape.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points  Points around the shape.
	 * @param string                                        $fill    Fill token (a slot class without "slot-"), "" for none.
	 * @param string                                        $stroke  Stroke token, "" for no outline.
	 * @param string                                        $key     Names the stroke.
	 * @param float                                         $width   Outline width.
	 * @param float                                         $amp     Wobble.
	 * @return string
	 */
	public function shape( array $points, string $fill, string $stroke, string $key, float $width = 2.6, float $amp = 1.0 ): string {
		$d = G::closed( $this->wobble( $points, $amp, $key ) );

		return $this->draw( $d, $fill, $stroke, $key, $width, $amp, $points, true );
	}

	/**
	 * An open stroke (a lash line, a strand, a fold).
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Points along the line.
	 * @param string                                        $stroke Stroke token.
	 * @param string                                        $key    Names the stroke.
	 * @param float                                         $width  Width.
	 * @param float                                         $amp    Wobble.
	 * @return string
	 */
	public function line( array $points, string $stroke, string $key, float $width = 2.2, float $amp = 0.8 ): string {
		$d = G::open( $this->wobble( $points, $amp, $key ) );

		return '<path class="slot-stroke-' . $stroke . '" d="' . $d . '" fill="none" stroke-width="' . G::n( $width ) . '" stroke-linecap="round" stroke-linejoin="round"/>';
	}

	/**
	 * A thick round-ended limb between two points: a slightly wider, darker, translucent stroke underneath and
	 * the colour on top, so it reads as a flat shape with a soft edge.
	 *
	 * @param array{0: float|int, 1: float|int} $from  Start.
	 * @param array{0: float|int, 1: float|int} $to    End.
	 * @param string                            $fill  Fill token.
	 * @param string                            $edge  Edge token.
	 * @param float                             $width Thickness.
	 * @param string                            $key   Names the stroke.
	 * @return string
	 */
	public function limb( array $from, array $to, string $fill, string $edge, float $width, string $key ): string {
		$points = $this->wobble( [ $from, $to ], 0.5, $key );
		$d      = 'M' . G::pt( $points[0] ) . ' L' . G::pt( $points[1] );

		return '<path class="slot-stroke-' . $edge . '" d="' . $d . '" fill="none" stroke-width="' . G::n( $width + 2.4 ) . '" stroke-linecap="round" opacity="0.6"/>'
			. '<path class="slot-stroke-' . $fill . '" d="' . $d . '" fill="none" stroke-width="' . G::n( $width ) . '" stroke-linecap="round"/>';
	}

	/**
	 * A flat shape with a thin, soft edge (one pass, translucent line): the look of flat-colour illustration.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Points around the shape.
	 * @param string                                        $fill   Fill token.
	 * @param string                                        $edge   Edge token, "" for none.
	 * @param string                                        $key    Names the stroke.
	 * @param float                                         $width  Edge width.
	 * @param float                                         $amp    Wobble.
	 * @return string
	 */
	public function soft( array $points, string $fill, string $edge, string $key, float $width = 1.3, float $amp = 0.7 ): string {
		$d = G::closed( $this->wobble( $points, $amp, $key ) );

		return '<path class="slot-' . $fill . ( '' !== $edge ? ' slot-stroke-' . $edge : '' ) . '" d="' . $d . '"'
			. ( '' !== $edge ? ' stroke-width="' . G::n( $width ) . '" stroke-linejoin="round" stroke-opacity="0.55"' : '' ) . '/>';
	}

	/**
	 * A translucent patch of colour without an edge: cel shading, highlights, blush.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points  Points around the patch.
	 * @param string                                        $fill    Fill token.
	 * @param float                                         $opacity Opacity.
	 * @param string                                        $key     Names the shape.
	 * @return string
	 */
	public function patch( array $points, string $fill, float $opacity, string $key ): string {
		$d = G::closed( $this->wobble( $points, 0.4, $key ) );

		return '<path class="slot-' . $fill . '" opacity="' . G::n( $opacity ) . '" d="' . $d . '"/>';
	}

	/**
	 * Fill and outline for a prepared path.
	 *
	 * @param string                                        $d      Path data.
	 * @param string                                        $fill   Fill token.
	 * @param string                                        $stroke Stroke token.
	 * @param string                                        $key    Stroke name.
	 * @param float                                         $width  Width.
	 * @param float                                         $amp    Wobble.
	 * @param array<int, array{0: float|int, 1: float|int}> $points Original points (for the second pass).
	 * @param bool                                          $closed Closed shape.
	 * @return string
	 */
	private function draw( string $d, string $fill, string $stroke, string $key, float $width, float $amp, array $points, bool $closed ): string {
		$class = [];
		if ( '' !== $fill ) {
			$class[] = 'slot-' . $fill;
		}
		if ( '' !== $stroke ) {
			$class[] = 'slot-stroke-' . $stroke;
		}
		$out = '<path class="' . implode( ' ', $class ) . '" d="' . $d . '"' . ( '' === $fill ? ' fill="none"' : '' ) . ( '' !== $stroke ? ' stroke-width="' . G::n( $width ) . '" stroke-linejoin="round" stroke-linecap="round"' : '' ) . '/>';

		if ( '' !== $stroke && $closed ) {
			// A faint second pass that doesn't quite follow the first, like a re-traced pencil line.
			$again = G::closed( $this->wobble( $points, $amp * 1.6, $key . '#2' ) );
			$out  .= '<path class="slot-stroke-' . $stroke . '" d="' . $again . '" fill="none" stroke-width="' . G::n( max( 0.8, $width * 0.38 ) ) . '" stroke-linejoin="round" opacity="0.5"/>';
		}

		return $out;
	}
}
