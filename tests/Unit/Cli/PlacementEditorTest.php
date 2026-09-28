<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cli\PlacementEditor;

final class PlacementEditorTest extends TestCase {

	private static function block( ?string $name, array $attrs = [], array $inner = [] ): array {
		return [
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $inner ), null ),
		];
	}

	private static function page(): array {
		return [
			self::block( 'core/heading' ),
			self::block( PlacementEditor::BLOCK, [ 'template' => 'a' ] ),
			self::block(
				'core/group',
				[],
				[
					self::block( 'core/paragraph' ),
					self::block(
						PlacementEditor::BLOCK,
						[
							'template' => 'b',
							'seed'     => 4,
						]
					),
				]
			),
		];
	}

	public function test_counts_nested_blocks(): void {
		$this->assertSame( 2, PlacementEditor::count_blocks( self::page() ) );
	}

	public function test_updates_nth_block_including_nested_and_keeps_other_attrs(): void {
		$result = PlacementEditor::blocks(
			self::page(),
			2,
			'',
			[
				'template' => 'z',
				'keywords' => 'team',
			]
		);

		$this->assertSame( '', $result['error'] );
		$this->assertSame( 1, $result['changed'] );
		$this->assertSame(
			[
				'template' => 'z',
				'seed'     => 4,
				'keywords' => 'team',
			],
			$result['blocks'][2]['innerBlocks'][1]['attrs']
		);
		$this->assertSame( [ 'template' => 'a' ], $result['blocks'][1]['attrs'] );
	}

	public function test_index_out_of_range(): void {
		$result = PlacementEditor::blocks( self::page(), 3, '', [ 'template' => 'z' ] );

		$this->assertSame( 0, $result['changed'] );
		$this->assertStringContainsString( '2', $result['error'] );
	}

	public function test_insert_top_and_bottom(): void {
		$top    = PlacementEditor::blocks( self::page(), null, 'top', [ 'template' => 'new' ] );
		$bottom = PlacementEditor::blocks( self::page(), null, 'bottom', [ 'template' => 'new' ] );

		$this->assertSame( PlacementEditor::BLOCK, $top['blocks'][0]['blockName'] );
		$this->assertSame( [ 'template' => 'new' ], $top['blocks'][0]['attrs'] );
		$this->assertSame( PlacementEditor::BLOCK, end( $bottom['blocks'] )['blockName'] );
		$this->assertSame( 3, PlacementEditor::count_blocks( $top['blocks'] ) );
		$this->assertSame( 1, $bottom['changed'] );
		$this->assertNotSame( '', PlacementEditor::blocks( self::page(), null, 'middle', [] )['error'] );
	}

	public function test_updates_nth_elementor_widget_in_nested_containers(): void {
		$widget   = static fn( string $id, string $type, array $settings = [] ): array => [
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => [],
		];
		$elements = [
			[
				'id'       => 'c1',
				'elType'   => 'container',
				'settings' => [],
				'elements' => [
					$widget( 'h', 'heading', [ 'title' => 'Hi' ] ),
					$widget( 'w1', PlacementEditor::WIDGET, [ 'template' => 'a' ] ),
					[
						'id'       => 'c2',
						'elType'   => 'container',
						'settings' => [],
						'elements' => [
							$widget(
								'w2',
								PlacementEditor::WIDGET,
								[
									'template' => 'b',
									'palette'  => 'site',
								]
							),
						],
					],
				],
			],
		];

		$this->assertSame( 2, PlacementEditor::count_widgets( $elements ) );

		$result = PlacementEditor::elementor(
			$elements,
			2,
			[
				'template'        => 'z',
				'illustration_id' => '0',
			]
		);
		$this->assertSame( 1, $result['changed'] );
		$this->assertSame(
			[
				'template'        => 'z',
				'palette'         => 'site',
				'illustration_id' => '0',
			],
			$result['elements'][0]['elements'][2]['elements'][0]['settings']
		);
		$this->assertSame( [ 'title' => 'Hi' ], $result['elements'][0]['elements'][0]['settings'] );

		$this->assertStringContainsString( '2', PlacementEditor::elementor( $elements, 5, [] )['error'] );
	}
}
