<?php
/**
 * GET /library — templates and pieces for the Builder.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Plugin;

/**
 * Library listing with cached piece thumbnails in the site palette.
 */
final class LibraryController {

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
			'/library',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'library' ],
				'permission_callback' => [ Permissions::class, 'edit_posts' ],
			]
		);
	}

	/**
	 * Templates, pieces (with previews) and palette presets.
	 *
	 * @return \WP_REST_Response
	 */
	public function library(): \WP_REST_Response {
		$services = $this->plugin->services();
		$previews = new PiecePreviews( new PieceLoader( $services->sanitizer ), $services->sanitizer );
		$palette  = $this->plugin->site_palette()->palette();
		$pieces   = [];

		foreach ( $services->manifest->all() as $piece ) {
			$pieces[] = $piece->to_array() + [ 'preview' => $previews->svg( $piece, $palette ) ];
		}

		return new \WP_REST_Response(
			[
				'templates' => array_map( static fn( Template $template ): array => $template->to_array(), array_values( $services->templates->all() ) ),
				'pieces'    => $pieces,
				'presets'   => $this->plugin->editor_choices()['presets'],
			]
		);
	}
}
