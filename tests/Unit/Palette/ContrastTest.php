<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Contrast;
use SprintIllustrations\Palette\Palette;

final class ContrastTest extends TestCase {

	public function test_known_ratios(): void {
		$this->assertEqualsWithDelta( 21.0, Contrast::ratio( '#000000', '#ffffff' ), 0.01 );
		$this->assertEqualsWithDelta( 1.0, Contrast::ratio( '#5b5bd6', '#5b5bd6' ), 0.001 );
		$this->assertEqualsWithDelta( Contrast::ratio( '#ffffff', '#767676' ), Contrast::ratio( '#767676', '#ffffff' ), 0.0001 );
		$this->assertEqualsWithDelta( 4.54, Contrast::ratio( '#767676', '#ffffff' ), 0.01 );
	}

	public function test_invalid_hex_counts_as_black(): void {
		$this->assertEqualsWithDelta( 21.0, Contrast::ratio( 'nope', '#fff' ), 0.01 );
	}

	public function test_default_palette_has_no_warnings(): void {
		$this->assertSame( [], Palette::default()->warnings() );
	}

	public function test_low_contrast_base_slot_is_flagged(): void {
		$warnings = Palette::from_array(
			[
				'primary'    => '#f4f4f4',
				'background' => '#ffffff',
			]
		)->warnings();

		$this->assertSame( [ 'primary' ], array_keys( $warnings ) );
		$this->assertStringContainsString( 'Low contrast against background (1.1:1)', $warnings['primary'] );
	}
}
