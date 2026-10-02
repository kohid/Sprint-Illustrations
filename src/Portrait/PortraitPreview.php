<?php
/**
 * A portrait as it looks in a palette: recoloured, sanitized, without the anchor markers.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Portrait;

use SprintIllustrations\Compose\Recolorer;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\SvgDom;

/**
 * The same per-piece steps a saved portrait goes through when a scene is composed, so what the page
 * shows is what the library piece will look like.
 */
final class PortraitPreview {

	/**
	 * Render a portrait.
	 *
	 * @param PortraitSpec $spec      Choices.
	 * @param Palette      $palette   Palette.
	 * @param Sanitizer    $sanitizer Sanitizer.
	 * @return string SVG markup.
	 */
	public static function render( PortraitSpec $spec, Palette $palette, Sanitizer $sanitizer ): string {
		$doc  = SvgDom::parse( PortraitBuilder::svg( $spec, 'Portrait', [ 'person' ], '' ) );
		$root = $doc->documentElement;

		foreach ( iterator_to_array( ( new \DOMXPath( $doc ) )->query( '//*[starts-with(@id, "anchor-")]' ) ) as $marker ) {
			$marker->parentNode->removeChild( $marker );
		}
		foreach ( [ 'data-si-label', 'data-si-tags', 'data-si-accepts', 'data-si-person' ] as $attribute ) {
			$root->removeAttribute( $attribute );
		}
		$root->setAttribute( 'aria-hidden', 'true' );
		$root->setAttribute( 'focusable', 'false' );

		( new Recolorer() )->apply( $root, $palette, $spec->skin, $spec->hair );

		return $sanitizer->sanitize( (string) $doc->saveXML( $root ) );
	}
}
