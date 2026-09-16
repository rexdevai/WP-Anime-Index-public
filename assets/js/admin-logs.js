(function () {
    'use strict';

    var table = document.getElementById('wai-logs-table');
    if (!table) return;

    var tabs         = document.querySelectorAll('.wai-tab');
    var rows         = table.querySelectorAll('tbody tr');
    var searchInput  = document.getElementById('wai-log-search');
    var levelSelect  = document.getElementById('wai-log-level');
    var visibleCount = document.getElementById('wai-log-visible');
    var emptyMsg     = document.getElementById('wai-logs-empty');
    var clearTarget  = document.getElementById('wai-clear-target');
    var clearBtn     = document.getElementById('wai-clear-btn');

    var activeTab = 'all';

    function applyFilters() {
        var q     = (searchInput.value || '').toLowerCase().trim();
        var level = levelSelect.value || '';
        var shown = 0;

        rows.forEach(function (row) {
            var origin   = row.dataset.origin || '';
            var rowLevel = row.dataset.level  || '';
            var text     = row.textContent.toLowerCase();

            var tabOk   = activeTab === 'all' || origin === activeTab;
            var levelOk = !level || rowLevel === level;
            var textOk  = !q || text.indexOf(q) !== -1;

            var visible = tabOk && levelOk && textOk;
            row.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });

        visibleCount.textContent = shown;
        emptyMsg.style.display = shown === 0 ? 'block' : 'none';

        // El botón "Limpiar" actúa sobre el tab activo
        if (clearTarget) {
            clearTarget.value = activeTab;
        }
        if (clearBtn) {
            var labels = {
                all:      '🗑 Limpiar TODO el registro',
                activity: '🗑 Limpiar registro de actividad',
                error:    '🗑 Limpiar registro de errores'
            };
            clearBtn.textContent = labels[activeTab] || '🗑 Limpiar registro';
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            activeTab = tab.dataset.tab || 'all';
            applyFilters();
        });
    });

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (levelSelect) levelSelect.addEventListener('change', applyFilters);

    // Ejecutar al cargar para inicializar contadores y etiquetas
    applyFilters();
})();