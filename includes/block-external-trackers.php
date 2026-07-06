<?php
/**
 * بلاک trackerهای خارجی غیرضروری (stats.wp.com, amplitude.com)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string[]
 */
function sc_get_blocked_external_tracker_patterns() {
    return [
        'stats.wp.com/w.js',
        'stats.wp.com',
        'amplitude.com',
    ];
}

/**
 * @param string $url
 * @return bool
 */
function sc_is_blocked_external_tracker_url($url) {
    $url = (string) $url;
    if ($url === '') {
        return false;
    }
    foreach (sc_get_blocked_external_tracker_patterns() as $pattern) {
        if (stripos($url, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * @param string $html
 * @return string
 */
function sc_strip_blocked_external_tracker_markup($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    $html = preg_replace(
        '#<script\b[^>]*\bsrc=["\'][^"\']*(?:stats\.wp\.com|amplitude\.com)[^"\']*["\'][^>]*>\s*</script>#is',
        '',
        $html
    );

    $html = preg_replace(
        '#<link\b[^>]*\bhref=["\'][^"\']*(?:stats\.wp\.com|amplitude\.com)[^"\']*["\'][^>]*>#is',
        '',
        $html
    );

    return $html;
}

/**
 * @return bool
 */
function sc_should_block_external_tracker_output_buffer() {
    if (wp_doing_ajax() || wp_is_json_request() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    if (defined('WP_CLI') && WP_CLI) {
        return false;
    }
    return true;
}

function sc_start_external_tracker_output_buffer() {
    if (!sc_should_block_external_tracker_output_buffer()) {
        return;
    }
    ob_start('sc_strip_blocked_external_tracker_markup');
}

add_filter('script_loader_src', 'sc_block_external_tracker_script_src', 9999, 2);
/**
 * @param string|false $src
 * @return string|false
 */
function sc_block_external_tracker_script_src($src, $handle = '') {
    unset($handle);
    if ($src && sc_is_blocked_external_tracker_url($src)) {
        return false;
    }
    return $src;
}

add_filter('style_loader_src', 'sc_block_external_tracker_style_src', 9999, 2);
/**
 * @param string|false $src
 * @return string|false
 */
function sc_block_external_tracker_style_src($src, $handle = '') {
    unset($handle);
    if ($src && sc_is_blocked_external_tracker_url($src)) {
        return false;
    }
    return $src;
}

add_filter('pre_http_request', 'sc_block_external_tracker_http_request', 10, 3);
/**
 * @param false|array|WP_Error $preempt
 * @param array              $args
 * @param string             $url
 * @return false|array|WP_Error
 */
function sc_block_external_tracker_http_request($preempt, $args, $url) {
    unset($args);
    if (sc_is_blocked_external_tracker_url($url)) {
        return new WP_Error('sc_blocked_external_tracker', 'External tracker request blocked.');
    }
    return $preempt;
}

add_action('admin_init', 'sc_start_external_tracker_output_buffer', 0);
add_action('template_redirect', 'sc_start_external_tracker_output_buffer', 0);
