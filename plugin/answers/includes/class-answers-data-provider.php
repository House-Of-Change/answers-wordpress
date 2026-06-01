<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Data_Provider {

    private static $json_cache = null;

    /**
     * Return the FAQ markup to inject for a set.
     *
     * When an API URL is configured the plugin fetches the pre-rendered HTML
     * feed (?format=html) and injects it verbatim — the app owns the markup,
     * the CSS (full or basic, per its publish settings) and the embedded
     * FAQPage JSON-LD. If the request fails, or no API URL is set at all, the
     * local JSON sample is rendered in PHP as a fallback.
     *
     * @param string $faq_set_id Publish-feed set ID (or UUID).
     * @param array  $attrs      Presentation attrs (heading/heading_tag/
     *                           show_source). Honored only by the local PHP
     *                           renderer; the API HTML controls its own.
     */
    public static function get_faq_html( string $faq_set_id, array $attrs = [] ): string {
        $api_url = get_option( 'answers_api_url', '' );

        if ( ! empty( $api_url ) ) {
            $html = self::get_html_from_api( $faq_set_id, $api_url );
            if ( $html !== null ) {
                return $html;
            }

            // API unreachable — render the local sample instead. The wp_head
            // JSON-LD hook is disabled whenever an API URL is configured (the
            // API HTML normally carries its own), so emit it inline here.
            $faqs = self::get_faqs_from_json( $faq_set_id );
            if ( empty( $faqs ) ) {
                return '';
            }
            return Answers_Renderer::render_html( $faqs, $attrs )
                 . Answers_Renderer::render_jsonld( $faqs );
        }

        $faqs = self::get_faqs_from_json( $faq_set_id );
        if ( empty( $faqs ) ) {
            return '';
        }
        return Answers_Renderer::render_html( $faqs, $attrs );
    }

    /**
     * FAQ array for a set. Used for the wp_head JSON-LD injection in
     * local-only mode (no API URL configured).
     */
    public static function get_faqs( string $faq_set_id ): array {
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

    /**
     * Fetch the pre-rendered FAQ HTML fragment from the publish feed.
     *
     * The endpoint is queried with ?format=html&variant=embedded and replies
     * with a JSON envelope { html, meta } — `html` is the embeddable fragment
     * (no <html>/<head> chrome) that bakes in the publisher's chosen styling
     * (scoped / full / none) and, when enabled, the FAQPage JSON-LD. The
     * `embedded` variant is requested deliberately: the `standalone` variant is
     * a complete <!DOCTYPE html> document and cannot be injected into a page.
     *
     * The fragment is injected verbatim, so the response is trusted — this is a
     * first-party endpoint reached over HTTPS.
     *
     * @return string|null Fragment HTML on success (possibly an empty string if
     *                     the set has no published FAQs), or null if the
     *                     request failed and the caller should fall back.
     */
    private static function get_html_from_api( string $faq_set_id, string $api_url ): ?string {
        $cache_key = 'answers_html_' . md5( $faq_set_id );
        $cached    = get_transient( $cache_key );

        if ( is_string( $cached ) ) {
            return $cached;
        }

        $api_key = get_option( 'answers_api_key', '' );
        $ttl     = (int) get_option( 'answers_cache_ttl', 3600 );
        $url     = trailingslashit( $api_url ) . urlencode( $faq_set_id );
        $url     = add_query_arg(
            [
                'format'  => 'html',
                'variant' => 'embedded',
            ],
            $url
        );

        $args = [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if ( ! empty( $api_key ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            self::log_api_failure(
                $url,
                is_wp_error( $response )
                    ? $response->get_error_message()
                    : 'HTTP ' . wp_remote_retrieve_response_code( $response )
            );
            return null;
        }

        // The feed wraps the fragment in a JSON envelope: { html, meta }.
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['html'] ) || ! is_string( $decoded['html'] ) ) {
            self::log_api_failure( $url, 'unexpected response shape (no html field)' );
            return null;
        }

        $html = $decoded['html'];

        set_transient( $cache_key, $html, $ttl );

        return $html;
    }

    private static function log_api_failure( string $url, string $reason ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[answers] API request to %s failed (%s); falling back to local JSON.', $url, $reason ) );
        }
    }
}
