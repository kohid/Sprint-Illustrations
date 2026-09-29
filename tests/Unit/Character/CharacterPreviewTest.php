<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Character;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Character\CharacterPreview;
use SprintIllustrations\Character\CharacterSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\Sanitizer;

final class CharacterPreviewTest extends TestCase {

	public function test_the_preview_is_a_recoloured_sanitized_picture_without_library_markers(): void {
		$svg = CharacterPreview::render(
			CharacterSpec::from_array(
				[
					'top'     => 'hoodie',
					'glasses' => 'round',
				]
			),
			Palette::default(),
			new Sanitizer()
		);

		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 160 320"', $svg );
		$this->assertStringContainsString( 'aria-hidden="true"', $svg );
		$this->assertStringNotContainsString( 'slot-', $svg, 'Colour classes are replaced by real colours.' );
		$this->assertStringNotContainsString( 'anchor-', $svg );
		$this->assertStringNotContainsString( 'data-si-', $svg );
		$this->assertMatchesRegularExpression( '/fill="#[0-9a-f]{6}"/i', $svg );
	}

	public function test_the_preview_tones_and_palette_change_the_colours(): void {
		$sanitizer = new Sanitizer();
		$spec      = CharacterSpec::from_array( [] );
		$plain     = CharacterPreview::render( $spec, Palette::default(), $sanitizer );

		$this->assertNotSame( $plain, CharacterPreview::render( $spec->with( [ 'skin' => 3 ] ), Palette::default(), $sanitizer ) );
		$this->assertNotSame( $plain, CharacterPreview::render( $spec->with( [ 'hair_tone' => 2 ] ), Palette::default(), $sanitizer ) );
		$this->assertNotSame( $plain, CharacterPreview::render( $spec->with( [ 'top_color' => 'accent' ] ), Palette::default(), $sanitizer ) );
	}
}
