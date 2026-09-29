<?php
/**
 * Whether a Claude Code session is watching the piece request queue.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Online while the watcher's last heartbeat is recent.
 */
final class DrawerStatus {

	public const ONLINE_SECONDS = 30;

	/**
	 * Whether the watcher counts as online.
	 *
	 * @param int $last_seen Last heartbeat (Unix time; 0 = never).
	 * @param int $now       Current Unix time.
	 * @return bool
	 */
	public static function is_online( int $last_seen, int $now ): bool {
		return 0 < $last_seen && $now - $last_seen <= self::ONLINE_SECONDS;
	}
}
