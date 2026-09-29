<?php
/**
 * Maps slot-* classes to explicit fill/stroke attributes.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Palette\Color;
use SprintIllustrations\Palette\Palette;

/**
 * Class grammar:
 *   slot-<name>[-light|-dark]          → fill
 *   slot-stroke-<name>[-light|-dark]   → stroke
 *   slot-outline                       → stroke (outline colour)
 * where <name> is a Palette slot (primary, secondary, accent, neutral, background, outline, skin, hair).
 */
final class Recolorer {

	/**
	 * Parse one class token.
	 *
	 * @param string $token Class token.
	 * @return array{prop: string, name: string, variant: string}|null Null when not a valid slot token.
	 */
	public static function parse_token( string $token ): ?array {
		if ( ! str_starts_with( $token, 'slot-' ) ) {
			return null;
		}

		$rest = substr( $token, 5 );
		$prop = 'fill';

		if ( str_starts_with( $rest, 'stroke-' ) ) {
			$prop = 'stroke';
			$rest = substr( $rest, 7 );
		}

		$variant = '';
		if ( preg_match( '/^(.+)-(light|dark)$/D', $rest, $match ) ) {
			$rest    = $match[1];
			$variant = $match[2];
		}

		if ( 'outline' === $rest ) {
			$prop = 'stroke';
		}

		if ( ! in_array( $rest, array_merge( Palette::SLOTS, Palette::LIST_SLOTS ), true ) ) {
			return null;
		}

		return [
			'prop'    => $prop,
			'name'    => $rest,
			'variant' => $variant,
		];
	}

	/**
	 * Recolour $root and its descendants in place and strip slot classes.
	 *
	 * @param \DOMElement $root       Subtree root.
	 * @param Palette     $palette    Palette.
	 * @param int         $skin_index Skin index for this piece instance.
	 * @param int         $hair_index Hair index for this piece instance.
	 * @param array<string, string> $overrides Slot => colour or url(#gradient) replacing the palette's.
	 */
	public function apply( \DOMElement $root, Palette $palette, int $skin_index = 0, int $hair_index = 0, array $overrides = [] ): void {
		$xpath = new \DOMXPath( $root->ownerDocument );

		foreach ( iterator_to_array( $xpath->query( 'descendant-or-self::*[@class]', $root ) ) as $element ) {
			$keep = [];

			foreach ( preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
				$slot = self::parse_token( $token );

				if ( null === $slot ) {
					if ( ! str_starts_with( $token, 'slot-' ) ) {
						$keep[] = $token;
					}
					continue;
				}

				$color = self::override( $overrides, $slot ) ?? $palette->resolve( $slot['name'], $slot['variant'], $skin_index, $hair_index );
				if ( null !== $color ) {
					$element->setAttribute( $slot['prop'], $color );
				}
			}

			if ( $keep ) {
				$element->setAttribute( 'class', implode( ' ', $keep ) );
			} else {
				$element->removeAttribute( 'class' );
			}
		}//end foreach
	}

	/**
	 * The override for a parsed token: the colour with the light/dark shift applied, or a gradient as is.
	 *
	 * @param array<string, string>                              $overrides Slot => colour or url(#gradient).
	 * @param array{prop: string, name: string, variant: string} $slot      Parsed token.
	 * @return string|null
	 */
	private static function override( array $overrides, array $slot ): ?string {
		$value = $overrides[ $slot['name'] ] ?? null;
		if ( null === $value || '' === $slot['variant'] || ! str_starts_with( $value, '#' ) ) {
			return $value;
		}

		return Color::adjust_lightness( $value, 'light' === $slot['variant'] ? Palette::VARIANT_DELTA : -Palette::VARIANT_DELTA );
	}
}
