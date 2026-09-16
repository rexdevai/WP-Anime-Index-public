<?php
defined('ABSPATH') || exit;

class WAI_Cache {

    const CF_OPTION = 'wai_cloudflare_config';

    public static function init(): void {
        add_action('wp_ajax_wai_purge_all_cache',    [__CLASS__, 'ajax_purge_all']);
        add_action('wp_ajax_wai_purge_single_url',   [__CLASS__, 'ajax_purge_single_url']);
        add_action('init',                           [__CLASS__, 'exclude_from_cache']);
    }

    // ── Purga total ──────────────────────────────────────────────────────────

    public static function purge_all(): array {
        $results = [];

        // 1. Transients del plugin
        delete_transient('wai_top5_rankings');
        delete_transient('wai_trailer_of_day');
        $results['plugin_transients'] = 'borrados';

        // 2. Transients generales de WordPress
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
        $results['wp_transients'] = 'borrados';

        // 3. LiteSpeed
        if (defined('LSCWP_V')) {
            do_action('litespeed_purge_all');
            $results['litespeed'] = 'purgado';
        } else {
            $results['litespeed'] = 'no detectado';
        }

        // 4. Cloudflare
        $results['cloudflare'] = self::cloudflare_purge_everything();

        // 5. WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $results['wp_rocket'] = 'purgado';
        } else {
            $results['wp_rocket'] = 'no detectado';
        }

        // 6. W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $results['w3_total_cache'] = 'purgado';
        } else {
            $results['w3_total_cache'] = 'no detectado';
        }

        // 7. WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            $results['wp_super_cache'] = 'purgado';
        } else {
            $results['wp_super_cache'] = 'no detectado';
        }

        // 8. WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
            $results['wp_fastest_cache'] = 'purgado';
        } else {
            $results['wp_fastest_cache'] = 'no detectado';
        }

        // 9. Nginx Helper
        if (function_exists('rt_nginx_helper_purge_all')) {
            rt_nginx_helper_purge_all();
            $results['nginx_helper'] = 'purgado';
        } else {
            $results['nginx_helper'] = 'no detectado';
        }

        // 10. Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
            $results['autoptimize'] = 'purgado';
        } else {
            $results['autoptimize'] = 'no detectado';
        }

        // 11. Breeze
        if (function_exists('breeze_cache_purge_all')) {
            breeze_cache_purge_all();
            $results['breeze'] = 'purgado';
        } else {
            $results['breeze'] = 'no detectado';
        }

        // 12. SiteGround
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
            $results['siteground'] = 'purgado';
        } else {
            $results['siteground'] = 'no detectado';
        }

        // 13. Kinsta
        if (function_exists('kinsta_cache_purge_all')) {
            kinsta_cache_purge_all();
            $results['kinsta'] = 'purgado';
        } else {
            $results['kinsta'] = 'no detectado';
        }

        // 14. WP Engine
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_memcached')) {
            WpeCommon::purge_memcached();
            WpeCommon::purge_varnish_cache();
            $results['wp_engine'] = 'purgado';
        } else {
            $results['wp_engine'] = 'no detectado';
        }

        // 15. Hummingbird
        if (function_exists('wphb_clear_cache')) {
            wphb_clear_cache();
            $results['hummingbird'] = 'purgado';
        } else {
            $results['hummingbird'] = 'no detectado';
        }

        // 16. NitroPack
        if (function_exists('nitropack_purge_all')) {
            nitropack_purge_all();
            $results['nitropack'] = 'purgado';
        } else {
            $results['nitropack'] = 'no detectado';
        }

        // 17. Comet Cache
        if (class_exists('comet_cache')) {
            comet_cache::clear();
            $results['comet_cache'] = 'purgado';
        } else {
            $results['comet_cache'] = 'no detectado';
        }

        // 18. OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $results['opcache'] = 'reseteado';
        } else {
            $results['opcache'] = 'no disponible';
        }

        // 19. Object Cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $results['object_cache'] = 'flusheado';
        }

        WAI_Admin::log(sprintf(
            '[%s] Purga total ejecutada. %s',
            current_time('Y-m-d H:i:s'),
            wp_json_encode($results)
        ));

        return $results;
    }

    // ── Purga dirigida por URL ───────────────────────────────────────────────

    public static function purge_urls(array $urls): array {
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', $urls))));
        if (empty($urls)) return ['skipped' => 'sin URLs'];

        $results = ['urls' => count($urls)];

        // LiteSpeed — purga por URL
        if (defined('LSCWP_V')) {
            foreach ($urls as $url) {
                do_action('litespeed_purge_url', $url);
            }
            $results['litespeed'] = 'purgado';
        } else {
            $results['litespeed'] = 'no detectado';
        }

        // Cloudflare — purga por URL
        $results['cloudflare'] = self::cloudflare_purge_urls($urls);

        // WP Rocket
        if (function_exists('rocket_clean_files')) {
            rocket_clean_files($urls);
            $results['wp_rocket'] = 'purgado';
        } else {
            $results['wp_rocket'] = 'no detectado';
        }

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            foreach ($urls as $url) {
                wp_cache_post_change($url);
            }
            $results['wp_super_cache'] = 'purgado';
        } else {
            $results['wp_super_cache'] = 'no detectado';
        }

        return $results;
    }

    // ── Cloudflare ───────────────────────────────────────────────────────────

    private static function cloudflare_purge_everything(): string {
        $config = get_option(self::CF_OPTION, []);
        $zone_id   = trim($config['zone_id']   ?? '');
        $api_token = trim($config['api_token'] ?? '');

        if (!$zone_id || !$api_token) {
            return 'no configurado';
        }

        $response = wp_remote_post(
            'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/purge_cache',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['purge_everything' => true]),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            return 'error: ' . $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return 'error HTTP ' . $code . ': ' . ($body['errors'][0]['message'] ?? 'desconocido');
        }

        return 'purgado (' . ($body['result']['id'] ?? 'OK') . ')';
    }

    private static function cloudflare_purge_urls(array $urls): string {
        $config = get_option(self::CF_OPTION, []);
        $zone_id   = trim($config['zone_id']   ?? '');
        $api_token = trim($config['api_token'] ?? '');

        if (!$zone_id || !$api_token) return 'no configurado';

        $chunks = array_chunk($urls, 30);
        $ok = 0; $err = 0;

        foreach ($chunks as $chunk) {
            $response = wp_remote_post(
                'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/purge_cache',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_token,
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => wp_json_encode(['files' => array_values($chunk)]),
                    'timeout' => 15,
                ]
            );

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $err += count($chunk);
            } else {
                $ok += count($chunk);
            }
        }

        return sprintf('purgado %d urls, %d errores', $ok, $err);
    }

    // ── Exclusión de caché para filtros ──────────────────────────────────────

    public static function exclude_from_cache(): void {
        if (!defined('LSCWP_V')) return;

        if (defined('DOING_AJAX') && DOING_AJAX) {
            do_action('litespeed_control_set_nocache', 'WAI: AJAX no cacheable');
        }

        if (!empty($_GET['wai_search']) || !empty($_GET['wai_genre']) ||
            !empty($_GET['wai_year'])   || !empty($_GET['wai_status'])) {
            do_action('litespeed_control_set_nocache', 'WAI: filtros no cacheables');
        }
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    public static function ajax_purge_all(): void {
        check_ajax_referer('wai_purge_cache_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No autorizado.');
        }

        $results = self::purge_all();
        wp_send_json_success($results);
    }

    public static function ajax_purge_single_url(): void {
        check_ajax_referer('wai_purge_cache_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No autorizado.');
        }

        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) {
            wp_send_json_error('URL inválida.');
        }

        $results = self::purge_urls([$url]);
        wp_send_json_success($results);
    }
}