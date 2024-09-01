<?php

defined('ABSPATH') || exit;

class XML_Parser {
    private $xml_url;

    public function __construct($xml_url) {
        $this->xml_url = $xml_url;
    }

    public function update_products_stock_status() {
        error_log('Функція update_products_stock_status починається.');

        $xml_data = $this->fetch_xml_data();

        if (!$xml_data) {
            error_log('Не вдалося отримати XML дані.');
            return;
        }

        error_log('XML дані отримані. Початок парсингу.');

        try {
            $products = new SimpleXMLElement($xml_data);
        } catch (Exception $e) {
            error_log('Помилка парсингу XML: ' . $e->getMessage());
            return;
        }

        foreach ($products->product as $product) {
            $product_id = (string)$product->id;
            $quantity_in_stock = (string)$product->quantity_in_stock;

            $wc_product = $this->find_product_by_sku($product_id);

            if ($wc_product) {
                $stock_status = !empty($quantity_in_stock) ? 'instock' : 'outofstock';

                $wc_product->set_stock_status($stock_status);
                $wc_product->save();

                error_log("Товар SKU {$wc_product->get_sku()} оновлений до статусу: {$stock_status}");
            } else {
                error_log("Товар з ID {$product_id} не знайдено за SKU.");
            }
        }

        error_log('Функція update_products_stock_status завершена.');
    }

    private function fetch_xml_data() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->xml_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); // Тайм-аут
        $body = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if ($status_code !== 200) {
            error_log('Помилка: сервер повернув код ' . $status_code);
            return false;
        }
    
        error_log('Довжина отриманого тіла: ' . strlen($body));
        if (empty($body)) {
            error_log('Помилка: XML порожній або не був отриманий.');
            return false;
        }
    
        return $body;
    }    

    private function find_product_by_sku($sku) {
        $args = [
            'post_type'   => 'product',
            'meta_query'  => [
                [
                    'key'     => '_sku',
                    'value'   => $sku,
                    'compare' => '='
                ]
            ]
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            return wc_get_product($query->posts[0]->ID);
        }

        return false;
    }
}
