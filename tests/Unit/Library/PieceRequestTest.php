<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\PieceRequest;

final class PieceRequestTest extends TestCase {

	public function test_valid_request(): void {
		$this->assertSame( '', PieceRequest::validate( 'objects', 'A black Aberdeen taxi, side view' ) );
	}

	public function test_unknown_category(): void {
		$this->assertStringContainsString( 'category', PieceRequest::validate( 'vehicles', 'A taxi' ) );
	}

	public function test_length_counts_clean_text(): void {
		$this->assertStringContainsString( '3', PieceRequest::validate( 'decor', ' <b>ab</b> ' ) );
		$this->assertStringContainsString( '300', PieceRequest::validate( 'decor', str_repeat( 'a', 301 ) ) );
		$this->assertSame( '', PieceRequest::validate( 'decor', str_repeat( 'a', 300 ) ) );
	}

	public function test_clean(): void {
		$this->assertSame( 'A taxi with a sign', PieceRequest::clean( "  A <em>taxi</em>\n with   a sign " ) );
	}

	public function test_states(): void {
		$this->assertSame( [ 'queued', 'done', 'declined' ], PieceRequest::STATES );
	}
}
