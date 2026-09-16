<?php
defined('ABSPATH') || exit;

class WAI_Admin {

    public static function init(): void {
        add_action('admin_menu',                         [__CLASS__, 'menu']);
        add_action('admin_post_wai_import',              [__CLASS__, 'handle_manual_import']);
        add_action('admin_post_wai_save_settings',       [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_wai_reset_page',          [__CLASS__, 'handle_reset_page']);
        add_action('admin_post_wai_reimport',            [__CLASS__, 'handle_reimport']);
        add_action('admin_enqueue_scripts',              [__CLASS__, 'enqueue']);
        add_action('admin_post_wai_save_appearance',     [__CLASS__, 'handle_save_appearance']);
        add_action('admin_post_wai_migrate_covers',      [__CLASS__, 'handle_migrate_covers']);
        add_action('wp_head',                             [__CLASS__, 'inject_page_title_css']);
        add_action('wp_ajax_wai_search_anime',           [__CLASS__, 'ajax_search_anime']);
        add_action('wp_ajax_wai_import_single',          [__CLASS__, 'ajax_import_single']);
        add_action('wp_ajax_wai_migrate_covers',         [__CLASS__, 'ajax_migrate_covers']);
        add_action('wp_ajax_wai_retry_covers',           [__CLASS__, 'ajax_retry_covers']);
        add_action('wp_ajax_wai_api_status',             [__CLASS__, 'ajax_api_status']);
        add_action('wp_ajax_wai_revert_anime',           [__CLASS__, 'ajax_revert_anime']);
        add_action('wp_ajax_wai_revert_bulk',            [__CLASS__, 'ajax_revert_bulk']);
        add_action('wp_ajax_wai_complete_pending',       [__CLASS__, 'ajax_complete_pending']);

        // Purga de caché
        add_action('admin_bar_menu',                     [__CLASS__, 'admin_bar_purge_button'], 999);
        add_action('wp_ajax_wai_purge_all_cache',        ['WAI_Cache', 'ajax_purge_all']);
        add_action('wp_ajax_wai_purge_single_url',       ['WAI_Cache', 'ajax_purge_single_url']);
        add_action('admin_post_wai_save_cloudflare',     [__CLASS__, 'handle_save_cloudflare']);

        // El script del admin bar debe cargarse tanto en admin como en frontend
        add_action('wp_footer',                          [__CLASS__, 'print_admin_bar_script'], 100);
        add_action('admin_footer',                       [__CLASS__, 'print_admin_bar_script'], 100);
    }

    public static function menu(): void {
        add_menu_page('Anime Index', 'Anime Index', 'manage_options', 'wai-settings',
            [__CLASS__, 'page_settings'], 'dashicons-video-alt', 30);
        add_submenu_page('wai-settings', 'Configuración', 'Configuración',
            'manage_options', 'wai-settings', [__CLASS__, 'page_settings']);
        add_submenu_page('wai-settings', 'Importar manual', 'Importar manual',
            'manage_options', 'wai-import-manual', [__CLASS__, 'page_import_manual']);
        add_submenu_page('wai-settings', 'Apariencia', 'Apariencia',
            'manage_options', 'wai-appearance', [__CLASS__, 'page_appearance']);
        add_submenu_page('wai-settings', 'Pendientes', 'Pendientes',
            'manage_options', 'wai-pending', [__CLASS__, 'page_pending']);
        add_submenu_page('wai-settings', 'Registros', self::menu_label_registros(),
            'manage_options', 'wai-logs', [__CLASS__, 'page_logs']);
    }

    /**
     * Etiqueta del submenú "Registros" con badge rojo si hay errores.
     */
    private static function menu_label_registros(): string {
        $count = count(get_option('wai_error_log', []));
        if ($count === 0) {
            return 'Registros';
        }
        return 'Registros <span class="wai-badge-error">' . (int) $count . '</span>';
    }

    public static function enqueue( string $hook ): void {
        if (strpos($hook, 'wai') === false) {
            return;
        }

        $admin_css = WAI_DIR . 'assets/css/admin.css';
        wp_enqueue_style(
            'wai-admin',
            WAI_URL . 'assets/css/admin.css',
            [],
            file_exists($admin_css) ? filemtime($admin_css) : WAI_VERSION
        );

        if (strpos($hook, 'wai-import-manual') !== false) {
            $search_js = WAI_DIR . 'assets/js/admin-search.js';
            wp_enqueue_script(
                'wai-admin-search',
                WAI_URL . 'assets/js/admin-search.js',
                ['jquery'],
                file_exists($search_js) ? filemtime($search_js) : WAI_VERSION,
                true
            );
            wp_localize_script('wai-admin-search', 'wai_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('wai_search_nonce'),
            ]);
        }

        $needs_migrate = strpos($hook, 'wai-settings') !== false || strpos($hook, 'wai-pending') !== false;

        if ($needs_migrate) {
            $migrate_js = WAI_DIR . 'assets/js/admin-migrate.js';
            wp_enqueue_script(
                'wai-admin-migrate',
                WAI_URL . 'assets/js/admin-migrate.js',
                ['jquery'],
                file_exists($migrate_js) ? filemtime($migrate_js) : WAI_VERSION,
                true
            );
            wp_localize_script('wai-admin-migrate', 'wai_migrate', [
                'ajax_url'              => admin_url('admin-ajax.php'),
                'nonce'                 => wp_create_nonce('wai_migrate_nonce'),
                'api_status_nonce'      => wp_create_nonce('wai_api_status_nonce'),
                'revert_bulk_nonce'     => wp_create_nonce('wai_revert_bulk_nonce'),
                'complete_pending_nonce'=> wp_create_nonce('wai_complete_pending_nonce'),
                'purge_nonce'           => wp_create_nonce('wai_purge_cache_nonce'),
            ]);
        }

        // Script de tabs y filtros del módulo Registros
        if (strpos($hook, 'wai-logs') !== false) {
            $logs_js = WAI_DIR . 'assets/js/admin-logs.js';
            wp_enqueue_script(
                'wai-admin-logs',
                WAI_URL . 'assets/js/admin-logs.js',
                [],
                file_exists($logs_js) ? filemtime($logs_js) : WAI_VERSION,
                true
            );
        }
    }

    /**
     * Imprime el JS del admin bar en el footer.
     * Se engancha a wp_footer (frontend) y admin_footer (backend).
     */
    public static function print_admin_bar_script(): void {
        if (!current_user_can('manage_options')) return;
        if (!function_exists('is_admin_bar_showing') || !is_admin_bar_showing()) return;

        $cfg = [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'purge_nonce' => wp_create_nonce('wai_purge_cache_nonce'),
        ];
        ?>
        <script>
        window.WAI_ADMIN_BAR = <?php echo wp_json_encode($cfg); ?>;
        <?php echo self::admin_bar_inline_js(); ?>
        </script>
        <?php
    }

    private static function admin_bar_inline_js(): string {
        return <<<'JS'
(function () {
    'use strict';
    var cfg = window.WAI_ADMIN_BAR || {};

    document.addEventListener('click', function (e) {
        var urlBtn = e.target.closest('.wai-purge-url-btn');
        var allBtn = e.target.closest('.wai-purge-all-btn');

        if (urlBtn) {
            e.preventDefault();
            e.stopPropagation();
            purgeUrl(urlBtn);
            return;
        }

        if (allBtn) {
            e.preventDefault();
            e.stopPropagation();
            purgeAll(allBtn);
        }
    });

    function purgeUrl(triggerEl) {
        var url = window.location.href;

        if (!confirm('¿Purgar la caché solo de esta URL?\n\n' + url)) return;

        var fd = new FormData();
        fd.append('action', 'wai_purge_single_url');
        fd.append('nonce',  cfg.purge_nonce);
        fd.append('url',    url);

        setItemBusy(triggerEl, '⏳ Purgando...');
        showTopNotice('⏳ Purgando la URL...', 'info');

        fetch(cfg.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var clean = text.slice(text.indexOf('{'));
                var r;
                try { r = JSON.parse(clean); }
                catch (e) { showTopNotice('❌ Respuesta inválida del servidor', 'error'); return; }

                if (r.success && r.data) {
                    var parts = [];
                    Object.keys(r.data).forEach(function (k) { parts.push(k + ': ' + r.data[k]); });
                    showTopNotice('✅ URL purgada correctamente.<br><small>' + parts.join(' · ') + '</small>', 'success');
                    setTimeout(function () { window.location.reload(); }, 1000);
                } else {
                    showTopNotice('❌ Error: ' + (r.data || 'desconocido'), 'error');
                }
            })
            .catch(function (err) {
                showTopNotice('❌ Error de conexión: ' + err.message, 'error');
            })
            .finally(function () {
                setItemBusy(triggerEl, null);
            });
    }

    function purgeAll(triggerEl) {
        var msg = '¿Purgar TODA la caché del sitio?\n\n'
                + '⚠️ ADVERTENCIA:\n'
                + '• Se purgarán LiteSpeed, Cloudflare y transients de TODO el sitio.\n'
                + '• Si tienes muchas URLs, Cloudflare puede tardar y consumir cuota de API.\n'
                + '• El sitio puede ir más lento durante 1-2 minutos mientras se regenera la caché.\n'
                + '• Los usuarios activos verán la carga directa a PHP hasta que se regenere.\n'
                + '• Si tienes un sitio con tráfico alto, considera purgar solo URLs específicas.\n\n'
                + '¿Continuar con la purga total?';

        if (!confirm(msg)) return;

        var fd = new FormData();
        fd.append('action', 'wai_purge_all_cache');
        fd.append('nonce',  cfg.purge_nonce);

        setItemBusy(triggerEl, '⏳ Purga total...');
        showTopNotice('⏳ Purgando TODA la caché del sitio...', 'info');

        fetch(cfg.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var clean = text.slice(text.indexOf('{'));
                var r;
                try { r = JSON.parse(clean); }
                catch (e) { showTopNotice('❌ Respuesta inválida del servidor', 'error'); return; }

                if (r.success && r.data) {
                    var items = [];
                    Object.keys(r.data).forEach(function (k) {
                        var val = String(r.data[k]);
                        var icon = /purgado|borrado|flusheado|reseteado|OK/i.test(val) ? '✅'
                                 : (/(no detectado|no disponible|no configurado)/i.test(val) ? '➖' : '⚠️');
                        items.push(icon + ' ' + k + ': ' + val);
                    });
                    showTopNotice('✅ Purga total completada.<br><small>' + items.join('<br>') + '</small>', 'success', 8000);
                } else {
                    showTopNotice('❌ Error: ' + (r.data || 'desconocido'), 'error');
                }
            })
            .catch(function (err) {
                showTopNotice('❌ Error de conexión: ' + err.message, 'error');
            })
            .finally(function () {
                setItemBusy(triggerEl, null);
            });
    }

    function setItemBusy(el, label) {
        if (!el) return;
        var target = el.tagName === 'A' ? el : el.querySelector('a');
        if (!target) target = el;
        if (label === null) {
            if (target.dataset.waiOrigLabel) {
                target.textContent = target.dataset.waiOrigLabel;
                delete target.dataset.waiOrigLabel;
            }
            target.style.opacity = '';
        } else {
            if (!target.dataset.waiOrigLabel) {
                target.dataset.waiOrigLabel = target.textContent;
            }
            target.textContent = label;
            target.style.opacity = '0.6';
        }
    }

    function showTopNotice(html, type, timeout) {
        var existing = document.getElementById('wai-bar-notice');
        if (existing) existing.remove();

        var colors = {
            success: { bg: '#edfaef', border: '#46b450' },
            error:   { bg: '#fde8e8', border: '#dc3232' },
            info:    { bg: '#eef6fc', border: '#2271b1' }
        };
        var c = colors[type] || colors.info;

        var el = document.createElement('div');
        el.id = 'wai-bar-notice';
        el.style.cssText = 'position:fixed;top:46px;right:20px;z-index:999999;'
            + 'background:' + c.bg + ';'
            + 'border-left:4px solid ' + c.border + ';'
            + 'color:#1a1a2e;'
            + 'padding:12px 18px;border-radius:4px;'
            + 'box-shadow:0 4px 16px rgba(0,0,0,.18);'
            + 'font-size:13px;max-width:420px;line-height:1.5;'
            + 'transition:opacity .25s;';
        el.innerHTML = html;
        document.body.appendChild(el);

        var wait = timeout || 5000;
        if (type !== 'info') {
            setTimeout(function () {
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 300);
            }, wait);
        }
    }
})();
JS;
    }

    // ── Admin bar ───────────────────────────────────────────────────────────

    public static function admin_bar_purge_button($wp_admin_bar): void {
        if (!current_user_can('manage_options')) return;

        $wp_admin_bar->add_node([
            'id'    => 'wai-purge',
            'title' => '🧹 Caché WAI',
            'href'  => '#',
            'meta'  => [
                'title' => 'Herramientas de caché del sitio',
            ],
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'wai-purge-url',
            'parent' => 'wai-purge',
            'title'  => '🧹 Purgar esta URL',
            'href'   => '#',
            'meta'   => [
                'title' => 'Purga solo la URL que estás viendo',
                'class' => 'wai-purge-url-btn',
            ],
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'wai-purge-all',
            'parent' => 'wai-purge',
            'title'  => '☢️ Purga Total',
            'href'   => '#',
            'meta'   => [
                'title' => 'Purgar TODA la caché del sitio (LiteSpeed, Cloudflare, transients...)',
                'class' => 'wai-purge-all-btn',
            ],
        ]);
    }

    // ── Configuración ───────────────────────────────────────────────────────

    public static function page_settings(): void {
        if (!current_user_can('manage_options')) return;
        $imported_count = count(get_option('wai_imported_ids', []));
        $offset         = (int) get_option('wai_import_offset', 0);
        $next_ts        = wp_next_scheduled(WAI_Cron::HOOK);
        $cf             = get_option('wai_cloudflare_config', []);
        ?>
        <div class="wrap wai-wrap">
            <h1>⚡ Anime Index — Configuración</h1>

            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['cf_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>Configuración de Cloudflare guardada.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['imported'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Importación: <strong><?php echo (int)$_GET['imported']; ?> importados</strong>,
                    <?php echo (int)$_GET['skipped']; ?> omitidos, <?php echo (int)$_GET['errors']; ?> errores.</p>
                </div>
            <?php endif; ?>

            <div class="wai-stats-box">
                <strong>📊 Estado del importador automático</strong>
                <ul>
                    <li>Fase actual: <strong><?php
                    $ph = (int)get_option('wai_import_phase',1);
                    echo ['1'=>'1 — Populares','2'=>'2 — Top valorados','3'=>'3 — Alfabético'][$ph] ?? $ph;
                ?></strong></li>
                <li>Offset actual: <strong><?php echo $offset; ?></strong></li>
                    <li>Animes en registro: <strong><?php echo $imported_count; ?></strong></li>
                    <li>Próxima ejecución del cron: <strong>
                        <?php
                        if ($next_ts) {
                            echo date_i18n('d/m/Y H:i:s', $next_ts) . ' (en ' . human_time_diff(time(), $next_ts) . ')';
                        } elseif (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                            echo '✅ Cron externo activo (gestionado por el servidor)';
                        } else {
                            echo '⚠️ No programado';
                        }
                        ?>
                    </strong></li>
                </ul>
            </div>

            <div class="wai-stats-box wai-api-status-box" style="margin-top:12px;">
                <strong>🌐 Estado de APIs externas</strong>
                <ul class="wai-api-status-list" style="margin-top:8px;">
                    <li>
                        <span class="wai-api-dot" id="wai-dot-anilist" style="background:#ccc"></span>
                        <span>AniList</span>
                        <em id="wai-label-anilist" style="color:#999;margin-left:6px;">Verificando...</em>
                    </li>
                    <li style="margin-top:6px;">
                        <span class="wai-api-dot" id="wai-dot-jikan" style="background:#ccc"></span>
                        <span>Jikan (MAL)</span>
                        <em id="wai-label-jikan" style="color:#999;margin-left:6px;">Verificando...</em>
                    </li>
                    <li style="margin-top:6px;">
                        <span class="wai-api-dot" id="wai-dot-kitsu" style="background:#ccc"></span>
                        <span>Kitsu</span>
                        <em id="wai-label-kitsu" style="color:#999;margin-left:6px;">Verificando...</em>
                    </li>
                </ul>
                <button type="button" id="wai-check-apis" class="button button-small" style="margin-top:10px;">↺ Verificar ahora</button>
            </div>

            <div class="wai-stats-box" style="margin-top:12px;">
                <strong>⚡ Estado del rendimiento</strong>
                <ul>
                    <li>Object cache persistente (Redis/Memcached):
                        <strong style="color:<?php echo wp_using_ext_object_cache() ? '#46b450' : '#dba617'; ?>">
                            <?php echo wp_using_ext_object_cache() ? '✅ Activo' : '⚠️ No detectado (usa transients)'; ?>
                        </strong>
                    </li>
                    <li>LiteSpeed Cache:
                        <strong style="color:<?php echo defined('LSCWP_V') ? '#46b450' : '#999'; ?>">
                            <?php echo defined('LSCWP_V') ? '✅ Detectado' : '➖ No detectado'; ?>
                        </strong>
                    </li>
                    <li>Cloudflare:
                        <strong style="color:<?php echo (!empty($cf['zone_id']) && !empty($cf['api_token'])) ? '#46b450' : '#dba617'; ?>">
                            <?php echo (!empty($cf['zone_id']) && !empty($cf['api_token'])) ? '✅ Configurado' : '⚠️ Sin configurar'; ?>
                        </strong>
                    </li>
                </ul>
                <?php if (!wp_using_ext_object_cache()): ?>
                    <p style="margin-top:6px;font-size:12px;color:#666;">
                        Recomendación: instalá el plugin <a href="<?php echo admin_url('plugin-install.php?s=redis+object+cache&tab=search&type=term'); ?>">Redis Object Cache</a> o Memcached para que el home se renderice desde memoria y no desde MySQL. Mejora típica de <strong>40–60% en TTFB</strong> del home.
                    </p>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wai_save_settings">
                <?php wp_nonce_field('wai_save_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>API Key de Gemini</th>
                        <td>
                            <?php $k = get_option('wai_gemini_key', ''); ?>
                            <input type="password" name="wai_gemini_key" value="<?php echo esc_attr($k); ?>"
                                class="regular-text" autocomplete="off">
                            <?php if ($k): ?><span style="color:green;margin-left:8px">✔ Guardada</span><?php endif; ?>
                            <p class="description">Obtén tu key en <a href="https://aistudio.google.com" target="_blank">aistudio.google.com</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Versión de la API</th>
                        <td>
                            <select name="wai_gemini_api_version">
                                <?php foreach (['v1beta' => 'v1beta (recomendado)', 'v1' => 'v1'] as $v => $l):
                                    printf('<option value="%s"%s>%s</option>', $v, selected(get_option('wai_gemini_api_version','v1beta'), $v, false), $l);
                                endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Modelo Gemini</th>
                        <td>
                            <input type="text" name="wai_gemini_model"
                                value="<?php echo esc_attr(get_option('wai_gemini_model','gemini-3.6-flash')); ?>"
                                class="regular-text" placeholder="gemini-3.6-flash">
                            <p class="description">Nombre exacto del modelo. Ej: gemini-3.6-flash</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Timeout Gemini (seg)</th>
                        <td>
                            <input type="number" name="wai_gemini_timeout" min="30" max="300"
                                value="<?php echo (int)get_option('wai_gemini_timeout', 120); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Temperatura Gemini</th>
                        <td>
                            <input type="number" name="wai_gemini_temperature" min="0" max="2" step="0.1"
                                value="<?php echo esc_attr(get_option('wai_gemini_temperature', 0.7)); ?>" class="small-text">
                            <p class="description">Valor usado en generationConfig. Predeterminado: 0.7.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Máximo de tokens de salida</th>
                        <td>
                            <input type="number" name="wai_gemini_max_output_tokens" min="512" max="8192" step="1"
                                value="<?php echo (int)get_option('wai_gemini_max_output_tokens', 4096); ?>" class="small-text">
                            <p class="description">Límite de salida para evitar que un artículo quede truncado. Predeterminado: 4096.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Nivel de razonamiento Gemini 3</th>
                        <td>
                            <select name="wai_gemini_thinking_level">
                                <?php foreach (['minimal'=>'Minimal','low'=>'Low (recomendado)','medium'=>'Medium','high'=>'High'] as $v => $l):
                                    printf('<option value="%s"%s>%s</option>', $v, selected(get_option('wai_gemini_thinking_level','low'), $v, false), $l);
                                endforeach; ?>
                            </select>
                            <p class="description">Solo se envía a modelos Gemini 3.x. Para generación editorial usamos Low para dejar más presupuesto a la respuesta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Estado de posts importados</th>
                        <td>
                            <select name="wai_post_status">
                                <?php foreach (['draft' => 'Borrador', 'publish' => 'Publicado'] as $v => $l):
                                    printf('<option value="%s"%s>%s</option>', $v, selected(get_option('wai_post_status','draft'), $v, false), $l);
                                endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Frecuencia del cron</th>
                        <td>
                            <select name="wai_cron_freq">
                                <?php
                                $freqs = ['10min' => 'Cada 10 min', '30min' => 'Cada 30 min',
                                          'hourly' => 'Cada hora', 'twicedaily' => 'Dos veces al día', 'daily' => 'Diario'];
                                $cur   = get_option('wai_cron_freq', '10min');
                                foreach ($freqs as $v => $l) printf('<option value="%s"%s>%s</option>', $v, selected($cur, $v, false), $l);
                                ?>
                            </select>
                            <p class="description">En cada ejecución se importa 1 anime en orden alfabético.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Modo lote manual</th>
                        <td>
                            <select name="wai_import_mode">
                                <?php foreach (['popular' => 'Más populares', 'top' => 'Mejor puntuados', 'season' => 'Temporada actual'] as $v => $l):
                                    printf('<option value="%s"%s>%s</option>', $v, selected(get_option('wai_import_mode','popular'), $v, false), $l);
                                endforeach; ?>
                            </select>
                            <p class="description">Usado solo con el botón "Importar lote ahora".</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Animes por lote manual</th>
                        <td>
                            <input type="number" name="wai_import_batch" min="1" max="50"
                                value="<?php echo (int)get_option('wai_import_batch', 10); ?>" class="small-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar configuración'); ?>
            </form>

            <hr>
            <h2>Cloudflare</h2>
            <p>Configura el Zone ID y un API Token con permiso <code>Zone → Cache Purge</code> para que la purga total también limpie Cloudflare.</p>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wai_save_cloudflare">
                <?php wp_nonce_field('wai_save_cloudflare'); ?>
                <table class="form-table">
                    <tr>
                        <th>Zone ID</th>
                        <td>
                            <input type="text" name="wai_cf_zone_id" value="<?php echo esc_attr($cf['zone_id'] ?? ''); ?>" class="regular-text">
                            <p class="description">Encontralo en el dashboard de Cloudflare → Overview de tu dominio (columna derecha).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>API Token</th>
                        <td>
                            <input type="password" name="wai_cf_api_token" value="<?php echo esc_attr($cf['api_token'] ?? ''); ?>" class="regular-text" autocomplete="off">
                            <p class="description">Creá uno en <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">My Profile → API Tokens</a> con permiso <code>Zone → Cache Purge</code> para tu zona.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar Cloudflare'); ?>
            </form>

            <hr id="wai-purge-all">
            <h2>Purga Total</h2>
            <p>Limpia LiteSpeed, Cloudflare, transients de WP, WP Rocket, W3 Total Cache y cualquier otro sistema de caché detectado.</p>
            <button type="button" id="wai-purge-all-btn" class="button button-primary" style="margin-bottom:10px;">🧹 Purgar toda la caché</button>
            <div id="wai-purge-result" style="display:none;margin-top:10px;max-width:700px;background:#f0f6fc;padding:12px;border-radius:4px;border-left:4px solid #2271b1;"></div>

            <hr>
            <h2>Acciones</h2>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wai_import">
                    <?php wp_nonce_field('wai_manual_import'); ?>
                    <?php submit_button('▶ Importar lote ahora', 'secondary', 'submit', false); ?>
                </form>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wai_reset_page">
                    <?php wp_nonce_field('wai_reset_page'); ?>
                    <?php submit_button('↺ Reiniciar offset a 0', 'delete', 'submit', false,
                        ['onclick' => 'return confirm("¿Reiniciar el offset del importador automático?")']); ?>
                </form>
                <button type="button" id="wai-migrate-covers" class="button button-secondary">
                    🔗 Migrar imágenes a AniList CDN
                </button>
                <div id="wai-migrate-status" style="display:none;margin-top:15px;max-width:600px;">
                    <p id="wai-migrate-message"></p>
                    <div style="background:#eee;border-radius:4px;overflow:hidden;height:22px;">
                        <div id="wai-migrate-progress" style="width:0%;height:100%;transition:width .3s;"></div>
                    </div>
                    <p id="wai-migrate-count"></p>
                </div>

                <button type="button" id="wai-retry-covers" class="button button-secondary" style="margin-top:8px;">
                    🔄 Recuperar imágenes faltantes
                </button>
                <div id="wai-retry-status" style="display:none;margin-top:10px;max-width:600px;">
                    <p id="wai-retry-message" style="font-style:italic;color:#555;"></p>
                </div>

                <p class="description" style="margin-top:8px">Para importar un anime específico, ve a <a href="<?php echo admin_url('admin.php?page=wai-import-manual'); ?>">Importar manual</a>.</p>
            </div>
        </div>
        <?php
    }

    // ── Importar manual ─────────────────────────────────────────────────────

    public static function page_import_manual(): void {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap wai-wrap">
            <h1>🔍 Importar anime manualmente</h1>
            <p>Busca un anime por nombre e impórtalo directamente.</p>
            <div style="display:flex;gap:10px;margin-bottom:20px">
                <input type="text" id="wai-search-input" placeholder="Ej: Frieren, Naruto..."
                    style="flex:1;padding:8px 12px;border:1px solid #ccc;border-radius:4px;">
                <button id="wai-search-button" class="button button-primary">Buscar</button>
            </div>
            <div id="wai-search-results"></div>
        </div>
        <?php
    }

    // ── Pendientes ──────────────────────────────────────────────────────────

    public static function page_pending(): void {
        if (!current_user_can('manage_options')) return;
        $pending_ids = get_option('wai_pending_reprocess', []);
        $total       = count($pending_ids);
        ?>
        <div class="wrap wai-wrap">
            <h1>Anime Index — Animes pendientes de completar</h1>
            <p>Estos animes fueron importados desde una API de fallback (Jikan o Kitsu) y tienen campos incompletos. Cuando AniList esté disponible, usa los botones de abajo para completarlos o revertirlos.</p>

            <div class="wai-pending-actions">
                <button id="wai-btn-complete-all" class="button button-primary" <?php echo $total ? '' : 'disabled'; ?>>
                    ✅ Completar todos con AniList (<?php echo $total; ?>)
                </button>
                <button id="wai-btn-revert-all" class="button" style="margin-left:10px;color:#a00;" <?php echo $total ? '' : 'disabled'; ?>>
                    🗑 Revertir todos los incompletos (<?php echo $total; ?>)
                </button>
            </div>

            <div id="wai-bulk-progress" style="display:none;margin-top:16px;">
                <div class="wai-progress-bar-wrap">
                    <div class="wai-progress-bar" id="wai-progress-bar" style="width:0%"></div>
                </div>
                <p id="wai-bulk-status" style="margin-top:6px;font-size:13px;"></p>
            </div>

            <?php if (empty($pending_ids)): ?>
                <p style="margin-top:20px;color:#888;">No hay animes pendientes. ✓</p>
            <?php else: ?>
                <table class="widefat fixed striped" style="margin-top:20px;">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Anime</th>
                            <th>Fuente</th>
                            <th>Campos pendientes</th>
                            <th style="width:80px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending_ids as $i => $post_id):
                        $post    = get_post((int) $post_id);
                        if (!$post) continue;
                        $source  = get_post_meta($post_id, 'wai_import_source', true) ?: '—';
                        $pending = get_post_meta($post_id, 'wai_pending_fields', true) ?: [];
                        ?>
                        <tr id="wai-pending-row-<?php echo $post_id; ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><a href="<?php echo get_edit_post_link($post_id); ?>"><?php echo esc_html($post->post_title); ?></a></td>
                            <td><code><?php echo esc_html($source); ?></code></td>
                            <td style="font-size:12px;color:#888;"><?php echo esc_html(implode(', ', $pending)); ?></td>
                            <td>
                                <button class="button button-small wai-revert-single"
                                    data-id="<?php echo $post_id; ?>"
                                    data-nonce="<?php echo wp_create_nonce('wai_revert_anime_' . $post_id); ?>">
                                    Revertir
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Registros (módulo unificado) ────────────────────────────────────────

    public static function page_logs(): void {
        if (!current_user_can('manage_options')) return;

        // Limpiar registro
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['wai_clear_log'])) {
            check_admin_referer('wai_clear_logs');
            $target = sanitize_key($_POST['wai_clear_target'] ?? 'all');
            if ($target === 'activity' || $target === 'all') {
                update_option(WAI_LOG_OPT, []);
            }
            if ($target === 'error' || $target === 'all') {
                update_option('wai_error_log', []);
            }
            wp_redirect(add_query_arg(['page' => 'wai-logs', 'cleared' => $target], admin_url('admin.php')));
            exit;
        }

        // Recolectar y unificar entradas
        $activity_entries = array_map(
            [__CLASS__, 'parse_activity_entry'],
            (array) get_option(WAI_LOG_OPT, [])
        );
        $error_entries = array_map(
            [__CLASS__, 'parse_error_entry'],
            (array) get_option('wai_error_log', [])
        );

        $all_entries = array_merge($activity_entries, $error_entries);

        // Ordenar por timestamp descendente (más reciente arriba)
        usort($all_entries, function ($a, $b) {
            return strcmp($b['ts'], $a['ts']);
        });

        $total_count    = count($all_entries);
        $activity_count = count($activity_entries);
        $error_count    = count($error_entries);

        $cleared = sanitize_key($_GET['cleared'] ?? '');
        ?>
        <div class="wrap wai-wrap">
            <h1>📋 Registros</h1>
            <p class="wai-logs-intro">
                Historial completo del plugin. Usá las pestañas para separar entre
                <strong>actividad normal</strong> (importaciones, cron, caché, migraciones)
                y <strong>errores de APIs externas</strong> (AniList, Jikan, Kitsu).
                El buscador y el filtro de nivel operan sobre la pestaña activa.
            </p>

            <?php if ($cleared): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        if ($cleared === 'all')           echo 'Todas las entradas fueron eliminadas.';
                        elseif ($cleared === 'activity')  echo 'El registro de actividad fue eliminado.';
                        elseif ($cleared === 'error')     echo 'El registro de errores fue eliminado.';
                        else                              echo 'Registro limpiado.';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="wai-logs-tabs">
                <button type="button" class="wai-tab is-active" data-tab="all">
                    Todo <span class="wai-tab-count"><?php echo $total_count; ?></span>
                </button>
                <button type="button" class="wai-tab" data-tab="activity">
                    Actividad <span class="wai-tab-count"><?php echo $activity_count; ?></span>
                </button>
                <button type="button" class="wai-tab" data-tab="error">
                    Errores <span class="wai-tab-count"><?php echo $error_count; ?></span>
                </button>
            </div>

            <?php if (!empty($all_entries)): ?>
                <div class="wai-logs-filters">
                    <input type="search" id="wai-log-search" placeholder="Buscar en los mensajes...">
                    <select id="wai-log-level">
                        <option value="">Nivel: Todos</option>
                        <option value="error">❌ Error</option>
                        <option value="warning">⚠️ Advertencia</option>
                        <option value="success">✅ Éxito</option>
                        <option value="info">ℹ️ Info</option>
                    </select>
                    <span class="wai-logs-count">
                        Mostrando <strong id="wai-log-visible"><?php echo $total_count; ?></strong>
                        de <?php echo $total_count; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (empty($all_entries)): ?>
                <p class="wai-logs-empty">No hay entradas registradas todavía.</p>
            <?php else: ?>
                <table class="widefat fixed striped wai-logs-table" id="wai-logs-table">
                    <thead>
                        <tr>
                            <th style="width:150px">Fecha</th>
                            <th style="width:110px">Nivel</th>
                            <th style="width:130px">Fuente</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_entries as $entry): ?>
                            <tr data-origin="<?php echo esc_attr($entry['origin']); ?>"
                                data-level="<?php echo esc_attr($entry['level']); ?>">
                                <td><?php echo esc_html($entry['ts']); ?></td>
                                <td>
                                    <span class="wai-log-level wai-level-<?php echo esc_attr($entry['level']); ?>">
                                        <?php echo esc_html(self::level_label($entry['level'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($entry['source']); ?></td>
                                <td><?php echo esc_html($entry['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="wai-logs-empty" id="wai-logs-empty" style="display:none;">
                    No hay entradas que coincidan con los filtros actuales.
                </p>
            <?php endif; ?>

            <?php if (!empty($all_entries)): ?>
                <div class="wai-logs-actions">
                    <form method="post" style="display:inline-block;">
                        <?php wp_nonce_field('wai_clear_logs'); ?>
                        <input type="hidden" name="wai_clear_log" value="1">
                        <input type="hidden" name="wai_clear_target" id="wai-clear-target" value="all">
                        <button type="submit" class="button" id="wai-clear-btn"
                            onclick="return confirm('¿Limpiar las entradas del registro seleccionado? Esta acción no se puede deshacer.');">
                            🗑 Limpiar TODO el registro
                        </button>
                    </form>
                    <span style="color:#666;font-size:12px;margin-left:12px;">
                        Limpia solo el tab activo. "Todo" limpia ambos registros.
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Etiqueta legible del nivel.
     */
    private static function level_label(string $level): string {
        $map = [
            'error'   => '❌ Error',
            'warning' => '⚠️ Advertencia',
            'success' => '✅ Éxito',
            'info'    => 'ℹ️ Info',
        ];
        return $map[$level] ?? ucfirst($level);
    }

    /**
     * Normaliza una entrada del log de actividad (string plano) al formato
     * unificado de la tabla. Intenta extraer fecha, nivel y fuente del texto.
     */
    private static function parse_activity_entry(string $raw): array {
        $ts      = '—';
        $level   = 'info';
        $source  = 'Sistema';
        $message = $raw;

        // Extraer [YYYY-MM-DD HH:MM:SS] al inicio
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*/', $raw, $m)) {
            $ts      = $m[1];
            $message = trim(substr($raw, strlen($m[0])));
        }

        // Nivel
        if (stripos($message, '[ERROR]') !== false
            || stripos($message, 'error total') !== false
            || stripos($message, 'no se pudo') !== false
            || stripos($message, 'falló') !== false) {
            $level = 'error';
        } elseif (stripos($message, '[OK]') !== false
            || stripos($message, 'importado') !== false
            || stripos($message, 'completada') !== false
            || stripos($message, 'purgado') !== false) {
            $level = 'success';
        }

        // Fuente
        if (preg_match('/\[(AniList|Jikan|Kitsu|Gemini|Importer|Media|Cache|Cron)\]/i', $message, $m)) {
            $source = ucfirst(strtolower($m[1]));
        } elseif (stripos($message, 'migración') !== false) {
            $source = 'Migración';
        } elseif (stripos($message, 'purga') !== false || stripos($message, 'caché') !== false) {
            $source = 'Caché';
        } elseif (stripos($message, 'importado') !== false || stripos($message, 'lote') !== false) {
            $source = 'Importer';
        } elseif (stripos($message, 'ranking') !== false) {
            $source = 'Home';
        } elseif (stripos($message, 'fase') !== false || stripos($message, 'cron') !== false) {
            $source = 'Cron';
        }

        return [
            'ts'      => $ts,
            'level'   => $level,
            'source'  => $source,
            'message' => $message,
            'origin'  => 'activity',
        ];
    }

    /**
     * Normaliza una entrada del log de errores (ya estructurada) al formato
     * unificado de la tabla.
     */
    private static function parse_error_entry(array $entry): array {
        return [
            'ts'      => $entry['ts']      ?? '—',
            'level'   => strtolower($entry['level'] ?? 'error'),
            'source'  => $entry['source']  ?? 'Sistema',
            'message' => $entry['message'] ?? '',
            'origin'  => 'error',
        ];
    }

    // ── Handlers POST ───────────────────────────────────────────────────────

    public static function handle_save_settings(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wai_save_settings')) wp_die('No autorizado.');
        $freq_changed = ($_POST['wai_cron_freq'] ?? '') !== get_option('wai_cron_freq', '10min');
        update_option('wai_gemini_key',         sanitize_text_field($_POST['wai_gemini_key'] ?? ''));
        update_option('wai_gemini_api_version', in_array($_POST['wai_gemini_api_version'] ?? '', ['v1','v1beta']) ? $_POST['wai_gemini_api_version'] : 'v1beta');
        update_option('wai_gemini_model',       sanitize_text_field($_POST['wai_gemini_model'] ?? 'gemini-3.6-flash'));
        update_option('wai_gemini_timeout',     max(30, min(300, (int)($_POST['wai_gemini_timeout'] ?? 120))));
        update_option('wai_gemini_temperature', max(0, min(2, (float)($_POST['wai_gemini_temperature'] ?? 0.7))));
        update_option('wai_gemini_max_output_tokens', max(512, min(8192, (int)($_POST['wai_gemini_max_output_tokens'] ?? 4096))));
        $thinking_level = $_POST['wai_gemini_thinking_level'] ?? 'low';
        update_option('wai_gemini_thinking_level', in_array($thinking_level, ['minimal','low','medium','high'], true) ? $thinking_level : 'low');
        update_option('wai_post_status',        in_array($_POST['wai_post_status'] ?? '', ['draft','publish']) ? $_POST['wai_post_status'] : 'draft');
        update_option('wai_cron_freq',          sanitize_text_field($_POST['wai_cron_freq'] ?? '10min'));
        update_option('wai_import_mode',        sanitize_text_field($_POST['wai_import_mode'] ?? 'popular'));
        update_option('wai_import_batch',       max(1, min(50, (int)($_POST['wai_import_batch'] ?? 10))));
        if ($freq_changed) WAI_Cron::reschedule();
        wp_redirect(add_query_arg(['page' => 'wai-settings', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_save_cloudflare(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wai_save_cloudflare')) {
            wp_die('No autorizado.');
        }

        $config = [
            'zone_id'   => sanitize_text_field($_POST['wai_cf_zone_id']   ?? ''),
            'api_token' => sanitize_text_field($_POST['wai_cf_api_token'] ?? ''),
        ];
        update_option('wai_cloudflare_config', $config, false);

        wp_redirect(add_query_arg(['page' => 'wai-settings', 'cf_saved' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_manual_import(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wai_manual_import')) wp_die('No autorizado.');
        $result = WAI_Importer::run();
        wp_redirect(add_query_arg(array_merge(['page' => 'wai-settings'], $result), admin_url('admin.php')));
        exit;
    }

    public static function handle_reset_page(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wai_reset_page')) wp_die('No autorizado.');
        update_option('wai_import_offset', 0);
        update_option('wai_imported_ids', []);
        update_option('wai_import_page', 1);
        wp_redirect(add_query_arg(['page' => 'wai-settings', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_reimport(): void {
        if (!current_user_can('manage_options') || !isset($_GET['post_id']) || !check_admin_referer('wai_reimport')) wp_die('No autorizado.');
        $result = WAI_Importer::reimport((int)$_GET['post_id']);
        wp_redirect(add_query_arg(['page' => 'wai-settings', 'reimport' => $result ? 'ok' : 'fail'], admin_url('admin.php')));
        exit;
    }

    // ── AJAX ────────────────────────────────────────────────────────────────

    public static function ajax_search_anime(): void {
        check_ajax_referer('wai_search_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('No autorizado');

        $query = sanitize_text_field($_POST['query'] ?? '');
        if (strlen($query) < 2) wp_send_json_error('Escribe al menos 2 caracteres.');

        $results = WAI_AniList::search($query, 12);
        if (empty($results)) wp_send_json_error('No se encontraron animes.');

        ob_start(); ?>
        <div class="wai-search-grid">
            <?php foreach ($results as $anime):
                $title   = $anime['title']['english'] ?: $anime['title']['romaji'];
                $id      = (int) $anime['id'];
                $cover   = $anime['coverImage']['medium'] ?? '';
                $score   = $anime['averageScore'] ? number_format($anime['averageScore'] / 10, 1) : '?';
                $year    = $anime['seasonYear'] ?? '';
                $exists  = WAI_Importer::exists($id);
            ?>
            <div class="wai-search-card">
                <?php if ($cover): ?>
                    <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>">
                <?php endif; ?>
                <div class="wai-search-card-info">
                    <h4><?php echo esc_html($title); ?></h4>
                    <p>⭐ <?php echo $score; ?> &bull; <?php echo esc_html($year); ?></p>
                    <?php if ($exists): ?>
                        <span class="wai-badge-ok">✔ Ya importado</span>
                        <button class="button button-small wai-import-single" data-id="<?php echo $id; ?>"
                            data-title="<?php echo esc_attr($title); ?>" data-force="1">↺ Re-importar</button>
                    <?php else: ?>
                        <button class="button button-primary wai-import-single" data-id="<?php echo $id; ?>"
                            data-title="<?php echo esc_attr($title); ?>" data-force="0">Importar</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        wp_send_json_success(ob_get_clean());
    }

    public static function ajax_import_single(): void {
        check_ajax_referer('wai_search_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('No autorizado');

        register_shutdown_function(function() {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo wp_json_encode([
                    'success' => false,
                    'data'    => 'Fatal PHP: ' . $err['message'] . ' en ' . $err['file'] . ':' . $err['line'],
                ]);
            }
        });

        $id    = (int) ($_POST['anilist_id'] ?? 0);
        $force = (bool) ($_POST['force'] ?? false);
        if (!$id) wp_send_json_error('ID inválido.');

        try {
            if ($force) {
                global $wpdb;
                $post_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wai_anilist_id' AND meta_value=%d LIMIT 1", $id
                ));
                if ($post_id) {
                    $ok = WAI_Importer::reimport($post_id);
                    $ok ? wp_send_json_success('Re-importado correctamente.') : wp_send_json_error('Error al re-importar.');
                    return;
                }
            }

            $ok = WAI_Importer::import_single($id);
            $ok ? wp_send_json_success('Importado correctamente.') : wp_send_json_error('No se pudo importar (puede que ya exista o Gemini falló).');
        } catch (\Throwable $e) {
            WAI_Admin::error_log('ImportSingle', $e->getMessage());
            wp_send_json_error('Error interno: ' . $e->getMessage());
        }
    }

    // ── Apariencia ──────────────────────────────────────────────────────────

    public static function inject_page_title_css(): void {
        $page_id = get_option('wai_home_page_id', 0);
        if (!$page_id || !is_page($page_id)) return;
        echo '<style>.entry-title,.page-title,.wai-page-title-hide{display:none!important}</style>';
    }

    public static function page_appearance(): void {
        if (!current_user_can('manage_options')) return;
        $colors = WAI_Theme::get_colors();
        $labels = [
            'bg' => 'Fondo general',
            'surface' => 'Fondos de tarjetas / contenido',
            'surface2' => 'Fondos secundarios / hover',
            'header' => 'Fondo del header y menú',
            'header_text' => 'Texto del header / menú',
            'header_link' => 'Links del header y footer',
            'header_link_hover' => 'Links del header y footer al pasar el cursor',
            'text' => 'Texto principal',
            'muted' => 'Texto secundario',
            'heading' => 'Títulos',
            'link' => 'Links',
            'link_hover' => 'Links al pasar el cursor',
            'accent' => 'Acento / botones',
            'accent_hover' => 'Acento al pasar el cursor',
            'border' => 'Bordes',
            'input_bg' => 'Fondo de inputs / selects',
            'input_text' => 'Texto de inputs / selects',
            'form_label' => 'Etiquetas de formularios (Nombre, Email, Subject, Mensaje)',
        ];
        ?>
        <div class="wrap wai-wrap">
            <h1>🎨 Apariencia — Esquema global</h1>
            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>Guardado correctamente.</p></div>
            <?php endif; ?>
            <p>El plugin controla el esquema de color del frontend completo y se superpone a la paleta de Astra. El modo claro es el predeterminado. Los cambios afectan escritorio, tablet, móvil, menú y componentes del plugin.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wai_save_appearance">
                <?php wp_nonce_field('wai_save_appearance'); ?>

                <h2>Modo claro</h2>
                <table class="form-table">
                    <?php foreach ($labels as $key => $label): ?>
                        <tr>
                            <th><label for="wai-light-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td><input type="color" id="wai-light-<?php echo esc_attr($key); ?>" name="wai_theme[light][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($colors['light'][$key]); ?>"> <code><?php echo esc_html($colors['light'][$key]); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2>Modo oscuro</h2>
                <table class="form-table">
                    <?php foreach ($labels as $key => $label): ?>
                        <tr>
                            <th><label for="wai-dark-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td><input type="color" id="wai-dark-<?php echo esc_attr($key); ?>" name="wai_theme[dark][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($colors['dark'][$key]); ?>"> <code><?php echo esc_html($colors['dark'][$key]); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2>Home</h2>
                <table class="form-table">
                    <tr>
                        <th>Página del home</th>
                        <td>
                            <?php wp_dropdown_pages(['name' => 'wai_home_page_id', 'selected' => get_option('wai_home_page_id', 0), 'show_option_none' => '— Selecciona —']); ?>
                            <p class="description">El título H1 del tema se ocultará automáticamente en esta página.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar apariencia'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_save_appearance(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wai_save_appearance')) wp_die('No autorizado.');

        update_option('wai_home_page_id', (int)($_POST['wai_home_page_id'] ?? 0));

        $defaults = WAI_Theme::defaults();
        $posted = isset($_POST['wai_theme']) && is_array($_POST['wai_theme']) ? wp_unslash($_POST['wai_theme']) : [];
        $clean = [];
        foreach (['light', 'dark'] as $mode) {
            $clean[$mode] = [];
            foreach ($defaults[$mode] as $key => $default) {
                $value = isset($posted[$mode][$key]) ? sanitize_hex_color($posted[$mode][$key]) : '';
                $clean[$mode][$key] = $value ?: $default;
            }
        }
        update_option(WAI_Theme::OPTION, $clean, false);

        wp_redirect(add_query_arg(['page' => 'wai-appearance', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_migrate_covers(): void {
        if (!current_user_can('manage_options') || !check_admin_referer('wai_migrate_covers')) {
            wp_die('No autorizado.');
        }

        wp_redirect(add_query_arg(['page' => 'wai-settings'], admin_url('admin.php')));
        exit;
    }

    public static function ajax_migrate_covers(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No autorizado.', 403);
        }

        check_ajax_referer('wai_migrate_nonce', 'nonce');

        global $wpdb;
        $batch_size = 10;
        $state      = get_option('wai_cover_migration_state', []);

        if (empty($state) || empty($state['active'])) {
            $total = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'anime'
                  AND pm.meta_key = 'wai_anilist_id'
                  AND p.post_status IN ('publish', 'draft')
            ");

            $state = [
                'active'   => true,
                'offset'   => 0,
                'total'    => $total,
                'done'     => 0,
                'migrated' => 0,
                'skipped'  => 0,
                'errors'   => 0,
            ];

            update_option('wai_cover_migration_state', $state, false);
        }

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID, pm.meta_value AS anilist_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'anime'
                  AND pm.meta_key = 'wai_anilist_id'
                  AND p.post_status IN ('publish', 'draft')
                ORDER BY p.ID ASC
                LIMIT %d OFFSET %d",
                $batch_size,
                (int) $state['offset']
            )
        );

        if (empty($posts)) {
            $state['active'] = false;
            update_option('wai_cover_migration_state', $state, false);

            self::log(sprintf(
                '[%s] Migración CDN completada: %d migrados, %d omitidos, %d errores.',
                current_time('Y-m-d H:i:s'),
                $state['migrated'],
                $state['skipped'],
                $state['errors']
            ));

            wp_send_json_success([
                'finished' => true,
                'total'    => $state['total'],
                'done'     => $state['done'],
                'migrated' => $state['migrated'],
                'skipped'  => $state['skipped'],
                'errors'   => $state['errors'],
            ]);
        }

        foreach ($posts as $row) {
            $post_id    = (int) $row->ID;
            $anilist_id = (int) $row->anilist_id;

            $existing_url = trim((string) get_post_meta($post_id, 'wai_cover_url', true));

            if ($existing_url) {
                $thumb_id = get_post_thumbnail_id($post_id);
                if ($thumb_id) {
                    wp_delete_attachment($thumb_id, true);
                    delete_post_thumbnail($post_id);
                }
                $state['migrated']++;
                $state['done']++;
                continue;
            }

            if ($anilist_id <= 0) {
                $state['skipped']++;
                $state['done']++;
                self::log(sprintf('Migración CDN: post %d sin AniList ID válido.', $post_id));
                continue;
            }

            $anime = WAI_AniList::fetch_by_id($anilist_id);

            if (!$anime) {
                $state['errors']++;
                $state['done']++;
                self::log(sprintf('Migración CDN: no se pudo obtener AniList ID %d (post %d).', $anilist_id, $post_id));
                continue;
            }

            $url = '';
            if (!empty($anime['coverImage']['extraLarge'])) {
                $url = $anime['coverImage']['extraLarge'];
            } elseif (!empty($anime['coverImage']['large'])) {
                $url = $anime['coverImage']['large'];
            }

            if (!$url) {
                $state['skipped']++;
                $state['done']++;
                self::log(sprintf('Migración CDN: AniList ID %d no tiene cover.', $anilist_id));
                continue;
            }

            update_post_meta($post_id, 'wai_cover_url', esc_url_raw($url));

            if (!empty($anime['coverImage']['large']) && $anime['coverImage']['large'] !== $url) {
                update_post_meta($post_id, 'wai_cover_url_large', esc_url_raw($anime['coverImage']['large']));
            }

            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                wp_delete_attachment($thumb_id, true);
                delete_post_thumbnail($post_id);
            }

            $state['migrated']++;
            $state['done']++;
        }

        $state['offset'] += count($posts);
        update_option('wai_cover_migration_state', $state, false);

        wp_send_json_success([
            'finished' => false,
            'total'    => $state['total'],
            'done'     => $state['done'],
            'migrated' => $state['migrated'],
            'skipped'  => $state['skipped'],
            'errors'   => $state['errors'],
        ]);
    }

    public static function ajax_retry_covers(): void {
        check_ajax_referer('wai_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        global $wpdb;

        $posts = $wpdb->get_results("
            SELECT p.ID, pm.meta_value as anilist_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'wai_cover_url'
            WHERE p.post_type = 'anime'
              AND p.post_status IN ('publish','draft')
              AND pm.meta_key = 'wai_anilist_id'
              AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
            LIMIT 5
        ");

        if (empty($posts)) {
            wp_send_json_success(['done' => true, 'message' => 'Todos los animes tienen URL de cover.']);
            return;
        }

        $fixed  = 0;
        $failed = 0;

        foreach ($posts as $row) {
            $anime = WAI_AniList::fetch_by_id((int) $row->anilist_id);
            if ($anime) {
                $url = $anime['coverImage']['extraLarge'] ?: ($anime['coverImage']['large'] ?? '');
                if ($url) {
                    update_post_meta((int) $row->ID, 'wai_cover_url', esc_url_raw($url));
                    if (!empty($anime['coverImage']['large'])) {
                        update_post_meta((int) $row->ID, 'wai_cover_url_large', esc_url_raw($anime['coverImage']['large']));
                    }
                    $fixed++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
            sleep(1);
        }

        $remaining = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'wai_anilist_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'wai_cover_url'
            WHERE p.post_type = 'anime'
              AND p.post_status IN ('publish','draft')
              AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        ");

        wp_send_json_success([
            'done'      => $remaining === 0,
            'fixed'     => $fixed,
            'failed'    => $failed,
            'remaining' => $remaining,
            'message'   => "Lote procesado: $fixed recuperados, $failed fallidos. Quedan: $remaining",
        ]);
    }

    public static function ajax_api_status(): void {
        check_ajax_referer('wai_api_status_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        $anilist_ok = false;
        $al_response = wp_remote_post('https://graphql.anilist.co', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode(['query' => '{ Page(page:1,perPage:1){ media(type:ANIME){ id } } }']),
            'timeout' => 6,
        ]);
        if (!is_wp_error($al_response) && wp_remote_retrieve_response_code($al_response) === 200) {
            $anilist_ok = true;
        }

        $jikan = WAI_Jikan::ping();
        $kitsu = WAI_Kitsu::ping();

        wp_send_json_success([
            'anilist'      => $anilist_ok,
            'jikan'        => $jikan['ok'],
            'jikan_error'  => $jikan['error'] ?? null,
            'kitsu'        => $kitsu['ok'],
            'kitsu_error'  => $kitsu['error'] ?? null,
        ]);
    }

    public static function ajax_revert_anime(): void {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id) wp_send_json_error('ID inválido.');
        check_ajax_referer('wai_revert_anime_' . $post_id, 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        $ok = WAI_Importer::revert($post_id);
        $ok ? wp_send_json_success(['message' => 'Anime revertido correctamente.'])
            : wp_send_json_error('No se pudo eliminar el post.');
    }

    public static function ajax_revert_bulk(): void {
        check_ajax_referer('wai_revert_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        $batch  = max(1, min(20, (int) ($_POST['batch'] ?? 10)));
        $result = WAI_Importer::revert_incomplete_batch($batch);

        wp_send_json_success([
            'reverted'  => $result['reverted'],
            'remaining' => $result['remaining'],
            'done'      => $result['remaining'] === 0,
            'message'   => sprintf('Revertidos: %d. Quedan: %d.', $result['reverted'], $result['remaining']),
        ]);
    }

    public static function ajax_complete_pending(): void {
        check_ajax_referer('wai_complete_pending_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('No autorizado.');

        $batch  = max(1, min(10, (int) ($_POST['batch'] ?? 5)));
        $result = WAI_Importer::complete_pending_batch($batch);

        wp_send_json_success([
            'completed' => $result['completed'],
            'remaining' => $result['remaining'],
            'done'      => $result['remaining'] === 0,
            'message'   => sprintf('Completados: %d. Quedan: %d.', $result['completed'], $result['remaining']),
        ]);
    }

    public static function log( string $message ): void {
        $log   = get_option(WAI_LOG_OPT, []);
        $log[] = $message;
        if (count($log) > 200) $log = array_slice($log, -200);
        update_option(WAI_LOG_OPT, $log, false);
    }

    public static function error_log( string $source, string $message, string $level = 'ERROR' ): void {
        $log   = get_option('wai_error_log', []);
        $log[] = [
            'ts'      => current_time('Y-m-d H:i:s'),
            'level'   => $level,
            'source'  => $source,
            'message' => $message,
        ];
        if (count($log) > 300) $log = array_slice($log, -300);
        update_option('wai_error_log', $log, false);
    }
}