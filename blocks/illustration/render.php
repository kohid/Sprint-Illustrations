<?php
/**
 * Server render for sprint-illustrations/illustration.
 *
 * @package SprintIllustrations
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes wrapper attributes.
	\SprintIllustrations\Plugin::instance()->renderer()->render( $attributes, current_user_can( 'edit_posts' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
);
