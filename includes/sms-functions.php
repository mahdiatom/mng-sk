<?php
/**
 * SMS Functions for SportClub Manager
 * Integration with sms.ir API
 */

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
 * Send SMS via sms.ir API
 */
function sc_send_sms($mobile, $message, $is_pattern = false, $pattern_code = null, $parameters = array()) {
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

    // Log the result
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

    // If it starts with 9 (without 0), add 0
    if (preg_match('/^9\d{8}$/', $mobile)) {
        return '0' . $mobile;
    }

    return false;
}

/**
 * Log SMS activities
 */
function sc_log_sms($status, $message, $data = array()) {
    $log_file = WP_CONTENT_DIR . '/sc-sms-log.txt';
    $timestamp = current_time('Y-m-d H:i:s');

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
}

/**
 * Get SMS template for specific action
 */
function sc_get_sms_template($action, $type = 'user') {
    // $type can be 'user' or 'admin'
    return sc_get_setting("sms_{$action}_{$type}_template", '');
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

    // Get invoice details
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, m.first_name, m.last_name, m.player_phone, c.title as course_title, c.price
         FROM $invoices_table i
         LEFT JOIN $members_table m ON i.member_id = m.id
         LEFT JOIN $courses_table c ON i.course_id = c.id
         WHERE i.id = %d",
        $invoice_id
    ));

    if (!$invoice || empty($invoice->player_phone)) {
        return;
    }

    $user_name = trim($invoice->first_name . ' ' . $invoice->last_name);
    $course_name = $invoice->course_title ?: 'دوره';
    $amount = number_format($invoice->amount + $invoice->penalty_amount, 0, '.', ',');
    $due_date = date('Y/m/d', strtotime($invoice->created_at . ' +7 days'));

    $variables = [
        'user_name' => $user_name,
        'course_name' => $course_name,
        'amount' => $amount,
        'due_date' => $due_date
    ];

    // Send SMS to user
    if (sc_is_sms_enabled_for('invoice', 'user')) {
        $template = sc_get_sms_template('invoice', 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('invoice', 'user');
            sc_send_sms($invoice->player_phone, $message, !empty($pattern_code), $pattern_code, $variables);
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
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables);
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
            $result = sc_send_sms($enrollment->player_phone, $message, !empty($pattern_code), $pattern_code, $variables);

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
                $result = sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables);

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

    // Get invoice details
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, m.first_name, m.last_name, m.player_phone, c.title as course_title
         FROM $invoices_table i
         LEFT JOIN $members_table m ON i.member_id = m.id
         LEFT JOIN $courses_table c ON i.course_id = c.id
         WHERE i.id = %d",
        $invoice_id
    ));

    if (!$invoice || empty($invoice->player_phone)) {
        return;
    }

    $user_name = trim($invoice->first_name . ' ' . $invoice->last_name);
    $course_name = $invoice->course_title ?: 'دوره';
    $amount = number_format($invoice->amount + $invoice->penalty_amount, 0, '.', ',');
    $penalty_amount = number_format($invoice->penalty_amount, 0, '.', ',');

    $variables = [
        'user_name' => $user_name,
        'course_name' => $course_name,
        'amount' => $amount,
        'penalty_amount' => $penalty_amount
    ];

    // Send SMS to user
    if (sc_is_sms_enabled_for('reminder', 'user')) {
        $template = sc_get_sms_template('reminder', 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('reminder', 'user');
            sc_send_sms($invoice->player_phone, $message, !empty($pattern_code), $pattern_code, $variables);
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
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables);
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

    // Only send SMS for new records or first time absence
    // Check if this record was created/updated recently (within last 5 minutes)
    $updated_time = strtotime($attendance->updated_at);
    $current_time = current_time('timestamp');
    $time_diff = $current_time - $updated_time;

    sc_log_sms('DEBUG', 'Attendance SMS check', [
        'attendance_id' => $attendance_id,
        'status' => $attendance->status,
        'player_phone' => $attendance->player_phone,
        'updated_at' => $attendance->updated_at,
        'time_diff' => $time_diff,
        'will_send' => ($time_diff <= 300)
    ]);

    // If updated more than 5 minutes ago, it's probably an old update, don't send SMS
    if ($time_diff > 300) {
        return;
    }

    $user_name = trim($attendance->first_name . ' ' . $attendance->last_name);
    $course_name = $attendance->course_title ?: 'دوره';
    $date = date('Y/m/d', strtotime($attendance->attendance_date));

    $variables = [
        'user_name' => $user_name,
        'course_name' => $course_name,
        'date' => $date
    ];

    // Send SMS to user
    if (sc_is_sms_enabled_for('absence', 'user')) {
        $template = sc_get_sms_template('absence', 'user');
        if (!empty($template)) {
            $message = sc_replace_sms_variables($template, $variables);
            $pattern_code = sc_get_sms_pattern('absence', 'user');
            sc_send_sms($attendance->player_phone, $message, !empty($pattern_code), $pattern_code, $variables);
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
                sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables);
            }
        }
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
        'sms_invoice_user_template' => 'کاربر گرامی %user_name%، صورت حساب دوره %course_name% به مبلغ %amount% تومان ایجاد شد. مهلت پرداخت: %due_date%',
        'sms_invoice_user_pattern' => '',

        // Invoice SMS - Admin
        'sms_invoice_admin_enabled' => '1',
        'sms_invoice_admin_template' => 'صورت حساب جدید: %user_name% - دوره %course_name% - مبلغ %amount% تومان',
        'sms_invoice_admin_pattern' => '',

        // Enrollment SMS - User
        'sms_enrollment_user_enabled' => '1',
        'sms_enrollment_user_template' => 'کاربر گرامی %user_name%، ثبت نام شما در دوره %course_name% با موفقیت انجام شد.',
        'sms_enrollment_user_pattern' => '',

        // Enrollment SMS - Admin
        'sms_enrollment_admin_enabled' => '1',
        'sms_enrollment_admin_template' => 'ثبت نام جدید: %user_name% در دوره %course_name%',
        'sms_enrollment_admin_pattern' => '',

        // Reminder SMS - User
        'sms_reminder_user_enabled' => '1',
        'sms_reminder_user_template' => 'کاربر گرامی %user_name%، صورت حساب دوره %course_name% به مبلغ %amount% تومان پرداخت نشده است. در صورت تأخیر شامل جریمه %penalty_amount% تومان می‌شود.',
        'sms_reminder_user_pattern' => '',

        // Reminder SMS - Admin
        'sms_reminder_admin_enabled' => '1',
        'sms_reminder_admin_template' => 'یادآوری پرداخت: %user_name% - دوره %course_name% - مبلغ %amount% تومان',
        'sms_reminder_admin_pattern' => '',

        // Absence SMS - User
        'sms_absence_user_enabled' => '1',
        'sms_absence_user_template' => 'کاربر گرامی %user_name%، غیبت شما در جلسه دوره %course_name% مورخ %date% ثبت شد.',
        'sms_absence_user_pattern' => '',

        // Absence SMS - Admin
        'sms_absence_admin_enabled' => '1',
        'sms_absence_admin_template' => 'غیبت: %user_name% - دوره %course_name% - تاریخ %date%',
        'sms_absence_admin_pattern' => '',

        // Reminder Settings
        'sms_reminder_delay_minutes' => '4320', // 3 days in minutes
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
