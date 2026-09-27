<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Services;
use SprintIllustrations\Svg\SvgDom;

final class ComposerTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	private function compose( array $spec, ?Palette $palette = null ): ComposedSvg {
		return $this->services->composer->compose( SceneSpec::from_array( $spec ), $palette ?? Palette::default() );
	}

	public function test_output_is_valid_accessible_svg(): void {
		$result = $this->compose(
			[
				'template' => 'fixture-hero',
				'seed'     => 5,
			]
		);
		$doc    = SvgDom::parse( $result->markup );
		$svg    = $doc->documentElement;

		$this->assertSame( '0 0 400 300', $svg->getAttribute( 'viewBox' ) );
		$this->assertSame( 'img', $svg->getAttribute( 'role' ) );
		$this->assertSame( '__SIID__-t __SIID__-d', $svg->getAttribute( 'aria-labelledby' ) );
		$this->assertSame( 'title', $svg->firstChild->localName );
		$this->assertSame( 'Fixture hero', $svg->firstChild->textContent );
		$this->assertStringStartsWith( 'Illustration showing ', $svg->childNodes->item( 1 )->textContent );
		$this->assertFalse( $svg->hasAttribute( 'width' ) );
	}

	public function test_no_slot_classes_or_unscoped_ids_remain(): void {
		$markup = $this->compose(
			[
				'template' => 'fixture-object',
				'seed'     => 1,
				'picks'    => [ 'hero' => 'obj-orb' ],
			]
		)->markup;

		$this->assertStringNotContainsString( 'slot-', $markup );
		preg_match_all( '/\bid="([^"]+)"/', $markup, $ids );
		$this->assertNotEmpty( $ids[1] );
		foreach ( $ids[1] as $id ) {
			$this->assertStringStartsWith( ComposedSvg::ID_TOKEN . '-', $id );
		}
		$this->assertMatchesRegularExpression( '/fill="url\(#__SIID__-p\d+-shine\)"/', $markup );
	}

	public function test_same_piece_twice_gets_distinct_ids(): void {
		$template_dir = sys_get_temp_dir() . '/si-twice-' . uniqid();
		mkdir( $template_dir );
		file_put_contents(
			$template_dir . '/twice.json',
			json_encode(
				[
					'id'     => 'twice',
					'canvas' => [ 100, 100 ],
					'slots'  => [
						[
							'name'     => 'orbs',
							'category' => 'objects',
							'box'      => [ 0, 0, 100, 100 ],
							'count'    => [ 2, 2 ],
						],
					],
				]
			)
		);

		$services = Services::create( __DIR__ . '/../../fixtures/library', [], [ $template_dir ] );
		$markup   = $services->composer->compose(
			SceneSpec::from_array(
				[
					'template' => 'twice',
					'picks'    => [ 'orbs' => [ 'obj-orb', 'obj-orb' ] ],
				]
			),
			Palette::default()
		)->markup;

		unlink( $template_dir . '/twice.json' );
		rmdir( $template_dir );

		preg_match_all( '/\bid="([^"]+)"/', $markup, $ids );
		$this->assertSame( $ids[1], array_unique( $ids[1] ) );
		$this->assertCount( 2, preg_grep( '/-shine$/', $ids[1] ) );
	}

	public function test_deterministic_markup_and_instance_ids(): void {
		$spec = [
			'template' => 'fixture-hero',
			'seed'     => 77,
		];
		$a    = $this->compose( $spec );
		$b    = $this->compose( $spec );

		$this->assertSame( $a->markup, $b->markup );

		$rendered = $a->with_instance_id( 'si-abc-1' );
		$this->assertStringNotContainsString( ComposedSvg::ID_TOKEN, $rendered );
		$this->assertStringContainsString( 'aria-labelledby="si-abc-1-t si-abc-1-d"', $rendered );
	}

	public function test_invalid_instance_id_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->compose( [ 'template' => 'fixture-object' ] )->with_instance_id( '1"><script>' );
	}

	public function test_instance_id_with_trailing_newline_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->compose( [ 'template' => 'fixture-object' ] )->with_instance_id( "si-abc\n" );
	}

	public function test_palette_changes_colours_only(): void {
		$spec  = [
			'template' => 'fixture-object',
			'seed'     => 2,
		];
		$plain = $this->compose( $spec )->markup;
		$green = $this->compose( $spec, Palette::from_array( [ 'accent' => '#00aa00' ] ) )->markup;

		$this->assertNotSame( $plain, $green );
		$this->assertSame( preg_replace( '/#[0-9a-f]{6}/', '#', $plain ), preg_replace( '/#[0-9a-f]{6}/', '#', $green ) );
	}

	public function test_resolved_spec_reproduces_identical_output(): void {
		$first  = $this->compose(
			[
				'keywords' => [ 'coffee' ],
				'seed'     => 8,
			]
		);
		$replay = $this->services->composer->compose( $first->spec, Palette::default() );

		$this->assertSame( 'fixture-object', $first->spec->template );
		$this->assertArrayHasKey( 'hero', $first->spec->picks );
		$this->assertSame( $first->markup, $replay->markup );
	}

	public function test_decorative_mode(): void {
		$markup = $this->compose(
			[
				'template'   => 'fixture-object',
				'decorative' => true,
			]
		)->markup;

		$this->assertStringContainsString( 'aria-hidden="true"', $markup );
		$this->assertStringNotContainsString( '<title', $markup );
		$this->assertStringNotContainsString( 'role="img"', $markup );
	}

	public function test_custom_title_is_escaped(): void {
		$markup = $this->compose(
			[
				'template' => 'fixture-object',
				'title'    => 'Tom & Jerry',
			]
		)->markup;

		$this->assertStringContainsString( '<title id="__SIID__-t">Tom &amp; Jerry</title>', $markup );
	}

	public function test_unknown_template_throws(): void {
		$this->expectException( CompositionException::class );
		$this->compose( [ 'template' => 'does-not-exist' ] );
	}
}
