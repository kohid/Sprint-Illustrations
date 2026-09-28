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
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor' ] );
	}

	/**
	 * Elementor callback.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 */
	public function register_widget( $widgets_manager ): void {
		$widgets_manager->register( new Widget() );
	}

	/**
	 * Editor script for the widget's Suggest button (wp-api-fetch brings the REST nonce).
	 */
	public function enqueue_editor(): void {
		wp_enqueue_script( 'sprint-illustrations-elementor', plugins_url( 'assets/elementor/editor.js', SPRINT_ILLUSTRATIONS_FILE ), [ 'jquery', 'wp-api-fetch', 'wp-i18n' ], SPRINT_ILLUSTRATIONS_VERSION, true );
	}
}
