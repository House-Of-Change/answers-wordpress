<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor widget that wraps the `[answers_faq]` shortcode.
 *
 * This class extends \Elementor\Widget_Base and must therefore only be loaded
 * after Elementor is confirmed present — see Answers_Elementor::load_widget_class().
 * render() builds the shortcode from the control values and defers to the
 * existing shortcode callback, so no rendering logic is duplicated here.
 */
class Answers_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'answers_faq';
    }

    public function get_title(): string {
        return __( 'Answers FAQ', 'answers' );
    }

    public function get_icon(): string {
        return 'eicon-help-o';
    }

    public function get_categories(): array {
        return [ 'general' ];
    }

    public function get_keywords(): array {
        return [ 'faq', 'answers', 'questions', 'seo' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'answers_section', [
            'label' => __( 'Answers FAQ', 'answers' ),
        ] );

        $this->add_control( 'id', [
            'label'       => __( 'Feed ID', 'answers' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __( 'Leave empty to use the default feed set in Answers settings.', 'answers' ),
        ] );

        $this->add_control( 'slug', [
            'label'       => __( 'Slug', 'answers' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __( 'Optional. Overrides the slug captured with the snapshot.', 'answers' ),
        ] );

        $this->add_control( 'variant', [
            'label'   => __( 'Variant', 'answers' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''           => __( 'Default (publisher preset)', 'answers' ),
                'embedded'   => __( 'Embedded', 'answers' ),
                'standalone' => __( 'Standalone', 'answers' ),
            ],
        ] );

        $this->add_control( 'styling', [
            'label'   => __( 'Styling', 'answers' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''       => __( 'Default (publisher preset)', 'answers' ),
                'scoped' => __( 'Scoped', 'answers' ),
                'full'   => __( 'Full', 'answers' ),
                'none'   => __( 'None', 'answers' ),
            ],
        ] );

        $this->add_control( 'heading_level', [
            'label'   => __( 'Heading level', 'answers' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''  => __( 'Default (publisher preset)', 'answers' ),
                '1' => 'H1',
                '2' => 'H2',
                '3' => 'H3',
            ],
        ] );

        $this->add_control( 'hide', [
            'label'       => __( 'Hide', 'answers' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __( 'Comma-separated list of parts to hide.', 'answers' ),
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $atts = '';
        foreach ( [ 'id', 'slug', 'variant', 'styling', 'heading_level', 'hide' ] as $key ) {
            $value = isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
            if ( $value !== '' ) {
                $atts .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
            }
        }

        // Output flows through the existing [answers_faq] render callback.
        echo do_shortcode( '[answers_faq' . $atts . ']' );
    }
}
