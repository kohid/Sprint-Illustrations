<?php
/**
 * Attribute allowlist for the SVG sanitizer.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Security;

use SprintIllustrations\Vendor\enshrined\svgSanitize\data\AttributeInterface;

/**
 * Presentation and geometry attributes only. No `style`: colour comes from attributes.
 */
final class AllowedAttributes implements AttributeInterface {

	/**
	 * Allowed attribute names (lower-case, compared case-insensitively).
	 *
	 * @return array<string>
	 */
	public static function getAttributes(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Interface contract.
		return [
			'id',
			'class',
			'viewbox',
			'preserveaspectratio',
			'xmlns',
			'xmlns:xlink',
			'width',
			'height',
			'x',
			'y',
			'x1',
			'y1',
			'x2',
			'y2',
			'cx',
			'cy',
			'r',
			'rx',
			'ry',
			'fx',
			'fy',
			'd',
			'points',
			'transform',
			'fill',
			'fill-opacity',
			'fill-rule',
			'clip-rule',
			'stroke',
			'stroke-width',
			'stroke-linecap',
			'stroke-linejoin',
			'stroke-miterlimit',
			'stroke-dasharray',
			'stroke-dashoffset',
			'stroke-opacity',
			'opacity',
			'vector-effect',
			'clip-path',
			'clippathunits',
			'mask',
			'maskunits',
			'maskcontentunits',
			'offset',
			'stop-color',
			'stop-opacity',
			'gradientunits',
			'gradienttransform',
			'spreadmethod',
			'href',
			'xlink:href',
			'role',
			'focusable',
			'display',
			'visibility',
		];
	}
}
