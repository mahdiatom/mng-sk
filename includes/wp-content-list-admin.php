<?php
/**
 * استایل لیست برگه‌ها و نوشته‌های وردپرس (edit.php)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $classes
 * @return string
 */
function sc_wp_content_list_admin_body_class($classes) {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return $classes;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'edit') {
        return $classes;
    }
    if (!in_array($screen->post_type, ['post', 'page'], true)) {
        return $classes;
    }
    return $classes . ' sc-wp-content-list-page';
}

add_filter('admin_body_class', 'sc_wp_content_list_admin_body_class');
