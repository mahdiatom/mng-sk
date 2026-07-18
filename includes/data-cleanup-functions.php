<?php
/**
 * پاکسازی اطلاعات کاربری — فقط مدیر کل و مدیر باشگاه
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_DATA_CLEANUP_OTP_EXPIRY', 120);
define('SC_DATA_CLEANUP_OTP_MAX_ATTEMPTS', 5);
define('SC_DATA_CLEANUP_JOB_EXPIRY', 3600);
define('SC_DATA_CLEANUP_BATCH_SIZE', 80);
define('SC_DATA_CLEANUP_DEFAULT_SUPER_ADMIN_PHONE', '09944338956');

/**
 * آیا کاربر مجاز به پاکسازی است؟ (مدیر کل یا مدیر باشگاه — نه منشی، نه مدیر سامانه)
 *
 * @param int $user_id
 * @return bool
 */
function sc_user_can_data_cleanup($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        return false;
    }
    $roles = (array) $user->roles;
    if (in_array('system_manager', $roles, true)) {
        return false;
    }
    if (in_array('administrator', $roles, true)) {
        return true;
    }
    if (in_array('club_coach', $roles, true)) {
        return true;
    }
    return false;
}

/**
 * ماژول‌های قابل پاکسازی
 *
 * @return array<string,array{label:string,description:string,danger:string}>
 */
function sc_data_cleanup_modules() {
    return [
        'tickets' => [
            'label'       => 'تیکت‌ها',
            'description' => 'تمام تیکت‌های پشتیبانی و پیام‌ها و پیوست‌های آن‌ها',
            'danger'      => 'تمام تیکت‌ها و پیوست‌ها برای همیشه حذف می‌شوند.',
        ],
        'announcements' => [
            'label'       => 'اطلاعیه‌ها',
            'description' => 'اطلاعیه‌های ارسال‌شده به کاربران (بدون هشدارهای سیستمی)',
            'danger'      => 'تمام اطلاعیه‌ها و پیوست‌های آن‌ها حذف می‌شوند.',
        ],
        'notes' => [
            'label'       => 'یادداشت‌ها',
            'description' => 'یادداشت‌های خصوصی، رشته‌ها، پیام‌ها و پیوست‌ها',
            'danger'      => 'تمام یادداشت‌های خصوصی حذف می‌شوند.',
        ],
        'weekly_schedules' => [
            'label'       => 'برنامه هفتگی‌ها',
            'description' => 'برنامه هفتگی کلاس‌های دوره‌ها و لغو جلسات مرتبط',
            'danger'      => 'تمام برنامه‌های هفتگی حذف می‌شوند.',
        ],
        'alerts' => [
            'label'       => 'هشدارها',
            'description' => 'هشدارهای سیستمی (غیبت، بدهی و …)',
            'danger'      => 'تمام هشدارهای سیستمی حذف می‌شوند.',
        ],
        'certificates' => [
            'label'       => 'گواهینامه‌ها',
            'description' => 'گواهینامه‌های صادرشده برای اعضا',
            'danger'      => 'تمام گواهینامه‌ها حذف می‌شوند.',
        ],
        'tarddod' => [
            'label'       => 'ثبت ترددها',
            'description' => 'سشن‌ها و رکوردهای تردد به‌همراه فایل‌های عکس اسکن',
            'danger'      => 'تمام ثبت‌ترددها و فایل‌های مرتبط حذف می‌شوند.',
        ],
        'attendance' => [
            'label'       => 'حضور و غیاب‌ها',
            'description' => 'حضور و غیاب، لاگ API، کدهای QR و فایل‌های اسکن',
            'danger'      => 'تمام حضور و غیاب‌ها، QRها و فایل‌های مرتبط حذف می‌شوند.',
        ],
        'honors' => [
            'label'       => 'افتخارات',
            'description' => 'افتخارات ثبت‌شده و فایل‌های پیوست',
            'danger'      => 'تمام افتخارات و فایل‌های آن‌ها حذف می‌شوند.',
        ],
        'users' => [
            'label'       => 'کاربران',
            'description' => 'اعضا و تمام اطلاعات وابسته (ثبت‌نام دوره، کیف پول، QR و …)',
            'danger'      => 'تمام اعضای باشگاه و داده‌های مرتبط برای همیشه حذف می‌شوند.',
        ],
        'events' => [
            'label'       => 'رویدادها',
            'description' => 'رویدادها، فیلدها، ثبت‌نام‌ها و فایل‌های مرتبط',
            'danger'      => 'تمام رویدادها و ثبت‌نام‌ها حذف می‌شوند.',
        ],
        'courses' => [
            'label'       => 'دوره‌ها',
            'description' => 'دوره‌ها و داده‌های وابسته (ثبت‌نام، برنامه، گروه و …)',
            'danger'      => 'تمام دوره‌ها و داده‌های وابسته حذف می‌شوند.',
        ],
        'invoices' => [
            'label'       => 'صورت‌حساب‌ها',
            'description' => 'صورت‌حساب‌ها و سفارش‌های ووکامرس مرتبط',
            'danger'      => 'تمام صورت‌حساب‌ها و سفارش‌های مرتبط حذف می‌شوند.',
        ],
    ];
}

/**
 * @param string $module
 * @return bool
 */
function sc_data_cleanup_is_valid_module($module) {
    $modules = sc_data_cleanup_modules();
    return isset($modules[$module]);
}

/**
 * شماره OTP اولیه (همان شماره QR)
 *
 * @return string
 */
function sc_data_cleanup_get_otp_phone() {
    if (function_exists('sc_attendance_qr_get_regenerate_otp_phone')) {
        return sc_attendance_qr_get_regenerate_otp_phone();
    }
    $phone = trim((string) sc_get_setting('attendance_qr_regenerate_otp_phone', ''));
    if ($phone !== '' && function_exists('sc_login_register_normalize_phone')) {
        return sc_login_register_normalize_phone($phone);
    }
    return $phone;
}

/**
 * شماره مدیر کل برای تأیید نهایی
 *
 * @return string
 */
function sc_data_cleanup_get_super_admin_phone() {
    $phone = trim((string) sc_get_setting('data_cleanup_super_admin_phone', SC_DATA_CLEANUP_DEFAULT_SUPER_ADMIN_PHONE));
    if ($phone === '') {
        $phone = SC_DATA_CLEANUP_DEFAULT_SUPER_ADMIN_PHONE;
    }
    if (function_exists('sc_login_register_normalize_phone')) {
        return sc_login_register_normalize_phone($phone);
    }
    return $phone;
}

/**
 * @param string $phone
 * @return string
 */
function sc_data_cleanup_mask_phone($phone) {
    $phone = (string) $phone;
    return strlen($phone) >= 4
        ? str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4)
        : $phone;
}

/**
 * @param string $phone
 * @param string $code
 * @return bool
 */
function sc_data_cleanup_send_otp_sms($phone, $code) {
    $pattern = '';
    if (function_exists('sc_get_setting')) {
        $pattern = trim((string) sc_get_setting('attendance_qr_regenerate_otp_pattern', ''));
        if ($pattern === '') {
            $pattern = trim((string) sc_get_setting('sc_login_otp_pattern', ''));
        }
    }
    if ($pattern !== '' && function_exists('sc_send_pattern_sms')) {
        return (bool) sc_send_pattern_sms($phone, $pattern, ['Code' => $code]);
    }
    if (function_exists('sc_login_register_send_otp_sms')) {
        return (bool) sc_login_register_send_otp_sms($phone, $code);
    }
    return false;
}

/**
 * @param string $table
 * @return bool
 */
function sc_data_cleanup_table_exists($table) {
    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return $found === $table;
}

/**
 * @param string $table
 * @return int
 */
function sc_data_cleanup_count_table($table) {
    global $wpdb;
    if (!sc_data_cleanup_table_exists($table)) {
        return 0;
    }
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
}

/**
 * @param array $ids
 */
function sc_data_cleanup_delete_attachment_ids($ids) {
    if (!is_array($ids) || empty($ids)) {
        return;
    }
    if (!function_exists('wp_delete_attachment')) {
        require_once ABSPATH . 'wp-admin/includes/post.php';
    }
    foreach ($ids as $id) {
        $id = absint($id);
        if ($id > 0) {
            wp_delete_attachment($id, true);
        }
    }
}

/**
 * @param string|null $json
 */
function sc_data_cleanup_delete_attachments_from_json($json) {
    if ($json === null || $json === '') {
        return;
    }
    $decoded = json_decode((string) $json, true);
    if (!is_array($decoded)) {
        return;
    }
    sc_data_cleanup_delete_attachment_ids($decoded);
}

/**
 * @param string $url
 */
function sc_data_cleanup_unlink_upload_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return;
    }
    $uploads = wp_upload_dir();
    if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
        return;
    }
    $baseurl = trailingslashit($uploads['baseurl']);
    if (strpos($url, $baseurl) !== 0) {
        return;
    }
    $rel = substr($url, strlen($baseurl));
    $path = trailingslashit($uploads['basedir']) . ltrim($rel, '/\\');
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * پاکسازی کامل یک پوشه
 *
 * @param string $dir
 */
function sc_data_cleanup_purge_directory($dir) {
    $dir = rtrim((string) $dir, '/\\');
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            sc_data_cleanup_purge_directory($path);
            @rmdir($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * تعداد تقریبی رکوردهای هر ماژول برای نمایش در UI
 *
 * @param string $module
 * @return int
 */
function sc_data_cleanup_module_count($module) {
    global $wpdb;
    switch ($module) {
        case 'tickets':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_support_tickets');
        case 'announcements':
            $t = $wpdb->prefix . 'sc_notifications';
            if (!sc_data_cleanup_table_exists($t)) {
                return 0;
            }
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `$t` WHERE target_config IS NULL OR target_config NOT LIKE '%\"alert_key\"%'"
            );
        case 'notes':
            $threads = sc_data_cleanup_count_table($wpdb->prefix . 'sc_private_note_threads');
            $legacy = sc_data_cleanup_count_table($wpdb->prefix . 'sc_private_notes');
            return $threads + $legacy;
        case 'weekly_schedules':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_course_weekly_schedule');
        case 'alerts':
            $t = $wpdb->prefix . 'sc_notifications';
            if (!sc_data_cleanup_table_exists($t)) {
                return 0;
            }
            return (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM `$t` WHERE target_config LIKE '%\"alert_key\"%'"
            );
        case 'certificates':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_certificates');
        case 'tarddod':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_tarddod_records');
        case 'attendance':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_attendances');
        case 'honors':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_honors');
        case 'users':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_members');
        case 'events':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_events');
        case 'courses':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_courses');
        case 'invoices':
            return sc_data_cleanup_count_table($wpdb->prefix . 'sc_invoices');
        default:
            return 0;
    }
}

/**
 * @param string $table
 * @return int تعداد حذف‌شده
 */
function sc_data_cleanup_truncate_table($table) {
    global $wpdb;
    if (!sc_data_cleanup_table_exists($table)) {
        return 0;
    }
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
    $wpdb->query("DELETE FROM `$table`");
    if ($count > 0) {
        $wpdb->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
    }
    return $count;
}

/**
 * حذف دسته‌ای از جدول با LIMIT
 *
 * @param string $table
 * @param int    $limit
 * @return int
 */
function sc_data_cleanup_delete_batch($table, $limit = SC_DATA_CLEANUP_BATCH_SIZE) {
    global $wpdb;
    if (!sc_data_cleanup_table_exists($table)) {
        return 0;
    }
    $limit = max(1, (int) $limit);
    $ids = $wpdb->get_col("SELECT id FROM `$table` ORDER BY id ASC LIMIT $limit");
    if (empty($ids)) {
        return 0;
    }
    $ids = array_map('absint', $ids);
    $in = implode(',', $ids);
    $wpdb->query("DELETE FROM `$table` WHERE id IN ($in)");
    return count($ids);
}

/**
 * پردازش یک بچ از پاکسازی
 *
 * @param string $module
 * @param int    $offset مرحله داخلی (cursor)
 * @return array{done:bool,processed:int,message:string,next_offset:int,total_hint?:int}
 */
function sc_data_cleanup_process_batch($module, $offset = 0) {
    global $wpdb;
    @ignore_user_abort(true);
    @set_time_limit(120);

    $offset = max(0, (int) $offset);
    $batch = SC_DATA_CLEANUP_BATCH_SIZE;

    switch ($module) {
        case 'tickets':
            return sc_data_cleanup_process_tickets_batch($batch);
        case 'announcements':
            return sc_data_cleanup_process_notifications_batch(false, $batch);
        case 'alerts':
            return sc_data_cleanup_process_notifications_batch(true, $batch);
        case 'notes':
            return sc_data_cleanup_process_notes_batch($batch);
        case 'weekly_schedules':
            $n1 = sc_data_cleanup_truncate_table($wpdb->prefix . 'sc_course_session_cancellations');
            $n2 = sc_data_cleanup_truncate_table($wpdb->prefix . 'sc_course_weekly_schedule');
            return [
                'done'        => true,
                'processed'   => $n1 + $n2,
                'message'     => 'برنامه‌های هفتگی پاک شدند.',
                'next_offset' => 0,
            ];
        case 'certificates':
            return sc_data_cleanup_process_certificates_batch($batch);
        case 'tarddod':
            return sc_data_cleanup_process_tarddod_batch($batch);
        case 'attendance':
            return sc_data_cleanup_process_attendance_batch($offset, $batch);
        case 'honors':
            return sc_data_cleanup_process_honors_batch($batch);
        case 'users':
            return sc_data_cleanup_process_users_batch($batch);
        case 'events':
            return sc_data_cleanup_process_events_batch($offset, $batch);
        case 'courses':
            return sc_data_cleanup_process_courses_batch($offset, $batch);
        case 'invoices':
            return sc_data_cleanup_process_invoices_batch($batch);
        default:
            return [
                'done'        => true,
                'processed'   => 0,
                'message'     => 'ماژول نامعتبر است.',
                'next_offset' => 0,
            ];
    }
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_tickets_batch($batch) {
    global $wpdb;
    $tickets = $wpdb->prefix . 'sc_support_tickets';
    $messages = $wpdb->prefix . 'sc_support_ticket_messages';
    if (!sc_data_cleanup_table_exists($tickets)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }
    $rows = $wpdb->get_results("SELECT id FROM `$tickets` ORDER BY id ASC LIMIT " . (int) $batch);
    if (empty($rows)) {
        sc_data_cleanup_truncate_table($messages);
        return ['done' => true, 'processed' => 0, 'message' => 'تیکت‌ها پاک شدند.', 'next_offset' => 0];
    }
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int) $row->id;
        if (sc_data_cleanup_table_exists($messages)) {
            $msg_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT attachment_ids FROM `$messages` WHERE ticket_id = %d",
                (int) $row->id
            ));
            foreach ((array) $msg_rows as $m) {
                sc_data_cleanup_delete_attachments_from_json($m->attachment_ids);
            }
            $wpdb->delete($messages, ['ticket_id' => (int) $row->id], ['%d']);
        }
        $wpdb->delete($tickets, ['id' => (int) $row->id], ['%d']);
    }
    $remaining = sc_data_cleanup_count_table($tickets);
    return [
        'done'        => $remaining === 0,
        'processed'   => count($ids),
        'message'     => $remaining === 0 ? 'تیکت‌ها پاک شدند.' : 'در حال پاکسازی تیکت‌ها…',
        'next_offset' => 0,
    ];
}

/**
 * @param bool $alerts_only
 * @param int  $batch
 * @return array
 */
function sc_data_cleanup_process_notifications_batch($alerts_only, $batch) {
    global $wpdb;
    $n = $wpdb->prefix . 'sc_notifications';
    $r = $wpdb->prefix . 'sc_notification_recipients';
    $reads = $wpdb->prefix . 'sc_notification_reads';
    if (!sc_data_cleanup_table_exists($n)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }
    $like = $alerts_only
        ? "target_config LIKE '%\"alert_key\"%'"
        : "(target_config IS NULL OR target_config NOT LIKE '%\"alert_key\"%')";
    $rows = $wpdb->get_results(
        "SELECT id, attachment_ids FROM `$n` WHERE $like ORDER BY id ASC LIMIT " . (int) $batch
    );
    if (empty($rows)) {
        return [
            'done'        => true,
            'processed'   => 0,
            'message'     => $alerts_only ? 'هشدارها پاک شدند.' : 'اطلاعیه‌ها پاک شدند.',
            'next_offset' => 0,
        ];
    }
    foreach ($rows as $row) {
        $id = (int) $row->id;
        sc_data_cleanup_delete_attachments_from_json($row->attachment_ids);
        if (sc_data_cleanup_table_exists($reads)) {
            $wpdb->delete($reads, ['notification_id' => $id], ['%d']);
        }
        if (sc_data_cleanup_table_exists($r)) {
            $wpdb->delete($r, ['notification_id' => $id], ['%d']);
        }
        $wpdb->delete($n, ['id' => $id], ['%d']);
    }
    $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$n` WHERE $like");
    return [
        'done'        => $remaining === 0,
        'processed'   => count($rows),
        'message'     => $remaining === 0
            ? ($alerts_only ? 'هشدارها پاک شدند.' : 'اطلاعیه‌ها پاک شدند.')
            : 'در حال پاکسازی…',
        'next_offset' => 0,
    ];
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_notes_batch($batch) {
    global $wpdb;
    $threads = $wpdb->prefix . 'sc_private_note_threads';
    $messages = $wpdb->prefix . 'sc_private_note_messages';
    $legacy = $wpdb->prefix . 'sc_private_notes';

    if (sc_data_cleanup_table_exists($threads)) {
        $rows = $wpdb->get_results("SELECT id FROM `$threads` ORDER BY id ASC LIMIT " . (int) $batch);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $tid = (int) $row->id;
                if (sc_data_cleanup_table_exists($messages)) {
                    $msg_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT attachment_ids FROM `$messages` WHERE thread_id = %d",
                        $tid
                    ));
                    foreach ((array) $msg_rows as $m) {
                        sc_data_cleanup_delete_attachments_from_json($m->attachment_ids);
                    }
                    $wpdb->delete($messages, ['thread_id' => $tid], ['%d']);
                }
                $wpdb->delete($threads, ['id' => $tid], ['%d']);
            }
            $remaining = sc_data_cleanup_count_table($threads);
            if ($remaining > 0) {
                return [
                    'done'        => false,
                    'processed'   => count($rows),
                    'message'     => 'در حال پاکسازی یادداشت‌ها…',
                    'next_offset' => 0,
                ];
            }
        }
    }

    if (sc_data_cleanup_table_exists($legacy)) {
        $rows = $wpdb->get_results("SELECT id, attachment_ids FROM `$legacy` ORDER BY id ASC LIMIT " . (int) $batch);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                sc_data_cleanup_delete_attachments_from_json($row->attachment_ids);
                $wpdb->delete($legacy, ['id' => (int) $row->id], ['%d']);
            }
            $remaining = sc_data_cleanup_count_table($legacy);
            return [
                'done'        => $remaining === 0,
                'processed'   => count($rows),
                'message'     => $remaining === 0 ? 'یادداشت‌ها پاک شدند.' : 'در حال پاکسازی یادداشت‌ها…',
                'next_offset' => 0,
            ];
        }
    }

    return ['done' => true, 'processed' => 0, 'message' => 'یادداشت‌ها پاک شدند.', 'next_offset' => 0];
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_certificates_batch($batch) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_certificates';
    if (!sc_data_cleanup_table_exists($t)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }
    $rows = $wpdb->get_results(
        "SELECT id, background_image, signature_one_image, signature_two_image FROM `$t` ORDER BY id ASC LIMIT " . (int) $batch
    );
    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'message' => 'گواهینامه‌ها پاک شدند.', 'next_offset' => 0];
    }
    foreach ($rows as $row) {
        sc_data_cleanup_unlink_upload_url($row->background_image ?? '');
        sc_data_cleanup_unlink_upload_url($row->signature_one_image ?? '');
        sc_data_cleanup_unlink_upload_url($row->signature_two_image ?? '');
        $wpdb->delete($t, ['id' => (int) $row->id], ['%d']);
    }
    $remaining = sc_data_cleanup_count_table($t);
    return [
        'done'        => $remaining === 0,
        'processed'   => count($rows),
        'message'     => $remaining === 0 ? 'گواهینامه‌ها پاک شدند.' : 'در حال پاکسازی گواهینامه‌ها…',
        'next_offset' => 0,
    ];
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_tarddod_batch($batch) {
    global $wpdb;
    $records = $wpdb->prefix . 'sc_tarddod_records';
    $sessions = $wpdb->prefix . 'sc_tarddod_sessions';

    if (sc_data_cleanup_table_exists($records)) {
        $cols = $wpdb->get_col("SHOW COLUMNS FROM `$records`");
        $has_photo = in_array('scan_photo', (array) $cols, true);
        $has_front = in_array('scan_photo_front', (array) $cols, true);
        $select = 'id';
        if ($has_photo) {
            $select .= ', scan_photo';
        }
        if ($has_front) {
            $select .= ', scan_photo_front';
        }
        $rows = $wpdb->get_results("SELECT $select FROM `$records` ORDER BY id ASC LIMIT " . (int) $batch);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if ($has_photo && !empty($row->scan_photo) && function_exists('sc_qr_scan_photo_delete_file')) {
                    sc_qr_scan_photo_delete_file($row->scan_photo);
                }
                if ($has_front && !empty($row->scan_photo_front) && function_exists('sc_qr_scan_photo_delete_file')) {
                    sc_qr_scan_photo_delete_file($row->scan_photo_front);
                }
                $wpdb->delete($records, ['id' => (int) $row->id], ['%d']);
            }
            $remaining = sc_data_cleanup_count_table($records);
            if ($remaining > 0) {
                return [
                    'done'        => false,
                    'processed'   => count($rows),
                    'message'     => 'در حال پاکسازی ترددها…',
                    'next_offset' => 0,
                ];
            }
        }
    }

    sc_data_cleanup_truncate_table($sessions);
    return ['done' => true, 'processed' => 0, 'message' => 'ثبت ترددها پاک شدند.', 'next_offset' => 0];
}

/**
 * @param int $offset
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_attendance_batch($offset, $batch) {
    global $wpdb;
    $att = $wpdb->prefix . 'sc_attendances';
    $logs = $wpdb->prefix . 'sc_api_attendance_logs';
    $member_qr = $wpdb->prefix . 'sc_member_qr_codes';
    $staff_qr = $wpdb->prefix . 'sc_staff_qr_codes';

    // phase 0: attendances + photos
    if ($offset === 0 || $offset === 1) {
        if (sc_data_cleanup_table_exists($att)) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM `$att`");
            $has_photo = in_array('scan_photo', (array) $cols, true);
            $has_front = in_array('scan_photo_front', (array) $cols, true);
            $select = 'id';
            if ($has_photo) {
                $select .= ', scan_photo';
            }
            if ($has_front) {
                $select .= ', scan_photo_front';
            }
            $rows = $wpdb->get_results("SELECT $select FROM `$att` ORDER BY id ASC LIMIT " . (int) $batch);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    if ($has_photo && !empty($row->scan_photo) && function_exists('sc_qr_scan_photo_delete_file')) {
                        sc_qr_scan_photo_delete_file($row->scan_photo);
                    }
                    if ($has_front && !empty($row->scan_photo_front) && function_exists('sc_qr_scan_photo_delete_file')) {
                        sc_qr_scan_photo_delete_file($row->scan_photo_front);
                    }
                    $wpdb->delete($att, ['id' => (int) $row->id], ['%d']);
                }
                return [
                    'done'        => false,
                    'processed'   => count($rows),
                    'message'     => 'در حال پاکسازی حضور و غیاب…',
                    'next_offset' => 1,
                ];
            }
        }
        $offset = 2;
    }

    if ($offset === 2) {
        sc_data_cleanup_truncate_table($logs);
        sc_data_cleanup_truncate_table($member_qr);
        sc_data_cleanup_truncate_table($staff_qr);
        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir'])) {
            sc_data_cleanup_purge_directory(trailingslashit($uploads['basedir']) . 'sportclub/qr-snapshots');
            sc_data_cleanup_purge_directory(trailingslashit($uploads['basedir']) . 'sc-qr-cache');
        }
        return [
            'done'        => true,
            'processed'   => 0,
            'message'     => 'حضور و غیاب‌ها و فایل‌های مرتبط پاک شدند.',
            'next_offset' => 0,
        ];
    }

    return ['done' => true, 'processed' => 0, 'message' => 'حضور و غیاب‌ها پاک شدند.', 'next_offset' => 0];
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_honors_batch($batch) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_honors';
    if (!sc_data_cleanup_table_exists($t)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }
    $rows = $wpdb->get_results("SELECT id, file_url FROM `$t` ORDER BY id ASC LIMIT " . (int) $batch);
    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'message' => 'افتخارات پاک شدند.', 'next_offset' => 0];
    }
    foreach ($rows as $row) {
        sc_data_cleanup_unlink_upload_url($row->file_url ?? '');
        $wpdb->delete($t, ['id' => (int) $row->id], ['%d']);
    }
    $remaining = sc_data_cleanup_count_table($t);
    return [
        'done'        => $remaining === 0,
        'processed'   => count($rows),
        'message'     => $remaining === 0 ? 'افتخارات پاک شدند.' : 'در حال پاکسازی افتخارات…',
        'next_offset' => 0,
    ];
}

/**
 * پاکسازی یک عضو و وابستگی‌هایش
 *
 * @param object $member
 */
function sc_data_cleanup_purge_one_member($member) {
    global $wpdb;
    $member_id = (int) $member->id;
    $user_id = isset($member->user_id) ? (int) $member->user_id : 0;

    $related = [
        'sc_member_courses',
        'sc_attendances',
        'sc_invoices',
        'sc_honors',
        'sc_certificates',
        'sc_member_qr_codes',
        'sc_wallet_transactions',
        'sc_course_capacity_waitlist',
    ];
    foreach ($related as $suffix) {
        $t = $wpdb->prefix . $suffix;
        if (sc_data_cleanup_table_exists($t)) {
            $wpdb->delete($t, ['member_id' => $member_id], ['%d']);
        }
    }

    $threads = $wpdb->prefix . 'sc_private_note_threads';
    $messages = $wpdb->prefix . 'sc_private_note_messages';
    if (sc_data_cleanup_table_exists($threads)) {
        $thread_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM `$threads` WHERE member_id = %d", $member_id));
        foreach ((array) $thread_ids as $tid) {
            if (sc_data_cleanup_table_exists($messages)) {
                $wpdb->delete($messages, ['thread_id' => (int) $tid], ['%d']);
            }
            $wpdb->delete($threads, ['id' => (int) $tid], ['%d']);
        }
    }
    $legacy_notes = $wpdb->prefix . 'sc_private_notes';
    if (sc_data_cleanup_table_exists($legacy_notes)) {
        $wpdb->delete($legacy_notes, ['member_id' => $member_id], ['%d']);
    }

    $regs = $wpdb->prefix . 'sc_event_registrations';
    if (sc_data_cleanup_table_exists($regs)) {
        $wpdb->delete($regs, ['member_id' => $member_id], ['%d']);
    }

    foreach (['personal_photo', 'id_card_photo', 'sport_insurance_photo'] as $photo_col) {
        if (!empty($member->{$photo_col})) {
            sc_data_cleanup_unlink_upload_url($member->{$photo_col});
        }
    }

    $members = $wpdb->prefix . 'sc_members';
    $can_delete_wp_user = true;
    if ($user_id > 0) {
        $wp_user = get_userdata($user_id);
        $protected_roles = ['administrator', 'club_coach', 'system_manager', 'secretary', 'coach', 'accountantt'];
        if ($wp_user && array_intersect($protected_roles, (array) $wp_user->roles)) {
            $can_delete_wp_user = false;
        }
    }
    if ($can_delete_wp_user && function_exists('sc_delete_wp_user_by_table_id')) {
        sc_delete_wp_user_by_table_id($members, $member_id);
    }
    $wpdb->delete($members, ['id' => $member_id], ['%d']);
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_users_batch($batch) {
    global $wpdb;
    $members = $wpdb->prefix . 'sc_members';
    if (!sc_data_cleanup_table_exists($members)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }
    $rows = $wpdb->get_results("SELECT * FROM `$members` ORDER BY id ASC LIMIT " . (int) $batch);
    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'message' => 'کاربران پاک شدند.', 'next_offset' => 0];
    }
    foreach ($rows as $row) {
        sc_data_cleanup_purge_one_member($row);
    }
    $remaining = sc_data_cleanup_count_table($members);
    return [
        'done'        => $remaining === 0,
        'processed'   => count($rows),
        'message'     => $remaining === 0 ? 'کاربران پاک شدند.' : 'در حال پاکسازی کاربران…',
        'next_offset' => 0,
    ];
}

/**
 * @param int $offset
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_events_batch($offset, $batch) {
    global $wpdb;
    $events = $wpdb->prefix . 'sc_events';
    $fields = $wpdb->prefix . 'sc_event_fields';
    $regs = $wpdb->prefix . 'sc_event_registrations';

    if (sc_data_cleanup_table_exists($regs) && ($offset === 0 || $offset === 1)) {
        $rows = $wpdb->get_results("SELECT id, files FROM `$regs` ORDER BY id ASC LIMIT " . (int) $batch);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (!empty($row->files)) {
                    $decoded = json_decode((string) $row->files, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $file_item) {
                            if (is_string($file_item)) {
                                sc_data_cleanup_unlink_upload_url($file_item);
                            } elseif (is_array($file_item) && !empty($file_item['url'])) {
                                sc_data_cleanup_unlink_upload_url($file_item['url']);
                            }
                        }
                    }
                }
                $wpdb->delete($regs, ['id' => (int) $row->id], ['%d']);
            }
            return [
                'done'        => false,
                'processed'   => count($rows),
                'message'     => 'در حال پاکسازی ثبت‌نام رویدادها…',
                'next_offset' => 1,
            ];
        }
        $offset = 2;
    }

    if ($offset <= 2) {
        sc_data_cleanup_truncate_table($fields);
        if (sc_data_cleanup_table_exists($events)) {
            $rows = $wpdb->get_results("SELECT id, image FROM `$events` ORDER BY id ASC LIMIT " . (int) $batch);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    sc_data_cleanup_unlink_upload_url($row->image ?? '');
                    $wpdb->delete($events, ['id' => (int) $row->id], ['%d']);
                }
                $remaining = sc_data_cleanup_count_table($events);
                return [
                    'done'        => $remaining === 0,
                    'processed'   => count($rows),
                    'message'     => $remaining === 0 ? 'رویدادها پاک شدند.' : 'در حال پاکسازی رویدادها…',
                    'next_offset' => 2,
                ];
            }
        }
    }

    return ['done' => true, 'processed' => 0, 'message' => 'رویدادها پاک شدند.', 'next_offset' => 0];
}

/**
 * @param int $offset
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_courses_batch($offset, $batch) {
    global $wpdb;
    $courses = $wpdb->prefix . 'sc_courses';
    $child_tables = [
        'sc_member_courses',
        'sc_course_coaches',
        'sc_course_chapters',
        'sc_course_groups',
        'sc_course_packages',
        'sc_course_weekly_schedule',
        'sc_course_session_cancellations',
        'sc_course_capacity_waitlist',
        'sc_course_assistant_coaches',
    ];

    if ($offset === 0) {
        foreach ($child_tables as $suffix) {
            sc_data_cleanup_truncate_table($wpdb->prefix . $suffix);
        }
        $offset = 1;
    }

    if (!sc_data_cleanup_table_exists($courses)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }

    $rows = $wpdb->get_results("SELECT id, image FROM `$courses` ORDER BY id ASC LIMIT " . (int) $batch);
    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'message' => 'دوره‌ها پاک شدند.', 'next_offset' => 0];
    }
    foreach ($rows as $row) {
        sc_data_cleanup_unlink_upload_url($row->image ?? '');
        $wpdb->delete($courses, ['id' => (int) $row->id], ['%d']);
    }
    $remaining = sc_data_cleanup_count_table($courses);
    return [
        'done'        => $remaining === 0,
        'processed'   => count($rows),
        'message'     => $remaining === 0 ? 'دوره‌ها پاک شدند.' : 'در حال پاکسازی دوره‌ها…',
        'next_offset' => 1,
    ];
}

/**
 * @param int $batch
 * @return array
 */
function sc_data_cleanup_process_invoices_batch($batch) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_invoices';
    if (!sc_data_cleanup_table_exists($t)) {
        return ['done' => true, 'processed' => 0, 'message' => 'جدولی وجود ندارد.', 'next_offset' => 0];
    }
    $rows = $wpdb->get_results("SELECT id, woocommerce_order_id FROM `$t` ORDER BY id ASC LIMIT " . (int) $batch);
    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'message' => 'صورت‌حساب‌ها پاک شدند.', 'next_offset' => 0];
    }
    foreach ($rows as $row) {
        if (!empty($row->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order((int) $row->woocommerce_order_id);
            if ($order) {
                $order->delete(true);
            }
        }
        $wpdb->delete($t, ['id' => (int) $row->id], ['%d']);
        do_action('sc_invoice_deleted', (int) $row->id);
    }
    $remaining = sc_data_cleanup_count_table($t);
    return [
        'done'        => $remaining === 0,
        'processed'   => count($rows),
        'message'     => $remaining === 0 ? 'صورت‌حساب‌ها پاک شدند.' : 'در حال پاکسازی صورت‌حساب‌ها…',
        'next_offset' => 0,
    ];
}

/**
 * @param int    $user_id
 * @param string $step  otp|super_admin
 * @return array{success:bool,message:string,masked_phone?:string}
 */
function sc_data_cleanup_request_otp($user_id, $step) {
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id || !sc_user_can_data_cleanup($user_id)) {
        return ['success' => false, 'message' => 'دسترسی ندارید.'];
    }
    $step = $step === 'super_admin' ? 'super_admin' : 'otp';
    $phone = $step === 'super_admin'
        ? sc_data_cleanup_get_super_admin_phone()
        : sc_data_cleanup_get_otp_phone();

    if ($phone === '') {
        return [
            'success' => false,
            'message' => $step === 'super_admin'
                ? 'شماره مدیر کل در امکانات پرو تنظیم نشده است.'
                : 'شماره OTP تولید QR در تنظیمات حضور و غیاب تنظیم نشده است.',
        ];
    }

    $rate_key = 'sc_dcu_otp_rate_' . $step . '_' . $user_id;
    if (get_transient($rate_key)) {
        return ['success' => false, 'message' => 'لطفاً چند ثانیه صبر کنید و دوباره تلاش کنید.'];
    }
    set_transient($rate_key, '1', 30);

    $code = (string) wp_rand(100000, 999999);
    $sent = sc_data_cleanup_send_otp_sms($phone, $code);
    if (!$sent) {
        return ['success' => false, 'message' => 'ارسال پیامک با خطا مواجه شد.'];
    }

    set_transient('sc_dcu_otp_' . $step . '_' . $user_id, [
        'code'     => $code,
        'attempts' => 0,
        'phone'    => $phone,
    ], SC_DATA_CLEANUP_OTP_EXPIRY);

    return [
        'success'      => true,
        'message'      => 'کد تأیید ارسال شد.',
        'masked_phone' => sc_data_cleanup_mask_phone($phone),
    ];
}

/**
 * @param int    $user_id
 * @param string $step
 * @param string $otp_code
 * @return bool
 */
function sc_data_cleanup_verify_otp($user_id, $step, $otp_code) {
    $user_id = absint($user_id);
    $step = $step === 'super_admin' ? 'super_admin' : 'otp';
    $otp_code = trim((string) $otp_code);
    if (!$user_id || $otp_code === '') {
        return false;
    }
    $key = 'sc_dcu_otp_' . $step . '_' . $user_id;
    $data = get_transient($key);
    if (!is_array($data) || empty($data['code'])) {
        return false;
    }
    $data['attempts'] = isset($data['attempts']) ? (int) $data['attempts'] + 1 : 1;
    if ($data['attempts'] > SC_DATA_CLEANUP_OTP_MAX_ATTEMPTS) {
        delete_transient($key);
        return false;
    }
    set_transient($key, $data, SC_DATA_CLEANUP_OTP_EXPIRY);
    if (!hash_equals((string) $data['code'], $otp_code)) {
        return false;
    }
    delete_transient($key);
    return true;
}

/**
 * ایجاد توکن جاب پس از تأیید دو مرحله‌ای
 *
 * @param int    $user_id
 * @param string $module
 * @return string|false
 */
function sc_data_cleanup_create_job($user_id, $module) {
    if (!sc_data_cleanup_is_valid_module($module) || !sc_user_can_data_cleanup($user_id)) {
        return false;
    }
    $token = wp_generate_password(32, false, false);
    $job = [
        'token'        => $token,
        'module'       => $module,
        'user_id'      => (int) $user_id,
        'offset'       => 0,
        'processed'    => 0,
        'created_at'   => time(),
        'status'       => 'ready',
    ];
    set_transient('sc_dcu_job_' . $token, $job, SC_DATA_CLEANUP_JOB_EXPIRY);
    return $token;
}

/**
 * @param string $token
 * @return array|null
 */
function sc_data_cleanup_get_job($token) {
    $token = sanitize_text_field((string) $token);
    if ($token === '') {
        return null;
    }
    $job = get_transient('sc_dcu_job_' . $token);
    return is_array($job) ? $job : null;
}

/**
 * @param string $token
 * @param array  $job
 */
function sc_data_cleanup_save_job($token, array $job) {
    set_transient('sc_dcu_job_' . $token, $job, SC_DATA_CLEANUP_JOB_EXPIRY);
}

/**
 * AJAX: درخواست OTP اولیه
 */
add_action('wp_ajax_sc_data_cleanup_request_otp', 'sc_ajax_data_cleanup_request_otp');
function sc_ajax_data_cleanup_request_otp() {
    check_ajax_referer('sc_data_cleanup', 'nonce');
    if (!sc_user_can_data_cleanup()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $module = isset($_POST['module']) ? sanitize_key(wp_unslash($_POST['module'])) : '';
    if (!sc_data_cleanup_is_valid_module($module)) {
        wp_send_json_error(['message' => 'ماژول نامعتبر است.']);
    }
    $result = sc_data_cleanup_request_otp(get_current_user_id(), 'otp');
    if (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message']]);
    }
    set_transient('sc_dcu_pending_module_' . get_current_user_id(), $module, SC_DATA_CLEANUP_OTP_EXPIRY);
    wp_send_json_success($result);
}

/**
 * AJAX: تأیید OTP اولیه و ارسال کد به مدیر کل
 */
add_action('wp_ajax_sc_data_cleanup_verify_otp', 'sc_ajax_data_cleanup_verify_otp');
function sc_ajax_data_cleanup_verify_otp() {
    check_ajax_referer('sc_data_cleanup', 'nonce');
    if (!sc_user_can_data_cleanup()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $user_id = get_current_user_id();
    $otp = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';
    $module = get_transient('sc_dcu_pending_module_' . $user_id);
    if (!$module || !sc_data_cleanup_is_valid_module($module)) {
        wp_send_json_error(['message' => 'جلسه تأیید منقضی شده است. دوباره تلاش کنید.']);
    }
    if (!sc_data_cleanup_verify_otp($user_id, 'otp', $otp)) {
        wp_send_json_error(['message' => 'کد تأیید نامعتبر است.']);
    }

    set_transient('sc_dcu_otp_passed_' . $user_id, $module, SC_DATA_CLEANUP_OTP_EXPIRY);
    $result = sc_data_cleanup_request_otp($user_id, 'super_admin');
    if (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message']]);
    }
    $result['module'] = $module;
    $result['message'] = 'کد تأیید برای مدیر کل ارسال شد.';
    wp_send_json_success($result);
}

/**
 * AJAX: تأیید کد مدیر کل و ایجاد جاب
 */
add_action('wp_ajax_sc_data_cleanup_verify_super_admin', 'sc_ajax_data_cleanup_verify_super_admin');
function sc_ajax_data_cleanup_verify_super_admin() {
    check_ajax_referer('sc_data_cleanup', 'nonce');
    if (!sc_user_can_data_cleanup()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $user_id = get_current_user_id();
    $otp = isset($_POST['otp_code']) ? sanitize_text_field(wp_unslash($_POST['otp_code'])) : '';
    $module = get_transient('sc_dcu_otp_passed_' . $user_id);
    if (!$module || !sc_data_cleanup_is_valid_module($module)) {
        wp_send_json_error(['message' => 'جلسه تأیید منقضی شده است. دوباره تلاش کنید.']);
    }
    if (!sc_data_cleanup_verify_otp($user_id, 'super_admin', $otp)) {
        wp_send_json_error(['message' => 'کد تأیید مدیر کل نامعتبر است.']);
    }

    $token = sc_data_cleanup_create_job($user_id, $module);
    if (!$token) {
        wp_send_json_error(['message' => 'ایجاد عملیات پاکسازی ناموفق بود.']);
    }
    delete_transient('sc_dcu_pending_module_' . $user_id);
    delete_transient('sc_dcu_otp_passed_' . $user_id);

    $modules = sc_data_cleanup_modules();
    if (function_exists('sc_log_activity')) {
        sc_log_activity(
            'created',
            'data_cleanup',
            0,
            'شروع پاکسازی: ' . ($modules[$module]['label'] ?? $module),
            null,
            ['module' => $module, 'token' => $token]
        );
    }

    wp_send_json_success([
        'message'     => 'تأیید شد. پاکسازی در حال اجراست…',
        'job_token'   => $token,
        'module'      => $module,
        'module_label'=> $modules[$module]['label'] ?? $module,
        'total_hint'  => sc_data_cleanup_module_count($module),
    ]);
}

/**
 * AJAX: اجرای یک بچ
 */
add_action('wp_ajax_sc_data_cleanup_process_batch', 'sc_ajax_data_cleanup_process_batch');
function sc_ajax_data_cleanup_process_batch() {
    check_ajax_referer('sc_data_cleanup', 'nonce');
    if (!sc_user_can_data_cleanup()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $token = isset($_POST['job_token']) ? sanitize_text_field(wp_unslash($_POST['job_token'])) : '';
    $job = sc_data_cleanup_get_job($token);
    if (!$job || (int) ($job['user_id'] ?? 0) !== get_current_user_id()) {
        wp_send_json_error(['message' => 'عملیات نامعتبر یا منقضی شده است.']);
    }
    if (($job['status'] ?? '') === 'done') {
        wp_send_json_success([
            'done'      => true,
            'processed' => (int) ($job['processed'] ?? 0),
            'message'   => 'پاکسازی قبلاً تکمیل شده است.',
        ]);
    }

    $result = sc_data_cleanup_process_batch($job['module'], (int) ($job['offset'] ?? 0));
    $job['offset'] = (int) ($result['next_offset'] ?? 0);
    $job['processed'] = (int) ($job['processed'] ?? 0) + (int) ($result['processed'] ?? 0);
    if (!empty($result['done'])) {
        $job['status'] = 'done';
        $modules = sc_data_cleanup_modules();
        if (function_exists('sc_log_activity')) {
            sc_log_activity(
                'deleted',
                'data_cleanup',
                0,
                'پاکسازی تکمیل شد: ' . ($modules[$job['module']]['label'] ?? $job['module']) . ' — ' . $job['processed'] . ' مورد',
                null,
                ['module' => $job['module'], 'processed' => $job['processed']]
            );
        }
    }
    sc_data_cleanup_save_job($token, $job);

    wp_send_json_success([
        'done'         => !empty($result['done']),
        'processed'    => (int) ($result['processed'] ?? 0),
        'total_done'   => (int) $job['processed'],
        'message'      => $result['message'] ?? '',
        'module'       => $job['module'],
        'remaining'    => sc_data_cleanup_module_count($job['module']),
    ]);
}

/**
 * صفحه ادمین
 */
function sc_admin_data_cleanup_page() {
    if (!sc_user_can_data_cleanup()) {
        wp_die('شما دسترسی لازم برای این بخش را ندارید. فقط مدیر کل و مدیر باشگاه مجاز هستند.');
    }
    include SC_TEMPLATES_ADMIN_DIR . 'data-cleanup.php';
}
