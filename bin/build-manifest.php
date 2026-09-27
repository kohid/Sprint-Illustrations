<?php
/**
 * Build a piece manifest without WordPress.
 *
 * Usage:
 *   php bin/build-manifest.php [--source=<dir>] [--target=<dir>] [--non-interactive]
 *
 * Defaults build the bundled library: --source=assets/pieces-src --target=assets
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

use SprintIllustrations\Cli\ManifestBuilder;
use SprintIllustrations\Cli\NullPrompter;
use SprintIllustrations\Cli\StdinPrompter;
use SprintIllustrations\Security\Sanitizer;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$sprint_illustrations_root = dirname( __DIR__ );

require $sprint_illustrations_root . '/vendor/autoload.php';
require $sprint_illustrations_root . '/vendor-prefixed/autoload.php';

$sprint_illustrations_options = getopt( '', [ 'source:', 'target:', 'non-interactive' ] );
$sprint_illustrations_source  = $sprint_illustrations_options['source'] ?? $sprint_illustrations_root . '/assets/pieces-src';
$sprint_illustrations_target  = $sprint_illustrations_options['target'] ?? $sprint_illustrations_root . '/assets';
$sprint_illustrations_prompt  = isset( $sprint_illustrations_options['non-interactive'] ) ? new NullPrompter() : new StdinPrompter();

$sprint_illustrations_report = ( new ManifestBuilder( new Sanitizer(), $sprint_illustrations_prompt ) )
	->build( $sprint_illustrations_source, $sprint_illustrations_target );

foreach ( $sprint_illustrations_report->warnings as $sprint_illustrations_line ) {
	fwrite( STDERR, "warning: $sprint_illustrations_line\n" );
}
foreach ( $sprint_illustrations_report->errors as $sprint_illustrations_line ) {
	fwrite( STDERR, "error: $sprint_illustrations_line\n" );
}

printf(
	"Built %d pieces into %s/manifest.json (version %d).\n",
	count( $sprint_illustrations_report->built ),
	$sprint_illustrations_target,
	$sprint_illustrations_report->version
);

exit( $sprint_illustrations_report->errors ? 1 : 0 );
