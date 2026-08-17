<?php
/**
 * WHICH FEED does this placement render? One function, five call sites.
 *
 * The id used to be read with get_post_meta( …, '_answers_feed_id' ) in five
 * places (content filter, product tab, tab callback, style enqueue, JSON-LD)
 * and from a shortcode attribute in a sixth. Elementor and WPBakery both build
 * a shortcode, so they inherit whatever the shortcode does. That was harmless
 * while the answer was "read one meta field", and stops being harmless the
 * moment resolution has to consider anything else: per-market feeds are next,
 * and five copies of a resolution rule become five places to forget one.
 *
 * So: every path asks this class, and the `answers_feed_id` filter below is the
 * seam a per-market feed map plugs into later, without touching any caller.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Feed {

    const META_KEY = '_answers_feed_id';

    /** The id stored on a post, or '' when the post carries none. */
    public static function id_for_post( int $post_id ): string {
        if ( $post_id < 1 ) {
            return '';
        }
        return sanitize_text_field( (string) get_post_meta( $post_id, self::META_KEY, true ) );
    }

    /**
     * The id for this placement.
     *
     * @param string   $explicit Id supplied by the placement itself (shortcode
     *                           `id=`, page builder control). Wins, because a
     *                           publisher who typed an id meant it.
     * @param int|null $post_id  Post to read the meta field from; defaults to
     *                           the post in the current loop.
     * @param bool     $allow_site_default Fall back to the site-wide default
     *                           feed. TRUE only for DELIBERATE placements: a
     *                           shortcode or a page-builder element is someone
     *                           asking for a feed here, so "which one" may fall
     *                           back to the site default. The auto-injection
     *                           paths (content filter, product tab) must NOT:
     *                           they run on every singular view, so a site with
     *                           a default feed configured would grow a FAQ block
     *                           on every page and product that has no id of its
     *                           own. That is not what the option ever meant, and
     *                           it is how a refactor of a five-copy meta read
     *                           quietly becomes an outage.
     */
    public static function resolve( string $explicit = '', ?int $post_id = null, bool $allow_site_default = false ): string {
        $feed_id = sanitize_text_field( $explicit );

        if ( $feed_id === '' ) {
            $id = $post_id ?? (int) get_the_ID();
            $feed_id = self::id_for_post( (int) $id );
        }

        if ( $feed_id === '' && $allow_site_default ) {
            $feed_id = sanitize_text_field( (string) get_option( 'answers_default_feed', '' ) );
        }

        $market = Answers_Market::current();

        /**
         * Final say over which feed a placement renders.
         *
         * The market is passed because that is the reason this filter exists:
         * a site serving several markets from one post will map the base id to
         * the market's own feed here. Returning '' renders nothing.
         *
         * @param string $feed_id Resolved id ('' when nothing is configured).
         * @param string $market  Current market tag, '' when unknown.
         * @param string $source  How the market was detected.
         */
        return (string) apply_filters( 'answers_feed_id', $feed_id, $market['market'], $market['source'] );
    }
}
