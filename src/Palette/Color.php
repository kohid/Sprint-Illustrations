<?php
/**
 * Hex / HSL colour helpers.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Stateless colour maths.
 */
final class Color {

	/**
	 * Normalize "#abc" / "ABCDEF" / "#aabbcc" to "#aabbcc". Returns null when invalid.
	 *
	 * @param string $hex Colour string.
	 * @return string|null
	 */
	public static function normalize_hex( string $hex ): ?string {
		$hex = ltrim( trim( $hex ), '#' );

		if ( preg_match( '/^[0-9a-f]{3}$/iD', $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return preg_match( '/^[0-9a-f]{6}$/iD', $hex ) ? '#' . strtolower( $hex ) : null;
	}

	/**
	 * Convert hex to HSL.
	 *
	 * @param string $hex Normalized hex colour.
	 * @return array{0: float, 1: float, 2: float} Hue 0-360, saturation 0-1, lightness 0-1.
	 */
	public static function to_hsl( string $hex ): array {
		$r = hexdec( substr( $hex, 1, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 3, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 5, 2 ) ) / 255;

		$max   = max( $r, $g, $b );
		$min   = min( $r, $g, $b );
		$l     = ( $max + $min ) / 2;
		$delta = $max - $min;

		if ( 0.0 === (float) $delta ) {
			return [ 0.0, 0.0, $l ];
		}

		$s = $l > 0.5 ? $delta / ( 2 - $max - $min ) : $delta / ( $max + $min );

		if ( $max === $r ) {
			$h = ( $g - $b ) / $delta + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $delta + 2;
		} else {
			$h = ( $r - $g ) / $delta + 4;
		}

		return [ $h * 60, $s, $l ];
	}

	/**
	 * Convert HSL to hex.
	 *
	 * @param float $h Hue 0-360.
	 * @param float $s Saturation 0-1.
	 * @param float $l Lightness 0-1.
	 * @return string
	 */
	public static function from_hsl( float $h, float $s, float $l ): string {
		$h = fmod( fmod( $h, 360 ) + 360, 360 ) / 360;

		if ( 0.0 === $s ) {
			$r = $l;
			$g = $l;
			$b = $l;
		} else {
			$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
			$p = 2 * $l - $q;
			$r = self::hue_to_rgb( $p, $q, $h + 1 / 3 );
			$g = self::hue_to_rgb( $p, $q, $h );
			$b = self::hue_to_rgb( $p, $q, $h - 1 / 3 );
		}

		return sprintf( '#%02x%02x%02x', (int) round( $r * 255 ), (int) round( $g * 255 ), (int) round( $b * 255 ) );
	}

	/**
	 * Shift lightness by $delta (e.g. +0.18 lighter, -0.18 darker), clamped to [0.04, 0.96].
	 *
	 * @param string $hex   Normalized hex colour.
	 * @param float  $delta Lightness delta.
	 * @return string
	 */
	public static function adjust_lightness( string $hex, float $delta ): string {
		[ $h, $s, $l ] = self::to_hsl( $hex );

		return self::from_hsl( $h, $s, max( 0.04, min( 0.96, $l + $delta ) ) );
	}

	/**
	 * HSL helper.
	 *
	 * @param float $p P.
	 * @param float $q Q.
	 * @param float $t Hue offset.
	 * @return float
	 */
	private static function hue_to_rgb( float $p, float $q, float $t ): float {
		if ( $t < 0 ) {
			++$t;
		}
		if ( $t > 1 ) {
			--$t;
		}
		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}
		if ( $t < 1 / 2 ) {
			return $q;
		}
		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}
		return $p;
	}
}
