<?php
/**
 * QR پرسنل (مربی، منشی، مدیران) — ذخیره per user_id
 */
if (!defined('ABSPATH')) {
    exit;
}

define('SC_STAFF_QR_PREFIX', 'SC2:');

/**
 * @return string
 */
function sc_staff_qr_codes_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_staff_qr_codes';
}

/**
 * @return string[]
 */
function sc_staff_qr_get_eligible_roles() {
    return ['administrator', 'club_coach', 'system_manager', 'coach', 'secretary', 'shop_manager', 'accountantt'];
}

/**
 * @param int $user_id
 * @return bool
 */
function sc_staff_qr_user_is_eligible($user_id) {
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }
    if (function_exists('sc_user_profile_get_member_id_by_user_id')) {
        $member_id = (int) sc_user_profile_get_member_id_by_user_id($user_id);
        if ($member_id > 0) {
            return false;
        }
    }
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    foreach (sc_staff_qr_get_eligible_roles() as $role) {
        if (in_array($role, (array) $user->roles, true)) {
            return true;
        }
    }
    return false;
}

/**
 * @return bool
 */
function sc_staff_qr_table_exists() {
    global $wpdb;
    $table = sc_staff_qr_codes_table();
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return is_string($found) && $found === $table;
}

/**
 * @return bool
 */
function sc_staff_qr_ensure_db_ready() {
    static $running = false;
    if ($running) {
        return sc_staff_qr_table_exists();
    }
    if (sc_staff_qr_table_exists()) {
        return true;
    }
    $running = true;
    if (function_exists('sc_create_staff_qr_codes_table')) {
        sc_create_staff_qr_codes_table();
    }
    if (function_exists('sc_update_database')) {
        sc_update_database();
    }
    $running = false;
    return sc_staff_qr_table_exists();
}

/**
 * @param string $hash
 * @return string
 */
function sc_staff_qr_format_payload($hash) {
    if (!function_exists('sc_attendance_qr_sanitize_hash')) {
        return '';
    }
    $hash = sc_attendance_qr_sanitize_hash($hash);
    return $hash === '' ? '' : SC_STAFF_QR_PREFIX . $hash;
}

/**
 * @param string $raw
 * @return string
 */
function sc_staff_qr_parse_payload($raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') {
        return '';
    }
    if (stripos($raw, SC_STAFF_QR_PREFIX) === 0) {
        $raw = substr($raw, strlen(SC_STAFF_QR_PREFIX));
    }
    return function_exists('sc_attendance_qr_sanitize_hash') ? sc_attendance_qr_sanitize_hash($raw) : '';
}

/**
 * @return string
 */
function sc_staff_qr_generate_short_code() {
    sc_staff_qr_ensure_db_ready();
    global $wpdb;
    $table = sc_staff_qr_codes_table();
    $charset = function_exists('sc_attendance_qr_short_code_charset')
        ? sc_attendance_qr_short_code_charset()
        : 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $len = strlen($charset);
    do {
        $code = '';
        for ($i = 0; $i < 7; $i++) {
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
function sc_staff_qr_get_by_hash($hash) {
    $hash = sc_staff_qr_parse_payload($hash);
    if ($hash === '' || !sc_staff_qr_ensure_db_ready()) {
        return null;
    }
    global $wpdb;
    $table = sc_staff_qr_codes_table();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE hash = %s LIMIT 1",
        $hash
    ));
}

/**
 * @param int $user_id
 * @return object|null
 */
function sc_staff_qr_get_active_code($user_id) {
    $user_id = absint($user_id);
    if (!$user_id || !sc_staff_qr_ensure_db_ready()) {
        return null;
    }
    global $wpdb;
    $table = sc_staff_qr_codes_table();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$table` WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
        $user_id
    ));
}

/**
 * @param int $user_id
 * @return string|false
 */
function sc_staff_qr_ensure_user_hash($user_id) {
    $user_id = absint($user_id);
    if (!$user_id || !sc_staff_qr_user_is_eligible($user_id) || !sc_staff_qr_ensure_db_ready()) {
        return false;
    }
    $active = sc_staff_qr_get_active_code($user_id);
    if ($active && !empty($active->hash)) {
        return trim((string) $active->hash);
    }

    global $wpdb;
    $table = sc_staff_qr_codes_table();
    do {
        $hash = hash('sha256', wp_generate_password(48, true, true) . wp_salt('auth') . microtime(true) . wp_rand());
        $hash = substr($hash, 0, SC_ATTENDANCE_QR_HASH_LENGTH);
    } while (sc_staff_qr_get_by_hash($hash));

    $inserted = $wpdb->insert(
        $table,
        [
            'user_id'     => $user_id,
            'hash'        => $hash,
            'short_code'  => sc_staff_qr_generate_short_code(),
            'status'      => 'active',
            'created_at'  => current_time('mysql'),
            'created_by'  => get_current_user_id(),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d']
    );
    if (!$inserted) {
        return false;
    }
    return $hash;
}

/**
 * @param int $user_id
 * @return string
 */
function sc_staff_qr_get_display_name($user_id) {
    $user = get_userdata(absint($user_id));
    if (!$user) {
        return '';
    }
    $name = trim((string) $user->display_name);
    if ($name !== '') {
        return $name;
    }
    return trim((string) $user->first_name . ' ' . (string) $user->last_name);
}

/**
 * @param int $user_id
 * @param int $size
 * @return string|false
 */
function sc_staff_qr_render_png_binary($user_id, $size = 420) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('sc_attendance_qr_prepare_library')) {
        return false;
    }
    $hash = sc_staff_qr_ensure_user_hash($user_id);
    if (!$hash) {
        return false;
    }
    $payload = sc_staff_qr_format_payload($hash);
    if ($payload === '') {
        return false;
    }
    sc_attendance_qr_prepare_library();
    $tmp_file = wp_tempnam('sc-staff-qr-');
    if (!$tmp_file) {
        return false;
    }
    $size = max(200, min(800, absint($size)));
    $module_size = max(3, min(8, (int) round($size / 56)));
    QRcode::png($payload, $tmp_file, QR_ECLEVEL_H, $module_size, 2);
    $png = @file_get_contents($tmp_file);
    @unlink($tmp_file);
    return $png ?: false;
}

/**
 * @param int $user_id
 * @param int $size
 * @return string
 */
function sc_staff_qr_get_image_url($user_id, $size = 280) {
    $user_id = absint($user_id);
    $size = max(200, min(800, absint($size)));
    return add_query_arg([
        'action'  => 'sc_staff_qr_image',
        'user_id' => $user_id,
        'size'    => $size,
        'nonce'   => wp_create_nonce('sc_staff_qr_image_' . $user_id),
        'v'       => substr(md5((string) $user_id . $size), 0, 8),
    ], admin_url('admin-ajax.php'));
}

/**
 * @param int $user_id
 * @param int $size
 * @return array
 */
function sc_staff_qr_get_user_card_data($user_id, $size = 280) {
    $user_id = absint($user_id);
    $card = [
        'user_id'    => $user_id,
        'user_name'  => sc_staff_qr_get_display_name($user_id),
        'image_url'  => '',
        'short_code' => '',
        'error'      => '',
        'can_manage' => false,
    ];
    if (!$user_id) {
        $card['error'] = 'شناسه کاربر نامعتبر است.';
        return $card;
    }
    if (!sc_staff_qr_user_is_eligible($user_id)) {
        $card['error'] = 'این کاربر جزو پرسنل واجد QR نیست (بازیکن‌ها QR جداگانه در پروفایل بازیکن دارند).';
        return $card;
    }
    if (!function_exists('imagecreatetruecolor')) {
        $card['error'] = 'افزونه GD در سرور فعال نیست.';
        return $card;
    }
    $hash = sc_staff_qr_ensure_user_hash($user_id);
    if (!$hash) {
        $card['error'] = 'امکان تولید QR پرسنل وجود ندارد.';
        return $card;
    }
    $active = sc_staff_qr_get_active_code($user_id);
    if ($active) {
        $card['short_code'] = (string) $active->short_code;
    }
    $card['image_url'] = sc_staff_qr_get_image_url($user_id, $size);
    return $card;
}

/**
 * @param int $user_id
 * @return bool
 */
function sc_staff_qr_user_has_coach_role($user_id) {
    $user = get_userdata(absint($user_id));
    return $user instanceof WP_User && in_array('coach', (array) $user->roles, true);
}

/**
 * نقش‌هایی که QR خود را در داشبورد می‌بینند (بدون پروفایل اختصاصی افزونه)
 *
 * @return string[]
 */
function sc_staff_qr_dashboard_panel_roles() {
    return ['secretary', 'shop_manager', 'accountantt'];
}

/**
 * @param int $user_id
 * @return bool
 */
function sc_staff_qr_should_show_on_user_edit($user_id) {
    $user_id = absint($user_id);
    if (!$user_id || sc_staff_qr_user_has_coach_role($user_id)) {
        return false;
    }
    return sc_staff_qr_user_is_eligible($user_id);
}

/**
 * @param int $user_id
 * @return bool
 */
function sc_staff_qr_user_can_view_image($user_id) {
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }
    if ((int) get_current_user_id() === $user_id && sc_staff_qr_user_is_eligible($user_id)) {
        return true;
    }
    if (current_user_can('edit_user', $user_id)) {
        return true;
    }
    return function_exists('sc_attendance_qr_user_can_manage_codes') && sc_attendance_qr_user_can_manage_codes();
}

/**
 * @param int    $user_id
 * @param string $context
 * @param int    $size
 */
function sc_staff_qr_render_user_card($user_id, $context = 'user-edit', $size = 280) {
    if (!sc_staff_qr_user_is_eligible($user_id)) {
        return;
    }
    if ($context === 'user-edit' && !sc_staff_qr_should_show_on_user_edit($user_id)) {
        return;
    }
    $sc_staff_qr_card = sc_staff_qr_get_user_card_data($user_id, $size);
    $sc_staff_qr_card['context'] = $context;
    $sc_staff_qr_card['readonly'] = true;
    $partial = SC_TEMPLATES_DIR . 'partials/staff-qr-card.php';
    if (file_exists($partial)) {
        include $partial;
    }
}

/**
 * QR تردد کاربر جاری در پنل خودش (فقط مشاهده)
 *
 * @param string $context dashboard|profile|coach-my-profile|coach-edit
 */
function sc_staff_qr_render_panel_for_current_user($context = 'dashboard') {
    $user_id = get_current_user_id();
    if (!$user_id || !sc_staff_qr_user_is_eligible($user_id)) {
        return;
    }

    if ($context === 'dashboard') {
        if (sc_staff_qr_user_has_coach_role($user_id)) {
            return;
        }
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        $show = false;
        foreach (sc_staff_qr_dashboard_panel_roles() as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                $show = true;
                break;
            }
        }
        if (!$show && (user_can($user_id, 'manage_options') || user_can($user_id, 'club_coach') || user_can($user_id, 'system_manager'))) {
            $show = true;
        }
        if (!$show) {
            return;
        }
    }

    if ($context === 'profile' && sc_staff_qr_user_has_coach_role($user_id)) {
        return;
    }

    echo '<div class="sc-staff-qr-panel-wrap sc-staff-qr-panel-wrap--' . esc_attr($context) . '">';
    echo '<h2 class="sc-staff-qr-panel-title">QR تردد من</h2>';
    echo '<p class="description">این QR برای ثبت تردد شما استفاده می‌شود. امکان تغییر یا تولید مجدد توسط خودتان وجود ندارد.</p>';
    sc_staff_qr_render_user_card($user_id, $context, 240);
    echo '</div>';
}

/**
 * @param string $hash
 * @return array{user:WP_User|null,qr:object|null,scan_code:string,subject_name:string}
 */
function sc_staff_qr_resolve_scan_hash($hash) {
    $hash = sc_staff_qr_parse_payload($hash);
    if ($hash === '') {
        return ['user' => null, 'qr' => null, 'scan_code' => 'invalid_qr', 'subject_name' => ''];
    }
    $qr = sc_staff_qr_get_by_hash($hash);
    if (!$qr) {
        return ['user' => null, 'qr' => null, 'scan_code' => 'unknown_qr', 'subject_name' => ''];
    }
    $user = get_userdata((int) $qr->user_id);
    $name = $user ? sc_staff_qr_get_display_name((int) $qr->user_id) : '';
    if ($qr->status === 'disabled') {
        return ['user' => $user, 'qr' => $qr, 'scan_code' => 'qr_disabled', 'subject_name' => $name];
    }
    if ($qr->status !== 'active') {
        return ['user' => $user, 'qr' => $qr, 'scan_code' => 'qr_inactive', 'subject_name' => $name];
    }
    if (!$user) {
        return ['user' => null, 'qr' => $qr, 'scan_code' => 'unknown_qr', 'subject_name' => ''];
    }
    return ['user' => $user, 'qr' => $qr, 'scan_code' => 'ok', 'subject_name' => $name];
}

add_action('show_user_profile', 'sc_staff_qr_user_profile_fields', 25);
add_action('edit_user_profile', 'sc_staff_qr_user_profile_fields', 25);
add_action('show_user_profile', 'sc_staff_qr_own_profile_panel', 5);

function sc_staff_qr_own_profile_panel($user) {
    if (!$user instanceof WP_User || (int) get_current_user_id() !== (int) $user->ID) {
        return;
    }
    global $pagenow;
    if ($pagenow !== 'profile.php') {
        return;
    }
    sc_staff_qr_render_panel_for_current_user('profile');
}

function sc_staff_qr_user_profile_fields($user) {
    if (!$user instanceof WP_User || !sc_staff_qr_should_show_on_user_edit($user->ID)) {
        return;
    }
    global $pagenow;
    if ($pagenow === 'profile.php' && (int) get_current_user_id() === (int) $user->ID) {
        return;
    }
    if (!current_user_can('edit_user', $user->ID) && !sc_attendance_qr_user_can_manage_codes()) {
        return;
    }
    echo '<h2>QR تردد پرسنل</h2>';
    echo '<p class="description">QR ثبت تردد پرسنل — فقط مدیران می‌توانند از این بخش مشاهده کنند. کاربر نمی‌تواند QR خود را تغییر دهد.</p>';
    sc_staff_qr_render_user_card((int) $user->ID, 'user-edit', 260);
}

add_action('wp_ajax_sc_staff_qr_image', 'sc_ajax_staff_qr_image');
function sc_ajax_staff_qr_image() {
    $user_id = absint($_GET['user_id'] ?? 0);
    if (!$user_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'] ?? '')), 'sc_staff_qr_image_' . $user_id)) {
        status_header(403);
        exit;
    }
    if (!sc_staff_qr_user_can_view_image($user_id)) {
        status_header(403);
        exit;
    }
    $size = max(200, min(800, absint($_GET['size'] ?? 280)));
    $png = sc_staff_qr_render_png_binary($user_id, $size);
    if (!$png) {
        status_header(500);
        exit;
    }
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: public, max-age=604800');
    echo $png;
    exit;
}
