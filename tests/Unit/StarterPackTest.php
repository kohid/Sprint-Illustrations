<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Selection\Keywords;
use SprintIllustrations\Services;

/**
 * Guards the bundled library: sources lint clean, the committed manifest is current,
 * and every template composes without warnings.
 */
final class StarterPackTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	private static Services $services;

	public static function setUpBeforeClass(): void {
		self::$services = Services::create( self::ROOT );
	}

	public function test_sources_lint_clean_and_committed_manifest_is_current(): void {
		$target = sys_get_temp_dir() . '/si-pack-' . uniqid();
		$report = ( new ManifestBuilder( new Sanitizer(), new NullPrompter() ) )->build( self::ROOT . '/assets/pieces-src', $target );

		$this->assertSame( [], $report->errors );
		$this->assertSame( [], $report->warnings, "Authoring warnings:\n" . implode( "\n", $report->warnings ) );

		$fresh     = json_decode( file_get_contents( $target . '/manifest.json' ), true );
		$committed = json_decode( file_get_contents( self::ROOT . '/assets/manifest.json' ), true );

		$strip = static fn( array $data ) => array_column( $data['pieces'], null, 'id' );
		$this->assertEquals( $strip( $fresh ), $strip( $committed ), 'assets/manifest.json is stale: run php bin/build-manifest.php --non-interactive' );

		array_map( 'unlink', glob( $target . '/pieces/*/*.svg' ) );
		array_map( 'rmdir', glob( $target . '/pieces/*', GLOB_ONLYDIR ) );
		rmdir( $target . '/pieces' );
		unlink( $target . '/manifest.json' );
		rmdir( $target );
	}

	public function test_pieces_follow_conventions(): void {
		$keywords = new Keywords();

		foreach ( self::$services->manifest->all() as $piece ) {
			$this->assertNotEmpty( $piece->slots, $piece->id . ' uses no colour slots.' );
			$this->assertNotEmpty( $piece->tags, $piece->id . ' has no tags.' );

			foreach ( $piece->tags as $tag ) {
				$this->assertSame( [ $tag ], $keywords->tokenize( $tag ), "{$piece->id}: tag \"$tag\" must be a single, singular, non-stopword token." );
			}

			if ( 'characters' === $piece->category ) {
				$this->assertNotNull( $piece->anchor( 'ground' ), $piece->id . ' needs an anchor-ground marker.' );
				$this->assertArrayHasKey( 'hold', $piece->accepts, $piece->id . ' needs a "hold" anchor that accepts an attachment type.' );
			}

			foreach ( $piece->mounts as $type => $anchor ) {
				$this->assertContains( $type, [ 'handheld', 'lap', 'surface' ], "{$piece->id}: unknown mount type $type." );
			}
		}
	}

	public function test_every_template_composes_cleanly(): void {
		foreach ( self::$services->templates->ids() as $template ) {
			foreach ( ContactSheet::review_palettes() as $palette ) {
				for ( $seed = 1; $seed <= 8; $seed++ ) {
					$result = self::$services->composer->compose(
						SceneSpec::from_array(
							[
								'template' => $template,
								'seed'     => $seed,
							]
						),
						$palette
					);

					$this->assertSame( [], $result->warnings, "$template seed $seed" );
					$this->assertStringNotContainsString( 'slot-', $result->markup );
				}
			}
		}
	}

	public function test_pack_is_complete(): void {
		$count = static fn( string $category ) => count( self::$services->manifest->by_category( $category ) );

		$this->assertGreaterThanOrEqual( 8, $count( 'characters' ) );
		$this->assertGreaterThanOrEqual( 10, $count( 'objects' ) );
		$this->assertGreaterThanOrEqual( 3, $count( 'backgrounds' ) );
		$this->assertGreaterThanOrEqual( 6, $count( 'decor' ) );

		$lap = array_filter( self::$services->manifest->by_category( 'objects' ), static fn( $p ) => isset( $p->mounts['lap'] ) );
		$this->assertNotEmpty( $lap, 'Seated poses need at least one lap-mountable object.' );
	}

	public function test_template_tags_and_synonyms_use_token_forms(): void {
		$keywords = new Keywords();
		$synonyms = json_decode( file_get_contents( self::ROOT . '/assets/keywords/synonyms.json' ), true );

		foreach ( self::$services->templates->all() as $template ) {
			foreach ( $template->tags as $tag ) {
				$this->assertSame( [ $tag ], $keywords->tokenize( $tag ), "{$template->id}: tag \"$tag\"." );
			}
		}

		foreach ( $synonyms as $token => $related ) {
			$this->assertSame( [ $token ], $keywords->tokenize( $token ), "synonyms key \"$token\"." );
			foreach ( $related as $word ) {
				$this->assertSame( [ $word ], $keywords->tokenize( $word ), "synonym \"$word\" of \"$token\"." );
			}
		}
	}
}
