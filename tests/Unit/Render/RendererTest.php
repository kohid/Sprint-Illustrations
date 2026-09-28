<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Render;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Render\Renderer;
use SprintIllustrations\Services;

final class RendererTest extends TestCase {

	/** @var array<string|array> */
	private array $palette_refs = [];

	/** @var array<string> */
	private array $logged = [];

	private function renderer(): Renderer {
		return new Renderer(
			Services::create( __DIR__ . '/../../fixtures/library' )->composer,
			function ( string|array $ref ): Palette {
				$this->palette_refs[] = $ref;
				return Palette::default();
			},
			function ( string $message ): void {
				$this->logged[] = $message;
			}
		);
	}

	private function renderer_with_saved(): Renderer {
		return new Renderer(
			Services::create( __DIR__ . '/../../fixtures/library' )->composer,
			static fn(): Palette => Palette::default(),
			function ( string $message ): void {
				$this->logged[] = $message;
			},
			static fn( int $id ): ?\SprintIllustrations\Compose\SceneSpec => 7 === $id
				? \SprintIllustrations\Compose\SceneSpec::from_array(
					[
						'template' => 'fixture-object',
						'seed'     => 11,
						'title'    => 'Saved title',
					]
				)
				: null
		);
	}

	public function test_saved_illustration_wins_over_attributes(): void {
		$html = $this->renderer_with_saved()->render(
			[
				'id'       => '7',
				'template' => 'fixture-hero',
				'seed'     => 1,
			]
		);

		$this->assertStringContainsString( 'si-template-fixture-object', $html );
		$this->assertStringContainsString( '>Saved title</title>', $html );
	}

	public function test_saved_illustration_title_and_decorative_can_be_overridden(): void {
		$renderer = $this->renderer_with_saved();

		$this->assertStringContainsString(
			'>Placement title</title>',
			$renderer->render(
				[
					'id'    => 7,
					'title' => 'Placement title',
				]
			)
		);
		$this->assertStringContainsString(
			'aria-hidden="true"',
			$renderer->render(
				[
					'id'         => 7,
					'decorative' => true,
				]
			)
		);
	}

	public function test_unknown_saved_illustration_takes_the_error_path(): void {
		$this->assertSame( '<!-- Sprint Illustrations: illustration could not be rendered. -->', $this->renderer_with_saved()->render( [ 'id' => 99 ] ) );
		$this->assertStringContainsString( 'Saved illustration 99 was not found.', $this->logged[0] );
	}

	public function test_id_is_ignored_without_a_lookup(): void {
		$this->assertStringContainsString(
			'si-template-fixture-hero',
			$this->renderer()->render(
				[
					'id'       => 7,
					'template' => 'fixture-hero',
				]
			)
		);
	}

	public function test_spec_normalizes_surface_attributes(): void {
		$spec = Renderer::spec(
			[
				'template'   => 'auto',
				'keywords'   => 'Team, Remote',
				'seed'       => '42',
				'palette'    => '',
				'title'      => 'Our team',
				'decorative' => 'yes',
			]
		);

		$this->assertNull( $spec->template );
		$this->assertSame( [ 'team', 'remote' ], $spec->keywords );
		$this->assertSame( 42, $spec->seed );
		$this->assertSame( 'site', $spec->palette );
		$this->assertSame( 'Our team', $spec->title );
		$this->assertTrue( $spec->decorative );
	}

	public function test_spec_defaults(): void {
		$spec = Renderer::spec( [] );

		$this->assertNull( $spec->template );
		$this->assertSame( 1, $spec->seed );
		$this->assertSame( 'site', $spec->palette );
		$this->assertFalse( $spec->decorative );
	}

	public function test_renders_figure_with_unique_ids(): void {
		$renderer = $this->renderer();
		$args     = [
			'template' => 'fixture-hero',
			'seed'     => 5,
		];

		$first  = $renderer->render( $args );
		$second = $renderer->render( $args );

		$this->assertStringStartsWith( '<figure class="si-illustration si-template-fixture-hero"><svg', $first );
		$this->assertStringEndsWith( '</svg></figure>', $first );
		$this->assertStringNotContainsString( '__SIID__', $first );
		$this->assertMatchesRegularExpression( '/id="si-[0-9a-f]{8}-1-t"/', $first );
		$this->assertMatchesRegularExpression( '/id="si-[0-9a-f]{8}-2-t"/', $second );
		$this->assertSame( [ 'site', 'site' ], $this->palette_refs );
	}

	public function test_errors_are_hidden_from_visitors(): void {
		$html = $this->renderer()->render( [ 'template' => 'no-such-template' ] );

		$this->assertSame( '<!-- Sprint Illustrations: illustration could not be rendered. -->', $html );
		$this->assertCount( 1, $this->logged );
		$this->assertStringContainsString( 'no-such-template', $this->logged[0] );
	}

	public function test_errors_are_shown_escaped_to_editors(): void {
		$html = $this->renderer()->render( [ 'template' => 'no-such-template' ], true );

		$this->assertStringStartsWith( '<div class="si-illustration-error" role="alert">Sprint Illustrations: ', $html );
		$this->assertStringContainsString( '&quot;no-such-template&quot;', $html );
	}
}
