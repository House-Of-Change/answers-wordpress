<?php
/**
 * Which market is this request for, and may a base-market feed render in it?
 *
 * A feed is written for ONE market. Its product names, its retailer facts and
 * its claim wording are reviewed for that market, and its structured data is
 * anchored to that market's URL. So the same fragment on another market's
 * rendering of the same page is not "untranslated content", it is wrong
 * content: German retailers offered to Dutch shoppers, and an @id pointing at
 * the German page.
 *
 * That is not hypothetical. A translation layer (TranslatePress, WPML,
 * Polylang) commonly renders ONE WordPress post at several language URLs, so
 * the FAQ block a publisher placed once appears in every market automatically,
 * and nobody can remove it from a page that does not exist as a post.
 *
 * Until per-market feeds exist (phase 1/2 of the market-variants plan), this
 * class answers the narrow question: is the current request the base market? If
 * it is not, the publisher's policy decides between rendering nothing (default)
 * and rendering the base feed anyway.
 *
 * WHAT "UNKNOWN" MEANS, AND WHY IT IS NOT "OTHER". A request whose market we
 * cannot determine is almost always the ordinary, single-language rendering of
 * an ordinary site. Treating unknown as a foreign market would black out every
 * plain WordPress install the moment this shipped. So unknown resolves to base,
 * and only a market we positively identified as different can be suppressed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Market {

    /** Serve nothing in a market this feed was not written for. */
    const FALLBACK_NONE = 'none';
    /** Serve the base feed anyway — the publisher's explicit choice. */
    const FALLBACK_BASE = 'base';

    /** @var array<string,mixed>|null Memoised per request; resolution is pure. */
    private static $resolved = null;

    /** @var int Suppressed placements this request, for the footer diagnostic. */
    private static $suppressed = 0;

    /**
     * The market of the current request as a BCP-47-ish tag ("de-DE", "nl"),
     * plus WHERE it came from — the source is half the answer when a publisher
     * asks why a block did or did not render.
     *
     * @return array{market:string,source:string}
     */
    public static function current(): array {
        if ( self::$resolved !== null ) {
            return self::$resolved;
        }

        $resolved = self::detect();

        /**
         * Final say over the detected market. For sites whose language lives
         * somewhere this class cannot see (a custom router, a headless front
         * end, a translation plugin we have not met yet).
         *
         * @param string $market Detected tag, or '' when nothing was detected.
         * @param string $source Detection source, for logging.
         */
        $market = (string) apply_filters( 'answers_current_market', $resolved['market'], $resolved['source'] );
        if ( $market !== $resolved['market'] ) {
            $resolved = [ 'market' => self::normalise( $market ), 'source' => 'filter' ];
        }

        self::$resolved = $resolved;
        return self::$resolved;
    }

    /**
     * The market the publisher's feeds are written for.
     *
     * NOT get_locale() first, and that is the whole point. TranslatePress, WPML
     * and Polylang all filter `locale` to the language of the CURRENT request,
     * so on precisely the sites this class exists for, get_locale() returns
     * "nl_NL" while serving the Dutch rendering of German content. Deriving the
     * base from it would make base equal current on every request, the
     * comparison would always match, and the gate would be silently inert while
     * looking configured. So we ask each translation plugin for its DEFAULT
     * language, then the raw site-language option (which they do not filter),
     * and only then the runtime locale, which is correct for a site with no
     * translation layer at all.
     */
    public static function base(): string {
        $declared = self::normalise( (string) get_option( 'answers_base_market', '' ) );
        if ( $declared !== '' ) {
            return $declared;
        }

        // TranslatePress keeps its default language in its own settings array.
        $trp = get_option( 'trp_settings', [] );
        if ( is_array( $trp ) && ! empty( $trp['default-language'] ) && is_string( $trp['default-language'] ) ) {
            $tag = self::normalise( $trp['default-language'] );
            if ( $tag !== '' ) {
                return $tag;
            }
        }

        // WPML.
        $wpml_default = apply_filters( 'wpml_default_language', null );
        if ( is_string( $wpml_default ) && $wpml_default !== '' ) {
            $tag = self::normalise( $wpml_default );
            if ( $tag !== '' ) {
                return $tag;
            }
        }

        // Polylang.
        if ( function_exists( 'pll_default_language' ) ) {
            $tag = self::normalise( (string) pll_default_language( 'locale' ) );
            if ( $tag !== '' ) {
                return $tag;
            }
        }

        // The site-language option as stored, unfiltered by any of the above.
        $wplang = get_option( 'WPLANG', '' );
        if ( is_string( $wplang ) && $wplang !== '' ) {
            $tag = self::normalise( $wplang );
            if ( $tag !== '' ) {
                return $tag;
            }
        }

        // A site with no translation layer: the runtime locale IS the base.
        return self::normalise( get_locale() );
    }

    /** The publisher's policy for markets with no feed of their own. */
    public static function fallback(): string {
        $value = (string) get_option( 'answers_market_fallback', self::FALLBACK_NONE );
        return $value === self::FALLBACK_BASE ? self::FALLBACK_BASE : self::FALLBACK_NONE;
    }

    /**
     * May a base-market feed render in this request?
     *
     * @return array{render:bool,reason:string,market:string,base:string,source:string}
     */
    public static function decide(): array {
        $current = self::current();
        $base    = self::base();

        if ( $current['market'] === '' ) {
            return self::verdict( true, 'unknown-market', $current, $base );
        }
        if ( self::same_market( $current['market'], $base ) ) {
            return self::verdict( true, 'base-market', $current, $base );
        }
        if ( self::fallback() === self::FALLBACK_BASE ) {
            return self::verdict( true, 'fallback-base', $current, $base );
        }
        return self::verdict( false, 'suppressed-foreign-market', $current, $base );
    }

    /**
     * The gate every render path passes through. Side-effect free, because it
     * is asked more than once per placement (a stylesheet enqueue asks before
     * the markup does), and never suppresses in the admin or REST context,
     * where "the current market" is a meaningless question and a publisher
     * editing a post should still see their feed.
     */
    public static function may_render(): bool {
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return true;
        }
        return self::decide()['render'];
    }

    /**
     * Record ONE withheld placement. Called only from the single choke point
     * every render path funnels through (Answers_Data_Provider::get_faq_html),
     * so the number in the footer is placements and not gate consultations.
     */
    public static function note_suppressed(): void {
        self::$suppressed++;
    }

    /**
     * One HTML comment per request, only when something was actually withheld.
     * Invisible to readers, and the first thing a developer greps for when a
     * FAQ block they placed is not on the page. It is also how we can confirm
     * the policy remotely, without asking anyone to open wp-admin.
     */
    public static function render_diagnostic(): void {
        if ( self::$suppressed < 1 ) {
            return;
        }

        $decision = self::decide();
        printf(
            "\n<!-- info.link/answers: %d FAQ block(s) not rendered here. This request is market %s (detected via %s); the feeds are written for %s. Publish a feed for this market, or set the fallback to serve the base market anyway. -->\n",
            (int) self::$suppressed,
            esc_html( $decision['market'] ),
            esc_html( $decision['source'] ),
            esc_html( $decision['base'] )
        );
    }

    /**
     * One shape for every decision, so callers (and the diagnostic) never have
     * to reconstruct the market or the reason from the boolean.
     *
     * @param array{market:string,source:string} $current
     * @return array{render:bool,reason:string,market:string,base:string,source:string}
     */
    private static function verdict( bool $render, string $reason, array $current, string $base ): array {
        return [
            'render' => $render,
            'reason' => $reason,
            'market' => $current['market'],
            'base'   => $base,
            'source' => $current['source'],
        ];
    }

    // ─── resolution ──────────────────────────────────────────────────────────

    /**
     * The signal ladder. Translation plugins first: they know the answer, and
     * they know it for setups (domain per language, cookie, session) that no
     * URL inspection can see. WordPress core next. The URL last, and only for
     * prefixes the publisher declared — see market_from_url().
     *
     * @return array{market:string,source:string}
     */
    private static function detect(): array {
        // TranslatePress. The global holds the current language as a WP locale;
        // the helper is not present in every version, so try both.
        if ( function_exists( 'trp_get_current_language' ) ) {
            $tag = self::normalise( (string) trp_get_current_language() );
            if ( $tag !== '' ) {
                return [ 'market' => $tag, 'source' => 'translatepress' ];
            }
        }
        if ( ! empty( $GLOBALS['TRP_LANGUAGE'] ) && is_string( $GLOBALS['TRP_LANGUAGE'] ) ) {
            return [ 'market' => self::normalise( $GLOBALS['TRP_LANGUAGE'] ), 'source' => 'translatepress' ];
        }

        // WPML. The filter is the documented API and returns a language code.
        $wpml = apply_filters( 'wpml_current_language', null );
        if ( is_string( $wpml ) && $wpml !== '' && $wpml !== 'all' ) {
            return [ 'market' => self::normalise( $wpml ), 'source' => 'wpml' ];
        }
        if ( defined( 'ICL_LANGUAGE_CODE' ) && is_string( ICL_LANGUAGE_CODE ) && ICL_LANGUAGE_CODE !== '' ) {
            return [ 'market' => self::normalise( ICL_LANGUAGE_CODE ), 'source' => 'wpml' ];
        }

        // Polylang.
        if ( function_exists( 'pll_current_language' ) ) {
            $tag = self::normalise( (string) pll_current_language( 'locale' ) );
            if ( $tag !== '' ) {
                return [ 'market' => $tag, 'source' => 'polylang' ];
            }
        }

        $from_url = self::market_from_url();
        if ( $from_url !== '' ) {
            return [ 'market' => $from_url, 'source' => 'url' ];
        }

        // WordPress core. On the front end this is the site locale, so it
        // usually equals the base and renders — which is the intent.
        if ( function_exists( 'determine_locale' ) ) {
            $tag = self::normalise( determine_locale() );
            if ( $tag !== '' ) {
                return [ 'market' => $tag, 'source' => 'wordpress' ];
            }
        }

        return [ 'market' => '', 'source' => 'none' ];
    }

    /**
     * A market from the request path or host, restricted to prefixes the
     * publisher declared (`answers_market_prefixes`, e.g. "nl, fr-BE").
     *
     * DELIBERATELY NOT A PATTERN GUESS. Any two-letter first segment looks like
     * a language: /it/ is Italian on one site and "IT services" on another, and
     * guessing wrong here does not mis-label anything, it DELETES the FAQ block
     * from a page that was fine. So an undeclared site keeps today's behaviour
     * and we leave a way in for developers via the filter.
     */
    private static function market_from_url(): string {
        $declared = array_filter( array_map(
            [ __CLASS__, 'normalise' ],
            explode( ',', (string) get_option( 'answers_market_prefixes', '' ) )
        ) );
        if ( empty( $declared ) ) {
            return '';
        }

        $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
        $first = $path === '' ? '' : self::normalise( explode( '/', $path )[0] );

        $host = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
        $sub  = strpos( $host, '.' ) !== false ? self::normalise( explode( '.', $host )[0] ) : '';

        foreach ( $declared as $tag ) {
            if ( $tag !== '' && ( $tag === $first || $tag === $sub ) ) {
                return $tag;
            }
        }
        return '';
    }

    /**
     * "de_DE", "de-de", "de_DE.UTF-8", "nl" → "de-DE", "de-DE", "de-DE", "nl".
     * Anything that is not a plausible language tag normalises to '' rather
     * than to a guess, because '' means "unknown" and unknown renders.
     */
    public static function normalise( string $raw ): string {
        $tag = trim( $raw );
        if ( $tag === '' ) {
            return '';
        }
        // Locale extras a WP locale can carry: "de_DE.UTF-8", "ca@valencia".
        $tag = preg_replace( '/[.@].*$/', '', $tag );
        $tag = str_replace( '_', '-', (string) $tag );
        $parts = explode( '-', $tag );
        $lang  = strtolower( $parts[0] );
        if ( ! preg_match( '/^[a-z]{2,3}$/', $lang ) ) {
            return '';
        }
        if ( ! isset( $parts[1] ) || $parts[1] === '' ) {
            return $lang;
        }
        $region = strtoupper( $parts[1] );
        if ( ! preg_match( '/^[A-Z]{2}$/', $region ) ) {
            return $lang;
        }
        return $lang . '-' . $region;
    }

    /**
     * Same market? Exact match, or same language when either side has no
     * region.
     *
     * PHASE-0 LIMIT, ON PURPOSE: this makes de-AT equal to de-DE, so an
     * Austrian rendering still gets the German feed. The exposure this release
     * exists to close is cross-LANGUAGE (German answers on Dutch pages), and a
     * region-strict comparison here would suppress content for regions no
     * publisher has declared yet. Region-level markets arrive with the per-
     * market feed map, which is the point at which they become expressible.
     */
    public static function same_market( string $a, string $b ): bool {
        $a = self::normalise( $a );
        $b = self::normalise( $b );
        if ( $a === '' || $b === '' ) {
            return true;
        }
        if ( $a === $b ) {
            return true;
        }
        return explode( '-', $a )[0] === explode( '-', $b )[0];
    }
}
