<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Portrait;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Portrait\PortraitBuilder;
use SprintIllustrations\Portrait\PortraitPresets;
use SprintIllustrations\Portrait\PortraitPreview;
use SprintIllustrations\Portrait\PortraitSpec;
use SprintIllustrations\Security\Sanitizer;

/**
 * A generated portrait has to be as good as a hand-drawn piece: it builds into the library with no
 * warnings (colour only from slots) and carries both anchors.
 */
final class PortraitBuilderTest extends TestCase {

	/**
	 * Specs that between them use every option value at least once.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function specs(): array {
		$specs = [ 'default' => [] ];
		foreach ( PortraitSpec::options() as $field => $allowed ) {
			foreach ( array_keys( $allowed ) as $value ) {
				$specs[ "$field=$value" ] = [ $field => $value ];
			}
		}
		for ( $seed = 1; $seed <= 40; $seed++ ) {
			$specs[ "shuffled $seed" ] = PortraitPresets::shuffled( $seed )->to_array();
		}
		foreach ( PortraitPresets::all() as $preset ) {
			$specs[ 'preset ' . $preset['id'] ] = $preset['choices'];
		}

		return $specs;
	}

	public function test_every_option_and_shuffle_builds_into_the_library_without_warnings(): void {
		$source = sys_get_temp_dir() . '/si-portrait-' . uniqid();
		$target = $source . '-out';
		mkdir( $source . '/characters', 0777, true );

		$names = [];
		$index = 0;
		foreach ( self::specs() as $label => $data ) {
			$name = 'tester-' . ( $index++ );
			file_put_contents( $source . "/characters/$name.svg", PortraitBuilder::svg( PortraitSpec::from_array( $data ), 'Tester ' . $label, [ 'person', 'portrait' ], 'tester' ) );
			$names[ 'char-' . $name ] = $label;
		}

		$report = ( new ManifestBuilder( new Sanitizer(), new NullPrompter() ) )->build( $source, $target );

		$this->assertSame( [], $report->errors );
		$this->assertSame( [], $report->warnings, "Authoring warnings:\n" . implode( "\n", $report->warnings ) );
		$this->assertCount( count( $names ), $report->built );

		$manifest = Manifest::from_files( [ $target . '/manifest.json' ] );
		foreach ( $names as $id => $label ) {
			$piece = $manifest->get( $id );
			$this->assertNotNull( $piece, $label );
			$this->assertNotNull( $piece->anchor( 'ground' ), "$label: ground anchor" );
			$this->assertNotNull( $piece->anchor( 'hold' ), "$label: hold anchor" );
			$this->assertSame( 'tester', $piece->person, $label );
			$this->assertContains( 'skin', $piece->slots, "$label uses the skin slot" );
		}

		self::remove_tree( $source );
		self::remove_tree( $target );
	}

	/**
	 * Delete a temporary folder and everything in it.
	 *
	 * @param string $dir Folder.
	 */
	private static function remove_tree( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ) as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}

	public function test_the_same_choices_always_draw_the_same_portrait(): void {
		$spec = PortraitSpec::from_array(
			[
				'hair'  => 'curly',
				'scene' => 'taxi',
				'pose'  => 'wheel',
			]
		);

		$this->assertSame( PortraitBuilder::svg( $spec ), PortraitBuilder::svg( $spec ) );
	}

	public function test_changing_one_part_does_not_redraw_the_others(): void {
		$a = PortraitBuilder::svg( PortraitSpec::from_array( [ 'mouth' => 'smile' ] ) );
		$b = PortraitBuilder::svg( PortraitSpec::from_array( [ 'mouth' => 'grin' ] ) );

		$this->assertNotSame( $a, $b );
		// The face outline wobbles from its own key, so it is the same in both.
		preg_match( '/<path class="slot-skin slot-stroke-skin-dark" d="(M[^"]+)"[^>]*\/>/', $a, $face_a );
		preg_match( '/<path class="slot-skin slot-stroke-skin-dark" d="(M[^"]+)"[^>]*\/>/', $b, $face_b );
		$this->assertSame( $face_a[1], $face_b[1] );
	}

	public function test_unknown_input_falls_back_to_defaults_and_the_spec_round_trips(): void {
		$spec = PortraitSpec::from_array(
			[
				'hair'      => 'nope',
				'eyes'      => 'almond',
				'skin'      => 99,
				'top_color' => 'purple',
			]
		);

		$this->assertSame( PortraitSpec::defaults()['hair'], $spec->get( 'hair' ) );
		$this->assertSame( 'almond', $spec->get( 'eyes' ) );
		$this->assertSame( PortraitSpec::MAX_TONE, $spec->skin );
		$this->assertSame( PortraitSpec::defaults()['top_color'], $spec->get( 'top_color' ) );
		$this->assertEquals( $spec, PortraitSpec::from_array( $spec->to_array() ) );
	}

	public function test_the_preview_is_recoloured_and_has_no_markers_or_slot_classes(): void {
		$svg = PortraitPreview::render(
			PortraitSpec::from_array(
				[
					'scene'   => 'taxi',
					'glasses' => 'round',
				]
			),
			Palette::default(),
			new Sanitizer()
		);

		$this->assertStringNotContainsString( 'anchor-', $svg );
		$this->assertStringNotContainsString( 'slot-', $svg );
		$this->assertStringNotContainsString( 'data-si-', $svg );
		$this->assertStringContainsString( 'fill="#', $svg );
	}

	public function test_shuffle_is_deterministic_and_respects_the_hair_pool(): void {
		$this->assertEquals( PortraitPresets::shuffled( 7, 'woman' ), PortraitPresets::shuffled( 7, 'woman' ) );
		for ( $seed = 1; $seed <= 30; $seed++ ) {
			$this->assertContains( PortraitPresets::shuffled( $seed, 'man' )->get( 'hair' ), [ 'short', 'curly', 'buzz', 'bald' ] );
			$this->assertSame( 'none', PortraitPresets::shuffled( $seed, 'woman' )->get( 'facial_hair' ) );
		}
	}
}
