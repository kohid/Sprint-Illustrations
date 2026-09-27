<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\Seed;

final class SeedTest extends TestCase {

	public function test_matches_javascript_mulberry32_reference(): void {
		// Reference values from the canonical JS mulberry32 implementation.
		$seed = new Seed( 1 );
		$this->assertSame( '0.6270739406', sprintf( '%.10f', $seed->next() ) );
		$this->assertSame( '0.0027357212', sprintf( '%.10f', $seed->next() ) );
		$this->assertSame( '0.5274470400', sprintf( '%.10f', $seed->next() ) );

		$this->assertSame( '0.6011037519', sprintf( '%.10f', ( new Seed( 42 ) )->next() ) );
	}

	public function test_same_key_same_sequence(): void {
		$a = Seed::from_string( '42|hero|subject' );
		$b = Seed::from_string( '42|hero|subject' );

		for ( $i = 0; $i < 50; $i++ ) {
			$this->assertSame( $a->next(), $b->next() );
		}
	}

	public function test_int_and_float_ranges(): void {
		$seed = new Seed( 7 );

		for ( $i = 0; $i < 500; $i++ ) {
			$int = $seed->int( 2, 5 );
			$this->assertGreaterThanOrEqual( 2, $int );
			$this->assertLessThanOrEqual( 5, $int );

			$float = $seed->float( 0.9, 1.1 );
			$this->assertGreaterThanOrEqual( 0.9, $float );
			$this->assertLessThan( 1.1, $float );
		}
	}

	public function test_int_with_equal_bounds_still_consumes_a_draw(): void {
		$a = new Seed( 3 );
		$b = new Seed( 3 );

		$this->assertSame( 4, $a->int( 4, 4 ) );
		$b->next();
		$this->assertSame( $a->next(), $b->next() );
	}

	public function test_pick(): void {
		$this->assertSame( 'b', ( new Seed( 1 ) )->pick( [ 'a', 'b', 'c' ] ) );

		$this->expectException( \InvalidArgumentException::class );
		( new Seed( 1 ) )->pick( [] );
	}
}
