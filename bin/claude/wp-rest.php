<?php
/**
 * `wp sprint-illustrations requests …` over the site's REST API, for a drawer that can't run WP-CLI there
 * (called by bin/claude/wp.sh when SI_REST_URL is set).
 *
 * Environment: SI_REST_URL (https://site; plain http only for localhost), optional SI_REST_USER and SI_REST_PASSWORD (an Application
 * Password; leave empty when a sandbox proxy adds the credential itself).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

use SprintIllustrations\Cli\CurlTransport;
use SprintIllustrations\Cli\RestClient;

require_once __DIR__ . '/../../src/Library/WatchQueue.php';
require_once __DIR__ . '/../../src/Cli/RestTransport.php';
require_once __DIR__ . '/../../src/Cli/CurlTransport.php';
require_once __DIR__ . '/../../src/Cli/RestClient.php';

/**
 * Entry point.
 *
 * @param array<int, string> $args Arguments after the script name.
 * @return int Exit code.
 */
function sprint_illustrations_rest_main( array $args ): int {
	$site   = rtrim( (string) getenv( 'SI_REST_URL' ), '/' );
	$secure = str_starts_with( $site, 'https://' ) || 1 === preg_match( '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#', $site );
	if ( ! $secure || ! extension_loaded( 'curl' ) ) {
		fwrite( STDERR, "Error: set SI_REST_URL to the site's https:// address (plain http only for localhost), and enable PHP's curl extension.\n" );
		return 1;
	}

	$client = new RestClient(
		new CurlTransport( $site, (string) getenv( 'SI_REST_USER' ), (string) getenv( 'SI_REST_PASSWORD' ) ),
		$site,
		sys_get_temp_dir() . '/si-rest-refs'
	);

	return $client->run( $args );
}

exit( sprint_illustrations_rest_main( array_slice( $argv, 1 ) ) );
