<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneArrangement;
use SprintIllustrations\Compose\SceneSpec;

final class SceneArrangementTest extends TestCase {

	public function test_pieces_sit_in_a_row_inside_the_canvas_without_overlapping(): void {
		$items = SceneArrangement::row(
			[
				'obj-drone'  => [ 100.0, 60.0 ],
				'obj-parcel' => [ 50.0, 50.0 ],
				'obj-bike'   => [ 200.0, 120.0 ],
			],
			[ 800, 600 ]
		);

		$this->assertCount( 3, $items );
		$right = -1.0;
		foreach ( $items as $index => $item ) {
			$this->assertSame( $index, $item['key'] );
			$this->assertGreaterThanOrEqual( $right, $item['x'] );
			$this->assertGreaterThanOrEqual( 0, $item['x'] );
			$this->assertLessThanOrEqual( 800, $item['x'] + $item['w'] );
			$this->assertGreaterThan( 0, $item['y'] );
			$right = $item['x'] + $item['w'];
		}
	}

	public function test_keys_skip_those_taken_and_items_are_capped(): void {
		$items = SceneArrangement::row( [ 'obj-a' => [ 10.0, 10.0 ] ], [ 400, 300 ], [ 0, 1 ] );
		$this->assertSame( 2, $items[0]['key'] );

		$taken = range( 0, SceneSpec::MAX_ITEMS - 1 );
		$this->assertSame( [], SceneArrangement::row( [ 'obj-a' => [ 10.0, 10.0 ] ], [ 400, 300 ], $taken ) );
	}

	public function test_empty_and_degenerate_sizes_give_no_items(): void {
		$this->assertSame( [], SceneArrangement::row( [], [ 400, 300 ] ) );
		$this->assertSame( [], SceneArrangement::row( [ 'obj-a' => [ 0.0, 10.0 ] ], [ 400, 300 ] ) );
	}
}
