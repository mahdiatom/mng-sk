<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_add_bot_endpoint() {
    add_rewrite_endpoint('bot-connect', EP_ROOT | EP_PAGES);
}
add_action('init', 'sc_add_bot_endpoint');

function sc_add_bot_menu_item($items) {
    $items['bot-connect'] = 'اتصال به ربات';
    return $items;
}
add_filter('woocommerce_account_menu_items', 'sc_add_bot_menu_item');

function sc_bot_endpoint_content() {
    $user_id = get_current_user_id();
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT bot_id, bot_token FROM $table WHERE user_id = %d",
        $user_id
    ));

    $logo_path = SC_ASSETS_DIR . 'img/bale_logo.svg';
    $logo_svg  = file_exists($logo_path) ? file_get_contents($logo_path) : '';
    ?>
    <div class="sc-bale-bot-connect">
        <?php if ($logo_svg) : ?>
            <div class="sc-bale-bot-connect__logo"><?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php endif; ?>
        <h2>اتصال به ربات بله</h2>
        <p class="sc-bale-bot-connect__desc">
            برای اتصال، نرم‌افزار بله را نصب داشته باشید سپس روی دکمه اتصال بزنید.
            در صورت نیاز به اتصال مجدد، ابتدا اتصال را بازنشانی کنید.
        </p>
    <?php

    if ($member && !empty($member->bot_id)) {
        echo '<div class="sc-bale-bot-connect__status sc-bale-bot-connect__status--ok">';
        echo '<p>✅ حساب شما به ربات متصل است.</p>';
        echo '<p class="sc-bale-bot-connect__chat-id">شناسه چت: <code>' . esc_html($member->bot_id) . '</code></p>';
        echo '</div>';

        echo '<form method="post" class="sc-bale-bot-connect__reset">';
        wp_nonce_field('sc_reset_bot_connection');
        echo '<button type="submit" name="sc_reset_bot" class="button">بازنشانی اتصال</button>';
        echo '</form>';
        echo '</div>';
        return;
    }

    if (!$member || empty($member->bot_token)) {
        $token = wp_generate_password(20, false);
        $wpdb->update($table, ['bot_token' => $token], ['user_id' => $user_id]);
    } else {
        $token = $member->bot_token;
    }

    $bot_username = bale_get_bot_username();
    $bale_link = 'https://ble.ir/' . rawurlencode($bot_username) . '?start=' . rawurlencode($token);
    $miniapp_link = 'https://ble.ir/' . rawurlencode($bot_username) . '?startapp=connect';

    if ($bot_username) {
        echo '<div class="sc-bale-bot-connect__actions">';
        echo '<a class="button button-primary sc-bale-bot-connect__btn" href="' . esc_url($bale_link) . '" target="_blank" rel="noopener">اتصال به ربات</a>';
        if (sc_bale_use_miniapp()) {
            echo '<a class="button sc-bale-bot-connect__btn sc-bale-bot-connect__btn--miniapp" href="' . esc_url($miniapp_link) . '" target="_blank" rel="noopener">اتصال از طریق مینی‌اپ</a>';
        }
        echo '</div>';
    } else {
        echo '<p class="sc-bale-bot-connect__error">تنظیمات ربات توسط مدیریت تکمیل نشده است.</p>';
    }

    echo '</div>';
}
add_action('woocommerce_account_bot-connect_endpoint', 'sc_bot_endpoint_content');
