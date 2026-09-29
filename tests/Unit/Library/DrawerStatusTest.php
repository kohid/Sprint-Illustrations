<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\DrawerStatus;

final class DrawerStatusTest extends TestCase {

	public function test_never_seen_is_offline(): void {
		$this->assertFalse( DrawerStatus::is_online( 0, 1000 ) );
	}

	public function test_threshold(): void {
		$this->assertTrue( DrawerStatus::is_online( 1000, 1030 ) );
		$this->assertFalse( DrawerStatus::is_online( 1000, 1031 ) );
	}
}
