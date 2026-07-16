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
    return count(sc_login_register_get_users_by_phone($phone)) > 0;
}

/**
 * All WP accounts associated with a phone (login, member phone, billing_phone)
 *
 * @return array<int, array{user_id:int,name:string,label:string,national_id:string}>
 */
function sc_login_register_get_users_by_phone($phone) {
    $mobile = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return [];
    }

    sc_check_and_create_tables();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $user_ids      = [];

    $direct = get_user_by('login', $mobile);
    if ($direct) {
        $user_ids[(int) $direct->ID] = true;
    }

    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, first_name, last_name, national_id
         FROM $members_table
         WHERE player_phone = %s AND user_id IS NOT NULL AND user_id > 0",
        $mobile
    ));
    foreach ((array) $members as $member) {
        $user_ids[(int) $member->user_id] = true;
    }

    $meta_users = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s",
        $mobile
    ));
    foreach ((array) $meta_users as $uid) {
        $user_ids[(int) $uid] = true;
    }

    $result = [];
    foreach (array_keys($user_ids) as $uid) {
        $user = get_userdata($uid);
        if (!$user) {
            continue;
        }

        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name, national_id FROM $members_table WHERE user_id = %d LIMIT 1",
            $uid
        ));

        $first = $member && !empty($member->first_name) ? $member->first_name : $user->first_name;
        $last  = $member && !empty($member->last_name) ? $member->last_name : $user->last_name;
        $name  = trim($first . ' ' . $last);
        if ($name === '') {
            $name = $user->display_name ?: $user->user_login;
        }

        $national_id = ($member && !empty($member->national_id)) ? (string) $member->national_id : '';
        $label       = $name;
        if ($national_id !== '' && sc_login_register_normalize_national_id($national_id)) {
            $label .= ' — ' . $national_id;
        }

        $result[] = [
            'user_id'     => (int) $uid,
            'name'        => $name,
            'label'       => $label,
            'national_id' => $national_id,
        ];
    }

    usort($result, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $result;
}

/**
 * Resolve WP user for phone login (optionally by selected user_id)
 */
function sc_login_register_resolve_phone_user($phone, $user_id = 0) {
    $mobile  = sc_login_register_normalize_phone($phone);
    $user_id = (int) $user_id;
    if (!$mobile) {
        return null;
    }

    $users = sc_login_register_get_users_by_phone($mobile);
    if ($user_id > 0) {
        foreach ($users as $item) {
            if ((int) $item['user_id'] === $user_id) {
                return get_userdata($user_id);
            }
        }
        return null;
    }

    if (count($users) === 1) {
        return get_userdata((int) $users[0]['user_id']);
    }

    return sc_get_user_by_phone($mobile);
}

/**
 * Normalize national ID to 10 digits
 */
function sc_login_register_normalize_national_id($national_id) {
    $value = (string) $national_id;
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $value   = str_replace($persian, range(0, 9), $value);
    $value   = str_replace($arabic, range(0, 9), $value);
    $digits = preg_replace('/\D/', '', $value);
    if (preg_match('/^\d{10}$/', $digits)) {
        return $digits;
    }
    return '';
}

/**
 * Find member row by national_id in sc_members
 */
function sc_login_register_get_member_by_national_id($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        return null;
    }
    sc_check_and_create_tables();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, national_id, player_phone, first_name, last_name
         FROM $members_table WHERE national_id = %s LIMIT 1",
        $nid
    ));
}

/**
 * Get WP user by national_id (user_login or sc_members.user_id)
 */
function sc_get_user_by_national_id($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        return null;
    }

    $user = get_user_by('login', $nid);
    if ($user) {
        return $user;
    }

    $member = sc_login_register_get_member_by_national_id($nid);
    if ($member && !empty($member->user_id)) {
        $user = get_userdata((int) $member->user_id);
        return $user ? $user : null;
    }

    return null;
}

/**
 * Whether national_id already has a linked WP account
 */
function sc_login_register_national_id_registered($national_id) {
    return sc_get_user_by_national_id($national_id) !== null;
}

/**
 * Get OTP/mobile phone for a national_id account
 */
function sc_login_register_get_phone_for_national_id_user($national_id) {
    $user = sc_get_user_by_national_id($national_id);
    if (!$user) {
        return '';
    }
    if (function_exists('sc_get_user_phone')) {
        $phone = sc_get_user_phone($user->ID);
        if ($phone) {
            return sc_login_register_normalize_phone($phone);
        }
    }
    $member = sc_login_register_get_member_by_national_id($national_id);
    if ($member && !empty($member->player_phone)) {
        return sc_login_register_normalize_phone($member->player_phone);
    }
    return sc_login_register_normalize_phone(get_user_meta($user->ID, 'billing_phone', true));
}

/**
 * Check national_id availability for registration
 *
 * @return array{taken:bool,message?:string}
 */
function sc_login_register_check_national_id_available($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        return ['taken' => true, 'message' => 'کد ملی معتبر وارد کنید.'];
    }
    if (sc_login_register_national_id_registered($nid) || username_exists($nid)) {
        return ['taken' => true, 'message' => 'این کد ملی قبلاً ثبت شده است. از ورود استفاده کنید.'];
    }
    $member = sc_login_register_get_member_by_national_id($nid);
    if ($member) {
        return ['taken' => true, 'message' => 'این کد ملی در سیستم وجود دارد. لطفاً با پشتیبانی تماس بگیرید.'];
    }
    return ['taken' => false];
}

/**
 * Login lock by national_id
 */
function sc_login_register_is_locked_nid($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        return true;
    }
    $lock_until = get_transient('sc_login_lock_nid_' . $nid);
    return $lock_until && (int) $lock_until > time();
}

function sc_login_register_record_failed_nid($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        return;
    }
    $key = 'sc_login_attempts_nid_' . $nid;
    $count = (int) get_transient($key);
    $count++;
    set_transient($key, $count, SC_LOGIN_LOCK_DURATION);
    if ($count >= SC_LOGIN_MAX_ATTEMPTS) {
        set_transient('sc_login_lock_nid_' . $nid, time() + SC_LOGIN_LOCK_DURATION, SC_LOGIN_LOCK_DURATION);
        delete_transient($key);
    }
}

function sc_login_register_clear_attempts_nid($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if ($nid) {
        delete_transient('sc_login_attempts_nid_' . $nid);
        delete_transient('sc_login_lock_nid_' . $nid);
    }
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
 * OTP scoped by national_id (for registration when phone may be shared)
 */
function sc_login_register_set_otp_nid($national_id, $phone) {
    $nid = sc_login_register_normalize_national_id($national_id);
    $mobile = sc_login_register_normalize_phone($phone);
    if (!$nid || !$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return null;
    }
    $code = (string) wp_rand(10000, 99999);
    set_transient('sc_otp_nid_' . $nid, ['code' => $code, 'at' => time(), 'phone' => $mobile], SC_OTP_EXPIRY);
    return $code;
}

function sc_login_register_verify_otp_nid($national_id, $code) {
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        return false;
    }
    $stored = get_transient('sc_otp_nid_' . $nid);
    if (!$stored || !isset($stored['code']) || (string) $stored['code'] !== (string) $code) {
        return false;
    }
    delete_transient('sc_otp_nid_' . $nid);
    return true;
}

function sc_login_register_pending_nid_key($national_id) {
    $nid = sc_login_register_normalize_national_id($national_id);
    return $nid ? 'sc_register_pending_nid_' . $nid : '';
}

/**
 * OTP scoped by user_id (phone login with multiple accounts)
 */
function sc_login_register_set_otp_for_user($user_id, $phone) {
    $user_id = (int) $user_id;
    $mobile  = sc_login_register_normalize_phone($phone);
    if ($user_id < 1 || !$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return null;
    }
    $code = (string) wp_rand(10000, 99999);
    set_transient('sc_otp_user_' . $user_id, ['code' => $code, 'at' => time(), 'phone' => $mobile], SC_OTP_EXPIRY);
    return $code;
}

function sc_login_register_verify_otp_for_user($user_id, $code) {
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return false;
    }
    $stored = get_transient('sc_otp_user_' . $user_id);
    if (!$stored || !isset($stored['code']) || (string) $stored['code'] !== (string) $code) {
        return false;
    }
    delete_transient('sc_otp_user_' . $user_id);
    return true;
}

function sc_login_register_is_locked_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return true;
    }
    $lock_until = get_transient('sc_login_lock_user_' . $user_id);
    return $lock_until && (int) $lock_until > time();
}

function sc_login_register_record_failed_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return;
    }
    $key   = 'sc_login_attempts_user_' . $user_id;
    $count = (int) get_transient($key);
    $count++;
    set_transient($key, $count, SC_LOGIN_LOCK_DURATION);
    if ($count >= SC_LOGIN_MAX_ATTEMPTS) {
        set_transient('sc_login_lock_user_' . $user_id, time() + SC_LOGIN_LOCK_DURATION, SC_LOGIN_LOCK_DURATION);
        delete_transient($key);
    }
}

function sc_login_register_clear_attempts_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id > 0) {
        delete_transient('sc_login_attempts_user_' . $user_id);
        delete_transient('sc_login_lock_user_' . $user_id);
    }
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
function sc_login_register_redirect_url($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id > 0 && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only($user_id)) {
        return function_exists('sc_secretary_admin_url') ? sc_secretary_admin_url() : admin_url('admin.php?page=sc-dashboard');
    }
    if ($user_id > 0) {
        $staff_caps = ['administrator', 'club_coach', 'system_manager', 'coach', 'shop_manager', 'accountantt'];
        foreach ($staff_caps as $cap) {
            if (user_can($user_id, $cap)) {
                return admin_url();
            }
        }
    }
    $path = sc_get_setting('sc_login_redirect_path', 'my-account/sc-submit-documents/');
    $path = ltrim($path, '/');
    return home_url('/' . $path);
}

/**
 * نقش پیش‌فرض بازیکن در وردپرس (subscriber → بازیکن)
 */
function sc_login_register_player_role() {
    return 'subscriber';
}

/**
 * اطمینان از نقش بازیکن برای کاربر وردپرس
 * همیشه subscriber (بازیکن) ست می‌شود تا نقش خالی («هیچکدام») یا نقش‌های حذف‌شده مثل customer باقی نماند.
 */
function sc_login_register_assign_player_role($user_id) {
    $user_id = (int) $user_id;
    $role    = sc_login_register_player_role();
    $user    = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    $user->set_role($role);
    return true;
}

/**
 * ایجاد/به‌روزرسانی رکورد بازیکن در sc_members پس از ساخت کاربر وردپرس
 *
 * @param int   $user_id
 * @param array $args first_name, last_name, phone, national_id
 * @return int|false member_id
 */
function sc_login_register_sync_member_profile($user_id, $args = []) {
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return false;
    }

    sc_login_register_assign_player_role($user_id);

    $phone = !empty($args['phone']) ? sc_login_register_normalize_phone($args['phone']) : '';
    if ($phone) {
        update_user_meta($user_id, 'billing_phone', $phone);
    }

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

    if (!$member) {
        return false;
    }

    $update = ['updated_at' => current_time('mysql')];
    $format = ['%s'];

    if (!empty($args['first_name'])) {
        $update['first_name'] = sanitize_text_field($args['first_name']);
        $format[]             = '%s';
    }
    if (!empty($args['last_name'])) {
        $update['last_name'] = sanitize_text_field($args['last_name']);
        $format[]            = '%s';
    }
    if ($phone) {
        $update['player_phone'] = $phone;
        $format[]               = '%s';
    }
    if (!empty($args['national_id'])) {
        $nid = sc_login_register_normalize_national_id($args['national_id']);
        if ($nid) {
            $update['national_id'] = $nid;
            $format[]              = '%s';
        }
    }

    if (count($update) > 1) {
        $wpdb->update(
            $members_table,
            $update,
            ['id' => $member->id],
            $format,
            ['%d']
        );
    }

    return (int) $member->id;
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
        'role'         => sc_login_register_player_role(),
    ]);
    if (is_wp_error($user_id)) {
        return ['success' => false, 'message' => $user_id->get_error_message()];
    }

    $member_id = sc_login_register_sync_member_profile($user_id, [
        'first_name'  => $first_name,
        'last_name'   => $last_name,
        'phone'       => $mobile,
        'national_id' => '9' . str_pad((string) $user_id, 9, '0', STR_PAD_LEFT),
    ]);
    if (!$member_id) {
        return ['success' => false, 'message' => 'خطا در ایجاد پروفایل بازیکن.'];
    }

    return ['success' => true, 'user_id' => $user_id];
}

/**
 * Create WP user with national_id as username
 */
function sc_login_register_create_user_by_national_id($national_id, $phone, $first_name, $last_name, $password) {
    $nid = sc_login_register_normalize_national_id($national_id);
    $mobile = sc_login_register_normalize_phone($phone);

    if (!$nid) {
        return ['success' => false, 'message' => 'کد ملی معتبر نیست.'];
    }
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        return ['success' => false, 'message' => 'شماره موبایل معتبر نیست.'];
    }

    $available = sc_login_register_check_national_id_available($nid);
    if (!empty($available['taken'])) {
        return ['success' => false, 'message' => $available['message'] ?? 'این کد ملی قبلاً ثبت شده است.'];
    }

    $first_name = sanitize_text_field($first_name);
    $last_name  = sanitize_text_field($last_name);
    if (strlen($first_name) < 2 || strlen($last_name) < 2) {
        return ['success' => false, 'message' => 'نام و نام خانوادگی را صحیح وارد کنید.'];
    }

    $login   = sanitize_user($nid, true);
    $email   = $nid . '@gmail.com';
    $display = trim($first_name . ' ' . $last_name);

    $user_id = wp_insert_user([
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => $password,
        'display_name' => $display,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'role'         => sc_login_register_player_role(),
    ]);
    if (is_wp_error($user_id)) {
        return ['success' => false, 'message' => $user_id->get_error_message()];
    }

    $member_id = sc_login_register_sync_member_profile($user_id, [
        'first_name'  => $first_name,
        'last_name'   => $last_name,
        'phone'       => $mobile,
        'national_id' => $nid,
    ]);
    if (!$member_id) {
        return ['success' => false, 'message' => 'خطا در ایجاد پروفایل بازیکن.'];
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

    $users = sc_login_register_get_users_by_phone($mobile);
    $count = count($users);
    if ($count < 1) {
        wp_send_json_success(['exists' => false]);
    }

    $public_users = array_map(function ($item) {
        return [
            'user_id' => (int) $item['user_id'],
            'name'    => $item['name'],
            'label'   => $item['label'],
        ];
    }, $users);

    wp_send_json_success([
        'exists'   => true,
        'multiple' => $count > 1,
        'users'    => $public_users,
        'user_id'  => $count === 1 ? (int) $users[0]['user_id'] : 0,
    ]);
}

add_action('wp_ajax_sc_login_register_send_otp', 'sc_ajax_login_register_send_otp');
add_action('wp_ajax_nopriv_sc_login_register_send_otp', 'sc_ajax_login_register_send_otp');
function sc_ajax_login_register_send_otp() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $mobile  = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }

    $user = sc_login_register_resolve_phone_user($mobile, $user_id);
    if (!$user) {
        wp_send_json_error(['message' => 'ابتدا ثبت‌نام کنید یا حساب را انتخاب کنید.']);
    }

    $code = sc_login_register_set_otp_for_user($user->ID, $mobile);
    if (!$code) {
        wp_send_json_error(['message' => 'خطا در ایجاد کد.']);
    }
    $send = sc_login_register_send_otp_sms($mobile, $code);
    if (empty($send['success'])) {
        wp_send_json_error(['message' => isset($send['message']) ? $send['message'] : 'خطا در ارسال پیامک.']);
    }
    wp_send_json_success(['message' => 'کد تأیید ارسال شد.', 'user_id' => (int) $user->ID]);
}

add_action('wp_ajax_sc_login_register_verify_otp', 'sc_ajax_login_register_verify_otp');
add_action('wp_ajax_nopriv_sc_login_register_verify_otp', 'sc_ajax_login_register_verify_otp');
function sc_ajax_login_register_verify_otp() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone   = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $code    = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $mobile  = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }

    $pending = get_transient('sc_register_pending_' . $mobile);
    if ($pending && is_array($pending) && !empty($pending['first_name']) && !empty($pending['last_name']) && !empty($pending['password'])) {
        if (!sc_login_register_verify_otp($mobile, $code)) {
            wp_send_json_error(['message' => 'کد تأیید نادرست یا منقضی است.']);
        }
        delete_transient('sc_register_pending_' . $mobile);
        if (!empty($pending['register_method']) && $pending['register_method'] === 'national_id' && !empty($pending['national_id'])) {
            $create = sc_login_register_create_user_by_national_id(
                $pending['national_id'],
                $mobile,
                $pending['first_name'],
                $pending['last_name'],
                $pending['password']
            );
        } else {
            $create = sc_login_register_create_user($mobile, $pending['first_name'], $pending['last_name'], $pending['password']);
        }
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
        if (!empty($pending['national_id'])) {
            sc_login_register_clear_attempts_nid($pending['national_id']);
        }
        wp_send_json_success(['redirect' => sc_login_register_redirect_url((int) $user->ID)]);
    }

    $user = sc_login_register_resolve_phone_user($mobile, $user_id);
    if (!$user) {
        wp_send_json_error(['message' => 'کاربر یافت نشد. لطفاً دوباره درخواست کد دهید.']);
    }
    if (!sc_login_register_verify_otp_for_user($user->ID, $code)) {
        wp_send_json_error(['message' => 'کد تأیید نادرست یا منقضی است.']);
    }
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    sc_login_register_clear_attempts($mobile);
    sc_login_register_clear_attempts_user($user->ID);
    wp_send_json_success(['redirect' => sc_login_register_redirect_url((int) $user->ID)]);
}

add_action('wp_ajax_sc_login_register_login_password', 'sc_ajax_login_register_login_password');
add_action('wp_ajax_nopriv_sc_login_register_login_password', 'sc_ajax_login_register_login_password');
function sc_ajax_login_register_login_password() {
    check_ajax_referer('sc_login_register', 'nonce');
    $phone    = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $user_id  = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    $mobile   = sc_login_register_normalize_phone($phone);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }

    $user = sc_login_register_resolve_phone_user($mobile, $user_id);
    if (!$user) {
        sc_login_register_record_failed($mobile);
        wp_send_json_error(['message' => 'کاربری با این شماره یافت نشد.']);
    }

    if (sc_login_register_is_locked_user($user->ID)) {
        wp_send_json_error(['message' => 'به دلیل تلاش‌های ناموفق، به مدت ۳ دقیقه امکان ورود با رمز وجود ندارد. از ارسال کد یکبارمصرف استفاده کنید.']);
    }

    $auth = wp_authenticate($user->user_login, $password);
    if (is_wp_error($auth)) {
        sc_login_register_record_failed_user($user->ID);
        wp_send_json_error(['message' => 'رمز عبور اشتباه است.']);
    }
    wp_clear_auth_cookie();
    wp_set_current_user($auth->ID);
    wp_set_auth_cookie($auth->ID, true);
    sc_login_register_clear_attempts($mobile);
    sc_login_register_clear_attempts_user($user->ID);
    wp_send_json_success(['redirect' => sc_login_register_redirect_url((int) $user->ID)]);
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
    if (count(sc_login_register_get_users_by_phone($mobile)) > 0) {
        wp_send_json_error(['message' => 'با این شماره حسابی وجود دارد. از بخش ورود استفاده کنید.']);
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

add_action('wp_ajax_sc_login_register_check_national_id', 'sc_ajax_login_register_check_national_id');
add_action('wp_ajax_nopriv_sc_login_register_check_national_id', 'sc_ajax_login_register_check_national_id');
function sc_ajax_login_register_check_national_id() {
    check_ajax_referer('sc_login_register', 'nonce');
    $national_id = isset($_POST['national_id']) ? sanitize_text_field($_POST['national_id']) : '';
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        wp_send_json_success(['exists' => false, 'message' => 'کد ملی ۱۰ رقمی معتبر وارد کنید.']);
    }

    if (sc_login_register_national_id_registered($nid)) {
        wp_send_json_success(['exists' => true]);
    }

    $available = sc_login_register_check_national_id_available($nid);
    if (!empty($available['taken'])) {
        wp_send_json_error(['message' => $available['message'] ?? 'این کد ملی قابل ثبت‌نام نیست.']);
    }

    wp_send_json_success(['exists' => false]);
}

add_action('wp_ajax_sc_login_register_login_password_nid', 'sc_ajax_login_register_login_password_nid');
add_action('wp_ajax_nopriv_sc_login_register_login_password_nid', 'sc_ajax_login_register_login_password_nid');
function sc_ajax_login_register_login_password_nid() {
    check_ajax_referer('sc_login_register', 'nonce');
    $national_id = isset($_POST['national_id']) ? sanitize_text_field($_POST['national_id']) : '';
    $password    = isset($_POST['password']) ? $_POST['password'] : '';
    $nid         = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        wp_send_json_error(['message' => 'کد ملی معتبر نیست.']);
    }
    if (sc_login_register_is_locked_nid($nid)) {
        wp_send_json_error(['message' => 'به دلیل تلاش‌های ناموفق، به مدت ۳ دقیقه امکان ورود با رمز وجود ندارد. از ارسال کد یکبارمصرف استفاده کنید.']);
    }
    $user = sc_get_user_by_national_id($nid);
    if (!$user) {
        sc_login_register_record_failed_nid($nid);
        wp_send_json_error(['message' => 'کاربری با این کد ملی یافت نشد.']);
    }
    $auth = wp_authenticate($user->user_login, $password);
    if (is_wp_error($auth)) {
        sc_login_register_record_failed_nid($nid);
        wp_send_json_error(['message' => 'رمز عبور اشتباه است.']);
    }
    wp_clear_auth_cookie();
    wp_set_current_user($auth->ID);
    wp_set_auth_cookie($auth->ID, true);
    sc_login_register_clear_attempts_nid($nid);
    $phone = sc_login_register_get_phone_for_national_id_user($nid);
    if ($phone) {
        sc_login_register_clear_attempts($phone);
    }
    wp_send_json_success(['redirect' => sc_login_register_redirect_url((int) $user->ID)]);
}

add_action('wp_ajax_sc_login_register_send_otp_nid', 'sc_ajax_login_register_send_otp_nid');
add_action('wp_ajax_nopriv_sc_login_register_send_otp_nid', 'sc_ajax_login_register_send_otp_nid');
function sc_ajax_login_register_send_otp_nid() {
    check_ajax_referer('sc_login_register', 'nonce');
    $national_id = isset($_POST['national_id']) ? sanitize_text_field($_POST['national_id']) : '';
    $nid = sc_login_register_normalize_national_id($national_id);
    if (!$nid) {
        wp_send_json_error(['message' => 'کد ملی معتبر نیست.']);
    }
    if (!sc_login_register_national_id_registered($nid)) {
        wp_send_json_error(['message' => 'ابتدا ثبت‌نام کنید.']);
    }
    $mobile = sc_login_register_get_phone_for_national_id_user($nid);
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل برای این حساب یافت نشد. لطفاً با پشتیبانی تماس بگیرید.']);
    }
    $code = sc_login_register_set_otp($mobile);
    if (!$code) {
        wp_send_json_error(['message' => 'خطا در ایجاد کد.']);
    }
    $send = sc_login_register_send_otp_sms($mobile, $code);
    if (empty($send['success'])) {
        wp_send_json_error(['message' => isset($send['message']) ? $send['message'] : 'خطا در ارسال پیامک.']);
    }
    wp_send_json_success(['message' => 'کد تأیید ارسال شد.', 'phone' => $mobile]);
}

add_action('wp_ajax_sc_login_register_register_nid', 'sc_ajax_login_register_register_nid');
add_action('wp_ajax_nopriv_sc_login_register_register_nid', 'sc_ajax_login_register_register_nid');
function sc_ajax_login_register_register_nid() {
    check_ajax_referer('sc_login_register', 'nonce');
    $national_id = isset($_POST['national_id']) ? sanitize_text_field($_POST['national_id']) : '';
    $phone       = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $first_name  = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name   = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
    $password    = isset($_POST['password']) ? $_POST['password'] : '';
    $nid         = sc_login_register_normalize_national_id($national_id);
    $mobile      = sc_login_register_normalize_phone($phone);

    if (!$nid) {
        wp_send_json_error(['message' => 'کد ملی معتبر نیست.']);
    }
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }
    $available = sc_login_register_check_national_id_available($nid);
    if (!empty($available['taken'])) {
        wp_send_json_error(['message' => $available['message'] ?? 'این کد ملی قبلاً ثبت شده است.']);
    }
    if (strlen($first_name) < 2 || strlen($last_name) < 2) {
        wp_send_json_error(['message' => 'نام و نام خانوادگی را صحیح وارد کنید.']);
    }
    if (strlen($password) < 6) {
        wp_send_json_error(['message' => 'رمز عبور حداقل ۶ کاراکتر باشد.']);
    }

    set_transient('sc_register_pending_nid_' . $nid, [
        'first_name'      => $first_name,
        'last_name'       => $last_name,
        'password'        => $password,
        'register_method' => 'national_id',
        'national_id'     => $nid,
        'phone'           => $mobile,
    ], SC_REGISTER_PENDING_EXPIRY);

    $code = sc_login_register_set_otp_nid($nid, $mobile);
    if (!$code) {
        delete_transient('sc_register_pending_nid_' . $nid);
        wp_send_json_error(['message' => 'خطا در ایجاد کد تأیید.']);
    }
    $send = sc_login_register_send_otp_sms($mobile, $code);
    if (empty($send['success'])) {
        delete_transient('sc_register_pending_nid_' . $nid);
        wp_send_json_error(['message' => isset($send['message']) ? $send['message'] : 'خطا در ارسال پیامک.']);
    }
    wp_send_json_success(['message' => 'کد تأیید به شماره شما ارسال شد.', 'step' => 'verify_otp', 'phone' => $mobile]);
}

add_action('wp_ajax_sc_login_register_verify_otp_nid', 'sc_ajax_login_register_verify_otp_nid');
add_action('wp_ajax_nopriv_sc_login_register_verify_otp_nid', 'sc_ajax_login_register_verify_otp_nid');
function sc_ajax_login_register_verify_otp_nid() {
    check_ajax_referer('sc_login_register', 'nonce');
    $national_id = isset($_POST['national_id']) ? sanitize_text_field($_POST['national_id']) : '';
    $phone       = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $code        = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
    $nid         = sc_login_register_normalize_national_id($national_id);
    $mobile      = sc_login_register_normalize_phone($phone);

    if (!$nid) {
        wp_send_json_error(['message' => 'کد ملی معتبر نیست.']);
    }
    if (!$mobile || !preg_match('/^09\d{9}$/', $mobile)) {
        wp_send_json_error(['message' => 'شماره موبایل معتبر نیست.']);
    }

    $pending = get_transient('sc_register_pending_nid_' . $nid);
    if ($pending && is_array($pending) && !empty($pending['register_method']) && $pending['register_method'] === 'national_id') {
        if (empty($pending['national_id']) || $pending['national_id'] !== $nid) {
            wp_send_json_error(['message' => 'اطلاعات ثبت‌نام نامعتبر است. لطفاً دوباره تلاش کنید.']);
        }
        if (!sc_login_register_verify_otp_nid($nid, $code)) {
            wp_send_json_error(['message' => 'کد تأیید نادرست یا منقضی است.']);
        }
        $mobile = !empty($pending['phone']) ? sc_login_register_normalize_phone($pending['phone']) : $mobile;
        delete_transient('sc_register_pending_nid_' . $nid);
        $create = sc_login_register_create_user_by_national_id(
            $nid,
            $mobile,
            $pending['first_name'] ?? '',
            $pending['last_name'] ?? '',
            $pending['password'] ?? ''
        );
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
        sc_login_register_clear_attempts_nid($nid);
        wp_send_json_success(['redirect' => sc_login_register_redirect_url((int) $user->ID)]);
    }

    if (!sc_login_register_verify_otp($mobile, $code)) {
        wp_send_json_error(['message' => 'کد تأیید نادرست یا منقضی است.']);
    }

    $user = sc_get_user_by_national_id($nid);
    if (!$user) {
        wp_send_json_error(['message' => 'کاربر یافت نشد. لطفاً دوباره درخواست کد دهید.']);
    }
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    sc_login_register_clear_attempts($mobile);
    sc_login_register_clear_attempts_nid($nid);
    wp_send_json_success(['redirect' => sc_login_register_redirect_url((int) $user->ID)]);
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
    add_shortcode('sc_admin_login_form', 'sc_admin_login_shortcode');
});

/**
 * HTML داخلی کارت فرم ورود
 */
function sc_login_register_render_card($args = []) {
    $logo_url     = sc_get_setting('sc_login_logo_url', '');
    $sc_name_club = sc_get_setting('sc_name_club', '');
    $args = wp_parse_args($args, [
        'card_class' => '',
        'badge_text' => '',
        'title'      => 'ورود به ' . $sc_name_club,
        'subtitle'   => '',
    ]);
    ob_start();
    ?>
    <div class="sc-lr-card <?php echo esc_attr($args['card_class']); ?>">
        <div class="sc-lr-alert sc-lr-alert-error" id="sc-lr-global-error" role="alert" aria-live="polite" style="display:none;">
            <span class="sc-lr-alert-icon" aria-hidden="true"></span>
            <span class="sc-lr-alert-text" id="sc-lr-global-error-text"></span>
        </div>
        <?php if ($logo_url) : ?>
            <div class="sc-lr-logo">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </div>
        <?php endif; ?>
        <?php if ($args['badge_text'] !== '') : ?>
            <div class="sc-lr-badge"><?php echo esc_html($args['badge_text']); ?></div>
        <?php endif; ?>
        <h2 class="sc-lr-title"><?php echo esc_html($args['title']); ?></h2>
        <?php if ($args['subtitle'] !== '') : ?>
            <p class="sc-lr-title-sub"><?php echo esc_html($args['subtitle']); ?></p>
        <?php endif; ?>
        <div class="sc-lr-tabs" role="tablist" aria-label="روش ورود">
            <button type="button" class="sc-lr-tab is-active" id="sc-lr-tab-phone" role="tab" aria-selected="true" aria-controls="sc-lr-panel-phone" data-tab="phone">ورود با موبایل</button>
            <button type="button" class="sc-lr-tab" id="sc-lr-tab-nid" role="tab" aria-selected="false" aria-controls="sc-lr-panel-nid" data-tab="nid">ورود با کد ملی</button>
        </div>
        <div class="sc-lr-form" id="sc-login-register-form">
            <div class="sc-lr-panel" id="sc-lr-panel-phone" data-panel="phone" role="tabpanel" aria-labelledby="sc-lr-tab-phone">
            <div class="sc-lr-step sc-lr-step-phone" data-step="phone" data-panel="phone">
                <p class="sc-lr-desc">لطفا شماره موبایل خود را وارد کنید</p>
                <div class="sc-lr-field" id="sc-lr-phone-field">
                    <input type="tel" class="sc-lr-input" id="sc-lr-phone" placeholder="مثال: ۰۹۱۲۱۲۳۴۵۶۷" autocomplete="tel" maxlength="11" inputmode="numeric" aria-describedby="sc-lr-phone-alert">
                    <div class="sc-lr-field-alert sc-lr-field-alert-error" id="sc-lr-phone-alert" role="alert" aria-live="polite" style="display:none;">
                        <span class="sc-lr-field-alert-icon" aria-hidden="true"></span>
                        <span class="sc-lr-field-alert-text" id="sc-lr-phone-alert-text"></span>
                    </div>
                </div>
                <button type="button" class="sc-lr-btn" id="sc-lr-submit-phone">ورود </button>
            </div>
            <div class="sc-lr-step sc-lr-step-pick-user" data-step="pick-user" data-panel="phone" style="display:none;">
                <p class="sc-lr-desc">چند حساب با این شماره یافت شد. لطفاً حساب مورد نظر را انتخاب کنید:</p>
                <div class="sc-lr-user-list" id="sc-lr-user-list" role="listbox" aria-label="انتخاب حساب کاربری"></div>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-pick-user">بازگشت و تصحیح شماره</button></p>
            </div>
            <div class="sc-lr-step sc-lr-step-exists" data-step="exists" data-panel="phone" style="display:none;">
                <p class="sc-lr-desc sc-lr-selected-user-wrap" id="sc-lr-selected-user-wrap" style="display:none;">ورود به حساب: <strong id="sc-lr-selected-user-label"></strong></p>
                <p class="sc-lr-desc">رمز عبور خود را وارد کنید در صورتی که فراموش کرده اید از کد یک بار مصرف استفاده کنید.</p>
                <input type="password" class="sc-lr-input" id="sc-lr-password" placeholder="رمز عبور" autocomplete="current-password">
                <button type="button" class="sc-lr-btn" id="sc-lr-login-password">ورود</button>
                <button type="button" class="sc-lr-btn sc-lr-btn-outline" id="sc-lr-send-otp">ارسال کد یکبارمصرف</button>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-phone">بازگشت و تصحیح شماره </button></p>
            </div>
            <div class="sc-lr-step sc-lr-step-register" data-step="register" data-panel="phone" style="display:none;">
                <input type="text" class="sc-lr-input" id="sc-lr-first-name" placeholder="نام" autocomplete="given-name">
                <input type="text" class="sc-lr-input" id="sc-lr-last-name" placeholder="نام خانوادگی" autocomplete="family-name">
                <input type="password" class="sc-lr-input" id="sc-lr-reg-password" placeholder="رمز عبور (حداقل ۶ کاراکتر)" autocomplete="new-password">
                <button type="button" class="sc-lr-btn" id="sc-lr-register-submit">ثبت‌نام و دریافت کد</button>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-phone-reg">بازگشت</button></p>
            </div>
            </div>

            <div class="sc-lr-panel" id="sc-lr-panel-nid" data-panel="nid" role="tabpanel" aria-labelledby="sc-lr-tab-nid" style="display:none;">
            <div class="sc-lr-step sc-lr-step-nid" data-step="nid" data-panel="nid">
                <p class="sc-lr-desc">لطفاً کد ملی خود را وارد کنید</p>
                <input type="text" class="sc-lr-input" id="sc-lr-national-id" placeholder="کد ملی (۱۰ رقم)" autocomplete="off" maxlength="12" inputmode="numeric">
                <button type="button" class="sc-lr-btn" id="sc-lr-submit-nid">ادامه</button>
            </div>
            <div class="sc-lr-step sc-lr-step-nid-exists" data-step="nid-exists" data-panel="nid" style="display:none;">
                <p class="sc-lr-desc sc-lr-nid-display-wrap">کد ملی: <strong id="sc-lr-nid-display-exists"></strong></p>
                <p class="sc-lr-desc">رمز عبور خود را وارد کنید. در صورت فراموشی از کد یکبارمصرف استفاده کنید.</p>
                <input type="password" class="sc-lr-input" id="sc-lr-nid-password" placeholder="رمز عبور" autocomplete="current-password">
                <button type="button" class="sc-lr-btn" id="sc-lr-nid-login-password">ورود</button>
                <button type="button" class="sc-lr-btn sc-lr-btn-outline" id="sc-lr-nid-send-otp">ارسال کد یکبارمصرف</button>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-nid">بازگشت و تصحیح کد ملی</button></p>
            </div>
            <div class="sc-lr-step sc-lr-step-nid-register" data-step="nid-register" data-panel="nid" style="display:none;">
                <p class="sc-lr-desc sc-lr-nid-display-wrap">کد ملی: <strong id="sc-lr-nid-display-register"></strong></p>
                <input type="tel" class="sc-lr-input" id="sc-lr-nid-phone" placeholder="شماره موبایل" autocomplete="tel" maxlength="11" inputmode="numeric">
                <input type="text" class="sc-lr-input" id="sc-lr-nid-first-name" placeholder="نام" autocomplete="given-name">
                <input type="text" class="sc-lr-input" id="sc-lr-nid-last-name" placeholder="نام خانوادگی" autocomplete="family-name">
                <input type="password" class="sc-lr-input" id="sc-lr-nid-reg-password" placeholder="رمز عبور (حداقل ۶ کاراکتر)" autocomplete="new-password">
                <button type="button" class="sc-lr-btn" id="sc-lr-nid-register-submit">ثبت‌نام و دریافت کد</button>
                <p class="sc-lr-back"><button type="button" class="sc-lr-link-btn" id="sc-lr-back-nid-reg">بازگشت</button></p>
            </div>
            </div>

            <div class="sc-lr-step sc-lr-step-otp" data-step="otp" style="display:none;">
                <input type="text" class="sc-lr-input" id="sc-lr-otp" placeholder="کد تأیید ۵ رقمی ارسال شده" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                <button type="button" class="sc-lr-btn" id="sc-lr-verify-otp">تأیید و ورود</button>
                <button type="button" class="sc-lr-back-top" id="sc-lr-otp-back" title="بازگشت">→ بازگشت</button>
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
            'err_phone'       => 'شماره موبایل معتبر وارد کنید.',
            'err_phone_empty' => 'لطفاً شماره موبایل را وارد کنید.',
            'err_phone_short' => 'شماره موبایل باید ۱۱ رقم و به صورت ۰۹xxxxxxxxx باشد.',
            'err_phone_prefix'=> 'شماره موبایل باید با ۰۹ شروع شود.',
            'err_phone_invalid'=> 'فرمت شماره موبایل صحیح نیست. نمونه: ۰۹۱۲۱۲۳۴۵۶۷',
            'err_nid'       => 'کد ملی ۱۰ رقمی معتبر وارد کنید.',
            'tab_phone'     => 'ورود با موبایل',
            'tab_nid'       => 'ورود با کد ملی',
            'pick_user'     => 'انتخاب حساب کاربری',
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

function sc_admin_login_shortcode() {
    if (is_user_logged_in()) {
        $redirect = admin_url();
        return '<div class="sc-lr-logged-in"><p>شما وارد شده‌اید.</p><p><a href="' . esc_url($redirect) . '">رفتن به پنل مدیریت</a></p></div>';
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
            'submit'        => 'ورود مدیران',
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
            'err_phone'       => 'شماره موبایل معتبر وارد کنید.',
            'err_phone_empty' => 'لطفاً شماره موبایل را وارد کنید.',
            'err_phone_short' => 'شماره موبایل باید ۱۱ رقم و به صورت ۰۹xxxxxxxxx باشد.',
            'err_phone_prefix'=> 'شماره موبایل باید با ۰۹ شروع شود.',
            'err_phone_invalid'=> 'فرمت شماره موبایل صحیح نیست. نمونه: ۰۹۱۲۱۲۳۴۵۶۷',
            'err_nid'       => 'کد ملی ۱۰ رقمی معتبر وارد کنید.',
            'tab_phone'     => 'ورود با موبایل',
            'tab_nid'       => 'ورود با کد ملی',
            'pick_user'     => 'انتخاب حساب کاربری',
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

    $wrap_classes = 'sc-login-register-wrap sc-lr-admin-login sc-lr-mode-' . esc_attr(substr($display_mode, -1)) . ' sc-lr-align-' . esc_attr($card_align);
    $card_html = sc_login_register_render_card([
        'card_class' => 'sc-lr-card-admin',
        'badge_text' => 'ورود مدیریت',
        'title'      => 'صفحه ورود مدیران',
        'subtitle'   => 'این بخش مخصوص مدیران باشگاه است.',
    ]);

    ob_start();
    if (in_array($display_mode, ['mode2', 'mode3'], true)) :
        ?>
        <div class="<?php echo esc_attr($wrap_classes); ?>" style="<?php echo $style_vars; ?>">
            <div class="sc-lr-split">
                <?php if ($display_mode === 'mode3') : ?>
                    <div class="sc-lr-split-form">
                        <?php echo $card_html; ?>
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
                        <?php echo $card_html; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    else :
        ?>
        <div class="<?php echo esc_attr($wrap_classes); ?>" style="<?php echo $style_vars; ?>">
            <div class="sc-lr-background" aria-hidden="true"></div>
            <?php echo $card_html; ?>
        </div>
        <?php
    endif;

    return ob_get_clean();
}
