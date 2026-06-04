<?php
/**
 * Plugin Name: info.link/answers – Be visible in ChatGPT and Google AI (GEO, AEO)
 * Description: Get your content cited by AI chatbots like ChatGPT and Google AI. Server-side FAQ rendering optimized for AI search, SEO, and WooCommerce.
 * Version: 1.0.9
 * Plugin URI: https://github.com/House-Of-Change/answers-wordpress
 * Update URI: https://github.com/House-Of-Change/answers-wordpress
 * Author: info.link
 * Text Domain: answers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ANSWERS_VERSION', '1.0.9' );
define( 'ANSWERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ANSWERS_URL', plugin_dir_url( __FILE__ ) );

// The hosted publish endpoint the plugin ships pointed at. Used everywhere the
// API URL is read so an unconfigured site still works out of the box; the
// publisher can change it (or clear it to fall back to the local JSON) from the
// settings page.
define( 'ANSWERS_DEFAULT_API_URL', 'https://answers.info.link/api/publish' );

require_once ANSWERS_PATH . 'includes/class-answers-data-provider.php';
require_once ANSWERS_PATH . 'includes/class-answers-renderer.php';
require_once ANSWERS_PATH . 'includes/class-answers-shortcode.php';
require_once ANSWERS_PATH . 'includes/class-answers-hooks.php';
require_once ANSWERS_PATH . 'includes/class-answers-admin.php';
require_once ANSWERS_PATH . 'includes/class-answers-meta-box.php';
require_once ANSWERS_PATH . 'includes/class-answers-wpbakery.php';
require_once ANSWERS_PATH . 'includes/class-answers-elementor.php';
require_once ANSWERS_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$answers_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/House-Of-Change/answers-wordpress/',
    __FILE__,
    'answers'
);
$answers_update_checker->getVcsApi()->enableReleaseAssets();

// Persist the default API URL on first activation so it shows as a real,
// editable value in the settings field rather than a placeholder. add_option()
// is a no-op when the option already exists, so it never overwrites a value the
// publisher has changed or deliberately cleared.
register_activation_hook( __FILE__, function () {
    add_option( 'answers_api_url', ANSWERS_DEFAULT_API_URL );
} );

add_action( 'plugins_loaded', function () {
    Answers_Shortcode::init();
    Answers_Hooks::init();
    Answers_Admin::init();
    Answers_Meta_Box::init();
    Answers_WPBakery::init();
    Answers_Elementor::init();
} );
