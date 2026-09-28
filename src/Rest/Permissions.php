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
}
