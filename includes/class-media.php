<?php
defined('ABSPATH') || exit;

class WAI_Media {

    public static function cover_img(
        int $post_id,
        string $title = '',
        string $loading = 'lazy',
        bool $priority = false
    ): string {
        $alt   = esc_attr($title ?: get_the_title($post_id));
        $url = get_post_meta($post_id, 'wai_cover_url', true);
        if ($url) {
            $extra = $priority ? ' fetchpriority="high"' : '';
            $large = get_post_meta($post_id, 'wai_cover_url_large', true);
            $srcset = '';
            if ($large && $large !== $url) {
                $srcset = sprintf(' srcset="%s 1x, %s 2x"', esc_url($large), esc_url($url));
            }
            return sprintf(
                '<img src="%s"%s alt="%s" loading="%s" decoding="async" referrerpolicy="no-referrer"%s>',
                esc_url($url),
                $srcset,
                $alt,
                esc_attr($loading),
                $extra
            );
        }

        if (has_post_thumbnail($post_id)) {
            $size  = ($loading === 'eager') ? 'large' : 'medium';
            $attrs = ['loading' => $loading, 'decoding' => 'async', 'alt' => $alt];
            if ($priority) $attrs['fetchpriority'] = 'high';
            return get_the_post_thumbnail($post_id, $size, $attrs);
        }

        return '<div class="wai-no-cover">🎬</div>';
    }

    public static function cover_url( int $post_id ): string {
        $url = (string) get_post_meta($post_id, 'wai_cover_url', true);
        if ($url) return $url;

        $mal_id = (int) get_post_meta($post_id, 'wai_mal_id', true);
        if ($mal_id <= 0) return '';

        $jikan_url = WAI_Jikan::get_cover_url($mal_id);
        if ($jikan_url) {
            update_post_meta($post_id, 'wai_cover_url', esc_url_raw($jikan_url));
            return $jikan_url;
        }

        $kitsu_url = WAI_Kitsu::get_cover_url($mal_id);
        if ($kitsu_url) {
            update_post_meta($post_id, 'wai_cover_url', esc_url_raw($kitsu_url));
            return $kitsu_url;
        }

        return '';
    }

    public static function sideload( string $url, int $post_id, string $title = '' ): int {
        if (empty($url)) return 0;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            WAI_Admin::log('Media download error: ' . $tmp->get_error_message());
            return 0;
        }

        $ext      = self::ext_from_url($url);
        $filename = sanitize_title($title ?: 'anime') . '-cover.' . $ext;

        $file = [
            'name'     => $filename,
            'type'     => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => filesize($tmp),
        ];

        $att_id = media_handle_sideload($file, $post_id);
        @unlink($tmp);

        if (is_wp_error($att_id)) {
            WAI_Admin::log('Media sideload error: ' . $att_id->get_error_message());
            return 0;
        }

        return (int) $att_id;
    }

    private static function ext_from_url( string $url ): string {
        $path = parse_url($url, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }
}