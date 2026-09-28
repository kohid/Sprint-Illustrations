<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Media\PngCheck;

final class PngCheckTest extends TestCase {

	private static function head( int $w, int $h ): string {
		return "\x89PNG\r\n\x1a\n" . pack( 'N', 13 ) . 'IHDR' . pack( 'N', $w ) . pack( 'N', $h ) . str_repeat( "\0", 8 );
	}

	public function test_valid_png(): void {
		$this->assertNull( PngCheck::problem( self::head( 1600, 1200 ), 250000 ) );
		$this->assertNull( PngCheck::problem( self::head( 1600, 1200 ), 250000, "\0\0\0\0IEND\xAE\x42\x60\x82" ) );
	}

	public function test_rejects_incomplete_png(): void {
		$this->assertStringContainsString( 'incomplete', (string) PngCheck::problem( self::head( 10, 10 ), 100, str_repeat( "\0", 12 ) ) );
	}

	public function test_rejects_non_png(): void {
		$this->assertStringContainsString( 'not a PNG', (string) PngCheck::problem( 'GIF89a' . str_repeat( "\0", 26 ), 100 ) );
	}

	public function test_rejects_large_files_and_bad_dimensions(): void {
		$this->assertStringContainsString( '5 MB', (string) PngCheck::problem( self::head( 100, 100 ), PngCheck::MAX_BYTES + 1 ) );
		$this->assertStringContainsString( 'dimensions', (string) PngCheck::problem( self::head( 0, 100 ), 100 ) );
		$this->assertStringContainsString( 'dimensions', (string) PngCheck::problem( self::head( 9000, 100 ), 100 ) );
		$this->assertStringContainsString( 'not a PNG', (string) PngCheck::problem( "\x89PNG", 4 ) );
	}
}
