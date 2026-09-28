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
	 */
	public function __construct(
		private ComposesSvg $composer,
		private \Closure $palettes,
		private ?\Closure $log = null,
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
		$spec = self::spec( $args );

		try {
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
	 * Escape for HTML (framework-free).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
