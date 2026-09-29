<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Cli\RestClient;
use SprintIllustrations\Cli\RestTransport;

final class RestClientTest extends TestCase {

	private const SITE = 'https://example.test';

	private string $tmp;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/si-rest-test-' . getmypid() . '-' . uniqid();
	}

	protected function tearDown(): void {
		foreach ( (array) glob( $this->tmp . '/*' ) as $file ) {
			unlink( (string) $file );
		}
		if ( is_dir( $this->tmp ) ) {
			rmdir( $this->tmp );
		}
	}

	/**
	 * Client over a fake transport.
	 *
	 * @param array<string, array{int, mixed}> $responses "METHOD path" => [status, decoded body].
	 * @param array<int, string>              $out       Collected output.
	 * @param array<int, string>              $err       Collected errors.
	 * @return array{RestClient, object}
	 */
	private function client( array $responses, array &$out, array &$err ): array {
		$http = new class( $responses ) implements RestTransport {
			/** @var array<int, string> */
			public array $calls = [];
			/** @var array<int, array<string, mixed>|null> */
			public array $bodies = [];
			/** @var array<int, string> */
			public array $downloads = [];

			/**
			 * @param array<string, array{int, mixed}> $responses Canned responses.
			 */
			public function __construct( private array $responses ) {}

			public function request( string $method, string $path, ?array $json = null ): array {
				$this->calls[]  = $method . ' ' . $path;
				$this->bodies[] = $json;
				$hit            = $this->responses[ $method . ' ' . $path ] ?? [ 0, null ];

				return [
					'status' => $hit[0],
					'body'   => is_string( $hit[1] ) ? $hit[1] : (string) json_encode( $hit[1] ),
				];
			}

			public function download( string $url, string $dest ): bool {
				$this->downloads[] = $url;
				return false !== file_put_contents( $dest, 'PNG' );
			}
		};

		$time   = 1000;
		$client = new RestClient(
			$http,
			self::SITE,
			$this->tmp,
			static function ( string $text ) use ( &$out ): void {
				$out[] = $text;
			},
			static function ( string $text ) use ( &$err ): void {
				$err[] = $text;
			},
			static function ( int $seconds ) use ( &$time ): void {
				$time += $seconds;
			},
			static function () use ( &$time ): int {
				return $time;
			}
		);

		return [ $client, $http ];
	}

	/**
	 * A request row as the API returns it.
	 *
	 * @param array<string, mixed> $override Fields to replace.
	 * @return array<string, mixed>
	 */
	private function row( array $override = [] ): array {
		return array_merge(
			[
				'id'            => 593,
				'category'      => 'backgrounds',
				'description'   => 'Circle curve shape',
				'state'         => 'queued',
				'piece'         => '',
				'note'          => '',
				'draft'         => '',
				'feedback'      => '',
				'author'        => 'kohid',
				'date'          => '2026-09-29 17:57:29',
				'changed'       => '',
				'reference_url' => '',
			],
			$override
		);
	}

	public function test_only_the_request_commands_are_available(): void {
		$out               = [];
		$err               = [];
		[ $client, $http ] = $this->client( [], $out, $err );

		$this->assertSame( 1, $client->run( [ 'core', 'version' ] ) );
		$this->assertSame( 1, $client->run( [ 'sprint-illustrations', 'requests', 'eval' ] ) );
		$this->assertSame( [], $http->calls );
		$this->assertStringContainsString( 'Error: Only', $err[0] );
	}

	public function test_list_json_matches_the_cli_shape_with_local_reference_paths(): void {
		$out               = [];
		$err               = [];
		$rows              = [
			$this->row( [ 'reference_url' => 'https://example.test/wp-content/uploads/ref.png' ] ),
			$this->row( [ 'id' => 595 ] ),
		];
		[ $client, $http ] = $this->client( [ 'GET /wp-json/sprint-illustrations/v1/requests?state=queued' => [ 200, $rows ] ], $out, $err );

		$this->assertSame( 0, $client->run( [ 'sprint-illustrations', 'requests', 'list', '--format=json' ] ) );

		$data = json_decode( implode( '', $out ), true );
		$this->assertCount( 2, $data );
		$this->assertArrayNotHasKey( 'reference_url', $data[0] );
		$this->assertStringStartsWith( $this->tmp, $data[0]['reference'] );
		$this->assertFileExists( $data[0]['reference'] );
		$this->assertSame( '', $data[1]['reference'] );
		$this->assertSame( [ 'https://example.test/wp-content/uploads/ref.png' ], $http->downloads );
	}

	public function test_references_from_other_hosts_or_non_images_are_not_downloaded(): void {
		$out               = [];
		$err               = [];
		$rows              = [
			$this->row( [ 'reference_url' => 'https://evil.test/ref.png' ] ),
			$this->row(
				[
					'id'            => 2,
					'reference_url' => 'https://example.test/secret.php',
				]
			),
		];
		[ $client, $http ] = $this->client( [ 'GET /wp-json/sprint-illustrations/v1/requests?state=queued' => [ 200, $rows ] ], $out, $err );

		$client->run( [ 'sprint-illustrations', 'requests', 'list', '--format=json' ] );

		$data = json_decode( implode( '', $out ), true );
		$this->assertSame( '', $data[0]['reference'] );
		$this->assertSame( '', $data[1]['reference'] );
		$this->assertSame( [], $http->downloads );
	}

	public function test_start_and_decline(): void {
		$out               = [];
		$err               = [];
		[ $client, $http ] = $this->client(
			[
				'POST /wp-json/sprint-illustrations/v1/requests/593/start'   => [ 200, [ 'ok' => true ] ],
				'POST /wp-json/sprint-illustrations/v1/requests/595/decline' => [ 200, [ 'ok' => true ] ],
				'POST /wp-json/sprint-illustrations/v1/requests/598/start'   => [ 409, [ 'message' => 'Request 598 is not queued.' ] ],
			],
			$out,
			$err
		);

		$this->assertSame( 0, $client->run( [ 'sprint-illustrations', 'requests', 'start', '593' ] ) );
		$this->assertSame( "Success: Request 593: drawing.\n", $out[0] );

		$this->assertSame( 1, $client->run( [ 'sprint-illustrations', 'requests', 'start', '598' ] ) );
		$this->assertSame( "Error: Request 598 is not queued.\n", $err[0] );

		$this->assertSame( 1, $client->run( [ 'sprint-illustrations', 'requests', 'decline', '595' ] ) );
		$this->assertStringContainsString( '--note is required', $err[1] );

		$this->assertSame( 0, $client->run( [ 'sprint-illustrations', 'requests', 'decline', '595', '--note=Too detailed for the flat style' ] ) );
		$this->assertSame( [ 'note' => 'Too detailed for the flat style' ], $http->bodies[ count( $http->bodies ) - 1 ] );
	}

	public function test_draft_sends_the_name_and_svg(): void {
		$out  = [];
		$err  = [];
		$file = sys_get_temp_dir() . '/circle-curve.svg';
		file_put_contents( $file, '<svg viewBox="0 0 1 1"/>' );
		[ $client, $http ] = $this->client(
			[
				'POST /wp-json/sprint-illustrations/v1/requests/593/draft' => [
					200,
					[
						'ok'    => true,
						'piece' => 'bg-circle-curve',
					],
				],
			],
			$out,
			$err
		);

		$code = $client->run( [ 'sprint-illustrations', 'requests', 'draft', '593', '--file=' . $file ] );
		unlink( $file );

		$this->assertSame( 0, $code );
		$this->assertSame(
			[
				'name' => 'circle-curve',
				'svg'  => '<svg viewBox="0 0 1 1"/>',
			],
			$http->bodies[0]
		);
		$this->assertSame( "Success: Request 593: draft bg-circle-curve is ready for review on the Library page.\n", $out[0] );
	}

	public function test_a_rejected_draft_prints_each_warning_then_fails(): void {
		$out  = [];
		$err  = [];
		$file = sys_get_temp_dir() . '/bad-piece.svg';
		file_put_contents( $file, '<svg/>' );
		[ $client ] = $this->client(
			[
				'POST /wp-json/sprint-illustrations/v1/requests/593/draft' => [
					422,
					[
						'ok'       => false,
						'messages' => [ 'Literal colour #ff0000', 'Missing anchor' ],
					],
				],
			],
			$out,
			$err
		);

		$code = $client->run( [ 'sprint-illustrations', 'requests', 'draft', '593', '--file=' . $file ] );
		unlink( $file );

		$this->assertSame( 1, $code );
		$this->assertSame( "Warning: Literal colour #ff0000\n", $err[0] );
		$this->assertSame( "Warning: Missing anchor\n", $err[1] );
		$this->assertStringContainsString( 'The draft was not accepted', $err[2] );
	}

	public function test_draft_needs_a_readable_file(): void {
		$out               = [];
		$err               = [];
		[ $client, $http ] = $this->client( [], $out, $err );

		$this->assertSame( 1, $client->run( [ 'sprint-illustrations', 'requests', 'draft', '593', '--file=/nope/missing.svg' ] ) );
		$this->assertSame( [], $http->calls );
	}

	public function test_watch_announces_each_request_once_and_stops_at_max_runtime(): void {
		$out               = [];
		$err               = [];
		$rows              = [
			$this->row(),
			$this->row(
				[
					'id'   => 595,
					'date' => '2026-09-29 18:37:41',
				]
			),
		];
		[ $client, $http ] = $this->client( [ 'POST /wp-json/sprint-illustrations/v1/requests/watch' => [ 200, [ 'requests' => $rows ] ] ], $out, $err );

		$code = $client->run( [ 'sprint-illustrations', 'requests', 'watch', '--interval=10', '--max-runtime=25' ] );

		$this->assertSame( 0, $code );
		$this->assertGreaterThanOrEqual( 3, count( $http->calls ) );
		$events = array_map( static fn( string $line ): array => json_decode( $line, true ), $out );
		$this->assertCount( 2, $events, 'Each waiting request is announced once, not on every poll.' );
		$this->assertSame( [ 593, 595 ], array_column( $events, 'id' ) );
		$this->assertSame( 'request', $events[0]['event'] );
	}

	public function test_watch_once_polls_a_single_time(): void {
		$out               = [];
		$err               = [];
		[ $client, $http ] = $this->client( [ 'POST /wp-json/sprint-illustrations/v1/requests/watch' => [ 200, [ 'requests' => [] ] ] ], $out, $err );

		$this->assertSame( 0, $client->run( [ 'sprint-illustrations', 'requests', 'watch', '--once' ] ) );
		$this->assertCount( 1, $http->calls );
		$this->assertSame( [], $out );
	}

	public function test_watch_stops_on_an_auth_failure_but_rides_out_a_blip(): void {
		$out        = [];
		$err        = [];
		[ $client ] = $this->client( [ 'POST /wp-json/sprint-illustrations/v1/requests/watch' => [ 401, [ 'message' => 'Sorry, you are not allowed to do that.' ] ] ], $out, $err );

		$this->assertSame( 1, $client->run( [ 'sprint-illustrations', 'requests', 'watch', '--once' ] ) );
		$this->assertSame( "Error: Sorry, you are not allowed to do that.\n", $err[0] );

		$out        = [];
		$err        = [];
		[ $client ] = $this->client( [], $out, $err );
		$this->assertSame( 0, $client->run( [ 'sprint-illustrations', 'requests', 'watch', '--once' ] ) );
		$this->assertStringContainsString( 'Queue check failed: Could not reach the site.', $err[0] );
	}
}
