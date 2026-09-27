<?php
/**
 * Tag-scoring template selection (no API key needed).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Compose\Seed;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Library\Template;
use SprintIllustrations\Library\TemplateRepository;

/**
 * Template score = 3 × template tag hits + 1 per slot whose category has a piece matching a token.
 * Ties are broken by the seed. Piece scoring happens in SceneResolver.
 */
final class RulesSelector implements Selector {

	/**
	 * Constructor.
	 *
	 * @param TemplateRepository $templates Templates.
	 * @param Manifest           $manifest  Pieces.
	 * @param Keywords           $keywords  Tokenizer.
	 */
	public function __construct(
		private TemplateRepository $templates,
		private Manifest $manifest,
		private Keywords $keywords,
	) {}

	/**
	 * Suggest a spec for content.
	 *
	 * @param string $content Content.
	 * @param int    $seed    Seed.
	 * @return SceneSpec
	 */
	public function suggest( string $content, int $seed ): SceneSpec {
		$tokens = $this->keywords->tokenize( $content );

		return SceneSpec::from_array(
			[
				'template' => $this->choose_template( $this->keywords->expand( $tokens ), $seed )->id,
				'seed'     => $seed,
				'keywords' => $tokens,
			]
		);
	}

	/**
	 * Best-scoring template for tokens.
	 *
	 * @param array<string> $tokens Expanded tokens.
	 * @param int           $seed   Seed.
	 * @return Template
	 * @throws CompositionException When no templates exist.
	 */
	public function choose_template( array $tokens, int $seed ): Template {
		$best       = [];
		$best_score = -1;

		foreach ( $this->templates->all() as $template ) {
			$score = 3 * count( array_intersect( $template->tags, $tokens ) );

			foreach ( $template->slots as $slot ) {
				foreach ( $this->manifest->by_category( $slot->category ) as $piece ) {
					if ( array_intersect( $piece->tags, $tokens ) ) {
						++$score;
						break;
					}
				}
			}

			if ( $score > $best_score ) {
				$best       = [ $template ];
				$best_score = $score;
			} elseif ( $score === $best_score ) {
				$best[] = $template;
			}
		}

		if ( ! $best ) {
			throw new CompositionException( 'No templates are available.' );
		}

		return Seed::from_string( $seed . '|template' )->pick( $best );
	}
}
