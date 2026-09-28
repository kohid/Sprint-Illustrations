<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\LibraryFilter;
use SprintIllustrations\Services;

final class LibraryFilterTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	private function ids( array $items ): array {
		return array_map( static fn( $item ) => $item->id, $items );
	}

	public function test_category_filter(): void {
		$this->assertSame( [ 'char-stick', 'char-runner' ], $this->ids( LibraryFilter::pieces( $this->services->manifest->all(), 'characters', '' ) ) );
	}

	public function test_search_matches_label_and_tags_with_every_word(): void {
		$all = $this->services->manifest->all();

		$this->assertSame( [ 'char-runner' ], $this->ids( LibraryFilter::pieces( $all, 'characters', 'SPORT' ) ) );
		$this->assertSame( [ 'obj-mug' ], $this->ids( LibraryFilter::pieces( $all, 'objects', 'mug coffee' ) ) );
		$this->assertSame( [], LibraryFilter::pieces( $all, 'objects', 'mug plant' ) );
	}

	public function test_templates_search(): void {
		$all = $this->services->templates->all();

		$this->assertCount( count( $all ), LibraryFilter::templates( $all, '' ) );
		$this->assertSame( [ 'fixture-duo' ], $this->ids( LibraryFilter::templates( $all, 'team' ) ) );
	}
}
