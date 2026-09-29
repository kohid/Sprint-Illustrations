<?php
/**
 * Scene plans: describe a scene, request the objects the library lacks, then build the scene.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\SceneArrangement;
use SprintIllustrations\Library\PieceRequest;
use SprintIllustrations\Plugin;
use SprintIllustrations\Selection\AiRequest;
use SprintIllustrations\Selection\BriefSplitter;

/**
 * POST /plans queues one piece request per object (Claude Code draws them, the requester keeps or
 * discards each on the Library page, so nothing joins the plugin's assets unreviewed). GET /plans
 * follows their progress; POST /plans/<id>/build returns the scene with the kept pieces placed.
 */
final class PlansController {

	public const MAX_OBJECTS = 6;

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
		$permission = [ Permissions::class, 'edit_posts' ];

		register_rest_route(
			Permissions::NAMESPACE,
			'/plans',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'list' ],
					'permission_callback' => $permission,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create' ],
					'permission_callback' => $permission,
				],
			]
		);
		register_rest_route(
			Permissions::NAMESPACE,
			'/plans/split',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'split' ],
				'permission_callback' => $permission,
			]
		);
		register_rest_route(
			Permissions::NAMESPACE,
			'/plans/(?P<id>[a-f0-9]{12})',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => $permission,
			]
		);
		register_rest_route(
			Permissions::NAMESPACE,
			'/plans/(?P<id>[a-f0-9]{12})/build',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'build' ],
				'permission_callback' => $permission,
			]
		);
	}

	/**
	 * Plans the user may see, with the state of their pieces.
	 *
	 * @return \WP_REST_Response
	 */
	public function list(): \WP_REST_Response {
		$rows = [];
		foreach ( $this->plugin->scene_plans()->for_user( get_current_user_id(), current_user_can( 'manage_options' ) ) as $id => $plan ) {
			$rows[] = $this->present( (string) $id, $plan );
		}

		return new \WP_REST_Response( $rows );
	}

	/**
	 * Split a long numbered brief into one short request per layer, each with the shared style notes.
	 *
	 * @param \WP_REST_Request $request Request {content}.
	 * @return \WP_REST_Response
	 */
	public function split( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( BriefSplitter::split( AiRequest::clean( (string) ( $request['content'] ?? '' ) ) ) );
	}

	/**
	 * Create a plan and queue a request per object.
	 *
	 * @param \WP_REST_Request $request Request {content, objects[], template, keywords[], title, seed}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$content = AiRequest::clean( (string) ( $request['content'] ?? '' ) );
		$objects = array_slice( array_values( array_filter( array_map( static fn( $item ): string => is_string( $item ) ? PieceRequest::clean( $item ) : '', (array) ( $request['objects'] ?? [] ) ) ) ), 0, self::MAX_OBJECTS );

		if ( '' === $content || [] === $objects ) {
			return new \WP_Error( 'sprint_illustrations_invalid_plan', __( 'Describe the scene and choose at least one object to draw.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$template = sanitize_key( (string) ( $request['template'] ?? '' ) );
		if ( '' === $template || null === $this->plugin->services()->templates->get( $template ) ) {
			return new \WP_Error( 'sprint_illustrations_invalid_plan', __( 'Pick a template first, for example with Suggest.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		$scene_note = ' (for a scene: ' . mb_substr( $content, 0, 120 ) . ')';
		$user       = get_current_user_id();
		$requests   = $this->plugin->piece_requests();
		$ids        = [];
		foreach ( $objects as $object ) {
			// Short objects get a note about the scene; long ones (a split layer with style notes) keep their room.
			$description = mb_strlen( $object . $scene_note ) <= PieceRequest::MAX_LENGTH ? $object . $scene_note : mb_substr( $object, 0, PieceRequest::MAX_LENGTH );
			$id          = $requests->create( 'objects', $description, $user );
			if ( is_wp_error( $id ) ) {
				foreach ( $ids as $done ) {
					$requests->cancel( $done );
				}

				return new \WP_Error( 'sprint_illustrations_invalid_plan', $id->get_error_message(), [ 'status' => 400 ] );
			}
			$ids[] = $id;
		}

		$keywords = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $request['keywords'] ?? [] ) ) ) ), 0, 6 );
		$plan_id  = $this->plugin->scene_plans()->create(
			[
				'user'        => $user,
				'description' => mb_substr( $content, 0, 300 ),
				'template'    => $template,
				'keywords'    => $keywords,
				'title'       => mb_substr( sanitize_text_field( (string) ( $request['title'] ?? '' ) ), 0, 120 ),
				'seed'        => max( 1, absint( $request['seed'] ?? 1 ) ),
				'requests'    => $ids,
			]
		);

		return new \WP_REST_Response( $this->present( $plan_id, (array) $this->plugin->scene_plans()->get( $plan_id ) ), 201 );
	}

	/**
	 * Forget a plan.
	 *
	 * @param \WP_REST_Request $request Request {id}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$plan = $this->owned( (string) $request['id'] );

		return is_wp_error( $plan ) ? $plan : new \WP_REST_Response( [ 'deleted' => $this->plugin->scene_plans()->delete( (string) $request['id'] ) ] );
	}

	/**
	 * The scene: the suggested template and keywords with every kept piece placed as a free item.
	 *
	 * @param \WP_REST_Request $request Request {id}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function build( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$plan = $this->owned( (string) $request['id'] );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$row = $this->present( (string) $request['id'], $plan );
		if ( ! $row['ready'] ) {
			return new \WP_Error( 'sprint_illustrations_plan_not_ready', __( 'The new pieces aren\'t all kept yet. Keep or discard each one on the Library page first.', 'sprint-illustrations' ), [ 'status' => 409 ] );
		}

		$services = $this->plugin->services();
		$sizes    = [];
		foreach ( $row['requests'] as $item ) {
			$piece = 'done' === $item['state'] ? $services->manifest->get( $item['piece'] ) : null;
			if ( null !== $piece ) {
				$sizes[ $piece->id ] = [ $piece->width(), $piece->height() ];
			}
		}
		if ( [] === $sizes ) {
			return new \WP_Error( 'sprint_illustrations_plan_not_ready', __( 'None of the new pieces are in the library yet.', 'sprint-illustrations' ), [ 'status' => 409 ] );
		}

		$template = $services->templates->get( (string) $plan['template'] );
		$canvas   = null === $template ? [ 800, 600 ] : $template->canvas;

		return new \WP_REST_Response(
			[
				'template' => (string) $plan['template'],
				'keywords' => $plan['keywords'],
				'title'    => $plan['title'],
				'seed'     => $plan['seed'],
				'items'    => SceneArrangement::row( $sizes, [ (int) $canvas[0], (int) $canvas[1] ] ),
			]
		);
	}

	/**
	 * A plan the current user may use, or a 404.
	 *
	 * @param string $id Plan ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function owned( string $id ): array|\WP_Error {
		$plan = $this->plugin->scene_plans()->get( $id );
		if ( null === $plan || ( get_current_user_id() !== (int) $plan['user'] && ! current_user_can( 'manage_options' ) ) ) {
			return new \WP_Error( 'sprint_illustrations_not_found', __( 'That scene plan no longer exists.', 'sprint-illustrations' ), [ 'status' => 404 ] );
		}

		return $plan;
	}

	/**
	 * Plan with the current state of each of its piece requests.
	 *
	 * @param string               $id   Plan ID.
	 * @param array<string, mixed> $plan Stored plan.
	 * @return array{id: string, description: string, template: string, title: string, requests: array<int, array{id: int, description: string, state: string, piece: string, note: string}>, ready: bool, waiting: int}
	 */
	private function present( string $id, array $plan ): array {
		$repo     = $this->plugin->piece_requests();
		$requests = [];
		foreach ( (array) $plan['requests'] as $request_id ) {
			$row = $repo->get( (int) $request_id );
			if ( null !== $row ) {
				$requests[] = [
					'id'          => $row['id'],
					'description' => $row['description'],
					'state'       => $row['state'],
					'piece'       => $row['piece'],
					'note'        => $row['note'],
				];
			}
		}

		$waiting = count( array_filter( $requests, static fn( array $row ): bool => in_array( $row['state'], [ 'queued', 'drawing', 'review' ], true ) ) );
		$done    = count( array_filter( $requests, static fn( array $row ): bool => 'done' === $row['state'] ) );

		return [
			'id'          => $id,
			'description' => (string) $plan['description'],
			'template'    => (string) $plan['template'],
			'title'       => (string) $plan['title'],
			'requests'    => $requests,
			'ready'       => 0 === $waiting && $done > 0,
			'waiting'     => $waiting,
		];
	}
}
