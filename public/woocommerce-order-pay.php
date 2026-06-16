<?php
/**
 * WooCommerce Order Pay page customization.
 *
 * @package SportClub Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_locate_template', 'sc_override_form_pay_template', 10, 3);
function sc_override_form_pay_template($template, $template_name, $template_path) {
    if ($template_name === 'checkout/form-pay.php') {
        $plugin_template = SC_TEMPLATES_PUBLIC_DIR . 'form-pay.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

add_filter('body_class', 'sc_order_pay_body_class');
function sc_order_pay_body_class($classes) {
    if (function_exists('is_checkout') && is_checkout() && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
        $classes[] = 'sc-order-pay-page-active';
    }
    return $classes;
}

add_filter('woocommerce_pay_order_button_text', 'sc_order_pay_button_text');
function sc_order_pay_button_text($text) {
    return 'پرداخت و تکمیل سفارش';
}

add_action('template_redirect', 'sc_order_pay_page_setup', 5);
function sc_order_pay_page_setup() {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-pay')) {
        return;
    }

    add_filter('the_title', 'sc_order_pay_hide_page_title', 10, 2);
}

function sc_order_pay_hide_page_title($title, $post_id = 0) {
    if (!is_admin() && in_the_loop() && is_main_query() && is_checkout()) {
        return '';
    }
    return $title;
}
