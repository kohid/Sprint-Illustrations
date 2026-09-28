<?php
/**
 * Admin menu registration.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Plugin;

/**
 * Top-level menu: Settings (landing page) and the Test page.
 */
final class Menu {

	public const SLUG = 'sprint-illustrations';

	public const TEST_SLUG = 'sprint-illustrations-test';

	/**
	 * Settings page.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private string $settings_hook = '';

	/**
	 * Test page hook suffix.
	 *
	 * @var string
	 */
	private string $test_hook = '';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {
		$this->settings = new SettingsPage( $plugin );
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		$this->settings->register();
		add_action( 'admin_menu', [ $this, 'add_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Add menu pages: Settings (landing) and Test page.
	 */
	public function add_pages(): void {
		$this->settings_hook = (string) add_menu_page(
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			SettingsPage::CAPABILITY,
			self::SLUG,
			[ $this->settings, 'render' ],
			'dashicons-art',
			58
		);

		add_submenu_page( self::SLUG, __( 'Sprint Illustrations Settings', 'sprint-illustrations' ), __( 'Settings', 'sprint-illustrations' ), SettingsPage::CAPABILITY, self::SLUG, [ $this->settings, 'render' ] );

		$this->test_hook = (string) add_submenu_page(
			self::SLUG,
			__( 'Sprint Illustrations Test page', 'sprint-illustrations' ),
			__( 'Test page', 'sprint-illustrations' ),
			TestPage::CAPABILITY,
			self::TEST_SLUG,
			[ new TestPage( $this->plugin ), 'render' ]
		);
	}

	/**
	 * Enqueue assets for our pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix === $this->settings_hook ) {
			$this->settings->enqueue();
			return;
		}

		if ( $hook_suffix === $this->test_hook ) {
			wp_register_style( 'sprint-illustrations-test', false, [], SPRINT_ILLUSTRATIONS_VERSION );
			wp_enqueue_style( 'sprint-illustrations-test' );
			wp_add_inline_style( 'sprint-illustrations-test', ContactSheet::styles() );
		}
	}
}
