<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Selection\AiRequest;
use SprintIllustrations\Services;

final class AiRequestTest extends TestCase {

	private Services $services;

	protected function setUp(): void {
		$this->services = Services::create( __DIR__ . '/../../fixtures/library' );
	}

	public function test_tags_are_sorted_unique_pieces_and_templates(): void {
		$tags = AiRequest::tags( $this->services->manifest->all(), $this->services->templates->all() );

		$this->assertSame( $tags, array_values( array_unique( $tags ) ) );
		$this->assertContains( 'coffee', $tags );
		$this->assertContains( 'team', $tags );
		$sorted = $tags;
		sort( $sorted );
		$this->assertSame( $sorted, $tags );
	}

	public function test_body_shape_and_schema(): void {
		$templates = $this->services->templates->all();
		$tags      = AiRequest::tags( $this->services->manifest->all(), $templates );
		$body      = AiRequest::body( '<p>Our <b>team</b> drinks coffee</p>' . str_repeat( 'x', 5000 ), $templates, $tags, 'claude-sonnet-5-5' );

		$this->assertSame( 'claude-sonnet-5-5', $body['model'] );
		$this->assertSame( 1024, $body['max_tokens'] );
		$this->assertSame( 'low', $body['output_config']['effort'] );

		$schema = $body['output_config']['format']['schema'];
		$this->assertSame( 'json_schema', $body['output_config']['format']['type'] );
		$this->assertSame( [ 'template', 'keywords', 'title' ], $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertSame( array_values( array_map( static fn( $t ) => $t->id, $templates ) ), $schema['properties']['template']['enum'] );
		$this->assertSame( $tags, $schema['properties']['keywords']['items']['enum'] );

		$json = (string) json_encode( $schema );
		foreach ( [ 'maxItems', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum' ] as $unsupported ) {
			$this->assertStringNotContainsString( $unsupported, $json );
		}

		$message = $body['messages'][0]['content'];
		$this->assertSame( 'user', $body['messages'][0]['role'] );
		$this->assertStringContainsString( 'Our team drinks coffee', $message );
		$this->assertStringNotContainsString( '<b>', $message );
		$this->assertStringContainsString( 'fixture-duo', $message );
		$this->assertLessThan( 4000 + 2000, strlen( $message ) );
		$this->assertStringContainsString( 'alt text', $body['system'] );
	}

	public function test_default_model_is_listed(): void {
		$this->assertArrayHasKey( AiRequest::DEFAULT_MODEL, AiRequest::MODELS );
		$this->assertSame( 'claude-sonnet-5-5', AiRequest::DEFAULT_MODEL );
	}
}
