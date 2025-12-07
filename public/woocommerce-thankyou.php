<?php
/**
 * WooCommerce Thank You Page Customization
 * 
 * این فایل برای سفارشی‌سازی صفحه تشکر WooCommerce استفاده می‌شود.
 * 
 * @package SportClub Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Override WooCommerce order received template
 * استفاده از filter برای جایگزینی template صفحه تشکر
 */
add_filter('woocommerce_locate_template', 'sc_override_thankyou_template', 10, 3);
function sc_override_thankyou_template($template, $template_name, $template_path) {
    // فقط برای template صفحه تشکر
    if ($template_name === 'checkout/thankyou.php') {
        $plugin_template = SC_TEMPLATES_PUBLIC_DIR . 'order-received.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

/**
 * Override template path برای WooCommerce
 */
add_filter('woocommerce_template_path', 'sc_woocommerce_template_path');
function sc_woocommerce_template_path() {
    return 'templates/public/';
}

/**
 * حذف تمام hook‌های پیش‌فرض WooCommerce از صفحه تشکر
 */
add_action('template_redirect', 'sc_remove_woocommerce_thankyou_hooks', 1);
function sc_remove_woocommerce_thankyou_hooks() {
    if (is_wc_endpoint_url('order-received')) {
        // حذف تمام action‌های مربوط به صفحه تشکر
        remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
        remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 20);
        remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button', 10);
        remove_action('woocommerce_thankyou_order_received_text', 'woocommerce_thankyou_order_received_text', 10);
    }
}

/**
 * جلوگیری از نمایش template پیش‌فرض WooCommerce
 */
add_filter('woocommerce_thankyou_order_received_text', '__return_empty_string', 999);

