<?php
/**
 * Starting points for portraits: role presets, a description-to-portrait helper and a seeded shuffle.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Portrait;

use SprintIllustrations\Compose\Seed;
use SprintIllustrations\Selection\Keywords;

/**
 * Lets a portrait start close to what a page is about ("a taxi driver at the wheel") instead of from the
 * default face. Everything stays editable on the page afterwards.
 */
final class PortraitPresets {

	/**
	 * Role => label, the choices it sets, and the words that suggest it (the first two name the role).
	 */
	private const ROLES = [
		'driver'    => [
			'label'   => 'Taxi driver at the wheel',
			'words'   => [ 'taxi', 'driver', 'cab', 'chauffeur', 'wheel', 'driving' ],
			'choices' => [
				'scene'       => 'taxi',
				'scene_color' => 'accent',
				'pose'        => 'wheel',
				'seatbelt'    => 'on',
				'top'         => 'shirt',
				'top_color'   => 'secondary-light',
				'hair'        => 'short',
				'headwear'    => 'cap',
				'mouth'       => 'smile',
			],
		],
		'passenger' => [
			'label'   => 'Passenger',
			'words'   => [ 'passenger', 'customer', 'rider', 'fare', 'journey', 'booking' ],
			'choices' => [
				'scene'       => 'taxi',
				'scene_color' => 'accent',
				'pose'        => 'phone',
				'seatbelt'    => 'on',
				'top'         => 'hoodie',
				'hair'        => 'wavy',
				'mouth'       => 'smile',
				'cheeks'      => 'both',
			],
		],
		'support'   => [
			'label'   => 'Friendly support agent',
			'words'   => [ 'support', 'help', 'agent', 'call', 'contact', 'service', 'advice' ],
			'choices' => [
				'scene'     => 'wash',
				'headwear'  => 'headset',
				'top'       => 'shirt',
				'hair'      => 'ponytail',
				'mouth'     => 'smile',
				'eyes'      => 'almond',
				'top_color' => 'primary-light',
			],
		],
		'teacher'   => [
			'label'   => 'Teacher',
			'words'   => [ 'teacher', 'trainer', 'tutor', 'lesson', 'course', 'learn', 'student', 'study' ],
			'choices' => [
				'scene'     => 'wash',
				'glasses'   => 'round',
				'top'       => 'blazer',
				'hair'      => 'bun',
				'pose'      => 'wave',
				'mouth'     => 'grin',
				'top_color' => 'secondary',
			],
		],
		'greeter'   => [
			'label'   => 'Welcoming host',
			'words'   => [ 'welcome', 'hello', 'host', 'greet', 'hi', 'new', 'start' ],
			'choices' => [
				'scene'    => 'wash',
				'pose'     => 'wave',
				'mouth'    => 'grin',
				'eyes'     => 'happy',
				'top'      => 'tee',
				'hair'     => 'long',
				'earrings' => 'hoops',
			],
		],
	];

	private const WOMAN_HAIR = [ 'shoulder', 'long', 'bob', 'wavy', 'ponytail', 'bun', 'curly' ];
	private const MAN_HAIR   = [ 'short', 'curly', 'buzz', 'bald', 'short' ];

	/**
	 * Presets for the page.
	 *
	 * @return array<int, array{id: string, label: string, choices: array<string, string>}>
	 */
	public static function all(): array {
		$list = [];
		foreach ( self::ROLES as $id => $role ) {
			$list[] = [
				'id'      => $id,
				'label'   => $role['label'],
				'choices' => $role['choices'],
			];
		}

		return $list;
	}

	/**
	 * The role a description suggests: the one whose words match most, the role's own name counting most.
	 *
	 * @param string   $text     Description.
	 * @param Keywords $keywords Tokenizer.
	 * @return string|null Role ID.
	 */
	public static function role_for( string $text, Keywords $keywords ): ?string {
		$tokens = array_flip( $keywords->tokenize( $text ) );
		$best   = null;
		$score  = 0;

		foreach ( self::ROLES as $id => $role ) {
			$hits = 0;
			foreach ( $role['words'] as $position => $word ) {
				$hits += isset( $tokens[ $keywords->tokenize( $word )[0] ?? $word ] ) ? ( $position < 2 ? 3 : 1 ) : 0;
			}
			if ( $hits > $score ) {
				$best  = $id;
				$score = $hits;
			}
		}

		return $best;
	}

	/**
	 * The spec for a role.
	 *
	 * @param string $role Role ID.
	 * @return PortraitSpec
	 */
	public static function spec_for( string $role ): PortraitSpec {
		return PortraitSpec::from_array( self::ROLES[ $role ]['choices'] ?? [] );
	}

	/**
	 * A varied portrait from a seed (the same seed always gives the same one).
	 *
	 * @param int    $seed   Seed.
	 * @param string $gender "any", "woman" or "man": limits hair and facial hair to that pool.
	 * @return PortraitSpec
	 */
	public static function shuffled( int $seed, string $gender = 'any' ): PortraitSpec {
		$random = new Seed( $seed );
		$pick   = static function ( array $choices ) use ( $random ): string {
			$keys = array_values( array_map( 'strval', array_keys( $choices ) ) );

			return $keys[ $random->int( 0, count( $keys ) - 1 ) ];
		};

		$chosen = [];
		foreach ( PortraitSpec::options() as $field => $allowed ) {
			if ( 'hair' === $field ) {
				$pool    = 'woman' === $gender ? self::WOMAN_HAIR : ( 'man' === $gender ? self::MAN_HAIR : array_keys( $allowed ) );
				$allowed = array_intersect_key( $allowed, array_flip( $pool ) );
			}
			$chosen[ $field ] = $pick( $allowed );
		}

		// Keep it sensible: most people wear no headwear or glasses, and the scene, pose and tilt stay as they are.
		foreach ( [ 'headwear', 'glasses', 'earrings' ] as $field ) {
			if ( $random->int( 0, 2 ) > 0 ) {
				$chosen[ $field ] = 'none';
			}
		}
		if ( 'man' !== $gender && ( 'any' !== $gender || $random->int( 0, 2 ) > 0 ) ) {
			$chosen['facial_hair'] = 'none';
		}
		foreach ( [ 'scene', 'pose', 'tilt', 'seatbelt', 'look' ] as $field ) {
			unset( $chosen[ $field ] );
		}
		$chosen['skin']      = $random->int( 0, PortraitSpec::MAX_TONE );
		$chosen['hair_tone'] = $random->int( 0, PortraitSpec::MAX_TONE );

		return PortraitSpec::from_array( $chosen );
	}
}
