<?php
/**
 * Starting points: role presets, a description-to-character helper and a seeded shuffle.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Character;

use SprintIllustrations\Compose\Seed;
use SprintIllustrations\Selection\Keywords;

/**
 * Lets a character start close to what a page is about ("a taxi driver studying for a test") instead of
 * from the default figure. Everything stays editable in the builder afterwards.
 */
final class CharacterPresets {

	/**
	 * Role => label, the choices it sets, and the words that suggest it.
	 */
	private const ROLES = [
		'taxi-driver' => [
			'label'   => 'Taxi driver',
			'words'   => [ 'taxi', 'cab', 'driver', 'chauffeur', 'hackney' ],
			'choices' => [
				'top'          => 'polo',
				'outer'        => 'hivis',
				'outer_color'  => 'secondary',
				'headwear'     => 'cap',
				'hair'         => 'short',
				'pose'         => 'relaxed',
				'bottom_color' => 'neutral-dark',
			],
		],
		'courier'     => [
			'label'   => 'Courier',
			'words'   => [ 'courier', 'delivery', 'deliver', 'parcel', 'rider', 'logistics' ],
			'choices' => [
				'top'      => 'tee',
				'outer'    => 'hivis',
				'headwear' => 'cap',
				'hair'     => 'buzz',
				'bag'      => 'backpack',
				'legs'     => 'step',
			],
		],
		'office'      => [
			'label'   => 'Office worker',
			'words'   => [ 'office', 'admin', 'team', 'staff', 'colleague', 'employee', 'work' ],
			'choices' => [
				'top'    => 'shirt',
				'outer'  => 'none',
				'extra'  => 'lanyard',
				'hair'   => 'short',
				'pose'   => 'folder',
				'bottom' => 'slim',
			],
		],
		'manager'     => [
			'label'   => 'Manager',
			'words'   => [ 'manager', 'business', 'finance', 'legal', 'law', 'lawyer', 'formal', 'executive', 'boss' ],
			'choices' => [
				'top'         => 'shirt',
				'outer'       => 'blazer',
				'outer_color' => 'secondary-dark',
				'extra'       => 'tie',
				'hair'        => 'quiff',
				'pose'        => 'crossed',
				'bag'         => 'briefcase',
				'shoes'       => 'shoes',
			],
		],
		'developer'   => [
			'label'   => 'Developer',
			'words'   => [ 'developer', 'code', 'coding', 'programmer', 'software', 'engineer', 'tech', 'laptop' ],
			'choices' => [
				'stance'  => 'sitting',
				'pose'    => 'typing',
				'top'     => 'hoodie',
				'outer'   => 'none',
				'glasses' => 'round',
				'hair'    => 'curly',
			],
		],
		'support'     => [
			'label'   => 'Support agent',
			'words'   => [ 'support', 'help', 'call', 'chat', 'customer', 'service', 'contact', 'enquiry' ],
			'choices' => [
				'stance'   => 'sitting',
				'pose'     => 'wave',
				'top'      => 'polo',
				'outer'    => 'none',
				'headwear' => 'headset',
				'hair'     => 'bob',
			],
		],
		'instructor'  => [
			'label'   => 'Instructor',
			'words'   => [ 'teach', 'teacher', 'instructor', 'lesson', 'course', 'training', 'tutor', 'lecture', 'coach' ],
			'choices' => [
				'top'     => 'shirt',
				'outer'   => 'blazer',
				'glasses' => 'square',
				'pose'    => 'point',
				'hair'    => 'short',
			],
		],
		'student'     => [
			'label'   => 'Student',
			'words'   => [ 'student', 'study', 'studying', 'learn', 'learning', 'exam', 'test', 'knowledge', 'school', 'revision' ],
			'choices' => [
				'top'     => 'sweater',
				'outer'   => 'none',
				'glasses' => 'round',
				'hair'    => 'bun',
				'bag'     => 'backpack',
				'pose'    => 'folder',
				'bottom'  => 'cropped',
			],
		],
		'barista'     => [
			'label'   => 'Barista or chef',
			'words'   => [ 'cafe', 'coffee', 'barista', 'kitchen', 'restaurant', 'chef', 'food', 'baker', 'bakery' ],
			'choices' => [
				'top'   => 'tee',
				'outer' => 'apron',
				'hair'  => 'bun',
				'pose'  => 'hip',
			],
		],
		'greeter'     => [
			'label'   => 'Friendly host',
			'words'   => [ 'welcome', 'host', 'hello', 'greet', 'hospitality', 'reception', 'concierge', 'guide' ],
			'choices' => [
				'top'    => 'blouse',
				'outer'  => 'none',
				'bottom' => 'midi',
				'pose'   => 'present',
				'hair'   => 'ponytail',
			],
		],
	];
	/**
	 * Presets for the builder page.
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
	 * The role a description suggests: the one whose words match most, the role's own name counting most
	 * (ties keep the list order).
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
			// The first two words name the role itself and count triple: "taxi driver studying for a test" is a taxi driver.
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
	 * @return CharacterSpec
	 */
	public static function spec_for( string $role ): CharacterSpec {
		return CharacterSpec::from_array( self::ROLES[ $role ]['choices'] ?? [] );
	}

	/**
	 * A varied character from a seed (the same seed always gives the same one).
	 *
	 * @param int $seed Seed.
	 * @return CharacterSpec
	 */
	public static function shuffled( int $seed ): CharacterSpec {
		$random = new Seed( $seed );
		$pick   = static function ( array $choices ) use ( $random ): string {
			$keys = array_keys( $choices );

			return (string) $keys[ $random->int( 0, count( $keys ) - 1 ) ];
		};

		$stance = 0 === $random->int( 0, 3 ) ? 'sitting' : 'standing';
		$chosen = [ 'stance' => $stance ];
		foreach ( CharacterSpec::options( $stance ) as $field => $allowed ) {
			if ( ! in_array( $field, [ 'stance', 'tag' ], true ) ) {
				$chosen[ $field ] = $pick( $allowed );
			}
		}
		// Keep it sensible: most people wear no headwear, glasses or facial hair, and only some an extra.
		foreach ( [ 'headwear', 'glasses', 'facial_hair', 'extra' ] as $field ) {
			if ( $random->int( 0, 2 ) > 0 ) {
				$chosen[ $field ] = 'none';
			}
		}
		$chosen['skin']      = $random->int( 0, CharacterSpec::MAX_TONE );
		$chosen['hair_tone'] = $random->int( 0, CharacterSpec::MAX_TONE );

		return CharacterSpec::from_array( $chosen );
	}
}
