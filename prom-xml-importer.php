<?php
/**
 * Plugin Name: Prom XML Importer
 * Description: Плагін для імпорту XML даних та оновлення статусу запасів.
 * Version: 1.0
 * Author: Your Name
 */

// Забороняємо прямий доступ
defined('ABSPATH') || exit;

// Підключення файлів
require_once plugin_dir_path(__FILE__) . 'includes/class-cron-job.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-xml-parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Деактивація плагіна
register_deactivation_hook(__FILE__, ['Cron_Job', 'deactivate']);
add_action(Cron_Job::CRON_HOOK, ['Cron_Job', 'update_stock']);