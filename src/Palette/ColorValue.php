<?php
/**
 * CSS colour strings to hex.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Palette;

/**
 * Converts the colour formats Elementor stores into "#rrggbb". Alpha is dropped.
 */
final class ColorValue {

	/**
	 * Convert a CSS colour to hex.
	 *
	 * @param string $css "#rgb", "#rgba", "#rrggbb", "#rrggbbaa", "rgb()" or "rgba()".
	 * @return string|null Null for anything else (var(), hsl(), names, invalid).
	 */
	public static function to_hex( string $css ): ?string {
		$css = strtolower( trim( $css ) );

		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/D', $css ) ) {
			return Color::normalize_hex( $css );
		}

		if ( preg_match( '/^#([0-9a-f]{3})[0-9a-f]$/D', $css, $m ) || preg_match( '/^#([0-9a-f]{6})[0-9a-f]{2}$/D', $css, $m ) ) {
			return Color::normalize_hex( $m[1] );
		}

		$sep = '(?:\s*,\s*|\s+)';
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})' . $sep . '(\d{1,3})' . $sep . '(\d{1,3})\s*(?:[,\/]\s*[\d.]+%?\s*)?\)$/D', $css, $m ) ) {
			$channels = [ (int) $m[1], (int) $m[2], (int) $m[3] ];

			return max( $channels ) > 255 ? null : sprintf( '#%02x%02x%02x', ...$channels );
		}

		return null;
	}
}
