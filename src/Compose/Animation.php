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

	public const ENTER = [ 'none', 'fade', 'rise', 'pop', 'slide-left', 'slide-right', 'drop', 'zoom', 'turn' ];
	public const LOOP  = [ 'none', 'float', 'sway', 'pulse', 'spin', 'twinkle', 'bounce', 'wobble', 'swing', 'drive', 'ping', 'orbit' ];
	public const SPEED = [ 'slow', 'normal', 'fast' ];
	public const MAX   = 64;
	public const DELAY = 3.0;

	/**
	 * CSS shared by every animated layer. Each string here also appears verbatim in
	 * assets/front/illustration.css (AnimationTest checks this), so pages and files move the same way.
	 */
	public const CSS_BASE = '.si-a-layer,.si-a-layer>g{transform-box:fill-box;transform-origin:50% 100%}' . "\n"
		. '.si-a-layer>.si-a-loop-spin,.si-a-layer>.si-a-loop-pulse,.si-a-layer>.si-a-loop-twinkle,.si-a-layer>.si-a-loop-ping,.si-a-layer>.si-a-loop-orbit,.si-a-enter-pop,.si-a-enter-zoom,.si-a-enter-turn{transform-origin:50% 50%}' . "\n"
		. '.si-a-layer>.si-a-loop-swing{transform-origin:50% 0}' . "\n"
		. '.si-a-s-slow{--si-speed:1.6}.si-a-s-normal{--si-speed:1}.si-a-s-fast{--si-speed:.6}';

	public const CSS_ENTER = '.si-a-enter-fade,.si-a-enter-rise,.si-a-enter-pop,.si-a-enter-slide-left,.si-a-enter-slide-right,.si-a-enter-drop,.si-a-enter-zoom,.si-a-enter-turn{animation:.8s cubic-bezier(.2,.7,.3,1) calc(var(--si-delay,0s)) both;animation-duration:calc(.8s * var(--si-speed,1))}';

	public const CSS_LOOP = '.si-a-layer>[class^="si-a-loop-"]{animation-iteration-count:infinite;animation-delay:var(--si-delay,0s);animation-fill-mode:both}' . "\n"
		. '.si-a-layer[class*="si-a-enter-"]>[class^="si-a-loop-"]{animation-delay:calc(var(--si-delay,0s) + .8s * var(--si-speed,1))}';

	public const CSS_RULES = [
		'fade'        => '.si-a-enter-fade{animation-name:si-a-fade}',
		'rise'        => '.si-a-enter-rise{animation-name:si-a-rise}',
		'pop'         => '.si-a-enter-pop{animation-name:si-a-pop}',
		'slide-left'  => '.si-a-enter-slide-left{animation-name:si-a-slide-left}',
		'slide-right' => '.si-a-enter-slide-right{animation-name:si-a-slide-right}',
		'drop'        => '.si-a-enter-drop{animation-name:si-a-drop;animation-duration:calc(1.1s * var(--si-speed,1));animation-timing-function:ease-out}',
		'zoom'        => '.si-a-enter-zoom{animation-name:si-a-zoom}',
		'turn'        => '.si-a-enter-turn{animation-name:si-a-turn}',
		'float'       => '.si-a-loop-float{animation-name:si-a-float;animation-duration:calc(4s * var(--si-speed,1));animation-timing-function:ease-in-out}',
		'sway'        => '.si-a-loop-sway{animation-name:si-a-sway;animation-duration:calc(3.5s * var(--si-speed,1));animation-timing-function:ease-in-out}',
		'pulse'       => '.si-a-loop-pulse{animation-name:si-a-pulse;animation-duration:calc(2.4s * var(--si-speed,1));animation-timing-function:ease-in-out}',
		'spin'        => '.si-a-loop-spin{animation-name:si-a-spin;animation-duration:calc(14s * var(--si-speed,1));animation-timing-function:linear}',
		'twinkle'     => '.si-a-loop-twinkle{animation-name:si-a-twinkle;animation-duration:calc(1.8s * var(--si-speed,1));animation-timing-function:ease-in-out}',
		'bounce'      => '.si-a-loop-bounce{animation-name:si-a-bounce;animation-duration:calc(1.6s * var(--si-speed,1));animation-timing-function:ease-out}',
		'wobble'      => '.si-a-loop-wobble{animation-name:si-a-wobble;animation-duration:calc(2.6s * var(--si-speed,1));animation-timing-function:ease-in-out}',
		'swing'       => '.si-a-loop-swing{animation-name:si-a-swing;animation-duration:calc(3s * var(--si-speed,1));animation-timing-function:ease-in-out}',
		'drive'       => '.si-a-loop-drive{animation-name:si-a-drive;animation-duration:calc(7s * var(--si-speed,1));animation-timing-function:linear}',
		'ping'        => '.si-a-loop-ping{animation-name:si-a-ping;animation-duration:calc(2s * var(--si-speed,1));animation-timing-function:ease-out}',
		'orbit'       => '.si-a-loop-orbit{animation-name:si-a-orbit;animation-duration:calc(6s * var(--si-speed,1));animation-timing-function:linear}',
	];

	public const CSS_KEYFRAMES = [
		'fade'        => '@keyframes si-a-fade{from{opacity:0}}',
		'rise'        => '@keyframes si-a-rise{from{opacity:0;transform:translateY(24px)}}',
		'pop'         => '@keyframes si-a-pop{from{opacity:0;transform:scale(.6)}}',
		'slide-left'  => '@keyframes si-a-slide-left{from{opacity:0;transform:translateX(-80px)}}',
		'slide-right' => '@keyframes si-a-slide-right{from{opacity:0;transform:translateX(80px)}}',
		'drop'        => '@keyframes si-a-drop{0%{opacity:0;transform:translateY(-90px)}50%{opacity:1;transform:translateY(0)}68%{transform:translateY(-16px)}84%{transform:translateY(0)}92%{transform:translateY(-5px)}100%{transform:translateY(0)}}',
		'zoom'        => '@keyframes si-a-zoom{0%{opacity:0;transform:scale(.2)}70%{opacity:1;transform:scale(1.08)}100%{transform:scale(1)}}',
		'turn'        => '@keyframes si-a-turn{from{opacity:0;transform:rotate(-120deg) scale(.4)}}',
		'float'       => '@keyframes si-a-float{50%{transform:translateY(-10px)}}',
		'sway'        => '@keyframes si-a-sway{0%,100%{transform:rotate(-3deg)}50%{transform:rotate(3deg)}}',
		'pulse'       => '@keyframes si-a-pulse{50%{transform:scale(1.06)}}',
		'spin'        => '@keyframes si-a-spin{to{transform:rotate(360deg)}}',
		'twinkle'     => '@keyframes si-a-twinkle{50%{opacity:.35;transform:scale(.9)}}',
		'bounce'      => '@keyframes si-a-bounce{0%,60%,100%{transform:translateY(0)}20%{transform:translateY(-18px)}40%{transform:translateY(0)}50%{transform:translateY(-6px)}}',
		'wobble'      => '@keyframes si-a-wobble{0%,100%{transform:rotate(0)}15%{transform:rotate(-5deg)}30%{transform:rotate(4deg)}45%{transform:rotate(-3deg)}60%{transform:rotate(2deg)}75%{transform:rotate(-1deg)}}',
		'swing'       => '@keyframes si-a-swing{0%,100%{transform:rotate(8deg)}50%{transform:rotate(-8deg)}}',
		'drive'       => '@keyframes si-a-drive{0%{opacity:0;transform:translateX(-60px)}10%,85%{opacity:1}100%{opacity:0;transform:translateX(60px)}}',
		'ping'        => '@keyframes si-a-ping{0%{opacity:1;transform:scale(.9)}70%,100%{opacity:0;transform:scale(1.5)}}',
		'orbit'       => '@keyframes si-a-orbit{0%,100%{transform:translate(0,-8px)}25%{transform:translate(8px,0)}50%{transform:translate(0,8px)}75%{transform:translate(-8px,0)}}',
	];

	/**
	 * Stylesheet for a standalone animated SVG file: only the motions, delays and speeds in use.
	 *
	 * @param array<string, array{enter: string, loop: string, delay: float, speed: string}> $animations Normalized animations.
	 * @return string Empty when nothing is animated.
	 */
	public static function css( array $animations ): string {
		if ( ! $animations ) {
			return '';
		}

		$motions = [];
		$delays  = [];
		foreach ( $animations as $entry ) {
			foreach ( [ $entry['enter'], $entry['loop'] ] as $motion ) {
				if ( 'none' !== $motion ) {
					$motions[ $motion ] = true;
				}
			}
			$tenths            = (int) round( $entry['delay'] * 10 );
			$delays[ $tenths ] = '.si-a-d-' . $tenths . '{--si-delay:' . ( $tenths / 10 ) . 's}';
		}
		ksort( $delays );

		$enters    = array_flip( array_diff( self::ENTER, [ 'none' ] ) );
		$has_enter = (bool) array_intersect_key( $motions, $enters );
		$has_loop  = (bool) array_diff_key( $motions, $enters );
		$rules     = array_merge(
			$has_enter ? [ self::CSS_ENTER ] : [],
			$has_loop ? [ self::CSS_LOOP ] : [],
			array_values( array_intersect_key( self::CSS_RULES, $motions ) )
		);

		return self::CSS_BASE . "\n" . implode( '', $delays ) . "\n"
			. '@media (prefers-reduced-motion:no-preference){' . "\n" . implode( "\n", $rules ) . "\n}\n"
			. implode( "\n", array_values( array_intersect_key( self::CSS_KEYFRAMES, $motions ) ) );
	}

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
