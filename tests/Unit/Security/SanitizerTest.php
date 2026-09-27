<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Security\SanitizationException;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\SvgDom;

final class SanitizerTest extends TestCase {

	private const OPEN = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">';

	private Sanitizer $sanitizer;

	protected function setUp(): void {
		$this->sanitizer = new Sanitizer();
	}

	/**
	 * @dataProvider attacks
	 */
	public function test_strips_attack( string $body, string $forbidden ): void {
		$clean = $this->sanitizer->sanitize( self::OPEN . $body . '</svg>' );

		$this->assertStringNotContainsStringIgnoringCase( $forbidden, $clean );
	}

	public static function attacks(): array {
		return [
			'script element'      => [ '<script>alert(1)</script>', 'script' ],
			'event handler'       => [ '<rect onload="alert(1)" width="1" height="1"/>', 'onload' ],
			'mixed-case handler'  => [ '<rect OnClick="alert(1)" width="1" height="1"/>', 'onclick' ],
			'foreignObject'       => [ '<foreignObject><div>x</div></foreignObject>', 'foreignObject' ],
			'external use'        => [ '<use xlink:href="https://evil.test/a.svg#x"/>', 'evil.test' ],
			'javascript href'     => [ '<use href="javascript:alert(1)"/>', 'javascript' ],
			'data uri href'       => [ '<use href="data:image/svg+xml;base64,AAAA"/>', 'data:' ],
			'external url() fill' => [ '<rect fill="url(https://evil.test/p.svg#g)" width="1" height="1"/>', 'evil.test' ],
			'style attribute'     => [ '<rect style="fill:url(https://evil.test/x)" width="1" height="1"/>', 'style' ],
			'style element'       => [ '<style>@import url(https://evil.test/x.css);</style>', 'evil.test' ],
			'image element'       => [ '<image href="https://evil.test/x.png"/>', 'image' ],
			'anchor element'      => [ '<a href="https://evil.test"><rect width="1" height="1"/></a>', 'evil.test' ],
			'animate set'         => [ '<rect width="1" height="1"><set attributeName="fill" to="red"/></rect>', '<set' ],
			'data attribute'      => [ '<rect data-payload="x" width="1" height="1"/>', 'data-payload' ],
			'comment'             => [ '<!-- secret --><rect width="1" height="1"/>', 'secret' ],
			'processing instr.'   => [ '<?php echo 1; ?><rect width="1" height="1"/>', '<?php' ],
		];
	}

	public function test_preserves_legitimate_structure(): void {
		$input = self::OPEN
			. '<title id="__SIID__-t">Hi</title>'
			. '<defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient><clipPath id="c"><rect width="5" height="5"/></clipPath></defs>'
			. '<g transform="translate(1 2)"><rect fill="url(#g)" clip-path="url(#c)" width="10" height="10" vector-effect="non-scaling-stroke"/></g>'
			. '<use href="#c"/>'
			. '</svg>';

		$clean = $this->sanitizer->sanitize( $input );

		foreach ( [ 'viewBox="0 0 10 10"', '<linearGradient id="g"', '<clipPath id="c"', 'fill="url(#g)"', 'clip-path="url(#c)"', 'vector-effect="non-scaling-stroke"', 'href="#c"', 'transform="translate(1 2)"', '__SIID__-t' ] as $expected ) {
			$this->assertStringContainsString( $expected, $clean );
		}
	}

	public function test_keeps_accessibility_attributes(): void {
		$clean = $this->sanitizer->sanitize( '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="a b" aria-hidden="true" focusable="false"/>' );

		$this->assertStringContainsString( 'role="img"', $clean );
		$this->assertStringContainsString( 'aria-labelledby="a b"', $clean );
		$this->assertStringContainsString( 'aria-hidden="true"', $clean );
		$this->assertStringContainsString( 'focusable="false"', $clean );
	}

	public function test_output_has_no_xml_declaration_and_is_idempotent(): void {
		$once  = $this->sanitizer->sanitize( '<?xml version="1.0"?>' . self::OPEN . '<rect width="1" height="1"/></svg>' );
		$twice = $this->sanitizer->sanitize( $once );

		$this->assertStringStartsWith( '<svg', $once );
		$this->assertSame( $once, $twice );
	}

	public function test_entity_expansion_is_not_performed(): void {
		$bomb = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "AAAAAAAAAA"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><title>&b;</title></svg>';

		try {
			$clean = $this->sanitizer->sanitize( $bomb );
			$this->assertStringNotContainsString( 'AAAAAAAAAA', $clean );
		} catch ( SanitizationException $e ) {
			$this->addToAssertionCount( 1 ); // Rejecting outright is also acceptable.
		}
	}

	public function test_throws_on_garbage(): void {
		$this->expectException( SanitizationException::class );
		$this->sanitizer->sanitize( 'not an svg' );
	}

	/**
	 * These three payloads were confirmed, via a throwaway script instantiating
	 * `SprintIllustrations\Vendor\enshrined\svgSanitize\Sanitizer` directly with
	 * `removeRemoteReferences( true )`, to survive the vendor engine untouched.
	 * Only our own hardening pass in `Sanitizer::harden()` strips them, so these
	 * exercise that pass rather than the vendor allowlist.
	 *
	 * Note: the same payloads on a `<use>` element do not qualify, because the
	 * vendor engine already deletes the whole `<use>` element whenever its href
	 * does not start with `#` (`isUseTagDirty()`), regardless of our hardening.
	 */
	public function test_strips_root_relative_href_not_caught_by_vendor(): void {
		$clean = $this->sanitizer->sanitize( self::OPEN . '<rect href="/x.svg#a" width="1" height="1"/></svg>' );

		$this->assertStringNotContainsString( 'href', $clean );
	}

	public function test_strips_data_image_href_not_caught_by_vendor(): void {
		$clean = $this->sanitizer->sanitize( self::OPEN . '<rect href="data:image/png;base64,AAAA" width="1" height="1"/></svg>' );

		$this->assertStringNotContainsString( 'href', $clean );
	}

	public function test_strips_relative_url_target_not_caught_by_vendor(): void {
		$clean = $this->sanitizer->sanitize( self::OPEN . '<rect fill="url(x.svg#a)" width="1" height="1"/></svg>' );

		$this->assertStringNotContainsString( 'url(', $clean );
	}

	/**
	 * Exercises `Sanitizer::harden()` directly via reflection, on a document
	 * built with `SvgDom::parse()` rather than run through the vendor engine
	 * first. This proves the hardening pass itself removes each dangerous
	 * construct, independent of whatever the vendor allowlist already caught.
	 */
	public function test_harden_removes_blocked_elements_dangerous_attributes_and_nodes(): void {
		$doc = SvgDom::parse(
			'<svg xmlns="http://www.w3.org/2000/svg">'
			. '<!-- secret -->'
			. '<?xml-stylesheet type="text/xsl" href="x.xsl"?>'
			. '<script>alert(1)</script>'
			. '<foreignObject><div>x</div></foreignObject>'
			. '<style>@import url(evil.css);</style>'
			. '<rect onload="alert(1)" style="fill:red" width="1" height="1"/>'
			. '<rect fill="url(#g)" width="1" height="1"/>'
			. '<use href="#c"/>'
			. '</svg>'
		);

		$harden = new \ReflectionMethod( Sanitizer::class, 'harden' );
		$harden->setAccessible( true );
		$harden->invoke( $this->sanitizer, $doc );

		$xml = (string) $doc->saveXML( $doc->documentElement );

		$this->assertStringNotContainsString( 'secret', $xml );
		$this->assertStringNotContainsString( 'xml-stylesheet', $xml );
		$this->assertStringNotContainsString( '<script', $xml );
		$this->assertStringNotContainsStringIgnoringCase( 'foreignObject', $xml );
		$this->assertStringNotContainsString( '<style', $xml );
		$this->assertStringNotContainsString( 'onload', $xml );
		$this->assertStringNotContainsString( 'style=', $xml );
		$this->assertStringContainsString( 'fill="url(#g)"', $xml );
		$this->assertStringContainsString( 'href="#c"', $xml );
	}
}
