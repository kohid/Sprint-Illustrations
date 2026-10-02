<?php
/**
 * Draws a full-body cartoon figure with a big head, in a soft flat-colour illustration style.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Figure;

use SprintIllustrations\Character\Geometry as G;
use SprintIllustrations\Portrait\Sketch;

/**
 * Pure: a FigureSpec in, a library-ready SVG out (viewBox 240 × 350, colour only from slot classes,
 * `anchor-ground` between the feet, `anchor-hold` at the hand). Proportions: a big round head (centre
 * 120,96), shoulders at y 158, hips at 222, feet on 336. The look is flat colour with a thin, soft,
 * translucent edge, one darker patch of cel shading per garment and a glossy highlight on the hair.
 */
final class FigureBuilder {

	public const WIDTH  = 240;
	public const HEIGHT = 350;

	private const CX     = 120;
	private const CY     = 96;
	private const GROUND = 336;

	/** Half-width of the torso by build. */
	private const BODY_W = [
		'slim'    => 34,
		'regular' => 39,
		'round'   => 46,
	];

	/** Limb thickness by build: [arm, leg]. */
	private const LIMB_W = [
		'slim'    => [ 17, 21 ],
		'regular' => [ 20, 24 ],
		'round'   => [ 23, 27 ],
	];

	/** Head half-width and half-height by face. */
	private const HEAD = [
		'round' => [ 64, 58 ],
		'oval'  => [ 58, 62 ],
		'wide'  => [ 70, 54 ],
	];

	/**
	 * Arm angles per pose and side, in degrees from straight down, positive swinging outward and up:
	 * [upper arm, forearm].
	 */
	private const ARMS = [
		'relaxed'  => [
			'r' => [ 12, 4 ],
			'l' => [ 12, 4 ],
		],
		'hips'     => [
			'r' => [ 38, -62 ],
			'l' => [ 38, -62 ],
		],
		'hold'     => [
			'r' => [ 22, -104 ],
			'l' => [ 22, -104 ],
		],
		'wave'     => [
			'r' => [ 128, 168 ],
			'l' => [ 12, 4 ],
		],
		'pockets'  => [
			'r' => [ 8, -36 ],
			'l' => [ 8, -36 ],
		],
		'cheer'    => [
			'r' => [ 138, 170 ],
			'l' => [ 138, 170 ],
		],
		'thinking' => [
			'r' => [ 32, -124 ],
			'l' => [ 10, 4 ],
		],
		'walk'     => [
			'r' => [ 28, 14 ],
			'l' => [ -22, -8 ],
		],
		'point'    => [
			'r' => [ 82, 96 ],
			'l' => [ 12, 4 ],
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
		$rotate = abs( $tilt ) > 0.0 ? ' transform="rotate(' . G::n( $tilt ) . ' ' . self::CX . ' 150)"' : '';

		$layers = [
			self::backpack( $sk, $spec ),
			'<g' . $rotate . '>' . self::hair_back( $sk, $spec ) . '</g>',
			self::legs( $sk, $spec ),
			self::torso( $sk, $spec ),
			self::carried( $sk, $spec ),
			'<g' . $rotate . '>' . self::head( $sk, $spec ) . '</g>',
			self::arms( $sk, $spec, $arms ),
		];

		$flip = 'yes' === $spec->get( 'flip' );
		$body = self::scene( $sk, $spec ) . ( $flip ? '<g transform="translate(' . self::WIDTH . ' 0) scale(-1 1)">' : '<g>' ) . implode( '', array_filter( $layers ) ) . '</g>';

		$hold = 'hold' === $spec->get( 'pose' ) ? [ (float) self::CX, 190.0 ] : $arms['r'][2];
		if ( $flip ) {
			$hold[0] = self::WIDTH - $hold[0];
		}

		$attributes = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" data-si-label="%3$s" data-si-tags="%4$s" data-si-accepts="hold:handheld"%5$s',
			self::WIDTH,
			self::HEIGHT,
			htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			htmlspecialchars( implode( ',', $tags ), ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			'' !== $person ? ' data-si-person="' . htmlspecialchars( $person, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"' : ''
		);

		return '<svg ' . $attributes . ">\n\t" . $body
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
	 * The edge token for a fill.
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
	 * Shoulder, elbow and hand for each arm.
	 *
	 * @param FigureSpec $spec Spec.
	 * @return array{r: array<int, array{0: float, 1: float}>, l: array<int, array{0: float, 1: float}>}
	 */
	private static function arm_points( FigureSpec $spec ): array {
		$w    = self::BODY_W[ $spec->get( 'build' ) ] ?? 39;
		$pose = self::ARMS[ $spec->get( 'pose' ) ] ?? self::ARMS['relaxed'];
		$out  = [];
		foreach ( [
			'r' => 1,
			'l' => -1,
		] as $side => $dir ) {
			$shoulder     = [ (float) ( self::CX + $dir * ( $w - 5 ) ), 168.0 ];
			$a1           = deg2rad( (float) $pose[ $side ][0] );
			$a2           = deg2rad( (float) $pose[ $side ][1] );
			$elbow        = [ $shoulder[0] + $dir * 25 * sin( $a1 ), $shoulder[1] + 25 * cos( $a1 ) ];
			$hand         = [ $elbow[0] + $dir * 23 * sin( $a2 ), $elbow[1] + 23 * cos( $a2 ) ];
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
		$ground = '<ellipse class="slot-' . $dark . '" opacity="0.32" cx="120" cy="' . ( self::GROUND - 1 ) . '" rx="80" ry="11"/>';

		if ( 'splash' !== $spec->get( 'scene' ) ) {
			return $ground;
		}

		$out  = '<path class="slot-' . $colour . '" opacity="0.26" d="' . G::closed( $sk->wobble( [ [ 28, 84 ], [ 64, 26 ], [ 146, 20 ], [ 206, 64 ], [ 214, 160 ], [ 200, 256 ], [ 164, 312 ], [ 80, 316 ], [ 34, 256 ], [ 18, 170 ] ], 9, 'splash-a' ) ) . '"/>';
		$out .= '<path class="slot-' . $colour . '" opacity="0.5" d="' . G::closed( $sk->wobble( [ [ 150, 36 ], [ 204, 52 ], [ 220, 112 ], [ 178, 104 ] ], 6, 'splash-b' ) ) . '"/>';
		$out .= $ground;
		foreach ( [ [ 22, 40, 2.4 ], [ 44, 20, 1.6 ], [ 198, 24, 2.2 ], [ 226, 90, 1.8 ], [ 14, 150, 2 ], [ 228, 214, 2.4 ], [ 30, 310, 1.8 ], [ 206, 326, 1.6 ], [ 90, 12, 1.4 ] ] as $dot ) {
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
		$width  = (float) ( self::LIMB_W[ $spec->get( 'build' ) ][1] ?? 21 );
		$bottom = $spec->get( 'bottom' );
		$colour = $spec->get( 'bottom_color' );
		$walk   = 'walk' === $spec->get( 'pose' );
		$out    = '';

		foreach ( [
			-1 => 'l',
			1  => 'r',
		] as $dir => $side ) {
			$swing = $walk ? ( 'r' === $side ? 1 : -1 ) * 15.0 : $dir * 4.0;
			$hip   = [ self::CX + $dir * 15, 222.0 ];
			$ankle = [ $hip[0] + 98 * sin( deg2rad( $swing ) ), $hip[1] + 98 * cos( deg2rad( $swing ) ) ];

			if ( 'trousers' === $bottom ) {
				$out .= $sk->limb( $hip, $ankle, $colour, self::edge( $colour ), $width, 'leg' . $side );
				$out .= $sk->patch( self::circle( $hip[0] + $dir * 3 + ( $ankle[0] - $hip[0] ) * 0.5, $hip[1] + 48, 8, 8 ), self::shade( $colour, 'dark' ), 0.28, 'fold' . $side );
			} elseif ( 'shorts' === $bottom ) {
				$mid  = [ $hip[0] + 36 * sin( deg2rad( $swing ) ), $hip[1] + 36 * cos( deg2rad( $swing ) ) ];
				$out .= $sk->limb( $hip, $ankle, 'skin', 'skin-dark', $width - 5, 'leg' . $side );
				$out .= $sk->limb( $hip, $mid, $colour, self::edge( $colour ), $width + 3, 'short' . $side );
			} else {
				$out .= $sk->limb( $hip, $ankle, 'skin', 'skin-dark', $width - 6, 'leg' . $side );
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
			'boots' => [ $at( -11, -16 ), $at( 9, -16 ), $at( 11, -2 ), $at( 19, 6 ), $at( 20, 15 ), $at( -12, 16 ) ],
			'flats' => [ $at( -10, -2 ), $at( 7, -4 ), $at( 17, 6 ), $at( 17, 14 ), $at( -11, 15 ) ],
			default => [ $at( -11, -5 ), $at( 7, -7 ), $at( 19, 5 ), $at( 20, 14 ), $at( 9, 18 ), $at( -12, 16 ) ],
		};

		$out = $sk->soft( $pts, $colour, self::edge( $colour ), 'shoe' . $side, 1.2, 0.5 );
		if ( 'flats' !== $spec->get( 'shoes' ) ) {
			$out .= $sk->line( [ $at( -11, 15 ), $at( 8, 16.5 ), $at( 19, 14 ) ], 'neutral-light', 'sole' . $side, 3.2, 0.3 );
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
		$w      = (float) ( self::BODY_W[ $spec->get( 'build' ) ] ?? 39 );
		$top    = $spec->get( 'top' );
		$colour = $spec->get( 'top_color' );
		$dark   = self::edge( $colour );
		$light  = self::shade( $colour, 'light' );
		$bottom = $spec->get( 'bottom_color' );
		$hem    = [
			'tee'      => 234.0,
			'hoodie'   => 242.0,
			'jacket'   => 244.0,
			'overalls' => 234.0,
			'shirt'    => 238.0,
			'raincoat' => 268.0,
		][ $top ] ?? 228.0;
		$flare  = 'raincoat' === $top ? 8.0 : 3.0;
		$cx     = (float) self::CX;

		$out  = $sk->soft( [ [ $cx - 11, 140 ], [ $cx + 11, 140 ], [ $cx + 12, 162 ], [ $cx - 12, 162 ] ], 'skin', 'skin-dark', 'neck', 1.0, 0.4 );
		$out .= $sk->patch( [ [ $cx - 11, 144 ], [ $cx + 11, 144 ], [ $cx + 12, 156 ], [ $cx, 160 ], [ $cx - 12, 156 ] ], 'skin-dark', 0.3, 'neck-shadow' );
		$body = [ [ $cx - $w + 12, 156 ], [ $cx, 152 ], [ $cx + $w - 12, 156 ], [ $cx + $w, 174 ], [ $cx + $w + $flare, $hem - 8 ], [ $cx + $w - 4, $hem ], [ $cx, $hem + 3 ], [ $cx - $w + 4, $hem ], [ $cx - $w - $flare, $hem - 8 ], [ $cx - $w, 174 ] ];
		$out .= $sk->soft( $body, $colour, $dark, 'torso', 1.4, 0.8 );
		$out .= $sk->patch( [ [ $cx + $w * 0.3, 154 ], [ $cx + $w - 10, 157 ], [ $cx + $w, 174 ], [ $cx + $w + $flare, $hem - 8 ], [ $cx + $w - 4, $hem ], [ $cx + $w * 0.25, $hem + 2 ] ], self::shade( $colour, 'dark' ), 0.28, 'torso-shade' );

		if ( 'skirt' === $spec->get( 'bottom' ) ) {
			$out .= $sk->soft( [ [ $cx - $w + 2, 224 ], [ $cx + $w - 2, 224 ], [ $cx + $w + 14, 276 ], [ $cx, 282 ], [ $cx - $w - 14, 276 ] ], $bottom, self::edge( $bottom ), 'skirt', 1.4, 0.8 );
			$out .= $sk->patch( [ [ $cx + 8, 226 ], [ $cx + $w - 2, 224 ], [ $cx + $w + 14, 276 ], [ $cx + 10, 281 ] ], self::shade( $bottom, 'dark' ), 0.28, 'skirt-shade' );
		}

		switch ( $top ) {
			case 'tee':
				$out .= $sk->line( [ [ $cx - 15, 156 ], [ $cx, 168 ], [ $cx + 15, 156 ] ], $dark, 'neckline', 2.2, 0.3 );
				break;
			case 'hoodie':
				$out .= $sk->soft( [ [ $cx - 28, 154 ], [ $cx, 148 ], [ $cx + 28, 154 ], [ $cx + 21, 172 ], [ $cx, 178 ], [ $cx - 21, 172 ] ], $light, $dark, 'hood', 1.4, 0.5 );
				$out .= $sk->soft( [ [ $cx - 26, 204 ], [ $cx + 26, 204 ], [ $cx + 32, 230 ], [ $cx - 32, 230 ] ], $light, $dark, 'pocket', 1.3, 0.5 );
				$out .= $sk->line( [ [ $cx - 8, 176 ], [ $cx - 9, 198 ] ], 'neutral-light', 'cord-l', 2.0, 0.3 ) . $sk->line( [ [ $cx + 8, 176 ], [ $cx + 9, 198 ] ], 'neutral-light', 'cord-r', 2.0, 0.3 );
				break;
			case 'jacket':
				$out .= $sk->line( [ [ $cx, 160 ], [ $cx, $hem ] ], $dark, 'zip', 1.8, 0.3 );
				$out .= $sk->soft( [ [ $cx - 24, 152 ], [ $cx - 2, 150 ], [ $cx - 4, 176 ] ], $light, $dark, 'collar-l', 1.3, 0.4 );
				$out .= $sk->soft( [ [ $cx + 24, 152 ], [ $cx + 2, 150 ], [ $cx + 4, 176 ] ], $light, $dark, 'collar-r', 1.3, 0.4 );
				$out .= $sk->line( [ [ $cx - $w + 8, 206 ], [ $cx - 13, 210 ] ], $dark, 'pk-l', 1.6, 0.3 ) . $sk->line( [ [ $cx + $w - 8, 206 ], [ $cx + 13, 210 ] ], $dark, 'pk-r', 1.6, 0.3 );
				break;
			case 'overalls':
				$bib  = [ [ $cx - $w + 12, 190 ], [ $cx + $w - 12, 190 ], [ $cx + $w, 234 ], [ $cx, 238 ], [ $cx - $w, 234 ] ];
				$out .= $sk->soft( $bib, $bottom, self::edge( $bottom ), 'bib', 1.4, 0.7 );
				$out .= $sk->patch( [ [ $cx + 6, 192 ], [ $cx + $w - 12, 190 ], [ $cx + $w, 234 ], [ $cx + 6, 237 ] ], self::shade( $bottom, 'dark' ), 0.28, 'bib-shade' );
				$out .= $sk->limb( [ $cx - $w + 14, 188 ], [ $cx - 15, 157 ], $bottom, self::edge( $bottom ), 7, 'strap-l' );
				$out .= $sk->limb( [ $cx + $w - 14, 188 ], [ $cx + 15, 157 ], $bottom, self::edge( $bottom ), 7, 'strap-r' );
				$out .= '<circle class="slot-' . self::shade( $bottom, 'light' ) . '" cx="' . G::n( $cx - $w + 14 ) . '" cy="190" r="3.2"/><circle class="slot-' . self::shade( $bottom, 'light' ) . '" cx="' . G::n( $cx + $w - 14 ) . '" cy="190" r="3.2"/>';
				$out .= $sk->soft( [ [ $cx - 13, 204 ], [ $cx + 13, 204 ], [ $cx + 13, 222 ], [ $cx - 13, 222 ] ], self::shade( $bottom, 'dark' ), self::shade( $bottom, 'dark' ), 'bibpocket', 1.0, 0.3 );
				break;
			case 'shirt':
				$out .= $sk->soft( [ [ $cx - 21, 154 ], [ $cx - 1, 156 ], [ $cx - 4, 174 ] ], 'background', $dark, 'collar-l', 1.2, 0.3 );
				$out .= $sk->soft( [ [ $cx + 21, 154 ], [ $cx + 1, 156 ], [ $cx + 4, 174 ] ], 'background', $dark, 'collar-r', 1.2, 0.3 );
				$out .= $sk->line( [ [ $cx, 174 ], [ $cx, $hem ] ], $dark, 'placket', 1.4, 0.3 );
				foreach ( [ 186, 200, 214 ] as $y ) {
					$out .= '<circle class="slot-' . $dark . '" cx="' . $cx . '" cy="' . $y . '" r="2.1"/>';
				}
				break;
			case 'raincoat':
				$out .= $sk->line( [ [ $cx, 162 ], [ $cx, $hem ] ], $dark, 'zip', 1.8, 0.3 );
				$out .= $sk->soft( [ [ $cx - 25, 152 ], [ $cx, 148 ], [ $cx + 25, 152 ], [ $cx + 17, 172 ], [ $cx, 176 ], [ $cx - 17, 172 ] ], $light, $dark, 'hood', 1.4, 0.5 );
				foreach ( [ 190, 210, 232 ] as $y ) {
					$out .= '<circle class="slot-' . $dark . '" cx="' . G::n( $cx + 8 ) . '" cy="' . $y . '" r="2.4"/>';
				}
				break;
		}//end switch

		if ( 'backpack' === $spec->get( 'backpack' ) ) {
			$strap = self::shade( $spec->get( 'bag_color' ), 'dark' );
			$out  .= $sk->limb( [ $cx - $w + 14, 160 ], [ $cx - $w + 10, 206 ], $strap, self::edge( $strap ), 6, 'bp-l' ) . $sk->limb( [ $cx + $w - 14, 160 ], [ $cx + $w - 10, 206 ], $strap, self::edge( $strap ), 6, 'bp-r' );
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
		$w      = (float) ( self::BODY_W[ $spec->get( 'build' ) ] ?? 39 ) + 11;
		$colour = $spec->get( 'bag_color' );
		$cx     = (float) self::CX;

		return $sk->soft( [ [ $cx - $w, 158 ], [ $cx + $w, 158 ], [ $cx + $w + 3, 220 ], [ $cx - $w - 3, 220 ] ], $colour, self::edge( $colour ), 'pack', 1.5, 1.0 )
			. $sk->patch( [ [ $cx + 6, 160 ], [ $cx + $w, 158 ], [ $cx + $w + 3, 220 ], [ $cx + 6, 220 ] ], self::shade( $colour, 'dark' ), 0.28, 'pack-shade' )
			. $sk->soft( [ [ $cx - $w + 6, 192 ], [ $cx + $w - 6, 192 ], [ $cx + $w - 4, 216 ], [ $cx - $w + 4, 216 ] ], self::shade( $colour, 'light' ), self::edge( $colour ), 'pack-pocket', 1.3, 0.6 );
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
				return $sk->soft( [ [ $cx - 27, 176 ], [ $cx + 27, 176 ], [ $cx + 30, 224 ], [ $cx - 30, 224 ] ], $colour, $dark, 'bag', 1.4, 0.9 )
					. $sk->patch( [ [ $cx + 6, 178 ], [ $cx + 27, 176 ], [ $cx + 30, 224 ], [ $cx + 8, 224 ] ], self::shade( $colour, 'dark' ), 0.28, 'bag-shade' )
					. $sk->soft( [ [ $cx - 27, 176 ], [ $cx + 27, 176 ], [ $cx + 25, 187 ], [ $cx - 25, 187 ] ], self::shade( $colour, 'light' ), $dark, 'bag-fold', 1.2, 0.5 );
			case 'clipboard':
				return $sk->soft( [ [ $cx - 19, 168 ], [ $cx + 19, 168 ], [ $cx + 19, 222 ], [ $cx - 19, 222 ] ], $colour, $dark, 'board', 1.4, 0.6 )
					. $sk->soft( [ [ $cx - 14, 178 ], [ $cx + 14, 178 ], [ $cx + 14, 216 ], [ $cx - 14, 216 ] ], 'background', $dark, 'paper', 1.0, 0.4 )
					. $sk->line( [ [ $cx - 9, 188 ], [ $cx + 9, 188 ] ], $dark, 'pl1', 1.3, 0.2 ) . $sk->line( [ [ $cx - 9, 197 ], [ $cx + 9, 197 ] ], $dark, 'pl2', 1.3, 0.2 ) . $sk->line( [ [ $cx - 9, 206 ], [ $cx + 4, 206 ] ], $dark, 'pl3', 1.3, 0.2 )
					. $sk->soft( [ [ $cx - 8, 164 ], [ $cx + 8, 164 ], [ $cx + 8, 176 ], [ $cx - 8, 176 ] ], 'neutral', 'neutral-dark', 'clip', 1.2, 0.3 );
			case 'parcel':
				return $sk->soft( [ [ $cx - 29, 180 ], [ $cx + 29, 180 ], [ $cx + 29, 222 ], [ $cx - 29, 222 ] ], $colour, $dark, 'box', 1.4, 0.8 )
					. $sk->patch( [ [ $cx + 8, 181 ], [ $cx + 29, 180 ], [ $cx + 29, 222 ], [ $cx + 8, 222 ] ], self::shade( $colour, 'dark' ), 0.28, 'box-shade' )
					. $sk->soft( [ [ $cx - 5, 180 ], [ $cx + 5, 180 ], [ $cx + 5, 222 ], [ $cx - 5, 222 ] ], self::shade( $colour, 'light' ), $dark, 'tape', 1.0, 0.3 );
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
		$width  = (float) ( self::LIMB_W[ $spec->get( 'build' ) ][0] ?? 20 );
		$top    = $spec->get( 'top' );
		$colour = $spec->get( 'top_color' );
		$long   = in_array( $top, [ 'hoodie', 'jacket', 'shirt', 'raincoat' ], true );
		$out    = '';

		foreach ( [ 'l', 'r' ] as $side ) {
			[ $shoulder, $elbow, $hand ] = $arms[ $side ];
			$out                        .= $sk->limb( $elbow, $hand, $long ? $colour : 'skin', $long ? self::edge( $colour ) : 'skin-dark', $width - 2, 'fore' . $side );
			$out                        .= $sk->limb( $shoulder, $elbow, $colour, self::edge( $colour ), $width + 1, 'upper' . $side );
			$out                        .= '<circle class="slot-skin slot-stroke-skin-dark" stroke-opacity="0.45" cx="' . G::n( $hand[0] ) . '" cy="' . G::n( $hand[1] ) . '" r="8.6" stroke-width="1.2"/>';
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
			$bottom = sin( $t ) > 0 ? 0.94 : 1.0;
			$ring[] = [ $cx + $rx * cos( $t ), $cy + $ry * sin( $t ) * $bottom ];
		}

		$out = '';
		foreach ( [ -1, 1 ] as $dir ) {
			$out .= $sk->soft( self::circle( $cx + $dir * ( $rx - 1 ), $cy + 8, 10, 8 ), 'skin', 'skin-dark', 'ear' . $dir, 1.0, 0.4 );
		}
		$out .= $sk->soft( $ring, 'skin', 'skin-dark', 'face', 1.2, 0.8 );
		$out .= self::features( $sk, $spec, $rx );
		$out .= self::hair_front( $sk, $spec );
		$out .= self::eyewear( $spec, $rx );
		$out .= self::headwear( $sk, $spec );

		return $out;
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
		$ex  = 25.0 + ( $rx - 64 ) * 0.4;
		$ey  = $cy + 10;
		$out = '';

		if ( 'none' !== $spec->get( 'cheeks' ) ) {
			foreach ( [ -1, 1 ] as $dir ) {
				$out .= '<ellipse class="slot-skin-dark" opacity="0.5" cx="' . G::n( $cx + $dir * ( $ex + 16 ) ) . '" cy="' . G::n( $cy + 26 ) . '" rx="12" ry="8"/>';
			}
		}
		if ( 'freckles' === $spec->get( 'cheeks' ) ) {
			foreach ( [ -1, 1 ] as $dir ) {
				foreach ( [ [ 8, 14 ], [ 15, 17 ], [ 22, 13 ] ] as $d ) {
					$out .= '<circle class="slot-skin-dark" cx="' . G::n( $cx + $dir * ( $ex + $d[0] - 4 ) ) . '" cy="' . G::n( $cy + $d[1] + 8 ) . '" r="1.5"/>';
				}
			}
		}

		foreach ( [ -1, 1 ] as $dir ) {
			$x = $cx + $dir * $ex;
			switch ( $spec->get( 'eyes' ) ) {
				case 'happy':
					$out .= $sk->line( [ [ $x - 7, $ey + 3 ], [ $x, $ey - 5 ], [ $x + 7, $ey + 3 ] ], 'neutral-dark', 'eye' . $dir, 3.0, 0.3 );
					break;
				case 'sleepy':
					$out .= $sk->line( [ [ $x - 7, $ey ], [ $x, $ey + 3 ], [ $x + 7, $ey ] ], 'neutral-dark', 'eye' . $dir, 3.0, 0.3 );
					break;
				case 'wide':
					$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $x ) . '" cy="' . G::n( $ey ) . '" rx="5" ry="6.4"/><circle class="slot-background" cx="' . G::n( $x - 1.6 ) . '" cy="' . G::n( $ey - 2.4 ) . '" r="1.9"/>';
					break;
				case 'sparkle':
					$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $x ) . '" cy="' . G::n( $ey ) . '" rx="4.6" ry="5.8"/><circle class="slot-background" cx="' . G::n( $x - 1.5 ) . '" cy="' . G::n( $ey - 2.2 ) . '" r="1.8"/><circle class="slot-background" cx="' . G::n( $x + 1.6 ) . '" cy="' . G::n( $ey + 2.6 ) . '" r="0.9"/>';
					break;
				default:
					$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $x ) . '" cy="' . G::n( $ey ) . '" rx="3.4" ry="4.6"/><circle class="slot-background" cx="' . G::n( $x - 1 ) . '" cy="' . G::n( $ey - 1.6 ) . '" r="1.1"/>';
			}

			$by = $ey - 14;
			switch ( $spec->get( 'brows' ) ) {
				case 'soft':
					$out .= $sk->line( [ [ $x - 7, $by + 1 ], [ $x, $by - 2 ], [ $x + 7, $by + 1 ] ], 'hair-dark', 'brow' . $dir, 2.2, 0.3 );
					break;
				case 'thick':
					$out .= $sk->line( [ [ $x - 8, $by + 1 ], [ $x, $by - 2 ], [ $x + 8, $by + 2 ] ], 'hair-dark', 'brow' . $dir, 4.2, 0.3 );
					break;
				case 'raised':
					$out .= $sk->line( [ [ $x - 7, $by - 2 ], [ $x, $by - 8 ], [ $x + 7, $by - 3 ] ], 'hair-dark', 'brow' . $dir, 2.4, 0.3 );
					break;
			}
		}//end foreach

		switch ( $spec->get( 'nose' ) ) {
			case 'dot':
				$out .= '<ellipse class="slot-skin-dark" cx="' . G::n( $cx + 1 ) . '" cy="' . G::n( $cy + 22 ) . '" rx="2" ry="1.5"/>';
				break;
			case 'line':
				$out .= $sk->line( [ [ $cx + 2, $cy + 15 ], [ $cx, $cy + 23 ], [ $cx + 5, $cy + 23 ] ], 'skin-dark', 'nose', 1.6, 0.2 );
				break;
		}

		$my = $cy + 36;
		switch ( $spec->get( 'mouth' ) ) {
			case 'grin':
				$out .= $sk->soft( [ [ $cx - 11, $my - 4 ], [ $cx, $my - 2 ], [ $cx + 11, $my - 4 ], [ $cx + 8, $my + 8 ], [ $cx, $my + 11 ], [ $cx - 8, $my + 8 ] ], 'neutral-dark', 'neutral-dark', 'mouth', 1.2, 0.3 );
				$out .= '<path class="slot-background" d="M' . G::n( $cx - 9 ) . ' ' . G::n( $my - 3 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $my + 1 ) . ' ' . G::n( $cx + 9 ) . ' ' . G::n( $my - 3 ) . ' L' . G::n( $cx + 8 ) . ' ' . G::n( $my + 2 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $my + 5 ) . ' ' . G::n( $cx - 8 ) . ' ' . G::n( $my + 2 ) . ' Z"/>';
				break;
			case 'open':
				$out .= '<ellipse class="slot-neutral-dark" cx="' . G::n( $cx ) . '" cy="' . G::n( $my + 3 ) . '" rx="6" ry="7.5"/><ellipse class="slot-primary" cx="' . G::n( $cx ) . '" cy="' . G::n( $my + 8 ) . '" rx="3.6" ry="2.2"/>';
				break;
			case 'flat':
				$out .= $sk->line( [ [ $cx - 7, $my + 1 ], [ $cx, $my + 2 ], [ $cx + 7, $my + 1 ] ], 'neutral-dark', 'mouth', 2.4, 0.3 );
				break;
			case 'smirk':
				$out .= $sk->line( [ [ $cx - 7, $my + 3 ], [ $cx + 2, $my + 3 ], [ $cx + 9, $my - 3 ] ], 'neutral-dark', 'mouth', 2.4, 0.3 );
				break;
			case 'cat':
				$out .= $sk->line( [ [ $cx - 10, $my - 2 ], [ $cx - 5, $my + 4 ], [ $cx, $my - 1 ], [ $cx + 5, $my + 4 ], [ $cx + 10, $my - 2 ] ], 'neutral-dark', 'mouth', 2.2, 0.3 );
				break;
			default:
				$out .= $sk->line( [ [ $cx - 9, $my - 2 ], [ $cx, $my + 5 ], [ $cx + 9, $my - 2 ] ], 'neutral-dark', 'mouth', 2.6, 0.3 );
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
		$ey = self::CY + 10.0;
		$ex = 25.0 + ( $rx - 64 ) * 0.4;
		$l  = G::n( $cx - $ex );
		$r  = G::n( $cx + $ex );
		$y  = G::n( $ey );

		if ( 'sunglasses' === $style ) {
			return '<rect class="slot-neutral-dark" x="' . G::n( $cx - $ex - 15 ) . '" y="' . G::n( $ey - 9 ) . '" width="30" height="20" rx="8"/><rect class="slot-neutral-dark" x="' . G::n( $cx + $ex - 15 ) . '" y="' . G::n( $ey - 9 ) . '" width="30" height="20" rx="8"/>'
				. '<path class="slot-stroke-neutral-dark" d="M' . G::n( $cx - $ex + 15 ) . ' ' . G::n( $ey - 3 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $ey - 8 ) . ' ' . G::n( $cx + $ex - 15 ) . ' ' . G::n( $ey - 3 ) . '" fill="none" stroke-width="3"/>'
				. '<path class="slot-neutral-light" opacity="0.5" d="M' . G::n( $cx - $ex - 9 ) . ' ' . G::n( $ey - 4 ) . ' l7 -2 l-2 6 z"/>';
		}

		$frame = ' fill="none" stroke-width="2.8" stroke-linejoin="round"';
		$lens  = 'round' === $style
			? '<circle class="slot-stroke-neutral-dark" cx="' . $l . '" cy="' . $y . '" r="14.5"' . $frame . '/><circle class="slot-stroke-neutral-dark" cx="' . $r . '" cy="' . $y . '" r="14.5"' . $frame . '/>'
			: '<rect class="slot-stroke-neutral-dark" x="' . G::n( $cx - $ex - 15 ) . '" y="' . G::n( $ey - 11 ) . '" width="30" height="22" rx="5"' . $frame . '/><rect class="slot-stroke-neutral-dark" x="' . G::n( $cx + $ex - 15 ) . '" y="' . G::n( $ey - 11 ) . '" width="30" height="22" rx="5"' . $frame . '/>';

		return $lens . '<path class="slot-stroke-neutral-dark" d="M' . G::n( $cx - $ex + 15 ) . ' ' . G::n( $ey - 2 ) . ' Q' . G::n( $cx ) . ' ' . G::n( $ey - 7 ) . ' ' . G::n( $cx + $ex - 15 ) . ' ' . G::n( $ey - 2 ) . '" fill="none" stroke-width="2.8"/>';
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
		$one         = static fn( array $p, string $key ): string => $sk->soft( $p, 'hair', 'hair-dark', $key, 1.4, 0.9 );

		return match ( $spec->get( 'hair' ) ) {
			'bob'       => $one( [ [ $cx - $rx - 6, $cy - 10 ], [ $cx - $rx - 12, $cy + 30 ], [ $cx - $rx - 2, $cy + 58 ], [ $cx + $rx + 2, $cy + 58 ], [ $cx + $rx + 12, $cy + 30 ], [ $cx + $rx + 6, $cy - 10 ], [ $cx + $rx * 0.7, $cy - $ry - 4 ], [ $cx, $cy - $ry - 10 ], [ $cx - $rx * 0.7, $cy - $ry - 4 ] ], 'hb' ),
			'long'      => $one( [ [ $cx - $rx - 6, $cy - 10 ], [ $cx - $rx - 14, $cy + 40 ], [ $cx - $rx - 8, $cy + 86 ], [ $cx + $rx + 8, $cy + 86 ], [ $cx + $rx + 14, $cy + 40 ], [ $cx + $rx + 6, $cy - 10 ], [ $cx + $rx * 0.7, $cy - $ry - 4 ], [ $cx, $cy - $ry - 10 ], [ $cx - $rx * 0.7, $cy - $ry - 4 ] ], 'hb' ),
			'ponytail'  => $one( [ [ $cx + $rx - 6, $cy - 24 ], [ $cx + $rx + 28, $cy - 12 ], [ $cx + $rx + 36, $cy + 30 ], [ $cx + $rx + 22, $cy + 62 ], [ $cx + $rx + 12, $cy + 30 ], [ $cx + $rx - 2, $cy + 4 ] ], 'tail' )
				. '<ellipse class="slot-accent" cx="' . G::n( $cx + $rx + 2 ) . '" cy="' . G::n( $cy - 12 ) . '" rx="6" ry="9"/>',
			'twintails' => $one( [ [ $cx - $rx + 6, $cy - 10 ], [ $cx - $rx - 26, $cy - 2 ], [ $cx - $rx - 34, $cy + 36 ], [ $cx - $rx - 22, $cy + 66 ], [ $cx - $rx - 10, $cy + 34 ], [ $cx - $rx + 2, $cy + 10 ] ], 'tail-l' )
				. $one( [ [ $cx + $rx - 6, $cy - 10 ], [ $cx + $rx + 26, $cy - 2 ], [ $cx + $rx + 34, $cy + 36 ], [ $cx + $rx + 22, $cy + 66 ], [ $cx + $rx + 10, $cy + 34 ], [ $cx + $rx - 2, $cy + 10 ] ], 'tail-r' )
				. '<ellipse class="slot-accent" cx="' . G::n( $cx - $rx - 2 ) . '" cy="' . G::n( $cy ) . '" rx="6" ry="8"/><ellipse class="slot-accent" cx="' . G::n( $cx + $rx + 2 ) . '" cy="' . G::n( $cy ) . '" rx="6" ry="8"/>',
			'bun'       => $one( self::circle( $cx, $cy - $ry - 14, 21, 12 ), 'bun' ),
			'curly'     => $one( self::bumps( $cx, $cy + 6, $rx + 10, $ry + 8, 9, 7 ), 'hb' ),
			'afro'      => $one( self::bumps( $cx, $cy - 2, $rx + 22, $ry + 20, 10, 9 ), 'hb' ),
			default     => '',
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
	 * Hair over the head: the fringe and top, with a glossy highlight.
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

		$inner = [ [ $cx + $rx * 0.8, $cy - 10 ], [ $cx + $rx * 0.42, $cy - 28 ], [ $cx + 10, $cy - 32 ], [ $cx - $rx * 0.1, $cy - 20 ], [ $cx - $rx * 0.5, $cy - 30 ], [ $cx - $rx * 0.8, $cy - 10 ] ];
		$outer = [ [ $cx - $rx - 2, $cy + 8 ], [ $cx - $rx - 3, $cy - 24 ], [ $cx - $rx * 0.62, $cy - $ry - 4 ], [ $cx, $cy - $ry - 9 ], [ $cx + $rx * 0.62, $cy - $ry - 4 ], [ $cx + $rx + 3, $cy - 24 ], [ $cx + $rx + 2, $cy + 8 ] ];

		if ( 'messy' === $style ) {
			$outer = [ [ $cx - $rx - 4, $cy + 10 ], [ $cx - $rx - 8, $cy - 20 ], [ $cx - $rx * 0.8, $cy - $ry + 2 ], [ $cx - $rx * 0.5, $cy - $ry - 12 ], [ $cx - 16, $cy - $ry - 2 ], [ $cx, $cy - $ry - 16 ], [ $cx + 18, $cy - $ry - 2 ], [ $cx + $rx * 0.5, $cy - $ry - 14 ], [ $cx + $rx * 0.8, $cy - $ry + 2 ], [ $cx + $rx + 8, $cy - 18 ], [ $cx + $rx + 3, $cy + 10 ] ];
		} elseif ( 'curly' === $style || 'afro' === $style ) {
			$outer = [ [ $cx - $rx - 6, $cy + 8 ], [ $cx - $rx - 8, $cy - 18 ], [ $cx - $rx * 0.7, $cy - $ry + 2 ], [ $cx - $rx * 0.35, $cy - $ry - 10 ], [ $cx, $cy - $ry - 4 ], [ $cx + $rx * 0.35, $cy - $ry - 10 ], [ $cx + $rx * 0.7, $cy - $ry + 2 ], [ $cx + $rx + 8, $cy - 18 ], [ $cx + $rx + 6, $cy + 8 ] ];
		} elseif ( 'sidepart' === $style ) {
			$inner = [ [ $cx + $rx * 0.8, $cy - 8 ], [ $cx + $rx * 0.5, $cy - 24 ], [ $cx + 4, $cy - 36 ], [ $cx - $rx * 0.3, $cy - 30 ], [ $cx - $rx * 0.62, $cy - 8 ], [ $cx - $rx * 0.8, $cy - 2 ] ];
		}

		$out  = $sk->soft( array_merge( $outer, $inner ), 'hair', 'hair-dark', 'hf', 1.4, 0.9 );
		$out .= $sk->patch( [ [ $cx - $rx * 0.58, $cy - $ry + 14 ], [ $cx - $rx * 0.3, $cy - $ry + 2 ], [ $cx + $rx * 0.12, $cy - $ry - 3 ], [ $cx - $rx * 0.08, $cy - $ry + 6 ], [ $cx - $rx * 0.4, $cy - $ry + 18 ] ], 'hair-light', 0.55, 'gloss' );
		$out .= $sk->line( [ [ $cx + 10, $cy - $ry - 4 ], [ $cx + 4, $cy - 40 ], [ $cx - 4, $cy - 30 ] ], 'hair-dark', 'strand-a', 1.4, 0.4 );

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

		$dome   = [ [ $cx - $rx - 2, $cy - 6 ], [ $cx - $rx + 2, $cy - 34 ], [ $cx - $rx * 0.5, $cy - $ry - 8 ], [ $cx, $cy - $ry - 14 ], [ $cx + $rx * 0.5, $cy - $ry - 8 ], [ $cx + $rx - 2, $cy - 34 ], [ $cx + $rx + 2, $cy - 6 ], [ $cx, $cy - 14 ] ];
		$shadow = $sk->patch( [ [ $cx - $rx * 0.8, $cy - 8 ], [ $cx, $cy - 4 ], [ $cx + $rx * 0.8, $cy - 8 ], [ $cx + $rx * 0.6, $cy + 4 ], [ $cx - $rx * 0.6, $cy + 4 ] ], 'skin-dark', 0.3, 'hat-shadow' );
		$shade  = $sk->patch( [ [ $cx + $rx * 0.2, $cy - $ry - 12 ], [ $cx + $rx * 0.5, $cy - $ry - 8 ], [ $cx + $rx - 2, $cy - 34 ], [ $cx + $rx + 2, $cy - 8 ], [ $cx + $rx * 0.3, $cy - 14 ] ], self::shade( $colour, 'dark' ), 0.3, 'hat-shade' );

		switch ( $style ) {
			case 'cap':
				return $shadow . $sk->soft( $dome, $colour, $dark, 'dome', 1.5, 0.8 ) . $shade
					. $sk->soft( [ [ $cx - $rx * 0.2, $cy - 14 ], [ $cx + $rx * 0.7, $cy - 20 ], [ $cx + $rx + 22, $cy - 8 ], [ $cx + $rx + 12, $cy - 1 ], [ $cx + $rx * 0.3, $cy - 8 ] ], $light, $dark, 'brim', 1.4, 0.6 )
					. '<circle class="slot-' . $dark . '" cx="' . G::n( $cx ) . '" cy="' . G::n( $cy - $ry - 13 ) . '" r="4.4"/>';
			case 'cap_back':
				return $sk->soft( $dome, $colour, $dark, 'dome', 1.5, 0.8 ) . $shade
					. $sk->soft( [ [ $cx - $rx - 2, $cy - 20 ], [ $cx - $rx - 20, $cy - 14 ], [ $cx - $rx - 12, $cy - 6 ], [ $cx - $rx + 4, $cy - 10 ] ], $light, $dark, 'brim', 1.4, 0.6 )
					. '<circle class="slot-' . $dark . '" cx="' . G::n( $cx ) . '" cy="' . G::n( $cy - $ry - 13 ) . '" r="4.4"/>';
			case 'hardhat':
				$hat = [ [ $cx - $rx - 4, $cy - 4 ], [ $cx - $rx, $cy - 38 ], [ $cx - $rx * 0.5, $cy - $ry - 18 ], [ $cx, $cy - $ry - 24 ], [ $cx + $rx * 0.5, $cy - $ry - 18 ], [ $cx + $rx, $cy - 38 ], [ $cx + $rx + 4, $cy - 4 ], [ $cx, $cy - 12 ] ];

				return $shadow . $sk->soft( $hat, $colour, $dark, 'dome', 1.5, 0.8 ) . $shade
					. $sk->soft( [ [ $cx - $rx - 16, $cy - 6 ], [ $cx, $cy - 17 ], [ $cx + $rx + 16, $cy - 6 ], [ $cx + $rx + 10, $cy + 2 ], [ $cx, $cy - 6 ], [ $cx - $rx - 10, $cy + 2 ] ], $colour, $dark, 'brim', 1.5, 0.7 )
					. $sk->line( [ [ $cx - 12, $cy - $ry - 20 ], [ $cx - 8, $cy - 24 ], [ $cx - 8, $cy - 12 ] ], $dark, 'ridge-l', 1.8, 0.3 )
					. $sk->line( [ [ $cx + 12, $cy - $ry - 20 ], [ $cx + 8, $cy - 24 ], [ $cx + 8, $cy - 12 ] ], $dark, 'ridge-r', 1.8, 0.3 );
			case 'beanie':
				return $sk->soft( $dome, $colour, $dark, 'dome', 1.5, 0.8 ) . $shade
					. $sk->soft( [ [ $cx - $rx - 3, $cy - 18 ], [ $cx, $cy - 25 ], [ $cx + $rx + 3, $cy - 18 ], [ $cx + $rx + 3, $cy - 4 ], [ $cx, $cy - 10 ], [ $cx - $rx - 3, $cy - 4 ] ], $light, $dark, 'cuff', 1.4, 0.6 )
					. '<circle class="slot-' . $light . ' slot-stroke-' . $dark . '" stroke-opacity="0.55" cx="' . G::n( $cx ) . '" cy="' . G::n( $cy - $ry - 16 ) . '" r="8" stroke-width="1.4"/>';
			case 'bucket':
				return $shadow . $sk->soft( [ [ $cx - $rx + 4, $cy - 12 ], [ $cx - $rx + 8, $cy - 34 ], [ $cx, $cy - $ry - 8 ], [ $cx + $rx - 8, $cy - 34 ], [ $cx + $rx - 4, $cy - 12 ], [ $cx, $cy - 8 ] ], $colour, $dark, 'dome', 1.5, 0.8 )
					. $sk->soft( [ [ $cx - $rx - 18, $cy - 8 ], [ $cx, $cy - 20 ], [ $cx + $rx + 18, $cy - 8 ], [ $cx + $rx + 8, $cy + 2 ], [ $cx, $cy - 6 ], [ $cx - $rx - 8, $cy + 2 ] ], $light, $dark, 'brim', 1.5, 0.7 );
		}//end switch

		return '';
	}
}
