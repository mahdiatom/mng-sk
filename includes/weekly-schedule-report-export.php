<?php
/**
 * خروجی PDF گزارش برنامه هفتگی
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', static function () {
    if (empty($_GET['page']) || sanitize_text_field(wp_unslash((string) $_GET['page'])) !== 'sc-reports-weekly-schedule') {
        return;
    }
    if (empty($_GET['sc_export_weekly_schedule_pdf'])) {
        return;
    }
    if (!current_user_can('sc_finance_reports_access') && !current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_export_weekly_schedule_pdf');
    if (function_exists('sc_export_weekly_schedule_report_to_pdf')) {
        sc_export_weekly_schedule_report_to_pdf(sc_weekly_schedule_report_parse_filters($_GET));
    }
});

/**
 * @param array{filter_course?:int,filter_chapter?:string,filter_coach?:int,filter_group?:string} $filters
 */
function sc_export_weekly_schedule_report_to_pdf(array $filters) {
    global $wpdb;

    if (!function_exists('sc_get_admin_weekly_schedule_report')) {
        wp_die('ماژول برنامه هفتگی در دسترس نیست.');
    }

    $report = sc_get_admin_weekly_schedule_report($filters);
    $sections = isset($report['sections']) && is_array($report['sections']) ? $report['sections'] : [];
    $days = isset($report['days']) && is_array($report['days']) ? $report['days'] : sc_course_weekday_labels_ir();

    $courses_table = $wpdb->prefix . 'sc_courses';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $filter_labels = [];
    if (!empty($filters['filter_course'])) {
        $course_title = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM {$courses_table} WHERE id = %d AND deleted_at IS NULL",
            (int) $filters['filter_course']
        ));
        $filter_labels[] = 'دوره: ' . ($course_title ? (string) $course_title : '#' . (int) $filters['filter_course']);
    }
    if (!empty($filters['filter_chapter'])) {
        $filter_labels[] = 'شعبه: ' . $filters['filter_chapter'];
    }
    if (!empty($filters['filter_coach'])) {
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name FROM {$coaches_table} WHERE id = %d",
            (int) $filters['filter_coach']
        ));
        $coach_name = $coach ? trim((string) $coach->first_name . ' ' . (string) $coach->last_name) : ('#' . (int) $filters['filter_coach']);
        $filter_labels[] = 'مربی: ' . $coach_name;
    }
    if (!empty($filters['filter_group'])) {
        $group_label = ($filters['filter_group'] === '__none__') ? 'بدون گروه' : $filters['filter_group'];
        $filter_labels[] = 'گروه: ' . $group_label;
    }
    if (empty($filter_labels)) {
        $filter_labels[] = 'همه دوره‌ها، شعبه‌ها، مربی‌ها و گروه‌ها';
    }

    if (function_exists('sc_users_export_discard_output_buffers')) {
        sc_users_export_discard_output_buffers();
    } else {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $title = 'گزارش برنامه هفتگی';
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($title); ?></title>
        <link rel="stylesheet" href="<?php echo esc_url(SC_ASSETS_URL . 'css/weekly-schedule-report-print.css'); ?>">
    </head>
    <body class="sc-weekly-schedule-print-page">
        <div class="sc-weekly-schedule-print-header">
            <div class="sc-weekly-schedule-print-title-wrap">
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html(implode(' | ', $filter_labels)); ?></p>
                <p class="sc-weekly-schedule-print-meta"><?php echo esc_html(count($sections) . ' دوره'); ?></p>
            </div>
            <div class="sc-weekly-schedule-print-actions">
                <button type="button" onclick="window.print()">چاپ / PDF</button>
            </div>
        </div>

        <?php if (empty($sections)) : ?>
            <div class="sc-weekly-schedule-print-empty">برنامه هفتگی‌ای برای فیلترهای انتخاب‌شده یافت نشد.</div>
        <?php else : ?>
            <?php foreach ($sections as $section) : ?>
                <section class="sc-weekly-schedule-print-section">
                    <h2><?php echo esc_html($section['course_title'] ?? ''); ?></h2>
                    <?php
                    $ws_days = $days;
                    $ws_cells = isset($section['cells']) && is_array($section['cells']) ? $section['cells'] : [];
                    $ws_show_meta = true;
                    include SC_TEMPLATES_ADMIN_DIR . 'partials/weekly-schedule-print-list.php';
                    ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}
