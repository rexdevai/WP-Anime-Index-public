(function () {
    'use strict';

    var root = document.documentElement;
    var saved = 'light';
    try {
        saved = localStorage.getItem('wai-theme-v2') || 'light';
    } catch (e) {}
    if (saved !== 'dark' && saved !== 'light') saved = 'light';

    function setTheme(theme) {
        if (theme !== 'dark' && theme !== 'light') theme = 'light';
        root.setAttribute('data-wai-theme', theme);
        root.setAttribute('data-theme', theme);
        if (document.body) {
            document.body.setAttribute('data-theme', theme);
            document.body.setAttribute('data-wai-theme', theme);
        }
        try { localStorage.setItem('wai-theme-v2', theme); } catch (e) {}
        document.querySelectorAll('.wai-theme-toggle, .wai-global-theme-toggle').forEach(function (button) {
            var icon = button.querySelector('.wai-theme-toggle__icon, .wai-global-theme-toggle__icon');
            if (icon) icon.textContent = theme === 'dark' ? '🌙' : '☀️';
            button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            button.setAttribute('title', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        });
    }

    root.setAttribute('data-wai-theme', saved);

    function ensureGlobalToggle() {
        if (document.querySelector('.wai-theme-toggle')) return;
        if (document.querySelector('.wai-global-theme-toggle')) return;
        if (!document.body) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'wai-global-theme-toggle';
        button.innerHTML = '<span class="wai-global-theme-toggle__icon" aria-hidden="true">🌙</span>';
        document.body.appendChild(button);
    }

    function bind() {
        ensureGlobalToggle();
        setTheme(root.getAttribute('data-wai-theme') || saved);
        document.querySelectorAll('.wai-theme-toggle, .wai-global-theme-toggle').forEach(function (button) {
            if (button.dataset.waiThemeBound === '1') return;
            button.dataset.waiThemeBound = '1';
            button.addEventListener('click', function () {
                var current = root.getAttribute('data-wai-theme') || 'light';
                setTheme(current === 'dark' ? 'light' : 'dark');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();