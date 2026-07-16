<?php
/**
 * Customize WordPress wp-login.php for club managers.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logo used on membership login and wp-login.
 */
function sc_get_login_brand_logo_url() {
    $logo = trim((string) sc_get_setting('sc_login_logo_url', ''));
    if ($logo === '') {
        $logo = trim((string) sc_get_setting('sc_club_logo_url', ''));
    }
    return $logo;
}

/**
 * Style variables shared with membership login settings.
 */
function sc_get_wp_login_style_vars() {
    $bg_color  = (string) sc_get_setting('sc_login_bg_color', '#f3f5f9');
    $bg_image  = (string) sc_get_setting('sc_login_bg_image', '');
    $btn_bg    = (string) sc_get_setting('sc_login_btn_bg', '#e60012');
    $btn_color = (string) sc_get_setting('sc_login_btn_color', '#ffffff');

    return [
        'bg_color'  => $bg_color !== '' ? $bg_color : '#f3f5f9',
        'bg_image'  => $bg_image,
        'btn_bg'    => $btn_bg !== '' ? $btn_bg : '#e60012',
        'btn_color' => $btn_color !== '' ? $btn_color : '#ffffff',
    ];
}

add_action('login_enqueue_scripts', 'sc_wp_login_enqueue_assets');
function sc_wp_login_enqueue_assets() {
    $vars = sc_get_wp_login_style_vars();
    $logo = sc_get_login_brand_logo_url();

    wp_enqueue_style(
        'sc-wp-admin-login',
        SC_ASSETS_URL . 'css/wp-admin-login.css',
        [],
        defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : time()
    );

    $inline = ':root{'
        . '--sc-wp-login-bg:' . esc_attr($vars['bg_color']) . ';'
        . '--sc-wp-login-btn:' . esc_attr($vars['btn_bg']) . ';'
        . '--sc-wp-login-btn-color:' . esc_attr($vars['btn_color']) . ';'
        . '}';

    if ($logo !== '') {
        $inline .= '#login h1 a{background-image:url(' . esc_url($logo) . ')!important;}';
    }

    if ($vars['bg_image'] !== '') {
        $inline .= 'body.login{background-image:url(' . esc_url($vars['bg_image']) . ')!important;}';
    }

    wp_add_inline_style('sc-wp-admin-login', $inline);
}

add_filter('login_headerurl', 'sc_wp_login_header_url');
function sc_wp_login_header_url() {
    return home_url('/');
}

add_filter('login_headertext', 'sc_wp_login_header_text');
function sc_wp_login_header_text() {
    $club_name = trim((string) sc_get_setting('sc_name_club', ''));
    if ($club_name !== '') {
        return $club_name;
    }
    return get_bloginfo('name');
}

add_filter('login_message', 'sc_wp_login_message');
function sc_wp_login_message($message) {
    $club_name = trim((string) sc_get_setting('sc_name_club', ''));
    $subtitle = $club_name !== ''
        ? 'ورود به پنل مدیریت ' . $club_name
        : 'ورود به پنل مدیریت باشگاه';

    $header = '<div class="sc-wp-login-intro">'
        . '<span class="sc-wp-login-badge">ورود مدیریت</span>'
        . '<h2 class="sc-wp-login-title">صفحه ورود مدیران</h2>'
        . '<p class="sc-wp-login-subtitle">' . esc_html($subtitle) . '</p>'
        . '</div>';

    return $header . $message;
}

add_filter('login_body_class', 'sc_wp_login_body_class');
function sc_wp_login_body_class($classes) {
    $classes[] = 'sc-wp-login-page';
    return $classes;
}
