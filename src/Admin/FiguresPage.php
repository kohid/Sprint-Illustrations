<?php
/**
 * Cartoon figures page: mount point, assets and boot data.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Plugin;

/**
 * A separate page (Sprint Illustrations → Figures) for building a full-body cartoon figure. The React app
 * lives in src-js/figures/ and builds to build/figures.js.
 */
final class FiguresPage {

	public const CAPABILITY = 'edit_posts';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Path to the generated asset manifest.
	 *
	 * @return string
	 */
	private function asset_file(): string {
		return $this->plugin->dir() . '/build/figures.asset.php';
	}

	/**
	 * Enqueue the app.
	 */
	public function enqueue(): void {
		if ( ! is_readable( $this->asset_file() ) ) {
			return;
		}

		$asset = require $this->asset_file();

		wp_enqueue_script( 'sprint-illustrations-figures', plugins_url( 'build/figures.js', SPRINT_ILLUSTRATIONS_FILE ), (array) ( $asset['dependencies'] ?? [] ), (string) ( $asset['version'] ?? SPRINT_ILLUSTRATIONS_VERSION ), true );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'sprint-illustrations-figures', plugins_url( 'assets/admin/figures.css', SPRINT_ILLUSTRATIONS_FILE ), [ 'wp-components' ], SPRINT_ILLUSTRATIONS_VERSION );

		wp_localize_script(
			'sprint-illustrations-figures',
			'sprintIllustrationsFigures',
			[
				'builderUrl'    => admin_url( 'admin.php?page=' . Menu::SLUG ),
				'libraryUrl'    => admin_url( 'admin.php?page=' . Menu::LIBRARY_SLUG . '&tab=characters' ),
				'charactersUrl' => admin_url( 'admin.php?page=' . Menu::CHARACTERS_SLUG ),
			]
		);
	}

	/**
	 * Render the mount point.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sprint-illustrations' ) );
		}

		echo '<div class="wrap si-figures-wrap">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'Cartoon figures', 'sprint-illustrations' ) . '</h1>';

		if ( ! is_readable( $this->asset_file() ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The Cartoon figures hasn\'t been built. Run npm install and npm run build in the plugin folder.', 'sprint-illustrations' ) . '</p></div></div>';
			return;
		}

		echo '<div id="si-figures"></div>';
		echo '<noscript><div class="notice notice-warning"><p>' . esc_html__( 'The Cartoon figures needs JavaScript.', 'sprint-illustrations' ) . '</p></div></noscript>';
		echo '</div>';
	}
}
