<?php
/**
 * POST /references: store a reference image from a pasted screenshot, a chosen file or an image address.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Plugin;

/**
 * Returns the stored name, which a piece request (Library form or Draw new pieces) then attaches.
 * Every image goes through the same checks and re-encoding as an upload. Needs `upload_files`, like
 * the Library form's file field.
 */
final class ReferencesController {

	private const RATE_LIMIT = 40;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			Permissions::NAMESPACE,
			'/references',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'store' ],
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' ),
			]
		);
	}

	/**
	 * Store an image.
	 *
	 * @param \WP_REST_Request $request Request: multipart `file`, or `text` (an image address or inline image).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->take_slot() ) {
			return new \WP_Error( 'sprint_illustrations_rate', __( 'Too many images this hour. Try again later.', 'sprint-illustrations' ), [ 'status' => 429 ] );
		}

		$files  = $request->get_file_params();
		$images = $this->plugin->reference_images();
		if ( isset( $files['file'] ) && is_array( $files['file'] ) ) {
			$name = $images->store( $files['file'] );
		} else {
			$name = $images->store_pasted_text( (string) ( $request['text'] ?? '' ) );
		}

		if ( is_wp_error( $name ) ) {
			$name->add_data( [ 'status' => 400 ] );

			return $name;
		}

		$images->prune();

		return new \WP_REST_Response(
			[
				'name' => $name,
				'url'  => $images->url( $name ),
			],
			201
		);
	}

	/**
	 * Count one call against the user's hourly limit.
	 *
	 * @return bool False when the limit is used up.
	 */
	private function take_slot(): bool {
		$key   = 'si_reference_calls_' . get_current_user_id();
		$calls = (int) get_transient( $key );
		if ( $calls >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $calls + 1, HOUR_IN_SECONDS );

		return true;
	}
}
