<?php
/**
 * Piece requests over REST, for a remote drawer (Claude Code in the cloud) that can't run WP-CLI here.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Rest;

use SprintIllustrations\Library\RemoteRequest;
use SprintIllustrations\Library\WatchQueue;
use SprintIllustrations\Plugin;
use SprintIllustrations\Storage\PieceDrafts;

/**
 * The same five moves as `wp sprint-illustrations requests`: list, watch (heartbeat), start, draft, decline.
 * Only users with the drawer capability (or administrators) may use them.
 */
final class RequestsController {

	private const STATES = [ 'queued', 'drawing', 'review', 'done', 'discarded', 'declined', 'all' ];

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
		$permission = [ Permissions::class, 'drawer' ];
		$routes     = [
			'/requests'                     => [ 'GET', 'list_requests' ],
			'/requests/watch'               => [ 'POST', 'watch' ],
			'/requests/(?P<id>\d+)/start'   => [ 'POST', 'start' ],
			'/requests/(?P<id>\d+)/draft'   => [ 'POST', 'draft' ],
			'/requests/(?P<id>\d+)/decline' => [ 'POST', 'decline' ],
		];

		foreach ( $routes as $route => [ $method, $callback ] ) {
			register_rest_route(
				Permissions::NAMESPACE,
				$route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $callback ],
					'permission_callback' => $permission,
				]
			);
		}
	}

	/**
	 * GET /requests?state=queued
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_requests( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$state = sanitize_key( (string) ( $request['state'] ?? 'queued' ) );
		if ( ! in_array( $state, self::STATES, true ) ) {
			return $this->error( 'sprint_illustrations_bad_state', __( 'Unknown state.', 'sprint-illustrations' ), 400 );
		}

		return new \WP_REST_Response( $this->rows( $this->plugin->piece_requests()->list( $state, 200 ) ) );
	}

	/**
	 * POST /requests/watch: beat the heartbeat, requeue stalled drawings, return what is waiting.
	 *
	 * @return \WP_REST_Response
	 */
	public function watch(): \WP_REST_Response {
		$requests = $this->plugin->piece_requests();
		foreach ( WatchQueue::stale( $requests->list( 'drawing', 200 ), time() ) as $id ) {
			$requests->move( $id, 'queued' );
		}
		$this->plugin->drawer_heartbeat()->beat();

		return new \WP_REST_Response( [ 'requests' => $this->rows( $requests->list( 'queued', 200 ) ) ] );
	}

	/**
	 * POST /requests/<id>/start
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = absint( $request['id'] );
		if ( null === $this->plugin->piece_requests()->get( $id ) ) {
			return $this->not_found();
		}
		if ( ! $this->plugin->piece_requests()->start( $id ) ) {
			/* translators: %d: request ID. */
			return $this->error( 'sprint_illustrations_not_queued', sprintf( __( 'Request %d is not queued.', 'sprint-illustrations' ), $id ), 409 );
		}

		return new \WP_REST_Response( [ 'ok' => true ] );
	}

	/**
	 * POST /requests/<id>/draft {name, svg}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id  = absint( $request['id'] );
		$row = $this->plugin->piece_requests()->get( $id );
		if ( null === $row ) {
			return $this->not_found();
		}
		if ( ! in_array( $row['state'], [ 'queued', 'drawing' ], true ) ) {
			/* translators: 1: request ID, 2: state. */
			return $this->error( 'sprint_illustrations_wrong_state', sprintf( __( 'Request %1$d is %2$s; only queued or drawing requests take a draft.', 'sprint-illustrations' ), $id, $row['state'] ), 409 );
		}

		$payload = RemoteRequest::draft( $request['name'] ?? null, $request['svg'] ?? null );
		if ( is_string( $payload ) ) {
			return $this->error( 'sprint_illustrations_bad_draft', $payload, 400 );
		}

		$dir = trailingslashit( get_temp_dir() ) . 'si-draft-' . wp_generate_password( 12, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return $this->error( 'sprint_illustrations_no_temp', __( 'Could not create a temporary folder.', 'sprint-illustrations' ), 500 );
		}

		$file   = $dir . '/' . $payload['name'] . '.svg';
		$result = [
			'ok'       => false,
			'piece'    => '',
			'messages' => [ __( 'Could not write the SVG.', 'sprint-illustrations' ) ],
		];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary file handed to the draft builder.
		if ( false !== file_put_contents( $file, $payload['svg'] ) ) {
			$result = $this->plugin->piece_drafts()->submit( $row, $file );
		}
		wp_delete_file( $file );
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Our own empty temporary folder.

		if ( ! $result['ok'] ) {
			return new \WP_REST_Response(
				[
					'ok'       => false,
					'messages' => $result['messages'],
				],
				422
			);
		}

		$this->plugin->piece_requests()->submit_draft( $id, PieceDrafts::name( $file ) );

		return new \WP_REST_Response(
			[
				'ok'    => true,
				'piece' => $result['piece'],
			]
		);
	}

	/**
	 * POST /requests/<id>/decline {note}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function decline( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = absint( $request['id'] );
		if ( null === $this->plugin->piece_requests()->get( $id ) ) {
			return $this->not_found();
		}

		$note = RemoteRequest::note( $request['note'] ?? null );
		if ( null === $note ) {
			return $this->error( 'sprint_illustrations_note_required', __( 'A note is required so the requester knows why.', 'sprint-illustrations' ), 400 );
		}
		if ( ! $this->plugin->piece_requests()->decline( $id, $note ) ) {
			/* translators: %d: request ID. */
			return $this->error( 'sprint_illustrations_cannot_decline', sprintf( __( 'Request %d cannot be declined now.', 'sprint-illustrations' ), $id ), 409 );
		}

		return new \WP_REST_Response( [ 'ok' => true ] );
	}

	/**
	 * Request rows for the drawer: author as a login, reference as a URL (never a server path).
	 *
	 * @param array<int, array<string, mixed>> $rows Rows from the repository.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( array $rows ): array {
		$images = $this->plugin->reference_images();

		return array_map(
			static function ( array $row ) use ( $images ): array {
				$author               = get_userdata( (int) $row['author'] );
				$row['author']        = $author ? $author->user_login : (string) $row['author'];
				$row['reference_url'] = $images->url( (string) $row['reference'] );
				unset( $row['reference'] );

				return $row;
			},
			$rows
		);
	}

	/**
	 * Not found.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return $this->error( 'sprint_illustrations_request_not_found', __( 'Request not found.', 'sprint-illustrations' ), 404 );
	}

	/**
	 * Error with a status.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
