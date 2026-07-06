<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitize raw CSS/JS from settings (preserve code, strip null bytes).
 *
 * @param mixed $raw
 * @return string
 */
function sc_sanitize_custom_code_field($raw) {
    if (!is_string($raw)) {
        return '';
    }
    return str_replace("\0", '', wp_unslash($raw));
}

/**
 * @param string $key
 * @return string
 */
function sc_get_custom_code($key) {
    $allowed = ['custom_css_admin', 'custom_css_public', 'custom_js_admin', 'custom_js_public'];
    if (!in_array($key, $allowed, true)) {
        return '';
    }
    return (string) sc_get_setting($key, '');
}

/**
 * Print raw CSS in a dedicated style tag.
 * wp_add_inline_style() strips <...> sequences (e.g. attr(id type(<custom-ident>), none)).
 *
 * @param string $css
 * @param string $id
 */
function sc_print_custom_code_style_tag($css, $id) {
    if ($css === '') {
        return;
    }
    echo '<style id="' . esc_attr($id) . '">' . "\n";
    echo str_replace('</style>', '<\/style>', $css);
    echo "\n" . '</style>' . "\n";
}

/**
 * Output custom admin CSS after plugin styles load.
 */
function sc_output_custom_admin_css() {
    if (!wp_style_is('sc-admin-css', 'enqueued')) {
        return;
    }
    sc_print_custom_code_style_tag(sc_get_custom_code('custom_css_admin'), 'sc-custom-css-admin');
}
add_action('admin_print_styles', 'sc_output_custom_admin_css', 999);

/**
 * Output custom admin JS after plugin scripts load.
 */
function sc_output_custom_admin_js() {
    $js = sc_get_custom_code('custom_js_admin');
    if ($js !== '' && wp_script_is('sc-admin-js', 'enqueued')) {
        wp_add_inline_script('sc-admin-js', $js, 'after');
    }
}
add_action('admin_enqueue_scripts', 'sc_output_custom_admin_js', 999);

/**
 * Output custom public CSS after plugin styles load.
 */
function sc_output_custom_public_css() {
    if (!wp_style_is('sc-public-css', 'enqueued')) {
        return;
    }
    sc_print_custom_code_style_tag(sc_get_custom_code('custom_css_public'), 'sc-custom-css-public');
}
add_action('wp_print_styles', 'sc_output_custom_public_css', 999);

/**
 * Output custom public JS after plugin scripts load.
 */
function sc_output_custom_public_js() {
    $js = sc_get_custom_code('custom_js_public');
    if ($js !== '' && wp_script_is('sc-public-js', 'enqueued')) {
        wp_add_inline_script('sc-public-js', $js, 'after');
    }
}
add_action('wp_enqueue_scripts', 'sc_output_custom_public_js', 999);
