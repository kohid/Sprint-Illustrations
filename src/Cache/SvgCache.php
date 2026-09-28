<?php
/**
 * File cache for composed SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cache;

use SprintIllustrations\Compose\ComposedSvg;

/**
 * One JSON file per key. Falls back to memory for the request when the directory is not writable.
 */
final class SvgCache {

	/**
	 * Entries kept in memory when the directory is not writable.
	 *
	 * @var array<string, ComposedSvg>
	 */
	private array $memory = [];

	/**
	 * Whether the directory is usable (null until checked).
	 *
	 * @var bool|null
	 */
	private ?bool $writable = null;

	/**
	 * Constructor.
	 *
	 * @param string $dir Cache directory (created on first write).
	 */
	public function __construct( private string $dir ) {
		$this->dir = rtrim( $dir, '/\\' );
	}

	/**
	 * Cached result, or null. A hit refreshes the file's modified time (used as "last used").
	 *
	 * @param string $key 40-character hex key.
	 * @return ComposedSvg|null
	 */
	public function get( string $key ): ?ComposedSvg {
		$file = $this->path( $key );

		if ( isset( $this->memory[ $key ] ) ) {
			return $this->memory[ $key ];
		}

		if ( ! is_file( $file ) ) {
			return null;
		}

		$data   = json_decode( (string) file_get_contents( $file ), true );
		$result = is_array( $data ) ? ComposedSvg::from_array( $data ) : null;

		if ( null === $result ) {
			unlink( $file );
			return null;
		}

		touch( $file );

		return $result;
	}

	/**
	 * Store a result.
	 *
	 * @param string      $key Key.
	 * @param ComposedSvg $svg Result.
	 */
	public function put( string $key, ComposedSvg $svg ): void {
		$file = $this->path( $key );

		if ( $this->writable() ) {
			file_put_contents( $file, (string) json_encode( $svg->to_array() ), LOCK_EX );
			return;
		}

		$this->memory[ $key ] = $svg;
	}

	/**
	 * Whether files can be written (creates the directory and its guard files on first call).
	 *
	 * @return bool
	 */
	public function writable(): bool {
		if ( null === $this->writable ) {
			$this->writable = $this->prepare();
		}

		return $this->writable;
	}

	/**
	 * Delete every entry.
	 *
	 * @return int Entries removed.
	 */
	public function purge(): int {
		$count = 0;
		foreach ( $this->entries() as $file ) {
			if ( unlink( $file ) ) {
				++$count;
			}
		}
		$this->memory = [];

		return $count;
	}

	/**
	 * Entry count and total size.
	 *
	 * @return array{files: int, bytes: int}
	 */
	public function stats(): array {
		$files = $this->entries();

		return [
			'files' => count( $files ),
			'bytes' => (int) array_sum( array_map( 'filesize', $files ) ),
		];
	}

	/**
	 * Delete entries not used for $max_age_seconds.
	 *
	 * @param int      $max_age_seconds Maximum age.
	 * @param int|null $now             Current time (tests).
	 * @return int Entries removed.
	 */
	public function collect_garbage( int $max_age_seconds, ?int $now = null ): int {
		$cutoff = ( $now ?? time() ) - $max_age_seconds;
		$count  = 0;

		foreach ( $this->entries() as $file ) {
			if ( filemtime( $file ) < $cutoff && unlink( $file ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Cache directory.
	 *
	 * @return string
	 */
	public function directory(): string {
		return $this->dir;
	}

	/**
	 * Create the directory with index.php and .htaccess guards.
	 *
	 * @return bool
	 */
	private function prepare(): bool {
		if ( file_exists( $this->dir ) && ! is_dir( $this->dir ) ) {
			return false;
		}

		if ( ! is_dir( $this->dir ) && ! mkdir( $this->dir, 0755, true ) && ! is_dir( $this->dir ) ) {
			return false;
		}

		if ( ! is_writable( $this->dir ) ) {
			return false;
		}

		if ( ! is_file( $this->dir . '/index.php' ) ) {
			file_put_contents( $this->dir . '/index.php', "<?php // Silence is golden.\n" );
		}
		if ( ! is_file( $this->dir . '/.htaccess' ) ) {
			file_put_contents( $this->dir . '/.htaccess', "Require all denied\n" );
		}

		return true;
	}

	/**
	 * Entry files.
	 *
	 * @return array<string>
	 */
	private function entries(): array {
		if ( ! is_dir( $this->dir ) ) {
			return [];
		}

		$files = glob( $this->dir . '/*.json' );

		return $files ? $files : [];
	}

	/**
	 * Entry path.
	 *
	 * @param string $key Key.
	 * @return string
	 * @throws \InvalidArgumentException When the key is not 40 hex characters.
	 */
	private function path( string $key ): string {
		if ( ! preg_match( '/^[a-f0-9]{40}$/D', $key ) ) {
			throw new \InvalidArgumentException( 'Invalid cache key.' );
		}

		return $this->dir . '/' . $key . '.json';
	}
}
