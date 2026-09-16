<?php
defined('ABSPATH') || exit;

class WAI_Jikan {

    const ENDPOINT  = 'https://api.jikan.moe/v4';
    const PENDING   = '__pending__';

    // ── Cover URL (fallback para animes ya importados) ────────────────────────

    public static function get_cover_url( int $mal_id ): ?string {
        if ($mal_id <= 0) return null;

        $cached = get_transient('wai_jikan_cover_' . $mal_id);
        if ($cached !== false) return $cached ?: null;

        $response = wp_remote_get(self::ENDPOINT . '/anime/' . $mal_id, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) return null;
        if (wp_remote_retrieve_response_code($response) !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $url  = $body['data']['images']['jpg']['large_image_url']
             ?? $body['data']['images']['jpg']['image_url']
             ?? null;

        set_transient('wai_jikan_cover_' . $mal_id, $url ?? '', $url ? WEEK_IN_SECONDS : HOUR_IN_SECONDS);
        return $url;
    }

    // ── Fetch completo por MAL ID (para importación con fallback) ─────────────

    public static function fetch_by_mal_id( int $mal_id ): ?array {
        if ($mal_id <= 0) return null;

        $response = wp_remote_get(self::ENDPOINT . '/anime/' . $mal_id, [
            'timeout' => 12,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            WAI_Admin::error_log('Jikan', "WP_Error para MAL ID {$mal_id}: " . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            WAI_Admin::error_log('Jikan', "HTTP {$code} para MAL ID {$mal_id}");
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['data'])) return null;

        return self::normalize($body['data'], $mal_id);
    }

    // ── Normaliza respuesta Jikan al formato interno del importador ───────────

    public static function normalize( array $d, int $mal_id ): array {
        $p = self::PENDING;

        // Score: Jikan usa 0-10, internamente usamos 0-100
        $score = isset($d['score']) && $d['score'] > 0
            ? (float) $d['score'] * 10
            : $p;

        // Season desde fecha de inicio
        $start_month = (int) ($d['aired']['prop']['from']['month'] ?? 0);
        $start_year  = (int) ($d['aired']['prop']['from']['year']  ?? 0);
        $season      = $start_month > 0 ? self::month_to_season($start_month) : $p;

        // Studios
        $studios = [];
        foreach (($d['studios'] ?? []) as $s) {
            if (!empty($s['name'])) $studios[] = $s['name'];
        }

        // Géneros
        $genres = [];
        foreach (($d['genres'] ?? []) as $g) {
            if (!empty($g['name'])) $genres[] = $g['name'];
        }

        // Cover
        $cover_large = $d['images']['jpg']['large_image_url']
                    ?? $d['images']['jpg']['image_url']
                    ?? $p;

        // Status map Jikan → AniList
        $status_map = [
            'Finished Airing'   => 'FINISHED',
            'Currently Airing'  => 'RELEASING',
            'Not yet aired'     => 'NOT_YET_RELEASED',
        ];
        $status = $status_map[$d['status'] ?? ''] ?? $p;

        // Format map
        $type_map = [
            'TV'      => 'TV',
            'Movie'   => 'MOVIE',
            'OVA'     => 'OVA',
            'ONA'     => 'ONA',
            'Special' => 'SPECIAL',
            'Music'   => 'MUSIC',
        ];
        $format = $type_map[$d['type'] ?? ''] ?? $p;

        return [
            'id'          => $p,   // No hay AniList ID desde Jikan
            'idMal'       => $mal_id,
            '_source'     => 'jikan',
            'title'       => [
                'romaji'  => $d['title_japanese'] ?? ($d['title'] ?? $p),
                'english' => $d['title_english']  ?? $p,
                'native'  => $d['title_japanese'] ?? $p,
            ],
            'description'  => $d['synopsis'] ?? $p,
            'format'       => $format,
            'status'       => $status,
            'episodes'     => $d['episodes'] ?? $p,
            'duration'     => $d['duration']
                                ? (int) preg_replace('/[^0-9]/', '', $d['duration'])
                                : $p,
            'averageScore' => $score,
            'popularity'   => $d['members'] ?? $p,
            'season'       => $season,
            'seasonYear'   => $start_year ?: $p,
            'genres'       => $genres ?: [$p],
            'studios'      => ['nodes' => array_map(fn($s) => ['name' => $s], $studios)],
            'coverImage'   => ['extraLarge' => $cover_large, 'large' => $cover_large],
            'bannerImage'  => $p,
            'trailer'      => ['id' => $p, 'site' => $p],
            'startDate'    => [
                'year'  => $start_year  ?: $p,
                'month' => $start_month ?: $p,
                'day'   => (int) ($d['aired']['prop']['from']['day'] ?? 0) ?: $p,
            ],
        ];
    }

    // ── Ping ──────────────────────────────────────────────────────────────────

    public static function ping(): array {
        $response = wp_remote_get(self::ENDPOINT . '/anime/1', [
            'timeout' => 12,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($response);
        return [
            'ok'    => $code === 200,
            'error' => $code !== 200 ? 'HTTP ' . $code : null,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function month_to_season( int $month ): string {
        if ($month <= 3) return 'WINTER';
        if ($month <= 6) return 'SPRING';
        if ($month <= 9) return 'SUMMER';
        return 'FALL';
    }
}
