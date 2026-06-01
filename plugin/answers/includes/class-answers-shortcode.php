<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Shortcode {

    public static function init(): void {
        add_shortcode( 'answers_faq', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ): string {
        $atts = shortcode_atts( [
            'set'         => '',
            'heading'     => 'Frequently Asked Questions',
            'show_source' => 'yes',
            'heading_tag' => 'h2',
        ], $atts, 'answers_faq' );

        $set_id = sanitize_text_field( $atts['set'] );
        if ( empty( $set_id ) ) {
            $set_id = get_option( 'answers_default_set', '' );
        }

        if ( empty( $set_id ) ) {
            return '';
        }

        $html = Answers_Data_Provider::get_faq_html( $set_id, $atts );
        if ( $html === '' ) {
            return '';
        }

        wp_enqueue_style( 'answers-style' );

        return $html;
    }
}
