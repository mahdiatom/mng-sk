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
 * AJAX: بررسی وضعیت تحویل یک رکورد لاگ پیامک
 */
add_action('wp_ajax_sc_check_sms_delivery_status', 'sc_ajax_check_sms_delivery_status');
function sc_ajax_check_sms_delivery_status() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sc_check_sms_delivery')) {
        wp_send_json_error(['message' => 'خطای امنیتی.']);
    }
    $log_id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;
    if ($log_id <= 0) {
        wp_send_json_error(['message' => 'شناسه رکورد نامعتبر است.']);
    }
    $result = sc_sms_log_check_delivery_status($log_id);
    if (!empty($result['success'])) {
        wp_send_json_success($result);
    }
    wp_send_json_error(['message' => $result['message'] ?? 'خطا در بررسی وضعیت.']);
}

/**
 * Send SMS via sms.ir API
 * @param string $context بخش مبدا برای لاگ: ticket_new, invoice, enrollment, ...
 */
function sc_send_sms($mobile, $message, $is_pattern = false, $pattern_code = null, $parameters = array(), $context = '') {
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

    return $result;
}

/**
 * Send regular SMS via sms.ir
 */
function sc_send_regular_sms($mobile, $message) {
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
 * Get SMS template for specific action
 */
function sc_get_sms_template($action, $type = 'user') {
    // $type can be 'user' or 'admin'
    $default_templates = [
        'invoice' => [
            'user' => '%user_name% عزیز، صورت حساب جدید برای %item_name% صادر شد. مبلغ %amount% تومان - سررسید: %due_date%',
            'admin' => 'صورت حساب جدید: %user_name% - %item_name% - مبلغ %amount% تومان'
        ],
        'wallet_low_balance' => [
            'user' => '%user_name% عزیز، موجودی کیف پول شما به %balance% تومان رسیده است. لطفاً کیف پول خود را شارژ کنید.',
            'admin' => 'هشدار موجودی کم: %user_name% - موجودی: %balance% تومان'
        ],
        'wallet_negative_balance' => [
            'user' => '%user_name% عزیز، موجودی کیف پول شما منفی شده است (%balance% تومان). لطفاً فوراً کیف پول خود را شارژ کنید.',
            'admin' => 'هشدار موجودی منفی: %user_name% - موجودی: %balance% تومان'
        ],
        'wallet_charge_success' => [
            'user' => '%user_name% عزیز، کیف پول شما به مبلغ %amount% تومان شارژ شد. موجودی فعلی: %balance% تومان.',
            'admin' => 'شارژ کیف پول: %user_name% - مبلغ: %amount% تومان - موجودی: %balance% تومان'
        ],
        'wallet_payment' => [
            'user' => '%user_name% عزیز، مبلغ %amount% تومان از کیف پول شما کسر شد. موجودی فعلی: %balance% تومان.',
            'admin' => 'پرداخت از کیف پول: %user_name% - مبلغ: %amount% تومان - موجودی: %balance% تومان'
        ],
        'enrollment' => [
            'user' => '%user_name% عزیز، ثبت نام شما در %item_name% تکمیل شد.',
            'admin' => 'ثبت نام جدید: %user_name% در %item_name%'
        ],
        'reminder' => [
            'user' => '%user_name% عزیز، صورت حساب %amount% تومان برای %item_name% سر رسیده است. لطفاً پرداخت کنید.',
            'admin' => 'یادآوری پرداخت: %user_name% - %item_name% - مبلغ %amount% تومان'
        ],
        'absence' => [
            'user' => 'کاربر گرامی %user_name%، غیبت شما در جلسه %item_name% مورخ %date% ثبت شد.',
            'admin' => 'غیبت: %user_name% - %item_name% - تاریخ %date%'
        ],
        'absence_alert' => [
            'user' => 'کاربر گرامی %user_name%، تعداد غیبت شما در %item_name% به %absence_count% رسیده است (حد مجاز: %absence_limit%).',
            'admin' => 'هشدار غیبت: %user_name% در %item_name% دارای %absence_count% غیبت است (حد مجاز: %absence_limit%).'
        ],
        'birthday' => [
            'user' => 'کاربر گرامی %user_name%، تولدتان مبارک! باشگاه ورزشی ما این روز را به شما تبریک می‌گوید.'
        ],
        'insurance_expiry' => [
            'user' => 'کاربر گرامی %user_name%، تاریخ انقضای بیمه شما %expiry_date% است. لطفاً نسبت به تمدید اقدام کنید.'
        ],
        'identity_verified' => [
            'user' => 'کاربر گرامی %user_name%، احراز هویت شما تایید شد.'
        ],
        'certificate' => [
            'user' => 'کاربر گرامی %user_name%، یک گواهینامه برای شما صادر شد. لطفا به پنل خود مراجعه کنید.'
        ],
        'course_capacity_waitlist' => [
            'user' => 'کاربر گرامی %user_name%، ظرفیت ثبت‌نام دوره «%item_name%» باز شد. ثبت‌نام: %enroll_url%'
        ],
        'survey_submission' => [
            'user' => 'کاربر گرامی %user_name%، پاسخ شما در نظرسنجی «%survey_title%» با موفقیت ثبت شد.',
            'admin' => 'پاسخ جدید نظرسنجی: %user_name% - %survey_title%'
        ]
    ];

    $default = isset($default_templates[$action][$type]) ? $default_templates[$action][$type] : '';
    return sc_get_setting("sms_{$action}_{$type}_template", $default);
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
    // $type can be 'user' or 'admin'
    return (int)sc_get_setting("sms_{$action}_{$type}_enabled", '0') === 1;
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

    // Calculate due date (7 days from creation) in Shamsi format
    $due_date_timestamp = strtotime($invoice->created_at . ' +7 days');
    $due_date = sc_date_shamsi_date_only(date('Y-m-d', $due_date_timestamp));

    $variables = [
        'user_name' => $user_name,
        'course_name' => $invoice->course_title ?: '',
        'event_name' => $invoice->event_name ?: '',
        'item_name' => $item_name,
        'expense_name' => $invoice->expense_name ?: '',
        'amount' => $amount,
        'due_date' => $due_date
    ];

    // Send SMS to user
    if (sc_is_sms_enabled_for('invoice', 'user')) {
        $template = sc_get_sms_template('invoice', 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('invoice', 'user');
            sc_send_sms($invoice->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'invoice');
        }
        $bot_id = sc_get_user_bale_chat_id($invoice->id);
        if($bot_id > 0 && function_exists('bale_send_message') ){
            bale_send_message($bot_id ,$message );
        }elseif($bot_id === null && function_exists('sc_bale_send_by_phone')){
            sc_bale_send_by_phone(sc_convert_phone_to_98($invoice->player_phone) , $message);
        }
    }

    // Send SMS to admin
    if (sc_is_sms_enabled_for('invoice', 'admin')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if (!empty($admin_phone)) {
            $template = sc_get_sms_template('invoice', 'admin');
            if (!empty($template)) {
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = sc_get_sms_pattern('invoice', 'admin');
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'invoice');
                
            }
        }
    }
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

            $bot_id = sc_get_user_bale_chat_id($enrollment->member_id);
            if($bot_id > 0 && function_exists('bale_send_message') ){
                bale_send_message($bot_id ,$message );
            }elseif($bot_id === null && function_exists('sc_bale_send_by_phone')){
                sc_bale_send_by_phone(sc_convert_phone_to_98($enrollment->player_phone) , $message);
            }
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
            $bot_id = sc_get_user_bale_chat_id($invoice->id);
            if($bot_id > 0 && function_exists('bale_send_message') ){
                bale_send_message($bot_id ,$message );
            }elseif($bot_id === null && function_exists('sc_bale_send_by_phone')){
                sc_bale_send_by_phone(sc_convert_phone_to_98($invoice->player_phone) , $message);
            }
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
            $bot_id = sc_get_user_bale_chat_id($attendance->member_id);
            if($bot_id > 0 && function_exists('bale_send_message') ){
                bale_send_message($bot_id ,$message );
            }elseif($bot_id === null && function_exists('sc_bale_send_by_phone')){
                sc_bale_send_by_phone(sc_convert_phone_to_98($attendance->member_id) , $message);
            }
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
 * Send SMS + fixed notification when identity verification is approved by admin
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

    // Fixed in-app notification text
    if (function_exists('sc_save_notification')) {
        sc_save_notification([
            'title' => 'تایید احراز هویت',
            'content' => 'کاربر گرامی .... احراز هویت شما تایید شد.',
            'target_type' => 'specific',
            'target_config' => [
                'recipient_ids' => ['member_' . (int) $member->id]
            ],
            'notification_type' => 'system',
            'send_sms' => 0
        ]);
    }

    // SMS (configurable from settings)
    if (!function_exists('sc_is_sms_enabled_for') || !function_exists('sc_get_sms_template') || !function_exists('sc_send_sms')) {
        return;
    }

    if (sc_is_sms_enabled_for('identity_verified', 'user') && !empty($member->player_phone)) {
        $template = sc_get_sms_template('identity_verified', 'user');
        if (!empty($template)) {
            $variables = [
                'user_name' => $user_name
            ];
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('identity_verified', 'user');
            sc_send_sms($member->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'identity_verified');
        }
    }
}

/**
 * Initialize SMS settings defaults
 */
function sc_initialize_sms_settings() {
    $defaults = [
        // API Settings
        'sms_api_key' => '',
        'sms_sender' => '',
        'sms_admin_phone' => '',
        'sms_reminder_delay_minutes' => '4320', // 3 days in minutes

        // Invoice SMS - User
        'sms_invoice_user_enabled' => '1',
        'sms_invoice_user_template' => 'کاربر گرامی %user_name%، صورت حساب %item_name% به مبلغ %amount% تومان ایجاد شد. مهلت پرداخت: %due_date%',
        'sms_invoice_user_pattern' => '',

        // Invoice SMS - Admin
        'sms_invoice_admin_enabled' => '1',
        'sms_invoice_admin_template' => 'صورت حساب جدید: %user_name% - %item_name% - مبلغ %amount% تومان',
        'sms_invoice_admin_pattern' => '',

        // Enrollment SMS - User
        'sms_enrollment_user_enabled' => '1',
        'sms_enrollment_user_template' => 'کاربر گرامی %user_name%، ثبت نام شما در %item_name% با موفقیت انجام شد.',
        'sms_enrollment_user_pattern' => '',

        // Enrollment SMS - Admin
        'sms_enrollment_admin_enabled' => '1',
        'sms_enrollment_admin_template' => 'ثبت نام جدید: %user_name% در %item_name%',
        'sms_enrollment_admin_pattern' => '',

        // Reminder SMS - User
        'sms_reminder_user_enabled' => '1',
        'sms_reminder_user_template' => 'کاربر گرامی %user_name%، صورت حساب %item_name% به مبلغ %amount% تومان پرداخت نشده است. در صورت تأخیر شامل جریمه %penalty_amount% تومان می‌شود.',
        'sms_reminder_user_pattern' => '',

        // Reminder SMS - Admin
        'sms_reminder_admin_enabled' => '1',
        'sms_reminder_admin_template' => 'یادآوری پرداخت: %user_name% - %item_name% - مبلغ %amount% تومان',
        'sms_reminder_admin_pattern' => '',

        // Absence SMS - User
        'sms_absence_user_enabled' => '1',
        'sms_absence_user_template' => 'کاربر گرامی %user_name%، غیبت شما در جلسه %item_name% مورخ %date% ثبت شد.',
        'sms_absence_user_pattern' => '',

        // Absence SMS - Admin
        'sms_absence_admin_enabled' => '1',
        'sms_absence_admin_template' => 'غیبت: %user_name% - %item_name% - تاریخ %date%',
        'sms_absence_admin_pattern' => '',
        'sms_absence_alert_user_enabled' => '0',
        'sms_absence_alert_user_template' => 'کاربر گرامی %user_name%، تعداد غیبت شما در %item_name% به %absence_count% رسیده است (حد مجاز: %absence_limit%).',
        'sms_absence_alert_user_pattern' => '',
        'sms_absence_alert_admin_enabled' => '0',
        'sms_absence_alert_admin_template' => 'هشدار غیبت: %user_name% در %item_name% دارای %absence_count% غیبت است (حد مجاز: %absence_limit%).',
        'sms_absence_alert_admin_pattern' => '',

        // Reminder Settings
        //'sms_reminder_delay_minutes' => '4320', // 3 days in minutes
        'sms_identity_verified_user_enabled' => '1',
        'sms_identity_verified_user_template' => 'کاربر گرامی %user_name%، احراز هویت شما تایید شد.',
        'sms_identity_verified_user_pattern' => '',
        'sms_certificate_user_enabled' => '1',
        'sms_certificate_user_template' => 'کاربر گرامی %user_name%، یک گواهینامه برای شما صادر شد. لطفا به پنل خود مراجعه کنید.',
        'sms_certificate_user_pattern' => '',
        'sms_course_capacity_waitlist_user_enabled' => '0',
        'sms_course_capacity_waitlist_user_template' => 'کاربر گرامی %user_name%، ظرفیت ثبت‌نام دوره «%item_name%» باز شد. ثبت‌نام: %enroll_url%',
        'sms_course_capacity_waitlist_user_pattern' => '',
    ];

    foreach ($defaults as $key => $value) {
        if (sc_get_setting($key, null) === null) {
            sc_update_setting($key, $value, 'sms');
            error_log("SC SMS: Initialized setting $key = $value");
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
