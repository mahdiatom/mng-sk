<?php
if (!defined('ABSPATH')) {
    exit;
}

define('SC_BOT_LIST_PER_PAGE', 5);

/**
 * @return array{user_id:int,member_id:int,full_name:string,chat_id:int|string}|false
 */
function sc_bot_resolve_member($chat_id) {
    $member = sc_get_member_by_chatid($chat_id);
    if (!$member || empty($member->user_id)) {
        return false;
    }

    $user_id = (int) $member->user_id;
    $member_id = !empty($member->id) ? (int) $member->id : 0;

    if ($member_id <= 0 && function_exists('sc_survey_get_member_id_for_user')) {
        $member_id = (int) sc_survey_get_member_id_for_user($user_id);
    }
    if ($member_id <= 0) {
        global $wpdb;
        $member_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_members WHERE user_id = %d LIMIT 1",
            $user_id
        ));
    }

    return [
        'user_id'    => $user_id,
        'member_id'  => $member_id,
        'full_name'  => trim((string) ($member->full_name ?? '')),
        'chat_id'    => $chat_id,
    ];
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

function sc_bot_get_active_courses($member_id, $limit = 6) {
    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $courses = $wpdb->prefix . 'sc_courses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.title, mc.status, mc.start_date
         FROM $mc mc
         INNER JOIN $courses c ON c.id = mc.course_id
         WHERE mc.member_id = %d AND mc.status = 'active'
         ORDER BY mc.start_date DESC
         LIMIT %d",
        (int) $member_id,
        (int) $limit
    ));
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
