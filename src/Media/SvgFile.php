<?php
/**
 * Composer output as a standalone .svg file.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Media;

/**
 * Adds the XML declaration and an intrinsic width/height from the viewBox.
 */
final class SvgFile {

	private const DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

	/**
	 * Width and height from the root viewBox.
	 *
	 * @param string $markup SVG markup.
	 * @return array{0: int, 1: int}|null
	 */
	public static function size( string $markup ): ?array {
		if ( ! preg_match( '/<svg\b[^>]*\bviewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*"/', $markup, $m ) ) {
			return null;
		}

		return [ (int) round( (float) $m[1] ), (int) round( (float) $m[2] ) ];
	}

	/**
	 * Standalone file content (idempotent).
	 *
	 * @param string $markup SVG markup (instance IDs already applied).
	 * @return string
	 */
	public static function standalone( string $markup ): string {
		$markup = trim( str_replace( self::DECLARATION, '', $markup ) );
		$size   = self::size( $markup );

		if ( null !== $size && ! preg_match( '/^<svg\b[^>]*\swidth="/', $markup ) ) {
			$markup = (string) preg_replace( '/^<svg\b/', sprintf( '<svg width="%d" height="%d"', $size[0], $size[1] ), $markup, 1 );
		}

		return self::DECLARATION . $markup;
	}
}
