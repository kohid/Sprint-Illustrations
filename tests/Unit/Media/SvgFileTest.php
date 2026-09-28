<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Media\SvgFile;

final class SvgFileTest extends TestCase {

	private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img"><rect width="1" height="1"/></svg>';

	public function test_size_from_viewbox(): void {
		$this->assertSame( [ 800, 600 ], SvgFile::size( self::SVG ) );
		$this->assertNull( SvgFile::size( '<svg xmlns="http://www.w3.org/2000/svg"/>' ) );
	}

	public function test_standalone_adds_declaration_and_size(): void {
		$file = SvgFile::standalone( self::SVG );

		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg width="800" height="600" ', $file );
		$this->assertStringContainsString( 'viewBox="0 0 800 600"', $file );
	}

	public function test_standalone_is_idempotent_and_keeps_existing_size(): void {
		$once = SvgFile::standalone( self::SVG );

		$this->assertSame( $once, SvgFile::standalone( $once ) );
		$this->assertStringStartsWith( '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<svg width="10"', SvgFile::standalone( '<svg width="10" height="5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"/>' ) );
	}
}
