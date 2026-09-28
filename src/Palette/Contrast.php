<?php
/**
 * WCAG contrast maths.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Relative luminance and contrast ratio (WCAG 2.x).
 */
final class Contrast {

	/**
	 * Below this ratio against the background, a slot is flagged as hard to see.
	 */
	public const MIN_RATIO = 1.3;

	/**
	 * Contrast ratio between two colours, 1.0 to 21.0. Invalid colours count as black.
	 *
	 * @param string $hex_a Colour.
	 * @param string $hex_b Colour.
	 * @return float
	 */
	public static function ratio( string $hex_a, string $hex_b ): float {
		$a = self::luminance( $hex_a );
		$b = self::luminance( $hex_b );

		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	/**
	 * Relative luminance, 0.0 to 1.0.
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	public static function luminance( string $hex ): float {
		$hex     = Color::normalize_hex( $hex ) ?? '#000000';
		$channel = static function ( string $pair ): float {
			$c = hexdec( $pair ) / 255;

			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};

		return 0.2126 * $channel( substr( $hex, 1, 2 ) ) + 0.7152 * $channel( substr( $hex, 3, 2 ) ) + 0.0722 * $channel( substr( $hex, 5, 2 ) );
	}
}
