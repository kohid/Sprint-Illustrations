<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneSpec;

final class SceneSpecEditsTest extends TestCase {

	public function test_old_spec_array_is_unchanged(): void {
		$array = SceneSpec::from_array(
			[
				'template' => 'fixture-duo',
				'seed'     => 2,
				'items'    => [],
				'layers'   => [],
			]
		)->to_array();

		$this->assertSame( [ 'decorative', 'keywords', 'palette', 'picks', 'seed', 'template', 'title' ], array_keys( $array ) );
	}

	public function test_canvas_is_clamped_or_dropped(): void {
		$this->assertSame( [ 200, 4000 ], SceneSpec::from_array( [ 'canvas' => [ 100, 5000 ] ] )->canvas );
		$this->assertSame( [ 801, 600 ], SceneSpec::from_array( [ 'canvas' => [ 800.6, '600' ] ] )->canvas );
		$this->assertNull( SceneSpec::from_array( [ 'canvas' => 'big' ] )->canvas );
		$this->assertNull( SceneSpec::from_array( [ 'canvas' => [ 800 ] ] )->canvas );
	}

	public function test_items_are_normalized(): void {
		$spec = SceneSpec::from_array(
			[
				'items' => [
					[
						'piece' => 'obj-mug',
						'x'     => 10.456,
						'y'     => -9000,
						'w'     => 0,
					],
					[
						'key'   => 0,
						'piece' => 'obj-plant',
						'x'     => 1,
						'y'     => 2,
						'w'     => 30,
						'flip'  => 'true',
					],
					[
						'piece' => 'Bad Id',
						'x'     => 1,
						'y'     => 1,
						'w'     => 1,
					],
					'nope',
				],
			]
		);

		$this->assertSame(
			[
				[
					'key'   => 0,
					'piece' => 'obj-mug',
					'x'     => 10.46,
					'y'     => -8000.0,
					'w'     => 1.0,
					'flip'  => false,
				],
				[
					'key'   => 1,
					'piece' => 'obj-plant',
					'x'     => 1.0,
					'y'     => 2.0,
					'w'     => 30.0,
					'flip'  => true,
				],
			],
			$spec->items
		);
	}

	public function test_items_are_limited(): void {
		$items = array_fill(
			0,
			40,
			[
				'piece' => 'obj-mug',
				'x'     => 0,
				'y'     => 0,
				'w'     => 10,
			]
		);

		$this->assertCount( SceneSpec::MAX_ITEMS, SceneSpec::from_array( [ 'items' => $items ] )->items );
	}

	public function test_layers_are_normalized(): void {
		$spec = SceneSpec::from_array( [ 'layers' => [ 'bg', 'item:3', 'bg', 'Bad!', 'item:100', 7 ] ] );

		$this->assertSame( [ 'bg', 'item:3' ], $spec->layers );
	}

	public function test_round_trip_keeps_edits(): void {
		$spec = SceneSpec::from_array(
			[
				'template' => 'fixture-duo',
				'canvas'   => [ 800, 300 ],
				'items'    => [
					[
						'key'   => 4,
						'piece' => 'obj-mug',
						'x'     => 5,
						'y'     => 6,
						'w'     => 20,
					],
				],
				'layers'   => [ 'item:4', 'left' ],
			]
		);
		$next = $spec->with_seed( 9 )->with_picks( [ 'left' => 'char-stick' ] );

		$this->assertSame( $spec->canvas, $next->canvas );
		$this->assertSame( $spec->items, $next->items );
		$this->assertSame( $spec->layers, $next->layers );
		$this->assertSame( $next->to_array(), SceneSpec::from_array( $next->to_array() )->to_array() );
	}
}
