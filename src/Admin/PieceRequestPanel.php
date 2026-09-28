<?php
/**
 * "Request a piece" panel on the Library page.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Admin;

use SprintIllustrations\Library\PieceRequest;
use SprintIllustrations\Plugin;

/**
 * Form + queue. Claude Code fulfils queued requests through `wp sprint-illustrations requests`.
 */
final class PieceRequestPanel {

	public const CREATE_ACTION = 'sprint_illustrations_piece_request';

	public const CANCEL_ACTION = 'sprint_illustrations_piece_request_cancel';

	private const RECENT = 5;

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
		add_action( 'admin_post_' . self::CREATE_ACTION, [ $this, 'handle_create' ] );
		add_action( 'admin_post_' . self::CANCEL_ACTION, [ $this, 'handle_cancel' ] );
	}

	/**
	 * Category labels.
	 *
	 * @return array<string, string>
	 */
	private function categories(): array {
		return [
			'characters'  => __( 'Character', 'sprint-illustrations' ),
			'objects'     => __( 'Object', 'sprint-illustrations' ),
			'backgrounds' => __( 'Background', 'sprint-illustrations' ),
			'decor'       => __( 'Decor', 'sprint-illustrations' ),
		];
	}

	/**
	 * Render the panel.
	 *
	 * @param string $tab Current Library tab (preselects the category).
	 */
	public function render( string $tab ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$repo     = $this->plugin->piece_requests();
		$queued   = $repo->list( 'queued', 50 );
		$done     = $repo->list( 'done', self::RECENT );
		$declined = $repo->list( 'declined', self::RECENT );
		$selected = isset( $this->categories()[ $tab ] ) ? $tab : 'objects';

		echo '<section class="si-panel si-requests" aria-labelledby="si-requests-title">';
		echo '<div class="si-requests__form">';
		echo '<h2 class="si-panel__title" id="si-requests-title">' . esc_html__( 'Request a piece', 'sprint-illustrations' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::CREATE_ACTION ) . '">';
		wp_nonce_field( self::CREATE_ACTION );

		echo '<p class="si-requests__row"><label for="si-request-category">' . esc_html__( 'Category', 'sprint-illustrations' ) . '</label>';
		echo '<select id="si-request-category" name="category">';
		foreach ( $this->categories() as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select></p>';

		echo '<p class="si-requests__row"><label for="si-request-description">' . esc_html__( 'Describe it', 'sprint-illustrations' ) . '</label>';
		printf(
			'<textarea id="si-request-description" name="description" rows="3" maxlength="%1$d" required placeholder="%2$s"></textarea></p>',
			(int) PieceRequest::MAX_LENGTH,
			esc_attr__( 'e.g. A black Aberdeen taxi, side view', 'sprint-illustrations' )
		);

		echo '<p class="si-requests__actions"><button type="submit" class="button button-primary">' . esc_html__( 'Add request', 'sprint-illustrations' ) . '</button></p>';
		echo '<p class="si-requests__hint">' . esc_html__( 'Then ask Claude Code: “make the requested pieces”. New pieces appear here and in the Builder.', 'sprint-illustrations' ) . '</p>';
		echo '</form></div>';

		echo '<div class="si-requests__queue">';
		/* translators: %d: number of queued requests. */
		$this->render_list( sprintf( __( 'Waiting (%d)', 'sprint-illustrations' ), count( $queued ) ), $queued, 'queued', __( 'No requests waiting.', 'sprint-illustrations' ) );
		if ( $done ) {
			$this->render_list( __( 'Recently added', 'sprint-illustrations' ), $done, 'done', '' );
		}
		if ( $declined ) {
			$this->render_list( __( 'Declined', 'sprint-illustrations' ), $declined, 'declined', '' );
		}
		echo '</div></section>';
	}

	/**
	 * One list of requests.
	 *
	 * @param string                           $heading Heading.
	 * @param array<int, array<string, mixed>> $rows    Requests.
	 * @param string                           $state   State.
	 * @param string                           $none    Text when the list is empty.
	 */
	private function render_list( string $heading, array $rows, string $state, string $none ): void {
		echo '<h3 class="si-requests__heading">' . esc_html( $heading ) . '</h3>';

		if ( ! $rows ) {
			echo '<p class="si-requests__empty">' . esc_html( $none ) . '</p>';
			return;
		}

		echo '<ul class="si-requests__list si-requests__list--' . esc_attr( $state ) . '">';
		foreach ( $rows as $row ) {
			$author = get_userdata( (int) $row['author'] );
			$when   = human_time_diff( (int) strtotime( $row['date'] . ' UTC' ) );

			echo '<li class="si-request">';
			echo '<span class="si-request__category">' . esc_html( $this->categories()[ $row['category'] ] ?? $row['category'] ) . '</span>';
			echo '<span class="si-request__text">' . esc_html( $row['description'] ) . '</span>';
			echo '<span class="si-request__meta">';
			/* translators: 1: user name, 2: time ago. */
			echo esc_html( sprintf( __( 'by %1$s, %2$s ago', 'sprint-illustrations' ), $author ? $author->display_name : __( 'someone', 'sprint-illustrations' ), $when ) );

			if ( 'queued' === $state && $this->can_cancel( $row ) ) {
				printf(
					' · <a href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CANCEL_ACTION . '&request=' . (int) $row['id'] ), self::CANCEL_ACTION . '_' . (int) $row['id'] ) ),
					esc_html__( 'Cancel', 'sprint-illustrations' ),
					esc_html( $row['description'] )
				);
			}
			if ( 'done' === $state && '' !== $row['piece'] ) {
				printf(
					' · <a href="%1$s">%2$s</a>',
					esc_url(
						add_query_arg(
							[
								'page'  => Menu::LIBRARY_SLUG,
								'tab'   => $row['category'],
								'piece' => $row['piece'],
							],
							admin_url( 'admin.php' )
						) . '#piece-' . rawurlencode( $row['piece'] )
					),
					esc_html__( 'View piece', 'sprint-illustrations' )
				);
			}
			echo '</span>';

			if ( 'declined' === $state && '' !== $row['note'] ) {
				echo '<span class="si-request__note">' . esc_html( $row['note'] ) . '</span>';
			}
			echo '</li>';
		}//end foreach
		echo '</ul>';
	}

	/**
	 * Whether the current user may cancel a request.
	 *
	 * @param array<string, mixed> $row Request.
	 * @return bool
	 */
	private function can_cancel( array $row ): bool {
		return current_user_can( 'manage_options' ) || get_current_user_id() === (int) $row['author'];
	}

	/**
	 * Admin-post handler: create a request.
	 */
	public function handle_create(): void {
		check_admin_referer( self::CREATE_ACTION );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sprint-illustrations' ), 403 );
		}

		$category = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$text     = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$result   = $this->plugin->piece_requests()->create( $category, $text, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			Notices::add_for_user( get_current_user_id(), $result->get_error_message(), 'error' );
		} else {
			Notices::add_for_user( get_current_user_id(), __( 'Request added. Ask Claude Code to “make the requested pieces”.', 'sprint-illustrations' ), 'success' );
		}

		$this->back( $category );
	}

	/**
	 * Admin-post handler: cancel a queued request.
	 */
	public function handle_cancel(): void {
		$id = isset( $_GET['request'] ) ? absint( $_GET['request'] ) : 0;
		check_admin_referer( self::CANCEL_ACTION . '_' . $id );

		$repo    = $this->plugin->piece_requests();
		$request = $repo->get( $id );

		if ( null === $request || ! current_user_can( 'edit_posts' ) || ! $this->can_cancel( $request ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sprint-illustrations' ), 403 );
		}

		Notices::add_for_user(
			get_current_user_id(),
			$repo->cancel( $id ) ? __( 'Request cancelled.', 'sprint-illustrations' ) : __( 'That request was already handled.', 'sprint-illustrations' ),
			'info'
		);

		$this->back( $request['category'] );
	}

	/**
	 * Redirect to the Library tab.
	 *
	 * @param string $tab Tab.
	 */
	private function back( string $tab ): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => Menu::LIBRARY_SLUG,
					'tab'  => isset( $this->categories()[ $tab ] ) ? $tab : 'characters',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
