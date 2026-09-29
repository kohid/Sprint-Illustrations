<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Character;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Character\CharacterPresets;
use SprintIllustrations\Character\CharacterSpec;
use SprintIllustrations\Selection\Keywords;

final class CharacterPresetsTest extends TestCase {

	public function test_every_preset_uses_valid_choices(): void {
		foreach ( CharacterPresets::all() as $preset ) {
			$spec = CharacterSpec::from_array( $preset['choices'] );
			foreach ( $preset['choices'] as $field => $value ) {
				$this->assertSame( $value, $spec->get( $field ), $preset['id'] . ' ' . $field );
			}
		}
	}

	public function test_a_description_picks_the_closest_role(): void {
		$keywords = new Keywords();

		$this->assertSame( 'taxi-driver', CharacterPresets::role_for( 'A taxi driver studying for a city knowledge test', $keywords ) );
		$this->assertSame( 'developer', CharacterPresets::role_for( 'Our developers writing code on laptops', $keywords ) );
		$this->assertSame( 'support', CharacterPresets::role_for( 'Customer support chat', $keywords ) );
		$this->assertNull( CharacterPresets::role_for( 'Mountains at sunrise', $keywords ) );
		$this->assertNull( CharacterPresets::role_for( '', $keywords ) );
	}

	public function test_a_role_becomes_a_spec(): void {
		$spec = CharacterPresets::spec_for( 'taxi-driver' );

		$this->assertSame( 'hivis', $spec->get( 'outer' ) );
		$this->assertSame( 'cap', $spec->get( 'headwear' ) );
		$this->assertSame( CharacterSpec::defaults()['build'], CharacterPresets::spec_for( 'nonsense' )->get( 'build' ) );
	}

	public function test_a_shuffle_is_repeatable_and_varied(): void {
		$this->assertEquals( CharacterPresets::shuffled( 7 ), CharacterPresets::shuffled( 7 ) );

		$tops = [];
		for ( $seed = 1; $seed <= 30; $seed++ ) {
			$tops[ CharacterPresets::shuffled( $seed )->get( 'top' ) ] = true;
		}
		$this->assertGreaterThan( 4, count( $tops ) );
	}
}
