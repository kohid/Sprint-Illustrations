<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Selection\AiResponse;

final class AiResponseTest extends TestCase {

	private const IDS  = [ 'fixture-duo', 'fixture-hero' ];
	private const TAGS = [ 'coffee', 'drink', 'person', 'team' ];

	private static function reply( mixed $json, array $before = [] ): array {
		return [
			'content' => array_merge(
				$before,
				[
					[
						'type' => 'text',
						'text' => is_string( $json ) ? $json : json_encode( $json ),
					],
				]
			),
		];
	}

	public function test_valid_reply_after_thinking_block(): void {
		$spec = AiResponse::spec(
			self::reply(
				[
					'template' => 'fixture-duo',
					'keywords' => [ 'team', 'coffee' ],
					'title'    => 'Two people sharing coffee',
				],
				[
					[
						'type'     => 'thinking',
						'thinking' => '…',
					],
				]
			),
			self::IDS,
			self::TAGS
		);

		$this->assertSame(
			[
				'template' => 'fixture-duo',
				'keywords' => [ 'team', 'coffee' ],
				'title'    => 'Two people sharing coffee',
			],
			$spec
		);
	}

	public function test_filters_unknown_duplicate_and_excess_keywords_and_cleans_title(): void {
		$spec = AiResponse::spec(
			self::reply(
				[
					'template' => 'fixture-hero',
					'keywords' => [ 'team', 'TEAM', 'rocket', 'coffee', 'drink', 'person', 'team', 'coffee' ],
					'title'    => '<b>' . str_repeat( 'a', 200 ) . '</b>',
				]
			),
			self::IDS,
			[ 'coffee', 'drink', 'person', 'team', 'a', 'b' ]
		);

		$this->assertSame( [ 'team', 'coffee', 'drink', 'person' ], $spec['keywords'] );
		$this->assertSame( AiResponse::MAX_TITLE, mb_strlen( $spec['title'] ) );
		$this->assertStringNotContainsString( '<b>', $spec['title'] );
	}

	public function test_invalid_replies(): void {
		$this->assertNull(
			AiResponse::spec(
				self::reply(
					[
						'template' => 'nope',
						'keywords' => [],
						'title'    => 'x',
					]
				),
				self::IDS,
				self::TAGS
			)
		);
		$this->assertNull( AiResponse::spec( self::reply( '{not json' ), self::IDS, self::TAGS ) );
		$this->assertNull( AiResponse::spec( self::reply( [ 'keywords' => [ 'team' ] ] ), self::IDS, self::TAGS ) );
		$this->assertNull(
			AiResponse::spec(
				[
					'content' => [
						[
							'type'     => 'thinking',
							'thinking' => '…',
						],
					],
				],
				self::IDS,
				self::TAGS
			)
		);
		$this->assertNull( AiResponse::spec( [], self::IDS, self::TAGS ) );
	}
}
