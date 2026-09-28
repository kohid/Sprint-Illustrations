<?php
/**
 * Anthropic Messages request body for a suggestion.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Selection;

use SprintIllustrations\Library\Piece;
use SprintIllustrations\Library\Template;

/**
 * Structured output constrained to the library: template IDs and tags are enums.
 */
final class AiRequest {

	public const MODELS = [
		'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (fast, recommended)',
		'claude-opus-5-5'   => 'Claude Opus 5.5 (most capable, slower)',
	];

	public const DEFAULT_MODEL = 'claude-sonnet-5-5';

	public const MAX_CONTENT = 4000;

	private const SYSTEM = 'You choose a flat illustration for a web page from a fixed library. Read the content and pick the one template that fits it best, 1 to 6 keywords from the allowed tag list that describe what the scene should show, and alt text: one plain sentence of at most 120 characters describing the scene for screen reader users. Use only the template IDs and tags you are given.';

	/**
	 * All piece and template tags, sorted and unique.
	 *
	 * @param array<Piece>    $pieces    Pieces.
	 * @param array<Template> $templates Templates.
	 * @return array<string>
	 */
	public static function tags( array $pieces, array $templates ): array {
		$tags = [];
		foreach ( array_merge( $pieces, $templates ) as $item ) {
			foreach ( $item->tags as $tag ) {
				$tags[ $tag ] = true;
			}
		}

		$tags = array_keys( $tags );
		sort( $tags );

		return array_values( array_map( 'strval', $tags ) );
	}

	/**
	 * Request body.
	 *
	 * @param string          $content   User content (HTML allowed; stripped).
	 * @param array<Template> $templates Templates.
	 * @param array<string>   $tags      Allowed keywords.
	 * @param string          $model     Model ID.
	 * @return array<string, mixed>
	 */
	public static function body( string $content, array $templates, array $tags, string $model ): array {
		$templates = array_values( $templates );
		$lines     = array_map(
			static fn( Template $template ): string => sprintf(
				'- %s: %s (tags: %s; shows: %s)',
				$template->id,
				$template->label,
				implode( ', ', $template->tags ),
				implode( ', ', array_unique( array_map( static fn( $slot ): string => $slot->category, $template->slots ) ) )
			),
			$templates
		);

		return [
			'model'         => $model,
			'max_tokens'    => 1024,
			'system'        => self::SYSTEM,
			'messages'      => [
				[
					'role'    => 'user',
					'content' => "Templates:\n" . implode( "\n", $lines ) . "\n\nContent:\n" . self::clean( $content ),
				],
			],
			'output_config' => [
				'effort' => 'low',
				'format' => [
					'type'   => 'json_schema',
					'schema' => [
						'type'                 => 'object',
						'properties'           => [
							'template' => [
								'type' => 'string',
								'enum' => array_map( static fn( Template $template ): string => $template->id, $templates ),
							],
							'keywords' => [
								'type'  => 'array',
								'items' => [
									'type' => 'string',
									'enum' => array_values( $tags ),
								],
							],
							'title'    => [ 'type' => 'string' ],
						],
						'required'             => [ 'template', 'keywords', 'title' ],
						'additionalProperties' => false,
					],
				],
			],
		];
	}

	/**
	 * Smallest possible request, for "Test connection".
	 *
	 * @param string $model Model ID.
	 * @return array<string, mixed>
	 */
	public static function ping( string $model ): array {
		return [
			'model'      => $model,
			'max_tokens' => 16,
			'messages'   => [
				[
					'role'    => 'user',
					'content' => 'Reply with OK.',
				],
			],
		];
	}

	/**
	 * Plain text, collapsed whitespace, capped length.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function clean( string $content ): string {
		$text = strip_tags( (string) preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $content ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure namespace; no WordPress functions.

		return mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ), 0, self::MAX_CONTENT );
	}
}
