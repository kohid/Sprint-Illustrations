<?php
/**
 * [sprint_illustration] shortcode.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations;

use SprintIllustrations\Plugin;

/**
 * [sprint_illustration template="hero-left-character" keywords="team,remote" seed="42" palette="preset:ocean"].
 */
final class Shortcode {

	public const TAG = 'sprint_illustration';

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
				'id'         => '0',
				'template'   => '',
				'keywords'   => '',
				'seed'       => '1',
				'palette'    => 'site',
				'title'      => '',
				'decorative' => 'false',
			],
			is_array( $atts ) ? $atts : [],
			self::TAG
		);

		wp_enqueue_style( Plugin::STYLE_HANDLE );

		return $this->plugin->renderer()->render( $atts, current_user_can( 'edit_posts' ) );
	}
}
