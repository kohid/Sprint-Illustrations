<?php
/**
 * Raised when a scene cannot be composed.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Compose;

/**
 * Composition failure (unknown template, unfillable required slot, missing piece file).
 */
final class CompositionException extends \RuntimeException {
}
