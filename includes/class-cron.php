<?php
defined('ABSPATH') || exit;

class WAI_Cron {

    const HOOK = 'wai_cron_import';

    public static function init(): void {
        add_filter('cron_schedules', [__CLASS__, 'add_cron_interval']);
        add_action(self::HOOK, ['WAI_Importer', 'run_auto']);
    }

    public static function add_cron_interval( array $schedules ): array {
        $schedules['10min'] = ['interval' => 600,  'display' => 'Cada 10 minutos'];
        $schedules['30min'] = ['interval' => 1800, 'display' => 'Cada 30 minutos'];
        return $schedules;
    }

    public static function activate(): void {
        WAI_CPT::register_cpt();
        WAI_CPT::register_taxonomies();
        flush_rewrite_rules();
        self::schedule();
    }

    public static function deactivate(): void {
        $ts = wp_next_scheduled(self::HOOK);
        if ($ts) wp_unschedule_event($ts, self::HOOK);
        wp_clear_scheduled_hook(self::HOOK);
        flush_rewrite_rules();
    }

    public static function schedule(): void {
        if (wp_next_scheduled(self::HOOK)) return;
        $freq = get_option('wai_cron_freq', '10min');
        wp_schedule_event(time() + 60, $freq, self::HOOK);
    }

    public static function reschedule(): void {
        $ts = wp_next_scheduled(self::HOOK);
        if ($ts) wp_unschedule_event($ts, self::HOOK);
        wp_clear_scheduled_hook(self::HOOK);
        self::schedule();
    }
}
