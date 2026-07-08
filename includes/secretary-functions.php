<?php
/**
 * نقش منشی شعبه — دسترسی محدود به wp-admin با فیلتر شعبه
 */
if (!defined('ABSPATH')) {
    exit;
}

define('SC_SECRETARY_CHAPTERS_META', 'sc_secretary_chapters');
define('SC_SECRETARY_FILTER_QUERY_VAR', 'sc_secretary_chapter');

/**
 * Capability منوهای پنل منشی (در زمان ثبت منو برای کاربر جاری).
 */
function sc_admin_menu_cap() {
    return sc_user_is_secretary_only() ? 'sc_secretary_panel' : 'manage_options';
}

/**
 * دسترسی به پنل wp-admin باشگاه (مدیر یا منشی).
 *
 * @param int $user_id
 */
function sc_user_can_staff_admin_panel($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    return user_can($user_id, 'manage_options') || user_can($user_id, 'sc_secretary_panel');
}

/**
 * @param int $user_id
 */
function sc_user_is_secretary($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    $user = get_userdata($user_id);
    return $user && in_array('secretary', (array) $user->roles, true);
}

/**
 * منشی خالص — بدون مدیر کل / مدیر باشگاه / مدیر سامانه.
 *
 * @param int $user_id
 */
function sc_user_is_secretary_only($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0 || !sc_user_is_secretary($user_id)) {
        return false;
    }
    if (user_can($user_id, 'administrator')) {
        return false;
    }
    if (function_exists('sc_user_has_club_manager_role') && sc_user_has_club_manager_role($user_id)) {
        return false;
    }
    return true;
}

/**
 * @param int $user_id
 */
function sc_user_can_manage_secretaries($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    return user_can($user_id, 'administrator')
        || (function_exists('sc_user_has_club_manager_role') && sc_user_has_club_manager_role($user_id));
}

/**
 * دسترسی به صفحه و AJAX اقدامات سریع (منشی، مدیر باشگاه، مدیر سامانه، مدیرکل).
 *
 * @param int $user_id
 */
function sc_user_can_quick_actions($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    if (sc_user_is_secretary_only($user_id)) {
        return true;
    }
    return sc_user_can_manage_secretaries($user_id);
}

/**
 * آیا لیست دوره/شعبه در اقدامات سریع به شعبه منشی محدود شود؟
 */
function sc_quick_actions_is_branch_scoped() {
    return sc_user_is_secretary_only();
}

/**
 * شعبه‌های قابل انتخاب در اقدامات سریع.
 *
 * @return string[]
 */
function sc_quick_actions_get_chapters() {
    if (sc_quick_actions_is_branch_scoped()) {
        return sc_secretary_get_effective_chapters();
    }
    if (sc_user_can_manage_secretaries()) {
        return sc_secretary_get_all_chapter_names();
    }
    return [];
}

/**
 * @return string[]
 */
function sc_secretary_get_allowed_admin_pages() {
    $pages = [
        'sc-dashboard',
        'sc-members',
        'sc-add-member',
        'sc-view-member',
        'sc-attendance-add',
        'sc-attendance-list',
        'sc-attendance-report',
        'sc-attendance-session-cancellations',
        'sc-reports-attendance-qr',
        'sc-notifications',
        'sc-add-notification',
        'sc-bale-bot-messages',
        'sc-bale-bot-send',
        'sc-support-tickets',
        'sc-support-ticket-view',
        'sc-support-ticket-new',
        'sc-bulk-actions',
        'sc-invoices',
        'sc-add-invoice',
        'wc-orders',
        'sc-expenses',
        'sc-add-expense',
        'sc-reports',
        'sc-reports-active-users',
        'sc-reports-income-expenses',
        'sc-reports-bi-analytics',
        'sc-reports-coach-performance',
        'sc-reports-debtors',
        'sc-reports-weekly-schedule',
        'sc-reports-sms-log',
        'sc-reports-activity-log',
        'sc-users-info-export',
        'sc-users-export-templates',
        'sc-secretary-quick-actions',
        'sc-courses',
        'sc-events',
        'sc-event-registrations',
        'index.php',
        'profile.php',
    ];
    return apply_filters('sc_secretary_allowed_admin_pages', $pages);
}

/**
 * @param int $user_id
 * @return string[]
 */
function sc_get_secretary_chapters($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return [];
    }
    $raw = get_user_meta($user_id, SC_SECRETARY_CHAPTERS_META, true);
    if (!is_array($raw)) {
        if (is_string($raw) && $raw !== '') {
            $raw = maybe_unserialize($raw);
        }
        if (!is_array($raw)) {
            $raw = $raw ? [(string) $raw] : [];
        }
    }
    $out = [];
    foreach ($raw as $name) {
        $name = trim(sanitize_text_field((string) $name));
        if ($name !== '') {
            $out[] = $name;
        }
    }
    return array_values(array_unique($out));
}

/**
 * @param int $user_id
 * @param string[] $chapters
 */
function sc_save_secretary_chapters($user_id, array $chapters) {
    $user_id = absint($user_id);
    if ($user_id < 1) {
        return;
    }
    $clean = [];
    foreach ($chapters as $chapter) {
        $chapter = sanitize_text_field((string) $chapter);
        if ($chapter !== '') {
            $clean[] = $chapter;
        }
    }
    $clean = array_values(array_unique($clean));
    if (empty($clean)) {
        delete_user_meta($user_id, SC_SECRETARY_CHAPTERS_META);
        return;
    }
    update_user_meta($user_id, SC_SECRETARY_CHAPTERS_META, $clean);
}

/**
 * @return string[] نام شعبه‌های معتبر از جدول
 */
function sc_secretary_get_all_chapter_names() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_chapter_categories';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [];
    }
    $rows = $wpdb->get_col("SELECT name FROM {$table} ORDER BY name ASC");
    return is_array($rows) ? array_map('strval', $rows) : [];
}

/**
 * فیلتر شعبه از GET — پیش‌فرض: همه شعبه‌های مجاز.
 */
function sc_secretary_get_filter_chapter() {
    if (!sc_user_is_secretary_only()) {
        return '';
    }
    $filter = isset($_GET[SC_SECRETARY_FILTER_QUERY_VAR])
        ? sanitize_text_field(wp_unslash((string) $_GET[SC_SECRETARY_FILTER_QUERY_VAR]))
        : 'all';
    if ($filter === '' || $filter === 'all') {
        return 'all';
    }
    $allowed = sc_get_secretary_chapters();
    return in_array($filter, $allowed, true) ? $filter : 'all';
}

/**
 * شعبه‌های مؤثر برای کوئری (با توجه به فیلتر).
 *
 * @return string[]
 */
function sc_secretary_get_effective_chapters() {
    $assigned = sc_get_secretary_chapters();
    if (!sc_user_is_secretary_only()) {
        return $assigned;
    }
    if (empty($assigned)) {
        return [];
    }
    $filter = sc_secretary_get_filter_chapter();
    if ($filter !== 'all') {
        return [$filter];
    }
    return $assigned;
}

/**
 * @param string $chapter
 */
function sc_secretary_chapter_in_scope($chapter) {
    if (!sc_user_is_secretary_only()) {
        return true;
    }
    $chapter = sanitize_text_field((string) $chapter);
    $chapters = sc_secretary_get_effective_chapters();
    return $chapter !== '' && in_array($chapter, $chapters, true);
}

/**
 * شرط SQL: ثبت‌نام بازیکن در شعبه(های) مجاز منشی.
 *
 * @param string $mc_alias
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_secretary_member_enrollment_match_sql($mc_alias = 'mc_sc') {
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return ['sql' => '1=0', 'args' => []];
    }
    global $wpdb;
    $mc_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $mc_alias) ?: 'mc_sc';
    $cc_table = $wpdb->prefix . 'sc_course_chapters';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));

    $sql = "(
        TRIM(IFNULL({$mc_alias}.chapter, '')) IN ({$placeholders})
        OR (
            TRIM(IFNULL({$mc_alias}.chapter, '')) = ''
            AND EXISTS (
                SELECT 1 FROM {$cc_table} cc_sec
                WHERE cc_sec.course_id = {$mc_alias}.course_id
                  AND TRIM(cc_sec.chapter_name) IN ({$placeholders})
            )
        )
        OR (
            TRIM(IFNULL({$mc_alias}.chapter, '')) = ''
            AND TRIM(IFNULL((
                SELECT co.chapter FROM {$courses_table} co
                WHERE co.id = {$mc_alias}.course_id
                LIMIT 1
            ), '')) IN ({$placeholders})
        )
    )";

    return [
        'sql' => $sql,
        'args' => array_merge($chapters, $chapters, $chapters),
    ];
}

/**
 * SQL scope: بازیکن با حداقل یک ثبت‌نام active در شعبه(های) مجاز.
 *
 * @param string $member_alias
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_secretary_member_scope_sql($member_alias = 'm') {
    if (!sc_user_is_secretary_only()) {
        return ['sql' => '', 'args' => []];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }
    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $member_alias);
    $member_col = $alias !== '' ? "{$alias}.id" : "{$members_table}.id";
    $match = sc_secretary_member_enrollment_match_sql('mc_sc');
    $sql = " AND EXISTS (
        SELECT 1 FROM {$mc} mc_sc
        WHERE mc_sc.member_id = {$member_col}
          AND mc_sc.status = 'active'
          AND {$match['sql']}
    )";
    return ['sql' => $sql, 'args' => $match['args']];
}

/**
 * @param string $where
 * @param string $member_alias
 * @return string
 */
function sc_secretary_append_member_where($where, $member_alias = 'm') {
    if (!sc_user_is_secretary_only()) {
        return $where;
    }
    global $wpdb;
    $scope = sc_secretary_member_scope_sql($member_alias);
    if ($scope['sql'] === '') {
        return $where;
    }
    if (!empty($scope['args'])) {
        return $where . $wpdb->prepare($scope['sql'], $scope['args']);
    }
    return $where . $scope['sql'];
}

/**
 * @param int $member_id
 */
function sc_secretary_can_access_member($member_id) {
    $member_id = absint($member_id);
    if ($member_id < 1) {
        return false;
    }
    if (!sc_user_is_secretary_only()) {
        return true;
    }
    global $wpdb;
    $scope = sc_secretary_member_scope_sql('m');
    if ($scope['sql'] === '') {
        return true;
    }
    $members_table = $wpdb->prefix . 'sc_members';
    $sql = "SELECT COUNT(*) FROM {$members_table} m WHERE m.id = %d" . $scope['sql'];
    $args = array_merge([$member_id], $scope['args']);
    return (int) $wpdb->get_var($wpdb->prepare($sql, $args)) > 0;
}

/**
 * فیلتر chapter برای فاکتور/هزینه/گزارش.
 *
 * @param string $invoice_alias alias جدول invoices با نقطه، مثلاً i.
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_secretary_invoice_chapter_scope_sql($invoice_alias = 'i') {
    if (!sc_user_is_secretary_only()) {
        return ['sql' => '', 'args' => []];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }
    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $alias = rtrim(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $invoice_alias), '_');
    if ($alias === '') {
        $alias = 'i';
    }
    $match = sc_secretary_member_enrollment_match_sql('mc_inv');
    if ($match['sql'] === '1=0') {
        return ['sql' => ' AND 1=0', 'args' => []];
    }
    $sql = " AND (
        EXISTS (
            SELECT 1 FROM {$mc} mc_inv
            WHERE mc_inv.id = {$alias}.member_course_id
              AND mc_inv.status = 'active'
              AND {$match['sql']}
        )
        OR (
            ({$alias}.member_course_id IS NULL OR {$alias}.member_course_id = 0)
            AND {$alias}.member_id IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM {$mc} mc_inv
                WHERE mc_inv.member_id = {$alias}.member_id
                  AND mc_inv.status = 'active'
                  AND ({$alias}.course_id = 0 OR mc_inv.course_id = {$alias}.course_id)
                  AND {$match['sql']}
            )
        )
    )";

    return [
        'sql' => $sql,
        'args' => array_merge($match['args'], $match['args']),
    ];
}

/**
 * @param string $expense_alias
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_secretary_expense_chapter_scope_sql($expense_alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return ['sql' => '', 'args' => []];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $expense_alias) ?: 'e';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $sql = " AND TRIM(IFNULL({$alias}.chapter, '')) IN ({$placeholders})";
    return ['sql' => $sql, 'args' => $chapters];
}

/**
 * آیا درآمد فروشگاه (کل باشگاه) برای منشی نمایش داده شود؟
 */
function sc_secretary_finance_include_store_revenue() {
    return !sc_user_is_secretary_only();
}

/**
 * @param array<int,object> $courses
 * @return array<int,object>
 */
function sc_secretary_finance_filter_courses_list($courses) {
    if (!sc_user_is_secretary_only()) {
        return $courses;
    }
    $allowed = [];
    if (function_exists('sc_secretary_get_branch_courses_for_attendance_filter')) {
        foreach (sc_secretary_get_branch_courses_for_attendance_filter() as $row) {
            $allowed[(int) $row->id] = true;
        }
    }
    if (empty($allowed)) {
        return [];
    }
    return array_values(array_filter((array) $courses, static function ($course) use ($allowed) {
        $id = is_object($course) ? (int) ($course->id ?? 0) : 0;
        return $id > 0 && isset($allowed[$id]);
    }));
}

/**
 * شناسه دوره‌های شعبه منشی (برای گزارش مربی و BI).
 *
 * @return int[]
 */
function sc_secretary_get_branch_course_ids() {
    if (!sc_user_is_secretary_only()) {
        return [];
    }
    $ids = [];
    if (!function_exists('sc_secretary_get_branch_courses_for_attendance_filter')) {
        return [];
    }
    foreach (sc_secretary_get_branch_courses_for_attendance_filter() as $row) {
        $id = (int) ($row->id ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

/**
 * @param int[] $course_ids
 * @return int[]
 */
function sc_secretary_filter_course_ids(array $course_ids) {
    $course_ids = array_values(array_filter(array_map('absint', $course_ids)));
    if (!sc_user_is_secretary_only()) {
        return $course_ids;
    }
    $allowed = array_fill_keys(sc_secretary_get_branch_course_ids(), true);
    if (empty($allowed)) {
        return [];
    }
    return array_values(array_filter($course_ids, static function ($id) use ($allowed) {
        return $id > 0 && isset($allowed[$id]);
    }));
}

/**
 * مربیانی که روی دوره‌های شعبه منشی فعال هستند.
 *
 * @return int[]
 */
function sc_secretary_get_branch_coach_ids() {
    if (!sc_user_is_secretary_only()) {
        return [];
    }
    $course_ids = sc_secretary_get_branch_course_ids();
    if (empty($course_ids)) {
        return [];
    }
    global $wpdb;
    $cc_table = $wpdb->prefix . 'sc_course_coaches';
    $holders = implode(',', array_fill(0, count($course_ids), '%d'));
    return array_values(array_unique(array_map('absint', $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT coach_id FROM {$cc_table} WHERE course_id IN ({$holders})",
        ...$course_ids
    )) ?: [])));
}

/**
 * @param array<int,string> $where
 * @param array<int,mixed> $params
 * @param string $column
 */
function sc_secretary_merge_course_ids_where(array &$where, array &$params, $column = 'c.id') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $ids = sc_secretary_get_branch_course_ids();
    if (empty($ids)) {
        $where[] = '1=0';
        return;
    }
    $column = preg_replace('/[^a-zA-Z0-9_.]/', '', (string) $column) ?: 'c.id';
    $holders = implode(',', array_fill(0, count($ids), '%d'));
    $where[] = "{$column} IN ({$holders})";
    $params = array_merge($params, $ids);
}

/**
 * @param array<int,string> $where
 * @param array<int,mixed> $params
 * @param string $wallet_alias
 */
function sc_secretary_merge_coach_wallet_course_scope(array &$where, array &$params, $wallet_alias = 'w') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $ids = sc_secretary_get_branch_course_ids();
    if (empty($ids)) {
        $where[] = '1=0';
        return;
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $wallet_alias) ?: 'w';
    $holders = implode(',', array_fill(0, count($ids), '%d'));
    $where[] = "{$alias}.related_course_id IN ({$holders})";
    $params = array_merge($params, $ids);
}

/**
 * @param array<int,string> $where
 * @param array<int,mixed> $params
 * @param string $salary_alias
 */
function sc_secretary_merge_salary_course_scope(array &$where, array &$params, $salary_alias = 'sr') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $ids = sc_secretary_get_branch_course_ids();
    if (empty($ids)) {
        $where[] = '1=0';
        return;
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $salary_alias) ?: 'sr';
    $holders = implode(',', array_fill(0, count($ids), '%d'));
    $where[] = "{$alias}.course_id IN ({$holders})";
    $params = array_merge($params, $ids);
}

/**
 * دوره‌هایی که حداقل یک ردیف برنامه هفتگی در شعبه منشی دارند.
 *
 * @return int[]
 */
function sc_secretary_get_branch_weekly_schedule_course_ids() {
    if (!sc_user_is_secretary_only()) {
        return [];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return [];
    }
    if (!function_exists('sc_course_weekly_schedule_table_ready') || !sc_course_weekly_schedule_table_ready()) {
        return [];
    }
    global $wpdb;
    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';
    $courses_table = $wpdb->prefix . 'sc_courses';

    if (function_exists('sc_course_schedule_has_chapter_coach_columns') && sc_course_schedule_has_chapter_coach_columns()) {
        $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
        return array_values(array_unique(array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT s.course_id
             FROM {$sch} s
             INNER JOIN {$courses_table} c ON c.id = s.course_id AND c.deleted_at IS NULL
             WHERE TRIM(IFNULL(s.chapter_name, '')) IN ({$placeholders})",
            ...$chapters
        )) ?: [])));
    }

    return sc_secretary_get_branch_course_ids();
}

/**
 * مربیانی که در برنامه هفتگی شعبه منشی ثبت شده‌اند.
 *
 * @return int[]
 */
function sc_secretary_get_branch_weekly_schedule_coach_ids() {
    if (!sc_user_is_secretary_only()) {
        return [];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return [];
    }
    if (!function_exists('sc_course_weekly_schedule_table_ready') || !sc_course_weekly_schedule_table_ready()) {
        return [];
    }
    global $wpdb;
    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';
    $courses_table = $wpdb->prefix . 'sc_courses';

    if (function_exists('sc_course_schedule_has_chapter_coach_columns') && sc_course_schedule_has_chapter_coach_columns()) {
        $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
        return array_values(array_unique(array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT s.coach_id
             FROM {$sch} s
             INNER JOIN {$courses_table} c ON c.id = s.course_id AND c.deleted_at IS NULL
             WHERE s.coach_id > 0
               AND TRIM(IFNULL(s.chapter_name, '')) IN ({$placeholders})",
            ...$chapters
        )) ?: [])));
    }

    return sc_secretary_get_branch_coach_ids();
}

/**
 * محدود کردن کوئری برنامه هفتگی به شعبه(های) منشی (بر اساس chapter_name هر ردیف).
 *
 * @param array<int,string> $where
 * @param array<int,mixed> $params
 * @param string $schedule_alias
 */
function sc_secretary_merge_weekly_schedule_chapter_scope(array &$where, array &$params, $schedule_alias = 's') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        $where[] = '1=0';
        return;
    }
    if (!function_exists('sc_course_schedule_has_chapter_coach_columns') || !sc_course_schedule_has_chapter_coach_columns()) {
        if (function_exists('sc_secretary_merge_course_ids_where')) {
            $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $schedule_alias) ?: 's';
            sc_secretary_merge_course_ids_where($where, $params, $alias . '.course_id');
        } else {
            $where[] = '1=0';
        }
        return;
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $schedule_alias) ?: 's';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $where[] = "TRIM(IFNULL({$alias}.chapter_name, '')) IN ({$placeholders})";
    $params = array_merge($params, $chapters);
}

/**
 * @param int $expense_id
 */
function sc_secretary_can_access_expense($expense_id) {
    if (!sc_user_is_secretary_only()) {
        return true;
    }
    $expense_id = absint($expense_id);
    if ($expense_id < 1) {
        return true;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_expenses';
    $chapter = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT chapter FROM {$table} WHERE id = %d LIMIT 1",
        $expense_id
    ));
    return $chapter !== '' && sc_secretary_chapter_in_scope($chapter);
}

/**
 * @param int $invoice_id
 */
function sc_secretary_can_access_invoice($invoice_id) {
    $invoice_id = absint($invoice_id);
    if ($invoice_id < 1) {
        return false;
    }
    if (!sc_user_is_secretary_only()) {
        return true;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_invoices';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT member_id, member_course_id FROM {$table} WHERE id = %d LIMIT 1",
        $invoice_id
    ), ARRAY_A);
    if (!$row) {
        return false;
    }
    $member_id = absint($row['member_id'] ?? 0);
    if ($member_id > 0 && sc_secretary_can_access_member($member_id)) {
        return true;
    }
    $mc_id = absint($row['member_course_id'] ?? 0);
    if ($mc_id < 1) {
        return false;
    }
    $mc = $wpdb->prefix . 'sc_member_courses';
    $member_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT member_id FROM {$mc} WHERE id = %d LIMIT 1",
        $mc_id
    ));
    return $member_id > 0 && sc_secretary_can_access_member($member_id);
}

/**
 * دسترسی ویرایش سفارش ووکامرس — فقط سفارش‌های مرتبط با فاکتورهای مجاز شعبه.
 *
 * @param int $order_id
 */
function sc_secretary_can_access_wc_order($order_id) {
    $order_id = absint($order_id);
    if ($order_id < 1) {
        return false;
    }
    if (!sc_user_is_secretary_only()) {
        return true;
    }
    global $wpdb;
    $inv_table = $wpdb->prefix . 'sc_invoices';
    $invoice_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$inv_table} WHERE woocommerce_order_id = %d ORDER BY id DESC LIMIT 1",
        $order_id
    ));
    if ($invoice_id < 1) {
        return false;
    }
    return sc_secretary_can_access_invoice($invoice_id);
}

/**
 * Capabilityهای ووکامرس برای منشی — فقط ویرایش سفارش (فاکتور)، بدون محصولات/فروشگاه.
 *
 * @return array<string,bool>
 */
function sc_get_secretary_woocommerce_order_capabilities() {
    return [
        'read' => true,
        'manage_woocommerce' => true,
        'edit_shop_orders' => true,
        'read_shop_orders' => true,
        'edit_published_shop_orders' => true,
        'read_private_shop_orders' => true,
        'edit_private_shop_orders' => true,
        'edit_others_shop_orders' => true,
    ];
}

/**
 * اعطای دسترسی ویرایش سفارش به نقش منشی (برای نقش‌های از قبل ساخته‌شده).
 */
function sc_grant_order_capabilities_to_secretary() {
    $role = get_role('secretary');
    if (!$role) {
        return;
    }
    foreach (sc_get_secretary_woocommerce_order_capabilities() as $cap => $grant) {
        if ($grant && !$role->has_cap($cap)) {
            $role->add_cap($cap);
        }
    }
}
add_action('admin_init', 'sc_grant_order_capabilities_to_secretary', 11);

/**
 * اجازهٔ ویرایش/مشاهده سفارش مشخص برای منشی در صورت مجاز بودن فاکتور شعبه.
 */
add_filter('map_meta_cap', 'sc_secretary_map_wc_order_meta_caps', 20, 4);
function sc_secretary_map_wc_order_meta_caps($caps, $cap, $user_id, $args) {
    if (!in_array($cap, ['edit_shop_order', 'read_shop_order', 'delete_shop_order'], true)) {
        return $caps;
    }
    if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only($user_id)) {
        return $caps;
    }
    $order_id = isset($args[0]) ? absint($args[0]) : 0;
    if ($order_id < 1) {
        return ['do_not_allow'];
    }
    if ($cap === 'delete_shop_order') {
        return ['do_not_allow'];
    }
    if (sc_secretary_can_access_wc_order($order_id)) {
        return ['read'];
    }
    return ['do_not_allow'];
}

/**
 * منشی فقط صفحه ویرایش سفارش فاکتورهای شعبه خود را ببیند (نه لیست کلی سفارش‌ها).
 */
add_action('admin_init', 'sc_secretary_guard_wc_order_admin', 6);
function sc_secretary_guard_wc_order_admin() {
    if (!sc_user_is_secretary_only() || empty($_GET['page'])) {
        return;
    }
    $page = sanitize_text_field(wp_unslash((string) $_GET['page']));
    if ($page !== 'wc-orders') {
        return;
    }
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash((string) $_GET['action'])) : '';
    if ($action !== 'edit') {
        sc_secretary_die_access_denied(
            'لیست سفارش‌های ووکامرس',
            'منشی فقط می‌تواند از صفحه فاکتورها، سفارش مرتبط با همان فاکتور را ویرایش کند.'
        );
    }
    $order_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($order_id < 1 || !sc_secretary_can_access_wc_order($order_id)) {
        sc_secretary_die_access_denied(
            'ویرایش سفارش فاکتور',
            'این سفارش به فاکتور شعبه شما مرتبط نیست یا دسترسی مجاز ندارید.'
        );
    }
}

/**
 * دوره‌های حضور و غیاب برای منشی — هر ردیف یک دوره + شعبه (گروهی و خصوصی).
 *
 * @return object[]
 */
function sc_secretary_get_branch_courses_for_attendance() {
    if (!sc_user_is_secretary_only()) {
        return [];
    }
    global $wpdb;
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return [];
    }
    $courses_table = $wpdb->prefix . 'sc_courses';
    $chapters_table = $wpdb->prefix . 'sc_course_chapters';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));

    $sql = "SELECT c.id, c.title, c.course_type, TRIM(cc.chapter_name) AS chapter_name
            FROM {$courses_table} c
            INNER JOIN {$chapters_table} cc ON cc.course_id = c.id
            WHERE c.deleted_at IS NULL AND c.is_active = 1
              AND TRIM(cc.chapter_name) IN ({$placeholders})
            ORDER BY c.title ASC, cc.chapter_name ASC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $chapters));

    $legacy_sql = "SELECT c.id, c.title, c.course_type, TRIM(c.chapter) AS chapter_name
            FROM {$courses_table} c
            WHERE c.deleted_at IS NULL AND c.is_active = 1
              AND TRIM(IFNULL(c.chapter, '')) IN ({$placeholders})
              AND NOT EXISTS (SELECT 1 FROM {$chapters_table} cc WHERE cc.course_id = c.id)
            ORDER BY c.title ASC";
    $legacy = $wpdb->get_results($wpdb->prepare($legacy_sql, $chapters));

    if (empty($legacy)) {
        return is_array($rows) ? $rows : [];
    }
    $merged = is_array($rows) ? $rows : [];
    $seen = [];
    foreach ($merged as $row) {
        $seen[(int) $row->id . '|' . trim((string) ($row->chapter_name ?? ''))] = true;
    }
    foreach ($legacy as $row) {
        $key = (int) $row->id . '|' . trim((string) ($row->chapter_name ?? ''));
        if (!isset($seen[$key])) {
            $merged[] = $row;
            $seen[$key] = true;
        }
    }
    return $merged;
}

/**
 * لیست یکتا id/title دوره‌های مجاز منشی برای فیلتر حضور و غیاب.
 *
 * @return object[]
 */
function sc_secretary_get_branch_courses_for_attendance_filter() {
    $rows = sc_secretary_get_branch_courses_for_attendance();
    if (empty($rows)) {
        return [];
    }
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $id = (int) $row->id;
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[] = (object) [
            'id' => $id,
            'title' => (string) ($row->title ?? ('#' . $id)),
        ];
    }
    return $out;
}

/**
 * @param int $course_id
 * @param string $chapter_name
 */
function sc_secretary_can_access_attendance_course($course_id, $chapter_name = '') {
    if (!sc_user_is_secretary_only()) {
        return true;
    }
    $course_id = absint($course_id);
    if ($course_id < 1) {
        return false;
    }
    $chapter_name = trim(sanitize_text_field((string) $chapter_name));
    foreach (sc_secretary_get_branch_courses_for_attendance() as $row) {
        if ((int) $row->id !== $course_id) {
            continue;
        }
        $row_chapter = trim((string) ($row->chapter_name ?? ''));
        if ($chapter_name === '' || $chapter_name === $row_chapter) {
            return true;
        }
    }
    return false;
}

/**
 * محدودیت course_id در لیست/گزارش حضور و غیاب برای منشی.
 *
 * @param array<int,string> $where_conditions
 * @param array<int,mixed> $where_values
 * @param string $course_alias
 */
function sc_secretary_append_attendance_course_scope(array &$where_conditions, array &$where_values, $course_alias = 'a') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $branch_rows = sc_secretary_get_branch_courses_for_attendance();
    $ids = array_values(array_unique(array_filter(array_map(static function ($row) {
        return (int) $row->id;
    }, $branch_rows))));
    if (empty($ids)) {
        $where_conditions[] = '1=0';
        return;
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $course_alias) ?: 'a';
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $where_conditions[] = "{$alias}.course_id IN ($placeholders)";
    $where_values = array_merge($where_values, $ids);
    $where_conditions[] = sc_secretary_attendance_member_scope_list_sql($alias);
}

/**
 * شرط SQL: رکورد حضور فقط برای ثبت‌نام فعال بازیکن در شعبه(های) مجاز منشی.
 *
 * @param string $attendance_alias
 * @return string
 */
function sc_secretary_attendance_member_scope_list_sql($attendance_alias = 'a') {
    if (!sc_user_is_secretary_only()) {
        return '1=1';
    }
    global $wpdb;
    if (empty(sc_secretary_get_effective_chapters())) {
        return '1=0';
    }
    $mc = $wpdb->prefix . 'sc_member_courses';
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $attendance_alias) ?: 'a';
    $match = sc_secretary_member_enrollment_match_sql('mc_sec');
    if ($match['sql'] === '1=0') {
        return '1=0';
    }
    $sql = "EXISTS (
        SELECT 1 FROM {$mc} mc_sec
        WHERE mc_sec.member_id = {$alias}.member_id
          AND mc_sec.course_id = {$alias}.course_id
          AND mc_sec.status = %s
          AND {$match['sql']}
    )";

    return $wpdb->prepare($sql, array_merge(['active'], $match['args']));
}

/**
 * دوره‌های قابل انتخاب برای منشی (دارای شعبه مجاز).
 *
 * @return object[]
 */
function sc_secretary_get_branch_courses() {
    global $wpdb;
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return [];
    }
    $courses_table = $wpdb->prefix . 'sc_courses';
    $chapters_table = $wpdb->prefix . 'sc_course_chapters';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $sql = "SELECT DISTINCT c.*
            FROM {$courses_table} c
            INNER JOIN {$chapters_table} cc ON cc.course_id = c.id
            WHERE c.deleted_at IS NULL AND c.is_active = 1
              AND TRIM(cc.chapter_name) IN ({$placeholders})
            ORDER BY c.title ASC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $chapters));

    $legacy_sql = "SELECT c.*
            FROM {$courses_table} c
            WHERE c.deleted_at IS NULL AND c.is_active = 1
              AND TRIM(IFNULL(c.chapter, '')) IN ({$placeholders})
              AND NOT EXISTS (SELECT 1 FROM {$chapters_table} cc WHERE cc.course_id = c.id)
            ORDER BY c.title ASC";
    $legacy = $wpdb->get_results($wpdb->prepare($legacy_sql, $chapters));

    if (empty($legacy)) {
        return is_array($rows) ? $rows : [];
    }
    $merged = is_array($rows) ? $rows : [];
    $seen = [];
    foreach ($merged as $row) {
        $seen[(int) $row->id] = true;
    }
    foreach ($legacy as $row) {
        $id = (int) $row->id;
        if (!isset($seen[$id])) {
            $merged[] = $row;
            $seen[$id] = true;
        }
    }
    return $merged;
}

/**
 * ایجاد نقش منشی.
 */
function sc_create_secretary_role() {
    if (get_role('secretary')) {
        $role = get_role('secretary');
        if ($role && !$role->has_cap('sc_secretary_panel')) {
            $role->add_cap('sc_secretary_panel');
        }
        if ($role && !$role->has_cap('secretary')) {
            $role->add_cap('secretary');
        }
        if ($role && !$role->has_cap('sc_manage_attendance')) {
            $role->add_cap('sc_manage_attendance');
        }
        foreach (sc_get_secretary_woocommerce_order_capabilities() as $cap => $grant) {
            if ($grant && !$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }
        return;
    }
    add_role('secretary', 'منشی', array_merge([
        'secretary' => true,
        'sc_secretary_panel' => true,
        'sc_manage_attendance' => true,
    ], sc_get_secretary_woocommerce_order_capabilities()));
}
add_action('admin_init', 'sc_create_secretary_role', 15);

/**
 * دسترسی حضور و غیاب (مربی، مدیر، منشی).
 */
function sc_user_can_manage_attendance($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    return user_can($user_id, 'manage_options')
        || user_can($user_id, 'sc_manage_attendance')
        || user_can($user_id, 'sc_manage_attendance_or_admin');
}

/** Capability حضور و غیاب برای منشی */
add_filter('user_has_cap', 'sc_secretary_attendance_cap', 10, 4);
function sc_secretary_attendance_cap($allcaps, $caps, $args, $user) {
    if (empty($user->roles) || !in_array('secretary', (array) $user->roles, true)) {
        return $allcaps;
    }
    if (!empty($allcaps['administrator']) || !empty($allcaps['club_coach']) || !empty($allcaps['system_manager'])) {
        return $allcaps;
    }
    foreach ($caps as $cap) {
        if ($cap === 'sc_manage_attendance_or_admin' || $cap === 'sc_manage_attendance') {
            $allcaps[$cap] = true;
        }
    }
    return $allcaps;
}

/** اطلاعیه برای منشی */
add_filter('user_has_cap', 'sc_secretary_notifications_cap', 10, 4);
function sc_secretary_notifications_cap($allcaps, $caps, $args, $user) {
    foreach ($caps as $cap) {
        if ($cap !== 'sc_manage_notifications') {
            continue;
        }
        if (!empty($user->roles) && in_array('secretary', (array) $user->roles, true)
            && empty($allcaps['administrator']) && empty($allcaps['club_coach']) && empty($allcaps['system_manager'])) {
            $allcaps['sc_manage_notifications'] = true;
        }
    }
    return $allcaps;
}

/** گزارشات مالی برای منشی */
add_filter('user_has_cap', 'sc_secretary_finance_reports_cap', 10, 4);
function sc_secretary_finance_reports_cap($allcaps, $caps, $args, $user) {
    foreach ($caps as $cap) {
        if ($cap !== 'sc_finance_reports_access') {
            continue;
        }
        if (!empty($user->roles) && in_array('secretary', (array) $user->roles, true)
            && empty($allcaps['administrator']) && empty($allcaps['club_coach']) && empty($allcaps['system_manager'])) {
            $allcaps['sc_finance_reports_access'] = true;
        }
    }
    return $allcaps;
}

/** تیکت پشتیبانی برای منشی */
add_action('admin_init', 'sc_secretary_register_support_cap', 6);
function sc_secretary_register_support_cap() {
    if (!defined('SC_CAP_CLUB_SUPPORT_TICKETS')) {
        return;
    }
    $role = get_role('secretary');
    if ($role && !$role->has_cap(SC_CAP_CLUB_SUPPORT_TICKETS)) {
        $role->add_cap(SC_CAP_CLUB_SUPPORT_TICKETS);
    }
}

/** ریدایرکت لاگین منشی به wp-admin */
add_filter('login_redirect', 'sc_secretary_login_redirect', 20, 3);
function sc_secretary_login_redirect($redirect_to, $requested, $user) {
    if ($user instanceof WP_User && sc_user_is_secretary_only($user->ID)) {
        return admin_url('index.php');
    }
    return $redirect_to;
}

add_filter('woocommerce_login_redirect', 'sc_secretary_wc_login_redirect', 20, 2);
function sc_secretary_wc_login_redirect($redirect, $user) {
    if ($user instanceof WP_User && sc_user_is_secretary_only($user->ID)) {
        return admin_url('index.php');
    }
    return $redirect;
}

/**
 * ووکامرس کاربران بدون edit_posts را از wp-admin بیرون می‌کند — منشی استثنا است.
 */
add_filter('woocommerce_prevent_admin_access', 'sc_secretary_allow_wp_admin_access', 20);
function sc_secretary_allow_wp_admin_access($prevent) {
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        return false;
    }
    return $prevent;
}

/** نمایش نوار ادمین برای منشی */
add_filter('show_admin_bar', 'sc_secretary_show_admin_bar', 20);
function sc_secretary_show_admin_bar($show) {
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        return true;
    }
    return $show;
}

/** URL پنل منشی */
function sc_secretary_admin_url() {
    return admin_url('index.php');
}

/** اگر منشی به my-account یا پورتال بازیکن رفت → پنل ادمین */
add_action('template_redirect', 'sc_secretary_redirect_from_player_area', 5);
function sc_secretary_redirect_from_player_area() {
    if (!is_user_logged_in() || !function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only()) {
        return;
    }
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    $request_path = isset($GLOBALS['wp']->request) ? trim((string) $GLOBALS['wp']->request, '/') : '';
    $is_my_account = ($request_path === 'my-account' || strpos($request_path, 'my-account/') === 0);
    $is_portal = ($request_path === 'portal' || strpos($request_path, 'portal/') === 0);
    if (function_exists('is_account_page') && is_account_page()) {
        $is_my_account = true;
    }
    if ($is_my_account || $is_portal) {
        wp_safe_redirect(sc_secretary_admin_url());
        exit;
    }
}

add_action('admin_notices', 'sc_secretary_no_chapters_notice', 2);
function sc_secretary_no_chapters_notice() {
    if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only()) {
        return;
    }
    if (!empty(sc_get_secretary_chapters())) {
        return;
    }
    echo '<div class="notice notice-error"><p>هیچ شعبه‌ای برای حساب منشی شما تعریف نشده است. با مدیر باشگاه تماس بگیرید.</p></div>';
}

/** فیلتر شعبه در بالای ادمین */
add_action('admin_notices', 'sc_secretary_render_branch_filter_bar', 3);
function sc_secretary_render_branch_filter_bar() {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $chapters = sc_get_secretary_chapters();
    if (count($chapters) < 2) {
        return;
    }
    $current = sc_secretary_get_filter_chapter();
    $base = remove_query_arg(SC_SECRETARY_FILTER_QUERY_VAR);
    ?>
    <div class="notice notice-info sc-secretary-branch-filter" style="display:flex;align-items:center;gap:12px;padding:10px 14px;">
        <strong>فیلتر شعبه:</strong>
        <select id="sc-secretary-chapter-filter" style="min-width:200px;">
            <option value="all" <?php selected($current, 'all'); ?>>همه شعبه‌های مجاز</option>
            <?php foreach ($chapters as $ch) : ?>
                <option value="<?php echo esc_attr($ch); ?>" <?php selected($current, $ch); ?>><?php echo esc_html($ch); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <script>
    (function () {
        var sel = document.getElementById('sc-secretary-chapter-filter');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (this.value === 'all') {
                url.searchParams.delete('<?php echo esc_js(SC_SECRETARY_FILTER_QUERY_VAR); ?>');
            } else {
                url.searchParams.set('<?php echo esc_js(SC_SECRETARY_FILTER_QUERY_VAR); ?>', this.value);
            }
            window.location.href = url.toString();
        });
    })();
    </script>
    <?php
}

/** فیلد شعبه در افزودن/ویرایش کاربر */
add_action('user_new_form', 'sc_secretary_user_form_fields');
add_action('show_user_profile', 'sc_secretary_user_form_fields');
add_action('edit_user_profile', 'sc_secretary_user_form_fields');

function sc_secretary_user_form_fields($user) {
    if (!sc_user_can_manage_secretaries()) {
        return;
    }
    $selected = [];
    $is_secretary = false;
    if ($user instanceof WP_User) {
        $selected = sc_get_secretary_chapters($user->ID);
        $is_secretary = in_array('secretary', (array) $user->roles, true);
    }
    $chapters = sc_secretary_get_all_chapter_names();
    ?>
    <h2>دسترسی منشی / شعبه‌ها</h2>
    <table class="form-table sc-secretary-chapters-row" id="sc-secretary-chapters-wrap"<?php echo $is_secretary ? '' : ' style="display:none;"'; ?>>
        <tr>
            <th><label for="sc_secretary_chapters">شعبه‌های منشی</label></th>
            <td>
                <?php if (empty($chapters)) : ?>
                    <p class="description">ابتدا شعبه‌ها را از بخش مدیریت شعب تعریف کنید.</p>
                <?php else : ?>
                    <select name="sc_secretary_chapters[]" id="sc_secretary_chapters" multiple size="<?php echo esc_attr(min(8, max(3, count($chapters)))); ?>" style="min-width:280px;">
                        <?php foreach ($chapters as $ch) : ?>
                            <option value="<?php echo esc_attr($ch); ?>" <?php selected(in_array($ch, $selected, true)); ?>><?php echo esc_html($ch); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">برای نقش منشی، یک یا چند شعبه انتخاب یا تغییر دهید (Ctrl+کلیک). در ویرایش کاربر هم می‌توانید شعبه‌ها را تغییر دهید.</p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
    sc_secretary_enqueue_user_role_script();
}

function sc_secretary_enqueue_user_role_script() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    wp_enqueue_script(
        'sc-secretary-user-role',
        SC_ASSETS_URL . 'js/secretary-user-role.js',
        ['jquery'],
        defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0',
        true
    );
}

add_action('user_register', 'sc_secretary_save_user_chapters', 99);
add_action('edit_user_profile_update', 'sc_secretary_save_user_chapters', 99);
add_action('personal_options_update', 'sc_secretary_save_user_chapters', 99);

function sc_secretary_save_user_chapters($user_id) {
    if (!sc_user_can_manage_secretaries()) {
        return;
    }
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }
    if (!in_array('secretary', (array) $user->roles, true)) {
        delete_user_meta($user_id, SC_SECRETARY_CHAPTERS_META);
        return;
    }
    if (!isset($_POST['sc_secretary_chapters'])) {
        return;
    }
    $chapters = (array) wp_unslash($_POST['sc_secretary_chapters']);
    sc_save_secretary_chapters($user_id, $chapters);
}

add_action('user_register', 'sc_secretary_guard_role_assignment', 5);
add_action('edit_user_profile_update', 'sc_secretary_guard_role_assignment', 5);
function sc_secretary_guard_role_assignment($user_id) {
    if (sc_user_can_manage_secretaries()) {
        return;
    }
    if (isset($_POST['role']) && sanitize_text_field(wp_unslash((string) $_POST['role'])) === 'secretary') {
        wp_die('شما مجاز به تعیین نقش منشی نیستید.', 'خطای دسترسی', ['response' => 403]);
    }
}

/** جلوگیری از ایجاد منشی توسط خود منشی */
add_filter('editable_roles', 'sc_secretary_filter_editable_roles');
function sc_secretary_filter_editable_roles($roles) {
    if (sc_user_is_secretary_only()) {
        unset($roles['secretary'], $roles['administrator'], $roles['club_coach'], $roles['system_manager']);
    }
    return $roles;
}

/**
 * به‌روزرسانی وضعیت پرداخت فاکتور + همگام‌سازی سفارش WooCommerce.
 *
 * @return true|\WP_Error
 */
function sc_secretary_apply_invoice_payment_status($invoice_id, $payment_status) {
    $invoice_id = absint($invoice_id);
    $payment_status = sanitize_text_field((string) $payment_status);
    $allowed = ['pending', 'processing', 'completed', 'cancelled', 'on-hold'];
    if ($invoice_id < 1 || !in_array($payment_status, $allowed, true)) {
        return new WP_Error('sc_inv_status', 'وضعیت یا شناسه فاکتور نامعتبر است.');
    }

    global $wpdb;
    $inv_table = $wpdb->prefix . 'sc_invoices';
    $prev = $wpdb->get_row($wpdb->prepare("SELECT status, woocommerce_order_id FROM {$inv_table} WHERE id = %d LIMIT 1", $invoice_id));
    if (!$prev) {
        return new WP_Error('sc_inv_missing', 'فاکتور یافت نشد.');
    }

    if (in_array($payment_status, ['processing', 'completed'], true)) {
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$inv_table} SET status = %s, payment_date = %s, updated_at = %s WHERE id = %d",
            $payment_status,
            current_time('mysql'),
            current_time('mysql'),
            $invoice_id
        ));
    } else {
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$inv_table} SET status = %s, payment_date = NULL, updated_at = %s WHERE id = %d",
            $payment_status,
            current_time('mysql'),
            $invoice_id
        ));
    }
    if ($updated === false) {
        return new WP_Error('sc_inv_db', 'خطا در به‌روزرسانی وضعیت فاکتور: ' . ($wpdb->last_error ?: 'نامشخص'));
    }

    if (!empty($prev->woocommerce_order_id) && function_exists('wc_get_order')) {
        $order = wc_get_order((int) $prev->woocommerce_order_id);
        if ($order && $order->get_status() !== $payment_status) {
            $order->update_status($payment_status, 'تغییر وضعیت از اقدامات سریع منشی');
        }
    }

    $was_paid = in_array((string) $prev->status, ['completed', 'paid', 'processing'], true);
    if (!$was_paid && in_array($payment_status, ['completed', 'processing'], true)) {
        do_action('sc_invoice_paid', $invoice_id);
    }

    return true;
}

/**
 * آیا دوره برای شعبه در برنامه هفتگی ثبت شده است؟
 */
function sc_secretary_course_has_weekly_schedule_in_chapter($course_id, $chapter) {
    $course_id = absint($course_id);
    $chapter = trim(sanitize_text_field((string) $chapter));
    if ($course_id < 1 || $chapter === '') {
        return false;
    }
    if (!function_exists('sc_course_weekly_schedule_table_ready') || !sc_course_weekly_schedule_table_ready()) {
        return true;
    }
    if (!function_exists('sc_course_schedule_has_chapter_coach_columns') || !sc_course_schedule_has_chapter_coach_columns()) {
        return true;
    }
    global $wpdb;
    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$sch} WHERE course_id = %d AND TRIM(IFNULL(chapter_name, '')) = %s",
        $course_id,
        $chapter
    )) > 0;
}

/**
 * دوره‌های قابل انتخاب در اقدامات سریع برای یک شعبه (دارای برنامه هفتگی فعال).
 *
 * @return object[]
 */
function sc_secretary_get_quick_action_courses($chapter = '') {
    $chapter = trim(sanitize_text_field((string) $chapter));
    if ($chapter !== '' && !sc_secretary_chapter_in_scope($chapter)) {
        return [];
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $chapters_table = $wpdb->prefix . 'sc_course_chapters';

    if (!sc_quick_actions_is_branch_scoped() && sc_user_can_manage_secretaries()) {
        $chapters = $chapter !== '' ? [$chapter] : sc_secretary_get_all_chapter_names();
        if (empty($chapters)) {
            $rows = $wpdb->get_results(
                "SELECT * FROM {$courses_table} WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
            );
            return is_array($rows) ? $rows : [];
        }
        $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
        $sql = "SELECT DISTINCT c.*
                FROM {$courses_table} c
                INNER JOIN {$chapters_table} cc ON cc.course_id = c.id
                WHERE c.deleted_at IS NULL AND c.is_active = 1
                  AND TRIM(cc.chapter_name) IN ({$placeholders})
                ORDER BY c.title ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $chapters));
        return is_array($rows) ? $rows : [];
    }

    $chapters = $chapter !== '' ? [$chapter] : sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $sql = "SELECT DISTINCT c.*
            FROM {$courses_table} c
            INNER JOIN {$chapters_table} cc ON cc.course_id = c.id
            WHERE c.deleted_at IS NULL AND c.is_active = 1
              AND TRIM(cc.chapter_name) IN ({$placeholders})
            ORDER BY c.title ASC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $chapters));
    $courses = is_array($rows) ? $rows : [];

    if ($chapter === '') {
        $weekly_ids = sc_secretary_get_branch_weekly_schedule_course_ids();
        if (empty($weekly_ids)) {
            return $courses;
        }
        $weekly_map = array_fill_keys($weekly_ids, true);
        return array_values(array_filter($courses, static function ($row) use ($weekly_map) {
            return isset($weekly_map[(int) $row->id]);
        }));
    }

    return array_values(array_filter($courses, static function ($row) use ($chapter) {
        return sc_secretary_course_has_weekly_schedule_in_chapter((int) $row->id, $chapter);
    }));
}

/**
 * فعال‌سازی یا به‌روزرسانی ثبت‌نام یک دوره (بدون دست زدن به سایر دوره‌های بازیکن).
 *
 * @return int|\WP_Error شناسه sc_member_courses
 */
function sc_secretary_upsert_active_member_course($member_id, $course_id, $chapter, $coach_id = 0, $group_name = '') {
    $member_id = absint($member_id);
    $course_id = absint($course_id);
    $chapter = sanitize_text_field((string) $chapter);
    $coach_id = absint($coach_id);
    $group_name = sanitize_text_field((string) $group_name);

    if ($member_id < 1 || $course_id < 1 || $chapter === '') {
        return new WP_Error('sc_qa_bad_args', 'اطلاعات ثبت‌نام ناقص است.');
    }

    global $wpdb;
    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$courses_table} WHERE id = %d AND deleted_at IS NULL AND is_active = 1 LIMIT 1",
        $course_id
    ));
    if (!$course) {
        return new WP_Error('sc_qa_no_course', 'دوره یافت نشد یا غیرفعال است.');
    }

    $pkg_sel = 0;
    if (function_exists('sc_course_has_packages') && sc_course_has_packages($course_id)) {
        $pkgs = function_exists('sc_get_course_packages') ? sc_get_course_packages($course_id) : [];
        if (!empty($pkgs[0]->sessions_count)) {
            $pkg_sel = (int) $pkgs[0]->sessions_count;
        }
        if ($pkg_sel < 1) {
            return new WP_Error('sc_qa_pkg', 'برای این دوره پکیج جلسه تعریف نشده است.');
        }
    }

    $sf = function_exists('sc_member_course_session_fields_for_course')
        ? sc_member_course_session_fields_for_course($course_id, $pkg_sel > 0 ? $pkg_sel : null)
        : ['enrollment_sessions' => null, 'total_sessions' => 0, 'remaining_sessions' => 0];

    $existing_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, coach_id, chapter, group_name, status FROM {$mc_table} WHERE member_id = %d AND course_id = %d LIMIT 1",
        $member_id,
        $course_id
    ));

    $existing_coach_id = $existing_row ? (int) $existing_row->coach_id : 0;
    $existing_chapter = $existing_row && isset($existing_row->chapter) ? (string) $existing_row->chapter : '';
    $existing_group = $existing_row && isset($existing_row->group_name) ? (string) $existing_row->group_name : '';
    $assignment = function_exists('sc_resolve_member_course_assignment')
        ? sc_resolve_member_course_assignment($course_id, $chapter, $coach_id, $existing_chapter, $existing_coach_id)
        : ['chapter' => $chapter, 'coach_id' => $coach_id > 0 ? $coach_id : $existing_coach_id];
    if (function_exists('sc_resolve_member_course_group')) {
        $assignment['group_name'] = sc_resolve_member_course_group($course_id, $group_name, $existing_group);
    } else {
        $assignment['group_name'] = $group_name !== '' ? $group_name : $existing_group;
    }

    $active_fields = [
        'status' => 'active',
        'coach_id' => (int) $assignment['coach_id'],
        'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
        'enrollment_date' => current_time('Y-m-d'),
        'total_sessions' => (int) $sf['total_sessions'],
        'remaining_sessions' => (int) $sf['remaining_sessions'],
        'updated_at' => current_time('mysql'),
    ];
    $fmt = ['%s', '%d', '%s', '%s', '%d', '%d', '%s'];
    if (function_exists('sc_member_course_row_with_group')) {
        $active_fields = array_merge($active_fields, sc_member_course_row_with_group($assignment));
        $fmt[] = '%s';
    }
    if ($sf['enrollment_sessions'] === null) {
        $active_fields['enrollment_sessions'] = null;
        $fmt[] = '%s';
    } else {
        $active_fields['enrollment_sessions'] = (int) $sf['enrollment_sessions'];
        $fmt[] = '%d';
    }

    if ($existing_row) {
        $res = $wpdb->update($mc_table, $active_fields, ['id' => (int) $existing_row->id], $fmt, ['%d']);
        if ($res === false) {
            return new WP_Error('sc_qa_db', 'خطا در به‌روزرسانی ثبت‌نام: ' . ($wpdb->last_error ?: 'نامشخص'));
        }
        return (int) $existing_row->id;
    }

    $insert_row = array_merge($active_fields, [
        'member_id' => $member_id,
        'course_id' => $course_id,
        'course_status_flags' => '',
        'created_at' => current_time('mysql'),
    ]);
    $insert_fmt = array_merge($fmt, ['%d', '%d', '%s', '%s']);
    $res = $wpdb->insert($mc_table, $insert_row, $insert_fmt);
    if ($res === false) {
        return new WP_Error('sc_qa_db', 'خطا در ایجاد ثبت‌نام: ' . ($wpdb->last_error ?: 'نامشخص'));
    }

    return (int) $wpdb->insert_id;
}

/**
 * بررسی امکان ثبت‌نام سریع قبل از ثبت نهایی.
 *
 * @param array<string,mixed> $args
 * @return array{ok:bool,messages:array<int,array{type:string,text:string}>,sound:string,member_course_id?:int}
 */
function sc_secretary_validate_quick_enroll($args) {
    $messages = [];
    $sound = 'error';

    if (!sc_user_can_quick_actions()) {
        return ['ok' => false, 'messages' => [['type' => 'error', 'text' => 'دسترسی ندارید.']], 'sound' => 'error'];
    }

    $member_id = absint($args['member_id'] ?? 0);
    $course_id = absint($args['course_id'] ?? 0);
    $chapter = sanitize_text_field((string) ($args['chapter'] ?? ''));
    $is_new_member = !empty($args['is_new_member']);
    $mobile = isset($args['mobile']) ? preg_replace('/\D/', '', (string) $args['mobile']) : '';
    $first_name = sanitize_text_field((string) ($args['first_name'] ?? ''));
    $last_name = sanitize_text_field((string) ($args['last_name'] ?? ''));

    if ($is_new_member) {
        if (!preg_match('/^09\d{9}$/', $mobile)) {
            $messages[] = ['type' => 'error', 'text' => 'شماره موبایل معتبر نیست (مثال: 09123456789).'];
        } else {
            $messages[] = ['type' => 'success', 'text' => 'شماره موبایل معتبر است.'];
        }
        if ($first_name === '' || $last_name === '') {
            $messages[] = ['type' => 'error', 'text' => 'نام و نام خانوادگی الزامی است.'];
        } else {
            $messages[] = ['type' => 'success', 'text' => 'نام بازیکن: ' . trim($first_name . ' ' . $last_name)];
        }
        if (username_exists($mobile) || email_exists($mobile . '@sportclub.local')) {
            $messages[] = ['type' => 'error', 'text' => 'این شماره قبلاً ثبت شده؛ از تب «بازیکن موجود» استفاده کنید.'];
        }
    } else {
        if ($member_id < 1) {
            $messages[] = ['type' => 'error', 'text' => 'بازیکن را از نتایج جستجو انتخاب کنید.'];
        } else {
            global $wpdb;
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT id, first_name, last_name, player_phone, is_active FROM {$wpdb->prefix}sc_members WHERE id = %d LIMIT 1",
                $member_id
            ));
            if (!$member) {
                $messages[] = ['type' => 'error', 'text' => 'بازیکن یافت نشد.'];
            } else {
                $label = trim($member->first_name . ' ' . $member->last_name) . ' — ' . $member->player_phone;
                $messages[] = ['type' => 'success', 'text' => 'بازیکن انتخاب‌شده: ' . $label];
                if ((int) $member->is_active !== 1) {
                    $messages[] = ['type' => 'warning', 'text' => 'این بازیکن در سیستم غیرفعال است.'];
                    $sound = 'debt_warning';
                }
            }
        }
    }

    if ($chapter === '') {
        $messages[] = ['type' => 'error', 'text' => 'شعبه را انتخاب کنید.'];
    } elseif (!sc_secretary_chapter_in_scope($chapter)) {
        $messages[] = ['type' => 'error', 'text' => 'شعبه انتخاب‌شده در محدوده دسترسی شما نیست.'];
    } else {
        $messages[] = ['type' => 'success', 'text' => 'شعبه: ' . $chapter];
    }

    if ($course_id < 1) {
        $messages[] = ['type' => 'error', 'text' => 'دوره را انتخاب کنید.'];
    } else {
        global $wpdb;
        $courses_table = $wpdb->prefix . 'sc_courses';
        $chapters_table = $wpdb->prefix . 'sc_course_chapters';
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT id, title FROM {$courses_table} WHERE id = %d AND deleted_at IS NULL AND is_active = 1 LIMIT 1",
            $course_id
        ));
        if (!$course) {
            $messages[] = ['type' => 'error', 'text' => 'دوره یافت نشد یا غیرفعال است.'];
        } else {
            $allowed_ids = array_map(static function ($row) {
                return (int) $row->id;
            }, sc_secretary_get_quick_action_courses($chapter));
            if (!in_array($course_id, $allowed_ids, true)) {
                $messages[] = ['type' => 'error', 'text' => 'این دوره برای شعبه «' . $chapter . '» در برنامه هفتگی فعال نیست یا قابل ثبت‌نام نیست.'];
            } else {
                $ok_chapter = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$chapters_table} WHERE course_id = %d AND TRIM(chapter_name) = %s",
                    $course_id,
                    $chapter
                ));
                if ($ok_chapter < 1) {
                    $messages[] = ['type' => 'error', 'text' => 'این دوره به شعبه انتخاب‌شده متصل نیست.'];
                } else {
                    $messages[] = ['type' => 'success', 'text' => 'دوره: ' . $course->title];
                    if (!sc_secretary_course_has_weekly_schedule_in_chapter($course_id, $chapter)) {
                        $messages[] = ['type' => 'error', 'text' => 'برای این دوره در شعبه انتخاب‌شده برنامه هفتگی ثبت نشده است.'];
                    } else {
                        $messages[] = ['type' => 'success', 'text' => 'برنامه هفتگی شعبه برای این دوره فعال است.'];
                    }
                }
            }

            if ($member_id > 0 && !$is_new_member) {
                $mc_table = $wpdb->prefix . 'sc_member_courses';
                $mc = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, status, chapter, remaining_sessions, total_sessions FROM {$mc_table}
                     WHERE member_id = %d AND course_id = %d LIMIT 1",
                    $member_id,
                    $course_id
                ));
                if ($mc && (string) $mc->status === 'active') {
                    $chapter_label = trim((string) $mc->chapter) !== '' ? (string) $mc->chapter : '—';
                    $messages[] = [
                        'type' => 'error',
                        'text' => 'این بازیکن هم‌اکنون در دوره «' . $course->title . '» فعال است (شعبه: ' . $chapter_label . '). ثبت‌نام مجدد و صدور فاکتور جدید امکان‌پذیر نیست.',
                    ];
                    $inv_table = $wpdb->prefix . 'sc_invoices';
                    $inv_count = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$inv_table} WHERE member_course_id = %d",
                        (int) $mc->id
                    ));
                    if ($inv_count > 0) {
                        $messages[] = [
                            'type' => 'info',
                            'text' => 'برای این ثبت‌نام ' . number_format_i18n($inv_count) . ' فاکتور قبلاً صادر شده است.',
                        ];
                    }
                }
            }

            if (function_exists('sc_course_has_packages') && sc_course_has_packages($course_id)) {
                $pkgs = function_exists('sc_get_course_packages') ? sc_get_course_packages($course_id) : [];
                if (empty($pkgs)) {
                    $messages[] = ['type' => 'error', 'text' => 'برای دوره پکیجی، پکیج جلسه تعریف نشده است.'];
                } else {
                    $messages[] = ['type' => 'info', 'text' => 'پکیج پیش‌فرض: ' . (int) $pkgs[0]->sessions_count . ' جلسه'];
                }
            } elseif (function_exists('sc_member_course_session_fields_for_course')) {
                $sf = sc_member_course_session_fields_for_course($course_id);
                $messages[] = ['type' => 'info', 'text' => 'پس از فعال‌سازی، ' . (int) $sf['remaining_sessions'] . ' جلسه شارژ می‌شود.'];
            }
        }
    }

    $has_error = false;
    foreach ($messages as $msg) {
        if (($msg['type'] ?? '') === 'error') {
            $has_error = true;
            break;
        }
    }
    if (!$has_error) {
        $sound = 'success';
    }

    return ['ok' => !$has_error, 'messages' => $messages, 'sound' => $sound];
}

/**
 * ثبت‌نام سریع: فعال‌سازی دوره + فاکتور.
 *
 * @param array<string,mixed> $args
 * @return array{success:bool,message:string,member_id?:int,invoice_id?:int,member_course_id?:int}
 */
function sc_secretary_quick_enroll($args) {
    if (!sc_user_can_quick_actions()) {
        return ['success' => false, 'message' => 'دسترسی ندارید.'];
    }

    $validation = sc_secretary_validate_quick_enroll($args);
    if (empty($validation['ok'])) {
        $errors = array_filter(array_map(static function ($m) {
            return ($m['type'] ?? '') === 'error' ? ($m['text'] ?? '') : '';
        }, $validation['messages']));
        return ['success' => false, 'message' => $errors ? implode(' ', $errors) : 'امکان ثبت‌نام وجود ندارد.'];
    }

    $member_id = absint($args['member_id'] ?? 0);
    $course_id = absint($args['course_id'] ?? 0);
    $chapter = sanitize_text_field((string) ($args['chapter'] ?? ''));
    $payment_status = sanitize_text_field((string) ($args['payment_status'] ?? 'pending'));
    $allowed_status = ['pending', 'processing', 'completed'];
    if (!in_array($payment_status, $allowed_status, true)) {
        $payment_status = 'pending';
    }

    global $wpdb;
    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $inv_table = $wpdb->prefix . 'sc_invoices';
    $existing_mc = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status FROM {$mc_table} WHERE member_id = %d AND course_id = %d LIMIT 1",
        $member_id,
        $course_id
    ));
    if ($existing_mc && (string) $existing_mc->status === 'active') {
        return [
            'success' => false,
            'message' => 'این بازیکن هم‌اکنون در این دوره فعال است؛ ثبت‌نام مجدد و صدور فاکتور جدید امکان‌پذیر نیست.',
            'member_id' => $member_id,
            'member_course_id' => (int) $existing_mc->id,
        ];
    }
    if ($existing_mc) {
        $existing_inv_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$inv_table} WHERE member_course_id = %d",
            (int) $existing_mc->id
        ));
        if ($existing_inv_count > 0) {
            return [
                'success' => false,
                'message' => 'برای این بازیکن در این دوره قبلاً فاکتور صادر شده است؛ فاکتور جدید ایجاد نمی‌شود.',
                'member_id' => $member_id,
                'member_course_id' => (int) $existing_mc->id,
                'invoice_id' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$inv_table} WHERE member_course_id = %d ORDER BY id DESC LIMIT 1",
                    (int) $existing_mc->id
                )),
            ];
        }
    }

    $mc_id = sc_secretary_upsert_active_member_course(
        $member_id,
        $course_id,
        $chapter,
        absint($args['coach_id'] ?? 0),
        sanitize_text_field((string) ($args['group_name'] ?? ''))
    );
    if (is_wp_error($mc_id)) {
        return ['success' => false, 'message' => $mc_id->get_error_message()];
    }

    $invoice_id = 0;
    if (function_exists('sc_create_enrollment_invoice_for_member_course')) {
        $inv = sc_create_enrollment_invoice_for_member_course($member_id, (int) $mc_id, [
            'short_sessions_mode' => 'charge_remaining',
        ]);
        if (!empty($inv['invoice_id'])) {
            $invoice_id = (int) $inv['invoice_id'];
        }
    }
    if ($invoice_id < 1) {
        $invoice_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_invoices WHERE member_course_id = %d ORDER BY id DESC LIMIT 1",
            (int) $mc_id
        ));
    }

    if ($invoice_id > 0 && $payment_status !== 'pending') {
        $status_res = sc_secretary_apply_invoice_payment_status($invoice_id, $payment_status);
        if (is_wp_error($status_res)) {
            return [
                'success' => false,
                'message' => 'ثبت‌نام انجام شد اما به‌روزرسانی وضعیت فاکتور ناموفق بود: ' . $status_res->get_error_message(),
                'member_id' => $member_id,
                'invoice_id' => $invoice_id,
                'member_course_id' => (int) $mc_id,
            ];
        }
    }

    $status_labels = [
        'pending' => 'در انتظار پرداخت',
        'processing' => 'پرداخت شده',
        'completed' => 'تایید پرداخت',
    ];
    $pay_label = $status_labels[$payment_status] ?? $payment_status;
    $msg = 'ثبت‌نام با موفقیت انجام شد و بازیکن در دوره فعال شد.';
    if ($invoice_id > 0) {
        $msg .= ' فاکتور صادر شد';
        $msg .= $payment_status === 'pending' ? ' (در انتظار پرداخت).' : ' (وضعیت: ' . $pay_label . ').';
    } else {
        $msg .= ' (فاکتور در این مرحله صادر نشد؛ ممکن است در ماه بعد ایجاد شود).';
    }

    return [
        'success' => true,
        'message' => $msg,
        'member_id' => $member_id,
        'invoice_id' => $invoice_id,
        'member_course_id' => (int) $mc_id,
    ];
}

/**
 * ایجاد کاربر جدید (بازیکن) + member + ثبت‌نام.
 *
 * @param array<string,mixed> $args
 * @return array{success:bool,message:string,member_id?:int}
 */
function sc_secretary_quick_register_and_enroll($args) {
    if (!sc_user_can_quick_actions()) {
        return ['success' => false, 'message' => 'دسترسی ندارید.'];
    }

    $mobile = isset($args['mobile']) ? preg_replace('/\D/', '', (string) $args['mobile']) : '';
    $first_name = sanitize_text_field((string) ($args['first_name'] ?? ''));
    $last_name = sanitize_text_field((string) ($args['last_name'] ?? ''));

    if (!preg_match('/^09\d{9}$/', $mobile)) {
        return ['success' => false, 'message' => 'شماره موبایل معتبر نیست.'];
    }
    if ($first_name === '' || $last_name === '') {
        return ['success' => false, 'message' => 'نام و نام خانوادگی الزامی است.'];
    }

    if (username_exists($mobile) || email_exists($mobile . '@sportclub.local')) {
        return ['success' => false, 'message' => 'این شماره قبلاً ثبت شده است. از تب کاربر موجود استفاده کنید.'];
    }

    $password = wp_generate_password(12, true);
    $user_id = wp_insert_user([
        'user_login' => $mobile,
        'user_email' => $mobile . '@sportclub.local',
        'user_pass' => $password,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => 'subscriber',
        'display_name' => trim($first_name . ' ' . $last_name),
    ]);
    if (is_wp_error($user_id)) {
        return ['success' => false, 'message' => $user_id->get_error_message()];
    }
    update_user_meta($user_id, 'billing_phone', $mobile);

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    if ($member_id < 1 && function_exists('sc_auto_create_member_on_user_register')) {
        sc_auto_create_member_on_user_register($user_id);
        $member_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));
    }
    if ($member_id < 1) {
        return ['success' => false, 'message' => 'خطا در ایجاد پروفایل بازیکن.'];
    }

    $wpdb->update(
        $members_table,
        [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'player_phone' => $mobile,
            'is_active' => 1,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $member_id],
        ['%s', '%s', '%s', '%d', '%s'],
        ['%d']
    );

    $args['member_id'] = $member_id;
    return sc_secretary_quick_enroll($args);
}

add_action('wp_ajax_sc_secretary_validate_enroll', 'sc_ajax_secretary_validate_enroll');
function sc_ajax_secretary_validate_enroll() {
    check_ajax_referer('sc_secretary_quick_actions', 'nonce');
    if (!sc_user_can_quick_actions()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $tab = isset($_POST['tab']) ? sanitize_text_field(wp_unslash($_POST['tab'])) : 'existing';
    $args = [
        'member_id' => isset($_POST['member_id']) ? absint($_POST['member_id']) : 0,
        'course_id' => isset($_POST['course_id']) ? absint($_POST['course_id']) : 0,
        'chapter' => isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '',
        'is_new_member' => ($tab === 'new'),
        'mobile' => isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '',
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
    ];
    $result = sc_secretary_validate_quick_enroll($args);
    if (empty($result['ok'])) {
        wp_send_json_error($result);
    }
    wp_send_json_success($result);
}

add_action('wp_ajax_sc_secretary_quick_action_courses', 'sc_ajax_secretary_quick_action_courses');
function sc_ajax_secretary_quick_action_courses() {
    check_ajax_referer('sc_secretary_quick_actions', 'nonce');
    if (!sc_user_can_quick_actions()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $chapter = isset($_GET['chapter']) ? sanitize_text_field(wp_unslash($_GET['chapter'])) : '';
    $courses = sc_secretary_get_quick_action_courses($chapter);
    $items = [];
    foreach ($courses as $c) {
        $items[] = ['id' => (int) $c->id, 'title' => (string) $c->title];
    }
    wp_send_json_success(['items' => $items]);
}

add_action('wp_ajax_sc_secretary_quick_enroll', 'sc_ajax_secretary_quick_enroll');
function sc_ajax_secretary_quick_enroll() {
    check_ajax_referer('sc_secretary_quick_actions', 'nonce');
    if (!sc_user_can_quick_actions()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $result = sc_secretary_quick_enroll([
        'member_id' => isset($_POST['member_id']) ? absint($_POST['member_id']) : 0,
        'course_id' => isset($_POST['course_id']) ? absint($_POST['course_id']) : 0,
        'chapter' => isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '',
        'payment_status' => isset($_POST['payment_status']) ? sanitize_text_field(wp_unslash($_POST['payment_status'])) : 'pending',
        'coach_id' => isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0,
        'group_name' => isset($_POST['group_name']) ? sanitize_text_field(wp_unslash($_POST['group_name'])) : '',
    ]);
    if (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message'] ?? 'خطا']);
    }
    wp_send_json_success($result);
}

add_action('wp_ajax_sc_secretary_quick_register', 'sc_ajax_secretary_quick_register');
function sc_ajax_secretary_quick_register() {
    check_ajax_referer('sc_secretary_quick_actions', 'nonce');
    if (!sc_user_can_quick_actions()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $result = sc_secretary_quick_register_and_enroll([
        'mobile' => isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '',
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
        'course_id' => isset($_POST['course_id']) ? absint($_POST['course_id']) : 0,
        'chapter' => isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '',
        'payment_status' => isset($_POST['payment_status']) ? sanitize_text_field(wp_unslash($_POST['payment_status'])) : 'pending',
    ]);
    if (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message'] ?? 'خطا']);
    }
    wp_send_json_success($result);
}

add_action('wp_ajax_sc_secretary_search_members', 'sc_ajax_secretary_search_members');
function sc_ajax_secretary_search_members() {
    check_ajax_referer('sc_secretary_quick_actions', 'nonce');
    if (!sc_user_can_quick_actions()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
    if ($q === '') {
        wp_send_json_success(['items' => []]);
    }
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    // در اقدامات سریع، منشی باید بتواند همه بازیکنان سایت را پیدا کند (حتی از شعبه دیگر).
    $digits = preg_replace('/\D/', '', $q);
    $like = '%' . $wpdb->esc_like($q) . '%';
    $where = '(m.first_name LIKE %s OR m.last_name LIKE %s OR CONCAT(m.first_name, " ", m.last_name) LIKE %s OR m.player_phone LIKE %s OR m.national_id LIKE %s)';
    $args = [$like, $like, $like, $like, $like];
    if ($digits !== '' && $digits !== $q) {
        $digits_like = '%' . $wpdb->esc_like($digits) . '%';
        $where .= ' OR REPLACE(REPLACE(m.player_phone, "-", ""), " ", "") LIKE %s OR m.national_id LIKE %s';
        $args[] = $digits_like;
        $args[] = $digits_like;
    }
    $sql = "SELECT m.id, m.first_name, m.last_name, m.player_phone, m.national_id
            FROM {$members_table} m
            WHERE {$where}
            ORDER BY m.last_name, m.first_name
            LIMIT 30";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
    $out = [];
    foreach ((array) $rows as $row) {
        $name = trim($row->first_name . ' ' . $row->last_name);
        $label = $name . ' — ' . $row->player_phone;
        if (!empty($row->national_id)) {
            $label .= ' (کد ملی: ' . $row->national_id . ')';
        }
        $out[] = [
            'id' => (int) $row->id,
            'label' => $label,
            'name' => $name,
            'phone' => (string) $row->player_phone,
        ];
    }
    wp_send_json_success(['items' => $out]);
}

/**
 * @param array<int,string> $where
 * @param array<int,mixed> $params
 */
function sc_secretary_merge_member_where_parts(array &$where, array &$params, $member_alias = 'm') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $scope = sc_secretary_member_scope_sql($member_alias);
    if ($scope['sql'] === '') {
        return;
    }
    $part = preg_replace('/^\s*AND\s+/i', '', trim($scope['sql']));
    if ($part !== '') {
        $where[] = $part;
        $params = array_merge($params, $scope['args']);
    }
}

/**
 * @param array<int,string> $where_conditions
 * @param array<int,mixed> $where_values
 */
function sc_secretary_merge_invoice_where(array &$where_conditions, array &$where_values, $alias = 'i') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $scope = sc_secretary_invoice_chapter_scope_sql($alias);
    if ($scope['sql'] === '') {
        return;
    }
    $part = preg_replace('/^\s*AND\s+/i', '', trim($scope['sql']));
    if ($part !== '') {
        $where_conditions[] = $part;
        $where_values = array_merge($where_values, $scope['args']);
    }
}

/**
 * @param array<int,string> $where_conditions
 * @param array<int,mixed> $where_values
 */
function sc_secretary_merge_expense_where(array &$where_conditions, array &$where_values, $alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $scope = sc_secretary_expense_chapter_scope_sql($alias);
    if ($scope['sql'] === '') {
        return;
    }
    $part = preg_replace('/^\s*AND\s+/i', '', trim($scope['sql']));
    if ($part !== '') {
        $where_conditions[] = $part;
        $where_values = array_merge($where_values, $scope['args']);
    }
}

/**
 * @param string $event_alias
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_secretary_event_chapter_scope_sql($event_alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return ['sql' => '', 'args' => []];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $event_alias) ?: 'e';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $sql = " AND TRIM(IFNULL({$alias}.chapter, '')) IN ({$placeholders})";
    return ['sql' => $sql, 'args' => $chapters];
}

/**
 * @param array<int,string> $where_conditions
 * @param array<int,mixed> $where_values
 * @param string $alias
 */
function sc_secretary_merge_event_chapter_where(array &$where_conditions, array &$where_values, $alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $scope = sc_secretary_event_chapter_scope_sql($alias);
    if ($scope['sql'] === '') {
        return;
    }
    $part = preg_replace('/^\s*AND\s+/i', '', trim($scope['sql']));
    if ($part !== '') {
        $where_conditions[] = $part;
        $where_values = array_merge($where_values, $scope['args']);
    }
}

/**
 * محدوده ثبت‌نامی رویداد برای منشی: رویدادهای شعبه + بازیکنان شعبه.
 *
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_secretary_event_registration_scope_sql($reg_alias = 'r', $event_alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return ['sql' => '', 'args' => []];
    }
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }
    global $wpdb;
    $reg_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $reg_alias) ?: 'r';
    $event_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $event_alias) ?: 'e';
    $mc = $wpdb->prefix . 'sc_member_courses';
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $member_match = sc_secretary_member_enrollment_match_sql('mc_er');
    $sql = " AND (
        TRIM(IFNULL({$event_alias}.chapter, '')) IN ({$placeholders})
        OR (
            {$reg_alias}.member_id IS NOT NULL
            AND {$reg_alias}.member_id > 0
            AND EXISTS (
                SELECT 1 FROM {$mc} mc_er
                WHERE mc_er.member_id = {$reg_alias}.member_id
                  AND mc_er.status = 'active'
                  AND {$member_match['sql']}
            )
        )
    )";
    return [
        'sql' => $sql,
        'args' => array_merge($chapters, $member_match['args']),
    ];
}

/**
 * @param array<int,string> $where_conditions
 * @param array<int,mixed> $where_values
 */
function sc_secretary_merge_event_registration_where(array &$where_conditions, array &$where_values, $reg_alias = 'r', $event_alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $scope = sc_secretary_event_registration_scope_sql($reg_alias, $event_alias);
    if ($scope['sql'] === '') {
        return;
    }
    $part = preg_replace('/^\s*AND\s+/i', '', trim($scope['sql']));
    if ($part !== '') {
        $where_conditions[] = $part;
        $where_values = array_merge($where_values, $scope['args']);
    }
}

/**
 * شناسه دوره‌های قابل مشاهده منشی در لیست دوره‌ها.
 *
 * @return int[]
 */
function sc_secretary_get_branch_course_ids_for_list() {
    if (!function_exists('sc_secretary_get_branch_courses')) {
        return [];
    }
    $ids = [];
    foreach (sc_secretary_get_branch_courses() as $row) {
        $ids[] = (int) $row->id;
    }
    return array_values(array_unique(array_filter($ids)));
}

/**
 * @param array<int,string> $where_parts
 * @param array<int,mixed> $prepare_args
 */
function sc_secretary_merge_course_list_where(array &$where_parts, array &$prepare_args) {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $ids = sc_secretary_get_branch_course_ids_for_list();
    if (empty($ids)) {
        $where_parts[] = '1=0';
        return;
    }
    $holders = implode(',', array_fill(0, count($ids), '%d'));
    $where_parts[] = "c.id IN ({$holders})";
    foreach ($ids as $id) {
        $prepare_args[] = $id;
    }
}

/**
 * آیا منشی فقط مشاهده (بدون ویرایش/حذف) در لیست دوره/رویداد است؟
 */
function sc_secretary_is_readonly_course_event_lists() {
    return sc_user_is_secretary_only();
}

/**
 * افزودن محدوده شعبه به WHERE لیست رویدادها.
 */
function sc_secretary_append_event_list_where($where, $event_alias = 'e') {
    if (!sc_user_is_secretary_only()) {
        return $where;
    }
    global $wpdb;
    $scope = sc_secretary_event_chapter_scope_sql($event_alias);
    if ($scope['sql'] === '') {
        return $where;
    }
    $part = preg_replace('/^\s*AND\s+/i', '', trim($scope['sql']));
    if ($part === '') {
        return $where;
    }
    if (!empty($scope['args'])) {
        return $where . ' AND ' . $wpdb->prepare($part, $scope['args']);
    }
    return $where . ' AND ' . $part;
}

/**
 * محدود کردن درآمد دوره به دوره‌های برگزارشده در شعبه منشی.
 *
 * @param array<int,string> $where_conditions
 * @param array<int,mixed> $where_values
 * @param string $course_id_column
 */
function sc_secretary_merge_finance_course_scope(array &$where_conditions, array &$where_values, $course_id_column = 'i.course_id') {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $ids = sc_secretary_get_branch_course_ids();
    if (empty($ids)) {
        $where_conditions[] = '1=0';
        return;
    }
    $column = preg_replace('/[^a-zA-Z0-9_.]/', '', (string) $course_id_column) ?: 'i.course_id';
    $holders = implode(',', array_fill(0, count($ids), '%d'));
    $where_conditions[] = "{$column} IN ({$holders})";
    $where_values = array_merge($where_values, $ids);
}

/**
 * @param array<int,string> $where
 * @param array<int,mixed> $params
 * @param string $ticket_alias
 */
function sc_support_apply_secretary_ticket_list_scope(array &$where, array &$params, $ticket_alias = 't') {
    if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only()) {
        return;
    }
    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $members = $wpdb->prefix . 'sc_members';
    $chapters = sc_secretary_get_effective_chapters();
    if (empty($chapters)) {
        $where[] = '1=0';
        return;
    }
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $ticket_alias);
    if ($alias !== '') {
        $alias .= '.';
    }
    $placeholders = implode(', ', array_fill(0, count($chapters), '%s'));
    $where[] = "EXISTS (
        SELECT 1 FROM {$members} sm
        INNER JOIN {$mc} mc_t ON mc_t.member_id = sm.id
        WHERE sm.user_id = {$alias}user_id
          AND mc_t.status = 'active'
          AND mc_t.chapter IN ({$placeholders})
    )";
    $params = array_merge($params, $chapters);
}

/** فیلتر هشدارها برای منشی */
function sc_secretary_filter_alert_rows(array $rows) {
    if (!sc_user_is_secretary_only()) {
        return $rows;
    }
    return array_values(array_filter($rows, static function ($row) {
        return function_exists('sc_secretary_can_access_member')
            && sc_secretary_can_access_member((int) ($row->member_id ?? 0));
    }));
}

/**
 * فیلتر user_idهای مخاطب (ربات بله / اطلاعیه) — فقط بازیکنان شعبه منشی.
 *
 * @param array<int,int> $user_ids
 * @return array<int,int>
 */
function sc_secretary_filter_notification_user_ids(array $user_ids) {
    if (!sc_user_is_secretary_only()) {
        return $user_ids;
    }
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $filtered = [];
    foreach ($user_ids as $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            continue;
        }
        $member_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        if ($member_id > 0 && sc_secretary_can_access_member($member_id)) {
            $filtered[] = $user_id;
        }
    }
    return array_values(array_unique($filtered));
}

/**
 * بازیکنان قابل انتخاب در فیلترها (dropdown) برای منشی.
 *
 * @return object[]
 */
function sc_secretary_get_branch_members_for_picker() {
    if (!sc_user_is_secretary_only()) {
        return [];
    }
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $where = sc_secretary_append_member_where('m.is_active = 1', 'm');
    return $wpdb->get_results(
        "SELECT DISTINCT m.id, m.first_name, m.last_name, m.national_id
         FROM {$members_table} m
         WHERE {$where}
         ORDER BY m.last_name ASC, m.first_name ASC"
    );
}

function sc_secretary_filter_chapters_list($chapters) {
    if (!sc_user_is_secretary_only()) {
        return $chapters;
    }
    $allowed = sc_get_secretary_chapters();
    $out = [];
    foreach ((array) $chapters as $ch) {
        $name = is_object($ch) ? (string) ($ch->name ?? '') : (string) $ch;
        if ($name !== '' && in_array($name, $allowed, true)) {
            $out[] = $ch;
        }
    }
    return $out;
}

/**
 * @param string $filter_chapter
 */
function sc_secretary_validate_report_chapter(&$filter_chapter) {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $effective = sc_secretary_get_effective_chapters();
    if ($filter_chapter !== '' && !in_array($filter_chapter, $effective, true)) {
        $filter_chapter = '';
    }
    if ($filter_chapter === '' && sc_secretary_get_filter_chapter() !== 'all' && count($effective) === 1) {
        $filter_chapter = $effective[0];
    }
}

add_action('admin_enqueue_scripts', 'sc_secretary_enqueue_user_role_script_admin');
function sc_secretary_enqueue_user_role_script_admin($hook) {
    if (!sc_user_can_manage_secretaries()) {
        return;
    }
    if (!in_array($hook, ['user-new.php', 'user-edit.php', 'profile.php'], true)) {
        return;
    }
    sc_secretary_enqueue_user_role_script();
}

/**
 * پیام خطای دسترسی مخصوص نقش منشی.
 *
 * @param string $action_label
 * @param string $detail
 */
function sc_secretary_die_access_denied($action_label = '', $detail = '') {
    $title = 'محدودیت دسترسی منشی';
    $action_html = $action_label !== ''
        ? '<p style="margin:8px 0 0;color:#4b5563;">عملیات: <strong>' . esc_html($action_label) . '</strong></p>'
        : '';
    $detail_html = $detail !== ''
        ? '<p style="margin:10px 0 0;color:#6b7280;line-height:1.8;">' . esc_html($detail) . '</p>'
        : '<p style="margin:10px 0 0;color:#6b7280;line-height:1.8;">نقش شما «منشی شعبه» است و فقط به اطلاعات مربوط به شعبه(های) اختصاص‌یافته‌تان دسترسی دارید.</p>';
    $back_url = admin_url('admin.php?page=sc-members');
    wp_die(
        '<div style="max-width:640px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,.06);font-family:tahoma,arial,sans-serif;direction:rtl;text-align:right;">'
        . '<h2 style="margin:0 0 8px;color:#b91c1c;font-size:20px;">دسترسی مجاز نیست</h2>'
        . '<p style="margin:0;color:#111827;line-height:1.9;">به‌خاطر نقش <strong>منشی</strong>، به این بخش/بازیکن دسترسی ندارید.</p>'
        . $action_html
        . $detail_html
        . '<p style="margin:18px 0 0;"><a class="button button-primary" href="' . esc_url($back_url) . '">بازگشت به لیست بازیکنان شعبه</a></p>'
        . '</div>',
        $title,
        ['response' => 403]
    );
}

/** محدودیت دسترسی به صفحات مشاهده/ویرایش بازیکن برای منشی */
add_action('load-sc-members_page_sc-add-member', 'sc_secretary_guard_member_edit', 1);
add_action('load-admin_page_sc-add-member', 'sc_secretary_guard_member_edit', 1);
add_action('load-sc-members_page_sc-view-member', 'sc_secretary_guard_member_view', 1);
add_action('load-admin_page_sc-view-member', 'sc_secretary_guard_member_view', 1);
add_action('admin_init', 'sc_secretary_guard_member_pages_on_admin_init', 5);

function sc_secretary_guard_member_pages_on_admin_init() {
    if (!sc_user_is_secretary_only() || empty($_GET['page'])) {
        return;
    }
    $page = sanitize_text_field(wp_unslash((string) $_GET['page']));
    if ($page === 'sc-view-member') {
        sc_secretary_guard_member_view();
        return;
    }
    if ($page === 'sc-add-member') {
        sc_secretary_guard_member_edit();
    }
}

function sc_secretary_guard_member_view() {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
    if ($player_id < 1) {
        sc_secretary_die_access_denied('مشاهده بازیکن', 'برای مشاهده باید از لیست بازیکنان شعبه خودتان یک بازیکن را انتخاب کنید.');
    }
    if (!sc_secretary_can_access_member($player_id)) {
        sc_secretary_die_access_denied(
            'مشاهده بازیکن',
            'این بازیکن در شعبه(های) شما ثبت‌نام فعال ندارد؛ منشی فقط بازیکنان شعبه خودش را می‌تواند ببیند.'
        );
    }
}

function sc_secretary_guard_member_edit() {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    $player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
    if ($player_id < 1) {
        sc_secretary_die_access_denied(
            'افزودن/ویرایش بازیکن',
            'منشی اجازه ایجاد بازیکن جدید از این صفحه را ندارد. برای ویرایش، از لیست بازیکنان شعبه خودتان روی «ویرایش» کلیک کنید.'
        );
    }
    if (!sc_secretary_can_access_member($player_id)) {
        sc_secretary_die_access_denied(
            'ویرایش بازیکن',
            'این بازیکن متعلق به شعبه(های) شما نیست؛ منشی فقط بازیکنان شعبه خودش را می‌تواند ویرایش کند.'
        );
    }
}

add_action('admin_menu', 'sc_secretary_trim_course_event_menus', 1000);
function sc_secretary_trim_course_event_menus() {
    if (!sc_user_is_secretary_only()) {
        return;
    }
    remove_submenu_page('sc-courses', 'sc-add-course');
    remove_submenu_page('sc-events', 'sc-add-event');
}
