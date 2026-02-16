<?php
/**
 * Login & Register (SMS OTP + password) – SportClub Manager
 * ورود و عضویت با شماره موبایل، رمز عبور و کد یکبارمصرف
 */

if (!defined('ABSPATH')) {
    exit;
}

/** OTP expiry seconds */
define('SC_OTP_EXPIRY', 120);

/** Max failed login attempts before lock */
define('SC_LOGIN_MAX_ATTEMPTS', 5);

/** Lock duration in seconds (3 minutes) */
define('SC_LOGIN_LOCK_DURATION', 180);

/**
 * Get WP user by mobile (user_login or billing_phone or sc_members.player_phone)
 */
function sc_get_user_by_phone($phone) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return null;
    }

    $user = get_user_by('login', $mobile);
    if ($user) {
        return $user;
    }

    $users = get_users([
        'meta_key'   => 'billing_phone',
        'meta_value' => $mobile,
        'number'     => 1,
    ]);
    if (!empty($users)) {
        return $users[0];
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $members_table)) !== $members_table) {
        return null;
    }
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE player_phone = %s AND user_id IS NOT NULL LIMIT 1",
        $mobile
    ));
    if ($user_id) {
        return get_userdata((int) $user_id);
    }

    return null;
}

/**
 * Check if phone is registered (has WP user linked)
 */
function sc_login_register_user_exists($phone) {
    return sc_get_user_by_phone($phone) !== null;
}

/**
 * Generate 5-digit OTP and store in transient
 */
function sc_login_register_set_otp($phone) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return null;
    }
    $code = (string) wp_rand(10000, 99999);
    set_transient('sc_otp_' . $mobile, ['code' => $code, 'at' => time()], SC_OTP_EXPIRY);
    return $code;
}

/**
 * Verify OTP and clear transient on success
 */
function sc_login_register_verify_otp($phone, $code) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return false;
    }
    $stored = get_transient('sc_otp_' . $mobile);
    if (!$stored || !isset($stored['code']) || (string) $stored['code'] !== (string) $code) {
        return false;
    }
    delete_transient('sc_otp_' . $mobile);
    return true;
}

/**
 * Check if login is locked for this phone (too many failed attempts)
 */
function sc_login_register_is_locked($phone) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile) {
        return true;
    }
    $lock_until = get_transient('sc_login_lock_' . $mobile);
    return $lock_until && (int) $lock_until > time();
}

/**
 * Record failed login attempt; lock if over limit
 */
function sc_login_register_record_failed($phone) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile) {
        return;
    }
    $key = 'sc_login_attempts_' . $mobile;
    $count = (int) get_transient($key);
    $count++;
    set_transient($key, $count, SC_LOGIN_LOCK_DURATION);
    if ($count >= SC_LOGIN_MAX_ATTEMPTS) {
        set_transient('sc_login_lock_' . $mobile, time() + SC_LOGIN_LOCK_DURATION, SC_LOGIN_LOCK_DURATION);
        delete_transient($key);
    }
}

/**
 * Clear failed attempts on successful login
 */
function sc_login_register_clear_attempts($phone) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if ($mobile) {
        delete_transient('sc_login_attempts_' . $mobile);
        delete_transient('sc_login_lock_' . $mobile);
    }
}

/**
 * Send OTP SMS using pattern from settings
 */
function sc_login_register_send_otp_sms($phone, $code) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !function_exists('sc_send_pattern_sms')) {
        return ['success' => false, 'message' => 'تنظیمات پیامک یا شماره معتبر نیست.'];
    }
    $pattern_code = (int) sc_get_setting('sc_login_otp_pattern', '');
    if ($pattern_code < 1) {
        return ['success' => false, 'message' => 'کد پترن ورود/عضویت در تنظیمات ثبت نشده است.'];
    }
    $result = sc_send_pattern_sms($mobile, $pattern_code, ['Code' => $code]);
    return $result;
}

/**
 * Get redirect URL after login (from settings or default)
 */
function sc_login_register_redirect_url() {
    $path = sc_get_setting('sc_login_redirect_path', 'my-account/sc-submit-documents/');
    $path = ltrim($path, '/');
    return home_url('/' . $path);
}

/**
 * Create WP user and let plugin create member (display_name + billing_phone)
 */
function sc_login_register_create_user($phone, $first_name, $last_name, $password) {
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['success' => false, 'message' => 'شماره موبایل معتبر نیست.'];
    }

    if (sc_get_user_by_phone($mobile)) {
        return ['success' => false, 'message' => 'این شماره قبلاً ثبت شده است.'];
    }

    $first_name = sanitize_text_field($first_name);
    $last_name  = sanitize_text_field($last_name);
    if (strlen($first_name) < 2 || strlen($last_name) < 2) {
        return ['success' => false, 'message' => 'نام و نام خانوادگی را صحیح وارد کنید.'];
    }

    $login   = $mobile;
    $email   = $mobile . '@gmail.com';
    $display = trim($first_name . ' ' . $last_name);

    $user_id = wp_insert_user([
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => $password,
        'display_name' => $display,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'role'         => 'customer',
    ]);
    if (is_wp_error($user_id)) {
        return ['success' => false, 'message' => $user_id->get_error_message()];
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return ['success' => false, 'message' => 'خطا در ایجاد کاربر.'];
    }

    update_user_meta($user_id, 'billing_phone', $mobile);

    sc_check_and_create_tables();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    if ($member) {
        $wpdb->update(
            $members_table,
            [
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'player_phone' => $mobile,
                'updated_at'  => current_time('mysql'),
            ],
            ['id' => $member->id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    return ['success' => true, 'user_id' => $user_id];
}

// ----- AJAX handlers (nopriv + logged in for redirect) -----

add_action('wp_ajax_sc_login_register_check_phone', 'sc_ajax_login_register_check_phone');
add_action('wp_ajax_nopriv_sc_login_register_check_phone', 'sc_ajax_login_register_check_phone');
function sc_ajax_login_register_check_phone() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_success(['exists' => false, 'message' => 'شماره موبایل معتبر وارد کنید.']);
    }
    $exists = sc_login_register_user_exists($mobile);
    wp_send_json_success(['exists' => $exists]);
}

add_action('wp_ajax_sc_login_register_send_otp', 'sc_ajax_login_register_send_otp');
add_action('wp_ajax_nopriv_sc_login_register_send_otp', 'sc_ajax_login_register_send_otp');
function sc_ajax_login_register_send_otp() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }
    if (!sc_login_register_user_exists($mobile)) {
        wp_send_json_error(['message' => 'ابتدا ثبت‌نام کنید.']);
    }
    $code = sc_login_register_set_otp($mobile);
    if (!$code) {
        wp_send_json_error(['message' => 'خطا در ایجاد کد.']);
    }
    $send = sc_login_register_send_otp_sms($mobile, $code);
    if (empty($send['success'])) {
        wp_send_json_error(['message' => isset($send['message']) ? $send['message'] : 'خطا در ارسال پیامک.']);
    }
    wp_send_json_success(['message' => 'کد تأیید ارسال شد.']);
}

add_action('wp_ajax_sc_login_register_verify_otp', 'sc_ajax_login_register_verify_otp');
add_action('wp_ajax_nopriv_sc_login_register_verify_otp', 'sc_ajax_login_register_verify_otp');
function sc_ajax_login_register_verify_otp() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $code  = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }
    if (!sc_login_register_verify_otp($mobile, $code)) {
        wp_send_json_error(['message' => 'کد تأیید نادرست یا منقضی است.']);
    }
    $user = sc_get_user_by_phone($mobile);
    if (!$user) {
        wp_send_json_error(['message' => 'کاربر یافت نشد.']);
    }
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    sc_login_register_clear_attempts($mobile);
    wp_send_json_success(['redirect' => sc_login_register_redirect_url()]);
}

add_action('wp_ajax_sc_login_register_login_password', 'sc_ajax_login_register_login_password');
add_action('wp_ajax_nopriv_sc_login_register_login_password', 'sc_ajax_login_register_login_password');
function sc_ajax_login_register_login_password() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone    = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $mobile   = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }
    if (sc_login_register_is_locked($mobile)) {
        wp_send_json_error(['message' => 'به دلیل تلاش‌های ناموفق، به مدت ۳ دقیقه امکان ورود با رمز وجود ندارد. از ارسال کد یکبارمصرف استفاده کنید.']);
    }
    $user = sc_get_user_by_phone($mobile);
    if (!$user) {
        sc_login_register_record_failed($mobile);
        wp_send_json_error(['message' => 'کاربری با این شماره یافت نشد.']);
    }
    $auth = wp_authenticate($user->user_login, $password);
    if (is_wp_error($auth)) {
        sc_login_register_record_failed($mobile);
        wp_send_json_error(['message' => 'رمز عبور اشتباه است.']);
    }
    wp_clear_auth_cookie();
    wp_set_current_user($auth->ID);
    wp_set_auth_cookie($auth->ID, true);
    sc_login_register_clear_attempts($mobile);
    wp_send_json_success(['redirect' => sc_login_register_redirect_url()]);
}

add_action('wp_ajax_sc_login_register_register', 'sc_ajax_login_register_register');
add_action('wp_ajax_nopriv_sc_login_register_register', 'sc_ajax_login_register_register');
function sc_ajax_login_register_register() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone      = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name  = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
    $password   = isset($_POST['password']) ? $_POST['password'] : '';
    if (strlen($password) < 6) {
        wp_send_json_error(['message' => 'رمز عبور حداقل ۶ کاراکتر باشد.']);
    }
    $create = sc_login_register_create_user($phone, $first_name, $last_name, $password);
    if (empty($create['success'])) {
        wp_send_json_error(['message' => isset($create['message']) ? $create['message'] : 'خطا در ثبت‌نام.']);
    }
    $mobile = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : preg_replace('/\D/', '', $phone);
    $code   = sc_login_register_set_otp($mobile);
    if ($code) {
        sc_login_register_send_otp_sms($mobile, $code);
    }
    wp_send_json_success(['message' => 'ثبت‌نام انجام شد. کد تأیید به شماره شما ارسال شد.', 'step' => 'verify_otp']);
}

/**
 * ریدایرکت بعد از خروج به صفحه اصلی
 */
add_filter('logout_redirect', function ($redirect, $requested_redirect, $user) {
    return home_url('/');
}, 10, 3);

/**
 * Shortcode [sc_login_register_form]
 */
add_action('init', function () {
    add_shortcode('sc_login_register_form', 'sc_login_register_shortcode');
});
function sc_login_register_shortcode() {
    if (is_user_logged_in()) {
        $redirect = sc_login_register_redirect_url();
        return '<div class="sc-lr-logged-in"><p>شما وارد شده‌اید.</p><p><a href="' . esc_url($redirect) . '">رفتن به پنل کاربری</a></p></div>';
    }
    wp_enqueue_style(
        'sc-login-register',
        SC_ASSETS_URL . 'css/login-register.css',
        [],
        (defined('SC_VERSION') ? SC_VERSION : time())
    );
    wp_enqueue_script(
        'sc-login-register',
        SC_ASSETS_URL . 'js/login-register.js',
        ['jquery'],
        (defined('SC_VERSION') ? SC_VERSION : time()),
        true
    );
    wp_localize_script('sc-login-register', 'scLoginRegister', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('sc_login_register'),
        'strings' => [
            'enter_phone'   => 'شماره موبایل یا پست الکترونیک',
            'submit'        => 'ورود به ' . get_bloginfo('name'),
            'password'      => 'رمز عبور',
            'send_otp'      => 'ارسال کد یکبارمصرف',
            'otp_placeholder' => 'کد تأیید',
            'first_name'    => 'نام',
            'last_name'     => 'نام خانوادگی',
            'register_btn'  => 'ثبت‌نام و دریافت کد',
            'verify_btn'    => 'تأیید و ورود',
            'login_btn'     => 'ورود',
            'back'          => 'بازگشت',
            'loading'       => 'لطفاً صبر کنید...',
            'err_phone'     => 'شماره موبایل معتبر وارد کنید.',
        ],
    ]);
    $logo_url   = sc_get_setting('sc_login_logo_url', '');
    $bg_color   = sc_get_setting('sc_login_bg_color', '#ffffff');
    $bg_image   = sc_get_setting('sc_login_bg_image', '');
    $btn_bg    = sc_get_setting('sc_login_btn_bg', '#e60012');
    $btn_color = sc_get_setting('sc_login_btn_color', '#ffffff');
    $style_vars = '--sc-lr-bg:' . esc_attr($bg_color) . ';--sc-lr-btn-bg:' . esc_attr($btn_bg) . ';--sc-lr-btn-color:' . esc_attr($btn_color) . ';';
    if ($bg_image) {
        $style_vars .= '--sc-lr-bg-image:url(' . esc_url($bg_image) . ');';
    }
    ob_start();
    ?>
    <div class="sc-login-register-wrap" style="<?php echo $style_vars; ?>">
        <div class="sc-lr-background" aria-hidden="true"></div>
        <div class="sc-lr-card">
            <?php if ($logo_url) : ?>
                <div class="sc-lr-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </div>
            <?php endif; ?>
            <h2 class="sc-lr-title">ورود به <?php echo esc_html(get_bloginfo('name')); ?></h2>
            <p class="sc-lr-desc">لطفا شماره موبایل خود را وارد کنید</p>
            <div class="sc-lr-form" id="sc-login-register-form">
                <div class="sc-lr-step sc-lr-step-phone" data-step="phone">
                    <input type="tel" class="sc-lr-input" id="sc-lr-phone" placeholder="شماره موبایل" autocomplete="tel" maxlength="11" inputmode="numeric">
                    <p class="sc-lr-error" id="sc-lr-phone-error" role="alert"></p>
                    <button type="button" class="sc-lr-btn" id="sc-lr-submit-phone">ورود به <?php echo esc_html(get_bloginfo('name')); ?></button>
                </div>
                <div class="sc-lr-step sc-lr-step-exists" data-step="exists" style="display:none;">
                    <input type="password" class="sc-lr-input" id="sc-lr-password" placeholder="رمز عبور" autocomplete="current-password">
                    <p class="sc-lr-error" id="sc-lr-password-error" role="alert"></p>
                    <button type="button" class="sc-lr-btn" id="sc-lr-login-password">ورود</button>
                    <p class="sc-lr-otp-link"><button type="button" class="sc-lr-link-btn" id="sc-lr-send-otp">ارسال کد یکبارمصرف</button></p>
                    <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-phone">بازگشت</button></p>
                </div>
                <div class="sc-lr-step sc-lr-step-register" data-step="register" style="display:none;">
                    <input type="text" class="sc-lr-input" id="sc-lr-first-name" placeholder="نام" autocomplete="given-name">
                    <input type="text" class="sc-lr-input" id="sc-lr-last-name" placeholder="نام خانوادگی" autocomplete="family-name">
                    <input type="password" class="sc-lr-input" id="sc-lr-reg-password" placeholder="رمز عبور (حداقل ۶ کاراکتر)" autocomplete="new-password">
                    <p class="sc-lr-error" id="sc-lr-reg-error" role="alert"></p>
                    <button type="button" class="sc-lr-btn" id="sc-lr-register-submit">ثبت‌نام و دریافت کد</button>
                    <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-phone-reg">بازگشت</button></p>
                </div>
                <div class="sc-lr-step sc-lr-step-otp" data-step="otp" style="display:none;">
                    <input type="text" class="sc-lr-input" id="sc-lr-otp" placeholder="کد تأیید" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                    <p class="sc-lr-error" id="sc-lr-otp-error" role="alert"></p>
                    <button type="button" class="sc-lr-btn" id="sc-lr-verify-otp">تأیید و ورود</button>
                    <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-resend-otp">دریافت مجدد کد</button></p>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
