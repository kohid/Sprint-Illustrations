<?php
/**
 * Piece requests stored as si_piece_request posts.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Library\PieceRequest;

/**
 * Create, list and move requests through queued → done | declined.
 */
final class PieceRequestRepository {

	/**
	 * Create a queued request.
	 *
	 * @param string $category    Category.
	 * @param string $description Description.
	 * @param int    $author      Requesting user.
	 * @return int|\WP_Error Request ID.
	 */
	public function create( string $category, string $description, int $author ): int|\WP_Error {
		$error = PieceRequest::validate( $category, $description );
		if ( '' !== $error ) {
			return new \WP_Error( 'sprint_illustrations_invalid_request', $error );
		}

		$id = wp_insert_post(
			[
				'post_type'   => PieceRequestPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => PieceRequest::clean( $description ),
				'post_author' => $author,
				'meta_input'  => [
					PieceRequestPostType::META_CATEGORY => $category,
					PieceRequestPostType::META_STATE    => 'queued',
				],
			],
			true
		);

		return is_wp_error( $id ) ? $id : (int) $id;
	}

	/**
	 * One request.
	 *
	 * @param int $id Request ID.
	 * @return array{id: int, category: string, description: string, state: string, piece: string, note: string, author: int, date: string}|null
	 */
	public function get( int $id ): ?array {
		$post = get_post( $id );

		return $post instanceof \WP_Post && PieceRequestPostType::POST_TYPE === $post->post_type ? $this->row( $post ) : null;
	}

	/**
	 * Requests in a state, newest first ('all' for every state).
	 *
	 * @param string $state State or 'all'.
	 * @param int    $limit Maximum.
	 * @return array<int, array{id: int, category: string, description: string, state: string, piece: string, note: string, author: int, date: string}>
	 */
	public function list( string $state, int $limit = 50 ): array {
		$args = [
			'post_type'      => PieceRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'queued' === $state ? 'ASC' : 'DESC',
			'no_found_rows'  => true,
		];
		if ( 'all' !== $state ) {
			$args['meta_key']   = PieceRequestPostType::META_STATE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small private table.
			$args['meta_value'] = $state; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small private table.
		}

		return array_map( [ $this, 'row' ], get_posts( $args ) );
	}

	/**
	 * Delete a queued request.
	 *
	 * @param int $id Request ID.
	 * @return bool
	 */
	public function cancel( int $id ): bool {
		$request = $this->get( $id );

		return null !== $request && 'queued' === $request['state'] && (bool) wp_delete_post( $id, true );
	}

	/**
	 * Mark a queued request done with the piece that fulfilled it.
	 *
	 * @param int    $id    Request ID.
	 * @param string $piece Piece ID.
	 * @return bool
	 */
	public function complete( int $id, string $piece ): bool {
		return $this->finish( $id, 'done', [ PieceRequestPostType::META_PIECE => $piece ] );
	}

	/**
	 * Decline a queued request with a note for the requester.
	 *
	 * @param int    $id   Request ID.
	 * @param string $note Why, and what to try instead.
	 * @return bool
	 */
	public function decline( int $id, string $note ): bool {
		return $this->finish( $id, 'declined', [ PieceRequestPostType::META_NOTE => PieceRequest::clean( $note ) ] );
	}

	/**
	 * Move a queued request to a final state.
	 *
	 * @param int                   $id    Request ID.
	 * @param string                $state Final state.
	 * @param array<string, string> $meta  Extra meta.
	 * @return bool
	 */
	private function finish( int $id, string $state, array $meta ): bool {
		$request = $this->get( $id );
		if ( null === $request || 'queued' !== $request['state'] ) {
			return false;
		}

		foreach ( $meta + [ PieceRequestPostType::META_STATE => $state ] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return true;
	}

	/**
	 * Row for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array{id: int, category: string, description: string, state: string, piece: string, note: string, author: int, date: string}
	 */
	private function row( \WP_Post $post ): array {
		$state = (string) get_post_meta( $post->ID, PieceRequestPostType::META_STATE, true );

		return [
			'id'          => (int) $post->ID,
			'category'    => (string) get_post_meta( $post->ID, PieceRequestPostType::META_CATEGORY, true ),
			'description' => $post->post_title,
			'state'       => in_array( $state, PieceRequest::STATES, true ) ? $state : 'queued',
			'piece'       => (string) get_post_meta( $post->ID, PieceRequestPostType::META_PIECE, true ),
			'note'        => (string) get_post_meta( $post->ID, PieceRequestPostType::META_NOTE, true ),
			'author'      => (int) $post->post_author,
			'date'        => $post->post_date_gmt,
		];
	}
}
