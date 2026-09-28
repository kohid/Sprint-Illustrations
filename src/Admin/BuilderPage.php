<?php
/**
 * Builder screen (React app mounted from build/builder.js).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Plugin;

/**
 * Mount point, assets and boot data for the Builder.
 */
final class BuilderPage {

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
		return $this->plugin->dir() . '/build/builder.asset.php';
	}

	/**
	 * Enqueue the app.
	 */
	public function enqueue(): void {
		if ( ! is_readable( $this->asset_file() ) ) {
			return;
		}

		$asset = require $this->asset_file();

		wp_enqueue_script( 'sprint-illustrations-builder', plugins_url( 'build/builder.js', SPRINT_ILLUSTRATIONS_FILE ), (array) ( $asset['dependencies'] ?? [] ), (string) ( $asset['version'] ?? SPRINT_ILLUSTRATIONS_VERSION ), true );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'sprint-illustrations-builder', plugins_url( 'assets/admin/builder.css', SPRINT_ILLUSTRATIONS_FILE ), [ 'wp-components' ], SPRINT_ILLUSTRATIONS_VERSION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which illustration to open.
		$initial = isset( $_GET['illustration'] ) ? absint( $_GET['illustration'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: which template to start on.
		$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';

		wp_localize_script(
			'sprint-illustrations-builder',
			'sprintIllustrationsBuilder',
			[
				'initialId'       => $initial,
				'initialTemplate' => $template,
				'aiReady'         => $this->plugin->ai()->ready(),
				'settingsUrl'     => current_user_can( SettingsPage::CAPABILITY ) ? admin_url( 'admin.php?page=' . Menu::SETTINGS_SLUG ) : '',
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

		echo '<div class="wrap si-builder-wrap">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'Sprint Illustrations Builder', 'sprint-illustrations' ) . '</h1>';

		if ( ! is_readable( $this->asset_file() ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The Builder hasn\'t been built. Run npm install and npm run build in the plugin folder.', 'sprint-illustrations' ) . '</p></div></div>';
			return;
		}

		echo '<div id="si-builder"></div>';
		echo '<noscript><div class="notice notice-warning"><p>' . esc_html__( 'The Builder needs JavaScript.', 'sprint-illustrations' ) . '</p></div></noscript>';
		echo '</div>';
	}
}
