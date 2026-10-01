<?php
/**
 * Scene plans: describe a scene, request the objects the library lacks, then build the scene.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Compose\SceneArrangement;
use SprintIllustrations\Library\Piece;
use SprintIllustrations\Library\PieceRequest;
use SprintIllustrations\Library\ReferenceImage;
use SprintIllustrations\Library\Template;
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

	private const PREVIEW_TRANSIENT = 'si_draft_preview_';

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
		foreach ( [ 'keep', 'discard' ] as $action ) {
			register_rest_route(
				Permissions::NAMESPACE,
				'/plans/requests/(?P<id>\d+)/' . $action,
				[
					'methods'             => 'POST',
					'callback'            => [ $this, $action ],
					'permission_callback' => $permission,
				]
			);
		}
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
	 * The pieces to draw: strings (objects) or {description, category}, cleaned and capped.
	 *
	 * @param array<mixed> $raw Request input.
	 * @return array<int, array{description: string, category: string, reference: string}>
	 */
	private static function objects( array $raw ): array {
		$pieces = [];
		foreach ( $raw as $item ) {
			$text     = is_array( $item ) ? $item['description'] ?? '' : $item;
			$category = is_array( $item ) ? sanitize_key( (string) ( $item['category'] ?? '' ) ) : '';
			$text     = is_string( $text ) ? PieceRequest::clean( $text ) : '';
			if ( '' === $text ) {
				continue;
			}

			$reference = is_array( $item ) && is_string( $item['reference'] ?? null ) ? $item['reference'] : '';
			$pieces[]  = [
				'description' => $text,
				'category'    => in_array( $category, Piece::CATEGORIES, true ) ? $category : 'objects',
				'reference'   => ReferenceImage::is_name( $reference ) ? $reference : '',
			];
		}

		return array_slice( $pieces, 0, self::MAX_OBJECTS );
	}

	/**
	 * Create a plan and queue a request per object.
	 *
	 * @param \WP_REST_Request $request Request {content, objects[], template, keywords[], title, seed}.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$given   = AiRequest::clean( (string) ( $request['content'] ?? '' ) );
		$objects = self::objects( (array) ( $request['objects'] ?? [] ) );

		if ( [] === $objects ) {
			return new \WP_Error( 'sprint_illustrations_invalid_plan', __( 'Choose at least one object to draw.', 'sprint-illustrations' ), [ 'status' => 400 ] );
		}

		// Without a description or a valid template the plan still works: it describes itself by its objects and builds on a blank canvas.
		$content  = '' !== $given ? $given : implode( ', ', array_column( $objects, 'description' ) );
		$template = sanitize_key( (string) ( $request['template'] ?? '' ) );
		if ( '' === $template || null === $this->plugin->services()->templates->get( $template ) ) {
			$template = Template::BLANK_ID;
		}

		$scene_note = '' !== $given ? ' (for a scene: ' . mb_substr( $given, 0, 120 ) . ')' : '';
		$user       = get_current_user_id();
		$requests   = $this->plugin->piece_requests();
		$ids        = [];
		foreach ( $objects as $piece ) {
			// Short objects get a note about the scene; long ones (a split layer with style notes) keep their room.
			$object      = $piece['description'];
			$description = mb_strlen( $object . $scene_note ) <= PieceRequest::MAX_LENGTH ? $object . $scene_note : mb_substr( $object, 0, PieceRequest::MAX_LENGTH );
			$reference   = $this->plugin->reference_images()->exists( $piece['reference'] ) ? $piece['reference'] : '';
			$id          = $requests->create( $piece['category'], $description, $user, $reference );
			if ( is_wp_error( $id ) ) {
				foreach ( $ids as $done ) {
					$made = $requests->get( $done );
					if ( $requests->cancel( $done ) && null !== $made ) {
						$this->plugin->reference_images()->delete( $made['reference'] );
					}
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
	 * Keep a drawn piece: it joins the library (the plugin's own assets when writable).
	 *
	 * @param \WP_REST_Request $request Request {id}: the piece request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function keep( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$row = $this->reviewable( (int) $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$result = $this->plugin->piece_drafts()->accept( $row );
		if ( ! $result['ok'] ) {
			return new \WP_Error( 'sprint_illustrations_keep_failed', implode( ' ', $result['messages'] ), [ 'status' => 422 ] );
		}
		delete_transient( self::PREVIEW_TRANSIENT . $row['id'] );

		return new \WP_REST_Response(
			[
				'piece' => $result['piece'],
				'where' => $result['where'] ?? 'site',
			]
		);
	}

	/**
	 * Discard a drawn piece (Try again on the Library page can re-queue it).
	 *
	 * @param \WP_REST_Request $request Request {id}: the piece request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function discard( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$row = $this->reviewable( (int) $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$this->plugin->piece_drafts()->reject( $row );
		delete_transient( self::PREVIEW_TRANSIENT . $row['id'] );

		return new \WP_REST_Response( [ 'discarded' => true ] );
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
	 * A piece request the current user may keep or discard and that is waiting for review.
	 *
	 * @param int $id Request ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function reviewable( int $id ): array|\WP_Error {
		$row = $this->plugin->piece_requests()->get( $id );
		if ( null === $row || ! $this->can_act( $row ) ) {
			return new \WP_Error( 'sprint_illustrations_not_found', __( 'That request no longer exists.', 'sprint-illustrations' ), [ 'status' => 404 ] );
		}
		if ( 'review' !== $row['state'] ) {
			return new \WP_Error( 'sprint_illustrations_not_in_review', __( 'That piece is not waiting for review.', 'sprint-illustrations' ), [ 'status' => 409 ] );
		}

		return $row;
	}

	/**
	 * Whether the current user may keep or discard a request (its requester, or an admin).
	 *
	 * @param array<string, mixed> $row Request.
	 * @return bool
	 */
	private function can_act( array $row ): bool {
		return current_user_can( 'manage_options' ) || get_current_user_id() === (int) $row['author'];
	}

	/**
	 * The piece alone as a sanitized SVG, cached until the request changes.
	 *
	 * @param array<string, mixed> $row Request in review.
	 * @return string
	 */
	private function preview( array $row ): string {
		$key    = self::PREVIEW_TRANSIENT . $row['id'];
		$cached = get_transient( $key );
		if ( is_array( $cached ) && ( $cached['changed'] ?? '' ) === $row['changed'] ) {
			return (string) $cached['svg'];
		}

		$svg = $this->plugin->piece_drafts()->preview( $row )['piece'];
		set_transient(
			$key,
			[
				'changed' => $row['changed'],
				'svg'     => $svg,
			],
			HOUR_IN_SECONDS
		);

		return $svg;
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
	 * @return array{id: string, description: string, template: string, title: string, requests: array<int, array{id: int, description: string, category: string, state: string, piece: string, note: string, reference: string, can_act: bool, preview: string}>, ready: bool, waiting: int}
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
					'category'    => $row['category'],
					'state'       => $row['state'],
					'piece'       => $row['piece'],
					'note'        => $row['note'],
					'reference'   => $this->plugin->reference_images()->url( $row['reference'] ),
					'can_act'     => $this->can_act( $row ),
					'preview'     => 'review' === $row['state'] ? $this->preview( $row ) : '',
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
