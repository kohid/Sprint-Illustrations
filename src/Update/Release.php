<?php
/**
 * A published plugin release, read from a GitHub "latest release" API response.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Update;

/**
 * Pure value object: no WordPress calls.
 */
final class Release {

	/**
	 * Constructor.
	 *
	 * @param string $version Version without the leading "v" (e.g. 0.8.0).
	 * @param string $package Download URL of the plugin zip.
	 * @param string $url     Release page.
	 * @param string $notes   Release notes (plain text).
	 */
	private function __construct(
		public readonly string $version,
		public readonly string $package,
		public readonly string $url,
		public readonly string $notes
	) {}

	/**
	 * Read a release, or null when it isn't a usable one (draft, pre-release, odd tag, no zip from this repo).
	 *
	 * @param array<string,mixed> $data       Decoded GitHub API response.
	 * @param string              $repo       "owner/name".
	 * @param string              $asset_name File name of the release zip.
	 * @return self|null
	 */
	public static function from_github( array $data, string $repo, string $asset_name ): ?self {
		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		if ( 1 !== preg_match( '/^v?(\d+\.\d+\.\d+)$/', (string) ( $data['tag_name'] ?? '' ), $match ) ) {
			return null;
		}

		$repo_url = 'https://github.com/' . $repo . '/';
		$prefix   = $repo_url . 'releases/download/';
		foreach ( (array) ( $data['assets'] ?? [] ) as $asset ) {
			if ( ! is_array( $asset ) || ( $asset['name'] ?? '' ) !== $asset_name ) {
				continue;
			}
			$package = (string) ( $asset['browser_download_url'] ?? '' );
			if ( ! str_starts_with( $package, $prefix ) ) {
				continue;
			}
			$url = (string) ( $data['html_url'] ?? '' );

			return new self(
				$match[1],
				$package,
				str_starts_with( $url, $repo_url ) ? $url : rtrim( $repo_url, '/' ),
				(string) ( $data['body'] ?? '' )
			);
		}

		return null;
	}

	/**
	 * Whether this release is newer than an installed version.
	 *
	 * @param string $installed Installed version.
	 * @return bool
	 */
	public function is_newer_than( string $installed ): bool {
		return version_compare( $this->version, $installed, '>' );
	}
}
