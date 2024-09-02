<?php

defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin settings page to the admin menu.
 *
 * @return void
 */
function prom_xml_importer_add_admin_menu() {
	add_menu_page(
		'Prom XML Importer Settings',
		'Prom XML Importer',
		'manage_options',
		'prom-xml-importer',
		'prom_xml_importer_settings_page'
	);
}

add_action( 'admin_menu', 'prom_xml_importer_add_admin_menu' );

/**
 * Renders the settings page for the plugin.
 *
 * @return void
 */
function prom_xml_importer_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Prom XML Importer Settings', 'text-domain' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'prom_xml_importer_settings' );
			do_settings_sections( 'prom-xml-importer' );
			submit_button( 'Save Settings' );
			?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			wp_nonce_field( 'prom_xml_importer_action', 'prom_xml_importer_nonce' );
			?>
			<input type="hidden" name="action" value="prom_xml_importer_action">
			<input type="submit" name="run_script" class="button button-primary" value="<?php esc_attr_e( 'Run Auto Stock Status', 'text-domain' ); ?>" style="margin-right: 10px;">
			<input type="submit" name="prom_xml_importer_stop" class="button button-secondary" value="<?php esc_attr_e( 'Stop Cron Jobs', 'text-domain' ); ?>">
		</form>
		<?php
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
			echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'text-domain' ) . '</p></div>';
		}
		?>
	</div>
	<?php
}

/**
 * Initializes the settings for the plugin.
 *
 * @return void
 */
function prom_xml_importer_settings_init() {
	register_setting( 'prom_xml_importer_settings', 'prom_xml_url' );
	register_setting( 'prom_xml_importer_settings', 'prom_xml_update_interval' );
	register_setting( 'prom_xml_importer_settings', 'telegram_user_ids' );
	register_setting( 'prom_xml_importer_settings', 'telegram_token_id' );

	add_settings_section(
		'prom_xml_importer_section',
		__( 'Основні налаштування', 'text-domain' ),
		null,
		'prom-xml-importer'
	);

	add_settings_field(
		'prom_xml_url',
		__( 'URL XML файлу', 'text-domain' ),
		'prom_xml_importer_url_render',
		'prom-xml-importer',
		'prom_xml_importer_section'
	);

	add_settings_field(
		'prom_xml_update_interval',
		__( 'Інтервал оновлення', 'text-domain' ),
		'prom_xml_importer_interval_render',
		'prom-xml-importer',
		'prom_xml_importer_section'
	);

	add_settings_field(
		'telegram_user_ids',
		__( 'Telegram User IDs', 'text-domain' ),
		'prom_xml_importer_telegram_user_ids_render',
		'prom-xml-importer',
		'prom_xml_importer_section'
	);

	add_settings_field(
		'telegram_token_id',
		__( 'Telegram Token ID', 'text-domain' ),
		'prom_xml_importer_telegram_token_id_render',
		'prom-xml-importer',
		'prom_xml_importer_section'
	);
}

add_action( 'admin_init', 'prom_xml_importer_settings_init' );

/**
 * Renders the XML URL input field.
 *
 * @return void
 */
function prom_xml_importer_url_render() {
	$url = get_option( 'prom_xml_url', '' );
	?>
	<input type="text" name="prom_xml_url" value="<?php echo esc_attr( $url ); ?>" style="width: 100%;">
	<?php
}

/**
 * Renders the update interval select field.
 *
 * @return void
 */
function prom_xml_importer_interval_render() {
	$interval = get_option( 'prom_xml_update_interval', 'hourly' );
	?>
	<select name="prom_xml_update_interval">
		<option value="5_minute" <?php selected( $interval, '5_minute' ); ?>><?php esc_html_e( 'Що 5 хв', 'text-domain' ); ?></option>
		<option value="hourly" <?php selected( $interval, 'hourly' ); ?>><?php esc_html_e( 'Щогодини', 'text-domain' ); ?></option>
		<option value="twicedaily" <?php selected( $interval, 'twicedaily' ); ?>><?php esc_html_e( 'Двічі на день', 'text-domain' ); ?></option>
		<option value="daily" <?php selected( $interval, 'daily' ); ?>><?php esc_html_e( 'Щодня', 'text-domain' ); ?></option>
	</select>
	<?php
}

/**
 * Renders the Telegram User IDs input field.
 *
 * @return void
 */
function prom_xml_importer_telegram_user_ids_render() {
	$user_ids = get_option( 'telegram_user_ids', '' );
	?>
	<input type="text" name="telegram_user_ids" value="<?php echo esc_attr( $user_ids ); ?>" style="width: 100%;">
	<p class="description"><?php esc_html_e( 'Введіть Telegram User IDs, розділені комою.', 'text-domain' ); ?></p>
	<?php
}

/**
 * Renders the Telegram Token ID input field.
 *
 * @return void
 */
function prom_xml_importer_telegram_token_id_render() {
	$token_id = get_option( 'telegram_token_id', '' );
	?>
	<input type="text" name="telegram_token_id" value="<?php echo esc_attr( $token_id ); ?>" style="width: 100%;">
	<?php
}

/**
 * Handles the admin post actions for running the script and stopping cron jobs.
 *
 * @return void
 */
function prom_xml_importer_handle_action() {
	if ( ! isset( $_POST['prom_xml_importer_nonce'] ) || ! wp_verify_nonce( $_POST['prom_xml_importer_nonce'], 'prom_xml_importer_action' ) ) {
		wp_die( 'Nonce verification failed' );
	}

	if ( isset( $_POST['run_script'] ) ) {
		$xml_url = get_option( 'prom_xml_url', '' );

		if ( $xml_url ) {
			$xml_parser = new XML_Parser( $xml_url );
			$xml_parser->update_products_stock_status();
		}

		if ( ! wp_next_scheduled( 'prom_update_stock_cron' ) ) {
			Cron_Job::deactivate();
			Cron_Job::activate();
		}

		add_settings_error( 'prom_xml_importer_settings', 'settings_updated', __( 'Script run successfully.', 'text-domain' ), 'updated' );
	}

	if ( isset( $_POST['prom_xml_importer_stop'] ) ) {
		wp_clear_scheduled_hook( 'prom_update_stock_cron' );
		add_settings_error( 'prom_xml_importer_settings', 'settings_updated', __( 'Cron jobs stopped.', 'text-domain' ), 'updated' );
	}

	wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
	exit;
}

add_action( 'admin_post_prom_xml_importer_action', 'prom_xml_importer_handle_action' );
