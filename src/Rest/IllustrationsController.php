<?php
/**
 * /illustrations — saved illustration CRUD.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Plugin;
use SprintIllustrations\Storage\Illustration;

/**
 * Own controller (the post type is not exposed through core REST).
 */
final class IllustrationsController {

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
			'/illustrations',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ Permissions::class, 'edit_posts' ],
					'args'                => [
						'page'     => [
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						],
						'per_page' => [
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						],
						'search'   => [
							'type'    => 'string',
							'default' => '',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ Permissions::class, 'edit_posts' ],
				],
			]
		);

		register_rest_route(
			Permissions::NAMESPACE,
			'/illustrations/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'show' ],
					'permission_callback' => static fn( \WP_REST_Request $request ) => Permissions::item( $request, 'read_post' ),
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update' ],
					'permission_callback' => static fn( \WP_REST_Request $request ) => Permissions::item( $request, 'edit_post' ),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'destroy' ],
					'permission_callback' => static fn( \WP_REST_Request $request ) => Permissions::item( $request, 'delete_post' ),
				],
			]
		);
	}

	/**
	 * List.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$per_page = (int) $request['per_page'];
		$result   = $this->plugin->illustrations()->list( (int) $request['page'], $per_page, sanitize_text_field( (string) $request['search'] ) );

		return new \WP_REST_Response(
			[
				'items' => array_map(
					fn( Illustration $item ): array => [
						'id'           => $item->id,
						'title'        => $item->title,
						'template'     => $item->spec->template,
						'modified_gmt' => $item->modified_gmt,
						'author_id'    => $item->author_id,
					] + $this->caps( (int) $item->id ),
					$result['items']
				),
				'total' => $result['total'],
				'pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			]
		);
	}

	/**
	 * Single.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$item = $this->plugin->illustrations()->find( (int) $request['id'] );

		return null === $item ? $this->not_found() : new \WP_REST_Response( $this->shape( $item ) );
	}

	/**
	 * Create.
	 *
	 * @param \WP_REST_Request $request Request {title, spec}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$title = $this->title( $request );
		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$spec = $this->resolved_spec( $request['spec'] ?? null );
		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$item = $this->plugin->illustrations()->create( $title, $spec, get_current_user_id() );

		return null === $item
			? new \WP_Error( 'sprint_illustrations_save_failed', __( 'The illustration could not be saved.', 'sprint-illustrations' ), [ 'status' => 500 ] )
			: new \WP_REST_Response( $this->shape( $item ), 201 );
	}

	/**
	 * Update (partial).
	 *
	 * @param \WP_REST_Request $request Request {title?, spec?}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$has_title = null !== $request['title'];
		$has_spec  = null !== $request['spec'];

		if ( ! $has_title && ! $has_spec ) {
			return new \WP_Error( 'sprint_illustrations_invalid_title', __( 'Send a name, a design, or both.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$title = $has_title ? $this->title( $request ) : null;
		if ( is_wp_error( $title ) ) {
			return $title;
		}

		$spec = $has_spec ? $this->resolved_spec( $request['spec'] ) : null;
		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$item = $this->plugin->illustrations()->update( (int) $request['id'], $title, $spec );

		return null === $item ? $this->not_found() : new \WP_REST_Response( $this->shape( $item ) );
	}

	/**
	 * Trash or delete.
	 *
	 * @param \WP_REST_Request $request Request (?force=true to delete permanently).
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id    = (int) $request['id'];
		$force = rest_sanitize_boolean( $request['force'] ?? false );
		$done  = $force ? $this->plugin->illustrations()->delete( $id ) : $this->plugin->illustrations()->trash( $id );

		return $done
			? new \WP_REST_Response(
				[
					'deleted' => true,
					'id'      => $id,
					'status'  => $force ? 'deleted' : 'trash',
				]
			)
			: $this->not_found();
	}

	/**
	 * Validated name.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string|\WP_Error
	 */
	private function title( \WP_REST_Request $request ): string|\WP_Error {
		$title = trim( sanitize_text_field( (string) $request['title'] ) );

		return '' === $title
			? new \WP_Error( 'sprint_illustrations_invalid_title', __( 'Give the illustration a name.', 'sprint-illustrations' ), [ 'status' => 400 ] )
			: mb_substr( $title, 0, Illustration::MAX_TITLE );
	}

	/**
	 * Compose the submitted spec (cached composer) and return the resolved spec to store.
	 *
	 * @param mixed $raw Spec data.
	 * @return SceneSpec|\WP_Error
	 */
	private function resolved_spec( mixed $raw ): SceneSpec|\WP_Error {
		$spec = SceneSpec::from_array( is_array( $raw ) ? $raw : [] );

		try {
			return $this->plugin->composer()->compose( $spec, $this->plugin->site_palette()->resolve( $spec->palette ) )->spec;
		} catch ( CompositionException $e ) {
			return new \WP_Error( 'sprint_illustrations_composition_failed', $e->getMessage(), [ 'status' => 422 ] );
		}
	}

	/**
	 * Full item shape.
	 *
	 * @param Illustration $item Item.
	 * @return array<string, mixed>
	 */
	private function shape( Illustration $item ): array {
		return $item->to_array() + $this->caps( (int) $item->id );
	}

	/**
	 * Current user's rights on an item.
	 *
	 * @param int $id Post ID.
	 * @return array{can_edit: bool, can_delete: bool}
	 */
	private function caps( int $id ): array {
		return [
			'can_edit'   => current_user_can( 'edit_post', $id ),
			'can_delete' => current_user_can( 'delete_post', $id ),
		];
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'sprint_illustrations_not_found', __( 'Illustration not found.', 'sprint-illustrations' ), [ 'status' => 404 ] );
	}
}
