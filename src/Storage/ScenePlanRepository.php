<?php
/**
 * Scene plans: a described scene waiting for its new pieces to be drawn and kept.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

/**
 * Kept in one option (a handful per site, oldest dropped). A plan remembers the description, the
 * template and keywords that were suggested, and the piece requests made for it; the pieces
 * themselves are tracked by PieceRequestRepository.
 */
final class ScenePlanRepository {

	public const OPTION = 'sprint_illustrations_scene_plans';
	public const MAX    = 20;

	/**
	 * Create a plan.
	 *
	 * @param array{user: int, description: string, template: string, keywords: array<string>, title: string, seed: int, requests: array<int>} $data Plan data.
	 * @return string Plan ID.
	 */
	public function create( array $data ): string {
		$id    = substr( md5( wp_generate_uuid4() ), 0, 12 );
		$plans = $this->all();

		$plans[ $id ] = $data + [ 'created' => gmdate( 'Y-m-d H:i:s' ) ];
		$this->save( array_slice( $plans, -self::MAX, null, true ) );

		return $id;
	}

	/**
	 * One plan.
	 *
	 * @param string $id Plan ID.
	 * @return array{user: int, description: string, template: string, keywords: array<string>, title: string, seed: int, requests: array<int>, created: string}|null
	 */
	public function get( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	/**
	 * Plans of a user, newest first, keyed by ID.
	 *
	 * @param int  $user Owner.
	 * @param bool $all  Every user's plans instead.
	 * @return array<string, array<string, mixed>>
	 */
	public function for_user( int $user, bool $all = false ): array {
		$plans = array_filter( $this->all(), static fn( array $plan ): bool => $all || (int) $plan['user'] === $user );

		return array_reverse( $plans, true );
	}

	/**
	 * Delete a plan (its piece requests stay).
	 *
	 * @param string $id Plan ID.
	 * @return bool
	 */
	public function delete( string $id ): bool {
		$plans = $this->all();
		if ( ! isset( $plans[ $id ] ) ) {
			return false;
		}

		unset( $plans[ $id ] );
		$this->save( $plans );

		return true;
	}

	/**
	 * Every stored plan.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function all(): array {
		$plans = get_option( self::OPTION, [] );

		return is_array( $plans ) ? $plans : [];
	}

	/**
	 * Store the plans.
	 *
	 * @param array<string, array<string, mixed>> $plans Plans.
	 */
	private function save( array $plans ): void {
		update_option( self::OPTION, $plans, false );
	}
}
