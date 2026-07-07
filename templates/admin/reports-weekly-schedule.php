<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('sc_finance_reports_access') && !current_user_can('manage_options') && !(function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only())) {
    wp_die('دسترسی غیرمجاز.');
}

sc_check_and_create_tables();

global $wpdb;

$courses_table = $wpdb->prefix . 'sc_courses';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$chapters_table = $wpdb->prefix . 'sc_chapter_categories';

$filters = function_exists('sc_weekly_schedule_report_parse_filters')
    ? sc_weekly_schedule_report_parse_filters()
    : [
        'filter_course' => 0,
        'filter_chapter' => '',
        'filter_coach' => 0,
        'filter_group' => '',
    ];

$filter_course = (int) $filters['filter_course'];
$filter_chapter = (string) $filters['filter_chapter'];
$filter_coach = (int) $filters['filter_coach'];
$filter_group = (string) $filters['filter_group'];

$courses = $wpdb->get_results(
    "SELECT id, title FROM {$courses_table} WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);
if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_weekly_schedule_course_ids')) {
    $allowed_course_ids = array_fill_keys(sc_secretary_get_branch_weekly_schedule_course_ids(), true);
    if (empty($allowed_course_ids)) {
        $courses = [];
    } else {
        $courses = array_values(array_filter($courses, static function ($course) use ($allowed_course_ids) {
            return isset($allowed_course_ids[(int) $course->id]);
        }));
    }
}
$chapters = $wpdb->get_results("SELECT name FROM {$chapters_table} ORDER BY name ASC");
if (function_exists('sc_secretary_filter_chapters_list')) {
    $chapters = sc_secretary_filter_chapters_list($chapters);
}
$coaches = $wpdb->get_results(
    "SELECT id, first_name, last_name FROM {$coaches_table} WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);
if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_weekly_schedule_coach_ids')) {
    $allowed_coach_ids = sc_secretary_get_branch_weekly_schedule_coach_ids();
    if (empty($allowed_coach_ids)) {
        $coaches = [];
    } else {
        $holders = implode(',', array_fill(0, count($allowed_coach_ids), '%d'));
        $coaches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name FROM {$coaches_table}
             WHERE is_active = 1 AND id IN ($holders)
             ORDER BY last_name ASC, first_name ASC",
            ...$allowed_coach_ids
        ));
    }
}
$course_groups_map = function_exists('sc_finance_course_groups_map_for_ui')
    ? sc_finance_course_groups_map_for_ui($courses)
    : [];

$report = function_exists('sc_get_admin_weekly_schedule_report')
    ? sc_get_admin_weekly_schedule_report($filters)
    : ['days' => [], 'sections' => []];
$ws_days = isset($report['days']) && is_array($report['days']) ? $report['days'] : [];
$sections = isset($report['sections']) && is_array($report['sections']) ? $report['sections'] : [];

$active_filters_count = function_exists('sc_weekly_schedule_report_active_filters_count')
    ? sc_weekly_schedule_report_active_filters_count($filters)
    : 0;
$filters_open = $active_filters_count > 0;

$pdf_export_url = function_exists('sc_weekly_schedule_report_pdf_export_url')
    ? sc_weekly_schedule_report_pdf_export_url($filters)
    : '';
?>
<div class="wrap sc-reports-list-wrap sc-weekly-schedule-report-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">گزارش برنامه هفتگی</h1>
            <p class="sc-reports-list-desc">برنامه کلاس‌های هفتگی دوره‌ها بر اساس شعبه، مربی و گروه. در حالت پیش‌فرض همه دوره‌ها نمایش داده می‌شوند.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <?php if ($pdf_export_url !== '') : ?>
                <a href="<?php echo esc_url($pdf_export_url); ?>" class="sc-reports-list-export-btn" target="_blank" rel="noopener">خروجی PDF</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-weekly-schedule-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-weekly-schedule-filters-panel">
                <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-weekly-schedule')); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-reports-list-filters-panel" id="sc-weekly-schedule-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-reports-weekly-schedule">
            <div class="sc-filter-grid sc-weekly-schedule-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, (int) $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_chapter">شعبه</label>
                    <select name="filter_chapter" id="filter_chapter" class="sc-filter-control">
                        <option value="">همه شعبه‌ها</option>
                        <?php foreach ($chapters as $chapter_item) :
                            $chapter_name = isset($chapter_item->name) ? (string) $chapter_item->name : '';
                            if ($chapter_name === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr($chapter_name); ?>" <?php selected($filter_chapter, $chapter_name); ?>>
                                <?php echo esc_html($chapter_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_coach">مربی</label>
                    <select name="filter_coach" id="filter_coach" class="sc-filter-control">
                        <option value="0">همه مربی‌ها</option>
                        <?php foreach ($coaches as $coach) : ?>
                            <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach, (int) $coach->id); ?>>
                                <?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field" id="sc-weekly-schedule-group-field"<?php echo ($filter_course > 0 && !empty($course_groups_map[$filter_course])) ? '' : ' hidden'; ?>>
                    <label class="sc-filter-label" for="filter_group">گروه</label>
                    <select name="filter_group" id="filter_group" class="sc-filter-control">
                        <option value="">همه گروه‌ها</option>
                        <option value="__none__" <?php selected($filter_group, '__none__'); ?>>بدون گروه</option>
                        <?php
                        if ($filter_course > 0 && !empty($course_groups_map[$filter_course])) {
                            foreach ($course_groups_map[$filter_course] as $group_name) :
                                ?>
                                <option value="<?php echo esc_attr($group_name); ?>" <?php selected($filter_group, $group_name); ?>>
                                    <?php echo esc_html($group_name); ?>
                                </option>
                            <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="sc-reports-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-weekly-schedule')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                <?php if ($pdf_export_url !== '') : ?>
                    <a href="<?php echo esc_url($pdf_export_url); ?>" class="button button_export" target="_blank" rel="noopener">خروجی PDF</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <p class="sc-reports-note">تعداد دوره: <strong><?php echo number_format(count($sections)); ?></strong></p>

    <div class="sc-reports-list-table-card sc-weekly-schedule-report-results">
        <?php if (empty($sections)) : ?>
            <div class="sc-reports-empty">برنامه هفتگی‌ای یافت نشد.</div>
        <?php else : ?>
            <?php foreach ($sections as $section) : ?>
                <div class="sc-weekly-schedule-report-section">
                    <div class="sc-weekly-schedule-report-section-head">
                        <h2><?php echo esc_html($section['course_title'] ?? ''); ?></h2>
                        <?php if (!empty($section['course_type'])) : ?>
                            <span class="sc-weekly-schedule-report-type">
                                <?php echo esc_html(((string) $section['course_type'] === 'private') ? 'خصوصی/نیمه‌خصوصی' : 'گروهی'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php
                    $ws_cells = isset($section['cells']) && is_array($section['cells']) ? $section['cells'] : [];
                    $ws_show_meta = true;
                    include SC_TEMPLATES_ADMIN_DIR . 'partials/weekly-schedule-matrix.php';
                    ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-weekly-schedule-filters-toggle');
    var $panel = $('#sc-weekly-schedule-filters-panel');
    var $card = $toggle.closest('.sc-reports-list-filters-card');
    var $label = $toggle.find('.sc-reports-list-filters-toggle-label');

    $toggle.on('click', function () {
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

    var weeklyScheduleCourseGroups = <?php echo wp_json_encode($course_groups_map, JSON_UNESCAPED_UNICODE); ?>;
    var selectedGroup = <?php echo wp_json_encode($filter_group, JSON_UNESCAPED_UNICODE); ?>;

    function refreshWeeklyScheduleGroupField() {
        var $field = $('#sc-weekly-schedule-group-field');
        var $sel = $('#filter_group');
        if (!$field.length || !$sel.length) {
            return;
        }
        var courseId = parseInt($('#filter_course').val(), 10) || 0;
        var groups = weeklyScheduleCourseGroups[courseId] || weeklyScheduleCourseGroups[String(courseId)] || [];
        $sel.find('option').not('[value=""], [value="__none__"]').remove();
        if (!courseId || !groups.length) {
            $field.attr('hidden', true);
            $sel.val('');
            return;
        }
        $field.removeAttr('hidden');
        groups.forEach(function (name) {
            $sel.append($('<option></option>').val(name).text(name));
        });
        if (selectedGroup && $sel.find('option[value="' + selectedGroup.replace(/"/g, '\\"') + '"]').length) {
            $sel.val(selectedGroup);
        } else {
            $sel.val('');
        }
    }

    $('#filter_course').on('change', function () {
        selectedGroup = '';
        refreshWeeklyScheduleGroupField();
    });
    refreshWeeklyScheduleGroupField();
});
</script>
