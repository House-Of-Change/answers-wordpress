<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Hooks {

    public static function init(): void {
        add_filter( 'the_content', [ __CLASS__, 'append_to_content' ], 99 );
        add_filter( 'woocommerce_product_tabs', [ __CLASS__, 'add_product_tab' ] );
        add_action( 'wp_head', [ __CLASS__, 'inject_jsonld' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_styles' ] );
    }

    public static function register_styles(): void {
        wp_register_style(
            'answers-style',
            ANSWERS_URL . 'assets/css/answers.css',
            [],
            ANSWERS_VERSION
        );

        if ( is_singular() ) {
            $set_id = get_post_meta( get_the_ID(), '_answers_set_id', true );
            if ( ! empty( $set_id ) ) {
                wp_enqueue_style( 'answers-style' );
            }
        }
    }

    public static function append_to_content( string $content ): string {
        if ( ! is_singular() || ! is_main_query() ) {
            return $content;
        }

        if ( function_exists( 'is_product' ) && is_product() ) {
            return $content;
        }

        $set_id = get_post_meta( get_the_ID(), '_answers_set_id', true );
        if ( empty( $set_id ) ) {
            return $content;
        }

        $html = Answers_Data_Provider::get_faq_html( $set_id );
        if ( $html === '' ) {
            return $content;
        }

        wp_enqueue_style( 'answers-style' );

        return $content . $html;
    }

    public static function add_product_tab( array $tabs ): array {
        global $product;

        if ( ! $product ) {
            return $tabs;
        }

        $set_id = get_post_meta( $product->get_id(), '_answers_set_id', true );
        if ( empty( $set_id ) ) {
            return $tabs;
        }

        // Resolve the markup now (cached in a transient) so we only add the tab
        // when there is actually content — covers API-only feeds that aren't in
        // the local sample. render_product_tab() reuses the cached result.
        $html = Answers_Data_Provider::get_faq_html( $set_id );
        if ( $html === '' ) {
            return $tabs;
        }

        $tabs['answers'] = [
            'title'    => __( 'FAQ', 'answers' ),
            'priority' => 30,
            'callback' => [ __CLASS__, 'render_product_tab' ],
        ];

        return $tabs;
    }

    public static function render_product_tab(): void {
        global $product;

        if ( ! $product ) {
            return;
        }

        $set_id = get_post_meta( $product->get_id(), '_answers_set_id', true );

        echo Answers_Data_Provider::get_faq_html( $set_id );
    }

    public static function inject_jsonld(): void {
        if ( ! is_singular() ) {
            return;
        }

        // When an API URL is configured the pre-rendered HTML carries its own
        // FAQPage JSON-LD (and the local fallback emits it inline), so skip the
        // head injection to avoid duplicate structured data.
        if ( ! empty( get_option( 'answers_api_url', '' ) ) ) {
            return;
        }

        $set_id = get_post_meta( get_the_ID(), '_answers_set_id', true );
        if ( empty( $set_id ) ) {
            return;
        }

        $faqs = Answers_Data_Provider::get_faqs( $set_id );
        if ( empty( $faqs ) ) {
            return;
        }

        echo Answers_Renderer::render_jsonld( $faqs );
    }
}
