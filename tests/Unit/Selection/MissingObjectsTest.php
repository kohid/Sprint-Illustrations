<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Selection\Keywords;
use SprintIllustrations\Selection\MissingObjects;

final class MissingObjectsTest extends TestCase {

	private Keywords $keywords;

	protected function setUp(): void {
		$this->keywords = new Keywords( [ 'app' => [ 'phone' ] ] );
	}

	public function test_returns_words_the_library_has_nothing_for(): void {
		$found = MissingObjects::find( 'A drone delivering a parcel to our team, showing the new app', $this->keywords, [ 'team', 'phone' ] );

		$this->assertSame( [ 'drone', 'delivering', 'parcel' ], $found );
	}

	public function test_skips_tags_synonym_words_filler_and_numbers(): void {
		$this->assertSame( [], MissingObjects::find( 'team app about 2026 page', $this->keywords, [ 'team' ] ) );
	}

	public function test_is_capped(): void {
		$found = MissingObjects::find( 'alpha bravo charlie delta echo foxtrot golf hotel', $this->keywords, [] );

		$this->assertCount( MissingObjects::MAX, $found );
	}

	public function test_words_are_singular_and_lower_case(): void {
		$this->assertSame( [ 'drone' ], MissingObjects::find( 'DRONES', $this->keywords, [] ) );
	}
}
