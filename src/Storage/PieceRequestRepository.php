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
 * Create, list and move requests: queued → drawing → review → done | discarded; declined; retry → queued.
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
	 * @return array{id: int, category: string, description: string, state: string, piece: string, note: string, draft: string, feedback: string, author: int, date: string, changed: string}|null
	 */
	public function get( int $id ): ?array {
		$post = get_post( $id );

		return $post instanceof \WP_Post && PieceRequestPostType::POST_TYPE === $post->post_type ? $this->row( $post ) : null;
	}

	/**
	 * Requests in one or more states ('all' for every state). Active states list oldest first; finished ones newest first.
	 *
	 * @param string|array<string> $state State(s) or 'all'.
	 * @param int                  $limit Maximum.
	 * @return array<int, array{id: int, category: string, description: string, state: string, piece: string, note: string, draft: string, feedback: string, author: int, date: string, changed: string}>
	 */
	public function list( string|array $state, int $limit = 50 ): array {
		$states = (array) $state;
		$active = [] === array_diff( $states, [ 'queued', 'drawing', 'review' ] );
		$args   = [
			'post_type'      => PieceRequestPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => $active ? 'ASC' : 'DESC',
			'no_found_rows'  => true,
		];
		if ( [ 'all' ] !== $states ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small private table.
			$args['meta_query'] = [
				[
					'key'     => PieceRequestPostType::META_STATE,
					'value'   => $states,
					'compare' => 'IN',
				],
			];
		}

		$rows = array_map( [ $this, 'row' ], get_posts( $args ) );
		if ( ! $active ) {
			$when = static fn( array $row ): string => '' !== $row['changed'] ? $row['changed'] : $row['date'];
			usort( $rows, static fn( array $a, array $b ): int => strcmp( $when( $b ), $when( $a ) ) );
		}

		return $rows;
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
	 * Claude Code started drawing.
	 *
	 * @param int $id Request ID.
	 * @return bool
	 */
	public function start( int $id ): bool {
		return $this->move( $id, 'drawing' );
	}

	/**
	 * A draft is ready for the requester to review.
	 *
	 * @param int    $id   Request ID.
	 * @param string $name Draft file name (without .svg).
	 * @return bool
	 */
	public function submit_draft( int $id, string $name ): bool {
		return $this->move( $id, 'review', [ PieceRequestPostType::META_DRAFT => $name ] );
	}

	/**
	 * The draft was kept and is now this library piece.
	 *
	 * @param int    $id    Request ID.
	 * @param string $piece Piece ID.
	 * @return bool
	 */
	public function keep( int $id, string $piece ): bool {
		return $this->move( $id, 'done', [ PieceRequestPostType::META_PIECE => $piece ] );
	}

	/**
	 * The draft was discarded.
	 *
	 * @param int $id Request ID.
	 * @return bool
	 */
	public function discard( int $id ): bool {
		return $this->move( $id, 'discarded', [ PieceRequestPostType::META_DRAFT => '' ] );
	}

	/**
	 * Send a discarded or declined request back to the queue with optional feedback.
	 *
	 * @param int    $id       Request ID.
	 * @param string $feedback What to change.
	 * @return bool
	 */
	public function retry( int $id, string $feedback ): bool {
		return $this->move(
			$id,
			'queued',
			[
				PieceRequestPostType::META_FEEDBACK => mb_substr( PieceRequest::clean( $feedback ), 0, PieceRequest::MAX_LENGTH ),
				PieceRequestPostType::META_NOTE     => '',
			]
		);
	}

	/**
	 * Decline with a note for the requester.
	 *
	 * @param int    $id   Request ID.
	 * @param string $note Why, and what to try instead.
	 * @return bool
	 */
	public function decline( int $id, string $note ): bool {
		return $this->move( $id, 'declined', [ PieceRequestPostType::META_NOTE => PieceRequest::clean( $note ) ] );
	}

	/**
	 * Move a request to another state when the transition is allowed.
	 *
	 * @param int                   $id   Request ID.
	 * @param string                $to   Target state.
	 * @param array<string, string> $meta Extra meta.
	 * @return bool
	 */
	public function move( int $id, string $to, array $meta = [] ): bool {
		$request = $this->get( $id );
		if ( null === $request || ! PieceRequest::can_move( $request['state'], $to ) ) {
			return false;
		}

		$meta[ PieceRequestPostType::META_STATE ]   = $to;
		$meta[ PieceRequestPostType::META_CHANGED ] = gmdate( 'Y-m-d H:i:s' );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return true;
	}

	/**
	 * Row for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array{id: int, category: string, description: string, state: string, piece: string, note: string, draft: string, feedback: string, author: int, date: string, changed: string}
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
			'draft'       => (string) get_post_meta( $post->ID, PieceRequestPostType::META_DRAFT, true ),
			'feedback'    => (string) get_post_meta( $post->ID, PieceRequestPostType::META_FEEDBACK, true ),
			'author'      => (int) $post->post_author,
			'date'        => $post->post_date_gmt,
			'changed'     => (string) get_post_meta( $post->ID, PieceRequestPostType::META_CHANGED, true ),
		];
	}
}
