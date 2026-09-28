<?php
/**
 * Registers the Elementor widget.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

/**
 * Hooks the classic widget into Elementor (3.x and 4.x).
 */
final class Loader {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
	}

	/**
	 * Elementor callback.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 */
	public function register_widget( $widgets_manager ): void {
		$widgets_manager->register( new Widget() );
	}
}
