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
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");

$templates = sc_users_export_get_saved_templates();
$field_labels = sc_users_export_get_field_labels();
?>

<div class="wrap sc-users-export-wrap sc-cert-wrap">
    <div class="sc-cert-header">
        <div class="sc-cert-header-text">
            <h1 class="sc-cert-title">خروجی اطلاعات کاربران</h1>
            <p class="sc-cert-desc">فیلتر کاربران و فیلدهای خروجی را انتخاب کنید. در صورت انتخاب عکس پرسنلی، خروجی فقط PDF خواهد بود.</p>
        </div>
        <div class="sc-cert-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-users-export-templates')); ?>" class="sc-cert-btn-secondary">تعریف قالب خروجی</a>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sc-users-export-form">
        <?php wp_nonce_field('sc_users_info_export_action', 'sc_users_info_export_nonce'); ?>
        <input type="hidden" name="action" value="sc_users_info_export">

        <div class="sc-users-export-card sc-cert-card">
            <h2>۱) انتخاب قالب (اختیاری)</h2>
            <select name="template_key" id="sc-template-key">
                <option value="">بدون قالب</option>
                <?php foreach ($templates as $template) : ?>
                    <option
                        value="<?php echo esc_attr($template['key']); ?>"
                        data-template="<?php echo esc_attr(wp_json_encode($template)); ?>"
                    >
                        <?php echo esc_html($template['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="sc-inline-check">
                <input type="checkbox" name="override_template_fields" id="sc-override-template-fields" value="1">
                ویرایش دستی فیلدها (به‌جای فیلدهای قالب)
            </label>
        </div>

        <div class="sc-users-export-card sc-cert-card">
            <h2>۲) فیلتر کاربران</h2>

            <div class="sc-row">
                <label for="sc-target-type">نوع انتخاب</label>
                <select name="target_type" id="sc-target-type">
                    <option value="all">همه کاربران فعال</option>
                    <option value="free_users">کاربران آزاد (بدون هیچ دوره)</option>
                    <option value="specific">انتخاب کاربران خاص (جستجو)</option>
                    <option value="course">بر اساس دوره</option>
                    <option value="event">بر اساس رویداد</option>
                    <option value="team">بر اساس تیم</option>
                    <option value="level">بر اساس سطح</option>
                    <option value="team_level">بر اساس تیم + سطح</option>
                </select>
            </div>

            <div class="sc-row">
                <label for="sc-member-type">دسته‌بندی بازیکن</label>
                <select name="member_type" id="sc-member-type">
                    <option value="all">همه</option>
                    <option value="normal">بازیکن عادی</option>
                    <option value="team">بازیکن تیم</option>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-filter-specific">
                <label>انتخاب کاربران</label>
                <div id="sc-selected-members-count" class="sc-selected-count">0 کاربر انتخاب شده</div>
                <div id="sc-selected-members" class="sc-selected-tags"></div>
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
            </div>

            <div class="sc-filter-block" id="sc-filter-course">
                <label for="sc-course-ids">دوره‌ها</label>
                <select name="course_ids[]" id="sc-course-ids" multiple size="7">
                    <?php foreach ($courses as $course) : ?>
                        <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-filter-event">
                <label for="sc-event-ids">رویدادها</label>
                <select name="event_ids[]" id="sc-event-ids" multiple size="7">
                    <?php foreach ($events as $event) : ?>
                        <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-filter-team">
                <label for="sc-team-names">تیم‌ها</label>
                <select name="team_names[]" id="sc-team-names" multiple size="7">
                    <?php foreach ($teams as $team) : ?>
                        <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-filter-level">
                <label for="sc-level-names">سطح‌ها</label>
                <select name="level_names[]" id="sc-level-names" multiple size="7">
                    <?php foreach ($levels as $level) : ?>
                        <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-filter-exclude">
                <label>استثنا از نتایج فیلتر (اختیاری)</label>
                <div id="sc-excluded-members-count" class="sc-selected-count">0 کاربر از خروجی حذف شده</div>
                <div id="sc-excluded-members" class="sc-selected-tags"></div>
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
            </div>

            <p class="submit">
                <input type="hidden" id="sc-users-preview-nonce" value="<?php echo esc_attr(wp_create_nonce('sc_users_export_preview_members')); ?>">
                <button type="button" class="button button-secondary" id="sc-users-preview-btn">پیش نمایش کاربران فیلتر شده</button>
            </p>
        </div>

        <div class="sc-users-export-card sc-cert-card">
            <h2>۳) پیش نمایش کاربران</h2>
            <div id="sc-users-preview-result" class="sc-bulk-preview-result back_table_list">
                <p class="description">بعد از انتخاب فیلتر، روی «پیش نمایش کاربران فیلتر شده» کلیک کنید.</p>
            </div>
        </div>

        <div class="sc-users-export-card sc-cert-card">
            <h2>۴) فیلدهای خروجی</h2>
            <div class="sc-fields-grid" id="sc-fields-grid">
                <?php foreach ($field_labels as $key => $label) : ?>
                    <label class="sc-inline-check sc-base-field-check">
                        <input type="checkbox" name="fields[]" value="<?php echo esc_attr($key); ?>" <?php checked($key === 'full_name'); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div id="sc-event-fields-grid" class="sc-fields-grid sc-event-fields-grid"></div>
            <input type="hidden" id="sc-event-fields-nonce" value="<?php echo esc_attr(wp_create_nonce('sc_users_export_get_event_fields')); ?>">
        </div>

        <div class="sc-users-export-card sc-cert-card">
            <h2>۵) خروجی</h2>
            <div class="sc-row">
                <label for="sc-export-format">فرمت خروجی</label>
                <select name="export_format" id="sc-export-format">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                </select>
            </div>

            <div class="sc-row">
                <label for="sc-page-size">اندازه صفحه</label>
                <select name="page_size" id="sc-page-size">
                    <option value="A4">A4</option>
                    <option value="A5">A5</option>
                </select>
            </div>

            <div class="sc-row">
                <label for="sc-cards-per-page">تعداد کارت در صفحه</label>
                <select name="cards_per_page" id="sc-cards-per-page">
                    <option value="1">1</option>
                    <option value="2" selected>2</option>
                    <option value="4">4</option>
                </select>
            </div>

            <div class="sc-template-preview" id="sc-template-preview">
                <div class="sc-template-preview-title">پیش‌نمایش چیدمان</div>
                <div class="sc-template-preview-meta" id="sc-template-preview-meta">A4 - 2 کارت در صفحه</div>
                <div class="sc-template-preview-grid" id="sc-template-preview-grid"></div>
            </div>
        </div>

        <p class="submit sc-cert-submit">
            <button class="button button-primary" type="submit">ایجاد خروجی</button>
        </p>
    </form>
</div>
