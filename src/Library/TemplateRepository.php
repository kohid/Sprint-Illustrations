<?php
/**
 * Loads scene templates from JSON files.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Library;

/**
 * Template lookup. Earlier directories win on duplicate IDs.
 */
final class TemplateRepository {

	/**
	 * Templates keyed by ID, sorted by ID.
	 *
	 * @var array<string, Template>
	 */
	private array $templates = [];

	/**
	 * Load every *.json in the given directories. Missing directories are skipped.
	 *
	 * @param array<string> $directories Absolute directory paths.
	 * @return self
	 * @throws LibraryException When a template file is invalid.
	 */
	public static function from_directories( array $directories ): self {
		$repository = new self();

		foreach ( $directories as $directory ) {
			$files = glob( rtrim( $directory, '/\\' ) . '/*.json' );
			foreach ( $files ? $files : [] as $file ) {
				$data = json_decode( (string) file_get_contents( $file ), true );
				if ( ! is_array( $data ) ) {
					throw new LibraryException( sprintf( 'Invalid template JSON: %s', $file ) );
				}
				$template = Template::from_array( $data );
				if ( ! isset( $repository->templates[ $template->id ] ) ) {
					$repository->templates[ $template->id ] = $template;
				}
			}
		}

		ksort( $repository->templates );

		return $repository;
	}

	/**
	 * Template by ID.
	 *
	 * @param string $id Template ID.
	 * @return Template|null
	 */
	public function get( string $id ): ?Template {
		return $this->templates[ $id ] ?? null;
	}

	/**
	 * All templates sorted by ID.
	 *
	 * @return array<Template>
	 */
	public function all(): array {
		return array_values( $this->templates );
	}

	/**
	 * All template IDs sorted.
	 *
	 * @return array<string>
	 */
	public function ids(): array {
		return array_keys( $this->templates );
	}
}
