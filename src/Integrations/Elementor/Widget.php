<?php
/**
 * "Sprint Illustration" Elementor widget.
 *
 * @package SprintIllustrations
 */

declare( strict_types=1 );

namespace SprintIllustrations\Integrations\Elementor;

use SprintIllustrations\Plugin;

/**
 * Classic widget rendered on the server in both the editor preview and the front end.
 */
final class Widget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'sprint-illustration';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'Sprint Illustration', 'sprint-illustrations' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-image';
	}

	/**
	 * Panel categories.
	 *
	 * @return array<string>
	 */
	public function get_categories(): array {
		return [ 'general' ];
	}

	/**
	 * Search keywords.
	 *
	 * @return array<string>
	 */
	public function get_keywords(): array {
		return [ 'illustration', 'svg', 'sprint', 'hero', 'image' ];
	}

	/**
	 * Styles to load with the widget.
	 *
	 * @return array<string>
	 */
	public function get_style_depends(): array {
		return [ Plugin::STYLE_HANDLE ];
	}

	/**
	 * Controls.
	 */
	protected function register_controls(): void {
		$choices = Plugin::instance()->editor_choices();

		$this->start_controls_section( 'illustration', [ 'label' => esc_html__( 'Illustration', 'sprint-illustrations' ) ] );

		$this->add_control(
			'template',
			[
				'label'   => esc_html__( 'Template', 'sprint-illustrations' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => [ '' => esc_html__( 'Automatic (from keywords)', 'sprint-illustrations' ) ] + array_column( $choices['templates'], 'label', 'value' ),
			]
		);
		$this->add_control(
			'keywords',
			[
				'label'       => esc_html__( 'Keywords', 'sprint-illustrations' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'team, remote', 'sprint-illustrations' ),
				'label_block' => true,
			]
		);
		$this->add_control(
			'seed',
			[
				'label'   => esc_html__( 'Seed', 'sprint-illustrations' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 1,
			]
		);
		$this->add_control(
			'palette',
			[
				'label'   => esc_html__( 'Palette', 'sprint-illustrations' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'site',
				'options' => [ 'site' => esc_html__( 'Site palette', 'sprint-illustrations' ) ] + array_column( $choices['presets'], 'label', 'value' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'accessibility', [ 'label' => esc_html__( 'Accessibility', 'sprint-illustrations' ) ] );

		$this->add_control(
			'title',
			[
				'label'       => esc_html__( 'Title (alt text)', 'sprint-illustrations' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Leave blank to use the template name.', 'sprint-illustrations' ),
				'label_block' => true,
			]
		);
		$this->add_control(
			'decorative',
			[
				'label'        => esc_html__( 'Decorative (hide from screen readers)', 'sprint-illustrations' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render (front end and editor preview).
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$html     = Plugin::instance()->renderer()->render(
			[
				'template'   => (string) ( $settings['template'] ?? '' ),
				'keywords'   => (string) ( $settings['keywords'] ?? '' ),
				'seed'       => $settings['seed'] ?? 1,
				'palette'    => (string) ( $settings['palette'] ?? 'site' ),
				'title'      => (string) ( $settings['title'] ?? '' ),
				'decorative' => 'yes' === ( $settings['decorative'] ?? '' ),
			],
			current_user_can( 'edit_posts' )
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized SVG from the composer.
	}
}
