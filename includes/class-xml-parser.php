<?php
// Забороняємо прямий доступ
defined('ABSPATH') || exit;

class Prom_XML_Parser {

    // Парсинг XML і оновлення товарів
    public static function parse_and_update($xml_url) {
        $response = wp_remote_get($xml_url);

        if (is_wp_error($response)) {
            error_log('Помилка отримання XML: ' . $response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);

        if ($xml === false) {
            error_log('Помилка парсингу XML.');
            return;
        }

        // Обробка XML і оновлення товарів
        foreach ($xml->product as $product) {
            // Логіка оновлення товару
        }
    }
}
?>
