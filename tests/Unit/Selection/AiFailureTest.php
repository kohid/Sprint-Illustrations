<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Selection\AiFailure;

final class AiFailureTest extends TestCase {

	public function test_workspace_error_is_recognized(): void {
		$this->assertSame(
			AiFailure::WORKSPACE,
			AiFailure::reason( 'invalid_request_error', 'This API key is not scoped to a workspace, so this request must include the anthropic-workspace-id header with the ID of the workspace to use.' )
		);
	}

	public function test_reasons_by_type(): void {
		$this->assertSame( AiFailure::AUTH, AiFailure::reason( 'authentication_error', 'invalid x-api-key' ) );
		$this->assertSame( AiFailure::AUTH, AiFailure::reason( 'permission_error', 'no access' ) );
		$this->assertSame( AiFailure::BUSY, AiFailure::reason( 'rate_limit_error', '' ) );
		$this->assertSame( AiFailure::BUSY, AiFailure::reason( 'overloaded_error', '' ) );
		$this->assertSame( AiFailure::NETWORK, AiFailure::reason( 'http_request_failed', 'cURL error 28' ) );
		$this->assertSame( AiFailure::NETWORK, AiFailure::reason( 'invalid_json', '' ) );
		$this->assertSame( AiFailure::REQUEST, AiFailure::reason( 'invalid_request_error', 'model: not supported' ) );
		$this->assertSame( AiFailure::REQUEST, AiFailure::reason( 'not_found_error', 'model: claude-x' ) );
		$this->assertSame( AiFailure::REQUEST, AiFailure::reason( 'api_error', 'Internal server error' ) );
	}

	public function test_detail_is_plain_and_capped(): void {
		$detail = AiFailure::detail( '<b>Bad</b>   request ' . str_repeat( 'x', 400 ) );

		$this->assertStringStartsWith( 'Bad request x', $detail );
		$this->assertSame( AiFailure::MAX_DETAIL, mb_strlen( $detail ) );
	}
}
