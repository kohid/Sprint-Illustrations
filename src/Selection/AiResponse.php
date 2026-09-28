<?php
/**
 * Validate a Messages API reply against the library.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

/**
 * First text block → JSON → Suggestion rules (unknown tags are dropped).
 */
final class AiResponse {

	public const MAX_KEYWORDS = Suggestion::MAX_KEYWORDS;

	public const MAX_TITLE = Suggestion::MAX_TITLE;

	/**
	 * Suggestion, or null when the reply is unusable.
	 *
	 * @param array<string, mixed> $decoded      Decoded API response.
	 * @param array<string>        $template_ids Known template IDs.
	 * @param array<string>        $tags         Known tags.
	 * @return array{template: string, keywords: array<string>, title: string}|null
	 */
	public static function spec( array $decoded, array $template_ids, array $tags ): ?array {
		$text = null;
		foreach ( is_array( $decoded['content'] ?? null ) ? $decoded['content'] : [] as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && is_string( $block['text'] ?? null ) ) {
				$text = $block['text'];
				break;
			}
		}

		$data = null === $text ? null : json_decode( $text, true );

		return is_array( $data ) ? Suggestion::validate( $data, $template_ids, $tags, false )['suggestion'] : null;
	}
}
