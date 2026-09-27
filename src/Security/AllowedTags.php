<?php
/**
 * Element allowlist for the SVG sanitizer.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Security;

use SprintIllustrations\Vendor\enshrined\svgSanitize\data\TagInterface;

/**
 * Tight allowlist: static vector shapes, gradients, clips and masks only.
 */
final class AllowedTags implements TagInterface {

	/**
	 * Elements removed by our own hardening pass even if an allowlist change lets them through.
	 * Lower-case local names.
	 */
	public const BLOCKED = [
		'script',
		'foreignobject',
		'image',
		'a',
		'style',
		'iframe',
		'animate',
		'animatemotion',
		'animatetransform',
		'set',
		'handler',
		'listener',
	];

	/**
	 * Allowed element names (lower-case, as the sanitizer compares case-insensitively).
	 *
	 * @return array<string>
	 */
	public static function getTags(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Interface contract.
		return [
			'svg',
			'g',
			'defs',
			'title',
			'desc',
			'path',
			'rect',
			'circle',
			'ellipse',
			'line',
			'polyline',
			'polygon',
			'clippath',
			'mask',
			'lineargradient',
			'radialgradient',
			'stop',
			'use',
			'symbol',
		];
	}
}
