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
    $chat_id = get_user_meta($user_id, SC_BALE_CHAT_META, true);

    if (sc_bot_user_is_manager($user_id) || sc_bot_get_coach_id_for_user($user_id) > 0 || sc_bot_user_is_secretary_only($user_id)) {
        wp_safe_redirect(admin_url('admin.php?page=sc-bale-bot-connect'));
        exit;
    }

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

    if ($chat_id || ($member && !empty($member->bot_id))) {
        $display_id = $chat_id ?: $member->bot_id;
        echo '<div class="sc-bale-bot-connect__status sc-bale-bot-connect__status--ok">';
        echo '<p>✅ حساب شما به ربات متصل است.</p>';
        echo '<p class="sc-bale-bot-connect__chat-id">شناسه چت: <code>' . esc_html((string) $display_id) . '</code></p>';
        echo '</div>';

        echo '<form method="post" class="sc-bale-bot-connect__reset">';
        wp_nonce_field('sc_reset_bot_connection');
        echo '<button type="submit" name="sc_reset_bot" class="button">بازنشانی اتصال</button>';
        echo '</form>';
        echo '</div>';
        return;
    }

    $token = sc_bot_get_or_create_connect_token($user_id);
    $bale_link = sc_bot_get_bale_start_link($user_id);
    $bot_username = bale_get_bot_username();
    $miniapp_link = $bot_username ? 'https://ble.ir/' . rawurlencode($bot_username) . '?startapp=connect' : '';

    if ($bale_link) {
        echo '<div class="sc-bale-bot-connect__actions">';
        echo '<a class="button button-primary sc-bale-bot-connect__btn" href="' . esc_url($bale_link) . '" target="_blank" rel="noopener">اتصال به ربات</a>';
        if (sc_bale_use_miniapp() && $miniapp_link) {
            echo '<a class="button sc-bale-bot-connect__btn sc-bale-bot-connect__btn--miniapp" href="' . esc_url($miniapp_link) . '" target="_blank" rel="noopener">اتصال از طریق مینی‌اپ</a>';
        }
        echo '</div>';
    } else {
        echo '<p class="sc-bale-bot-connect__error">تنظیمات ربات توسط مدیریت تکمیل نشده است.</p>';
    }

    echo '</div>';
}
add_action('woocommerce_account_bot-connect_endpoint', 'sc_bot_endpoint_content');
