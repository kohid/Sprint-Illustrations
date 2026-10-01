<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Selection;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Library\PieceRequest;
use SprintIllustrations\Selection\BriefSplitter;

final class BriefSplitterTest extends TestCase {

	private const BRIEF = 'Isometric flat-style illustration of a taxi driver studying for a city knowledge test, built as separate animatable layers: (1) a stylized tablet/device layer displaying a city street map with a highlighted route line, (2) an orange location-pin layer with 2 to 3 pins scattered across the map, (3) a small isometric taxi cab layer sitting on a winding road that curves through miniature granite-grey buildings evoking Aberdeen\'s architecture, (4) a tall background location-pin layer with a signal beacon on top, (5) a soft cloud and greenery layer framing the upper corners, (6) a ground-shadow layer beneath each object. Color palette: warm orange #F69216 as the primary accent, dark slate grey #37474F and charcoal #263238 for structure, white highlights, light grey #F5F5F5 shadows. Clean vector line work, flat minimal shading, subtle gradients, no outlines. Fully transparent background throughout, no ground plane, no background rectangle. Landscape composition, 16:9 aspect ratio (e.g. 1600x900px), elements arranged horizontally with the device and taxi spread across the width, left third kept open and uncluttered for hero text overlay. Suggested entrance animation per layer: device slides in from the right, pins drop in with a slight bounce, road and taxi fade in and taxi drives a short distance left to right, background pin zooms in, clouds fade in last.';

	public function test_splits_the_brief_into_one_request_per_layer_within_the_limit(): void {
		$result = BriefSplitter::split( self::BRIEF );

		$this->assertCount( 5, $result['layers'], 'Six layers, the shadow one folded into the style.' );
		foreach ( $result['layers'] as $request ) {
			$this->assertLessThanOrEqual( PieceRequest::MAX_LENGTH, mb_strlen( $request ) );
			$this->assertSame( '', PieceRequest::validate( 'objects', $request ) );
		}
		$this->assertStringStartsWith( 'A stylized tablet/device layer displaying a city street map with a highlighted route line.', $result['layers'][0] );
		$this->assertStringStartsWith( 'A small isometric taxi cab layer', $result['layers'][2] );
		$this->assertCount( 1, $result['skipped'] );
		$this->assertStringContainsString( 'ground-shadow', $result['skipped'][0] );
	}

	public function test_style_notes_ride_along_without_hex_codes_or_scene_notes(): void {
		$result = BriefSplitter::split( self::BRIEF );

		foreach ( $result['layers'] as $request ) {
			$this->assertStringContainsString( 'warm orange', $request );
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-f]{3,8}/i', $request );
			$this->assertDoesNotMatchRegularExpression( '/16:9|animation|hero text|slides in/i', $request );
		}
		$this->assertStringContainsString( 'Style: isometric flat.', $result['layers'][0] );
		$this->assertStringContainsString( 'Soft shadow underneath.', $result['style'] );
		$this->assertStringNotContainsString( 'composition', $result['style'] );
	}

	public function test_numbered_lists_on_separate_lines_work_too(): void {
		$result = BriefSplitter::split( "A calm office scene:\n1. a desk with a laptop\n2) a tall plant in a pot\n3. a wall clock\nUse flat colours and no outlines." );

		$this->assertSame( [ 'A desk with a laptop. Use flat colours and no outlines.', 'A tall plant in a pot. Use flat colours and no outlines.', 'A wall clock. Use flat colours and no outlines.' ], $result['layers'] );
	}

	public function test_a_brief_without_numbered_layers_gives_nothing(): void {
		$this->assertSame( [], BriefSplitter::split( 'A taxi driver studying for a test, (1) only one marker here.' )['layers'] );
		$this->assertSame( [], BriefSplitter::split( 'Just a plain sentence.' )['layers'] );
	}

	public function test_very_long_layers_are_cut_at_a_word_within_the_limit(): void {
		$long   = str_repeat( 'a very detailed brass instrument ', 20 );
		$result = BriefSplitter::split( "Scene: (1) {$long}, (2) a small stool." );

		$this->assertLessThanOrEqual( PieceRequest::MAX_LENGTH, mb_strlen( $result['layers'][0] ) );
		$this->assertStringEndsWith( '…', $result['layers'][0] );
	}
}
