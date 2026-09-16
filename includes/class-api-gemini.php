<?php
defined('ABSPATH') || exit;

class WAI_Gemini {

    const DEFAULT_API_VERSION = 'v1beta';
    const DEFAULT_MODEL       = 'gemini-3.6-flash';
    const DEFAULT_TIMEOUT     = 120;
    const DEFAULT_TEMPERATURE = 0.7;
    const DEFAULT_MAX_TOKENS  = 4096;
    const DEFAULT_THINKING    = 'low';

    public static function generate( array $anime ): ?string {
        $key = trim((string) get_option('wai_gemini_key', ''));
        if ($key === '') {
            WAI_Admin::log('[Gemini][ERROR] API key no configurada.');
            return null;
        }

        $api_version = get_option('wai_gemini_api_version', self::DEFAULT_API_VERSION);
        $api_version = in_array($api_version, ['v1', 'v1beta'], true) ? $api_version : self::DEFAULT_API_VERSION;

        $model = sanitize_text_field((string) get_option('wai_gemini_model', self::DEFAULT_MODEL));
        $model = trim($model) !== '' ? trim($model) : self::DEFAULT_MODEL;

        $timeout = (int) get_option('wai_gemini_timeout', self::DEFAULT_TIMEOUT);
        $timeout = max(30, min(300, $timeout));

        $temperature = (float) get_option('wai_gemini_temperature', self::DEFAULT_TEMPERATURE);
        $temperature = max(0.0, min(2.0, $temperature));

        $max_tokens = (int) get_option('wai_gemini_max_output_tokens', self::DEFAULT_MAX_TOKENS);
        $max_tokens = max(512, min(8192, $max_tokens));

        $thinking_level = get_option('wai_gemini_thinking_level', self::DEFAULT_THINKING);
        $thinking_level = in_array($thinking_level, ['minimal', 'low', 'medium', 'high'], true)
            ? $thinking_level
            : self::DEFAULT_THINKING;

        $prompt = self::build_prompt($anime);

        $generation_config = [
            'temperature'     => $temperature,
            'maxOutputTokens' => $max_tokens,
        ];

        // Gemini 3.x supports thinkingLevel. Do not send it to older models,
        // because those models can reject thinkingLevel as an invalid field.
        if (strpos(strtolower($model), 'gemini-3') === 0) {
            $generation_config['thinkingConfig'] = [
                'thinkingLevel' => $thinking_level,
            ];
        }

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/%s/models/%s:generateContent',
            $api_version,
            rawurlencode($model)
        );

        WAI_Admin::log(sprintf(
            '[Gemini][REQUEST] model=%s | api=%s | timeout=%ss | temperature=%.1f | maxOutputTokens=%d%s',
            $model,
            $api_version,
            $timeout,
            $temperature,
            $max_tokens,
            isset($generation_config['thinkingConfig']) ? ' | thinking=' . $thinking_level : ''
        ));

        $response = wp_remote_post($endpoint . '?key=' . rawurlencode($key), [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => $generation_config,
            ]),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            WAI_Admin::log('[Gemini][ERROR] WP_Error: ' . $response->get_error_message());
            return null;
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body  = wp_remote_retrieve_body($response);
        $body      = json_decode($raw_body, true);

        if ($http_code !== 200) {
            $detail = self::extract_api_error($body, $raw_body);
            WAI_Admin::log(sprintf(
                '[Gemini][ERROR] HTTP %d | model=%s | %s',
                $http_code,
                $model,
                $detail
            ));
            return null;
        }

        if (!is_array($body)) {
            WAI_Admin::log('[Gemini][ERROR] Respuesta JSON inválida.');
            return null;
        }

        $candidate = $body['candidates'][0] ?? [];
        $finish_reason = (string) ($candidate['finishReason'] ?? 'UNKNOWN');
        $block_reason  = (string) ($body['promptFeedback']['blockReason'] ?? '');

        $text_parts = [];
        foreach (($candidate['content']['parts'] ?? []) as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text_parts[] = $part['text'];
            }
        }
        $text = trim(implode("\n", $text_parts));

        $usage = $body['usageMetadata'] ?? [];
        $usage_log = sprintf(
            'prompt=%s | output=%s | thoughts=%s | total=%s',
            isset($usage['promptTokenCount']) ? (string) $usage['promptTokenCount'] : '?',
            isset($usage['candidatesTokenCount']) ? (string) $usage['candidatesTokenCount'] : '?',
            isset($usage['thoughtsTokenCount']) ? (string) $usage['thoughtsTokenCount'] : '?',
            isset($usage['totalTokenCount']) ? (string) $usage['totalTokenCount'] : '?'
        );

        // MAX_TOKENS means the candidate reached the generation limit. Do not
        // publish a potentially truncated article as if it were successful.
        if ($finish_reason === 'MAX_TOKENS') {
            WAI_Admin::log(sprintf(
                '[Gemini][ERROR] Generación truncada por MAX_TOKENS | model=%s | chars=%d | %s',
                $model,
                mb_strlen($text),
                $usage_log
            ));
            return null;
        }

        if ($finish_reason !== 'STOP') {
            $extra = $block_reason !== '' ? ' | blockReason=' . $block_reason : '';
            WAI_Admin::log(sprintf(
                '[Gemini][ERROR] Finalización no normal: finishReason=%s | model=%s | chars=%d%s | %s',
                $finish_reason,
                $model,
                mb_strlen($text),
                $extra,
                $usage_log
            ));
            return null;
        }

        if ($text === '') {
            WAI_Admin::log(sprintf(
                '[Gemini][ERROR] Respuesta vacía | finishReason=STOP | model=%s | %s',
                $model,
                $usage_log
            ));
            return null;
        }

        WAI_Admin::log(sprintf(
            '[Gemini][OK] model=%s | finishReason=STOP | chars=%d | %s',
            $model,
            mb_strlen($text),
            $usage_log
        ));

        return $text;
    }

    private static function extract_api_error( $body, string $raw_body ): string {
        if (is_array($body)) {
            $message = $body['error']['message'] ?? '';
            $status  = $body['error']['status'] ?? '';
            if ($message !== '') {
                return trim(($status ? $status . ': ' : '') . $message);
            }
        }

        $fallback = trim(preg_replace('/\s+/', ' ', $raw_body));
        return $fallback !== '' ? mb_substr($fallback, 0, 800) : 'Sin detalle en la respuesta.';
    }

    private static function build_prompt( array $a ): string {
        $titulo    = $a['title']['english'] ?: $a['title']['romaji'];
        $japones   = $a['title']['native'] ?? '';
        $generos   = implode(', ', $a['genres'] ?? []);
        $episodios = $a['episodes'] ?? 'Desconocido';
        $estado    = self::translate_status($a['status'] ?? '');
        $estudio   = $a['studios']['nodes'][0]['name'] ?? 'Desconocido';
        $anio      = $a['seasonYear'] ?? ($a['startDate']['year'] ?? 'Desconocido');
        $score     = isset($a['averageScore']) ? $a['averageScore'] . '/100' : 'Sin puntuación';
        $synopsis  = strip_tags($a['description'] ?? 'No disponible');
        $synopsis  = mb_substr($synopsis, 0, 600);

        return <<<PROMPT
Eres un editor de contenido especializado en anime para audiencia hispanohablante general.

Con los siguientes datos, escribe un artículo editorial en español latino de entre 250 y 800 palabras.

REGLAS:
- No copies ni parafrasees la sinopsis oficial
- No uses frases genéricas como "imperdible" u "obra maestra"
- Tono accesible, como si le explicaras a alguien que nunca ha visto anime
- Incluye: a quién va dirigido, qué lo hace distinto, contexto cultural de forma natural
- No inventes datos que no estén en los proporcionados
- Termina con una sección "¿Para quién es?" con exactamente 3 bullets cortos
- No agregues títulos ni encabezados, solo el artículo y los bullets al final
- La respuesta debe quedar completamente terminada; no cortes palabras, frases ni párrafos
- Si necesitas reducir contenido para terminar, elimina detalles secundarios antes de truncar la respuesta

DATOS:
Título: {$titulo}
Título original: {$japones}
Géneros: {$generos}
Episodios: {$episodios}
Estado: {$estado}
Estudio: {$estudio}
Año: {$anio}
Puntuación: {$score}
Sinopsis oficial: {$synopsis}
PROMPT;
    }

    private static function translate_status( string $s ): string {
        $map = [
            'FINISHED'         => 'Finalizado',
            'RELEASING'        => 'En emisión',
            'NOT_YET_RELEASED' => 'Próximamente',
            'CANCELLED'        => 'Cancelado',
            'HIATUS'           => 'En pausa',
        ];
        return $map[$s] ?? $s;
    }
}
