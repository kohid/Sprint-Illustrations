<?php
/**
 * The cURL transport for the REST piece request client.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Sends Basic credentials (a WordPress Application Password) only when given; a sandbox whose proxy
 * adds the credential itself sends none. Files are only fetched from the site's own host.
 */
final class CurlTransport implements RestTransport {

	/**
	 * Constructor.
	 *
	 * @param string $site     Site address, e.g. https://example.com.
	 * @param string $user     Optional user name.
	 * @param string $password Optional application password.
	 */
	public function __construct( private string $site, private string $user = '', private string $password = '' ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                   $method Method.
	 * @param string                   $path   Path.
	 * @param array<string,mixed>|null $json   JSON body.
	 * @return array{status: int, body: string}
	 */
	public function request( string $method, string $path, ?array $json = null ): array {
		$headers = [ 'Accept: application/json' ];
		$handle  = $this->handle( rtrim( $this->site, '/' ) . $path );
		curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		if ( null !== $json ) {
			$headers[] = 'Content-Type: application/json';
			curl_setopt( $handle, CURLOPT_POSTFIELDS, (string) json_encode( $json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );

		$body   = curl_exec( $handle );
		$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		curl_close( $handle );

		return [
			'status' => $status,
			'body'   => is_string( $body ) ? $body : '',
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $url  URL.
	 * @param string $dest Destination.
	 * @return bool
	 */
	public function download( string $url, string $dest ): bool {
		if ( parse_url( $url, PHP_URL_HOST ) !== parse_url( $this->site, PHP_URL_HOST ) ) {
			return false;
		}

		$out = fopen( $dest, 'wb' );
		if ( false === $out ) {
			return false;
		}
		$handle = $this->handle( $url );
		curl_setopt( $handle, CURLOPT_FILE, $out );
		$ok     = curl_exec( $handle );
		$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		curl_close( $handle );
		fclose( $out );

		if ( true !== $ok || 200 !== $status ) {
			unlink( $dest );
			return false;
		}

		return true;
	}

	/**
	 * A cURL handle with the shared options (timeouts, CA bundle from the sandbox, credentials).
	 *
	 * @param string $url URL.
	 * @return \CurlHandle
	 */
	private function handle( string $url ): \CurlHandle {
		$handle = curl_init( $url );
		curl_setopt( $handle, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, 10 );
		curl_setopt( $handle, CURLOPT_USERAGENT, 'Sprint-Illustrations-drawer' );
		if ( '' !== $this->user ) {
			curl_setopt( $handle, CURLOPT_USERPWD, $this->user . ':' . $this->password );
		}
		foreach ( [ 'CURL_CA_BUNDLE', 'SSL_CERT_FILE', 'REQUESTS_CA_BUNDLE' ] as $name ) {
			$bundle = (string) getenv( $name );
			if ( '' !== $bundle && is_readable( $bundle ) ) {
				curl_setopt( $handle, CURLOPT_CAINFO, $bundle );
				break;
			}
		}

		return $handle;
	}
}
