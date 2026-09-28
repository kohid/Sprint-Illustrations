<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Storage\Illustration;

final class IllustrationTest extends TestCase {

	public function test_round_trip(): void {
		$data = [
			'id'           => 12,
			'title'        => 'Homepage hero',
			'spec'         => SceneSpec::from_array(
				[
					'template' => 'hero-left-character',
					'seed'     => 4,
				]
			)->to_array(),
			'status'       => 'publish',
			'author_id'    => 3,
			'modified_gmt' => '2026-09-29T12:00:00',
		];

		$this->assertSame( $data, Illustration::from_array( $data )->to_array() );
	}

	public function test_normalizes_untrusted_input(): void {
		$item = Illustration::from_array(
			[
				'id'     => '-5',
				'title'  => '  <b>' . str_repeat( 'x', 300 ) . '</b> ',
				'spec'   => 'nope',
				'status' => 'weird',
			]
		);

		$this->assertNull( $item->id );
		$this->assertSame( 200, mb_strlen( $item->title ) );
		$this->assertStringNotContainsString( '<b>', $item->title );
		$this->assertNull( $item->spec->template );
		$this->assertSame( 'publish', $item->status );
		$this->assertSame( 0, $item->author_id );
	}

	public function test_copies(): void {
		$item = Illustration::from_array( [ 'title' => 'A' ] );

		$this->assertSame( 'B', $item->with_title( 'B' )->title );
		$this->assertSame( 9, $item->with_spec( SceneSpec::from_array( [ 'seed' => 9 ] ) )->spec->seed );
		$this->assertSame( 'A', $item->title );
	}
}
