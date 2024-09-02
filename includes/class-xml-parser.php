<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class XML_Parser
 *
 * A class for parsing XML data and updating product stock status in WooCommerce.
 */
class XML_Parser {
	/**
	 * URL for fetching XML data.
	 *
	 * @var string
	 */
	private $xml_url;

	/**
	 * Telegram bot token ID.
	 *
	 * @var string
	 */
	private $telegram_token_id;

	/**
	 * Array of Telegram user IDs to send messages to.
	 *
	 * @var array
	 */
	private $telegram_user_ids;

	/**
	 * XML_Parser constructor.
	 *
	 * @param string $xml_url URL for fetching XML data.
	 */
	public function __construct( $xml_url ) {
		$this->xml_url           = $xml_url;
		$this->telegram_token_id = get_option( 'telegram_token_id', '' );
		$this->telegram_user_ids = array_map( 'trim', explode( ',', get_option( 'telegram_user_ids', '' ) ) );
	}

	/**
	 * Update the stock status of products based on XML data.
	 *
	 * @return void
	 */
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

			$updates[ $sku ] = $stock_status;
		}

		$total_offers         = count( $updates );
		$updated_in_stock     = 0;
		$updated_out_of_stock = 0;
		$not_found            = 0;

		foreach ( $updates as $sku => $stock_status ) {
			$product_id = $this->find_product_id_by_sku( $sku );

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					if ( 'variable' === $product->get_type() ) {
						$this->update_variable_product_variations( $product, $stock_status );
					} else {
						$this->update_simple_product( $product, $stock_status );
						if ( 'instock' === $stock_status ) {
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

		$message = sprintf(
			"Всього товарів знайдено: %d\n" .
			"Товарів оновлено до статусу 'в наявності': %d\n" .
			"Товарів оновлено до статусу 'не в наявності': %d\n" .
			'Товарів не знайдено: %d',
			$total_offers,
			$updated_in_stock,
			$updated_out_of_stock,
			$not_found
		);

		$this->send_telegram_message( $message );
	}

	/**
	 * Fetch XML data from the specified URL.
	 *
	 * @return string|false XML data or false on failure.
	 */
	private function fetch_xml_data() {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->xml_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
		$body        = curl_exec( $ch );
		$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return ( 200 === $status_code ) ? $body : false;
	}

	/**
	 * Find the product ID by SKU.
	 *
	 * @param string $sku SKU of the product.
	 * @return int|false Product ID or false if not found.
	 */
	private function find_product_id_by_sku( $sku ) {
		global $wpdb;

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

		return ! empty( $product_ids ) ? $product_ids[0] : false;
	}

	/**
	 * Update variations of a variable product.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $stock_status Stock status.
	 * @return void
	 */
	private function update_variable_product_variations( $product, $stock_status ) {
		$variations = $product->get_children();
		foreach ( $variations as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$variation->set_stock_status( $stock_status );
				$variation->save();
				wc_delete_product_transients( $variation->get_id() );
			}
		}
	}

	/**
	 * Update a simple product.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $stock_status Stock status.
	 * @return void
	 */
	private function update_simple_product( $product, $stock_status ) {
		$product->set_stock_status( $stock_status );
		$product->save();
		wc_delete_product_transients( $product->get_id() );
	}

	/**
	 * Send a message to Telegram.
	 *
	 * @param string $message Message to send.
	 * @return void
	 */
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
			curl_setopt( $ch, CURLOPT_POST, true );
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
