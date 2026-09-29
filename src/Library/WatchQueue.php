<?php
/**
 * What the request watcher announces and which drawings have stalled.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Rows use the piece request row shape (id, state, date, changed, …); times are GMT `Y-m-d H:i:s`.
 */
final class WatchQueue {

	public const STALE_SECONDS = 1800;

	/**
	 * Identity of a queued request; it changes when the request is re-queued (Try again sets `changed`).
	 *
	 * @param array<string, mixed> $row Request.
	 * @return string
	 */
	public static function key( array $row ): string {
		return $row['id'] . '|' . ( '' !== $row['changed'] ? $row['changed'] : $row['date'] );
	}

	/**
	 * Queued requests not announced yet (oldest first), and the keys to remember for the next check.
	 *
	 * @param array<int, array<string, mixed>> $rows Requests.
	 * @param array<int, string>               $seen Keys already announced.
	 * @return array{rows: array<int, array<string, mixed>>, seen: array<int, string>}
	 */
	public static function announce( array $rows, array $seen ): array {
		$queued = array_values( array_filter( $rows, static fn( array $row ): bool => 'queued' === $row['state'] ) );
		usort( $queued, static fn( array $a, array $b ): int => strcmp( (string) $a['date'], (string) $b['date'] ) );
		$new = array_values( array_filter( $queued, static fn( array $row ): bool => ! in_array( self::key( $row ), $seen, true ) ) );

		return [
			'rows' => $new,
			'seen' => array_map( [ self::class, 'key' ], $queued ),
		];
	}

	/**
	 * IDs of requests left in `drawing` for longer than STALE_SECONDS (the session that took them closed).
	 *
	 * @param array<int, array<string, mixed>> $rows Requests.
	 * @param int                              $now  Current Unix time.
	 * @return array<int, int>
	 */
	public static function stale( array $rows, int $now ): array {
		$ids = [];
		foreach ( $rows as $row ) {
			if ( 'drawing' !== $row['state'] || '' === $row['changed'] ) {
				continue;
			}
			$changed = strtotime( $row['changed'] . ' UTC' );
			if ( false !== $changed && $now - $changed > self::STALE_SECONDS ) {
				$ids[] = (int) $row['id'];
			}
		}

		return $ids;
	}
}
