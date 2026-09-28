<?php
/**
 * A saved illustration (value object).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Storage;

use SprintIllustrations\Compose\SceneSpec;

/**
 * Named, frozen composition. Note: $title is the illustration's name (post title); $spec->title is the
 * accessible <title> of the SVG. They are different fields.
 */
final class Illustration {

	public const STATUSES = [ 'publish', 'draft', 'trash' ];

	public const MAX_TITLE = 200;

	/**
	 * Constructor. Use from_array().
	 *
	 * @param int|null    $id           Post ID (null when unsaved).
	 * @param string      $title        Name.
	 * @param SceneSpec   $spec         Resolved spec.
	 * @param string      $status       Post status.
	 * @param int         $author_id    Author.
	 * @param string|null $modified_gmt RFC 3339 modified time.
	 */
	private function __construct(
		public readonly ?int $id,
		public readonly string $title,
		public readonly SceneSpec $spec,
		public readonly string $status,
		public readonly int $author_id,
		public readonly ?string $modified_gmt,
	) {}

	/**
	 * Build from untrusted data. Never throws.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$id    = isset( $data['id'] ) && is_numeric( $data['id'] ) && (int) $data['id'] > 0 ? (int) $data['id'] : null;
		$title = isset( $data['title'] ) && is_scalar( $data['title'] ) ? trim( strip_tags( (string) $data['title'] ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure value object (no WordPress in unit tests), same as SceneSpec.

		return new self(
			$id,
			mb_substr( $title, 0, self::MAX_TITLE ),
			SceneSpec::from_array( is_array( $data['spec'] ?? null ) ? $data['spec'] : [] ),
			in_array( $data['status'] ?? null, self::STATUSES, true ) ? $data['status'] : 'publish',
			isset( $data['author_id'] ) && is_numeric( $data['author_id'] ) ? max( 0, (int) $data['author_id'] ) : 0,
			isset( $data['modified_gmt'] ) && is_string( $data['modified_gmt'] ) ? $data['modified_gmt'] : null
		);
	}

	/**
	 * Export.
	 *
	 * @return array{id: ?int, title: string, spec: array<string, mixed>, status: string, author_id: int, modified_gmt: ?string}
	 */
	public function to_array(): array {
		return [
			'id'           => $this->id,
			'title'        => $this->title,
			'spec'         => $this->spec->to_array(),
			'status'       => $this->status,
			'author_id'    => $this->author_id,
			'modified_gmt' => $this->modified_gmt,
		];
	}

	/**
	 * Copy with a new name.
	 *
	 * @param string $title Name.
	 * @return self
	 */
	public function with_title( string $title ): self {
		return self::from_array( [ 'title' => $title ] + $this->to_array() );
	}

	/**
	 * Copy with a new spec.
	 *
	 * @param SceneSpec $spec Spec.
	 * @return self
	 */
	public function with_spec( SceneSpec $spec ): self {
		return self::from_array( [ 'spec' => $spec->to_array() ] + $this->to_array() );
	}
}
