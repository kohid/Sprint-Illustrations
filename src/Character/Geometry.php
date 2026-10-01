<?php
/**
 * Small drawing helpers for the character generator: numbers, smooth curves and tapered limbs.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Character;

/**
 * Pure functions that return SVG path data. Curves go through the given points (Catmull-Rom converted to
 * cubic Béziers), which is how the organic outlines of hair, torsos and skirts are drawn.
 */
final class Geometry {

	/**
	 * Number for SVG output: one decimal at most, no trailing zeros.
	 *
	 * @param float|int $value Value.
	 * @return string
	 */
	public static function n( float|int $value ): string {
		return rtrim( rtrim( number_format( (float) $value, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * Point as "x y".
	 *
	 * @param array{0: float|int, 1: float|int} $p Point.
	 * @return string
	 */
	public static function pt( array $p ): string {
		return self::n( $p[0] ) . ' ' . self::n( $p[1] );
	}

	/**
	 * A closed smooth shape through the points.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Points around the shape.
	 * @return string
	 */
	public static function closed( array $points ): string {
		$count = count( $points );
		if ( $count < 3 ) {
			return '';
		}

		$d = 'M' . self::pt( $points[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$p0 = $points[ ( $i - 1 + $count ) % $count ];
			$p1 = $points[ $i ];
			$p2 = $points[ ( $i + 1 ) % $count ];
			$p3 = $points[ ( $i + 2 ) % $count ];
			$d .= ' C' . self::pt( [ $p1[0] + ( $p2[0] - $p0[0] ) / 6, $p1[1] + ( $p2[1] - $p0[1] ) / 6 ] )
				. ' ' . self::pt( [ $p2[0] - ( $p3[0] - $p1[0] ) / 6, $p2[1] - ( $p3[1] - $p1[1] ) / 6 ] )
				. ' ' . self::pt( $p2 );
		}

		return $d . ' Z';
	}

	/**
	 * A smooth open curve through the points.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Points.
	 * @return string
	 */
	public static function open( array $points ): string {
		$count = count( $points );
		if ( $count < 2 ) {
			return '';
		}

		$d = 'M' . self::pt( $points[0] );
		for ( $i = 0; $i < $count - 1; $i++ ) {
			$p0 = $points[ max( 0, $i - 1 ) ];
			$p1 = $points[ $i ];
			$p2 = $points[ $i + 1 ];
			$p3 = $points[ min( $count - 1, $i + 2 ) ];
			$d .= ' C' . self::pt( [ $p1[0] + ( $p2[0] - $p0[0] ) / 6, $p1[1] + ( $p2[1] - $p0[1] ) / 6 ] )
				. ' ' . self::pt( [ $p2[0] - ( $p3[0] - $p1[0] ) / 6, $p2[1] - ( $p3[1] - $p1[1] ) / 6 ] )
				. ' ' . self::pt( $p2 );
		}

		return $d;
	}

	/**
	 * Straight-sided polygon.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Corners.
	 * @return string
	 */
	public static function polygon( array $points ): string {
		$d = [];
		foreach ( $points as $i => $p ) {
			$d[] = ( 0 === $i ? 'M' : 'L' ) . self::pt( $p );
		}

		return implode( ' ', $d ) . ' Z';
	}

	/**
	 * A tapered limb with round ends between two joints.
	 *
	 * @param array{0: float|int, 1: float|int} $a  Start joint.
	 * @param array{0: float|int, 1: float|int} $b  End joint.
	 * @param float                             $wa Width at the start.
	 * @param float                             $wb Width at the end.
	 * @return string
	 */
	public static function limb( array $a, array $b, float $wa, float $wb ): string {
		$dx  = $b[0] - $a[0];
		$dy  = $b[1] - $a[1];
		$len = max( 0.001, sqrt( $dx * $dx + $dy * $dy ) );
		$nx  = -$dy / $len;
		$ny  = $dx / $len;
		$ra  = $wa / 2;
		$rb  = $wb / 2;

		return sprintf(
			'M%1$s L%2$s A%3$s %3$s 0 0 0 %4$s L%5$s A%6$s %6$s 0 0 0 %1$s Z',
			self::pt( [ $a[0] + $nx * $ra, $a[1] + $ny * $ra ] ),
			self::pt( [ $b[0] + $nx * $rb, $b[1] + $ny * $rb ] ),
			self::n( $rb ),
			self::pt( [ $b[0] - $nx * $rb, $b[1] - $ny * $rb ] ),
			self::pt( [ $a[0] - $nx * $ra, $a[1] - $ny * $ra ] ),
			self::n( $ra )
		);
	}

	/**
	 * The point a given length away at an angle, measured from straight down. Positive angles swing
	 * outwards (away from the body's middle) on the given side; 180 is straight up, negative crosses the body.
	 *
	 * @param array{0: float|int, 1: float|int} $from  Start.
	 * @param float                             $len   Length.
	 * @param float                             $angle Degrees.
	 * @param string                            $side  "l" or "r".
	 * @return array{0: float, 1: float}
	 */
	public static function reach( array $from, float $len, float $angle, string $side ): array {
		$rad  = deg2rad( $angle );
		$sign = 'r' === $side ? 1 : -1;

		return [ $from[0] + $sign * $len * sin( $rad ), $from[1] + $len * cos( $rad ) ];
	}

	/**
	 * Mirror a point across the vertical middle line.
	 *
	 * @param array{0: float|int, 1: float|int} $p  Point.
	 * @param float|int                         $cx Middle x.
	 * @return array{0: float, 1: float}
	 */
	public static function mirror( array $p, float|int $cx = 80 ): array {
		return [ 2 * $cx - $p[0], (float) $p[1] ];
	}
}
