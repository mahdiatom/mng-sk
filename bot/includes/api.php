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

/**
 * ارسال multipart به Bot API بله (برای آپلود فایل).
 *
 * @param string               $method
 * @param array<string,mixed>  $fields فیلدهای متنی
 * @param string               $file_field نام فیلد فایل (photo|video|document)
 * @param string               $file_path مسیر فایل محلی
 * @return array|WP_Error
 */
function bale_api_request_multipart($method, $fields, $file_field, $file_path) {
    $url = bale_get_api_url() . $method;

    if (!is_readable($file_path)) {
        return new WP_Error('bale_file_unreadable', 'فایل برای ارسال قابل خواندن نیست.');
    }

    if (class_exists('CURLFile') && function_exists('curl_init')) {
        $filename = isset($fields['_filename']) ? (string) $fields['_filename'] : basename($file_path);
        $mime     = isset($fields['_mime']) ? (string) $fields['_mime'] : 'application/octet-stream';
        unset($fields['_filename'], $fields['_mime']);

        $post = $fields;
        $post[$file_field] = new CURLFile($file_path, $mime, $filename);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return new WP_Error('bale_curl_error', $err !== '' ? $err : 'خطا در آپلود فایل به بله');
        }

        return [
            'headers'  => [],
            'body'     => $raw,
            'response' => ['code' => $code, 'message' => ''],
        ];
    }

    $boundary = wp_generate_password(24, false);
    $body = '';
    unset($fields['_filename'], $fields['_mime']);
    foreach ($fields as $key => $value) {
        if (is_array($value)) {
            $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
        $body .= $value . "\r\n";
    }

    $filename = basename($file_path);
    $file_contents = file_get_contents($file_path);
    if ($file_contents === false) {
        return new WP_Error('bale_file_read_failed', 'خواندن فایل ناموفق بود.');
    }
    $body .= "--{$boundary}\r\n";
    $body .= 'Content-Disposition: form-data; name="' . $file_field . '"; filename="' . $filename . "\"\r\n";
    $body .= "Content-Type: application/octet-stream\r\n\r\n";
    $body .= $file_contents . "\r\n";
    $body .= "--{$boundary}--\r\n";

    return wp_remote_post($url, [
        'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
        'body'    => $body,
        'timeout' => 120,
    ]);
}

/**
 * @param string $chat_id
 * @param string $photo مسیر محلی، file_id یا URL
 * @param string $caption
 * @param array  $extra _filename / _mime برای آپلود محلی
 */
function bale_send_photo($chat_id, $photo, $caption = '', $extra = []) {
    $fields = ['chat_id' => $chat_id];
    if ($caption !== '') {
        $fields['caption'] = $caption;
        $fields['parse_mode'] = 'HTML';
    }

    if (is_string($photo) && file_exists($photo) && is_file($photo)) {
        $fields = array_merge($fields, $extra);
        return bale_api_request_multipart('sendPhoto', $fields, 'photo', $photo);
    }

    $fields['photo'] = $photo;
    return bale_api_request('sendPhoto', $fields);
}

/**
 * @param string $chat_id
 * @param string $video مسیر محلی، file_id یا URL
 * @param string $caption
 * @param array  $extra
 */
function bale_send_video($chat_id, $video, $caption = '', $extra = []) {
    $fields = ['chat_id' => $chat_id];
    if ($caption !== '') {
        $fields['caption'] = $caption;
        $fields['parse_mode'] = 'HTML';
    }

    if (is_string($video) && file_exists($video) && is_file($video)) {
        $fields = array_merge($fields, $extra);
        return bale_api_request_multipart('sendVideo', $fields, 'video', $video);
    }

    $fields['video'] = $video;
    return bale_api_request('sendVideo', $fields);
}

/**
 * @param string $chat_id
 * @param string $document مسیر محلی، file_id یا URL
 * @param string $caption
 * @param array  $extra
 */
function bale_send_document($chat_id, $document, $caption = '', $extra = []) {
    $fields = ['chat_id' => $chat_id];
    if ($caption !== '') {
        $fields['caption'] = $caption;
        $fields['parse_mode'] = 'HTML';
    }

    if (is_string($document) && file_exists($document) && is_file($document)) {
        $fields = array_merge($fields, $extra);
        return bale_api_request_multipart('sendDocument', $fields, 'document', $document);
    }

    $fields['document'] = $document;
    return bale_api_request('sendDocument', $fields);
}

/**
 * ارسال رسانه به chat بر اساس نوع (photo|video|document).
 *
 * @param string      $chat_id
 * @param string      $media_type photo|video|document
 * @param string      $file_or_id مسیر یا file_id
 * @param string      $caption
 * @param array       $extra
 */
function bale_send_media($chat_id, $media_type, $file_or_id, $caption = '', $extra = []) {
    if ($media_type === 'photo') {
        return bale_send_photo($chat_id, $file_or_id, $caption, $extra);
    }
    if ($media_type === 'video') {
        return bale_send_video($chat_id, $file_or_id, $caption, $extra);
    }
    return bale_send_document($chat_id, $file_or_id, $caption, $extra);
}

/**
 * استخراج file_id از پاسخ موفق Bot API برای reuse در ارسال انبوه.
 *
 * @param array  $parsed خروجی bale_parse_api_response
 * @param string $media_type photo|video|document
 * @return string
 */
function bale_extract_file_id_from_response($parsed, $media_type) {
    if (empty($parsed['ok']) || empty($parsed['data']) || !is_array($parsed['data'])) {
        return '';
    }

    $result = $parsed['data']['result'] ?? $parsed['data'];
    if (!is_array($result)) {
        return '';
    }

    if ($media_type === 'photo' && !empty($result['photo']) && is_array($result['photo'])) {
        $last = end($result['photo']);
        return !empty($last['file_id']) ? (string) $last['file_id'] : '';
    }
    if ($media_type === 'video' && !empty($result['video']['file_id'])) {
        return (string) $result['video']['file_id'];
    }
    if ($media_type === 'document' && !empty($result['document']['file_id'])) {
        return (string) $result['document']['file_id'];
    }

    // fallback: هر نوع رسانه‌ای که در پاسخ باشد
    foreach (['document', 'video', 'audio', 'animation', 'voice'] as $key) {
        if (!empty($result[$key]['file_id'])) {
            return (string) $result[$key]['file_id'];
        }
    }
    if (!empty($result['photo']) && is_array($result['photo'])) {
        $last = end($result['photo']);
        if (!empty($last['file_id'])) {
            return (string) $last['file_id'];
        }
    }

    return '';
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

/**
 * آپلود فایل به سرویس سفیر و دریافت file_id.
 *
 * @param string $file_path
 * @param string $filename
 * @param string $mime
 * @return array{ok:bool,file_id?:string,message?:string}
 */
function sc_bale_safir_upload_file($file_path, $filename = '', $mime = '') {
    $api_key = sc_get_setting('sc_bale_safir_api_key', '');
    if ($api_key === '') {
        return ['ok' => false, 'message' => 'تنظیمات سفیر بله کامل نیست.'];
    }
    if (!is_readable($file_path)) {
        return ['ok' => false, 'message' => 'فایل برای آپلود قابل خواندن نیست.'];
    }

    $filename = $filename !== '' ? $filename : basename($file_path);
    $mime     = $mime !== '' ? $mime : 'application/octet-stream';
    $url      = 'https://safir.bale.ai/api/v3/upload_file';

    if (class_exists('CURLFile') && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['api-access-key: ' . $api_key],
            CURLOPT_POSTFIELDS     => [
                'file' => new CURLFile($file_path, $mime, $filename),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'message' => $err !== '' ? $err : 'خطا در آپلود فایل به سفیر'];
        }

        $body = json_decode($raw, true);
        if ($code >= 200 && $code < 300 && is_array($body) && !empty($body['file_id'])) {
            return ['ok' => true, 'file_id' => (string) $body['file_id']];
        }

        $message = 'آپلود فایل به سفیر ناموفق بود.';
        if (is_array($body)) {
            if (!empty($body['error']['description'])) {
                $message = $body['error']['description'];
            } elseif (!empty($body['description'])) {
                $message = $body['description'];
            }
        }
        return ['ok' => false, 'message' => $message];
    }

    return ['ok' => false, 'message' => 'افزونه CURL برای آپلود فایل به سفیر لازم است.'];
}

/**
 * @param string $phone شماره با فرمت 98...
 * @param string $text
 * @param string $file_id شناسه فایل سفیر (اختیاری)
 */
function sc_bale_send_by_phone($phone, $text, $file_id = '') {
    $api_key = sc_get_setting('sc_bale_safir_api_key', '');
    $bot_id  = sc_get_setting('sc_bale_safir_bot_id', '');

    if ($api_key === '' || $bot_id === '') {
        return new WP_Error('safir_not_configured', 'تنظیمات سفیر بله کامل نیست.');
    }

    $message = [];
    if ($text !== '') {
        $message['text'] = $text;
    }
    if ($file_id !== '') {
        $message['file_id'] = $file_id;
    }
    if (empty($message)) {
        return new WP_Error('safir_empty_message', 'متن یا فایل برای ارسال الزامی است.');
    }

    $url = 'https://safir.bale.ai/api/v3/send_message';
    $body = [
        'request_id'   => uniqid('msg_', true),
        'bot_id'       => (int) $bot_id,
        'phone_number' => $phone,
        'message_data' => [
            'message' => $message,
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

    if (
        $code >= 200 && $code < 300
        && is_array($body)
        && empty($body['error_data'])
        && (!isset($body['ok']) || $body['ok'] === true)
    ) {
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
