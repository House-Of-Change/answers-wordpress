<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Admin {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function add_settings_page(): void {
        add_options_page(
            'info.link/answers',
            'info.link/answers',
            'manage_options',
            'answers-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function register_settings(): void {
        register_setting( 'answers_settings', 'answers_api_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => ANSWERS_DEFAULT_API_URL,
        ] );
        register_setting( 'answers_settings', 'answers_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'answers_settings', 'answers_cache_ttl', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3600,
        ] );
        register_setting( 'answers_settings', 'answers_default_feed', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'answers_settings', 'answers_base_market', [
            'type'              => 'string',
            'sanitize_callback' => [ __CLASS__, 'sanitize_market' ],
            'default'           => '',
        ] );
        register_setting( 'answers_settings', 'answers_market_fallback', [
            'type'              => 'string',
            'sanitize_callback' => [ __CLASS__, 'sanitize_fallback' ],
            'default'           => Answers_Market::FALLBACK_NONE,
        ] );
        register_setting( 'answers_settings', 'answers_market_prefixes', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        add_settings_section(
            'answers_main_section',
            'API Configuration',
            function () {
                echo '<p>Configure the API connection. Leave API URL empty to use the local JSON data file.</p>';
            },
            'answers-settings'
        );

        add_settings_field( 'answers_api_url', 'API URL', [ __CLASS__, 'render_text_field' ], 'answers-settings', 'answers_main_section', [
            'name'        => 'answers_api_url',
            'placeholder' => 'https://answers.info.link/api/publish',
        ] );
        add_settings_field( 'answers_api_key', 'API Key', [ __CLASS__, 'render_text_field' ], 'answers-settings', 'answers_main_section', [
            'name'        => 'answers_api_key',
            'placeholder' => 'sk-...',
            'type'        => 'password',
        ] );
        add_settings_field( 'answers_cache_ttl', 'Cache Duration (seconds)', [ __CLASS__, 'render_text_field' ], 'answers-settings', 'answers_main_section', [
            'name'        => 'answers_cache_ttl',
            'placeholder' => '3600',
            'type'        => 'number',
        ] );
        add_settings_field( 'answers_default_feed', 'Default Feed ID', [ __CLASS__, 'render_text_field' ], 'answers-settings', 'answers_main_section', [
            'name'        => 'answers_default_feed',
            'placeholder' => '8da215bd-62a2-45e7-b6b2-70dafae4b57f',
        ] );

        add_settings_section(
            'answers_market_section',
            'Languages and markets',
            function () {
                $decision = Answers_Market::decide();
                echo '<p>FAQs are written and reviewed for one market: its product names, its retailers, its claim wording. If your site serves several languages from the same pages, these settings decide what happens on the pages this content was not written for.</p>';
                printf(
                    '<p><strong>Right now:</strong> this admin request looks like <code>%s</code> (detected via %s) and your content is set up for <code>%s</code>.</p>',
                    esc_html( $decision['market'] !== '' ? $decision['market'] : 'unknown' ),
                    esc_html( $decision['source'] ),
                    esc_html( $decision['base'] )
                );
            },
            'answers-settings'
        );

        add_settings_field( 'answers_base_market', 'Content language', [ __CLASS__, 'render_text_field' ], 'answers-settings', 'answers_market_section', [
            'name'        => 'answers_base_market',
            'placeholder' => Answers_Market::base(),
            'description' => 'The language your FAQs are written in, e.g. de-DE. Leave empty to use this site\'s own language.',
        ] );
        add_settings_field( 'answers_market_fallback', 'On other languages', [ __CLASS__, 'render_fallback_field' ], 'answers-settings', 'answers_market_section' );
        add_settings_field( 'answers_market_prefixes', 'Language URL prefixes', [ __CLASS__, 'render_text_field' ], 'answers-settings', 'answers_market_section', [
            'name'        => 'answers_market_prefixes',
            'placeholder' => 'nl, fr-BE',
            'description' => 'Only needed if your translation plugin is not detected automatically. Comma-separated, matching the first part of your translated URLs (/nl/…) or a subdomain (nl.example.com). Left empty, URLs are never guessed at: a page whose address starts with a two-letter word is not necessarily a translation.',
        ] );
    }

    public static function render_text_field( array $args ): void {
        $name        = $args['name'];
        $value       = get_option( $name, '' );
        $placeholder = $args['placeholder'] ?? '';
        $type        = $args['type'] ?? 'text';

        printf(
            '<input type="%s" name="%s" value="%s" placeholder="%s" class="regular-text" />',
            esc_attr( $type ),
            esc_attr( $name ),
            esc_attr( $value ),
            esc_attr( $placeholder )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * The one setting a publisher should think about rather than accept: on a
     * language the FAQs were not written for, silence or the base content.
     * Silence is the default because the alternative offers, for example, German
     * retailers to Dutch shoppers.
     */
    public static function render_fallback_field(): void {
        $current = Answers_Market::fallback();
        $choices = [
            Answers_Market::FALLBACK_NONE => 'Show nothing (recommended) — no FAQs on pages in another language until that language has its own set.',
            Answers_Market::FALLBACK_BASE => 'Show the ' . Answers_Market::base() . ' FAQs anyway — some content is better than none, and search engines can read it.',
        ];

        foreach ( $choices as $value => $label ) {
            printf(
                '<label style="display:block;margin-bottom:6px"><input type="radio" name="answers_market_fallback" value="%s" %s /> %s</label>',
                esc_attr( $value ),
                checked( $current, $value, false ),
                esc_html( $label )
            );
        }
    }

    /**
     * Normalise a declared market, tolerating whatever the form posts. Not
     * Answers_Market::normalise() directly: that takes a string, and a posted
     * array would be a fatal instead of a rejected value.
     */
    public static function sanitize_market( $value ): string {
        return is_string( $value ) ? Answers_Market::normalise( $value ) : '';
    }

    public static function sanitize_fallback( $value ): string {
        return $value === Answers_Market::FALLBACK_BASE
            ? Answers_Market::FALLBACK_BASE
            : Answers_Market::FALLBACK_NONE;
    }

    public static function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>info.link/answers Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'answers_settings' );
                do_settings_sections( 'answers-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
