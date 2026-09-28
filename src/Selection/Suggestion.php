<?php
/**
 * One rulebook for suggestions from Claude (API) or Claude Code (CLI).
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

/**
 * Known template; known tags (≤ 6, unique, lower-case); clean alt text (≤ 120).
 */
final class Suggestion {

	public const MAX_KEYWORDS = 6;

	public const MAX_TITLE = 120;

	/**
	 * Validate and normalize a suggestion.
	 *
	 * @param array<string, mixed> $data         {template, keywords (array or comma string), title}.
	 * @param array<string>        $template_ids Known template IDs.
	 * @param array<string>        $tags         Known tags.
	 * @param bool                 $strict       Unknown tags are an error (CLI) instead of being dropped (API).
	 * @return array{suggestion: array{template: string, keywords: array<string>, title: string}|null, error: string}
	 */
	public static function validate( array $data, array $template_ids, array $tags, bool $strict ): array {
		$template = $data['template'] ?? null;
		if ( ! is_string( $template ) || ! in_array( $template, $template_ids, true ) ) {
			return self::fail( sprintf( 'Unknown template "%s". Known templates: %s.', is_string( $template ) ? $template : '', implode( ', ', $template_ids ) ) );
		}

		$raw      = $data['keywords'] ?? [];
		$raw      = is_string( $raw ) ? explode( ',', $raw ) : ( is_array( $raw ) ? $raw : [] );
		$keywords = [];
		$unknown  = [];
		foreach ( $raw as $keyword ) {
			$keyword = is_string( $keyword ) ? strtolower( trim( $keyword ) ) : '';
			if ( '' === $keyword || in_array( $keyword, $keywords, true ) ) {
				continue;
			}
			if ( in_array( $keyword, $tags, true ) ) {
				$keywords[] = $keyword;
			} elseif ( ! in_array( $keyword, $unknown, true ) ) {
				$unknown[] = $keyword;
			}
		}

		if ( $strict && [] !== $unknown ) {
			return self::fail( sprintf( 'Unknown tags: %s. Run "wp sprint-illustrations library" for the list.', implode( ', ', $unknown ) ) );
		}

		$title = is_string( $data['title'] ?? null ) ? $data['title'] : '';
		$title = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( $title ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure namespace; no WordPress functions.

		return [
			'suggestion' => [
				'template' => $template,
				'keywords' => array_slice( $keywords, 0, self::MAX_KEYWORDS ),
				'title'    => mb_substr( $title, 0, self::MAX_TITLE ),
			],
			'error'      => '',
		];
	}

	/**
	 * Failure result.
	 *
	 * @param string $error Message.
	 * @return array{suggestion: null, error: string}
	 */
	private static function fail( string $error ): array {
		return [
			'suggestion' => null,
			'error'      => $error,
		];
	}
}
