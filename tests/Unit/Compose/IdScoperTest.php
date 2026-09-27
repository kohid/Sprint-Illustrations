<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\IdScoper;
use SprintIllustrations\Svg\SvgDom;

final class IdScoperTest extends TestCase {

	public function test_prefixes_ids_and_rewrites_references(): void {
		$doc   = SvgDom::parse(
			'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><g>'
			. '<linearGradient id="g"/><clipPath id="c"/>'
			. '<rect fill="url(#g)" clip-path="url( \'#c\' )" mask="url(#other)"/>'
			. '<use href="#c"/><use xlink:href="#g"/><use href="#external"/>'
			. '<text aria-labelledby="g c x"/>'
			. '</g></svg>'
		);
		$group = $doc->getElementsByTagName( 'g' )->item( 0 );

		( new IdScoper() )->scope( $group, 'P-' );

		$xml = $doc->saveXML( $group );

		$this->assertStringContainsString( 'id="P-g"', $xml );
		$this->assertStringContainsString( 'id="P-c"', $xml );
		$this->assertStringContainsString( 'fill="url(#P-g)"', $xml );
		$this->assertStringContainsString( 'clip-path="url(#P-c)"', $xml );
		$this->assertStringContainsString( 'mask="url(#other)"', $xml );
		$this->assertStringContainsString( '<use href="#P-c"/>', $xml );
		$this->assertStringContainsString( 'xlink:href="#P-g"', $xml );
		$this->assertStringContainsString( '<use href="#external"/>', $xml );
		$this->assertStringContainsString( 'aria-labelledby="P-g P-c x"', $xml );
	}

	public function test_no_ids_is_a_no_op(): void {
		$doc    = SvgDom::parse( '<svg xmlns="http://www.w3.org/2000/svg"><g><rect fill="url(#a)"/></g></svg>' );
		$before = $doc->saveXML();

		( new IdScoper() )->scope( $doc->documentElement, 'P-' );

		$this->assertSame( $before, $doc->saveXML() );
	}
}
