<?php
/**
 * Per-layer colour and gradient overrides.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Palette\Color;

/**
 * A layer key maps palette slots to a solid colour ("#rrggbb") or a two-stop gradient
 * ({ from, to, angle }). Only these slots can be overridden: skin and hair vary per instance.
 */
final class Paint {

	public const SLOTS = [ 'primary', 'secondary', 'accent', 'neutral', 'skin', 'hair' ];

	/**
	 * Slots that take a single colour only (a gradient on skin or hair makes no sense).
	 */
	public const SOLID_ONLY = [ 'skin', 'hair' ];
	public const MAX        = 64;

	/**
	 * Normalize a layer key => slot => paint map; invalid entries are dropped.
	 *
	 * @param mixed $paints Candidate.
	 * @return array<string, array<string, string|array{from: string, to: string, angle: int}>>
	 */
	public static function normalize( mixed $paints ): array {
		if ( ! is_array( $paints ) ) {
			return [];
		}

		$clean = [];
		foreach ( $paints as $key => $slots ) {
			if ( self::MAX <= count( $clean ) ) {
				break;
			}
			if ( ! is_string( $key ) || ! preg_match( '/^(?:[a-z][a-z0-9_-]*|item:(?:0|[1-9][0-9]?))$/D', $key ) || ! is_array( $slots ) ) {
				continue;
			}

			$entry = [];
			foreach ( self::SLOTS as $slot ) {
				$paint = self::normalize_paint( $slots[ $slot ] ?? null, in_array( $slot, self::SOLID_ONLY, true ) );
				if ( null !== $paint ) {
					$entry[ $slot ] = $paint;
				}
			}
			if ( $entry ) {
				$clean[ $key ] = $entry;
			}
		}

		return $clean;
	}

	/**
	 * One paint: a hex colour or a gradient.
	 *
	 * @param mixed $paint      Candidate.
	 * @param bool  $solid_only Only a single colour is allowed.
	 * @return string|array{from: string, to: string, angle: int}|null
	 */
	private static function normalize_paint( mixed $paint, bool $solid_only = false ): string|array|null {
		if ( is_string( $paint ) ) {
			return Color::normalize_hex( $paint );
		}
		if ( $solid_only || ! is_array( $paint ) || ! isset( $paint['from'], $paint['to'] ) || ! is_string( $paint['from'] ) || ! is_string( $paint['to'] ) ) {
			return null;
		}

		$from = Color::normalize_hex( $paint['from'] );
		$to   = Color::normalize_hex( $paint['to'] );
		if ( null === $from || null === $to ) {
			return null;
		}

		$angle = is_numeric( $paint['angle'] ?? null ) ? (int) round( (float) $paint['angle'] ) : 90;

		return [
			'from'  => $from,
			'to'    => $to,
			'angle' => ( ( $angle % 360 ) + 360 ) % 360,
		];
	}

	/**
	 * Add a <linearGradient> for every gradient paint to $group and resolve the layer's paints to
	 * values usable as fill/stroke: the colour itself, or "url(#id)". The ids are scoped afterwards
	 * by IdScoper, so they are unique per placed piece.
	 *
	 * @param \DOMElement                                                       $group Piece group.
	 * @param array<string, string|array{from: string, to: string, angle: int}> $paints Slot => paint.
	 * @return array<string, string> Slot => colour or url reference.
	 */
	public static function apply_defs( \DOMElement $group, array $paints ): array {
		$values = [];
		$doc    = $group->ownerDocument;
		foreach ( $paints as $slot => $paint ) {
			if ( is_string( $paint ) ) {
				$values[ $slot ] = $paint;
				continue;
			}

			$id       = 'si-paint-' . $slot;
			$radians  = deg2rad( (float) $paint['angle'] );
			$dx       = sin( $radians ) / 2;
			$dy       = -cos( $radians ) / 2;
			$gradient = $doc->createElementNS( $group->namespaceURI, 'linearGradient' );
			$gradient->setAttribute( 'id', $id );
			foreach ( [
				'x1' => 0.5 - $dx,
				'y1' => 0.5 - $dy,
				'x2' => 0.5 + $dx,
				'y2' => 0.5 + $dy,
			] as $name => $value ) {
				$gradient->setAttribute( $name, (string) round( $value, 4 ) );
			}
			foreach ( [
				'0' => $paint['from'],
				'1' => $paint['to'],
			] as $offset => $color ) {
				$stop = $doc->createElementNS( $group->namespaceURI, 'stop' );
				$stop->setAttribute( 'offset', (string) $offset );
				$stop->setAttribute( 'stop-color', $color );
				$gradient->appendChild( $stop );
			}
			$group->insertBefore( $gradient, $group->firstChild );

			$values[ $slot ] = 'url(#' . $id . ')';
		}//end foreach

		return $values;
	}
}
