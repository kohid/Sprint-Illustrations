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
 * Form + progress queue. Claude Code draws drafts (WP-CLI); the requester keeps or discards them here.
 */
final class PieceRequestPanel {

	public const CREATE_ACTION = 'sprint_illustrations_piece_request';

	public const CANCEL_ACTION = 'sprint_illustrations_piece_request_cancel';

	public const KEEP_ACTION = 'sprint_illustrations_piece_request_keep';

	public const DISCARD_ACTION = 'sprint_illustrations_piece_request_discard';

	public const RETRY_ACTION = 'sprint_illustrations_piece_request_retry';

	public const STATES_ACTION = 'sprint_illustrations_request_states';

	private const ACTIVE = [ 'queued', 'drawing', 'review' ];

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
		add_action( 'admin_post_' . self::KEEP_ACTION, [ $this, 'handle_keep' ] );
		add_action( 'admin_post_' . self::DISCARD_ACTION, [ $this, 'handle_discard' ] );
		add_action( 'admin_post_' . self::RETRY_ACTION, [ $this, 'handle_retry' ] );
		add_action( 'wp_ajax_' . self::STATES_ACTION, [ $this, 'ajax_states' ] );
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
	 * Current states of active requests (for live updates).
	 *
	 * @return array<int, string>
	 */
	public function states(): array {
		$states = [];
		foreach ( $this->plugin->piece_requests()->list( self::ACTIVE, 50 ) as $row ) {
			$states[ $row['id'] ] = $row['state'];
		}

		return $states;
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
		$active   = $repo->list( self::ACTIVE, 50 );
		$done     = $repo->list( 'done', self::RECENT );
		$closed   = $repo->list( [ 'discarded', 'declined' ], self::RECENT );
		$selected = isset( $this->categories()[ $tab ] ) ? $tab : 'objects';

		printf(
			'<section class="si-panel si-requests%1$s" aria-labelledby="si-requests-title" data-si-requests="%2$s">',
			$this->plugin->drawer_heartbeat()->online() ? ' is-drawer-online' : '',
			esc_attr( (string) wp_json_encode( (object) $this->states() ) )
		);
		$this->render_form( $selected );

		echo '<div class="si-requests__queue">';
		$this->render_drawer();
		/* translators: %d: number of requests in progress. */
		echo '<h3 class="si-requests__heading">' . esc_html( sprintf( __( 'In progress (%d)', 'sprint-illustrations' ), count( $active ) ) ) . '</h3>';
		if ( ! $active ) {
			echo '<p class="si-requests__empty">' . esc_html__( 'Nothing in progress. Add a request to get started.', 'sprint-illustrations' ) . '</p>';
		} else {
			echo '<ul class="si-requests__list">';
			foreach ( $active as $row ) {
				$this->render_active( $row );
			}
			echo '</ul>';
		}

		if ( $done ) {
			echo '<h3 class="si-requests__heading">' . esc_html__( 'Recently added', 'sprint-illustrations' ) . '</h3><ul class="si-requests__list si-requests__list--done">';
			foreach ( $done as $row ) {
				$this->render_finished( $row );
			}
			echo '</ul>';
		}

		if ( $closed ) {
			echo '<h3 class="si-requests__heading">' . esc_html__( 'Discarded or declined', 'sprint-illustrations' ) . '</h3><ul class="si-requests__list si-requests__list--closed">';
			foreach ( $closed as $row ) {
				$this->render_finished( $row );
			}
			echo '</ul>';
		}
		echo '</div></section>';
	}

	/**
	 * The request form.
	 *
	 * @param string $selected Preselected category.
	 */
	private function render_form( string $selected ): void {
		echo '<div class="si-requests__form">';
		echo '<h2 class="si-panel__title" id="si-requests-title">' . esc_html__( 'Request a piece', 'sprint-illustrations' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
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

		if ( current_user_can( 'upload_files' ) ) {
			echo '<div class="si-requests__row si-reference"><label for="si-request-reference">' . esc_html__( 'Reference image (optional)', 'sprint-illustrations' ) . '</label>';
			echo '<input type="file" id="si-request-reference" name="reference" accept="image/png,image/jpeg,image/webp" aria-describedby="si-request-reference-help" data-invalid="' . esc_attr__( 'Choose a PNG, JPEG or WebP image of 5 MB or less.', 'sprint-illustrations' ) . '">';
			echo '<span class="si-reference__preview" hidden><img alt=""><button type="button" class="button-link si-reference__remove">' . esc_html__( 'Remove', 'sprint-illustrations' ) . '</button></span>';
			echo '<span class="si-requests__help" id="si-request-reference-help">' . esc_html__( 'PNG, JPEG or WebP, up to 5 MB. Claude Code draws from it in the library’s flat style.', 'sprint-illustrations' ) . '</span></div>';
		}

		echo '<p class="si-requests__actions"><button type="submit" class="button button-primary">' . esc_html__( 'Add request', 'sprint-illustrations' ) . '</button></p>';
		echo '<p class="si-requests__hint">' . esc_html__( 'Claude Code draws it while a session is open. You’ll see it here before it joins the library.', 'sprint-illustrations' ) . '</p>';
		echo '</form></div>';
	}

	/**
	 * Whether a Claude Code session is watching the queue. Both messages are rendered; CSS shows one
	 * (the `is-drawer-online` class on the panel) so library.js can switch them without a reload.
	 */
	private function render_drawer(): void {
		echo '<div class="si-drawer" role="status"><span class="si-drawer__dot" aria-hidden="true"></span>';
		echo '<span class="si-when-online"><strong>' . esc_html__( 'Claude Code is watching', 'sprint-illustrations' ) . '</strong> ' . esc_html__( 'New requests start within a few seconds.', 'sprint-illustrations' ) . '</span>';
		echo '<span class="si-when-offline"><strong>' . esc_html__( 'No Claude Code session open', 'sprint-illustrations' ) . '</strong> ' . esc_html__( 'Requests wait here until you open one in this project.', 'sprint-illustrations' ) . '</span>';
		echo '</div>';
	}

	/**
	 * A request that's queued, being drawn, or waiting for review.
	 *
	 * @param array<string, mixed> $row Request.
	 */
	private function render_active( array $row ): void {
		$state = (string) $row['state'];

		printf( '<li class="si-request is-%s">', esc_attr( $state ) );
		$this->render_head( $row );
		echo '<span class="si-request__meta">' . esc_html( $this->requested_by( $row ) );
		if ( 'queued' === $state && $this->can_act( $row ) ) {
			echo ' · ' . $this->action_link( self::CANCEL_ACTION, $row, __( 'Cancel', 'sprint-illustrations' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in action_link().
		}
		echo '</span>';

		if ( '' !== $row['feedback'] ) {
			/* translators: %s: feedback. */
			echo '<span class="si-request__note">' . esc_html( sprintf( __( 'Your feedback: %s', 'sprint-illustrations' ), $row['feedback'] ) ) . '</span>';
		}

		$this->render_track( $state );

		if ( 'queued' === $state ) {
			$status = '<span class="si-when-online">' . esc_html__( 'Claude Code will start this in a moment.', 'sprint-illustrations' ) . '</span>'
				. '<span class="si-when-offline">' . esc_html__( 'It’ll be drawn the next time you open a Claude Code session.', 'sprint-illustrations' ) . '</span>';
		} elseif ( 'drawing' === $state ) {
			$since = '' !== $row['changed'] ? (string) $row['changed'] : (string) $row['date'];
			/* translators: %s: time ago, e.g. "2 mins". */
			$status = esc_html( sprintf( __( 'Claude Code started drawing this %s ago.', 'sprint-illustrations' ), $this->ago( $since ) ) );
		} else {
			$status = esc_html__( 'Ready for review. Keep it to add it to the library, or discard it.', 'sprint-illustrations' );
		}
		echo '<p class="si-request__status" role="status">' . $status . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

		if ( 'review' === $state ) {
			$this->render_review( $row );
		}
		echo '</li>';
	}

	/**
	 * Previews and Keep / Discard.
	 *
	 * @param array<string, mixed> $row Request.
	 */
	private function render_review( array $row ): void {
		$preview = $this->plugin->piece_drafts()->preview( $row );

		echo '<div class="si-review">';
		if ( '' !== $preview['piece'] ) {
			echo '<figure class="si-review__art si-review__art--piece"><div class="si-review__canvas">' . $preview['piece'] . '</div><figcaption>' . esc_html__( 'The piece', 'sprint-illustrations' ) . '</figcaption></figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitizer output.
		}
		if ( '' !== $preview['scene'] ) {
			echo '<figure class="si-review__art si-review__art--scene"><div class="si-review__canvas">' . $preview['scene'] . '</div><figcaption>' . esc_html__( 'In a scene', 'sprint-illustrations' ) . '</figcaption></figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composer output.
		}
		if ( '' === $preview['piece'] ) {
			echo '<p class="si-request__note">' . esc_html__( 'The draft preview is missing. Ask Claude Code to draw it again.', 'sprint-illustrations' ) . '</p>';
		}
		echo '</div>';

		if ( $this->can_act( $row ) ) {
			echo '<div class="si-review__actions">';
			$this->render_button( self::KEEP_ACTION, $row, __( 'Keep', 'sprint-illustrations' ), 'button button-primary', '' !== $preview['piece'] );
			$this->render_button( self::DISCARD_ACTION, $row, __( 'Discard', 'sprint-illustrations' ), 'button', true );
			echo '</div>';
		}
	}

	/**
	 * Added, discarded or declined.
	 *
	 * @param array<string, mixed> $row Request.
	 */
	private function render_finished( array $row ): void {
		$state   = (string) $row['state'];
		$changed = '' !== $row['changed'] ? (string) $row['changed'] : (string) $row['date'];
		$labels  = [
			/* translators: %s: time ago. */
			'done'      => __( 'Added %s ago', 'sprint-illustrations' ),
			/* translators: %s: time ago. */
			'discarded' => __( 'Discarded %s ago', 'sprint-illustrations' ),
			/* translators: %s: time ago. */
			'declined'  => __( 'Declined %s ago', 'sprint-illustrations' ),
		];

		printf( '<li class="si-request is-%s">', esc_attr( $state ) );
		$this->render_head( $row );
		echo '<span class="si-request__meta">' . esc_html( $this->requested_by( $row ) . ' · ' . sprintf( $labels[ $state ] ?? '%s', $this->ago( $changed ) ) );

		if ( 'done' === $state && '' !== $row['piece'] ) {
			$url = add_query_arg(
				[
					'page'  => Menu::LIBRARY_SLUG,
					'tab'   => $row['category'],
					'piece' => $row['piece'],
				],
				admin_url( 'admin.php' )
			) . '#piece-' . rawurlencode( (string) $row['piece'] );
			printf( ' · <a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'View piece', 'sprint-illustrations' ) );
		}
		echo '</span>';

		if ( 'declined' === $state && '' !== $row['note'] ) {
			echo '<span class="si-request__note">' . esc_html( (string) $row['note'] ) . '</span>';
		}

		if ( 'done' !== $state && $this->can_act( $row ) ) {
			$this->render_retry( $row );
		}
		echo '</li>';
	}

	/**
	 * Try again form with optional feedback.
	 *
	 * @param array<string, mixed> $row Request.
	 */
	private function render_retry( array $row ): void {
		$id = (int) $row['id'];

		echo '<form class="si-retry" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::RETRY_ACTION ) . '"><input type="hidden" name="request" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( self::RETRY_ACTION . '_' . $id );
		echo '<label class="screen-reader-text" for="si-retry-' . esc_attr( (string) $id ) . '">' . esc_html__( 'What should change?', 'sprint-illustrations' ) . '</label>';
		printf(
			'<input type="text" id="si-retry-%1$d" name="feedback" maxlength="%2$d" placeholder="%3$s">',
			(int) $id,
			(int) PieceRequest::MAX_LENGTH,
			esc_attr__( 'What should change? (optional)', 'sprint-illustrations' )
		);
		echo '<button type="submit" class="button">' . esc_html__( 'Try again', 'sprint-illustrations' ) . '</button></form>';
	}

	/**
	 * Category chip + description.
	 *
	 * @param array<string, mixed> $row Request.
	 */
	private function render_head( array $row ): void {
		$reference = $this->plugin->reference_images()->url( (string) ( $row['reference'] ?? '' ) );
		if ( '' !== $reference ) {
			printf( '<a class="si-request__reference" href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" loading="lazy"></a>', esc_url( $reference ), esc_attr__( 'Reference image', 'sprint-illustrations' ) );
		}
		echo '<span class="si-request__category">' . esc_html( $this->categories()[ $row['category'] ] ?? (string) $row['category'] ) . '</span>';
		echo '<span class="si-request__text">' . esc_html( (string) $row['description'] ) . '</span>';
	}

	/**
	 * Four-step progress track.
	 *
	 * @param string $state Current state.
	 */
	private function render_track( string $state ): void {
		$steps   = [
			'queued'  => __( 'Requested', 'sprint-illustrations' ),
			'drawing' => __( 'Drawing', 'sprint-illustrations' ),
			'review'  => __( 'Ready for review', 'sprint-illustrations' ),
			'done'    => __( 'Added', 'sprint-illustrations' ),
		];
		$current = (int) array_search( $state, array_keys( $steps ), true );

		echo '<ol class="si-track" aria-label="' . esc_attr__( 'Progress', 'sprint-illustrations' ) . '">';
		foreach ( array_values( $steps ) as $i => $label ) {
			$class = '';
			if ( $i < $current ) {
				$class = 'is-done';
			} elseif ( $i === $current ) {
				$class = 'is-current';
			}
			// While drawing, a pen line draws itself under the current step (static with reduced motion).
			$pen = 'drawing' === $state && $i === $current
				? '<svg class="si-track__pen" viewBox="0 0 60 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M1 4C10 1 18 6 28 3S46 1 59 4" pathLength="100"/></svg>'
				: '';
			printf( '<li class="si-track__step %1$s"%2$s>%3$s%4$s</li>', esc_attr( $class ), $i === $current ? ' aria-current="step"' : '', esc_html( $label ), $pen ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $pen is a fixed string.
		}
		echo '</ol>';
	}

	/**
	 * POST button for a request action.
	 *
	 * @param string               $action  Action.
	 * @param array<string, mixed> $row     Request.
	 * @param string               $label   Label.
	 * @param string               $classes Button classes.
	 * @param bool                 $enabled Enabled.
	 */
	private function render_button( string $action, array $row, string $label, string $classes, bool $enabled ): void {
		$id = (int) $row['id'];

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="request" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( $action . '_' . $id );
		printf(
			'<button type="submit" class="%1$s"%2$s>%3$s<span class="screen-reader-text"> %4$s</span></button></form>',
			esc_attr( $classes ),
			$enabled ? '' : ' disabled',
			esc_html( $label ),
			esc_html( (string) $row['description'] )
		);
	}

	/**
	 * Nonced GET link for a request action.
	 *
	 * @param string               $action Action.
	 * @param array<string, mixed> $row    Request.
	 * @param string               $label  Label.
	 * @return string
	 */
	private function action_link( string $action, array $row, string $label ): string {
		return sprintf(
			'<a href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&request=' . (int) $row['id'] ), $action . '_' . (int) $row['id'] ) ),
			esc_html( $label ),
			esc_html( (string) $row['description'] )
		);
	}

	/**
	 * "Requested by <name> <time> ago".
	 *
	 * @param array<string, mixed> $row Request.
	 * @return string
	 */
	private function requested_by( array $row ): string {
		$author = get_userdata( (int) $row['author'] );

		/* translators: 1: user name, 2: time ago. */
		return sprintf( __( 'Requested by %1$s %2$s ago', 'sprint-illustrations' ), $author ? $author->display_name : __( 'someone', 'sprint-illustrations' ), $this->ago( (string) $row['date'] ) );
	}

	/**
	 * Human time since a GMT datetime.
	 *
	 * @param string $gmt Y-m-d H:i:s (GMT).
	 * @return string
	 */
	private function ago( string $gmt ): string {
		return human_time_diff( (int) strtotime( $gmt . ' UTC' ) );
	}

	/**
	 * Whether the current user may act on a request (requester or admin).
	 *
	 * @param array<string, mixed> $row Request.
	 * @return bool
	 */
	private function can_act( array $row ): bool {
		return current_user_can( 'manage_options' ) || ( current_user_can( 'edit_posts' ) && get_current_user_id() === (int) $row['author'] );
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

		// Optional reference image: stored (re-encoded) before the request, removed again if that fails.
		$reference = '';
		$file      = isset( $_FILES['reference'] ) && is_array( $_FILES['reference'] ) ? $_FILES['reference'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated and re-encoded in ReferenceImages::store().
		if ( $file && current_user_can( 'upload_files' ) && UPLOAD_ERR_NO_FILE !== ( is_int( $file['error'] ?? null ) ? $file['error'] : UPLOAD_ERR_NO_FILE ) ) {
			$stored = $this->plugin->reference_images()->store( $file );
			if ( is_wp_error( $stored ) ) {
				Notices::add_for_user( get_current_user_id(), $stored->get_error_message(), 'error' );
				$this->back( $category );
			}
			$reference = (string) $stored;
		}

		$result = $this->plugin->piece_requests()->create( $category, $text, get_current_user_id(), $reference );

		if ( is_wp_error( $result ) ) {
			$this->plugin->reference_images()->delete( $reference );
			Notices::add_for_user( get_current_user_id(), $result->get_error_message(), 'error' );
		} else {
			Notices::add_for_user( get_current_user_id(), __( 'Request added. Claude Code draws it while a session is open.', 'sprint-illustrations' ), 'success' );
		}

		$this->back( $category );
	}

	/**
	 * Admin-post handler: cancel a queued request.
	 */
	public function handle_cancel(): void {
		$request = $this->authorized( self::CANCEL_ACTION, 'GET' );
		$done    = $this->plugin->piece_requests()->cancel( (int) $request['id'] );
		if ( $done ) {
			$this->plugin->reference_images()->delete( (string) $request['reference'] );
		}

		Notices::add_for_user( get_current_user_id(), $done ? __( 'Request cancelled.', 'sprint-illustrations' ) : __( 'That request is already being handled.', 'sprint-illustrations' ), 'info' );
		$this->back( (string) $request['category'] );
	}

	/**
	 * Admin-post handler: keep a draft (it joins the library).
	 */
	public function handle_keep(): void {
		$request = $this->authorized( self::KEEP_ACTION, 'POST' );

		if ( 'review' !== $request['state'] ) {
			Notices::add_for_user( get_current_user_id(), __( 'That request is not waiting for review.', 'sprint-illustrations' ), 'info' );
			$this->back( (string) $request['category'] );
		}

		$result = $this->plugin->piece_drafts()->keep( $request );
		if ( ! $result['ok'] ) {
			Notices::add_for_user( get_current_user_id(), implode( ' ', $result['messages'] ), 'error' );
			$this->back( (string) $request['category'] );
		}

		$this->plugin->piece_requests()->keep( (int) $request['id'], $result['piece'] );
		$this->plugin->reference_images()->delete( (string) $request['reference'] );
		Notices::add_for_user(
			get_current_user_id(),
			'plugin' === ( $result['where'] ?? '' )
				? __( 'Kept. The piece is now in the plugin’s library, so it ships with the plugin to every site. Ask Claude Code to commit it.', 'sprint-illustrations' )
				: __( 'Kept. The piece is in this site’s library (the plugin folder isn’t writable here).', 'sprint-illustrations' ),
			'success'
		);
		$this->back( (string) $request['category'], $result['piece'] );
	}

	/**
	 * Admin-post handler: discard a draft.
	 */
	public function handle_discard(): void {
		$request = $this->authorized( self::DISCARD_ACTION, 'POST' );

		if ( 'review' === $request['state'] ) {
			$this->plugin->piece_drafts()->discard( $request );
			$this->plugin->piece_requests()->discard( (int) $request['id'] );
			Notices::add_for_user( get_current_user_id(), __( 'Discarded. Use Try again to ask for a new version.', 'sprint-illustrations' ), 'info' );
		}

		$this->back( (string) $request['category'] );
	}

	/**
	 * Admin-post handler: send a discarded or declined request back to the queue.
	 */
	public function handle_retry(): void {
		$request  = $this->authorized( self::RETRY_ACTION, 'POST' );
		$feedback = isset( $_POST['feedback'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorized().

		if ( $this->plugin->piece_requests()->retry( (int) $request['id'], $feedback ) ) {
			Notices::add_for_user( get_current_user_id(), __( 'Back in the queue. Ask Claude Code to “make the requested pieces”.', 'sprint-illustrations' ), 'success' );
		}

		$this->back( (string) $request['category'] );
	}

	/**
	 * AJAX: current states for live updates.
	 */
	public function ajax_states(): void {
		check_ajax_referer( self::STATES_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}

		wp_send_json_success(
			[
				'states' => (object) $this->states(),
				'online' => $this->plugin->drawer_heartbeat()->online(),
			]
		);
	}

	/**
	 * Nonce + permission check for a per-request action; returns the request or dies.
	 *
	 * @param string $action Action.
	 * @param string $method GET or POST.
	 * @return array<string, mixed>
	 */
	private function authorized( string $action, string $method ): array {
		// phpcs:disable WordPress.Security.NonceVerification -- Verified just below.
		$source = 'GET' === $method ? $_GET : $_POST;
		$id     = isset( $source['request'] ) ? absint( $source['request'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification
		check_admin_referer( $action . '_' . $id );

		$request = $this->plugin->piece_requests()->get( $id );
		if ( null === $request || ! $this->can_act( $request ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'sprint-illustrations' ), 403 );
		}

		return $request;
	}

	/**
	 * Redirect to the Library tab (optionally highlighting a piece).
	 *
	 * @param string $tab   Tab.
	 * @param string $piece Piece to highlight.
	 */
	private function back( string $tab, string $piece = '' ): void {
		$args = [
			'page' => Menu::LIBRARY_SLUG,
			'tab'  => isset( $this->categories()[ $tab ] ) ? $tab : 'characters',
		];
		if ( '' !== $piece ) {
			$args['piece'] = $piece;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) . ( '' !== $piece ? '#piece-' . rawurlencode( $piece ) : '' ) );
		exit;
	}
}
