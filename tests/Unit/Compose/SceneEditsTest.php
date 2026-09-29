<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\Placement;
use SprintIllustrations\Compose\SceneResolver;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Services;

/**
 * Free items, layer order and canvas size (phase 8).
 */
final class SceneEditsTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	private function compose( array $spec ): ComposedSvg {
		return $this->services->composer->compose( SceneSpec::from_array( $spec + [ 'template' => 'fixture-duo' ] ), Palette::default() );
	}

	/**
	 * @return array<string>
	 */
	private function slots_in_order( array $spec ): array {
		$template = $this->services->templates->get( 'fixture-duo' );
		$scene    = ( new SceneResolver( $this->services->manifest ) )->resolve( $template, SceneSpec::from_array( $spec + [ 'template' => 'fixture-duo' ] ), [] );

		return array_map( static fn( Placement $p ): string => $p->slot, $scene->placements );
	}

	private function item( int $key, string $piece, float $x = 100, float $y = 50, float $w = 80 ): array {
		return [
			'key'   => $key,
			'piece' => $piece,
			'x'     => $x,
			'y'     => $y,
			'w'     => $w,
		];
	}

	public function test_item_is_placed_on_top_with_its_box(): void {
		$result = $this->compose(
			[
				'seed'  => 3,
				'items' => [ $this->item( 0, 'obj-plant' ) ],
			]
		);

		$this->assertSame( [ [ 100.0, 50.0, 80.0, 160.0 ] ], $result->boxes['item:0'] );
		$this->assertSame( [ 'left', 'right', 'item:0' ], $result->layers );
		$this->assertSame( [], $result->warnings );
	}

	public function test_unknown_item_piece_is_left_out_with_a_warning(): void {
		$result = $this->compose( [ 'items' => [ $this->item( 0, 'obj-nope' ) ] ] );

		$this->assertArrayNotHasKey( 'item:0', $result->boxes );
		$this->assertStringContainsString( 'obj-nope', implode( ' ', $result->warnings ) );
	}

	public function test_item_look_does_not_depend_on_other_items(): void {
		$alone = $this->compose( [ 'items' => [ $this->item( 1, 'char-stick', 0, 0, 100 ) ] ] );
		$pair  = $this->compose( [ 'items' => [ $this->item( 0, 'char-runner', 200, 0, 100 ), $this->item( 1, 'char-stick', 0, 0, 100 ) ] ] );

		$last = static function ( string $markup ): string {
			preg_match_all( '/<g transform="translate\(0 0\)[^"]*">.*?<\/g>/s', $markup, $groups );
			return (string) preg_replace( '/-p\d+-/', '-p-', (string) end( $groups[0] ) );
		};

		$this->assertNotSame( '', $last( $alone->markup ) );
		$this->assertSame( $last( $alone->markup ), $last( $pair->markup ) );
	}

	public function test_canvas_scales_and_centres_the_template(): void {
		$base  = $this->compose( [ 'seed' => 3 ] );
		$wider = $this->compose(
			[
				'seed'   => 3,
				'canvas' => [ 800, 300 ],
			]
		);

		$this->assertStringContainsString( 'viewBox="0 0 800 300"', $wider->markup );
		$this->assertEqualsWithDelta( $base->boxes['left'][0][0] + 200, $wider->boxes['left'][0][0], 0.01 );
		$this->assertEqualsWithDelta( $base->boxes['left'][0][2], $wider->boxes['left'][0][2], 0.01 );

		$bigger = $this->compose(
			[
				'seed'   => 3,
				'canvas' => [ 800, 600 ],
			]
		);
		$this->assertEqualsWithDelta( $base->boxes['left'][0][2] * 2, $bigger->boxes['left'][0][2], 0.01 );
	}

	public function test_natural_order_without_layers(): void {
		// Exactly the z order used before layers existed (props share a z level, so they interleave).
		$this->assertSame( [ 'left', 'right', 'left-prop', 'right-prop' ], $this->slots_in_order( [ 'seed' => 3 ] ) );
	}

	public function test_layers_reorder_groups_and_attached_slots_follow(): void {
		$this->assertSame(
			[ 'right', 'right-prop', 'left', 'left-prop' ],
			$this->slots_in_order(
				[
					'seed'   => 3,
					'layers' => [ 'right', 'left' ],
				]
			)
		);
	}

	public function test_unlisted_groups_keep_their_place(): void {
		$this->assertSame(
			[ 'item:0', 'right', 'right-prop', 'left', 'left-prop' ],
			$this->slots_in_order(
				[
					'seed'   => 3,
					'items'  => [ $this->item( 0, 'obj-orb' ) ],
					'layers' => [ 'item:0', 'left' ],
				]
			)
		);
	}

	public function test_edits_survive_the_cache_format(): void {
		$result = $this->compose(
			[
				'seed'   => 3,
				'items'  => [ $this->item( 0, 'obj-orb' ) ],
				'layers' => [ 'item:0', 'left', 'right' ],
			]
		);
		$copy   = ComposedSvg::from_array( $result->to_array() );

		$this->assertNotNull( $copy );
		$this->assertSame( [ 'item:0', 'left', 'right' ], $copy->layers );
		$this->assertSame( $result->spec->items, $copy->spec->items );
	}
}
