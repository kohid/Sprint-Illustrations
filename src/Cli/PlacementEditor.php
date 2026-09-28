<?php
/**
 * Find and change illustration blocks / Elementor widgets in parsed page data.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

/**
 * Pure array edits over parse_blocks() output and Elementor's element tree. Indexes count from 1, depth-first.
 */
final class PlacementEditor {

	public const BLOCK = 'sprint-illustrations/illustration';

	public const WIDGET = 'sprint-illustration';

	/**
	 * Number of illustration blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return int
	 */
	public static function count_blocks( array $blocks ): int {
		$count = 0;
		foreach ( $blocks as $block ) {
			$count += ( self::BLOCK === ( $block['blockName'] ?? null ) ? 1 : 0 ) + self::count_blocks( (array) ( $block['innerBlocks'] ?? [] ) );
		}

		return $count;
	}

	/**
	 * Update the nth illustration block, or insert a new one at the top or bottom.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param int|null                         $index  1-based block to update (when $insert is '').
	 * @param string                           $insert '' | 'top' | 'bottom'.
	 * @param array<string, mixed>             $attrs  Attributes to set (merged over existing ones).
	 * @return array{blocks: array<int, array<string, mixed>>, changed: int, error: string}
	 */
	public static function blocks( array $blocks, ?int $index, string $insert, array $attrs ): array {
		if ( '' !== $insert ) {
			if ( ! in_array( $insert, [ 'top', 'bottom' ], true ) ) {
				return self::result( $blocks, 0, 'Insert must be "top" or "bottom".' );
			}

			$new = [
				'blockName'    => self::BLOCK,
				'attrs'        => $attrs,
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			];
			$gap = [
				'blockName'    => null,
				'attrs'        => [],
				'innerBlocks'  => [],
				'innerHTML'    => "\n\n",
				'innerContent' => [ "\n\n" ],
			];

			return self::result( 'top' === $insert ? array_merge( [ $new, $gap ], $blocks ) : array_merge( $blocks, [ $gap, $new ] ), 1, '' );
		}//end if

		$total = self::count_blocks( $blocks );
		if ( null === $index || $index < 1 || $index > $total ) {
			return self::result( $blocks, 0, sprintf( 'Illustration block %d not found; the page has %d.', (int) $index, $total ) );
		}

		$seen = 0;
		return self::result( self::update_blocks( $blocks, $index, $attrs, $seen ), 1, '' );
	}

	/**
	 * Number of illustration widgets in an Elementor element tree.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @return int
	 */
	public static function count_widgets( array $elements ): int {
		$count = 0;
		foreach ( $elements as $element ) {
			$count += ( self::WIDGET === ( $element['widgetType'] ?? null ) ? 1 : 0 ) + self::count_widgets( (array) ( $element['elements'] ?? [] ) );
		}

		return $count;
	}

	/**
	 * Update the nth illustration widget's settings.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @param int                              $index    1-based widget.
	 * @param array<string, mixed>             $settings Settings to set (merged over existing ones).
	 * @return array{elements: array<int, array<string, mixed>>, changed: int, error: string}
	 */
	public static function elementor( array $elements, int $index, array $settings ): array {
		$total = self::count_widgets( $elements );
		if ( $index < 1 || $index > $total ) {
			return [
				'elements' => $elements,
				'changed'  => 0,
				'error'    => sprintf( 'Illustration widget %d not found; the page has %d.', $index, $total ),
			];
		}

		$seen = 0;
		return [
			'elements' => self::update_widgets( $elements, $index, $settings, $seen ),
			'changed'  => 1,
			'error'    => '',
		];
	}

	/**
	 * Depth-first block update.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param int                              $index  Target.
	 * @param array<string, mixed>             $attrs  Attributes.
	 * @param int                              $seen   Illustration blocks seen so far.
	 * @return array<int, array<string, mixed>>
	 */
	private static function update_blocks( array $blocks, int $index, array $attrs, int &$seen ): array {
		foreach ( $blocks as $i => $block ) {
			if ( self::BLOCK === ( $block['blockName'] ?? null ) && ++$seen === $index ) {
				$blocks[ $i ]['attrs'] = array_merge( (array) ( $block['attrs'] ?? [] ), $attrs );
				return $blocks;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::update_blocks( (array) $block['innerBlocks'], $index, $attrs, $seen );
			}
		}

		return $blocks;
	}

	/**
	 * Depth-first widget update.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @param int                              $index    Target.
	 * @param array<string, mixed>             $settings Settings.
	 * @param int                              $seen     Illustration widgets seen so far.
	 * @return array<int, array<string, mixed>>
	 */
	private static function update_widgets( array $elements, int $index, array $settings, int &$seen ): array {
		foreach ( $elements as $i => $element ) {
			if ( self::WIDGET === ( $element['widgetType'] ?? null ) && ++$seen === $index ) {
				$elements[ $i ]['settings'] = array_merge( (array) ( $element['settings'] ?? [] ), $settings );
				return $elements;
			}
			if ( ! empty( $element['elements'] ) ) {
				$elements[ $i ]['elements'] = self::update_widgets( (array) $element['elements'], $index, $settings, $seen );
			}
		}

		return $elements;
	}

	/**
	 * Block result.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Blocks.
	 * @param int                              $changed Changed count.
	 * @param string                           $error   Error.
	 * @return array{blocks: array<int, array<string, mixed>>, changed: int, error: string}
	 */
	private static function result( array $blocks, int $changed, string $error ): array {
		return [
			'blocks'  => $blocks,
			'changed' => $changed,
			'error'   => $error,
		];
	}
}
