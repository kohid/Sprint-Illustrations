<?php
/**
 * Remote mode helper for bin/claude/wp-remote.sh: copy each request's reference image from the server
 * to a local temp file and rewrite its "reference" path, so the session can open it. Other lines pass through.
 *
 * Usage: … | SI_SCP="scp …" SI_REMOTE_ROOT=/path/to/wordpress php remote-filter.php user@host
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

/**
 * Local copy of a remote reference image, or '' when it can't be fetched.
 *
 * @param string               $remote Remote path.
 * @param array<string,string> $conf   Keys target, scp, root, cache.
 * @return string
 */
function sprint_illustrations_fetch_reference( string $remote, array $conf ): string {
	if ( ! str_starts_with( $remote, $conf['root'] ) || str_contains( $remote, '..' ) || 1 !== preg_match( '/\.(png|jpe?g|webp)$/i', $remote ) ) {
		return '';
	}
	if ( ! is_dir( $conf['cache'] ) && ! mkdir( $conf['cache'], 0700, true ) ) {
		return '';
	}
	$local = $conf['cache'] . '/' . substr( sha1( $remote ), 0, 12 ) . '-' . basename( $remote );
	if ( ! is_file( $local ) ) {
		$out  = [];
		$code = 1;
		exec( sprintf( '%s %s %s 2>&1', $conf['scp'], escapeshellarg( $conf['target'] . ':' . $remote ), escapeshellarg( $local ) ), $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Fixed scp command, arguments escaped.
		if ( 0 !== $code || ! is_file( $local ) ) {
			return '';
		}
	}
	return str_replace( '\\', '/', $local );
}

/**
 * Rewrite the "reference" of one request row.
 *
 * @param array<string,mixed>  $row  Request row.
 * @param array<string,string> $conf Keys target, scp, root, cache.
 * @return array<string,mixed>
 */
function sprint_illustrations_rewrite_row( array $row, array $conf ): array {
	if ( isset( $row['reference'] ) && is_string( $row['reference'] ) && '' !== $row['reference'] ) {
		$local = sprint_illustrations_fetch_reference( $row['reference'], $conf );
		if ( '' === $local ) {
			$row['reference_unavailable'] = true;
		}
		$row['reference'] = $local;
	}
	return $row;
}

/**
 * Filter STDIN to STDOUT line by line.
 *
 * @param string $target SSH target.
 * @return void
 */
function sprint_illustrations_remote_filter( string $target ): void {
	$conf = [
		'target' => $target,
		'scp'    => (string) getenv( 'SI_SCP' ),
		'root'   => rtrim( (string) getenv( 'SI_REMOTE_ROOT' ), '/' ) . '/',
		'cache'  => sys_get_temp_dir() . '/si-remote-refs',
	];

	while ( ! feof( STDIN ) ) {
		$line = fgets( STDIN );
		if ( false === $line ) {
			break;
		}
		$data = json_decode( $line, true );
		if ( is_array( $data ) ) {
			$data = array_is_list( $data )
				? array_map( static fn( $row ) => is_array( $row ) ? sprint_illustrations_rewrite_row( $row, $conf ) : $row, $data )
				: sprint_illustrations_rewrite_row( $data, $conf );
			$line = sprint_illustrations_json( $data ) . "\n";
		}
		fwrite( STDOUT, $line );
		fflush( STDOUT );
	}
}

/**
 * JSON for output lines (no WordPress here, so not wp_json_encode).
 *
 * @param mixed $data Data.
 * @return string
 */
function sprint_illustrations_json( $data ): string {
	return (string) json_encode( $data, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone script, WordPress isn't loaded.
}

sprint_illustrations_remote_filter( (string) ( $argv[1] ?? '' ) );
