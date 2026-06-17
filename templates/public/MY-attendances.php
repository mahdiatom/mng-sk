<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$attendances_table = $wpdb->prefix . 'sc_attendances';
$members_table     = $wpdb->prefix . 'sc_members';
$courses_table     = $wpdb->prefix . 'sc_courses';
$schedule_table    = $wpdb->prefix . 'sc_course_weekly_schedule';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$coaches_table     = $wpdb->prefix . 'sc_coaches';

$current_user_id = get_current_user_id();
if (!$current_user_id) {
    echo '<div class="sc-invoices-empty">لطفاً وارد حساب کاربری خود شوید.</div>';
    return;
}

$member_id = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d AND is_active = 1",
        $current_user_id
    )
);

if ($member_id <= 0) {
    echo '<div class="sc-invoices-empty">پروفایل کاربری شما یافت نشد.</div>';
    return;
}

$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$active_enrollment_courses = function_exists('sc_get_member_active_enrollment_courses')
    ? sc_get_member_active_enrollment_courses($member_id)
    : [];
$allowed_course_ids = array_map(static function ($course) {
    return (int) $course->id;
}, $active_enrollment_courses);
if ($filter_course > 0 && !in_array($filter_course, $allowed_course_ids, true)) {
    $filter_course = 0;
}
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
$date_filter_requested = array_key_exists('filter_date_from_shamsi', $_GET)
    || array_key_exists('filter_date_to_shamsi', $_GET)
    || array_key_exists('filter_date_from', $_GET)
    || array_key_exists('filter_date_to', $_GET);

$filter_date_from = '';
$filter_date_to = '';

if (isset($_GET['filter_date_from']) && $_GET['filter_date_from'] !== '') {
    $filter_date_from = sanitize_text_field(wp_unslash($_GET['filter_date_from']));
}
if (isset($_GET['filter_date_to']) && $_GET['filter_date_to'] !== '') {
    $filter_date_to = sanitize_text_field(wp_unslash($_GET['filter_date_to']));
}

if ($filter_date_from === '' && $filter_date_from_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if ($filter_date_to === '' && $filter_date_to_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}

if ($filter_date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
    $filter_date_from = '';
}
if ($filter_date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
    $filter_date_to = '';
}

$display_from_shamsi = $filter_date_from_shamsi;
$display_to_shamsi = $filter_date_to_shamsi;

if (!$date_filter_requested) {
    $today_gregorian = current_time('Y-m-d');
    $one_year_ago_gregorian = date('Y-m-d', strtotime($today_gregorian . ' -1 year'));

    if (function_exists('sc_date_shamsi_date_only')) {
        $display_to_shamsi = sc_date_shamsi_date_only($today_gregorian);
        $display_from_shamsi = sc_date_shamsi_date_only($one_year_ago_gregorian);
    }

    $filter_date_from = $one_year_ago_gregorian;
    $filter_date_to = $today_gregorian;
} else {
    if ($display_from_shamsi === '' && $filter_date_from !== '' && function_exists('sc_date_shamsi_date_only')) {
        $display_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
    }
    if ($display_to_shamsi === '' && $filter_date_to !== '' && function_exists('sc_date_shamsi_date_only')) {
        $display_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
    }
}

if ($filter_date_from !== '' && $filter_date_to !== '' && $filter_date_from > $filter_date_to) {
    $tmp = $filter_date_from;
    $filter_date_from = $filter_date_to;
    $filter_date_to = $tmp;
    $tmp = $display_from_shamsi;
    $display_from_shamsi = $display_to_shamsi;
    $display_to_shamsi = $tmp;
}

$has_active_filters = $date_filter_requested || $filter_course > 0;

$where_conditions = ['a.member_id = %d'];
$where_values     = [$member_id];

if ($filter_course > 0) {
    $where_conditions[] = 'a.course_id = %d';
    $where_values[]     = $filter_course;
}
if ($filter_date_from !== '') {
    $where_conditions[] = 'a.attendance_date >= %s';
    $where_values[]     = $filter_date_from;
}
if ($filter_date_to !== '') {
    $where_conditions[] = 'a.attendance_date <= %s';
    $where_values[]     = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);
$schedule_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $schedule_table)) === $schedule_table;

$query = "
    SELECT
        a.id,
        a.attendance_date,
        a.status,
        a.schedule_slot_id,
        a.course_id,
        c.title AS course_title,
        s.chapter_name AS slot_chapter,
        s.coach_id AS slot_coach_id,
        sch_coach.first_name AS slot_coach_first_name,
        sch_coach.last_name AS slot_coach_last_name,
        s.time_start AS slot_time_start,
        s.time_end AS slot_time_end
    FROM $attendances_table a
    INNER JOIN $courses_table c ON c.id = a.course_id
";
if ($schedule_exists) {
    $query .= "
    LEFT JOIN $schedule_table s ON s.id = a.schedule_slot_id AND a.schedule_slot_id > 0
    LEFT JOIN $coaches_table sch_coach ON sch_coach.id = s.coach_id AND s.coach_id > 0
    ";
}
$query .= "
    WHERE $where_clause
    ORDER BY a.attendance_date DESC, a.id DESC
";

$attendances = $wpdb->get_results($wpdb->prepare($query, $where_values));

$mc_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT mc.course_id, mc.chapter, mc.coach_id,
            ch.first_name AS coach_first_name, ch.last_name AS coach_last_name
     FROM $member_courses_table mc
     LEFT JOIN $coaches_table ch ON ch.id = mc.coach_id
     WHERE mc.member_id = %d AND mc.status IN ('active', 'inactive')
     ORDER BY mc.id DESC",
    $member_id
));

$member_course_map = [];
foreach ($mc_rows as $mc_row) {
    $cid = (int) $mc_row->course_id;
    if (!isset($member_course_map[$cid])) {
        $member_course_map[$cid] = [];
    }
    $member_course_map[$cid][] = $mc_row;
}

$courses = $active_enrollment_courses;

$attendance_items = [];
$stats = ['present' => 0, 'absent' => 0, 'excused' => 0];

foreach ((array) $attendances as $row) {
    $chapter = trim((string) ($row->slot_chapter ?? ''));
    $coach_name = trim((string) ($row->slot_coach_first_name ?? '') . ' ' . (string) ($row->slot_coach_last_name ?? ''));

    if (($chapter === '' || $coach_name === '') && !empty($member_course_map[(int) $row->course_id])) {
        $mc_match = null;
        foreach ($member_course_map[(int) $row->course_id] as $mc_candidate) {
            if ($chapter !== '' && trim((string) $mc_candidate->chapter) !== $chapter) {
                continue;
            }
            $mc_match = $mc_candidate;
            break;
        }
        if (!$mc_match) {
            $mc_match = $member_course_map[(int) $row->course_id][0];
        }
        if ($chapter === '' && !empty($mc_match->chapter)) {
            $chapter = trim((string) $mc_match->chapter);
        }
        if ($coach_name === '') {
            $coach_name = trim((string) ($mc_match->coach_first_name ?? '') . ' ' . (string) ($mc_match->coach_last_name ?? ''));
            if ($coach_name === '' && !empty($mc_match->coach_id) && function_exists('sc_get_coach_display_name')) {
                $coach_name = sc_get_coach_display_name((int) $mc_match->coach_id);
            }
        }
    }

    $status = (string) $row->status;
    if (isset($stats[$status])) {
        $stats[$status]++;
    }

    $attendance_items[] = (object) [
        'id' => (int) $row->id,
        'attendance_date' => $row->attendance_date,
        'status' => $status,
        'course_title' => (string) $row->course_title,
        'chapter' => $chapter,
        'coach_name' => $coach_name,
        'time_start' => $row->slot_time_start ?? '',
        'time_end' => $row->slot_time_end ?? '',
    ];
}

$attendance_endpoint = function_exists('wc_get_account_endpoint_url')
    ? wc_get_account_endpoint_url('sc-my-attendances')
    : remove_query_arg(['filter_course', 'filter_date_from', 'filter_date_to', 'filter_date_from_shamsi', 'filter_date_to_shamsi']);

function sc_my_attendance_status_display($status) {
    switch ($status) {
        case 'present':
            return ['label' => 'حاضر', 'class' => 'present', 'bg' => '#d4edda', 'color' => '#155724', 'icon' => '✓'];
        case 'excused':
            return ['label' => 'غیبت مجاز', 'class' => 'excused', 'bg' => '#e5f5fa', 'color' => '#2271b1', 'icon' => '📋'];
        case 'absent':
        default:
            return ['label' => 'غایب', 'class' => 'absent', 'bg' => '#ffeaea', 'color' => '#d63638', 'icon' => '✗'];
    }
}
?>

<div class="sc-my-attendances-page sc-account-list-page wrap_attendace_user">
    <div class="sc-invoices-page-header sc-my-attendances-header">
        <div class="sc-invoices-page-icon">📋</div>
        <div>
            <h2 class="sc-invoices-page-title">گزارش حضور و غیاب من</h2>
            <p class="sc-invoices-page-subtitle">لیست جلسات ثبت‌شده با وضعیت حضور، شعبه و مربی</p>
        </div>
    </div>

    <div class="sc-invoices-filters sc-my-attendances-filters">
        <form method="GET" class="sc-invoices-filter-form sc-my-attendances-filter-form">
            <div class="sc-invoices-filter-field">
                <label for="filter_course">دوره</label>
                <select name="filter_course" id="filter_course" class="sc-invoices-filter-control sc_attendamce_select">
                    <option value="0">همه دوره‌ها</option>
                    <?php foreach ($courses as $course) : ?>
                        <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, (int) $course->id); ?>>
                            <?php echo esc_html($course->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-invoices-filter-field sc-invoices-filter-field-dates">
                <label>بازه تاریخ</label>
                <div class="sc-invoices-date-range">
                    <input type="text" name="filter_date_from_shamsi" id="sc_att_filter_date_from_shamsi" class="persian-date-input sc-no-default-date sc-invoices-filter-control sc_attendamce_select" placeholder="از تاریخ" value="<?php echo esc_attr($display_from_shamsi); ?>" readonly autocomplete="off">
                    <span class="sc-invoices-date-sep">تا</span>
                    <input type="text" name="filter_date_to_shamsi" id="sc_att_filter_date_to_shamsi" class="persian-date-input sc-no-default-date sc-invoices-filter-control sc_attendamce_select" placeholder="تا تاریخ" value="<?php echo esc_attr($display_to_shamsi); ?>" readonly autocomplete="off">
                </div>
                <input type="hidden" name="filter_date_from" id="sc_att_filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                <input type="hidden" name="filter_date_to" id="sc_att_filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            </div>

            <div class="sc-invoices-filter-actions">
                <button type="submit" class="button button-primary sc-invoices-filter-submit">اعمال فیلتر</button>
                <a href="<?php echo esc_url($attendance_endpoint); ?>" class="button sc-invoices-filter-reset">پاک کردن</a>
            </div>
        </form>
    </div>

    <?php if (!empty($attendance_items)) : ?>
        <div class="sc-my-attendances-stats">
            <div class="sc-my-attendances-stat sc-my-attendances-stat--present">
                <span class="sc-my-attendances-stat-value"><?php echo esc_html((string) $stats['present']); ?></span>
                <span class="sc-my-attendances-stat-label">حاضر</span>
            </div>
            <div class="sc-my-attendances-stat sc-my-attendances-stat--absent">
                <span class="sc-my-attendances-stat-value"><?php echo esc_html((string) $stats['absent']); ?></span>
                <span class="sc-my-attendances-stat-label">غایب</span>
            </div>
            <div class="sc-my-attendances-stat sc-my-attendances-stat--excused">
                <span class="sc-my-attendances-stat-value"><?php echo esc_html((string) $stats['excused']); ?></span>
                <span class="sc-my-attendances-stat-label">غیبت مجاز</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($attendance_items)) : ?>
        <div class="sc-invoices-empty">
            <?php if ($has_active_filters) : ?>
                موردی با این فیلترها یافت نشد.
            <?php else : ?>
                هیچ اطلاعاتی برای نمایش وجود ندارد.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="sc-invoices-list sc-my-attendances-list">
            <?php foreach ($attendance_items as $item) :
                $status = sc_my_attendance_status_display($item->status);
                $time_label = '';
                if ($item->time_start && $item->time_end) {
                    $time_label = substr((string) $item->time_start, 0, 5) . ' تا ' . substr((string) $item->time_end, 0, 5);
                }
            ?>
                <article class="sc-account-card sc-my-attendance-card sc-my-attendance-card--<?php echo esc_attr($status['class']); ?>">
                    <div class="sc-my-attendance-card-head">
                        <div class="sc-my-attendance-card-date">
                            <span class="sc-my-attendance-date-icon">📅</span>
                            <div>
                                <span class="sc-invoice-card-number-label">تاریخ جلسه</span>
                                <strong><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($item->attendance_date) : $item->attendance_date); ?></strong>
                                <?php if ($time_label !== '') : ?>
                                    <span class="sc-invoice-card-number-label sc-my-attendance-time-label">ساعت جلسه</span>
                                    <span class="sc-my-attendance-card-time"><?php echo esc_html($time_label); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="sc-invoice-status-badge sc-my-attendance-status" style="background-color: <?php echo esc_attr($status['bg']); ?>; color: <?php echo esc_attr($status['color']); ?>;">
                            <span class="sc-invoice-status-icon"><?php echo esc_html($status['icon']); ?></span>
                            <?php echo esc_html($status['label']); ?>
                        </span>
                    </div>

                    <div class="sc-account-card-summary sc-my-attendance-card-body">
                        <div class="sc-my-attendance-card-main">
                            <div class="sc-invoice-card-item-head">
                                <span class="sc-invoice-card-item-icon">📚</span>
                                <span class="sc-invoice-card-item-type">دوره</span>
                            </div>
                            <h3 class="sc-invoice-card-item-title"><?php echo esc_html($item->course_title); ?></h3>

                            <?php if ($item->chapter !== '' || $item->coach_name !== '') : ?>
                                <div class="sc-account-card-chips">
                                    <?php if ($item->chapter !== '') : ?>
                                        <span class="sc-account-card-chip">شعبه: <?php echo esc_html($item->chapter); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item->coach_name !== '') : ?>
                                        <span class="sc-account-card-chip">مربی: <?php echo esc_html($item->coach_name); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <div class="sc-my-attendance-no-meta">شعبه یا مربی برای این جلسه ثبت نشده</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function scAttJalaliToGregorian(jy, jm, jd) {
        var gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;
        var days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        gy += 400 * Math.floor(days / 146097);
        days %= 146097;
        if (days > 36524) {
            gy += 100 * Math.floor(--days / 36524);
            days %= 36524;
            if (days >= 365) {
                days++;
            }
        }
        gy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) {
            gy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var gd = days + 1;
        var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > sal_a[gm]) {
            gd -= sal_a[gm];
            gm++;
        }
        return [gy, gm, gd];
    }

    function scAttShamsiToGregorianInput(shamsiDate) {
        if (!shamsiDate) {
            return '';
        }
        var normalized = String(shamsiDate).trim().replace(/-/g, '/');
        var parts = normalized.split('/');
        if (parts.length !== 3) {
            return '';
        }
        var jy = parseInt(parts[0], 10);
        var jm = parseInt(parts[1], 10);
        var jd = parseInt(parts[2], 10);
        if (isNaN(jy) || isNaN(jm) || isNaN(jd)) {
            return '';
        }
        var gregorian = scAttJalaliToGregorian(jy, jm, jd);
        return gregorian[0] + '-' +
            (gregorian[1] < 10 ? '0' + gregorian[1] : String(gregorian[1])) + '-' +
            (gregorian[2] < 10 ? '0' + gregorian[2] : String(gregorian[2]));
    }

    function scAttSyncAttendanceDateFilters() {
        var fromInput = document.getElementById('sc_att_filter_date_from_shamsi');
        var toInput = document.getElementById('sc_att_filter_date_to_shamsi');
        var fromHidden = document.getElementById('sc_att_filter_date_from');
        var toHidden = document.getElementById('sc_att_filter_date_to');
        if (fromInput && fromHidden) {
            fromHidden.value = scAttShamsiToGregorianInput(fromInput.value);
        }
        if (toInput && toHidden) {
            toHidden.value = scAttShamsiToGregorianInput(toInput.value);
        }
    }

    var attendanceFilterForm = document.querySelector('.sc-my-attendances-filter-form');
    if (attendanceFilterForm) {
        attendanceFilterForm.addEventListener('submit', scAttSyncAttendanceDateFilters);
    }
    ['sc_att_filter_date_from_shamsi', 'sc_att_filter_date_to_shamsi'].forEach(function (inputId) {
        var input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('change', scAttSyncAttendanceDateFilters);
        }
    });
    scAttSyncAttendanceDateFilters();

    var interval = setInterval(function() {
        var el = document.querySelector('.sc-my-attendances-header');
        if (el) {
            var y = window.scrollY || document.documentElement.scrollTop || 0;
            if (y < 120) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            clearInterval(interval);
        }
    }, 100);
});
</script>
