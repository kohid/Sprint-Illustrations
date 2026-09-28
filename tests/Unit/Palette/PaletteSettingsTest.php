<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PaletteSettings;
use SprintIllustrations\Palette\PresetRepository;

final class PaletteSettingsTest extends TestCase {

	private PaletteSettings $settings;

	protected function setUp(): void {
		$this->settings = new PaletteSettings( PresetRepository::bundled() );
	}

	public function test_missing_option_means_sprint_preset(): void {
		$normalized = $this->settings->normalize( false );

		$this->assertSame( 'preset', $normalized['source'] );
		$this->assertSame( 'sprint', $normalized['preset'] );
		$this->assertSame( '#5b5bd6', $normalized['colors']['primary'] );
		$this->assertSame( Palette::default()->to_array()['skin'], $normalized['skin'] );
		$this->assertSame(
			[
				'map'  => [],
				'sync' => false,
			],
			$normalized['elementor']
		);
	}

	public function test_preset_source_copies_preset_colours(): void {
		$normalized = $this->settings->normalize(
			[
				'source' => 'preset',
				'preset' => 'forest',
				'colors' => [ 'primary' => '#000000' ],
			]
		);

		$this->assertSame( '#2f7d5b', $normalized['colors']['primary'] );
	}

	public function test_unknown_source_and_preset_fall_back(): void {
		$normalized = $this->settings->normalize(
			[
				'source' => 'magic',
				'preset' => 'nope',
			]
		);

		$this->assertSame( 'preset', $normalized['source'] );
		$this->assertSame( 'sprint', $normalized['preset'] );
	}

	public function test_custom_colours_are_validated(): void {
		$normalized = $this->settings->normalize(
			[
				'source' => 'custom',
				'colors' => [
					'primary'      => '#ABC',
					'secondary'    => 'javascript:alert(1)',
					'primary-dark' => '#000000',
					'bogus'        => '#123456',
				],
				'skin'   => [ '#111111', '', 'nope', '#222', '#333333', '#444444', '#555555', '#666666', '#777777' ],
				'hair'   => 'not a list',
			]
		);

		$this->assertSame( '#aabbcc', $normalized['colors']['primary'] );
		$this->assertSame( '#ffb224', $normalized['colors']['secondary'] );
		$this->assertSame( Palette::SLOTS, array_keys( $normalized['colors'] ) );
		$this->assertSame( [ '#111111', '#222222', '#333333', '#444444', '#555555', '#666666' ], $normalized['skin'] );
		$this->assertSame( Palette::default()->to_array()['hair'], $normalized['hair'] );
	}

	public function test_elementor_map_is_filtered(): void {
		$normalized = $this->settings->normalize(
			[
				'source'    => 'elementor',
				'elementor' => [
					'map'  => [
						'primary'   => 'kit:primary',
						'accent'    => 'var:e-gv-3',
						'neutral'   => '',
						'secondary' => 'evil:<script>',
						'bogus'     => 'kit:text',
					],
					'sync' => '1',
				],
			]
		);

		$this->assertSame( 'elementor', $normalized['source'] );
		$this->assertSame(
			[
				'primary' => 'kit:primary',
				'accent'  => 'var:e-gv-3',
			],
			$normalized['elementor']['map']
		);
		$this->assertTrue( $normalized['elementor']['sync'] );
	}

	public function test_normalize_is_idempotent(): void {
		$once = $this->settings->normalize(
			[
				'source' => 'custom',
				'colors' => [ 'accent' => '#00ff00' ],
			]
		);

		$this->assertSame( $once, $this->settings->normalize( $once ) );
	}

	public function test_resolve_references(): void {
		$site = $this->settings->normalize(
			[
				'source' => 'custom',
				'colors' => [ 'primary' => '#010203' ],
			]
		);

		$this->assertSame( '#010203', $this->settings->resolve( 'site', $site )->resolve( 'primary' ) );
		$this->assertSame( '#0e7490', $this->settings->resolve( 'preset:ocean', $site )->resolve( 'primary' ) );
		$this->assertSame( '#5b5bd6', $this->settings->resolve( 'preset:nope', $site )->resolve( 'primary' ) );
		$this->assertSame( '#5b5bd6', $this->settings->resolve( 'default', $site )->resolve( 'primary' ) );
		$this->assertSame( '#abcdef', $this->settings->resolve( [ 'primary' => '#abcdef' ], $site )->resolve( 'primary' ) );
	}
}
