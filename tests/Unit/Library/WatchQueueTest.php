<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\WatchQueue;

final class WatchQueueTest extends TestCase {

	/**
	 * @return array{id: int, state: string, date: string, changed: string}
	 */
	private function row( int $id, string $state, string $date, string $changed = '' ): array {
		return [
			'id'      => $id,
			'state'   => $state,
			'date'    => $date,
			'changed' => $changed,
		];
	}

	public function test_announces_queued_once_oldest_first(): void {
		$rows  = [
			$this->row( 2, 'queued', '2026-09-29 10:00:00' ),
			$this->row( 1, 'queued', '2026-09-29 09:00:00' ),
			$this->row( 3, 'drawing', '2026-09-29 08:00:00' ),
		];
		$first = WatchQueue::announce( $rows, [] );

		$this->assertSame( [ 1, 2 ], array_column( $first['rows'], 'id' ) );
		$this->assertSame( [], WatchQueue::announce( $rows, $first['seen'] )['rows'] );
	}

	public function test_requeue_is_announced_again(): void {
		$seen = WatchQueue::announce( [ $this->row( 1, 'queued', '2026-09-29 09:00:00' ) ], [] )['seen'];
		$next = WatchQueue::announce( [ $this->row( 1, 'queued', '2026-09-29 09:00:00', '2026-09-29 11:00:00' ) ], $seen );

		$this->assertSame( [ 1 ], array_column( $next['rows'], 'id' ) );
	}

	public function test_stale_drawing(): void {
		$now  = (int) strtotime( '2026-09-29 12:00:00 UTC' );
		$rows = [
			$this->row( 1, 'drawing', '2026-09-29 09:00:00', '2026-09-29 11:29:59' ),
			$this->row( 2, 'drawing', '2026-09-29 09:00:00', '2026-09-29 11:30:00' ),
			$this->row( 3, 'queued', '2026-09-29 09:00:00', '2026-09-29 08:00:00' ),
		];

		$this->assertSame( [ 1 ], WatchQueue::stale( $rows, $now ) );
	}
}
