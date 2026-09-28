<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cache\CacheKey;
use SprintIllustrations\Cache\CachingComposer;
use SprintIllustrations\Cache\SvgCache;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Services;

final class CachingComposerTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/si-cc-' . uniqid();
	}

	protected function tearDown(): void {
		( new SvgCache( $this->dir ) )->purge();
		foreach ( [ 'index.php', '.htaccess' ] as $guard ) {
			if ( is_file( $this->dir . '/' . $guard ) ) {
				unlink( $this->dir . '/' . $guard );
			}
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir );
		}
	}

	/**
	 * A composer that counts calls and delegates to the fixture library (or returns a canned result).
	 */
	private function counting( ?ComposedSvg $canned = null ): ComposesSvg {
		$inner = Services::create( __DIR__ . '/../../fixtures/library' )->composer;

		return new class( $inner, $canned ) implements ComposesSvg {
			public int $calls = 0;

			public function __construct( private ComposesSvg $inner, private ?ComposedSvg $canned ) {}

			public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg {
				++$this->calls;

				return $this->canned ?? $this->inner->compose( $spec, $palette );
			}
		};
	}

	private function caching( ComposesSvg $inner, ?SvgCache $cache = null ): CachingComposer {
		return new CachingComposer( $inner, $cache ?? new SvgCache( $this->dir ), new Sanitizer(), '1', '0.2.0' );
	}

	private function spec( int $seed = 1 ): SceneSpec {
		return SceneSpec::from_array(
			[
				'template' => 'fixture-hero',
				'seed'     => $seed,
			]
		);
	}

	public function test_second_call_is_a_hit(): void {
		$inner   = $this->counting();
		$caching = $this->caching( $inner );

		$first  = $caching->compose( $this->spec(), Palette::default() );
		$second = $caching->compose( $this->spec(), Palette::default() );

		$this->assertSame( 1, $inner->calls );
		$this->assertSame( $first->markup, $second->markup );
		$this->assertSame( $first->spec->to_array(), $second->spec->to_array() );
	}

	public function test_different_inputs_miss(): void {
		$inner   = $this->counting();
		$caching = $this->caching( $inner );

		$caching->compose( $this->spec(), Palette::default() );
		$caching->compose( $this->spec( 2 ), Palette::default() );
		$caching->compose( $this->spec(), Palette::from_array( [ 'primary' => '#000000' ] ) );

		$this->assertSame( 3, $inner->calls );
	}

	public function test_results_with_warnings_are_not_stored(): void {
		$inner   = $this->counting( new ComposedSvg( '<svg xmlns="http://www.w3.org/2000/svg"/>', $this->spec(), [ 'Pick "x" is not valid.' ] ) );
		$caching = $this->caching( $inner );

		$caching->compose( $this->spec(), Palette::default() );
		$caching->compose( $this->spec(), Palette::default() );

		$this->assertSame( 2, $inner->calls );
	}

	public function test_tampered_entries_are_sanitized_on_read(): void {
		$cache = new SvgCache( $this->dir );
		$key   = CacheKey::make( $this->spec(), Palette::default(), '1', '0.2.0' );
		$cache->put( $key, new ComposedSvg( '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>', $this->spec(), [] ) );

		$result = $this->caching( $this->counting(), $cache )->compose( $this->spec(), Palette::default() );

		$this->assertStringNotContainsString( 'script', $result->markup );
		$this->assertStringContainsString( '<rect', $result->markup );
	}
}
