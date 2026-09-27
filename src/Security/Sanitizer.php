<?php
/**
 * SVG sanitizer: enshrined/svg-sanitize plus a hardening pass.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Security;

use SprintIllustrations\Svg\SvgDom;
use SprintIllustrations\Svg\SvgException;
use SprintIllustrations\Vendor\enshrined\svgSanitize\Sanitizer as Engine;

/**
 * Sanitizes complete SVG documents and returns compact markup without an XML declaration.
 */
final class Sanitizer {

	/**
	 * Underlying allowlist sanitizer.
	 *
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->engine = new Engine();
		$this->engine->setAllowedTags( new AllowedTags() );
		$this->engine->setAllowedAttrs( new AllowedAttributes() );
		$this->engine->removeRemoteReferences( true );
		$this->engine->removeXMLTag( true );
	}

	/**
	 * Sanitize an SVG document.
	 *
	 * @param string $svg Untrusted SVG markup.
	 * @return string Clean markup (root <svg> element only).
	 * @throws SanitizationException When the markup cannot be parsed.
	 */
	public function sanitize( string $svg ): string {
		$clean = $this->engine->sanitize( $svg );

		if ( ! is_string( $clean ) || '' === trim( $clean ) ) {
			throw new SanitizationException( 'SVG could not be parsed for sanitization.' );
		}

		try {
			$doc = SvgDom::parse( $clean );
		} catch ( SvgException $e ) {
			throw new SanitizationException( $e->getMessage(), 0, $e );
		}

		$this->harden( $doc );

		return (string) $doc->saveXML( $doc->documentElement );
	}

	/**
	 * Defence in depth on top of the allowlist.
	 *
	 * @param \DOMDocument $doc Parsed document.
	 */
	private function harden( \DOMDocument $doc ): void {
		$xpath = new \DOMXPath( $doc );

		foreach ( iterator_to_array( $xpath->query( '//processing-instruction() | //comment()' ) ) as $node ) {
			$node->parentNode?->removeChild( $node );
		}

		foreach ( iterator_to_array( $xpath->query( '//*' ) ) as $element ) {
			if ( in_array( strtolower( $element->localName ), AllowedTags::BLOCKED, true ) ) {
				$element->parentNode?->removeChild( $element );
				continue;
			}

			foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
				if ( $this->is_dangerous( strtolower( $attribute->nodeName ), $attribute->value ) ) {
					$element->removeAttributeNode( $attribute );
				}
			}
		}
	}

	/**
	 * Whether an attribute must be removed.
	 *
	 * @param string $name  Lower-case attribute name.
	 * @param string $value Attribute value.
	 * @return bool
	 */
	private function is_dangerous( string $name, string $value ): bool {
		if ( str_starts_with( $name, 'on' ) || str_starts_with( $name, 'data-' ) || 'style' === $name ) {
			return true;
		}

		if ( ( 'href' === $name || 'xlink:href' === $name ) && ! str_starts_with( trim( $value ), '#' ) ) {
			return true;
		}

		if ( preg_match_all( '/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $matches ) ) {
			foreach ( $matches[2] as $target ) {
				if ( ! str_starts_with( trim( $target ), '#' ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
