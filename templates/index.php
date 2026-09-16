<?php defined('ABSPATH') || exit; ?>

<div class="wai-index">

    <!-- Barra de filtros -->
    <form class="wai-filters" method="get">
        <input type="text" name="wai_search"
               placeholder="Buscar anime..."
               value="<?php echo esc_attr($_GET['wai_search'] ?? ''); ?>">

        <select name="wai_genre">
            <option value="">— Género —</option>
            <?php
            $genres = get_terms(['taxonomy' => 'anime_genre', 'hide_empty' => true, 'orderby' => 'name']);
            foreach ($genres as $g) {
                printf('<option value="%s"%s>%s</option>',
                    esc_attr($g->name),
                    selected($_GET['wai_genre'] ?? '', $g->name, false),
                    esc_html($g->name)
                );
            }
            ?>
        </select>

        <select name="wai_year">
            <option value="">— Año —</option>
            <?php
            $years = get_terms(['taxonomy' => 'anime_year', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC']);
            foreach ($years as $y) {
                printf('<option value="%s"%s>%s</option>',
                    esc_attr($y->name),
                    selected($_GET['wai_year'] ?? '', $y->name, false),
                    esc_html($y->name)
                );
            }
            ?>
        </select>

        <button type="submit">Filtrar</button>
        <a href="<?php echo esc_url(strtok($_SERVER['REQUEST_URI'], '?')); ?>">Limpiar</a>
    </form>

    <!-- Grid de animes -->
    <div id="wai-index-grid">
        <?php include WAI_DIR . 'templates/index-grid.php'; ?>
    </div>

    <!-- Paginación AJAX -->
    <?php if ($query->max_num_pages > 1): ?>
    <div class="wai-pagination" id="wai-pagination"
         data-total="<?php echo (int) $query->max_num_pages; ?>"
         data-paged="1"
         data-per-page="<?php echo (int) $atts['per_page']; ?>">
        <button class="wai-page-btn" id="wai-prev-page" disabled>← Anterior</button>
        <span id="wai-page-info">Página 1 de <?php echo (int) $query->max_num_pages; ?></span>
        <button class="wai-page-btn" id="wai-next-page">Siguiente →</button>
    </div>
    <?php endif; ?>

</div>
