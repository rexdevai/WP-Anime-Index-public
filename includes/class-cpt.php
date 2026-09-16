<?php
defined('ABSPATH') || exit;

class WAI_CPT {

    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('init', [__CLASS__, 'register_taxonomies']);

        // Columnas en la lista de posts
        add_filter('manage_anime_posts_columns',       [__CLASS__, 'columns']);
        add_action('manage_anime_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
        add_filter('manage_anime_posts_sortable_columns', [__CLASS__, 'sortable_columns']);

        // Botón revert en row actions
        add_filter('post_row_actions', [__CLASS__, 'row_actions'], 10, 2);

        // Enqueue JS solo en la lista de animes
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_list_scripts']);

        // AJAX handler del revert individual (también registrado en class-admin)
        // No duplicar — class-admin.php lo maneja
    }

    public static function register_cpt() {
        register_post_type('anime', [
            'labels' => [
                'name'          => 'Animes',
                'singular_name' => 'Anime',
                'add_new_item'  => 'Agregar Anime',
                'edit_item'     => 'Editar Anime',
                'search_items'  => 'Buscar Animes',
                'not_found'     => 'No se encontraron animes.',
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'anime'],
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon'    => 'dashicons-video-alt',
            'show_in_rest' => true,
        ]);
    }

    public static function register_taxonomies() {
        $taxes = [
            'anime_genre'  => ['slug' => 'genero',    'label' => 'Géneros'],
            'anime_studio' => ['slug' => 'estudio',   'label' => 'Estudios'],
            'anime_year'   => ['slug' => 'anio',      'label' => 'Año'],
            'anime_status' => ['slug' => 'estado',    'label' => 'Estado'],
            'anime_season' => ['slug' => 'temporada', 'label' => 'Temporada'],
        ];

        foreach ($taxes as $tax => $data) {
            register_taxonomy($tax, 'anime', [
                'labels'       => ['name' => $data['label'], 'singular_name' => $data['label']],
                'public'       => true,
                'rewrite'      => ['slug' => $data['slug']],
                'hierarchical' => false,
                'show_in_rest' => true,
            ]);
        }
    }

    // ── Columnas personalizadas ───────────────────────────────────────────────

    public static function columns( array $cols ): array {
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['wai_source']  = 'Fuente';
                $new['wai_pending'] = 'Estado';
            }
        }
        return $new;
    }

    public static function column_content( string $column, int $post_id ): void {
        if ($column === 'wai_source') {
            $source = get_post_meta($post_id, 'wai_import_source', true) ?: 'anilist';
            $labels = [
                'anilist' => '<span style="color:#02a9ff;">AniList</span>',
                'jikan'   => '<span style="color:#e87722;">Jikan</span>',
                'kitsu'   => '<span style="color:#f75239;">Kitsu</span>',
            ];
            echo $labels[$source] ?? esc_html($source);
        }

        if ($column === 'wai_pending') {
            $pending = get_post_meta($post_id, 'wai_pending_fields', true);
            if (!empty($pending) && is_array($pending)) {
                echo '<span style="color:#a00;font-size:12px;" title="' . esc_attr(implode(', ', $pending)) . '">⚠ Incompleto</span>';
            } else {
                echo '<span style="color:#46b450;font-size:12px;">✓ Completo</span>';
            }
        }
    }

    public static function sortable_columns( array $cols ): array {
        $cols['wai_source'] = 'wai_source';
        return $cols;
    }

    // ── Row action: Revertir ──────────────────────────────────────────────────

    public static function row_actions( array $actions, \WP_Post $post ): array {
        if ($post->post_type !== 'anime') return $actions;

        $nonce = wp_create_nonce('wai_revert_anime_' . $post->ID);
        $actions['wai_revert'] = sprintf(
            '<a href="#" class="wai-revert-single" style="color:#a00;" data-id="%d" data-nonce="%s">Revertir</a>',
            $post->ID,
            $nonce
        );
        return $actions;
    }

    // ── Enqueue JS solo en la lista de posts de anime ─────────────────────────

    public static function enqueue_list_scripts( string $hook ): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'anime' || $screen->base !== 'edit') return;

        wp_enqueue_script(
            'wai-cpt-list',
            WAI_URL . 'assets/js/cpt-list.js',
            [],
            WAI_VERSION,
            true
        );

        wp_localize_script('wai-cpt-list', 'wai_cpt', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'confirm'  => '¿Seguro que quieres revertir y eliminar permanentemente este anime? Esta acción no se puede deshacer.',
        ]);
    }
}
