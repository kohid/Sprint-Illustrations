<?php
/**
 * Draws a character as a library piece (SVG with colour slots, anchors and metadata).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Character;

use SprintIllustrations\Character\Geometry as G;

/**
 * Slender, layered flat characters in the style of modern people illustrations: about 7.5 heads tall with
 * tapered limbs, garments in layers (a top, an outer layer, trousers or a skirt, shoes), shaped hair and
 * hats, small faces and carried bags. Every character shares one grid (160 x 320 standing, 200 x 300 sitting;
 * head centred at 80,26; shoulders at y 60; a standing adult is about 300 units tall), so any combination
 * lines up. Colour comes only from slot classes (skin, hair, primary, secondary, accent, neutral, background),
 * and each character has an `anchor-ground` and an `anchor-hold`, so it behaves like a hand-drawn piece in
 * every template.
 */
final class CharacterBuilder {

	private const CX = 80;

	private const SEAT_SHIFT = 40;

	/**
	 * Side of the fixed canvas a hand-made pose is edited in.
	 */
	public const EDIT_FRAME = 420;

	/**
	 * Which parts are drawn in front of the body or behind it, unless a hand-made pose says otherwise.
	 */
	public const DEFAULT_ORDER = [
		'arm_l' => 'front',
		'arm_r' => 'front',
		'leg_l' => 'back',
		'leg_r' => 'back',
	];

	/**
	 * Proportions per build: shoulder, waist and hip half widths.
	 */
	private const BUILD = [
		'slim'    => [
			'sh' => 20.0,
			'wa' => 14.0,
			'hi' => 18.0,
		],
		'regular' => [
			'sh' => 23.0,
			'wa' => 16.5,
			'hi' => 20.5,
		],
		'broad'   => [
			'sh' => 27.0,
			'wa' => 20.0,
			'hi' => 23.5,
		],
	];

	/**
	 * Arm poses. Each arm is [upper arm angle, forearm angle] from straight down, outward positive (see
	 * Geometry::reach), plus which hand is free for the hold anchor and whether an item is carried.
	 */
	private const POSES = [
		'standing' => [
			'relaxed' => [
				'l'    => [ 6, 3 ],
				'r'    => [ 6, 3 ],
				'free' => 'r',
				'over' => false,
			],
			'wave'    => [
				'l'    => [ 6, 3 ],
				'r'    => [ 96, 172 ],
				'free' => 'l',
				'over' => false,
			],
			'phone'   => [
				'l'    => [ 6, 3 ],
				'r'    => [ 28, 158 ],
				'free' => 'l',
				'over' => false,
				'item' => [ 'phone', 'r' ],
			],
			'folder'  => [
				'l'    => [ 6, 3 ],
				'r'    => [ 16, -100 ],
				'free' => 'l',
				'over' => true,
				'item' => [ 'folder', 'r' ],
			],
			'crossed' => [
				'l'    => [ 10, -104 ],
				'r'    => [ 10, -104 ],
				'free' => 'r',
				'over' => true,
			],
			'hip'     => [
				'l'    => [ 6, 3 ],
				'r'    => [ 40, -28 ],
				'free' => 'l',
				'over' => true,
			],
			'present' => [
				'l'    => [ 6, 3 ],
				'r'    => [ 26, 50 ],
				'free' => 'l',
				'over' => false,
			],
			'point'   => [
				'l'    => [ 6, 3 ],
				'r'    => [ 34, 48 ],
				'free' => 'l',
				'over' => false,
			],
			'think'   => [
				'l'    => [ 10, -104 ],
				'r'    => [ 16, 218 ],
				'free' => 'l',
				'over' => true,
			],
			'clasped' => [
				'l'    => [ 8, -40 ],
				'r'    => [ 8, -40 ],
				'free' => 'r',
				'over' => false,
			],
			'pockets' => [
				'l'    => [ 22, -20 ],
				'r'    => [ 22, -20 ],
				'free' => 'r',
				'over' => false,
			],
			'cheer'   => [
				'l'    => [ 62, 168 ],
				'r'    => [ 62, 168 ],
				'free' => 'l',
				'over' => false,
			],
			'shrug'   => [
				'l'    => [ 34, 132 ],
				'r'    => [ 34, 132 ],
				'free' => 'l',
				'over' => false,
			],
			'tablet'  => [
				'l'    => [ 6, 3 ],
				'r'    => [ 24, -76 ],
				'free' => 'l',
				'over' => true,
				'item' => [ 'tablet', 'r' ],
			],
			'coffee'  => [
				'l'    => [ 6, 3 ],
				'r'    => [ 26, 164 ],
				'free' => 'l',
				'over' => false,
				'item' => [ 'cup', 'r' ],
			],
			'book'    => [
				'l'    => [ 12, -84 ],
				'r'    => [ 12, -84 ],
				'free' => 'r',
				'over' => true,
				'item' => [ 'book', 'both' ],
			],
			'box'     => [
				'l'    => [ 26, -46 ],
				'r'    => [ 26, -46 ],
				'free' => 'r',
				'over' => true,
				'item' => [ 'box', 'both' ],
			],
		],
		'sitting'  => [
			'lap'    => [
				'l'    => [ 8, -62 ],
				'r'    => [ 8, -62 ],
				'free' => 'r',
				'over' => true,
			],
			'typing' => [
				'l'    => [ 14, -98 ],
				'r'    => [ 14, -98 ],
				'free' => 'r',
				'over' => true,
			],
			'wave'   => [
				'l'    => [ 8, -62 ],
				'r'    => [ 96, 172 ],
				'free' => 'l',
				'over' => false,
			],
			'phone'  => [
				'l'    => [ 8, -62 ],
				'r'    => [ 28, 158 ],
				'free' => 'l',
				'over' => false,
				'item' => [ 'phone', 'r' ],
			],
			'think'  => [
				'l'    => [ 8, -62 ],
				'r'    => [ 16, 218 ],
				'free' => 'l',
				'over' => true,
			],
			'book'   => [
				'l'    => [ 12, -84 ],
				'r'    => [ 12, -84 ],
				'free' => 'r',
				'over' => true,
				'item' => [ 'book', 'both' ],
			],
			'coffee' => [
				'l'    => [ 8, -62 ],
				'r'    => [ 26, 164 ],
				'free' => 'l',
				'over' => false,
				'item' => [ 'cup', 'r' ],
			],
			'cheer'  => [
				'l'    => [ 62, 168 ],
				'r'    => [ 62, 168 ],
				'free' => 'l',
				'over' => false,
			],
		],
	];

	/**
	 * Arm poses for the other stances. `screen` poses give screen angles (0 down, 90 right, 180 up), which
	 * are turned into angles relative to the leaning or lying body when the figure is drawn.
	 */
	private const NEW_ARMS = [
		'swing' => [
			'l'    => [ 12, -22 ],
			'r'    => [ 28, 34 ],
			'free' => 'l',
			'over' => false,
		],
		'run'   => [
			'screen' => true,
			'l'      => [ -40, -140 ],
			'r'      => [ 35, 150 ],
			'free'   => 'l',
			'over'   => false,
		],
		'out'   => [
			'screen' => true,
			'l'      => [ -95, -92 ],
			'r'      => [ 95, 92 ],
			'free'   => 'l',
			'over'   => false,
		],
		'hang'  => [
			'screen' => true,
			'l'      => [ -4, -3 ],
			'r'      => [ 4, 3 ],
			'free'   => 'l',
			'over'   => false,
		],
		'reach' => [
			'screen' => true,
			'l'      => [ 8, 5 ],
			'r'      => [ 28, 14 ],
			'free'   => 'l',
			'over'   => false,
		],
		'knees' => [
			'screen' => true,
			'l'      => [ -38, -22 ],
			'r'      => [ 38, 22 ],
			'free'   => 'l',
			'over'   => true,
		],
		'pray'  => [
			'l'    => [ 8, -120 ],
			'r'    => [ 8, -120 ],
			'free' => 'r',
			'over' => true,
		],
		'climb' => [
			'screen' => true,
			'l'      => [ -160, -176 ],
			'r'      => [ 125, 165 ],
			'free'   => 'r',
			'over'   => false,
		],
		'grip'  => [
			'screen' => true,
			'l'      => [ -172, -178 ],
			'r'      => [ 172, 178 ],
			'free'   => 'r',
			'over'   => false,
		],
		'one'   => [
			'screen' => true,
			'l'      => [ -175, -180 ],
			'r'      => [ 6, 4 ],
			'free'   => 'r',
			'over'   => false,
		],
		'rest'  => [
			'screen' => true,
			'l'      => [ 90, 92 ],
			'r'      => [ 90, 92 ],
			'free'   => 'l',
			'over'   => false,
		],
		'up'    => [
			'screen' => true,
			'l'      => [ -88, -90 ],
			'r'      => [ -88, -90 ],
			'free'   => 'l',
			'over'   => false,
		],
		'hands' => [
			'screen' => true,
			'l'      => [ -2, -1 ],
			'r'      => [ 2, 1 ],
			'free'   => 'l',
			'over'   => false,
		],
		'crawl' => [
			'screen' => true,
			'l'      => [ -4, -2 ],
			'r'      => [ 32, 18 ],
			'free'   => 'r',
			'over'   => false,
		],
		'hug'   => [
			'screen' => true,
			'l'      => [ 55, 80 ],
			'r'      => [ 60, 85 ],
			'free'   => 'l',
			'over'   => true,
		],
	];

	/**
	 * The piece as an SVG string, ready for the library (anchors are markers the manifest builder removes).
	 *
	 * @param CharacterSpec $spec   Choices.
	 * @param string        $label  Piece label.
	 * @param array<string> $tags   Tags.
	 * @param string        $person Person name.
	 * @param bool          $edit   Fixed editing canvas for a hand-made pose.
	 * @return string
	 */
	public static function svg( CharacterSpec $spec, string $label = 'Character', array $tags = [ 'person' ], string $person = '', bool $edit = false ): string {
		if ( null !== Stances::get( $spec->get( 'stance' ) ) || null !== $spec->custom ) {
			return self::posed( $spec, $label, $tags, $person, $edit );
		}

		$sitting = 'sitting' === $spec->get( 'stance' );
		$rig     = self::rig( $spec );
		$dy      = $sitting ? self::SEAT_SHIFT : 0;

		$lower = [ self::shadow( $sitting ) ];
		$upper = [];

		// Behind the body: backpack, long hair, ponytail.
		if ( 'backpack' === $spec->get( 'bag' ) ) {
			$upper[] = self::backpack( $spec, $rig );
		}
		$upper[] = self::hair( $spec, false );

		$lower[] = self::legs( $spec, $rig );

		$upper[] = '<path class="slot-skin-dark" d="M74 38 H86 V62 H74 Z"/>';
		$upper[] = self::torso( $spec, $rig );
		$upper[] = self::bag_over_torso( $spec, $rig );
		$upper[] = self::arms( $spec, $rig );
		$upper[] = self::carried( $spec, $rig );
		$upper[] = self::head( $spec );

		$anchors = self::anchors( $spec, $rig, $dy );

		$attributes = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" data-si-label="%3$s" data-si-tags="%4$s" data-si-accepts="%5$s"%6$s',
			$sitting ? 200 : 160,
			$sitting ? 300 : 320,
			htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			htmlspecialchars( implode( ',', $tags ), ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			$sitting ? 'hold:lap' : 'hold:handheld',
			'' !== $person ? ' data-si-person="' . htmlspecialchars( $person, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"' : ''
		);

		$upper_markup = implode( "\n\t\t", array_filter( $upper ) );
		$body         = $dy > 0 ? "<g transform=\"translate(0 $dy)\">\n\t\t" . $upper_markup . "\n\t</g>" : $upper_markup;

		return '<svg ' . $attributes . ">\n\t" . implode( "\n\t", array_filter( $lower ) ) . "\n\t" . $body . "\n\t" . $anchors . "\n</svg>\n";
	}

	// ---------------------------------------------------------------- colour helpers.

	/**
	 * Fill class for a colour token ("primary", "accent-dark", "background").
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function f( string $token ): string {
		return 'slot-' . $token;
	}

	/**
	 * Stroke class for a colour token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function s( string $token ): string {
		return 'slot-stroke-' . $token;
	}

	/**
	 * A darker or lighter shade of a token; shades of shades and of off-white stay themselves.
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

	// ---------------------------------------------------------------- rig.

	/**
	 * Body landmarks and joint positions for the spec.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @return array<string, mixed>
	 */
	private static function rig( CharacterSpec $spec ): array {
		$build = self::BUILD[ $spec->get( 'build' ) ];
		// Body shape: women a little narrower at the shoulder and fuller at the hip, men the other way.
		$shape        = [
			'woman' => [ -1.5, -1.0, 1.5 ],
			'man'   => [ 1.5, 0.5, -1.0 ],
		][ $spec->get( 'gender' ) ] ?? [ 0.0, 0.0, 0.0 ];
		$build['sh'] += $shape[0];
		$build['wa'] += $shape[1];
		$build['hi'] += $shape[2];
		$sitting      = 'sitting' === $spec->get( 'stance' );
		$pose         = self::arm_pose( $spec );
		$cx           = self::CX;

		$shoulder = [
			'l' => [ $cx - $build['sh'] + 3, 66.0 ],
			'r' => [ $cx + $build['sh'] - 3, 66.0 ],
		];
		$elbow    = [];
		$wrist    = [];
		foreach ( [ 'l', 'r' ] as $side ) {
			$elbow[ $side ] = G::reach( $shoulder[ $side ], 42, (float) $pose[ $side ][0], $side );
			$wrist[ $side ] = G::reach( $elbow[ $side ], 38, (float) $pose[ $side ][1], $side );
		}

		return [
			'sh'       => $build['sh'],
			'wa'       => $build['wa'],
			'hi'       => $build['hi'],
			'shoulder' => $shoulder,
			'elbow'    => $elbow,
			'wrist'    => $wrist,
			'pose'     => $pose,
			'sitting'  => $sitting,
			'carry'    => in_array( $spec->get( 'stance' ), [ 'standing', 'walking', 'leaning' ], true ),
		];
	}

	/**
	 * Ground shadow.
	 *
	 * @param bool $sitting Sitting.
	 * @return string
	 */
	private static function shadow( bool $sitting ): string {
		return $sitting
			? '<ellipse class="slot-neutral" cx="104" cy="291" rx="80" ry="6" opacity="0.12"/>'
			: '<ellipse class="slot-neutral" cx="80" cy="309" rx="42" ry="5" opacity="0.12"/>';
	}

	// ---------------------------------------------------------------- lower body.

	/**
	 * Legs, trousers or skirt, and shoes (and the chair when seated).
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @return string
	 */
	private static function legs( CharacterSpec $spec, array $rig ): string {
		$bottom = 'dress' === $spec->get( 'top' ) ? 'skirt' : $spec->get( 'bottom' );
		$colour = 'dress' === $spec->get( 'top' ) ? $spec->get( 'top_color' ) : $spec->get( 'bottom_color' );
		$bare   = in_array( $bottom, [ 'shorts', 'skirt', 'midi' ], true );
		$hi     = (float) $rig['hi'];
		$cx     = self::CX;
		$out    = [];

		if ( $rig['sitting'] ) {
			return self::seated_legs( $spec, $rig, $bottom, $colour, $bare );
		}

		$stance = [
			'straight' => [
				'l' => [ [ $cx - 10, 226 ], [ $cx - 11, 298 ] ],
				'r' => [ [ $cx + 10, 226 ], [ $cx + 11, 298 ] ],
			],
			'step'     => [
				'l' => [ [ $cx - 13, 226 ], [ $cx - 18, 300 ] ],
				'r' => [ [ $cx + 9, 223 ], [ $cx + 13, 295 ] ],
			],
			'wide'     => [
				'l' => [ [ $cx - 15, 226 ], [ $cx - 23, 298 ] ],
				'r' => [ [ $cx + 15, 226 ], [ $cx + 23, 298 ] ],
			],
		][ $spec->get( 'legs' ) ];

		$thigh = 15.0 + ( $hi - 20.5 ) * 0.5;
		$knee  = 'slim' === $bottom ? 11.0 : 13.0;
		$ankle = 'slim' === $bottom ? 8.5 : ( 'trousers' === $bottom ? 11.5 : 9.0 );
		$cut   = 'cropped' === $bottom ? 274.0 : ( 'shorts' === $bottom ? 234.0 : 400.0 );

		foreach ( [ 'l', 'r' ] as $side ) {
			$hip       = [ $cx + ( 'l' === $side ? -8.5 : 8.5 ), 152.0 ];
			[ $k, $a ] = $stance[ $side ];
			$leg       = [
				'hip'   => $hip,
				'knee'  => $k,
				'ankle' => $a,
			];

			// Skin first: it shows below shorts, cropped trousers and skirts.
			$out[] = '<path class="slot-skin" d="' . G::limb( $hip, $k, $thigh, 11 ) . '"/><path class="slot-skin" d="' . G::limb( $k, $a, 10, 7 ) . '"/>';
			if ( ! $bare || 'shorts' === $bottom ) {
				$end   = $a;
				$width = $ankle;
				if ( $cut < 400 ) {
					$t     = max( 0.0, min( 1.0, ( $cut - $k[1] ) / max( 1.0, $a[1] - $k[1] ) ) );
					$end   = [ $k[0] + ( $a[0] - $k[0] ) * $t, $cut ];
					$width = $knee + ( $ankle - $knee ) * $t;
				}
				if ( 'shorts' === $bottom ) {
					$end   = [ $hip[0] + ( $k[0] - $hip[0] ) * 0.85, $hip[1] + ( $k[1] - $hip[1] ) * 0.85 ];
					$width = $thigh - 2;
				}
				$fill = self::f( $colour );
				if ( 'shorts' === $bottom ) {
					$out[] = '<path class="' . $fill . '" d="' . G::limb( $hip, $end, $thigh + 1.5, $width + 1.5 ) . '"/>';
				} else {
					$out[] = '<path class="' . $fill . '" d="' . G::limb( $hip, $k, $thigh + 1.5, $knee ) . '"/><path class="' . $fill . '" d="' . G::limb( $k, $end, $knee, $width ) . '"/>';
				}
			}
			$out[] = self::shoe( $spec, $a, 'l' === $side ? -1 : 1 );
			unset( $leg );
		}//end foreach

		// Hips: fills the gap between the legs and the torso.
		$fill  = $bare && 'shorts' !== $bottom ? self::f( $colour ) : self::f( $colour );
		$out[] = '<path class="' . $fill . '" d="' . G::closed( [ [ $cx - $hi, 136 ], [ $cx - $hi + 1, 150 ], [ $cx - 4, 162 ], [ $cx + 4, 162 ], [ $cx + $hi - 1, 150 ], [ $cx + $hi, 136 ], [ $cx, 128 ] ] ) . '"/>';

		if ( in_array( $bottom, [ 'skirt', 'midi' ], true ) ) {
			$hem    = 'midi' === $bottom ? 262.0 : 214.0;
			$spread = 'midi' === $bottom ? 34.0 : 32.0;
			$out[]  = '<path class="' . self::f( $colour ) . '" d="' . G::closed( [ [ $cx - $rig['wa'] - 1, 124 ], [ $cx - $hi - 2, 146 ], [ $cx - $spread, $hem - 2 ], [ $cx - $spread + 6, $hem ], [ $cx + $spread - 6, $hem ], [ $cx + $spread, $hem - 2 ], [ $cx + $hi + 2, 146 ], [ $cx + $rig['wa'] + 1, 124 ] ] ) . '"/>';
			$out[]  = '<path class="' . self::f( self::shade( $colour, 'dark' ) ) . '" d="M' . G::n( $cx - 6 ) . ' 150 Q' . G::n( $cx - 9 ) . ' ' . G::n( $hem - 30 ) . ' ' . G::n( $cx - 12 ) . ' ' . G::n( $hem - 2 ) . ' L' . G::n( $cx - 4 ) . ' ' . G::n( $hem - 2 ) . ' Q' . G::n( $cx - 3 ) . ' 190 ' . G::n( $cx - 2 ) . ' 152 Z" opacity="0.5"/>';
		}

		return implode( "\n\t", $out );
	}

	/**
	 * A shoe at the ankle; toes point outwards.
	 *
	 * @param CharacterSpec                     $spec  Spec.
	 * @param array{0: float|int, 1: float|int} $ankle Ankle.
	 * @param int                               $dir   -1 for the left foot, 1 for the right.
	 * @return string
	 */
	private static function shoe( CharacterSpec $spec, array $ankle, int $dir ): string {
		[ $x, $y ] = [ (float) $ankle[0], (float) $ankle[1] ];
		$colour    = $spec->get( 'shoes_color' );
		$style     = $spec->get( 'shoes' );
		$top       = 'boots' === $style ? $y - 16 : $y - 6;
		$toe       = $x + $dir * 12;
		$shape     = G::polygon( [ [ $x - 7 * $dir, $top ], [ $x + 7 * $dir, $top ], [ $x + 7 * $dir, $y + 2 ], [ $toe, $y + 7 ], [ $toe, $y + 10.5 ], [ $x - 6 * $dir, $y + 10.5 ], [ $x - 7 * $dir, $y + 3 ] ] );
		$out       = '<path class="' . self::f( $colour ) . '" d="' . $shape . '"/>';
		if ( 'sneakers' === $style ) {
			$out .= '<path class="slot-background" d="M' . G::n( $x - 6.5 * $dir ) . ' ' . G::n( $y + 8 ) . ' H' . G::n( $toe + 0.5 * $dir ) . ' V' . G::n( $y + 11 ) . ' Q' . G::n( $x ) . ' ' . G::n( $y + 12.5 ) . ' ' . G::n( $x - 6.5 * $dir ) . ' ' . G::n( $y + 11 ) . ' Z"/>';
		}

		return $out;
	}

	/**
	 * Seated lower body: thighs forward, shins down, chair.
	 *
	 * @param CharacterSpec        $spec   Spec.
	 * @param array<string, mixed> $rig    Rig.
	 * @param string               $bottom Bottom style.
	 * @param string               $colour Bottom colour.
	 * @param bool                 $bare   Legs show below the garment.
	 * @return string
	 */
	private static function seated_legs( CharacterSpec $spec, array $rig, string $bottom, string $colour, bool $bare ): string {
		$dy  = self::SEAT_SHIFT;
		$hi  = (float) $rig['hi'];
		$cx  = self::CX;
		$out = [];

		if ( 'none' !== $spec->get( 'seat' ) ) {
			$out[] = '<rect class="slot-neutral-light" x="34" y="' . ( 190 + 4 ) . '" width="92" height="88" rx="10"/>';
			$out[] = '<path class="slot-outline" d="M42 ' . ( 190 + 20 ) . ' H118" fill="none" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/>';
		}

		$legs = [
			[ [ $cx + 2, 192.0 ], [ 138.0, 192.0 ], [ 140.0, 268.0 ] ],
			[ [ $cx + 6, 197.0 ], [ 152.0, 197.0 ], [ 154.0, 270.0 ] ],
		];
		foreach ( $legs as $i => [ $hip, $knee, $ankle ] ) {
			$out[] = '<path class="slot-skin" d="' . G::limb( $hip, $knee, 16, 13 ) . '"/><path class="slot-skin" d="' . G::limb( $knee, $ankle, 11, 8 ) . '"/>';
			if ( ! $bare || 'shorts' === $bottom ) {
				$cover = 'shorts' === $bottom ? [ $hip[0] + ( $knee[0] - $hip[0] ) * 0.75, $hip[1] ] : $knee;
				$out[] = '<path class="' . self::f( $colour ) . '" d="' . G::limb( $hip, $cover, 17, 14 ) . '"/>';
				if ( 'shorts' !== $bottom ) {
					$end   = 'cropped' === $bottom ? [ $knee[0] + ( $ankle[0] - $knee[0] ) * 0.7, $knee[1] + ( $ankle[1] - $knee[1] ) * 0.7 ] : $ankle;
					$out[] = '<path class="' . self::f( $colour ) . '" d="' . G::limb( $knee, $end, 14, 'slim' === $bottom ? 9.5 : 12 ) . '"/>';
				}
			} elseif ( in_array( $bottom, [ 'skirt', 'midi' ], true ) ) {
				$out[] = '<path class="' . self::f( $colour ) . '" d="' . G::limb( $hip, [ $hip[0] + ( $knee[0] - $hip[0] ) * 0.85, $hip[1] ], 20, 17 ) . '"/>';
			}
			$out[] = self::shoe( $spec, [ $ankle[0] - 2, $ankle[1] + 1 ], 1 );
			unset( $i );
		}
		$out[] = '<path class="' . self::f( $colour ) . '" d="' . G::closed( [ [ $cx - $hi, 178 ], [ $cx - $hi + 1, 190 ], [ $cx + 4, 200 ], [ $cx + 12, 196 ], [ $cx + 12, 184 ], [ $cx, 172 ] ] ) . '"/>';

		return implode( "\n\t", $out );
	}

	// ---------------------------------------------------------------- torso.

	/**
	 * Torso outline in the given hem and flare.
	 *
	 * @param array<string, mixed> $rig  Rig.
	 * @param float                $hem  Hem y.
	 * @param float                $flare How far the hem spreads beyond the hips.
	 * @param float                $inset Narrowing at the shoulders (for sleeveless garments).
	 * @return string
	 */
	private static function torso_path( array $rig, float $hem, float $flare = 0.0, float $inset = 0.0 ): string {
		$cx = self::CX;
		$sh = (float) $rig['sh'] - $inset;
		$wa = (float) $rig['wa'];
		$hi = (float) $rig['hi'];

		$left  = [ [ $cx - 6.5, 58.5 ], [ $cx - $sh + 2, 61.5 ], [ $cx - $sh, 70 ], [ $cx - $wa - 3, 94 ], [ $cx - $wa, 118 ], [ $cx - $hi + 0.5, 140 ], [ $cx - $hi - $flare, $hem ] ];
		$right = array_reverse( array_map( static fn( array $p ): array => G::mirror( $p, $cx ), $left ) );

		return G::open( $left ) . ' L' . G::pt( $right[0] ) . ' ' . substr( G::open( $right ), strlen( 'M' . G::pt( $right[0] ) ) ) . ' Q' . G::pt( [ $cx, 62 ] ) . ' ' . G::pt( $left[0] ) . ' Z';
	}

	/**
	 * The garments on the upper body: top, outer layer, extras.
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @return string
	 */
	private static function torso( CharacterSpec $spec, array $rig ): string {
		$top   = $spec->get( 'top' );
		$tc    = $spec->get( 'top_color' );
		$outer = $spec->get( 'outer' );
		$cx    = self::CX;
		$out   = [];

		$hem = 'dress' === $top ? 132.0 : ( in_array( $top, [ 'sweater', 'hoodie', 'longsleeve' ], true ) ? 146.0 : 140.0 );
		if ( 'blouse' === $top ) {
			$hem = 148.0;
		}
		$out[] = '<path class="' . self::f( $tc ) . '" d="' . self::torso_path( $rig, $hem, 'sweater' === $top || 'hoodie' === $top ? 1.5 : 0.0 ) . '"/>';
		$out[] = self::top_details( $top, $tc, $rig, $hem );

		if ( 'tie' === $spec->get( 'extra' ) && in_array( $top, [ 'shirt', 'polo', 'blouse' ], true ) ) {
			$out[] = self::extra( $spec );
		}
		if ( 'none' !== $outer ) {
			$out[] = self::outer( $spec, $rig );
		}
		if ( in_array( $spec->get( 'extra' ), [ 'lanyard', 'scarf' ], true ) || ( 'tie' === $spec->get( 'extra' ) && ! in_array( $top, [ 'shirt', 'polo', 'blouse' ], true ) ) ) {
			$out[] = self::extra( $spec );
		}
		unset( $cx );

		return implode( "\n\t\t", array_filter( $out ) );
	}

	/**
	 * Necklines, collars, pockets and other details of a top.
	 *
	 * @param string               $top Top.
	 * @param string               $tc  Top colour token.
	 * @param array<string, mixed> $rig Rig.
	 * @param float                $hem Hem y.
	 * @return string
	 */
	private static function top_details( string $top, string $tc, array $rig, float $hem ): string {
		$cx   = self::CX;
		$dark = self::shade( $tc, 'dark' );
		$out  = [];

		switch ( $top ) {
			case 'shirt':
			case 'polo':
				$collar = 'background' === $tc ? 'background' : self::shade( $tc, 'light' );
				$out[]  = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx ) . ' 74 L' . G::n( $cx - 9 ) . ' 60 L' . G::n( $cx - 4 ) . ' 57 L' . G::n( $cx ) . ' 63 L' . G::n( $cx + 4 ) . ' 57 L' . G::n( $cx + 9 ) . ' 60 Z" opacity="0.55"/>';
				$out[]  = '<path class="' . self::f( 'background' === $tc ? 'neutral-light' : $collar ) . '" d="M' . G::n( $cx ) . ' 70 L' . G::n( $cx - 10 ) . ' 59 L' . G::n( $cx - 3 ) . ' 57 L' . G::n( $cx ) . ' 62 L' . G::n( $cx + 3 ) . ' 57 L' . G::n( $cx + 10 ) . ' 59 Z"/>';
				$out[]  = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx ) . ' 70 V' . G::n( $hem - 4 ) . '" fill="none" stroke-width="1" opacity="0.6"/>';
				if ( 'polo' === $top ) {
					$out[] = '<circle class="' . self::f( $dark ) . '" cx="' . G::n( $cx ) . '" cy="76" r="1.2"/><circle class="' . self::f( $dark ) . '" cx="' . G::n( $cx ) . '" cy="84" r="1.2"/>';
				}
				break;
			case 'blouse':
				$out[] = '<path class="slot-skin-dark" d="M' . G::n( $cx - 8 ) . ' 58.5 L' . G::n( $cx ) . ' 76 L' . G::n( $cx + 8 ) . ' 58.5 Q' . G::n( $cx ) . ' 63 ' . G::n( $cx - 8 ) . ' 58.5 Z"/>';
				break;
			case 'hoodie':
				$out[] = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx - 15 ) . ' 62 Q' . G::n( $cx - 14 ) . ' 54 ' . G::n( $cx ) . ' 53 Q' . G::n( $cx + 14 ) . ' 54 ' . G::n( $cx + 15 ) . ' 62 Q' . G::n( $cx ) . ' 82 ' . G::n( $cx - 15 ) . ' 62 Z"/>';
				$out[] = '<path class="slot-skin-dark" d="M' . G::n( $cx - 8 ) . ' 58 Q' . G::n( $cx ) . ' 70 ' . G::n( $cx + 8 ) . ' 58 Z"/>';
				$out[] = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx - 15 ) . ' 118 H' . G::n( $cx + 15 ) . ' L' . G::n( $cx + 18 ) . ' 136 H' . G::n( $cx - 18 ) . ' Z"/>';
				$out[] = '<path class="slot-stroke-background" d="M' . G::n( $cx - 4 ) . ' 70 V96 M' . G::n( $cx + 4 ) . ' 70 V96" fill="none" stroke-width="1.6" stroke-linecap="round"/>';
				break;
			case 'sweater':
				$out[] = '<path class="slot-skin-dark" d="M' . G::n( $cx - 8 ) . ' 58.5 Q' . G::n( $cx ) . ' 68 ' . G::n( $cx + 8 ) . ' 58.5 Z"/>';
				$out[] = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx - 8 ) . ' 58.5 Q' . G::n( $cx ) . ' 68 ' . G::n( $cx + 8 ) . ' 58.5" fill="none" stroke-width="2.4" stroke-linecap="round"/><rect class="' . self::f( $dark ) . '" x="' . G::n( $cx - $rig['hi'] - 1 ) . '" y="' . G::n( $hem - 6 ) . '" width="' . G::n( 2 * $rig['hi'] + 2 ) . '" height="6" rx="2"/>';
				break;
			case 'dress':
				$out[] = '<path class="slot-skin-dark" d="M' . G::n( $cx - 8 ) . ' 58.5 Q' . G::n( $cx ) . ' 70 ' . G::n( $cx + 8 ) . ' 58.5 Z"/>';
				$out[] = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx - $rig['wa'] ) . ' 118 Q' . G::n( $cx ) . ' 124 ' . G::n( $cx + $rig['wa'] ) . ' 118 V123 Q' . G::n( $cx ) . ' 129 ' . G::n( $cx - $rig['wa'] ) . ' 123 Z"/>';
				break;
			default:
				// T-shirt and long sleeve: a round neckline.
				$out[] = '<path class="slot-skin-dark" d="M' . G::n( $cx - 8 ) . ' 58.5 Q' . G::n( $cx ) . ' 69 ' . G::n( $cx + 8 ) . ' 58.5 Z"/>';
		}//end switch

		return implode( "\n\t\t", $out );
	}

	/**
	 * The outer layer: jacket, blazer, cardigan, waistcoat, high-vis vest, apron or coat.
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @return string
	 */
	private static function outer( CharacterSpec $spec, array $rig ): string {
		$style = $spec->get( 'outer' );
		$oc    = $spec->get( 'outer_color' );
		$dark  = self::shade( $oc, 'dark' );
		$cx    = self::CX;
		$wa    = (float) $rig['wa'];
		$hi    = (float) $rig['hi'];
		$out   = [];

		if ( 'apron' === $style ) {
			$out[] = '<path class="' . self::f( $oc ) . '" d="' . G::closed( [ [ $cx - 11, 78 ], [ $cx + 11, 78 ], [ $cx + $wa + 2, 118 ], [ $cx + $hi + 5, 200 ], [ $cx - $hi - 5, 200 ], [ $cx - $wa - 2, 118 ] ] ) . '"/>';
			$out[] = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx - 9 ) . ' 78 L' . G::n( $cx - 12 ) . ' 60 M' . G::n( $cx + 9 ) . ' 78 L' . G::n( $cx + 12 ) . ' 60" fill="none" stroke-width="2" stroke-linecap="round"/>';
			$out[] = '<rect class="' . self::f( $dark ) . '" x="' . G::n( $cx - 9 ) . '" y="150" width="18" height="14" rx="2"/>';
			$out[] = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx - $wa - 2 ) . ' 118 Q' . G::n( $cx ) . ' 124 ' . G::n( $cx + $wa + 2 ) . ' 118" fill="none" stroke-width="1.6"/>';

			return implode( "\n\t\t", $out );
		}

		if ( 'hivis' === $style || 'vest' === $style ) {
			$hem   = 'vest' === $style ? 138.0 : 142.0;
			$out[] = '<path class="' . self::f( $oc ) . '" d="' . self::torso_path( $rig, $hem, 0.0, 5.0 ) . '"/>';
			$out[] = '<path class="slot-skin-dark" d="M' . G::n( $cx - 8 ) . ' 58.5 L' . G::n( $cx ) . ' 74 L' . G::n( $cx + 8 ) . ' 58.5 Q' . G::n( $cx ) . ' 63 ' . G::n( $cx - 8 ) . ' 58.5 Z" opacity="0"/>';
			if ( 'hivis' === $style ) {
				$out[] = '<path class="slot-background" d="M' . G::n( $cx - $wa - 4 ) . ' 100 H' . G::n( $cx + $wa + 4 ) . ' V106 H' . G::n( $cx - $wa - 4 ) . ' Z M' . G::n( $cx - $hi ) . ' 126 H' . G::n( $cx + $hi ) . ' V132 H' . G::n( $cx - $hi ) . ' Z" opacity="0.9"/>';
			}
			$out[] = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx - 7 ) . ' 60 L' . G::n( $cx ) . ' 80 L' . G::n( $cx + 7 ) . ' 60 L' . G::n( $cx + 3 ) . ' 60 L' . G::n( $cx ) . ' 68 L' . G::n( $cx - 3 ) . ' 60 Z"/>';
			$out[] = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx ) . ' 80 V' . G::n( $hem - 3 ) . '" fill="none" stroke-width="1.2" opacity="0.7"/>';

			return implode( "\n\t\t", $out );
		}

		// Jackets and coats: two front panels with a gap at the front, revealing the top beneath.
		$table = [
			'jacket'   => [
				'hem'   => 148.0,
				'gap'   => 6.0,
				'flare' => 1.5,
			],
			'blazer'   => [
				'hem'   => 158.0,
				'gap'   => 4.0,
				'flare' => 1.0,
			],
			'cardigan' => [
				'hem'   => 156.0,
				'gap'   => 5.0,
				'flare' => 2.0,
			],
			'coat'     => [
				'hem'   => 232.0,
				'gap'   => 4.0,
				'flare' => 9.0,
			],
		][ $style ];

		$hem   = $table['hem'];
		$gap   = $table['gap'];
		$flare = $table['flare'];
		$full  = self::torso_path( $rig, $hem, $flare );
		$out[] = '<path class="' . self::f( $oc ) . '" d="' . $full . '"/>';

		// The opening: top colour shows, narrowing to the hem.
		$open  = 'coat' === $style ? 9.0 : 7.5;
		$tc    = $spec->get( 'top_color' );
		$out[] = '<path class="' . self::f( 'dress' === $spec->get( 'top' ) ? $tc : $tc ) . '" d="M' . G::n( $cx - $open ) . ' 60 L' . G::n( $cx + $open ) . ' 60 L' . G::n( $cx + $gap ) . ' ' . G::n( $hem ) . ' L' . G::n( $cx - $gap ) . ' ' . G::n( $hem ) . ' Z"/>';
		$out[] = self::top_details( $spec->get( 'top' ), $tc, $rig, $hem );

		// Lapels or collar, and the front edge.
		if ( in_array( $style, [ 'jacket', 'blazer', 'coat' ], true ) ) {
			$out[] = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx - 8 ) . ' 58 L' . G::n( $cx - $open ) . ' 60 L' . G::n( $cx - $gap - 1 ) . ' ' . G::n( 'jacket' === $style ? 96 : 108 ) . ' L' . G::n( $cx - 15 ) . ' 78 L' . G::n( $cx - 20 ) . ' 64 Z"/>';
			$out[] = '<path class="' . self::f( $dark ) . '" d="M' . G::n( $cx + 8 ) . ' 58 L' . G::n( $cx + $open ) . ' 60 L' . G::n( $cx + $gap + 1 ) . ' ' . G::n( 'jacket' === $style ? 96 : 108 ) . ' L' . G::n( $cx + 15 ) . ' 78 L' . G::n( $cx + 20 ) . ' 64 Z"/>';
		} else {
			$out[] = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx - $open ) . ' 60 L' . G::n( $cx - $gap ) . ' ' . G::n( $hem ) . ' M' . G::n( $cx + $open ) . ' 60 L' . G::n( $cx + $gap ) . ' ' . G::n( $hem ) . '" fill="none" stroke-width="2.4"/>';
		}
		$out[] = '<path class="' . self::s( $dark ) . '" d="M' . G::n( $cx - $wa - 1 ) . ' 132 H' . G::n( $cx - $wa + 9 ) . ' M' . G::n( $cx + $wa + 1 ) . ' 132 H' . G::n( $cx + $wa - 9 ) . '" fill="none" stroke-width="1.6" stroke-linecap="round" opacity="0.7"/>';

		return implode( "\n\t\t", $out );
	}

	/**
	 * Tie, lanyard with badge, or scarf.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @return string
	 */
	private static function extra( CharacterSpec $spec ): string {
		$cx = self::CX;
		$c  = $spec->get( 'extra_color' );
		$d  = self::shade( $c, 'dark' );

		return match ( $spec->get( 'extra' ) ) {
			'tie'     => '<path class="' . self::f( $c ) . '" d="M' . G::n( $cx - 3 ) . ' 61 L' . G::n( $cx + 3 ) . ' 61 L' . G::n( $cx + 2.5 ) . ' 67 L' . G::n( $cx + 4.5 ) . ' 100 L' . G::n( $cx ) . ' 108 L' . G::n( $cx - 4.5 ) . ' 100 L' . G::n( $cx - 2.5 ) . ' 67 Z"/>',
			'lanyard' => '<path class="' . self::s( $d ) . '" d="M' . G::n( $cx - 8 ) . ' 59 L' . G::n( $cx - 1 ) . ' 100 M' . G::n( $cx + 8 ) . ' 59 L' . G::n( $cx + 1 ) . ' 100" fill="none" stroke-width="2"/><rect class="slot-background" x="' . G::n( $cx - 6 ) . '" y="98" width="12" height="16" rx="2"/><rect class="' . self::f( $c ) . '" x="' . G::n( $cx - 4 ) . '" y="101" width="8" height="5" rx="1"/>',
			'scarf'   => '<path class="' . self::f( $c ) . '" d="' . G::closed( [ [ $cx - 13, 60 ], [ $cx, 68 ], [ $cx + 13, 60 ], [ $cx + 12, 67 ], [ $cx, 75 ], [ $cx - 12, 67 ] ] ) . '"/><path class="' . self::f( $d ) . '" d="M' . G::n( $cx + 4 ) . ' 72 L' . G::n( $cx + 12 ) . ' 74 L' . G::n( $cx + 14 ) . ' 104 L' . G::n( $cx + 6 ) . ' 102 Z"/>',
			default   => '',
		};
	}

	// ---------------------------------------------------------------- arms and carried things.

	/**
	 * Arms with sleeves and hands.
	 *
	 * @param CharacterSpec        $spec  Spec.
	 * @param array<string, mixed> $rig   Rig.
	 * @param array<string>        $sides Which arms to draw.
	 * @return string
	 */
	private static function arms( CharacterSpec $spec, array $rig, array $sides = [ 'l', 'r' ] ): string {
		$top     = $spec->get( 'top' );
		$outer   = $spec->get( 'outer' );
		$sleeved = in_array( $outer, [ 'jacket', 'blazer', 'cardigan', 'coat' ], true );
		$colour  = $sleeved ? $spec->get( 'outer_color' ) : $spec->get( 'top_color' );
		$long    = $sleeved || in_array( $top, [ 'longsleeve', 'shirt', 'sweater', 'hoodie' ], true );
		$over    = (bool) $rig['pose']['over'];
		$tone    = $over ? self::shade( $colour, 'dark' ) : $colour;
		$out     = [];

		foreach ( $sides as $side ) {
			$s = $rig['shoulder'][ $side ];
			$e = $rig['elbow'][ $side ];
			$w = $rig['wrist'][ $side ];

			if ( $long ) {
				$out[] = '<path class="' . self::f( $tone ) . '" d="' . G::limb( $s, $e, 13, 11 ) . ' ' . G::limb( $e, $w, 11, 9 ) . '"/><circle class="' . self::f( $tone ) . '" cx="' . G::n( $e[0] ) . '" cy="' . G::n( $e[1] ) . '" r="5.5"/>';
			} else {
				$out[] = '<path class="slot-skin" d="' . G::limb( $s, $e, 11, 9.5 ) . ' ' . G::limb( $e, $w, 9.5, 8 ) . '"/><circle class="slot-skin" cx="' . G::n( $e[0] ) . '" cy="' . G::n( $e[1] ) . '" r="4.7"/>';
				$cap   = [ $s[0] + ( $e[0] - $s[0] ) * 0.55, $s[1] + ( $e[1] - $s[1] ) * 0.55 ];
				$out[] = '<path class="' . self::f( $tone ) . '" d="' . G::limb( $s, $cap, 14, 13 ) . '"/>';
			}
			$out[] = '<circle class="slot-skin" cx="' . G::n( $w[0] ) . '" cy="' . G::n( $w[1] ) . '" r="6"/>';
		}

		return implode( "\n\t\t", $out );
	}

	/**
	 * Things in the hands: a phone, a folder, a tote bag or briefcase.
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @return string
	 */
	private static function carried( CharacterSpec $spec, array $rig ): string {
		$out  = [];
		$item = $rig['pose']['item'] ?? null;

		if ( null !== $item ) {
			[ $kind, $side ] = $item;
			$w               = 'both' === $side
				? [ ( $rig['wrist']['l'][0] + $rig['wrist']['r'][0] ) / 2, ( $rig['wrist']['l'][1] + $rig['wrist']['r'][1] ) / 2 ]
				: $rig['wrist'][ $side ];
			$hand            = static fn( array $p ): string => '<circle class="slot-skin" cx="' . G::n( $p[0] ) . '" cy="' . G::n( $p[1] ) . '" r="5.6"/>';
			$c               = $spec->get( 'bag_color' );
			$d               = self::shade( $c, 'dark' );
			if ( 'tablet' === $kind ) {
				$rot   = ' transform="rotate(-8 ' . G::n( $w[0] ) . ' ' . G::n( $w[1] ) . ')"';
				$out[] = '<rect class="slot-neutral-dark" x="' . G::n( $w[0] - 13 ) . '" y="' . G::n( $w[1] - 24 ) . '" width="26" height="32" rx="3"' . $rot . '/><rect class="slot-background" x="' . G::n( $w[0] - 10.5 ) . '" y="' . G::n( $w[1] - 21.5 ) . '" width="21" height="27" rx="1.5"' . $rot . '/><rect class="' . self::f( $c ) . '" x="' . G::n( $w[0] - 8 ) . '" y="' . G::n( $w[1] - 18 ) . '" width="16" height="4" rx="1"' . $rot . '/>' . $hand( $w );
			} elseif ( 'cup' === $kind ) {
				$out[] = '<path class="' . self::f( $c ) . '" d="M' . G::n( $w[0] - 6.5 ) . ' ' . G::n( $w[1] - 11 ) . ' H' . G::n( $w[0] + 6.5 ) . ' L' . G::n( $w[0] + 5 ) . ' ' . G::n( $w[1] + 9 ) . ' H' . G::n( $w[0] - 5 ) . ' Z"/><rect class="' . self::f( $d ) . '" x="' . G::n( $w[0] - 7.5 ) . '" y="' . G::n( $w[1] - 14 ) . '" width="15" height="4" rx="1.5"/>' . $hand( [ $w[0] + 4, $w[1] ] );
			} elseif ( 'book' === $kind ) {
				$out[] = '<rect class="' . self::f( $c ) . '" x="' . G::n( $w[0] - 17 ) . '" y="' . G::n( $w[1] - 15 ) . '" width="34" height="24" rx="2"/><rect class="slot-background" x="' . G::n( $w[0] - 15 ) . '" y="' . G::n( $w[1] - 13 ) . '" width="14" height="20" rx="1"/><rect class="slot-background" x="' . G::n( $w[0] + 1 ) . '" y="' . G::n( $w[1] - 13 ) . '" width="14" height="20" rx="1"/>' . $hand( [ $w[0] - 15, $w[1] + 6 ] ) . $hand( [ $w[0] + 15, $w[1] + 6 ] );
			} elseif ( 'box' === $kind ) {
				$out[] = '<rect class="' . self::f( $c ) . '" x="' . G::n( $w[0] - 22 ) . '" y="' . G::n( $w[1] - 14 ) . '" width="44" height="32" rx="2"/><rect class="' . self::f( $d ) . '" x="' . G::n( $w[0] - 22 ) . '" y="' . G::n( $w[1] - 14 ) . '" width="44" height="7" rx="2"/><rect class="slot-background" x="' . G::n( $w[0] - 5 ) . '" y="' . G::n( $w[1] - 14 ) . '" width="10" height="7"/>' . $hand( [ $w[0] - 22, $w[1] + 6 ] ) . $hand( [ $w[0] + 22, $w[1] + 6 ] );
			}
			if ( 'phone' === $kind ) {
				$out[] = '<rect class="slot-neutral-dark" x="' . G::n( $w[0] - 4 ) . '" y="' . G::n( $w[1] - 16 ) . '" width="8" height="15" rx="1.6" transform="rotate(' . ( 'r' === $side ? 8 : -8 ) . ' ' . G::n( $w[0] ) . ' ' . G::n( $w[1] ) . ')"/><circle class="slot-skin" cx="' . G::n( $w[0] ) . '" cy="' . G::n( $w[1] ) . '" r="5.4"/>';
			} elseif ( 'folder' === $kind ) {
				$out[] = '<rect class="' . self::f( $spec->get( 'bag_color' ) ) . '" x="' . G::n( $w[0] - 12 ) . '" y="' . G::n( $w[1] - 22 ) . '" width="24" height="30" rx="2" transform="rotate(-8 ' . G::n( $w[0] ) . ' ' . G::n( $w[1] ) . ')"/><rect class="slot-background" x="' . G::n( $w[0] - 9 ) . '" y="' . G::n( $w[1] - 19 ) . '" width="18" height="24" rx="1" transform="rotate(-8 ' . G::n( $w[0] ) . ' ' . G::n( $w[1] ) . ')" opacity="0.9"/><circle class="slot-skin" cx="' . G::n( $w[0] ) . '" cy="' . G::n( $w[1] ) . '" r="5.6"/>';
			}
		}//end if

		$bag = $spec->get( 'bag' );
		if ( in_array( $bag, [ 'tote', 'briefcase' ], true ) && $rig['carry'] ) {
			// Hangs from the lowest relaxed hand.
			$side = $rig['wrist']['l'][1] >= $rig['wrist']['r'][1] ? 'l' : 'r';
			$w    = $rig['wrist'][ $side ];
			$c    = $spec->get( 'bag_color' );
			$d    = self::shade( $c, 'dark' );
			if ( 'tote' === $bag ) {
				$out[] = '<path class="' . self::s( $d ) . '" d="M' . G::n( $w[0] - 8 ) . ' ' . G::n( $w[1] + 14 ) . ' Q' . G::n( $w[0] ) . ' ' . G::n( $w[1] - 8 ) . ' ' . G::n( $w[0] + 8 ) . ' ' . G::n( $w[1] + 14 ) . '" fill="none" stroke-width="2"/><rect class="' . self::f( $c ) . '" x="' . G::n( $w[0] - 15 ) . '" y="' . G::n( $w[1] + 12 ) . '" width="30" height="32" rx="2"/><path class="' . self::f( $d ) . '" d="M' . G::n( $w[0] - 15 ) . ' ' . G::n( $w[1] + 12 ) . ' h30 v5 h-30 Z"/><circle class="slot-skin" cx="' . G::n( $w[0] ) . '" cy="' . G::n( $w[1] ) . '" r="5.6"/>';
			} else {
				$out[] = '<path class="' . self::s( $d ) . '" d="M' . G::n( $w[0] - 6 ) . ' ' . G::n( $w[1] + 18 ) . ' V' . G::n( $w[1] + 10 ) . ' H' . G::n( $w[0] + 6 ) . ' V' . G::n( $w[1] + 18 ) . '" fill="none" stroke-width="2"/><rect class="' . self::f( $c ) . '" x="' . G::n( $w[0] - 17 ) . '" y="' . G::n( $w[1] + 16 ) . '" width="34" height="24" rx="3"/><rect class="' . self::f( $d ) . '" x="' . G::n( $w[0] - 17 ) . '" y="' . G::n( $w[1] + 16 ) . '" width="34" height="6" rx="3"/><circle class="slot-skin" cx="' . G::n( $w[0] ) . '" cy="' . G::n( $w[1] ) . '" r="5.6"/>';
			}
		}

		return implode( "\n\t\t", $out );
	}

	/**
	 * Backpack (behind the body, with straps over the front).
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @return string
	 */
	private static function backpack( CharacterSpec $spec, array $rig ): string {
		$c  = $spec->get( 'bag_color' );
		$cx = self::CX;
		$sh = (float) $rig['sh'];

		return '<rect class="' . self::f( self::shade( $c, 'dark' ) ) . '" x="' . G::n( $cx - $sh - 2 ) . '" y="66" width="' . G::n( 2 * $sh + 4 ) . '" height="62" rx="12"/>';
	}

	/**
	 * Bag parts that lie over the torso: backpack straps, or a shoulder bag with its strap.
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @return string
	 */
	private static function bag_over_torso( CharacterSpec $spec, array $rig ): string {
		$bag = $spec->get( 'bag' );
		$c   = $spec->get( 'bag_color' );
		$d   = self::shade( $c, 'dark' );
		$cx  = self::CX;
		$sh  = (float) $rig['sh'];
		$hi  = (float) $rig['hi'];

		if ( 'backpack' === $bag ) {
			return '<path class="' . self::s( $c ) . '" d="M' . G::n( $cx - $sh + 9 ) . ' 60 Q' . G::n( $cx - $sh + 5 ) . ' 92 ' . G::n( $cx - $sh + 9 ) . ' 118 M' . G::n( $cx + $sh - 9 ) . ' 60 Q' . G::n( $cx + $sh - 5 ) . ' 92 ' . G::n( $cx + $sh - 9 ) . ' 118" fill="none" stroke-width="5" stroke-linecap="round"/>';
		}
		if ( 'messenger' === $bag ) {
			return '<path class="' . self::s( $d ) . '" d="M' . G::n( $cx + $sh - 8 ) . ' 60 L' . G::n( $cx - $hi - 4 ) . ' 140" fill="none" stroke-width="3.2" stroke-linecap="round"/><rect class="' . self::f( $c ) . '" x="' . G::n( $cx - $hi - 20 ) . '" y="130" width="26" height="24" rx="3"/><path class="' . self::f( $d ) . '" d="M' . G::n( $cx - $hi - 20 ) . ' 130 h26 v9 q-13 6 -26 0 Z"/>';
		}

		return '';
	}

	// ---------------------------------------------------------------- head.

	/**
	 * Head: ears, face shape, features, hair, headwear, glasses.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @param bool          $back Seen from behind (face down): hair covers the face.
	 * @return string
	 */
	private static function head( CharacterSpec $spec, bool $back = false ): string {
		$out   = [];
		$out[] = '<ellipse class="slot-skin" cx="65.5" cy="28" rx="2.8" ry="4.2"/><ellipse class="slot-skin" cx="94.5" cy="28" rx="2.8" ry="4.2"/>';
		$out[] = '<ellipse class="slot-skin" cx="80" cy="26" rx="14.5" ry="18"/>';
		if ( $back ) {
			$out[] = '<ellipse class="' . ( 'bald' === $spec->get( 'hair' ) ? 'slot-skin' : 'slot-hair' ) . '" cx="80" cy="25" rx="14.8" ry="17.6"/>';
		} elseif ( 'simple' === $spec->get( 'face' ) ) {
			$out[] = '<ellipse class="slot-neutral-dark" cx="74.4" cy="27" rx="1.3" ry="1.7"/><ellipse class="slot-neutral-dark" cx="85.6" cy="27" rx="1.3" ry="1.7"/><path class="slot-stroke-skin-dark" d="M80 28.5 Q82 32 79.4 33.2" fill="none" stroke-width="1.2" stroke-linecap="round"/><path class="slot-stroke-skin-dark" d="M76.6 37 Q80 39.6 83.4 37" fill="none" stroke-width="1.4" stroke-linecap="round"/>';
		}
		$out[] = $back ? '' : self::facial_hair( $spec->get( 'facial_hair' ) );
		$out[] = self::hair( $spec, true );
		$out[] = $back ? '' : self::glasses( $spec->get( 'glasses' ) );
		$out[] = self::headwear( $spec->get( 'headwear' ), $spec->get( 'headwear_color' ) );

		return implode( "\n\t\t", array_filter( $out ) );
	}

	/**
	 * Hair, behind the head (back) or over it (front).
	 *
	 * @param CharacterSpec $spec  Spec.
	 * @param bool          $front Front layer.
	 * @return string
	 */
	private static function hair( CharacterSpec $spec, bool $front ): string {
		$style = $spec->get( 'hair' );
		$cap   = '<path class="slot-hair" d="' . G::closed( [ [ 65, 27 ], [ 64.5, 17 ], [ 69, 10 ], [ 77, 7 ], [ 86, 7.5 ], [ 93, 11 ], [ 96, 19 ], [ 95, 28 ], [ 92.5, 21 ], [ 87, 16.5 ], [ 79, 15 ], [ 71, 17.5 ], [ 67.5, 23 ] ] ) . '"/>';
		$parts = [
			'short'    => $cap,
			'quiff'    => '<path class="slot-hair" d="' . G::closed( [ [ 65, 27 ], [ 63.5, 16 ], [ 67, 8 ], [ 75, 3.5 ], [ 86, 3 ], [ 94, 6.5 ], [ 97, 15 ], [ 95, 28 ], [ 92, 20 ], [ 86, 14.5 ], [ 78, 13.5 ], [ 70, 16.5 ], [ 67, 22 ] ] ) . '"/>',
			'buzz'     => '<path class="slot-hair" d="' . G::closed( [ [ 65.5, 25 ], [ 65.5, 17 ], [ 70, 11 ], [ 80, 8.5 ], [ 90, 11 ], [ 94.5, 17 ], [ 94.5, 25 ], [ 91, 17.5 ], [ 80, 14 ], [ 69, 17.5 ] ] ) . '"/>',
			'curly'    => '<circle class="slot-hair" cx="66" cy="19" r="8"/><circle class="slot-hair" cx="72" cy="11" r="8.5"/><circle class="slot-hair" cx="81" cy="8" r="9"/><circle class="slot-hair" cx="90" cy="11" r="8.5"/><circle class="slot-hair" cx="95" cy="19" r="8"/><path class="slot-hair" d="' . G::closed( [ [ 66, 22 ], [ 80, 15 ], [ 94, 22 ], [ 80, 12 ] ] ) . '"/>',
			'afro'     => '<circle class="slot-hair" cx="63" cy="20" r="11"/><circle class="slot-hair" cx="70" cy="9" r="12"/><circle class="slot-hair" cx="82" cy="5" r="13"/><circle class="slot-hair" cx="93" cy="10" r="12"/><circle class="slot-hair" cx="98" cy="21" r="11"/><circle class="slot-hair" cx="80" cy="14" r="14"/>',
			'long'     => $cap,
			'wavy'     => $cap,
			'bob'      => $cap,
			'ponytail' => $cap,
			'bun'      => $cap . '<circle class="slot-hair" cx="80" cy="4.5" r="7"/>',
			'bald'     => '',
		];
		$back  = [
			'long'     => '<path class="slot-hair" d="' . G::closed( [ [ 65, 18 ], [ 68, 9 ], [ 78, 5.5 ], [ 89, 7 ], [ 95, 15 ], [ 96.5, 36 ], [ 98, 62 ], [ 92, 70 ], [ 88, 56 ], [ 72, 56 ], [ 68, 70 ], [ 62, 62 ], [ 63.5, 36 ] ] ) . '"/>',
			'wavy'     => '<path class="slot-hair" d="' . G::closed( [ [ 65, 18 ], [ 68, 9 ], [ 78, 5.5 ], [ 89, 7 ], [ 95, 15 ], [ 98, 32 ], [ 95, 46 ], [ 99, 60 ], [ 92, 72 ], [ 87, 58 ], [ 73, 58 ], [ 68, 72 ], [ 61, 60 ], [ 65, 46 ], [ 62, 32 ] ] ) . '"/>',
			'bob'      => '<path class="slot-hair" d="' . G::closed( [ [ 64, 18 ], [ 68, 9 ], [ 78, 5.5 ], [ 89, 7 ], [ 96, 15 ], [ 97, 32 ], [ 96, 46 ], [ 90, 48 ], [ 70, 48 ], [ 64, 46 ], [ 63, 32 ] ] ) . '"/>',
			'ponytail' => '<path class="slot-hair" d="' . G::closed( [ [ 93, 14 ], [ 101, 16 ], [ 106, 30 ], [ 104, 50 ], [ 98, 62 ], [ 97, 48 ], [ 96, 32 ] ] ) . '"/>',
		];

		return $front ? ( $parts[ $style ] ?? '' ) : ( $back[ $style ] ?? '' );
	}

	/**
	 * Facial hair.
	 *
	 * @param string $style Style.
	 * @return string
	 */
	private static function facial_hair( string $style ): string {
		return match ( $style ) {
			'stubble'   => '<path class="slot-hair" opacity="0.32" d="' . G::closed( [ [ 66, 30 ], [ 68, 38 ], [ 74, 43.5 ], [ 80, 44.5 ], [ 86, 43.5 ], [ 92, 38 ], [ 94, 30 ], [ 90, 36 ], [ 80, 38 ], [ 70, 36 ] ] ) . '"/>',
			'moustache' => '<path class="slot-hair" d="' . G::closed( [ [ 72, 34.5 ], [ 76, 32.5 ], [ 80, 33.5 ], [ 84, 32.5 ], [ 88, 34.5 ], [ 84, 36.5 ], [ 80, 35 ], [ 76, 36.5 ] ] ) . '"/>',
			'beard'     => '<path class="slot-hair" d="' . G::closed( [ [ 66, 28 ], [ 67, 38 ], [ 72, 45 ], [ 80, 48 ], [ 88, 45 ], [ 93, 38 ], [ 94, 28 ], [ 90, 35 ], [ 84, 34.5 ], [ 80, 36 ], [ 76, 34.5 ], [ 70, 35 ] ] ) . '"/>',
			default     => '',
		};
	}

	/**
	 * Glasses.
	 *
	 * @param string $style Style.
	 * @return string
	 */
	private static function glasses( string $style ): string {
		return match ( $style ) {
			'round'  => '<g fill="none" class="slot-stroke-neutral-dark" stroke-width="1.6"><circle cx="73.5" cy="27" r="5.4"/><circle cx="86.5" cy="27" r="5.4"/><path d="M78.9 26.6 H81.1"/></g>',
			'square' => '<g fill="none" class="slot-stroke-neutral-dark" stroke-width="1.6"><rect x="67.5" y="22.5" width="11.5" height="9" rx="1.8"/><rect x="81" y="22.5" width="11.5" height="9" rx="1.8"/><path d="M79 26 H81"/></g>',
			'sun'    => '<path class="slot-neutral-dark" d="' . G::closed( [ [ 67, 23 ], [ 93, 23 ], [ 92, 30 ], [ 87, 32.5 ], [ 82, 30 ], [ 80, 27 ], [ 78, 30 ], [ 73, 32.5 ], [ 68, 30 ] ] ) . '"/>',
			default  => '',
		};
	}

	/**
	 * Headwear.
	 *
	 * @param string $style  Style.
	 * @param string $colour Colour token.
	 * @return string
	 */
	private static function headwear( string $style, string $colour ): string {
		$c = self::f( $colour );
		$d = self::f( self::shade( $colour, 'dark' ) );

		return match ( $style ) {
			'cap'     => '<path class="' . $c . '" d="' . G::closed( [ [ 64.5, 21 ], [ 64.5, 13 ], [ 69, 7 ], [ 80, 3.5 ], [ 91, 7 ], [ 95.5, 13 ], [ 95.5, 21 ], [ 80, 18 ] ] ) . '"/><path class="' . $d . '" d="M63.5 20.5 Q80 27 96.5 20.5 Q97 25 89 25.5 Q80 26 71 25.5 Q63 25 63.5 20.5 Z"/>',
			'bucket'  => '<path class="' . $c . '" d="' . G::closed( [ [ 67, 17 ], [ 68.5, 8 ], [ 72, 3.5 ], [ 88, 3.5 ], [ 91.5, 8 ], [ 93, 17 ] ] ) . '"/><ellipse class="' . $d . '" cx="80" cy="17.5" rx="22" ry="5"/><path class="' . $c . '" d="M67 17 Q80 21.5 93 17 Q80 15 67 17 Z"/>',
			'beanie'  => '<path class="' . $c . '" d="' . G::closed( [ [ 64.5, 19 ], [ 65.5, 9 ], [ 72, 3 ], [ 88, 3 ], [ 94.5, 9 ], [ 95.5, 19 ] ] ) . '"/><rect class="' . $d . '" x="63.5" y="15" width="33" height="7" rx="3"/><circle class="' . $d . '" cx="80" cy="2.5" r="3.5"/>',
			'flatcap' => '<path class="' . $c . '" d="' . G::closed( [ [ 64, 20 ], [ 66, 11 ], [ 74, 6.5 ], [ 86, 6.5 ], [ 94, 11 ], [ 96, 20 ], [ 80, 17 ] ] ) . '"/><path class="' . $d . '" d="M64 20 Q80 26 96 20 Q80 22 64 20 Z"/>',
			'sunhat'  => '<ellipse class="' . $d . '" cx="80" cy="15.5" rx="31" ry="7"/><path class="' . $c . '" d="' . G::closed( [ [ 67, 15 ], [ 69, 6 ], [ 74, 2.5 ], [ 86, 2.5 ], [ 91, 6 ], [ 93, 15 ] ] ) . '"/><path class="' . $d . '" d="M67 12.5 Q80 15.5 93 12.5 V15 Q80 18 67 15 Z"/>',
			'headset' => '<path class="slot-stroke-neutral-dark" d="M64 26 C63 6 97 6 96 26" fill="none" stroke-width="2.6" stroke-linecap="round"/><rect class="slot-neutral-dark" x="60.5" y="22" width="6" height="12" rx="3"/><rect class="slot-neutral-dark" x="93.5" y="22" width="6" height="12" rx="3"/><path class="slot-stroke-neutral-dark" d="M97 32 Q97 42 86 41" fill="none" stroke-width="1.6" stroke-linecap="round"/><circle class="' . $c . '" cx="85" cy="41" r="2.4"/>',
			default   => '',
		};
	}

	// ---------------------------------------------------------------- anchors.

	/**
	 * Anchor markers.
	 *
	 * @param CharacterSpec        $spec Spec.
	 * @param array<string, mixed> $rig  Rig.
	 * @param int                  $dy   Upper-body shift.
	 * @return string
	 */
	private static function anchors( CharacterSpec $spec, array $rig, int $dy ): string {
		if ( $rig['sitting'] ) {
			$hold   = [ 118, 186 ];
			$ground = [ 104, 291 ];
		} else {
			$free   = (string) $rig['pose']['free'];
			$hold   = [ (float) $rig['wrist'][ $free ][0], (float) $rig['wrist'][ $free ][1] + $dy ];
			$ground = [ 80, 309 ];
		}
		unset( $spec );

		return '<circle id="anchor-hold" cx="' . G::n( $hold[0] ) . '" cy="' . G::n( $hold[1] ) . '" r="3"/>' . "\n\t" . '<circle id="anchor-ground" cx="' . G::n( $ground[0] ) . '" cy="' . G::n( $ground[1] ) . '" r="3"/>';
	}
	// ---------------------------------------------------------------- other stances and hand-made poses.

	/**
	 * The stance data that drives the drawing: the chosen stance, or a hand-made pose laid over it.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @return array<string, mixed>
	 */
	private static function stance_data( CharacterSpec $spec ): array {
		$st     = Stances::base( $spec->get( 'stance' ) );
		$custom = $spec->custom;
		if ( null === $custom ) {
			return $st;
		}

		$st['theta']  = $custom['theta'];
		$st['head']   = $custom['head'];
		$st['custom'] = true;
		unset( $st['dy'] );
		foreach ( [ 'l', 'r' ] as $side ) {
			$st['legs'][ $side ] = $custom['legs'][ $side ] + $st['legs'][ $side ];
		}
		// Whatever is lowest touches the floor; a body lying down rests on its side.
		$st['contact'] = abs( $custom['theta'] ) > 60 ? [ [ 'hipside', 28 ], [ 'ankle', 10 ], [ 'knee', 8 ] ] : [ [ 'ankle', 10.5 ], [ 'knee', 8 ], [ 'hip', 12 ] ];

		return $st;
	}

	/**
	 * Arm angles for the spec's pose, relative to the torso.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @return array<string, mixed>
	 */
	private static function arm_pose( CharacterSpec $spec ): array {
		$stance = $spec->get( 'stance' );
		$pose   = $spec->get( 'pose' );
		$st     = self::stance_data( $spec );
		$family = (string) $st['family'];
		$entry  = self::POSES[ $stance ][ $pose ]
			?? self::NEW_ARMS[ $pose ]
			?? self::POSES[ $family ][ $pose ]
			?? self::POSES['standing'][ $pose ]
			?? self::POSES['sitting'][ $pose ]
			?? self::POSES['standing']['relaxed'];

		$screen = ! empty( $entry['screen'] );
		if ( null !== $spec->custom ) {
			$entry['l'] = $spec->custom['arms']['l'];
			$entry['r'] = $spec->custom['arms']['r'];
			$screen     = true;
		}
		if ( $screen ) {
			$theta = (float) $st['theta'];
			foreach ( [ 'l', 'r' ] as $side ) {
				$sign           = 'r' === $side ? 1 : -1;
				$entry[ $side ] = [
					$sign * ( $entry[ $side ][0] + $theta ),
					$sign * ( $entry[ $side ][1] + $theta ),
				];
			}
			unset( $entry['screen'] );
		}

		return $entry;
	}

	/**
	 * A point of the upper body (drawn upright about the hips) after the body is turned and moved.
	 *
	 * @param array{0: float|int, 1: float|int} $p     Point in the upright drawing.
	 * @param float                             $theta Turn in degrees (clockwise).
	 * @param float                             $dx    Shift right.
	 * @param float                             $dy    Shift down.
	 * @return array{0: float, 1: float}
	 */
	private static function turned( array $p, float $theta, float $dx, float $dy ): array {
		$rad = deg2rad( $theta );
		$x   = (float) $p[0] - 80.0;
		$y   = (float) $p[1] - 152.0;

		return [
			80.0 + $dx + $x * cos( $rad ) - $y * sin( $rad ),
			152.0 + $dy + $x * sin( $rad ) + $y * cos( $rad ),
		];
	}

	/**
	 * Where everything of a posed character is: legs, hands, the ground and the canvas. A hand-made pose is
	 * fitted to its own canvas, or (while it is being edited) to a fixed one so the figure doesn't jump.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @param bool          $edit Use the fixed editing canvas for a hand-made pose.
	 * @return array<string, mixed>
	 */
	private static function layout( CharacterSpec $spec, bool $edit = false ): array {
		$st        = self::stance_data( $spec );
		$rig       = self::rig( $spec );
		$theta     = (float) $st['theta'];
		$dx        = (float) $st['dx'];
		$custom    = ! empty( $st['custom'] );
		[ $w, $h ] = $st['vb'];
		if ( $custom ) {
			$w  = self::EDIT_FRAME;
			$h  = self::EDIT_FRAME;
			$dx = self::EDIT_FRAME / 2 - 80.0;
		}

		$place = static function ( float $shift_x, float $dy ) use ( $st, $rig, $theta ): array {
			$legs = [];
			foreach ( [ 'l', 'r' ] as $side ) {
				$def           = $st['legs'][ $side ] + [
					4 => 74.0,
					5 => 72.0,
					6 => 0.0,
				];
				$hip           = self::turned( [ 'l' === $side ? 71.5 : 88.5, 152.0 ], $theta, $shift_x, $dy );
				$hip[1]       += (float) $def[6];
				$knee          = G::reach( $hip, (float) $def[4], (float) $def[0], 'r' );
				$ankle         = G::reach( $knee, (float) $def[5], (float) $def[1], 'r' );
				$legs[ $side ] = [
					'hip'   => $hip,
					'knee'  => $knee,
					'ankle' => $ankle,
					'toe'   => (float) $def[2],
					'dir'   => (int) $def[3],
				];
			}
			$wrists = [];
			foreach ( [ 'l', 'r' ] as $side ) {
				$wrists[ $side ] = self::turned( $rig['wrist'][ $side ], $theta, $shift_x, $dy );
			}

			return [ $legs, $wrists ];
		};

		[ $legs, $wrists ] = $place( $dx, 0.0 );
		if ( isset( $st['dy'] ) ) {
			$dy = (float) $st['dy'];
		} else {
			$lowest = 0.0;
			foreach ( $st['contact'] as [ $part, $margin ] ) {
				foreach ( [ 'l', 'r' ] as $side ) {
					$y = match ( $part ) {
						'ankle'             => $legs[ $side ]['ankle'][1],
						'knee'              => $legs[ $side ]['knee'][1],
						'hip', 'hipside'    => $legs[ $side ]['hip'][1],
						'wrist'             => $wrists[ $side ][1],
						default             => 0.0,
					};
					$lowest = max( $lowest, $y + (float) $margin );
				}
			}
			$dy = (float) $h - 11.0 - $lowest;
		}
		[ $legs, $wrists ] = $place( $dx, $dy );

		$points_of = static function ( array $legs, array $wrists, float $shift_x, float $shift_y ) use ( $rig, $theta ): array {
			$points = [ self::turned( [ 80.0, 26.0 ], $theta, $shift_x, $shift_y ), self::turned( [ 80.0, 4.0 ], $theta, $shift_x, $shift_y ) ];
			foreach ( [ 'l', 'r' ] as $side ) {
				$points[] = $legs[ $side ]['hip'];
				$points[] = $legs[ $side ]['knee'];
				$points[] = [ $legs[ $side ]['ankle'][0] + 12 * $legs[ $side ]['dir'], $legs[ $side ]['ankle'][1] + 10.5 ];
				$points[] = $legs[ $side ]['ankle'];
				$points[] = $wrists[ $side ];
				$points[] = self::turned( $rig['shoulder'][ $side ], $theta, $shift_x, $shift_y );
			}

			return $points;
		};
		$points    = $points_of( $legs, $wrists, $dx, $dy );

		// A hand-made pose gets a canvas that fits it.
		if ( $custom && ! $edit ) {
			$xs                = array_column( $points, 0 );
			$ys                = array_column( $points, 1 );
			$ground            = $h - 11.0;
			$shift_x           = 30.0 - min( $xs );
			$shift_y           = 26.0 - min( $ys );
			$dx               += $shift_x;
			$dy               += $shift_y;
			$w                 = (int) ceil( max( $xs ) - min( $xs ) + 60.0 );
			$h                 = (int) ceil( max( $ground, max( $ys ) ) - min( $ys ) + 26.0 + 11.0 );
			[ $legs, $wrists ] = $place( $dx, $dy );
			$points            = $points_of( $legs, $wrists, $dx, $dy );
		}

		$free = (string) $rig['pose']['free'];
		$xs   = array_column( $points, 0 );

		return [
			'vb'     => [ (int) $w, (int) $h ],
			'dx'     => $dx,
			'dy'     => $dy,
			'legs'   => $legs,
			'wrists' => $wrists,
			'points' => $points,
			'hold'   => $wrists[ $free ],
			'ground' => [ ( min( $xs ) + max( $xs ) ) / 2, (float) $h - 11.0 ],
			'span'   => max( $xs ) - min( $xs ),
		];
	}

	/**
	 * The canvas and the key points (head, shoulders, hips, knees, feet, hands) of a posed character, for
	 * checking that everything fits. Null for standing and sitting on a chair.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @return array{vb: array{0: int, 1: int}, points: array<int, array{0: float, 1: float}>}|null
	 */
	public static function extent( CharacterSpec $spec ): ?array {
		if ( null === Stances::get( $spec->get( 'stance' ) ) && null === $spec->custom ) {
			return null;
		}
		$lay = self::layout( $spec );

		return [
			'vb'     => $lay['vb'],
			'points' => $lay['points'],
		];
	}

	/**
	 * The joints of a character in the fixed editing canvas, and the angles that pose them, for the page's
	 * drag handles. Angles are screen angles in degrees: 0 points down, 90 right, 180 up.
	 *
	 * @param CharacterSpec $spec Spec.
	 * @return array<string, mixed>
	 */
	public static function pose( CharacterSpec $spec ): array {
		$st    = self::stance_data( $spec );
		$rig   = self::rig( $spec );
		$lay   = self::layout( $spec, true );
		$theta = (float) $st['theta'];
		$dx    = (float) $lay['dx'];
		$dy    = (float) $lay['dy'];
		$round = static fn( array $p ): array => [ round( (float) $p[0], 1 ), round( (float) $p[1], 1 ) ];

		// The head turns about the neck; its handle sits on a stalk out beyond the top of the head.
		$tilt    = deg2rad( (float) ( $st['head'] ?? 0 ) );
		$joints  = [
			'head'  => $round( self::turned( [ 80.0 + 24.0 * sin( $tilt ), 50.0 - 24.0 * cos( $tilt ) ], $theta, $dx, $dy ) ),
			'stalk' => $round( self::turned( [ 80.0 + 62.0 * sin( $tilt ), 50.0 - 62.0 * cos( $tilt ) ], $theta, $dx, $dy ) ),
			'neck'  => $round( self::turned( [ 80.0, 50.0 ], $theta, $dx, $dy ) ),
			'chest' => $round( self::turned( [ 80.0, 104.0 ], $theta, $dx, $dy ) ),
			'pivot' => $round( self::turned( [ 80.0, 152.0 ], $theta, $dx, $dy ) ),
		];
		$arms    = [];
		$legs    = [];
		$lengths = [];
		foreach ( [ 'l', 'r' ] as $side ) {
			$sign            = 'r' === $side ? 1 : -1;
			$joints[ $side ] = [
				'shoulder' => $round( self::turned( $rig['shoulder'][ $side ], $theta, $dx, $dy ) ),
				'elbow'    => $round( self::turned( $rig['elbow'][ $side ], $theta, $dx, $dy ) ),
				'wrist'    => $round( $lay['wrists'][ $side ] ),
				'hip'      => $round( $lay['legs'][ $side ]['hip'] ),
				'knee'     => $round( $lay['legs'][ $side ]['knee'] ),
				'ankle'    => $round( $lay['legs'][ $side ]['ankle'] ),
			];
			// Local angles back to screen angles.
			$local            = $rig['pose'][ $side ];
			$arms[ $side ]    = [ round( $sign * (float) $local[0] - $theta, 1 ), round( $sign * (float) $local[1] - $theta, 1 ) ];
			$leg              = $st['legs'][ $side ];
			$legs[ $side ]    = [ (float) $leg[0], (float) $leg[1], (float) $leg[2] ];
			$lengths[ $side ] = [ (float) ( $leg[4] ?? 74.0 ), (float) ( $leg[5] ?? 72.0 ) ];
		}

		return [
			'frame'   => self::EDIT_FRAME,
			'joints'  => $joints,
			'lengths' => [
				'arm'  => [ 42.0, 38.0 ],
				'legs' => $lengths,
			],
			'angles'  => [
				'theta' => $theta,
				'head'  => (float) ( $st['head'] ?? 0 ),
				'arms'  => $arms,
				'legs'  => $legs,
				'order' => (array) ( $spec->custom['order'] ?? [] ) + self::DEFAULT_ORDER,
			],
		];
	}

	/**
	 * A character in one of the other body positions, or with a hand-made pose.
	 *
	 * @param CharacterSpec $spec   Spec.
	 * @param string        $label  Label.
	 * @param array<string> $tags   Tags.
	 * @param string        $person Person.
	 * @param bool          $edit   Fixed editing canvas.
	 * @return string
	 */
	private static function posed( CharacterSpec $spec, string $label, array $tags, string $person, bool $edit = false ): string {
		$st        = self::stance_data( $spec );
		$rig       = self::rig( $spec );
		$lay       = self::layout( $spec, $edit );
		$theta     = (float) $st['theta'];
		$tilt      = (float) ( $st['head'] ?? 0 );
		$back      = ! empty( $st['back'] );
		[ $w, $h ] = $lay['vb'];
		$bottom    = 'dress' === $spec->get( 'top' ) ? 'skirt' : $spec->get( 'bottom' );
		$colour    = 'dress' === $spec->get( 'top' ) ? $spec->get( 'top_color' ) : $spec->get( 'bottom_color' );
		$hi        = (float) $rig['hi'];
		$cx        = self::CX;

		$head_wrap = static fn( string $markup ): string => 0.0 === $tilt ? $markup : '<g transform="rotate(' . G::n( $tilt ) . ' 80 50)">' . $markup . '</g>';

		$upper   = [];
		$upper[] = '<path class="' . self::f( $colour ) . '" d="' . G::closed( [ [ $cx - $hi, 136 ], [ $cx - $hi + 1, 150 ], [ $cx - 4, 162 ], [ $cx + 4, 162 ], [ $cx + $hi - 1, 150 ], [ $cx + $hi, 136 ], [ $cx, 128 ] ] ) . '"/>';
		if ( 'backpack' === $spec->get( 'bag' ) ) {
			$upper[] = self::backpack( $spec, $rig );
		}
		// Parts set behind the body are drawn first; legs set in front come after the whole upper body.
		$order = (array) ( $spec->custom['order'] ?? [] ) + self::DEFAULT_ORDER;
		$in    = static fn( string $part, string $where ): bool => $where === $order[ $part ];
		$arms  = [
			'front' => array_values( array_filter( [ 'l', 'r' ], static fn( $side ) => $in( 'arm_' . $side, 'front' ) ) ),
			'back'  => array_values( array_filter( [ 'l', 'r' ], static fn( $side ) => $in( 'arm_' . $side, 'back' ) ) ),
		];
		$legs  = [
			'front' => array_values( array_filter( [ 'l', 'r' ], static fn( $side ) => $in( 'leg_' . $side, 'front' ) ) ),
			'back'  => array_values( array_filter( [ 'l', 'r' ], static fn( $side ) => $in( 'leg_' . $side, 'back' ) ) ),
		];

		$upper[] = self::arms( $spec, $rig, $arms['back'] );
		$upper[] = $head_wrap( self::hair( $spec, false ) );
		$upper[] = '<path class="slot-skin-dark" d="M74 38 H86 V62 H74 Z"/>';
		$upper[] = self::torso( $spec, $rig );
		$upper[] = self::bag_over_torso( $spec, $rig );
		$upper[] = self::arms( $spec, $rig, $arms['front'] );
		$upper[] = self::carried( $spec, $rig );
		$upper[] = $head_wrap( self::head( $spec, $back ) );

		$shadow = '<ellipse class="slot-neutral" cx="' . G::n( $lay['ground'][0] ) . '" cy="' . G::n( $h - 9 ) . '" rx="' . G::n( min( 92.0, max( 34.0, $lay['span'] * 0.45 ) ) ) . '" ry="5" opacity="0.12"/>';

		$attributes = sprintf(
			'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" data-si-label="%3$s" data-si-tags="%4$s" data-si-accepts="%5$s"%6$s',
			$w,
			$h,
			htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			htmlspecialchars( implode( ',', $tags ), ENT_QUOTES | ENT_XML1, 'UTF-8' ),
			'lap' === $st['accepts'] ? 'hold:lap' : 'hold:handheld',
			'' !== $person ? ' data-si-person="' . htmlspecialchars( $person, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"' : ''
		);

		$group = '<g transform="translate(' . G::n( (float) $lay['dx'] ) . ' ' . G::n( $lay['dy'] ) . ') rotate(' . G::n( $theta ) . ' 80 152)">' . "\n\t\t" . implode( "\n\t\t", array_filter( $upper ) ) . "\n\t</g>";

		$anchors = '<circle id="anchor-hold" cx="' . G::n( $lay['hold'][0] ) . '" cy="' . G::n( $lay['hold'][1] ) . '" r="3"/>' . "\n\t" . '<circle id="anchor-ground" cx="' . G::n( $lay['ground'][0] ) . '" cy="' . G::n( $lay['ground'][1] ) . '" r="3"/>';

		return '<svg ' . $attributes . ">\n\t" . $shadow . "\n\t" . self::posed_legs( $spec, $rig, $lay, $bottom, $colour, $legs['back'] ) . "\n\t" . $group . "\n\t" . self::posed_legs( $spec, $rig, $lay, $bottom, $colour, $legs['front'] ) . "\n\t" . $anchors . "\n</svg>\n";
	}
	/**
	 * Legs, trousers or skirt and shoes for a posed character.
	 *
	 * @param CharacterSpec        $spec   Spec.
	 * @param array<string, mixed> $rig    Rig.
	 * @param array<string, mixed> $lay    Layout.
	 * @param string               $bottom Bottom style.
	 * @param string               $colour Bottom colour token.
	 * @param array<string>        $sides  Which legs to draw.
	 * @return string
	 */
	private static function posed_legs( CharacterSpec $spec, array $rig, array $lay, string $bottom, string $colour, array $sides = [ 'l', 'r' ] ): string {
		$bare    = in_array( $bottom, [ 'shorts', 'skirt', 'midi' ], true );
		$thigh   = 15.0 + ( (float) $rig['hi'] - 20.5 ) * 0.5;
		$knee_w  = 'slim' === $bottom ? 11.0 : 13.0;
		$ankle_w = 'slim' === $bottom ? 8.5 : ( 'trousers' === $bottom ? 11.5 : 9.0 );
		$fill    = self::f( $colour );
		$mix     = static fn( array $a, array $b, float $t ): array => [ $a[0] + ( $b[0] - $a[0] ) * $t, $a[1] + ( $b[1] - $a[1] ) * $t ];
		$out     = [];

		foreach ( $sides as $side ) {
			$leg   = $lay['legs'][ $side ];
			$hip   = $leg['hip'];
			$knee  = $leg['knee'];
			$ankle = $leg['ankle'];

			$out[] = '<path class="slot-skin" d="' . G::limb( $hip, $knee, $thigh, 11 ) . '"/><path class="slot-skin" d="' . G::limb( $knee, $ankle, 10, 7 ) . '"/>';
			if ( 'shorts' === $bottom ) {
				$out[] = '<path class="' . $fill . '" d="' . G::limb( $hip, $mix( $hip, $knee, 0.85 ), $thigh - 0.5, $thigh - 0.5 ) . '"/>';
			} elseif ( ! $bare ) {
				$end   = 'cropped' === $bottom ? $mix( $knee, $ankle, 0.7 ) : $ankle;
				$out[] = '<path class="' . $fill . '" d="' . G::limb( $hip, $knee, $thigh + 1.5, $knee_w ) . '"/><path class="' . $fill . '" d="' . G::limb( $knee, $end, $knee_w, $ankle_w ) . '"/>';
			} else {
				$out[] = '<path class="' . $fill . '" d="' . G::limb( $hip, $mix( $hip, $knee, 0.92 ), 21, 18 ) . '"/>';
				if ( 'midi' === $bottom ) {
					$out[] = '<path class="' . $fill . '" d="' . G::limb( $mix( $hip, $knee, 0.9 ), $mix( $knee, $ankle, 0.3 ), 18, 15 ) . '"/>';
				}
			}

			$shoe  = self::shoe( $spec, $ankle, (int) $leg['dir'] );
			$rot   = $leg['toe'] * $leg['dir'];
			$out[] = abs( $rot ) > 0.5 ? '<g transform="rotate(' . G::n( $rot ) . ' ' . G::n( $ankle[0] ) . ' ' . G::n( $ankle[1] ) . ')">' . $shoe . '</g>' : $shoe;
		}//end foreach

		return implode( "\n\t", $out );
	}
}
