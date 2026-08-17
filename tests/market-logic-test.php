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

    // A test IS a new request, and in production every request is a new PHP
    // process: the memoised market AND the withheld-placement counter both
    // start empty. Resetting only the first one made two diagnostic checks
    // depend on how many mismatches earlier checks had accumulated.
    foreach ( [ 'resolved' => null, 'suppressed' => 0, 'withheld_from' => '' ] as $prop => $value ) {
        $reset = new ReflectionProperty( 'Answers_Market', $prop );
        $reset->setAccessible( true );
        $reset->setValue( null, $value );
    }
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

// ─── what market a fragment belongs to ───────────────────────────────────────

$de_fragment = '<div><script type="application/ld+json">{"@graph":[{"inLanguage":"de","@type":"FAQPage"}]}</script></div>';
$bare        = '<div>no structured data here</div>';

check( 'meta.market is trusted first', 'nl-NL', Answers_Market::market_of( [ 'market' => 'nl_NL' ], $de_fragment ) );
check( 'the platform can mark content as safe anywhere', '', Answers_Market::market_of( [ 'market' => '*' ], $de_fragment ) );
check( 'without meta.market, the fragment\'s inLanguage answers', 'de', Answers_Market::market_of( [], $de_fragment ) );
check( 'a fragment that declares nothing is unknown', '', Answers_Market::market_of( [], $bare ) );

// ─── the decision, driven by the response ────────────────────────────────────

reset_request( [], 'de_DE' );
check( 'German request, German fragment: renders', true, Answers_Market::may_render( [], $de_fragment ) );

reset_request( [], 'nl_NL' );
check( 'Dutch request, German fragment: withheld', false, Answers_Market::may_render( [], $de_fragment ) );

reset_request( [], 'nl_NL' );
check( 'Dutch request, Dutch fragment: renders', true, Answers_Market::may_render( [ 'market' => 'nl-NL' ], $de_fragment ) );

reset_request( [], 'nl_NL' );
check( 'a fragment declaring nothing renders anywhere', true, Answers_Market::may_render( [], $bare ) );

reset_request( [], 'nl_NL' );
check( 'the wildcard renders anywhere', true, Answers_Market::may_render( [ 'market' => '*' ], $de_fragment ) );

reset_request( [], 'de_AT' );
check( 'a region variant of the same language renders', true, Answers_Market::may_render( [ 'market' => 'de-DE' ], $de_fragment ) );

reset_request( [], 'nl_NL' );
$GLOBALS['stub_admin'] = true;
check( 'wp-admin always renders, whatever the locale says', true, Answers_Market::may_render( [], $de_fragment ) );
$GLOBALS['stub_admin'] = false;

// ─── the diagnostic ──────────────────────────────────────────────────────────

reset_request( [], 'de_DE' );
ob_start();
Answers_Market::render_diagnostic();
check( 'nothing withheld yet, nothing to explain', '', ob_get_clean() );

reset_request( [], 'nl_NL' );
Answers_Market::may_render( [], $de_fragment );
Answers_Market::may_render( [], $de_fragment );
ob_start();
Answers_Market::render_diagnostic();
$comment = ob_get_clean();
check( 'the comment counts what it withheld', true, strpos( $comment, '2 FAQ block(s) withheld' ) !== false );
check( 'the comment names both markets', true, strpos( $comment, 'nl-NL' ) !== false && strpos( $comment, 'for de' ) !== false );

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
