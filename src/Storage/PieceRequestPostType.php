<?php
/**
 * The si_piece_request post type.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

/**
 * Private queue of piece requests (the Library page is the UI; Claude Code works through WP-CLI).
 */
final class PieceRequestPostType {

	public const POST_TYPE = 'si_piece_request';

	public const META_CATEGORY = '_si_category';

	public const META_STATE = '_si_state';

	public const META_PIECE = '_si_piece';

	public const META_NOTE = '_si_note';

	public const META_DRAFT = '_si_draft';

	public const META_FEEDBACK = '_si_feedback';

	public const META_CHANGED = '_si_changed';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'           => __( 'Piece requests', 'sprint-illustrations' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => [ 'title', 'author' ],
				'rewrite'         => false,
				'query_var'       => false,
				'has_archive'     => false,
			]
		);
	}
}
