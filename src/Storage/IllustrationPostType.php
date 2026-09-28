<?php
/**
 * The si_illustration post type.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

/**
 * Private storage for saved illustrations (the Builder is the UI; REST is our own).
 */
final class IllustrationPostType {

	public const POST_TYPE = 'si_illustration';

	public const META_SPEC = '_si_spec';

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
				'label'           => __( 'Illustrations', 'sprint-illustrations' ),
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
