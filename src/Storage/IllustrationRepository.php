<?php
/**
 * CRUD for saved illustrations.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Compose\SceneSpec;

/**
 * WP_Post <-> Illustration. Every ID is checked to be an si_illustration before use.
 */
final class IllustrationRepository {

	/**
	 * Any status.
	 *
	 * @param int $id Post ID.
	 * @return Illustration|null
	 */
	public function find( int $id ): ?Illustration {
		$post = $this->post( $id );

		return null === $post ? null : $this->hydrate( $post );
	}

	/**
	 * Published only (the public render path).
	 *
	 * @param int $id Post ID.
	 * @return Illustration|null
	 */
	public function find_published( int $id ): ?Illustration {
		$post = $this->post( $id );

		return null !== $post && 'publish' === $post->post_status ? $this->hydrate( $post ) : null;
	}

	/**
	 * Whether the ID is an illustration.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function exists( int $id ): bool {
		return null !== $this->post( $id );
	}

	/**
	 * Published illustrations, newest first.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Page size.
	 * @param string $search   Search text.
	 * @return array{items: array<Illustration>, total: int}
	 */
	public function list( int $page = 1, int $per_page = 20, string $search = '' ): array {
		$query = new \WP_Query(
			[
				'post_type'      => IllustrationPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => max( 1, $page ),
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);

		return [
			'items' => array_map( fn( \WP_Post $post ): Illustration => $this->hydrate( $post ), $query->posts ),
			'total' => (int) $query->found_posts,
		];
	}

	/**
	 * Create.
	 *
	 * @param string    $title     Name.
	 * @param SceneSpec $spec      Resolved spec.
	 * @param int       $author_id Author.
	 * @return Illustration|null Null when WordPress refused.
	 */
	public function create( string $title, SceneSpec $spec, int $author_id ): ?Illustration {
		$id = wp_insert_post(
			[
				'post_type'   => IllustrationPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => $author_id,
			],
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return null;
		}

		update_post_meta( $id, IllustrationPostType::META_SPEC, wp_slash( (string) wp_json_encode( $spec->to_array() ) ) );

		return $this->find( (int) $id );
	}

	/**
	 * Update name and/or spec.
	 *
	 * @param int            $id    Post ID.
	 * @param string|null    $title New name.
	 * @param SceneSpec|null $spec  New spec.
	 * @return Illustration|null Null when not an illustration.
	 */
	public function update( int $id, ?string $title, ?SceneSpec $spec ): ?Illustration {
		if ( null === $this->post( $id ) ) {
			return null;
		}

		if ( null !== $spec ) {
			update_post_meta( $id, IllustrationPostType::META_SPEC, wp_slash( (string) wp_json_encode( $spec->to_array() ) ) );
		}

		wp_update_post( [ 'ID' => $id ] + ( null === $title ? [] : [ 'post_title' => $title ] ) );

		return $this->find( $id );
	}

	/**
	 * Move to trash.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function trash( int $id ): bool {
		return null !== $this->post( $id ) && (bool) wp_trash_post( $id );
	}

	/**
	 * Delete permanently.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return null !== $this->post( $id ) && (bool) wp_delete_post( $id, true );
	}

	/**
	 * The post, only when it is an illustration.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|null
	 */
	private function post( int $id ): ?\WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;

		return $post instanceof \WP_Post && IllustrationPostType::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * Post to value object.
	 *
	 * @param \WP_Post $post Post.
	 * @return Illustration
	 */
	private function hydrate( \WP_Post $post ): Illustration {
		$raw  = get_post_meta( $post->ID, IllustrationPostType::META_SPEC, true );
		$spec = is_string( $raw ) ? json_decode( $raw, true ) : null;

		return Illustration::from_array(
			[
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'spec'         => is_array( $spec ) ? $spec : [],
				'status'       => $post->post_status,
				'author_id'    => (int) $post->post_author,
				'modified_gmt' => mysql_to_rfc3339( $post->post_modified_gmt ),
			]
		);
	}
}
