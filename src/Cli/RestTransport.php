<?php
/**
 * HTTP for the REST piece request client.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Lets RestClient be tested without a network.
 */
interface RestTransport {

	/**
	 * Call the site's REST API.
	 *
	 * @param string                   $method GET or POST.
	 * @param string                   $path   Path and query, starting with /wp-json/.
	 * @param array<string,mixed>|null $json   JSON body.
	 * @return array{status: int, body: string} Status 0 when the request could not be made.
	 */
	public function request( string $method, string $path, ?array $json = null ): array;

	/**
	 * Save a file from the same site.
	 *
	 * @param string $url  File URL.
	 * @param string $dest Local path.
	 * @return bool
	 */
	public function download( string $url, string $dest ): bool;
}
