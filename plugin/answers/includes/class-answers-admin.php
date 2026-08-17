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
