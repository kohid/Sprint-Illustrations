<?php
/**
 * Composer decorator backed by SvgCache.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cache;

use SprintIllustrations\Compose\ComposedSvg;
use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\SanitizationException;
use SprintIllustrations\Security\Sanitizer;

/**
 * Serves repeats from disk. Only warning-free results are stored; every hit is re-sanitized.
 */
final class CachingComposer implements ComposesSvg {

	/**
	 * Constructor.
	 *
	 * @param ComposesSvg $inner            Real composer.
	 * @param SvgCache    $cache            Cache.
	 * @param Sanitizer   $sanitizer        Sanitizer (guards against tampered files).
	 * @param string      $manifest_version Manifest::version().
	 * @param string      $plugin_version   Plugin version.
	 */
	public function __construct(
		private ComposesSvg $inner,
		private SvgCache $cache,
		private Sanitizer $sanitizer,
		private string $manifest_version,
		private string $plugin_version,
	) {}

	/**
	 * Compose, using the cache when possible.
	 *
	 * @param SceneSpec $spec    Scene request.
	 * @param Palette   $palette Resolved palette.
	 * @return ComposedSvg
	 */
	public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg {
		$key = CacheKey::make( $spec, $palette, $this->manifest_version, $this->plugin_version );
		$hit = $this->cache->get( $key );

		if ( null !== $hit ) {
			try {
				return new ComposedSvg( $this->sanitizer->sanitize( $hit->markup ), $hit->spec, $hit->warnings );
			} catch ( SanitizationException ) {
				// Unreadable entry: fall through, recompose and overwrite it.
			}
		}

		$result = $this->inner->compose( $spec, $palette );

		if ( [] === $result->warnings ) {
			$this->cache->put( $key, $result );
		}

		return $result;
	}
}
