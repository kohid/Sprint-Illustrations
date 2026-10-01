<?php
declare( strict_types=1 );

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Tests build inline image data URLs.

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\ReferenceSource;

final class ReferenceSourceTest extends TestCase {

	public function test_only_plain_http_and_https_addresses_are_accepted(): void {
		$this->assertSame( 'https://example.com/a/taxi.png?w=800', ReferenceSource::url( "  https://example.com/a/taxi.png?w=800\n" ) );
		$this->assertSame( 'http://example.com/x.jpg', ReferenceSource::url( 'http://example.com/x.jpg' ) );

		foreach ( [ '', 'example.com/x.png', 'ftp://example.com/x.png', 'file:///etc/passwd', 'javascript:alert(1)', 'https://user:pw@example.com/x.png', 'https:///x.png', 'https://example.com/a b.png', 'a picture of a taxi' ] as $bad ) {
			$this->assertNull( ReferenceSource::url( $bad ), $bad );
		}
		$this->assertNull( ReferenceSource::url( 'https://example.com/' . str_repeat( 'a', 2000 ) ) );
	}

	public function test_inline_images_decode_and_everything_else_is_refused(): void {
		$png = base64_encode( "\x89PNG\r\n\x1a\nrest" );

		$this->assertSame( "\x89PNG\r\n\x1a\nrest", ReferenceSource::data( 'data:image/png;base64,' . $png ) );
		$this->assertSame( 'abc', ReferenceSource::data( 'data:image/webp;base64,' . base64_encode( 'abc' ) ) );
		$this->assertNull( ReferenceSource::data( 'data:image/svg+xml;base64,' . base64_encode( '<svg/>' ) ) );
		$this->assertNull( ReferenceSource::data( 'data:text/html;base64,' . base64_encode( '<script>' ) ) );
		$this->assertNull( ReferenceSource::data( 'data:image/png;base64,!!!not base64!!!' ) );
		$this->assertNull( ReferenceSource::data( 'https://example.com/x.png' ) );
	}

	public function test_extension_follows_the_detected_type(): void {
		$this->assertSame( 'jpg', ReferenceSource::extension( 'image/jpeg' ) );
		$this->assertSame( 'webp', ReferenceSource::extension( 'image/webp' ) );
		$this->assertSame( 'png', ReferenceSource::extension( 'image/png' ) );
	}
}
