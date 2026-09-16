<?php
defined('ABSPATH') || exit;

wp_enqueue_style('wai-home', WAI_URL . 'assets/css/home.css', [], WAI_VERSION);
wp_enqueue_script('wai-home', WAI_URL . 'assets/js/home.js', [], WAI_VERSION, true);
wp_localize_script('wai-home', 'WAI_HOME', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('wai_home_search'),
]);

get_header();

while (have_posts()) :
    the_post();
    $pid        = get_the_ID();
    $score      = get_post_meta($pid, 'wai_score', true);
    $eps        = get_post_meta($pid, 'wai_episodes', true);
    $duration   = get_post_meta($pid, 'wai_duration', true);
    $year       = get_post_meta($pid, 'wai_season_year', true);
    $format     = get_post_meta($pid, 'wai_format', true);
    $romaji     = get_post_meta($pid, 'wai_title_romaji', true);
    $native     = get_post_meta($pid, 'wai_title_native', true);
    $trailer_id = get_post_meta($pid, 'wai_trailer_id', true);
    $trailer_st = get_post_meta($pid, 'wai_trailer_site', true);
    $status     = get_post_meta($pid, 'wai_status', true);
    $banner     = get_post_meta($pid, 'wai_banner', true);
    $franchise  = get_post_meta($pid, 'wai_franchise', true);
    $genres     = get_the_terms($pid, 'anime_genre');
    $studios    = get_the_terms($pid, 'anime_studio');
    $status_map = [
        'FINISHED'         => 'Finalizado',
        'RELEASING'        => 'En emisión',
        'NOT_YET_RELEASED' => 'Próximamente',
        'CANCELLED'        => 'Cancelado',
        'HIATUS'           => 'En pausa',
    ];
?>
<style>
/* Neutralizar el contenedor del tema en esta página */
.wai-page-override { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; float: none !important; }
</style>

<div class="wai-single-page">

    <?php if ($banner): ?>
    <div class="wai-banner" style="background-image:url('<?php echo esc_url($banner); ?>')"></div>
    <?php endif; ?>

    <div class="wai-single-wrap">
        <div class="wai-single-layout">

            <!-- COLUMNA IZQUIERDA -->
            <aside class="wai-single-aside">
                <?php $cover_html = WAI_Media::cover_img($pid, get_the_title($pid), 'eager', true); ?>
                <?php if ($cover_html): ?>
                <div class="wai-single-cover">
                    <?php echo $cover_html; ?>
                </div>
                <?php endif; ?>

                <table class="wai-meta-table">
                    <?php if ($score): ?>
                    <tr><th>Puntuación</th><td>⭐ <?php echo number_format($score / 10, 1); ?>/10</td></tr>
                    <?php endif; ?>
                    <?php if ($format): ?>
                    <tr><th>Formato</th><td><?php echo esc_html($format); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($eps): ?>
                    <tr><th>Episodios</th><td><?php echo (int)$eps; ?></td></tr>
                    <?php endif; ?>
                    <?php if ($duration): ?>
                    <tr><th>Duración</th><td><?php echo (int)$duration; ?> min./ep.</td></tr>
                    <?php endif; ?>
                    <?php if ($status): ?>
                    <tr><th>Estado</th><td><?php echo esc_html($status_map[$status] ?? $status); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($year): ?>
                    <tr><th>Año</th><td><?php echo (int)$year; ?></td></tr>
                    <?php endif; ?>
                    <?php if ($studios && !is_wp_error($studios)): ?>
                    <tr><th>Estudio</th><td><?php echo esc_html(implode(', ', array_column($studios, 'name'))); ?></td></tr>
                    <?php endif; ?>
                </table>

                <?php if ($genres && !is_wp_error($genres)): ?>
                <div class="wai-genres">
                    <?php foreach ($genres as $g): ?>
                    <a class="wai-tag" href="<?php echo esc_url(get_term_link($g)); ?>"><?php echo esc_html($g->name); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php
                // Otras temporadas de la misma franquicia
                if ($franchise):
                    $others = get_posts([
                        'post_type'      => 'anime',
                        'posts_per_page' => 10,
                        'post__not_in'   => [$pid],
                        'meta_query'     => [['key' => 'wai_franchise', 'value' => $franchise]],
                        'orderby'        => 'meta_value_num',
                        'meta_key'       => 'wai_season_year',
                        'order'          => 'ASC',
                    ]);
                    if ($others):
                ?>
                <div class="wai-franchise">
                    <h4>Más de esta franquicia</h4>
                    <?php foreach ($others as $o): ?>
                    <a class="wai-franchise-item" href="<?php echo get_permalink($o->ID); ?>">
                        <?php echo esc_html($o->post_title); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; endif; ?>
            </aside>

            <!-- COLUMNA DERECHA -->
            <main class="wai-single-main">
                <h1 class="wai-single-title"><?php the_title(); ?></h1>
                <?php if ($romaji && $romaji !== get_the_title()): ?>
                <p class="wai-alt-title"><?php echo esc_html($romaji); ?></p>
                <?php endif; ?>
                <?php if ($native): ?>
                <p class="wai-native-title"><?php echo esc_html($native); ?></p>
                <?php endif; ?>

                <?php $content = get_the_content(); if (!empty(trim($content))): ?>
                <div class="wai-editorial">
                    <h2>Sobre este anime</h2>
                    <?php echo apply_filters('the_content', $content); ?>
                </div>
                <?php endif; ?>

                <?php if ($trailer_id && $trailer_st === 'youtube'): ?>
                <div class="wai-trailer">
                    <h2>Trailer oficial</h2>
                    <div class="wai-trailer__wrap">
                        <iframe src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($trailer_id); ?>"
                            frameborder="0" allowfullscreen loading="lazy"
                            title="Trailer de <?php the_title(); ?>"></iframe>
                    </div>
                </div>
                <?php endif; ?>
            </main>

        </div>
    </div>

    </div>

   <!-- Lightbox para trailer del single -->
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

</div>

<?php endwhile; ?>
<?php get_footer(); ?>
