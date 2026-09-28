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
