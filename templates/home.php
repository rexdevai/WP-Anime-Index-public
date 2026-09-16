<?php defined('ABSPATH') || exit; ?>

<div class="wai-home" id="wai-home">

    <!-- ── SEARCH + THEME ─────────────────────────────────────── -->
    <div class="wai-home-tools">
        <div class="wai-home-tools__inner">
            <form class="wai-home-search" method="get" action="<?php echo esc_url(home_url('/directorio/')); ?>">
                <span class="wai-home-search__icon" aria-hidden="true">⌕</span>
                <input type="search" id="wai-live-search" name="wai_search"
                    placeholder="Buscar anime..."
                    value="<?php echo esc_attr($_GET['wai_search'] ?? ''); ?>"
                    autocomplete="off" aria-label="Buscar anime">
                <div class="wai-search-dropdown" id="wai-search-dropdown" hidden></div>
            </form>

            <button type="button" class="wai-theme-toggle" id="wai-theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
                <span class="wai-theme-toggle__icon" aria-hidden="true">🌙</span>
            </button>
        </div>
    </div>

    <!-- ── HERO SLIDESHOW ────────────────────────────────────── -->
    <?php if (!empty($heroes)): ?>
    <section class="wai-hero-slider" id="wai-hero-slider">
        <?php foreach ($heroes as $i => $h): ?>
        <div class="wai-hero wai-hero-slide<?php echo $i === 0 ? ' is-active' : ''; ?>"
             style="--hero-bg:url('<?php echo esc_url($h['banner'] ?: $h['cover']); ?>')"
             data-index="<?php echo $i; ?>">
            <div class="wai-hero__overlay"></div>
            <div class="wai-hero__inner">
                <div class="wai-hero__poster">
                    <?php if ($h['cover']): ?>
                    <img src="<?php echo esc_url($h['cover']); ?>"
                         alt="<?php echo esc_attr($h['title']); ?>"
                         loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
                         decoding="async"
                         <?php echo $i === 0 ? 'fetchpriority="high"' : ''; ?>>
                    <?php endif; ?>
                </div>
                <div class="wai-hero__content">
                    <span class="wai-badge wai-badge--trend">#<?php echo $i + 1; ?> Tendencia</span>
                    <h2 class="wai-hero__title"><?php echo esc_html($h['title']); ?></h2>
                    <div class="wai-hero__meta">
                        <?php if ($h['score']): ?><span>⭐ <?php echo number_format($h['score'] / 10, 1); ?>/10</span><?php endif; ?>
                        <?php if ($h['year']): ?><span><?php echo $h['year']; ?></span><?php endif; ?>
                        <?php if ($h['format']): ?><span><?php echo esc_html($h['format']); ?></span><?php endif; ?>
                        <?php if ($h['studio']): ?><span><?php echo esc_html($h['studio']); ?></span><?php endif; ?>
                    </div>
                    <?php if ($h['excerpt']): ?>
                    <p class="wai-hero__excerpt"><?php echo esc_html($h['excerpt']); ?></p>
                    <?php endif; ?>
                    <div class="wai-hero__actions">
                        <a class="wai-btn wai-btn--primary" href="<?php echo esc_url($h['url']); ?>">Ver Ficha</a>
                        <?php if ($h['trailer_id'] && $h['trailer_st'] === 'youtube'): ?>
                        <button class="wai-btn wai-btn--secondary wai-lightbox-trigger"
                            data-video="<?php echo esc_attr($h['trailer_id']); ?>">
                            ▶ Ver Tráiler
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Dots de navegación -->
        <?php if (count($heroes) > 1): ?>
        <div class="wai-hero-dots">
            <?php foreach ($heroes as $i => $h): ?>
            <button class="wai-hero-dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
                data-slide="<?php echo $i; ?>" aria-label="Ir al slide <?php echo $i + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ── FILTROS ────────────────────────────────────────────── -->
    <section class="wai-section wai-filters-section">
        <form class="wai-filters" method="get" action="<?php echo esc_url(home_url('/directorio/')); ?>">
            <input type="text" name="wai_search" placeholder="Escribe un título..."
                value="<?php echo esc_attr($_GET['wai_search'] ?? ''); ?>">
            <select name="wai_genre">
                <option value="">Género: Todos</option>
                <?php foreach (get_terms(['taxonomy'=>'anime_genre','hide_empty'=>true,'orderby'=>'name']) as $g):
                    printf('<option value="%s"%s>%s</option>', esc_attr($g->name), selected($_GET['wai_genre']??'',$g->name,false), esc_html($g->name));
                endforeach; ?>
            </select>
            <select name="wai_year">
                <option value="">Año: Todos</option>
                <?php foreach (get_terms(['taxonomy'=>'anime_year','hide_empty'=>true,'orderby'=>'name','order'=>'DESC']) as $y):
                    printf('<option value="%s"%s>%s</option>', esc_attr($y->name), selected($_GET['wai_year']??'',$y->name,false), esc_html($y->name));
                endforeach; ?>
            </select>
            <select name="wai_status">
                <option value="">Estado: Todos</option>
                <?php foreach (['FINISHED'=>'Finalizado','RELEASING'=>'En emisión','NOT_YET_RELEASED'=>'Próximamente'] as $v=>$l):
                    printf('<option value="%s"%s>%s</option>', esc_attr($v), selected($_GET['wai_status']??'',$v,false), $l);
                endforeach; ?>
            </select>
            <button type="submit" class="wai-btn wai-btn--primary">Filtrar</button>
        </form>
    </section>

    <!-- ── ÚLTIMAS INCORPORACIONES ────────────────────────────── -->
    <?php if (!empty($latest)): ?>
    <section class="wai-section">
        <div class="wai-section__header">
            <h2><span class="wai-icon">🔥</span> Últimas Incorporaciones</h2>
            <a class="wai-see-all" href="<?php echo esc_url(home_url('/directorio/')); ?>">Ver todo →</a>
        </div>
        <div class="wai-row">
            <?php foreach ($latest as $a): ?>
            <a class="wai-card-h" href="<?php echo esc_url($a['url']); ?>">
                <div class="wai-card-h__thumb">
                    <?php if ($a['cover']): ?>
                        <img src="<?php echo esc_url($a['cover']); ?>" alt="<?php echo esc_attr($a['title']); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="wai-card-h__nopic">🎬</div>
                    <?php endif; ?>
                    <?php if ($a['score']): ?>
                        <span class="wai-card-h__score">⭐ <?php echo number_format($a['score']/10,1); ?></span>
                    <?php endif; ?>
                </div>
                <div class="wai-card-h__info">
                    <p class="wai-card-h__title"><?php echo esc_html($a['title']); ?></p>
                    <p class="wai-card-h__meta">
                        <?php echo esc_html($a['format'] ?: 'TV'); ?>
                        <?php echo $a['episodes'] ? ' · ' . $a['episodes'] . ' ep.' : ''; ?>
                        <?php
                        $st = ['FINISHED'=>'Finalizado','RELEASING'=>'En emisión'];
                        echo isset($st[$a['status']]) ? ' · ' . $st[$a['status']] : '';
                        ?>
                    </p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── TOP 5 + TRÁILER ────────────────────────────────────── -->
    <section class="wai-section wai-two-col">

        <?php if (!empty($top5)): ?>
        <div class="wai-top5">
            <div class="wai-section__header">
                <h2><span class="wai-icon">🏆</span> Top 5 Mejor Valorados</h2>
            </div>
            <ul class="wai-top5__list">
                <?php foreach ($top5 as $i => $a): ?>
                <li class="wai-top5__item">
                    <span class="wai-top5__rank"><?php echo $i + 1; ?></span>
                    <?php if ($a['cover']): ?>
                        <img src="<?php echo esc_url($a['cover']); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <div class="wai-top5__data">
                        <a href="<?php echo esc_url($a['url']); ?>"><?php echo esc_html($a['title']); ?></a>
                        <?php if ($a['score']): ?>
                        <span class="wai-top5__score">⭐ <?php echo number_format($a['score']/10,1); ?>/10</span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($trailer && $trailer['trailer_id']): ?>
        <div class="wai-trailer-day">
            <div class="wai-section__header">
                <h2><span class="wai-icon">🎬</span> Tráiler Destacado del Día</h2>
            </div>
            <div class="wai-trailer-day__wrap">
                <div class="wai-trailer-day__thumb"
                    style="background-image:url('https://img.youtube.com/vi/<?php echo esc_attr($trailer['trailer_id']); ?>/hqdefault.jpg')">
                    <button class="wai-play-btn wai-lightbox-trigger"
                        data-video="<?php echo esc_attr($trailer['trailer_id']); ?>"
                        aria-label="Reproducir tráiler">
                        <span>▶</span>
                    </button>
                </div>
                <p class="wai-trailer-day__title">
                    <a href="<?php echo esc_url($trailer['url']); ?>"><?php echo esc_html($trailer['title']); ?></a>
                </p>
            </div>
        </div>
        <?php endif; ?>

    </section>

    <!-- ── LIGHTBOX ───────────────────────────────────────────── -->
    <div class="wai-lightbox" id="wai-lightbox" hidden>
        <div class="wai-lightbox__backdrop" id="wai-lightbox-backdrop"></div>
        <div class="wai-lightbox__box">
            <button class="wai-lightbox__close" id="wai-lightbox-close">✕</button>
            <div class="wai-lightbox__video">
                <iframe id="wai-lightbox-iframe" src="" frameborder="0"
                    allow="autoplay; fullscreen" allowfullscreen loading="lazy"></iframe>
            </div>
        </div>
    </div>

</div><!-- .wai-home -->