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

            $parts = explode(' ', $text);
            $token = isset($parts[1]) ? trim($parts[1]) : '';

            if ($token !== '') {
                $user_id = sc_bot_find_user_by_token($token);
                if ($user_id > 0) {
                    sc_bot_link_chat_to_user($chat_id, $user_id);
                    $ctx = sc_bot_resolve_user_context($chat_id);
                    $role_label = sc_bot_get_role_label($ctx['active_role'] ?? 'player');
                    bale_send_message($chat_id, "✅ حساب شما با موفقیت متصل شد.\nنقش فعال: {$role_label}");
                    bale_cmd_start($chat_id, $ctx);
                } else {
                    bale_send_message($chat_id, '❌ توکن معتبر نیست یا منقضی شده. از سایت دوباره «اتصال به ربات» را بزنید.');
                }
                return;
            }

            $ctx = sc_bot_resolve_user_context($chat_id);
            bale_cmd_start($chat_id, $ctx);
            return;
        }

        if ($text === '/help') {
            $ctx = sc_bot_require_connected_context($chat_id);
            if (!$ctx) {
                return;
            }
            require_once SC_BOT_COMMANDS_DIR . 'help.php';
            return bale_cmd_help($chat_id);
        }

        $ctx = sc_bot_resolve_user_context($chat_id);
        $commands = bale_get_text_commands($ctx);

        if (isset($commands[$text])) {
            if ($text !== '🔗 اتصال به حساب' && empty($ctx['connected'])) {
                require_once SC_BOT_COMMANDS_DIR . 'staff.php';
                bale_cmd_connect_prompt($chat_id);
                return;
            }
            require_once SC_BOT_COMMANDS_DIR . $commands[$text]['file'];
            $func = $commands[$text]['func'];
            if (function_exists($func)) {
                return $func($chat_id);
            }
        }

        if (empty($ctx['connected'])) {
            require_once SC_BOT_COMMANDS_DIR . 'staff.php';
            bale_cmd_connect_prompt($chat_id);
            return;
        }

        require_once SC_BOT_COMMANDS_DIR . 'start.php';
        bale_cmd_start($chat_id, $ctx);
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

        if (strpos($data, 'brole:') === 0) {
            if (function_exists('bale_bot_route_role_callback') && bale_bot_route_role_callback($chat_id, $data, $callback_id)) {
                return;
            }
        }

        if ($data === 'blogout:yes' || $data === 'blogout:no' || strpos($data, 'binv:') === 0
            || strpos($data, 'bcert:') === 0 || strpos($data, 'bwal:') === 0
            || strpos($data, 'bnotif:') === 0 || strpos($data, 'bord:') === 0
            || strpos($data, 'bnote:') === 0) {
            if (!sc_bot_require_connected_context($chat_id)) {
                if ($callback_id !== '') {
                    bale_answer_callback_query($callback_id, 'ابتدا حساب را متصل کنید.');
                }
                return;
            }
        }

        if (bale_bot_route_data_callback($chat_id, $data, $callback_id)) {
            return;
        }

        if (!sc_bot_require_connected_context($chat_id)) {
            if ($callback_id !== '') {
                bale_answer_callback_query($callback_id, 'ابتدا حساب را متصل کنید.');
            }
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
    require_once SC_BOT_COMMANDS_DIR . 'staff.php';
    bale_cmd_profile_unified($chat_id);
}
