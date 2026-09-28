<?php
/**
 * Validate a Messages API reply against the library.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

/**
 * First text block → JSON → known template, known tags (≤ 6, unique), clean alt text (≤ 120).
 */
final class AiResponse {

	public const MAX_KEYWORDS = 6;

	public const MAX_TITLE = 120;

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
		if ( ! is_array( $data ) || ! is_string( $data['template'] ?? null ) || ! in_array( $data['template'], $template_ids, true ) ) {
			return null;
		}

		$keywords = [];
		foreach ( is_array( $data['keywords'] ?? null ) ? $data['keywords'] : [] as $keyword ) {
			$keyword = is_string( $keyword ) ? strtolower( trim( $keyword ) ) : '';
			if ( in_array( $keyword, $tags, true ) && ! in_array( $keyword, $keywords, true ) ) {
				$keywords[] = $keyword;
			}
		}

		$title = is_string( $data['title'] ?? null ) ? $data['title'] : '';
		$title = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( $title ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure namespace; no WordPress functions.

		return [
			'template' => $data['template'],
			'keywords' => array_slice( $keywords, 0, self::MAX_KEYWORDS ),
			'title'    => mb_substr( $title, 0, self::MAX_TITLE ),
		];
	}
}
