<?php
defined('ABSPATH') || exit;

class WAI_Shortcodes {

    public static function init(): void {
        add_shortcode('anime_index',  [__CLASS__, 'index']);
        add_shortcode('anime_card',   [__CLASS__, 'card']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_wai_index_page',        [__CLASS__, 'ajax_index_page']);
        add_action('wp_ajax_nopriv_wai_index_page', [__CLASS__, 'ajax_index_page']);
        // single_content ELIMINADO — el single-anime.php ya maneja todo
        add_filter('single_template', [__CLASS__, 'single_template']);
    }

    public static function enqueue(): void {
        $front_css = WAI_DIR . 'assets/css/front.css';
        $pagi_js   = WAI_DIR . 'assets/js/index-pagination.js';

        wp_enqueue_style(
            'wai-front',
            WAI_URL . 'assets/css/front.css',
            [],
            file_exists($front_css) ? filemtime($front_css) : WAI_VERSION
        );

        wp_register_script(
            'wai-index-pagination',
            WAI_URL . 'assets/js/index-pagination.js',
            [],
            file_exists($pagi_js) ? filemtime($pagi_js) : WAI_VERSION,
            true
        );
    }

    public static function index( array $atts ): string {
        $atts = shortcode_atts([
            'genre'    => '',
            'year'     => '',
            'status'   => '',
            'per_page' => 20,
            'orderby'  => 'meta_value_num',
            'order'    => 'DESC',
        ], $atts, 'anime_index');

        $query = self::build_query($atts, 1);

        // Inyecta nonce y per_page para el JS de paginación
        wp_localize_script('wai-index-pagination', 'wai_index', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wai_index_page'),
            'per_page' => (int) $atts['per_page'],
        ]);
        wp_enqueue_script('wai-index-pagination');

        ob_start();
        include WAI_DIR . 'templates/index.php';
        wp_reset_postdata();
        return ob_get_clean();
    }

    // ── Construye WP_Query reutilizable (shortcode + AJAX) ───────────────────

    public static function build_query( array $atts, int $paged ): \WP_Query {
        $args = [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['per_page'],
            'paged'          => max(1, $paged),
            'orderby'        => sanitize_text_field($atts['orderby']),
            'order'          => sanitize_text_field($atts['order']),
            'meta_key'       => 'wai_score',
        ];

        $tax_query = [];
        if ($atts['genre'])  $tax_query[] = ['taxonomy' => 'anime_genre',  'field' => 'name', 'terms' => sanitize_text_field($atts['genre'])];
        if ($atts['year'])   $tax_query[] = ['taxonomy' => 'anime_year',   'field' => 'name', 'terms' => sanitize_text_field($atts['year'])];
        if ($atts['status']) $tax_query[] = ['taxonomy' => 'anime_status', 'field' => 'name', 'terms' => sanitize_text_field($atts['status'])];
        if ($tax_query) $args['tax_query'] = $tax_query;

        if (!empty($_GET['wai_genre']))  $args['tax_query'][] = ['taxonomy' => 'anime_genre',  'field' => 'name', 'terms' => sanitize_text_field($_GET['wai_genre'])];
        if (!empty($_GET['wai_year']))   $args['tax_query'][] = ['taxonomy' => 'anime_year',   'field' => 'name', 'terms' => sanitize_text_field($_GET['wai_year'])];
        if (!empty($_GET['wai_status'])) $args['tax_query'][] = ['taxonomy' => 'anime_status', 'field' => 'name', 'terms' => sanitize_text_field($_GET['wai_status'])];
        if (!empty($_GET['wai_search'])) {
            $args['s'] = sanitize_text_field($_GET['wai_search']);
            unset($args['orderby'], $args['meta_key']);
        }

        if (isset($args['tax_query']) && count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        return new \WP_Query($args);
    }

    // ── AJAX: devuelve HTML del grid para la página solicitada ───────────────

    public static function ajax_index_page(): void {
        check_ajax_referer('wai_index_page', 'nonce');

        $paged    = max(1, (int) ($_POST['paged'] ?? 1));
        $per_page = max(1, min(50, (int) ($_POST['per_page'] ?? 20)));

        $atts = [
            'genre'    => sanitize_text_field($_POST['genre']    ?? ''),
            'year'     => sanitize_text_field($_POST['year']     ?? ''),
            'status'   => sanitize_text_field($_POST['status']   ?? ''),
            'per_page' => $per_page,
            'orderby'  => 'meta_value_num',
            'order'    => 'DESC',
        ];

        // Permite que build_query lea los filtros GET también (el JS los envía como POST)
        if (!empty($_POST['wai_search'])) $_GET['wai_search'] = sanitize_text_field($_POST['wai_search']);
        if (!empty($_POST['wai_genre']))  $_GET['wai_genre']  = sanitize_text_field($_POST['wai_genre']);
        if (!empty($_POST['wai_year']))   $_GET['wai_year']   = sanitize_text_field($_POST['wai_year']);

        $query = self::build_query($atts, $paged);

        ob_start();
        include WAI_DIR . 'templates/index-grid.php';
        wp_reset_postdata();
        $html = ob_get_clean();

        wp_send_json_success([
            'html'        => $html,
            'total_pages' => (int) $query->max_num_pages,
            'paged'       => $paged,
        ]);
    }

    public static function card( array $atts ): string {
        $atts    = shortcode_atts(['id' => 0], $atts, 'anime_card');
        $post_id = (int) $atts['id'];
        if (!$post_id) return '';
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'anime') return '';
        ob_start();
        include WAI_DIR . 'templates/card.php';
        return ob_get_clean();
    }

    public static function single_template( string $template ): string {
        if (is_singular('anime') && !locate_template(['single-anime.php'])) {
            $plugin_tpl = WAI_DIR . 'templates/single-anime.php';
            if (file_exists($plugin_tpl)) return $plugin_tpl;
        }
        return $template;
    }
}
