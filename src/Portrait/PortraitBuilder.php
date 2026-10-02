<?php
/**
 * Draws a head-and-shoulders portrait as hand-drawn line art.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Portrait;

use SprintIllustrations\Character\Geometry as G;

/**
 * Pure: a PortraitSpec in, a library-ready SVG out (viewBox 400 × 440, colour only from slot classes,
 * `anchor-ground` at the bottom of the bust and `anchor-hold` at the hand). The head sits at about
 * (200, 170): eyes on y 172 and ±36 from the centre line, nose at 206, mouth at 242, chin at 268.
 */
final class PortraitBuilder {

	public const WIDTH  = 400;
	public const HEIGHT = 440;

	/** Where the face is centred. */
	private const CX = 200;

	/** Half-profiles of the face outline: [x offset from the centre, y], from the crown to the chin. */
	private const FACE_SIDES = [
		'oval'   => [ [ 0, 70 ], [ 44, 82 ], [ 62, 130 ], [ 62, 178 ], [ 52, 225 ], [ 28, 258 ], [ 0, 268 ] ],
		'round'  => [ [ 0, 72 ], [ 52, 80 ], [ 68, 130 ], [ 68, 185 ], [ 56, 232 ], [ 30, 262 ], [ 0, 270 ] ],
		'heart'  => [ [ 0, 72 ], [ 50, 80 ], [ 66, 128 ], [ 62, 175 ], [ 46, 225 ], [ 22, 258 ], [ 0, 272 ] ],
		'square' => [ [ 0, 74 ], [ 50, 82 ], [ 64, 130 ], [ 64, 190 ], [ 60, 235 ], [ 34, 262 ], [ 0, 266 ] ],
	];

	/**
	 * The SVG for a portrait.
	 *
	 * @param PortraitSpec       $spec   Choices.
	 * @param string             $label  Library label.
	 * @param array<int, string> $tags   Library tags.
	 * @param string             $person Person name ("" for none).
	 * @return string
	 */
	public static function svg( PortraitSpec $spec, string $label = 'Portrait', array $tags = [ 'person' ], string $person = '' ): string {
		$sk   = new Sketch( 'si-portrait' );
		$yaw  = [
			'front' => 0.0,
			'left'  => -24.0,
			'right' => 24.0,
		][ $spec->get( 'look' ) ] ?? 0.0;
		$tilt = [
			'none'  => 0.0,
			'left'  => -6.0,
			'right' => 6.0,
		][ $spec->get( 'tilt' ) ] ?? 0.0;

		$head_back = [ self::hair_back( $sk, $spec ) ];
		$head_back = [ self::turned( implode( '', $head_back ), $yaw, -5.0, 0.6 ) ];

		$head   = [];
		$head[] = self::ears( $sk, $spec, $yaw );
		$head[] = self::face( $sk, $spec );
		$head[] = self::turned( self::features( $sk, $spec ), $yaw, 14.0, 0.45 );
		$head[] = self::turned( self::hair_front( $sk, $spec ), $yaw, 6.0, 0.6 );
		$head[] = self::turned( self::headwear( $sk, $spec ), $yaw, 7.0, 0.55 );

		$rotate = abs( $tilt ) > 0.0 ? ' transform="rotate(' . G::n( $tilt ) . ' ' . self::CX . ' 300)"' : '';

		$hold = self::hold_point( $spec );
		$body = [
			self::scene( $sk, $spec ),
			'<g' . $rotate . '>' . implode( '', array_filter( $head_back ) ) . '</g>',
			self::body( $sk, $spec ),
			self::seatbelt( $sk, $spec ),
			self::locks( $sk, $spec ),
			'<g' . $rotate . '>' . implode( '', array_filter( $head ) ) . '</g>',
			self::hands( $sk, $spec ),
		];

		$attributes = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" data-si-label="%3$s" data-si-tags="%4$s" data-si-accepts="hold:handheld"%5$s',
			self::WIDTH,
			self::HEIGHT,
			htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			htmlspecialchars( implode( ',', $tags ), ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			'' !== $person ? ' data-si-person="' . htmlspecialchars( $person, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"' : ''
		);

		return '<svg ' . $attributes . ">\n\t" . implode( "\n\t", array_filter( $body ) )
			. "\n\t" . '<circle id="anchor-hold" cx="' . G::n( $hold[0] ) . '" cy="' . G::n( $hold[1] ) . '" r="3"/>'
			. "\n\t" . '<circle id="anchor-ground" cx="200" cy="436" r="3"/>' . "\n</svg>\n";
	}

	// ---------------------------------------------------------------- helpers.

	/**
	 * A darker or lighter shade of a token.
	 *
	 * @param string $token Token.
	 * @param string $shade "dark" or "light".
	 * @return string
	 */
	private static function shade( string $token, string $shade ): string {
		if ( 'background' === $token ) {
			return 'dark' === $shade ? 'neutral-light' : 'background';
		}
		if ( str_ends_with( $token, '-dark' ) ) {
			return 'dark' === $shade ? $token : substr( $token, 0, -5 );
		}
		if ( str_ends_with( $token, '-light' ) ) {
			return 'light' === $shade ? $token : substr( $token, 0, -6 );
		}

		return $token . '-' . $shade;
	}

	/**
	 * Mirror points about the centre line.
	 *
	 * @param array<int, array{0: float|int, 1: float|int}> $points Points.
	 * @return array<int, array{0: float, 1: float}>
	 */
	private static function mirror( array $points ): array {
		return array_map( static fn( array $p ): array => [ 2 * self::CX - $p[0], (float) $p[1] ], $points );
	}

	/**
	 * Markup that follows a head turn: slid and squeezed sideways about the centre line.
	 *
	 * @param string $markup Markup.
	 * @param float  $yaw    Turn in degrees.
	 * @param float  $reach  Slide at a full turn.
	 * @param float  $floor  Narrowest width, as a share of the front.
	 * @return string
	 */
	private static function turned( string $markup, float $yaw, float $reach, float $floor ): string {
		if ( '' === $markup || abs( $yaw ) < 1.0 ) {
			return $markup;
		}
		$rad   = deg2rad( $yaw );
		$scale = $floor + ( 1 - $floor ) * max( 0.0, cos( $rad ) );
		$shift = self::CX + $reach * sin( $rad ) - self::CX * $scale;

		return '<g transform="translate(' . G::n( $shift ) . ' 0) scale(' . G::n( $scale ) . ' 1)">' . $markup . '</g>';
	}

	/**
	 * Where a held object goes.
	 *
	 * @param PortraitSpec $spec Spec.
	 * @return array{0: float, 1: float}
	 */
	private static function hold_point( PortraitSpec $spec ): array {
		return match ( $spec->get( 'pose' ) ) {
			'wheel' => [ 292.0, 412.0 ],
			'wave'  => [ 338.0, 226.0 ],
			'phone' => [ 300.0, 300.0 ],
			default => [ 316.0, 420.0 ],
		};
	}

	// ---------------------------------------------------------------- scene.

	/**
	 * The frame behind the person.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function scene( Sketch $sk, PortraitSpec $spec ): string {
		$colour = $spec->get( 'scene_color' );
		$light  = self::shade( $colour, 'light' );
		$dark   = self::shade( $colour, 'dark' );

		if ( 'wash' === $spec->get( 'scene' ) ) {
			return '<g opacity="0.85">'
				. '<path class="slot-' . $light . '" d="' . G::closed( $sk->wobble( [ [ 40, 90 ], [ 160, 26 ], [ 300, 34 ], [ 372, 130 ], [ 360, 270 ], [ 300, 400 ], [ 150, 424 ], [ 50, 330 ], [ 24, 200 ] ], 9, 'wash-a' ) ) . '"/>'
				. '<path class="slot-' . $colour . '" opacity="0.5" d="' . G::closed( $sk->wobble( [ [ 280, 60 ], [ 350, 90 ], [ 372, 170 ], [ 330, 210 ], [ 270, 150 ] ], 6, 'wash-b' ) ) . '"/>'
				. '<path class="slot-' . $colour . '" opacity="0.4" d="' . G::closed( $sk->wobble( [ [ 36, 280 ], [ 84, 250 ], [ 120, 300 ], [ 90, 360 ], [ 40, 350 ] ], 6, 'wash-c' ) ) . '"/>'
				. '</g>';
		}

		if ( 'taxi' !== $spec->get( 'scene' ) ) {
			return '';
		}

		// A taxi driver's seat: window glass, the yellow door frame and a rear-view mirror.
		$out  = '<rect class="slot-neutral-light" x="0" y="0" width="400" height="440"/>';
		$out .= $sk->shape( [ [ 0, 0 ], [ 400, 0 ], [ 400, 38 ], [ 0, 52 ] ], $colour, $dark, 'roof', 2.6, 1.5 );
		$out .= $sk->shape( [ [ 0, 40 ], [ 46, 46 ], [ 40, 250 ], [ 0, 440 ] ], $colour, $dark, 'pillar', 2.6, 1.5 );
		$out .= $sk->shape( [ [ 0, 372 ], [ 130, 366 ], [ 270, 372 ], [ 400, 362 ], [ 400, 440 ], [ 0, 440 ] ], $colour, $dark, 'door', 2.6, 1.5 );
		$out .= '<path class="slot-stroke-neutral-dark" d="M330 40 L330 74" fill="none" stroke-width="4" stroke-linecap="round"/>';
		$out .= $sk->shape( [ [ 296, 78 ], [ 330, 70 ], [ 366, 80 ], [ 362, 100 ], [ 330, 106 ], [ 298, 98 ] ], 'neutral-dark', 'neutral-dark', 'mirror', 2.0, 0.8 );

		return $out;
	}

	// ---------------------------------------------------------------- body.

	/**
	 * Neck, chest and the garment.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function body( Sketch $sk, PortraitSpec $spec ): string {
		$top    = $spec->get( 'top' );
		$colour = $spec->get( 'top_color' );
		$dark   = self::shade( $colour, 'dark' );
		$light  = self::shade( $colour, 'light' );

		$out  = $sk->shape( [ [ 176, 236 ], [ 224, 236 ], [ 228, 316 ], [ 214, 344 ], [ 200, 350 ], [ 186, 344 ], [ 172, 316 ] ], 'skin', 'skin-dark', 'neck', 2.4, 0.8 );
		$out .= '<path class="slot-skin-dark" opacity="0.35" d="M178 262 Q200 292 222 262 L224 244 L176 244 Z"/>';

		if ( 'hoodie' === $top ) {
			$out .= $sk->shape( [ [ 150, 300 ], [ 176, 280 ], [ 224, 280 ], [ 250, 300 ], [ 262, 344 ], [ 138, 344 ] ], $light, $dark, 'hood', 2.6, 1.2 );
		}

		$shoulder = [ [ 40, 440 ], [ 46, 380 ], [ 76, 346 ], [ 128, 326 ], [ 170, 316 ] ];
		$neckline = match ( $top ) {
			'shirt', 'blazer' => [ [ 186, 322 ], [ 200, 352 ], [ 214, 322 ] ],
			'turtleneck'      => [ [ 176, 288 ], [ 200, 292 ], [ 224, 288 ] ],
			'tee'             => [ [ 182, 328 ], [ 200, 348 ], [ 218, 328 ] ],
			default           => [ [ 184, 334 ], [ 200, 346 ], [ 216, 334 ] ],
		};
		$right  = self::mirror( array_reverse( $shoulder ) );
		$points = array_merge( $shoulder, 'turtleneck' === $top ? [ [ 174, 300 ] ] : [], $neckline, 'turtleneck' === $top ? [ [ 226, 300 ] ] : [], $right );
		$out   .= $sk->shape( $points, $colour, $dark, 'top', 2.8, 1.2 );

		// What gives each garment its character.
		switch ( $top ) {
			case 'sweater':
				$out .= $sk->line( [ [ 184, 334 ], [ 200, 350 ], [ 216, 334 ] ], $dark, 'crew', 3.4, 0.6 );
				$out .= $sk->line( [ [ 96, 400 ], [ 112, 384 ], [ 124, 400 ] ], $dark, 'fold-a', 1.8 );
				$out .= $sk->line( [ [ 300, 392 ], [ 286, 376 ], [ 276, 394 ] ], $dark, 'fold-b', 1.8 );
				break;
			case 'tee':
				$out .= $sk->line( [ [ 112, 372 ], [ 126, 394 ] ], $dark, 'fold-a', 1.8 );
				$out .= $sk->line( [ [ 290, 372 ], [ 278, 396 ] ], $dark, 'fold-b', 1.8 );
				break;
			case 'shirt':
				$out .= $sk->shape( [ [ 160, 312 ], [ 190, 322 ], [ 200, 354 ], [ 176, 350 ] ], 'background', $dark, 'collar-l', 2.4, 0.8 );
				$out .= $sk->shape( [ [ 240, 312 ], [ 210, 322 ], [ 200, 354 ], [ 224, 350 ] ], 'background', $dark, 'collar-r', 2.4, 0.8 );
				$out .= '<circle class="slot-' . $dark . '" cx="200" cy="380" r="3.4"/><circle class="slot-' . $dark . '" cx="200" cy="410" r="3.4"/>';
				$out .= $sk->line( [ [ 200, 356 ], [ 200, 436 ] ], $dark, 'placket', 1.6, 0.4 );
				break;
			case 'hoodie':
				$out .= $sk->line( [ [ 184, 340 ], [ 180, 392 ] ], 'neutral-dark', 'cord-a', 2.4, 0.5 );
				$out .= $sk->line( [ [ 216, 340 ], [ 220, 392 ], [ 218, 404 ] ], 'neutral-dark', 'cord-b', 2.4, 0.5 );
				$out .= $sk->shape( [ [ 150, 400 ], [ 200, 384 ], [ 250, 400 ], [ 262, 436 ], [ 138, 436 ] ], $light, $dark, 'pocket', 2.2, 1.0 );
				break;
			case 'blazer':
				$out .= $sk->shape( [ [ 150, 322 ], [ 186, 322 ], [ 214, 410 ], [ 168, 392 ] ], $light, $dark, 'lapel-l', 2.4, 0.8 );
				$out .= $sk->shape( [ [ 250, 322 ], [ 214, 322 ], [ 186, 410 ], [ 232, 392 ] ], $light, $dark, 'lapel-r', 2.4, 0.8 );
				$out .= $sk->shape( [ [ 190, 326 ], [ 200, 352 ], [ 210, 326 ], [ 200, 330 ] ], 'background', $dark, 'shirt', 1.8, 0.4 );
				break;
			case 'turtleneck':
				$out .= $sk->line( [ [ 178, 300 ], [ 200, 308 ], [ 222, 300 ] ], $dark, 'roll', 2.4, 0.6 );
				$out .= $sk->line( [ [ 96, 400 ], [ 112, 384 ], [ 124, 400 ] ], $dark, 'fold-a', 1.8 );
				break;
		}//end switch

		return $out;
	}

	/**
	 * A seatbelt strap across the chest.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function seatbelt( Sketch $sk, PortraitSpec $spec ): string {
		if ( 'on' !== $spec->get( 'seatbelt' ) ) {
			return '';
		}

		return $sk->shape( [ [ 120, 326 ], [ 146, 318 ], [ 348, 440 ], [ 296, 440 ] ], 'neutral-dark', 'neutral-dark', 'belt', 2.0, 0.8 )
			. '<rect class="slot-neutral-light" x="268" y="404" width="22" height="16" rx="3" transform="rotate(32 279 412)"/>';
	}

	/**
	 * Locks of long hair that fall in front of the shoulders.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function locks( Sketch $sk, PortraitSpec $spec ): string {
		$hair = $spec->get( 'hair' );
		if ( ! in_array( $hair, [ 'shoulder', 'long', 'wavy' ], true ) ) {
			return '';
		}
		$len  = 'long' === $hair ? 410 : 350;
		$end  = 'wavy' === $hair ? 6 : 0;
		$lock = [ [ 146, 190 ], [ 138, 250 ], [ 130 - $end, 310 ], [ 136, $len ], [ 160 + $end, $len - 8 ], [ 168, 320 ], [ 160, 250 ], [ 160, 200 ] ];

		return $sk->shape( $lock, 'hair', 'hair-dark', 'lock-l', 2.6, 1.2 )
			. $sk->shape( self::mirror( $lock ), 'hair', 'hair-dark', 'lock-r', 2.6, 1.2 )
			. $sk->line( [ [ 148, 230 ], [ 144, 290 ], [ 146, $len - 24 ] ], 'hair-dark', 'lock-l-s', 1.6, 0.8 )
			. $sk->line( self::mirror( [ [ 148, 230 ], [ 144, 290 ], [ 146, $len - 24 ] ] ), 'hair-dark', 'lock-r-s', 1.6, 0.8 );
	}

	// ---------------------------------------------------------------- head.

	/**
	 * The face outline points.
	 *
	 * @param string $face Face shape.
	 * @return array<int, array{0: float, 1: float}>
	 */
	private static function face_points( string $face ): array {
		$side  = self::FACE_SIDES[ $face ] ?? self::FACE_SIDES['oval'];
		$right = [];
		foreach ( $side as $p ) {
			$right[] = [ (float) ( self::CX + $p[0] ), (float) $p[1] ];
		}
		$left = [];
		for ( $i = count( $side ) - 2; $i >= 1; $i-- ) {
			$left[] = [ (float) ( self::CX - $side[ $i ][0] ), (float) $side[ $i ][1] ];
		}

		return array_merge( $right, $left );
	}

	/**
	 * Ears.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @param float        $yaw  Head turn.
	 * @return string
	 */
	private static function ears( Sketch $sk, PortraitSpec $spec, float $yaw ): string {
		$side = self::FACE_SIDES[ $spec->get( 'face' ) ] ?? self::FACE_SIDES['oval'];
		$edge = $side[3][0];
		$out  = '';
		foreach ( [ -1, 1 ] as $dir ) {
			$x    = self::CX + $dir * ( $edge - 2 );
			$out .= $sk->shape( [ [ $x, 168 ], [ $x + $dir * 12, 172 ], [ $x + $dir * 14, 196 ], [ $x + $dir * 4, 210 ], [ $x, 206 ] ], 'skin', 'skin-dark', 'ear' . $dir, 2.2, 0.8 );
			$out .= $sk->line( [ [ $x + $dir * 4, 180 ], [ $x + $dir * 8, 190 ], [ $x + $dir * 4, 198 ] ], 'skin-dark', 'ear-in' . $dir, 1.4, 0.4 );
		}

		if ( 'none' !== $spec->get( 'earrings' ) ) {
			foreach ( [ -1, 1 ] as $dir ) {
				$x = self::CX + $dir * ( $edge + 3 );
				if ( 'studs' === $spec->get( 'earrings' ) ) {
					$out .= '<circle class="slot-accent" cx="' . G::n( $x ) . '" cy="212" r="4.6"/>';
				} else {
					$out .= '<circle class="slot-stroke-accent" cx="' . G::n( $x ) . '" cy="224" r="11" fill="none" stroke-width="3"/>';
				}
			}
		}
		unset( $yaw );

		return $out;
	}

	/**
	 * The face shape with cheek colour and freckles.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function face( Sketch $sk, PortraitSpec $spec ): string {
		return $sk->shape( self::face_points( $spec->get( 'face' ) ), 'skin', 'skin-dark', 'face', 2.8, 1.0 );
	}

	/**
	 * Eyes, brows, nose, mouth, cheeks, facial hair and glasses.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function features( Sketch $sk, PortraitSpec $spec ): string {
		$out    = '';
		$cheeks = $spec->get( 'cheeks' );

		if ( in_array( $cheeks, [ 'blush', 'both' ], true ) ) {
			$out .= '<ellipse class="slot-skin-dark" opacity="0.4" cx="153" cy="214" rx="20" ry="11"/><ellipse class="slot-skin-dark" opacity="0.4" cx="247" cy="214" rx="20" ry="11"/>';
		}
		if ( in_array( $cheeks, [ 'freckles', 'both' ], true ) ) {
			$dots = [ [ 148, 200 ], [ 160, 196 ], [ 170, 204 ], [ 156, 210 ], [ 142, 210 ], [ 166, 214 ] ];
			foreach ( $dots as $d ) {
				$out .= '<circle class="slot-skin-dark" opacity="0.85" cx="' . $d[0] . '" cy="' . $d[1] . '" r="1.7"/><circle class="slot-skin-dark" opacity="0.85" cx="' . ( 400 - $d[0] ) . '" cy="' . $d[1] . '" r="1.7"/>';
			}
		}

		$out .= self::eye( $sk, $spec, 1 ) . self::eye( $sk, $spec, -1 );
		$out .= self::brows( $sk, $spec );
		$out .= self::nose( $sk, $spec );

		$out .= self::facial_hair( $sk, $spec );
		$out .= self::mouth( $sk, $spec );
		$out .= self::glasses( $spec );

		return $out;
	}

	/**
	 * One eye. $dir is 1 for the right-hand side of the picture and -1 for the left.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @param int          $dir  Side.
	 * @return string
	 */
	private static function eye( Sketch $sk, PortraitSpec $spec, int $dir ): string {
		$cx    = self::CX + $dir * 36;
		$cy    = 172;
		$iris  = $spec->get( 'iris' );
		$style = $spec->get( 'eyes' );
		$ink   = 'neutral-dark';
		$at    = static fn( float $dx, float $dy ): array => [ $cx + $dir * $dx, $cy + $dy ];

		if ( 'happy' === $style ) {
			return $sk->line( [ $at( -15, 4 ), $at( 0, -10 ), $at( 15, 4 ) ], $ink, 'eye-h' . $dir, 4.2, 0.4 )
				. $sk->line( [ $at( 14, 0 ), $at( 20, -6 ) ], $ink, 'lash-h' . $dir, 2.6, 0.3 );
		}

		$rx = [
			'round'  => 19.0,
			'almond' => 20.0,
			'sleepy' => 19.0,
			'wide'   => 21.0,
		][ $style ] ?? 17.0;
		$ry = [
			'round'  => 22.0,
			'almond' => 15.0,
			'sleepy' => 17.0,
			'wide'   => 25.0,
		][ $style ] ?? 17.0;
		$ir = min( $ry - 3.5, 13.0 );

		$white = [ $at( -$rx, 0 ), $at( -$rx * 0.55, -$ry * 0.92 ), $at( $rx * 0.4, -$ry ), $at( $rx, -$ry * 0.2 ), $at( $rx * 0.6, $ry * 0.8 ), $at( -$rx * 0.3, $ry ) ];
		if ( 'almond' === $style ) {
			$white = [ $at( -$rx, 3 ), $at( -$rx * 0.4, -$ry * 0.8 ), $at( $rx * 0.5, -$ry * 0.8 ), $at( $rx, -3 ), $at( $rx * 0.4, $ry * 0.7 ), $at( -$rx * 0.4, $ry * 0.7 ) ];
		}

		$out  = '<path class="slot-background" d="' . G::closed( $white ) . '"/>';
		$out .= '<circle class="slot-' . $iris . '" cx="' . G::n( $cx + $dir * 1.5 ) . '" cy="' . G::n( $cy + 1 ) . '" r="' . G::n( $ir ) . '"/>';
		$out .= '<circle class="slot-' . self::shade( 'hair-dark' === $iris ? 'neutral' : $iris, 'dark' ) . '" cx="' . G::n( $cx + $dir * 1.5 ) . '" cy="' . G::n( $cy + 1 ) . '" r="' . G::n( $ir * 0.48 ) . '"/>';
		$out .= '<circle class="slot-background" cx="' . G::n( $cx + $dir * 1.5 - 4 ) . '" cy="' . G::n( $cy - 4 ) . '" r="3.4"/><circle class="slot-background" cx="' . G::n( $cx + $dir * 1.5 + 4 ) . '" cy="' . G::n( $cy + 5 ) . '" r="1.6"/>';
		if ( 'sleepy' === $style ) {
			$out .= '<path class="slot-skin" d="' . G::closed( [ $at( -$rx - 2, -$ry - 2 ), $at( $rx + 2, -$ry - 2 ), $at( $rx + 2, -3 ), $at( 0, -6 ), $at( -$rx - 2, -3 ) ] ) . '"/>';
		}

		$out .= $sk->shape( $white, '', $ink, 'eye' . $dir, 1.6, 0.5 );
		// A heavy upper lash line with a flick at the outer corner.
		$out .= $sk->line( [ $at( -$rx - 1, 2 ), $at( -$rx * 0.5, -$ry * 0.9 ), $at( $rx * 0.45, -$ry * 1.02 ), $at( $rx + 1, -$ry * 0.3 ), $at( $rx + 7, -$ry * 0.5 ) ], $ink, 'lash' . $dir, 4.0, 0.4 );
		$out .= $sk->line( [ $at( $rx * 0.2, -$ry - 1 ), $at( $rx * 0.35, -$ry - 7 ) ], $ink, 'lashup' . $dir, 2.0, 0.3 );
		$out .= $sk->line( [ $at( -$rx * 0.3, $ry * 0.95 ), $at( $rx * 0.3, $ry * 1.02 ) ], 'skin-dark', 'lower' . $dir, 1.4, 0.3 );

		return $out;
	}

	/**
	 * Brows.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function brows( Sketch $sk, PortraitSpec $spec ): string {
		$style = $spec->get( 'brows' );
		$width = [
			'soft'     => 4.0,
			'arched'   => 4.0,
			'straight' => 4.4,
			'thick'    => 7.5,
			'thin'     => 2.2,
		][ $style ] ?? 4.0;
		$out   = '';
		foreach ( [ 1, -1 ] as $dir ) {
			$cx  = self::CX + $dir * 38;
			$pts = match ( $style ) {
				'arched'   => [ [ $cx - $dir * 20, 142 ], [ $cx - $dir * 6, 130 ], [ $cx + $dir * 12, 130 ], [ $cx + $dir * 24, 142 ] ],
				'straight' => [ [ $cx - $dir * 20, 138 ], [ $cx, 134 ], [ $cx + $dir * 22, 135 ] ],
				'thick'    => [ [ $cx - $dir * 20, 140 ], [ $cx - $dir * 4, 132 ], [ $cx + $dir * 22, 136 ] ],
				'thin'     => [ [ $cx - $dir * 20, 140 ], [ $cx, 131 ], [ $cx + $dir * 22, 138 ] ],
				default    => [ [ $cx - $dir * 20, 142 ], [ $cx - $dir * 2, 134 ], [ $cx + $dir * 22, 138 ] ],
			};
			$out .= $sk->line( $pts, 'hair-dark', 'brow' . $dir, $width, 0.6 );
		}

		return $out;
	}

	/**
	 * Nose.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function nose( Sketch $sk, PortraitSpec $spec ): string {
		return match ( $spec->get( 'nose' ) ) {
			'line'   => $sk->line( [ [ 203, 186 ], [ 202, 206 ], [ 194, 214 ], [ 204, 215 ] ], 'skin-dark', 'nose', 2.4, 0.5 ),
			'pointy' => $sk->line( [ [ 204, 182 ], [ 208, 212 ], [ 198, 220 ], [ 190, 214 ] ], 'skin-dark', 'nose', 2.6, 0.5 )
				. $sk->line( [ [ 200, 222 ], [ 208, 220 ] ], 'skin-dark', 'nose-b', 2.0, 0.3 ),
			default  => $sk->line( [ [ 194, 206 ], [ 198, 212 ], [ 206, 212 ], [ 208, 206 ] ], 'skin-dark', 'nose', 2.6, 0.5 )
				. $sk->line( [ [ 204, 192 ], [ 203, 202 ] ], 'skin-dark', 'nose-b', 1.6, 0.4 ),
		};
	}

	/**
	 * Mouth.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function mouth( Sketch $sk, PortraitSpec $spec ): string {
		$beard = in_array( $spec->get( 'facial_hair' ), [ 'beard' ], true );
		$out   = $beard ? '<ellipse class="slot-skin" cx="200" cy="244" rx="26" ry="14"/>' : '';

		switch ( $spec->get( 'mouth' ) ) {
			case 'smile':
				$out .= $sk->shape( [ [ 176, 236 ], [ 200, 242 ], [ 224, 236 ], [ 214, 252 ], [ 200, 256 ], [ 186, 252 ] ], 'background', 'neutral-dark', 'mouth', 2.2, 0.5 );
				break;
			case 'grin':
				$out .= $sk->shape( [ [ 172, 234 ], [ 200, 240 ], [ 228, 234 ], [ 218, 258 ], [ 200, 262 ], [ 182, 258 ] ], 'background', 'neutral-dark', 'mouth', 2.4, 0.5 );
				$out .= $sk->line( [ [ 174, 236 ], [ 200, 244 ], [ 226, 236 ] ], 'neutral-dark', 'teeth', 1.6, 0.3 );
				break;
			case 'smirk':
				$out .= $sk->line( [ [ 184, 246 ], [ 200, 245 ], [ 218, 238 ], [ 224, 232 ] ], 'neutral-dark', 'mouth', 3.0, 0.4 );
				break;
			case 'open':
				$out .= $sk->shape( [ [ 184, 238 ], [ 200, 236 ], [ 216, 238 ], [ 214, 254 ], [ 200, 260 ], [ 186, 254 ] ], 'neutral-dark', 'neutral-dark', 'mouth', 2.0, 0.5 );
				$out .= '<ellipse class="slot-primary" cx="200" cy="254" rx="9" ry="4.5"/>';
				break;
			case 'serious':
				$out .= $sk->line( [ [ 184, 244 ], [ 200, 244 ], [ 216, 244 ] ], 'neutral-dark', 'mouth', 3.0, 0.4 );
				break;
			default:
				$out .= $sk->line( [ [ 184, 242 ], [ 200, 246 ], [ 216, 242 ] ], 'neutral-dark', 'mouth', 3.0, 0.4 );
				$out .= $sk->line( [ [ 192, 252 ], [ 208, 252 ] ], 'skin-dark', 'lip', 1.6, 0.3 );
		}//end switch

		return $out;
	}

	/**
	 * Stubble, moustache or beard.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function facial_hair( Sketch $sk, PortraitSpec $spec ): string {
		switch ( $spec->get( 'facial_hair' ) ) {
			case 'stubble':
				$out = '';
				for ( $i = 0; $i < 46; $i++ ) {
					$a    = 0.2 + $i / 46 * 2.74;
					$r    = 40 + ( $i % 3 ) * 7;
					$out .= '<circle class="slot-hair" opacity="0.55" cx="' . G::n( 200 - cos( $a ) * $r * 1.2 ) . '" cy="' . G::n( 208 + sin( $a ) * $r * 0.9 ) . '" r="1.2"/>';
				}

				return $out;
			case 'moustache':
				return $sk->shape( [ [ 168, 232 ], [ 186, 226 ], [ 200, 230 ], [ 214, 226 ], [ 232, 232 ], [ 214, 240 ], [ 200, 236 ], [ 186, 240 ] ], 'hair', 'hair-dark', 'moustache', 2.2, 0.6 );
			case 'beard':
				$pts = [ [ 142, 190 ], [ 146, 236 ], [ 166, 270 ], [ 200, 290 ], [ 234, 270 ], [ 254, 236 ], [ 258, 190 ], [ 240, 214 ], [ 220, 226 ], [ 200, 222 ], [ 180, 226 ], [ 160, 214 ] ];

				return $sk->shape( $pts, 'hair', 'hair-dark', 'beard', 2.6, 1.0 );
		}

		return '';
	}

	/**
	 * Glasses.
	 *
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function glasses( PortraitSpec $spec ): string {
		$style = $spec->get( 'glasses' );
		if ( 'none' === $style ) {
			return '';
		}
		$frame = ' fill="none" stroke-width="3.4" stroke-linejoin="round"';
		if ( 'round' === $style ) {
			$out = '<circle class="slot-stroke-neutral-dark" cx="164" cy="172" r="27"' . $frame . '/><circle class="slot-stroke-neutral-dark" cx="236" cy="172" r="27"' . $frame . '/>';
		} else {
			$out = '<rect class="slot-stroke-neutral-dark" x="137" y="150" width="54" height="42" rx="9"' . $frame . '/><rect class="slot-stroke-neutral-dark" x="209" y="150" width="54" height="42" rx="9"' . $frame . '/>';
		}

		return $out . '<path class="slot-stroke-neutral-dark" d="M190 170 Q200 164 210 170" fill="none" stroke-width="3.4"/>'
			. '<path class="slot-stroke-neutral-dark" d="M138 168 L128 164 M262 168 L272 164" fill="none" stroke-width="3"/>';
	}

	// ---------------------------------------------------------------- hair and headwear.

	/**
	 * Hair behind the head and shoulders.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function hair_back( Sketch $sk, PortraitSpec $spec ): string {
		$style = $spec->get( 'hair' );
		$one   = static fn( array $p, string $key ): string => $sk->shape( $p, 'hair', 'hair-dark', $key, 2.8, 1.2 );

		return match ( $style ) {
			'shoulder' => $one( [ [ 200, 52 ], [ 262, 66 ], [ 292, 130 ], [ 298, 220 ], [ 306, 330 ], [ 270, 350 ], [ 130, 350 ], [ 94, 330 ], [ 102, 220 ], [ 108, 130 ], [ 138, 66 ] ], 'hb' ),
			'long'     => $one( [ [ 200, 52 ], [ 264, 66 ], [ 296, 130 ], [ 304, 240 ], [ 318, 380 ], [ 280, 420 ], [ 120, 420 ], [ 82, 380 ], [ 96, 240 ], [ 104, 130 ], [ 136, 66 ] ], 'hb' ),
			'bob'      => $one( [ [ 200, 54 ], [ 266, 68 ], [ 292, 134 ], [ 296, 214 ], [ 286, 272 ], [ 250, 284 ], [ 150, 284 ], [ 114, 272 ], [ 104, 214 ], [ 108, 134 ], [ 134, 68 ] ], 'hb' ),
			'wavy'     => $one( [ [ 200, 50 ], [ 266, 64 ], [ 298, 130 ], [ 290, 210 ], [ 312, 270 ], [ 290, 330 ], [ 306, 372 ], [ 270, 356 ], [ 130, 356 ], [ 94, 372 ], [ 110, 330 ], [ 88, 270 ], [ 110, 210 ], [ 102, 130 ], [ 134, 64 ] ], 'hb' ),
			'ponytail' => $one( [ [ 200, 56 ], [ 258, 70 ], [ 270, 116 ], [ 140, 116 ], [ 142, 70 ] ], 'hb' )
				. $one( [ [ 266, 112 ], [ 304, 130 ], [ 326, 200 ], [ 318, 290 ], [ 296, 330 ], [ 290, 270 ], [ 290, 190 ], [ 270, 150 ] ], 'tail' )
				. '<ellipse class="slot-accent" cx="274" cy="124" rx="8" ry="12"/>',
			'bun'      => $one( [ [ 200, 56 ], [ 250, 68 ], [ 150, 68 ] ], 'hb' )
				. $one( [ [ 200, 8 ], [ 234, 18 ], [ 244, 50 ], [ 220, 70 ], [ 180, 70 ], [ 156, 50 ], [ 166, 18 ] ], 'bun' )
				. $sk->line( [ [ 176, 30 ], [ 200, 22 ], [ 224, 32 ] ], 'hair-dark', 'bun-s', 1.6, 0.6 ),
			'curly'    => $one( self::bumps( 200, 150, 106, 100, 12, 20 ), 'hb' ),
			default    => '',
		};
	}

	/**
	 * A ring of rounded bumps (curly hair).
	 *
	 * @param float $cx     Centre x.
	 * @param float $cy     Centre y.
	 * @param float $rx     Radius x.
	 * @param float $ry     Radius y.
	 * @param int   $count  Bumps.
	 * @param float $depth  How far each pushes out.
	 * @return array<int, array{0: float, 1: float}>
	 */
	private static function bumps( float $cx, float $cy, float $rx, float $ry, int $count, float $depth ): array {
		$pts = [];
		for ( $i = 0; $i < $count * 2; $i++ ) {
			$a     = -M_PI / 2 + $i / ( $count * 2 ) * 2 * M_PI;
			$k     = 0 === $i % 2 ? 1.0 : 0.82;
			$pts[] = [ $cx + cos( $a ) * ( $rx + $depth * 0.4 ) * $k, $cy + sin( $a ) * ( $ry + $depth * 0.4 ) * $k ];
		}

		return $pts;
	}

	/**
	 * Hair over the head: the fringe, parting and the hairline.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function hair_front( Sketch $sk, PortraitSpec $spec ): string {
		$style = $spec->get( 'hair' );
		if ( 'bald' === $style ) {
			return '';
		}
		if ( 'buzz' === $style ) {
			return $sk->shape( [ [ 200, 64 ], [ 248, 76 ], [ 262, 120 ], [ 254, 142 ], [ 230, 118 ], [ 200, 112 ], [ 170, 118 ], [ 146, 142 ], [ 138, 120 ], [ 152, 76 ] ], 'hair', 'hair-dark', 'hf', 1.6, 0.8 );
		}

		$short = in_array( $style, [ 'short', 'curly', 'bob' ], true );
		$wide  = 'curly' === $style ? 6 : 0;

		// A side-swept hairline: the parting sits left of the centre and the hair sweeps across the forehead.
		$cap = [
			[ 138 - $wide, 178 ],
			[ 138 - $wide, 118 ],
			[ 160, 74 ],
			[ 200, 58 ],
			[ 244, 74 ],
			[ 264 + $wide, 118 ],
			[ 264 + $wide, 178 ],
			[ 256, 142 ],
			[ 238, 118 ],
			[ 214, 104 ],
			[ 186, 100 ],
			[ 168, 118 ],
			[ 150, 142 ],
		];
		if ( $short ) {
			$cap[6] = [ 262 + $wide, 150 ];
			$cap[0] = [ 140 - $wide, 150 ];
		}

		$out  = $sk->shape( $cap, 'hair', 'hair-dark', 'hf', 2.8, 1.2 );
		$out .= $sk->line( [ [ 188, 100 ], [ 206, 80 ], [ 222, 62 ] ], 'hair-dark', 'part', 2.0, 0.5 );
		$out .= $sk->line( [ [ 160, 128 ], [ 178, 108 ], [ 196, 100 ] ], 'hair-dark', 'strand-a', 1.6, 0.6 );
		$out .= $sk->line( [ [ 236, 90 ], [ 248, 108 ], [ 254, 130 ] ], 'hair-dark', 'strand-b', 1.6, 0.6 );
		$out .= $sk->line( [ [ 146, 112 ], [ 146, 150 ], [ 140, 176 ] ], 'hair-dark', 'strand-c', 1.6, 0.6 );

		return $out;
	}

	/**
	 * Things worn on the head.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function headwear( Sketch $sk, PortraitSpec $spec ): string {
		$colour = $spec->get( 'headwear_color' );
		$dark   = self::shade( $colour, 'dark' );

		switch ( $spec->get( 'headwear' ) ) {
			case 'cap':
				return $sk->shape( [ [ 138, 120 ], [ 150, 72 ], [ 200, 52 ], [ 252, 72 ], [ 264, 120 ], [ 200, 108 ] ], $colour, $dark, 'cap', 2.8, 1.0 )
					. $sk->shape( [ [ 140, 114 ], [ 200, 106 ], [ 262, 116 ], [ 300, 132 ], [ 250, 126 ], [ 190, 122 ] ], $colour, $dark, 'brim', 2.6, 1.0 )
					. '<circle class="slot-' . $dark . '" cx="200" cy="54" r="5"/>';
			case 'beanie':
				return $sk->shape( [ [ 134, 124 ], [ 138, 76 ], [ 170, 46 ], [ 200, 42 ], [ 232, 46 ], [ 264, 76 ], [ 268, 124 ], [ 200, 112 ] ], $colour, $dark, 'beanie', 2.8, 1.0 )
					. $sk->shape( [ [ 132, 120 ], [ 200, 108 ], [ 270, 120 ], [ 270, 142 ], [ 200, 130 ], [ 130, 142 ] ], self::shade( $colour, 'light' ), $dark, 'cuff', 2.6, 0.8 )
					. $sk->line( [ [ 160, 62 ], [ 156, 108 ] ], $dark, 'rib-a', 1.4 ) . $sk->line( [ [ 200, 52 ], [ 200, 106 ] ], $dark, 'rib-b', 1.4 ) . $sk->line( [ [ 240, 62 ], [ 244, 108 ] ], $dark, 'rib-c', 1.4 );
			case 'headset':
				return '<path class="slot-stroke-' . $colour . '" d="M140 190 C132 90 268 90 260 190" fill="none" stroke-width="7" stroke-linecap="round"/>'
					. $sk->shape( [ [ 124, 172 ], [ 142, 164 ], [ 148, 210 ], [ 130, 214 ] ], $colour, $dark, 'cup-l', 2.4, 0.6 )
					. $sk->shape( [ [ 276, 172 ], [ 258, 164 ], [ 252, 210 ], [ 270, 214 ] ], $colour, $dark, 'cup-r', 2.4, 0.6 )
					. '<path class="slot-stroke-' . $dark . '" d="M130 212 C140 258 176 262 188 252" fill="none" stroke-width="3.4" stroke-linecap="round"/>'
					. '<circle class="slot-' . $dark . '" cx="190" cy="252" r="5"/>';
		}

		return '';
	}

	// ---------------------------------------------------------------- hands.

	/**
	 * Arms, hands and what they hold.
	 *
	 * @param Sketch       $sk   Pen.
	 * @param PortraitSpec $spec Spec.
	 * @return string
	 */
	private static function hands( Sketch $sk, PortraitSpec $spec ): string {
		$colour = $spec->get( 'top_color' );
		$dark   = self::shade( $colour, 'dark' );

		switch ( $spec->get( 'pose' ) ) {
			case 'wheel':
				$out  = '<path class="slot-stroke-neutral-dark" d="M30 520 C60 372 340 372 370 520" fill="none" stroke-width="22" stroke-linecap="round"/>';
				$out .= '<path class="slot-stroke-neutral" d="M44 510 C72 388 328 388 356 510" fill="none" stroke-width="5" stroke-linecap="round" opacity="0.6"/>';
				$out .= $sk->shape( [ [ 290, 330 ], [ 326, 356 ], [ 322, 410 ], [ 296, 424 ], [ 274, 380 ] ], $colour, $dark, 'sleeve', 2.6, 1.0 );
				$out .= self::hand( $sk, 292, 416, 'wheel' );

				return $out;
			case 'wave':
				$out  = $sk->shape( [ [ 300, 340 ], [ 338, 312 ], [ 348, 246 ], [ 328, 242 ], [ 308, 290 ], [ 280, 326 ] ], $colour, $dark, 'sleeve', 2.6, 1.0 );
				$out .= self::hand( $sk, 338, 240, 'open' );

				return $out;
			case 'phone':
				$out  = $sk->shape( [ [ 296, 340 ], [ 330, 332 ], [ 326, 296 ], [ 296, 292 ], [ 280, 322 ] ], $colour, $dark, 'sleeve', 2.6, 1.0 );
				$out .= $sk->shape( [ [ 288, 252 ], [ 322, 250 ], [ 326, 316 ], [ 292, 318 ] ], 'neutral-dark', 'neutral-dark', 'phone', 2.4, 0.5 );
				$out .= '<path class="slot-neutral-light" opacity="0.55" d="M296 258 L318 257 L320 308 L297 309 Z"/>';
				$out .= self::hand( $sk, 300, 304, 'grip' );

				return $out;
		}//end switch

		return '';
	}

	/**
	 * A hand: palm and fingers.
	 *
	 * @param Sketch $sk   Pen.
	 * @param float  $x    Wrist x.
	 * @param float  $y    Wrist y.
	 * @param string $kind "wheel", "open" or "grip".
	 * @return string
	 */
	private static function hand( Sketch $sk, float $x, float $y, string $kind ): string {
		if ( 'open' === $kind ) {
			$out = $sk->shape( [ [ $x - 14, $y ], [ $x - 18, $y - 26 ], [ $x, $y - 34 ], [ $x + 18, $y - 26 ], [ $x + 14, $y ] ], 'skin', 'skin-dark', 'palm', 2.2, 0.6 );
			foreach ( [ -16, -8, 0, 8, 16 ] as $i => $dx ) {
				$out .= $sk->shape( [ [ $x + $dx - 3.4, $y - 22 ], [ $x + $dx - 3.4, $y - 50 + abs( $dx ) * 0.7 ], [ $x + $dx + 3.4, $y - 50 + abs( $dx ) * 0.7 ], [ $x + $dx + 3.4, $y - 22 ] ], 'skin', 'skin-dark', 'finger' . $i, 1.8, 0.4 );
			}

			return $out;
		}

		$out = $sk->shape( [ [ $x - 16, $y - 12 ], [ $x - 10, $y - 24 ], [ $x + 10, $y - 24 ], [ $x + 18, $y - 8 ], [ $x + 10, $y + 8 ], [ $x - 10, $y + 8 ] ], 'skin', 'skin-dark', 'palm', 2.2, 0.6 );
		foreach ( [ -10, -3, 4, 11 ] as $i => $dx ) {
			$out .= $sk->shape( [ [ $x + $dx - 3.2, $y - 14 ], [ $x + $dx - 3.2, $y + 4 ], [ $x + $dx + 3.2, $y + 4 ], [ $x + $dx + 3.2, $y - 14 ] ], 'skin', 'skin-dark', 'finger' . $i, 1.6, 0.3 );
		}

		return $out;
	}
}
