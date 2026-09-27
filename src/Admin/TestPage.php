<?php
/**
 * Admin Test page: contact sheet of templates × seeds × palettes.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Library\LibraryException;
use SprintIllustrations\Plugin;

/**
 * Read-only preview screen (GET filters only; no state changes, so no nonce).
 */
final class TestPage {

	public const CAPABILITY = 'manage_options';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sprint-illustrations' ) );
		}

		try {
			$services = $this->plugin->services();
		} catch ( LibraryException $e ) {
			printf( '<div class="wrap"><h1>%s</h1><div class="notice notice-error"><p>%s</p></div></div>', esc_html__( 'Sprint Illustrations', 'sprint-illustrations' ), esc_html( $e->getMessage() ) );
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view filters.
		$template   = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		$first_seed = isset( $_GET['seed'] ) ? absint( $_GET['seed'] ) : 1;
		$count      = isset( $_GET['count'] ) ? min( 12, max( 1, absint( $_GET['count'] ) ) ) : 4;
		$keywords   = isset( $_GET['keywords'] ) ? sanitize_text_field( wp_unslash( $_GET['keywords'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$all_ids   = $services->templates->ids();
		$templates = in_array( $template, $all_ids, true ) ? [ $template ] : $all_ids;
		$seeds     = range( $first_seed, $first_seed + $count - 1 );
		$sheet     = new ContactSheet( $services->composer );

		echo '<div class="wrap"><h1>' . esc_html__( 'Sprint Illustrations — Test page', 'sprint-illustrations' ) . '</h1>';

		$this->render_filters( $all_ids, $template, $first_seed, $count, $keywords );

		foreach ( $services->manifest->errors() as $error ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $error ) );
		}

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: piece count, 2: template count, 3: manifest version. */
					__( '%1$d pieces, %2$d templates, manifest version %3$s.', 'sprint-illustrations' ),
					count( $services->manifest->all() ),
					count( $all_ids ),
					$services->manifest->version()
				)
			)
		);

		// SVG markup is sanitized by the Composer's allowlist sanitizer; all text is escaped by ContactSheet.
		echo $sheet->render( $templates, $seeds, ContactSheet::review_palettes(), '' === $keywords ? [] : explode( ',', $keywords ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '</div>';
	}

	/**
	 * Filter form.
	 *
	 * @param array<string> $all_ids    Template IDs.
	 * @param string        $template   Selected template.
	 * @param int           $first_seed First seed.
	 * @param int           $count      Seed count.
	 * @param string        $keywords   Keywords.
	 */
	private function render_filters( array $all_ids, string $template, int $first_seed, int $count, string $keywords ): void {
		?>
		<form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:16px 0">
			<input type="hidden" name="page" value="<?php echo esc_attr( Menu::SLUG ); ?>">
			<label><?php esc_html_e( 'Template', 'sprint-illustrations' ); ?><br>
				<select name="template">
					<option value=""><?php esc_html_e( 'All templates', 'sprint-illustrations' ); ?></option>
					<?php foreach ( $all_ids as $id ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $template, $id ); ?>><?php echo esc_html( $id ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'First seed', 'sprint-illustrations' ); ?><br>
				<input type="number" name="seed" min="0" value="<?php echo esc_attr( (string) $first_seed ); ?>" class="small-text">
			</label>
			<label><?php esc_html_e( 'Seeds', 'sprint-illustrations' ); ?><br>
				<input type="number" name="count" min="1" max="12" value="<?php echo esc_attr( (string) $count ); ?>" class="small-text">
			</label>
			<label><?php esc_html_e( 'Keywords', 'sprint-illustrations' ); ?><br>
				<input type="text" name="keywords" value="<?php echo esc_attr( $keywords ); ?>" placeholder="team, coffee">
			</label>
			<?php submit_button( __( 'Render', 'sprint-illustrations' ), 'secondary', '', false ); ?>
		</form>
		<?php
	}
}
