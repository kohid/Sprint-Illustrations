<?php
declare( strict_types=1 );

namespace SprintIllustrations\Tests\Unit\Update;

use PHPUnit\Framework\TestCase;
use SprintIllustrations\Update\Release;

final class ReleaseTest extends TestCase {

	private const REPO = 'kohid/Sprint-Illustrations';

	/**
	 * A GitHub release response with overrides.
	 *
	 * @param array<string,mixed> $override Fields to replace.
	 * @return array<string,mixed>
	 */
	private function data( array $override = [] ): array {
		return array_merge(
			[
				'tag_name'   => 'v0.8.0',
				'html_url'   => 'https://github.com/kohid/Sprint-Illustrations/releases/tag/v0.8.0',
				'body'       => 'Notes',
				'draft'      => false,
				'prerelease' => false,
				'assets'     => [
					[
						'name'                 => 'other.zip',
						'browser_download_url' => 'https://github.com/kohid/Sprint-Illustrations/releases/download/v0.8.0/other.zip',
					],
					[
						'name'                 => 'sprint-illustrations.zip',
						'browser_download_url' => 'https://github.com/kohid/Sprint-Illustrations/releases/download/v0.8.0/sprint-illustrations.zip',
					],
				],
			],
			$override
		);
	}

	public function test_reads_a_release(): void {
		$release = Release::from_github( $this->data(), self::REPO, 'sprint-illustrations.zip' );

		$this->assertNotNull( $release );
		$this->assertSame( '0.8.0', $release->version );
		$this->assertSame( 'https://github.com/kohid/Sprint-Illustrations/releases/download/v0.8.0/sprint-illustrations.zip', $release->package );
		$this->assertSame( 'Notes', $release->notes );
	}

	public function test_tag_without_v_is_accepted(): void {
		$release = Release::from_github( $this->data( [ 'tag_name' => '1.2.3' ] ), self::REPO, 'sprint-illustrations.zip' );

		$this->assertSame( '1.2.3', $release?->version );
	}

	public function test_drafts_prereleases_and_odd_tags_are_ignored(): void {
		$this->assertNull( Release::from_github( $this->data( [ 'draft' => true ] ), self::REPO, 'sprint-illustrations.zip' ) );
		$this->assertNull( Release::from_github( $this->data( [ 'prerelease' => true ] ), self::REPO, 'sprint-illustrations.zip' ) );
		$this->assertNull( Release::from_github( $this->data( [ 'tag_name' => 'v0.8.0-rc1' ] ), self::REPO, 'sprint-illustrations.zip' ) );
		$this->assertNull( Release::from_github( $this->data( [ 'tag_name' => 'latest' ] ), self::REPO, 'sprint-illustrations.zip' ) );
	}

	public function test_missing_asset_is_ignored(): void {
		$this->assertNull( Release::from_github( $this->data( [ 'assets' => [] ] ), self::REPO, 'sprint-illustrations.zip' ) );
		$this->assertNull( Release::from_github( $this->data(), self::REPO, 'nope.zip' ) );
	}

	public function test_download_from_another_repo_is_refused(): void {
		$data = $this->data(
			[
				'assets' => [
					[
						'name'                 => 'sprint-illustrations.zip',
						'browser_download_url' => 'https://github.com/evil/Repo/releases/download/v0.8.0/sprint-illustrations.zip',
					],
				],
			]
		);

		$this->assertNull( Release::from_github( $data, self::REPO, 'sprint-illustrations.zip' ) );
	}

	public function test_foreign_release_page_falls_back_to_the_repo(): void {
		$release = Release::from_github( $this->data( [ 'html_url' => 'https://evil.example/x' ] ), self::REPO, 'sprint-illustrations.zip' );

		$this->assertSame( 'https://github.com/kohid/Sprint-Illustrations', $release?->url );
	}

	public function test_is_newer_than(): void {
		$release = Release::from_github( $this->data(), self::REPO, 'sprint-illustrations.zip' );

		$this->assertTrue( $release?->is_newer_than( '0.7.0' ) );
		$this->assertTrue( $release?->is_newer_than( '0.7.9' ) );
		$this->assertFalse( $release?->is_newer_than( '0.8.0' ) );
		$this->assertFalse( $release?->is_newer_than( '0.10.0' ) );
	}
}
