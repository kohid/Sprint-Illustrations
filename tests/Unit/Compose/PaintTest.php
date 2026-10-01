<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\Paint;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Services;

final class PaintTest extends TestCase {

	public function test_normalize_keeps_valid_paints_and_drops_the_rest(): void {
		$paints = Paint::normalize(
			[
				'left'    => [
					'primary'   => '#ABCDEF',
					'secondary' => [
						'from'  => '#000000',
						'to'    => '#ffffff',
						'angle' => -90,
					],
					'accent'    => 'red',
					'skin'      => '#123456',
					'hair'      => [
						'from' => '#000000',
						'to'   => '#ffffff',
					],
				],
				'BAD KEY' => [ 'primary' => '#111111' ],
				'right'   => [ 'primary' => 'nope' ],
			]
		);

		$this->assertSame(
			[
				'left' => [
					'primary'   => '#abcdef',
					'secondary' => [
						'from'  => '#000000',
						'to'    => '#ffffff',
						'angle' => 270,
					],
					'skin'      => '#123456',
				],
			],
			$paints
		);
	}

	public function test_spec_round_trips_and_omits_empty_paints(): void {
		$this->assertArrayNotHasKey( 'paints', SceneSpec::from_array( [ 'template' => 'fixture-duo' ] )->to_array() );

		$spec = SceneSpec::from_array( [ 'paints' => [ 'left' => [ 'primary' => '#ff0000' ] ] ] );
		$this->assertSame( [ 'left' => [ 'primary' => '#ff0000' ] ], SceneSpec::from_array( $spec->to_array() )->paints );
	}

	public function test_solid_and_gradient_paints_reach_the_output(): void {
		$services = Services::create( __DIR__ . '/../../fixtures/library' );
		$base     = [
			'template' => 'fixture-duo',
			'seed'     => 3,
		];
		$every    = static function ( mixed $paint ): array {
			$slots = array_fill_keys( Paint::SLOTS, $paint );
			return [
				'left'  => $slots,
				'right' => $slots,
			];
		};
		$compose  = static fn( array $extra ) => $services->composer->compose( SceneSpec::from_array( $base + $extra ), Palette::default() );

		$plain    = $compose( [] );
		$solid    = $compose( [ 'paints' => $every( '#ff0000' ) ] );
		$gradient = $compose(
			[
				'paints' => $every(
					[
						'from'  => '#ff0000',
						'to'    => '#0000ff',
						'angle' => 45,
					]
				),
			]
		);

		$this->assertNotSame( $plain->markup, $solid->markup );
		$this->assertStringContainsString( '#ff0000', $solid->markup );
		$this->assertStringContainsString( '<linearGradient', $gradient->markup );
		$this->assertStringContainsString( 'stop-color="#0000ff"', $gradient->markup );
		$this->assertMatchesRegularExpression( '/fill="url\(#__SIID__-p\d+-si-paint-[a-z]+\)"/', $gradient->markup );
	}

	public function test_skin_and_hair_can_be_set_for_a_character(): void {
		$services = Services::create( __DIR__ . '/../../fixtures/library' );
		$compose  = static fn( array $extra ) => $services->composer->compose(
			SceneSpec::from_array(
				[
					'template' => 'fixture-duo',
					'seed'     => 3,
				] + $extra
			),
			Palette::default()
		);

		$plain = $compose( [] );
		$set   = $compose(
			[
				'paints' => [
					'left'  => [
						'skin' => '#112233',
						'hair' => '#445566',
					],
					'right' => [
						'skin' => '#112233',
						'hair' => '#445566',
					],
				],
			]
		);

		$this->assertStringNotContainsString( '#112233', $plain->markup );
		$this->assertStringContainsString( '#112233', $set->markup );
		$this->assertStringContainsString( '#445566', $set->markup );
	}
}
