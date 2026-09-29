<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Services;

final class BlankTemplateTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	public function test_blank_is_reachable_by_id_only(): void {
		$blank = $this->services->templates->get( Template::BLANK_ID );

		$this->assertNotNull( $blank );
		$this->assertSame( [], $blank->slots );
		$this->assertNotContains( Template::BLANK_ID, $this->services->templates->ids() );
		$this->assertNotContains( $blank->id, array_map( static fn( Template $t ): string => $t->id, $this->services->templates->all() ) );
	}

	public function test_empty_blank_composes_without_warnings(): void {
		$result = $this->services->composer->compose( SceneSpec::from_array( [ 'template' => 'blank' ] ), Palette::default() );

		$this->assertSame( [], $result->warnings );
		$this->assertSame( [], $result->boxes );
		$this->assertStringContainsString( 'viewBox="0 0 800 600"', $result->markup );
		$this->assertStringContainsString( 'Blank canvas', $result->markup );
		$this->assertStringNotContainsString( '<g ', $result->markup );
	}

	public function test_blank_renders_only_added_pieces(): void {
		$result = $this->services->composer->compose(
			SceneSpec::from_array(
				[
					'template' => 'blank',
					'items'    => [
						[
							'key'   => 0,
							'piece' => 'char-stick',
							'x'     => 100,
							'y'     => 100,
							'w'     => 100,
						],
					],
				]
			),
			Palette::default()
		);

		$this->assertSame( [ 'item:0' ], array_keys( $result->boxes ) );
		$this->assertSame( [ 'item:0' ], $result->layers );
		$this->assertSame( 1, substr_count( $result->markup, '<g ' ) );
	}

	public function test_automatic_choice_never_picks_blank(): void {
		foreach ( range( 1, 20 ) as $seed ) {
			$this->assertNotSame( Template::BLANK_ID, $this->services->selector->choose_template( [], $seed )->id );
		}
	}
}
