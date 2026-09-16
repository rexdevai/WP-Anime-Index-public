(function () {
    // Delegar en document para que funcione con cualquier fila
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.wai-revert-single');
        if (!btn) return;
        e.preventDefault();

        if (!confirm(typeof wai_cpt !== 'undefined' ? wai_cpt.confirm : '¿Eliminar permanentemente este anime?')) return;

        var postId = btn.dataset.id;
        var nonce  = btn.dataset.nonce;
        var row    = document.getElementById('wai-pending-row-' + postId)
                  || btn.closest('tr');

        btn.textContent  = 'Eliminando...';
        btn.disabled     = true;

        var fd = new FormData();
        fd.append('action',  'wai_revert_anime');
        fd.append('post_id', postId);
        fd.append('nonce',   nonce);

        var ajaxUrl = typeof wai_cpt   !== 'undefined' ? wai_cpt.ajax_url
                    : typeof wai_migrate !== 'undefined' ? wai_migrate.ajax_url
                    : '/wp-admin/admin-ajax.php';

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    if (row) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity    = '0';
                        setTimeout(function () { row.remove(); }, 320);
                    }
                } else {
                    btn.textContent = 'Error';
                    btn.disabled    = false;
                    alert(r.data || 'Error al revertir.');
                }
            })
            .catch(function () {
                btn.textContent = 'Error';
                btn.disabled    = false;
            });
    });
})();
