<?php
/**
 * Plugin Name: Verified Answers FAQ
 * Description: Renders FAQ content server-side for SEO and LLM crawlability. Supports WooCommerce product tabs, shortcodes, and automatic content injection.
 * Version: 1.0.0
 * Plugin URI: https://github.com/benatwork/answers-wordpress
 * Update URI: https://github.com/benatwork/answers-wordpress
 * Author: Verified Answers
 * Text Domain: verified-answers-faq
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'VA_FAQ_VERSION', '1.0.0' );
define( 'VA_FAQ_PATH', plugin_dir_path( __FILE__ ) );
define( 'VA_FAQ_URL', plugin_dir_url( __FILE__ ) );

require_once VA_FAQ_PATH . 'includes/class-va-faq-data-provider.php';
require_once VA_FAQ_PATH . 'includes/class-va-faq-renderer.php';
require_once VA_FAQ_PATH . 'includes/class-va-faq-shortcode.php';
require_once VA_FAQ_PATH . 'includes/class-va-faq-hooks.php';
require_once VA_FAQ_PATH . 'includes/class-va-faq-admin.php';
require_once VA_FAQ_PATH . 'includes/class-va-faq-meta-box.php';
require_once VA_FAQ_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$va_faq_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/benatwork/answers-wordpress/',
    __FILE__,
    'verified-answers-faq'
);
$va_faq_update_checker->getVcsApi()->enableReleaseAssets();

add_action( 'plugins_loaded', function () {
    VA_FAQ_Shortcode::init();
    VA_FAQ_Hooks::init();
    VA_FAQ_Admin::init();
    VA_FAQ_Meta_Box::init();
} );
