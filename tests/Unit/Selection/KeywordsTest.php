<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Selection\Keywords;

final class KeywordsTest extends TestCase {

	public function test_tokenize(): void {
		$tokens = ( new Keywords() )->tokenize( '<h1>How Remote Teams Use Coffee Breaks & Analytics</h1> for the best stories, glass, status' );

		$this->assertSame( [ 'remote', 'team', 'use', 'coffee', 'break', 'analytic', 'story', 'glass', 'status' ], $tokens );
	}

	public function test_expand_with_synonyms_file(): void {
		$keywords = Keywords::from_file( __DIR__ . '/../../fixtures/library/assets/keywords/synonyms.json' );

		$this->assertSame( [ 'cafe', 'team', 'coffee', 'drink', 'person', 'people' ], $keywords->expand( [ 'cafe', 'team' ] ) );
	}

	public function test_missing_synonyms_file_is_harmless(): void {
		$this->assertSame( [ 'x' ], Keywords::from_file( '/nope.json' )->expand( [ 'x' ] ) );
	}
}
