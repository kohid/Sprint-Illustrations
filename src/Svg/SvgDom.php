<?php
/**
 * Safe DOM parsing and number formatting helpers for SVG markup.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Svg;

/**
 * Static helpers shared by the sanitizer, composer and manifest builder.
 */
final class SvgDom {

	public const NS       = 'http://www.w3.org/2000/svg';
	public const XLINK_NS = 'http://www.w3.org/1999/xlink';

	/**
	 * Parse SVG markup into a DOMDocument without network access or entity expansion.
	 *
	 * @param string $markup SVG markup.
	 * @return \DOMDocument
	 * @throws SvgException When the markup is not a well-formed <svg> document.
	 */
	public static function parse( string $markup ): \DOMDocument {
		$previous = libxml_use_internal_errors( true );

		$doc                     = new \DOMDocument( '1.0', 'UTF-8' );
		$doc->preserveWhiteSpace = false;
		$loaded                  = '' !== trim( $markup ) && $doc->loadXML( $markup, LIBXML_NONET | LIBXML_COMPACT );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $doc->documentElement instanceof \DOMElement || 'svg' !== $doc->documentElement->localName ) {
			throw new SvgException( 'Markup is not a well-formed SVG document.' );
		}

		return $doc;
	}

	/**
	 * Format a number compactly: fixed precision, no trailing zeros, no negative zero.
	 *
	 * @param float $value     Number.
	 * @param int   $precision Decimal places.
	 * @return string
	 */
	public static function num( float $value, int $precision = 2 ): string {
		$formatted = rtrim( rtrim( number_format( $value, $precision, '.', '' ), '0' ), '.' );

		return ( '-0' === $formatted || '' === $formatted ) ? '0' : $formatted;
	}
}
