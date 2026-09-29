<?php
/**
 * The `wp sprint-illustrations requests …` commands, over the site's REST API.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Library\WatchQueue;

/**
 * Same commands, arguments and output as Cli\RequestsCommand (list, start, draft, decline, watch), so
 * bin/claude/wp.sh and the Claude Code hooks work unchanged for a drawer that can't run WP-CLI on the site.
 * Reference images are downloaded and reported as local paths.
 */
final class RestClient {

	private const API = '/wp-json/sprint-illustrations/v1';

	/**
	 * Output line writer.
	 *
	 * @var \Closure(string): void
	 */
	private \Closure $out;

	/**
	 * Error line writer.
	 *
	 * @var \Closure(string): void
	 */
	private \Closure $err;

	/**
	 * Sleep.
	 *
	 * @var \Closure(int): void
	 */
	private \Closure $sleep;

	/**
	 * Clock.
	 *
	 * @var \Closure(): int
	 */
	private \Closure $now;

	/**
	 * Constructor.
	 *
	 * @param RestTransport $http     Transport.
	 * @param string        $site     Site address (the only host reference images may come from).
	 * @param string        $tmp_dir  Folder for downloaded reference images.
	 * @param \Closure|null $out      Writes to STDOUT (default) — receives a string with its newline.
	 * @param \Closure|null $err      Writes to STDERR (default).
	 * @param \Closure|null $sleep    Sleeps N seconds (default sleep()).
	 * @param \Closure|null $now      Unix time (default time()).
	 */
	public function __construct(
		private RestTransport $http,
		private string $site,
		private string $tmp_dir,
		?\Closure $out = null,
		?\Closure $err = null,
		?\Closure $sleep = null,
		?\Closure $now = null
	) {
		$this->out   = $out ?? static function ( string $text ): void {
			fwrite( STDOUT, $text );
			fflush( STDOUT );
		};
		$this->err   = $err ?? static function ( string $text ): void {
			fwrite( STDERR, $text );
		};
		$this->sleep = $sleep ?? static function ( int $seconds ): void {
			sleep( $seconds );
		};
		$this->now   = $now ?? static fn(): int => time();
	}

	/**
	 * Run one command.
	 *
	 * @param array<int, string> $argv Arguments after the program name, e.g. sprint-illustrations requests list --format=json.
	 * @return int Exit code.
	 */
	public function run( array $argv ): int {
		if ( 'sprint-illustrations' !== ( $argv[0] ?? '' ) || 'requests' !== ( $argv[1] ?? '' ) ) {
			return $this->fail( 'Only "sprint-illustrations requests list|start|draft|decline|watch" is available here.' );
		}

		$sub    = $argv[2] ?? '';
		$parsed = $this->parse( array_slice( $argv, 3 ) );

		return match ( $sub ) {
			'list'    => $this->list_( $parsed['options'] ),
			'start'   => $this->start( $parsed['args'] ),
			'draft'   => $this->draft( $parsed['args'], $parsed['options'] ),
			'decline' => $this->decline( $parsed['args'], $parsed['options'] ),
			'watch'   => $this->watch( $parsed['options'] ),
			default   => $this->fail( 'Only "sprint-illustrations requests list|start|draft|decline|watch" is available here.' ),
		};
	}

	/**
	 * Split WP-CLI style arguments.
	 *
	 * @param array<int, string> $items Arguments.
	 * @return array{args: array<int, string>, options: array<string, string|bool>}
	 */
	private function parse( array $items ): array {
		$args    = [];
		$options = [];
		foreach ( $items as $item ) {
			if ( str_starts_with( $item, '--' ) ) {
				$pair                = explode( '=', substr( $item, 2 ), 2 );
				$options[ $pair[0] ] = $pair[1] ?? true;
				continue;
			}
			$args[] = $item;
		}

		return [
			'args'    => $args,
			'options' => $options,
		];
	}

	/**
	 * Requests list.
	 *
	 * @param array<string, string|bool> $options Options.
	 * @return int
	 */
	private function list_( array $options ): int {
		$state  = is_string( $options['state'] ?? null ) ? (string) $options['state'] : 'queued';
		$result = $this->call( 'GET', '/requests?state=' . rawurlencode( $state ) );
		if ( ! $result['ok'] || ! is_array( $result['data'] ) ) {
			return $this->fail( $result['message'] );
		}

		$rows = array_map( [ $this, 'localize' ], array_values( $result['data'] ) );
		if ( 'json' === ( $options['format'] ?? 'table' ) ) {
			( $this->out )( json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
			return 0;
		}
		if ( ! $rows ) {
			( $this->out )( "No requests.\n" );
			return 0;
		}

		foreach ( $rows as $row ) {
			( $this->out )( implode( "\t", [ $row['id'], $row['category'], $row['description'], $row['state'], $row['feedback'], $row['piece'], $row['author'], $row['date'] ] ) . "\n" );
		}

		return 0;
	}

	/**
	 * Mark a request as being drawn.
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return int
	 */
	private function start( array $args ): int {
		$id     = (int) ( $args[0] ?? 0 );
		$result = $this->call( 'POST', '/requests/' . $id . '/start' );
		if ( ! $result['ok'] ) {
			return $this->fail( $result['message'] );
		}
		( $this->out )( sprintf( "Success: Request %d: drawing.\n", $id ) );

		return 0;
	}

	/**
	 * Submit a drawn SVG as the draft.
	 *
	 * @param array<int, string>         $args    Positional arguments.
	 * @param array<string, string|bool> $options Options.
	 * @return int
	 */
	private function draft( array $args, array $options ): int {
		$id   = (int) ( $args[0] ?? 0 );
		$file = is_string( $options['file'] ?? null ) ? (string) $options['file'] : '';
		if ( '' === $file || ! is_readable( $file ) ) {
			return $this->fail( sprintf( 'Cannot read %s.', $file ) );
		}

		$result = $this->call(
			'POST',
			'/requests/' . $id . '/draft',
			[
				'name' => basename( $file, '.svg' ),
				'svg'  => (string) file_get_contents( $file ),
			]
		);
		if ( ! $result['ok'] ) {
			$messages = is_array( $result['data'] ) && is_array( $result['data']['messages'] ?? null ) ? $result['data']['messages'] : [];
			foreach ( $messages as $message ) {
				( $this->err )( 'Warning: ' . $message . "\n" );
			}

			return $this->fail( $messages ? 'The draft was not accepted; fix the SVG and submit it again.' : $result['message'] );
		}

		$piece = is_array( $result['data'] ) ? (string) ( $result['data']['piece'] ?? '' ) : '';
		( $this->out )( sprintf( "Success: Request %d: draft %s is ready for review on the Library page.\n", $id, $piece ) );

		return 0;
	}

	/**
	 * Decline a request with a note.
	 *
	 * @param array<int, string>         $args    Positional arguments.
	 * @param array<string, string|bool> $options Options.
	 * @return int
	 */
	private function decline( array $args, array $options ): int {
		$id   = (int) ( $args[0] ?? 0 );
		$note = trim( is_string( $options['note'] ?? null ) ? (string) $options['note'] : '' );
		if ( '' === $note ) {
			return $this->fail( '--note is required so the requester knows why.' );
		}

		$result = $this->call( 'POST', '/requests/' . $id . '/decline', [ 'note' => $note ] );
		if ( ! $result['ok'] ) {
			return $this->fail( $result['message'] );
		}
		( $this->out )( sprintf( "Success: Request %d declined.\n", $id ) );

		return 0;
	}

	/**
	 * Watch the queue: heartbeat plus one JSON line per newly waiting request.
	 *
	 * @param array<string, string|bool> $options Options.
	 * @return int
	 */
	private function watch( array $options ): int {
		$interval = max( 5, min( 60, (int) ( is_string( $options['interval'] ?? null ) ? $options['interval'] : 10 ) ) );
		$once     = isset( $options['once'] );
		$runtime  = max( 0, (int) ( is_string( $options['max-runtime'] ?? null ) ? $options['max-runtime'] : 0 ) );
		$parent   = max( 0, (int) ( is_string( $options['parent'] ?? null ) ? $options['parent'] : 0 ) );
		$started  = ( $this->now )();
		$seen     = [];

		while ( true ) {
			$result = $this->call( 'POST', '/requests/watch' );
			if ( $result['ok'] && is_array( $result['data'] ) && is_array( $result['data']['requests'] ?? null ) ) {
				$next = WatchQueue::announce( array_values( $result['data']['requests'] ), $seen );
				foreach ( $next['rows'] as $row ) {
					$this->announce( $row );
				}
				$seen = $next['seen'];
			} elseif ( in_array( $result['status'], [ 401, 403, 404 ], true ) ) {
				return $this->fail( $result['message'] );
			} else {
				( $this->err )( 'Warning: Queue check failed: ' . $result['message'] . "\n" );
			}

			if ( $once || ( $runtime && ( $this->now )() - $started >= $runtime ) ) {
				return 0;
			}
			( $this->sleep )( $interval );
			if ( ! $this->running( $parent ) ) {
				return 0;
			}
		}//end while
	}

	/**
	 * One request event line.
	 *
	 * @param array<string, mixed> $row Request row.
	 */
	private function announce( array $row ): void {
		$row = $this->localize( $row );

		( $this->out )(
			json_encode(
				[
					'event'       => 'request',
					'id'          => $row['id'],
					'category'    => $row['category'],
					'description' => $row['description'],
					'feedback'    => $row['feedback'],
					'reference'   => $row['reference'],
				],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			) . "\n"
		);
	}

	/**
	 * Replace a row's reference URL by a local copy of the image.
	 *
	 * @param array<string, mixed> $row Row from the API.
	 * @return array<string, mixed>
	 */
	private function localize( array $row ): array {
		$url = (string) ( $row['reference_url'] ?? '' );
		unset( $row['reference_url'] );
		$row['reference'] = '' === $url ? '' : $this->download_reference( $url );

		return $row;
	}

	/**
	 * Download a reference image from the site (only images, only from the site's own host).
	 *
	 * @param string $url Image URL.
	 * @return string Local path, or '' when it can't be fetched.
	 */
	private function download_reference( string $url ): string {
		$path = (string) parse_url( $url, PHP_URL_PATH );
		if ( parse_url( $url, PHP_URL_HOST ) !== parse_url( $this->site, PHP_URL_HOST ) || 1 !== preg_match( '/\.(png|jpe?g|webp)$/i', $path ) ) {
			return '';
		}
		if ( ! is_dir( $this->tmp_dir ) && ! mkdir( $this->tmp_dir, 0700, true ) ) {
			return '';
		}

		$dest = rtrim( $this->tmp_dir, '/' ) . '/' . substr( sha1( $url ), 0, 12 ) . '-' . basename( $path );
		if ( ! is_file( $dest ) && ! $this->http->download( $url, $dest ) ) {
			return '';
		}

		return str_replace( '\\', '/', $dest );
	}

	/**
	 * One API call.
	 *
	 * @param string                   $method Method.
	 * @param string                   $path   Path under the API root.
	 * @param array<string,mixed>|null $json   JSON body.
	 * @return array{ok: bool, status: int, data: mixed, message: string}
	 */
	private function call( string $method, string $path, ?array $json = null ): array {
		$response = $this->http->request( $method, self::API . $path, $json );
		$data     = json_decode( $response['body'], true );
		$ok       = $response['status'] >= 200 && $response['status'] < 300;

		if ( $ok ) {
			$message = '';
		} elseif ( is_array( $data ) && is_string( $data['message'] ?? null ) ) {
			$message = $data['message'];
		} elseif ( 0 === $response['status'] ) {
			$message = 'Could not reach the site.';
		} else {
			$message = sprintf( 'The site answered HTTP %d without a usable message.', $response['status'] );
		}

		return [
			'ok'      => $ok,
			'status'  => $response['status'],
			'data'    => $data,
			'message' => $message,
		];
	}

	/**
	 * Whether the parent process is still running (true when none was given or it can't be checked).
	 *
	 * @param int $pid Process ID (0 = none).
	 * @return bool
	 */
	private function running( int $pid ): bool {
		return 0 === $pid || ! function_exists( 'posix_kill' ) || posix_kill( $pid, 0 );
	}

	/**
	 * Print an error line and return the failure code.
	 *
	 * @param string $message Message.
	 * @return int
	 */
	private function fail( string $message ): int {
		( $this->err )( 'Error: ' . $message . "\n" );

		return 1;
	}
}
