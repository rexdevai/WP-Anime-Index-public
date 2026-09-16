jQuery(function ($) {

    const $button   = $('#wai-migrate-covers');
    const $status   = $('#wai-migrate-status');
    const $message  = $('#wai-migrate-message');
    const $progress = $('#wai-migrate-progress');
    const $count    = $('#wai-migrate-count');

    if (!$button.length) {
        return;
    }

    let running = false;

    function updateProgress(data) {
        const total = parseInt(data.total || 0, 10);
        const done  = parseInt(data.done || 0, 10);
        let percent = 0;
        if (total > 0) {
            percent = Math.min(100, Math.round((done / total) * 100));
        }
        $progress.css('width', percent + '%');
        $count.text(done + ' / ' + total + ' (' + percent + '%)');
    }

    function migrateBatch() {
        $.post(wai_migrate.ajax_url, {
            action: 'wai_migrate_covers',
            nonce:  wai_migrate.nonce
        })
        .done(function (response) {
            if (!response || !response.success) {
                const message = response && response.data ? response.data : 'Error desconocido durante la migración.';
                $message.text(message);
                $button.prop('disabled', false);
                running = false;
                return;
            }
            const data = response.data;
            updateProgress(data);
            if (data.finished) {
                $message.text(
                    'Migración completada: ' +
                    data.migrated + ' migrados, ' +
                    data.skipped + ' omitidos, ' +
                    data.errors + ' errores.'
                );
                $progress.css('width', '100%');
                $button.prop('disabled', false);
                running = false;
                return;
            }
            $message.text('Migrando imágenes...');
            setTimeout(migrateBatch, 300);
        })
        .fail(function () {
            $message.text('Error de conexión durante la migración.');
            $button.prop('disabled', false);
            running = false;
        });
    }

    $button.on('click', function () {
        if (running) return;
        if (!confirm('Esto borrará los thumbnails locales y usará las URLs de AniList. ¿Continuar?')) return;

        running = true;
        $button.prop('disabled', true);
        $status.show();
        $message.text('Iniciando migración...');
        $progress.css('width', '0%');
        $count.text('');
        migrateBatch();
    });

});

// ── Recuperar imágenes faltantes ─────────────────────────────────────────────
(function() {
    var btn    = document.getElementById('wai-retry-covers');
    var status = document.getElementById('wai-retry-status');
    var msg    = document.getElementById('wai-retry-message');
    if (!btn) return;

    function runBatch() {
        msg.textContent = 'Recuperando... por favor espera.';
        var fd = new FormData();
        fd.append('action', 'wai_retry_covers');
        fd.append('nonce',  wai_migrate.nonce);

        fetch(wai_migrate.ajax_url, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (!r.success) {
                    msg.textContent = 'Error: ' + (r.data || 'desconocido');
                    btn.disabled = false;
                    return;
                }
                var d = r.data;
                msg.textContent = d.message;
                if (!d.done) {
                    setTimeout(runBatch, 1500);
                } else {
                    msg.textContent = '✔ ' + d.message;
                    btn.disabled = false;
                }
            })
            .catch(function() {
                msg.textContent = 'Error de conexión. AniList puede estar caído, intenta más tarde.';
                btn.disabled = false;
            });
    }

    btn.addEventListener('click', function() {
        btn.disabled = true;
        status.style.display = 'block';
        runBatch();
    });
})();

// ── Status de APIs externas ───────────────────────────────────────────────────
(function () {
    function setStatus(api, ok) {
        var dot   = document.getElementById('wai-dot-' + api);
        var label = document.getElementById('wai-label-' + api);
        if (!dot || !label) return;
        dot.style.background = ok ? '#46b450' : '#dc3232';
        label.textContent    = ok ? 'OK' : 'Caída';
        label.style.color    = ok ? '#46b450' : '#dc3232';
    }

    function checkApis() {
        var dot_a = document.getElementById('wai-dot-anilist');
        if (!dot_a) return;

        ['anilist','jikan','kitsu'].forEach(function(api) {
            var dot   = document.getElementById('wai-dot-' + api);
            var label = document.getElementById('wai-label-' + api);
            if (dot)   dot.style.background = '#ccc';
            if (label) { label.textContent = 'Verificando...'; label.style.color = '#999'; }
        });

        var fd = new FormData();
        fd.append('action', 'wai_api_status');
        fd.append('nonce',  wai_migrate.api_status_nonce);

        fetch(wai_migrate.ajax_url, { method: 'POST', body: fd })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (text) {
                var jsonStart = text.indexOf('{');
                var clean     = jsonStart >= 0 ? text.slice(jsonStart) : text;
                var r         = JSON.parse(clean);
                if (r.success && r.data) {
                    setStatus('anilist', r.data.anilist);
                    setStatus('jikan',   r.data.jikan);
                    setStatus('kitsu',   r.data.kitsu);
                    if (!r.data.jikan && r.data.jikan_error) {
                        var lbl = document.getElementById('wai-label-jikan');
                        if (lbl) lbl.textContent = 'Caída (' + r.data.jikan_error + ')';
                    }
                    if (!r.data.kitsu && r.data.kitsu_error) {
                        var lblk = document.getElementById('wai-label-kitsu');
                        if (lblk) lblk.textContent = 'Caída (' + r.data.kitsu_error + ')';
                    }
                } else {
                    setStatus('anilist', false);
                    setStatus('jikan',   false);
                    setStatus('kitsu',   false);
                }
            })
            .catch(function (err) {
                console.warn('[WAI] API status check failed:', err);
                var la = document.getElementById('wai-label-anilist');
                var lj = document.getElementById('wai-label-jikan');
                var lk = document.getElementById('wai-label-kitsu');
                if (la) { la.textContent = 'Error al verificar'; la.style.color = '#999'; }
                if (lj) { lj.textContent = 'Error al verificar'; lj.style.color = '#999'; }
                if (lk) { lk.textContent = 'Error al verificar'; lk.style.color = '#999'; }
            });
    }

    if (typeof wai_migrate !== 'undefined' && wai_migrate.api_status_nonce) {
        checkApis();
    }

    var btn = document.getElementById('wai-check-apis');
    if (btn) btn.addEventListener('click', checkApis);
})();

// ── Bulk revert de incompletos ────────────────────────────────────────────────
(function () {
    var btnRevert   = document.getElementById('wai-btn-revert-all');
    var btnComplete = document.getElementById('wai-btn-complete-all');
    var progress    = document.getElementById('wai-bulk-progress');
    var bar         = document.getElementById('wai-progress-bar');
    var statusEl    = document.getElementById('wai-bulk-status');

    if (!btnRevert && !btnComplete) return;

    var totalInitial = 0;

    function getTotal() {
        var m = (btnRevert || btnComplete).textContent.match(/\((\d+)\)/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function showProgress() {
        if (progress) progress.style.display = 'block';
    }

    function updateBar(done, total) {
        if (!bar || !total) return;
        var pct = Math.round((done / total) * 100);
        bar.style.width = pct + '%';
    }

    function setStatus(msg) {
        if (statusEl) statusEl.textContent = msg;
    }

    function disableBtns(state) {
        if (btnRevert)   btnRevert.disabled   = state;
        if (btnComplete) btnComplete.disabled = state;
    }

    function runRevertBatch(done, total) {
        var fd = new FormData();
        fd.append('action', 'wai_revert_bulk');
        fd.append('nonce',  wai_migrate.revert_bulk_nonce);
        fd.append('batch',  10);

        fetch(wai_migrate.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var clean = text.slice(text.indexOf('{'));
                var r     = JSON.parse(clean);
                if (r.success && r.data) {
                    var nowDone = done + r.data.reverted;
                    updateBar(nowDone, total);
                    setStatus(r.data.message);
                    if (!r.data.done) {
                        setTimeout(function () { runRevertBatch(nowDone, total); }, 600);
                    } else {
                        setStatus('✅ Revert completo. Todos los incompletos fueron eliminados.');
                        disableBtns(false);
                        document.querySelectorAll('[id^="wai-pending-row-"]').forEach(function (row) { row.remove(); });
                    }
                }
            })
            .catch(function (err) {
                setStatus('Error: ' + err.message);
                disableBtns(false);
            });
    }

    if (btnRevert) {
        btnRevert.addEventListener('click', function () {
            if (!confirm('¿Revertir y eliminar permanentemente TODOS los animes incompletos? Esta acción no se puede deshacer.')) return;
            totalInitial = getTotal();
            showProgress();
            disableBtns(true);
            setStatus('Iniciando revert...');
            runRevertBatch(0, totalInitial);
        });
    }

    function runCompleteBatch(done, total) {
        var fd = new FormData();
        fd.append('action', 'wai_complete_pending');
        fd.append('nonce',  wai_migrate.complete_pending_nonce);
        fd.append('batch',  5);

        fetch(wai_migrate.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var clean = text.slice(text.indexOf('{'));
                var r     = JSON.parse(clean);
                if (r.success && r.data) {
                    var nowDone = done + r.data.completed;
                    updateBar(nowDone, total);
                    setStatus(r.data.message);
                    if (!r.data.done) {
                        setTimeout(function () { runCompleteBatch(nowDone, total); }, 800);
                    } else {
                        setStatus('✅ Completado. Todos los campos pendientes fueron actualizados.');
                        disableBtns(false);
                        setTimeout(function () { window.location.reload(); }, 1500);
                    }
                }
            })
            .catch(function (err) {
                setStatus('Error: ' + err.message);
                disableBtns(false);
            });
    }

    if (btnComplete) {
        btnComplete.addEventListener('click', function () {
            totalInitial = getTotal();
            showProgress();
            disableBtns(true);
            setStatus('Conectando con AniList...');
            runCompleteBatch(0, totalInitial);
        });
    }
})();

// ── Purga Total de caché (página de ajustes) ─────────────────────────────────
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#wai-purge-all-btn');
        if (!btn) return;

        if (btn.dataset.waiPurging === '1') return;

        if (!confirm('¿Purgar TODA la caché del sitio? (LiteSpeed, Cloudflare, transients, WP Rocket, W3TC, etc.)')) return;

        if (typeof wai_migrate === 'undefined') {
            alert('Error: objeto wai_migrate no definido. Recargá la página.');
            return;
        }

        btn.dataset.waiPurging = '1';
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Purgando...';

        var fd = new FormData();
        fd.append('action', 'wai_purge_all_cache');
        fd.append('nonce', wai_migrate.purge_nonce || '');

        fetch(wai_migrate.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var clean = text.slice(text.indexOf('{'));
                var r;
                try {
                    r = JSON.parse(clean);
                } catch (err) {
                    showPurgeNotice('error', 'Respuesta inválida del servidor.');
                    return;
                }
                if (!r || !r.success || !r.data) {
                    showPurgeNotice('error', r && r.data ? r.data : 'Error desconocido en la purga.');
                    return;
                }
                var html = '<strong>✅ Purga total completada</strong><ul style="margin:8px 0 0 20px;list-style:disc;">';
                Object.keys(r.data).forEach(function (k) {
                    var val = String(r.data[k]);
                    var isOk = /purgado|borrado|flusheado|reseteado|OK/i.test(val);
                    var icon = isOk ? '✅' : (/(no detectado|no disponible|no configurado)/i.test(val) ? '➖' : '⚠️');
                    html += '<li>' + icon + ' <code>' + k + '</code>: ' + val + '</li>';
                });
                html += '</ul>';
                showPurgeNotice('success', html);

                var box = document.getElementById('wai-purge-result');
                if (box) {
                    box.style.display = 'block';
                    box.innerHTML = html;
                    try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
                }
            })
            .catch(function (err) {
                showPurgeNotice('error', 'Error de conexión: ' + err.message);
            })
            .finally(function () {
                btn.dataset.waiPurging = '0';
                btn.disabled = false;
                btn.textContent = originalText;
            });
    });

    function showPurgeNotice(type, content) {
        var wrap = document.querySelector('.wrap.wai-wrap') || document.querySelector('.wrap');
        if (!wrap) { alert(content.replace(/<[^>]+>/g, '')); return; }

        var prev = wrap.querySelector('.wai-purge-notice');
        if (prev) prev.remove();

        var notice = document.createElement('div');
        notice.className = 'notice notice-' + (type === 'error' ? 'error' : 'success') + ' is-dismissible wai-purge-notice';
        notice.style.marginTop = '12px';
        notice.style.marginBottom = '12px';
        notice.innerHTML = '<p style="margin:8px 0 4px;">' + content + '</p>';

        var dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'notice-dismiss';
        dismiss.innerHTML = '<span class="screen-reader-text">Descartar</span>';
        dismiss.addEventListener('click', function () { notice.remove(); });
        notice.appendChild(dismiss);

        var h1 = wrap.querySelector('h1');
        if (h1 && h1.nextSibling) {
            h1.parentNode.insertBefore(notice, h1.nextSibling);
        } else {
            wrap.insertBefore(notice, wrap.firstChild);
        }

        try { notice.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) {}
    }
})();