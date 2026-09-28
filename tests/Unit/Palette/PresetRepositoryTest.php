<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PresetRepository;

final class PresetRepositoryTest extends TestCase {

	public function test_bundled_presets_in_order(): void {
		$this->assertSame( [ 'sprint', 'forest', 'night', 'ocean', 'sunset', 'mono' ], PresetRepository::bundled()->ids() );
	}

	public function test_sprint_equals_default_palette(): void {
		$this->assertSame( Palette::default()->to_array(), PresetRepository::bundled()->get( 'sprint' )->to_array() );
	}

	public function test_every_preset_is_readable(): void {
		foreach ( PresetRepository::bundled()->all() as $id => $preset ) {
			$this->assertNotSame( '', $preset['label'], $id );
			$this->assertSame( [], $preset['palette']->warnings(), "Preset $id has low-contrast slots." );
		}
	}

	public function test_unknown_preset_is_null(): void {
		$this->assertNull( PresetRepository::bundled()->get( 'nope' ) );
	}

	public function test_invalid_entries_are_skipped(): void {
		$file = tempnam( sys_get_temp_dir(), 'si-presets' );
		file_put_contents( $file, '{"ok":{"label":"OK","primary":"#123456"},"Bad Id":{"label":"x"},"list":[1,2]}' );

		$presets = PresetRepository::from_file( $file );
		unlink( $file );

		$this->assertSame( [ 'ok', 'list' ], $presets->ids() );
		$this->assertSame( '#123456', $presets->get( 'ok' )->resolve( 'primary' ) );
		$this->assertSame( 'list', $presets->all()['list']['label'] );
	}

	public function test_missing_file_throws(): void {
		$this->expectException( \RuntimeException::class );
		PresetRepository::from_file( sys_get_temp_dir() . '/si-missing-presets.json' );
	}

	public function test_review_palettes_come_from_presets(): void {
		$review = ContactSheet::review_palettes();

		$this->assertSame( [ 'Sprint', 'Forest', 'Night' ], array_keys( $review ) );
		$this->assertSame( '#2f7d5b', $review['Forest']->resolve( 'primary' ) );
	}
}
