<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Shortcode {

    public static function init(): void {
        add_shortcode( 'answers_faq', [ __CLASS__, 'render' ] );
    }

    public static function render( $atts ): string {
        // Attributes mirror the publish-feed query params. Every option except
        // `id` is optional; whatever is omitted defers to the default preset
        // the publisher captured with the snapshot.
        $atts = shortcode_atts( [
            'id'            => '',
            'slug'          => '',
            'variant'       => '',
            'styling'       => '',
            'heading_level' => '',
            'hide'          => '',
        ], $atts, 'answers_faq' );

        $feed_id = sanitize_text_field( $atts['id'] );
        if ( empty( $feed_id ) ) {
            $feed_id = get_option( 'answers_default_set', '' );
        }

        if ( empty( $feed_id ) ) {
            return '';
        }

        $options = [
            'slug'          => sanitize_text_field( $atts['slug'] ),
            'variant'       => sanitize_key( $atts['variant'] ),
            'styling'       => sanitize_key( $atts['styling'] ),
            'heading_level' => sanitize_text_field( $atts['heading_level'] ),
            'hide'          => sanitize_text_field( $atts['hide'] ),
        ];

        $html = Answers_Data_Provider::get_faq_html( $feed_id, $options );
        if ( $html === '' ) {
            return '';
        }

        wp_enqueue_style( 'answers-style' );

        return $html;
    }
}
