<?php
/**
 * QR حضور و غیاب — توکن هش، تولید تصویر با لوگو، اسکن و ثبت AJAX
 */
if (!defined('ABSPATH')) {
    exit;
}

define('SC_ATTENDANCE_QR_PREFIX', 'SC1:');
define('SC_ATTENDANCE_QR_HASH_LENGTH', 64);
define('SC_ATTENDANCE_QR_SHORT_CODE_LENGTH', 7);
define('SC_ATTENDANCE_QR_OTP_EXPIRY', 120);
define('SC_ATTENDANCE_QR_OTP_MAX_ATTEMPTS', 5);

/**
 * @return string
 */
function sc_attendance_qr_codes_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_member_qr_codes';
}

/**
 * @param int $user_id
 * @return bool
 */
function sc_attendance_qr_user_can_manage_codes($user_id = 0) {
    $user_id = $user_id ?: get_current_user_id();
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    if (function_exists('sc_user_has_club_manager_role')) {
        return sc_user_has_club_manager_role($user_id);
    }
    return false;
}

/**
 * @return bool
 */
function sc_attendance_qr_table_exists() {
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return is_string($found) && $found === $table;
}

/**
 * @return int
 */
function sc_attendance_qr_get_max_codes_per_member() {
    return max(1, min(50, (int) sc_get_setting('attendance_qr_max_codes_per_member', '20')));
}

/**
 * @return string
 */
function sc_attendance_qr_short_code_charset() {
    return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
}

/**
 * @return string
 */
function sc_attendance_qr_generate_short_code() {
    if (!sc_attendance_qr_table_exists()) {
        sc_attendance_qr_ensure_db_ready();
    }
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    $charset = sc_attendance_qr_short_code_charset();
    $len = strlen($charset);
    $max = SC_ATTENDANCE_QR_SHORT_CODE_LENGTH;

    do {
        $code = '';
        for ($i = 0; $i < $max; $i++) {
            $code .= $charset[wp_rand(0, $len - 1)];
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE short_code = %s",
            $code
        ));
    } while ($exists > 0);

    return $code;
}

/**
 * @param string $hash
 * @return object|null
 */
function sc_attendance_qr_get_by_hash($hash) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    if ($hash === '') {
        return null;
    }
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE hash = %s LIMIT 1",
        $hash
    ));
}

/**
 * @param int $member_id
 * @return object|null
 */
function sc_attendance_qr_get_active_code($member_id) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return null;
    }
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE member_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
        $member_id
    ));
}

/**
 * @param int $qr_id
 * @return object|null
 */
function sc_attendance_qr_get_code_by_id($qr_id) {
    $qr_id = absint($qr_id);
    if (!$qr_id) {
        return null;
    }
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE id = %d LIMIT 1",
        $qr_id
    ));
}

/**
 * @param int $member_id
 * @return object[]
 */
function sc_attendance_qr_get_codes_for_member($member_id) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return [];
    }
    if (!sc_attendance_qr_ensure_db_ready() || !sc_attendance_qr_table_exists()) {
        return [];
    }
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `$table` WHERE member_id = %d ORDER BY (status = 'active') DESC, id DESC",
        $member_id
    ));
    return is_array($rows) ? $rows : [];
}

/**
 * @param int    $member_id
 * @param string $hash
 * @param int    $user_id
 * @param string $status
 * @return object|false
 */
function sc_attendance_qr_insert_code_record($member_id, $hash, $user_id = 0, $status = 'active') {
    if (!sc_attendance_qr_ensure_db_ready()) {
        return false;
    }
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    $now = current_time('mysql');
    $short_code = sc_attendance_qr_generate_short_code();
    $inserted = $wpdb->insert(
        $table,
        [
            'member_id'   => absint($member_id),
            'hash'        => $hash,
            'short_code'  => $short_code,
            'status'      => in_array($status, ['active', 'inactive', 'disabled'], true) ? $status : 'inactive',
            'created_at'  => $now,
            'created_by'  => absint($user_id),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d']
    );
    if (!$inserted) {
        return false;
    }
    return sc_attendance_qr_get_code_by_id((int) $wpdb->insert_id);
}

/**
 * @param int $member_id
 * @return string|false
 */
function sc_attendance_qr_get_legacy_member_hash($member_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $hash = $wpdb->get_var($wpdb->prepare(
        "SELECT attendance_qr_hash FROM `$table` WHERE id = %d LIMIT 1",
        absint($member_id)
    ));
    if (!is_string($hash)) {
        return false;
    }
    $hash = trim($hash);
    if (strlen($hash) !== SC_ATTENDANCE_QR_HASH_LENGTH) {
        return false;
    }
    return $hash;
}

/**
 * @param int    $member_id
 * @param string $hash
 * @param int    $user_id
 * @return string|false
 */
function sc_attendance_qr_adopt_legacy_hash($member_id, $hash, $user_id = 0) {
    $member_id = absint($member_id);
    $hash = sc_attendance_qr_sanitize_hash($hash);
    if (!$member_id || $hash === '') {
        return false;
    }
    if (!sc_attendance_qr_ensure_db_ready()) {
        return false;
    }

    $existing = sc_attendance_qr_get_by_hash($hash);
    if ($existing) {
        if ((int) $existing->member_id === $member_id && $existing->status === 'active') {
            sc_attendance_qr_sync_member_hash_column($member_id, $hash);
            return $hash;
        }
        if ((int) $existing->member_id === $member_id && $existing->status !== 'active') {
            sc_attendance_qr_set_active((int) $existing->id, $member_id, $user_id);
            return $hash;
        }
    }

    $record = sc_attendance_qr_insert_code_record($member_id, $hash, $user_id, 'active');
    if (!$record) {
        return false;
    }
    sc_attendance_qr_sync_member_hash_column($member_id, $hash);
    return $hash;
}

/**
 * @param int $member_id
 * @param string $hash
 */
function sc_attendance_qr_sync_member_hash_column($member_id, $hash) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $wpdb->update(
        $table,
        [
            'attendance_qr_hash' => $hash,
            'updated_at'         => current_time('mysql'),
        ],
        ['id' => absint($member_id)],
        ['%s', '%s'],
        ['%d']
    );
}

/**
 * مهاجرت هش‌های قدیمی به جدول جدید
 */
function sc_attendance_qr_migrate_legacy_hashes() {
    if (get_option('sc_member_qr_codes_migrated', '0') === '1') {
        return;
    }
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $qr_table = sc_attendance_qr_codes_table();
    $rows = $wpdb->get_results(
        "SELECT id, attendance_qr_hash FROM `$members_table`
         WHERE attendance_qr_hash IS NOT NULL AND attendance_qr_hash <> ''
         AND CHAR_LENGTH(attendance_qr_hash) = " . SC_ATTENDANCE_QR_HASH_LENGTH
    );
    foreach ($rows as $row) {
        $hash = trim((string) $row->attendance_qr_hash);
        if ($hash === '') {
            continue;
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$qr_table` WHERE hash = %s",
            $hash
        ));
        if ($exists > 0) {
            continue;
        }
        sc_attendance_qr_insert_code_record((int) $row->id, $hash, 0, 'active');
    }
    update_option('sc_member_qr_codes_migrated', '1');
}

/**
 * @param int $member_id
 * @param int $user_id
 * @param bool $set_active
 * @return object|false
 */
function sc_attendance_qr_create_code($member_id, $user_id = 0, $set_active = true) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return false;
    }
    sc_attendance_qr_ensure_db_ready();

    $existing_count = count(sc_attendance_qr_get_codes_for_member($member_id));
    if ($existing_count >= sc_attendance_qr_get_max_codes_per_member()) {
        return false;
    }

    do {
        $hash = hash('sha256', wp_generate_password(48, true, true) . wp_salt('auth') . microtime(true) . wp_rand());
        $hash = substr($hash, 0, SC_ATTENDANCE_QR_HASH_LENGTH);
    } while (sc_attendance_qr_get_by_hash($hash));

    $status = $set_active ? 'active' : 'inactive';
    $record = sc_attendance_qr_insert_code_record($member_id, $hash, $user_id, $status);
    if (!$record) {
        return false;
    }

    if ($set_active) {
        sc_attendance_qr_set_active((int) $record->id, $member_id, $user_id);
        $record = sc_attendance_qr_get_code_by_id((int) $record->id);
    }

    return $record ?: false;
}

/**
 * @param int $qr_id
 * @param int $member_id
 * @param int $user_id
 * @return bool
 */
function sc_attendance_qr_set_active($qr_id, $member_id, $user_id = 0) {
    $qr_id = absint($qr_id);
    $member_id = absint($member_id);
    if (!$qr_id || !$member_id) {
        return false;
    }
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = sc_attendance_qr_codes_table();

    $target = sc_attendance_qr_get_code_by_id($qr_id);
    if (!$target || (int) $target->member_id !== $member_id) {
        return false;
    }
    if ($target->status === 'disabled') {
        return false;
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE `$table` SET status = 'inactive' WHERE member_id = %d AND status = 'active' AND id <> %d",
        $member_id,
        $qr_id
    ));
    $wpdb->update(
        $table,
        ['status' => 'active'],
        ['id' => $qr_id],
        ['%s'],
        ['%d']
    );

    sc_attendance_qr_sync_member_hash_column($member_id, $target->hash);
    sc_attendance_qr_clear_member_cache($member_id);
    return true;
}

/**
 * @param int  $qr_id
 * @param bool $disabled
 * @param int  $user_id
 * @return bool
 */
function sc_attendance_qr_set_disabled($qr_id, $disabled, $user_id = 0) {
    $qr_id = absint($qr_id);
    if (!$qr_id) {
        return false;
    }
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    $record = sc_attendance_qr_get_code_by_id($qr_id);
    if (!$record) {
        return false;
    }

    if ($disabled) {
        $was_active = $record->status === 'active';
        $wpdb->update(
            $table,
            [
                'status'      => 'disabled',
                'disabled_at' => current_time('mysql'),
                'disabled_by' => absint($user_id),
            ],
            ['id' => $qr_id],
            ['%s', '%s', '%d'],
            ['%d']
        );
        if ($was_active) {
            $members_table = $wpdb->prefix . 'sc_members';
            $wpdb->update(
                $members_table,
                ['attendance_qr_hash' => null, 'updated_at' => current_time('mysql')],
                ['id' => (int) $record->member_id],
                ['%s', '%s'],
                ['%d']
            );
            $next = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM `$table` WHERE member_id = %d AND status = 'inactive' ORDER BY id DESC LIMIT 1",
                (int) $record->member_id
            ));
            if ($next) {
                sc_attendance_qr_set_active((int) $next->id, (int) $record->member_id, $user_id);
            }
        }
    } else {
        $wpdb->update(
            $table,
            [
                'status'      => 'inactive',
                'disabled_at' => null,
                'disabled_by' => null,
            ],
            ['id' => $qr_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    sc_attendance_qr_clear_member_cache((int) $record->member_id);
    return true;
}

/**
 * @param int $qr_id
 * @param int $user_id
 * @return bool
 */
function sc_attendance_qr_enable_disabled_code($qr_id, $user_id = 0) {
    $qr_id = absint($qr_id);
    if (!$qr_id) {
        return false;
    }
    global $wpdb;
    $table = sc_attendance_qr_codes_table();
    $record = sc_attendance_qr_get_code_by_id($qr_id);
    if (!$record || $record->status !== 'disabled') {
        return false;
    }
    $wpdb->update(
        $table,
        [
            'status'      => 'inactive',
            'disabled_at' => null,
            'disabled_by' => null,
        ],
        ['id' => $qr_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    sc_attendance_qr_clear_member_cache((int) $record->member_id);
    return true;
}

/**
 * @param object $qr_record
 * @return array{id:int,member_id:int,hash:string,short_code:string,status:string,image_url:string,download_url:string,created_at:string,is_active:bool}
 */
function sc_attendance_qr_format_code_for_ui($qr_record) {
    if (!$qr_record) {
        return [];
    }
    $member_id = (int) $qr_record->member_id;
    $qr_id = (int) $qr_record->id;
    return [
        'id'           => $qr_id,
        'member_id'    => $member_id,
        'hash'         => (string) $qr_record->hash,
        'short_code'   => (string) $qr_record->short_code,
        'status'       => (string) $qr_record->status,
        'image_url'    => sc_attendance_qr_get_image_url_for_code($qr_record, 200),
        'download_url' => sc_attendance_qr_get_download_url_for_code($qr_record, 420),
        'created_at'   => (string) $qr_record->created_at,
        'is_active'    => $qr_record->status === 'active',
    ];
}

/**
 * @return string
 */
function sc_attendance_qr_get_regenerate_otp_phone() {
    $phone = trim((string) sc_get_setting('attendance_qr_regenerate_otp_phone', ''));
    if ($phone !== '' && function_exists('sc_login_register_normalize_phone')) {
        return sc_login_register_normalize_phone($phone);
    }
    return $phone;
}

/**
 * @param int $user_id
 * @return array{success:bool,message:string,masked_phone?:string}
 */
function sc_attendance_qr_request_regenerate_otp($user_id = 0) {
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id || !sc_attendance_qr_user_can_manage_codes($user_id)) {
        return ['success' => false, 'message' => 'دسترسی ندارید.'];
    }

    $phone = sc_attendance_qr_get_regenerate_otp_phone();
    if ($phone === '') {
        return ['success' => false, 'message' => 'شماره دریافت OTP در تنظیمات QR تنظیم نشده است.'];
    }

    $rate_key = 'sc_qr_otp_rate_' . $user_id;
    if (get_transient($rate_key)) {
        return ['success' => false, 'message' => 'لطفاً چند ثانیه صبر کنید و دوباره تلاش کنید.'];
    }
    set_transient($rate_key, '1', 30);

    $code = (string) wp_rand(100000, 999999);
    $pattern = trim((string) sc_get_setting('attendance_qr_regenerate_otp_pattern', ''));
    if ($pattern === '') {
        $pattern = trim((string) sc_get_setting('sc_login_otp_pattern', ''));
    }

    $sent = false;
    if ($pattern !== '' && function_exists('sc_send_pattern_sms')) {
        $sent = (bool) sc_send_pattern_sms($phone, $pattern, ['Code' => $code]);
    } elseif (function_exists('sc_login_register_send_otp_sms')) {
        $sent = (bool) sc_login_register_send_otp_sms($phone, $code);
    }

    if (!$sent) {
        return ['success' => false, 'message' => 'ارسال پیامک با خطا مواجه شد.'];
    }

    set_transient('sc_qr_regenerate_otp_' . $user_id, [
        'code'    => $code,
        'attempts'=> 0,
        'phone'   => $phone,
    ], SC_ATTENDANCE_QR_OTP_EXPIRY);

    $masked = strlen($phone) >= 4 ? str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4) : $phone;
    return ['success' => true, 'message' => 'کد تأیید ارسال شد.', 'masked_phone' => $masked];
}

/**
 * @param int    $user_id
 * @param string $otp_code
 * @return bool
 */
function sc_attendance_qr_verify_regenerate_otp($user_id, $otp_code) {
    $user_id = absint($user_id);
    $otp_code = trim((string) $otp_code);
    if (!$user_id || $otp_code === '') {
        return false;
    }
    $key = 'sc_qr_regenerate_otp_' . $user_id;
    $data = get_transient($key);
    if (!is_array($data) || empty($data['code'])) {
        return false;
    }
    $data['attempts'] = isset($data['attempts']) ? (int) $data['attempts'] + 1 : 1;
    if ($data['attempts'] > SC_ATTENDANCE_QR_OTP_MAX_ATTEMPTS) {
        delete_transient($key);
        return false;
    }
    set_transient($key, $data, SC_ATTENDANCE_QR_OTP_EXPIRY);
    if (!hash_equals((string) $data['code'], $otp_code)) {
        return false;
    }
    delete_transient($key);
    return true;
}

/**
 * @return bool
 */
function sc_attendance_qr_is_enabled() {
    if (function_exists('sc_is_pro_feature_attendance_enabled') && !sc_is_pro_feature_attendance_enabled()) {
        return false;
    }
    if (function_exists('sc_is_pro_feature_attendance_qr_enabled') && !sc_is_pro_feature_attendance_qr_enabled()) {
        return false;
    }
    return (int) sc_get_setting('attendance_qr_enabled', '1') === 1;
}

/**
 * مسدود کردن صفحات مدیریت QR وقتی امکانات پرو غیرفعال است
 */
add_action('admin_init', 'sc_attendance_qr_block_disabled_pages');
function sc_attendance_qr_block_disabled_pages() {
    if (!is_admin() || empty($_GET['page'])) {
        return;
    }
    $page = sanitize_text_field(wp_unslash($_GET['page']));
    $qr_pages = ['sc-member-qr-codes', 'sc-reports-attendance-qr'];
    if (!in_array($page, $qr_pages, true)) {
        return;
    }
    $attendance_ok = !function_exists('sc_is_pro_feature_attendance_enabled') || sc_is_pro_feature_attendance_enabled();
    $qr_ok = !function_exists('sc_is_pro_feature_attendance_qr_enabled') || sc_is_pro_feature_attendance_qr_enabled();
    if ($attendance_ok && $qr_ok) {
        return;
    }
    wp_die(
        '<div style="max-width:560px;margin:40px auto;padding:24px;font-family:Tahoma,sans-serif;direction:rtl;text-align:right;">'
        . '<h2>امکان غیرفعال است</h2>'
        . '<p>امکان اسکن QR حضور و غیاب در تنظیمات امکانات پرو غیرفعال شده است.</p></div>',
        'امکان غیرفعال',
        ['response' => 403, 'back_link' => true]
    );
}

/**
 * @param array $members
 * @return array<string,array{id:int,name:string}>
 */
function sc_attendance_qr_build_member_lookup_map($members) {
    $map = [];
    if (!is_array($members)) {
        return $map;
    }
    foreach ($members as $member) {
        $id = is_object($member) ? (int) ($member->id ?? 0) : 0;
        if (!$id) {
            continue;
        }
        $hash = sc_attendance_qr_ensure_member_hash($id);
        if (!$hash) {
            continue;
        }
        $name = trim((string) ($member->first_name ?? '') . ' ' . (string) ($member->last_name ?? ''));
        sc_attendance_qr_scan_lookup_map_add($map, $hash, $id, $name);
    }
    return $map;
}

/**
 * @param array<string,array{id:int,name:string,type:string}> $map
 * @param string $hash
 * @param int    $member_id
 * @param string $name
 */
function sc_attendance_qr_scan_lookup_map_add(array &$map, $hash, $member_id, $name) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    $member_id = absint($member_id);
    if ($hash === '' || !$member_id) {
        return;
    }
    $entry = [
        'id'   => $member_id,
        'name' => trim((string) $name),
        'type' => 'member',
    ];
    $payload = sc_attendance_qr_format_payload($hash);
    $map[$payload] = $entry;
    $map[$hash] = $entry;
    $map[strtoupper($hash)] = $entry;
}

/**
 * نقشهٔ جستجوی سریع برای اسکن — همه QRهای ثبت‌شده (فعال و غیرفعال).
 *
 * @param array{include_inactive_members?:bool} $options
 * @return array<string,array{id:int,name:string,type:string}>
 */
function sc_attendance_qr_build_scan_lookup_map(array $options = []) {
    $include_inactive_members = !empty($options['include_inactive_members']);
    $map = [];

    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_filter = $include_inactive_members ? '1=1' : 'm.is_active = 1';

    if (sc_attendance_qr_table_exists()) {
        $qr_table = sc_attendance_qr_codes_table();
        $rows = $wpdb->get_results(
            "SELECT q.hash, m.id, m.first_name, m.last_name
             FROM `$qr_table` q
             INNER JOIN `$members_table` m ON m.id = q.member_id
             WHERE $member_filter"
        );
        foreach ((array) $rows as $row) {
            $name = trim((string) $row->first_name . ' ' . (string) $row->last_name);
            sc_attendance_qr_scan_lookup_map_add($map, (string) $row->hash, (int) $row->id, $name);
        }
    }

    $legacy_filter = "m.attendance_qr_hash IS NOT NULL AND m.attendance_qr_hash <> ''
        AND CHAR_LENGTH(m.attendance_qr_hash) = " . SC_ATTENDANCE_QR_HASH_LENGTH;
    if (!$include_inactive_members) {
        $legacy_filter .= ' AND m.is_active = 1';
    }
    $legacy_rows = $wpdb->get_results(
        "SELECT m.id, m.first_name, m.last_name, m.attendance_qr_hash AS hash
         FROM `$members_table` m
         WHERE $legacy_filter"
    );
    foreach ((array) $legacy_rows as $row) {
        $name = trim((string) $row->first_name . ' ' . (string) $row->last_name);
        sc_attendance_qr_scan_lookup_map_add($map, (string) $row->hash, (int) $row->id, $name);
    }

    return $map;
}

/**
 * @return string
 */
function sc_attendance_qr_generate_hash() {
    do {
        $hash = hash('sha256', wp_generate_password(48, true, true) . wp_salt('auth') . microtime(true) . wp_rand());
        $hash = substr($hash, 0, SC_ATTENDANCE_QR_HASH_LENGTH);
    } while (sc_attendance_qr_get_by_hash($hash));

    return $hash;
}

/**
 * @param int  $member_id
 * @param bool $force
 * @return string|false
 */
function sc_attendance_qr_ensure_member_hash($member_id, $force = false) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return false;
    }

    if (!sc_attendance_qr_ensure_db_ready()) {
        $legacy = sc_attendance_qr_get_legacy_member_hash($member_id);
        return $legacy ?: false;
    }

    $active = sc_attendance_qr_get_active_code($member_id);
    if (!$force && $active && is_string($active->hash) && strlen(trim($active->hash)) === SC_ATTENDANCE_QR_HASH_LENGTH) {
        sc_attendance_qr_sync_member_hash_column($member_id, trim($active->hash));
        return trim($active->hash);
    }

    if (!$force) {
        $legacy = sc_attendance_qr_get_legacy_member_hash($member_id);
        if ($legacy) {
            $adopted = sc_attendance_qr_adopt_legacy_hash($member_id, $legacy, get_current_user_id());
            if ($adopted) {
                return $adopted;
            }
        }
    }

    if ($force) {
        sc_attendance_qr_clear_member_cache($member_id);
    }

    $record = sc_attendance_qr_create_code($member_id, get_current_user_id(), true);
    if (!$record || empty($record->hash)) {
        $legacy = sc_attendance_qr_get_legacy_member_hash($member_id);
        return $legacy ?: false;
    }

    return trim((string) $record->hash);
}

/**
 * @param string $hash
 * @return object|null
 */
function sc_attendance_qr_get_member_by_hash($hash) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    if ($hash === '') {
        return null;
    }

    $qr = sc_attendance_qr_get_by_hash($hash);
    if (!$qr || $qr->status !== 'active') {
        return null;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE id = %d AND is_active = 1 LIMIT 1",
        (int) $qr->member_id
    ));
}

/**
 * @param string $hash
 * @param array{allow_inactive_member?:bool,allow_inactive_qr?:bool} $options
 * @return array{member:object|null,qr:object|null,scan_code:string}
 */
function sc_attendance_qr_resolve_scan_hash($hash, $options = []) {
    $allow_inactive_member = !empty($options['allow_inactive_member']);
    $allow_inactive_qr = !empty($options['allow_inactive_qr']);

    $hash = sc_attendance_qr_sanitize_hash($hash);
    if ($hash === '') {
        return ['member' => null, 'qr' => null, 'scan_code' => 'invalid_qr'];
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';

    $qr = sc_attendance_qr_get_by_hash($hash);
    if (!$qr) {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$members_table` WHERE attendance_qr_hash = %s LIMIT 1",
            $hash
        ));
        if (!$member) {
            return ['member' => null, 'qr' => null, 'scan_code' => 'unknown_qr'];
        }
        if (function_exists('sc_attendance_qr_adopt_legacy_hash')) {
            sc_attendance_qr_adopt_legacy_hash((int) $member->id, $hash, get_current_user_id());
            $qr = sc_attendance_qr_get_by_hash($hash);
        }
        if (!$qr) {
            if (!$allow_inactive_member && (int) $member->is_active !== 1) {
                return ['member' => $member, 'qr' => null, 'scan_code' => 'unknown_qr'];
            }
            return ['member' => $member, 'qr' => null, 'scan_code' => 'ok'];
        }
    }

    if ($qr->status === 'disabled') {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$members_table` WHERE id = %d LIMIT 1",
            (int) $qr->member_id
        ));
        return ['member' => $member, 'qr' => $qr, 'scan_code' => 'qr_disabled'];
    }

    if ($qr->status === 'inactive' && !$allow_inactive_qr) {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$members_table` WHERE id = %d LIMIT 1",
            (int) $qr->member_id
        ));
        return ['member' => $member, 'qr' => $qr, 'scan_code' => 'qr_inactive'];
    }

    if ($allow_inactive_member) {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$members_table` WHERE id = %d LIMIT 1",
            (int) $qr->member_id
        ));
    } else {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `$members_table` WHERE id = %d AND is_active = 1 LIMIT 1",
            (int) $qr->member_id
        ));
    }
    if (!$member) {
        return ['member' => null, 'qr' => $qr, 'scan_code' => 'unknown_qr'];
    }

    return ['member' => $member, 'qr' => $qr, 'scan_code' => 'ok'];
}

/**
 * @param string $raw
 * @return string
 */
function sc_attendance_qr_sanitize_hash($raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') {
        return '';
    }
    if (stripos($raw, SC_ATTENDANCE_QR_PREFIX) === 0) {
        $raw = substr($raw, strlen(SC_ATTENDANCE_QR_PREFIX));
    }
    $raw = preg_replace('/[^a-f0-9]/i', '', $raw);
    if (strlen($raw) !== SC_ATTENDANCE_QR_HASH_LENGTH) {
        return '';
    }
    return strtolower($raw);
}

/**
 * @param string $hash
 * @return string
 */
function sc_attendance_qr_format_payload($hash) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    return $hash === '' ? '' : SC_ATTENDANCE_QR_PREFIX . $hash;
}

/**
 * @param string $scanned
 * @return string
 */
function sc_attendance_qr_parse_payload($scanned) {
    return sc_attendance_qr_sanitize_hash($scanned);
}

/**
 * @return string
 */
function sc_attendance_qr_get_logo_url() {
    $custom = trim((string) sc_get_setting('attendance_qr_logo_url', ''));
    if ($custom !== '') {
        return esc_url_raw($custom);
    }
    $club = trim((string) sc_get_setting('sc_club_logo_url', ''));
    if ($club !== '') {
        return esc_url_raw($club);
    }
    return '';
}

/**
 * @return int
 */
function sc_attendance_qr_get_logo_size_percent() {
    $size = (int) sc_get_setting('attendance_qr_logo_size_percent', '22');
    return max(12, min(30, $size));
}

/**
 * @return int
 */
function sc_attendance_qr_get_scan_cooldown_ms() {
    return max(300, min(5000, (int) sc_get_setting('attendance_qr_scan_cooldown_ms', '300')));
}

/**
 * @param string $type success|error|duplicate|not_in_course|debt_warning|debt_blocked|disabled
 * @return string
 */
function sc_attendance_qr_sound_file_basename($type) {
    $map = [
        'success'       => 'qr-success',
        'error'         => 'qr-error',
        'duplicate'     => 'qr-duplicate',
        'not_in_course' => 'qr-not-in-course',
        'debt_warning'  => 'qr-debt-warning',
        'debt_blocked'  => 'qr-debt-blocked',
        'disabled'      => 'qr-disabled',
    ];
    return isset($map[$type]) ? $map[$type] : 'qr-' . $type;
}

/**
 * @param string $type success|error|duplicate|not_in_course|debt_warning|debt_blocked|disabled
 * @return string
 */
function sc_attendance_qr_get_sound_url($type) {
    $basename = sc_attendance_qr_sound_file_basename($type);
    $defaults = [
        'success'       => SC_ASSETS_URL . 'sounds/qr-success.mp3',
        'error'         => SC_ASSETS_URL . 'sounds/qr-error.mp3',
        'duplicate'     => SC_ASSETS_URL . 'sounds/qr-duplicate.mp3',
        'not_in_course' => SC_ASSETS_URL . 'sounds/qr-not-in-course.mp3',
        'debt_warning'  => SC_ASSETS_URL . 'sounds/qr-debt-warning.mp3',
        'debt_blocked'  => SC_ASSETS_URL . 'sounds/qr-debt-blocked.mp3',
        'disabled'      => SC_ASSETS_URL . 'sounds/qr-disabled.mp3',
    ];
    $key = 'attendance_qr_sound_' . $type . '_url';
    $custom = trim((string) sc_get_setting($key, ''));
    if ($custom !== '') {
        return esc_url_raw($custom);
    }
    if (!isset($defaults[$type])) {
        return '';
    }
    $mp3_path = SC_ASSETS_DIR . 'sounds/' . $basename . '.mp3';
    if (file_exists($mp3_path) && filesize($mp3_path) > 100) {
        return $defaults[$type];
    }
    $wav_url = str_replace('.mp3', '.wav', $defaults[$type]);
    $wav_path = SC_ASSETS_DIR . 'sounds/' . $basename . '.wav';
    if (file_exists($wav_path) && filesize($wav_path) > 100) {
        return $wav_url;
    }
    return $defaults[$type];
}

/**
 * @param string $url
 * @return bool
 */
function sc_attendance_qr_is_valid_sound_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return true;
    }
    $path = wp_parse_url($url, PHP_URL_PATH);
    if (is_string($path) && preg_match('/\.mp3$/i', $path)) {
        return true;
    }
    $attachment_id = attachment_url_to_postid($url);
    if ($attachment_id > 0) {
        $mime = get_post_mime_type($attachment_id);
        return in_array($mime, ['audio/mpeg', 'audio/mp3'], true);
    }
    return false;
}

/**
 * @param string $wav_path
 * @param string $mp3_path
 * @return bool
 */
function sc_attendance_qr_convert_wav_to_mp3($wav_path, $mp3_path) {
    if (!file_exists($wav_path) || !function_exists('exec')) {
        return false;
    }
    $ffmpeg = '';
    foreach (['ffmpeg', 'C:\\ffmpeg\\bin\\ffmpeg.exe', 'C:\\xampp\\ffmpeg\\bin\\ffmpeg.exe'] as $candidate) {
        $cmd = stripos(PHP_OS, 'WIN') === 0
            ? 'where ' . escapeshellarg($candidate) . ' 2>nul'
            : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null';
        @exec($cmd, $out, $code);
        if ($code === 0 && !empty($out[0])) {
            $ffmpeg = trim((string) $out[0]);
            break;
        }
        if (is_file($candidate)) {
            $ffmpeg = $candidate;
            break;
        }
    }
    if ($ffmpeg === '') {
        return false;
    }
    $cmd = escapeshellarg($ffmpeg)
        . ' -y -i ' . escapeshellarg($wav_path)
        . ' -codec:a libmp3lame -b:a 128k '
        . escapeshellarg($mp3_path)
        . ' 2>&1';
    @exec($cmd, $unused, $exit_code);
    return $exit_code === 0 && is_file($mp3_path) && filesize($mp3_path) > 100;
}

/**
 * @return bool
 */
function sc_attendance_qr_show_in_dashboard() {
    return (int) sc_get_setting('attendance_qr_show_dashboard', '1') === 1;
}

/**
 * @param string $context public|admin
 * @return bool
 */
function sc_attendance_qr_should_show_member_card($context = 'public') {
    if (!sc_attendance_qr_is_enabled()) {
        return false;
    }
    if ($context === 'public' && !sc_attendance_qr_show_in_dashboard()) {
        return false;
    }
    return true;
}

/**
 * اطمینان از وجود ستون attendance_qr_hash (مهاجرت فقط admin_init قبلاً اجرا می‌شد).
 *
 * @return bool
 */
function sc_attendance_qr_ensure_db_ready() {
    static $running = false;
    if ($running) {
        return sc_attendance_qr_table_exists();
    }

    $hash_column_ready = get_option('sc_attendance_qr_hash_column_added', '0') === '1';
    $table_ready = get_option('sc_member_qr_codes_table_added', '0') === '1' && sc_attendance_qr_table_exists();
    if ($hash_column_ready && $table_ready) {
        return true;
    }

    $running = true;

    if (function_exists('sc_update_database')) {
        sc_update_database();
    }

    if (!sc_attendance_qr_table_exists() && function_exists('sc_create_member_qr_codes_table')) {
        sc_create_member_qr_codes_table();
        update_option('sc_member_qr_codes_table_added', '1');
    }

    if (sc_attendance_qr_table_exists()
        && get_option('sc_member_qr_codes_migrated', '0') !== '1'
        && function_exists('sc_attendance_qr_migrate_legacy_hashes')) {
        sc_attendance_qr_migrate_legacy_hashes();
    }

    if (sc_attendance_qr_table_exists()) {
        update_option('sc_member_qr_codes_table_added', '1');
    }

    $running = false;

    return get_option('sc_attendance_qr_hash_column_added', '0') === '1'
        && sc_attendance_qr_table_exists();
}

/**
 * @param resource $src
 * @param int      $new_w
 * @param int      $new_h
 * @return resource|false
 */
function sc_attendance_qr_scale_image($src, $new_w, $new_h) {
    if (!is_resource($src) && !($src instanceof GdImage)) {
        return false;
    }
    $new_w = max(1, (int) $new_w);
    $new_h = max(1, (int) $new_h);
    if (function_exists('imagescale')) {
        return imagescale($src, $new_w, $new_h);
    }
    $src_w = imagesx($src);
    $src_h = imagesy($src);
    if (!$src_w || !$src_h) {
        return false;
    }
    $dst = imagecreatetruecolor($new_w, $new_h);
    if (!$dst) {
        return false;
    }
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);
    return $dst;
}

/**
 * @param int $member_id
 * @param int $size
 * @return array{member_id:int,member_name:string,image_url:string,download_url:string,error:string}
 */
function sc_attendance_qr_get_member_card_data($member_id, $size = 420) {
    $member_id = absint($member_id);
    $card = [
        'member_id'     => $member_id,
        'member_name'   => '',
        'image_url'     => '',
        'download_url'  => '',
        'short_code'    => '',
        'error'         => '',
        'codes'         => [],
        'can_manage'    => sc_attendance_qr_user_can_manage_codes(),
    ];

    if (!$member_id) {
        $card['error'] = 'شناسه بازیکن نامعتبر است.';
        return $card;
    }

    sc_attendance_qr_ensure_db_ready();

    global $wpdb;
    $table  = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        $card['error'] = 'بازیکن یافت نشد.';
        return $card;
    }

    $card['member_name'] = trim((string) $member->first_name . ' ' . (string) $member->last_name);

    if (!function_exists('imagecreatetruecolor')) {
        $card['error'] = 'افزونه GD در سرور فعال نیست. با مدیر سایت تماس بگیرید.';
        return $card;
    }

    $hash = sc_attendance_qr_ensure_member_hash($member_id);
    if (!$hash) {
        $card['error'] = 'امکان تولید کد اختصاصی وجود ندارد. لطفاً با پشتیبانی تماس بگیرید.';
        return $card;
    }

    $active = sc_attendance_qr_get_active_code($member_id);
    if ($active) {
        $card['short_code'] = (string) $active->short_code;
        $card['image_url']    = sc_attendance_qr_get_image_url_for_code($active, $size);
        $card['download_url'] = sc_attendance_qr_get_download_url_for_code($active, max($size, 420));
    } else {
        $card['image_url']    = sc_attendance_qr_get_image_url($member_id, $size);
        $card['download_url'] = sc_attendance_qr_get_download_url($member_id, max($size, 420));
    }

    foreach (sc_attendance_qr_get_codes_for_member($member_id) as $code_row) {
        $card['codes'][] = sc_attendance_qr_format_code_for_ui($code_row);
    }

    return $card;
}

/**
 * @return string
 */
function sc_attendance_qr_get_cache_base_dir() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit($upload['basedir']) . 'sc-qr-cache/members/';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    return $dir;
}

/**
 * @param object|string $code_or_hash QR row or hash string
 * @param int           $size
 * @return string
 */
function sc_attendance_qr_get_cache_file_path_for_hash($hash, $size = 420) {
    $hash = sc_attendance_qr_sanitize_hash($hash);
    $size = max(200, min(800, absint($size)));
    if ($hash === '') {
        return '';
    }

    $cache_key = md5(
        $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    );

    return sc_attendance_qr_get_cache_base_dir() . substr($hash, 0, 12) . '-' . $cache_key . '.png';
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_cache_file_path($member_id, $size = 420) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return '';
    }

    $active = sc_attendance_qr_get_active_code($member_id);
    if ($active && !empty($active->hash)) {
        return sc_attendance_qr_get_cache_file_path_for_hash($active->hash, $size);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $hash  = $wpdb->get_var($wpdb->prepare(
        "SELECT attendance_qr_hash FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!is_string($hash) || strlen(trim($hash)) !== SC_ATTENDANCE_QR_HASH_LENGTH) {
        return '';
    }

    return sc_attendance_qr_get_cache_file_path_for_hash(trim($hash), $size);
}

/**
 * @param int $member_id
 */
function sc_attendance_qr_clear_member_cache($member_id) {
    $member_id = absint($member_id);
    if (!$member_id) {
        return;
    }
    $dir  = sc_attendance_qr_get_cache_base_dir();
    foreach (sc_attendance_qr_get_codes_for_member($member_id) as $code_row) {
        if (empty($code_row->hash)) {
            continue;
        }
        $prefix = substr(sc_attendance_qr_sanitize_hash($code_row->hash), 0, 12);
        $glob = glob($dir . $prefix . '-*.png');
        if (!is_array($glob)) {
            continue;
        }
        foreach ($glob as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    $glob_legacy = glob($dir . $member_id . '-*.png');
    if (is_array($glob_legacy)) {
        foreach ($glob_legacy as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

/**
 * بارگذاری phpqrcode با تنظیمات سریع‌تر
 */
function sc_attendance_qr_prepare_library() {
    if (class_exists('QRcode')) {
        return;
    }
    if (!defined('QR_CACHEABLE')) {
        define('QR_CACHEABLE', true);
    }
    if (!defined('QR_CACHE_DIR')) {
        $upload    = wp_upload_dir();
        $cache_dir = trailingslashit($upload['basedir']) . 'sc-qr-cache/lib/';
        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        define('QR_CACHE_DIR', trailingslashit($cache_dir));
    }
    if (!defined('QR_FIND_BEST_MASK')) {
        define('QR_FIND_BEST_MASK', false);
    }
    if (!defined('QR_FIND_FROM_RANDOM')) {
        define('QR_FIND_FROM_RANDOM', false);
    }
    require_once SC_INCLUDES_DIR . 'lib/phpqrcode/qrlib.php';
}

/**
 * @param string $path
 * @param string $disposition inline|attachment
 * @param int    $member_id
 */
function sc_attendance_qr_send_png_file($path, $disposition, $member_id) {
    if (!is_file($path)) {
        status_header(404);
        exit;
    }

    $filesize = (int) filesize($path);
    if ($filesize <= 0) {
        status_header(500);
        exit;
    }

    $etag = '"' . md5_file($path) . '"';
    header('Content-Type: image/png');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: public, max-age=604800, immutable');
    header('ETag: ' . $etag);

    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';
    if ($if_none_match !== '' && ($if_none_match === $etag || $if_none_match === trim($etag, '"'))) {
        status_header(304);
        exit;
    }

    $filename = 'attendance-qr-' . absint($member_id) . '.png';
    if ($disposition === 'attachment') {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }

    readfile($path);
    exit;
}

/**
 * @param int    $member_id
 * @param string $context public|admin
 * @param int    $size
 */
function sc_attendance_qr_render_member_card($member_id, $context = 'public', $size = 420) {
    if (!sc_attendance_qr_should_show_member_card($context)) {
        return;
    }

    $display_size = $context === 'public' ? 280 : 320;
    $sc_qr_card = sc_attendance_qr_get_member_card_data($member_id, $display_size);
    $sc_qr_card['context'] = $context;
    $partial = SC_TEMPLATES_DIR . 'partials/member-qr-card.php';
    if (file_exists($partial)) {
        include $partial;
    }
}

/**
 * @param string $hash
 * @param int    $size
 * @return string|false PNG binary
 */
function sc_attendance_qr_render_png_binary_for_hash($hash, $size = 420) {
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $hash = sc_attendance_qr_sanitize_hash($hash);
    if ($hash === '') {
        return false;
    }

    $size = max(200, min(800, absint($size)));
    $cache_path = sc_attendance_qr_get_cache_file_path_for_hash($hash, $size);
    if ($cache_path !== '' && is_file($cache_path) && filesize($cache_path) > 100) {
        $cached = @file_get_contents($cache_path);
        if ($cached) {
            return $cached;
        }
    }

    $payload = sc_attendance_qr_format_payload($hash);
    if ($payload === '') {
        return false;
    }

    sc_attendance_qr_prepare_library();

    $tmp_file = wp_tempnam('sc-attendance-qr-');
    if (!$tmp_file) {
        return false;
    }

    $module_size = max(3, min(8, (int) round($size / 56)));
    QRcode::png($payload, $tmp_file, QR_ECLEVEL_H, $module_size, 2);
    $qr_binary = @file_get_contents($tmp_file);
    @unlink($tmp_file);

    if (!$qr_binary) {
        return false;
    }

    $qr_img = @imagecreatefromstring($qr_binary);
    if (!$qr_img) {
        return false;
    }

    $qr_w = imagesx($qr_img);
    $qr_h = imagesy($qr_img);
    $canvas = imagecreatetruecolor($qr_w, $qr_h);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $qr_w, $qr_h, $white);
    imagecopy($canvas, $qr_img, 0, 0, 0, 0, $qr_w, $qr_h);
    imagedestroy($qr_img);

    $logo_url = sc_attendance_qr_get_logo_url();
    if ($logo_url !== '') {
        $logo_path = sc_attendance_qr_resolve_logo_path();
        $logo_img  = $logo_path ? sc_attendance_qr_load_image($logo_path) : null;
        if ($logo_img) {
            $pct = sc_attendance_qr_get_logo_size_percent() / 100;
            $logo_box = (int) round(min($qr_w, $qr_h) * $pct);
            $pad = (int) round($logo_box * 0.12);
            $inner = max(8, $logo_box - ($pad * 2));
            $bg_box = imagecreatetruecolor($logo_box, $logo_box);
            $bg_white = imagecolorallocate($bg_box, 255, 255, 255);
            imagefilledrectangle($bg_box, 0, 0, $logo_box, $logo_box, $bg_white);
            $resized = sc_attendance_qr_scale_image($logo_img, $inner, $inner);
            if ($resized) {
                imagecopy($bg_box, $resized, $pad, $pad, 0, 0, $inner, $inner);
                imagedestroy($resized);
            }
            $x = (int) (($qr_w - $logo_box) / 2);
            $y = (int) (($qr_h - $logo_box) / 2);
            imagecopy($canvas, $bg_box, $x, $y, 0, 0, $logo_box, $logo_box);
            imagedestroy($bg_box);
            imagedestroy($logo_img);
        }
    }

    if ($size > 0 && ($qr_w !== $size || $qr_h !== $size)) {
        $scaled = sc_attendance_qr_scale_image($canvas, $size, $size);
        if ($scaled) {
            imagedestroy($canvas);
            $canvas = $scaled;
        }
    }

    ob_start();
    imagepng($canvas, null, 6);
    $png = ob_get_clean();
    imagedestroy($canvas);

    if ($png && $cache_path !== '') {
        @file_put_contents($cache_path, $png);
    }

    return $png ?: false;
}

/**
 * @param int $member_id
 * @param int $size
 * @return string|false PNG binary
 */
function sc_attendance_qr_render_png_binary($member_id, $size = 420) {
    $active = sc_attendance_qr_get_active_code(absint($member_id));
    if ($active && !empty($active->hash)) {
        return sc_attendance_qr_render_png_binary_for_hash($active->hash, $size);
    }

    $hash = sc_attendance_qr_ensure_member_hash($member_id);
    if (!$hash) {
        return false;
    }
    return sc_attendance_qr_render_png_binary_for_hash($hash, $size);
}

/**
 * @param string $url
 * @return string
 */
function sc_attendance_qr_url_to_local_path($url) {
    $upload = wp_upload_dir();
    if (!empty($upload['baseurl']) && strpos($url, $upload['baseurl']) === 0) {
        return str_replace($upload['baseurl'], $upload['basedir'], $url);
    }
    $site_url = site_url();
    if (strpos($url, $site_url) === 0) {
        $rel = ltrim(substr($url, strlen($site_url)), '/');
        $path = ABSPATH . $rel;
        return file_exists($path) ? $path : '';
    }
    return '';
}

/**
 * @return string|false مسیر محلی لوگو
 */
function sc_attendance_qr_resolve_logo_path() {
    $logo_url = sc_attendance_qr_get_logo_url();
    if ($logo_url === '') {
        return false;
    }

    $local = sc_attendance_qr_url_to_local_path($logo_url);
    if ($local && file_exists($local)) {
        return $local;
    }

    $cache_dir = trailingslashit(wp_upload_dir()['basedir']) . 'sc-qr-cache/';
    if (!is_dir($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    $cache_file = $cache_dir . 'logo-' . md5($logo_url) . '.img';
    if (file_exists($cache_file) && filesize($cache_file) > 32) {
        return $cache_file;
    }

    $logo_response = wp_remote_get($logo_url, ['timeout' => 4, 'sslverify' => false]);
    if (is_wp_error($logo_response) || wp_remote_retrieve_response_code($logo_response) !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($logo_response);
    if ($body === '') {
        return false;
    }

    @file_put_contents($cache_file, $body);
    return file_exists($cache_file) ? $cache_file : false;
}

/**
 * @param string $path
 * @return resource|false
 */
function sc_attendance_qr_load_image($path) {
    if (!file_exists($path)) {
        return false;
    }
    $info = @getimagesize($path);
    if (!$info) {
        return false;
    }
    switch ($info['mime']) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/gif':
            return @imagecreatefromgif($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
        default:
            return false;
    }
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_data_uri($member_id, $size = 420) {
    $png = sc_attendance_qr_render_png_binary($member_id, $size);
    if (!$png) {
        return '';
    }
    return 'data:image/png;base64,' . base64_encode($png);
}

/**
 * @param object $code_row
 * @param int    $size
 * @return string
 */
function sc_attendance_qr_get_image_url_for_code($code_row, $size = 280) {
    if (!$code_row || empty($code_row->hash)) {
        return '';
    }
    $qr_id = (int) ($code_row->id ?? 0);
    $size  = max(200, min(800, absint($size)));
    $hash  = sc_attendance_qr_sanitize_hash($code_row->hash);
    $version = substr(md5(
        $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    ), 0, 8);

    $args = [
        'action' => 'sc_attendance_qr_image',
        'qr_id'  => $qr_id,
        'size'   => $size,
        'nonce'  => wp_create_nonce('sc_attendance_qr_image_qr_' . $qr_id),
        'v'      => $version,
    ];
    if (!$qr_id) {
        unset($args['qr_id']);
        $args['member_id'] = (int) ($code_row->member_id ?? 0);
        $args['nonce'] = wp_create_nonce('sc_attendance_qr_image_' . $args['member_id']);
    }
    return add_query_arg($args, admin_url('admin-ajax.php'));
}

/**
 * @param object $code_row
 * @param int    $size
 * @return string
 */
function sc_attendance_qr_get_download_url_for_code($code_row, $size = 420) {
    if (!$code_row || empty($code_row->hash)) {
        return '';
    }
    $qr_id = (int) ($code_row->id ?? 0);
    $size  = max(200, min(800, absint($size)));
    $hash  = sc_attendance_qr_sanitize_hash($code_row->hash);
    $version = substr(md5(
        $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    ), 0, 8);

    $args = [
        'action' => 'sc_attendance_qr_download',
        'qr_id'  => $qr_id,
        'size'   => $size,
        'nonce'  => wp_create_nonce('sc_attendance_qr_download_qr_' . $qr_id),
        'v'      => $version,
    ];
    if (!$qr_id) {
        unset($args['qr_id']);
        $args['member_id'] = (int) ($code_row->member_id ?? 0);
        $args['nonce'] = wp_create_nonce('sc_attendance_qr_download_' . $args['member_id']);
    }
    return add_query_arg($args, admin_url('admin-ajax.php'));
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_image_url($member_id, $size = 280) {
    $member_id = absint($member_id);
    $size      = max(200, min(800, absint($size)));
    $active = sc_attendance_qr_get_active_code($member_id);
    if ($active) {
        return sc_attendance_qr_get_image_url_for_code($active, $size);
    }
    $hash = sc_attendance_qr_ensure_member_hash($member_id);
    $version   = substr(md5(
        (string) $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    ), 0, 8);

    return add_query_arg([
        'action'    => 'sc_attendance_qr_image',
        'member_id' => $member_id,
        'size'      => $size,
        'nonce'     => wp_create_nonce('sc_attendance_qr_image_' . $member_id),
        'v'         => $version,
    ], admin_url('admin-ajax.php'));
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_download_url($member_id, $size = 420) {
    $member_id = absint($member_id);
    $active = sc_attendance_qr_get_active_code($member_id);
    if ($active) {
        return sc_attendance_qr_get_download_url_for_code($active, $size);
    }
    $size      = max(200, min(800, absint($size)));
    $hash      = sc_attendance_qr_ensure_member_hash($member_id);
    $version   = substr(md5(
        (string) $hash . '|'
        . (string) sc_attendance_qr_get_logo_url() . '|'
        . (string) sc_attendance_qr_get_logo_size_percent() . '|'
        . $size
    ), 0, 8);

    return add_query_arg([
        'action'    => 'sc_attendance_qr_download',
        'member_id' => $member_id,
        'size'      => $size,
        'nonce'     => wp_create_nonce('sc_attendance_qr_download_' . $member_id),
        'v'         => $version,
    ], admin_url('admin-ajax.php'));
}

/**
 * @param int $member_id
 * @param int $size
 * @return string
 */
function sc_attendance_qr_get_export_data_uri($member_id, $size = 320) {
    if (!function_exists('sc_attendance_qr_is_enabled') || !sc_attendance_qr_is_enabled()) {
        return '';
    }
    return sc_attendance_qr_get_data_uri($member_id, $size);
}

/**
 * @param int $user_id
 * @param int $member_id
 * @return bool
 */
function sc_attendance_qr_user_can_view_member_qr($user_id, $member_id) {
    $user_id = absint($user_id);
    $member_id = absint($member_id);
    if (!$member_id) {
        return false;
    }
    if (current_user_can('manage_options') || current_user_can('sc_manage_attendance')) {
        return true;
    }
    if (!$user_id) {
        return false;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $owner = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM `$table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    return $owner > 0 && $owner === $user_id;
}

/**
 * ثبت حضور یک بازیکن (منطق مشترک با attendance-add.php)
 *
 * @param array $args
 * @return array{success:bool,code:string,message:string,member_name?:string,attendance_id?:int,is_new?:bool}
 */
function sc_attendance_qr_register_present(array $args) {
    global $wpdb;

    $member_id = absint($args['member_id'] ?? 0);
    $course_id = absint($args['course_id'] ?? 0);
    $chapter_name = sanitize_text_field((string) ($args['chapter_name'] ?? ''));
    $group_name = sanitize_text_field((string) ($args['group_name'] ?? ''));
    $attendance_date = sanitize_text_field((string) ($args['attendance_date'] ?? ''));
    $current_user_id = absint($args['user_id'] ?? get_current_user_id());
    $status = 'present';

    if (!$member_id || !$course_id || $attendance_date === '') {
        return ['success' => false, 'code' => 'invalid', 'message' => 'اطلاعات ناقص است.'];
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name FROM `$members_table` WHERE id = %d AND is_active = 1 LIMIT 1",
        $member_id
    ));
    if (!$member) {
        return ['success' => false, 'code' => 'member_not_found', 'message' => 'بازیکن یافت نشد.'];
    }
    $member_name = trim($member->first_name . ' ' . $member->last_name);
    $member_debt = 0;
    if (function_exists('debt_user')) {
        $debt_data = debt_user($member_id);
        $member_debt = isset($debt_data[0]) ? floatval($debt_data[0]) : 0;
    }

    if (function_exists('sc_attendance_member_debt_blocked') && sc_attendance_member_debt_blocked($member_id)) {
        return [
            'success' => false,
            'code'    => 'debt_blocked',
            'message' => 'به دلیل بدهی بالاتر از سقف مجاز، ثبت حضور امکان‌پذیر نیست.',
            'member_name' => $member_name,
        ];
    }

    $current_coach_id = 0;
    $current_is_coach = function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance();
    if ($current_is_coach) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $current_coach_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
    }

    if ($current_coach_id > 0) {
        if (function_exists('sc_validate_coach_attendance_date_access')) {
            $coach_date_access = sc_validate_coach_attendance_date_access(
                $current_coach_id,
                $course_id,
                $attendance_date,
                $chapter_name,
                $group_name
            );
            if (empty($coach_date_access['allowed'])) {
                return [
                    'success' => false,
                    'code'    => isset($coach_date_access['code']) ? (string) $coach_date_access['code'] : 'attendance_locked',
                    'message' => isset($coach_date_access['message']) ? (string) $coach_date_access['message'] : 'در این تاریخ امکان ثبت حضور و غیاب برای مربی وجود ندارد.',
                    'member_name' => $member_name,
                ];
            }
        }

        $member_scope = function_exists('sc_attendance_member_scope_sql')
            ? sc_attendance_member_scope_sql($course_id, $current_coach_id, $chapter_name)
            : [
                'coach_scope_where' => '(coach_id = %d OR coach_id IS NULL OR coach_id = 0)',
                'chapter_where'     => '',
                'prepare_args'      => [$current_coach_id],
            ];
        $group_filter = function_exists('sc_attendance_member_group_filter_sql')
            ? sc_attendance_member_group_filter_sql($group_name, $course_id)
            : ['sql' => '', 'args' => []];

        $can_touch_sql = "SELECT COUNT(*) FROM $member_courses_table mc
             WHERE mc.member_id = %d AND mc.course_id = %d AND mc.status = 'active'
               AND {$member_scope['coach_scope_where']}
               {$member_scope['chapter_where']}
               {$group_filter['sql']}
               AND (
                 mc.course_status_flags IS NULL OR mc.course_status_flags = ''
                 OR (
                   mc.course_status_flags NOT LIKE %s
                   AND mc.course_status_flags NOT LIKE %s
                   AND mc.course_status_flags NOT LIKE %s
                 )
               )";
        $can_touch_args = array_merge(
            [$member_id, $course_id],
            $member_scope['prepare_args'],
            $group_filter['args'],
            ['%paused%', '%completed%', '%canceled%']
        );
        if (!(int) $wpdb->get_var($wpdb->prepare($can_touch_sql, $can_touch_args))) {
            return [
                'success' => false,
                'code'    => 'not_in_course',
                'message' => 'این بازیکن در دوره/گروه انتخاب‌شده ثبت‌نام فعال ندارد.',
                'member_name' => $member_name,
            ];
        }

        if ($chapter_name !== '') {
            $wpdb->query($wpdb->prepare(
                "UPDATE $member_courses_table SET coach_id = %d, chapter = %s, updated_at = %s
                 WHERE member_id = %d AND course_id = %d AND (coach_id IS NULL OR coach_id = 0)
                   AND (chapter = %s OR chapter IS NULL OR chapter = '')",
                $current_coach_id, $chapter_name, current_time('mysql'),
                $member_id, $course_id, $chapter_name
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $member_courses_table SET coach_id = %d, updated_at = %s
                 WHERE member_id = %d AND course_id = %d AND (coach_id IS NULL OR coach_id = 0)",
                $current_coach_id, current_time('mysql'), $member_id, $course_id
            ));
        }
    } else {
        $group_filter = function_exists('sc_attendance_member_group_filter_sql')
            ? sc_attendance_member_group_filter_sql($group_name, $course_id)
            : ['sql' => '', 'args' => []];
        $active_sql = "SELECT COUNT(*) FROM $member_courses_table mc
            WHERE mc.member_id = %d AND mc.course_id = %d AND mc.status = 'active' {$group_filter['sql']}";
        $active_args = array_merge([$member_id, $course_id], $group_filter['args']);
        if (!(int) $wpdb->get_var($wpdb->prepare($active_sql, $active_args))) {
            return [
                'success' => false,
                'code'    => 'not_in_course',
                'message' => 'این بازیکن در دوره انتخاب‌شده ثبت‌نام فعال ندارد.',
                'member_name' => $member_name,
            ];
        }
    }

    $course_row = $wpdb->get_row($wpdb->prepare(
        "SELECT title, price_per_session, course_type FROM $courses_table WHERE id = %d LIMIT 1",
        $course_id
    ));
    $course_title = $course_row ? $course_row->title : '';
    $price_per_session = $course_row ? floatval($course_row->price_per_session) : 0;
    $attendance_date_shamsi = function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only($attendance_date)
        : $attendance_date;

    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $attendances_table WHERE member_id = %d AND course_id = %d AND attendance_date = %s AND schedule_slot_id = 0",
        $member_id, $course_id, $attendance_date
    ));

    if ($existing > 0) {
        $current_record = $wpdb->get_row($wpdb->prepare(
            "SELECT status, user_id FROM $attendances_table WHERE id = %d",
            $existing
        ));
        if ($current_record && $current_record->status === 'present') {
            return [
                'success'     => true,
                'code'        => 'duplicate',
                'message'     => 'قبلاً به عنوان حاضر ثبت شده است.',
                'member_name' => $member_name,
                'attendance_id' => $existing,
                'course_title' => $course_title,
                'is_new'      => false,
                'has_debt'    => $member_debt > 0,
                'debt_amount'  => $member_debt,
            ];
        }
        if ($current_record && $current_record->status === 'excused') {
            return [
                'success' => false,
                'code'    => 'excused_locked',
                'message' => 'وضعیت غیبت مجاز — امکان تغییر وجود ندارد.',
                'member_name' => $member_name,
            ];
        }

        $update_data = [
            'status'        => $status,
            'record_method' => 'qr',
            'updated_at'    => current_time('mysql'),
        ];
        if ($current_record && empty($current_record->user_id)) {
            $update_data['user_id'] = $current_user_id;
        }
        if ($current_record && $current_record->status === 'absent') {
            $update_data['absence_sms_sent'] = 0;
        }
        $update_formats = [];
        foreach (array_keys($update_data) as $update_key) {
            $update_formats[] = in_array($update_key, ['user_id', 'absence_sms_sent'], true) ? '%d' : '%s';
        }
        $wpdb->update($attendances_table, $update_data, ['id' => $existing], $update_formats, ['%d']);

        if (function_exists('sc_is_private_course') && $course_row && sc_is_private_course($course_row) && function_exists('sc_private_sync_session_with_attendance')) {
            sc_private_sync_session_with_attendance($member_id, $course_id, $attendance_date, $status);
        }

        if (function_exists('sc_process_coach_salary_attendance_notifications')) {
            sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title);
        }

        return [
            'success'       => true,
            'code'          => 'updated',
            'message'       => $member_debt > 0 ? 'حضور ثبت شد؛ بازیکن بدهی دارد.' : 'حضور با موفقیت ثبت شد.',
            'member_name'   => $member_name,
            'attendance_id' => $existing,
            'course_title'  => $course_title,
            'is_new'        => false,
            'has_debt'      => $member_debt > 0,
            'debt_amount'    => $member_debt,
        ];
    }

    $need_deduct = sc_is_member_team($member_id) && $price_per_session > 0
        && function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet();
    if ($need_deduct) {
        $deduct = sc_deduct_wallet_session_fee($member_id, $price_per_session, $course_title, $attendance_date_shamsi);
        if (empty($deduct['success'])) {
            return [
                'success' => false,
                'code'    => 'wallet_failed',
                'message' => isset($deduct['message']) ? $deduct['message'] : 'موجودی کیف پول ناکافی.',
                'member_name' => $member_name,
            ];
        }
    }

    $inserted = $wpdb->insert(
        $attendances_table,
        [
            'member_id'        => $member_id,
            'course_id'        => $course_id,
            'schedule_slot_id' => 0,
            'attendance_date'  => $attendance_date,
            'status'           => $status,
            'user_id'          => $current_user_id,
            'record_method'    => 'qr',
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
    );

    if (!$inserted) {
        return ['success' => false, 'code' => 'db_error', 'message' => 'خطا در ثبت حضور.'];
    }

    $new_id = (int) $wpdb->insert_id;
    sc_decrease_member_session($member_id, $course_id);

    if (function_exists('sc_is_private_course') && $course_row && sc_is_private_course($course_row) && function_exists('sc_private_sync_session_with_attendance')) {
        sc_private_sync_session_with_attendance($member_id, $course_id, $attendance_date, $status);
    }

    if (function_exists('sc_process_coach_salary_attendance_notifications')) {
        sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title);
    }

    if (function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'attendance', $course_id, 'حضور QR — «' . $member_name . '» در «' . $course_title . '»', null, [
            'member_id' => $member_id,
            'attendance_date' => $attendance_date,
            'via' => 'qr_scan',
        ]);
    }

    return [
        'success'       => true,
        'code'          => 'created',
        'message'       => $member_debt > 0 ? 'حضور ثبت شد؛ بازیکن بدهی دارد.' : 'حضور با موفقیت ثبت شد.',
        'member_name'   => $member_name,
        'attendance_id' => $new_id,
        'course_title'  => $course_title,
        'is_new'        => true,
        'has_debt'      => $member_debt > 0,
        'debt_amount'    => $member_debt,
    ];
}

/**
 * تولید هش برای همه اعضای فاقد QR
 */
function sc_attendance_qr_backfill_member_hashes() {
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $ids = $wpdb->get_col(
        "SELECT id FROM `$table` WHERE attendance_qr_hash IS NULL OR attendance_qr_hash = '' OR CHAR_LENGTH(attendance_qr_hash) <> " . SC_ATTENDANCE_QR_HASH_LENGTH
    );
    foreach ($ids as $id) {
        sc_attendance_qr_ensure_member_hash((int) $id);
    }
}

add_action('init', 'sc_attendance_qr_ensure_default_sounds_on_init', 5);
function sc_attendance_qr_ensure_default_sounds_on_init() {
    sc_attendance_qr_ensure_default_sounds();
}

add_action('init', 'sc_attendance_qr_maybe_backfill_hashes', 20);
function sc_attendance_qr_maybe_backfill_hashes() {
    sc_attendance_qr_ensure_db_ready();
    sc_attendance_qr_ensure_default_sounds();
    if (get_option('sc_attendance_qr_hashes_backfilled', '0') === '1') {
        return;
    }
    sc_attendance_qr_backfill_member_hashes();
    update_option('sc_attendance_qr_hashes_backfilled', '1');
}

/**
 * تولید فایل‌های صوتی پیش‌فرض در صورت نبود
 */
function sc_attendance_qr_ensure_default_sounds() {
    $dir = SC_ASSETS_DIR . 'sounds/';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    $map = [
        'qr-success.mp3'       => [880, 180, 0.35],
        'qr-error.mp3'         => [220, 350, 0.40],
        'qr-duplicate.mp3'     => [660, 120, 0.35],
        'qr-not-in-course.mp3' => [440, 220, 0.38],
        'qr-debt-warning.mp3'  => [520, 180, 0.38],
        'qr-debt-blocked.mp3'  => [180, 420, 0.42],
        'qr-disabled.mp3'      => [300, 280, 0.40],
    ];
    foreach ($map as $file => $cfg) {
        $mp3_path = $dir . $file;
        if (file_exists($mp3_path) && filesize($mp3_path) > 100) {
            continue;
        }
        $wav_path = preg_replace('/\.mp3$/i', '.wav', $mp3_path);
        $wav = sc_attendance_qr_build_wav((int) $cfg[0], (int) $cfg[1], (float) $cfg[2]);
        if (!$wav) {
            continue;
        }
        file_put_contents($wav_path, $wav);
        sc_attendance_qr_convert_wav_to_mp3($wav_path, $mp3_path);
    }
}

/**
 * @return string
 */
function sc_attendance_qr_build_wav($frequency, $duration_ms, $volume = 0.35) {
    $sample_rate = 44100;
    $samples = (int) ($sample_rate * $duration_ms / 1000);
    $data = '';
    for ($i = 0; $i < $samples; $i++) {
        $sample = (int) (32767 * $volume * sin(2 * M_PI * $frequency * $i / $sample_rate));
        $data .= pack('v', $sample);
    }
    $bits = 16;
    $channels = 1;
    $byte_rate = $sample_rate * $channels * $bits / 8;
    $block_align = $channels * $bits / 8;
    $chunk_size = 36 + strlen($data);
    return pack('a4Va4a4VvvVVvv', 'RIFF', $chunk_size, 'WAVE', 'fmt ', 16, 1, $channels, $sample_rate, $byte_rate, $block_align, $bits)
        . pack('a4V', 'data', strlen($data)) . $data;
}

add_action('sc_member_created', 'sc_attendance_qr_on_member_created', 10, 1);
add_action('sc_member_updated', 'sc_attendance_qr_on_member_created', 10, 1);
function sc_attendance_qr_on_member_created($member_id) {
    sc_attendance_qr_ensure_member_hash(absint($member_id));
}

/**
 * @param int $member_id
 * @return string
 */
function sc_attendance_qr_get_member_short_code($member_id) {
    $active = sc_attendance_qr_get_active_code(absint($member_id));
    return $active ? (string) $active->short_code : '';
}

/**
 * @param int $member_id
 * @return string
 */
function sc_attendance_qr_get_member_all_codes_text($member_id) {
    $labels = [
        'active'   => 'فعال',
        'inactive' => 'غیرفعال',
        'disabled' => 'غیرفعال موقت',
    ];
    $parts = [];
    foreach (sc_attendance_qr_get_codes_for_member($member_id) as $row) {
        $status = isset($labels[$row->status]) ? $labels[$row->status] : $row->status;
        $parts[] = $row->short_code . ' (' . $status . ')';
    }
    return implode('، ', $parts);
}

/**
 * @param array $args search, status, member_id, paged, per_page
 * @return array{items:object[],total:int}
 */
function sc_attendance_qr_query_all_codes(array $args = []) {
    sc_attendance_qr_ensure_db_ready();
    global $wpdb;
    $qr_table = sc_attendance_qr_codes_table();
    $members_table = $wpdb->prefix . 'sc_members';

    $search = trim((string) ($args['search'] ?? ''));
    $status = sanitize_text_field((string) ($args['status'] ?? ''));
    $member_id = absint($args['member_id'] ?? 0);
    $paged = max(1, absint($args['paged'] ?? 1));
    $per_page = max(10, min(100, absint($args['per_page'] ?? 25)));
    $offset = ($paged - 1) * $per_page;

    $where = ['1=1'];
    $prepare = [];

    if ($member_id > 0) {
        $where[] = 'q.member_id = %d';
        $prepare[] = $member_id;
    }
    if ($status !== '' && in_array($status, ['active', 'inactive', 'disabled'], true)) {
        $where[] = 'q.status = %s';
        $prepare[] = $status;
    }
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(q.short_code LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s OR CONCAT(m.first_name, " ", m.last_name) LIKE %s)';
        $prepare[] = $like;
        $prepare[] = $like;
        $prepare[] = $like;
        $prepare[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM `$qr_table` q INNER JOIN `$members_table` m ON m.id = q.member_id WHERE $where_sql";
    $list_sql = "SELECT q.*, m.first_name, m.last_name FROM `$qr_table` q INNER JOIN `$members_table` m ON m.id = q.member_id WHERE $where_sql ORDER BY q.id DESC LIMIT %d OFFSET %d";

    if (!empty($prepare)) {
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare));
        $items = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($prepare, [$per_page, $offset])));
    } else {
        $total = (int) $wpdb->get_var($count_sql);
        $items = $wpdb->get_results($wpdb->prepare($list_sql, $per_page, $offset));
    }

    return [
        'items' => is_array($items) ? $items : [],
        'total' => $total,
    ];
}

add_action('wp_ajax_sc_attendance_qr_scan', 'sc_ajax_attendance_qr_scan');
function sc_ajax_attendance_qr_scan() {
    check_ajax_referer('sc_attendance_qr_scan', 'nonce');

    if (!sc_attendance_qr_is_enabled()) {
        wp_send_json_error(['message' => 'اسکن QR غیرفعال است.', 'code' => 'disabled']);
    }
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.', 'code' => 'forbidden']);
    }

    $hash = sc_attendance_qr_parse_payload(wp_unslash($_POST['qr_payload'] ?? ''));
    if ($hash === '') {
        wp_send_json_error(['message' => 'کد QR نامعتبر است.', 'code' => 'invalid_qr']);
    }

    $resolved = sc_attendance_qr_resolve_scan_hash($hash);
    if ($resolved['scan_code'] === 'qr_disabled') {
        $member_name = $resolved['member']
            ? trim((string) $resolved['member']->first_name . ' ' . (string) $resolved['member']->last_name)
            : '';
        wp_send_json_error([
            'message'     => 'این QR موقتاً غیرفعال است.',
            'code'        => 'qr_disabled',
            'member_name' => $member_name,
        ]);
    }
    if ($resolved['scan_code'] === 'qr_inactive') {
        $member_name = $resolved['member']
            ? trim((string) $resolved['member']->first_name . ' ' . (string) $resolved['member']->last_name)
            : '';
        wp_send_json_error([
            'message'     => 'این QR دیگر فعال نیست.',
            'code'        => 'qr_inactive',
            'member_name' => $member_name,
        ]);
    }
    if ($resolved['scan_code'] !== 'ok' || !$resolved['member']) {
        wp_send_json_error(['message' => 'بازیکن مرتبط با این QR یافت نشد.', 'code' => 'unknown_qr']);
    }

    $member = $resolved['member'];
    $course_id = absint($_POST['course_id'] ?? 0);
    $attendance_date = sanitize_text_field(wp_unslash($_POST['attendance_date'] ?? ''));
    $chapter_name = sanitize_text_field(wp_unslash($_POST['chapter_name'] ?? ''));
    $group_name = sanitize_text_field(wp_unslash($_POST['group_name'] ?? ''));

    $result = sc_attendance_qr_register_present([
        'member_id'       => (int) $member->id,
        'course_id'       => $course_id,
        'chapter_name'    => $chapter_name,
        'group_name'      => $group_name,
        'attendance_date' => $attendance_date,
    ]);

    if (empty($result['success'])) {
        wp_send_json_error([
            'message'     => $result['message'],
            'code'        => $result['code'],
            'member_name' => $result['member_name'] ?? '',
        ]);
    }

    $attendance_id = isset($result['attendance_id']) ? (int) $result['attendance_id'] : 0;
    $photos = ['rear' => '', 'front' => '', 'rear_url' => '', 'front_url' => ''];
    if ($attendance_id && in_array($result['code'] ?? '', ['created', 'updated'], true)
        && function_exists('sc_qr_scan_photo_handle_post_for_record')) {
        $photos = sc_qr_scan_photo_handle_post_for_record('attendance', $attendance_id);
    }

    wp_send_json_success([
        'message'       => $result['message'],
        'code'          => $result['code'],
        'member_name'   => $result['member_name'],
        'member_id'     => (int) $member->id,
        'attendance_id' => $attendance_id,
        'course_title'  => isset($result['course_title']) ? $result['course_title'] : '',
        'is_new'        => !empty($result['is_new']),
        'has_debt'      => !empty($result['has_debt']),
        'debt_amount'   => isset($result['debt_amount']) ? floatval($result['debt_amount']) : 0,
        'snapshot'      => function_exists('sc_qr_scan_photo_is_enabled') && sc_qr_scan_photo_is_enabled(),
        'photo_url'     => $photos['rear_url'],
        'photo_front_url' => $photos['front_url'],
    ]);
}

add_action('wp_ajax_sc_attendance_qr_image', 'sc_ajax_attendance_qr_image');
add_action('wp_ajax_sc_attendance_qr_download', 'sc_ajax_attendance_qr_download');

/**
 * @param int         $member_id
 * @param int         $size
 * @param string      $disposition inline|attachment
 * @param object|null $code_row
 */
function sc_attendance_qr_output_png_response($member_id, $size = 420, $disposition = 'inline', $code_row = null) {
    $member_id = absint($member_id);
    if (!$member_id || !sc_attendance_qr_user_can_view_member_qr(get_current_user_id(), $member_id)) {
        status_header(403);
        exit;
    }

    $size = max(200, min(800, absint($size)));
    if (!$code_row) {
        $code_row = sc_attendance_qr_get_active_code($member_id);
    }

    if ($code_row && !empty($code_row->hash)) {
        $cache_path = sc_attendance_qr_get_cache_file_path_for_hash($code_row->hash, $size);
        if ($cache_path !== '' && is_file($cache_path) && filesize($cache_path) > 100) {
            sc_attendance_qr_send_png_file($cache_path, $disposition, $member_id);
        }
        $png = sc_attendance_qr_render_png_binary_for_hash($code_row->hash, $size);
    } else {
        $cache_path = sc_attendance_qr_get_cache_file_path($member_id, $size);
        if ($cache_path !== '' && is_file($cache_path) && filesize($cache_path) > 100) {
            sc_attendance_qr_send_png_file($cache_path, $disposition, $member_id);
        }
        $png = sc_attendance_qr_render_png_binary($member_id, $size);
    }

    if (!$png) {
        status_header(500);
        exit;
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: public, max-age=604800, immutable');
    $filename = 'attendance-qr-' . $member_id . '.png';
    if ($disposition === 'attachment') {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }
    echo $png;
    exit;
}

function sc_ajax_attendance_qr_image() {
    $qr_id = absint($_GET['qr_id'] ?? 0);
    if ($qr_id > 0) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_attendance_qr_image_qr_' . $qr_id)) {
            status_header(403);
            exit;
        }
        $code_row = sc_attendance_qr_get_code_by_id($qr_id);
        if (!$code_row) {
            status_header(404);
            exit;
        }
        sc_attendance_qr_output_png_response((int) $code_row->member_id, absint($_GET['size'] ?? 420), 'inline', $code_row);
    }

    $member_id = absint($_GET['member_id'] ?? 0);
    if (!$member_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_attendance_qr_image_' . $member_id)) {
        status_header(403);
        exit;
    }
    sc_attendance_qr_output_png_response($member_id, absint($_GET['size'] ?? 420), 'inline');
}

function sc_ajax_attendance_qr_download() {
    $qr_id = absint($_GET['qr_id'] ?? 0);
    if ($qr_id > 0) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_attendance_qr_download_qr_' . $qr_id)) {
            status_header(403);
            exit;
        }
        $code_row = sc_attendance_qr_get_code_by_id($qr_id);
        if (!$code_row) {
            status_header(404);
            exit;
        }
        sc_attendance_qr_output_png_response((int) $code_row->member_id, absint($_GET['size'] ?? 420), 'attachment', $code_row);
    }

    $member_id = absint($_GET['member_id'] ?? 0);
    if (!$member_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_attendance_qr_download_' . $member_id)) {
        status_header(403);
        exit;
    }
    sc_attendance_qr_output_png_response($member_id, absint($_GET['size'] ?? 420), 'attachment');
}

add_action('wp_ajax_sc_attendance_qr_request_regenerate_otp', 'sc_ajax_attendance_qr_request_regenerate_otp');
function sc_ajax_attendance_qr_request_regenerate_otp() {
    check_ajax_referer('sc_attendance_qr_admin', 'nonce');
    $result = sc_attendance_qr_request_regenerate_otp(get_current_user_id());
    if (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message']]);
    }
    wp_send_json_success([
        'message'      => $result['message'],
        'masked_phone' => $result['masked_phone'] ?? '',
    ]);
}

add_action('wp_ajax_sc_attendance_qr_regenerate', 'sc_ajax_attendance_qr_regenerate');
function sc_ajax_attendance_qr_regenerate() {
    check_ajax_referer('sc_attendance_qr_regenerate', 'nonce');
    $member_id = absint($_POST['member_id'] ?? 0);
    $otp_code = sanitize_text_field(wp_unslash($_POST['otp_code'] ?? ''));
    if (!$member_id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر.']);
    }
    if (!sc_attendance_qr_user_can_manage_codes()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    if (!sc_attendance_qr_verify_regenerate_otp(get_current_user_id(), $otp_code)) {
        wp_send_json_error(['message' => 'کد تأیید نامعتبر یا منقضی شده است.']);
    }

    $record = sc_attendance_qr_create_code($member_id, get_current_user_id(), true);
    if (!$record) {
        wp_send_json_error(['message' => 'خطا در تولید QR جدید. ممکن است سقف تعداد QR پر شده باشد.']);
    }

    $card = sc_attendance_qr_get_member_card_data($member_id, 420);
    wp_send_json_success([
        'message'      => 'کد QR جدید تولید و فعال شد.',
        'image_url'    => $card['image_url'] ?? sc_attendance_qr_get_image_url($member_id),
        'download_url' => $card['download_url'] ?? sc_attendance_qr_get_download_url($member_id),
        'short_code'   => (string) $record->short_code,
        'codes'        => $card['codes'] ?? [],
    ]);
}

add_action('wp_ajax_sc_attendance_qr_set_active', 'sc_ajax_attendance_qr_set_active');
function sc_ajax_attendance_qr_set_active() {
    check_ajax_referer('sc_attendance_qr_admin', 'nonce');
    if (!sc_attendance_qr_user_can_manage_codes()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $qr_id = absint($_POST['qr_id'] ?? 0);
    $member_id = absint($_POST['member_id'] ?? 0);
    if (!$qr_id || !$member_id) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
    }
    if (!sc_attendance_qr_set_active($qr_id, $member_id, get_current_user_id())) {
        wp_send_json_error(['message' => 'امکان فعال‌سازی این QR وجود ندارد.']);
    }
    wp_send_json_success([
        'message' => 'QR فعال شد.',
        'codes'   => array_map('sc_attendance_qr_format_code_for_ui', sc_attendance_qr_get_codes_for_member($member_id)),
        'card'    => sc_attendance_qr_get_member_card_data($member_id, 420),
    ]);
}

add_action('wp_ajax_sc_attendance_qr_toggle_disabled', 'sc_ajax_attendance_qr_toggle_disabled');
function sc_ajax_attendance_qr_toggle_disabled() {
    check_ajax_referer('sc_attendance_qr_admin', 'nonce');
    if (!sc_attendance_qr_user_can_manage_codes()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $qr_id = absint($_POST['qr_id'] ?? 0);
    $member_id = absint($_POST['member_id'] ?? 0);
    $action = sanitize_text_field(wp_unslash($_POST['toggle_action'] ?? ''));
    if (!$qr_id || !$member_id) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
    }

    $ok = false;
    if ($action === 'disable') {
        $ok = sc_attendance_qr_set_disabled($qr_id, true, get_current_user_id());
    } elseif ($action === 'enable') {
        $ok = sc_attendance_qr_enable_disabled_code($qr_id, get_current_user_id());
    } else {
        wp_send_json_error(['message' => 'عملیات نامعتبر.']);
    }

    if (!$ok) {
        wp_send_json_error(['message' => 'عملیات انجام نشد.']);
    }

    wp_send_json_success([
        'message' => $action === 'disable' ? 'QR موقتاً غیرفعال شد.' : 'QR از حالت غیرفعال خارج شد.',
        'codes'   => array_map('sc_attendance_qr_format_code_for_ui', sc_attendance_qr_get_codes_for_member($member_id)),
        'card'    => sc_attendance_qr_get_member_card_data($member_id, 420),
    ]);
}

add_action('wp_ajax_sc_attendance_qr_list', 'sc_ajax_attendance_qr_list');
function sc_ajax_attendance_qr_list() {
    check_ajax_referer('sc_attendance_qr_admin', 'nonce');
    $member_id = absint($_POST['member_id'] ?? $_GET['member_id'] ?? 0);
    if (!$member_id || !sc_attendance_qr_user_can_view_member_qr(get_current_user_id(), $member_id)) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    wp_send_json_success([
        'codes' => array_map('sc_attendance_qr_format_code_for_ui', sc_attendance_qr_get_codes_for_member($member_id)),
        'can_manage' => sc_attendance_qr_user_can_manage_codes(),
    ]);
}

/**
 * حذف رکورد(های) حضور ثبت‌شده با QR — فقط خود رکورد (+ فایل عکس اسکن).
 *
 * @param int[] $ids
 * @return array{deleted:int, failed:int}
 */
function sc_attendance_qr_delete_report_records(array $ids) {
    global $wpdb;
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    $result = ['deleted' => 0, 'failed' => 0];
    if (!$ids) {
        return $result;
    }

    if (function_exists('sc_qr_scan_photo_ensure_columns')) {
        sc_qr_scan_photo_ensure_columns();
    }

    $table = $wpdb->prefix . 'sc_attendances';
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $sql = "SELECT id, scan_photo, scan_photo_front
         FROM `$table`
         WHERE id IN ($placeholders) AND record_method = %s";
    $args = array_merge($ids, ['qr']);
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args));

    if (empty($rows)) {
        $result['failed'] = count($ids);
        return $result;
    }

    foreach ($rows as $row) {
        $id = (int) $row->id;
        if (!empty($row->scan_photo) && function_exists('sc_qr_scan_photo_delete_file')) {
            sc_qr_scan_photo_delete_file($row->scan_photo);
        }
        if (!empty($row->scan_photo_front) && function_exists('sc_qr_scan_photo_delete_file')) {
            sc_qr_scan_photo_delete_file($row->scan_photo_front);
        }
        $deleted = $wpdb->delete($table, ['id' => $id, 'record_method' => 'qr'], ['%d', '%s']);
        if ($deleted) {
            $result['deleted']++;
        } else {
            $result['failed']++;
        }
    }

    return $result;
}
