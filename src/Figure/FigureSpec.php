<?php
/**
 * The choices that make up one cartoon figure.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Figure;

use SprintIllustrations\Character\CharacterSpec;

/**
 * Immutable, normalized set of choices for a full-body cartoon figure. from_array() never throws: anything
 * unknown falls back to the default. The lists are the single source of truth for the page and the REST API.
 */
final class FigureSpec {

	public const FACES = [
		'round' => 'Round',
		'oval'  => 'Oval',
		'wide'  => 'Wide',
	];

	public const EYES = [
		'dot'     => 'Dots',
		'sparkle' => 'Sparkly',
		'happy'   => 'Smiling shut',
		'wide'    => 'Wide awake',
		'sleepy'  => 'Sleepy',
	];

	public const BROWS = [
		'none'   => 'None',
		'soft'   => 'Soft',
		'thick'  => 'Thick',
		'raised' => 'Raised',
	];

	public const NOSES = [
		'dot'  => 'Dot',
		'line' => 'Little line',
		'none' => 'None',
	];

	public const MOUTHS = [
		'smile' => 'Smile',
		'grin'  => 'Grin',
		'open'  => 'Open',
		'flat'  => 'Calm',
		'smirk' => 'Smirk',
		'cat'   => 'Cat smile',
	];

	public const CHEEKS = [
		'blush'    => 'Blush',
		'none'     => 'None',
		'freckles' => 'Blush and freckles',
	];

	public const HAIRS = [
		'short'    => 'Short',
		'messy'    => 'Messy',
		'bob'      => 'Bob',
		'long'     => 'Long',
		'ponytail' => 'Ponytail',
		'bun'      => 'Bun',
		'curly'    => 'Curly',
		'bald'     => 'No hair',
	];

	public const HEADWEAR = [
		'none'     => 'None',
		'cap'      => 'Cap',
		'cap_back' => 'Cap, backwards',
		'hardhat'  => 'Hard hat',
		'beanie'   => 'Beanie',
		'bucket'   => 'Bucket hat',
	];

	public const EYEWEAR = [
		'none'       => 'None',
		'round'      => 'Round glasses',
		'square'     => 'Square glasses',
		'sunglasses' => 'Sunglasses',
	];

	public const BUILDS = [
		'slim'    => 'Slim',
		'regular' => 'Regular',
		'round'   => 'Round',
	];

	public const TOPS = [
		'tee'      => 'T-shirt',
		'hoodie'   => 'Hoodie',
		'jacket'   => 'Zip jacket',
		'overalls' => 'Overalls',
		'shirt'    => 'Shirt',
		'raincoat' => 'Long coat',
	];

	public const BOTTOMS = [
		'trousers' => 'Trousers',
		'shorts'   => 'Shorts',
		'skirt'    => 'Skirt',
	];

	public const SHOES = [
		'sneakers' => 'Sneakers',
		'boots'    => 'Boots',
		'flats'    => 'Flat shoes',
	];

	public const POSES = [
		'relaxed'  => 'Relaxed',
		'hips'     => 'Hands on hips',
		'hold'     => 'Holding something',
		'wave'     => 'Waving',
		'pockets'  => 'Hands in pockets',
		'cheer'    => 'Cheering',
		'thinking' => 'Thinking',
		'walk'     => 'Walking',
	];

	public const CARRY = [
		'bag'       => 'Paper bag',
		'clipboard' => 'Clipboard',
		'parcel'    => 'Parcel',
		'none'      => 'Nothing',
	];

	public const BACKPACK = [
		'none'     => 'None',
		'backpack' => 'Backpack',
	];

	public const TILTS = [
		'none'  => 'Straight',
		'left'  => 'Tilted left',
		'right' => 'Tilted right',
	];

	public const SCENES = [
		'none'   => 'None',
		'splash' => 'Watercolour splash',
	];

	/** Fields that take a palette colour token. */
	public const COLOR_FIELDS = [ 'top_color', 'bottom_color', 'shoes_color', 'headwear_color', 'bag_color', 'scene_color' ];

	/** Highest skin or hair tone index. */
	public const MAX_TONE = 7;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $choices Normalized choices by field.
	 * @param int                   $skin    Skin tone shown in the preview.
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
	 * @return array<string, array<string, string>>
	 */
	public static function options(): array {
		$colors = CharacterSpec::COLORS;

		return [
			'pose'           => self::POSES,
			'carry'          => self::CARRY,
			'face'           => self::FACES,
			'eyes'           => self::EYES,
			'brows'          => self::BROWS,
			'nose'           => self::NOSES,
			'mouth'          => self::MOUTHS,
			'cheeks'         => self::CHEEKS,
			'hair'           => self::HAIRS,
			'headwear'       => self::HEADWEAR,
			'headwear_color' => $colors,
			'eyewear'        => self::EYEWEAR,
			'build'          => self::BUILDS,
			'top'            => self::TOPS,
			'top_color'      => $colors,
			'bottom'         => self::BOTTOMS,
			'bottom_color'   => $colors,
			'shoes'          => self::SHOES,
			'shoes_color'    => $colors,
			'backpack'       => self::BACKPACK,
			'bag_color'      => $colors,
			'tilt'           => self::TILTS,
			'scene'          => self::SCENES,
			'scene_color'    => $colors,
		];
	}

	/**
	 * Defaults.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return [
			'pose'           => 'relaxed',
			'carry'          => 'bag',
			'face'           => 'round',
			'eyes'           => 'dot',
			'brows'          => 'soft',
			'nose'           => 'dot',
			'mouth'          => 'smile',
			'cheeks'         => 'blush',
			'hair'           => 'short',
			'headwear'       => 'none',
			'headwear_color' => 'accent',
			'eyewear'        => 'none',
			'build'          => 'regular',
			'top'            => 'hoodie',
			'top_color'      => 'accent',
			'bottom'         => 'trousers',
			'bottom_color'   => 'secondary-dark',
			'shoes'          => 'sneakers',
			'shoes_color'    => 'neutral-dark',
			'backpack'       => 'none',
			'bag_color'      => 'neutral-light',
			'tilt'           => 'none',
			'scene'          => 'splash',
			'scene_color'    => 'neutral-light',
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
		foreach ( self::options() as $field => $allowed ) {
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
