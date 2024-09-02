<?php

defined('ABSPATH') || exit;

register_uninstall_hook(__FILE__, 'prom_uninstall_cleanup');
function prom_uninstall_cleanup() {
    wp_clear_scheduled_hook('prom_update_stock_event');
}
