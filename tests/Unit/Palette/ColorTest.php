<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Color;

final class ColorTest extends TestCase {

	public function test_normalize_hex(): void {
		$this->assertSame( '#aabbcc', Color::normalize_hex( '#ABC' ) );
		$this->assertSame( '#5b5bd6', Color::normalize_hex( '5B5BD6' ) );
		$this->assertNull( Color::normalize_hex( '#12345' ) );
		$this->assertNull( Color::normalize_hex( 'red' ) );
		$this->assertNull( Color::normalize_hex( 'url(#x)' ) );
	}

	public function test_hsl_round_trip(): void {
		foreach ( [ '#5b5bd6', '#ffb224', '#ff6b6b', '#2b2d42', '#eef0ff', '#000000', '#ffffff', '#808080' ] as $hex ) {
			[ $h, $s, $l ] = Color::to_hsl( $hex );
			$this->assertSame( $hex, Color::from_hsl( $h, $s, $l ), $hex );
		}
	}

	public function test_adjust_lightness(): void {
		$this->assertSame( '#aeaeae', Color::adjust_lightness( '#808080', 0.18 ) );
		$this->assertSame( '#525252', Color::adjust_lightness( '#808080', -0.18 ) );
		$this->assertSame( '#f5f5f5', Color::adjust_lightness( '#ffffff', 0.18 ) );
	}
}
