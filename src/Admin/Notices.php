<?php
/**
 * One-off admin notices.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

/**
 * Queues notices in a transient and shows them once to administrators.
 */
final class Notices {

	private const TRANSIENT = 'sprint_illustrations_notices';

	private const TYPES = [ 'success', 'warning', 'error', 'info' ];

	/**
	 * Queue a notice (keeps the last five).
	 *
	 * @param string $message Plain text.
	 * @param string $type    success, warning, error or info.
	 */
	public static function add( string $message, string $type = 'warning' ): void {
		$list   = get_transient( self::TRANSIENT );
		$list   = is_array( $list ) ? $list : [];
		$list[] = [
			'type'    => in_array( $type, self::TYPES, true ) ? $type : 'info',
			'message' => $message,
		];

		set_transient( self::TRANSIENT, array_slice( $list, -5 ), DAY_IN_SECONDS );
	}

	/**
	 * Queue a notice for one user (shown to them whatever their role).
	 *
	 * @param int    $user_id User.
	 * @param string $message Plain text.
	 * @param string $type    success, warning, error or info.
	 */
	public static function add_for_user( int $user_id, string $message, string $type = 'info' ): void {
		$key    = self::TRANSIENT . '_u' . $user_id;
		$list   = get_transient( $key );
		$list   = is_array( $list ) ? $list : [];
		$list[] = [
			'type'    => in_array( $type, self::TYPES, true ) ? $type : 'info',
			'message' => $message,
		];

		set_transient( $key, array_slice( $list, -5 ), HOUR_IN_SECONDS );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	/**
	 * Print and clear queued notices: the admin queue for admins, and the current user's own queue.
	 */
	public function render(): void {
		if ( current_user_can( 'manage_options' ) ) {
			$this->flush( self::TRANSIENT );
		}

		$this->flush( self::TRANSIENT . '_u' . get_current_user_id() );
	}

	/**
	 * Print and clear one queue.
	 *
	 * @param string $key Transient key.
	 */
	private function flush( string $key ): void {
		$list = get_transient( $key );
		if ( ! is_array( $list ) || ! $list ) {
			return;
		}

		delete_transient( $key );

		foreach ( $list as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
				esc_attr( (string) ( $notice['type'] ?? 'info' ) ),
				esc_html__( 'Sprint Illustrations:', 'sprint-illustrations' ),
				esc_html( (string) ( $notice['message'] ?? '' ) )
			);
		}
	}
}
