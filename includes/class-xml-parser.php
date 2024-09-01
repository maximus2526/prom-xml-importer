<?php

defined('ABSPATH') || exit;

class XML_Parser {
    private $xml_url;

    public function __construct($xml_url) {
        $this->xml_url = $xml_url;
    }

    public function update_products_stock_status() {
        $xml_data = $this->fetch_xml_data();

        if (!$xml_data) {
            error_log('Не вдалося отримати XML дані.');
            return;
        }

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
    }

    private function fetch_xml_data() {
        $response = wp_remote_get($this->xml_url, array('timeout' => 300));
        // error_log(var_export($response, true));
        if (is_wp_error($response)) {
            error_log('Помилка під час завантаження XML: ' . $response->get_error_message());
            return false;
        }
    
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('Помилка: сервер повернув код ' . $status_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
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
