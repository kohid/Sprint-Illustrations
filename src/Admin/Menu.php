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
 * Top-level "Sprint Illustrations" menu. Phase 1 contains only the Test page.
 */
final class Menu {

	public const SLUG = 'sprint-illustrations';

	/**
	 * Test page hook suffix.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Add menu pages.
	 */
	public function add_pages(): void {
		$page = new TestPage( $this->plugin );

		$this->hook = (string) add_menu_page(
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			__( 'Sprint Illustrations', 'sprint-illustrations' ),
			TestPage::CAPABILITY,
			self::SLUG,
			[ $page, 'render' ],
			'dashicons-art',
			58
		);
	}

	/**
	 * Enqueue Test page styles.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}

		wp_register_style( 'sprint-illustrations-test', false, [], SPRINT_ILLUSTRATIONS_VERSION );
		wp_enqueue_style( 'sprint-illustrations-test' );
		wp_add_inline_style( 'sprint-illustrations-test', ContactSheet::styles() );
	}
}
