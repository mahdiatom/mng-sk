<?php
/**
 * SMS Functions for SportClub Manager
 * Integration with sms.ir API
 */
if ( ! defined('ABSPATH') ) exit;
/**
 * Send SMS by pattern (shortcut function)
 */
function sc_send_sms_by_pattern($pattern_type, $mobile, $parameters = array()) {
    $pattern_code = sc_get_sms_pattern($pattern_type, 'user'); // Default to user pattern
    if (empty($pattern_code)) {
        return ['success' => false, 'message' => 'کد پترن تعریف نشده است'];
    }

    return sc_send_sms($mobile, '', true, $pattern_code, $parameters);
}

/**
 * Get SMS delivery status
 */
function sc_get_sms_status($message_id) {
    $api_key = sc_get_setting('sms_api_key', '');

    if (empty($api_key)) {
        return ['success' => false, 'message' => 'تنظیمات پیامک کامل نیست'];
    }

    $url = 'https://api.sms.ir/v1/send/' . (int)$message_id;

    $headers = [
        'Accept: application/json',
        'X-API-KEY: ' . $api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'خطا در اتصال به API: ' . $error];
    }

    $response_data = json_decode($response, true);

    if ($http_code == 200 && isset($response_data['status']) && $response_data['status'] == 1) {
        $delivery_states = [
            1 => 'رسیده به گوشی',
            2 => 'نرسیده به گوشی',
            3 => 'رسیده به مخابرات',
            4 => 'نرسیده به مخابرات',
            5 => 'رسیده به اپراتور',
            6 => 'ناموفق',
            7 => 'لیست سیاه'
        ];

        $delivery_state = isset($response_data['data']['deliveryState']) ?
            ($delivery_states[$response_data['data']['deliveryState']] ?? 'نامشخص') : 'نامشخص';

        return [
            'success' => true,
            'message_id' => $response_data['data']['messageId'] ?? null,
            'mobile' => $response_data['data']['mobile'] ?? null,
            'message_text' => $response_data['data']['messageText'] ?? null,
            'send_date_time' => $response_data['data']['sendDateTime'] ?? null,
            'delivery_state' => $delivery_state,
            'delivery_date_time' => $response_data['data']['deliveryDateTime'] ?? null
        ];
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'خطای نامشخص';
        return ['success' => false, 'message' => 'خطا در دریافت وضعیت: ' . $error_message];
    }
}

/**
 * Get SMS credit balance
 */
function sc_get_sms_credit() {
    $api_key = sc_get_setting('sms_api_key', '');

    if (empty($api_key)) {
        return ['success' => false, 'message' => 'تنظیمات پیامک کامل نیست'];
    }

    $url = 'https://api.sms.ir/v1/credit';

    $headers = [
        'Accept: application/json',
        'X-API-KEY: ' . $api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'خطا در اتصال به API: ' . $error];
    }

    $response_data = json_decode($response, true);

    if ($http_code == 200 && isset($response_data['status']) && $response_data['status'] == 1) {
        return [
            'success' => true,
            'credit' => $response_data['data'] ?? 0,
            'message' => 'اعتبار با موفقیت دریافت شد'
        ];
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'خطای نامشخص';
        return ['success' => false, 'message' => 'خطا در دریافت اعتبار: ' . $error_message];
    }
}

/**
 * ثبت لاگ ارسال پیامک در دیتابیس (برای گزارشات ارسال پیامک)
 */
function sc_sms_log_insert($mobile, $message_display, $context, $result) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_sms_log';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return;
    }
    $success = isset($result['success']) && $result['success'] ? 1 : 0;
    $error_message = isset($result['message']) && !$success ? $result['message'] : null;
    $response_message = isset($result['message']) && $success ? $result['message'] : null;
    if (strlen((string) $response_message) > 500) {
        $response_message = substr($response_message, 0, 497) . '…';
    }
    $message_id = isset($result['message_id']) ? $result['message_id'] : null;
    $extra = [];
    if (isset($result['error_code'])) {
        $extra['error_code'] = $result['error_code'];
    }
    if (isset($result['cost'])) {
        $extra['cost'] = $result['cost'];
    }
    $message_text = is_string($message_display) ? $message_display : '';
    if (strlen($message_text) > 65530) {
        $message_text = substr($message_text, 0, 65530);
    }
    $wpdb->insert($table, [
        'created_at' => current_time('mysql'),
        'mobile' => $mobile,
        'message_text' => $message_text,
        'context' => $context ?: null,
        'success' => $success,
        'error_message' => $error_message,
        'response_message' => $response_message,
        'message_id' => $message_id,
        'extra' => empty($extra) ? null : wp_json_encode($extra),
    ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
}

/**
 * بررسی وضعیت تحویل پیام از API سامانه (sms.ir) و به‌روزرسانی رکورد لاگ
 * برای تشخیص لیست سیاه / نرسیده به گوشی و غیره
 *
 * @param int $log_id شناسه رکورد در sc_sms_log
 * @return array// { success, delivery_state, message } یا خطا
 */
function sc_sms_log_check_delivery_status($log_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_sms_log';
    $log = $wpdb->get_row($wpdb->prepare("SELECT id, message_id FROM `$table` WHERE id = %d", $log_id));
    if (!$log || empty($log->message_id)) {
        return ['success' => false, 'message' => 'رکورد یا شناسه پیام یافت نشد.'];
    }
    $status = sc_get_sms_status($log->message_id);
    if (!isset($status['success']) || !$status['success']) {
        return [
            'success' => false,
            'message' => isset($status['message']) ? $status['message'] : 'خطا در دریافت وضعیت از API',
        ];
    }
    $delivery_state = isset($status['delivery_state']) ? $status['delivery_state'] : '';
    $now = current_time('mysql');
    $wpdb->update(
        $table,
        ['delivery_state' => $delivery_state, 'delivery_checked_at' => $now],
        ['id' => $log_id],
        ['%s', '%s'],
        ['%d']
    );
    return [
        'success' => true,
        'delivery_state' => $delivery_state,
        'message' => 'وضعیت تحویل به‌روزرسانی شد.',
    ];
}

/**
 * وضعیت‌های ناموفق تحویل پیامک (برای نمایش badge)
 *
 * @return string[]
 */
function sc_sms_delivery_failed_states() {
    return ['لیست سیاه', 'ناموفق', 'نرسیده به گوشی', 'نرسیده به مخابرات'];
}

/**
 * دریافت وضعیت تحویل از API برای رکوردهای بدون وضعیت ذخیره‌شده (هنگام بارگذاری گزارش)
 *
 * @param array $logs
 * @return array
 */
function sc_sms_log_refresh_delivery_states_for_list($logs) {
    if (empty($logs) || !is_array($logs)) {
        return $logs;
    }
    foreach ($logs as $log) {
        if (empty($log->message_id) || !empty($log->delivery_state)) {
            continue;
        }
        $result = sc_sms_log_check_delivery_status((int) $log->id);
        if (!empty($result['success'])) {
            $log->delivery_state = $result['delivery_state'];
        } else {
            $log->delivery_fetch_error = $result['message'] ?? 'خطا در دریافت وضعیت';
        }
    }
    return $logs;
}

/**
 * HTML badge وضعیت تحویل پیامک
 *
 * @param string $delivery_state
 * @param string $fetch_error
 * @return string
 */
function sc_render_sms_delivery_state_html($delivery_state, $fetch_error = '') {
    $delivery_state = (string) $delivery_state;
    if ($delivery_state !== '') {
        if (in_array($delivery_state, sc_sms_delivery_failed_states(), true)) {
            return '<span class="sc-badge sc-badge--danger sc-delivery-state sc-delivery-fail" title="وضعیت واقعی از API سامانه">' . esc_html($delivery_state) . '</span>';
        }
        if ($delivery_state === 'رسیده به گوشی') {
            return '<span class="sc-badge sc-badge--success sc-delivery-state sc-delivery-ok">' . esc_html($delivery_state) . '</span>';
        }
        return '<span class="sc-badge sc-badge--soft sc-delivery-state">' . esc_html($delivery_state) . '</span>';
    }
    if ($fetch_error !== '') {
        return '<span class="sc-badge sc-badge--muted sc-delivery-state" title="' . esc_attr($fetch_error) . '">نامشخص</span>';
    }
    return '<span class="sc-badge sc-badge--muted">—</span>';
}

/**
 * Send SMS via sms.ir API
 * @param string $context بخش مبدا برای لاگ: ticket_new, invoice, enrollment, ...
 */
function sc_send_sms($mobile, $message, $is_pattern = false, $pattern_code = null, $parameters = array(), $context = '') {
    // Master SMS switch check
    if ((int)sc_get_setting('sms_master_enabled', '1') !== 1) {
        return ['success' => false, 'message' => 'ارسال پیامک توسط مدیر غیرفعال شده است'];
    }

    // Get SMS settings
    $api_key = sc_get_setting('sms_api_key', '');
    $sender = sc_get_setting('sms_sender', '');

    if (empty($api_key) || empty($sender)) {
        sc_log_sms('ERROR', 'SMS settings not configured', ['mobile' => $mobile]);
        return ['success' => false, 'message' => 'تنظیمات پیامک کامل نیست'];
    }

    // Clean mobile number
    $original_mobile = $mobile;
    $mobile = sc_clean_mobile_number($mobile);
    sc_log_sms('DEBUG', 'Mobile number cleaned', ['original' => $original_mobile, 'cleaned' => $mobile]);
    if (!$mobile) {
        sc_log_sms('ERROR', 'Invalid mobile number', ['original' => $original_mobile, 'cleaned' => $mobile]);
        return ['success' => false, 'message' => 'شماره موبایل نامعتبر'];
    }

    $result = ['success' => false, 'message' => '', 'message_id' => null];

    if ($is_pattern && !empty($pattern_code)) {
        // Send pattern SMS
        $result = sc_send_pattern_sms($mobile, $pattern_code, $parameters);
    } else {
        // Send regular SMS
        $result = sc_send_regular_sms($mobile, $message);
    }

    // Log the result (file)
    sc_log_sms(
        $result['success'] ? 'SUCCESS' : 'ERROR',
        $result['success'] ? 'SMS sent successfully' : $result['message'],
        [
            'mobile' => $mobile,
            'is_pattern' => $is_pattern,
            'pattern_code' => $pattern_code,
            'message_id' => $result['message_id']
        ]
    );

    // لاگ در دیتابیس برای گزارشات ارسال پیامک
    $message_display = $is_pattern && $pattern_code ? ('پترن: ' . $pattern_code) : $message;
    sc_sms_log_insert($mobile, $message_display, $context, $result);

    if ($message !== '' && function_exists('sc_bale_mirror_sms_from_mobile')) {
        sc_bale_mirror_sms_from_mobile($mobile, $message, $context);
    }

    return $result;
}

/**
 * Send regular SMS via sms.ir
 */
function sc_send_regular_sms($mobile, $message) {
    if ((int)sc_get_setting('sms_master_enabled', '1') !== 1) {
        return ['success' => false, 'message' => 'ارسال پیامک توسط مدیر غیرفعال شده است'];
    }

    $api_key = sc_get_setting('sms_api_key', '');
    $sender = sc_get_setting('sms_sender', '');

    // sms.ir API endpoint for likeToLike SMS (supports different messages for different mobiles)
    $url = 'https://api.sms.ir/v1/send/likeToLike';

    $data = [
        'lineNumber' => (int)$sender,
        'messageTexts' => [$message],
        'mobiles' => [$mobile],
        'sendDateTime' => null // ارسال در لحظه
    ];

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-KEY: ' . $api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Debug API response
    sc_log_sms('DEBUG', 'SMS API Response', [
        'url' => $url,
        'http_code' => $http_code,
        'response' => $response,
        'data' => $data,
        'error' => $error,
        'api_key_masked' => substr($api_key, 0, 10) . '***'
    ]);

    if ($error) {
        return ['success' => false, 'message' => 'خطا در اتصال به API: ' . $error];
    }

    $response_data = json_decode($response, true);

    if ($http_code == 200 && isset($response_data['status']) && $response_data['status'] == 1) {
        return [
            'success' => true,
            'message' => 'پیامک با موفقیت ارسال شد',
            'message_id' => isset($response_data['data']['messageIds'][0]) ? $response_data['data']['messageIds'][0] : null,
            'pack_id' => isset($response_data['data']['packId']) ? $response_data['data']['packId'] : null,
            'cost' => isset($response_data['data']['cost']) ? $response_data['data']['cost'] : null
        ];
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'خطای نامشخص';
        $error_code = isset($response_data['status']) ? $response_data['status'] : 'نامشخص';

        // تبدیل کدهای خطا به پیام فارسی
        $error_messages = [
            0 => 'درخواست شما با خطا مواجه شده‌است',
            10 => 'کلید وب سرویس نامعتبر است',
            11 => 'کلید وب سرویس غیرفعال است',
            12 => 'کلید وب سرویس محدود به آی‌پی‌های تعریف شده می‌باشد',
            13 => 'حساب کاربری غیرفعال است',
            14 => 'حساب کاربری در حالت تعلیق قرار دارد',
            15 => 'به منظور استفاده از وب سرویس پلن خود را ارتقا دهید',
            16 => 'مقدار ارسالی پارامتر نادرست می‌باشد',
            20 => 'تعداد درخواست بیشتر از حد مجاز است',
            101 => 'شماره خط نامعتبر میباشد',
            102 => 'اعتبار کافی نمیباشد',
            103 => 'درخواست شما دارای متن (های) خالی است',
            104 => 'درخواست شما دارای موبایل (های) نادرست است',
            105 => 'تعداد موبایل ها بیشتر از حد مجاز (100 عدد) میباشد',
            106 => 'تعداد متن ها بیشتر از حد مجاز (100 عدد) میباشد',
            107 => 'لیست موبایل ها خالی میباشد',
            108 => 'لیست متن ها خالی میباشد',
            109 => 'زمان ارسال نامعتبر میباشد',
            110 => 'تعداد شماره موبایل ها و تعداد متن ها برابر نیستند',
            111 => 'با این شناسه ارسالی ثبت نشده است',
            112 => 'رکوردی برای حذف یافت نشد',
            113 => 'قالب یافت نشد',
            114 => 'طول رشته مقدار پارامتر، بیش از حد مجاز (25 کاراکتر) میباشد',
            115 => 'شماره موبایل(ها) در لیست سیاه سامانه می‌باشند',
            116 => 'نام یک یا چند پارامتر مقداردهی نشده‌است',
            117 => 'متن ارسال شده مورد تایید نمی‌باشد',
            118 => 'تعداد پیام ها بیشتر از حد مجاز میباشد',
            119 => 'به منظور استفاده از قالب‌ شخصی سازی شده پلن خود را ارتقا دهید',
            123 => 'خط ارسال‌کننده نیاز به فعال‌سازی دارد'
        ];

        if (isset($error_messages[$error_code])) {
            $error_message = $error_messages[$error_code];
        }

        return [
            'success' => false,
            'message' => 'خطا در ارسال پیامک: ' . $error_message,
            'error_code' => $error_code
        ];
    }
}

/**
 * Send pattern SMS via sms.ir
 */
function sc_send_pattern_sms($mobile, $pattern_code, $parameters = array()) {
    if ((int)sc_get_setting('sms_master_enabled', '1') !== 1) {
        return ['success' => false, 'message' => 'ارسال پیامک توسط مدیر غیرفعال شده است'];
    }

    $api_key = sc_get_setting('sms_api_key', '');

    // sms.ir API endpoint for verify (pattern) SMS
    $url = 'https://api.sms.ir/v1/send/verify';

    // Convert parameters array to sms.ir format
    $sms_parameters = [];
    foreach ($parameters as $key => $value) {
        $sms_parameters[] = [
            'name' => $key,
            'value' => (string)$value
        ];
    }

    $data = [
        'mobile' => $mobile,
        'templateId' => (int)$pattern_code,
        'parameters' => $sms_parameters
    ];

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-KEY: ' . $api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'خطا در اتصال به API: ' . $error];
    }

    $response_data = json_decode($response, true);

    if ($http_code == 200 && isset($response_data['status']) && $response_data['status'] == 1) {
        return [
            'success' => true,
            'message' => 'پیامک پترن با موفقیت ارسال شد',
            'message_id' => isset($response_data['data']['messageId']) ? $response_data['data']['messageId'] : null,
            'cost' => isset($response_data['data']['cost']) ? $response_data['data']['cost'] : null
        ];
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'خطای نامشخص';
        $error_code = isset($response_data['status']) ? $response_data['status'] : 'نامشخص';

        // تبدیل کدهای خطا به پیام فارسی
        $error_messages = [
            0 => 'درخواست شما با خطا مواجه شده‌است',
            10 => 'کلید وب سرویس نامعتبر است',
            11 => 'کلید وب سرویس غیرفعال است',
            12 => 'کلید وب سرویس محدود به آی‌پی‌های تعریف شده می‌باشد',
            13 => 'حساب کاربری غیرفعال است',
            14 => 'حساب کاربری در حالت تعلیق قرار دارد',
            15 => 'به منظور استفاده از وب سرویس پلن خود را ارتقا دهید',
            16 => 'مقدار ارسالی پارامتر نادرست می‌باشد',
            20 => 'تعداد درخواست بیشتر از حد مجاز است',
            101 => 'شماره خط نامعتبر میباشد',
            102 => 'اعتبار کافی نمیباشد',
            103 => 'درخواست شما دارای متن (های) خالی است',
            104 => 'درخواست شما دارای موبایل (های) نادرست است',
            105 => 'تعداد موبایل ها بیشتر از حد مجاز (100 عدد) میباشد',
            106 => 'تعداد متن ها بیشتر از حد مجاز (100 عدد) میباشد',
            107 => 'لیست موبایل ها خالی میباشد',
            108 => 'لیست متن ها خالی میباشد',
            109 => 'زمان ارسال نامعتبر میباشد',
            110 => 'تعداد شماره موبایل ها و تعداد متن ها برابر نیستند',
            111 => 'با این شناسه ارسالی ثبت نشده است',
            112 => 'رکوردی برای حذف یافت نشد',
            113 => 'قالب یافت نشد',
            114 => 'طول رشته مقدار پارامتر، بیش از حد مجاز (25 کاراکتر) میباشد',
            115 => 'شماره موبایل(ها) در لیست سیاه سامانه می‌باشند',
            116 => 'نام یک یا چند پارامتر مقداردهی نشده‌است',
            117 => 'متن ارسال شده مورد تایید نمی‌باشد',
            118 => 'تعداد پیام ها بیشتر از حد مجاز میباشد',
            119 => 'به منظور استفاده از قالب‌ شخصی سازی شده پلن خود را ارتقا دهید',
            123 => 'خط ارسال‌کننده نیاز به فعال‌سازی دارد'
        ];

        if (isset($error_messages[$error_code])) {
            $error_message = $error_messages[$error_code];
        }

        return [
            'success' => false,
            'message' => 'خطا در ارسال پیامک پترن: ' . $error_message,
            'error_code' => $error_code
        ];
    }
}

/**
 * Clean and validate mobile number
 */
function sc_clean_mobile_number($mobile) {
    // Remove all non-numeric characters - Updated
    $mobile = preg_replace('/\D/', '', $mobile);

    // Check if it's Iranian mobile number
    if (preg_match('/^09\d{9}$/', $mobile)) {
        return $mobile;
    }

    // If it starts with 98 (country code), convert to 09
    if (preg_match('/^989\d{9}$/', $mobile)) {
        return '0' . substr($mobile, 2);
    }

    // If it starts with 9 (without 0), add 0 — Iranian mobile is 10 digits: 9XXXXXXXXX
    if (preg_match('/^9\d{9}$/', $mobile)) {
        return '0' . $mobile;
    }

    // 00989xxxxxxxxx
    if (preg_match('/^00989\d{9}$/', $mobile)) {
        return '0' . substr($mobile, 4);
    }

    return false;
}

/**
 * Log SMS activities (file + دیتابیس برای گزارشات ارسال پیامک)
 */
function sc_log_sms($status, $message, $data = array()) {
    $timestamp = current_time('Y-m-d H:i:s');
    $log_file = WP_CONTENT_DIR . '/sc-sms-log.txt';

    $log_entry = sprintf(
        "[%s] %s: %s\n",
        $timestamp,
        $status,
        $message
    );

    if (!empty($data)) {
        $log_entry .= "Data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    }

    $log_entry .= "---\n";

    // Write to log file
    $fp = fopen($log_file, 'a');
    if ($fp) {
        fwrite($fp, $log_entry);
        fclose($fp);
    }

    // ذخیره در دیتابیس برای نمایش در گزارشات ارسال پیامک
    sc_log_sms_entry_to_db($timestamp, $status, $message, $data);
}

/**
 * ثبت هر ورودی لاگ پیامک در جدول sc_sms_log_entries
 */
function sc_log_sms_entry_to_db($created_at, $level, $message, $data = array()) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_sms_log_entries';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return;
    }
    $level = in_array($level, ['DEBUG', 'INFO', 'SUCCESS', 'ERROR'], true) ? $level : 'INFO';
    $message = is_string($message) ? $message : '';
    if (mb_strlen($message) > 500) {
        $message = mb_substr($message, 0, 497) . '…';
    }
    $data_json = empty($data) ? null : wp_json_encode($data, JSON_UNESCAPED_UNICODE);
    $wpdb->insert($table, [
        'created_at' => $created_at,
        'level' => $level,
        'message' => $message,
        'data' => $data_json,
    ], ['%s', '%s', '%s', '%s']);
}

/**
 * Default values for all SMS settings (templates, enabled flags, patterns).
 */
function sc_get_sms_settings_defaults() {
    static $defaults = null;
    if ($defaults !== null) {
        return $defaults;
    }

    $pair = static function ($prefix, $user_template, $admin_template) {
        return [
            $prefix . '_user_enabled' => '0',
            $prefix . '_user_template' => $user_template,
            $prefix . '_user_pattern' => '',
            $prefix . '_admin_enabled' => '0',
            $prefix . '_admin_template' => $admin_template,
            $prefix . '_admin_pattern' => '',
        ];
    };

    $user_only = static function ($prefix, $user_template) {
        return [
            $prefix . '_user_enabled' => '0',
            $prefix . '_user_template' => $user_template,
            $prefix . '_user_pattern' => '',
        ];
    };

    $defaults = [
        'sms_master_enabled' => '1',
        'sms_api_key' => '',
        'sms_sender' => '',
        'sms_admin_phone' => '',
        'sms_reminder_delay_minutes' => '4320',
    ];

    $defaults = array_merge($defaults, $pair(
        'sms_invoice',
        'کاربر گرامی %user_name%، صورت‌حساب %item_name% به مبلغ %amount% تومان صادر شد.',
        'صورت‌حساب جدید: %user_name% - %item_name% - %amount% تومان'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_invoice_cancelled',
        'کاربر گرامی %user_name%، صورت‌حساب %item_name% لغو شد.',
        'صورت‌حساب لغو شد: %user_name% - %item_name%'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_invoice_onhold',
        'کاربر گرامی %user_name%، صورت‌حساب %item_name% در انتظار بررسی است.',
        'صورت‌حساب در انتظار بررسی: %user_name% - %item_name%'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_invoice_paid',
        'کاربر گرامی %user_name%، صورت‌حساب %item_name% پرداخت شد.',
        'صورت‌حساب پرداخت شد: %user_name% - %item_name%'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_enrollment',
        'کاربر گرامی %user_name%، ثبت‌نام شما در %item_name% انجام شد.',
        'ثبت‌نام جدید: %user_name% در %item_name%'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_course_capacity_waitlist',
        'کاربر گرامی %user_name%، ظرفیت %item_name% باز شد. ثبت‌نام: %enroll_url%'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_reminder',
        'کاربر گرامی %user_name%، مهلت پرداخت %item_name% (%amount% تومان) به پایان رسیده است.',
        'یادآوری پرداخت: %user_name% - %item_name% - %amount% تومان'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_renewal_date',
        'کاربر گرامی %user_name%، تنها %days_remaining% روز تا زمان تمدید دوره %course_name% در تاریخ %renewal_date% باقی مانده است.'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_renewal_sessions',
        'کاربر گرامی %user_name%، از دوره %course_name% فقط %remaining_sessions% جلسه باقی مانده است. زمان تمدید دوره نزدیک است.'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_absence',
        'کاربر گرامی %user_name%، غیبت شما در %item_name% مورخ %date% ثبت شد.',
        'غیبت: %user_name% - %item_name% - %date%'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_absence_alert',
        'کاربر گرامی %user_name%، غیبت شما در %item_name% به %absence_count% رسید.',
        'هشدار غیبت: %user_name% - %item_name% - %absence_count% غیبت'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_birthday',
        '%user_name% عزیز، تولدتان مبارک!'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_insurance_expiry',
        'کاربر گرامی %user_name%، بیمه شما تا %expiry_date% اعتبار دارد.'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_coach_certificate_expiry',
        'مربی گرامی %user_name%، تاریخ انقضای مدرک مربیگری شما %expiry_date% است. لطفاً برای تمدید آن اقدام کنید.',
        'یادآوری مدیر: مدرک مربیگری %coach_name% در تاریخ %expiry_date% منقضی می‌شود.'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_identity_verified',
        'کاربر گرامی %user_name%، احراز هویت شما تأیید شد.'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_identity_rejected',
        'کاربر گرامی %user_name%، احراز هویت شما رد شد. علت: %reason%'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_certificate',
        'کاربر گرامی %user_name%، گواهینامه شما صادر شد.'
    ));
    $defaults = array_merge($defaults, $pair(
        'sms_survey_submission',
        'کاربر گرامی %user_name%، پاسخ شما در نظرسنجی «%survey_title%» ثبت شد.',
        'پاسخ جدید نظرسنجی: %user_name% - %survey_title%'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_wallet_low_balance',
        'کاربر گرامی %user_name%، موجودی کیف پول %balance% تومان است.'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_wallet_negative_balance',
        'کاربر گرامی %user_name%، موجودی کیف پول منفی شده (%balance% تومان).'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_wallet_charge_success',
        'کاربر گرامی %user_name%، کیف پول %amount% تومان شارژ شد. موجودی: %balance% تومان.'
    ));
    $defaults = array_merge($defaults, $user_only(
        'sms_wallet_payment',
        'کاربر گرامی %user_name%، %amount% تومان از کیف پول کسر شد. موجودی: %balance% تومان.'
    ));

    $wc_physical = [
        'paid' => ['پرداخت شده', 'کاربر گرامی %user_name%، سفارش #%order_id% پرداخت شد.', 'سفارش #%order_id% پرداخت شد - %user_name%'],
        'awaiting_shipment' => ['در انتظار ارسال', 'کاربر گرامی %user_name%، سفارش #%order_id% در انتظار ارسال است.', 'سفارش #%order_id% در انتظار ارسال - %user_name%'],
        'confirmed_shipped' => ['ارسال و تأیید', 'کاربر گرامی %user_name%، سفارش #%order_id% ارسال و تأیید شد.', 'سفارش #%order_id% ارسال و تأیید - %user_name%'],
        'completed' => ['تکمیل‌شده', 'کاربر گرامی %user_name%، سفارش #%order_id% تکمیل شد.', 'سفارش #%order_id% تکمیل شد - %user_name%'],
        'cancelled' => ['لغو‌شده', 'کاربر گرامی %user_name%، سفارش #%order_id% لغو شد.', 'سفارش #%order_id% لغو شد - %user_name%'],
        'onhold' => ['در انتظار بررسی', 'کاربر گرامی %user_name%، سفارش #%order_id% در انتظار بررسی است.', 'سفارش #%order_id% در انتظار بررسی - %user_name%'],
        'failed' => ['ناموفق', 'کاربر گرامی %user_name%، سفارش #%order_id% ناموفق بود.', 'سفارش #%order_id% ناموفق - %user_name%'],
    ];
    foreach ($wc_physical as $status => $tpl) {
        $defaults = array_merge($defaults, $pair('sms_wc_order_' . $status, $tpl[1], $tpl[2]));
    }

    $wc_virtual = [
        'paid' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% (%product_names%) پرداخت شد.', 'سفارش مجازی #%order_id% پرداخت شد - %user_name%'],
        'awaiting_shipment' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% در انتظار ارسال است.', 'سفارش مجازی #%order_id% در انتظار ارسال - %user_name%'],
        'confirmed_shipped' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% ارسال و تأیید شد.', 'سفارش مجازی #%order_id% ارسال و تأیید - %user_name%'],
        'completed' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% (%product_names%) تکمیل شد.', 'سفارش مجازی #%order_id% - %user_name% - %product_names%'],
        'cancelled' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% لغو شد.', 'سفارش مجازی #%order_id% لغو شد - %user_name%'],
        'onhold' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% در انتظار بررسی است.', 'سفارش مجازی #%order_id% در انتظار بررسی - %user_name%'],
        'failed' => ['کاربر گرامی %user_name%، سفارش مجازی #%order_id% ناموفق بود.', 'سفارش مجازی #%order_id% ناموفق - %user_name%'],
    ];
    foreach ($wc_virtual as $status => $tpl) {
        $defaults = array_merge($defaults, $pair('sms_wc_order_virtual_' . $status, $tpl[0], $tpl[1]));
    }

    $defaults['sms_ticket_new_recipient_enabled'] = '0';
    $defaults['sms_ticket_new_recipient_template'] = 'تیکت جدید #{ticket_id} - موضوع: {subject}';
    $defaults['sms_ticket_new_recipient_pattern'] = '';
    $defaults['sms_ticket_reply_enabled'] = '0';
    $defaults['sms_ticket_reply_template'] = 'پاسخ جدید به تیکت #{ticket_id}. لطفاً پنل خود را بررسی کنید.';
    $defaults['sms_ticket_reply_pattern'] = '';

    return $defaults;
}

/**
 * Get SMS setting with fallback to defaults (empty templates use default text).
 */
function sc_get_sms_setting($key) {
    $defaults = sc_get_sms_settings_defaults();
    $default = array_key_exists($key, $defaults) ? $defaults[$key] : '';
    $value = sc_get_setting($key, null);

    if ($value === null) {
        return $default;
    }

    if ($value === '' && $default !== '' && strpos($key, '_template') !== false) {
        return $default;
    }

    return $value;
}

/**
 * Get SMS template for specific action
 */
function sc_get_sms_template($action, $type = 'user') {
    return sc_get_sms_setting("sms_{$action}_{$type}_template");
}

/**
 * Get SMS pattern code for specific action
 */
function sc_get_sms_pattern($action, $type = 'user') {
    // $type can be 'user' or 'admin'
    $pattern = sc_get_setting("sms_{$action}_{$type}_pattern", '');
    return !empty($pattern) ? (int)$pattern : null;
}

/**
 * Check if SMS is enabled for specific action
 */
function sc_is_sms_enabled_for($action, $type = 'user') {
    return (int) sc_get_sms_setting("sms_{$action}_{$type}_enabled") === 1;
}

/**
 * Replace variables in SMS template
 */
function sc_replace_sms_variables($template, $variables) {
    foreach ($variables as $key => $value) {
        $template = str_replace("%{$key}%", $value, $template);
    }
    return $template;
}

/**
 * Send SMS notification for invoice creation
 */
function sc_send_invoice_sms($invoice_id) {
    error_log("SC SMS: Invoice SMS hook called for invoice ID: $invoice_id");
    sc_send_invoice_action_sms($invoice_id, 'invoice');
}

/**
 * Send SMS notification for course enrollment success (after payment)
 */
function sc_send_enrollment_success_sms($member_course_id) {
    sc_send_enrollment_sms($member_course_id);
}


/**
 * Send SMS notification for course enrollment (deprecated - use success version)
 */
function sc_send_enrollment_sms($member_course_id) {
    error_log("SC SMS: Enrollment SMS hook called for member_course_id: $member_course_id");

    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';

    // Get enrollment details
    $enrollment = $wpdb->get_row($wpdb->prepare(
        "SELECT mc.*, m.first_name, m.last_name, m.player_phone, c.title as course_title, c.price
         FROM $member_courses_table mc
         LEFT JOIN $members_table m ON mc.member_id = m.id
         LEFT JOIN $courses_table c ON mc.course_id = c.id
         WHERE mc.id = %d",
        $member_course_id
    ));

    // Debug logging
    error_log("SC SMS: Enrollment details - exists: " . ($enrollment ? 'yes' : 'no') . ", phone: " . ($enrollment ? $enrollment->player_phone : 'none'));

    sc_log_sms('DEBUG', 'Enrollment SMS called', [
        'member_course_id' => $member_course_id,
        'enrollment_exists' => $enrollment ? 'yes' : 'no',
        'player_phone' => $enrollment ? $enrollment->player_phone : 'no enrollment',
        'member_id' => $enrollment ? $enrollment->member_id : 'no enrollment'
    ]);

    if (!$enrollment || empty($enrollment->player_phone)) {
        sc_log_sms('DEBUG', 'Enrollment SMS skipped - no phone or enrollment', [
            'member_course_id' => $member_course_id,
            'reason' => !$enrollment ? 'no enrollment found' : 'empty phone'
        ]);
        return;
    }

    $user_name = trim($enrollment->first_name . ' ' . $enrollment->last_name);
    $course_name = $enrollment->course_title ?: 'دوره';
    $amount = number_format($enrollment->price, 0, '.', ',');

    $variables = [
        'user_name' => $user_name,
        'course_name' => $course_name,
        'event_name' => '', // Enrollment is for courses, not events
        'item_name' => $course_name, // For enrollment, item_name is the course
        'expense_name' => '',
        'amount' => $amount
    ];

    // Send SMS to user
    $user_enabled = sc_is_sms_enabled_for('enrollment', 'user');
    $user_template = sc_get_sms_template('enrollment', 'user');

    error_log("SC SMS: User SMS check - enabled: " . ($user_enabled ? 'yes' : 'no') . ", template: " . (!empty($user_template) ? 'exists' : 'empty') . ", phone: " . $enrollment->player_phone);

    sc_log_sms('DEBUG', 'Enrollment SMS check', [
        'user_enabled' => $user_enabled ? 'yes' : 'no',
        'user_template' => !empty($user_template) ? 'exists' : 'empty',
        'phone' => $enrollment->player_phone
    ]);

    if ($user_enabled) {
        if (!empty($user_template)) {
            $message = sc_replace_sms_variables($user_template, $variables);
            $pattern_code = sc_get_sms_pattern('enrollment', 'user');
            $result = sc_send_sms($enrollment->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'enrollment');

            sc_log_sms('INFO', 'Enrollment SMS to user sent', [
                'phone' => $enrollment->player_phone,
                'success' => $result['success'] ? 'yes' : 'no',
                'message' => $result['message']
            ]);

        } else {
            sc_log_sms('DEBUG', 'Enrollment SMS to user skipped - no template');
        }
    } else {
        sc_log_sms('DEBUG', 'Enrollment SMS to user disabled');
    }

    // Send SMS to admin
    if (sc_is_sms_enabled_for('enrollment', 'admin')) {
        sc_log_sms('DEBUG', 'Enrollment SMS to admin enabled', [
            'admin_phone' => sc_get_setting('sms_admin_phone', ''),
            'template_exists' => !empty(sc_get_sms_template('enrollment', 'admin'))
        ]);

        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if (!empty($admin_phone)) {
            $template = sc_get_sms_template('enrollment', 'admin');
            if (!empty($template)) {
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = sc_get_sms_pattern('enrollment', 'admin');
                $result = sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'enrollment');

                sc_log_sms('DEBUG', 'Enrollment SMS to admin result', [
                    'phone' => $admin_phone,
                    'success' => $result['success'],
                    'message' => $result['message']
                ]);
            } else {
                sc_log_sms('DEBUG', 'Enrollment SMS to admin skipped - no template');
            }
        } else {
            sc_log_sms('DEBUG', 'Enrollment SMS to admin skipped - no admin phone');
        }
    } else {
        sc_log_sms('DEBUG', 'Enrollment SMS to admin disabled');
    }
}

/**
 * Send SMS notification for payment reminder
 */
function sc_send_payment_reminder_sms($invoice_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $events_table = $wpdb->prefix . 'sc_events';

    // Get invoice details with support for courses, events, and expenses
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, m.first_name, m.last_name, m.player_phone,
                c.title as course_title, c.price as course_price,
                e.name as event_name, e.price as event_price
         FROM $invoices_table i
         LEFT JOIN $members_table m ON i.member_id = m.id
         LEFT JOIN $courses_table c ON i.course_id = c.id
         LEFT JOIN $events_table e ON i.event_id = e.id
         WHERE i.id = %d",
        $invoice_id
    ));

    if (!$invoice || empty($invoice->player_phone)) {
        return;
    }

    $user_name = trim($invoice->first_name . ' ' . $invoice->last_name);

    // Determine item name based on invoice type
    $item_name = '';
    if (!empty($invoice->course_title)) {
        $item_name = $invoice->course_title;
    } elseif (!empty($invoice->event_name)) {
        $item_name = $invoice->event_name;
    } elseif (!empty($invoice->expense_name)) {
        $item_name = $invoice->expense_name;
    } else {
        $item_name = 'صورت حساب';
    }

    $amount = number_format($invoice->amount + $invoice->penalty_amount, 0, '.', ',');
    $penalty_amount = number_format($invoice->penalty_amount, 0, '.', ',');

    $variables = [
        'user_name' => $user_name,
        'course_name' => $invoice->course_title ?: '',
        'event_name' => $invoice->event_name ?: '',
        'item_name' => $item_name,
        'expense_name' => $invoice->expense_name ?: '',
        'amount' => $amount,
        'penalty_amount' => $penalty_amount
    ];

    // Send SMS to user
    if (sc_is_sms_enabled_for('reminder', 'user')) {
        $template = sc_get_sms_template('reminder', 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('reminder', 'user');
            sc_send_sms($invoice->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'reminder');
        }
    }

    // Send SMS to admin
    if (sc_is_sms_enabled_for('reminder', 'admin')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if (!empty($admin_phone)) {
            $template = sc_get_sms_template('reminder', 'admin');
            if (!empty($template)) {
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = sc_get_sms_pattern('reminder', 'admin');
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'reminder');
            }
        }
    }
}

/**
 * Send SMS notification for absence
 */
function sc_send_absence_sms($attendance_id) {
    global $wpdb;
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';

    // Get attendance details
    $attendance = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, m.first_name, m.last_name, m.player_phone, c.title as course_title
         FROM $attendances_table a
         LEFT JOIN $members_table m ON a.member_id = m.id
         LEFT JOIN $courses_table c ON a.course_id = c.id
         WHERE a.id = %d AND a.status = 'absent'",
        $attendance_id
    ));

    if (!$attendance || empty($attendance->player_phone)) {
        sc_log_sms('DEBUG', 'Attendance or phone missing', ['attendance_id' => $attendance_id, 'attendance' => $attendance]);
        return;
    }

    // Check if SMS has already been sent for this absence
    if ($attendance->absence_sms_sent == 1) {
        sc_log_sms('DEBUG', 'SMS already sent for this absence', ['attendance_id' => $attendance_id]);
        return;
    }

    $user_name = trim($attendance->first_name . ' ' . $attendance->last_name);
    $course_name = $attendance->course_title ?: 'دوره';
    $date = sc_date_shamsi_date_only($attendance->attendance_date);

    $variables = [
        'user_name' => $user_name,
        'course_name' => $course_name,
        'event_name' => '', // Absence is for courses
        'item_name' => $course_name, // For absence, item_name is the course
        'expense_name' => '',
        'date' => $date
    ];

    // Send SMS to user
    $sms_sent_successfully = false;
    if (sc_is_sms_enabled_for('absence', 'user')) {
        $template = sc_get_sms_template('absence', 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('absence', 'user');
            $result = sc_send_sms($attendance->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'absence');
            if ($result['success']) {
                $sms_sent_successfully = true;
            }
        }
    }

    // Send SMS to admin
    if (sc_is_sms_enabled_for('absence', 'admin')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if (!empty($admin_phone)) {
            $template = sc_get_sms_template('absence', 'admin');
            if (!empty($template)) {
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = sc_get_sms_pattern('absence', 'admin');
                $result = sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'absence');
                if ($result['success']) {
                    $sms_sent_successfully = true;
                }
            }
        }
    }

    // If any SMS was sent successfully, mark as sent
    if ($sms_sent_successfully) {
        $wpdb->update(
            $attendances_table,
            ['absence_sms_sent' => 1],
            ['id' => $attendance_id],
            ['%d'],
            ['%d']
        );
    }
}

// Hook for invoice creation
add_action('sc_invoice_created', 'sc_send_invoice_sms', 10, 1);
add_action('sc_invoice_paid', function($invoice_id) {
    sc_send_invoice_action_sms($invoice_id, 'invoice_paid');
}, 10, 1);

// Hook for course enrollment success (after payment)
add_action('sc_course_enrolled_success', 'sc_send_enrollment_success_sms', 10, 1);


// Hook for payment reminder
add_action('sc_payment_reminder', 'sc_send_payment_reminder_sms', 10, 1);

// Hook for absence
add_action('sc_attendance_absent', 'sc_send_absence_sms', 10, 1);

// Hook for penalty applied
add_action('sc_penalty_applied', 'sc_send_penalty_sms', 10, 1);

// Initialize SMS settings on plugin load
add_action('admin_init', 'sc_initialize_sms_settings');

/**
 * Send SMS for penalty applied
 */
function sc_send_penalty_sms($invoice_id) {
    // This will be handled by payment reminder SMS
    sc_send_payment_reminder_sms($invoice_id);
}

/**
 * Send SMS + notification when identity verification is approved by admin
 */
function sc_send_identity_verified_notifications($member_id) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, first_name, last_name, player_phone FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        return;
    }

    $user_name = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
    if ($user_name === '') {
        $user_name = 'کاربر گرامی';
    }

    $variables = [
        'user_name' => $user_name,
    ];

    // Use SMS settings template for notification / Bale / SMS (single source of truth)
    $template = '';
    if (function_exists('sc_get_sms_template')) {
        $template = (string) sc_get_sms_template('identity_verified', 'user');
    }
    if ($template === '') {
        $template = 'کاربر گرامی %user_name%، احراز هویت شما تأیید شد.';
    }
    $verified_message = function_exists('sc_replace_sms_variables')
        ? sc_replace_sms_variables($template, $variables)
        : str_replace('%user_name%', $user_name, $template);

    // In-app notification (no SMS from notification — SMS is sent separately below)
    if (function_exists('sc_save_notification')) {
        sc_save_notification([
            'title' => 'تایید احراز هویت',
            'content' => $verified_message,
            'target_type' => 'specific',
            'target_config' => [
                'recipient_ids' => ['member_' . (int) $member->id]
            ],
            'notification_type' => 'system',
            'send_sms' => 0
        ]);
    }

    if (function_exists('sc_bale_notify_user')) {
        sc_bale_notify_user($member->id, '', $verified_message);
    }

    // SMS (configurable from settings)
    if (!function_exists('sc_is_sms_enabled_for') || !function_exists('sc_send_sms')) {
        return;
    }

    if (sc_is_sms_enabled_for('identity_verified', 'user') && !empty($member->player_phone)) {
        $pattern_code = function_exists('sc_get_sms_pattern')
            ? sc_get_sms_pattern('identity_verified', 'user')
            : null;
        sc_send_sms($member->player_phone, $verified_message, !empty($pattern_code), $pattern_code, $variables, 'identity_verified');
    }
}

/**
 * Initialize SMS settings defaults
 */
function sc_initialize_sms_settings() {
    $defaults = sc_get_sms_settings_defaults();

    foreach ($defaults as $key => $value) {
        $current = sc_get_setting($key, null);
        if ($current === null) {
            sc_update_setting($key, $value, 'sms');
            continue;
        }
        if ($current === '' && $value !== '' && strpos($key, '_template') !== false) {
            sc_update_setting($key, $value, 'sms');
        }
    }
}

/**
 * Get reminder delay in minutes
 */
function sc_get_reminder_delay_minutes() {
    return (int)sc_get_setting('sms_reminder_delay_minutes', '4320');
}

/**
 * Send SMS after survey submission
 */
function sc_send_survey_submission_sms($response_id) {
    if (!function_exists('sc_is_sms_enabled_for') || !sc_is_pro_feature_sms_enabled()) {
        return;
    }
    global $wpdb;
    $responses_table = $wpdb->prefix . 'sc_survey_responses';
    $surveys_table = $wpdb->prefix . 'sc_surveys';
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $response = $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, s.title AS survey_title
         FROM $responses_table r
         INNER JOIN $surveys_table s ON s.id = r.survey_id
         WHERE r.id = %d",
        absint($response_id)
    ));
    if (!$response) {
        return;
    }

    $phone = '';
    $user_name = 'کاربر';
    if ($response->member_id) {
        $member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, player_phone FROM $members_table WHERE id = %d", (int) $response->member_id));
        if ($member) {
            $phone = $member->player_phone ?? '';
            $user_name = trim($member->first_name . ' ' . $member->last_name);
        }
    } elseif ($response->user_id) {
        $coach = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, mobile_phone FROM $coaches_table WHERE user_id = %d LIMIT 1", (int) $response->user_id));
        if ($coach) {
            $phone = $coach->mobile_phone ?? '';
            $user_name = trim($coach->first_name . ' ' . $coach->last_name);
        }
    }

    $variables = [
        'user_name' => $user_name,
        'survey_title' => $response->survey_title,
    ];

    if ($phone && sc_is_sms_enabled_for('survey_submission', 'user')) {
        $message = sc_get_sms_template('survey_submission', 'user');
        $pattern_code = sc_get_sms_pattern('survey_submission', 'user');
        $message = sc_replace_sms_variables($message, $variables);
        sc_send_sms($phone, $message, !empty($pattern_code), $pattern_code, $variables, 'survey_submission');
    }

    if (sc_is_sms_enabled_for('survey_submission', 'admin')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if ($admin_phone) {
            $message = sc_get_sms_template('survey_submission', 'admin');
            $pattern_code = sc_get_sms_pattern('survey_submission', 'admin');
            $message = sc_replace_sms_variables($message, $variables);
            sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'survey_submission');
        }
    }
}

/**
 * Get internal invoice ID linked to a WooCommerce order (course/event/wallet).
 */
function sc_get_invoice_id_by_wc_order_id($order_id) {
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_invoices WHERE woocommerce_order_id = %d LIMIT 1",
        (int) $order_id
    ));
    return $id ? (int) $id : 0;
}

/**
 * Check if order contains at least one virtual or downloadable product.
 */
function sc_wc_order_has_virtual_or_downloadable_items($order) {
    if (!$order || !method_exists($order, 'get_items')) {
        return false;
    }
    foreach ($order->get_items() as $item) {
        if (!is_a($item, 'WC_Order_Item_Product')) {
            continue;
        }
        $product = $item->get_product();
        if ($product && ($product->is_virtual() || $product->is_downloadable())) {
            return true;
        }
    }
    return false;
}

/**
 * Get comma-separated names of virtual/downloadable products in an order.
 */
function sc_wc_order_get_virtual_product_names($order) {
    $names = [];
    if (!$order || !method_exists($order, 'get_items')) {
        return '';
    }
    foreach ($order->get_items() as $item) {
        if (!is_a($item, 'WC_Order_Item_Product')) {
            continue;
        }
        $product = $item->get_product();
        if ($product && ($product->is_virtual() || $product->is_downloadable())) {
            $names[] = $item->get_name();
        }
    }
    return implode('، ', $names);
}

/**
 * Build SMS variables array for an invoice record.
 */
function sc_build_invoice_sms_variables($invoice) {
    $user_name = trim(($invoice->first_name ?? '') . ' ' . ($invoice->last_name ?? ''));

    $item_name = '';
    if (!empty($invoice->course_title)) {
        $item_name = $invoice->course_title;
    } elseif (!empty($invoice->event_name)) {
        $item_name = $invoice->event_name;
    } elseif (!empty($invoice->expense_name)) {
        $item_name = $invoice->expense_name;
    } else {
        $item_name = 'صورت حساب';
    }

    $amount = number_format((float) $invoice->amount + (float) $invoice->penalty_amount, 0, '.', ',');
    $due_date_timestamp = strtotime($invoice->created_at . ' +7 days');
    $due_date = function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only(date('Y-m-d', $due_date_timestamp))
        : date('Y-m-d', $due_date_timestamp);

    return [
        'user_name' => $user_name,
        'course_name' => $invoice->course_title ?? '',
        'event_name' => $invoice->event_name ?? '',
        'item_name' => $item_name,
        'expense_name' => $invoice->expense_name ?? '',
        'amount' => $amount,
        'due_date' => $due_date,
    ];
}

/**
 * Fetch invoice row with member/course/event details for SMS.
 */
function sc_get_invoice_for_sms($invoice_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $events_table = $wpdb->prefix . 'sc_events';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, m.first_name, m.last_name, m.player_phone,
                c.title as course_title, c.price as course_price,
                e.name as event_name, e.price as event_price
         FROM $invoices_table i
         LEFT JOIN $members_table m ON i.member_id = m.id
         LEFT JOIN $courses_table c ON i.course_id = c.id
         LEFT JOIN $events_table e ON i.event_id = e.id
         WHERE i.id = %d",
        (int) $invoice_id
    ));
}

/**
 * Send invoice SMS for a specific action (invoice, invoice_cancelled, invoice_onhold, ...).
 */
function sc_send_invoice_action_sms($invoice_id, $action) {
    $invoice = sc_get_invoice_for_sms($invoice_id);
    if (!$invoice || empty($invoice->player_phone)) {
        return;
    }

    $variables = sc_build_invoice_sms_variables($invoice);

    if (sc_is_sms_enabled_for($action, 'user')) {
        $template = sc_get_sms_template($action, 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern($action, 'user');
            sc_send_sms($invoice->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, $action);
        }
    }

    if (sc_is_sms_enabled_for($action, 'admin')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if (!empty($admin_phone)) {
            $template = sc_get_sms_template($action, 'admin');
            if (!empty($template)) {
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = sc_get_sms_pattern($action, 'admin');
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, $action);
            }
        }
    }
}

/**
 * Send invoice status SMS when linked WooCommerce order status changes.
 */
function sc_send_invoice_sms_on_wc_order_status($order_id, $old_status, $new_status, $order) {
    if ($old_status === $new_status) {
        return;
    }

    $invoice_id = sc_get_invoice_id_by_wc_order_id($order_id);
    if (!$invoice_id) {
        return;
    }

    // پرداخت موفق فقط از هوک sc_invoice_paid پیامک می‌گیرد (جلوگیری از ارسال تکراری)
    $action_map = [
        'cancelled' => 'invoice_cancelled',
        'on-hold' => 'invoice_onhold',
        'pending' => 'invoice_onhold',
    ];

    $action = $action_map[$new_status] ?? null;
    if (!$action) {
        return;
    }

    sc_send_invoice_action_sms($invoice_id, $action);
}

/**
 * Send SMS for WooCommerce shop product orders (not course/event invoices).
 */
function sc_send_wc_order_status_sms($order_id, $status) {
    if (!class_exists('WC_Order')) {
        return;
    }

    // Course/event/wallet invoices use invoice SMS templates, not shop product SMS.
    if (sc_get_invoice_id_by_wc_order_id($order_id)) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $user_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    if (trim($user_name) === '') {
        $user_name = $order->get_billing_company() ?: 'کاربر';
    }

    $phone = $order->get_billing_phone();
    if (empty($phone)) {
        $user_id = $order->get_user_id();
        if ($user_id) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
        }
    }
    if (empty($phone)) {
        return;
    }

    $product_names = sc_wc_order_get_virtual_product_names($order);
    $variables = [
        'user_name' => trim($user_name),
        'order_id' => $order->get_order_number(),
        'amount' => number_format($order->get_total(), 0, '.', ','),
        'status' => $status,
        'product_names' => $product_names,
        'item_name' => $product_names ?: '',
    ];

    $is_virtual = sc_wc_order_has_virtual_or_downloadable_items($order);
    $prefix = $is_virtual ? 'wc_order_virtual' : 'wc_order';

    $status_suffix_map = [
        'processing' => 'awaiting_shipment',
        'on-hold' => 'paid',
        'pending' => 'paid',
        'completed' => 'confirmed_shipped',
        'cancelled' => 'cancelled',
        'failed' => 'failed',
    ];

    $suffix = $status_suffix_map[$status] ?? null;
    if (!$suffix) {
        return;
    }

    $action = $prefix . '_' . $suffix;

    if (sc_is_sms_enabled_for($action, 'user')) {
        $template = sc_get_sms_template($action, 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern($action, 'user');
            sc_send_sms($phone, $message, !empty($pattern_code), $pattern_code, $variables, $action);
        }
    }

    if (sc_is_sms_enabled_for($action, 'admin')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if (!empty($admin_phone)) {
            $template = sc_get_sms_template($action, 'admin');
            if (!empty($template)) {
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = sc_get_sms_pattern($action, 'admin');
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, $action);
            }
        }
    }
}

// WooCommerce order status hooks for product orders SMS
add_action('woocommerce_order_status_completed', function($order_id) {
    sc_send_wc_order_status_sms($order_id, 'completed');
}, 10, 1);

add_action('woocommerce_order_status_cancelled', function($order_id) {
    sc_send_wc_order_status_sms($order_id, 'cancelled');
}, 10, 1);

add_action('woocommerce_order_status_failed', function($order_id) {
    sc_send_wc_order_status_sms($order_id, 'failed');
}, 10, 1);

add_action('woocommerce_order_status_on-hold', function($order_id) {
    sc_send_wc_order_status_sms($order_id, 'on-hold');
}, 10, 1);

add_action('woocommerce_order_status_pending', function($order_id) {
    sc_send_wc_order_status_sms($order_id, 'pending');
}, 10, 1);

// Invoice SMS when linked WC order status changes (course/event/wallet)
add_action('woocommerce_order_status_changed', 'sc_send_invoice_sms_on_wc_order_status', 25, 4);

/**
 * Send SMS + notification when identity is rejected
 */
function sc_send_identity_rejected_notifications($member_id, $reason) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, first_name, last_name, player_phone FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        return;
    }

    $user_name = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
    if ($user_name === '') {
        $user_name = 'کاربر گرامی';
    }

    $variables = [
        'user_name' => $user_name,
        'reason' => $reason,
    ];

    $template = '';
    if (function_exists('sc_get_sms_template')) {
        $template = (string) sc_get_sms_template('identity_rejected', 'user');
    }
    if ($template === '') {
        $template = 'کاربر گرامی %user_name%، احراز هویت شما رد شد. علت: %reason%';
    }
    $rejected_message = function_exists('sc_replace_sms_variables')
        ? sc_replace_sms_variables($template, $variables)
        : str_replace(['%user_name%', '%reason%'], [$user_name, $reason], $template);

    // In-app notification (no SMS from notification — SMS is sent separately below)
    if (function_exists('sc_save_notification')) {
        sc_save_notification([
            'title' => 'رد احراز هویت',
            'content' => $rejected_message,
            'target_type' => 'specific',
            'target_config' => [
                'recipient_ids' => ['member_' . (int) $member->id]
            ],
            'notification_type' => 'system',
            'send_sms' => 0
        ]);
    }

    if (function_exists('sc_bale_notify_user')) {
        sc_bale_notify_user($member->id, '', $rejected_message);
    }

    // SMS (if enabled)
    if (!function_exists('sc_is_sms_enabled_for') || !function_exists('sc_send_sms')) {
        return;
    }

    if (sc_is_sms_enabled_for('identity_rejected', 'user') && !empty($member->player_phone)) {
        $pattern_code = function_exists('sc_get_sms_pattern')
            ? sc_get_sms_pattern('identity_rejected', 'user')
            : null;
        sc_send_sms($member->player_phone, $rejected_message, !empty($pattern_code), $pattern_code, $variables, 'identity_rejected');
    }
}

// AJAX handler for rejecting identity
add_action('wp_ajax_sc_reject_identity', 'sc_ajax_reject_identity');
function sc_ajax_reject_identity() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('دسترسی غیرمجاز');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sc_reject_identity')) {
        wp_send_json_error('خطای امنیتی');
    }
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
    if (!$member_id || empty($reason)) {
        wp_send_json_error('اطلاعات ناقص است');
    }

    // Optionally uncheck identity_verified in DB
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $wpdb->update($members_table, ['identity_verified' => 0], ['id' => $member_id], ['%d'], ['%d']);

    // Send notifications
    sc_send_identity_rejected_notifications($member_id, $reason);

    wp_send_json_success(['message' => 'ارسال شد']);
}
