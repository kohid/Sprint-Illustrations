<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Compose\PiecePreview;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Services;

final class PiecePreviewTest extends TestCase {

	private function render( string $id, ?Palette $palette = null ): string {
		$services  = Services::create( __DIR__ . '/../../fixtures/library' );
		$sanitizer = new Sanitizer();

		return PiecePreview::render( $services->manifest->get( $id ), $palette ?? Palette::default(), new PieceLoader( $sanitizer ), $sanitizer );
	}

	public function test_renders_piece_alone_recoloured(): void {
		$svg = $this->render( 'char-stick' );

		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 100 200"', $svg );
		$this->assertStringNotContainsString( 'slot-', $svg );
		$this->assertStringContainsString( '#5b5bd6', $svg );
	}

	public function test_palette_changes_colours(): void {
		$this->assertNotSame( $this->render( 'char-stick' ), $this->render( 'char-stick', Palette::from_array( [ 'primary' => '#000000' ] ) ) );
	}
}
