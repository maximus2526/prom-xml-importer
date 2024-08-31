<?php
// Забороняємо прямий доступ
defined('ABSPATH') || exit;

class Prom_XML_Cron_Job {

    const HOOK_NAME = 'prom_xml_import_event';

    // Активація планувальника
    public static function activate() {
        if (!wp_next_scheduled(self::HOOK_NAME)) {
            wp_schedule_event(time(), 'hourly', self::HOOK_NAME); // Стандартний інтервал - щогодини
        }
    }

    // Деактивація планувальника
    public static function deactivate() {
        wp_clear_scheduled_hook(self::HOOK_NAME);
    }

    // Імпорт товарів
    public static function import_products() {
        $xml_url = get_option('prom_xml_url', '');
        if (!empty($xml_url)) {
            Prom_XML_Parser::parse_and_update($xml_url);
        }
    }
}

// Додавання імпорту на планувальник
add_action(Prom_XML_Cron_Job::HOOK_NAME, ['Prom_XML_Cron_Job', 'import_products']);
?>
