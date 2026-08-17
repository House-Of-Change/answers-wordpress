<?php
/**
 * Which market is this request for, and is the fragment we got back written for
 * it?
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
 * WHERE THE DECISIONS LIVE, AND WHY. This class detects a FACT — the market of
 * the current request, which only WordPress can know — and the plugin sends it
 * with every feed request. Every POLICY (which feed a market gets, whether a
 * market with no feed of its own may see another market's content) belongs to
 * the platform, where an operator changes it for a client in seconds. So the
 * plugin carries no market settings at all: nothing to configure on a site we
 * may not have admin access to, and nothing a client has to be walked through
 * before their pages are right.
 *
 * The one rule the plugin does hold is the one it cannot delegate, because it is
 * about the response in its hand: render the fragment unless the fragment says
 * it belongs to a market that is not this one.
 *
 * WHAT "UNKNOWN" MEANS, AND WHY IT IS NOT "OTHER". A request whose market we
 * cannot determine, or a fragment that declares no market, renders. Anything
 * else would black out every single-language site the moment this shipped, and
 * would make a client's pages depend on a field the platform may not be sending
 * yet. Only a positive mismatch withholds anything.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Market {

    /** @var array<string,string>|null Memoised per request; detection is pure. */
    private static $resolved = null;

    /** @var int Withheld placements this request, for the footer diagnostic. */
    private static $suppressed = 0;

    /** @var string The market of the last fragment withheld, for the same. */
    private static $withheld_from = '';

    /**
     * The market of the current request as a BCP-47-ish tag ("de-DE", "nl"),
     * plus WHERE it came from — the source is half the answer when someone asks
     * why a block did or did not render.
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
     * May this fragment render in this request? Counts and remembers what it
     * withheld, so the footer can explain itself.
     *
     * @param array  $meta The response envelope's `meta` object.
     * @param string $html The fragment itself.
     */
    public static function may_render( array $meta, string $html ): bool {
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return true;
        }

        $ours   = self::current()['market'];
        $theirs = self::market_of( $meta, $html );

        if ( $ours === '' || $theirs === '' ) {
            return true; // unknown on either side renders — see the file header.
        }
        if ( self::same_market( $ours, $theirs ) ) {
            return true;
        }

        self::$suppressed++;
        self::$withheld_from = $theirs;
        return false;
    }

    /**
     * The market a fragment belongs to.
     *
     * `meta.market` is the field the platform sends, and the only one worth
     * trusting long-term: it also lets the platform say "*" for content with no
     * market specifics, which is a policy call and belongs there. Until it
     * exists, the fragment's own JSON-LD `inLanguage` is the honest interim
     * signal: it is generated from the tenant's declared locale and is already
     * present in every fragment that has a resolvable identity.
     */
    public static function market_of( array $meta, string $html ): string {
        if ( isset( $meta['market'] ) && is_string( $meta['market'] ) ) {
            // A deliberate wildcard: content the platform says is safe anywhere.
            if ( trim( $meta['market'] ) === '*' ) {
                return '';
            }
            $tag = self::normalise( $meta['market'] );
            if ( $tag !== '' ) {
                return $tag;
            }
        }

        if ( preg_match( '/"inLanguage"\s*:\s*"([A-Za-z_-]{2,10})"/', $html, $m ) ) {
            return self::normalise( $m[1] );
        }

        return '';
    }

    public static function suppressed_count(): int {
        return self::$suppressed;
    }

    /**
     * One HTML comment per request, only when something was actually withheld.
     * Invisible to readers, and the first thing a developer greps for when a
     * FAQ block they placed is not on the page. It is also how we confirm the
     * behaviour remotely, without admin access to anyone's site.
     */
    public static function render_diagnostic(): void {
        if ( self::$suppressed < 1 ) {
            return;
        }

        $current = self::current();
        printf(
            "\n<!-- info.link/answers: %d FAQ block(s) withheld. This request is market %s (detected via %s); the content offered was for %s. Publishing a feed for this market makes it appear here, with no change needed on this site. -->\n",
            (int) self::$suppressed,
            esc_html( $current['market'] !== '' ? $current['market'] : 'unknown' ),
            esc_html( $current['source'] ),
            esc_html( self::$withheld_from )
        );
    }

    // ─── detection ───────────────────────────────────────────────────────────

    /**
     * The signal ladder. Translation plugins first: they know the answer, and
     * they know it for setups (domain per language, cookie, session) that no
     * URL inspection can see. WordPress core last.
     *
     * Deliberately NOT parsing the URL. `/it/` is Italian on one site and IT
     * services on another, and guessing wrong here does not mislabel anything,
     * it withholds a block from a page that was fine. The plugin sends the page
     * URL with every request instead, so a site whose language shows only in its
     * URLs is something the platform resolves, with that tenant's own
     * configuration in front of it — the context a guess here would lack.
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

        // WordPress core. Translation plugins filter this to the current
        // language, which is exactly right here: this is the CURRENT market. The
        // market our CONTENT is written for is a different question, and one the
        // platform answers without ever asking WordPress.
        if ( function_exists( 'determine_locale' ) ) {
            $tag = self::normalise( determine_locale() );
            if ( $tag !== '' ) {
                return [ 'market' => $tag, 'source' => 'wordpress' ];
            }
        }

        return [ 'market' => '', 'source' => 'none' ];
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
     * Region-level markets (de-AT against de-DE) therefore count as the same
     * here. That is a real distinction, retailers and claim review differ, but
     * it is one the platform expresses by sending the right feed for the market,
     * not one the plugin can invent from a locale. Comparing strictly here would
     * withhold content for regions nobody has published for yet.
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
