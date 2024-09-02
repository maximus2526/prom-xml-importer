<?php

defined('ABSPATH') || exit;

class XML_Parser {
    private $xml_url;

    public function __construct($xml_url) {
        $this->xml_url = $xml_url;
    }

    public function update_products_stock_status() {
        error_log('XML парсинг починається');
        $xml_data = $this->fetch_xml_data();

        if (!$xml_data) {
            error_log('Не вдалося отримати XML дані');
            return;
        }

        try {
            $products = new SimpleXMLElement($xml_data);
            if (empty($products)) {
                error_log('XML дані порожні або не були створені');
                return;
            }
        } catch (Exception $e) {
            error_log('Помилка парсингу XML: ' . $e->getMessage());
            return;
        }

        $total_offers = 0;
        $updated_in_stock = 0;
        $updated_out_of_stock = 0;
        $not_found = 0;

        foreach ($products->shop->offers->offer as $offer) {
            $sku = (string)$offer['id'];
            $quantity_in_stock = (string)$offer->quantity_in_stock;

            $stock_status = !empty($quantity_in_stock) && (int)$quantity_in_stock > 0 ? 'instock' : 'outofstock';

            $wc_product = $this->find_product_by_sku($sku);

            if ($wc_product) {
                $wc_product->set_stock_status($stock_status);
                $wc_product->save();
                wc_delete_product_transients($wc_product->get_id());

                if ($stock_status === 'instock') {
                    $updated_in_stock++;
                } else {
                    $updated_out_of_stock++;
                }
            } else {
                $not_found++;
            }

            $total_offers++;
        }

        error_log("Всього товарів знайдено: {$total_offers}");
        error_log("Товарів оновлено до статусу 'в наявності': {$updated_in_stock}");
        error_log("Товарів оновлено до статусу 'не в наявності': {$updated_out_of_stock}");
        error_log("Товарів не знайдено: {$not_found}");
        error_log('XML парсинг завершено');
    }

    private function fetch_xml_data() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->xml_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $body = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status_code !== 200) {
            error_log('Помилка: сервер повернув код ' . $status_code);
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
