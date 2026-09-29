<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneResolver;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Compose\TemplateFromScene;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Services;

final class TemplateFromSceneTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function saved( array $spec ): array {
		$spec  = SceneSpec::from_array( $spec );
		$scene = ( new SceneResolver( $this->services->manifest ) )->resolve( $this->services->templates->get( (string) $spec->template ), $spec, [] );

		return TemplateFromScene::build( 'my-layout', 'My layout', $scene );
	}

	public function test_layers_become_slots_in_order_with_held_pieces_attached(): void {
		$data = $this->saved(
			[
				'template' => 'fixture-duo',
				'seed'     => 3,
				'canvas'   => [ 800, 300 ],
				'items'    => [
					[
						'key'   => 2,
						'piece' => 'obj-plant',
						'x'     => 10,
						'y'     => 20,
						'w'     => 40,
						'flip'  => true,
					],
				],
				'layers'   => [ 'right', 'left', 'item:2' ],
			]
		);

		$this->assertSame( [ 800, 300 ], $data['canvas'] );
		$this->assertSame( [ 'right', 'right-prop', 'left', 'left-prop', 'piece-2' ], array_column( $data['slots'], 'name' ) );
		$this->assertSame(
			[
				'to'     => 'left',
				'anchor' => 'hold',
			],
			$data['slots'][3]['attach']
		);
		$this->assertSame( [ 10.0, 20.0, 40.0, 80.0 ], $data['slots'][4]['box'] );
		$this->assertTrue( $data['slots'][4]['flip'] );
		$this->assertLessThan( $data['slots'][2]['z'], $data['slots'][0]['z'] );
		$this->assertNotEmpty( $data['slots'][4]['prefer'] );

		Template::from_array( $data );
	}

	public function test_saved_template_composes_cleanly_and_keeps_the_layout(): void {
		$original = [
			'template' => 'fixture-duo',
			'seed'     => 3,
		];
		$data     = $this->saved( $original );
		$dir      = sys_get_temp_dir() . '/si-tpl-' . uniqid();
		mkdir( $dir );
		file_put_contents( $dir . '/my-layout.json', json_encode( $data ) );

		$services = Services::create( __DIR__ . '/../../fixtures/library', [], [ $dir ] );
		$before   = $services->composer->compose( SceneSpec::from_array( $original ), Palette::default() );
		$after    = $services->composer->compose(
			SceneSpec::from_array(
				[
					'template' => 'my-layout',
					'seed'     => 3,
					'picks'    => [
						'left'  => $before->spec->picks['left'],
						'right' => $before->spec->picks['right'],
					],
				]
			),
			Palette::default()
		);

		$this->assertSame( [], $after->warnings );
		$this->assertEqualsWithDelta( $before->boxes['left'][0][0], $after->boxes['left'][0][0], 0.05 );
		$this->assertEqualsWithDelta( $before->boxes['left'][0][2], $after->boxes['left'][0][2], 0.05 );

		unlink( $dir . '/my-layout.json' );
		rmdir( $dir );
	}
}
