<?php
/**
 * POST /compose — live Builder preview.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\Paint;
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

		$palette = $this->plugin->site_palette()->resolve( $spec->palette );

		try {
			$result = $this->plugin->services()->composer->compose( $spec, $palette );
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

		$items = [];
		foreach ( $result->spec->items as $item ) {
			$items[] = [
				'key'   => $item['key'],
				'piece' => $item['piece'],
				'box'   => $result->boxes[ 'item:' . $item['key'] ][0] ?? null,
			];
		}

		return new \WP_REST_Response(
			[
				'svg'        => $result->with_instance_id( 'si-b' . substr( md5( uniqid( '', true ) ), 0, 10 ) ),
				'spec'       => $result->spec->to_array(),
				'warnings'   => $result->warnings,
				'template'   => null === $template ? null : [
					'id'     => $template->id,
					'label'  => $template->label,
					'canvas' => $template->canvas,
					'unit'   => $template->unit,
				],
				'canvas'     => $result->spec->canvas ?? ( null === $template ? null : $template->canvas ),
				'layers'     => $result->layers,
				'items'      => $items,
				'slots'      => $slots,
				'background' => (string) $palette->resolve( 'background' ),
				'colors'     => array_map( static fn( string $slot ): string => (string) $palette->resolve( $slot ), array_combine( Paint::SLOTS, Paint::SLOTS ) ),
			]
		);
	}
}
