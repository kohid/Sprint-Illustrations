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
		$this->assertSame( [ 'queued', 'drawing', 'review', 'done', 'discarded', 'declined' ], PieceRequest::STATES );
	}

	public function test_transitions(): void {
		$this->assertTrue( PieceRequest::can_move( 'queued', 'drawing' ) );
		$this->assertTrue( PieceRequest::can_move( 'queued', 'review' ) );
		$this->assertTrue( PieceRequest::can_move( 'drawing', 'review' ) );
		$this->assertTrue( PieceRequest::can_move( 'drawing', 'queued' ) );
		$this->assertTrue( PieceRequest::can_move( 'review', 'done' ) );
		$this->assertTrue( PieceRequest::can_move( 'review', 'discarded' ) );
		$this->assertTrue( PieceRequest::can_move( 'discarded', 'queued' ) );
		$this->assertTrue( PieceRequest::can_move( 'declined', 'queued' ) );

		$this->assertFalse( PieceRequest::can_move( 'queued', 'done' ) );
		$this->assertFalse( PieceRequest::can_move( 'review', 'queued' ) );
		$this->assertFalse( PieceRequest::can_move( 'done', 'queued' ) );
		$this->assertFalse( PieceRequest::can_move( 'done', 'discarded' ) );
		$this->assertFalse( PieceRequest::can_move( 'nope', 'queued' ) );
	}

	public function test_sample_scene_per_category(): void {
		$this->assertSame(
			[
				'template' => 'hero-left-character',
				'picks'    => [ 'subject' => 'char-x' ],
			],
			PieceRequest::sample( 'characters', 'char-x' )
		);
		$this->assertSame( [ 'hero' => 'obj-x' ], PieceRequest::sample( 'objects', 'obj-x' )['picks'] );
		$this->assertSame( [ 'bg' => 'bg-x' ], PieceRequest::sample( 'backgrounds', 'bg-x' )['picks'] );
		$this->assertSame( [ 'decor' => [ 'decor-x' ] ], PieceRequest::sample( 'decor', 'decor-x' )['picks'] );
		$this->assertSame( 'centered-object-with-decor', PieceRequest::sample( 'decor', 'decor-x' )['template'] );
	}

	public function test_only_discarded_and_declined_requests_can_be_removed(): void {
		foreach ( [ 'discarded', 'declined' ] as $state ) {
			$this->assertTrue( PieceRequest::can_remove( $state ), $state );
		}
		foreach ( [ 'queued', 'drawing', 'review', 'done', 'nonsense' ] as $state ) {
			$this->assertFalse( PieceRequest::can_remove( $state ), $state );
		}
	}
}
