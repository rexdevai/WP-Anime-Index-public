<?php
defined('ABSPATH') || exit;

class WAI_SEO {

    public static function init(): void {
        add_action('wp_head', [__CLASS__, 'anime_schema'], 5);
        add_action('wp_head', [__CLASS__, 'preload_hero_image'], 2);
        add_action('wp_head', [__CLASS__, 'preconnect_hints'], 1);

        add_filter('wpseo_title',            [__CLASS__, 'yoast_title']);
        add_filter('wpseo_metadesc',         [__CLASS__, 'yoast_description']);
        add_filter('wpseo_canonical',        [__CLASS__, 'yoast_canonical']);

        if (!defined('WPSEO_VERSION')) {
            add_action('wp_head', [__CLASS__, 'fallback_meta'], 2);
        }

        add_action('wp_head', [__CLASS__, 'noindex_filter_urls'], 1);
        add_filter('wpseo_robots',           [__CLASS__, 'yoast_robots_filter']);
        add_filter('wpseo_taxonomy_meta_defaults', [__CLASS__, 'yoast_taxonomy_defaults']);
    }

    // ── Preload del hero para LCP ────────────────────────────────────────────

    public static function preload_hero_image(): void {
        if (!is_page() && !is_front_page()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'anime_home')) return;

        $heroes = WAI_Home::get_heroes(1);
        if (empty($heroes[0]['cover'])) return;

        printf(
            '<link rel="preload" as="image" href="%s" fetchpriority="high">' . PHP_EOL,
            esc_url($heroes[0]['cover'])
        );
    }

    // ── Schema JSON-LD ──────────────────────────────────────────────────────

    public static function anime_schema(): void {
        if (!is_singular('anime')) return;

        $pid        = get_the_ID();
        $title      = get_the_title($pid);
        $url        = get_permalink($pid);
        $score      = (float) get_post_meta($pid, 'wai_score', true);
        $popularity = (int)   get_post_meta($pid, 'wai_popularity', true);
        $eps        = (int)   get_post_meta($pid, 'wai_episodes', true);
        $year       = (int)   get_post_meta($pid, 'wai_season_year', true);
        $format     = get_post_meta($pid, 'wai_format', true);
        $status     = get_post_meta($pid, 'wai_status', true);
        $trailer_id = get_post_meta($pid, 'wai_trailer_id', true);
        $trailer_st = get_post_meta($pid, 'wai_trailer_site', true);
        $banner     = get_post_meta($pid, 'wai_banner', true);
        $studios    = get_the_terms($pid, 'anime_studio');
        $genres     = get_the_terms($pid, 'anime_genre');
        $thumb      = WAI_Media::cover_url($pid);
        $desc       = wp_trim_words(get_the_content(), 30);

        $type = ($format === 'MOVIE') ? 'Movie' : 'TVSeries';

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => $type,
            'name'        => $title,
            'url'         => $url,
            'description' => $desc ?: $title,
        ];

        if ($thumb) $schema['image'] = $thumb;
        if ($banner) $schema['thumbnailUrl'] = $banner;
        if ($year) $schema['startDate'] = (string) $year;
        if ($eps && $type === 'TVSeries') $schema['numberOfEpisodes'] = $eps;

        if ($status) {
            $schema['countryOfOrigin'] = ['@type' => 'Country', 'name' => 'Japan'];
        }

        if ($genres && !is_wp_error($genres)) {
            $schema['genre'] = array_column($genres, 'name');
        }

        if ($studios && !is_wp_error($studios)) {
            $schema['productionCompany'] = [
                '@type' => 'Organization',
                'name'  => $studios[0]->name,
            ];
        }

        if ($score > 0) {
            $rating_value = round($score / 10, 1);
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $rating_value,
                'bestRating'  => 10,
                'worstRating' => 1,
                'ratingCount' => $popularity > 0 ? $popularity : 1,
            ];
        }

        if ($trailer_id && $trailer_st === 'youtube') {
            $schema['trailer'] = [
                '@type'        => 'VideoObject',
                'name'         => 'Tráiler oficial de ' . $title,
                'embedUrl'     => 'https://www.youtube.com/embed/' . $trailer_id,
                'thumbnailUrl' => 'https://img.youtube.com/vi/' . $trailer_id . '/hqdefault.jpg',
                'url'          => 'https://www.youtube.com/watch?v=' . $trailer_id,
            ];
        }

        $schema_breadcrumb = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio',     'item' => home_url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Directorio', 'item' => home_url('/directorio/')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title,      'item' => $url],
            ],
        ];

        $output = '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;

        if (!defined('WPSEO_VERSION')) {
            $output .= '<script type="application/ld+json">' . wp_json_encode($schema_breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
        }

        echo $output;
    }

    // ── Filtros Yoast ───────────────────────────────────────────────────────

    public static function yoast_title( string $title ): string {
        if (!is_singular('anime')) return $title;

        $pid    = get_the_ID();
        $name   = get_the_title($pid);
        $year   = get_post_meta($pid, 'wai_season_year', true);
        $format = get_post_meta($pid, 'wai_format', true);
        $genres = get_the_terms($pid, 'anime_genre');

        $genre_str = '';
        if ($genres && !is_wp_error($genres)) {
            $genre_str = implode(', ', array_slice(array_column($genres, 'name'), 0, 2));
        }

        $suffix = [];
        if ($genre_str) $suffix[] = $genre_str;
        if ($year)      $suffix[] = $year;
        if ($format)    $suffix[] = $format;

        $built = $name;
        if ($suffix) $built .= ' — ' . implode(' · ', $suffix);
        $built .= ' | ' . get_bloginfo('name');

        return $built;
    }

    public static function yoast_description( string $desc ): string {
        if (!is_singular('anime')) return $desc;

        $pid    = get_the_ID();
        $name   = get_the_title($pid);
        $year   = get_post_meta($pid, 'wai_season_year', true);
        $eps    = get_post_meta($pid, 'wai_episodes', true);
        $status = get_post_meta($pid, 'wai_status', true);
        $score  = (float) get_post_meta($pid, 'wai_score', true);
        $genres = get_the_terms($pid, 'anime_genre');

        $status_map = [
            'FINISHED'         => 'Finalizado',
            'RELEASING'        => 'En emisión',
            'NOT_YET_RELEASED' => 'Próximamente',
        ];

        $parts = ["Descubre {$name}"];
        if ($genres && !is_wp_error($genres)) {
            $g = implode(', ', array_slice(array_column($genres, 'name'), 0, 3));
            $parts[] = "un anime de {$g}";
        }
        if ($year)   $parts[] = "del {$year}";
        if ($eps)    $parts[] = "{$eps} episodios";
        if ($status && isset($status_map[$status])) $parts[] = $status_map[$status];
        if ($score > 0) $parts[] = 'puntuación ' . number_format($score / 10, 1) . '/10';

        return implode(', ', $parts) . '. Sinopsis, géneros, trailer y más en ' . get_bloginfo('name') . '.';
    }

    public static function yoast_canonical( string $canonical ): string {
        if (is_page() && (isset($_GET['wai_genre']) || isset($_GET['wai_year']) || isset($_GET['wai_status']) || isset($_GET['wai_search']))) {
            return get_permalink();
        }
        return $canonical;
    }

    // ── noindex en filtros ──────────────────────────────────────────────────

    public static function noindex_filter_urls(): void {
        if (defined('WPSEO_VERSION')) return;

        $has_filter = isset($_GET['wai_genre']) || isset($_GET['wai_year'])
                   || isset($_GET['wai_status']) || isset($_GET['wai_search']);

        if ($has_filter && is_page()) {
            echo '<meta name="robots" content="noindex, follow">' . PHP_EOL;
        }
    }

    // ── Fallback meta ───────────────────────────────────────────────────────

    public static function fallback_meta(): void {
        if (!is_singular('anime')) return;

        $pid   = get_the_ID();
        $desc  = self::yoast_description('');
        $thumb = WAI_Media::cover_url($pid);
        $url   = get_permalink($pid);
        $title = get_the_title($pid);

        echo '<meta name="description" content="' . esc_attr($desc) . '">' . PHP_EOL;
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . PHP_EOL;
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . PHP_EOL;
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . PHP_EOL;
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . PHP_EOL;
        echo '<meta property="og:type" content="video.tv_show">' . PHP_EOL;
        if ($thumb) echo '<meta property="og:image" content="' . esc_url($thumb) . '">' . PHP_EOL;
    }

    // ── Preconnect ──────────────────────────────────────────────────────────

    public static function preconnect_hints(): void {
        if (!is_front_page() && !is_page()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'anime_home')) return;

        echo '<link rel="preconnect" href="https://s4.anilist.co" crossorigin>' . PHP_EOL;
        echo '<link rel="dns-prefetch" href="https://s4.anilist.co">' . PHP_EOL;
    }

    // ── Yoast helpers ───────────────────────────────────────────────────────

    public static function yoast_robots_filter( string $robots ): string {
        $has_filter = isset($_GET['wai_genre']) || isset($_GET['wai_year'])
                   || isset($_GET['wai_status']) || isset($_GET['wai_search']);
        if ($has_filter && is_page()) {
            return 'noindex, follow';
        }
        return $robots;
    }

    public static function yoast_taxonomy_defaults( array $defaults ): array {
        $indexable = ['anime_genre', 'anime_studio', 'anime_year'];
        foreach ($indexable as $tax) {
            if (!isset($defaults[$tax])) {
                $defaults[$tax] = ['noindex' => '0'];
            }
        }
        return $defaults;
    }
}