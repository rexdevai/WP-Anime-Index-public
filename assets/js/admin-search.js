jQuery(document).ready(function($) {

    function parseResponse(xhr) {
        var text  = xhr.responseText || '';
        var start = text.indexOf('{');
        if (start < 0) return null;
        try { return JSON.parse(text.slice(start)); } catch(e) { return null; }
    }

    function doSearch(query) {
        if (query.length < 2) { alert('Escribe al menos 2 caracteres.'); return; }
        $('#wai-search-results').html('<p style="color:#666;font-style:italic">Buscando...</p>');
        $.ajax({
            url:      wai_ajax.ajax_url,
            method:   'POST',
            dataType: 'text',
            data: { action: 'wai_search_anime', query: query, nonce: wai_ajax.nonce }
        }).done(function(text, status, xhr) {
            var r = parseResponse(xhr);
            if (r && r.success) $('#wai-search-results').html(r.data);
            else $('#wai-search-results').html('<p style="color:#b32d2e">' + (r ? r.data : 'Error al buscar.') + '</p>');
        }).fail(function() {
            $('#wai-search-results').html('<p style="color:#b32d2e">Error de conexión.</p>');
        });
    }

    $('#wai-search-button').on('click', function() {
        doSearch($('#wai-search-input').val().trim());
    });

    $('#wai-search-input').on('keypress', function(e) {
        if (e.which === 13) { e.preventDefault(); $('#wai-search-button').click(); }
    });

    $(document).on('click', '.wai-import-single', function() {
        var $btn  = $(this);
        var id    = $btn.data('id');
        var title = $btn.data('title');
        var force = $btn.data('force');
        var msg   = force ? '¿Re-importar "' + title + '"? Se borrará la versión actual.' : '¿Importar "' + title + '"?';
        if (!confirm(msg)) return;

        $btn.text('Procesando...').prop('disabled', true);

        $.ajax({
            url:      wai_ajax.ajax_url,
            method:   'POST',
            dataType: 'text',
            data: { action: 'wai_import_single', anilist_id: id, force: force, nonce: wai_ajax.nonce }
        }).done(function(text, status, xhr) {
            var r = parseResponse(xhr);
            if (r && r.success) {
                $btn.closest('.wai-search-card').find('.wai-badge-ok, .wai-import-single').remove();
                $btn.closest('.wai-search-card-info').append('<span class="wai-badge-ok">✔ ' + r.data + '</span>');
            } else {
                alert('Error: ' + (r ? r.data : 'Respuesta inválida del servidor.'));
                $btn.text(force ? '↺ Re-importar' : 'Importar').prop('disabled', false);
            }
        }).fail(function() {
            alert('Error de conexión.');
            $btn.text(force ? '↺ Re-importar' : 'Importar').prop('disabled', false);
        });
    });
});