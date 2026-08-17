<?php
/**
 * Market resolution, tested without WordPress.
 *
 * Answers_Market decides whether a FAQ block renders at all, so a wrong answer
 * here either deletes content from a page that was fine or keeps serving one
 * market's claims to another. That is worth a harness, and the logic is pure
 * enough to run against stubs: the class touches WordPress only through
 * get_option / get_locale / apply_filters / is_admin and two escaping helpers.
 *
 *   php tests/market-logic-test.php
 *
 * Stubs are deliberately dumb. If a future change makes the class need more of
 * WordPress than this file provides, that is a signal about the change, not a
 * shortcoming of the test.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['stub_options'] = [];
$GLOBALS['stub_locale']  = 'de_DE';
$GLOBALS['stub_admin']   = false;
$GLOBALS['stub_post_id'] = 0;
$GLOBALS['stub_meta']    = [];

function get_option( $name, $default = false ) {
    return $GLOBALS['stub_options'][ $name ] ?? $default;
}
function get_locale() {
    return $GLOBALS['stub_locale'];
}
function determine_locale() {
    // The ladder prefers this over get_locale(); on the front end WordPress
    // returns the site locale here, which is what the stub reproduces.
    return $GLOBALS['stub_locale'];
}
function is_admin() {
    return $GLOBALS['stub_admin'];
}
function apply_filters( $hook, $value ) {
    return $value;
}
function esc_html( $text ) {
    return $text;
}
function sanitize_text_field( $text ) {
    return trim( (string) $text );
}
function wp_unslash( $value ) {
    return $value;
}
function get_the_ID() {
    return $GLOBALS['stub_post_id'] ?? 0;
}
function get_post_meta( $post_id, $key, $single = false ) {
    return $GLOBALS['stub_meta'][ $post_id ][ $key ] ?? '';
}
function wp_parse_url( $url, $component = -1 ) {
    return $component === -1 ? parse_url( $url ) : parse_url( $url, $component );
}

require_once __DIR__ . '/../plugin/answers/includes/class-answers-market.php';
require_once __DIR__ . '/../plugin/answers/includes/class-answers-feed.php';

$failures = 0;
$checks   = 0;

function reset_request( array $options = [], string $locale = 'de_DE', string $uri = '/produkt/x/', string $host = 'example.com' ): void {
    $GLOBALS['stub_options'] = $options;
    $GLOBALS['stub_locale']  = $locale;
    $_SERVER['REQUEST_URI']  = $uri;
    $_SERVER['HTTP_HOST']    = $host;

    // The class memoises resolution per request; a test IS a new request.
    $reset = new ReflectionProperty( 'Answers_Market', 'resolved' );
    $reset->setAccessible( true );
    $reset->setValue( null, null );
}

function check( string $desc, $expected, $actual ): void {
    global $failures, $checks;
    $checks++;
    if ( $expected === $actual ) {
        echo "PASS: $desc\n";
        return;
    }
    $failures++;
    printf( "FAIL: %s\n  expected: %s\n  actual:   %s\n", $desc, var_export( $expected, true ), var_export( $actual, true ) );
}

// ─── normalise ───────────────────────────────────────────────────────────────

check( 'WP locale to tag', 'de-DE', Answers_Market::normalise( 'de_DE' ) );
check( 'lowercase region is upper-cased', 'de-DE', Answers_Market::normalise( 'de-de' ) );
check( 'charset suffix dropped', 'de-DE', Answers_Market::normalise( 'de_DE.UTF-8' ) );
check( 'locale variant dropped', 'ca', Answers_Market::normalise( 'ca@valencia' ) );
check( 'language only stays language only', 'nl', Answers_Market::normalise( 'nl' ) );
check( 'three-letter language kept', 'fil', Answers_Market::normalise( 'fil' ) );
check( 'not a language tag is unknown, not a guess', '', Answers_Market::normalise( 'shop' ) );
check( 'empty stays empty', '', Answers_Market::normalise( '' ) );
check( 'nonsense region falls back to language', 'de', Answers_Market::normalise( 'de-XYZ' ) );
// Known tolerance: anything two or three letters is treated as a language
// subtag, because validating against the ISO list is not worth shipping. A
// publisher who types "not-a-locale" gets "not" and their content stops
// matching, which the settings page shows them ("your content is set up for…").
check( 'a three-letter word is accepted as a language', 'not', Answers_Market::normalise( 'not-a-locale' ) );

// ─── same_market ─────────────────────────────────────────────────────────────

check( 'identical tags match', true, Answers_Market::same_market( 'de-DE', 'de-DE' ) );
check( 'language matches its own region form', true, Answers_Market::same_market( 'de', 'de-DE' ) );
check( 'documented phase-0 limit: de-AT counts as de-DE', true, Answers_Market::same_market( 'de-AT', 'de-DE' ) );
check( 'different languages do not match', false, Answers_Market::same_market( 'nl-NL', 'de-DE' ) );
check( 'unknown matches anything (unknown renders)', true, Answers_Market::same_market( '', 'de-DE' ) );

// ─── the decision ────────────────────────────────────────────────────────────

reset_request();
check( 'site locale = base market renders', true, Answers_Market::may_render() );
check( '…and says why', 'base-market', Answers_Market::decide()['reason'] );

reset_request( [ 'answers_base_market' => 'de-DE' ], 'nl_NL' );
check( 'a foreign site locale is suppressed', false, Answers_Market::may_render() );
check( '…and names the market it withheld from', 'nl-NL', Answers_Market::decide()['market'] );

reset_request( [ 'answers_base_market' => 'de-DE', 'answers_market_fallback' => 'base' ], 'nl_NL' );
check( 'the publisher can opt into serving base anyway', true, Answers_Market::may_render() );
check( '…and the reason records that it was a choice', 'fallback-base', Answers_Market::decide()['reason'] );

reset_request( [ 'answers_base_market' => 'de-DE', 'answers_market_fallback' => 'nonsense' ], 'nl_NL' );
check( 'an unrecognised fallback value is treated as none', false, Answers_Market::may_render() );

reset_request( [ 'answers_base_market' => 'shop' ], 'de_DE' );
check( 'an unparseable declared base falls back to the site locale', 'de-DE', Answers_Market::base() );

// URL detection: only for declared prefixes.
reset_request( [ 'answers_base_market' => 'de-DE', 'answers_market_prefixes' => 'nl, fr-BE' ], 'de_DE', '/nl/produkt/x/' );
check( 'a declared URL prefix is detected', 'nl', Answers_Market::current()['market'] );
check( '…via the url source', 'url', Answers_Market::current()['source'] );
check( '…and suppresses', false, Answers_Market::may_render() );

reset_request( [ 'answers_base_market' => 'de-DE', 'answers_market_prefixes' => 'nl' ], 'de_DE', '/it/services/' );
check( 'an UNdeclared two-letter path is NOT guessed at', 'de-DE', Answers_Market::current()['market'] );
check( '…so a page that only looks translated still renders', true, Answers_Market::may_render() );

reset_request( [ 'answers_base_market' => 'de-DE', 'answers_market_prefixes' => 'nl' ], 'de_DE', '/produkt/x/', 'nl.example.com' );
check( 'a declared prefix also matches a subdomain', 'nl', Answers_Market::current()['market'] );

reset_request( [ 'answers_base_market' => 'de-DE' ], 'nl_NL' );
$GLOBALS['stub_admin'] = true;
check( 'wp-admin always renders, whatever the locale says', true, Answers_Market::may_render() );
$GLOBALS['stub_admin'] = false;

// ─── the diagnostic ──────────────────────────────────────────────────────────

reset_request( [ 'answers_base_market' => 'de-DE' ], 'nl_NL' );
ob_start();
Answers_Market::render_diagnostic();
check( 'nothing withheld yet, nothing to explain', '', ob_get_clean() );

Answers_Market::note_suppressed();
Answers_Market::note_suppressed();
ob_start();
Answers_Market::render_diagnostic();
$comment = ob_get_clean();
check( 'the comment counts placements', true, strpos( $comment, '2 FAQ block(s) not rendered' ) !== false );
check( 'the comment names both markets', true, strpos( $comment, 'nl-NL' ) !== false && strpos( $comment, 'de-DE' ) !== false );

// ─── base market on a translated site ────────────────────────────────────────
//
// The regression these guard: every translation plugin filters `locale` to the
// CURRENT request's language, so a base derived from get_locale() equals the
// current market on every request and the gate does nothing at all.

reset_request( [ 'trp_settings' => [ 'default-language' => 'de_DE' ] ], 'nl_NL' );
check( 'TranslatePress default language wins over the runtime locale', 'de-DE', Answers_Market::base() );
check( '…so a Dutch rendering of German content is suppressed', false, Answers_Market::may_render() );

reset_request( [ 'WPLANG' => 'de_DE' ], 'nl_NL' );
check( 'the raw site-language option is preferred over the filtered locale', 'de-DE', Answers_Market::base() );

reset_request( [ 'answers_base_market' => 'en-GB', 'WPLANG' => 'de_DE' ], 'nl_NL' );
check( 'an explicit setting still wins over everything', 'en-GB', Answers_Market::base() );

reset_request( [], 'de_DE' );
check( 'no translation layer: the runtime locale IS the base', 'de-DE', Answers_Market::base() );

// ─── feed resolution ─────────────────────────────────────────────────────────
//
// The site-wide default feed must reach DELIBERATE placements only. Applied to
// the auto-injection paths it would add a FAQ block to every page and product
// on a site that configured one, which is an outage dressed as a refactor.

$GLOBALS['stub_post_id'] = 7;
reset_request( [ 'answers_default_feed' => 'site-wide-feed' ], 'de_DE' );
$GLOBALS['stub_meta'] = [];
check( 'auto-injection does NOT fall back to the site default', '', Answers_Feed::resolve() );
check( 'a shortcode DOES', 'site-wide-feed', Answers_Feed::resolve( '', null, true ) );

$GLOBALS['stub_meta'] = [ 7 => [ '_answers_feed_id' => 'the-posts-own-feed' ] ];
check( "the post's own id is used where present", 'the-posts-own-feed', Answers_Feed::resolve() );
check( 'an explicit id beats the post meta', 'typed-in-the-shortcode', Answers_Feed::resolve( 'typed-in-the-shortcode' ) );
$GLOBALS['stub_post_id'] = 0;
$GLOBALS['stub_meta']    = [];

printf( "\n%d checks, %d failure(s)\n", $checks, $failures );
exit( $failures === 0 ? 0 : 1 );
