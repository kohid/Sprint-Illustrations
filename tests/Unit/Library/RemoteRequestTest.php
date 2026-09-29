<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\RemoteRequest;

final class RemoteRequestTest extends TestCase {

	public function test_accepts_a_draft_and_cleans_the_name(): void {
		$this->assertSame(
			[
				'name' => 'circle-curve',
				'svg'  => '<svg viewBox="0 0 1 1"/>',
			],
			RemoteRequest::draft( 'Circle Curve.svg', "  <svg viewBox=\"0 0 1 1\"/>\n" )
		);
	}

	public function test_name_cannot_climb_out_of_the_folder(): void {
		$result = RemoteRequest::draft( '../../etc/passwd', '<svg/>' );

		$this->assertSame( 'passwd', $result['name'] );
	}

	public function test_refuses_bad_payloads(): void {
		$this->assertIsString( RemoteRequest::draft( null, '<svg/>' ) );
		$this->assertIsString( RemoteRequest::draft( 'taxi', [ '<svg/>' ] ) );
		$this->assertIsString( RemoteRequest::draft( '...', '<svg/>' ) );
		$this->assertIsString( RemoteRequest::draft( 'taxi', '' ) );
		$this->assertIsString( RemoteRequest::draft( 'taxi', '<html></html>' ) );
	}

	public function test_refuses_an_oversized_svg(): void {
		$big = '<svg>' . str_repeat( 'x', RemoteRequest::MAX_SVG_BYTES ) . '</svg>';

		$this->assertIsString( RemoteRequest::draft( 'taxi', $big ) );
	}

	public function test_note(): void {
		$this->assertSame( 'Too detailed', RemoteRequest::note( '  Too detailed ' ) );
		$this->assertNull( RemoteRequest::note( '   ' ) );
		$this->assertNull( RemoteRequest::note( [ 'x' ] ) );
		$this->assertSame( RemoteRequest::MAX_NOTE, mb_strlen( (string) RemoteRequest::note( str_repeat( 'é', 5000 ) ) ) );
	}
}
