<?php
// Забороняємо прямий доступ
defined('WP_UNINSTALL_PLUGIN') || exit;

// Видалення опцій плагіна
delete_option('prom_xml_url');
delete_option('prom_xml_update_interval');

// Очистка планувальника
wp_clear_scheduled_hook('prom_xml_import_event');
?>
