<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cache\SvgCache;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\SceneSpec;

final class SvgCacheTest extends TestCase {

	private string $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/si-cache-' . uniqid();
	}

	protected function tearDown(): void {
		if ( ! is_dir( $this->dir ) ) {
			return;
		}
		foreach ( array_diff( scandir( $this->dir ), [ '.', '..' ] ) as $name ) {
			unlink( $this->dir . '/' . $name );
		}
		rmdir( $this->dir );
	}

	private function svg(): ComposedSvg {
		return new ComposedSvg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>', SceneSpec::from_array( [ 'template' => 'fixture-hero' ] ), [] );
	}

	public function test_round_trip_and_protection_files(): void {
		$cache = new SvgCache( $this->dir );
		$key   = sha1( 'a' );

		$this->assertNull( $cache->get( $key ) );
		$cache->put( $key, $this->svg() );

		$this->assertSame( $this->svg()->markup, $cache->get( $key )->markup );
		$this->assertFileExists( $this->dir . '/index.php' );
		$this->assertFileExists( $this->dir . '/.htaccess' );
		$this->assertSame( 1, $cache->stats()['files'] );
		$this->assertGreaterThan( 0, $cache->stats()['bytes'] );
	}

	public function test_purge(): void {
		$cache = new SvgCache( $this->dir );
		$cache->put( sha1( 'a' ), $this->svg() );
		$cache->put( sha1( 'b' ), $this->svg() );

		$this->assertSame( 2, $cache->purge() );
		$this->assertNull( $cache->get( sha1( 'a' ) ) );
		$this->assertSame( 0, $cache->stats()['files'] );
	}

	public function test_garbage_collection_uses_last_use(): void {
		$cache = new SvgCache( $this->dir );
		$old   = sha1( 'old' );
		$used  = sha1( 'used' );
		$cache->put( $old, $this->svg() );
		$cache->put( $used, $this->svg() );
		touch( $this->dir . "/$old.json", time() - 40 * 86400 );
		touch( $this->dir . "/$used.json", time() - 40 * 86400 );

		$cache->get( $used );

		$this->assertSame( 1, $cache->collect_garbage( 30 * 86400 ) );
		$this->assertNull( $cache->get( $old ) );
		$this->assertNotNull( $cache->get( $used ) );
	}

	public function test_corrupt_entry_is_dropped(): void {
		$cache = new SvgCache( $this->dir );
		$key   = sha1( 'bad' );
		$cache->put( $key, $this->svg() );
		file_put_contents( $this->dir . "/$key.json", '{nope' );

		$this->assertNull( $cache->get( $key ) );
		$this->assertFileDoesNotExist( $this->dir . "/$key.json" );
	}

	public function test_falls_back_to_memory_when_not_writable(): void {
		$file  = tempnam( sys_get_temp_dir(), 'si-not-a-dir' );
		$cache = new SvgCache( $file );

		$this->assertFalse( $cache->writable() );
		$cache->put( sha1( 'a' ), $this->svg() );
		$this->assertNotNull( $cache->get( sha1( 'a' ) ) );
		$this->assertSame( 0, $cache->stats()['files'] );

		unlink( $file );
	}

	public function test_rejects_invalid_keys(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new SvgCache( $this->dir ) )->get( '../../wp-config' );
	}
}
