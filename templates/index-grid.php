<?php defined('ABSPATH') || exit;
/**
 * Parcial: solo el grid de animes.
 * Usado tanto por index.php (primera carga) como por ajax_index_page.
 * Requiere $query (WP_Query) en scope.
 */
?>
<?php if ($query->have_posts()): ?>
    <div class="wai-grid">
        <?php while ($query->have_posts()): $query->the_post(); ?>
            <?php
            $pid    = get_the_ID();
            $score  = get_post_meta($pid, 'wai_score', true);
            $eps    = get_post_meta($pid, 'wai_episodes', true);
            $year   = get_post_meta($pid, 'wai_season_year', true);
            $genres = get_the_terms($pid, 'anime_genre');
            ?>
            <a class="wai-card" href="<?php the_permalink(); ?>">
                <div class="wai-card__thumb">
                    <?php echo WAI_Media::cover_img($pid, get_the_title(), 'lazy'); ?>
                    <?php if ($score): ?>
                        <span class="wai-card__score">⭐ <?php echo number_format($score / 10, 1); ?></span>
                    <?php endif; ?>
                </div>
                <div class="wai-card__info">
                    <h3><?php the_title(); ?></h3>
                    <p class="wai-card__meta">
                        <?php echo $year ? esc_html($year) : ''; ?>
                        <?php echo ($year && $eps) ? ' · ' : ''; ?>
                        <?php echo $eps ? esc_html($eps) . ' eps.' : ''; ?>
                    </p>
                    <?php if ($genres && !is_wp_error($genres)): ?>
                        <p class="wai-card__genres">
                            <?php echo esc_html(implode(', ', array_column(array_slice($genres, 0, 3), 'name'))); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p class="wai-empty">No se encontraron animes con esos filtros.</p>
<?php endif; ?>
