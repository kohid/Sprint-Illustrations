<?php
/**
 * Canvas fitting and layer ordering for resolved placements.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Pure helpers used by SceneResolver after the template slots and free items are placed.
 */
final class SceneLayout {

	/**
	 * Scale a layout made for one canvas to fit another, centred (the extra space stays empty).
	 *
	 * @param array<Placement>                  $placements Placements in `$from` units.
	 * @param array{0: int|float, 1: int|float} $from       Layout canvas.
	 * @param array{0: int|float, 1: int|float} $to         Target canvas.
	 * @return array<Placement>
	 */
	public static function fit( array $placements, array $from, array $to ): array {
		if ( (float) $from[0] === (float) $to[0] && (float) $from[1] === (float) $to[1] ) {
			return $placements;
		}

		$k  = min( $to[0] / $from[0], $to[1] / $from[1] );
		$ox = ( $to[0] - $from[0] * $k ) / 2;
		$oy = ( $to[1] - $from[1] * $k ) / 2;

		return array_map( static fn( Placement $p ): Placement => $p->framed( $k, $ox, $oy ), $placements );
	}

	/**
	 * Paint order. Placements are grouped by layer (a top-level slot with the slots attached to it, or
	 * one free item); groups named in `$layers` take that order among the positions they already hold,
	 * the rest stay put, and each group keeps its own internal order.
	 *
	 * @param array<Placement>      $placements Placements in natural order (back to front).
	 * @param array<string, string> $roots      Slot name => layer key (attached slots map to their root).
	 * @param array<int, string>    $layers     Requested order, back to front.
	 * @return array{placements: array<Placement>, layers: array<int, string>}
	 */
	public static function order( array $placements, array $roots, array $layers ): array {
		$groups = [];
		foreach ( $placements as $placement ) {
			$groups[ $roots[ $placement->slot ] ?? $placement->slot ][] = $placement;
		}

		$keys   = array_map( 'strval', array_keys( $groups ) );
		$listed = array_values( array_filter( $layers, static fn( string $key ): bool => isset( $groups[ $key ] ) ) );
		if ( ! $listed ) {
			// No requested order: paint exactly as resolved.
			return [
				'placements' => $placements,
				'layers'     => $keys,
			];
		}

		$next = 0;
		foreach ( $keys as $i => $key ) {
			if ( in_array( $key, $listed, true ) ) {
				$keys[ $i ] = $listed[ $next++ ];
			}
		}

		$ordered = [];
		foreach ( $keys as $key ) {
			array_push( $ordered, ...$groups[ $key ] );
		}

		return [
			'placements' => $ordered,
			'layers'     => $keys,
		];
	}
}
