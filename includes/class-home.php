<?php
defined('ABSPATH') || exit;

class WAI_Home {

    const CACHE_GROUP = 'wai_home';
    const CACHE_TTL   = 21600; // 6 horas

    public static function init(): void {
        add_shortcode('anime_home', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_nopriv_wai_home_search', [__CLASS__, 'ajax_search']);
        add_action('wp_ajax_wai_home_search',         [__CLASS__, 'ajax_search']);
        add_action('wai_refresh_rankings',            [__CLASS__, 'refresh_rankings']);
        add_action('wp_head',                          [__CLASS__, 'hide_page_title']);
        add_filter('body_class',                      [__CLASS__, 'body_class']);

        if (!wp_next_scheduled('wai_refresh_rankings')) {
            wp_schedule_event(time(), 'daily', 'wai_refresh_rankings');
        }
    }

    public static function enqueue(): void {
        if (!is_page() && !is_front_page()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'anime_home')) return;

        $home_css = WAI_DIR . 'assets/css/home.css';
        $home_js  = WAI_DIR . 'assets/js/home.js';

        wp_enqueue_style(
            'wai-home',
            WAI_URL . 'assets/css/home.css',
            [],
            file_exists($home_css) ? filemtime($home_css) : WAI_VERSION
        );
        wp_enqueue_script(
            'wai-home',
            WAI_URL . 'assets/js/home.js',
            [],
            file_exists($home_js) ? filemtime($home_js) : WAI_VERSION,
            true
        );
        wp_localize_script('wai-home', 'WAI_HOME', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wai_home_search'),
        ]);
    }
    public static function hide_page_title(): void {
        $page_id = get_option('wai_home_page_id', 0);
        if (!$page_id) {
            global $post;
            if ($post && has_shortcode($post->post_content, 'anime_home')) $page_id = $post->ID;
        }
        if (!$page_id || !is_page($page_id)) return;
        echo '<style>.entry-title,.page-title,.post-title,h1.title{display:none!important}</style>' . PHP_EOL;
    }

    public static function body_class(array $classes): array {
        $page_id = get_option('wai_home_page_id', 0);
        if (!$page_id) {
            global $post;
            if ($post && has_shortcode($post->post_content, 'anime_home')) {
                $page_id = $post->ID;
            }
        }

        if ($page_id && is_page($page_id)) {
            $classes[] = 'wai-home-page';
        }

        return $classes;
    }

    public static function render(): string {
        ob_start();
        $hero    = self::get_hero();
        $heroes  = self::get_heroes(5);
        $latest  = self::get_latest(5);
        $top5    = self::get_top5();
        $trailer = self::get_trailer_of_day();
        include WAI_DIR . 'templates/home.php';
        return ob_get_clean();
    }

    // ── Object cache helpers ────────────────────────────────────────────────

    private static function cache_get(string $key) {
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($key, self::CACHE_GROUP);
        }
        return get_transient($key);
    }

    private static function cache_set(string $key, $value, int $ttl): void {
        if (wp_using_ext_object_cache()) {
            wp_cache_set($key, $value, self::CACHE_GROUP, $ttl);
        } else {
            set_transient($key, $value, $ttl);
        }
    }

    private static function cache_delete(string $key): void {
        if (wp_using_ext_object_cache()) {
            wp_cache_delete($key, self::CACHE_GROUP);
        }
        delete_transient($key);
    }

    // ── Datos ───────────────────────────────────────────────────────────────

    public static function get_hero(): ?array {
        $heroes = self::get_heroes(5);
        return $heroes[0] ?? null;
    }

    public static function get_heroes(int $count = 5): array {
        $key    = 'heroes_' . $count;
        $cached = self::cache_get($key);
        if ($cached !== false && is_array($cached)) return $cached;

        $posts = get_posts([
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'wai_score',
            'meta_type'      => 'NUMERIC',
            'order'          => 'DESC',
        ]);
        if (empty($posts)) {
            $posts = get_posts(['post_type'=>'anime','post_status'=>'publish','posts_per_page'=>$count]);
        }
        $result = array_filter(array_map([__CLASS__, 'build_card'], $posts));
        self::cache_set($key, $result, self::CACHE_TTL);
        return $result;
    }

    public static function get_latest(int $count = 5): array {
        $key    = 'latest_' . $count;
        $cached = self::cache_get($key);
        if ($cached !== false && is_array($cached)) return $cached;

        $posts = get_posts([
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $result = array_map([__CLASS__, 'build_card'], $posts);
        self::cache_set($key, $result, self::CACHE_TTL);
        return $result;
    }

    public static function get_top5(): array {
        $cached = self::cache_get('top5_rankings');
        if ($cached !== false && is_array($cached)) return $cached;

        $posts = get_posts([
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'wai_score',
            'order'          => 'DESC',
        ]);
        $result = array_map([__CLASS__, 'build_card'], $posts);
        self::cache_set('top5_rankings', $result, DAY_IN_SECONDS);
        return $result;
    }

    public static function get_trailer_of_day(): ?array {
        $cached = self::cache_get('trailer_of_day');
        if ($cached !== false && is_array($cached)) return $cached;

        global $wpdb;
        $post_id = $wpdb->get_var("
            SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'anime'
              AND p.post_status = 'publish'
              AND pm.meta_key = 'wai_trailer_id'
              AND pm.meta_value != ''
            ORDER BY RAND() LIMIT 1
        ");

        if (!$post_id) return null;
        $result = self::build_card(get_post($post_id));
        self::cache_set('trailer_of_day', $result, DAY_IN_SECONDS);
        return $result;
    }

    // ── Refresh rankings desde AniList (cron 24h) ───────────────────────────

    public static function refresh_rankings(): void {
        $anilist_top = WAI_AniList::fetch('top', 1, 20);
        if (empty($anilist_top)) return;

        $matched = [];
        foreach ($anilist_top as $a) {
            $id = (int) $a['id'];
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wai_anilist_id' AND meta_value=%d LIMIT 1", $id
            ));
            if ($post_id) {
                update_post_meta($post_id, 'wai_score', (float)($a['averageScore'] ?? 0));
                $matched[] = self::build_card(get_post($post_id));
                if (count($matched) >= 5) break;
            }
        }

        if (!empty($matched)) {
            self::cache_set('top5_rankings', $matched, DAY_IN_SECONDS);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . '] Rankings actualizados desde AniList. ' . count($matched) . ' animes.');
        }

        self::cache_delete('trailer_of_day');
    }

    // ── Invalidación pública ────────────────────────────────────────────────

    public static function invalidate_all(): void {
        self::cache_delete('heroes_5');
        self::cache_delete('latest_5');
        self::cache_delete('top5_rankings');
        self::cache_delete('trailer_of_day');
    }

    // ── AJAX búsqueda frontend ──────────────────────────────────────────────

    public static function ajax_search(): void {
        check_ajax_referer('wai_home_search', 'nonce');
        $q = sanitize_text_field($_POST['q'] ?? '');
        if (strlen($q) < 2) wp_send_json_success([]);

        $args = [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 8,
            's'              => $q,
        ];

        $query = new WP_Query($args);
        $out   = [];
        foreach ($query->posts as $p) {
            $out[] = [
                'id'    => $p->ID,
                'title' => $p->post_title,
                'url'   => get_permalink($p->ID),
                'cover' => WAI_Media::cover_url($p->ID),
                'score' => get_post_meta($p->ID, 'wai_score', true),
                'year'  => get_post_meta($p->ID, 'wai_season_year', true),
            ];
        }
        wp_send_json_success($out);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    public static function build_card($post): array {
        if (!$post) return [];
        $pid = $post->ID;
        return [
            'id'         => $pid,
            'title'      => $post->post_title,
            'url'        => get_permalink($pid),
            'cover'      => WAI_Media::cover_url($pid),
            'banner'     => get_post_meta($pid, 'wai_banner', true) ?: '',
            'score'      => (float) get_post_meta($pid, 'wai_score', true),
            'episodes'   => (int) get_post_meta($pid, 'wai_episodes', true),
            'format'     => get_post_meta($pid, 'wai_format', true),
            'status'     => get_post_meta($pid, 'wai_status', true),
            'year'       => (int) get_post_meta($pid, 'wai_season_year', true),
            'trailer_id' => get_post_meta($pid, 'wai_trailer_id', true),
            'trailer_st' => get_post_meta($pid, 'wai_trailer_site', true),
            'excerpt'    => wp_trim_words($post->post_content, 20),
            'genres'     => wp_list_pluck(get_the_terms($pid, 'anime_genre') ?: [], 'name'),
            'studio'     => wp_list_pluck(get_the_terms($pid, 'anime_studio') ?: [], 'name')[0] ?? '',
        ];
    }
}