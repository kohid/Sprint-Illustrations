<?php
/**
 * Starting points for figures: role presets, a description-to-figure helper and a seeded shuffle.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Figure;

use SprintIllustrations\Character\CharacterSpec;
use SprintIllustrations\Compose\Seed;
use SprintIllustrations\Selection\Keywords;

/**
 * Lets a figure start close to what a page is about ("a builder with a clipboard") instead of from the
 * default look. Everything stays editable on the page afterwards.
 */
final class FigurePresets {

	/**
	 * Role => label, the choices it sets, and the words that suggest it (the first two name the role).
	 */
	private const ROLES = [
		'builder' => [
			'label'   => 'Builder with a hard hat',
			'words'   => [ 'builder', 'construction', 'site', 'worker', 'engineer', 'repair' ],
			'choices' => [
				'headwear'       => 'hardhat',
				'headwear_color' => 'accent',
				'hair'           => 'short',
				'top'            => 'jacket',
				'top_color'      => 'secondary',
				'bottom_color'   => 'neutral-dark',
				'pose'           => 'hold',
				'carry'          => 'clipboard',
				'shoes'          => 'boots',
			],
		],
		'courier' => [
			'label'   => 'Courier with a parcel',
			'words'   => [ 'courier', 'delivery', 'parcel', 'deliver', 'rider', 'logistics' ],
			'choices' => [
				'headwear'       => 'cap',
				'headwear_color' => 'primary',
				'hair'           => 'messy',
				'top'            => 'hoodie',
				'top_color'      => 'accent',
				'pose'           => 'hold',
				'carry'          => 'parcel',
				'backpack'       => 'backpack',
			],
		],
		'taxi'    => [
			'label'   => 'Taxi driver',
			'words'   => [ 'taxi', 'driver', 'cab', 'chauffeur', 'hackney', 'passenger' ],
			'choices' => [
				'headwear'       => 'cap',
				'headwear_color' => 'neutral-dark',
				'hair'           => 'short',
				'top'            => 'shirt',
				'top_color'      => 'background',
				'pose'           => 'hips',
				'bottom_color'   => 'neutral-dark',
				'mouth'          => 'grin',
			],
		],
		'student' => [
			'label'   => 'Student with a backpack',
			'words'   => [ 'student', 'learner', 'study', 'school', 'course', 'learn' ],
			'choices' => [
				'hair'     => 'messy',
				'top'      => 'hoodie',
				'pose'     => 'pockets',
				'backpack' => 'backpack',
				'eyewear'  => 'round',
				'eyes'     => 'sparkle',
			],
		],
		'friend'  => [
			'label'   => 'Friendly neighbour',
			'words'   => [ 'friend', 'neighbour', 'welcome', 'hello', 'host', 'community' ],
			'choices' => [
				'hair'      => 'bob',
				'top'       => 'overalls',
				'top_color' => 'background',
				'pose'      => 'wave',
				'headwear'  => 'bucket',
				'mouth'     => 'grin',
				'eyes'      => 'happy',
			],
		],
		'thinker' => [
			'label'   => 'Thinking it over',
			'words'   => [ 'thinking', 'question', 'planning', 'idea', 'decide', 'wonder' ],
			'choices' => [
				'pose'  => 'thinking',
				'hair'  => 'ponytail',
				'top'   => 'tee',
				'brows' => 'raised',
				'mouth' => 'flat',
				'carry' => 'none',
			],
		],
	];

	private const WOMAN_HAIR = [ 'bob', 'long', 'ponytail', 'bun', 'curly', 'short' ];
	private const MAN_HAIR   = [ 'short', 'messy', 'curly', 'bald', 'short' ];

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
	 * @return FigureSpec
	 */
	public static function spec_for( string $role ): FigureSpec {
		return FigureSpec::from_array( self::ROLES[ $role ]['choices'] ?? [] );
	}

	/**
	 * A varied figure from a seed (the same seed always gives the same one).
	 *
	 * @param int    $seed   Seed.
	 * @param string $gender "any", "woman" or "man": limits the hair styles to that pool.
	 * @return FigureSpec
	 */
	public static function shuffled( int $seed, string $gender = 'any' ): FigureSpec {
		$random = new Seed( $seed );
		$pick   = static function ( array $choices ) use ( $random ): string {
			$keys = array_values( array_map( 'strval', array_keys( $choices ) ) );

			return $keys[ $random->int( 0, count( $keys ) - 1 ) ];
		};

		$tokens = array_diff_key( CharacterSpec::COLORS, [ 'background' => 1 ] );
		$chosen = [];
		foreach ( FigureSpec::options() as $field => $allowed ) {
			if ( 'hair' === $field ) {
				$pool    = 'woman' === $gender ? self::WOMAN_HAIR : ( 'man' === $gender ? self::MAN_HAIR : array_keys( $allowed ) );
				$allowed = array_intersect_key( $allowed, array_flip( $pool ) );
			}
			if ( in_array( $field, FigureSpec::COLOR_FIELDS, true ) ) {
				$allowed = $tokens;
			}
			$chosen[ $field ] = $pick( $allowed );
		}

		// Keep it sensible: most figures wear no headwear or glasses and carry no backpack.
		foreach ( [ 'headwear', 'eyewear', 'backpack' ] as $field ) {
			if ( $random->int( 0, 2 ) > 0 ) {
				$chosen[ $field ] = 'none';
			}
		}
		// The frame and what is held belong to the page, not to the look.
		foreach ( [ 'scene', 'scene_color', 'pose', 'carry', 'tilt' ] as $field ) {
			unset( $chosen[ $field ] );
		}
		$chosen['skin']      = $random->int( 0, FigureSpec::MAX_TONE );
		$chosen['hair_tone'] = $random->int( 0, FigureSpec::MAX_TONE );

		return FigureSpec::from_array( $chosen );
	}
}
