<?php
// Забороняємо прямий доступ
defined('ABSPATH') || exit;

// Додавання меню налаштувань у адмінці
add_action('admin_menu', 'prom_xml_importer_add_admin_menu');

function prom_xml_importer_add_admin_menu() {
    add_options_page(
        'Prom XML Importer Settings',
        'Prom XML Importer',
        'manage_options',
        'prom-xml-importer',
        'prom_xml_importer_settings_page'
    );
}

// Відображення сторінки налаштувань
function prom_xml_importer_settings_page() {
    ?>
    <div class="wrap">
        <h1>Prom XML Importer Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('prom_xml_importer_settings');
            do_settings_sections('prom-xml-importer');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Реєстрація налаштувань
add_action('admin_init', 'prom_xml_importer_settings_init');

function prom_xml_importer_settings_init() {
    // Реєстрація опції для URL XML
    register_setting('prom_xml_importer_settings', 'prom_xml_url');

    // Реєстрація опції для інтервалу оновлення
    register_setting('prom_xml_importer_settings', 'prom_xml_update_interval');

    // Додавання секції налаштувань
    add_settings_section(
        'prom_xml_importer_section',
        'Основні налаштування',
        null,
        'prom-xml-importer'
    );

    // Поле для введення URL
    add_settings_field(
        'prom_xml_url',
        'URL XML файлу',
        'prom_xml_importer_url_render',
        'prom-xml-importer',
        'prom_xml_importer_section'
    );

    // Поле для вибору інтервалу
    add_settings_field(
        'prom_xml_update_interval',
        'Інтервал оновлення',
        'prom_xml_importer_interval_render',
        'prom-xml-importer',
        'prom_xml_importer_section'
    );
}

// Вивід поля для URL
function prom_xml_importer_url_render() {
    $url = get_option('prom_xml_url', '');
    ?>
    <input type="text" name="prom_xml_url" value="<?php echo esc_attr($url); ?>" style="width: 100%;">
    <?php
}

// Вивід поля для інтервалу
function prom_xml_importer_interval_render() {
    $interval = get_option('prom_xml_update_interval', 'hourly');
    ?>
    <select name="prom_xml_update_interval">
        <option value="hourly" <?php selected($interval, 'hourly'); ?>>Щогодини</option>
        <option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>>Двічі на день</option>
        <option value="daily" <?php selected($interval, 'daily'); ?>>Щодня</option>
    </select>
    <?php
}
?>
