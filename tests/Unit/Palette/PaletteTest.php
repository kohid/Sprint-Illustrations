<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Color;
use SprintIllustrations\Palette\Palette;

final class PaletteTest extends TestCase {

	public function test_resolves_base_slots(): void {
		$palette = Palette::default();

		$this->assertSame( '#5b5bd6', $palette->resolve( 'primary' ) );
		$this->assertSame( '#2b2d42', $palette->resolve( 'outline' ) );
		$this->assertNull( $palette->resolve( 'nope' ) );
	}

	public function test_derives_variants_unless_explicit(): void {
		$palette = Palette::from_array( [ 'primary-dark' => '#111111' ] );

		$this->assertSame( '#111111', $palette->resolve( 'primary', 'dark' ) );
		$this->assertSame( Color::adjust_lightness( '#5b5bd6', 0.18 ), $palette->resolve( 'primary', 'light' ) );
	}

	public function test_skin_and_hair_wrap_by_index(): void {
		$palette = Palette::from_array(
			[
				'skin' => [ '#111111', '#222222' ],
				'hair' => [ '#333333' ],
			]
		);

		$this->assertSame( '#111111', $palette->resolve( 'skin', '', 0 ) );
		$this->assertSame( '#222222', $palette->resolve( 'skin', '', 3 ) );
		$this->assertSame( '#333333', $palette->resolve( 'hair', '', 0, 41 ) );
		$this->assertSame( Color::adjust_lightness( '#222222', -0.18 ), $palette->resolve( 'skin', 'dark', 1 ) );
	}

	public function test_from_array_ignores_invalid_values(): void {
		$palette = Palette::from_array(
			[
				'primary'   => 'javascript:alert(1)',
				'secondary' => '#ABC',
				'bogus'     => '#123456',
				'skin'      => [ 'nope' ],
			]
		);

		$this->assertSame( '#5b5bd6', $palette->resolve( 'primary' ) );
		$this->assertSame( '#aabbcc', $palette->resolve( 'secondary' ) );
		$this->assertArrayNotHasKey( 'bogus', $palette->to_array() );
		$this->assertSame( Palette::default()->to_array()['skin'], $palette->to_array()['skin'] );
	}

	public function test_round_trip_and_hash(): void {
		$palette = Palette::from_array( [ 'accent' => '#00ff00' ] );

		$this->assertSame( $palette->to_array(), Palette::from_array( $palette->to_array() )->to_array() );
		$this->assertSame( $palette->hash(), Palette::from_array( $palette->to_array() )->hash() );
		$this->assertNotSame( $palette->hash(), Palette::default()->hash() );
	}
}
