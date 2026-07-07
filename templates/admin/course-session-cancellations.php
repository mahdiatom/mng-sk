<?php
/**
 * لغو بازهٔ زمانی جلسه — در این بازه لاگ دستگاه به حضور تبدیل نمی‌شود و غیبت خودکار برای همان اسلات زده نمی‌شود.
 */
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$table = $wpdb->prefix . 'sc_course_session_cancellations';
$courses_table = $wpdb->prefix . 'sc_courses';
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
$coaches_table = $wpdb->prefix . 'sc_coaches';

if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
    echo '<div class="wrap"><div class="notice notice-error"><p>جدول تعطیلی جلسه هنوز ایجاد نشده. یک بار از داشبورد عبور کنید.</p></div></div>';
    return;
}

$current_user_id = get_current_user_id();
$is_coach_only = current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach');
$coach_id = 0;
if ($is_coach_only) {
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM `{$coaches_table}` WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    $coach_id = $coach ? (int) $coach->id : 0;
}

$coach_can_course = static function ($cid) use ($wpdb, $course_coaches_table, $is_coach_only, $coach_id) {
    $cid = absint($cid);
    if (!$cid) {
        return false;
    }
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
        && function_exists('sc_secretary_can_access_attendance_course')) {
        return sc_secretary_can_access_attendance_course($cid);
    }
    if (!$is_coach_only) {
        return true;
    }
    if (!$coach_id) {
        return false;
    }
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$course_coaches_table}` WHERE coach_id = %d AND course_id = %d",
        $coach_id,
        $cid
    ));
    return $n > 0;
};

$today_shamsi_display = function_exists('sc_date_shamsi_date_only')
    ? sc_date_shamsi_date_only(current_time('Y-m-d'))
    : '';

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && isset($_GET['_wpnonce'])) {
    $del_id = absint($_GET['id']);
    if ($del_id && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sc_del_session_cancel_' . $del_id)) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT course_id FROM `{$table}` WHERE id = %d LIMIT 1", $del_id));
        if ($row && $coach_can_course((int) $row->course_id)) {
            $wpdb->delete($table, ['id' => $del_id], ['%d']);
            echo '<div class="notice notice-success is-dismissible"><p>رکورد حذف شد.</p></div>';
        } elseif ($row) {
            echo '<div class="notice notice-error is-dismissible"><p>مجوز حذف این رکورد را ندارید.</p></div>';
        }
    }
}

if (isset($_POST['sc_save_session_cancel']) && check_admin_referer('sc_session_cancel_nonce', 'sc_session_cancel_nonce')) {
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $ds = isset($_POST['session_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['session_date_shamsi'])) : '';
    $session_date = ($ds && function_exists('sc_shamsi_to_gregorian_date')) ? sc_shamsi_to_gregorian_date($ds) : '';
    $t1 = isset($_POST['time_start']) ? sanitize_text_field(wp_unslash($_POST['time_start'])) : '';
    $t2 = isset($_POST['time_end']) ? sanitize_text_field(wp_unslash($_POST['time_end'])) : '';
    $reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

    $ts = function_exists('sc_normalize_time_his') ? sc_normalize_time_his($t1) : null;
    $te = function_exists('sc_normalize_time_his') ? sc_normalize_time_his($t2) : null;

    if (!$coach_can_course($course_id)) {
        echo '<div class="notice notice-error is-dismissible"><p>مجوز ثبت برای این دوره را ندارید.</p></div>';
    } elseif (!$course_id || !$session_date || !$ts || !$te || strcmp($ts, $te) >= 0) {
        echo '<div class="notice notice-error is-dismissible"><p>دوره، تاریخ شمسی معتبر و بازهٔ ساعت را کامل وارد کنید (پایان باید بعد از شروع باشد).</p></div>';
    } else {
        $wpdb->insert(
            $table,
            [
                'course_id' => $course_id,
                'session_date' => $session_date,
                'time_start' => $ts,
                'time_end' => $te,
                'reason' => $reason !== '' ? $reason : null,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        echo '<div class="notice notice-success is-dismissible"><p>ثبت شد. در این بازهٔ تاریخ و ساعت، حضور/غیبت خودکار برای این دوره اجرا نمی‌شود.</p></div>';
    }
}

if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
    && function_exists('sc_secretary_get_branch_courses_for_attendance_filter')) {
    $courses = sc_secretary_get_branch_courses_for_attendance_filter();
    $ids = array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($courses, 'id')))));
    if (empty($ids)) {
        $rows = [];
    } else {
        $ids_in = implode(',', $ids);
        $rows = $wpdb->get_results(
            "SELECT x.*, c.title AS course_title FROM `{$table}` x
             INNER JOIN `{$courses_table}` c ON c.id = x.course_id
             WHERE x.course_id IN ({$ids_in})
             ORDER BY x.session_date DESC, x.time_start DESC, x.id DESC LIMIT 200"
        );
    }
} elseif ($is_coach_only) {
    $courses = $coach_id ? $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.title FROM `{$courses_table}` c
         INNER JOIN `{$course_coaches_table}` cc ON cc.course_id = c.id
         WHERE cc.coach_id = %d AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00') AND c.is_active = 1
         ORDER BY c.title ASC",
        $coach_id
    )) : [];
    if (!$coach_id || empty($courses)) {
        $rows = [];
    } else {
        $ids = array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($courses, 'id')))));
        $ids_in = implode(',', $ids);
        $rows = $wpdb->get_results(
            "SELECT x.*, c.title AS course_title FROM `{$table}` x
             INNER JOIN `{$courses_table}` c ON c.id = x.course_id
             WHERE x.course_id IN ({$ids_in})
             ORDER BY x.session_date DESC, x.time_start DESC, x.id DESC LIMIT 200"
        );
    }
} else {
    $courses = $wpdb->get_results(
        "SELECT id, title FROM `{$courses_table}` WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
    );
    $rows = $wpdb->get_results(
        "SELECT x.*, c.title AS course_title FROM `{$table}` x
         INNER JOIN `{$courses_table}` c ON c.id = x.course_id
         ORDER BY x.session_date DESC, x.time_start DESC, x.id DESC LIMIT 200"
    );
}

?>
<div class="wrap sc-att-cancel-wrap">
    <div class="sc-att-list-header-inner">
        <div class="sc-att-list-header-text">
            <h1 class="sc-att-list-title">تعطیلی بازهٔ جلسه</h1>
            <p class="sc-att-list-desc">با ثبت تاریخ و زمان، دستگاه در تایم مشخص‌شده کار نمی‌کند (مناسب وقتی جلسه لغو شده و نمی‌خواهید غیبت خودکار لحاظ شود).</p>
        </div>
        <div class="sc-att-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-list')); ?>" class="sc-att-btn-secondary">لیست حضور و غیاب</a>
        </div>
    </div>

    <div class="sc-att-cancel-layout">
        <div class="sc-att-cancel-form-card">
            <h2 class="sc-att-card-title">افزودن رکورد جدید</h2>
            <form method="post" class="sc-att-cancel-form">
                <?php wp_nonce_field('sc_session_cancel_nonce', 'sc_session_cancel_nonce'); ?>
                <div class="sc-att-report-field">
                    <label class="sc-att-report-label" for="course_id">دوره</label>
                    <select name="course_id" id="course_id" class="sc-att-report-input" required>
                        <option value="">— انتخاب —</option>
                        <?php foreach ($courses as $c) : ?>
                            <option value="<?php echo esc_attr((string) $c->id); ?>"><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-att-report-field">
                    <label class="sc-att-report-label" for="session_date_shamsi">تاریخ جلسه (شمسی)</label>
                    <input type="text" name="session_date_shamsi" id="session_date_shamsi" class="sc-att-report-input persian-date-input" value="<?php echo esc_attr($today_shamsi_display); ?>" placeholder="1403/09/15" required readonly>
                </div>
                <div class="sc-att-cancel-time-row">
                    <div class="sc-att-report-field">
                        <label class="sc-att-report-label" for="time_start">از ساعت</label>
                        <input type="time" name="time_start" id="time_start" class="sc-att-report-input" required>
                    </div>
                    <div class="sc-att-report-field">
                        <label class="sc-att-report-label" for="time_end">تا ساعت</label>
                        <input type="time" name="time_end" id="time_end" class="sc-att-report-input" required>
                    </div>
                </div>
                <div class="sc-att-report-field">
                    <label class="sc-att-report-label" for="reason">دلیل (اختیاری)</label>
                    <input type="text" name="reason" id="reason" class="sc-att-report-input" maxlength="255">
                </div>
                <button type="submit" name="sc_save_session_cancel" class="sc-att-btn-primary sc-att-report-submit">ذخیره</button>
            </form>
        </div>

        <div class="sc-att-cancel-list-card">
            <h2 class="sc-att-card-title">لیست تعطیلی‌ها</h2>
            <div class="sc-att-table-scroll">
                <table class="wp-list-table widefat striped sc-att-table">
                    <thead>
                        <tr>
                            <th>دوره</th>
                            <th>تاریخ (شمسی)</th>
                            <th>از</th>
                            <th>تا</th>
                            <th>دلیل</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr><td colspan="6" class="sc-att-empty">رکوردی نیست.</td></tr>
                        <?php else : ?>
                            <?php foreach ($rows as $r) :
                                $sh = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($r->session_date) : $r->session_date;
                                $del_url = wp_nonce_url(
                                    add_query_arg(['action' => 'delete', 'id' => $r->id], admin_url('admin.php?page=sc-attendance-session-cancellations')),
                                    'sc_del_session_cancel_' . $r->id
                                );
                                ?>
                                <tr>
                                    <td data-label="دوره"><strong><?php echo esc_html($r->course_title); ?></strong></td>
                                    <td data-label="تاریخ"><?php echo esc_html($sh); ?></td>
                                    <td data-label="از"><?php echo esc_html(substr((string) $r->time_start, 0, 5)); ?></td>
                                    <td data-label="تا"><?php echo esc_html(substr((string) $r->time_end, 0, 5)); ?></td>
                                    <td data-label="دلیل"><?php echo esc_html($r->reason ?? '—'); ?></td>
                                    <td data-label="عملیات"><a href="<?php echo esc_url($del_url); ?>" class="sc-att-delete-link" onclick="return scConfirmInline(event, { type: 'warning', message: 'حذف شود؟' });">حذف</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
