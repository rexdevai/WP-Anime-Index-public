<?php defined('ABSPATH') || exit;
// Variables disponibles: $post, $post_id
$pid        = $post->ID;
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
<div class="wai-single-card">
    <div class="wai-single-card__cover">
        <?php echo WAI_Media::cover_img($pid, $post->post_title, 'lazy'); ?>
    </div>
    <div class="wai-single-card__data">
        <h2><?php echo esc_html($post->post_title); ?></h2>
        <?php if ($romaji && $romaji !== $post->post_title): ?>
            <p class="wai-alt-title"><?php echo esc_html($romaji); ?></p>
        <?php endif; ?>
        <?php if ($native): ?>
            <p class="wai-native-title"><?php echo esc_html($native); ?></p>
        <?php endif; ?>

        <table class="wai-meta-table">
            <?php if ($score): ?>
            <tr><th>Puntuación</th><td>⭐ <?php echo number_format($score / 10, 1); ?> / 10</td></tr>
            <?php endif; ?>
            <?php if ($format): ?>
            <tr><th>Formato</th><td><?php echo esc_html($format); ?></td></tr>
            <?php endif; ?>
            <?php if ($eps): ?>
            <tr><th>Episodios</th><td><?php echo (int) $eps; ?></td></tr>
            <?php endif; ?>
            <?php if ($duration): ?>
            <tr><th>Duración</th><td><?php echo (int) $duration; ?> min. por ep.</td></tr>
            <?php endif; ?>
            <?php if ($status): ?>
            <tr><th>Estado</th><td><?php echo esc_html($status_map[$status] ?? $status); ?></td></tr>
            <?php endif; ?>
            <?php if ($year): ?>
            <tr><th>Año</th><td><?php echo (int) $year; ?></td></tr>
            <?php endif; ?>
            <?php if ($studios && !is_wp_error($studios)): ?>
            <tr><th>Estudio</th><td><?php echo esc_html(implode(', ', array_column($studios, 'name'))); ?></td></tr>
            <?php endif; ?>
            <?php if ($genres && !is_wp_error($genres)): ?>
            <tr><th>Géneros</th>
                <td><?php foreach ($genres as $g): ?>
                    <a class="wai-tag" href="<?php echo esc_url(get_term_link($g)); ?>"><?php echo esc_html($g->name); ?></a>
                <?php endforeach; ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if ($trailer_id && $trailer_st === 'youtube'): ?>
        <div class="wai-trailer">
            <h3>Trailer</h3>
            <div class="wai-trailer__wrap">
                <iframe src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($trailer_id); ?>"
                    frameborder="0" allowfullscreen loading="lazy" title="Trailer"></iframe>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
