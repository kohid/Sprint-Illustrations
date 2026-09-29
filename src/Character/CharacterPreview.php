<?php
/**
 * A character as it looks in a palette: recoloured, sanitized, without the anchor markers.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Character;

use SprintIllustrations\Compose\Recolorer;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\SvgDom;

/**
 * The same per-piece steps a saved character goes through when a scene is composed, so what the
 * builder shows is what the library piece will look like.
 */
final class CharacterPreview {

	/**
	 * Render a character.
	 *
	 * @param CharacterSpec $spec      Choices.
	 * @param Palette       $palette   Palette.
	 * @param Sanitizer     $sanitizer Sanitizer.
	 * @return string SVG markup.
	 */
	public static function render( CharacterSpec $spec, Palette $palette, Sanitizer $sanitizer ): string {
		$doc  = SvgDom::parse( CharacterBuilder::svg( $spec ) );
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
