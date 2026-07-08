<?php
if (!defined('ABSPATH')) {
    exit;
}

define('SC_BOT_LIST_PER_PAGE', 5);

/**
 * همان منطق sc_get_current_member_for_account_user — برای تطابق دادهٔ سایت و ربات
 *
 * @return object|null
 */
function sc_bot_get_member_for_wp_user($user_id) {
    global $wpdb;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }

    $table = $wpdb->prefix . 'sc_members';
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d LIMIT 1",
        $user_id
    ));

    if (!$player) {
        $billing_phone = get_user_meta($user_id, 'billing_phone', true);
        if ($billing_phone) {
            $player = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE player_phone = %s LIMIT 1",
                $billing_phone
            ));
            if (!$player && function_exists('sc_clean_mobile_number')) {
                $clean = sc_clean_mobile_number($billing_phone);
                if ($clean) {
                    $player = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table WHERE player_phone = %s LIMIT 1",
                        $clean
                    ));
                }
            }
        }
    }

    return $player ?: null;
}

/**
 * @return array{user_id:int,member_id:int,full_name:string,chat_id:int|string}|false
 */
function sc_bot_resolve_member($chat_id) {
    $ctx = sc_bot_resolve_user_context($chat_id);
    if (!$ctx || empty($ctx['connected'])) {
        return false;
    }

    if (($ctx['active_role'] ?? '') !== 'player' && empty($ctx['member_id'])) {
        if (in_array('player', $ctx['available_roles'] ?? [], true)) {
            $player = sc_bot_get_member_for_wp_user((int) $ctx['user_id']);
            if ($player) {
                $ctx['member_id'] = (int) $player->id;
            }
        }
    }

    if (empty($ctx['member_id']) && ($ctx['active_role'] ?? '') === 'player') {
        return false;
    }

    return [
        'user_id'    => (int) $ctx['user_id'],
        'member_id'  => (int) ($ctx['member_id'] ?? 0),
        'full_name'  => (string) ($ctx['full_name'] ?? ''),
        'chat_id'    => $chat_id,
        'active_role' => (string) ($ctx['active_role'] ?? 'player'),
        'coach_id'   => (int) ($ctx['coach_id'] ?? 0),
    ];
}

function sc_bot_admin_link_button($label, $page, $args = []) {
    $url = add_query_arg(array_merge(['page' => $page], (array) $args), admin_url('admin.php'));
    return [bale_make_link_button($label, $url)];
}

function sc_bot_format_amount($amount) {
    if (function_exists('sc_format_amount_display')) {
        return sc_format_amount_display((float) $amount);
    }
    return number_format((float) $amount, 0, '.', ',') . ' تومان';
}

function sc_bot_format_date($datetime) {
    if ($datetime === null || $datetime === '') {
        return '-';
    }
    if (function_exists('sc_date_shamsi_date_only')) {
        return sc_date_shamsi_date_only($datetime);
    }
    if (function_exists('sc_date_shamsi')) {
        return sc_date_shamsi($datetime);
    }
    return (string) $datetime;
}

function sc_bot_truncate($text, $max = 120) {
    $text = trim(wp_strip_all_tags((string) $text));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 1) . '…';
}

function sc_bot_invoice_status_label($status) {
    $map = [
        'paid'          => '✅ تایید پرداخت',
        'completed'     => '✅ تایید پرداخت',
        'processing'    => '✅ پرداخت شده',
        'pending'       => '⏳ در انتظار پرداخت',
        'under_review'  => '🔍 در حال بررسی',
        'on-hold'       => '🔍 در حال بررسی',
        'cancelled'     => '❌ لغو شده',
        'refunded'      => '↩️ بازگشت شده',
        'failed'        => '⚠️ ناموفق',
    ];
    return $map[$status] ?? '⏳ در انتظار پرداخت';
}

function sc_bot_invoice_item_name($invoice) {
    if (!empty($invoice->course_title)) {
        return $invoice->course_title;
    }
    if (!empty($invoice->event_name)) {
        return $invoice->event_name;
    }
    if (!empty($invoice->expense_name)) {
        return $invoice->expense_name;
    }
    return 'صورتحساب';
}

function sc_bot_wallet_type_label($type) {
    $map = [
        'charge'       => 'شارژ',
        'deduct'       => 'کسر',
        'payment'      => 'پرداخت',
        'refund'       => 'بازگشت',
        'session_fee'  => 'هزینه جلسه',
    ];
    return $map[$type] ?? (string) $type;
}

function sc_bot_wc_order_status_label($status) {
    $status = str_replace('wc-', '', (string) $status);
    $map = [
        'completed'  => '✅ تکمیل شده',
        'processing' => '📦 در حال پردازش',
        'on-hold'    => '🔍 در حال بررسی',
        'pending'    => '⏳ در انتظار پرداخت',
        'cancelled'  => '❌ لغو شده',
        'failed'     => '⚠️ ناموفق',
        'refunded'   => '↩️ بازگشت شده',
    ];
    return $map[$status] ?? $status;
}

function sc_bot_get_dashboard_summary(array $ctx) {
    global $wpdb;

    $member_id = (int) $ctx['member_id'];
    $user_id = (int) $ctx['user_id'];
    $summary = [
        'pending_invoices_count'  => 0,
        'pending_invoices_total'  => 0,
        'wallet_balance'          => null,
        'unread_notifications'    => 0,
        'certificates_count'      => 0,
        'upcoming_private'        => 0,
    ];

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount + COALESCE(penalty_amount, 0)), 0) AS total_amount
         FROM $invoices_table
         WHERE member_id = %d AND status IN ('pending', 'under_review')",
        $member_id
    ));
    if ($row) {
        $summary['pending_invoices_count'] = (int) $row->cnt;
        $summary['pending_invoices_total'] = (float) $row->total_amount;
    }

    if (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet() && function_exists('sc_get_wallet_balance')) {
        $summary['wallet_balance'] = (float) sc_get_wallet_balance($member_id);
    }

    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()
        && function_exists('sc_count_unread_notifications')) {
        $summary['unread_notifications'] = (int) sc_count_unread_notifications($user_id);
    }

    if (function_exists('sc_count_member_certificates')) {
        $summary['certificates_count'] = sc_count_member_certificates($member_id);
    }

    $sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
    $today = current_time('Y-m-d');
    $summary['upcoming_private'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $sessions_table WHERE member_id = %d AND session_date >= %s AND status NOT IN ('cancelled')",
        $member_id,
        $today
    ));

    return $summary;
}

function sc_bot_get_member_invoices($member_id, $page = 1, $per_page = SC_BOT_LIST_PER_PAGE) {
    global $wpdb;

    $member_id = (int) $member_id;
    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);
    $offset = ($page - 1) * $per_page;

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $events_table = $wpdb->prefix . 'sc_events';

    $from_sql = "FROM {$invoices_table} i
        LEFT JOIN {$courses_table} c ON i.course_id = c.id
        LEFT JOIN {$events_table} e ON i.event_id = e.id
        WHERE i.member_id = %d";

    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) {$from_sql}", $member_id));
    $total_pages = max(1, (int) ceil($total / $per_page));

    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, c.title AS course_title, e.name AS event_name
         {$from_sql}
         ORDER BY
            CASE WHEN i.status IN ('pending','under_review') THEN 0 ELSE 1 END,
            i.created_at DESC
         LIMIT %d OFFSET %d",
        $member_id,
        $per_page,
        $offset
    ));

    return compact('items', 'total', 'page', 'per_page', 'total_pages');
}

function sc_bot_get_invoice_detail($invoice_id, $member_id) {
    global $wpdb;

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $events_table = $wpdb->prefix . 'sc_events';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, c.title AS course_title, e.name AS event_name
         FROM {$invoices_table} i
         LEFT JOIN {$courses_table} c ON i.course_id = c.id
         LEFT JOIN {$events_table} e ON i.event_id = e.id
         WHERE i.id = %d AND i.member_id = %d LIMIT 1",
        (int) $invoice_id,
        (int) $member_id
    ));
}

function sc_bot_get_invoice_payment_url($invoice) {
    if (empty($invoice->woocommerce_order_id) || !function_exists('wc_get_order')) {
        return '';
    }
    if (!in_array($invoice->status, ['pending', 'under_review'], true)) {
        return '';
    }
    $order = wc_get_order((int) $invoice->woocommerce_order_id);
    if (!$order || $order->is_paid()) {
        return '';
    }
    if ($invoice->status === 'pending') {
        return $order->get_checkout_payment_url();
    }
    return $order->get_view_order_url();
}

function sc_bot_get_certificates_list($member_id, $page = 1, $per_page = SC_BOT_LIST_PER_PAGE) {
    $member_id = (int) $member_id;
    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);
    $offset = ($page - 1) * $per_page;

    $total = function_exists('sc_count_member_certificates') ? sc_count_member_certificates($member_id) : 0;
    $total_pages = max(1, (int) ceil($total / $per_page));
    $items = function_exists('sc_get_member_certificates')
        ? sc_get_member_certificates($member_id, $per_page, $offset)
        : [];

    return compact('items', 'total', 'page', 'per_page', 'total_pages');
}

function sc_bot_get_wallet_data($member_id, $trans_page = 1, $per_page = SC_BOT_LIST_PER_PAGE) {
    $member_id = (int) $member_id;
    $balance = function_exists('sc_get_wallet_balance') ? (float) sc_get_wallet_balance($member_id) : 0;
    $trans_page = max(1, (int) $trans_page);
    $offset = ($trans_page - 1) * $per_page;
    $total = function_exists('sc_get_wallet_transactions_count') ? sc_get_wallet_transactions_count($member_id) : 0;
    $total_pages = max(1, (int) ceil($total / $per_page));
    $transactions = function_exists('sc_get_wallet_transactions')
        ? sc_get_wallet_transactions($member_id, $per_page, $offset)
        : [];

    return compact('balance', 'transactions', 'total', 'trans_page', 'per_page', 'total_pages');
}

function sc_bot_get_notifications_list($user_id, $page = 1, $per_page = SC_BOT_LIST_PER_PAGE) {
    $user_id = (int) $user_id;
    $page = max(1, (int) $page);
    $offset = ($page - 1) * $per_page;

    $total = function_exists('sc_count_user_notifications')
        ? (int) sc_count_user_notifications($user_id, false, false, '')
        : 0;
    $total_pages = max(1, (int) ceil($total / $per_page));
    $items = function_exists('sc_get_user_notifications')
        ? sc_get_user_notifications($user_id, $per_page, $offset, false, false, '')
        : [];

    return compact('items', 'total', 'page', 'per_page', 'total_pages');
}

function sc_bot_get_orders_list($user_id, $page = 1, $per_page = SC_BOT_LIST_PER_PAGE) {
    $user_id = (int) $user_id;
    $page = max(1, (int) $page);
    $per_page = max(1, (int) $per_page);

    if (!function_exists('wc_get_orders')) {
        return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'total_pages' => 1];
    }

    $query = wc_get_orders([
        'customer_id' => $user_id,
        'limit'       => $per_page,
        'page'        => $page,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'type'        => 'shop_order',
        'return'      => 'objects',
        'paginate'    => true,
    ]);

    $items = [];
    $total = 0;
    $total_pages = 1;
    if (is_array($query)) {
        $items = $query['orders'] ?? [];
        $total = (int) ($query['total'] ?? count($items));
        $total_pages = max(1, (int) ($query['max_num_pages'] ?? 1));
    }

    return compact('items', 'total', 'page', 'per_page', 'total_pages');
}

function sc_bot_get_surveys_list($user_id) {
    if (!function_exists('sc_survey_list_for_user')) {
        return [];
    }
    return sc_survey_list_for_user((int) $user_id);
}

function sc_bot_get_private_notes_list($user_id, $page = 1, $per_page = SC_BOT_LIST_PER_PAGE) {
    if (!function_exists('sc_private_notes_query_user_threads')) {
        return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $per_page, 'total_pages' => 1];
    }
    return sc_private_notes_query_user_threads((int) $user_id, [
        'page'     => max(1, (int) $page),
        'per_page' => max(1, (int) $per_page),
    ]);
}

function sc_bot_get_private_sessions($member_id, $limit = 8) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_private_booking_sessions';
    $courses = $wpdb->prefix . 'sc_courses';
    $today = current_time('Y-m-d');

    return $wpdb->get_results($wpdb->prepare(
        "SELECT ps.*, c.title AS course_title
         FROM $table ps
         INNER JOIN $courses c ON c.id = ps.course_id
         WHERE ps.member_id = %d AND ps.session_date >= %s
         ORDER BY ps.session_date ASC, ps.time_start ASC
         LIMIT %d",
        (int) $member_id,
        $today,
        (int) $limit
    ));
}

/**
 * دکمه‌های صفحه‌بندی inline
 */
function sc_bot_pagination_buttons($prefix, $page, $total_pages, array $extra_rows = []) {
    $buttons = $extra_rows;
    $nav = [];
    if ($page > 1) {
        $nav[] = ['text' => '◀ قبلی', 'callback_data' => $prefix . ':p' . ($page - 1)];
    }
    if ($page < $total_pages) {
        $nav[] = ['text' => 'بعدی ▶', 'callback_data' => $prefix . ':p' . ($page + 1)];
    }
    if (!empty($nav)) {
        $buttons[] = $nav;
    }
    return $buttons;
}

function sc_bot_site_link_button($label, $path) {
    return [bale_make_link_button($label, $path)];
}

function sc_bot_get_member_row($member_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_members WHERE id = %d LIMIT 1",
        (int) $member_id
    ));
}

/**
 * همان فیلتر «دوره‌های فعال» در my-account/sc-my-courses
 */
function sc_bot_get_active_courses($member_id, $limit = 8) {
    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $courses = $wpdb->prefix . 'sc_courses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.title, mc.status, mc.start_date, mc.course_status_flags, mc.remaining_sessions
         FROM $mc mc
         INNER JOIN $courses c ON c.id = mc.course_id
         WHERE mc.member_id = %d
           AND mc.status IN ('active', 'inactive')
           AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
           AND c.deleted_at IS NULL
         ORDER BY
            CASE WHEN mc.status = 'active' THEN 0 ELSE 1 END,
            mc.created_at DESC
         LIMIT %d",
        (int) $member_id,
        (int) $limit
    ));
}

function sc_bot_course_status_label($row) {
    $flags = isset($row->course_status_flags) ? trim((string) $row->course_status_flags) : '';
    if ($flags !== '') {
        if (strpos($flags, 'paused') !== false) {
            return '⏸ متوقف';
        }
        if (strpos($flags, 'canceled') !== false) {
            return '❌ لغو شده';
        }
        if (strpos($flags, 'completed') !== false) {
            return '✅ تکمیل‌شده';
        }
    }
    if (isset($row->status) && $row->status === 'inactive') {
        return '⏳ در انتظار پرداخت';
    }
    return '✅ فعال';
}

function sc_bot_get_faq_items($limit = 20) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_faq';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [];
    }
    return $wpdb->get_results($wpdb->prepare(
        "SELECT id, question, answer FROM $table ORDER BY id ASC LIMIT %d",
        (int) $limit
    ));
}

/**
 * کارهای ناتمام — همان منطق dashboard-player.php (بدون «اتصال ربات»)
 *
 * @return array<int, array{icon:string,title:string,description:string}>
 */
function sc_bot_collect_dashboard_tasks($member_id, $user_id) {
    global $wpdb;

    $member_id = (int) $member_id;
    $user_id = (int) $user_id;
    $player = sc_bot_get_member_row($member_id);
    if (!$player) {
        return [];
    }

    $tasks = [];
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    if (function_exists('sc_check_profile_completed') && !sc_check_profile_completed($member_id)) {
        $tasks[] = [
            'icon' => '👤',
            'title' => 'تکمیل اطلاعات پروفایل',
            'description' => 'برای استفاده کامل از خدمات، اطلاعات پروفایل را تکمیل کنید.',
        ];
    }

    if (!empty($player->insurance_expiry_date_shamsi)
        && function_exists('sc_get_today_shamsi')
        && function_exists('sc_compare_shamsi_dates')) {
        if (sc_compare_shamsi_dates(sc_get_today_shamsi(), $player->insurance_expiry_date_shamsi) >= 0) {
            $tasks[] = [
                'icon' => '🛡️',
                'title' => 'تمدید بیمه ورزشی',
                'description' => 'اعتبار بیمه ورزشی شما به پایان رسیده است.',
            ];
        }
    }

    $active_courses_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON mc.course_id = c.id
         WHERE mc.member_id = %d
           AND mc.status IN ('active', 'inactive')
           AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
           AND c.deleted_at IS NULL",
        $member_id
    ));
    if ($active_courses_count === 0) {
        $tasks[] = [
            'icon' => '🏃',
            'title' => 'ثبت نام در دوره',
            'description' => 'در هیچ دوره فعالی ثبت‌نام نشده‌اید.',
        ];
    }

    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()
        && function_exists('sc_count_unread_notifications')) {
        $unread = (int) sc_count_unread_notifications($user_id);
        if ($unread > 0) {
            $tasks[] = [
                'icon' => '🔔',
                'title' => 'مطالعه اطلاعیه‌ها',
                'description' => sprintf('شما %s اطلاعیه خوانده‌نشده دارید.', number_format_i18n($unread)),
            ];
        }
    }

    $pending = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount + COALESCE(penalty_amount, 0)), 0) AS total_amount
         FROM $invoices_table
         WHERE member_id = %d
           AND status IN ('pending', 'under_review')
           AND (course_id > 0 OR event_id > 0 OR invoice_description IS NOT NULL)",
        $member_id
    ));
    if ($pending && (int) $pending->cnt > 0) {
        $tasks[] = [
            'icon' => '💳',
            'title' => 'پرداخت صورتحساب',
            'description' => sprintf(
                '%s صورتحساب در انتظار (جمع: %s)',
                number_format_i18n((int) $pending->cnt),
                sc_bot_format_amount((float) $pending->total_amount)
            ),
        ];
    }

    if (function_exists('sc_support_count_tickets_for_user')) {
        $pending_tickets = sc_support_count_tickets_for_user($user_id, 'pending_reply');
        if ($pending_tickets > 0) {
            $tasks[] = [
                'icon' => '🎫',
                'title' => 'پاسخ تیکت پشتیبانی',
                'description' => sprintf('%s تیکت در انتظار پاسخ شماست.', number_format_i18n($pending_tickets)),
            ];
        }
    }

    return $tasks;
}

function sc_bot_get_recent_attendances($member_id, $limit = 5) {
    global $wpdb;
    $att = $wpdb->prefix . 'sc_attendances';
    $courses = $wpdb->prefix . 'sc_courses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.attendance_date, a.status, c.title AS course_title
         FROM $att a
         LEFT JOIN $courses c ON c.id = a.course_id
         WHERE a.member_id = %d
         ORDER BY a.attendance_date DESC
         LIMIT %d",
        (int) $member_id,
        (int) $limit
    ));
}

function sc_bot_get_member_honors($member_id, $limit = 5) {
    global $wpdb;
    $honors = $wpdb->prefix . 'sc_honors';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT name, status, created_at FROM $honors
         WHERE member_id = %d AND status = 'approved'
         ORDER BY created_at DESC LIMIT %d",
        (int) $member_id,
        (int) $limit
    ));
}

function sc_bot_get_member_events($member_id, $limit = 6) {
    global $wpdb;
    $reg = $wpdb->prefix . 'sc_event_registrations';
    $events = $wpdb->prefix . 'sc_events';
    $invoices = $wpdb->prefix . 'sc_invoices';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT e.name, e.holding_date_shamsi, e.event_location, i.status AS invoice_status
         FROM $reg r
         INNER JOIN $events e ON r.event_id = e.id
         LEFT JOIN $invoices i ON r.invoice_id = i.id
         WHERE r.member_id = %d
         ORDER BY e.holding_date_gregorian DESC, e.id DESC
         LIMIT %d",
        (int) $member_id,
        (int) $limit
    ));
}

function sc_bot_attendance_status_label($status) {
    $map = [
        'present' => '✅ حاضر',
        'absent'  => '❌ غایب',
        'excused' => '⚠️ غیبت مجاز',
        'late'    => '⏰ تاخیر',
    ];
    return $map[$status] ?? $status;
}

/**
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_bot_secretary_member_scope_sql($user_id, $member_alias = 'm') {
    if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only($user_id)) {
        return ['sql' => '', 'args' => []];
    }

    $chapters = get_user_meta((int) $user_id, defined('SC_SECRETARY_CHAPTERS_META') ? SC_SECRETARY_CHAPTERS_META : 'sc_secretary_chapters', true);
    if (!is_array($chapters) || empty($chapters)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }

    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $member_alias);
    $member_col = $alias !== '' ? "{$alias}.id" : "{$wpdb->prefix}sc_members.id";

    $ph = implode(', ', array_fill(0, count($chapters), '%s'));
    $sql = " AND EXISTS (
        SELECT 1 FROM {$mc} mc_sc
        WHERE mc_sc.member_id = {$member_col}
          AND mc_sc.status = 'active'
          AND (
            TRIM(IFNULL(mc_sc.chapter, '')) IN ({$ph})
            OR (
              TRIM(IFNULL(mc_sc.chapter, '')) = ''
              AND EXISTS (
                SELECT 1 FROM {$wpdb->prefix}sc_course_chapters cc_d
                WHERE cc_d.course_id = mc_sc.course_id
                  AND TRIM(cc_d.chapter_name) IN ({$ph})
              )
            )
          )
    )";

    return ['sql' => $sql, 'args' => array_merge($chapters, $chapters)];
}

function sc_bot_get_manager_dashboard_stats($user_id) {
    global $wpdb;
    $user_id = (int) $user_id;
    $members = $wpdb->prefix . 'sc_members';
    $invoices = $wpdb->prefix . 'sc_invoices';
    $mc = $wpdb->prefix . 'sc_member_courses';

    $is_secretary = sc_bot_user_is_secretary_only($user_id);
    $scope = $is_secretary ? sc_bot_secretary_member_scope_sql($user_id, 'm') : ['sql' => '', 'args' => []];

    $member_sql = "SELECT COUNT(*) FROM $members m WHERE m.is_active = 1";
    if ($scope['sql'] !== '') {
        $member_sql .= $scope['sql'];
        $active_members = (int) $wpdb->get_var($wpdb->prepare($member_sql, $scope['args']));
    } else {
        $active_members = (int) $wpdb->get_var($member_sql);
    }

    $pending_invoices = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $invoices WHERE status IN ('pending', 'under_review', 'on-hold')"
    );

    $enrollment_sql = "SELECT COUNT(*) FROM $mc WHERE status = 'active'";
    $active_enrollments = (int) $wpdb->get_var($enrollment_sql);

    $pending_tickets = function_exists('sc_support_count_pending_reply_for_admin')
        ? (int) sc_support_count_pending_reply_for_admin()
        : 0;

    $chapters_label = '';
    if ($is_secretary) {
        $chapters = get_user_meta($user_id, defined('SC_SECRETARY_CHAPTERS_META') ? SC_SECRETARY_CHAPTERS_META : 'sc_secretary_chapters', true);
        if (is_array($chapters) && !empty($chapters)) {
            $chapters_label = implode('، ', array_map('strval', $chapters));
        }
    }

    return [
        'active_members'     => $active_members,
        'pending_invoices'   => $pending_invoices,
        'active_enrollments' => $active_enrollments,
        'pending_tickets'    => $pending_tickets,
        'is_secretary'       => $is_secretary,
        'chapters_label'     => $chapters_label,
    ];
}

function sc_bot_get_coach_dashboard_stats($coach_id) {
    global $wpdb;
    $coach_id = (int) $coach_id;
    if ($coach_id <= 0) {
        return [];
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    $mc = $wpdb->prefix . 'sc_member_courses';
    $courses = $wpdb->prefix . 'sc_courses';

    $courses_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT c.id)
         FROM $courses c
         INNER JOIN $cc cc ON cc.course_id = c.id AND cc.coach_id = %d
         WHERE c.deleted_at IS NULL",
        $coach_id
    ));

    $players_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT mc.member_id)
         FROM $mc mc
         INNER JOIN $cc cc ON cc.course_id = mc.course_id AND cc.coach_id = %d
         WHERE mc.status = 'active'",
        $coach_id
    ));

    $pending_tickets = function_exists('sc_support_count_pending_reply_for_coach')
        ? (int) sc_support_count_pending_reply_for_coach($coach_id)
        : 0;

    $wallet_balance = null;
    if (function_exists('sc_get_coach_wallet_balance')
        && function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled')
        && sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        $wallet_balance = (float) sc_get_coach_wallet_balance($coach_id);
    }

    $cert_warning = '';
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_coaches WHERE id = %d LIMIT 1",
        $coach_id
    ));
    if ($coach && function_exists('sc_get_coach_certificate_expiry_notice_data')) {
        $notice = sc_get_coach_certificate_expiry_notice_data($coach);
        if (!empty($notice['show'])) {
            $cert_warning = (string) ($notice['message'] ?? 'مدرک مربیگری نیاز به تمدید دارد.');
        }
    }

    return [
        'courses_count'    => $courses_count,
        'players_count'    => $players_count,
        'pending_tickets'  => $pending_tickets,
        'wallet_balance'   => $wallet_balance,
        'cert_warning'     => $cert_warning,
    ];
}

function sc_bot_get_coach_courses_summary($coach_id, $limit = 6) {
    global $wpdb;
    $coach_id = (int) $coach_id;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.title, cc.chapter_name, c.is_active
         FROM {$wpdb->prefix}sc_courses c
         INNER JOIN {$wpdb->prefix}sc_course_coaches cc ON cc.course_id = c.id AND cc.coach_id = %d
         WHERE c.deleted_at IS NULL
         ORDER BY c.title ASC
         LIMIT %d",
        $coach_id,
        (int) $limit
    ));
}

function sc_bot_get_coach_players_summary($coach_id, $limit = 5) {
    global $wpdb;
    $coach_id = (int) $coach_id;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $members = $wpdb->prefix . 'sc_members';
    $cc = $wpdb->prefix . 'sc_course_coaches';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT m.first_name, m.last_name, c.title AS course_title
         FROM $mc mc
         INNER JOIN $members m ON m.id = mc.member_id
         INNER JOIN {$wpdb->prefix}sc_courses c ON c.id = mc.course_id
         INNER JOIN $cc cc ON cc.course_id = mc.course_id AND cc.coach_id = %d
         WHERE mc.status = 'active'
         ORDER BY mc.created_at DESC
         LIMIT %d",
        $coach_id,
        (int) $limit
    ));
}

