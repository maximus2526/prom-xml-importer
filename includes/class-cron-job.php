<?php

defined('ABSPATH') || exit;

class Cron_Job {
    const CRON_HOOK = 'prom_update_stock_cron';

    public static function activate() {
        $interval = get_option('prom_xml_update_interval', 'hourly');
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $interval, self::CRON_HOOK);
            add_action(self::CRON_HOOK, [__CLASS__, 'update_stock']);
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function add_custom_cron_schedule($schedules) {
        $schedules['5_minute'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes')
        );

        if (!isset($schedules['hourly'])) {
            $schedules['hourly'] = array(
                'interval' => 3600,
                'display'  => __('Every Hour')
            );
        }

        if (!isset($schedules['twicedaily'])) {
            $schedules['twicedaily'] = array(
                'interval' => 43200,
                'display'  => __('Twice Daily')
            );
        }

        if (!isset($schedules['daily'])) {
            $schedules['daily'] = array(
                'interval' => 86400,
                'display'  => __('Once Daily')
            );
        }

        return $schedules;
    }

    public static function update_stock() {
        $xml_url = get_option('prom_xml_url', '');
        if ($xml_url) {
            $parser = new XML_Parser($xml_url);
            $parser->update_products_stock_status();
        }
    }
}

add_filter('cron_schedules', ['Cron_Job', 'add_custom_cron_schedule']);
