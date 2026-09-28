<?php
/**
 * Keeps the site palette in step with Elementor.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

use SprintIllustrations\Admin\Notices;
use SprintIllustrations\Palette\ElementorMapping;
use SprintIllustrations\Settings\SitePalette;

/**
 * Re-applies the stored mapping when the Kit or its Variables change.
 */
final class Sync {

	/**
	 * Constructor.
	 *
	 * @param SitePalette $site   Site palette.
	 * @param ColorSource $source Elementor colours.
	 */
	public function __construct( private SitePalette $site, private ColorSource $source ) {}

	/**
	 * Hook in only when the palette follows Elementor with "keep synced" on.
	 */
	public function register(): void {
		$settings = $this->site->settings();

		if ( 'elementor' !== $settings['source'] || ! $settings['elementor']['sync'] || ! ColorSource::available() ) {
			return;
		}

		add_action( 'elementor/document/after_save', [ $this, 'on_document_saved' ] );
		add_action( 'updated_post_meta', [ $this, 'on_meta_changed' ], 10, 3 );
		add_action( 'added_post_meta', [ $this, 'on_meta_changed' ], 10, 3 );
	}

	/**
	 * Kit saved in Elementor's Site Settings.
	 *
	 * @param object $document Elementor document.
	 */
	public function on_document_saved( $document ): void {
		if ( is_object( $document ) && method_exists( $document, 'get_id' ) && (int) $document->get_id() === (int) get_option( 'elementor_active_kit' ) ) {
			$this->resync();
		}
	}

	/**
	 * V4 Variables changed (they fire no Elementor action, so watch the meta).
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 */
	public function on_meta_changed( $meta_id, $object_id, $meta_key ): void {
		if ( '_elementor_global_variables' === $meta_key ) {
			$this->resync();
		}
	}

	/**
	 * Re-read Elementor and update the option when colours changed.
	 */
	public function resync(): void {
		$settings = $this->site->settings();
		$items    = $this->source->items();

		if ( ! $items ) {
			if ( $this->source->failed() ) {
				Notices::add( __( 'Elementor colours couldn\'t be read, so the palette was not updated.', 'sprint-illustrations' ) );
			}
			return;
		}

		$result = ( new ElementorMapping( $items ) )->apply( $settings['elementor']['map'], $settings['colors'] );

		if ( $result['missing'] ) {
			/* translators: %s: comma-separated Elementor colour IDs. */
			Notices::add( sprintf( __( 'Some mapped Elementor colours no longer exist: %s', 'sprint-illustrations' ), implode( ', ', $result['missing'] ) ) );
		}

		if ( $result['colors'] !== $settings['colors'] ) {
			$settings['colors'] = $result['colors'];
			$this->site->save( $settings );
		}
	}
}
