<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$attendances_table = $wpdb->prefix . 'sc_attendances';
$members_table     = $wpdb->prefix . 'sc_members';
$courses_table     = $wpdb->prefix . 'sc_courses';

/* =========================
   1. دریافت کاربر لاگین‌شده
========================= */
$current_user_id = get_current_user_id();

if (!$current_user_id) {
    echo '<div class="notice notice-error"><p>لطفاً وارد حساب کاربری خود شوید.</p></div>';
    return;
}

/* =========================
   2. دریافت member_id
========================= */
$member_id = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d AND is_active = 1",
        $current_user_id
    )
);

if (!$member_id) {
    echo '<div class="notice notice-error"><p>پروفایل کاربری شما یافت نشد.</p></div>';
    return;
}

/* =========================
   3. فیلترها
========================= */
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;

// مقدار پیش‌فرض
$filter_date_from = '';
$filter_date_to   = '';

// اگر کاربر تاریخ انتخاب کرده
if (!empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from = sc_shamsi_to_gregorian_date(
        sanitize_text_field($_GET['filter_date_from_shamsi'])
    );
}

if (!empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to = sc_shamsi_to_gregorian_date(
        sanitize_text_field($_GET['filter_date_to_shamsi'])
    );
}

// اگر هیچ تاریخی انتخاب نشده → امروز
if (!$filter_date_from && !$filter_date_to) {

    $today_gregorian = current_time('Y-m-d');

    $today = new DateTime($today_gregorian);
    $jalali = gregorian_to_jalali(
        (int)$today->format('Y'),
        (int)$today->format('m'),
        (int)$today->format('d')
    );

    $today_shamsi =
        $jalali[0] . '/' .
        str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
        str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

    $filter_date_from = $today_gregorian;
    $filter_date_to   = $today_gregorian;

    $_GET['filter_date_from_shamsi'] = $today_shamsi;
    $_GET['filter_date_to_shamsi']   = $today_shamsi;
}

/* =========================
   4. WHERE clause
========================= */
$where_conditions = [];
$where_values     = [];

// فقط همین کاربر
$where_conditions[] = "a.member_id = %d";
$where_values[]     = $member_id;

// فیلتر دوره
if ($filter_course > 0) {
    $where_conditions[] = "a.course_id = %d";
    $where_values[]     = $filter_course;
}

// فیلتر تاریخ
if ($filter_date_from) {
    $where_conditions[] = "a.attendance_date >= %s";
    $where_values[]     = $filter_date_from;
}

if ($filter_date_to) {
    $where_conditions[] = "a.attendance_date <= %s";
    $where_values[]     = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

/* =========================
   5. دریافت داده‌ها
========================= */
$query = "
    SELECT 
        a.attendance_date,
        a.status
    FROM $attendances_table a
    WHERE $where_clause
    ORDER BY a.attendance_date ASC
";

$attendances = $wpdb->get_results(
    $wpdb->prepare($query, $where_values)
);

/* =========================
   6. آماده‌سازی داده جدول
========================= */
$dates_list   = [];
$attendance_map = [];

foreach ($attendances as $row) {
    $attendance_map[$row->attendance_date] = $row->status;
    $dates_list[] = $row->attendance_date;
}

$dates_list = array_unique($dates_list);
sort($dates_list);

/* =========================
   7. دریافت دوره‌ها
========================= */
$member_courses_table = $wpdb->prefix . 'sc_member_courses';

$courses = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT DISTINCT c.id, c.title
        FROM $courses_table c
        INNER JOIN $member_courses_table mc 
            ON mc.course_id = c.id
        WHERE mc.member_id = %d
          AND mc.status = 'active'
          AND c.is_active = 1
          AND c.deleted_at IS NULL
        ORDER BY c.title ASC
        ",
        $member_id
    )
);

?>

<div class="wrap">

    <h2>گزارش حضور و غیاب من</h2>

    <!-- فیلترها -->
    <form method="GET" style="margin:20px 0; background:#fff; padding:15px; border:1px solid #ddd; border-radius:6px;">
        
        <!-- دوره -->
        <p>
            <label>دوره:</label><br>
            <select name="filter_course">
                <option value="0">همه دوره‌ها</option>
                <?php foreach ($courses as $course) : ?>
                    <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                        <?php echo esc_html($course->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <!-- تاریخ -->
        <p>
            <label>بازه تاریخ:</label><br>
            <input type="text"
                   name="filter_date_from_shamsi"
                   class="persian-date-input"
                   placeholder="از تاریخ"
                   value="<?php echo esc_attr($_GET['filter_date_from_shamsi'] ?? ''); ?>"
                   readonly>

            <span> تا </span>

            <input type="text"
                   name="filter_date_to_shamsi"
                   class="persian-date-input"
                   placeholder="تا تاریخ"
                   value="<?php echo esc_attr($_GET['filter_date_to_shamsi'] ?? ''); ?>"
                   readonly>
        </p>

        <p>
            <button type="submit" class="button button-primary">اعمال فیلتر</button>
            <a href="<?php echo esc_url(remove_query_arg(['filter_course','filter_date_from_shamsi','filter_date_to_shamsi'])); ?>" class="button">
                پاک کردن
            </a>
        </p>
    </form>

    <!-- جدول -->
    <?php if (empty($dates_list)) : ?>

        <div class="notice notice-info">
            <p>هیچ اطلاعاتی برای نمایش وجود ندارد.</p>
        </div>

    <?php else : ?>

        <div style="overflow-x:auto; background:#fff; padding:15px; border:1px solid #ddd; border-radius:6px;">
 
        <table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th style="text-align:center; width:50%">تاریخ</th>
            <th style="text-align:center; width:50%">وضعیت</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dates_list as $date) : ?>
            <tr>
                <!-- تاریخ -->
                <td style="text-align:center; font-weight:bold;">
                    <?php echo esc_html(sc_date_shamsi_date_only($date)); ?>
                </td>

                <!-- وضعیت -->
                <td style="text-align:center; font-size:20px;">
                    <?php
                    if (isset($attendance_map[$date])) {
                        echo $attendance_map[$date] === 'present'
                            ? '<span style="color:#00a32a;">✓</span>'
                            : '<span style="color:#d63638;">✗</span>';
                    } else {
                        echo '<span style="color:#999;">-</span>';
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

        </div>

    <?php endif; ?>

</div>
