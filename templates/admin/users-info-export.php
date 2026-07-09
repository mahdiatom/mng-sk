<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
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
if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
    if (function_exists('sc_secretary_get_branch_members_for_picker')) {
        $members = sc_secretary_get_branch_members_for_picker();
    }
    if (function_exists('sc_secretary_get_branch_courses_for_attendance')) {
        $courses = sc_secretary_get_branch_courses_for_attendance();
    }
}
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");

$templates = sc_users_export_get_saved_templates();
$field_labels = sc_users_export_get_field_labels();
$can_pvc_export = function_exists('sc_user_can_users_export_pvc') && sc_user_can_users_export_pvc();
?>

<div class="wrap sc-users-export-page-header sc-notification-add-wrap sc-users-export-wrap sc-cert-wrap">
    <h1 class="wp-heading-inline sc-notification-add-title">خروجی اطلاعات کاربران</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-users-export-templates')); ?>" class="page-title-action">تعریف قالب خروجی</a>
    <hr class="wp-header-end">
    <p class="sc-users-export-subtitle">فیلتر کاربران و فیلدهای خروجی را انتخاب کنید. در صورت انتخاب فیلد تصویری، خروجی PDF یا ZIP تصاویر کارت در دسترس است.</p>
</div>

<div class="wrap sc-users-export-page-body sc-notification-add-wrap sc-users-export-wrap sc-cert-wrap">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sc-users-export-form" class="sc-users-export-form sc-notification-form">
        <?php wp_nonce_field('sc_users_info_export_action', 'sc_users_info_export_nonce'); ?>
        <input type="hidden" name="action" value="sc_users_info_export">

        <div class="sc-notification-add-panel sc-users-export-template-panel postbox">
            <div class="postbox-header">
                <h2>۱) انتخاب قالب (اختیاری)</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-users-export-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sc-template-key">قالب خروجی</label></th>
                        <td>
                            <select name="template_key" id="sc-template-key" class="sc-notification-select">
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ویرایش فیلدها</th>
                        <td>
                            <label class="sc-inline-check">
                                <input type="checkbox" name="override_template_fields" id="sc-override-template-fields" value="1">
                                ویرایش دستی فیلدها (به‌جای فیلدهای قالب)
                            </label>
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
                    <tr id="sc-filter-specific" class="sc-users-export-target-row" style="display:none;">
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
                                    'id' => 'sc-users-export-course-picker',
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
                    <p class="description">بعد از انتخاب فیلتر، روی «پیش‌نمایش کاربران فیلترشده» کلیک کنید.</p>
                </div>
                <?php
                if (function_exists('sc_audience_render_preview_add_members_block')) {
                    sc_audience_render_preview_add_members_block(
                        function_exists('sc_audience_get_members_for_preview_add_picker') ? sc_audience_get_members_for_preview_add_picker() : $members,
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

        <div class="sc-notification-add-panel sc-users-export-fields-panel postbox">
            <div class="postbox-header">
                <h2>۴) فیلدهای خروجی</h2>
            </div>
            <div class="inside">
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
        </div>

        <div class="sc-notification-add-panel sc-users-export-output-panel postbox">
            <div class="postbox-header">
                <h2>۵) خروجی</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-users-export-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sc-export-format">فرمت خروجی</label></th>
                        <td>
                            <select name="export_format" id="sc-export-format" class="sc-notification-select">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="cards_zip">ZIP تصاویر کارت‌ها (PNG)</option>
                            </select>
                            <p class="description">در حالت ZIP، هر کارت به‌صورت یک فایل PNG جداگانه درون فایل فشرده قرار می‌گیرد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc-page-size">اندازه صفحه</label></th>
                        <td>
                            <select name="page_size" id="sc-page-size" class="sc-notification-select">
                                <option value="A4">A4</option>
                                <option value="A5">A5</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc-cards-per-page">تعداد کارت در صفحه</label></th>
                        <td>
                            <select name="cards_per_page" id="sc-cards-per-page" class="sc-notification-select">
                                <option value="1">1</option>
                                <option value="2" selected>2</option>
                                <option value="4">4</option>
                            </select>
                        </td>
                    </tr>
                    <?php if ($can_pvc_export) : ?>
                    <tr class="sc-pvc-export-row">
                        <th scope="row">خروجی کارت PVC</th>
                        <td>
                            <label class="sc-inline-check">
                                <input type="checkbox" name="pvc_export" id="sc-pvc-export" value="1">
                                حالت کارت PVC (فایل ZIP شامل اکسل + پوشه تصاویر)
                            </label>
                            <p class="description">فقط برای مدیر کل — فیلدهای متنی در اکسل و تصاویر انتخاب‌شده در پوشه <code>images</code> قرار می‌گیرند.</p>
                            <div id="sc-pvc-options" class="sc-pvc-options" style="display:none;">
                                <label for="sc-pvc-image-name-field">نام‌گذاری فایل عکس بر اساس</label>
                                <select name="pvc_image_name_field" id="sc-pvc-image-name-field" class="sc-notification-select"></select>
                                <p class="description">اگر «نام و نام خانوادگی» انتخاب شود: <code>نام خانوادگی + شماره همراه</code> — اگر «شماره همراه» انتخاب شود: فقط شماره (مثلاً <code>09038412995</code>).</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <div class="sc-template-preview" id="sc-template-preview">
                    <div class="sc-template-preview-title">پیش‌نمایش چیدمان</div>
                    <div class="sc-template-preview-meta" id="sc-template-preview-meta">A4 - 2 کارت در صفحه</div>
                    <div class="sc-template-preview-grid" id="sc-template-preview-grid"></div>
                </div>
            </div>
        </div>

        <script type="application/json" id="sc-export-field-labels-data"><?php echo wp_json_encode($field_labels, JSON_UNESCAPED_UNICODE); ?></script>
        <p class="submit sc-notification-add-submit sc-users-export-submit sc-cert-submit">
            <button class="button button-primary" type="submit">ایجاد خروجی</button>
        </p>
    </form>
</div>
