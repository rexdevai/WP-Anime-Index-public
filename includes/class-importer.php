<?php
defined('ABSPATH') || exit;

class WAI_Importer {

    const ALPHA_START_YEAR = 1960;
    const PENDING          = '__pending__';

    // ── Ciclo automático (cron) ───────────────────────────────────────────────

    public static function run_auto(): array {
        $phase        = (int) get_option('wai_import_phase', 1);
        $offset       = (int) get_option('wai_import_offset', 0);
        $imported_ids = get_option('wai_imported_ids', []);
        $per_page     = 50;
        $page         = (int) floor($offset / $per_page) + 1;
        $status       = get_option('wai_post_status', 'draft');

        if ($phase === 1 || $phase === 2) {
            $mode       = $phase === 1 ? 'popular' : 'top';
            $phase_name = $phase === 1 ? 'Populares' : 'Top valorados';
            $animes     = WAI_AniList::fetch($mode, $page, $per_page);

            if (empty($animes)) {
                update_option('wai_import_phase', $phase + 1);
                update_option('wai_import_offset', 0);
                if ($phase === 2) update_option('wai_import_year', self::ALPHA_START_YEAR);
                WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Fase {$phase} ({$phase_name}) completada. Avanzando a fase " . ($phase + 1) . '.');
                return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
            }

            return self::process_batch($animes, $offset, $per_page, $phase_name, $imported_ids, $status);
        }

        $current_year = (int) date('Y');
        $year         = (int) get_option('wai_import_year', self::ALPHA_START_YEAR);
        $phase_name   = "Alfabético {$year}";

        if ($year > $current_year) {
            update_option('wai_import_phase', 1);
            update_option('wai_import_offset', 0);
            update_option('wai_import_year', self::ALPHA_START_YEAR);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . '] Ciclo completo. Reiniciando desde fase 1.');
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $page_data = WAI_AniList::fetch_alpha_by_year($year, $page, $per_page);
        $animes    = $page_data['media'] ?? [];
        $has_next  = $page_data['pageInfo']['hasNextPage'] ?? false;

        if (empty($animes)) {
            update_option('wai_import_year', $year + 1);
            update_option('wai_import_offset', 0);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Año {$year} completado. Avanzando a " . ($year + 1) . '.');
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $result = self::process_batch($animes, $offset, $per_page, $phase_name, $imported_ids, $status);

        if (!$has_next) {
            update_option('wai_import_year', $year + 1);
            update_option('wai_import_offset', 0);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Última página de {$year}. Avanzando a " . ($year + 1) . '.');
        }

        return $result;
    }

    // ── Procesa un lote con fallback en cadena ───────────────────────────────

    private static function process_batch(
        array $animes,
        int $offset,
        int $per_page,
        string $phase_name,
        array $imported_ids,
        string $status
    ): array {
        $pos_in_page = $offset % $per_page;

        foreach (array_slice($animes, $pos_in_page) as $index => $anime) {
            $anilist_id = (int) $anime['id'];
            $new_offset = $offset + $index + 1;
            $title_log  = $anime['title']['romaji'] ?? 'Sin título';

            $mal_id = (int) ($anime['idMal'] ?? 0);
            if (in_array($anilist_id, $imported_ids, true) || self::exists($anilist_id)) {
                update_option('wai_import_offset', $new_offset);
                WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] [{$phase_name}] Omitido: {$title_log} (AniList ID {$anilist_id})");
                return ['imported' => 0, 'skipped' => 1, 'errors' => 0];
            }

            $result = self::process($anime, $status);
            update_option('wai_import_offset', $new_offset);

            if ($result === 'imported') {
                $imported_ids[] = $anilist_id;
                update_option('wai_imported_ids', $imported_ids);
                WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] [{$phase_name}] Importado (AniList): {$title_log} (ID {$anilist_id})");
                return ['imported' => 1, 'skipped' => 0, 'errors' => 0];
            }

            if ($mal_id > 0) {
                WAI_Admin::error_log('AniList', "Falló al importar '{$title_log}' (ID {$anilist_id}). Intentando Jikan.", 'WARNING');

                $fallback = self::try_fallback($mal_id, $title_log);
                if ($fallback) {
                    $result = self::process($fallback, $status);
                    if ($result === 'imported') {
                        $imported_ids[] = $anilist_id;
                        update_option('wai_imported_ids', $imported_ids);
                        $src = $fallback['_source'] ?? 'fallback';
                        WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] [{$phase_name}] Importado ({$src}): {$title_log} (MAL ID {$mal_id})");
                        return ['imported' => 1, 'skipped' => 0, 'errors' => 0];
                    }
                }
            }

            WAI_Admin::error_log('Importer', "No se pudo importar '{$title_log}' con ninguna API. AniList ID {$anilist_id}, MAL ID {$mal_id}.");
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] [{$phase_name}] Error total: {$title_log}");
            return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
        }

        update_option('wai_import_offset', $offset + $per_page);
        return ['imported' => 0, 'skipped' => count($animes), 'errors' => 0];
    }

    // ── Intenta Jikan, luego Kitsu como fallback ──────────────────────────────

    private static function try_fallback( int $mal_id, string $title_log ): ?array {
        $data = WAI_Jikan::fetch_by_mal_id($mal_id);
        if ($data) {
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Fallback Jikan OK para '{$title_log}'");
            return $data;
        }

        WAI_Admin::error_log('Jikan', "Falló para '{$title_log}' (MAL ID {$mal_id}). Intentando Kitsu.", 'WARNING');

        $data = WAI_Kitsu::fetch_by_mal_id($mal_id);
        if ($data) {
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Fallback Kitsu OK para '{$title_log}'");
            return $data;
        }

        WAI_Admin::error_log('Kitsu', "Falló para '{$title_log}' (MAL ID {$mal_id}). Sin más fallbacks.", 'ERROR');
        return null;
    }

    // ── Importación manual por lotes (botón del panel) ────────────────────────

    public static function run( string $mode = null ): array {
        $mode     = $mode ?: get_option('wai_import_mode', 'popular');
        $per_page = (int) get_option('wai_import_batch', 10);
        $status   = get_option('wai_post_status', 'draft');
        $page     = (int) get_option('wai_import_page', 1);
        $imported = 0; $skipped = 0; $errors = 0;

        $animes = WAI_AniList::fetch($mode, $page, $per_page);

        if (empty($animes)) {
            update_option('wai_import_page', 1);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Sin resultados en página $page. Reiniciando.");
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }

        foreach ($animes as $anime) {
            $result = self::process($anime, $status);
            if ($result === 'imported') {
                $imported++;
                $ids   = get_option('wai_imported_ids', []);
                $ids[] = (int) $anime['id'];
                update_option('wai_imported_ids', array_unique($ids));
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }
            sleep(5);
        }

        update_option('wai_import_page', $page + 1);
        WAI_Admin::log(sprintf('[%s] Lote pág.%d — Importados: %d | Omitidos: %d | Errores: %d',
            current_time('Y-m-d H:i:s'), $page, $imported, $skipped, $errors));

        return compact('imported', 'skipped', 'errors');
    }

    // ── Importar uno por ID desde búsqueda manual ─────────────────────────────

    public static function import_single( int $anilist_id ): bool {
        if (self::exists($anilist_id)) {
            WAI_Admin::log("El anime ID $anilist_id ya está importado.");
            return false;
        }

        $anime = WAI_AniList::fetch_by_id($anilist_id);
        if (!$anime) {
            WAI_Admin::log("No se encontró anime con ID $anilist_id en AniList.");
            return false;
        }

        $status = get_option('wai_post_status', 'draft');
        $result = self::process($anime, $status);

        if ($result === 'imported') {
            $ids   = get_option('wai_imported_ids', []);
            $ids[] = $anilist_id;
            update_option('wai_imported_ids', array_unique($ids));
            WAI_Admin::log("Importado manualmente: {$anime['title']['romaji']} (ID $anilist_id)");
            return true;
        }

        return false;
    }

    // ── Revertir un anime ────────────────────────────────────────────────────

    public static function revert( int $post_id ): bool {
        $anilist_id = (int) get_post_meta($post_id, 'wai_anilist_id', true);
        $title      = get_the_title($post_id);

        $thumb = get_post_thumbnail_id($post_id);
        if ($thumb) wp_delete_attachment($thumb, true);

        $deleted = wp_delete_post($post_id, true);
        if (!$deleted) return false;

        if ($anilist_id) {
            $ids = get_option('wai_imported_ids', []);
            $ids = array_values(array_diff($ids, [$anilist_id]));
            update_option('wai_imported_ids', $ids);
        }

        self::remove_from_pending($post_id);

        WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Revertido: '{$title}' (post {$post_id}, AniList ID {$anilist_id}).");

        // Invalidar cachés del home tras eliminar
        WAI_Home::invalidate_all();

        return true;
    }

    // ── Revertir bulk ────────────────────────────────────────────────────────

    public static function revert_incomplete_batch( int $batch = 10 ): array {
        $pending_ids = get_option('wai_pending_reprocess', []);
        if (empty($pending_ids)) return ['reverted' => 0, 'remaining' => 0];

        $to_process = array_splice($pending_ids, 0, $batch);
        $reverted   = 0;

        foreach ($to_process as $post_id) {
            if (self::revert((int) $post_id)) $reverted++;
        }

        update_option('wai_pending_reprocess', array_values($pending_ids));
        return ['reverted' => $reverted, 'remaining' => count($pending_ids)];
    }

    // ── Completar campos pendientes ─────────────────────────────────────────

    public static function complete_pending( int $post_id ): bool {
        $pending = get_post_meta($post_id, 'wai_pending_fields', true);
        if (empty($pending) || !is_array($pending)) return false;

        $anilist_id = (int) get_post_meta($post_id, 'wai_anilist_id', true);
        $mal_id     = (int) get_post_meta($post_id, 'wai_mal_id', true);
        $anime      = null;

        if ($anilist_id > 0) {
            $anime = WAI_AniList::fetch_by_id($anilist_id);
        }
        if (!$anime && $mal_id > 0) {
            $anime = WAI_AniList::fetch_by_mal_id($mal_id);
        }
        if (!$anime) return false;

        $field_map = [
            'wai_episodes'     => fn($a) => (int) ($a['episodes'] ?? 0),
            'wai_duration'     => fn($a) => (int) ($a['duration'] ?? 0),
            'wai_score'        => fn($a) => (float) ($a['averageScore'] ?? 0),
            'wai_popularity'   => fn($a) => (int) ($a['popularity'] ?? 0),
            'wai_format'       => fn($a) => sanitize_text_field($a['format'] ?? ''),
            'wai_status'       => fn($a) => sanitize_text_field($a['status'] ?? ''),
            'wai_season'       => fn($a) => sanitize_text_field($a['season'] ?? ''),
            'wai_season_year'  => fn($a) => (int) ($a['seasonYear'] ?? 0),
            'wai_trailer_id'   => fn($a) => sanitize_text_field($a['trailer']['id'] ?? ''),
            'wai_trailer_site' => fn($a) => sanitize_text_field($a['trailer']['site'] ?? ''),
            'wai_banner'       => fn($a) => esc_url_raw($a['bannerImage'] ?? ''),
            'wai_cover_url'    => fn($a) => esc_url_raw($a['coverImage']['extraLarge'] ?? $a['coverImage']['large'] ?? ''),
            'wai_title_romaji' => fn($a) => sanitize_text_field($a['title']['romaji'] ?? ''),
            'wai_title_native' => fn($a) => sanitize_text_field($a['title']['native'] ?? ''),
        ];

        $still_pending = [];
        foreach ($pending as $field) {
            if (isset($field_map[$field])) {
                $value = ($field_map[$field])($anime);
                if ($value && $value !== self::PENDING) {
                    update_post_meta($post_id, $field, $value);
                } else {
                    $still_pending[] = $field;
                }
            }
        }

        if (in_array('genres', $pending, true)) {
            self::save_terms($post_id, $anime);
        }

        if (empty($still_pending)) {
            delete_post_meta($post_id, 'wai_pending_fields');
            update_post_meta($post_id, 'wai_import_source', 'anilist');
            self::remove_from_pending($post_id);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Completado OK: post {$post_id}");
            WAI_Home::invalidate_all();
        } else {
            update_post_meta($post_id, 'wai_pending_fields', $still_pending);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Completado parcial: post {$post_id}. Aún pendientes: " . implode(', ', $still_pending));
        }

        return true;
    }

    public static function complete_pending_batch( int $batch = 5 ): array {
        $pending_ids = get_option('wai_pending_reprocess', []);
        if (empty($pending_ids)) return ['completed' => 0, 'remaining' => 0];

        $to_process = array_slice($pending_ids, 0, $batch);
        $completed  = 0;

        foreach ($to_process as $post_id) {
            $still_has_pending = (bool) get_post_meta($post_id, 'wai_pending_fields', true);
            if (!$still_has_pending) {
                self::remove_from_pending($post_id);
                continue;
            }
            if (self::complete_pending((int) $post_id)) {
                $completed++;
            }
        }

        $remaining = count(get_option('wai_pending_reprocess', []));
        return ['completed' => $completed, 'remaining' => $remaining];
    }

    // ── Proceso central ──────────────────────────────────────────────────────

    public static function process( array $anime, string $status = 'draft' ): string {
        $p          = self::PENDING;
        $anilist_id = $anime['id'] !== $p ? (int) $anime['id'] : 0;
        $source     = $anime['_source'] ?? 'anilist';

        if ($anilist_id && self::exists($anilist_id)) return 'skipped';
        $mal_id = (int) ($anime['idMal'] ?? 0);
        if ($mal_id && self::exists_by_mal($mal_id)) return 'skipped';

        $title = $anime['title']['english'] ?? '';
        if (!$title || $title === $p) $title = $anime['title']['romaji'] ?? '';
        if (empty($title) || $title === $p) return 'error';

        $content = WAI_Gemini::generate($anime);
        if (empty($content)) {
            WAI_Admin::log("[Importer][ERROR] Gemini no produjo contenido válido para: {$title}.");
            return 'error';
        }

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($title),
            'post_content' => wp_kses_post($content),
            'post_status'  => $status,
            'post_type'    => 'anime',
            'post_name'    => sanitize_title($title),
        ]);

        if (is_wp_error($post_id) || !$post_id) return 'error';

        $pending_fields = self::detect_pending($anime);

        self::save_meta($post_id, $anime, $source, $pending_fields);
        self::save_terms($post_id, $anime);

        // Cover URL: guardar ambas variantes para srcset
        $cover_xl = $anime['coverImage']['extraLarge'] ?? '';
        $cover_lg = $anime['coverImage']['large']      ?? '';
        if ($cover_xl && $cover_xl !== $p) {
            update_post_meta($post_id, 'wai_cover_url', esc_url_raw($cover_xl));
        }
        if ($cover_lg && $cover_lg !== $p) {
            update_post_meta($post_id, 'wai_cover_url_large', esc_url_raw($cover_lg));
        }

        if (!empty($pending_fields)) {
            self::add_to_pending($post_id);
            WAI_Admin::log('[' . current_time('Y-m-d H:i:s') . "] Importado con campos pendientes ({$source}): {$title}. Pendientes: " . implode(', ', $pending_fields));
        }

        // Invalidar cachés del home + purga dirigida de URLs afectadas
        WAI_Home::invalidate_all();
        WAI_Cache::purge_urls([
            get_permalink($post_id),
            home_url('/'),
            home_url('/directorio/'),
        ]);

        return 'imported';
    }

    // ── Detectar campos pendientes ───────────────────────────────────────────

    private static function detect_pending( array $a ): array {
        $p       = self::PENDING;
        $pending = [];

        $checks = [
            'wai_episodes'     => $a['episodes']     ?? null,
            'wai_duration'     => $a['duration']      ?? null,
            'wai_score'        => $a['averageScore']  ?? null,
            'wai_popularity'   => $a['popularity']    ?? null,
            'wai_format'       => $a['format']        ?? null,
            'wai_status'       => $a['status']        ?? null,
            'wai_season'       => $a['season']        ?? null,
            'wai_season_year'  => $a['seasonYear']    ?? null,
            'wai_trailer_id'   => $a['trailer']['id'] ?? null,
            'wai_banner'       => $a['bannerImage']   ?? null,
            'wai_cover_url'    => $a['coverImage']['extraLarge'] ?? ($a['coverImage']['large'] ?? null),
            'wai_title_romaji' => $a['title']['romaji'] ?? null,
            'genres'           => $a['genres'][0]    ?? null,
        ];

        foreach ($checks as $field => $value) {
            if ($value === null || $value === $p || $value === 0 || $value === '') {
                $pending[] = $field;
            }
        }

        return $pending;
    }

    // ── Helpers de lista de pendientes ───────────────────────────────────────

    public static function add_to_pending( int $post_id ): void {
        $list = get_option('wai_pending_reprocess', []);
        if (!in_array($post_id, $list, true)) {
            $list[] = $post_id;
            update_option('wai_pending_reprocess', $list, false);
        }
    }

    public static function remove_from_pending( int $post_id ): void {
        $list = get_option('wai_pending_reprocess', []);
        $list = array_values(array_diff($list, [$post_id]));
        update_option('wai_pending_reprocess', $list, false);
    }

    // ── Re-importar ──────────────────────────────────────────────────────────

    public static function reimport( int $post_id ): bool {
        $anilist_id = (int) get_post_meta($post_id, 'wai_anilist_id', true);
        if (!$anilist_id) return false;

        $thumb = get_post_thumbnail_id($post_id);
        if ($thumb) wp_delete_attachment($thumb, true);
        wp_delete_post($post_id, true);

        $ids = get_option('wai_imported_ids', []);
        $ids = array_values(array_diff($ids, [$anilist_id]));
        update_option('wai_imported_ids', $ids);

        self::remove_from_pending($post_id);

        WAI_Home::invalidate_all();

        return self::import_single($anilist_id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public static function exists( int $anilist_id ): bool {
        if (!$anilist_id) return false;
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wai_anilist_id' AND meta_value=%d LIMIT 1",
            $anilist_id
        ));
    }

    public static function exists_by_mal( int $mal_id ): bool {
        if (!$mal_id) return false;
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wai_mal_id' AND meta_value=%d LIMIT 1",
            $mal_id
        ));
    }

    private static function save_meta( int $post_id, array $a, string $source, array $pending ): void {
        $p    = self::PENDING;
        $meta = [
            'wai_anilist_id'    => $a['id'] !== $p ? (int) $a['id'] : 0,
            'wai_mal_id'        => (int) ($a['idMal'] ?? 0),
            'wai_import_source' => $source,
            'wai_title_romaji'  => ($v = $a['title']['romaji'] ?? '') !== $p ? sanitize_text_field($v) : '',
            'wai_title_native'  => ($v = $a['title']['native']  ?? '') !== $p ? sanitize_text_field($v) : '',
            'wai_episodes'      => ($v = $a['episodes']    ?? $p) !== $p ? (int) $v   : 0,
            'wai_duration'      => ($v = $a['duration']    ?? $p) !== $p ? (int) $v   : 0,
            'wai_score'         => ($v = $a['averageScore']?? $p) !== $p ? (float) $v : 0,
            'wai_popularity'    => ($v = $a['popularity']  ?? $p) !== $p ? (int) $v   : 0,
            'wai_format'        => ($v = $a['format']      ?? $p) !== $p ? sanitize_text_field($v) : '',
            'wai_status'        => ($v = $a['status']      ?? $p) !== $p ? sanitize_text_field($v) : '',
            'wai_season'        => ($v = $a['season']      ?? $p) !== $p ? sanitize_text_field($v) : '',
            'wai_season_year'   => ($v = $a['seasonYear']  ?? $p) !== $p ? (int) $v   : 0,
            'wai_trailer_id'    => ($v = $a['trailer']['id']   ?? $p) !== $p ? sanitize_text_field($v) : '',
            'wai_trailer_site'  => ($v = $a['trailer']['site'] ?? $p) !== $p ? sanitize_text_field($v) : '',
            'wai_banner'        => ($v = $a['bannerImage'] ?? $p) !== $p ? esc_url_raw($v) : '',
            'wai_franchise'     => self::detect_franchise($a['title']['romaji'] ?? '', $a['title']['english'] ?? ''),
        ];

        foreach ($meta as $key => $value) update_post_meta($post_id, $key, $value);

        if (!empty($pending)) {
            update_post_meta($post_id, 'wai_pending_fields', $pending);
        }
    }

    private static function detect_franchise( string $romaji, string $english ): string {
        $title = $english ?: $romaji;
        $title = preg_replace("/\s+(Season\s*\d+|Part\s*\d+|II+|IV|VI*|Final\s*Season|Movie|OVA|Specials?)$/i", '', trim($title));
        return sanitize_text_field(trim($title));
    }

    private static function save_terms( int $post_id, array $a ): void {
        $p = self::PENDING;
        if (!empty($a['genres']) && $a['genres'][0] !== $p) {
            wp_set_post_terms($post_id, $a['genres'], 'anime_genre');
        }
        $studio = $a['studios']['nodes'][0]['name'] ?? '';
        if ($studio && $studio !== $p) wp_set_post_terms($post_id, [$studio], 'anime_studio');
        $year = $a['seasonYear'] ?? ($a['startDate']['year'] ?? '');
        if ($year && $year !== $p) wp_set_post_terms($post_id, [(string) $year], 'anime_year');
        if (!empty($a['status']) && $a['status'] !== $p) wp_set_post_terms($post_id, [$a['status']], 'anime_status');
        if (!empty($a['season']) && $a['season'] !== $p) wp_set_post_terms($post_id, [$a['season']], 'anime_season');
    }
}