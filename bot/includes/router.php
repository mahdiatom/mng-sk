<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_router($update) {
    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'] ?? null;
        $text    = trim($update['message']['text'] ?? '');

        if (!$chat_id || $text === '') {
            return;
        }

        if (strpos($text, '/start') === 0) {
            require_once SC_BOT_COMMANDS_DIR . 'start.php';
            bale_cmd_start($chat_id);

            $parts = explode(' ', $text);
            $token = isset($parts[1]) ? trim($parts[1]) : null;

            if ($token) {
                global $wpdb;
                $table = $wpdb->prefix . 'sc_members';
                $member = $wpdb->get_row($wpdb->prepare(
                    "SELECT user_id FROM $table WHERE bot_token = %s",
                    $token
                ));

                if ($member) {
                    $wpdb->update(
                        $table,
                        ['bot_id' => $chat_id, 'bot_token' => null],
                        ['user_id' => $member->user_id]
                    );
                    bale_send_message($chat_id, 'حساب شما با موفقیت به سایت متصل شد.');
                } else {
                    bale_send_message($chat_id, '❌ توکن معتبر نیست.');
                }
                return;
            }

            bale_send_message($chat_id, 'سلام! لطفاً از طریق سایت وارد شوید و روی «اتصال به ربات» بزنید.');
            return;
        }

        if ($text === '/help') {
            $member = sc_require_connected_user($chat_id);
            if (!$member) {
                return;
            }
            require_once SC_BOT_COMMANDS_DIR . 'help.php';
            return bale_cmd_help($chat_id);
        }

        if ($text === 'اطلاعات من') {
            $member = sc_require_connected_user($chat_id);
            if (!$member) {
                return;
            }
            return bale_cmd_profile($chat_id);
        }

        if ($text === 'خروج از حساب کاربری') {
            $member = sc_require_connected_user($chat_id);
            if (!$member) {
                return;
            }
            return bale_present_logout_confirm($chat_id);
        }

        $commands = bale_get_text_commands();
        if (isset($commands[$text])) {
            $member = sc_require_connected_user($chat_id);
            if (!$member) {
                return;
            }
            require_once SC_BOT_COMMANDS_DIR . $commands[$text]['file'];
            $func = $commands[$text]['func'];
            if (function_exists($func)) {
                return $func($chat_id);
            }
        }

        $member = sc_require_connected_user($chat_id);
        if (!$member) {
            return;
        }
        require_once SC_BOT_COMMANDS_DIR . 'start.php';
        bale_cmd_start($chat_id);
        return;
    }

    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id  = $callback['message']['chat']['id'] ?? null;
        $data     = $callback['data'] ?? '';
        $callback_id = $callback['id'] ?? '';

        if (!$chat_id || !$data) {
            return;
        }

        if (!sc_require_connected_user($chat_id)) {
            if ($callback_id !== '') {
                bale_answer_callback_query($callback_id, 'ابتدا حساب را متصل کنید.');
            }
            return;
        }

        if (bale_bot_route_data_callback($chat_id, $data, $callback_id)) {
            return;
        }

        require_once SC_BOT_COMMANDS_DIR . 'callback.php';

        if ($data === 'cat_shop') {
            return bale_cb_cat_shop($chat_id);
        }

        if (strpos($data, 'shop_cat_') === 0) {
            $cat_id = str_replace('shop_cat_', '', $data);
            return bale_cb_show_products_in_category($chat_id, $cat_id);
        }
    }
}

function bale_cmd_profile($chat_id) {
    $member = sc_get_member_by_chatid($chat_id);
    if (!$member) {
        bale_send_message($chat_id, 'اطلاعاتی برای شما پیدا نشد.');
        return;
    }

    $labels = [
        'full_name'                       => 'نام و نام خانوادگی',
        'national_id'                     => 'کد ملی',
        'player_phone'                    => 'شماره موبایل',
        'birth_date_shamsi'               => 'تاریخ تولد (شمسی)',
        'insurance_expiry_date_gregorian' => 'انقضای بیمه',
        'is_active'                       => 'وضعیت فعال',
        'profile_completed'               => 'تکمیل پروفایل',
        'member_type'                     => 'نوع عضو',
        'updated_at'                      => 'آخرین بروزرسانی',
    ];

    $message = "اطلاعات حساب شما:\n\n";
    foreach ($member as $key => $value) {
        if ($value === null || $value === '' || !isset($labels[$key])) {
            continue;
        }
        if ($key === 'is_active') {
            $value = ((int) $value === 1) ? 'فعال' : 'غیرفعال';
        }
        if ($key === 'profile_completed') {
            $value = ((int) $value === 1) ? 'کامل' : 'ناقص';
        }
        if ($key === 'member_type') {
            $value = ($value === 'normal') ? 'عادی' : 'تیم';
        }
        if (in_array($key, ['insurance_expiry_date_gregorian', 'updated_at'], true) && function_exists('sc_date_shamsi_date_only')) {
            $value = sc_date_shamsi_date_only($value);
        }
        $message .= $labels[$key] . ' : ' . $value . "\n";
    }

    $buttons = [[bale_make_link_button('✏️ تغییر اطلاعات', '/my-account/sc-submit-documents/')]];
    bale_send_message_with_buttons($chat_id, $message, $buttons);
}
