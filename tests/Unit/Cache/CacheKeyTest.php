<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cache\CacheKey;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;

final class CacheKeyTest extends TestCase {

	private function key( array $spec = [], ?Palette $palette = null, string $manifest = '3', string $plugin = '0.2.0' ): string {
		return CacheKey::make( SceneSpec::from_array( $spec + [ 'template' => 'hero-left-character' ] ), $palette ?? Palette::default(), $manifest, $plugin );
	}

	public function test_is_sha1_and_stable(): void {
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{40}$/D', $this->key() );
		$this->assertSame( $this->key(), $this->key() );
	}

	public function test_pick_order_does_not_matter(): void {
		$a = $this->key(
			[
				'picks' => [
					'subject' => 'char-ava-wave',
					'prop'    => 'obj-phone',
				],
			]
		);
		$b = $this->key(
			[
				'picks' => [
					'prop'    => 'obj-phone',
					'subject' => 'char-ava-wave',
				],
			]
		);

		$this->assertSame( $a, $b );
	}

	public function test_every_input_changes_the_key(): void {
		$base = $this->key();

		$this->assertNotSame( $base, $this->key( [ 'seed' => 2 ] ) );
		$this->assertNotSame( $base, $this->key( [], Palette::from_array( [ 'primary' => '#000000' ] ) ) );
		$this->assertNotSame( $base, $this->key( [], null, '4' ) );
		$this->assertNotSame( $base, $this->key( [], null, '3', '0.2.1' ) );
	}
}
