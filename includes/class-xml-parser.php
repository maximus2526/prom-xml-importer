<?php

defined( 'ABSPATH' ) || exit;

class XML_Parser {
	private $xml_url;
	private $telegram_token_id;
	private $telegram_user_ids;

	public function __construct( $xml_url ) {
		$this->xml_url           = $xml_url;
		$this->telegram_token_id = get_option( 'telegram_token_id', '' );
		$this->telegram_user_ids = explode( ',', get_option( 'telegram_user_ids', '' ) );
	}

	public function update_products_stock_status() {
		$xml_data = $this->fetch_xml_data();

		if ( ! $xml_data ) {
			$this->send_telegram_message( 'Не вдалося отримати XML дані' );
			return;
		}

		try {
			$products = new SimpleXMLElement( $xml_data );
			if ( empty( $products ) ) {
				$this->send_telegram_message( 'XML дані порожні або не були створені' );
				return;
			}
		} catch ( Exception $e ) {
			$this->send_telegram_message( 'Помилка парсингу XML: ' . $e->getMessage() );
			return;
		}

		$updates = array();
		foreach ( $products->shop->offers->offer as $offer ) {
			$sku               = (string) $offer['id'];
			$quantity_in_stock = (string) $offer->quantity_in_stock;
			$stock_status      = ! empty( $quantity_in_stock ) && (int) $quantity_in_stock > 0 ? 'instock' : 'outofstock';

			// Зберігати сток статус для кожного SKU
			$updates[ $sku ] = $stock_status;
		}

		$total_offers         = count( $updates );
		$updated_in_stock     = 0;
		$updated_out_of_stock = 0;
		$not_found            = 0;

		foreach ( $updates as $sku => $stock_status ) {
			// Спочатку шукаємо продукт за SKU
			$product_id = $this->find_product_id_by_sku( $sku );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					if ( $product->get_type() === 'variable' ) {
						// Варіативний продукт: знайти всі варіації
						$variations = $product->get_children(); // отримати всі варіації
						foreach ( $variations as $variation_id ) {
							$variation = wc_get_product( $variation_id );
							if ( $variation ) {
								$variation->set_stock_status( $stock_status );
								$variation->save();
								wc_delete_product_transients( $variation->get_id() );
							}
						}
					} else {
						// Простий продукт
						$product->set_stock_status( $stock_status );
						$product->save();
						wc_delete_product_transients( $product->get_id() );

						if ( $stock_status === 'instock' ) {
							++$updated_in_stock;
						} else {
							++$updated_out_of_stock;
						}
					}
				} else {
					++$not_found;
				}
			} else {
				++$not_found;
			}
		}

		$message = "Всього товарів знайдено: {$total_offers}\n" .
					"Товарів оновлено до статусу 'в наявності': {$updated_in_stock}\n" .
					"Товарів оновлено до статусу 'не в наявності': {$updated_out_of_stock}\n" .
					"Товарів не знайдено: {$not_found}";

		$this->send_telegram_message( $message );
	}

	private function fetch_xml_data() {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->xml_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
		$body        = curl_exec( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $status_code !== 200 ) {
			return false;
		}

		return $body;
	}

	private function find_product_id_by_sku( $sku ) {
		global $wpdb;

		// Запит для пошуку продуктів за SKU
		$sql = $wpdb->prepare(
			"
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type IN ('product', 'product_variation')
            AND pm.meta_key = '_sku'
            AND pm.meta_value = %s
        ",
			$sku
		);

		$product_ids = $wpdb->get_col( $sql );

		if ( ! empty( $product_ids ) ) {
			return $product_ids[0]; // Повертаємо перший знайдений продукт або варіацію
		}

		return false;
	}

	private function send_telegram_message( $message ) {
		foreach ( $this->telegram_user_ids as $chat_id ) {
			$url  = "https://api.telegram.org/bot{$this->telegram_token_id}/sendMessage";
			$data = array(
				'chat_id'    => trim( $chat_id ),
				'text'       => $message,
				'parse_mode' => 'HTML',
			);

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $data ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $ch );

			if ( curl_errno( $ch ) ) {
				error_log( 'cURL error: ' . curl_error( $ch ) );
			} else {
				$response_data = json_decode( $response, true );
				if ( ! $response_data['ok'] ) {
					error_log( 'Telegram API error: ' . $response_data['description'] );
				}
			}

			curl_close( $ch );
		}
	}
}
