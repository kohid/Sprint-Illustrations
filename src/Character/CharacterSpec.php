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
		'standing' => 'Standing',
		'sitting'  => 'Sitting',
	];

	public const POSES = [
		'standing' => [
			'relaxed' => 'Relaxed',
			'wave'    => 'Waving',
			'phone'   => 'On the phone',
			'folder'  => 'Holding a folder',
			'crossed' => 'Arms crossed',
			'hip'     => 'Hand on hip',
			'present' => 'Presenting',
			'point'   => 'Pointing',
		],
		'sitting'  => [
			'lap'    => 'Hands in lap',
			'typing' => 'Working',
			'wave'   => 'Waving',
			'phone'  => 'On the phone',
		],
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
	 */
	private function __construct(
		public readonly array $choices,
		public readonly int $skin,
		public readonly int $hair,
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

		return new self( $choices, $tone( $data['skin'] ?? 0 ), $tone( $data['hair_tone'] ?? 0 ) );
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
		return self::from_array( $changes + $this->to_array() );
	}

	/**
	 * Export (round-trips through from_array()).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->choices + [
			'skin'      => $this->skin,
			'hair_tone' => $this->hair,
		];
	}
}
