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
 * Top-level menu: Builder (landing, edit_posts), Settings and the Test page (manage_options).
 */
final class Menu {

	public const SLUG = 'sprint-illustrations';

	public const SETTINGS_SLUG = 'sprint-illustrations-settings';

	public const TEST_SLUG = 'sprint-illustrations-test';

	/**
	 * Builder page.
	 *
	 * @var BuilderPage
	 */
	private BuilderPage $builder;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * Builder page hook suffix.
	 *
	 * @var string
	 */
	private string $builder_hook = '';

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
		$this->builder  = new BuilderPage( $plugin );
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
	 * Add menu pages: Builder (landing), Settings and Test page.
	 */
	public function add_pages(): void {
		$this->builder_hook = (string) add_menu_page(
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			BuilderPage::CAPABILITY,
			self::SLUG,
			[ $this->builder, 'render' ],
			'dashicons-art',
			58
		);

		add_submenu_page( self::SLUG, __( 'Sprint Illustrations Builder', 'sprint-illustrations' ), __( 'Builder', 'sprint-illustrations' ), BuilderPage::CAPABILITY, self::SLUG, [ $this->builder, 'render' ] );

		$this->settings_hook = (string) add_submenu_page(
			self::SLUG,
			__( 'Sprint Illustrations Settings', 'sprint-illustrations' ),
			__( 'Settings', 'sprint-illustrations' ),
			SettingsPage::CAPABILITY,
			self::SETTINGS_SLUG,
			[ $this->settings, 'render' ]
		);

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
		if ( $hook_suffix === $this->builder_hook ) {
			$this->builder->enqueue();
			return;
		}

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
