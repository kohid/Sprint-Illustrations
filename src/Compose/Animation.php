<?php
/**
 * Layer animations: normalization and the CSS classes that drive them.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * An entrance (plays once) and/or a loop per layer. Motion is pure CSS keyed by these classes
 * (assets/front/illustration.css), so markup stays free of style attributes and scripts.
 */
final class Animation {

	public const ENTER = [ 'none', 'fade', 'rise', 'pop' ];
	public const LOOP  = [ 'none', 'float', 'sway', 'pulse', 'spin', 'twinkle' ];
	public const SPEED = [ 'slow', 'normal', 'fast' ];
	public const MAX   = 64;
	public const DELAY = 3.0;

	/**
	 * Normalize a layer key => settings map; entries that animate nothing are dropped.
	 *
	 * @param mixed $animations Candidate.
	 * @return array<string, array{enter: string, loop: string, delay: float, speed: string}>
	 */
	public static function normalize( mixed $animations ): array {
		if ( ! is_array( $animations ) ) {
			return [];
		}

		$pick  = static fn( mixed $value, array $allowed, string $fallback ): string => is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $fallback;
		$clean = [];
		foreach ( $animations as $key => $entry ) {
			if ( self::MAX <= count( $clean ) ) {
				break;
			}
			if ( ! is_string( $key ) || ! preg_match( '/^(?:[a-z][a-z0-9_-]*|item:(?:0|[1-9][0-9]?))$/D', $key ) || ! is_array( $entry ) ) {
				continue;
			}

			$enter = $pick( $entry['enter'] ?? 'none', self::ENTER, 'none' );
			$loop  = $pick( $entry['loop'] ?? 'none', self::LOOP, 'none' );
			if ( 'none' === $enter && 'none' === $loop ) {
				continue;
			}

			$delay         = is_numeric( $entry['delay'] ?? null ) ? (float) $entry['delay'] : 0.0;
			$clean[ $key ] = [
				'enter' => $enter,
				'loop'  => $loop,
				'delay' => round( max( 0.0, min( self::DELAY, $delay ) ), 1 ),
				'speed' => $pick( $entry['speed'] ?? 'normal', self::SPEED, 'normal' ),
			];
		}//end foreach

		return $clean;
	}

	/**
	 * Classes for the outer (entrance + timing) and inner (loop) groups of an animated layer.
	 *
	 * @param array{enter: string, loop: string, delay: float, speed: string} $entry Normalized entry.
	 * @return array{outer: string, inner: string}
	 */
	public static function classes( array $entry ): array {
		$outer = [];
		if ( 'none' !== $entry['enter'] ) {
			$outer[] = 'si-a-enter-' . $entry['enter'];
		}
		$outer[] = 'si-a-d-' . (int) round( $entry['delay'] * 10 );
		$outer[] = 'si-a-s-' . $entry['speed'];

		return [
			'outer' => implode( ' ', $outer ),
			'inner' => 'none' === $entry['loop'] ? '' : 'si-a-loop-' . $entry['loop'],
		];
	}
}
