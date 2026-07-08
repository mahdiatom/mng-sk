<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'sc_bale_bot_register_staff_connect_page', 99);

function sc_bale_bot_register_staff_connect_page() {
    $cap = function_exists('sc_admin_menu_cap') ? sc_admin_menu_cap() : 'manage_options';
    add_submenu_page(
        'sc-bale-bot-messages',
        'اتصال به ربات',
        'اتصال به ربات',
        $cap,
        'sc-bale-bot-connect',
        'sc_bale_bot_staff_connect_page'
    );

    if (current_user_can('coach') && !current_user_can($cap)) {
        add_menu_page(
            'اتصال به ربات',
            'اتصال به ربات',
            'sc_view_coach_salary',
            'sc-bale-bot-connect',
            'sc_bale_bot_staff_connect_page',
            'dashicons-format-chat',
            99
        );
    }
}

function sc_bale_bot_staff_connect_page() {
    if (!is_user_logged_in()) {
        wp_die('لطفاً وارد شوید.');
    }

    $user_id = get_current_user_id();
    $is_staff = sc_bot_user_is_manager($user_id)
        || sc_bot_get_coach_id_for_user($user_id) > 0
        || sc_bot_user_is_secretary_only($user_id)
        || current_user_can('coach');

    if (!$is_staff) {
        wp_die('این صفحه فقط برای پرسنل باشگاه است. بازیکنان از بخش حساب کاربری متصل می‌شوند.');
    }

    $chat_id = get_user_meta($user_id, SC_BALE_CHAT_META, true);
    $logo_path = SC_ASSETS_DIR . 'img/bale_logo.svg';
    $logo_svg  = file_exists($logo_path) ? file_get_contents($logo_path) : '';
    ?>
    <div class="wrap sc-bale-bot-connect">
        <?php if ($logo_svg) : ?>
            <div class="sc-bale-bot-connect__logo" style="max-width:80px;margin-bottom:12px;"><?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php endif; ?>
        <h1>اتصال به ربات بله</h1>
        <p>برای دریافت اعلان‌ها و استفاده از منوی اختصاصی نقش خود در ربات، حساب وردپرس را به ربات متصل کنید.</p>
    <?php

    if (!empty($chat_id)) {
        echo '<div class="notice notice-success"><p>✅ حساب شما به ربات متصل است.</p>';
        echo '<p>شناسه چت: <code>' . esc_html((string) $chat_id) . '</code></p></div>';
        echo '<form method="post">';
        wp_nonce_field('sc_reset_bot_connection');
        echo '<button type="submit" name="sc_reset_bot" class="button">بازنشانی اتصال</button>';
        echo '</form></div>';
        return;
    }

    $token = sc_bot_get_or_create_connect_token($user_id);
    $bale_link = sc_bot_get_bale_start_link($user_id);
    $bot_username = function_exists('bale_get_bot_username') ? bale_get_bot_username() : '';

    if ($bale_link && $bot_username) {
        echo '<p><a class="button button-primary" href="' . esc_url($bale_link) . '" target="_blank" rel="noopener">اتصال به ربات</a></p>';
        if (function_exists('sc_bale_use_miniapp') && sc_bale_use_miniapp()) {
            $miniapp = 'https://ble.ir/' . rawurlencode($bot_username) . '?startapp=connect';
            echo '<p><a class="button" href="' . esc_url($miniapp) . '" target="_blank" rel="noopener">اتصال از طریق مینی‌اپ</a></p>';
        }
    } else {
        echo '<div class="notice notice-error"><p>تنظیمات ربات توسط مدیریت تکمیل نشده است.</p></div>';
    }

    echo '</div>';
}
