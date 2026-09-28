<?php
/**
 * Renders an illustration for a placement surface (shortcode, block, widget).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Render;

use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;

/**
 * Normalizes surface attributes, composes, scopes IDs per copy and wraps in a figure.
 */
final class Renderer {

	/**
	 * Copies rendered in this request (keeps IDs unique on a page).
	 *
	 * @var int
	 */
	private int $count = 0;

	/**
	 * Constructor.
	 *
	 * @param ComposesSvg   $composer Composer (normally the caching one).
	 * @param \Closure      $palettes fn( string|array $ref ): Palette.
	 * @param \Closure|null $log      fn( string $message ): void, for failures.
	 * @param \Closure|null $illustrations fn( int $id ): ?SceneSpec, for saved illustrations.
	 */
	public function __construct(
		private ComposesSvg $composer,
		private \Closure $palettes,
		private ?\Closure $log = null,
		private ?\Closure $illustrations = null,
	) {}

	/**
	 * Surface attributes to a scene spec. "" and "auto" template mean automatic; "" palette means site.
	 *
	 * @param array<string, mixed> $args Attributes.
	 * @return SceneSpec
	 */
	public static function spec( array $args ): SceneSpec {
		$template = isset( $args['template'] ) && is_string( $args['template'] ) ? trim( $args['template'] ) : '';
		$palette  = isset( $args['palette'] ) && is_string( $args['palette'] ) && '' !== trim( $args['palette'] ) ? trim( $args['palette'] ) : 'site';

		return SceneSpec::from_array(
			[
				'template'   => in_array( $template, [ '', 'auto' ], true ) ? null : $template,
				'keywords'   => $args['keywords'] ?? '',
				'seed'       => $args['seed'] ?? 1,
				'palette'    => $palette,
				'title'      => $args['title'] ?? '',
				'decorative' => $args['decorative'] ?? false,
			]
		);
	}

	/**
	 * Render markup.
	 *
	 * @param array<string, mixed> $args        Attributes.
	 * @param bool                 $show_errors Show a readable notice instead of a silent comment.
	 * @return string
	 */
	public function render( array $args, bool $show_errors = false ): string {
		try {
			$spec   = $this->resolve_spec( $args );
			$result = $this->composer->compose( $spec, ( $this->palettes )( $spec->palette ) );
		} catch ( CompositionException $e ) {
			if ( null !== $this->log ) {
				( $this->log )( $e->getMessage() );
			}

			return $show_errors
				? '<div class="si-illustration-error" role="alert">' . self::esc( 'Sprint Illustrations: ' . $e->getMessage() ) . '</div>'
				: '<!-- Sprint Illustrations: illustration could not be rendered. -->';
		}

		$instance = 'si-' . substr( sha1( (string) json_encode( $result->spec->to_array() ) ), 0, 8 ) . '-' . ( ++$this->count );

		return '<figure class="si-illustration si-template-' . self::esc( (string) $result->spec->template ) . '">'
			. $result->with_instance_id( $instance )
			. '</figure>';
	}

	/**
	 * Saved illustration (when "id" is set and a lookup exists) or the attributes.
	 *
	 * @param array<string, mixed> $args Attributes.
	 * @return SceneSpec
	 * @throws CompositionException When the saved illustration is missing.
	 */
	private function resolve_spec( array $args ): SceneSpec {
		$id = isset( $args['id'] ) && is_numeric( $args['id'] ) ? (int) $args['id'] : 0;

		if ( $id <= 0 || null === $this->illustrations ) {
			return self::spec( $args );
		}

		$saved = ( $this->illustrations )( $id );
		if ( ! $saved instanceof SceneSpec ) {
			throw new CompositionException( sprintf( 'Saved illustration %d was not found.', $id ) );
		}

		$overrides = [];
		if ( isset( $args['title'] ) && is_string( $args['title'] ) && '' !== trim( $args['title'] ) ) {
			$overrides['title'] = $args['title'];
		}
		if ( array_key_exists( 'decorative', $args ) ) {
			$overrides['decorative'] = $args['decorative'];
		}

		return $overrides ? SceneSpec::from_array( $overrides + $saved->to_array() ) : $saved;
	}

	/**
	 * Escape for HTML (framework-free).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
