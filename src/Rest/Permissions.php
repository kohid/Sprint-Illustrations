<?php
/**
 * Shared REST permission checks.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Plugin;

/**
 * Capability checks returning WP_Error with the right status.
 */
final class Permissions {

	public const NAMESPACE = 'sprint-illustrations/v1';

	/** Role and capability for the account a remote Claude Code session draws pieces with. */
	public const DRAWER_ROLE = 'sprint_illustrations_drawer';
	public const DRAWER_CAP  = 'sprint_illustrations_draw';

	/** Public studio calls a visitor may make in a minute. */
	public const STUDIO_LIMIT = 240;

	/**
	 * Base check for every route.
	 *
	 * @return true|\WP_Error
	 */
	public static function edit_posts(): bool|\WP_Error {
		return current_user_can( 'edit_posts' )
			? true
			: new \WP_Error( 'sprint_illustrations_rest_forbidden', __( 'You are not allowed to use Sprint Illustrations.', 'sprint-illustrations' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Per-post check: 404 for non-illustrations first, then the capability.
	 *
	 * @param \WP_REST_Request $request Request with an "id" URL param.
	 * @param string           $cap     "read_post", "edit_post" or "delete_post".
	 * @return true|\WP_Error
	 */
	public static function item( \WP_REST_Request $request, string $cap ): bool|\WP_Error {
		$base = self::edit_posts();
		if ( true !== $base ) {
			return $base;
		}

		$id = (int) $request['id'];
		if ( ! Plugin::instance()->illustrations()->exists( $id ) ) {
			return new \WP_Error( 'sprint_illustrations_not_found', __( 'Illustration not found.', 'sprint-illustrations' ), [ 'status' => 404 ] );
		}

		if ( ! current_user_can( $cap, $id ) ) {
			$code = 'delete_post' === $cap ? 'sprint_illustrations_cannot_delete' : 'sprint_illustrations_cannot_edit';
			return new \WP_Error( $code, __( 'You are not allowed to change this illustration.', 'sprint-illustrations' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Piece request endpoints: the drawer account or an administrator.
	 *
	 * @return bool|\WP_Error
	 */
	public static function drawer(): bool|\WP_Error {
		return current_user_can( self::DRAWER_CAP ) || current_user_can( 'manage_options' )
			? true
			: new \WP_Error( 'sprint_illustrations_rest_forbidden', __( 'You are not allowed to draw pieces.', 'sprint-illustrations' ), [ 'status' => rest_authorization_required_code() ] );
	}

	/**
	 * Public, read-only studio endpoints (the front-end figure builder): open to everyone, but each visitor
	 * (by address) gets a limited number of calls a minute. Editors are not limited.
	 *
	 * @return true|\WP_Error
	 */
	public static function studio(): bool|\WP_Error {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key     = 'si_studio_' . md5( $address . '|' . gmdate( 'YmdHi' ) );
		$count   = (int) get_transient( $key );
		if ( $count >= self::STUDIO_LIMIT ) {
			return new \WP_Error( 'sprint_illustrations_rate_limited', __( 'Too many requests. Wait a moment and try again.', 'sprint-illustrations' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );

		return true;
	}
}
