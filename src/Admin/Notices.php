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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	/**
	 * Print and clear queued notices.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$list = get_transient( self::TRANSIENT );
		if ( ! is_array( $list ) || ! $list ) {
			return;
		}

		delete_transient( self::TRANSIENT );

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
