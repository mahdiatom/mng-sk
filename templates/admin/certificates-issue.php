<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$events_table = $wpdb->prefix . 'sc_events';
$team_table = $wpdb->prefix . 'sc_team_categories';
$level_table = $wpdb->prefix . 'sc_level_categories';

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name, first_name");
$courses = function_exists('sc_audience_get_courses_for_picker')
    ? sc_audience_get_courses_for_picker(0)
    : $wpdb->get_results("SELECT id, title, course_type, chapter AS chapter_name FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");
$templates = sc_certificates_get_saved_templates();
?>

<div class="wrap sc-cert-issue-page-header sc-notification-add-wrap sc-users-export-wrap sc-cert-wrap">
    <h1 class="wp-heading-inline sc-notification-add-title">صدور گواهینامه</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-list')); ?>" class="page-title-action">گواهینامه‌ها</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-templates')); ?>" class="page-title-action">تعریف قالب</a>
    <hr class="wp-header-end">
    <p class="sc-cert-issue-subtitle">فیلتر کاربران را انتخاب کنید و گواهینامه را برای همه افراد فیلترشده صادر کنید.</p>
</div>

<div class="wrap sc-cert-issue-page-body sc-notification-add-wrap sc-users-export-wrap sc-cert-wrap">
    <?php if (isset($_GET['issued'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $issued = absint($_GET['issued']);
                $sms_sent = isset($_GET['sms_sent']) ? absint($_GET['sms_sent']) : 0;
                echo esc_html($issued . ' گواهینامه صادر شد. تعداد پیامک موفق: ' . $sms_sent);
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sc-users-export-form" class="sc-cert-issue-form sc-notification-form">
        <?php wp_nonce_field('sc_issue_certificates_action', 'sc_issue_certificates_nonce'); ?>
        <input type="hidden" name="action" value="sc_issue_certificates">

        <div class="sc-notification-add-panel sc-cert-template-panel postbox">
            <div class="postbox-header">
                <h2>۱) انتخاب قالب گواهینامه</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-cert-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sc-cert-template-key">قالب</label></th>
                        <td>
                            <select name="template_key" id="sc-cert-template-key" class="sc-notification-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($templates as $template) : ?>
                                    <option value="<?php echo esc_attr($template['key']); ?>"><?php echo esc_html($template['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">اگر قالبی ندارید ابتدا از منوی «تعریف قالب گواهینامه» ایجاد کنید.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sc-notification-filter-panel sc-cert-filter-panel postbox">
            <div class="postbox-header">
                <h2>۲) فیلتر کاربران</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-cert-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sc-target-type">نوع انتخاب</label></th>
                        <td>
                            <select name="target_type" id="sc-target-type" class="sc-notification-select">
                                <option value="all">همه کاربران فعال</option>
                                <option value="free_users">کاربران آزاد (بدون هیچ دوره)</option>
                                <option value="specific">انتخاب کاربران خاص (جستجو)</option>
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
                    <tr id="sc-filter-specific" class="sc-cert-target-row" style="display:none;">
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
                    <tr id="sc-filter-course" class="sc-cert-target-row" style="display:none;">
                        <th scope="row">دوره‌ها</th>
                        <td>
                            <?php
                            echo function_exists('sc_render_audience_course_picker')
                                ? sc_render_audience_course_picker((array) $courses, [], [
                                    'id' => 'sc-cert-course-picker',
                                    'name' => 'course_ids[]',
                                ])
                                : '';
                            ?>
                        </td>
                    </tr>
                    <tr id="sc-filter-event" class="sc-cert-target-row" style="display:none;">
                        <th scope="row"><label for="sc-event-ids">رویدادها</label></th>
                        <td>
                            <select name="event_ids[]" id="sc-event-ids" class="sc-notification-select" multiple size="7">
                                <?php foreach ($events as $event) : ?>
                                    <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-filter-team" class="sc-cert-target-row" style="display:none;">
                        <th scope="row"><label for="sc-team-names">تیم‌ها</label></th>
                        <td>
                            <select name="team_names[]" id="sc-team-names" class="sc-notification-select" multiple size="7">
                                <?php foreach ($teams as $team) : ?>
                                    <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-filter-level" class="sc-cert-target-row" style="display:none;">
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
                            <p class="description">اختیاری — کاربرانی که نباید در خروجی باشند را انتخاب کنید.</p>
                            <div id="sc-excluded-members-count" class="sc-selected-count">0 کاربر از خروجی حذف شده</div>
                            <div id="sc-excluded-members" class="sc-selected-tags sc-notification-recipient-tags"></div>
                            <div class="sc-users-member-dropdown" id="sc-exclude-member-dropdown">
                                <div class="sc-users-dropdown-toggle">
                                    <span class="sc-users-dropdown-placeholder">جستجو کاربر برای حذف از خروجی...</span>
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

                <input type="hidden" id="sc-cert-preview-nonce" value="<?php echo esc_attr(wp_create_nonce('sc_certificates_preview_members')); ?>">
                <p class="submit sc-notification-preview-submit-wrap sc-cert-preview-submit">
                    <button type="button" class="button button-secondary" id="sc-cert-preview-btn">پیش‌نمایش کاربران فیلترشده</button>
                </p>
            </div>
        </div>

        <div class="sc-notification-preview-panel sc-cert-preview-panel postbox">
            <div class="postbox-header">
                <h2>۳) پیش‌نمایش کاربران</h2>
            </div>
            <div class="inside">
                <div id="sc-cert-preview-result" class="sc-bulk-preview-result back_table_list">
                    <p class="description">بعد از انتخاب فیلتر، روی «پیش‌نمایش کاربران فیلترشده» کلیک کنید.</p>
                </div>
                <?php
                if (function_exists('sc_audience_render_preview_add_members_block')) {
                    sc_audience_render_preview_add_members_block(
                        function_exists('sc_audience_get_members_for_preview_add_picker') ? sc_audience_get_members_for_preview_add_picker() : $members,
                        [
                            'wrap_id' => 'sc-cert-preview-add-wrap',
                            'dropdown_id' => 'sc-cert-preview-add-dropdown',
                            'options_id' => 'sc-cert-preview-add-options',
                            'hidden_inputs_id' => 'sc-cert-preview-add-inputs',
                            'mode' => 'cert',
                        ]
                    );
                }
                ?>
            </div>
        </div>

        <p class="submit sc-notification-add-submit sc-cert-submit">
            <button class="button button-primary" type="submit">تایید و ارسال گواهینامه</button>
        </p>
    </form>
</div>
