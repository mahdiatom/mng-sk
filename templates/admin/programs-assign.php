<?php
if (!defined('ABSPATH')) {
    exit;
}
$is_coach = !empty($GLOBALS['sc_programs_is_coach']);
$GLOBALS['sc_programs_is_coach'] = $is_coach;

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$events_table = $wpdb->prefix . 'sc_events';
$team_table = $wpdb->prefix . 'sc_team_categories';
$level_table = $wpdb->prefix . 'sc_level_categories';

$ctx = sc_program_staff_context();
if ($ctx['is_coach'] && function_exists('sc_private_notes_get_member_ids_for_coach')) {
    $allowed = sc_private_notes_get_member_ids_for_coach($ctx['coach_id']);
    if (!empty($allowed)) {
        $ph = implode(',', array_fill(0, count($allowed), '%d'));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id FROM $members_table WHERE id IN ($ph) ORDER BY last_name, first_name",
            ...$allowed
        ));
    } else {
        $members = [];
    }
} elseif ($ctx['is_secretary'] && function_exists('sc_secretary_get_branch_members_for_picker')) {
    $members = sc_secretary_get_branch_members_for_picker();
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name, first_name");
}

$preview_add_members = function_exists('sc_audience_get_members_for_preview_add_picker')
    ? sc_audience_get_members_for_preview_add_picker()
    : $members;

$courses = function_exists('sc_audience_get_courses_for_picker')
    ? sc_audience_get_courses_for_picker(0)
    : $wpdb->get_results("SELECT id, title, course_type, chapter AS chapter_name FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");
$templates = sc_program_template_query(['status' => 'active', 'per_page' => 200]);
$today_ymd = sc_program_today_ymd();
$end_ymd = gmdate('Y-m-d', strtotime($today_ymd . ' +13 days'));
$today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_ymd) : $today_ymd;
$end_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($end_ymd) : $end_ymd;
$list_page = $is_coach ? 'sc-coach-programs' : 'sc-programs';
?>
<div class="wrap sc-users-export-page-header sc-notification-add-wrap sc-users-export-wrap sc-programs-wrap sc-prog-pro">
    <h1 class="wp-heading-inline">اختصاص برنامه تخصصی</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page)); ?>" class="page-title-action">لیست برنامه‌ها</a>
    <p class="sc-users-export-subtitle">قالب و تاریخ را تنظیم کنید، کاربران را فیلتر کنید، پیش‌نمایش بگیرید و سپس برنامه را اختصاص دهید — دقیقاً مثل خروجی اطلاعات کاربران.</p>
</div>

<div class="wrap sc-users-export-page-body sc-notification-add-wrap sc-users-export-wrap sc-programs-wrap sc-prog-pro">
    <?php if (!empty($_GET['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sc-users-export-form" class="sc-users-export-form sc-notification-form sc-prog-assign-form">
        <?php wp_nonce_field('sc_issue_specialized_programs', 'sc_issue_specialized_programs_nonce'); ?>
        <input type="hidden" name="action" value="sc_issue_specialized_programs">
        <input type="hidden" name="sc_programs_is_coach" value="<?php echo $is_coach ? '1' : '0'; ?>">

        <div class="sc-notification-add-panel sc-users-export-template-panel postbox">
            <div class="postbox-header">
                <h2>۱) قالب و تاریخ برنامه</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-users-export-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="template_id">قالب برنامه</label></th>
                        <td>
                            <select name="template_id" id="template_id" class="sc-notification-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($templates['rows'] as $tpl) :
                                    $mode = $tpl->schedule_mode ?? 'fixed';
                                    $mode_label = ['fixed' => 'بازه ثابت', 'manual' => 'دستی', 'weekly' => 'هفتگی'][$mode] ?? $mode;
                                    ?>
                                    <option value="<?php echo (int) $tpl->id; ?>">
                                        <?php echo esc_html($tpl->title . ' — ' . $mode_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc-prog-start-shamsi">تاریخ شروع (شمسی)</label></th>
                        <td>
                            <input type="text" id="sc-prog-start-shamsi" name="start_date_shamsi" class="persian-date-input sc-notification-input regular-text" readonly value="<?php echo esc_attr($today_shamsi); ?>">
                            <input type="hidden" name="start_date" value="<?php echo esc_attr($today_ymd); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc-prog-end-shamsi">تاریخ پایان (شمسی)</label></th>
                        <td>
                            <input type="text" id="sc-prog-end-shamsi" name="end_date_shamsi" class="persian-date-input sc-notification-input regular-text" readonly value="<?php echo esc_attr($end_shamsi); ?>">
                            <input type="hidden" name="end_date" value="<?php echo esc_attr($end_ymd); ?>">
                            <p class="description">برای حالت هفتگی پیشنهاد می‌شود؛ در حالت ثابت معمولاً از تعداد روز قالب استفاده می‌شود.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sc-notification-filter-panel sc-users-export-filter-panel postbox">
            <div class="postbox-header">
                <h2>۲) فیلتر کاربران</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-users-export-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sc-target-type">نوع انتخاب</label></th>
                        <td>
                            <select name="target_type" id="sc-target-type" class="sc-notification-select">
                                <option value="all">همه کاربران فعال</option>
                                <option value="free_users">کاربران آزاد (بدون هیچ دوره)</option>
                                <option value="specific" selected>انتخاب کاربران خاص (جستجو)</option>
                                <option value="course">بر اساس دوره</option>
                                <option value="event">بر اساس رویداد</option>
                                <option value="team">بر اساس تیم</option>
                                <option value="level">بر اساس سطح</option>
                                <option value="team_level">بر اساس تیم + سطح</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc-member-type">دسته‌بندی بازیکن</label></th>
                        <td>
                            <select name="member_type" id="sc-member-type" class="sc-notification-select">
                                <option value="all">همه</option>
                                <option value="normal">بازیکن عادی</option>
                                <option value="team">بازیکن تیم</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-filter-specific" class="sc-users-export-target-row">
                        <th scope="row">انتخاب کاربران</th>
                        <td>
                            <div id="sc-selected-members-count" class="sc-selected-count">0 کاربر انتخاب شده</div>
                            <div id="sc-selected-members" class="sc-selected-tags sc-notification-recipient-tags"></div>
                            <div class="sc-users-member-dropdown" id="sc-users-member-dropdown">
                                <div class="sc-users-dropdown-toggle">
                                    <span class="sc-users-dropdown-placeholder">جستجو با نام یا کد ملی...</span>
                                    <span class="sc-users-dropdown-arrow">▼</span>
                                </div>
                                <div class="sc-users-dropdown-menu">
                                    <div class="sc-users-dropdown-search">
                                        <input type="text" class="sc-users-search-input" placeholder="جستجوی نام یا کد ملی...">
                                    </div>
                                    <div class="sc-users-dropdown-options" id="sc-member-options">
                                        <?php foreach ($members as $member) :
                                            $name = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                                            $search = strtolower($name . ' ' . ($member->national_id ?: ''));
                                            ?>
                                            <div class="sc-users-dropdown-option"
                                                 data-id="<?php echo (int) $member->id; ?>"
                                                 data-label="<?php echo esc_attr($name . ' - ' . ($member->national_id ?: $member->id)); ?>"
                                                 data-search="<?php echo esc_attr($search); ?>">
                                                <?php echo esc_html($name . ' - ' . ($member->national_id ?: $member->id)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div id="sc-member-hidden-inputs"></div>
                        </td>
                    </tr>
                    <tr id="sc-filter-course" class="sc-users-export-target-row" style="display:none;">
                        <th scope="row">دوره‌ها</th>
                        <td>
                            <?php
                            echo function_exists('sc_render_audience_course_picker')
                                ? sc_render_audience_course_picker((array) $courses, [], [
                                    'id' => 'sc-program-course-picker',
                                    'name' => 'course_ids[]',
                                ])
                                : '';
                            ?>
                        </td>
                    </tr>
                    <tr id="sc-filter-event" class="sc-users-export-target-row" style="display:none;">
                        <th scope="row"><label for="sc-event-ids">رویدادها</label></th>
                        <td>
                            <select name="event_ids[]" id="sc-event-ids" class="sc-notification-select" multiple size="7">
                                <?php foreach ($events as $event) : ?>
                                    <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-filter-team" class="sc-users-export-target-row" style="display:none;">
                        <th scope="row"><label for="sc-team-names">تیم‌ها</label></th>
                        <td>
                            <select name="team_names[]" id="sc-team-names" class="sc-notification-select" multiple size="7">
                                <?php foreach ($teams as $team) : ?>
                                    <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-filter-level" class="sc-users-export-target-row" style="display:none;">
                        <th scope="row"><label for="sc-level-names">سطح‌ها</label></th>
                        <td>
                            <select name="level_names[]" id="sc-level-names" class="sc-notification-select" multiple size="7">
                                <?php foreach ($levels as $level) : ?>
                                    <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-filter-exclude">
                        <th scope="row">استثنا از نتایج فیلتر</th>
                        <td>
                            <p class="description">اختیاری — کاربرانی که نباید برنامه برایشان صادر شود را انتخاب کنید.</p>
                            <div id="sc-excluded-members-count" class="sc-selected-count">0 کاربر از خروجی حذف شده</div>
                            <div id="sc-excluded-members" class="sc-selected-tags sc-notification-recipient-tags"></div>
                            <div class="sc-users-member-dropdown" id="sc-exclude-member-dropdown">
                                <div class="sc-users-dropdown-toggle">
                                    <span class="sc-users-dropdown-placeholder">جستجو کاربر برای حذف از اختصاص...</span>
                                    <span class="sc-users-dropdown-arrow">▼</span>
                                </div>
                                <div class="sc-users-dropdown-menu">
                                    <div class="sc-users-dropdown-search">
                                        <input type="text" class="sc-users-search-input" placeholder="جستجوی نام یا کد ملی...">
                                    </div>
                                    <div class="sc-users-dropdown-options" id="sc-exclude-member-options">
                                        <?php foreach ($members as $member) :
                                            $name = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                                            $search = strtolower($name . ' ' . ($member->national_id ?: ''));
                                            ?>
                                            <div class="sc-users-dropdown-option"
                                                 data-id="<?php echo (int) $member->id; ?>"
                                                 data-label="<?php echo esc_attr($name . ' - ' . ($member->national_id ?: $member->id)); ?>"
                                                 data-search="<?php echo esc_attr($search); ?>">
                                                <?php echo esc_html($name . ' - ' . ($member->national_id ?: $member->id)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div id="sc-excluded-member-hidden-inputs"></div>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <input type="hidden" id="sc-users-preview-nonce" value="<?php echo esc_attr(wp_create_nonce('sc_users_export_preview_members')); ?>">
                <p class="submit sc-notification-preview-submit-wrap sc-users-export-preview-submit">
                    <button type="button" class="button button-secondary" id="sc-users-preview-btn">پیش‌نمایش کاربران فیلترشده</button>
                </p>
            </div>
        </div>

        <div class="sc-notification-preview-panel sc-users-export-preview-panel postbox">
            <div class="postbox-header">
                <h2>۳) پیش‌نمایش کاربران</h2>
            </div>
            <div class="inside">
                <div id="sc-users-preview-result" class="sc-bulk-preview-result back_table_list">
                    <p class="description">بعد از انتخاب فیلتر، روی «پیش‌نمایش کاربران فیلترشده» کلیک کنید. در پیش‌نمایش می‌توانید کاربران را انتخاب/حذف کنید.</p>
                </div>
                <?php
                if (function_exists('sc_audience_render_preview_add_members_block')) {
                    sc_audience_render_preview_add_members_block(
                        $preview_add_members,
                        [
                            'wrap_id' => 'sc-users-preview-add-wrap',
                            'dropdown_id' => 'sc-users-preview-add-dropdown',
                            'options_id' => 'sc-users-preview-add-options',
                            'hidden_inputs_id' => 'sc-users-preview-add-inputs',
                            'mode' => 'users',
                        ]
                    );
                }
                ?>
            </div>
        </div>

        <p class="submit sc-notification-add-submit sc-users-export-submit">
            <button type="submit" class="button button-primary sc_button sc_button--primary">اختصاص برنامه تخصصی</button>
        </p>
    </form>
</div>
