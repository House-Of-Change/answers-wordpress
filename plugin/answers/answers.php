<?php
/**
 * Plugin Name: Answers – AI Search GEO AEO Plugin
 * Description: Renders FAQ content server-side for SEO and LLM crawlability. Supports WooCommerce product tabs, shortcodes, and automatic content injection.
 * Version: 1.0.0
 * Plugin URI: https://github.com/House-Of-Change/answers-wordpress
 * Update URI: https://github.com/House-Of-Change/answers-wordpress
 * Author: info.link
 * Text Domain: answers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ANSWERS_VERSION', '1.0.0' );
define( 'ANSWERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ANSWERS_URL', plugin_dir_url( __FILE__ ) );

require_once ANSWERS_PATH . 'includes/class-answers-data-provider.php';
require_once ANSWERS_PATH . 'includes/class-answers-renderer.php';
require_once ANSWERS_PATH . 'includes/class-answers-shortcode.php';
require_once ANSWERS_PATH . 'includes/class-answers-hooks.php';
require_once ANSWERS_PATH . 'includes/class-answers-admin.php';
require_once ANSWERS_PATH . 'includes/class-answers-meta-box.php';
require_once ANSWERS_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$answers_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/House-Of-Change/answers-wordpress/',
    __FILE__,
    'answers'
);
$answers_update_checker->getVcsApi()->enableReleaseAssets();

add_action( 'plugins_loaded', function () {
    Answers_Shortcode::init();
    Answers_Hooks::init();
    Answers_Admin::init();
    Answers_Meta_Box::init();
} );
