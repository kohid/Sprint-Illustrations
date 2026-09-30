<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Character;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Character\CharacterBuilder;
use SprintIllustrations\Character\CharacterPresets;
use SprintIllustrations\Character\CharacterSpec;
use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Selection\Keywords;

/**
 * A generated character has to be as good as a hand-drawn one: it builds into the library with no
 * warnings (colour only from slots), has both anchors and the right attachment type.
 */
final class CharacterBuilderTest extends TestCase {

	/**
	 * Specs that between them use every option value at least once.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function specs(): array {
		$specs = [ 'default' => [] ];
		foreach ( [ 'standing', 'sitting' ] as $stance ) {
			foreach ( CharacterSpec::options( $stance ) as $field => $allowed ) {
				foreach ( array_keys( $allowed ) as $value ) {
					$specs[ "$stance $field=$value" ] = [
						'stance' => $stance,
						$field   => $value,
					];
				}
			}
		}
		foreach ( array_keys( CharacterSpec::STANCES ) as $stance ) {
			foreach ( array_keys( CharacterSpec::POSES[ $stance ] ) as $pose ) {
				foreach ( [ 'trousers', 'shorts', 'skirt', 'midi', 'cropped' ] as $bottom ) {
					$specs[ "$stance $pose $bottom" ] = [
						'stance' => $stance,
						'pose'   => $pose,
						'bottom' => $bottom,
					];
				}
			}
		}
		for ( $seed = 1; $seed <= 40; $seed++ ) {
			$specs[ "shuffled $seed" ] = CharacterPresets::shuffled( $seed )->to_array();
		}
		foreach ( CharacterPresets::all() as $preset ) {
			$specs[ 'preset ' . $preset['id'] ] = $preset['choices'];
		}

		return $specs;
	}

	public function test_every_option_and_shuffle_builds_into_the_library_without_warnings(): void {
		$source = sys_get_temp_dir() . '/si-char-' . uniqid();
		$target = $source . '-out';
		mkdir( $source . '/characters', 0777, true );

		$names = [];
		$index = 0;
		foreach ( self::specs() as $label => $data ) {
			$name = 'tester-' . ( $index++ );
			file_put_contents( $source . "/characters/$name.svg", CharacterBuilder::svg( CharacterSpec::from_array( $data ), 'Tester ' . $label, [ 'person', 'standing' ], 'tester' ) );
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
			$this->assertContains( $piece->accepts['hold'] ?? '', [ 'handheld', 'lap' ], $label );
			$this->assertSame( 'tester', $piece->person, $label );
			$this->assertNotEmpty( $piece->slots, $label );
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

	public function test_standing_and_sitting_characters_get_the_matching_attachment_and_size(): void {
		$standing = CharacterBuilder::svg( CharacterSpec::from_array( [] ) );
		$sitting  = CharacterBuilder::svg( CharacterSpec::from_array( [ 'stance' => 'sitting' ] ) );

		$this->assertStringContainsString( 'viewBox="0 0 160 320"', $standing );
		$this->assertStringContainsString( 'data-si-accepts="hold:handheld"', $standing );
		$this->assertStringContainsString( 'viewBox="0 0 200 300"', $sitting );
		$this->assertStringContainsString( 'data-si-accepts="hold:lap"', $sitting );
	}

	public function test_output_is_deterministic_and_escapes_the_label_and_tags(): void {
		$spec = CharacterSpec::from_array( [ 'top' => 'hoodie' ] );

		$this->assertSame( CharacterBuilder::svg( $spec, 'A', [ 'person' ], 'sam' ), CharacterBuilder::svg( $spec, 'A', [ 'person' ], 'sam' ) );

		$svg = CharacterBuilder::svg( $spec, 'Sam "the" <driver> & co', [ 'a"b', 'c<d' ], 'sam' );
		$this->assertStringNotContainsString( '<driver>', $svg );
		$this->assertNotFalse( simplexml_load_string( $svg ), 'The SVG is well-formed XML.' );
	}

	public function test_hands_stay_inside_the_drawing_for_every_build_and_pose(): void {
		foreach ( array_keys( CharacterSpec::BUILDS ) as $build ) {
			foreach ( array_keys( CharacterSpec::POSES['standing'] ) as $pose ) {
				$svg = CharacterBuilder::svg(
					CharacterSpec::from_array(
						[
							'build' => $build,
							'pose'  => $pose,
						]
					)
				);
				preg_match_all( '/<circle class="slot-skin" cx="([\d.]+)" cy="([\d.]+)" r="[\d.]+"\/>/', $svg, $hands, PREG_SET_ORDER );
				$this->assertNotEmpty( $hands );
				foreach ( $hands as $hand ) {
					$this->assertGreaterThanOrEqual( 0, (float) $hand[1], "$build $pose" );
					$this->assertLessThanOrEqual( 160, (float) $hand[1], "$build $pose" );
				}
			}
		}
	}

	public function test_the_person_and_tags_follow_the_library_conventions(): void {
		$keywords = new Keywords();
		foreach ( [ 'person', 'woman', 'man', 'standing', 'sitting' ] as $tag ) {
			$this->assertSame( [ $tag ], $keywords->tokenize( $tag ), $tag );
		}
	}
	public function test_every_stance_and_pose_fits_inside_its_canvas(): void {
		foreach ( array_keys( CharacterSpec::STANCES ) as $stance ) {
			foreach ( array_keys( CharacterSpec::POSES[ $stance ] ) as $pose ) {
				foreach ( array_keys( CharacterSpec::BUILDS ) as $build ) {
					$extent = CharacterBuilder::extent(
						CharacterSpec::from_array(
							[
								'stance' => $stance,
								'pose'   => $pose,
								'build'  => $build,
							]
						)
					);
					if ( null === $extent ) {
						continue;
					}
					[ $w, $h ] = $extent['vb'];
					foreach ( $extent['points'] as [ $x, $y ] ) {
						$this->assertGreaterThanOrEqual( 2, $x, "$stance $pose $build x" );
						$this->assertLessThanOrEqual( $w - 2, $x, "$stance $pose $build x" );
						$this->assertGreaterThanOrEqual( 2, $y, "$stance $pose $build y" );
						$this->assertLessThanOrEqual( $h - 2, $y, "$stance $pose $build y" );
					}
				}
			}
		}
	}

	public function test_sitting_without_a_chair_leaves_the_chair_out(): void {
		$with    = CharacterBuilder::svg( CharacterSpec::from_array( [ 'stance' => 'sitting' ] ) );
		$without = CharacterBuilder::svg(
			CharacterSpec::from_array(
				[
					'stance' => 'sitting',
					'seat'   => 'none',
				]
			)
		);

		$this->assertStringContainsString( 'slot-neutral-light" x="34"', $with );
		$this->assertStringNotContainsString( 'slot-neutral-light" x="34"', $without );
		$this->assertStringContainsString( 'viewBox="0 0 200 300"', $without );
	}
	public function test_a_hand_made_pose_round_trips_and_fits_its_canvas(): void {
		foreach ( [ 'standing', 'sitting', 'walking', 'all_fours', 'supine', 'bending' ] as $stance ) {
			$base           = CharacterSpec::from_array( [ 'stance' => $stance ] );
			$pose           = CharacterBuilder::pose( $base );
			$data           = $base->to_array();
			$data['custom'] = $pose['angles'];
			$spec           = CharacterSpec::from_array( $data );

			$this->assertNotNull( $spec->custom, $stance );
			$this->assertSame( $spec->custom, CharacterSpec::from_array( $spec->to_array() )->custom, $stance );

			$extent = CharacterBuilder::extent( $spec );
			$this->assertNotNull( $extent, $stance );
			[ $w, $h ] = $extent['vb'];
			foreach ( $extent['points'] as [ $x, $y ] ) {
				$this->assertGreaterThanOrEqual( 0, $x, $stance );
				$this->assertLessThanOrEqual( $w, $x, $stance );
				$this->assertGreaterThanOrEqual( 0, $y, $stance );
				$this->assertLessThanOrEqual( $h, $y, $stance );
			}
			$this->assertNotFalse( simplexml_load_string( CharacterBuilder::svg( $spec ) ), $stance );
			$this->assertStringContainsString( 'viewBox="0 0 ' . CharacterBuilder::EDIT_FRAME . ' ' . CharacterBuilder::EDIT_FRAME . '"', CharacterBuilder::svg( $spec, 'Editing', [ 'person' ], '', true ), $stance );
		}
	}

	public function test_a_broken_hand_made_pose_is_dropped_and_a_new_stance_clears_it(): void {
		$this->assertNull( CharacterSpec::from_array( [ 'custom' => [ 'theta' => 'x' ] ] )->custom );
		$this->assertNull( CharacterSpec::from_array( [ 'custom' => 'nope' ] )->custom );

		$pose          = CharacterBuilder::pose( CharacterSpec::from_array( [] ) )['angles'];
		$pose['theta'] = 999;
		$spec          = CharacterSpec::from_array( [ 'custom' => $pose ] );
		$this->assertSame( 180.0, $spec->custom['theta'] );
		$this->assertNull( $spec->with( [ 'stance' => 'walking' ] )->custom );
		$this->assertNotNull( $spec->with( [ 'top' => 'hoodie' ] )->custom );
	}
}
