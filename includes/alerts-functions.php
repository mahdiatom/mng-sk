<?php
/**
 * User Alerts (Admin only)
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'sc_schedule_user_alerts_daily_cron');
add_action('sc_user_alerts_daily_cron_event', 'sc_run_user_alerts_daily_cron');

function sc_schedule_user_alerts_daily_cron() {
    if (!wp_next_scheduled('sc_user_alerts_daily_cron_event')) {
        wp_schedule_event(time() + 300, 'daily', 'sc_user_alerts_daily_cron_event');
    }
}

function sc_run_user_alerts_daily_cron() {
    sc_generate_system_alert_notifications(false);
}

/**
 * Get absence threshold per course for alerts.
 */
function sc_get_user_alert_absence_limit() {
    return max(1, (int) sc_get_setting('user_alert_absence_limit', '3'));
}

/**
 * Alert set #1: users whose absences in a course exceed threshold.
 */
function sc_get_absence_limit_alerts() {
    global $wpdb;

    $limit = sc_get_user_alert_absence_limit();
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT
            a.member_id,
            a.course_id,
            COUNT(*) AS absent_count,
            MAX(a.attendance_date) AS last_absence_date,
            m.user_id,
            m.player_phone,
            m.first_name,
            m.last_name,
            m.national_id,
            c.title AS course_title
         FROM $attendances_table a
         INNER JOIN $members_table m ON m.id = a.member_id
         LEFT JOIN $courses_table c ON c.id = a.course_id
         WHERE a.status = %s
           AND m.is_active = 1
         GROUP BY a.member_id, a.course_id
         HAVING COUNT(*) > %d
         ORDER BY absent_count DESC, last_absence_date DESC",
        'absent',
        $limit
    ));

    return is_array($rows) ? $rows : [];
}

function sc_alert_notification_exists_today($alert_key, $target_type, $check_today_only = true) {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $alert_key_like = '%"alert_key":"' . $wpdb->esc_like($alert_key) . '"%';
    $sql = "SELECT id FROM $notifications_table
         WHERE notification_type = %s
           AND target_type = %s
           AND target_config LIKE %s";
    $params = ['system', $target_type, $alert_key_like];
    if ($check_today_only) {
        $today = current_time('Y-m-d');
        $sql .= " AND DATE(created_at) = %s";
        $params[] = $today;
    }
    $sql .= " ORDER BY id DESC LIMIT 1";
    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
}

/**
 * Alert set #2: users with more than 2 debts.
 */
function sc_get_multiple_debt_alerts() {
    global $wpdb;

    $members_table = $wpdb->prefix . 'sc_members';
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    $rows = $wpdb->get_results(
        "SELECT
            m.id AS member_id,
            m.first_name,
            m.last_name,
            m.national_id,
            COUNT(i.id) AS debt_count
         FROM $members_table m
         INNER JOIN $invoices_table i ON i.member_id = m.id
         WHERE m.is_active = 1
           AND i.status IN ('pending', 'under_review')
           AND (i.course_id > 0 OR i.invoice_description IS NOT NULL)
         GROUP BY m.id
         HAVING COUNT(i.id) > 2
         ORDER BY debt_count DESC, m.last_name ASC, m.first_name ASC"
    );

    $result = [];
    foreach ((array) $rows as $row) {
        $debt_data = function_exists('debt_user') ? debt_user((int) $row->member_id) : [0, (int) $row->debt_count];
        $row->debt_amount = isset($debt_data[0]) ? (float) $debt_data[0] : 0;
        $result[] = $row;
    }

    return $result;
}

function sc_create_system_alert_notification($alert_key, $alert_kind, $title, $content, $meta = [], $check_today_only = true) {
    $existing_id = sc_alert_notification_exists_today($alert_key, 'admin_users', $check_today_only);
    if ($existing_id > 0) {
        return $existing_id;
    }

    $result = sc_save_notification([
        'title' => $title,
        'content' => $content,
        'target_type' => 'admin_users',
        'target_config' => [
            'roles' => array_merge(['administrator'], function_exists('sc_get_club_manager_role_slugs') ? sc_get_club_manager_role_slugs() : ['club_coach']),
            'alert_kind' => $alert_kind,
            'alert_key' => $alert_key,
            'alert_meta' => $meta,
        ],
        'notification_type' => 'system',
        'send_sms' => 0,
    ]);
    return !empty($result['success']) ? (int) $result['notification_id'] : 0;
}

function sc_send_absence_alert_admin_sms($item, $absence_limit) {
    if (!function_exists('sc_is_sms_enabled_for') || !function_exists('sc_get_sms_template') || !function_exists('sc_replace_sms_variables') || !function_exists('sc_send_sms')) {
        return;
    }
    if (!sc_is_sms_enabled_for('absence_alert', 'admin')) {
        return;
    }
    $admin_phone = function_exists('sc_get_setting') ? sc_get_setting('sms_admin_phone', '') : '';
    if (empty($admin_phone)) {
        return;
    }
    $template = sc_get_sms_template('absence_alert', 'admin');
    if ($template === '') {
        return;
    }
    $member_name = trim((string) $item->first_name . ' ' . (string) $item->last_name);
    $course_title = (string) ($item->course_title ?: 'دوره');
    $last_date = !empty($item->last_absence_date) && function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only($item->last_absence_date)
        : (string) $item->last_absence_date;
    $variables = [
        'user_name' => $member_name,
        'course_name' => $course_title,
        'item_name' => $course_title,
        'date' => $last_date,
        'absence_count' => (int) $item->absent_count,
        'absence_limit' => (int) $absence_limit,
    ];
    $message = sc_replace_sms_variables($template, $variables);
    $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('absence_alert', 'admin') : null;
    sc_send_sms($admin_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'absence_alert');
}

function sc_create_member_absence_alert_notification_and_sms($item, $absence_limit) {
    $member_id = (int) $item->member_id;
    if ($member_id <= 0) {
        return 0;
    }

    $alert_key = 'absence_user_' . $member_id . '_' . (int) $item->course_id;
    $existing_id = sc_alert_notification_exists_today($alert_key, 'specific', false);
    if ($existing_id > 0) {
        return $existing_id;
    }

    $member_name = trim((string) $item->first_name . ' ' . (string) $item->last_name);
    $course_title = (string) ($item->course_title ?: 'دوره');
    $last_date = !empty($item->last_absence_date) && function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only($item->last_absence_date)
        : (string) $item->last_absence_date;

    $title = sprintf(
        'هشدار غیبت برای %s - تعداد غیبت %d - دوره %s',
        $member_name,
        (int) $item->absent_count,
        $course_title
    );
    $content = sprintf(
        'کاربر %s در دوره %s بیش از حد مجاز تعیین شده غیبت داشته است.',
        $member_name,
        $course_title
    )
        . ($last_date !== '' ? ' آخرین غیبت: ' . $last_date : '');

    $result = sc_save_notification([
        'title' => $title,
        'content' => $content,
        'target_type' => 'specific',
        'target_config' => [
            'recipient_ids' => ['member_' . $member_id],
            'alert_kind' => 'absence_limit_user',
            'alert_key' => $alert_key,
            'alert_meta' => [
                'member_id' => $member_id,
                'course_id' => (int) $item->course_id,
                'absent_count' => (int) $item->absent_count,
                'absence_limit' => (int) $absence_limit,
            ],
        ],
        'notification_type' => 'system',
        'send_sms' => 0,
    ]);

    if (function_exists('sc_is_sms_enabled_for') && function_exists('sc_get_sms_template') && function_exists('sc_replace_sms_variables') && function_exists('sc_send_sms')) {
        if (sc_is_sms_enabled_for('absence_alert', 'user')) {
            $template = sc_get_sms_template('absence_alert', 'user');
            $phone = !empty($item->player_phone) ? (string) $item->player_phone : '';
            if ($phone === '' && !empty($item->user_id) && function_exists('sc_get_user_phone')) {
                $phone = (string) sc_get_user_phone((int) $item->user_id);
            }
            if ($template !== '' && $phone !== '') {
                $variables = [
                    'user_name' => $member_name,
                    'course_name' => $course_title,
                    'item_name' => $course_title,
                    'date' => $last_date,
                    'absence_count' => (int) $item->absent_count,
                    'absence_limit' => (int) $absence_limit,
                ];
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('absence_alert', 'user') : null;
                sc_send_sms($phone, $message, !empty($pattern_code), $pattern_code, $variables, 'absence_alert');
            }
        }
    }

    return !empty($result['success']) ? (int) $result['notification_id'] : 0;
}

function sc_generate_system_alert_notifications($force = false) {
    $created_count = 0;
    $absence_limit = sc_get_user_alert_absence_limit();
    $absence_alerts = sc_get_absence_limit_alerts();
    foreach ($absence_alerts as $item) {
        $member_name = trim((string) $item->first_name . ' ' . (string) $item->last_name);
        $course_title = (string) ($item->course_title ?: 'بدون نام دوره');
        $last_date = !empty($item->last_absence_date) && function_exists('sc_date_shamsi_date_only')
            ? sc_date_shamsi_date_only($item->last_absence_date)
            : (string) $item->last_absence_date;
        $alert_key = 'absence_' . (int) $item->member_id . '_' . (int) $item->course_id;
        $absent_n = (int) $item->absent_count;
        $title = sprintf(
            'هشدار غیبت برای %s - تعداد غیبت %d - دوره %s',
            $member_name !== '' ? $member_name : 'کاربر',
            $absent_n,
            $course_title
        );
        $content = sprintf(
            'کاربر %s در دوره %s بیش از حد مجاز تعیین شده غیبت داشته است.',
            $member_name !== '' ? $member_name : 'نامشخص',
            $course_title
        );
        if ($last_date !== '') {
            $content .= ' آخرین غیبت: ' . $last_date;
        }
        $created = sc_create_system_alert_notification($alert_key, 'absence_limit', $title, $content, [
            'member_id' => (int) $item->member_id,
            'course_id' => (int) $item->course_id,
            'absent_count' => (int) $item->absent_count,
            'absence_limit' => (int) $absence_limit,
        ], false);
        if ($created > 0) {
            $created_count++;
            sc_send_absence_alert_admin_sms($item, $absence_limit);
        }
        sc_create_member_absence_alert_notification_and_sms($item, $absence_limit);
    }

    $debt_alerts = sc_get_multiple_debt_alerts();
    foreach ($debt_alerts as $item) {
        $member_name = trim((string) $item->first_name . ' ' . (string) $item->last_name);
        $alert_key = 'debt_' . (int) $item->member_id;
        $title = 'هشدار بدهی بیش از ۲ مورد';
        $content = 'بازیکن ' . $member_name . ' دارای ' . (int) $item->debt_count
            . ' بدهی  است. مجموع بدهی: ' . number_format((float) $item->debt_amount, 0, '.', ',') . ' تومان.';
        $created = sc_create_system_alert_notification($alert_key, 'debt_over_2', $title, $content, [
            'member_id' => (int) $item->member_id,
            'debt_count' => (int) $item->debt_count,
            'debt_amount' => (float) $item->debt_amount,
        ], false);
        if ($created > 0) {
            $created_count++;
        }
    }

    return $created_count;
}

function sc_get_admin_system_alert_notifications($user_id, $args = []) {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    $defaults = [
        'search' => '',
        'kind' => 'all',
        'status' => 'all',
        'date_from' => '',
        'date_to' => '',
        'per_page' => 0,
        'offset' => 0,
        'count_only' => false,
    ];
    $args = array_merge($defaults, (array) $args);

    $where = [
        'nr.user_id = %d',
        'n.notification_type = %s',
        'n.target_type = %s',
    ];
    $params = [(int) $user_id, 'system', 'admin_users'];

    if (!empty($args['search'])) {
        $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
        $where[] = '(n.title LIKE %s OR n.content LIKE %s)';
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($args['kind']) && $args['kind'] !== 'all') {
        $kind = (string) $args['kind'];
        if ($kind === 'other') {
            $where[] = '(n.target_config NOT LIKE %s AND n.target_config NOT LIKE %s)';
            $params[] = '%"alert_kind":"absence_limit"%';
            $params[] = '%"alert_kind":"debt_over_2"%';
        } else {
            $where[] = 'n.target_config LIKE %s';
            $params[] = '%"alert_kind":"' . $wpdb->esc_like($kind) . '"%';
        }
    }

    if (!empty($args['date_from'])) {
        $where[] = 'DATE(n.created_at) >= %s';
        $params[] = (string) $args['date_from'];
    }
    if (!empty($args['date_to'])) {
        $where[] = 'DATE(n.created_at) <= %s';
        $params[] = (string) $args['date_to'];
    }

    if (!empty($args['status']) && $args['status'] !== 'all') {
        if ($args['status'] === 'read') {
            $where[] = 'r.read_at IS NOT NULL';
        } elseif ($args['status'] === 'unread') {
            $where[] = 'r.read_at IS NULL';
        }
    }

    $where_sql = implode(' AND ', $where);

    if (!empty($args['count_only'])) {
        $sql = "SELECT COUNT(*)
                FROM $recipients_table nr
                INNER JOIN $notifications_table n ON n.id = nr.notification_id
                LEFT JOIN $reads_table r ON r.notification_id = n.id AND r.user_id = %d
                WHERE $where_sql";
        $count_params = array_merge([(int) $user_id], $params);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $count_params));
    }

    $sql = "SELECT n.id, n.title, n.content, n.created_at, n.target_config,
                CASE WHEN r.read_at IS NULL THEN 0 ELSE 1 END AS is_read,
                r.read_at
            FROM $recipients_table nr
            INNER JOIN $notifications_table n ON n.id = nr.notification_id
            LEFT JOIN $reads_table r ON r.notification_id = n.id AND r.user_id = %d
            WHERE $where_sql
            ORDER BY is_read ASC, n.created_at DESC";

    $query_params = array_merge([(int) $user_id], $params);

    if (!empty($args['per_page']) && (int) $args['per_page'] > 0) {
        $sql .= ' LIMIT %d OFFSET %d';
        $query_params[] = (int) $args['per_page'];
        $query_params[] = max(0, (int) $args['offset']);
    }

    return $wpdb->get_results($wpdb->prepare($sql, $query_params));
}

function sc_delete_system_alert_notification($notification_id) {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    $wpdb->delete($reads_table, ['notification_id' => $notification_id], ['%d']);
    $wpdb->delete($recipients_table, ['notification_id' => $notification_id], ['%d']);
    $wpdb->delete($notifications_table, ['id' => $notification_id], ['%d']);
}

/**
 * Render admin alerts page.
 */
function sc_render_user_alerts_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }

    sc_check_and_create_tables();

    $current_user_id = get_current_user_id();
    $message = '';
    $message_type = 'success';
    $action = isset($_GET['sc_alert_action']) ? sanitize_key($_GET['sc_alert_action']) : '';
    $notification_id = isset($_GET['notification_id']) ? absint($_GET['notification_id']) : 0;

    if (
        isset($_POST['sc_user_alerts_bulk_action'], $_POST['_wpnonce'])
        && sanitize_key(wp_unslash($_POST['sc_user_alerts_bulk_action'])) === 'confirm'
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'sc_bulk_confirm_user_alerts')
    ) {
        $raw_ids = isset($_POST['sc_alert_notification_ids']) ? wp_unslash($_POST['sc_alert_notification_ids']) : [];
        $raw_ids = is_array($raw_ids) ? $raw_ids : [];
        $confirmed = 0;
        foreach ($raw_ids as $nid) {
            $nid = absint($nid);
            if ($nid > 0 && sc_mark_notification_read($nid, $current_user_id)) {
                $confirmed++;
            }
        }
        $message = $confirmed > 0
            ? sprintf('%d هشدار تایید شد.', $confirmed)
            : 'هیچ هشداری برای تایید انتخاب نشده بود یا تایید انجام نشد.';
        $message_type = $confirmed > 0 ? 'success' : 'warning';
    } elseif ($action === 'generate' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'sc_generate_system_alerts')) {
        $created = sc_generate_system_alert_notifications(true);
        $message = $created > 0
            ? sprintf('%d هشدار جدید ثبت شد.', $created)
            : 'مورد جدیدی برای ثبت هشدار وجود نداشت.';
    } elseif ($action === 'confirm' && $notification_id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'sc_confirm_alert_' . $notification_id)) {
        sc_mark_notification_read($notification_id, $current_user_id);
        $message = 'هشدار تایید شد.';
    } elseif ($action === 'delete' && $notification_id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'sc_delete_alert_' . $notification_id)) {
        sc_delete_system_alert_notification($notification_id);
        $message = 'هشدار حذف شد.';
    }

    $filter_search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
    $filter_kind = isset($_REQUEST['filter_kind']) ? sanitize_key(wp_unslash($_REQUEST['filter_kind'])) : 'all';
    if (!in_array($filter_kind, ['all', 'absence_limit', 'debt_over_2', 'other'], true)) {
        $filter_kind = 'all';
    }
    $filter_status = isset($_REQUEST['filter_status']) ? sanitize_key(wp_unslash($_REQUEST['filter_status'])) : 'all';
    if (!in_array($filter_status, ['all', 'read', 'unread'], true)) {
        $filter_status = 'all';
    }

    $filter_date_from = '';
    $filter_date_to = '';
    $filter_date_from_shamsi = isset($_REQUEST['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_REQUEST['filter_date_from_shamsi'])) : '';
    $filter_date_to_shamsi = isset($_REQUEST['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_REQUEST['filter_date_to_shamsi'])) : '';
    if ($filter_date_from_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
        $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
    }
    if ($filter_date_to_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
        $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
    }

    $per_page_allowed = [10, 20, 50, 100, 200];
    $per_page = isset($_REQUEST['per_page']) ? absint($_REQUEST['per_page']) : 20;
    if (!in_array($per_page, $per_page_allowed, true)) {
        $per_page = 20;
    }

    $current_page = isset($_REQUEST['paged']) ? max(1, absint($_REQUEST['paged'])) : 1;

    $query_args_base = [
        'search' => $filter_search,
        'kind' => $filter_kind,
        'status' => $filter_status,
        'date_from' => $filter_date_from,
        'date_to' => $filter_date_to,
    ];

    $total_items = (int) sc_get_admin_system_alert_notifications(
        $current_user_id,
        array_merge($query_args_base, ['count_only' => true])
    );
    $total_pages = $per_page > 0 ? max(1, (int) ceil($total_items / $per_page)) : 1;
    $current_page = min($current_page, $total_pages);
    $offset = ($current_page - 1) * $per_page;

    $query_args = array_merge($query_args_base, [
        'per_page' => $per_page,
        'offset' => $offset,
    ]);

    $items = sc_get_admin_system_alert_notifications($current_user_id, $query_args);
    $absence_limit = sc_get_user_alert_absence_limit();

    $active_filters_count = 0;
    if ($filter_kind !== 'all') {
        $active_filters_count++;
    }
    if ($filter_status !== 'all') {
        $active_filters_count++;
    }
    if ($filter_search !== '') {
        $active_filters_count++;
    }
    if ($filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') {
        $active_filters_count++;
    }
    if ($per_page !== 20) {
        $active_filters_count++;
    }
    $filters_open = $active_filters_count > 0;

    $generate_url = wp_nonce_url(
        add_query_arg(['page' => 'sc-user-alerts', 'sc_alert_action' => 'generate'], admin_url('admin.php')),
        'sc_generate_system_alerts'
    );
    $sc_user_alerts_bulk_action_url = admin_url('admin.php?page=sc-user-alerts');
    $list_url = admin_url('admin.php?page=sc-user-alerts');
    ?>

    <?php if ($message !== '') : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <div class="wrap sc-alerts-list-wrap">
        <div class="sc-alerts-list-header">
            <div class="sc-alerts-list-header-text">
                <h1 class="sc-alerts-list-title">هشدارهای کاربر</h1>
                <p class="sc-alerts-list-desc">هشدارهای سیستمی غیبت، بدهی و موارد نیازمند تایید مدیر<?php echo $absence_limit ? ' — حد مجاز غیبت: ' . (int) $absence_limit : ''; ?></p>
            </div>
            <div class="sc-alerts-list-header-actions">
                <a href="<?php echo esc_url($generate_url); ?>" class="sc-alerts-list-add-btn">بررسی و تولید هشدار</a>
            </div>
        </div>

        <div class="sc-alerts-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
            <div class="sc-alerts-list-filters-toolbar">
                <button type="button" class="sc-alerts-list-filters-toggle" id="sc-alerts-filters-toggle" aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>" aria-controls="sc-alerts-filters-panel">
                    <span class="sc-alerts-list-filters-toggle-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <span class="sc-alerts-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها"><?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?></span>
                    <?php if ($active_filters_count > 0) : ?>
                        <span class="sc-alerts-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                    <?php endif; ?>
                    <span class="sc-alerts-list-filters-chevron" aria-hidden="true"></span>
                </button>
                <?php if ($active_filters_count > 0) : ?>
                    <a href="<?php echo esc_url($list_url); ?>" class="sc-alerts-list-filters-clear">پاک کردن فیلترها</a>
                <?php endif; ?>
            </div>

            <form method="get" action="" class="sc-alerts-list-filters-panel" id="sc-alerts-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
                <input type="hidden" name="page" value="sc-user-alerts">
                <div class="sc-filter-grid">
                    <div class="sc-filter-field">
                        <label class="sc-filter-label" for="filter_kind">نوع هشدار</label>
                        <select name="filter_kind" id="filter_kind" class="sc-filter-control">
                            <option value="all" <?php selected($filter_kind, 'all'); ?>>همه</option>
                            <option value="absence_limit" <?php selected($filter_kind, 'absence_limit'); ?>>غیبت بیش از حد</option>
                            <option value="debt_over_2" <?php selected($filter_kind, 'debt_over_2'); ?>>بیش از ۲ بدهی</option>
                            <option value="other" <?php selected($filter_kind, 'other'); ?>>سایر سیستمی</option>
                        </select>
                    </div>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label" for="filter_status">وضعیت</label>
                        <select name="filter_status" id="filter_status" class="sc-filter-control">
                            <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                            <option value="unread" <?php selected($filter_status, 'unread'); ?>>در انتظار تایید</option>
                            <option value="read" <?php selected($filter_status, 'read'); ?>>تایید شده</option>
                        </select>
                    </div>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label" for="user_alerts_search">جستجو</label>
                        <input type="search" id="user_alerts_search" name="s" class="sc-filter-control" value="<?php echo esc_attr($filter_search); ?>" placeholder="جستجو در عنوان و متن...">
                    </div>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label" for="user_alerts_per_page">تعداد در صفحه</label>
                        <select name="per_page" id="user_alerts_per_page" class="sc-filter-control">
                            <?php foreach ($per_page_allowed as $pp) : ?>
                                <option value="<?php echo esc_attr((string) $pp); ?>" <?php selected($per_page, $pp); ?>><?php echo esc_html((string) $pp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-filter-field sc-filter-date">
                        <label class="sc-filter-label">بازه تاریخ (شمسی)</label>
                        <div class="sc-date-range sc-alerts-date-range">
                            <input type="text" name="filter_date_from_shamsi" id="user_alerts_filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="از تاریخ" readonly>
                            <input type="text" name="filter_date_to_shamsi" id="user_alerts_filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="تا تاریخ" readonly>
                        </div>
                    </div>
                </div>
                <div class="sc-alerts-list-filters-actions">
                    <input type="submit" class="button button-primary" value="اعمال فیلتر">
                    <a href="<?php echo esc_url($list_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                </div>
            </form>
        </div>

        <div class="sc-alerts-list-table-card">
            <div class="sc-alerts-list-summary"><span><?php echo (int) $total_items; ?> هشدار</span></div>
            <form method="post" action="<?php echo esc_url($sc_user_alerts_bulk_action_url); ?>" id="sc-user-alerts-bulk-form" class="sc-user-alerts-list-form">
                <?php wp_nonce_field('sc_bulk_confirm_user_alerts'); ?>
                <?php if ($filter_kind !== 'all') : ?><input type="hidden" name="filter_kind" value="<?php echo esc_attr($filter_kind); ?>"><?php endif; ?>
                <?php if ($filter_status !== 'all') : ?><input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>"><?php endif; ?>
                <?php if ($filter_search !== '') : ?><input type="hidden" name="s" value="<?php echo esc_attr($filter_search); ?>"><?php endif; ?>
                <?php if ($filter_date_from_shamsi !== '') : ?><input type="hidden" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>"><?php endif; ?>
                <?php if ($filter_date_to_shamsi !== '') : ?><input type="hidden" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>"><?php endif; ?>
                <?php if ($current_page > 1) : ?><input type="hidden" name="paged" value="<?php echo esc_attr((string) $current_page); ?>"><?php endif; ?>
                <?php if ($per_page !== 20) : ?><input type="hidden" name="per_page" value="<?php echo esc_attr((string) $per_page); ?>"><?php endif; ?>

                <div class="tablenav top sc-alerts-bulk-nav">
                    <label for="sc_user_alerts_bulk_action" class="screen-reader-text">عملیات دسته‌جمعی</label>
                    <select name="sc_user_alerts_bulk_action" id="sc_user_alerts_bulk_action">
                        <option value=""><?php echo esc_html('عملیات دسته‌جمعی'); ?></option>
                        <option value="confirm"><?php echo esc_html('تایید انتخاب‌شده‌ها'); ?></option>
                    </select>
                    <button type="submit" class="button action"><?php echo esc_html('اعمال'); ?></button>
                </div>

                <div class="sc-alerts-table-scroll">
                    <table class="wp-list-table widefat striped sc-alerts-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="sc-select-all-alerts" aria-label="<?php echo esc_attr('انتخاب همه در این صفحه'); ?>">
                                </td>
                                <th class="manage-column">ردیف</th>
                                <th class="manage-column">نوع هشدار</th>
                                <th class="manage-column">عنوان</th>
                                <th class="manage-column">متن</th>
                                <th class="manage-column">تاریخ</th>
                                <th class="manage-column">وضعیت</th>
                                <th class="manage-column">اقدامات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="8" class="sc-alerts-empty">هیچ هشداری با این فیلترها یافت نشد.</td></tr>
                            <?php else : ?>
                                <?php $row_number = $offset; ?>
                                <?php foreach ($items as $item) :
                                    $row_number++;
                                    $cfg = !empty($item->target_config) ? json_decode($item->target_config, true) : [];
                                    $kind = is_array($cfg) && !empty($cfg['alert_kind']) ? (string) $cfg['alert_kind'] : '';
                                    $kind_label = $kind === 'absence_limit' ? 'غیبت بیش از حد' : ($kind === 'debt_over_2' ? 'بیش از ۲ بدهی' : 'سیستمی');
                                    $kind_badge = $kind === 'absence_limit' ? 'sc-badge--warning' : ($kind === 'debt_over_2' ? 'sc-badge--danger' : 'sc-badge--purple');
                                    $confirm_url = wp_nonce_url(
                                        add_query_arg(['page' => 'sc-user-alerts', 'sc_alert_action' => 'confirm', 'notification_id' => (int) $item->id], admin_url('admin.php')),
                                        'sc_confirm_alert_' . (int) $item->id
                                    );
                                    ?>
                                    <tr class="<?php echo empty($item->is_read) ? 'is-unread' : 'is-read'; ?>">
                                        <th scope="row" class="check-column">
                                            <?php if (empty($item->is_read)) : ?>
                                                <input type="checkbox" class="sc-alert-row-cb" name="sc_alert_notification_ids[]" value="<?php echo (int) $item->id; ?>">
                                            <?php endif; ?>
                                        </th>
                                        <td data-label="ردیف"><?php echo esc_html((string) $row_number); ?></td>
                                        <td data-label="نوع هشدار">
                                            <div class="sc-alerts-kind-cell">
                                                <span class="sc-alerts-kind-icon sc-alerts-kind-icon--<?php echo esc_attr($kind !== '' ? $kind : 'other'); ?>" aria-hidden="true"></span>
                                                <span class="sc-badge <?php echo esc_attr($kind_badge); ?>"><?php echo esc_html($kind_label); ?></span>
                                            </div>
                                        </td>
                                        <td data-label="عنوان"><strong class="sc-alerts-title"><?php echo esc_html((string) $item->title); ?></strong></td>
                                        <td data-label="متن" class="sc-alerts-content"><?php echo esc_html((string) $item->content); ?></td>
                                        <td data-label="تاریخ"><?php echo function_exists('sc_date_shamsi') ? esc_html(sc_date_shamsi($item->created_at, 'Y/m/d H:i')) : esc_html((string) $item->created_at); ?></td>
                                        <td data-label="وضعیت">
                                            <?php if (!empty($item->is_read)) : ?>
                                                <span class="sc-badge sc-badge--success"><?php echo esc_html('تایید شده'); ?></span>
                                            <?php else : ?>
                                                <span class="sc-badge sc-badge--warning"><?php echo esc_html('در انتظار تایید'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="اقدامات" class="sc-alerts-actions">
                                            <?php if (empty($item->is_read)) : ?>
                                                <a href="<?php echo esc_url($confirm_url); ?>" class="sc-alerts-action-link"><?php echo esc_html('تایید'); ?></a>
                                            <?php else : ?>
                                                <span class="sc-alerts-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = ['page' => 'sc-user-alerts'];
                        if ($filter_kind !== 'all') {
                            $pagination_args['filter_kind'] = $filter_kind;
                        }
                        if ($filter_status !== 'all') {
                            $pagination_args['filter_status'] = $filter_status;
                        }
                        if ($filter_search !== '') {
                            $pagination_args['s'] = $filter_search;
                        }
                        if ($filter_date_from_shamsi !== '') {
                            $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                        }
                        if ($filter_date_to_shamsi !== '') {
                            $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                        }
                        if ($per_page !== 20) {
                            $pagination_args['per_page'] = $per_page;
                        }
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                            'format' => '',
                            'prev_text' => '‹',
                            'next_text' => '›',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'add_args' => $pagination_args,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    jQuery(function($){
        var $toggle = $('#sc-alerts-filters-toggle');
        var $panel = $('#sc-alerts-filters-panel');
        var $card = $toggle.closest('.sc-alerts-list-filters-card');
        var $label = $toggle.find('.sc-alerts-list-filters-toggle-label');
        $toggle.on('click', function(){
            var isOpen = $card.hasClass('is-open');
            if (isOpen) {
                $card.removeClass('is-open');
                $panel.attr('hidden', true);
                $toggle.attr('aria-expanded', 'false');
                $label.text($label.data('label-closed'));
            } else {
                $card.addClass('is-open');
                $panel.removeAttr('hidden');
                $toggle.attr('aria-expanded', 'true');
                $label.text($label.data('label-open'));
            }
        });

        var master = document.getElementById('sc-select-all-alerts');
        var form = document.getElementById('sc-user-alerts-bulk-form');
        if (master) {
            master.addEventListener('change', function () {
                document.querySelectorAll('#sc-user-alerts-bulk-form .sc-alert-row-cb').forEach(function (cb) {
                    cb.checked = master.checked;
                });
            });
        }
        if (form) {
            form.addEventListener('submit', function (e) {
                var sel = document.getElementById('sc_user_alerts_bulk_action');
                if (!sel || sel.value !== 'confirm') return;
                var any = document.querySelector('#sc-user-alerts-bulk-form .sc-alert-row-cb:checked');
                if (!any) {
                    e.preventDefault();
                    window.alert('<?php echo esc_js('حداقل یک هشدار «در انتظار تایید» را انتخاب کنید.'); ?>');
                }
            });
        }
    });
    </script>
    <?php
}
