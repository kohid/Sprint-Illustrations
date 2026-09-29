<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\ReferenceImage;

final class ReferenceImageTest extends TestCase {

	public function test_accepts_common_images(): void {
		$this->assertSame( '', ReferenceImage::validate( 200000, 'image/png', 800, 600 ) );
		$this->assertSame( '', ReferenceImage::validate( 200000, 'image/jpeg', 16, 8000 ) );
		$this->assertSame( '', ReferenceImage::validate( 200000, 'image/webp', 1200, 900 ) );
	}

	public function test_rejects_other_types_sizes_and_dimensions(): void {
		$this->assertStringContainsString( 'PNG', ReferenceImage::validate( 1000, 'image/gif', 100, 100 ) );
		$this->assertStringContainsString( 'PNG', ReferenceImage::validate( 1000, 'image/svg+xml', 100, 100 ) );
		$this->assertStringContainsString( '5 MB', ReferenceImage::validate( ReferenceImage::MAX_BYTES + 1, 'image/png', 100, 100 ) );
		$this->assertNotSame( '', ReferenceImage::validate( 1000, 'image/png', 15, 100 ) );
		$this->assertNotSame( '', ReferenceImage::validate( 1000, 'image/png', 100, 8001 ) );
		$this->assertNotSame( '', ReferenceImage::validate( 0, 'image/png', 100, 100 ) );
	}

	public function test_fit_scales_down_only(): void {
		$this->assertSame( [ 1600, 1200 ], ReferenceImage::fit( 3200, 2400 ) );
		$this->assertSame( [ 900, 1600 ], ReferenceImage::fit( 1800, 3200 ) );
		$this->assertSame( [ 640, 480 ], ReferenceImage::fit( 640, 480 ) );
	}

	public function test_names(): void {
		$this->assertTrue( ReferenceImage::is_name( 'ref-abcdef0123456789.png' ) );
		$this->assertTrue( ReferenceImage::is_name( 'ref-abcdef0123456789.jpg' ) );
		$this->assertFalse( ReferenceImage::is_name( '../ref-abcdef0123456789.png' ) );
		$this->assertFalse( ReferenceImage::is_name( 'ref-abc.png' ) );
		$this->assertFalse( ReferenceImage::is_name( "ref-abcdef0123456789.png\n" ) );
		$this->assertSame( 'ref-abcdef0123456789.jpg', ReferenceImage::name( 'abcdef0123456789', false ) );
		$this->assertSame( 'ref-abcdef0123456789.png', ReferenceImage::name( 'abcdef0123456789', true ) );
	}
}
