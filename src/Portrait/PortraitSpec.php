<?php
/**
 * The choices that make up one portrait.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Portrait;

use SprintIllustrations\Character\CharacterSpec;

/**
 * Immutable, normalized set of choices for a head-and-shoulders portrait. from_array() never throws:
 * anything unknown falls back to the default. The lists are the single source of truth for the page and
 * the REST API.
 */
final class PortraitSpec {

	public const FACES = [
		'oval'   => 'Oval',
		'round'  => 'Round',
		'heart'  => 'Heart',
		'square' => 'Square',
	];

	public const EYES = [
		'round'  => 'Big and round',
		'almond' => 'Almond',
		'sleepy' => 'Sleepy',
		'wide'   => 'Wide awake',
		'happy'  => 'Smiling shut',
	];

	public const IRISES = [
		'hair-dark'    => 'Dark brown',
		'hair'         => 'Brown',
		'neutral-dark' => 'Grey',
		'primary'      => 'Primary',
		'secondary'    => 'Secondary',
		'accent'       => 'Accent',
	];

	public const BROWS = [
		'soft'     => 'Soft',
		'arched'   => 'Arched',
		'straight' => 'Straight',
		'thick'    => 'Thick',
		'thin'     => 'Thin',
	];

	public const NOSES = [
		'button' => 'Button',
		'line'   => 'Simple line',
		'pointy' => 'Pointed',
	];

	public const MOUTHS = [
		'neutral' => 'Calm',
		'smile'   => 'Smile',
		'grin'    => 'Grin',
		'smirk'   => 'Smirk',
		'open'    => 'Surprised',
		'serious' => 'Serious',
	];

	public const CHEEKS = [
		'none'     => 'Plain',
		'blush'    => 'Blush',
		'freckles' => 'Freckles',
		'both'     => 'Blush and freckles',
	];

	public const HAIRS = [
		'shoulder' => 'Shoulder length, side part',
		'long'     => 'Long and straight',
		'bob'      => 'Bob',
		'wavy'     => 'Long and wavy',
		'ponytail' => 'Ponytail',
		'bun'      => 'Bun',
		'short'    => 'Short',
		'curly'    => 'Curly',
		'buzz'     => 'Buzz cut',
		'bald'     => 'Bald',
	];

	public const FACIAL_HAIR = [
		'none'      => 'None',
		'stubble'   => 'Stubble',
		'moustache' => 'Moustache',
		'beard'     => 'Beard',
	];

	public const GLASSES = [
		'none'   => 'None',
		'round'  => 'Round',
		'square' => 'Square',
	];

	public const EARRINGS = [
		'none'  => 'None',
		'studs' => 'Studs',
		'hoops' => 'Hoops',
	];

	public const HEADWEAR = [
		'none'    => 'None',
		'cap'     => 'Cap',
		'beanie'  => 'Beanie',
		'headset' => 'Headset',
	];

	public const TOPS = [
		'sweater'    => 'Crew-neck sweater',
		'tee'        => 'T-shirt',
		'shirt'      => 'Shirt',
		'hoodie'     => 'Hoodie',
		'blazer'     => 'Blazer',
		'turtleneck' => 'Turtleneck',
	];

	public const POSES = [
		'rest'  => 'Arms down',
		'wheel' => 'Hands on a wheel',
		'wave'  => 'Waving',
		'phone' => 'Holding a phone',
	];

	public const LOOKS = [
		'front' => 'Facing us',
		'left'  => 'Turned left',
		'right' => 'Turned right',
	];

	public const TILTS = [
		'none'  => 'Upright',
		'left'  => 'Tilted left',
		'right' => 'Tilted right',
	];

	public const SEATBELTS = [
		'none' => 'No seatbelt',
		'on'   => 'Seatbelt on',
	];

	public const SCENES = [
		'none' => 'Just the person',
		'wash' => 'Soft colour wash',
		'taxi' => 'In the driver\'s seat',
	];

	public const COLOR_FIELDS = [ 'top_color', 'headwear_color', 'scene_color' ];

	public const MAX_TONE = 7;

	/**
	 * Constructor. Use from_array().
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
			'face'           => self::FACES,
			'eyes'           => self::EYES,
			'iris'           => self::IRISES,
			'brows'          => self::BROWS,
			'nose'           => self::NOSES,
			'mouth'          => self::MOUTHS,
			'cheeks'         => self::CHEEKS,
			'hair'           => self::HAIRS,
			'facial_hair'    => self::FACIAL_HAIR,
			'glasses'        => self::GLASSES,
			'earrings'       => self::EARRINGS,
			'headwear'       => self::HEADWEAR,
			'headwear_color' => $colors,
			'top'            => self::TOPS,
			'top_color'      => $colors,
			'pose'           => self::POSES,
			'look'           => self::LOOKS,
			'tilt'           => self::TILTS,
			'seatbelt'       => self::SEATBELTS,
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
			'face'           => 'oval',
			'eyes'           => 'round',
			'iris'           => 'hair-dark',
			'brows'          => 'soft',
			'nose'           => 'button',
			'mouth'          => 'neutral',
			'cheeks'         => 'both',
			'hair'           => 'shoulder',
			'facial_hair'    => 'none',
			'glasses'        => 'none',
			'earrings'       => 'none',
			'headwear'       => 'none',
			'headwear_color' => 'accent',
			'top'            => 'sweater',
			'top_color'      => 'secondary-light',
			'pose'           => 'rest',
			'look'           => 'front',
			'tilt'           => 'none',
			'seatbelt'       => 'none',
			'scene'          => 'none',
			'scene_color'    => 'accent',
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
