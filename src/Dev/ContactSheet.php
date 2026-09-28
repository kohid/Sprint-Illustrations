<?php
/**
 * Renders a grid of templates × palettes × seeds for visual review.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Dev;

use SprintIllustrations\Compose\ComposesSvg;
use SprintIllustrations\Compose\CompositionException;
use SprintIllustrations\Compose\SceneSpec;
use SprintIllustrations\Palette\Palette;
use SprintIllustrations\Palette\PresetRepository;

/**
 * Used by the admin Test page and bin/contact-sheet.php.
 */
final class ContactSheet {

	/**
	 * Constructor.
	 *
	 * @param ComposesSvg $composer Composer (plain or caching).
	 */
	public function __construct( private ComposesSvg $composer ) {}

	/**
	 * Presets used for review: the default plus two contrasting ones.
	 *
	 * @return array<string, Palette>
	 */
	public static function review_palettes(): array {
		$presets  = PresetRepository::bundled()->all();
		$palettes = [];

		foreach ( [ 'sprint', 'forest', 'night' ] as $id ) {
			$palettes[ $presets[ $id ]['label'] ] = $presets[ $id ]['palette'];
		}

		return $palettes;
	}

	/**
	 * Grid HTML. SVG markup is sanitized by the composer; all text is escaped here.
	 *
	 * @param array<string>          $template_ids Templates.
	 * @param array<int>             $seeds        Seeds.
	 * @param array<string, Palette> $palettes     Label => palette.
	 * @param array<string>          $keywords     Keywords applied to every cell.
	 * @return string
	 */
	public function render( array $template_ids, array $seeds, array $palettes, array $keywords = [] ): string {
		$html     = '';
		$instance = 0;

		foreach ( $template_ids as $template_id ) {
			$html .= '<section class="si-sheet"><h2>' . self::esc( $template_id ) . '</h2>';

			foreach ( $palettes as $label => $palette ) {
				$html .= '<h3>' . self::esc( (string) $label ) . '</h3><div class="si-sheet__grid">';

				foreach ( $seeds as $seed ) {
					$spec = SceneSpec::from_array(
						[
							'template' => $template_id,
							'seed'     => $seed,
							'keywords' => $keywords,
						]
					);

					try {
						$result  = $this->composer->compose( $spec, $palette );
						$figure  = $result->with_instance_id( 'si-sheet-' . ( ++$instance ) );
						$caption = 'seed ' . $seed . ' · ' . implode( ', ', array_map( static fn( $ids ) => implode( '+', (array) $ids ), $result->spec->picks ) );
						$notes   = $result->warnings;
					} catch ( CompositionException $e ) {
						$figure  = '<p class="si-sheet__error">' . self::esc( $e->getMessage() ) . '</p>';
						$caption = 'seed ' . $seed;
						$notes   = [];
					}

					$html .= '<figure>' . $figure . '<figcaption>' . self::esc( $caption ) . '</figcaption>';
					foreach ( $notes as $note ) {
						$html .= '<p class="si-sheet__warning">' . self::esc( $note ) . '</p>';
					}
					$html .= '</figure>';
				}//end foreach

				$html .= '</div>';
			}//end foreach

			$html .= '</section>';
		}//end foreach

		return $html;
	}

	/**
	 * Stylesheet for the grid.
	 *
	 * @return string
	 */
	public static function styles(): string {
		return '.si-sheet{margin:0 0 48px}.si-sheet h2{font:600 18px/1.3 system-ui,sans-serif;margin:24px 0 8px}'
			. '.si-sheet h3{font:500 13px/1.3 system-ui,sans-serif;color:#646970;margin:16px 0 8px;text-transform:uppercase;letter-spacing:.06em}'
			. '.si-sheet__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}'
			. '.si-sheet figure{margin:0;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px}'
			. '.si-sheet svg{display:block;width:100%;height:auto}'
			. '.si-sheet figcaption{font:12px/1.4 ui-monospace,monospace;color:#50575e;margin-top:8px;word-break:break-word}'
			. '.si-sheet__warning{font:12px/1.4 system-ui;color:#996800;margin:4px 0 0}.si-sheet__error{color:#d63638}';
	}

	/**
	 * Standalone HTML document (bin/contact-sheet.php).
	 *
	 * @param string $body Grid HTML.
	 * @return string
	 */
	public static function document( string $body ): string {
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Sprint Illustrations contact sheet</title>'
			. '<style>body{margin:24px;background:#f6f7f7}' . self::styles() . '</style></head><body>' . $body . '</body></html>';
	}

	/**
	 * Escape text for HTML (framework-free).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
