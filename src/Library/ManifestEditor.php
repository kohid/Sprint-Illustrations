<?php
/**
 * Edits to a decoded manifest.json.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Removing a piece bumps the version, which invalidates cached compositions that used it.
 */
final class ManifestEditor {

	/**
	 * Remove a piece entry.
	 *
	 * @param array<string, mixed> $manifest Decoded manifest {version, pieces}.
	 * @param string               $id       Piece ID.
	 * @return array{manifest: array<string, mixed>, file: string|null} The new manifest and the removed entry's file (relative), or null when not found.
	 */
	public static function remove( array $manifest, string $id ): array {
		$pieces = is_array( $manifest['pieces'] ?? null ) ? $manifest['pieces'] : [];
		$file   = null;
		$kept   = [];

		foreach ( $pieces as $entry ) {
			if ( null === $file && is_array( $entry ) && $id === ( $entry['id'] ?? null ) ) {
				$file = (string) ( $entry['file'] ?? '' );
				continue;
			}
			$kept[] = $entry;
		}

		if ( null === $file ) {
			return [
				'manifest' => $manifest,
				'file'     => null,
			];
		}

		$manifest['pieces']  = $kept;
		$manifest['version'] = (int) ( $manifest['version'] ?? 0 ) + 1;

		return [
			'manifest' => $manifest,
			'file'     => $file,
		];
	}
}
