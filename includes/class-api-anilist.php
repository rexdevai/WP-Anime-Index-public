<?php
defined('ABSPATH') || exit;

class WAI_AniList {

    const ENDPOINT = 'https://graphql.anilist.co';

    private static function query( string $gql, array $vars = [] ): ?array {
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode([
                'query'     => $gql,
                'variables' => $vars,
            ]),
            'timeout' => 20,
        ];

        // Hasta 3 intentos con backoff en 429
        $max_retries = 3;
        $response    = null;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_post(self::ENDPOINT, $args);

            if (is_wp_error($response)) {
                WAI_Admin::log('AniList WP_Error: ' . $response->get_error_message());
                return null;
            }

            $status = wp_remote_retrieve_response_code($response);

            if ($status === 429) {
                // Respeta el header Retry-After si AniList lo manda, si no espera exponencialmente
                $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
                $wait        = $retry_after > 0 ? $retry_after : (10 * $attempt);
                WAI_Admin::log("AniList 429 — esperando {$wait}s (intento {$attempt}/{$max_retries}).");
                sleep($wait);
                continue;
            }

            break; // cualquier otro código sale del loop
        }

    $raw = wp_remote_retrieve_body($response);

    if ($status < 200 || $status >= 300) {
        WAI_Admin::log(
            sprintf(
                'AniList HTTP error %d: %s',
                $status,
                wp_trim_words(wp_strip_all_tags($raw), 40)
            )
        );
        return null;
    }

    $body = json_decode($raw, true);

    if (!is_array($body)) {
        WAI_Admin::log(
            'AniList JSON error: respuesta inválida.'
        );
        return null;
    }

    if (!empty($body['errors'])) {
        foreach ($body['errors'] as $error) {
            $message = isset($error['message'])
                ? (string) $error['message']
                : 'Error GraphQL desconocido';

            WAI_Admin::log(
                'AniList GraphQL error: ' . $message
            );
        }

        return null;
    }

    return isset($body['data']) && is_array($body['data'])
        ? $body['data']
        : null;
}

    // Orden alfabético para el cron automático (incluye pageInfo para saber si hay más)
    public static function fetch_alpha( int $page = 1, int $per_page = 50 ): array {
        $gql = '
        query($page:Int,$perPage:Int){
          Page(page:$page,perPage:$perPage){
            pageInfo { hasNextPage }
            media(type:ANIME,sort:TITLE_ROMAJI,isAdult:false){
              ' . self::fields() . '
            }
          }
        }';
        $data = self::query($gql, ['page' => $page, 'perPage' => $per_page]);
        return $data['Page']['media'] ?? [];
    }

    // Fetch alfabético filtrado por año — evita el límite de 2500 de AniList
    public static function fetch_alpha_by_year( int $year, int $page = 1, int $per_page = 50 ): array {
        $start = $year . '0101';
        $end   = $year . '1231';
        $gql   = '
        query($page:Int,$perPage:Int,$start:FuzzyDateInt,$end:FuzzyDateInt){
          Page(page:$page,perPage:$perPage){
            pageInfo { hasNextPage }
            media(
              type:ANIME,
              sort:TITLE_ROMAJI,
              isAdult:false,
              startDate_greater:$start,
              startDate_lesser:$end
            ){
              ' . self::fields() . '
            }
          }
        }';
        $data = self::query($gql, [
            'page'    => $page,
            'perPage' => $per_page,
            'start'   => (int) $start,
            'end'     => (int) $end,
        ]);
        return $data['Page'] ?? [];  // devuelve Page completo para acceder a pageInfo
    }

    // Búsqueda libre por título (panel manual)
    public static function search( string $query, int $limit = 10 ): array {
        $gql = '
        query($search:String,$page:Int,$perPage:Int){
          Page(page:$page,perPage:$perPage){
            media(type:ANIME,search:$search,isAdult:false,sort:POPULARITY_DESC){
              id
              title { romaji english native }
              coverImage { medium large }
              averageScore seasonYear status episodes genres
            }
          }
        }';
        $data = self::query($gql, ['search' => $query, 'page' => 1, 'perPage' => $limit]);
        return $data['Page']['media'] ?? [];
    }

    // Fetch por modo (popular/top/season) para importación manual por lotes
    public static function fetch( string $mode = 'popular', int $page = 1, int $per_page = 10 ): array {
        if ($mode === 'season') {
            $now    = new DateTime('now', new DateTimeZone('UTC'));
            $month  = (int) $now->format('n');
            $season = self::month_to_season($month);
            $gql    = '
            query($page:Int,$perPage:Int,$season:MediaSeason,$year:Int){
              Page(page:$page,perPage:$perPage){
                media(type:ANIME,season:$season,seasonYear:$year,sort:POPULARITY_DESC,isAdult:false){
                  ' . self::fields() . '
                }
              }
            }';
            $vars = ['page' => $page, 'perPage' => $per_page,
                     'season' => $season, 'year' => (int) $now->format('Y')];
        } else {
            $sort = $mode === 'top' ? 'SCORE_DESC' : 'POPULARITY_DESC';
            $gql  = '
            query($page:Int,$perPage:Int){
              Page(page:$page,perPage:$perPage){
                media(type:ANIME,sort:' . $sort . ',isAdult:false){
                  ' . self::fields() . '
                }
              }
            }';
            $vars = ['page' => $page, 'perPage' => $per_page];
        }
        $data = self::query($gql, $vars);
        return $data['Page']['media'] ?? [];
    }

    // Fetch completo por ID
    public static function fetch_by_id( int $id ): ?array {
        $gql  = 'query($id:Int){ Media(id:$id,type:ANIME){ ' . self::fields() . ' } }';
        $data = self::query($gql, ['id' => $id]);
        return $data['Media'] ?? null;
    }

    public static function fetch_by_mal_id( int $mal_id ): ?array {
        $gql  = 'query($malId:Int){ Media(idMal:$malId,type:ANIME){ ' . self::fields() . ' } }';
        $data = self::query($gql, ['malId' => $mal_id]);
        return $data['Media'] ?? null;
    }

    private static function fields(): string {
        return '
            id
            idMal
            title { romaji english native }
            description(asHtml: false)
            format status episodes duration genres
            averageScore popularity season seasonYear
            studios(isMain: true) { nodes { name } }
            coverImage { large extraLarge }
            bannerImage
            trailer { id site }
            startDate { year month day }
        ';
    }

    private static function month_to_season( int $month ): string {
        if ($month <= 3) return 'WINTER';
        if ($month <= 6) return 'SPRING';
        if ($month <= 9) return 'SUMMER';
        return 'FALL';
    }
}
