<?php
/**
 * [sprint_figure_builder] shortcode: the cartoon figure studio on any page.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations;

use SprintIllustrations\Admin\FiguresPage;
use SprintIllustrations\Plugin;

/**
 * Puts the Figures studio on a front-end page. By default it fills the whole window (above the theme's header,
 * footer and sidebars), so the page looks like an application and not like a post; fullscreen="false" keeps it
 * inside the page at a fixed height. Visitors can design, download (SVG or PNG) and share a link; saving to the
 * library is only offered to people who can edit posts.
 */
final class FigureStudio {

	public const TAG = 'sprint_figure_builder';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
		add_filter( 'body_class', [ $this, 'body_class' ] );
	}

	/**
	 * Mark pages that carry the shortcode, so the stylesheet can quiet the theme around a full-window studio.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public function body_class( array $classes ): array {
		$post = is_singular() ? get_post() : null;
		if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, self::TAG ) ) {
			$classes[] = 'si-figures-page';
		}

		return $classes;
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, string>|string $atts Attributes ("" when none).
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'fullscreen' => 'true',
				'height'     => '760px',
				'save'       => 'auto',
				'title'      => '',
			],
			is_array( $atts ) ? $atts : [],
			self::TAG
		);

		$fullscreen = ! in_array( strtolower( (string) $atts['fullscreen'] ), [ 'false', '0', 'no', 'off' ], true );
		$height     = preg_match( '/^\d{3,4}(px|vh|rem)$/D', (string) $atts['height'] ) ? (string) $atts['height'] : '760px';
		$can_save   = 'off' !== $atts['save'] && current_user_can( 'edit_posts' );

		$page = new FiguresPage( $this->plugin );
		if ( ! is_readable( $this->plugin->dir() . '/build/figures.asset.php' ) ) {
			return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'The figure builder has not been built. Run npm run build in the plugin folder.', 'sprint-illustrations' ) . '</p>' : '';
		}

		$page->enqueue(
			[
				'front'      => true,
				'fullscreen' => $fullscreen,
				'canSave'    => $can_save,
				'title'      => sanitize_text_field( (string) $atts['title'] ),
				'restUrl'    => esc_url_raw( rest_url() ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'libraryUrl' => $can_save ? admin_url( 'admin.php?page=sprint-illustrations-library&tab=characters' ) : '',
				'shortcode'  => '',
			]
		);

		return '<div class="si-f-embed' . ( $fullscreen ? ' is-fullscreen' : '' ) . '" style="' . ( $fullscreen ? '' : 'height:' . esc_attr( $height ) ) . '">'
			. '<div id="si-figures"></div>'
			. '<noscript>' . esc_html__( 'The figure builder needs JavaScript.', 'sprint-illustrations' ) . '</noscript>'
			. '</div>';
	}
}
