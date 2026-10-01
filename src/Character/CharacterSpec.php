<?php
/**
 * The choices that make up one character.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Character;

/**
 * Immutable, normalized set of choices. from_array() never throws: anything unknown falls back to
 * the default. The option lists are the single source of truth for the builder page and the REST API.
 */
final class CharacterSpec {

	public const STANCES = [
		'standing'  => 'Standing (upright)',
		'walking'   => 'Walking',
		'running'   => 'Running',
		'leaning'   => 'Leaning',
		'balancing' => 'Balancing on one leg',
		'bending'   => 'Bending over',
		'stooping'  => 'Stooping',
		'squatting' => 'Squatting',
		'crouching' => 'Crouching',
		'kneeling'  => 'Kneeling',
		'sitting'   => 'Sitting',
		'sit_cross' => 'Sitting cross-legged',
		'sit_legs'  => 'Sitting with legs out',
		'reclining' => 'Reclining',
		'all_fours' => 'Hands and knees',
		'crawling'  => 'Crawling',
		'climbing'  => 'Climbing',
		'hanging'   => 'Hanging',
		'supine'    => 'Lying face up',
		'prone'     => 'Lying face down',
		'side'      => 'Lying on one side',
		'fetal'     => 'Curled up (fetal)',
	];
	public const POSES   = [
		'standing'  => [
			'relaxed' => 'Relaxed',
			'wave'    => 'Waving',
			'phone'   => 'On the phone',
			'folder'  => 'Holding a folder',
			'crossed' => 'Arms crossed',
			'hip'     => 'Hand on hip',
			'present' => 'Presenting',
			'point'   => 'Pointing',
			'think'   => 'Thinking',
			'clasped' => 'Hands together',
			'pockets' => 'Hands in pockets',
			'cheer'   => 'Cheering',
			'shrug'   => 'Shrugging',
			'tablet'  => 'With a tablet',
			'coffee'  => 'With a coffee',
			'book'    => 'Reading a book',
			'box'     => 'Carrying a box',
		],
		'sitting'   => [
			'lap'    => 'Hands in lap',
			'typing' => 'Working',
			'wave'   => 'Waving',
			'phone'  => 'On the phone',
			'think'  => 'Thinking',
			'book'   => 'Reading a book',
			'coffee' => 'With a coffee',
			'cheer'  => 'Cheering',
		],
		'walking'   => [
			'swing'  => 'Arms swinging',
			'phone'  => 'On the phone',
			'coffee' => 'With a coffee',
			'tablet' => 'With a tablet',
		],
		'running'   => [ 'run' => 'Running arms' ],
		'leaning'   => [
			'relaxed' => 'Relaxed',
			'pockets' => 'Hands in pockets',
			'crossed' => 'Arms crossed',
			'phone'   => 'On the phone',
			'hip'     => 'Hand on hip',
		],
		'balancing' => [
			'out'   => 'Arms out',
			'cheer' => 'Cheering',
		],
		'bending'   => [
			'hang'  => 'Arms hanging',
			'reach' => 'Reaching down',
		],
		'stooping'  => [
			'hang'  => 'Arms hanging',
			'reach' => 'Reaching down',
		],
		'squatting' => [
			'knees'   => 'Hands on knees',
			'clasped' => 'Hands together',
		],
		'crouching' => [
			'knees'   => 'Hands on knees',
			'clasped' => 'Hands together',
		],
		'kneeling'  => [
			'lap'   => 'Hands on lap',
			'pray'  => 'Hands together',
			'cheer' => 'Cheering',
		],
		'sit_cross' => [
			'lap'    => 'Hands in lap',
			'knees'  => 'Hands on knees',
			'think'  => 'Thinking',
			'book'   => 'Reading a book',
			'coffee' => 'With a coffee',
			'phone'  => 'On the phone',
			'cheer'  => 'Cheering',
		],
		'sit_legs'  => [
			'lap'   => 'Hands in lap',
			'phone' => 'On the phone',
			'book'  => 'Reading a book',
			'think' => 'Thinking',
		],
		'reclining' => [
			'relaxed' => 'Relaxed',
			'lap'     => 'Hands in lap',
			'book'    => 'Reading a book',
			'phone'   => 'On the phone',
		],
		'all_fours' => [ 'hands' => 'Hands on the ground' ],
		'crawling'  => [ 'crawl' => 'Crawling' ],
		'climbing'  => [ 'climb' => 'Reaching up' ],
		'hanging'   => [
			'grip' => 'Both hands',
			'one'  => 'One hand',
		],
		'supine'    => [
			'rest'    => 'Arms by the sides',
			'crossed' => 'Hands on chest',
			'up'      => 'Arms overhead',
		],
		'prone'     => [
			'rest' => 'Arms by the sides',
			'up'   => 'Arms overhead',
		],
		'side'      => [
			'rest'    => 'Arms by the sides',
			'crossed' => 'Hands on chest',
		],
		'fetal'     => [ 'hug' => 'Hugging the knees' ],
	];

	public const SEATS = [
		'chair' => 'On a chair',
		'none'  => 'No chair',
	];

	public const LEGS = [
		'straight' => 'Standing straight',
		'step'     => 'Mid-step',
		'wide'     => 'Feet apart',
	];

	public const BUILDS = [
		'slim'    => 'Slim',
		'regular' => 'Regular',
		'broad'   => 'Broad',
	];

	public const TOPS = [
		'tee'        => 'T-shirt',
		'longsleeve' => 'Long sleeve',
		'shirt'      => 'Shirt',
		'polo'       => 'Polo shirt',
		'blouse'     => 'Blouse',
		'sweater'    => 'Sweater',
		'hoodie'     => 'Hoodie',
		'dress'      => 'Dress',
	];

	public const OUTERS = [
		'none'     => 'None',
		'jacket'   => 'Jacket',
		'blazer'   => 'Blazer',
		'cardigan' => 'Cardigan',
		'vest'     => 'Waistcoat',
		'hivis'    => 'High-vis vest',
		'apron'    => 'Apron',
		'coat'     => 'Long coat',
	];

	public const BOTTOMS = [
		'trousers' => 'Trousers',
		'slim'     => 'Slim trousers',
		'cropped'  => 'Cropped trousers',
		'shorts'   => 'Shorts',
		'skirt'    => 'Skirt',
		'midi'     => 'Long skirt',
	];

	public const SHOES = [
		'sneakers' => 'Trainers',
		'boots'    => 'Boots',
		'shoes'    => 'Shoes',
	];

	public const HAIRS = [
		'short'    => 'Short',
		'quiff'    => 'Quiff',
		'buzz'     => 'Buzz cut',
		'curly'    => 'Curly',
		'afro'     => 'Afro',
		'long'     => 'Long',
		'wavy'     => 'Wavy',
		'bob'      => 'Bob',
		'ponytail' => 'Ponytail',
		'bun'      => 'Bun',
		'bald'     => 'Bald',
	];

	public const HEADWEAR = [
		'none'    => 'None',
		'cap'     => 'Cap',
		'bucket'  => 'Bucket hat',
		'beanie'  => 'Beanie',
		'flatcap' => 'Flat cap',
		'sunhat'  => 'Sun hat',
		'headset' => 'Headset',
	];

	public const FACES = [
		'simple' => 'Simple face',
		'none'   => 'No face',
	];

	public const GLASSES = [
		'none'   => 'None',
		'round'  => 'Round',
		'square' => 'Square',
		'sun'    => 'Sunglasses',
	];

	public const FACIAL_HAIR = [
		'none'      => 'None',
		'stubble'   => 'Stubble',
		'moustache' => 'Moustache',
		'beard'     => 'Beard',
	];

	public const BAGS = [
		'none'      => 'None',
		'messenger' => 'Shoulder bag',
		'tote'      => 'Tote bag',
		'briefcase' => 'Briefcase',
		'backpack'  => 'Backpack',
	];

	public const EXTRAS = [
		'none'    => 'None',
		'tie'     => 'Tie',
		'lanyard' => 'Lanyard and badge',
		'scarf'   => 'Scarf',
	];

	/**
	 * Palette colours a garment can take: each slot in its base, light and dark shade, and off-white.
	 */
	public const COLORS = [
		'primary'         => 'Primary',
		'primary-light'   => 'Primary, light',
		'primary-dark'    => 'Primary, dark',
		'secondary'       => 'Secondary',
		'secondary-light' => 'Secondary, light',
		'secondary-dark'  => 'Secondary, dark',
		'accent'          => 'Accent',
		'accent-light'    => 'Accent, light',
		'accent-dark'     => 'Accent, dark',
		'neutral'         => 'Neutral',
		'neutral-light'   => 'Neutral, light',
		'neutral-dark'    => 'Neutral, dark',
		'background'      => 'Off-white',
	];

	public const GENDERS = [
		'any'   => 'Any',
		'woman' => 'Woman',
		'man'   => 'Man',
	];

	/**
	 * The colour fields.
	 */
	public const COLOR_FIELDS = [ 'top_color', 'outer_color', 'bottom_color', 'shoes_color', 'headwear_color', 'bag_color', 'extra_color' ];

	public const MAX_TONE = 7;

	/**
	 * Constructor. Use from_array().
	 *
	 * @param array<string, string> $choices Normalized choices by field.
	 * @param int                   $skin    Skin tone shown in the preview (the saved piece follows the palette).
	 * @param int                   $hair    Hair colour shown in the preview.
	 * @param array|null            $custom  A hand-posed body (see parse_custom()), or null for the chosen stance.
	 */
	private function __construct(
		public readonly array $choices,
		public readonly int $skin,
		public readonly int $hair,
		public readonly ?array $custom = null,
	) {}

	/**
	 * Every field with its allowed values.
	 *
	 * @param string $stance Stance, which decides the poses on offer.
	 * @return array<string, array<string, string>>
	 */
	public static function options( string $stance = 'standing' ): array {
		return [
			'stance'         => self::STANCES,
			'pose'           => self::POSES[ isset( self::POSES[ $stance ] ) ? $stance : 'standing' ],
			'seat'           => self::SEATS,
			'legs'           => self::LEGS,
			'build'          => self::BUILDS,
			'top'            => self::TOPS,
			'top_color'      => self::COLORS,
			'outer'          => self::OUTERS,
			'outer_color'    => self::COLORS,
			'bottom'         => self::BOTTOMS,
			'bottom_color'   => self::COLORS,
			'shoes'          => self::SHOES,
			'shoes_color'    => self::COLORS,
			'hair'           => self::HAIRS,
			'headwear'       => self::HEADWEAR,
			'headwear_color' => self::COLORS,
			'face'           => self::FACES,
			'glasses'        => self::GLASSES,
			'facial_hair'    => self::FACIAL_HAIR,
			'bag'            => self::BAGS,
			'bag_color'      => self::COLORS,
			'extra'          => self::EXTRAS,
			'extra_color'    => self::COLORS,
			'gender'         => self::GENDERS,
		];
	}

	/**
	 * Defaults.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return [
			'stance'         => 'standing',
			'pose'           => 'relaxed',
			'seat'           => 'chair',
			'legs'           => 'straight',
			'build'          => 'regular',
			'top'            => 'tee',
			'top_color'      => 'background',
			'outer'          => 'jacket',
			'outer_color'    => 'secondary',
			'bottom'         => 'trousers',
			'bottom_color'   => 'neutral',
			'shoes'          => 'sneakers',
			'shoes_color'    => 'neutral-dark',
			'hair'           => 'short',
			'headwear'       => 'none',
			'headwear_color' => 'accent',
			'face'           => 'simple',
			'glasses'        => 'none',
			'facial_hair'    => 'none',
			'bag'            => 'none',
			'bag_color'      => 'accent-dark',
			'extra'          => 'none',
			'extra_color'    => 'accent',
			'gender'         => 'any',
		];
	}

	/**
	 * Build a normalized spec from untrusted input.
	 *
	 * @param array<string, mixed> $data Input.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$choices = self::defaults();

		$stance = $data['stance'] ?? null;
		if ( is_string( $stance ) && isset( self::STANCES[ $stance ] ) ) {
			$choices['stance'] = $stance;
		}
		// A pose that belongs to the other stance falls back to that stance's first pose.
		$poses           = self::POSES[ $choices['stance'] ];
		$choices['pose'] = isset( $data['pose'] ) && is_string( $data['pose'] ) && isset( $poses[ $data['pose'] ] ) ? $data['pose'] : (string) array_key_first( $poses );

		foreach ( self::options() as $field => $allowed ) {
			if ( in_array( $field, [ 'stance', 'pose' ], true ) ) {
				continue;
			}
			$value = $data[ $field ] ?? null;
			if ( is_string( $value ) && isset( $allowed[ $value ] ) ) {
				$choices[ $field ] = $value;
			}
		}

		$tone = static fn( mixed $value ): int => is_numeric( $value ) ? max( 0, min( self::MAX_TONE, (int) $value ) ) : 0;

		return new self( $choices, $tone( $data['skin'] ?? 0 ), $tone( $data['hair_tone'] ?? 0 ), self::parse_custom( $data['custom'] ?? null ) );
	}

	/**
	 * One choice.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	public function get( string $field ): string {
		return $this->choices[ $field ] ?? '';
	}

	/**
	 * A copy with some choices changed.
	 *
	 * @param array<string, mixed> $changes Field => value.
	 * @return self
	 */
	public function with( array $changes ): self {
		// Choosing another stance or pose starts from that preset again, unless a pose comes with the change.
		if ( ! array_key_exists( 'custom', $changes ) && ( array_key_exists( 'stance', $changes ) || array_key_exists( 'pose', $changes ) ) ) {
			$changes['custom'] = null;
		}

		return self::from_array( $changes + $this->to_array() );
	}

	/**
	 * Validate a hand-made pose: the body turn and head tilt in degrees, and per side the two arm angles and
	 * the two leg angles plus the foot tilt. Anything incomplete or not numeric drops the whole pose.
	 *
	 * @param mixed $data Untrusted input.
	 * @return array{theta: float, head: float, arms: array<string, array<int, float>>, legs: array<string, array<int, float>>}|null
	 */
	private static function parse_custom( mixed $data ): ?array {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$num = static fn( mixed $value, float $low, float $high ): ?float => is_numeric( $value ) ? round( max( $low, min( $high, (float) $value ) ), 1 ) : null;

		$theta = $num( $data['theta'] ?? null, -180.0, 180.0 );
		$head  = $num( $data['head'] ?? 0, -90.0, 90.0 );
		$yaw   = $num( $data['yaw'] ?? 0, -180.0, 180.0 );
		$spin  = $num( $data['spin'] ?? 0, -180.0, 180.0 );
		if ( null === $theta || null === $head || null === $yaw || null === $spin ) {
			return null;
		}

		$out = [
			'theta' => $theta,
			'head'  => $head,
			'yaw'   => $yaw,
			'spin'  => $spin,
			'arms'  => [],
			'legs'  => [],
			'order' => [],
		];
		// Each limb is drawn in front of the body or behind it.
		foreach ( CharacterBuilder::DEFAULT_ORDER as $part => $default ) {
			$where                 = $data['order'][ $part ] ?? $default;
			$out['order'][ $part ] = in_array( $where, [ 'front', 'back' ], true ) ? $where : $default;
		}
		foreach ( [ 'l', 'r' ] as $side ) {
			$arm = (array) ( $data['arms'][ $side ] ?? [] );
			$leg = (array) ( $data['legs'][ $side ] ?? [] );
			$a   = [ $num( $arm[0] ?? null, -360.0, 360.0 ), $num( $arm[1] ?? null, -360.0, 360.0 ) ];
			$b   = [ $num( $leg[0] ?? null, -360.0, 360.0 ), $num( $leg[1] ?? null, -360.0, 360.0 ), $num( $leg[2] ?? 0, -90.0, 90.0 ) ];
			if ( in_array( null, $a, true ) || in_array( null, $b, true ) ) {
				return null;
			}
			// Which way the toes point (-1 left, 1 right): flipping a foot mirrors it.
			if ( isset( $leg[3] ) && in_array( (int) $leg[3], [ -1, 1 ], true ) ) {
				$b[] = (int) $leg[3];
			}
			$out['arms'][ $side ] = $a;
			$out['legs'][ $side ] = $b;
		}

		return $out;
	}

	/**
	 * Export (round-trips through from_array()).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$out = $this->choices + [
			'skin'      => $this->skin,
			'hair_tone' => $this->hair,
		];
		if ( null !== $this->custom ) {
			$out['custom'] = $this->custom;
		}

		return $out;
	}
}
