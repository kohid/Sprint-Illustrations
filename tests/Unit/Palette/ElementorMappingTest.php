<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Palette;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Palette\ElementorMapping;

final class ElementorMappingTest extends TestCase {

	private function kit(): array {
		return [
			[
				'id'    => 'kit:primary',
				'label' => 'Primary',
				'value' => '#6EC1E4',
			],
			[
				'id'    => 'kit:secondary',
				'label' => 'Secondary',
				'value' => '#54595F',
			],
			[
				'id'    => 'kit:text',
				'label' => 'Text',
				'value' => '#7A7A7A',
			],
			[
				'id'    => 'kit:accent',
				'label' => 'Accent',
				'value' => '#61CE70',
			],
			[
				'id'    => 'kit:a1b2c3',
				'label' => 'Page background',
				'value' => '#F4F6FF',
			],
		];
	}

	public function test_suggests_kit_system_colours(): void {
		$this->assertSame(
			[
				'accent'     => 'kit:accent',
				'background' => 'kit:a1b2c3',
				'neutral'    => 'kit:text',
				'outline'    => 'kit:text',
				'primary'    => 'kit:primary',
				'secondary'  => 'kit:secondary',
			],
			( new ElementorMapping( $this->kit() ) )->suggest()
		);
	}

	public function test_variables_named_after_slots_override_and_win_background(): void {
		$items = array_merge(
			[
				[
					'id'    => 'var:e-gv-1',
					'label' => 'Primary',
					'value' => '#4F46E5',
				],
				[
					'id'    => 'var:e-gv-2',
					'label' => 'brand-bg',
					'value' => '#FAFAFF',
				],
				[
					'id'    => 'var:e-gv-3',
					'label' => 'accent',
					'value' => 'var(--e-gv-1)',
				],
			],
			$this->kit()
		);

		$map = ( new ElementorMapping( $items ) )->suggest();

		$this->assertSame( 'var:e-gv-1', $map['primary'] );
		$this->assertSame( 'var:e-gv-2', $map['background'] );
		$this->assertSame( 'kit:accent', $map['accent'], 'A variable that is not importable never overrides.' );
	}

	public function test_nothing_suggested_without_items(): void {
		$this->assertSame( [], ( new ElementorMapping( [] ) )->suggest() );
	}

	public function test_apply_uses_mapped_colours_and_reports_missing(): void {
		$current = [
			'primary'    => '#111111',
			'background' => '#eeeeee',
		];
		$result  = ( new ElementorMapping( $this->kit() ) )->apply(
			[
				'primary'    => 'kit:primary',
				'background' => 'var:e-gv-9',
				'bogus'      => 'kit:text',
			],
			$current
		);

		$this->assertSame(
			[
				'primary'    => '#6ec1e4',
				'background' => '#eeeeee',
			],
			$result['colors']
		);
		$this->assertSame( [ 'var:e-gv-9' ], $result['missing'] );
	}

	public function test_choices_mark_unimportable_items(): void {
		$choices = ( new ElementorMapping(
			[
				[
					'id'    => 'var:e-gv-3',
					'label' => 'Link',
					'value' => 'var(--x)',
				],
			]
		) )->choices();

		$this->assertSame(
			[
				[
					'id'    => 'var:e-gv-3',
					'label' => 'Link',
					'hex'   => null,
				],
			],
			$choices
		);
	}
}
