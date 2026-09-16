<?php
/**
 * Plugin Name: WP Anime Index
 * Plugin URI:  https://github.com/
 * Description: Importa animes desde AniList, genera contenido editorial con Gemini AI y los publica automáticamente.
 * Version:     1.2.5
 * Author:      Rexdevai
 * Text Domain: wp-anime-index
 */

defined('ABSPATH') || exit;

define('WAI_VERSION', '1.2.5');
define('WAI_DIR',     plugin_dir_path(__FILE__));
define('WAI_URL',     plugin_dir_url(__FILE__));
define('WAI_LOG_OPT', 'wai_import_log');

require_once WAI_DIR . 'includes/class-cpt.php';
require_once WAI_DIR . 'includes/class-api-anilist.php';
require_once WAI_DIR . 'includes/class-api-jikan.php';
require_once WAI_DIR . 'includes/class-api-kitsu.php';
require_once WAI_DIR . 'includes/class-api-gemini.php';
require_once WAI_DIR . 'includes/class-media.php';
require_once WAI_DIR . 'includes/class-importer.php';
require_once WAI_DIR . 'includes/class-admin.php';
require_once WAI_DIR . 'includes/class-cron.php';
require_once WAI_DIR . 'includes/class-shortcodes.php';
require_once WAI_DIR . 'includes/class-home.php';
require_once WAI_DIR . 'includes/class-seo.php';
require_once WAI_DIR . 'includes/class-theme.php';
require_once WAI_DIR . 'includes/class-cache.php';

register_activation_hook(__FILE__,   ['WAI_Cron', 'activate']);
register_deactivation_hook(__FILE__, ['WAI_Cron', 'deactivate']);

add_action('plugins_loaded', function () {
    // 1.2.4: migrate the previous default model only when the site still has
    // the old plugin default. Explicit administrator choices are preserved.
    if (get_option('wai_gemini_model', null) === 'gemini-2.0-flash') {
        update_option('wai_gemini_model', 'gemini-3.6-flash');
    }

    WAI_CPT::init();
    WAI_Admin::init();
    WAI_Cron::init();
    WAI_Shortcodes::init();
    WAI_Home::init();
    WAI_SEO::init();
    WAI_Theme::init();
    WAI_Cache::init();
});