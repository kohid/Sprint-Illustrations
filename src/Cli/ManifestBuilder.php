<?php
/**
 * Builds manifest.json and cleaned piece files from authored SVG sources.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Cli;

use SprintIllustrations\Compose\Recolorer;
use SprintIllustrations\Security\SanitizationException;
use SprintIllustrations\Security\Sanitizer;
use SprintIllustrations\Svg\Affine;
use SprintIllustrations\Svg\SvgDom;
use SprintIllustrations\Svg\SvgException;

/**
 * Source layout:  <source>/<category>/<name>.svg
 * Output layout:  <target>/manifest.json, <target>/pieces/<category>/<name>.svg
 *
 * Authoring conventions read from each source file:
 *   - Anchor markers: any <circle|ellipse|rect id="anchor-<name>"> (removed from output).
 *   - Optional root metadata (removed from output), used as prompt defaults:
 *       data-si-label, data-si-tags="a,b", data-si-z, data-si-accepts="hold:handheld",
 *       data-si-mounts="handheld:grip"
 *   - Colour slots: class="slot-*" (see Recolorer).
 */
final class ManifestBuilder {

	/**
	 * Category directory => ID prefix.
	 */
	public const CATEGORIES = [
		'backgrounds' => 'bg',
		'characters'  => 'char',
		'decor'       => 'decor',
		'objects'     => 'obj',
	];

	private const DEFAULT_Z = [
		'backgrounds' => 0,
		'characters'  => 30,
		'decor'       => 5,
		'objects'     => 20,
	];

	private const SHAPES = [ 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon' ];

	/**
	 * Constructor.
	 *
	 * @param Sanitizer $sanitizer Sanitizer.
	 * @param Prompter  $prompter  Prompter (NullPrompter for non-interactive builds).
	 */
	public function __construct(
		private Sanitizer $sanitizer,
		private Prompter $prompter,
	) {}

	/**
	 * Process every source SVG and merge entries into <target>/manifest.json.
	 *
	 * @param string $source_dir Source directory.
	 * @param string $target_dir Target directory.
	 * @return BuildReport
	 */
	public function build( string $source_dir, string $target_dir ): BuildReport {
		$source_dir    = rtrim( $source_dir, '/\\' );
		$target_dir    = rtrim( $target_dir, '/\\' );
		$manifest_file = $target_dir . '/manifest.json';
		$existing      = is_readable( $manifest_file ) ? json_decode( (string) file_get_contents( $manifest_file ), true ) : null;
		$existing      = is_array( $existing ) ? $existing : [];
		$report        = new BuildReport();

		$entries = [];
		foreach ( (array) ( $existing['pieces'] ?? [] ) as $entry ) {
			if ( isset( $entry['id'] ) ) {
				$entries[ (string) $entry['id'] ] = $entry;
			}
		}

		foreach ( self::CATEGORIES as $category => $prefix ) {
			$files = glob( $source_dir . '/' . $category . '/*.svg' );
			$files = $files ? $files : [];
			sort( $files );

			foreach ( $files as $file ) {
				$name = strtolower( (string) preg_replace( '/[^a-z0-9-]+/i', '-', basename( $file, '.svg' ) ) );
				$id   = $prefix . '-' . trim( $name, '-' );

				try {
					$entries[ $id ]  = $this->process( $file, $id, $name, $category, $target_dir, $entries[ $id ] ?? null, $report );
					$report->built[] = $id;
				} catch ( SvgException | SanitizationException $e ) {
					$report->errors[] = basename( $file ) . ': ' . $e->getMessage();
				}
			}
		}

		ksort( $entries );
		$report->version = (int) ( $existing['version'] ?? 0 ) + 1;

		if ( ! is_dir( $target_dir ) ) {
			mkdir( $target_dir, 0755, true );
		}
		file_put_contents(
			$manifest_file,
			json_encode(
				[
					'version' => $report->version,
					'pieces'  => array_values( $entries ),
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			) . "\n"
		);

		return $report;
	}

	/**
	 * Process one source file.
	 *
	 * @param string                    $file       Source path.
	 * @param string                    $id         Piece ID.
	 * @param string                    $name       File stem.
	 * @param string                    $category   Category.
	 * @param string                    $target_dir Target directory.
	 * @param array<string, mixed>|null $previous   Existing manifest entry.
	 * @param BuildReport               $report     Report.
	 * @return array<string, mixed> Manifest entry.
	 * @throws SvgException When the source cannot be parsed.
	 */
	private function process( string $file, string $id, string $name, string $category, string $target_dir, ?array $previous, BuildReport $report ): array {
		$doc  = SvgDom::parse( (string) file_get_contents( $file ) );
		$root = $doc->documentElement;

		$meta     = $this->take_meta( $root );
		$view_box = $this->view_box( $root );
		$anchors  = $this->take_anchors( $doc, $id, $report );
		$slots    = $this->detect_slots( $doc );

		foreach ( $this->lint( $doc ) as $warning ) {
			$report->warnings[] = $id . ': ' . $warning;
		}

		$label = $meta['label'] ?? (string) ( $previous['label'] ?? ucwords( str_replace( '-', ' ', $name ) ) );
		$tags  = $this->csv( $this->prompter->ask( "[$id] Tags (comma-separated)", $meta['tags'] ?? implode( ',', (array) ( $previous['tags'] ?? explode( '-', $name ) ) ) ) );
		$z     = (int) $this->prompter->ask( "[$id] Z-layer", (string) ( $meta['z'] ?? $previous['z'] ?? self::DEFAULT_Z[ $category ] ) );

		$accepts = [];
		$mounts  = [];
		if ( $anchors ) {
			$names   = implode( ', ', array_keys( $anchors ) );
			$accepts = $this->pairs( $this->prompter->ask( "[$id] Anchors that accept attachments, as anchor:type (anchors: $names)", $meta['accepts'] ?? $this->encode_pairs( (array) ( $previous['accepts'] ?? [] ) ) ) );
			$mounts  = $this->pairs( $this->prompter->ask( "[$id] Mount points, as type:anchor (types: handheld, lap, surface)", $meta['mounts'] ?? $this->encode_pairs( (array) ( $previous['mounts'] ?? [] ) ) ) );
		}

		foreach ( $accepts as $anchor => $type ) {
			if ( ! isset( $anchors[ $anchor ] ) ) {
				$report->warnings[] = sprintf( '%s: accepts refers to unknown anchor "%s"; dropped.', $id, $anchor );
				unset( $accepts[ $anchor ] );
			}
		}
		foreach ( $mounts as $type => $anchor ) {
			if ( ! isset( $anchors[ $anchor ] ) ) {
				$report->warnings[] = sprintf( '%s: mount refers to unknown anchor "%s"; dropped.', $id, $anchor );
				unset( $mounts[ $type ] );
			}
		}

		$clean    = $this->sanitizer->sanitize( (string) $doc->saveXML( $root ) );
		$relative = 'pieces/' . $category . '/' . $name . '.svg';
		$out_dir  = $target_dir . '/pieces/' . $category;

		if ( ! is_dir( $out_dir ) ) {
			mkdir( $out_dir, 0755, true );
		}
		file_put_contents( $target_dir . '/' . $relative, $clean . "\n" );

		return [
			'id'       => $id,
			'label'    => $label,
			'category' => $category,
			'file'     => $relative,
			'tags'     => $tags,
			'viewBox'  => $view_box,
			'size'     => [ $view_box[2], $view_box[3] ],
			'z'        => $z,
			'anchors'  => (object) $anchors,
			'accepts'  => (object) $accepts,
			'mounts'   => (object) $mounts,
			'slots'    => $slots,
			'hash'     => sha1( $clean ),
		];
	}

	/**
	 * Read and remove data-si-* metadata from the root.
	 *
	 * @param \DOMElement $root Root element.
	 * @return array<string, string>
	 */
	private function take_meta( \DOMElement $root ): array {
		$meta = [];

		foreach ( [ 'label', 'tags', 'z', 'accepts', 'mounts' ] as $key ) {
			$attribute = 'data-si-' . $key;
			if ( $root->hasAttribute( $attribute ) ) {
				$meta[ $key ] = trim( $root->getAttribute( $attribute ) );
				$root->removeAttribute( $attribute );
			}
		}

		return $meta;
	}

	/**
	 * Parse the root viewBox (falls back to width/height).
	 *
	 * @param \DOMElement $root Root element.
	 * @return array{0: float, 1: float, 2: float, 3: float}
	 * @throws SvgException When no usable viewBox exists.
	 */
	private function view_box( \DOMElement $root ): array {
		$values = array_map( 'floatval', preg_split( '/[\s,]+/', trim( $root->getAttribute( 'viewBox' ) ), -1, PREG_SPLIT_NO_EMPTY ) );

		if ( 4 !== count( $values ) ) {
			$values = [ 0.0, 0.0, (float) $root->getAttribute( 'width' ), (float) $root->getAttribute( 'height' ) ];
		}

		if ( $values[2] <= 0 || $values[3] <= 0 ) {
			throw new SvgException( 'Missing viewBox (and no width/height).' );
		}

		return $values;
	}

	/**
	 * Read and remove anchor markers, resolving ancestor transforms.
	 *
	 * @param \DOMDocument $doc    Document.
	 * @param string       $id     Piece ID (for warnings).
	 * @param BuildReport  $report Report.
	 * @return array<string, array{0: float, 1: float}>
	 */
	private function take_anchors( \DOMDocument $doc, string $id, BuildReport $report ): array {
		$xpath   = new \DOMXPath( $doc );
		$anchors = [];

		foreach ( iterator_to_array( $xpath->query( '//*[starts-with(@id, "anchor-")]' ) ) as $marker ) {
			$name = substr( $marker->getAttribute( 'id' ), 7 );

			switch ( $marker->localName ) {
				case 'circle':
				case 'ellipse':
					$local = [ (float) $marker->getAttribute( 'cx' ), (float) $marker->getAttribute( 'cy' ) ];
					break;
				case 'rect':
					$local = [
						(float) $marker->getAttribute( 'x' ) + (float) $marker->getAttribute( 'width' ) / 2,
						(float) $marker->getAttribute( 'y' ) + (float) $marker->getAttribute( 'height' ) / 2,
					];
					break;
				default:
					$report->warnings[] = sprintf( '%s: anchor "%s" must be a circle, ellipse or rect; ignored.', $id, $name );
					$marker->parentNode?->removeChild( $marker );
					continue 2;
			}

			[ $x, $y ]        = Affine::for_element( $marker )->apply( $local[0], $local[1] );
			$anchors[ $name ] = [ round( $x, 2 ), round( $y, 2 ) ];
			$marker->parentNode?->removeChild( $marker );
		}//end foreach

		ksort( $anchors );

		return $anchors;
	}

	/**
	 * Colour slot names used.
	 *
	 * @param \DOMDocument $doc Document.
	 * @return array<string>
	 */
	private function detect_slots( \DOMDocument $doc ): array {
		$slots = [];

		foreach ( ( new \DOMXPath( $doc ) )->query( '//*[@class]' ) as $element ) {
			foreach ( preg_split( '/\s+/', $element->getAttribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
				$slot = Recolorer::parse_token( $token );
				if ( null !== $slot ) {
					$slots[ $slot['name'] ] = true;
				}
			}
		}

		$slots = array_keys( $slots );
		sort( $slots );

		return $slots;
	}

	/**
	 * Authoring warnings.
	 *
	 * @param \DOMDocument $doc Document.
	 * @return array<string>
	 */
	private function lint( \DOMDocument $doc ): array {
		$warnings = [];

		foreach ( ( new \DOMXPath( $doc ) )->query( '//*' ) as $element ) {
			$tag = $element->localName;

			if ( in_array( strtolower( $tag ), [ 'script', 'style', 'image', 'foreignobject' ], true ) ) {
				$warnings[] = sprintf( '<%s> is not allowed and will be removed.', $tag );
			}

			foreach ( [ 'fill', 'stroke' ] as $property ) {
				$value = strtolower( trim( $element->getAttribute( $property ) ) );
				if ( '' !== $value && ! in_array( $value, [ 'none', 'transparent' ], true ) && ! str_starts_with( $value, 'url(#' ) ) {
					$warnings[] = sprintf( 'literal %s "%s" on <%s> will not follow the palette.', $property, $value, $tag );
				}
			}

			if ( in_array( $tag, self::SHAPES, true ) && ! $element->hasAttribute( 'fill' ) && ! $this->is_colour_managed( $element ) ) {
				$warnings[] = sprintf( '<%s> has no slot class and will render black.', $tag );
			}
		}

		return array_values( array_unique( $warnings ) );
	}

	/**
	 * Whether the element (or an ancestor) has a slot class, is an anchor marker, or sits inside
	 * defs / clipPath / mask where fill does not paint.
	 *
	 * @param \DOMElement $element Element.
	 * @return bool
	 */
	private function is_colour_managed( \DOMElement $element ): bool {
		for ( $node = $element; $node instanceof \DOMElement; $node = $node->parentNode ) {
			if ( str_contains( ' ' . $node->getAttribute( 'class' ), ' slot-' )
				|| str_starts_with( $node->getAttribute( 'id' ), 'anchor-' )
				|| in_array( strtolower( $node->localName ), [ 'defs', 'clippath', 'mask' ], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse "a, b ,c" into lower-case unique values.
	 *
	 * @param string $value CSV.
	 * @return array<string>
	 */
	private function csv( string $value ): array {
		return array_values( array_unique( array_filter( array_map( static fn( $v ) => strtolower( trim( $v ) ), explode( ',', $value ) ) ) ) );
	}

	/**
	 * Parse "a:b, c:d" into [a => b, c => d].
	 *
	 * @param string $value Pairs.
	 * @return array<string, string>
	 */
	private function pairs( string $value ): array {
		$pairs = [];

		foreach ( $this->csv( $value ) as $pair ) {
			$parts = array_map( 'trim', explode( ':', $pair, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
				$pairs[ $parts[0] ] = $parts[1];
			}
		}

		return $pairs;
	}

	/**
	 * Encode [a => b] as "a:b".
	 *
	 * @param array<string, string> $pairs Pairs.
	 * @return string
	 */
	private function encode_pairs( array $pairs ): string {
		return implode( ',', array_map( static fn( $key, $value ) => $key . ':' . $value, array_keys( $pairs ), $pairs ) );
	}
}
