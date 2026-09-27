<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Cli\Prompter;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Security\Sanitizer;

final class ManifestBuilderTest extends TestCase {

	private const SOURCE = __DIR__ . '/../../fixtures/source';

	private string $target;

	protected function setUp(): void {
		$this->target = sys_get_temp_dir() . '/si-build-' . uniqid();
	}

	protected function tearDown(): void {
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->target, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->target );
	}

	private function build( ?Prompter $prompter = null ): \SprintIllustrations\Cli\BuildReport {
		return ( new ManifestBuilder( new Sanitizer(), $prompter ?? new NullPrompter() ) )->build( self::SOURCE, $this->target );
	}

	private function entries(): array {
		$data = json_decode( file_get_contents( $this->target . '/manifest.json' ), true );
		return array_column( $data['pieces'], null, 'id' );
	}

	public function test_builds_entries_from_metadata_and_markers(): void {
		$report  = $this->build();
		$entries = $this->entries();

		$this->assertSame( [ 'char-hero-pose', 'obj-coffee-cup' ], $report->built );
		$this->assertSame( 1, $report->version );

		$hero = $entries['char-hero-pose'];
		$this->assertSame( 'Hero pose', $hero['label'] );
		$this->assertSame( 'characters', $hero['category'] );
		$this->assertSame( 'pieces/characters/hero-pose.svg', $hero['file'] );
		$this->assertSame( [ 'person', 'standing', 'hero' ], $hero['tags'] );
		$this->assertSame( 31, $hero['z'] );
		$this->assertSame( [ 70, 100 ], $hero['anchors']['hold'] );
		$this->assertSame( [ 50, 200 ], $hero['anchors']['ground'] );
		$this->assertSame( [ 'hold' => 'handheld' ], $hero['accepts'] );
		$this->assertSame( [ 'primary', 'skin' ], $hero['slots'] );
		$this->assertSame( [ 0, 0, 100, 200 ], $hero['viewBox'] );

		$cup = $entries['obj-coffee-cup'];
		$this->assertSame( [ 0, 0, 20, 24 ], $cup['viewBox'] );
		$this->assertSame( [ 'coffee', 'cup' ], $cup['tags'], 'Tags default to the normalized file name parts.' );
		$this->assertSame( 20, $cup['z'] );
		$this->assertSame( [ 'handheld' => 'grip' ], $cup['mounts'] );
	}

	public function test_cleaned_files_have_no_markers_metadata_or_scripts(): void {
		$this->build();

		$hero = file_get_contents( $this->target . '/pieces/characters/hero-pose.svg' );
		$cup  = file_get_contents( $this->target . '/pieces/objects/coffee-cup.svg' );

		$this->assertStringNotContainsString( 'anchor-', $hero . $cup );
		$this->assertStringNotContainsString( 'data-si', $hero . $cup );
		$this->assertStringNotContainsString( 'script', $cup );
		$this->assertSame( sha1( trim( $hero ) ), $this->entries()['char-hero-pose']['hash'] );
	}

	public function test_reports_warnings_and_errors(): void {
		$report   = $this->build();
		$warnings = implode( "\n", $report->warnings );

		$this->assertStringContainsString( 'char-hero-pose: accepts refers to unknown anchor "ghost"', $warnings );
		$this->assertStringContainsString( 'obj-coffee-cup: literal fill "#ff0000"', $warnings );
		$this->assertStringContainsString( 'obj-coffee-cup: <path> has no slot class', $warnings );
		$this->assertStringContainsString( 'obj-coffee-cup: <script> is not allowed', $warnings );
		$this->assertStringNotContainsString( '<rect> has no slot class', $warnings, 'Shapes in defs/clipPath and anchor markers are exempt.' );
		$this->assertCount( 1, $report->errors );
		$this->assertStringStartsWith( 'broken.svg:', $report->errors[0] );
	}

	public function test_rebuild_bumps_version_and_keeps_other_entries(): void {
		$this->build();

		$data             = json_decode( file_get_contents( $this->target . '/manifest.json' ), true );
		$data['pieces'][] = [
			'id'       => 'obj-legacy',
			'category' => 'objects',
			'file'     => 'pieces/objects/legacy.svg',
			'viewBox'  => [ 0, 0, 1, 1 ],
		];
		file_put_contents( $this->target . '/manifest.json', json_encode( $data ) );

		$report = $this->build();

		$this->assertSame( 2, $report->version );
		$this->assertArrayHasKey( 'obj-legacy', $this->entries() );
	}

	public function test_prompt_answers_override_defaults(): void {
		$prompter = new class() implements Prompter {
			public array $questions = [];

			public function ask( string $question, string $fallback ): string {
				$this->questions[] = $question;
				return str_contains( $question, '[obj-coffee-cup] Tags' ) ? 'coffee, drink, handheld' : $fallback;
			}
		};

		$this->build( $prompter );

		$this->assertSame( [ 'coffee', 'drink', 'handheld' ], $this->entries()['obj-coffee-cup']['tags'] );
		$this->assertCount( 8, $prompter->questions, 'Tags, z, accepts, mounts for each of the two valid pieces.' );
	}

	public function test_output_loads_as_manifest(): void {
		$this->build();

		$manifest = Manifest::from_files( [ $this->target . '/manifest.json' ] );

		$this->assertFileExists( $manifest->get( 'char-hero-pose' )->path );
		$this->assertSame( [ 70.0, 100.0 ], $manifest->get( 'char-hero-pose' )->anchor( 'hold' ) );
	}
}
