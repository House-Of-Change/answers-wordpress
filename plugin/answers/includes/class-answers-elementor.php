<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers an Elementor widget for the `[answers_faq]` shortcode.
 *
 * Defensive by design: this loader touches no Elementor class until the
 * `elementor/widgets/register` hook fires, the widget class is loaded lazily
 * only after `\Elementor\Widget_Base` is confirmed to exist, and the widget's
 * render() is a pure shortcode passthrough. If Elementor is absent or its
 * widget-registration API changes shape, this is a silent no-op and the
 * built-in Elementor "Shortcode" widget remains a working fallback.
 */
class Answers_Elementor {

    public static function init(): void {
        // Fires once Elementor is loaded and ready to register widgets
        // (Elementor 3.5+). If Elementor isn't active, the hook never fires.
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widget' ] );
    }

    /**
     * @param mixed $widgets_manager Elementor's widget manager (passed by the hook).
     */
    public static function register_widget( $widgets_manager = null ): void {
        if ( ! self::load_widget_class() || ! is_object( $widgets_manager ) ) {
            return;
        }

        $widget = new Answers_Elementor_Widget();

        // `register()` is the current API; `register_widget_type()` is the
        // pre-3.5 name. Prefer the modern one, fall back if only the old exists.
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( $widget );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( $widget );
        }
    }

    /**
     * Load the widget class only when Elementor's base class is available, so
     * extending it can never fatal on a site without Elementor.
     */
    private static function load_widget_class(): bool {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return false;
        }
        if ( ! class_exists( 'Answers_Elementor_Widget' ) ) {
            require_once ANSWERS_PATH . 'includes/class-answers-elementor-widget.php';
        }
        return class_exists( 'Answers_Elementor_Widget' );
    }
}
