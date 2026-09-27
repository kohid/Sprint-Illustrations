<?php
/**
 * Prefixes IDs inside a subtree and rewrites references to them.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Makes gradient / clip / mask IDs unique per piece instance.
 */
final class IdScoper {

	/**
	 * Prefix every id in $root's subtree and rewrite #id, url(#id) and aria-* references within it.
	 *
	 * @param \DOMElement $root   Subtree root.
	 * @param string      $prefix Prefix, e.g. "__SIID__-p3-".
	 */
	public function scope( \DOMElement $root, string $prefix ): void {
		$xpath = new \DOMXPath( $root->ownerDocument );
		$map   = [];

		foreach ( iterator_to_array( $xpath->query( 'descendant-or-self::*[@id]', $root ) ) as $element ) {
			$old         = $element->getAttribute( 'id' );
			$map[ $old ] = $prefix . $old;
			$element->setAttribute( 'id', $map[ $old ] );
		}

		if ( ! $map ) {
			return;
		}

		foreach ( iterator_to_array( $xpath->query( 'descendant-or-self::*/@*', $root ) ) as $attribute ) {
			if ( 'id' === $attribute->nodeName ) {
				continue;
			}

			$value = $this->rewrite( $attribute->nodeName, $attribute->value, $map );
			if ( $value === $attribute->value ) {
				continue;
			}

			$owner = $attribute->ownerElement;
			if ( null !== $attribute->namespaceURI ) {
				$owner->setAttributeNS( $attribute->namespaceURI, $attribute->nodeName, $value );
			} else {
				$owner->setAttribute( $attribute->nodeName, $value );
			}
		}
	}

	/**
	 * Rewrite references in one attribute value.
	 *
	 * @param string                $name  Attribute name.
	 * @param string                $value Attribute value.
	 * @param array<string, string> $map   Old ID => new ID.
	 * @return string
	 */
	private function rewrite( string $name, string $value, array $map ): string {
		if ( 'href' === $name || 'xlink:href' === $name ) {
			$target = ltrim( trim( $value ), '#' );
			return str_starts_with( trim( $value ), '#' ) && isset( $map[ $target ] ) ? '#' . $map[ $target ] : $value;
		}

		if ( 'aria-labelledby' === $name || 'aria-describedby' === $name ) {
			return implode( ' ', array_map( static fn( $id ) => $map[ $id ] ?? $id, preg_split( '/\s+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY ) ) );
		}

		return (string) preg_replace_callback(
			'/url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)/',
			static fn( array $m ) => isset( $map[ $m[2] ] ) ? 'url(#' . $map[ $m[2] ] . ')' : $m[0],
			$value
		);
	}
}
