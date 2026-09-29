<?php
/**
 * Print the Local (by Flywheel) environment of the site that contains this plugin, for bin/claude/wp.sh.
 *
 * Output lines: PHPRC=…, PHP=… (directory of php.exe), PUBLIC=… (site app/public), PHAR=… (WP-CLI).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

/**
 * Normalize a path for prefix comparison.
 *
 * @param string $path Path.
 * @return string
 */
function sprint_illustrations_norm( string $path ): string {
	return rtrim( strtolower( str_replace( '\\', '/', $path ) ), '/' ) . '/';
}

/**
 * The site's environment, or a plain reason it can't be found.
 *
 * @param string $plugin Plugin folder.
 * @return array<string, string>|string
 */
function sprint_illustrations_local_env( string $plugin ): array|string {
	$appdata = (string) getenv( 'APPDATA' );
	$home    = (string) ( getenv( 'USERPROFILE' ) ? getenv( 'USERPROFILE' ) : getenv( 'HOME' ) );
	$file    = $appdata . '/Local/sites.json';
	$sites   = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
	if ( ! is_array( $sites ) ) {
		return 'Local (by Flywheel) sites.json not found under APPDATA.';
	}

	$site = null;
	foreach ( $sites as $id => $candidate ) {
		$path = (string) preg_replace( '/^~/', addcslashes( $home, '\\$' ), (string) ( $candidate['path'] ?? '' ) );
		if ( '' !== $path && str_starts_with( sprint_illustrations_norm( $plugin ), sprint_illustrations_norm( $path ) ) ) {
			$site = [
				'id'   => (string) $id,
				'path' => $path,
				'php'  => (string) ( $candidate['services']['php']['version'] ?? '' ),
			];
			break;
		}
	}
	if ( null === $site ) {
		return 'no Local site contains this plugin folder.';
	}

	$php_dirs = glob( $appdata . '/Local/lightning-services/php-' . $site['php'] . '+*/bin/win64', GLOB_ONLYDIR );
	if ( ! $php_dirs ) {
		return "PHP {$site['php']} for this site is not installed in Local.";
	}
	natsort( $php_dirs );

	$phar = (string) getenv( 'WP_CLI_PHAR' );
	if ( '' === $phar ) {
		foreach ( [ getenv( 'ProgramFiles(x86)' ), getenv( 'ProgramFiles' ), getenv( 'LOCALAPPDATA' ) . '/Programs' ] as $base ) {
			if ( $base && is_file( $base . '/Local/resources/extraResources/bin/wp-cli/wp-cli.phar' ) ) {
				$phar = $base . '/Local/resources/extraResources/bin/wp-cli/wp-cli.phar';
				break;
			}
		}
	}
	if ( '' === $phar ) {
		return "Local's WP-CLI not found; set WP_CLI_PHAR.";
	}

	return [
		'PHPRC'  => $appdata . '/Local/run/' . $site['id'] . '/conf/php',
		'PHP'    => (string) end( $php_dirs ),
		'PUBLIC' => $site['path'] . '/app/public',
		'PHAR'   => $phar,
	];
}

/**
 * Print the environment, or the reason on STDERR with exit code 1.
 */
function sprint_illustrations_print_local_env(): void {
	$env = sprint_illustrations_local_env( (string) realpath( __DIR__ . '/../..' ) );
	if ( is_string( $env ) ) {
		fwrite( STDERR, "bin/claude/wp.sh: $env\n" );
		exit( 1 );
	}
	foreach ( $env as $key => $value ) {
		echo $key, '=', str_replace( '\\', '/', $value ), "\n";
	}
}

sprint_illustrations_print_local_env();
