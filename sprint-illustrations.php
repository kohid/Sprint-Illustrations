<?php
/**
 * Plugin Name:       Sprint Illustrations
 * Description:       Composes flat, brand-coloured illustrations from a library of SVG pieces.
 * Version:           0.9.1
 * Update URI:        https://github.com/kohid/Sprint-Illustrations
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Sprint
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sprint-illustrations
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SPRINT_ILLUSTRATIONS_VERSION', '0.9.1' );
define( 'SPRINT_ILLUSTRATIONS_FILE', __FILE__ );

/**
 * Show an admin notice and stop loading.
 *
 * @param string $message Plain-text message.
 */
function sprint_illustrations_fail( string $message ): void {
	add_action(
		'admin_notices',
		static function () use ( $message ): void {
			printf( '<div class="notice notice-error"><p><strong>Sprint Illustrations:</strong> %s</p></div>', esc_html( $message ) );
		}
	);
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( get_bloginfo( 'version' ), '6.4', '<' ) ) {
	sprint_illustrations_fail( __( 'Requires PHP 8.1+ and WordPress 6.4+.', 'sprint-illustrations' ) );
	return;
}

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) || ! is_readable( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	sprint_illustrations_fail( __( 'Dependencies are missing. Run "composer install" and "composer prefix" in the plugin folder.', 'sprint-illustrations' ) );
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor-prefixed/autoload.php';

register_activation_hook( __FILE__, [ \SprintIllustrations\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \SprintIllustrations\Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ \SprintIllustrations\Plugin::class, 'boot' ] );
