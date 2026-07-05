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
    if (!is_admin()) {
        return $classes;
    }

    $post_type = 'post';
    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && $screen->base === 'edit' && !empty($screen->post_type)) {
            $post_type = $screen->post_type;
        }
    }

    global $pagenow;
    if ($pagenow === 'edit.php' && isset($_GET['post_type'])) {
        $post_type = sanitize_key(wp_unslash($_GET['post_type']));
    }

    if ($pagenow === 'edit.php' && in_array($post_type, ['post', 'page'], true)) {
        return $classes . ' sc-wp-content-list-page';
    }

    return $classes;
}

add_filter('admin_body_class', 'sc_wp_content_list_admin_body_class');

/**
 * @param string $classes
 * @return string
 */
function sc_shop_products_list_admin_body_class($classes) {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return $classes;
    }
    $screen = get_current_screen();
    if ($screen && $screen->id === 'sc_orders_page_sc-products') {
        return $classes . ' sc-shop-products-admin-page';
    }
    return $classes;
}

add_filter('admin_body_class', 'sc_shop_products_list_admin_body_class');

/**
 * @param string $classes
 * @return string
 */
function sc_permalinks_admin_body_class($classes) {
    if (!is_admin()) {
        return $classes;
    }
    global $pagenow;
    if ($pagenow === 'options-permalink.php') {
        return $classes . ' sc-permalinks-admin-page';
    }
    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && $screen->base === 'options-permalink') {
            return $classes . ' sc-permalinks-admin-page';
        }
    }
    return $classes;
}

add_filter('admin_body_class', 'sc_permalinks_admin_body_class');

/**
 * @param string $classes
 * @return string
 */
function sc_wp_users_list_admin_body_class($classes) {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return $classes;
    }
    $screen = get_current_screen();
    if ($screen && $screen->base === 'users') {
        return $classes . ' sc-wp-users-list-page';
    }
    return $classes;
}

add_filter('admin_body_class', 'sc_wp_users_list_admin_body_class');

/**
 * @param string $classes
 * @return string
 */
function sc_wp_user_edit_admin_body_class($classes) {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return $classes;
    }
    $screen = get_current_screen();
    if ($screen && in_array($screen->base, ['user-edit', 'profile', 'user-new'], true)) {
        return $classes . ' sc-wp-user-edit-page';
    }
    return $classes;
}

add_filter('admin_body_class', 'sc_wp_user_edit_admin_body_class');
