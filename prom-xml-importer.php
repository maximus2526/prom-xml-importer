<?php
/*
Plugin Name: Prom XML Importer
Description: Імпорт товарів з XML файлів Prom з автоматичним оновленням.
Version: 1.0
Author: Ваше ім'я
*/

// Забороняємо прямий доступ
defined('ABSPATH') || exit;

// Підключення необхідних файлів
require_once plugin_dir_path(__FILE__) . 'includes/class-cron-job.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-xml-parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

// Активація плагіна
register_activation_hook(__FILE__, ['Prom_XML_Cron_Job', 'activate']);

// Деактивація плагіна
register_deactivation_hook(__FILE__, ['Prom_XML_Cron_Job', 'deactivate']);
?>
