<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Svg;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Svg\Affine;
use SprintIllustrations\Svg\SvgDom;

final class AffineTest extends TestCase {

	private function assertPoint( array $expected, array $actual ): void {
		$this->assertEqualsWithDelta( $expected[0], $actual[0], 0.0001 );
		$this->assertEqualsWithDelta( $expected[1], $actual[1], 0.0001 );
	}

	public function test_translate_and_scale_compose_left_to_right(): void {
		$this->assertPoint( [ 12, 25 ], Affine::parse( 'translate(10 5) scale(2)' )->apply( 1, 10 ) );
		$this->assertPoint( [ 22, 20 ], Affine::parse( 'scale(2) translate(10,5)' )->apply( 1, 5 ) );
	}

	public function test_rotate_about_centre(): void {
		$this->assertPoint( [ 10, 20 ], Affine::parse( 'rotate(90 10 10)' )->apply( 20, 10 ) );
	}

	public function test_matrix_and_unknown_functions(): void {
		$this->assertPoint( [ 7, 9 ], Affine::parse( 'matrix(1 0 0 1 5 6)' )->apply( 2, 3 ) );
		$this->assertPoint( [ 2, 3 ], Affine::parse( 'skewX(30)' )->apply( 2, 3 ) );
	}

	public function test_for_element_includes_ancestors_but_not_root(): void {
		$doc    = SvgDom::parse( '<svg xmlns="http://www.w3.org/2000/svg" transform="scale(100)"><g transform="translate(10 0)"><g transform="scale(2)"><circle id="c" transform="translate(1 1)" cx="3" cy="4"/></g></g></svg>' );
		$circle = $doc->getElementsByTagName( 'circle' )->item( 0 );

		$this->assertPoint( [ 18, 10 ], Affine::for_element( $circle )->apply( 3, 4 ) );
	}
}
