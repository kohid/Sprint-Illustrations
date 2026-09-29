<?php
/**
 * WP-CLI: piece requests queued on the Library page.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Library\WatchQueue;
use SprintIllustrations\Plugin;
use SprintIllustrations\Storage\PieceDrafts;

/**
 * Claude Code's side of the queue: list, start, submit a draft for review, decline, watch.
 */
final class RequestsCommand {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * List requests.
	 *
	 * ## OPTIONS
	 *
	 * [--state=<state>]
	 * : queued, drawing, review, done, discarded, declined or all.
	 * ---
	 * default: queued
	 * options:
	 *   - queued
	 *   - drawing
	 *   - review
	 *   - done
	 *   - discarded
	 *   - declined
	 *   - all
	 * ---
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$images = $this->plugin->reference_images();
		$rows   = array_map(
			static function ( array $row ) use ( $images ): array {
				$author           = get_userdata( $row['author'] );
				$row['author']    = $author ? $author->user_login : (string) $row['author'];
				$row['reference'] = '' !== $images->url( $row['reference'] ) ? $images->path( $row['reference'] ) : '';
				return $row;
			},
			$this->plugin->piece_requests()->list( (string) ( $assoc_args['state'] ?? 'queued' ), 200 )
		);

		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}

		if ( ! $rows ) {
			\WP_CLI::line( 'No requests.' );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'category', 'description', 'state', 'feedback', 'piece', 'author', 'date' ] );
	}

	/**
	 * Mark a queued request as being drawn (the Library page shows "Drawing").
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Request ID.
	 *
	 * @param array<int, string> $args Positional args.
	 */
	public function start( array $args ): void {
		$id = absint( $args[0] ?? 0 );
		$this->request( $id );

		if ( ! $this->plugin->piece_requests()->start( $id ) ) {
			\WP_CLI::error( sprintf( 'Request %d is not queued.', $id ) );
		}
		\WP_CLI::success( sprintf( 'Request %d: drawing.', $id ) );
	}

	/**
	 * Submit a drawn SVG as the draft for review (it is not added to the library until the requester keeps it).
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Request ID.
	 *
	 * --file=<svg>
	 * : The drawn piece. Its file name becomes the piece name (e.g. taxi.svg → obj-taxi).
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function draft( array $args, array $assoc_args ): void {
		$id      = absint( $args[0] ?? 0 );
		$request = $this->request( $id );

		if ( ! in_array( $request['state'], [ 'queued', 'drawing' ], true ) ) {
			\WP_CLI::error( sprintf( 'Request %d is %s; only queued or drawing requests take a draft.', $id, $request['state'] ) );
		}

		$file   = (string) ( $assoc_args['file'] ?? '' );
		$result = $this->plugin->piece_drafts()->submit( $request, $file );
		if ( ! $result['ok'] ) {
			foreach ( $result['messages'] as $message ) {
				\WP_CLI::warning( $message );
			}
			\WP_CLI::error( 'The draft was not accepted; fix the SVG and submit it again.' );
		}

		$this->plugin->piece_requests()->submit_draft( $id, PieceDrafts::name( $file ) );
		\WP_CLI::success( sprintf( 'Request %d: draft %s is ready for review on the Library page.', $id, $result['piece'] ) );
	}

	/**
	 * Decline a queued or drawing request with a note the requester sees on the Library page.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Request ID.
	 *
	 * --note=<text>
	 * : Why, and what to ask for instead.
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function decline( array $args, array $assoc_args ): void {
		$id   = absint( $args[0] ?? 0 );
		$note = trim( (string) ( $assoc_args['note'] ?? '' ) );
		$this->request( $id );

		if ( '' === $note ) {
			\WP_CLI::error( '--note is required so the requester knows why.' );
		}

		if ( ! $this->plugin->piece_requests()->decline( $id, $note ) ) {
			\WP_CLI::error( sprintf( 'Request %d cannot be declined now.', $id ) );
		}
		\WP_CLI::success( sprintf( 'Request %d declined.', $id ) );
	}

	/**
	 * Watch the queue for a Claude Code Monitor: one JSON line on STDOUT per waiting request.
	 *
	 * Keeps the Library page's "Claude Code is watching" status on while it runs. On start, requests
	 * left drawing for 30 minutes (their session closed) go back to the queue.
	 *
	 * ## OPTIONS
	 *
	 * [--interval=<seconds>]
	 * : Seconds between checks (2–60).
	 * ---
	 * default: 5
	 * ---
	 *
	 * [--once]
	 * : Check once and exit.
	 *
	 * [--max-runtime=<seconds>]
	 * : Exit after this long (0 = never). A Claude Code Monitor lasts at most 30 minutes.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--parent=<pid>]
	 * : Exit when this process ends (Windows doesn't stop child processes with their parent).
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function watch( array $args, array $assoc_args ): void {
		$interval = max( 2, min( 60, (int) ( $assoc_args['interval'] ?? 5 ) ) );
		$once     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'once', false );
		$runtime  = max( 0, (int) ( $assoc_args['max-runtime'] ?? 0 ) );
		$parent   = absint( $assoc_args['parent'] ?? 0 );
		$started  = time();
		$requests = $this->plugin->piece_requests();

		foreach ( WatchQueue::stale( $requests->list( 'drawing', 200 ), time() ) as $id ) {
			if ( $requests->move( $id, 'queued' ) ) {
				\WP_CLI::warning( sprintf( 'Requeued %d (drawing stalled).', $id ) );
			}
		}

		$seen = [];
		while ( true ) {
			$seen = $this->tick( $seen );
			if ( $once || ( $runtime && time() - $started >= $runtime ) ) {
				return;
			}
			sleep( $interval );
			if ( ! $this->running( $parent ) ) {
				return;
			}
			// Long-running: read fresh options and posts on every check.
			wp_cache_flush();
		}
	}

	/**
	 * One watch check: heartbeat, then one JSON line per request not announced yet.
	 *
	 * @param array<int, string> $seen Keys already announced.
	 * @return array<int, string> Keys to remember.
	 */
	private function tick( array $seen ): array {
		try {
			$this->plugin->drawer_heartbeat()->beat();
			$next = WatchQueue::announce( $this->plugin->piece_requests()->list( 'queued', 200 ), $seen );
		} catch ( \Throwable $e ) {
			\WP_CLI::warning( 'Queue check failed: ' . $e->getMessage() );
			return $seen;
		}

		foreach ( $next['rows'] as $row ) {
			$event = [
				'event'       => 'request',
				'id'          => $row['id'],
				'category'    => $row['category'],
				'description' => $row['description'],
				'feedback'    => $row['feedback'],
				'reference'   => '' !== $this->plugin->reference_images()->url( $row['reference'] ) ? $this->plugin->reference_images()->path( $row['reference'] ) : '',
			];
			\WP_CLI::line( (string) wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}

		return $next['seen'];
	}

	/**
	 * Whether a process is still running (true when no PID was given or it can't be checked).
	 *
	 * @param int $pid Process ID (0 = none).
	 * @return bool
	 */
	private function running( int $pid ): bool {
		if ( 0 === $pid ) {
			return true;
		}
		if ( function_exists( 'posix_kill' ) ) {
			return posix_kill( $pid, 0 );
		}
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$list = (string) shell_exec( sprintf( 'tasklist /FI "PID eq %d" /NH /FO CSV', $pid ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- Fixed command with an integer PID.
			return str_contains( $list, '"' . $pid . '"' );
		}

		return true;
	}

	/**
	 * A request, or exit with an error.
	 *
	 * @param int $id Request ID.
	 * @return array<string, mixed>
	 */
	private function request( int $id ): array {
		$request = $this->plugin->piece_requests()->get( $id );
		if ( null === $request ) {
			\WP_CLI::error( sprintf( 'Request %d not found.', $id ) );
		}

		return $request;
	}
}
