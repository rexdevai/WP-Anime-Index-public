<?php
defined('ABSPATH') || exit;

class WAI_Kitsu {

    const ENDPOINT = 'https://kitsu.io/api/edge';
    const PENDING  = '__pending__';

    // ── Cover URL (fallback para animes ya importados) ────────────────────────

    public static function get_cover_url( int $mal_id ): ?string {
        if ($mal_id <= 0) return null;

        $cached = get_transient('wai_kitsu_cover_' . $mal_id);
        if ($cached !== false) return $cached ?: null;

        $url = add_query_arg([
            'filter[mappings]' => 'myanimelist/anime,' . $mal_id,
            'fields[anime]'    => 'posterImage',
            'page[limit]'      => 1,
        ], self::ENDPOINT . '/anime');

        $response = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'Accept'       => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ],
        ]);

        if (is_wp_error($response)) return null;
        if (wp_remote_retrieve_response_code($response) !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $img  = $body['data'][0]['attributes']['posterImage']['large']
             ?? $body['data'][0]['attributes']['posterImage']['medium']
             ?? $body['data'][0]['attributes']['posterImage']['original']
             ?? null;

        set_transient('wai_kitsu_cover_' . $mal_id, $img ?? '', $img ? WEEK_IN_SECONDS : HOUR_IN_SECONDS);
        return $img;
    }

    // ── Fetch completo por MAL ID (para importación con fallback) ─────────────

    public static function fetch_by_mal_id( int $mal_id ): ?array {
        if ($mal_id <= 0) return null;

        $url = add_query_arg([
            'filter[mappings]' => 'myanimelist/anime,' . $mal_id,
            'page[limit]'      => 1,
        ], self::ENDPOINT . '/anime');

        $response = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'Accept'       => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ],
        ]);

        if (is_wp_error($response)) {
            WAI_Admin::error_log('Kitsu', "WP_Error para MAL ID {$mal_id}: " . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            WAI_Admin::error_log('Kitsu', "HTTP {$code} para MAL ID {$mal_id}");
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['data'][0])) return null;

        return self::normalize($body['data'][0]['attributes'] ?? [], $mal_id);
    }

    // ── Normaliza respuesta Kitsu al formato interno del importador ───────────

    public static function normalize( array $a, int $mal_id ): array {
        $p = self::PENDING;

        // Score: Kitsu usa 0-100 string
        $score = isset($a['averageRating']) && $a['averageRating'] > 0
            ? (float) $a['averageRating']
            : $p;

        // Season desde startDate (YYYY-MM-DD)
        $start_year  = 0;
        $start_month = 0;
        if (!empty($a['startDate'])) {
            $parts       = explode('-', $a['startDate']);
            $start_year  = (int) ($parts[0] ?? 0);
            $start_month = (int) ($parts[1] ?? 0);
        }
        $season = $start_month > 0 ? self::month_to_season($start_month) : $p;

        // Cover
        $cover = $a['posterImage']['large']
              ?? $a['posterImage']['medium']
              ?? $a['posterImage']['original']
              ?? $p;

        // Status map Kitsu → AniList
        $status_map = [
            'finished'   => 'FINISHED',
            'current'    => 'RELEASING',
            'upcoming'   => 'NOT_YET_RELEASED',
            'tba'        => 'NOT_YET_RELEASED',
            'unreleased' => 'NOT_YET_RELEASED',
        ];
        $status = $status_map[strtolower($a['status'] ?? '')] ?? $p;

        // Format map
        $type_map = [
            'TV'      => 'TV',
            'movie'   => 'MOVIE',
            'OVA'     => 'OVA',
            'ONA'     => 'ONA',
            'special' => 'SPECIAL',
            'music'   => 'MUSIC',
        ];
        $format = $type_map[$a['showType'] ?? ''] ?? $p;

        // Kitsu no devuelve studios ni géneros en el endpoint base de anime
        // Se necesitaría un request adicional a /categories — dejamos pending
        return [
            'id'          => $p,
            'idMal'       => $mal_id,
            '_source'     => 'kitsu',
            'title'       => [
                'romaji'  => $a['titles']['en_jp'] ?? ($a['canonicalTitle'] ?? $p),
                'english' => $a['titles']['en']    ?? $p,
                'native'  => $a['titles']['ja_jp'] ?? $p,
            ],
            'description'  => $a['synopsis'] ?? $p,
            'format'       => $format,
            'status'       => $status,
            'episodes'     => $a['episodeCount'] ?? $p,
            'duration'     => $a['episodeLength'] ?? $p,
            'averageScore' => $score,
            'popularity'   => $p,  // Kitsu no expone popularity en este endpoint
            'season'       => $season,
            'seasonYear'   => $start_year ?: $p,
            'genres'       => [$p],  // Requeriría /categories — pending
            'studios'      => ['nodes' => []],
            'coverImage'   => ['extraLarge' => $cover, 'large' => $cover],
            'bannerImage'  => $a['coverImage']['original'] ?? $p,
            'trailer'      => ['id' => $p, 'site' => $p],
            'startDate'    => [
                'year'  => $start_year  ?: $p,
                'month' => $start_month ?: $p,
                'day'   => 0,
            ],
        ];
    }

    // ── Ping ──────────────────────────────────────────────────────────────────

    public static function ping(): array {
        $url      = self::ENDPOINT . '/anime?page[limit]=1&fields[anime]=id';
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.api+json'],
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
