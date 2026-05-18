<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Data_Provider {

    private static $json_cache = null;

    public static function get_faqs( string $faq_set_id ): array {
        $api_url = get_option( 'answers_api_url', '' );

        if ( ! empty( $api_url ) ) {
            return self::get_faqs_from_api( $faq_set_id, $api_url );
        }

        return self::get_faqs_from_json( $faq_set_id );
    }

    private static function get_faqs_from_json( string $faq_set_id ): array {
        if ( self::$json_cache === null ) {
            $file = ANSWERS_PATH . 'data/sample-faqs.json';
            if ( ! file_exists( $file ) ) {
                return [];
            }
            $contents = file_get_contents( $file );
            self::$json_cache = json_decode( $contents, true );
            if ( ! is_array( self::$json_cache ) ) {
                self::$json_cache = [];
            }
        }

        if ( isset( self::$json_cache[ $faq_set_id ]['faqs'] ) ) {
            return self::$json_cache[ $faq_set_id ]['faqs'];
        }

        return [];
    }

    private static function get_faqs_from_api( string $faq_set_id, string $api_url ): array {
        $cache_key = 'answers_' . md5( $faq_set_id );
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        $api_key  = get_option( 'answers_api_key', '' );
        $ttl      = (int) get_option( 'answers_cache_ttl', 3600 );
        $url      = trailingslashit( $api_url ) . 'faqs/' . urlencode( $faq_set_id );

        $args = [
            'timeout' => 10,
            'headers' => [],
        ];

        if ( ! empty( $api_key ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return self::get_faqs_from_json( $faq_set_id );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $faqs = $body['faqs'] ?? [];

        set_transient( $cache_key, $faqs, $ttl );

        return $faqs;
    }
}
