<?php
/**
 * Render a contact sheet of every template without WordPress.
 *
 * Usage:
 *   php bin/contact-sheet.php [--seeds=1,2,3,4] [--templates=a,b] [--keywords=team,coffee] > sheet.html
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Services;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$sprint_illustrations_root = dirname( __DIR__ );

require $sprint_illustrations_root . '/vendor/autoload.php';
require $sprint_illustrations_root . '/vendor-prefixed/autoload.php';

$sprint_illustrations_options  = getopt( '', [ 'seeds:', 'templates:', 'keywords:' ] );
$sprint_illustrations_services = Services::create( $sprint_illustrations_root );

$sprint_illustrations_seeds     = array_map( 'intval', explode( ',', $sprint_illustrations_options['seeds'] ?? '1,2,3,4' ) );
$sprint_illustrations_templates = isset( $sprint_illustrations_options['templates'] )
	? explode( ',', $sprint_illustrations_options['templates'] )
	: $sprint_illustrations_services->templates->ids();
$sprint_illustrations_keywords  = isset( $sprint_illustrations_options['keywords'] ) ? explode( ',', $sprint_illustrations_options['keywords'] ) : [];

echo ContactSheet::document(
	( new ContactSheet( $sprint_illustrations_services->composer ) )->render(
		$sprint_illustrations_templates,
		$sprint_illustrations_seeds,
		ContactSheet::review_palettes(),
		$sprint_illustrations_keywords
	)
);
