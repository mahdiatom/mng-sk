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

/**
 * آیا سفارش با روش پرداخت کارت به کارت (BACS) ثبت شده است؟
 *
 * @param WC_Order|null $order
 * @return bool
 */
function sc_order_used_card_to_card_payment($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return false;
    }

    if ($order->get_payment_method() === 'bacs') {
        return true;
    }

    $title = (string) $order->get_payment_method_title();
    if ($title === '') {
        return false;
    }

    return (function_exists('mb_stripos')
        ? mb_stripos($title, 'کارت به کارت') !== false
        : stripos($title, 'کارت به کارت') !== false);
}

/**
 * متن دستورالعمل کارت به کارت از تنظیمات درگاه BACS ووکامرس.
 * فقط وقتی درگاه فعال باشد و سفارش با همین روش ثبت شده باشد برمی‌گردد.
 *
 * @param WC_Order|null $order
 * @return string
 */
function sc_get_card_to_card_instructions($order = null) {
    if (!function_exists('WC') || !WC()->payment_gateways()) {
        return '';
    }

    $gateways = WC()->payment_gateways()->payment_gateways();
    if (empty($gateways['bacs']) || $gateways['bacs']->enabled !== 'yes') {
        return '';
    }

    if ($order && !sc_order_used_card_to_card_payment($order)) {
        return '';
    }

    $instructions = $gateways['bacs']->get_option('instructions');
    if (!is_string($instructions)) {
        return '';
    }

    return trim($instructions);
}

