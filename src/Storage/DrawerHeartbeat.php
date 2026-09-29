<?php
/**
 * Heartbeat of the Claude Code request watcher (`requests watch`).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Library\DrawerStatus;

/**
 * Stored in a non-autoloaded option so the Library page can show whether a session is watching.
 */
final class DrawerHeartbeat {

	public const OPTION = 'sprint_illustrations_drawer';

	/**
	 * Record that the watcher is alive.
	 */
	public function beat(): void {
		update_option( self::OPTION, [ 'last_seen' => time() ], false );
	}

	/**
	 * Last heartbeat (Unix time; 0 = never).
	 *
	 * @return int
	 */
	public function last_seen(): int {
		$value = get_option( self::OPTION, [] );

		return is_array( $value ) ? (int) ( $value['last_seen'] ?? 0 ) : 0;
	}

	/**
	 * Whether a session is watching now.
	 *
	 * @return bool
	 */
	public function online(): bool {
		return DrawerStatus::is_online( $this->last_seen(), time() );
	}
}
