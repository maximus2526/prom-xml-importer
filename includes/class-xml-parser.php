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
		error_log( 'XML парсинг починається' );
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

			$stock_status = ! empty( $quantity_in_stock ) && (int) $quantity_in_stock > 0 ? 'instock' : 'outofstock';

			$updates[ $sku ] = $stock_status;
		}

		$total_offers         = count( $updates );
		$updated_in_stock     = 0;
		$updated_out_of_stock = 0;
		$not_found            = 0;

		foreach ( $updates as $sku => $stock_status ) {
			$product = $this->find_product_by_sku( $sku );

			if ( $product ) {
				$product->set_stock_status( $stock_status );
				$product->save();
				wc_delete_product_transients( $product->get_id() );

				if ( $stock_status === 'instock' ) {
					++$updated_in_stock;
				} else {
					++$updated_out_of_stock;
				}
			} else {
				++$not_found;
			}
		}

		error_log( "Всього товарів знайдено: {$total_offers}" );
		error_log( "Товарів оновлено до статусу 'в наявності': {$updated_in_stock}" );
		error_log( "Товарів оновлено до статусу 'не в наявності': {$updated_out_of_stock}" );
		error_log( "Товарів не знайдено: {$not_found}" );
		error_log( 'XML парсинг завершено' );

		$this->send_telegram_message( "Всього товарів знайдено: {$total_offers}\nТоварів оновлено до статусу 'в наявності': {$updated_in_stock}\nТоварів оновлено до статусу 'не в наявності': {$updated_out_of_stock}\nТоварів не знайдено: {$not_found}" );
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
			error_log( 'Помилка: сервер повернув код ' . $status_code );
			return false;
		}

		return $body;
	}

	private function find_product_by_sku( $sku ) {
		$args = array(
			'post_type'  => 'product',
			'meta_query' => array(
				array(
					'key'     => '_sku',
					'value'   => $sku,
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			return wc_get_product( $query->posts[0]->ID );
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
