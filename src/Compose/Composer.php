<?php
/**
 * Composes a scene into one sanitized, accessible SVG.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

use SprintIllustrations\Library\TemplateRepository;
use SprintIllustrations\Library\Manifest;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Security\SanitizationException;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Selection\Keywords;
use SprintIllustrations\Selection\RulesSelector;
use SprintIllustrations\Svg\SvgDom;

/**
 * Pipeline: select template → resolve slots → import pieces → recolour → scope IDs → wrap → sanitize.
 */
final class Composer {

	/**
	 * Recolorer.
	 *
	 * @var Recolorer
	 */
	private Recolorer $recolorer;

	/**
	 * ID scoper.
	 *
	 * @var IdScoper
	 */
	private IdScoper $scoper;

	/**
	 * Constructor.
	 *
	 * @param Manifest           $manifest  Piece library.
	 * @param TemplateRepository $templates Templates.
	 * @param PieceLoader        $loader    Piece loader.
	 * @param Sanitizer          $sanitizer Sanitizer.
	 * @param RulesSelector      $selector  Template selector for specs without a template.
	 * @param Keywords           $keywords  Keyword tokenizer.
	 */
	public function __construct(
		private Manifest $manifest,
		private TemplateRepository $templates,
		private PieceLoader $loader,
		private Sanitizer $sanitizer,
		private RulesSelector $selector,
		private Keywords $keywords,
	) {
		$this->recolorer = new Recolorer();
		$this->scoper    = new IdScoper();
	}

	/**
	 * Compose a scene.
	 *
	 * @param SceneSpec $spec    Scene request.
	 * @param Palette   $palette Resolved palette.
	 * @return ComposedSvg
	 * @throws CompositionException When the scene cannot be composed.
	 */
	public function compose( SceneSpec $spec, Palette $palette ): ComposedSvg {
		$tokens      = $this->keywords->expand( $this->keywords->tokenize( implode( ' ', $spec->keywords ) ) );
		$template_id = $spec->template ?? $this->selector->choose_template( $tokens, $spec->seed )->id;
		$template    = $this->templates->get( $template_id );

		if ( null === $template ) {
			throw new CompositionException( sprintf( 'Unknown template "%s".', $template_id ) );
		}

		$scene = ( new SceneResolver( $this->manifest ) )->resolve( $template, $spec, $tokens );

		return new ComposedSvg(
			$this->render( $scene, $spec, $palette ),
			$spec->with_template( $template->id )->with_picks( $scene->picks ),
			$scene->warnings
		);
	}

	/**
	 * Render placements into markup.
	 *
	 * @param ResolvedScene $scene   Scene.
	 * @param SceneSpec     $spec    Spec.
	 * @param Palette       $palette Palette.
	 * @return string
	 * @throws CompositionException When sanitization of the result fails.
	 */
	private function render( ResolvedScene $scene, SceneSpec $spec, Palette $palette ): string {
		$token = ComposedSvg::ID_TOKEN;
		$doc   = new \DOMDocument( '1.0', 'UTF-8' );
		$svg   = $doc->createElementNS( SvgDom::NS, 'svg' );
		$doc->appendChild( $svg );

		$svg->setAttribute( 'viewBox', sprintf( '0 0 %s %s', SvgDom::num( $scene->template->canvas[0] ), SvgDom::num( $scene->template->canvas[1] ) ) );

		$labels = [];
		foreach ( $scene->placements as $index => $placement ) {
			$group = $doc->createElementNS( SvgDom::NS, 'g' );
			$group->setAttribute( 'transform', $placement->transform() );

			foreach ( $this->loader->load( $placement->piece )->documentElement->childNodes as $child ) {
				if ( $child instanceof \DOMElement && ! in_array( $child->localName, [ 'title', 'desc', 'metadata' ], true ) ) {
					$group->appendChild( $doc->importNode( $child, true ) );
				}
			}

			$this->recolorer->apply( $group, $palette, $placement->skin_index, $placement->hair_index );
			$this->scoper->scope( $group, $token . '-p' . $index . '-' );
			$svg->appendChild( $group );

			if ( 'decor' !== $placement->piece->category && 'backgrounds' !== $placement->piece->category ) {
				$labels[ $placement->piece->label ] = true;
			}
		}

		if ( $spec->decorative ) {
			$svg->setAttribute( 'aria-hidden', 'true' );
			$svg->setAttribute( 'focusable', 'false' );
		} else {
			$title = $doc->createElementNS( SvgDom::NS, 'title' );
			$title->setAttribute( 'id', $token . '-t' );
			$title->appendChild( $doc->createTextNode( $spec->title ?? $scene->template->label ) );

			$desc = $doc->createElementNS( SvgDom::NS, 'desc' );
			$desc->setAttribute( 'id', $token . '-d' );
			$desc->appendChild( $doc->createTextNode( $labels ? 'Illustration showing ' . implode( ', ', array_keys( $labels ) ) . '.' : 'Decorative illustration.' ) );

			$svg->insertBefore( $desc, $svg->firstChild );
			$svg->insertBefore( $title, $desc );
			$svg->setAttribute( 'role', 'img' );
			$svg->setAttribute( 'aria-labelledby', $token . '-t ' . $token . '-d' );
		}

		try {
			return $this->sanitizer->sanitize( (string) $doc->saveXML( $svg ) );
		} catch ( SanitizationException $e ) {
			throw new CompositionException( 'Composed SVG failed sanitization.', 0, $e );
		}
	}
}
