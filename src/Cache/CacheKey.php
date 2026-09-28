<?php
/**
 * Cache keys for composed SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cache;

use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;

/**
 * Any change to the spec, palette, library or plugin produces a new key.
 */
final class CacheKey {

	/**
	 * Build a key.
	 *
	 * @param SceneSpec $spec             Spec.
	 * @param Palette   $palette          Resolved palette.
	 * @param string    $manifest_version Manifest::version().
	 * @param string    $plugin_version   Plugin version.
	 * @return string 40 hex characters.
	 */
	public static function make( SceneSpec $spec, Palette $palette, string $manifest_version, string $plugin_version ): string {
		return sha1(
			(string) json_encode(
				[
					'spec'     => self::sorted( $spec->to_array() ),
					'palette'  => $palette->hash(),
					'manifest' => $manifest_version,
					'plugin'   => $plugin_version,
				]
			)
		);
	}

	/**
	 * Sort map keys recursively (lists keep their order).
	 *
	 * @param array<mixed> $data Data.
	 * @return array<mixed>
	 */
	private static function sorted( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::sorted( $value );
			}
		}

		if ( ! array_is_list( $data ) ) {
			ksort( $data );
		}

		return $data;
	}
}
