<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\LibraryException;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\Piece;

final class ManifestTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../../fixtures/library/assets/manifest.json';

	public function test_loads_pieces_with_absolute_paths(): void {
		$manifest = Manifest::from_files( [ self::FIXTURE ] );
		$stick    = $manifest->get( 'char-stick' );

		$this->assertInstanceOf( Piece::class, $stick );
		$this->assertFileExists( $stick->path );
		$this->assertSame( [ 80.0, 110.0 ], $stick->anchor( 'hold' ) );
		$this->assertSame( 'handheld', $stick->accepts['hold'] );
		$this->assertSame( 100.0, $stick->width() );
		$this->assertTrue( $stick->has_tag( 'PERSON' ) );
		$this->assertSame( '7', $manifest->version() );
	}

	public function test_by_category_preserves_order(): void {
		$ids = array_map( static fn( Piece $p ) => $p->id, Manifest::from_files( [ self::FIXTURE ] )->by_category( 'objects' ) );

		$this->assertSame( [ 'obj-mug', 'obj-plant', 'obj-orb' ], $ids );
	}

	public function test_duplicates_are_skipped_and_reported(): void {
		$manifest = Manifest::from_files( [ self::FIXTURE, self::FIXTURE ] );

		$this->assertCount( 7, $manifest->all() );
		$this->assertCount( 7, $manifest->errors() );
		$this->assertSame( '7.7', $manifest->version() );
	}

	public function test_missing_files_are_skipped(): void {
		$manifest = Manifest::from_files( [ '/nope/manifest.json', self::FIXTURE ] );

		$this->assertCount( 7, $manifest->all() );
		$this->assertSame( [], $manifest->errors() );
	}

	public function test_invalid_json_throws(): void {
		$file = tempnam( sys_get_temp_dir(), 'si' );
		file_put_contents( $file, '{"pieces": 5}' );

		$this->expectException( LibraryException::class );
		try {
			Manifest::from_files( [ $file ] );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * @dataProvider invalid_pieces
	 */
	public function test_invalid_piece_entries_throw( array $entry ): void {
		$this->expectException( LibraryException::class );
		Piece::from_array( $entry, '/tmp' );
	}

	public static function invalid_pieces(): array {
		$valid = [
			'id'       => 'x',
			'category' => 'objects',
			'file'     => 'x.svg',
			'viewBox'  => [ 0, 0, 10, 10 ],
		];

		return [
			'missing id'   => [ array_diff_key( $valid, [ 'id' => 1 ] ) ],
			'bad category' => [ [ 'category' => 'monsters' ] + $valid ],
			'bad viewBox'  => [ [ 'viewBox' => [ 0, 0, 0, 10 ] ] + $valid ],
		];
	}
}
