<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneSpec;

final class SceneSpecTest extends TestCase {

	public function test_defaults(): void {
		$spec = SceneSpec::from_array( [] );

		$this->assertNull( $spec->template );
		$this->assertSame( 1, $spec->seed );
		$this->assertSame( 'default', $spec->palette );
		$this->assertSame( [], $spec->keywords );
		$this->assertSame( [], $spec->picks );
		$this->assertNull( $spec->title );
		$this->assertFalse( $spec->decorative );
	}

	public function test_normalizes_untrusted_input(): void {
		$spec = SceneSpec::from_array(
			[
				'template'   => 'Hero"><script>',
				'seed'       => '-99',
				'palette'    => 'preset:../../etc',
				'keywords'   => 'Remote Teams, <b>coffee</b>, , coffee',
				'picks'      => [
					'subject'  => 'char-ava-wave',
					'decor'    => [ 'decor-dot', 'BAD ID', 5 ],
					'Bad Slot' => 'x',
					'prop'     => [ 'nested' => [ 'x' ] ],
				],
				'title'      => '<em>Hello</em> world',
				'decorative' => 'true',
			]
		);

		$this->assertNull( $spec->template );
		$this->assertSame( 99, $spec->seed );
		$this->assertSame( 'default', $spec->palette );
		$this->assertSame( [ 'remote teams', 'coffee' ], $spec->keywords );
		$this->assertSame(
			[
				'subject' => 'char-ava-wave',
				'decor'   => [ 'decor-dot' ],
			],
			$spec->picks
		);
		$this->assertSame( 'Hello world', $spec->title );
		$this->assertTrue( $spec->decorative );
	}

	public function test_accepts_inline_palette_and_presets(): void {
		$this->assertSame( 'preset:ocean', SceneSpec::from_array( [ 'palette' => 'preset:ocean' ] )->palette );
		$this->assertSame( 'site', SceneSpec::from_array( [ 'palette' => 'site' ] )->palette );
		$this->assertSame(
			[ 'primary' => '#000000' ],
			SceneSpec::from_array(
				[
					'palette' => [
						'primary' => '#000000',
						0         => 'x',
					],
				]
			)->palette
		);
	}

	public function test_seed_is_clamped(): void {
		$this->assertSame( SceneSpec::MAX_SEED, SceneSpec::from_array( [ 'seed' => PHP_INT_MAX ] )->seed );
	}

	public function test_trailing_newlines_are_rejected(): void {
		$spec = SceneSpec::from_array(
			[
				'template' => "hero\n",
				'palette'  => "site\n",
				'picks'    => [
					'subject' => "char-a\n",
					"slot\n"  => 'char-b',
				],
			]
		);

		$this->assertNull( $spec->template );
		$this->assertSame( 'default', $spec->palette );
		$this->assertSame( [], $spec->picks );
	}

	public function test_withers_and_round_trip(): void {
		$spec = SceneSpec::from_array(
			[
				'seed'     => 5,
				'keywords' => [ 'team' ],
			]
		);
		$next = $spec->with_template( 'fixture-hero' )->with_picks( [ 'subject' => 'char-stick' ] )->with_seed( 6 );

		$this->assertSame( 'fixture-hero', $next->template );
		$this->assertSame( [ 'subject' => 'char-stick' ], $next->picks );
		$this->assertSame( 6, $next->seed );
		$this->assertSame( [ 'team' ], $next->keywords );
		$this->assertSame( $next->to_array(), SceneSpec::from_array( $next->to_array() )->to_array() );
		$this->assertSame( 5, $spec->seed, 'Original is immutable.' );
	}
}
