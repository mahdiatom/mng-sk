<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_api_request($method, $payload = []) {
    $url = bale_get_api_url() . $method;
    return wp_remote_post($url, [
        'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 20,
    ]);
}

function bale_send_message($chat_id, $text) {
    return bale_api_request('sendMessage', [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ]);
}

function bale_send_message_with_buttons($chat_id, $text, $buttons) {
    return bale_api_request('sendMessage', [
        'chat_id'      => $chat_id,
        'text'         => $text,
        'parse_mode'   => 'HTML',
        'reply_markup' => ['inline_keyboard' => $buttons],
    ]);
}

function bale_answer_callback_query($callback_query_id, $text = '') {
    $payload = ['callback_query_id' => $callback_query_id];
    if ($text !== '') {
        $payload['text'] = $text;
    }
    return bale_api_request('answerCallbackQuery', $payload);
}

function bale_send_message_with_keyboard($chat_id, $text, $keyboard) {
    return bale_api_request('sendMessage', [
        'chat_id'      => $chat_id,
        'text'         => $text,
        'parse_mode'   => 'HTML',
        'reply_markup' => [
            'keyboard'          => $keyboard,
            'resize_keyboard'   => true,
            'one_time_keyboard' => false,
        ],
    ]);
}

function sc_convert_phone_to_98($phone) {
    $phone = preg_replace('/\D+/', '', (string) $phone);

    if (strpos($phone, '09') === 0) {
        return '98' . substr($phone, 1);
    }
    if (strpos($phone, '9') === 0 && strlen($phone) === 10) {
        return '98' . $phone;
    }
    if (strpos($phone, '989') === 0) {
        return $phone;
    }
    return $phone;
}

function sc_bale_send_by_phone($phone, $text) {
    $api_key = sc_get_setting('sc_bale_safir_api_key', '');
    $bot_id  = sc_get_setting('sc_bale_safir_bot_id', '');

    if ($api_key === '' || $bot_id === '') {
        return new WP_Error('safir_not_configured', 'تنظیمات سفیر بله کامل نیست.');
    }

    $url = 'https://safir.bale.ai/api/v3/send_message';
    $body = [
        'request_id'   => uniqid('msg_', true),
        'bot_id'       => (int) $bot_id,
        'phone_number' => $phone,
        'message_data' => [
            'message' => ['text' => $text],
        ],
    ];

    return wp_remote_post($url, [
        'headers' => [
            'api-access-key' => $api_key,
            'Content-Type'   => 'application/json',
        ],
        'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
        'timeout' => 20,
    ]);
}

function sc_get_user_bale_chat_id($user_or_member_id) {
    global $wpdb;

    $lookup_id = (int) $user_or_member_id;
    if ($lookup_id <= 0) {
        return null;
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $user_id = $lookup_id;
    $member_user = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        $lookup_id
    ));
    if ($member_user > 0) {
        $user_id = $member_user;
    } else {
        $coach_user = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $coaches_table WHERE id = %d LIMIT 1",
            $lookup_id
        ));
        if ($coach_user > 0) {
            $user_id = $coach_user;
        }
    }

    $chat_id = get_user_meta($user_id, SC_BALE_CHAT_META, true);
    if ($chat_id) {
        return $chat_id;
    }

    $chat_id = $wpdb->get_var($wpdb->prepare(
        "SELECT bot_id FROM $members_table WHERE id = %d OR user_id = %d LIMIT 1",
        $lookup_id,
        $lookup_id
    ));
    if ($chat_id) {
        return $chat_id;
    }

    $chat_id = $wpdb->get_var($wpdb->prepare(
        "SELECT bot_id FROM $coaches_table WHERE user_id = %d LIMIT 1",
        $user_id
    ));

    return $chat_id ? $chat_id : null;
}

function bale_parse_api_response($response) {
    if (is_wp_error($response)) {
        return ['ok' => false, 'message' => $response->get_error_message()];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code >= 200 && $code < 300 && is_array($body) && empty($body['error_data'])) {
        return ['ok' => true, 'data' => $body];
    }

    $message = 'خطا در ارتباط با API بله';
    if (is_array($body)) {
        if (!empty($body['description'])) {
            $message = $body['description'];
        } elseif (!empty($body['error_data'][0]['description'])) {
            $message = $body['error_data'][0]['description'];
        }
    }

    return ['ok' => false, 'message' => $message, 'data' => $body];
}
