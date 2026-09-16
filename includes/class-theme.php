<?php
defined('ABSPATH') || exit;

class WAI_Theme {

    const OPTION = 'wai_theme_colors';

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 5);
        add_action('wp_head', [__CLASS__, 'bootstrap'], 1);
        add_filter('body_class', [__CLASS__, 'body_class']);
    }

    public static function defaults(): array {
        return [
            'light' => [
                'bg'          => '#f8f8f8',
                'surface'     => '#ffffff',
                'surface2'    => '#f1f3f5',
                'header'      => '#1f2532',
                'header_text' => '#ffffff',
                'header_link' => '#ffffff',
                'header_link_hover' => '#e63946',
                'form_label' => '#1a1a2e',
                'text'        => '#1a1a2e',
                'muted'       => '#66667a',
                'heading'     => '#f50000',
                'link'        => '#1f2532',
                'link_hover'  => '#e63946',
                'accent'      => '#e63946',
                'accent_hover'=> '#c1121f',
                'border'      => '#e2e4e8',
                'input_bg'    => '#ffffff',
                'input_text'  => '#1a1a2e',
            ],
            'dark' => [
                'bg'          => '#0f0f1a',
                'surface'     => '#181828',
                'surface2'    => '#1e1e30',
                'header'      => '#08080d',
                'header_text' => '#ffffff',
                'header_link' => '#ffffff',
                'header_link_hover' => '#ff4d5a',
                'form_label' => '#e8e8f0',
                'text'        => '#e8e8f0',
                'muted'       => '#a0a0bb',
                'heading'     => '#ff4d5a',
                'link'        => '#ffd166',
                'link_hover'  => '#ff4d5a',
                'accent'      => '#e63946',
                'accent_hover'=> '#c1121f',
                'border'      => '#343449',
                'input_bg'    => '#1e1e30',
                'input_text'  => '#f5f5fa',
            ],
        ];
    }

    public static function get_colors(): array {
        $defaults = self::defaults();
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];

        foreach (['light', 'dark'] as $mode) {
            if (!isset($saved[$mode]) || !is_array($saved[$mode])) $saved[$mode] = [];
            foreach ($defaults[$mode] as $key => $value) {
                $candidate = isset($saved[$mode][$key]) ? sanitize_hex_color($saved[$mode][$key]) : '';
                $saved[$mode][$key] = $candidate ?: $value;
            }
        }
        return $saved;
    }

    public static function enqueue(): void {
        $css_path = WAI_DIR . 'assets/css/theme.css';
        $js_path  = WAI_DIR . 'assets/js/theme.js';

        // Usa filemtime para forzar al navegador a descargar la versión nueva
        // cada vez que se modifica el archivo, sin necesidad de bumpear WAI_VERSION.
        $css_ver = file_exists($css_path) ? filemtime($css_path) : WAI_VERSION;
        $js_ver  = file_exists($js_path)  ? filemtime($js_path)  : WAI_VERSION;

        wp_enqueue_style('wai-theme', WAI_URL . 'assets/css/theme.css', [], $css_ver);
        wp_enqueue_script('wai-theme', WAI_URL . 'assets/js/theme.js', [], $js_ver, true);

        $colors = self::get_colors();
        wp_add_inline_style('wai-theme', self::dynamic_css($colors));
        wp_localize_script('wai-theme', 'WAI_THEME', [
            'default' => 'light',
        ]);
    }

    public static function bootstrap(): void {
        echo "<script>(function(){"
           . "try{"
           . "var t=localStorage.getItem('wai-theme-v2');"
           . "if(t!=='dark'&&t!=='light')t='light';"
           . "document.documentElement.setAttribute('data-wai-theme',t);"
           . "}catch(e){"
           . "document.documentElement.setAttribute('data-wai-theme','light');"
           . "}})();</script>\n";
    }

    public static function body_class(array $classes): array {
        // El plugin controla el esquema de color de todo el sitio (blog, singles,
        // archivos, taxonomías, home y páginas del plugin). Se aplica siempre
        // para que las variables --wai-* tengan un consumidor en cualquier vista.
        $classes[] = 'wai-theme-enabled';
        return $classes;
    }

    private static function page_has_wai_shortcode(): bool {
        global $post;
        if (!$post instanceof \WP_Post) return false;
        return has_shortcode($post->post_content, 'anime_index')
            || has_shortcode($post->post_content, 'anime_card')
            || has_shortcode($post->post_content, 'wai_home');
    }

    public static function dynamic_css(array $colors): string {
        $out = [];
        foreach (['light', 'dark'] as $mode) {
            $c = $colors[$mode];
            $selector = 'html[data-wai-theme="' . $mode . '"]';
            $out[] = $selector . '{'
                . '--wai-bg:' . $c['bg'] . ';'
                . '--wai-surface:' . $c['surface'] . ';'
                . '--wai-surface2:' . $c['surface2'] . ';'
                . '--wai-header:' . $c['header'] . ';'
                . '--wai-header-text:' . $c['header_text'] . ';'
                . '--wai-header-link:' . $c['header_link'] . ';'
                . '--wai-header-link-hover:' . $c['header_link_hover'] . ';'
                . '--wai-text:' . $c['text'] . ';'
                . '--wai-text-muted:' . $c['muted'] . ';'
                . '--wai-heading:' . $c['heading'] . ';'
                . '--wai-link:' . $c['link'] . ';'
                . '--wai-link-hover:' . $c['link_hover'] . ';'
                . '--wai-accent:' . $c['accent'] . ';'
                . '--wai-accent-h:' . $c['accent_hover'] . ';'
                . '--wai-border:' . $c['border'] . ';'
                . '--wai-input-bg:' . $c['input_bg'] . ';'
                . '--wai-input-text:' . $c['input_text'] . ';'
                . '--wai-form-label:' . $c['form_label'] . ';'
                . '--wai-gold:#ffd700;'
                . '--wai-radius:10px;'
                . '--wai-shadow:' . ($mode === 'dark' ? '0 4px 24px rgba(0,0,0,.40)' : '0 4px 24px rgba(0,0,0,.12)') . ';'
                . '--wai-transition:.22s cubic-bezier(.4,0,.2,1);'
                . '--ast-global-color-0:var(--wai-text);'
                . '--ast-global-color-1:var(--wai-accent);'
                . '--ast-global-color-2:var(--wai-link);'
                . '--ast-global-color-3:var(--wai-link);'
                . '--ast-global-color-4:var(--wai-surface);'
                . '--ast-global-color-5:var(--wai-surface);'
                . '--ast-global-color-6:var(--wai-surface2);'
                . '--ast-global-color-7:var(--wai-text-muted);'
                . '--ast-global-color-8:var(--wai-border);'
                . '--ast-border-color:' . $c['border'] . ';'
                . '--ast-global-primary-color:' . $c['accent'] . ';'
                . '--ast-global-secondary-color:' . $c['text'] . ';'
                . '}';
        }
        return implode("\n", $out);
    }
}