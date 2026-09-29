<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Character;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Character\CharacterSpec;

final class CharacterSpecTest extends TestCase {

	public function test_unknown_values_fall_back_to_the_defaults(): void {
		$spec = CharacterSpec::from_array(
			[
				'top'      => 'spacesuit',
				'hair'     => [ 'long' ],
				'build'    => 'broad',
				'skin'     => 99,
				'headwear' => 'cap',
			]
		);

		$this->assertSame( 'tee', $spec->get( 'top' ) );
		$this->assertSame( 'short', $spec->get( 'hair' ) );
		$this->assertSame( 'broad', $spec->get( 'build' ) );
		$this->assertSame( 'cap', $spec->get( 'headwear' ) );
		$this->assertSame( CharacterSpec::MAX_TONE, $spec->skin );
	}

	public function test_a_pose_from_the_other_stance_becomes_that_stances_first_pose(): void {
		$this->assertSame( 'relaxed', CharacterSpec::from_array( [ 'pose' => 'lap' ] )->get( 'pose' ) );
		$this->assertSame(
			'lap',
			CharacterSpec::from_array(
				[
					'stance' => 'sitting',
					'pose'   => 'point',
				]
			)->get( 'pose' )
		);
		$this->assertSame(
			'typing',
			CharacterSpec::from_array(
				[
					'stance' => 'sitting',
					'pose'   => 'typing',
				]
			)->get( 'pose' )
		);
	}

	public function test_round_trips_and_with_changes_one_thing(): void {
		$spec = CharacterSpec::from_array(
			[
				'top'     => 'hoodie',
				'glasses' => 'sun',
				'skin'    => 3,
			]
		);

		$this->assertEquals( $spec, CharacterSpec::from_array( $spec->to_array() ) );
		$changed = $spec->with( [ 'hair' => 'long' ] );
		$this->assertSame( 'long', $changed->get( 'hair' ) );
		$this->assertSame( 'hoodie', $changed->get( 'top' ) );
		$this->assertSame( 3, $changed->skin );
	}

	public function test_every_field_offers_options_and_the_default_is_one_of_them(): void {
		foreach ( CharacterSpec::defaults() as $field => $value ) {
			$this->assertArrayHasKey( $value, CharacterSpec::options()[ $field ] ?? [], $field );
		}
	}
}
