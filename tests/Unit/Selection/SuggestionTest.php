<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Selection\Suggestion;

final class SuggestionTest extends TestCase {

	private const IDS  = [ 'fixture-duo', 'fixture-hero' ];
	private const TAGS = [ 'coffee', 'drink', 'laptop', 'person', 'plant', 'team', 'work' ];

	public function test_valid_with_comma_keywords(): void {
		$result = Suggestion::validate(
			[
				'template' => 'fixture-duo',
				'keywords' => ' Team, laptop ,team ',
				'title'    => 'Two people at work',
			],
			self::IDS,
			self::TAGS,
			true
		);

		$this->assertSame( '', $result['error'] );
		$this->assertSame(
			[
				'template' => 'fixture-duo',
				'keywords' => [ 'team', 'laptop' ],
				'title'    => 'Two people at work',
			],
			$result['suggestion']
		);
	}

	public function test_unknown_tags_are_an_error_when_strict_and_dropped_otherwise(): void {
		$data = [
			'template' => 'fixture-hero',
			'keywords' => [ 'team', 'unicorn', 'rocket' ],
			'title'    => 'x',
		];

		$strict = Suggestion::validate( $data, self::IDS, self::TAGS, true );
		$this->assertNull( $strict['suggestion'] );
		$this->assertStringContainsString( 'unicorn, rocket', $strict['error'] );

		$lenient = Suggestion::validate( $data, self::IDS, self::TAGS, false );
		$this->assertSame( [ 'team' ], $lenient['suggestion']['keywords'] );
	}

	public function test_caps_keywords_and_cleans_title(): void {
		$result = Suggestion::validate(
			[
				'template' => 'fixture-hero',
				'keywords' => self::TAGS,
				'title'    => "<b>Hi</b>\n" . str_repeat( 'a', 200 ),
			],
			self::IDS,
			self::TAGS,
			true
		);

		$this->assertCount( Suggestion::MAX_KEYWORDS, $result['suggestion']['keywords'] );
		$this->assertStringStartsWith( 'Hi a', $result['suggestion']['title'] );
		$this->assertSame( Suggestion::MAX_TITLE, mb_strlen( $result['suggestion']['title'] ) );
	}

	public function test_unknown_or_missing_template(): void {
		$this->assertStringContainsString( 'nope', Suggestion::validate( [ 'template' => 'nope' ], self::IDS, self::TAGS, true )['error'] );
		$this->assertNotSame( '', Suggestion::validate( [], self::IDS, self::TAGS, false )['error'] );
		$this->assertNull( Suggestion::validate( [ 'template' => [ 'x' ] ], self::IDS, self::TAGS, false )['suggestion'] );
	}
}
