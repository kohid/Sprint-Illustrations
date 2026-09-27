<?php
/**
 * Framework-free service wiring, shared by WordPress, WP-CLI, bin/ scripts and tests.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations;

use SprintIllustrations\Compose\Composer;
use SprintIllustrations\Compose\PieceLoader;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\TemplateRepository;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Selection\Keywords;
use SprintIllustrations\Selection\RulesSelector;

/**
 * Service container for the pure composition core.
 */
final class Services {

	/**
	 * Constructor.
	 *
	 * @param Manifest           $manifest  Pieces.
	 * @param TemplateRepository $templates Templates.
	 * @param Sanitizer          $sanitizer Sanitizer.
	 * @param Keywords           $keywords  Tokenizer.
	 * @param RulesSelector      $selector  Rules-based selector.
	 * @param Composer           $composer  Composer.
	 */
	public function __construct(
		public readonly Manifest $manifest,
		public readonly TemplateRepository $templates,
		public readonly Sanitizer $sanitizer,
		public readonly Keywords $keywords,
		public readonly RulesSelector $selector,
		public readonly Composer $composer,
	) {}

	/**
	 * Build services from a library root laid out as:
	 *   <root>/assets/manifest.json, <root>/assets/templates/*.json, <root>/assets/keywords/synonyms.json
	 *
	 * @param string        $root                Plugin (or fixture) root directory.
	 * @param array<string> $extra_manifests     Additional manifest.json paths (user libraries).
	 * @param array<string> $extra_template_dirs Additional template directories.
	 * @return self
	 */
	public static function create( string $root, array $extra_manifests = [], array $extra_template_dirs = [] ): self {
		$root      = rtrim( $root, '/\\' );
		$sanitizer = new Sanitizer();
		$manifest  = Manifest::from_files( array_merge( [ $root . '/assets/manifest.json' ], $extra_manifests ) );
		$templates = TemplateRepository::from_directories( array_merge( [ $root . '/assets/templates' ], $extra_template_dirs ) );
		$keywords  = Keywords::from_file( $root . '/assets/keywords/synonyms.json' );
		$selector  = new RulesSelector( $templates, $manifest, $keywords );
		$composer  = new Composer( $manifest, $templates, new PieceLoader( $sanitizer ), $sanitizer, $selector, $keywords );

		return new self( $manifest, $templates, $sanitizer, $keywords, $selector, $composer );
	}
}
