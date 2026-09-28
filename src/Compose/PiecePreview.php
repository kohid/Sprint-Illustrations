<?php
/**
 * A single piece rendered on its own (Builder thumbnails).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\Piece;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\SvgDom;

/**
 * The same per-piece steps the Composer uses, without a template.
 */
final class PiecePreview {

	/**
	 * Render a piece in its own viewBox, recoloured, ID-scoped and sanitized.
	 *
	 * @param Piece       $piece     Piece.
	 * @param Palette     $palette   Palette.
	 * @param PieceLoader $loader    Loader.
	 * @param Sanitizer   $sanitizer Sanitizer.
	 * @return string
	 */
	public static function render( Piece $piece, Palette $palette, PieceLoader $loader, Sanitizer $sanitizer ): string {
		$doc = new \DOMDocument( '1.0', 'UTF-8' );
		$svg = $doc->createElementNS( SvgDom::NS, 'svg' );
		$doc->appendChild( $svg );

		$svg->setAttribute( 'viewBox', implode( ' ', array_map( static fn( float $n ): string => SvgDom::num( $n ), $piece->view_box ) ) );
		$svg->setAttribute( 'aria-hidden', 'true' );
		$svg->setAttribute( 'focusable', 'false' );

		foreach ( $loader->load( $piece )->documentElement->childNodes as $child ) {
			if ( $child instanceof \DOMElement && ! in_array( $child->localName, [ 'title', 'desc', 'metadata' ], true ) ) {
				$svg->appendChild( $doc->importNode( $child, true ) );
			}
		}

		( new Recolorer() )->apply( $svg, $palette, 0, 0 );
		( new IdScoper() )->scope( $svg, 'si-piece-' . $piece->id . '-' );

		return $sanitizer->sanitize( (string) $doc->saveXML( $svg ) );
	}
}
