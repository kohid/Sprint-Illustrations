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

		$markup = null === $hit ? null : $this->resanitize( $hit->markup );

		if ( null !== $hit && null !== $markup ) {
			return new ComposedSvg( $markup, $hit->spec, $hit->warnings, $hit->boxes );
		}

		$result = $this->inner->compose( $spec, $palette );

		if ( [] === $result->warnings ) {
			$this->cache->put( $key, $result );
		}

		return $result;
	}

	/**
	 * Sanitize cached markup again (files on disk may have been tampered with).
	 *
	 * @param string $markup Cached markup.
	 * @return string|null Null when unreadable, so the entry is recomposed and overwritten.
	 */
	private function resanitize( string $markup ): ?string {
		try {
			return $this->sanitizer->sanitize( $markup );
		} catch ( SanitizationException ) {
			return null;
		}
	}
}
