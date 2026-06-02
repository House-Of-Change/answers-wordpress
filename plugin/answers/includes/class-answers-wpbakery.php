<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the `[answers_faq]` shortcode as a native WPBakery Page Builder
 * element so editors can drop in an FAQ feed from the Add Element panel without
 * typing shortcode syntax. The mapping is a no-op when WPBakery is not active.
 */
class Answers_WPBakery {

    public static function init(): void {
        // vc_before_init fires once WPBakery has loaded and is ready for maps.
        add_action( 'vc_before_init', [ __CLASS__, 'map_element' ] );
    }

    public static function map_element(): void {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }

        vc_map( [
            'name'        => __( 'info.link/answers', 'answers' ),
            'base'        => 'answers_faq',
            'category'    => __( 'Content', 'answers' ),
            'description' => __( 'FAQs that AI chatbots love', 'answers' ),
            'icon'        => 'dashicons-editor-help',
            'params'      => [
                [
                    'type'        => 'textfield',
                    'heading'     => __( 'Feed ID', 'answers' ),
                    'param_name'  => 'id',
                    'admin_label' => true,
                    'description' => __( 'Leave empty to use the default feed set in info.link/answers settings.', 'answers' ),
                ],
                [
                    'type'        => 'textfield',
                    'heading'     => __( 'Slug', 'answers' ),
                    'param_name'  => 'slug',
                    'description' => __( 'Optional. Overrides the slug captured with the snapshot.', 'answers' ),
                ],
                [
                    'type'        => 'dropdown',
                    'heading'     => __( 'Variant', 'answers' ),
                    'param_name'  => 'variant',
                    'value'       => [
                        __( 'Default (publisher preset)', 'answers' ) => '',
                        __( 'Embedded', 'answers' )                   => 'embedded',
                        __( 'Standalone', 'answers' )                 => 'standalone',
                    ],
                ],
                [
                    'type'        => 'dropdown',
                    'heading'     => __( 'Styling', 'answers' ),
                    'param_name'  => 'styling',
                    'value'       => [
                        __( 'Default (publisher preset)', 'answers' ) => '',
                        __( 'Scoped', 'answers' )                     => 'scoped',
                        __( 'Full', 'answers' )                       => 'full',
                        __( 'None', 'answers' )                       => 'none',
                    ],
                ],
                [
                    'type'        => 'dropdown',
                    'heading'     => __( 'Heading level', 'answers' ),
                    'param_name'  => 'heading_level',
                    'value'       => [
                        __( 'Default (publisher preset)', 'answers' ) => '',
                        'H1'                                          => '1',
                        'H2'                                          => '2',
                        'H3'                                          => '3',
                    ],
                ],
                [
                    'type'        => 'textfield',
                    'heading'     => __( 'Hide', 'answers' ),
                    'param_name'  => 'hide',
                    'description' => __( 'Comma-separated list of parts to hide.', 'answers' ),
                ],
            ],
        ] );
    }
}
