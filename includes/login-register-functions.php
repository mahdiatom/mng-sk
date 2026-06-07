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

/** Pending registration transient expiry (15 minutes) */
define('SC_REGISTER_PENDING_EXPIRY', 900);

/**
 * Normalize phone to 09xxxxxxxxx for consistent lookup
 */
function sc_login_register_normalize_phone($phone) {
    $raw = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : null;
    if ($raw) {
        return $raw;
    }
    $digits = preg_replace('/\D/', '', $phone);
    if (preg_match('/^09\d{9}$/', $digits)) {
        return $digits;
    }
    if (preg_match('/^9\d{9}$/', $digits)) {
        return '0' . $digits;
    }
    return '';
}

/**
 * Get WP user by mobile – فقط بر اساس نام کاربری (شماره موبایل)
 * کاربر فقط وقتی وجود دارد که در wp_users با user_login = شماره موبایل رکورد داشته باشد
 */
function sc_get_user_by_phone($phone) {
    $mobile = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return null;
    }
    $user = get_user_by('login', $mobile);
    return $user ? $user : null;
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
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
    if ($mobile) {
        delete_transient('sc_login_attempts_' . $mobile);
        delete_transient('sc_login_lock_' . $mobile);
    }
}

/**
 * Send OTP SMS using pattern from settings
 */
function sc_login_register_send_otp_sms($phone, $code) {
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
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

    // استفاده از همان منطق افزونه: ایجاد رکورد در sc_members (در صورت عدم اجرای hook)
    if (function_exists('sc_auto_create_member_on_user_register')) {
        sc_auto_create_member_on_user_register($user_id);
    }

    sc_check_and_create_tables();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    if ($member) {
        $national_id = '9' . str_pad((string) $user_id, 9, '0', STR_PAD_LEFT);
        $wpdb->update(
            $members_table,
            [
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'player_phone' => $mobile,
                'national_id'  => $national_id,
                'updated_at'   => current_time('mysql'),
            ],
            ['id' => $member->id],
            ['%s', '%s', '%s', '%s', '%s'],
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
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
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
    $mobile = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }
    if (!sc_login_register_verify_otp($mobile, $code)) {
        wp_send_json_error(['message' => 'کد تأیید نادرست یا منقضی است.']);
    }

    $pending = get_transient('sc_register_pending_' . $mobile);
    if ($pending && is_array($pending) && !empty($pending['first_name']) && !empty($pending['last_name']) && !empty($pending['password'])) {
        delete_transient('sc_register_pending_' . $mobile);
        $create = sc_login_register_create_user($mobile, $pending['first_name'], $pending['last_name'], $pending['password']);
        if (empty($create['success'])) {
            wp_send_json_error(['message' => isset($create['message']) ? $create['message'] : 'خطا در ایجاد حساب.']);
        }
        $user = get_userdata((int) $create['user_id']);
        if (!$user) {
            wp_send_json_error(['message' => 'خطا در ورود بعد از ثبت‌نام.']);
        }
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        sc_login_register_clear_attempts($mobile);
        wp_send_json_success(['redirect' => sc_login_register_redirect_url()]);
    }

    $user = sc_get_user_by_phone($mobile);
    if (!$user) {
        wp_send_json_error(['message' => 'کاربر یافت نشد. لطفاً دوباره درخواست کد دهید.']);
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
    $mobile   = sc_login_register_normalize_phone($phone);
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
    $mobile     = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }
    if (sc_get_user_by_phone($mobile)) {
        wp_send_json_error(['message' => 'این شماره قبلاً ثبت شده است. از ورود با رمز یا کد یکبارمصرف استفاده کنید.']);
    }
    if (strlen($first_name) < 2 || strlen($last_name) < 2) {
        wp_send_json_error(['message' => 'نام و نام خانوادگی را صحیح وارد کنید.']);
    }
    if (strlen($password) < 6) {
        wp_send_json_error(['message' => 'رمز عبور حداقل ۶ کاراکتر باشد.']);
    }
    set_transient('sc_register_pending_' . $mobile, [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'password'   => $password,
    ], SC_REGISTER_PENDING_EXPIRY);
    $code = sc_login_register_set_otp($mobile);
    if (!$code) {
        delete_transient('sc_register_pending_' . $mobile);
        wp_send_json_error(['message' => 'خطا در ایجاد کد تأیید.']);
    }
    $send = sc_login_register_send_otp_sms($mobile, $code);
    if (empty($send['success'])) {
        delete_transient('sc_register_pending_' . $mobile);
        wp_send_json_error(['message' => isset($send['message']) ? $send['message'] : 'خطا در ارسال پیامک.']);
    }
    wp_send_json_success(['message' => 'کد تأیید به شماره شما ارسال شد. پس از وارد کردن کد، حساب شما ساخته می‌شود.', 'step' => 'verify_otp']);
}

/**
 * ریدایرکت بعد از خروج به صفحه اصلی (وردپرس و ووکامرس)
 */
add_filter('logout_redirect', function ($redirect, $requested_redirect, $user) {
    return home_url('/');
}, 10, 3);

add_filter('woocommerce_logout_default_redirect_url', function ($redirect) {
    return home_url('/');
}, 10, 1);

/**
 * Shortcode [sc_login_register_form]
 */
add_action('init', function () {
    add_shortcode('sc_login_register_form', 'sc_login_register_shortcode');
});

/**
 * HTML داخلی کارت فرم ورود
 */
function sc_login_register_render_card() {
    $logo_url     = sc_get_setting('sc_login_logo_url', '');
    $sc_name_club = sc_get_setting('sc_name_club', '');
    ob_start();
    ?>
    <div class="sc-lr-card">
        <div class="sc-lr-alert sc-lr-alert-error" id="sc-lr-global-error" role="alert" aria-live="polite" style="display:none;">
            <span class="sc-lr-alert-icon" aria-hidden="true"></span>
            <span class="sc-lr-alert-text" id="sc-lr-global-error-text"></span>
        </div>
        <?php if ($logo_url) : ?>
            <div class="sc-lr-logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </div>
        <?php endif; ?>
        <h2 class="sc-lr-title">ورود به <?php echo esc_html($sc_name_club); ?></h2>
        <div class="sc-lr-form" id="sc-login-register-form">
            <div class="sc-lr-step sc-lr-step-phone" data-step="phone">
                <p class="sc-lr-desc" id="sc-lr-desc">لطفا شماره موبایل خود را وارد کنید</p>
                <input type="tel" class="sc-lr-input" id="sc-lr-phone" placeholder="شماره موبایل بازیکن" autocomplete="tel" maxlength="11" inputmode="numeric">
                <button type="button" class="sc-lr-btn" id="sc-lr-submit-phone">ورود </button>
            </div>
            <div class="sc-lr-step sc-lr-step-exists" data-step="exists" style="display:none;">
                <p class="sc-lr-desc" id="sc-lr-desc">رمز عبور خود را وارد کنید در صورتی که فراموش کرده اید از کد یک بار مصرف استفاده کنید.</p>
                <input type="password" class="sc-lr-input" id="sc-lr-password" placeholder="رمز عبور" autocomplete="current-password">
                <button type="button" class="sc-lr-btn" id="sc-lr-login-password">ورود</button>
                <button type="button" class="sc-lr-btn sc-lr-btn-outline" id="sc-lr-send-otp">ارسال کد یکبارمصرف</button>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-phone">بازگشت و تصحیح شماره </button></p>
            </div>
            <div class="sc-lr-step sc-lr-step-register" data-step="register" style="display:none;">
                <input type="text" class="sc-lr-input" id="sc-lr-first-name" placeholder="نام" autocomplete="given-name">
                <input type="text" class="sc-lr-input" id="sc-lr-last-name" placeholder="نام خانوادگی" autocomplete="family-name">
                <input type="password" class="sc-lr-input" id="sc-lr-reg-password" placeholder="رمز عبور (حداقل ۶ کاراکتر)" autocomplete="new-password">
                <button type="button" class="sc-lr-btn" id="sc-lr-register-submit">ثبت‌نام و دریافت کد</button>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-phone-reg">بازگشت</button></p>
            </div>
            <div class="sc-lr-step sc-lr-step-otp" data-step="otp" style="display:none;">
                <input type="text" class="sc-lr-input" id="sc-lr-otp" placeholder="  کد تأیید ۵ رقمی ارسال شده به " maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                <button type="button" class="sc-lr-btn" id="sc-lr-verify-otp">تأیید و ورود</button>
                <button type="button" class="sc-lr-back-top" id="sc-lr-otp-back" title="بازگشت">→ بازگشت و تصحیح شماره </button>
                <div class="sc-lr-resend-wrap" id="sc-lr-resend-wrap" style="display:none;">
                    <button type="button" class="sc-lr-btn sc-lr-btn-outline" id="sc-lr-resend-otp">دریافت مجدد کد</button>
                </div>
                <p class="sc-lr-otp-timer-wrap">
                    <span class="sc-lr-otp-timer-label">اعتبار کد:</span>
                    <span class="sc-lr-otp-timer" id="sc-lr-otp-timer" aria-live="polite">۲:۰۰</span>
                </p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

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
    $bg_color   = sc_get_setting('sc_login_bg_color', '#ffffff');
    $bg_image   = sc_get_setting('sc_login_bg_image', '');
    $btn_bg     = sc_get_setting('sc_login_btn_bg', '#e60012');
    $btn_color  = sc_get_setting('sc_login_btn_color', '#ffffff');
    $display_mode = sc_get_setting('sc_login_display_mode', 'mode1');
    $card_align   = sc_get_setting('sc_login_card_align', 'center');
    if (!in_array($display_mode, ['mode1', 'mode2', 'mode3'], true)) {
        $display_mode = 'mode1';
    }
    if (!in_array($card_align, ['left', 'center', 'right'], true)) {
        $card_align = 'center';
    }
    $style_vars = '--sc-lr-bg:' . esc_attr($bg_color) . ';--sc-lr-btn-bg:' . esc_attr($btn_bg) . ';--sc-lr-btn-color:' . esc_attr($btn_color) . ';';
    if ($bg_image && $display_mode === 'mode1') {
        $style_vars .= '--sc-lr-bg-image:url(' . esc_url($bg_image) . ');';
    }
    $wrap_classes = 'sc-login-register-wrap sc-lr-mode-' . esc_attr(substr($display_mode, -1)) . ' sc-lr-align-' . esc_attr($card_align);
    ob_start();
    if (in_array($display_mode, ['mode2', 'mode3'], true)) :
        $form_html = sc_login_register_render_card();
        ?>
        <div class="<?php echo esc_attr($wrap_classes); ?>" style="<?php echo $style_vars; ?>">
            <div class="sc-lr-split">
                <?php if ($display_mode === 'mode3') : ?>
                    <div class="sc-lr-split-form">
                        <?php echo $form_html; ?>
                    </div>
                    <div class="sc-lr-split-image">
                        <?php if ($bg_image) : ?>
                            <img src="<?php echo esc_url($bg_image); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="sc-lr-split-image">
                        <?php if ($bg_image) : ?>
                            <img src="<?php echo esc_url($bg_image); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="sc-lr-split-form">
                        <?php echo $form_html; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    else :
        ?>
        <div class="<?php echo esc_attr($wrap_classes); ?>" style="<?php echo $style_vars; ?>">
            <div class="sc-lr-background" aria-hidden="true"></div>
            <?php echo sc_login_register_render_card(); ?>
        </div>
        <?php
    endif;
    return ob_get_clean();
}
