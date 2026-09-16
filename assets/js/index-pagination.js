(function () {
    var pagination = document.getElementById('wai-pagination');
    if (!pagination) return;

    var grid     = document.getElementById('wai-index-grid');
    var prevBtn  = document.getElementById('wai-prev-page');
    var nextBtn  = document.getElementById('wai-next-page');
    var pageInfo = document.getElementById('wai-page-info');

    var paged    = 1;
    var total    = parseInt(pagination.dataset.total,   10);
    var perPage  = parseInt(pagination.dataset.perPage, 10);
    var loading  = false;

    // Lee los filtros activos del formulario en la página
    function getFilters() {
        var form   = document.querySelector('.wai-filters');
        var params = {};
        if (!form) return params;
        var inputs = form.querySelectorAll('input, select');
        inputs.forEach(function (el) {
            if (el.name && el.value) params[el.name] = el.value;
        });
        return params;
    }

    function loadPage(page) {
        if (loading) return;
        loading = true;

        grid.style.opacity = '0.4';
        prevBtn.disabled   = true;
        nextBtn.disabled   = true;

        var fd = new FormData();
        fd.append('action',   'wai_index_page');
        fd.append('nonce',    wai_index.nonce);
        fd.append('paged',    page);
        fd.append('per_page', perPage);

        var filters = getFilters();
        Object.keys(filters).forEach(function (k) { fd.append(k, filters[k]); });

        fetch(wai_index.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success && r.data) {
                    grid.innerHTML  = r.data.html;
                    total           = r.data.total_pages;
                    paged           = r.data.paged;
                    pagination.dataset.total = total;
                    pagination.dataset.paged = paged;
                    pageInfo.textContent = 'Página ' + paged + ' de ' + total;
                }
            })
            .catch(function () {
                // Si falla, no cambia la página actual
            })
            .finally(function () {
                grid.style.opacity = '1';
                prevBtn.disabled   = paged <= 1;
                nextBtn.disabled   = paged >= total;
                loading = false;
                // Scroll suave al tope del grid
                grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
    }

    prevBtn.addEventListener('click', function () {
        if (paged > 1) loadPage(paged - 1);
    });

    nextBtn.addEventListener('click', function () {
        if (paged < total) loadPage(paged + 1);
    });

    // Cuando el usuario filtra, vuelve a página 1 via AJAX en lugar de recargar
    var form = document.querySelector('.wai-filters');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            paged = 1;
            loadPage(1);
        });
    }
})();
