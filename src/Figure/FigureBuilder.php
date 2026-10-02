<?php
/**
 * Draws a full-body cartoon figure with a big head, in the same hand-drawn line style as the portraits.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Figure;

use SprintIllustrations\Character\Geometry as G;
use SprintIllustrations\Portrait\Sketch;

/**
 * Pure: a FigureSpec in, a library-ready SVG out (viewBox 240 × 350, colour only from slot classes,
 * `anchor-ground` between the feet and `anchor-hold` at the hand). Proportions: the head is about a third
 * of the height (centre 120,100), shoulders at y 168, hips at 232, feet on y 334.
 */
final class FigureBuilder {

	public const WIDTH  = 240;
	public const HEIGHT = 350;

	private const CX     = 120;
	private const CY     = 100;
	private const GROUND = 334;

	/** Half-width of the torso by build. */
	private const BODY_W = [
		'slim'    => 31,
		'regular' => 36,
		'round'   => 43,
	];

	/** Limb thickness by build: [arm, leg]. */
	private const LIMB_W = [
		'slim'    => [ 15, 17 ],
		'regular' => [ 17, 19 ],
		'round'   => [ 19, 22 ],
	];

	/** Head half-width and half-height by face. */
	private const HEAD = [
		'round' => [ 60, 54 ],
		'oval'  => [ 54, 58 ],
		'wide'  => [ 66, 50 ],
	];

	/**
	 * Arm angles per pose and side, in degrees from straight down, positive swinging outward and up:
	 * [upper arm, forearm].
	 */
	private const ARMS = [
		'relaxed'  => [
			'r' => [ 14, 6 ],
			'l' => [ 14, 6 ],
		],
		'hips'     => [
			'r' => [ 36, -62 ],
			'l' => [ 36, -62 ],
		],
		'hold'     => [
			'r' => [ 20, -100 ],
			'l' => [ 20, -100 ],
		],
		'wave'     => [
			'r' => [ 125, 165 ],
			'l' => [ 14, 6 ],
		],
		'pockets'  => [
			'r' => [ 8, -38 ],
			'l' => [ 8, -38 ],
		],
		'cheer'    => [
			'r' => [ 135, 168 ],
			'l' => [ 135, 168 ],
		],
		'thinking' => [
			'r' => [ 30, -120 ],
			'l' => [ 10, 6 ],
		],
		'walk'     => [
			'r' => [ 28, 14 ],
			'l' => [ -22, -8 ],
		],
	];

	/**
	 * The SVG for a figure.
	 *
	 * @param FigureSpec         $spec   Choices.
	 * @param string             $label  Library label.
	 * @param array<int, string> $tags   Library tags.
	 * @param string             $person Person name ("" for none).
	 * @return string
	 */
	public static function svg( FigureSpec $spec, string $label = 'Figure', array $tags = [ 'person' ], string $person = '' ): string {
		$sk   = new Sketch( 'si-figure' );
		$tilt = [
			'none'  => 0.0,
			'left'  => -7.0,
			'right' => 7.0,
		][ $spec->get( 'tilt' ) ] ?? 0.0;

		$arms   = self::arm_points( $spec );
		$rotate = abs( $tilt ) > 0.0 ? ' transform="rotate(' . G::n( $tilt ) . ' ' . self::CX . ' 156)"' : '';

		$layers = [
			self::scene( $sk, $spec ),
			self::backpack( $sk, $spec ),
			'<g' . $rotate . '>' . self::hair_back( $sk, $spec ) . '</g>',
			self::legs( $sk, $spec ),
			self::torso( $sk, $spec ),
			self::carried( $sk, $spec ),
			'<g' . $rotate . '>' . self::head( $sk, $spec ) . '</g>',
			self::arms( $sk, $spec, $arms ),
		];

		$hold = 'hold' === $spec->get( 'pose' ) ? [ (float) self::CX, 196.0 ] : $arms['r'][2];

		$attributes = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" data-si-label="%3$s" data-si-tags="%4$s" data-si-accepts="hold:handheld"%5$s',
			self::WIDTH,
			self::HEIGHT,
			htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			htmlspecialchars( implode( ',', $tags ), ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			'' !== $person ? ' data-si-person="' . htmlspecialchars( $person, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"' : ''
		);

		return '<svg ' . $attributes . ">\n\t" . implode( "\n\t", array_filter( $layers ) )
			. "\n\t" . '<circle id="anchor-hold" cx="' . G::n( $hold[0] ) . '" cy="' . G::n( $hold[1] ) . '" r="3"/>'
			. "\n\t" . '<circle id="anchor-ground" cx="' . self::CX . '" cy="' . self::GROUND . '" r="3"/>' . "\n</svg>\n";
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
	 * The outline token for a fill.
	 *
	 * @param string $fill Fill token.
	 * @return string
	 */
	private static function edge( string $fill ): string {
		return self::shade( $fill, 'dark' );
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
	 * Head half-width and half-height.
	 *
	 * @param FigureSpec $spec Spec.
	 * @return array{0: int, 1: int}
	 */
	private static function head_size( FigureSpec $spec ): array {
		return self::HEAD[ $spec->get( 'face' ) ] ?? self::HEAD['round'];
	}

	/**
	 * Shoulder, elbow and hand for each arm.
	 *
	 * @param FigureSpec $spec Spec.
	 * @return array{r: array<int, array{0: float, 1: float}>, l: array<int, array{0: float, 1: float}>}
	 */
	private static function arm_points( FigureSpec $spec ): array {
		$w    = self::BODY_W[ $spec->get( 'build' ) ] ?? 36;
		$pose = self::ARMS[ $spec->get( 'pose' ) ] ?? self::ARMS['relaxed'];
		$out  = [];
		foreach ( [
			'r' => 1,
			'l' => -1,
		] as $side => $dir ) {
			$shoulder     = [ (float) ( self::CX + $dir * ( $w - 6 ) ), 172.0 ];
			$a1           = deg2rad( (float) $pose[ $side ][0] );
			$a2           = deg2rad( (float) $pose[ $side ][1] );
			$elbow        = [ $shoulder[0] + $dir * 26 * sin( $a1 ), $shoulder[1] + 26 * cos( $a1 ) ];
			$hand         = [ $elbow[0] + $dir * 24 * sin( $a2 ), $elbow[1] + 24 * cos( $a2 ) ];
			$out[ $side ] = [ $shoulder, $elbow, $hand ];
		}

		return $out;
	}

	// ---------------------------------------------------------------- scene.

	/**
	 * The watercolour splash and the soft ground shadow.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function scene( Sketch $sk, FigureSpec $spec ): string {
		$colour = $spec->get( 'scene_color' );
		$dark   = self::shade( $colour, 'dark' );
		$ground = '<ellipse class="slot-' . $dark . '" opacity="0.3" cx="120" cy="' . ( self::GROUND - 2 ) . '" rx="74" ry="11"/>';

		if ( 'splash' !== $spec->get( 'scene' ) ) {
			return $ground;
		}

		$out  = '<path class="slot-' . $colour . '" opacity="0.4" d="' . G::closed( $sk->wobble( [ [ 30, 90 ], [ 70, 30 ], [ 150, 24 ], [ 210, 70 ], [ 214, 160 ], [ 196, 250 ], [ 160, 300 ], [ 80, 306 ], [ 36, 250 ], [ 20, 170 ] ], 9, 'splash-a' ) ) . '"/>';
		$out .= '<path class="slot-' . $colour . '" opacity="0.5" d="' . G::closed( $sk->wobble( [ [ 150, 40 ], [ 206, 56 ], [ 220, 120 ], [ 176, 110 ] ], 6, 'splash-b' ) ) . '"/>';
		$out .= $ground;
		foreach ( [ [ 22, 40, 2.4 ], [ 44, 20, 1.6 ], [ 198, 24, 2.2 ], [ 226, 90, 1.8 ], [ 14, 150, 2 ], [ 228, 214, 2.4 ], [ 30, 300, 1.8 ], [ 206, 322, 1.6 ], [ 90, 14, 1.4 ] ] as $dot ) {
			$out .= '<circle class="slot-' . $dark . '" opacity="0.55" cx="' . $dot[0] . '" cy="' . $dot[1] . '" r="' . $dot[2] . '"/>';
		}

		return $out;
	}

	// ---------------------------------------------------------------- body.

	/**
	 * Legs and shoes.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function legs( Sketch $sk, FigureSpec $spec ): string {
		$width  = (float) ( self::LIMB_W[ $spec->get( 'build' ) ][1] ?? 19 );
		$bottom = $spec->get( 'bottom' );
		$colour = $spec->get( 'bottom_color' );
		$stance = 'walk' === $spec->get( 'pose' ) ? 15.0 : 4.0;
		$out    = '';

		foreach ( [
			-1 => 'l',
			1  => 'r',
		] as $dir => $side ) {
			$swing = 'walk' === $spec->get( 'pose' ) ? ( 'r' === $side ? 1 : -1 ) * $stance : $dir * $stance;
			$hip   = [ self::CX + $dir * 13, 230.0 ];
			$ankle = [ $hip[0] + 86 * sin( deg2rad( $swing ) ), $hip[1] + 86 * cos( deg2rad( $swing ) ) ];

			if ( 'trousers' === $bottom ) {
				$out .= $sk->limb( $hip, $ankle, $colour, self::edge( $colour ), $width, 'leg' . $side );
			} elseif ( 'shorts' === $bottom ) {
				$mid  = [ $hip[0] + 38 * sin( deg2rad( $swing ) ), $hip[1] + 38 * cos( deg2rad( $swing ) ) ];
				$out .= $sk->limb( $hip, $ankle, 'skin', 'skin-dark', $width - 4, 'leg' . $side );
				$out .= $sk->limb( $hip, $mid, $colour, self::edge( $colour ), $width + 3, 'short' . $side );
			} else {
				$out .= $sk->limb( $hip, $ankle, 'skin', 'skin-dark', $width - 5, 'leg' . $side );
			}
			$out .= self::shoe( $sk, $spec, $ankle, $dir, $side );
		}

		return $out;
	}

	/**
	 * One shoe, toes pointing outward.
	 *
	 * @param Sketch     $sk    Pen.
	 * @param FigureSpec $spec  Spec.
	 * @param array      $ankle Ankle point.
	 * @param int        $dir   -1 left, 1 right.
	 * @param string     $side  Side key.
	 * @return string
	 */
	private static function shoe( Sketch $sk, FigureSpec $spec, array $ankle, int $dir, string $side ): string {
		$colour = $spec->get( 'shoes_color' );
		$x      = $ankle[0];
		$y      = $ankle[1];
		$at     = static fn( float $dx, float $dy ): array => [ $x + $dir * $dx, $y + $dy ];

		$pts = match ( $spec->get( 'shoes' ) ) {
			'boots' => [ $at( -10, -16 ), $at( 8, -16 ), $at( 10, -2 ), $at( 18, 6 ), $at( 19, 15 ), $at( -11, 16 ) ],
			'flats' => [ $at( -9, -2 ), $at( 6, -4 ), $at( 16, 6 ), $at( 16, 14 ), $at( -10, 15 ) ],
			default => [ $at( -10, -5 ), $at( 6, -7 ), $at( 18, 5 ), $at( 19, 14 ), $at( 8, 18 ), $at( -11, 16 ) ],
		};

		$out = $sk->shape( $pts, $colour, self::edge( $colour ), 'shoe' . $side, 2.2, 0.6 );
		if ( 'flats' !== $spec->get( 'shoes' ) ) {
			$out .= $sk->line( [ $at( -10, 15 ), $at( 8, 16 ), $at( 18, 14 ) ], 'neutral-light', 'sole' . $side, 3.0, 0.3 );
		}

		return $out;
	}

	/**
	 * The torso and what is worn on it.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function torso( Sketch $sk, FigureSpec $spec ): string {
		$w      = (float) ( self::BODY_W[ $spec->get( 'build' ) ] ?? 36 );
		$top    = $spec->get( 'top' );
		$colour = $spec->get( 'top_color' );
		$dark   = self::edge( $colour );
		$light  = self::shade( $colour, 'light' );
		$bottom = $spec->get( 'bottom_color' );
		$hem    = [
			'tee'      => 232.0,
			'hoodie'   => 242.0,
			'jacket'   => 244.0,
			'overalls' => 232.0,
			'shirt'    => 236.0,
			'raincoat' => 268.0,
		][ $top ] ?? 236.0;
		$flare  = 'raincoat' === $top ? 8.0 : 2.0;
		$cx     = (float) self::CX;

		$out  = $sk->shape( [ [ $cx - 10, 146 ], [ $cx + 10, 146 ], [ $cx + 11, 170 ], [ $cx - 11, 170 ] ], 'skin', 'skin-dark', 'neck', 2.0, 0.5 );
		$body = [ [ $cx - $w + 10, 163 ], [ $cx, 160 ], [ $cx + $w - 10, 163 ], [ $cx + $w, 182 ], [ $cx + $w + $flare, $hem - 6 ], [ $cx, $hem + 2 ], [ $cx - $w - $flare, $hem - 6 ], [ $cx - $w, 182 ] ];
		$out .= $sk->shape( $body, $colour, $dark, 'torso', 2.4, 0.9 );

		if ( 'skirt' === $spec->get( 'bottom' ) ) {
			$out .= $sk->shape( [ [ $cx - $w + 2, 222 ], [ $cx + $w - 2, 222 ], [ $cx + $w + 16, 274 ], [ $cx, 280 ], [ $cx - $w - 16, 274 ] ], $bottom, self::edge( $bottom ), 'skirt', 2.4, 0.9 );
		}

		switch ( $top ) {
			case 'tee':
				$out .= $sk->line( [ [ $cx - 15, 162 ], [ $cx, 174 ], [ $cx + 15, 162 ] ], $dark, 'neckline', 2.6, 0.4 );
				break;
			case 'hoodie':
				$out .= $sk->shape( [ [ $cx - 27, 160 ], [ $cx, 154 ], [ $cx + 27, 160 ], [ $cx + 20, 178 ], [ $cx, 184 ], [ $cx - 20, 178 ] ], $light, $dark, 'hood', 2.4, 0.6 );
				$out .= $sk->shape( [ [ $cx - 24, 214 ], [ $cx + 24, 214 ], [ $cx + 30, 240 ], [ $cx - 30, 240 ] ], $light, $dark, 'pocket', 2.0, 0.6 );
				$out .= $sk->line( [ [ $cx - 8, 182 ], [ $cx - 9, 204 ] ], 'neutral-light', 'cord-l', 2.2, 0.3 ) . $sk->line( [ [ $cx + 8, 182 ], [ $cx + 9, 206 ] ], 'neutral-light', 'cord-r', 2.2, 0.3 );
				break;
			case 'jacket':
				$out .= $sk->line( [ [ $cx, 166 ], [ $cx, $hem ] ], $dark, 'zip', 2.2, 0.3 );
				$out .= $sk->shape( [ [ $cx - 22, 160 ], [ $cx - 2, 158 ], [ $cx - 4, 182 ] ], $light, $dark, 'collar-l', 2.2, 0.5 );
				$out .= $sk->shape( [ [ $cx + 22, 160 ], [ $cx + 2, 158 ], [ $cx + 4, 182 ] ], $light, $dark, 'collar-r', 2.2, 0.5 );
				$out .= $sk->line( [ [ $cx - $w + 8, 214 ], [ $cx - 12, 218 ] ], $dark, 'pk-l', 2.0, 0.3 ) . $sk->line( [ [ $cx + $w - 8, 214 ], [ $cx + 12, 218 ] ], $dark, 'pk-r', 2.0, 0.3 );
				break;
			case 'overalls':
				$bib  = [ [ $cx - $w + 12, 196 ], [ $cx + $w - 12, 196 ], [ $cx + $w, 232 ], [ $cx, 236 ], [ $cx - $w, 232 ] ];
				$out .= $sk->shape( $bib, $bottom, self::edge( $bottom ), 'bib', 2.4, 0.8 );
				$out .= $sk->limb( [ $cx - $w + 14, 196 ], [ $cx - 15, 163 ], $bottom, self::edge( $bottom ), 6, 'strap-l' );
				$out .= $sk->limb( [ $cx + $w - 14, 196 ], [ $cx + 15, 163 ], $bottom, self::edge( $bottom ), 6, 'strap-r' );
				$out .= '<circle class="slot-' . self::shade( $bottom, 'light' ) . '" cx="' . G::n( $cx - $w + 14 ) . '" cy="198" r="3.2"/><circle class="slot-' . self::shade( $bottom, 'light' ) . '" cx="' . G::n( $cx + $w - 14 ) . '" cy="198" r="3.2"/>';
				$out .= $sk->shape( [ [ $cx - 12, 208 ], [ $cx + 12, 208 ], [ $cx + 12, 224 ], [ $cx - 12, 224 ] ], self::shade( $bottom, 'dark' ), self::shade( $bottom, 'dark' ), 'bibpocket', 1.6, 0.4 );
				break;
			case 'shirt':
				$out .= $sk->shape( [ [ $cx - 20, 160 ], [ $cx - 1, 162 ], [ $cx - 4, 180 ] ], 'background', $dark, 'collar-l', 2.0, 0.4 );
				$out .= $sk->shape( [ [ $cx + 20, 160 ], [ $cx + 1, 162 ], [ $cx + 4, 180 ] ], 'background', $dark, 'collar-r', 2.0, 0.4 );
				$out .= $sk->line( [ [ $cx, 180 ], [ $cx, $hem ] ], $dark, 'placket', 1.6, 0.3 );
				foreach ( [ 192, 208, 224 ] as $y ) {
					$out .= '<circle class="slot-' . $dark . '" cx="' . $cx . '" cy="' . $y . '" r="2.2"/>';
				}
				break;
			case 'raincoat':
				$out .= $sk->line( [ [ $cx, 168 ], [ $cx, $hem ] ], $dark, 'zip', 2.2, 0.3 );
				$out .= $sk->shape( [ [ $cx - 24, 158 ], [ $cx, 154 ], [ $cx + 24, 158 ], [ $cx + 16, 178 ], [ $cx, 182 ], [ $cx - 16, 178 ] ], $light, $dark, 'hood', 2.4, 0.6 );
				foreach ( [ 196, 218, 240 ] as $y ) {
					$out .= '<circle class="slot-' . $dark . '" cx="' . G::n( $cx + 8 ) . '" cy="' . $y . '" r="2.4"/>';
				}
				break;
		}//end switch

		if ( 'backpack' === $spec->get( 'backpack' ) ) {
			$strap = self::shade( $spec->get( 'bag_color' ), 'dark' );
			$out  .= $sk->limb( [ $cx - $w + 14, 168 ], [ $cx - $w + 10, 214 ], $strap, self::edge( $strap ), 6, 'bp-l' ) . $sk->limb( [ $cx + $w - 14, 168 ], [ $cx + $w - 10, 214 ], $strap, self::edge( $strap ), 6, 'bp-r' );
		}

		return $out;
	}

	/**
	 * A backpack, behind the body.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function backpack( Sketch $sk, FigureSpec $spec ): string {
		if ( 'backpack' !== $spec->get( 'backpack' ) ) {
			return '';
		}
		$w      = (float) ( self::BODY_W[ $spec->get( 'build' ) ] ?? 36 ) + 10;
		$colour = $spec->get( 'bag_color' );
		$cx     = (float) self::CX;

		return $sk->shape( [ [ $cx - $w, 166 ], [ $cx + $w, 166 ], [ $cx + $w + 3, 226 ], [ $cx - $w - 3, 226 ] ], $colour, self::edge( $colour ), 'pack', 2.6, 1.0 )
			. $sk->shape( [ [ $cx - $w + 6, 200 ], [ $cx + $w - 6, 200 ], [ $cx + $w - 4, 222 ], [ $cx - $w + 4, 222 ] ], self::shade( $colour, 'light' ), self::edge( $colour ), 'pack-pocket', 2.0, 0.6 );
	}

	/**
	 * What is held in front of the chest when the pose is "Holding something".
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function carried( Sketch $sk, FigureSpec $spec ): string {
		if ( 'hold' !== $spec->get( 'pose' ) ) {
			return '';
		}
		$colour = $spec->get( 'bag_color' );
		$dark   = self::edge( $colour );
		$cx     = (float) self::CX;

		switch ( $spec->get( 'carry' ) ) {
			case 'bag':
				return $sk->shape( [ [ $cx - 26, 184 ], [ $cx + 26, 184 ], [ $cx + 29, 232 ], [ $cx - 29, 232 ] ], $colour, $dark, 'bag', 2.4, 0.9 )
					. $sk->shape( [ [ $cx - 26, 184 ], [ $cx + 26, 184 ], [ $cx + 24, 194 ], [ $cx - 24, 194 ] ], self::shade( $colour, 'light' ), $dark, 'bag-fold', 2.0, 0.5 )
					. $sk->line( [ [ $cx - 16, 208 ], [ $cx + 14, 210 ] ], $dark, 'bag-l1', 1.6, 0.3 );
			case 'clipboard':
				return $sk->shape( [ [ $cx - 18, 176 ], [ $cx + 18, 176 ], [ $cx + 18, 230 ], [ $cx - 18, 230 ] ], $colour, $dark, 'board', 2.4, 0.6 )
					. $sk->shape( [ [ $cx - 13, 186 ], [ $cx + 13, 186 ], [ $cx + 13, 224 ], [ $cx - 13, 224 ] ], 'background', $dark, 'paper', 1.6, 0.4 )
					. $sk->line( [ [ $cx - 8, 196 ], [ $cx + 8, 196 ] ], $dark, 'pl1', 1.4, 0.2 ) . $sk->line( [ [ $cx - 8, 205 ], [ $cx + 8, 205 ] ], $dark, 'pl2', 1.4, 0.2 ) . $sk->line( [ [ $cx - 8, 214 ], [ $cx + 4, 214 ] ], $dark, 'pl3', 1.4, 0.2 )
					. $sk->shape( [ [ $cx - 7, 172 ], [ $cx + 7, 172 ], [ $cx + 7, 184 ], [ $cx - 7, 184 ] ], 'neutral', 'neutral-dark', 'clip', 1.6, 0.3 );
			case 'parcel':
				return $sk->shape( [ [ $cx - 28, 188 ], [ $cx + 28, 188 ], [ $cx + 28, 230 ], [ $cx - 28, 230 ] ], $colour, $dark, 'box', 2.4, 0.8 )
					. $sk->shape( [ [ $cx - 5, 188 ], [ $cx + 5, 188 ], [ $cx + 5, 230 ], [ $cx - 5, 230 ] ], self::shade( $colour, 'light' ), $dark, 'tape', 1.6, 0.3 );
		}

		return '';
	}

	/**
	 * Arms: sleeves, forearms and hands.
	 *
	 * @param Sketch                                                                                    $sk   Pen.
	 * @param FigureSpec                                                                                $spec Spec.
	 * @param array{r: array<int, array{0: float, 1: float}>, l: array<int, array{0: float, 1: float}>} $arms Joints.
	 * @return string
	 */
	private static function arms( Sketch $sk, FigureSpec $spec, array $arms ): string {
		$width  = (float) ( self::LIMB_W[ $spec->get( 'build' ) ][0] ?? 17 );
		$top    = $spec->get( 'top' );
		$colour = $spec->get( 'top_color' );
		$long   = in_array( $top, [ 'hoodie', 'jacket', 'shirt', 'raincoat' ], true );
		$out    = '';

		foreach ( [ 'l', 'r' ] as $side ) {
			[ $shoulder, $elbow, $hand ] = $arms[ $side ];
			$out                        .= $sk->limb( $elbow, $hand, $long ? $colour : 'skin', $long ? self::edge( $colour ) : 'skin-dark', $width - 1, 'fore' . $side );
			$out                        .= $sk->limb( $shoulder, $elbow, $colour, self::edge( $colour ), $width + 1, 'upper' . $side );
			$out                        .= '<circle class="slot-skin slot-stroke-skin-dark" cx="' . G::n( $hand[0] ) . '" cy="' . G::n( $hand[1] ) . '" r="8" stroke-width="2.2"/>';
		}

		return $out;
	}

	// ---------------------------------------------------------------- head.

	/**
	 * The head: ears, face, features, hair, eyewear and headwear.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function head( Sketch $sk, FigureSpec $spec ): string {
		[ $rx, $ry ] = self::head_size( $spec );
		$cx          = (float) self::CX;
		$cy          = (float) self::CY;

		$ring = [];
		for ( $i = 0; $i < 14; $i++ ) {
			$t      = $i / 14 * 2 * M_PI;
			$bottom = sin( $t ) > 0 ? 0.93 : 1.0;
			$ring[] = [ $cx + $rx * cos( $t ), $cy + $ry * sin( $t ) * $bottom ];
		}

		$out = '';
		foreach ( [ -1, 1 ] as $dir ) {
			$out .= $sk->shape( self::circle( $cx + $dir * ( $rx - 1 ), $cy + 8, 10, 8 ), 'skin', 'skin-dark', 'ear' . $dir, 2.0, 0.5 );
		}
		$out .= $sk->shape( $ring, 'skin', 'skin-dark', 'face', 2.4, 0.9 );
		$out .= self::features( $sk, $spec, $rx );
		$out .= self::hair_front( $sk, $spec );
		$out .= self::eyewear( $spec, $rx );
		$out .= self::headwear( $sk, $spec );

		return $out;
	}

	/**
	 * A circle as points.
	 *
	 * @param float $x Centre x.
	 * @param float $y Centre y.
	 * @param float $r Radius.
	 * @param int   $n Number of points.
	 * @return array<int, array{0: float, 1: float}>
	 */
	private static function circle( float $x, float $y, float $r, int $n ): array {
		$pts = [];
		for ( $i = 0; $i < $n; $i++ ) {
			$t     = $i / $n * 2 * M_PI;
			$pts[] = [ $x + $r * cos( $t ), $y + $r * sin( $t ) ];
		}

		return $pts;
	}

	/**
	 * Eyes, brows, nose, mouth and cheeks.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @param int        $rx   Head half-width.
	 * @return string
	 */
	private static function features( Sketch $sk, FigureSpec $spec, int $rx ): string {
		$cx  = (float) self::CX;
		$cy  = (float) self::CY;
		$ex  = 22.0 + ( $rx - 60 ) * 0.4;
		$ey  = $cy + 8;
		$out = '';

		if ( 'none' !== $spec->get( 'cheeks' ) ) {
			foreach ( [ -1, 1 ] as $dir ) {
				$out .= '<ellipse class="slot-skin-dark" opacity="0.5" cx="' . G::n( $cx + $dir * ( $ex + 17 ) ) . '" cy="' . G::n( $cy + 24 ) . '" rx="11" ry="7.5"/>';
			}
		}
		if ( 'freckles' === $spec->get( 'cheeks' ) ) {
			foreach ( [ -1, 1 ] as $dir ) {
				foreach ( [ [ 8, 14 ], [ 15, 17 ], [ 22, 13 ] ] as $d ) {
					$out .= '<circle class="slot-skin-dark" cx="' . G::n( $cx + $dir * ( $ex + $d[0] - 4 ) ) . '" cy="' . G::n( $cy + $d[1] + 6 ) . '" r="1.5"/>';
				}
			}
		}

		foreach ( [ -1, 1 ] as $dir ) {
			$x = $cx + $dir * $ex;
			switch ( $spec->get( 'eyes' ) ) {
				case 'happy':
					$out .= $sk->line( [ [ $x - 7, $ey + 3 ], [ $x, $ey - 5 ], [ $x + 7, $ey + 3 ] ], 'neutral-dark', 'eye' . $dir, 3.2, 0.3 );
					break;
				case 'sleepy':
					$out .= $sk->line( [ [ $x - 7, $ey ], [ $x, $ey + 3 ], [ $x + 7, $ey ] ], 'neutral-dark', 'eye' . $dir, 3.2, 0.3 );
					break;
				case 'wide':
					$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $x ) . '" cy="' . G::n( $ey ) . '" rx="5.2" ry="6.6"/><circle class="slot-background" cx="' . G::n( $x - 1.6 ) . '" cy="' . G::n( $ey - 2.4 ) . '" r="1.9"/>';
					break;
				case 'sparkle':
					$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $x ) . '" cy="' . G::n( $ey ) . '" rx="4.8" ry="6"/><circle class="slot-background" cx="' . G::n( $x - 1.6 ) . '" cy="' . G::n( $ey - 2.2 ) . '" r="1.8"/><circle class="slot-background" cx="' . G::n( $x + 1.6 ) . '" cy="' . G::n( $ey + 2.6 ) . '" r="0.9"/>';
					break;
				default:
					$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $x ) . '" cy="' . G::n( $ey ) . '" rx="3.6" ry="4.8"/>';
			}

			$by = $ey - 14;
			switch ( $spec->get( 'brows' ) ) {
				case 'soft':
					$out .= $sk->line( [ [ $x - 7, $by + 1 ], [ $x, $by - 2 ], [ $x + 7, $by + 1 ] ], 'hair-dark', 'brow' . $dir, 2.4, 0.3 );
					break;
				case 'thick':
					$out .= $sk->line( [ [ $x - 8, $by + 1 ], [ $x, $by - 2 ], [ $x + 8, $by + 2 ] ], 'hair-dark', 'brow' . $dir, 4.4, 0.3 );
					break;
				case 'raised':
					$out .= $sk->line( [ [ $x - 7, $by - 2 ], [ $x, $by - 8 ], [ $x + 7, $by - 3 ] ], 'hair-dark', 'brow' . $dir, 2.6, 0.3 );
					break;
			}
		}//end foreach

		switch ( $spec->get( 'nose' ) ) {
			case 'dot':
				$out .= '<ellipse class="slot-skin-dark" cx="' . G::n( $cx + 1 ) . '" cy="' . G::n( $cy + 20 ) . '" rx="2" ry="1.5"/>';
				break;
			case 'line':
				$out .= $sk->line( [ [ $cx + 2, $cy + 14 ], [ $cx, $cy + 21 ], [ $cx + 5, $cy + 21 ] ], 'skin-dark', 'nose', 1.8, 0.2 );
				break;
		}

		$my = $cy + 33;
		switch ( $spec->get( 'mouth' ) ) {
			case 'grin':
				$out .= $sk->shape( [ [ $cx - 11, $my - 4 ], [ $cx, $my - 2 ], [ $cx + 11, $my - 4 ], [ $cx + 8, $my + 8 ], [ $cx, $my + 11 ], [ $cx - 8, $my + 8 ] ], 'neutral-dark', 'neutral-dark', 'mouth', 1.6, 0.3 );
				$out .= '<path class="slot-background" d="M' . G::n( $cx - 9 ) . ' ' . G::n( $my - 3 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $my + 1 ) . ' ' . G::n( $cx + 9 ) . ' ' . G::n( $my - 3 ) . ' L' . G::n( $cx + 8 ) . ' ' . G::n( $my + 2 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $my + 5 ) . ' ' . G::n( $cx - 8 ) . ' ' . G::n( $my + 2 ) . ' Z"/>';
				break;
			case 'open':
				$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $cx ) . '" cy="' . G::n( $my + 3 ) . '" rx="6" ry="7.5"/><ellipse class="slot-primary" cx="' . G::n( $cx ) . '" cy="' . G::n( $my + 8 ) . '" rx="3.6" ry="2.2"/>';
				break;
			case 'flat':
				$out .= $sk->line( [ [ $cx - 7, $my + 1 ], [ $cx, $my + 2 ], [ $cx + 7, $my + 1 ] ], 'neutral-dark', 'mouth', 2.6, 0.3 );
				break;
			case 'smirk':
				$out .= $sk->line( [ [ $cx - 7, $my + 3 ], [ $cx + 2, $my + 3 ], [ $cx + 9, $my - 3 ] ], 'neutral-dark', 'mouth', 2.6, 0.3 );
				break;
			case 'cat':
				$out .= $sk->line( [ [ $cx - 10, $my - 2 ], [ $cx - 5, $my + 4 ], [ $cx, $my - 1 ], [ $cx + 5, $my + 4 ], [ $cx + 10, $my - 2 ] ], 'neutral-dark', 'mouth', 2.4, 0.3 );
				break;
			default:
				$out .= $sk->line( [ [ $cx - 9, $my - 2 ], [ $cx, $my + 5 ], [ $cx + 9, $my - 2 ] ], 'neutral-dark', 'mouth', 2.8, 0.3 );
		}

		return $out;
	}

	/**
	 * Glasses and sunglasses.
	 *
	 * @param FigureSpec $spec Spec.
	 * @param int        $rx   Head half-width.
	 * @return string
	 */
	private static function eyewear( FigureSpec $spec, int $rx ): string {
		$style = $spec->get( 'eyewear' );
		if ( 'none' === $style ) {
			return '';
		}
		$cx = (float) self::CX;
		$ey = self::CY + 8.0;
		$ex = 22.0 + ( $rx - 60 ) * 0.4;
		$l  = G::n( $cx - $ex );
		$r  = G::n( $cx + $ex );
		$y  = G::n( $ey );

		if ( 'sunglasses' === $style ) {
			return '<rect class="slot-neutral-dark" x="' . G::n( $cx - $ex - 14 ) . '" y="' . G::n( $ey - 9 ) . '" width="28" height="19" rx="7"/><rect class="slot-neutral-dark" x="' . G::n( $cx + $ex - 14 ) . '" y="' . G::n( $ey - 9 ) . '" width="28" height="19" rx="7"/>'
				. '<path class="slot-stroke-neutral-dark" d="M' . G::n( $cx - $ex + 14 ) . ' ' . G::n( $ey - 3 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $ey - 8 ) . ' ' . G::n( $cx + $ex - 14 ) . ' ' . G::n( $ey - 3 ) . '" fill="none" stroke-width="3"/>'
				. '<path class="slot-neutral-light" opacity="0.5" d="M' . G::n( $cx - $ex - 8 ) . ' ' . G::n( $ey - 4 ) . ' l7 -2 l-2 6 z"/>';
		}

		$frame = ' fill="none" stroke-width="3" stroke-linejoin="round"';
		$lens  = 'round' === $style
			? '<circle class="slot-stroke-neutral-dark" cx="' . $l . '" cy="' . $y . '" r="14"' . $frame . '/><circle class="slot-stroke-neutral-dark" cx="' . $r . '" cy="' . $y . '" r="14"' . $frame . '/>'
			: '<rect class="slot-stroke-neutral-dark" x="' . G::n( $cx - $ex - 14 ) . '" y="' . G::n( $ey - 11 ) . '" width="28" height="22" rx="5"' . $frame . '/><rect class="slot-stroke-neutral-dark" x="' . G::n( $cx + $ex - 14 ) . '" y="' . G::n( $ey - 11 ) . '" width="28" height="22" rx="5"' . $frame . '/>';

		return $lens . '<path class="slot-stroke-neutral-dark" d="M' . G::n( $cx - $ex + 14 ) . ' ' . G::n( $ey - 2 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $ey - 7 ) . ' ' . G::n( $cx + $ex - 14 ) . ' ' . G::n( $ey - 2 ) . '" fill="none" stroke-width="3"/>';
	}

	// ---------------------------------------------------------------- hair and headwear.

	/**
	 * Hair behind the head.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function hair_back( Sketch $sk, FigureSpec $spec ): string {
		[ $rx, $ry ] = self::head_size( $spec );
		$cx          = (float) self::CX;
		$cy          = (float) self::CY;
		$one         = static fn( array $p, string $key ): string => $sk->shape( $p, 'hair', 'hair-dark', $key, 2.4, 1.0 );

		return match ( $spec->get( 'hair' ) ) {
			'bob'      => $one( [ [ $cx - $rx - 6, $cy - 10 ], [ $cx - $rx - 12, $cy + 30 ], [ $cx - $rx - 2, $cy + 58 ], [ $cx + $rx + 2, $cy + 58 ], [ $cx + $rx + 12, $cy + 30 ], [ $cx + $rx + 6, $cy - 10 ], [ $cx + $rx * 0.7, $cy - $ry - 4 ], [ $cx, $cy - $ry - 10 ], [ $cx - $rx * 0.7, $cy - $ry - 4 ] ], 'hb' ),
			'long'     => $one( [ [ $cx - $rx - 6, $cy - 10 ], [ $cx - $rx - 14, $cy + 40 ], [ $cx - $rx - 8, $cy + 86 ], [ $cx + $rx + 8, $cy + 86 ], [ $cx + $rx + 14, $cy + 40 ], [ $cx + $rx + 6, $cy - 10 ], [ $cx + $rx * 0.7, $cy - $ry - 4 ], [ $cx, $cy - $ry - 10 ], [ $cx - $rx * 0.7, $cy - $ry - 4 ] ], 'hb' ),
			'ponytail' => $one( [ [ $cx + $rx - 6, $cy - 24 ], [ $cx + $rx + 28, $cy - 12 ], [ $cx + $rx + 36, $cy + 30 ], [ $cx + $rx + 22, $cy + 62 ], [ $cx + $rx + 12, $cy + 30 ], [ $cx + $rx - 2, $cy + 4 ] ], 'tail' )
				. '<ellipse class="slot-accent" cx="' . G::n( $cx + $rx + 2 ) . '" cy="' . G::n( $cy - 12 ) . '" rx="6" ry="9"/>',
			'bun'      => $one( self::circle( $cx, $cy - $ry - 14, 21, 12 ), 'bun' ),
			'curly'    => $one( self::bumps( $cx, $cy + 6, $rx + 10, $ry + 8, 9, 7 ), 'hb' ),
			default    => '',
		};
	}

	/**
	 * A ring of rounded bumps (curly hair).
	 *
	 * @param float $cx    Centre x.
	 * @param float $cy    Centre y.
	 * @param float $rx    Radius x.
	 * @param float $ry    Radius y.
	 * @param int   $count Bumps.
	 * @param float $depth Push out.
	 * @return array<int, array{0: float, 1: float}>
	 */
	private static function bumps( float $cx, float $cy, float $rx, float $ry, int $count, float $depth ): array {
		$pts = [];
		for ( $i = 0; $i < $count * 2; $i++ ) {
			$a     = M_PI + $i / ( $count * 2 - 1 ) * M_PI;
			$k     = 0 === $i % 2 ? 1.0 : 0.88;
			$pts[] = [ $cx + cos( $a ) * ( $rx + $depth * 0.4 ) * $k, $cy + sin( $a ) * ( $ry + $depth * 0.4 ) * $k ];
		}
		$pts[] = [ $cx + $rx * 0.9, $cy + 28 ];
		$pts[] = [ $cx - $rx * 0.9, $cy + 28 ];

		return $pts;
	}

	/**
	 * Hair over the head: the fringe and top.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function hair_front( Sketch $sk, FigureSpec $spec ): string {
		$style = $spec->get( 'hair' );
		if ( 'bald' === $style ) {
			return '';
		}
		[ $rx, $ry ] = self::head_size( $spec );
		$cx          = (float) self::CX;
		$cy          = (float) self::CY;

		$inner = [ [ $cx + $rx * 0.8, $cy - 12 ], [ $cx + $rx * 0.42, $cy - 28 ], [ $cx + 8, $cy - 30 ], [ $cx - $rx * 0.1, $cy - 18 ], [ $cx - $rx * 0.5, $cy - 30 ], [ $cx - $rx * 0.8, $cy - 12 ] ];
		$outer = [ [ $cx - $rx - 2, $cy + 8 ], [ $cx - $rx - 3, $cy - 24 ], [ $cx - $rx * 0.62, $cy - $ry - 4 ], [ $cx, $cy - $ry - 9 ], [ $cx + $rx * 0.62, $cy - $ry - 4 ], [ $cx + $rx + 3, $cy - 24 ], [ $cx + $rx + 2, $cy + 8 ] ];

		if ( 'messy' === $style ) {
			$outer = [ [ $cx - $rx - 4, $cy + 10 ], [ $cx - $rx - 8, $cy - 20 ], [ $cx - $rx * 0.8, $cy - $ry + 2 ], [ $cx - $rx * 0.5, $cy - $ry - 12 ], [ $cx - 16, $cy - $ry - 2 ], [ $cx, $cy - $ry - 16 ], [ $cx + 18, $cy - $ry - 2 ], [ $cx + $rx * 0.5, $cy - $ry - 14 ], [ $cx + $rx * 0.8, $cy - $ry + 2 ], [ $cx + $rx + 8, $cy - 18 ], [ $cx + $rx + 3, $cy + 10 ] ];
		} elseif ( 'curly' === $style ) {
			$outer = [ [ $cx - $rx - 6, $cy + 8 ], [ $cx - $rx - 8, $cy - 18 ], [ $cx - $rx * 0.7, $cy - $ry + 2 ], [ $cx - $rx * 0.35, $cy - $ry - 10 ], [ $cx, $cy - $ry - 4 ], [ $cx + $rx * 0.35, $cy - $ry - 10 ], [ $cx + $rx * 0.7, $cy - $ry + 2 ], [ $cx + $rx + 8, $cy - 18 ], [ $cx + $rx + 6, $cy + 8 ] ];
		}

		$out  = $sk->shape( array_merge( $outer, $inner ), 'hair', 'hair-dark', 'hf', 2.4, 0.9 );
		$out .= $sk->line( [ [ $cx + 10, $cy - $ry - 4 ], [ $cx + 4, $cy - 40 ], [ $cx - 4, $cy - 30 ] ], 'hair-dark', 'strand-a', 1.6, 0.4 );
		$out .= $sk->line( [ [ $cx - $rx * 0.5, $cy - $ry + 6 ], [ $cx - $rx * 0.55, $cy - 32 ] ], 'hair-dark', 'strand-b', 1.4, 0.4 );

		return $out;
	}

	/**
	 * Hats.
	 *
	 * @param Sketch     $sk   Pen.
	 * @param FigureSpec $spec Spec.
	 * @return string
	 */
	private static function headwear( Sketch $sk, FigureSpec $spec ): string {
		$style = $spec->get( 'headwear' );
		if ( 'none' === $style ) {
			return '';
		}
		[ $rx, $ry ] = self::head_size( $spec );
		$cx          = (float) self::CX;
		$cy          = (float) self::CY;
		$colour      = $spec->get( 'headwear_color' );
		$dark        = self::edge( $colour );
		$light       = self::shade( $colour, 'light' );

		$dome = [ [ $cx - $rx - 2, $cy - 6 ], [ $cx - $rx + 2, $cy - 34 ], [ $cx - $rx * 0.5, $cy - $ry - 8 ], [ $cx, $cy - $ry - 14 ], [ $cx + $rx * 0.5, $cy - $ry - 8 ], [ $cx + $rx - 2, $cy - 34 ], [ $cx + $rx + 2, $cy - 6 ], [ $cx, $cy - 14 ] ];

		switch ( $style ) {
			case 'cap':
				return $sk->shape( $dome, $colour, $dark, 'dome', 2.6, 0.9 )
					. $sk->shape( [ [ $cx - $rx * 0.2, $cy - 14 ], [ $cx + $rx * 0.7, $cy - 20 ], [ $cx + $rx + 22, $cy - 8 ], [ $cx + $rx + 12, $cy - 1 ], [ $cx + $rx * 0.3, $cy - 8 ] ], $light, $dark, 'brim', 2.4, 0.7 )
					. '<circle class="slot-' . $dark . '" cx="' . G::n( $cx ) . '" cy="' . G::n( $cy - $ry - 13 ) . '" r="4.4"/>';
			case 'cap_back':
				return $sk->shape( $dome, $colour, $dark, 'dome', 2.6, 0.9 )
					. $sk->shape( [ [ $cx - $rx - 2, $cy - 20 ], [ $cx - $rx - 20, $cy - 14 ], [ $cx - $rx - 12, $cy - 6 ], [ $cx - $rx + 4, $cy - 10 ] ], $light, $dark, 'brim', 2.4, 0.7 )
					. '<circle class="slot-' . $dark . '" cx="' . G::n( $cx ) . '" cy="' . G::n( $cy - $ry - 13 ) . '" r="4.4"/>';
			case 'hardhat':
				$hat = [ [ $cx - $rx - 4, $cy - 4 ], [ $cx - $rx, $cy - 38 ], [ $cx - $rx * 0.5, $cy - $ry - 18 ], [ $cx, $cy - $ry - 24 ], [ $cx + $rx * 0.5, $cy - $ry - 18 ], [ $cx + $rx, $cy - 38 ], [ $cx + $rx + 4, $cy - 4 ], [ $cx, $cy - 12 ] ];

				return $sk->shape( $hat, $colour, $dark, 'dome', 2.6, 0.9 )
					. $sk->shape( [ [ $cx - $rx - 16, $cy - 6 ], [ $cx, $cy - 17 ], [ $cx + $rx + 16, $cy - 6 ], [ $cx + $rx + 10, $cy + 2 ], [ $cx, $cy - 6 ], [ $cx - $rx - 10, $cy + 2 ] ], $colour, $dark, 'brim', 2.6, 0.8 )
					. $sk->line( [ [ $cx - 12, $cy - $ry - 20 ], [ $cx - 8, $cy - 24 ], [ $cx - 8, $cy - 12 ] ], $dark, 'ridge-l', 2.0, 0.3 )
					. $sk->line( [ [ $cx + 12, $cy - $ry - 20 ], [ $cx + 8, $cy - 24 ], [ $cx + 8, $cy - 12 ] ], $dark, 'ridge-r', 2.0, 0.3 );
			case 'beanie':
				return $sk->shape( $dome, $colour, $dark, 'dome', 2.6, 0.9 )
					. $sk->shape( [ [ $cx - $rx - 3, $cy - 18 ], [ $cx, $cy - 25 ], [ $cx + $rx + 3, $cy - 18 ], [ $cx + $rx + 3, $cy - 4 ], [ $cx, $cy - 10 ], [ $cx - $rx - 3, $cy - 4 ] ], $light, $dark, 'cuff', 2.4, 0.7 )
					. '<circle class="slot-' . $light . ' slot-stroke-' . $dark . '" cx="' . G::n( $cx ) . '" cy="' . G::n( $cy - $ry - 16 ) . '" r="8" stroke-width="2.2"/>';
			case 'bucket':
				return $sk->shape( [ [ $cx - $rx + 4, $cy - 12 ], [ $cx - $rx + 8, $cy - 34 ], [ $cx, $cy - $ry - 8 ], [ $cx + $rx - 8, $cy - 34 ], [ $cx + $rx - 4, $cy - 12 ], [ $cx, $cy - 8 ] ], $colour, $dark, 'dome', 2.6, 0.9 )
					. $sk->shape( [ [ $cx - $rx - 18, $cy - 8 ], [ $cx, $cy - 20 ], [ $cx + $rx + 18, $cy - 8 ], [ $cx + $rx + 8, $cy + 2 ], [ $cx, $cy - 6 ], [ $cx - $rx - 8, $cy + 2 ] ], $light, $dark, 'brim', 2.6, 0.8 );
		}//end switch

		return '';
	}
}
