<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Dev;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Dev\ContactSheet;
use SprintIllustrations\Services;

final class ContactSheetTest extends TestCase {

	public function test_renders_one_figure_per_cell_with_unique_ids(): void {
		$sheet = new ContactSheet( Services::create( __DIR__ . '/../../fixtures/library' )->composer );
		$html  = $sheet->render( [ 'fixture-hero', 'fixture-object' ], [ 1, 2 ], ContactSheet::review_palettes() );

		$this->assertSame( 12, substr_count( $html, '<figure>' ) );
		$this->assertStringNotContainsString( '__SIID__', $html );
		$this->assertStringContainsString( 'si-sheet-12-t', $html );
	}

	public function test_unknown_template_shows_error_cell(): void {
		$sheet = new ContactSheet( Services::create( __DIR__ . '/../../fixtures/library' )->composer );
		$html  = $sheet->render( [ 'missing-template' ], [ 1 ], [ 'x' => ContactSheet::review_palettes()['Sprint'] ] );

		$this->assertStringContainsString( '<p class="si-sheet__error">Unknown template &quot;missing-template&quot;.</p>', $html );
	}

	public function test_malformed_template_id_is_escaped_and_falls_back_to_auto_selection(): void {
		$sheet = new ContactSheet( Services::create( __DIR__ . '/../../fixtures/library' )->composer );
		$html  = $sheet->render( [ 'missing<b>' ], [ 1 ], [ 'x' => ContactSheet::review_palettes()['Sprint'] ] );

		$this->assertStringContainsString( '<h2>missing&lt;b&gt;</h2>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '<svg', $html, 'SceneSpec drops the malformed ID, so the selector picks a template.' );
	}
}
