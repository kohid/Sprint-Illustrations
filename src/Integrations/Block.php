<?php
/**
 * The sprint-illustrations/illustration block.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations;

use SprintIllustrations\Plugin;

/**
 * Registers the block from blocks/illustration/block.json and feeds its editor the choices.
 */
final class Block {

	public const NAME = 'sprint-illustrations/illustration';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_type' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_data' ] );
	}

	/**
	 * Register the block type (after the stylesheet on init priority 5).
	 */
	public function register_type(): void {
		register_block_type( $this->plugin->dir() . '/blocks/illustration' );
	}

	/**
	 * Template and palette choices for the editor script.
	 */
	public function editor_data(): void {
		wp_add_inline_script(
			generate_block_asset_handle( self::NAME, 'editorScript' ),
			'window.sprintIllustrationsBlock = ' . wp_json_encode( $this->plugin->editor_choices() ) . ';',
			'before'
		);
	}
}
