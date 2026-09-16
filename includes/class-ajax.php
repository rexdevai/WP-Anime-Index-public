<?php
defined('ABSPATH') || exit;

class WAI_Ajax {

    public static function init(): void {
        add_action('wp_ajax_wai_search',     [__CLASS__, 'search']);
        add_action('wp_ajax_wai_import_one', [__CLASS__, 'import_one']);
    }

    // Búsqueda de animes desde AniList
    public static function search(): void {
        check_ajax_referer('wai_ajax', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        $q = sanitize_text_field($_POST['q'] ?? '');
        if (strlen($q) < 2) wp_send_json_error('Consulta muy corta.');

        $results = WAI_AniList::search($q);

        $out = [];
        foreach ($results as $a) {
            $out[] = [
                'id'       => $a['id'],
                'title'    => $a['title']['english'] ?: $a['title']['romaji'],
                'romaji'   => $a['title']['romaji'],
                'year'     => $a['seasonYear'] ?? '',
                'score'    => $a['averageScore'] ?? 0,
                'episodes' => $a['episodes'] ?? '?',
                'cover'    => $a['coverImage']['large'] ?? '',
                'exists'   => WAI_Importer::exists((int) $a['id']),
            ];
        }

        wp_send_json_success($out);
    }

    // Importar o re-importar un anime por ID
    public static function import_one(): void {
        check_ajax_referer('wai_ajax', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        $id    = (int) ($_POST['anilist_id'] ?? 0);
        $force = (bool) ($_POST['force'] ?? false);

        if (!$id) wp_send_json_error('ID inválido.');

        $result = WAI_Importer::import_one($id, $force);

        if ($result['ok']) wp_send_json_success($result['msg']);
        else wp_send_json_error($result['msg']);
    }
}
