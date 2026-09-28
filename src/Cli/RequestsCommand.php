<?php
/**
 * WP-CLI: piece requests queued on the Library page.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Plugin;

/**
 * List, complete and decline piece requests (Claude Code's side of the queue).
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
	 * : queued, done, declined or all.
	 * ---
	 * default: queued
	 * options:
	 *   - queued
	 *   - done
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
		$rows = array_map(
			static function ( array $row ): array {
				$author        = get_userdata( $row['author'] );
				$row['author'] = $author ? $author->user_login : (string) $row['author'];
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

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'category', 'description', 'state', 'piece', 'author', 'date' ] );
	}

	/**
	 * Mark a queued request done with the piece that fulfils it.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Request ID.
	 *
	 * --piece=<piece-id>
	 * : A piece that exists in the library (after build-manifest).
	 *
	 * @param array<int, string>         $args       Positional args.
	 * @param array<string, string|bool> $assoc_args Options.
	 */
	public function done( array $args, array $assoc_args ): void {
		$id      = absint( $args[0] ?? 0 );
		$piece   = (string) ( $assoc_args['piece'] ?? '' );
		$request = $this->queued( $id );

		$found = $this->plugin->services()->manifest->get( $piece );
		if ( null === $found ) {
			\WP_CLI::error( sprintf( 'Piece "%s" is not in the library. Run `wp sprint-illustrations build-manifest --non-interactive` first.', $piece ) );
		}
		if ( $found->category !== $request['category'] ) {
			\WP_CLI::warning( sprintf( 'The request asked for %s; "%s" is in %s.', $request['category'], $piece, $found->category ) );
		}

		$this->plugin->piece_requests()->complete( $id, $piece );
		\WP_CLI::success( sprintf( 'Request %d done: %s.', $id, $piece ) );
	}

	/**
	 * Decline a queued request with a note the requester sees on the Library page.
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
		$this->queued( $id );

		if ( '' === $note ) {
			\WP_CLI::error( '--note is required so the requester knows why.' );
		}

		$this->plugin->piece_requests()->decline( $id, $note );
		\WP_CLI::success( sprintf( 'Request %d declined.', $id ) );
	}

	/**
	 * A queued request, or exit with an error.
	 *
	 * @param int $id Request ID.
	 * @return array<string, mixed>
	 */
	private function queued( int $id ): array {
		$request = $this->plugin->piece_requests()->get( $id );
		if ( null === $request ) {
			\WP_CLI::error( sprintf( 'Request %d not found.', $id ) );
		}
		if ( 'queued' !== $request['state'] ) {
			\WP_CLI::error( sprintf( 'Request %d is already %s.', $id, $request['state'] ) );
		}

		return $request;
	}
}
