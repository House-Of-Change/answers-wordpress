<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Data_Provider {

    private static $json_cache = null;

    /**
     * Return the FAQ markup to inject for a feed.
     *
     * When an API URL is configured the plugin fetches the rendered HTML feed
     * (always ?format=html) and injects it verbatim — the app owns the markup,
     * styling, and embedded FAQPage JSON-LD. Any presentation options provided
     * are forwarded as query params; whatever is omitted is left to the API,
     * which falls back to the preset the publisher chose when the snapshot was
     * captured. If the request fails, or no API URL is set at all, the local
     * JSON sample is rendered in PHP as a fallback.
     *
     * @param string $feed_id Publish-feed ID (UUID).
     * @param array  $options Optional presentation overrides forwarded to the
     *                        API: slug, variant, styling, heading_level, hide.
     *                        Only valid, non-empty values are sent; anything
     *                        absent defers to the published default preset.
     */
    public static function get_faq_html( string $feed_id, array $options = [] ): string {
        $api_url = get_option( 'answers_api_url', ANSWERS_DEFAULT_API_URL );

        if ( ! empty( $api_url ) ) {
            $html = self::get_html_from_api( $feed_id, $api_url, $options );
            if ( $html !== null ) {
                return $html;
            }

            // API unreachable — render the local sample instead. The wp_head
            // JSON-LD hook is disabled whenever an API URL is configured (the
            // API HTML normally carries its own), so emit it inline here.
            $faqs = self::get_faqs_from_json( $feed_id );
            if ( empty( $faqs ) ) {
                return '';
            }
            return Answers_Renderer::render_html( $faqs )
                 . Answers_Renderer::render_jsonld( $faqs );
        }

        $faqs = self::get_faqs_from_json( $feed_id );
        if ( empty( $faqs ) ) {
            return '';
        }
        return Answers_Renderer::render_html( $faqs );
    }

    /**
     * FAQ array for a feed. Used for the wp_head JSON-LD injection in
     * local-only mode (no API URL configured).
     */
    public static function get_faqs( string $feed_id ): array {
        return self::get_faqs_from_json( $feed_id );
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
     * Fetch the rendered FAQ HTML from the publish feed.
     *
     * Always queries with ?format=html and replies with a JSON envelope
     * { html, meta } — `html` is the rendered markup that bakes in the chosen
     * styling and, when enabled, the FAQPage JSON-LD. Presentation options are
     * forwarded only when supplied; with none, the API serves the default
     * preset the publisher captured with the snapshot. The fragment is injected
     * verbatim, so the response is trusted — a first-party endpoint over HTTPS.
     *
     * @param array $options slug / variant / styling / heading_level / hide.
     * @return string|null   HTML on success (possibly an empty string if the
     *                       feed has no published FAQs), or null if the request
     *                       failed and the caller should fall back.
     */
    private static function get_html_from_api( string $feed_id, string $api_url, array $options = [] ): ?string {
        $url = trailingslashit( $api_url ) . urlencode( $feed_id );
        $url = add_query_arg( self::build_query_args( $options ), $url );

        // A TTL of 0 (or less) means "don't cache" — fetch fresh every time.
        // (WordPress treats set_transient( …, 0 ) as *never expire*, which
        // would otherwise pin stale HTML in place after a re-publish.)
        $ttl           = (int) get_option( 'answers_cache_ttl', 3600 );
        $cache_enabled = $ttl > 0;

        // Key the cache on the full request URL so different option
        // combinations (and different feeds) never share a cached entry.
        $cache_key = 'answers_html_' . md5( $url );

        if ( $cache_enabled ) {
            $cached = get_transient( $cache_key );
            if ( is_string( $cached ) ) {
                return $cached;
            }
        }

        $api_key = get_option( 'answers_api_key', '' );

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

        // The feed wraps the markup in a JSON envelope: { html, meta }.
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['html'] ) || ! is_string( $decoded['html'] ) ) {
            self::log_api_failure( $url, 'unexpected response shape (no html field)' );
            return null;
        }

        $html = $decoded['html'];

        if ( $cache_enabled ) {
            set_transient( $cache_key, $html, $ttl );
        }

        return $html;
    }

    /**
     * Translate shortcode-style options into publish-feed query args.
     *
     * `format=html` and `variant=embedded` are always set: we need the
     * embeddable fragment (no <html>/<head> chrome) so the markup can be
     * injected into a page — `standalone` returns a full document and is only
     * honored when a shortcode explicitly asks for it. Every other option is
     * forwarded only when present and valid; anything omitted (or invalid) is
     * left out so the API applies the publisher's default preset.
     */
    private static function build_query_args( array $options ): array {
        $query = [ 'format' => 'html' ];

        // Default to the embeddable fragment; allow an explicit override.
        $query['variant'] = ( ! empty( $options['variant'] ) && in_array( $options['variant'], [ 'embedded', 'standalone' ], true ) )
            ? $options['variant']
            : 'embedded';

        if ( ! empty( $options['slug'] ) ) {
            $query['slug'] = (string) $options['slug'];
        }

        if ( ! empty( $options['styling'] ) && in_array( $options['styling'], [ 'scoped', 'full', 'none' ], true ) ) {
            $query['styling'] = $options['styling'];
        }

        if ( ! empty( $options['heading_level'] ) && in_array( (int) $options['heading_level'], [ 1, 2, 3 ], true ) ) {
            $query['headingLevel'] = (int) $options['heading_level'];
        }

        if ( ! empty( $options['hide'] ) ) {
            $hide = is_array( $options['hide'] ) ? $options['hide'] : explode( ',', (string) $options['hide'] );
            $hide = array_filter( array_map( 'trim', $hide ) );
            if ( ! empty( $hide ) ) {
                $query['hide'] = implode( ',', $hide );
            }
        }

        return $query;
    }

    private static function log_api_failure( string $url, string $reason ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[answers] API request to %s failed (%s); falling back to local JSON.', $url, $reason ) );
        }
    }
}
