<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\ColorValue;

final class ColorValueTest extends TestCase {

	public static function values(): array {
		return [
			'short hex'        => [ '#ABC', '#aabbcc' ],
			'long hex'         => [ '#6EC1E4', '#6ec1e4' ],
			'hex with alpha'   => [ '#6ec1e480', '#6ec1e4' ],
			'short hex alpha'  => [ '#abcd', '#aabbcc' ],
			'padded'           => [ '  #6ec1e4 ', '#6ec1e4' ],
			'rgb commas'       => [ 'rgb(110, 193, 228)', '#6ec1e4' ],
			'rgba'             => [ 'rgba(110,193,228,0.5)', '#6ec1e4' ],
			'rgb space syntax' => [ 'rgb(110 193 228 / 50%)', '#6ec1e4' ],
			'rgb out of range' => [ 'rgb(300, 0, 0)', null ],
			'css variable'     => [ 'var(--e-global-color-primary)', null ],
			'hsl'              => [ 'hsl(0 0% 0%)', null ],
			'named colour'     => [ 'red', null ],
			'empty'            => [ '', null ],
			'hex without hash' => [ '6ec1e4', null ],
		];
	}

	#[DataProvider( 'values' )]
	public function test_to_hex( string $css, ?string $expected ): void {
		$this->assertSame( $expected, ColorValue::to_hex( $css ) );
	}
}
