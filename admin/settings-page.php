<?php
defined('ABSPATH') || exit;

add_action('admin_menu', 'prom_xml_importer_add_admin_menu');

function prom_xml_importer_add_admin_menu() {
    add_menu_page(
        'Prom XML Importer Settings',
        'Prom XML Importer',
        'manage_options',
        'prom-xml-importer',
        'prom_xml_importer_settings_page'
    );
}

function prom_xml_importer_settings_page() {
    ?>
    <div class="wrap">
        <h1>Prom XML Importer Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('prom_xml_importer_settings');
            do_settings_sections('prom-xml-importer');
            ?>
            <?php submit_button('Save Settings'); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            // Додаємо nonce для захисту
            wp_nonce_field('prom_xml_importer_action', 'prom_xml_importer_nonce');
            ?>
            <input type="hidden" name="action" value="prom_xml_importer_action">
            <input type="submit" name="run_script" class="button button-primary" value="Run Auto stock status" style="margin-right: 10px;">
            <input type="submit" name="prom_xml_importer_stop" class="button button-secondary" value="Stop Cron Jobs">
        </form>
        <?php
        // Обробка повідомлень
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        ?>
    </div>
    <?php
}

add_action('admin_init', 'prom_xml_importer_settings_init');

function prom_xml_importer_settings_init() {
    register_setting('prom_xml_importer_settings', 'prom_xml_url');
    register_setting('prom_xml_importer_settings', 'prom_xml_update_interval');

    add_settings_section(
        'prom_xml_importer_section',
        'Основні налаштування',
        null,
        'prom-xml-importer'
    );

    add_settings_field(
        'prom_xml_url',
        'URL XML файлу',
        'prom_xml_importer_url_render',
        'prom-xml-importer',
        'prom_xml_importer_section'
    );

    add_settings_field(
        'prom_xml_update_interval',
        'Інтервал оновлення',
        'prom_xml_importer_interval_render',
        'prom-xml-importer',
        'prom_xml_importer_section'
    );
}

function prom_xml_importer_url_render() {
    $url = get_option('prom_xml_url', '');
    ?>
    <input type="text" name="prom_xml_url" value="<?php echo esc_attr($url); ?>" style="width: 100%;">
    <?php
}

function prom_xml_importer_interval_render() {
    $interval = get_option('prom_xml_update_interval', 'hourly');
    ?>
    <select name="prom_xml_update_interval">
        <option value="5_minute" <?php selected($interval, '5_minute'); ?>>Що 5 хв</option>
        <option value="hourly" <?php selected($interval, 'hourly'); ?>>Щогодини</option>
        <option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>>Двічі на день</option>
        <option value="daily" <?php selected($interval, 'daily'); ?>>Щодня</option>
    </select>
    <?php
}

// Обробка запиту для запуску скрипту та зупинки CRON завдань
add_action('admin_post_prom_xml_importer_action', 'prom_xml_importer_handle_action');

function prom_xml_importer_handle_action() {
    if (!isset($_POST['prom_xml_importer_nonce']) || !wp_verify_nonce($_POST['prom_xml_importer_nonce'], 'prom_xml_importer_action')) {
        wp_die('Nonce verification failed');
    }

    if (isset($_POST['run_script'])) {
        $xml_url = get_option('prom_xml_url', '');

        if ($xml_url) {
            $xml_parser = new XML_Parser($xml_url);
            $xml_parser->update_products_stock_status();
        }

        if (!wp_next_scheduled('prom_update_stock_cron')) {
            Cron_Job::deactivate();
            Cron_Job::activate();
        }

        add_settings_error('prom_xml_importer_settings', 'settings_updated', 'Script run successfully.', 'updated');
    }

    if (isset($_POST['prom_xml_importer_stop'])) {
        wp_clear_scheduled_hook('prom_update_stock_cron');
        add_settings_error('prom_xml_importer_settings', 'settings_updated', 'Cron jobs stopped.', 'updated');
    }

    wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
    exit;
}


