<?php
/**
 * POST /compose — live Builder preview.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Plugin;

/**
 * Composes with the uncached composer (unsaved edits must not fill the cache).
 */
final class ComposeController {

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
			'/compose',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'compose' ],
				'permission_callback' => [ Permissions::class, 'edit_posts' ],
			]
		);
	}

	/**
	 * Compose a spec.
	 *
	 * @param \WP_REST_Request $request Request (SceneSpec JSON body).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function compose( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = $request->get_json_params();
		$spec   = SceneSpec::from_array( is_array( $params ) ? $params : $request->get_body_params() );

		try {
			$result = $this->plugin->services()->composer->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) );
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		$template = $this->plugin->services()->templates->get( (string) $result->spec->template );
		$slots    = [];
		foreach ( null === $template ? [] : $template->slots as $slot ) {
			$slots[] = $slot->to_array() + [
				'picked' => $result->spec->picks[ $slot->name ] ?? null,
				'locked' => array_key_exists( $slot->name, $spec->picks ),
				'boxes'  => $result->boxes[ $slot->name ] ?? [],
			];
		}

		return new \WP_REST_Response(
			[
				'svg'      => $result->with_instance_id( 'si-b' . substr( md5( uniqid( '', true ) ), 0, 10 ) ),
				'spec'     => $result->spec->to_array(),
				'warnings' => $result->warnings,
				'template' => null === $template ? null : [
					'id'     => $template->id,
					'label'  => $template->label,
					'canvas' => $template->canvas,
				],
				'slots'    => $slots,
			]
		);
	}
}
