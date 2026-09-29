<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\ManifestEditor;

final class ManifestEditorTest extends TestCase {

	private const MANIFEST = [
		'version' => 4,
		'pieces'  => [
			[
				'id'   => 'obj-taxi',
				'file' => 'pieces/objects/taxi.svg',
			],
			[
				'id'   => 'obj-mug',
				'file' => 'pieces/objects/mug.svg',
			],
		],
	];

	public function test_removes_entry_and_bumps_version(): void {
		$result = ManifestEditor::remove( self::MANIFEST, 'obj-taxi' );

		$this->assertSame( 'pieces/objects/taxi.svg', $result['file'] );
		$this->assertSame( 5, $result['manifest']['version'] );
		$this->assertSame( [ 'obj-mug' ], array_column( $result['manifest']['pieces'], 'id' ) );
		$this->assertSame( [ 0 ], array_keys( $result['manifest']['pieces'] ) );
	}

	public function test_unknown_id_changes_nothing(): void {
		$result = ManifestEditor::remove( self::MANIFEST, 'obj-nope' );

		$this->assertNull( $result['file'] );
		$this->assertSame( self::MANIFEST, $result['manifest'] );
	}
}
