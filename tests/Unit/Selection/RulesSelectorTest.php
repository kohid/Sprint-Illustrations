<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\TemplateRepository;
use SprintIllustrations\Selection\Keywords;
use SprintIllustrations\Selection\RulesSelector;

final class RulesSelectorTest extends TestCase {

	private const ASSETS = __DIR__ . '/../../fixtures/library/assets';

	private function selector( array $template_dirs = [ self::ASSETS . '/templates' ] ): RulesSelector {
		return new RulesSelector(
			TemplateRepository::from_directories( $template_dirs ),
			Manifest::from_files( [ self::ASSETS . '/manifest.json' ] ),
			Keywords::from_file( self::ASSETS . '/keywords/synonyms.json' )
		);
	}

	public function test_template_tags_dominate(): void {
		$this->assertSame( 'fixture-object', $this->selector()->choose_template( [ 'coffee', 'drink' ], 1 )->id );
		$this->assertSame( 'fixture-hero', $this->selector()->choose_template( [ 'hero' ], 1 )->id );
	}

	public function test_ties_break_by_seed_deterministically(): void {
		$ids = [];
		for ( $seed = 1; $seed <= 30; $seed++ ) {
			$ids[ $seed ] = $this->selector()->choose_template( [], $seed )->id;
			$this->assertSame( $ids[ $seed ], $this->selector()->choose_template( [], $seed )->id );
		}

		$this->assertGreaterThan( 1, count( array_unique( $ids ) ) );
	}

	public function test_suggest_expands_synonyms_and_keeps_raw_tokens(): void {
		$spec = $this->selector()->suggest( 'Our favourite cafe', 3 );

		$this->assertSame( 'fixture-object', $spec->template );
		$this->assertSame( 3, $spec->seed );
		$this->assertSame( [ 'favourite', 'cafe' ], $spec->keywords );
	}

	public function test_no_templates_throws(): void {
		$this->expectException( CompositionException::class );
		$this->selector( [] )->choose_template( [], 1 );
	}
}
