<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Compose;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\SceneSpec;

final class ComposedSvgTest extends TestCase {

	public function test_round_trip(): void {
		$svg = new ComposedSvg(
			'<svg xmlns="http://www.w3.org/2000/svg"/>',
			SceneSpec::from_array(
				[
					'template' => 'fixture-hero',
					'seed'     => 4,
					'picks'    => [ 'subject' => 'char-stick' ],
				]
			),
			[ 'note' ]
		);

		$copy = ComposedSvg::from_array( json_decode( (string) json_encode( $svg->to_array() ), true ) );

		$this->assertSame( $svg->markup, $copy->markup );
		$this->assertSame( $svg->spec->to_array(), $copy->spec->to_array() );
		$this->assertSame( [ 'note' ], $copy->warnings );
	}

	public function test_from_array_rejects_bad_shapes(): void {
		$this->assertNull( ComposedSvg::from_array( [] ) );
		$this->assertNull(
			ComposedSvg::from_array(
				[
					'markup'   => 1,
					'spec'     => [],
					'warnings' => [],
				]
			)
		);
	}
}
