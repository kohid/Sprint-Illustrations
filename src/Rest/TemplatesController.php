<?php
/**
 * REST: save what is on the Builder canvas as a reusable template.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneResolver;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Compose\TemplateFromScene;
use SprintIllustrations\Library\LibraryException;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Plugin;

/**
 * POST /templates (manage_options: it writes plugin files). The template goes to the plugin's
 * assets/templates/ so it ships with the plugin, or to the site library when that isn't writable.
 */
final class TemplatesController {

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
			'/templates',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create' ],
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
				'args'                => [
					'name' => [
						'type'     => 'string',
						'required' => true,
					],
					'spec' => [
						'type'     => 'object',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Save the scene as a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$label = trim( sanitize_text_field( (string) $request['name'] ) );
		$slug  = sanitize_title( $label );
		if ( '' === $label || ! preg_match( '/^[a-z0-9][a-z0-9-]*$/D', $slug ) ) {
			return new \WP_Error( 'sprint_illustrations_template_name', __( 'Give the template a name with letters or numbers.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$services = $this->plugin->services();
		$spec     = SceneSpec::from_array( is_array( $request['spec'] ) ? $request['spec'] : [] );
		$template = $services->templates->get( (string) $spec->template );
		if ( null === $template ) {
			return new \WP_Error( 'sprint_illustrations_template_missing', __( 'Choose a template or a blank canvas first.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		try {
			$tokens = $services->keywords->expand( $services->keywords->tokenize( implode( ' ', $spec->keywords ) ) );
			$scene  = ( new SceneResolver( $services->manifest ) )->resolve( $template, $spec, $tokens );
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}
		if ( ! $scene->placements ) {
			return new \WP_Error( 'sprint_illustrations_template_empty', __( 'Add something to the canvas before saving it as a template.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$id = $slug;
		$n  = 2;
		// "auto" means automatic choice in the shortcode and block, so it can't name a template.
		while ( 'auto' === $id || null !== $services->templates->get( $id ) ) {
			$id = $slug . '-' . $n;
			++$n;
		}

		$data = TemplateFromScene::build( $id, $label, $scene );
		try {
			Template::from_array( $data );
		} catch ( LibraryException $e ) {
			return new \WP_Error( 'sprint_illustrations_template_invalid', $e->getMessage(), [ 'status' => 422 ] );
		}

		$bundled = $this->plugin->bundled_library();
		$where   = $bundled->templates_writable() ? 'plugin' : 'site';
		$dir     = 'plugin' === $where ? $bundled->templates_dir() : $this->plugin->user_templates_dir();
		wp_mkdir_p( $dir );
		$written = file_put_contents( $dir . '/' . $id . '.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Plugin/library file.
		if ( false === $written ) {
			return new \WP_Error( 'sprint_illustrations_template_write', __( 'The template could not be saved.', 'sprint-illustrations' ), [ 'status' => 500 ] );
		}

		return new \WP_REST_Response(
			[
				'id'    => $id,
				'label' => $label,
				'where' => $where,
			],
			201
		);
	}
}
