<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Svg;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Svg\SvgDom;
use SprintIllustrations\Svg\SvgException;

final class SvgDomTest extends TestCase {

	public function test_parses_svg_and_drops_formatting_whitespace(): void {
		$doc = SvgDom::parse( "<svg xmlns=\"http://www.w3.org/2000/svg\">\n  <rect/>\n</svg>" );

		$this->assertSame( 'svg', $doc->documentElement->localName );
		$this->assertSame( 1, $doc->documentElement->childNodes->length );
	}

	/**
	 * @dataProvider invalid_markup
	 */
	public function test_rejects_non_svg( string $markup ): void {
		$this->expectException( SvgException::class );
		SvgDom::parse( $markup );
	}

	public static function invalid_markup(): array {
		return [
			'empty'     => [ '' ],
			'broken'    => [ '<svg><rect></svg>' ],
			'not svg'   => [ '<html></html>' ],
			'plain txt' => [ 'hello' ],
		];
	}

	public function test_num_formats_compactly(): void {
		$this->assertSame( '12', SvgDom::num( 12.0 ) );
		$this->assertSame( '12.5', SvgDom::num( 12.5 ) );
		$this->assertSame( '0.33', SvgDom::num( 1 / 3 ) );
		$this->assertSame( '0', SvgDom::num( -0.001 ) );
		$this->assertSame( '1.2346', SvgDom::num( 1.23456, 4 ) );
	}
}
