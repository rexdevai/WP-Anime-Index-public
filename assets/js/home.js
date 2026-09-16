(function() {
    'use strict';

    // ── Hero Slideshow ────────────────────────────────────────────
    var slides   = document.querySelectorAll('.wai-hero-slide');
    var dots     = document.querySelectorAll('.wai-hero-dot');
    var current  = 0;
    var slideTimer;

    function goToSlide(n) {
        if (slides.length <= 1) return;
        slides[current].classList.remove('is-active');
        dots[current] && dots[current].classList.remove('is-active');
        current = (n + slides.length) % slides.length;
        slides[current].classList.add('is-active');
        dots[current] && dots[current].classList.add('is-active');
    }

    function startAutoplay() {
        if (slides.length <= 1) return;
        slideTimer = setInterval(function() { goToSlide(current + 1); }, 6000);
    }

    function resetAutoplay() {
        clearInterval(slideTimer);
        startAutoplay();
    }

    dots.forEach(function(dot, i) {
        dot.addEventListener('click', function() {
            goToSlide(i);
            resetAutoplay();
        });
    });

    startAutoplay();

    // ── Live search con debounce ──────────────────────────────────
    var input    = document.getElementById('wai-live-search');
    var dropdown = document.getElementById('wai-search-dropdown');
    var timer;

    function closeDropdown() {
        if (dropdown) dropdown.hidden = true;
    }

    function renderResults(items) {
        if (!dropdown) return;
        if (!items || !items.length) {
            dropdown.innerHTML = '<div class="wai-search-noresult">No se encontraron animes.</div>';
            dropdown.hidden = false;
            return;
        }
        dropdown.innerHTML = items.map(function(a) {
            var score = a.score ? '\u2B50 ' + (parseFloat(a.score) / 10).toFixed(1) : '';
            var year  = a.year  ? ' · ' + a.year : '';
            var img   = a.cover ? '<img src="' + a.cover + '" alt="">' : '';
            return '<a class="wai-search-result" href="' + a.url + '">' +
                img +
                '<div>' +
                '<div class="wai-search-result__title">' + a.title + '</div>' +
                '<div class="wai-search-result__meta">' + score + year + '</div>' +
                '</div></a>';
        }).join('');
        dropdown.hidden = false;
    }

    if (input) {
        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) { closeDropdown(); return; }
            timer = setTimeout(function() {
                var fd = new FormData();
                fd.append('action', 'wai_home_search');
                fd.append('nonce',  WAI_HOME.nonce);
                fd.append('q',      q);
                fetch(WAI_HOME.ajax_url, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(r) { if (r.success) renderResults(r.data); })
                    .catch(function() { closeDropdown(); });
            }, 320);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDropdown();
        });

        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && dropdown && !dropdown.contains(e.target)) closeDropdown();
        });
    }

    // ── Lightbox ──────────────────────────────────────────────────
    var lightbox = document.getElementById('wai-lightbox');
    // Mover al body para evitar que overflow:hidden rompa fixed en mobile
    if (lightbox && lightbox.parentNode !== document.body) {
        document.body.appendChild(lightbox);
    }
    var backdrop = document.getElementById('wai-lightbox-backdrop');
    var closeBtn = document.getElementById('wai-lightbox-close');
    var iframe   = document.getElementById('wai-lightbox-iframe');

    function openLightbox(videoId) {
        if (!iframe || !lightbox) return;
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1&rel=0';
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.hidden = true;
        if (iframe) iframe.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.wai-lightbox-trigger');
        if (trigger) { e.preventDefault(); openLightbox(trigger.dataset.video); return; }
    });

    if (backdrop) backdrop.addEventListener('click', closeLightbox);
    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightbox && !lightbox.hidden) closeLightbox();
    });

    // ── Fade-up al scroll ─────────────────────────────────────────
    if ('IntersectionObserver' in window) {
        var items = document.querySelectorAll('.wai-card-h, .wai-top5__item');
        var obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(en) {
                if (en.isIntersecting) {
                    en.target.style.animationPlayState = 'running';
                    obs.unobserve(en.target);
                }
            });
        }, { threshold: 0.08 });
        items.forEach(function(el) {
            el.style.animationPlayState = 'paused';
            obs.observe(el);
        });
    }

})();
