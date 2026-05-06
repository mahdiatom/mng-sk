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

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table ORDER BY last_name, first_name");
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL ORDER BY title");
$coaches_table = $wpdb->prefix . 'sc_coaches';
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");
$course_flags = sc_bulk_actions_get_course_flag_options();

$course_coaches = $wpdb->get_results(
    "SELECT cc.course_id, cc.coach_id, c.first_name, c.last_name
     FROM $course_coaches_table cc
     INNER JOIN $coaches_table c ON c.id = cc.coach_id
     WHERE c.is_active = 1
     ORDER BY cc.course_id ASC, c.last_name ASC, c.first_name ASC"
);
$course_coaches_map = array();
foreach ($course_coaches as $row) {
    $cid = (int) $row->course_id;
    if (!isset($course_coaches_map[$cid])) {
        $course_coaches_map[$cid] = array();
    }
    $label = trim((string) $row->first_name . ' ' . (string) $row->last_name);
    if ($label === '') {
        $label = 'مربی #' . (int) $row->coach_id;
    }
    $course_coaches_map[$cid][] = array(
        'id' => (int) $row->coach_id,
        'label' => $label,
    );
}
?>

<div class="wrap sc-users-export-wrap sc-bulk-actions-wrap">
    <h1>کار های دست جمعی</h1>
    <p class="description">کاربران را فیلتر کنید، پیش نمایش بگیرید و عملیات گروهی را با تایید نهایی اجرا کنید.</p>

    <?php if (isset($_GET['sc_bulk_notice']) && $_GET['sc_bulk_notice'] === 'done') : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                عملیات با موفقیت اجرا شد.
                (<?php echo esc_html((string) absint($_GET['affected'] ?? 0)); ?> مورد تغییر از
                <?php echo esc_html((string) absint($_GET['total'] ?? 0)); ?> کاربر فیلتر شده)
            </p>
        </div>
    <?php elseif (isset($_GET['sc_bulk_notice']) && $_GET['sc_bulk_notice'] === 'empty') : ?>
        <div class="notice notice-warning is-dismissible">
            <p>هیچ کاربری با فیلتر فعلی پیدا نشد.</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sc-bulk-actions-form" data-course-coaches="<?php echo esc_attr(wp_json_encode($course_coaches_map)); ?>">
        <?php wp_nonce_field('sc_bulk_actions_execute_action', 'sc_bulk_actions_execute_nonce'); ?>
        <input type="hidden" name="action" value="sc_bulk_actions_execute">

        <div class="sc-users-export-card">
            <h2>۱) فیلتر کاربران</h2>
            <div class="sc-row">
                <label for="sc-target-type">نوع انتخاب</label>
                <select name="target_type" id="sc-target-type">
                    <option value="all">همه کاربران</option>
                    <option value="specific">انتخاب کاربران خاص (جستجو)</option>
                    <option value="course">بر اساس دوره</option>
                    <option value="event">بر اساس رویداد</option>
                    <option value="team">بر اساس تیم</option>
                    <option value="level">بر اساس سطح</option>
                    <option value="team_level">بر اساس تیم + سطح</option>
                </select>
            </div>

            <div class="sc-row">
                <label for="sc-member-status">وضعیت کاربر</label>
                <select name="member_status" id="sc-member-status">
                    <option value="all">همه</option>
                    <option value="active">فقط فعال</option>
                    <option value="inactive">فقط غیرفعال</option>
                </select>
            </div>

            <div class="sc-row">
                <label for="sc-member-type">دسته بندی بازیکن</label>
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
                <label for="sc-course-ids">دوره ها</label>
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
                <label for="sc-team-names">تیم ها</label>
                <select name="team_names[]" id="sc-team-names" multiple size="7">
                    <?php foreach ($teams as $team) : ?>
                        <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-filter-level">
                <label for="sc-level-names">سطح ها</label>
                <select name="level_names[]" id="sc-level-names" multiple size="7">
                    <?php foreach ($levels as $level) : ?>
                        <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="submit">
                <button type="button" class="button button-secondary" id="sc-bulk-preview-btn">پیش نمایش کاربران فیلتر شده</button>
            </p>
        </div>

        <div class="sc-users-export-card">
            <h2>۲) پیش نمایش کاربران</h2>
            <div id="sc-bulk-preview-result" class="sc-bulk-preview-result">
                <p class="description">بعد از انتخاب فیلتر، روی «پیش نمایش کاربران فیلتر شده» کلیک کنید.</p>
            </div>
        </div>

        <div class="sc-users-export-card">
            <h2>۳) انتخاب عملیات گروهی</h2>
            <div class="sc-row">
                <label for="sc-bulk-action-type">نوع عملیات</label>
                <select name="bulk_action_type" id="sc-bulk-action-type" required>
                    <option value="">انتخاب کنید</option>
                    <option value="activate_members">فعال کردن کاربر</option>
                    <option value="deactivate_members">غیرفعال کردن کاربر</option>
                    <option value="verify_identity">تایید احراز هویت</option>
                    <option value="send_sms_redirect">ارسال پیامک (رفتن به اطلاعیه)</option>
                    <option value="enable_auto_invoice">فعال کردن صورت حساب خودکار</option>
                    <option value="disable_auto_invoice">غیرفعال کردن صورت حساب خودکار</option>
                    <option value="change_team">تغییر تیم</option>
                    <option value="change_level">تغییر سطح</option>
                    <option value="change_member_type">تغییر نوع بازیکن</option>
                    <option value="course_activate">فعال کردن دوره</option>
                    <option value="course_deactivate">غیرفعال کردن دوره</option>
                    <option value="course_set_flag">افزودن فلگ دوره</option>
                    <option value="assign_course_coach">اختصاص بازیکن های انتخاب شده به مربی دوره</option>
                    <option value="delete_members">حذف بازیکن ها</option>
                </select>
            </div>

            <div id="sc-action-change-team" class="sc-action-extra">
                <div class="sc-row">
                    <label for="sc-action-team-player">تیم جدید</label>
                    <select name="action_team_player" id="sc-action-team-player">
                        <option value="">انتخاب تیم</option>
                        <?php foreach ($teams as $team) : ?>
                            <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="sc-action-change-level" class="sc-action-extra">
                <div class="sc-row">
                    <label for="sc-action-skill-level">سطح جدید</label>
                    <select name="action_skill_level" id="sc-action-skill-level">
                        <option value="">انتخاب سطح</option>
                        <?php foreach ($levels as $level) : ?>
                            <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="sc-action-change-type" class="sc-action-extra">
                <div class="sc-row">
                    <label for="sc-action-member-type">نوع بازیکن</label>
                    <select name="action_member_type" id="sc-action-member-type">
                        <option value="normal">بازیکن عادی</option>
                        <option value="team">بازیکن تیم</option>
                    </select>
                </div>
            </div>

            <div id="sc-action-course-common" class="sc-action-extra">
                <div class="sc-row">
                    <label for="sc-action-course-ids">دوره های هدف</label>
                    <select name="action_course_ids[]" id="sc-action-course-ids" multiple size="7">
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="sc-action-course-flag" class="sc-action-extra">
                <div class="sc-row">
                    <label for="sc-action-course-flag-select">فلگ دوره</label>
                    <select name="action_course_flag" id="sc-action-course-flag-select">
                        <option value="">انتخاب فلگ</option>
                        <?php foreach ($course_flags as $flag_key => $flag_label) : ?>
                            <option value="<?php echo esc_attr($flag_key); ?>"><?php echo esc_html($flag_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="description">فلگ انتخابی به فلگ های قبلی اضافه می شود و مقادیر قبلی حذف نمی شوند.</p>
            </div>

            <div id="sc-action-assign-course-coach" class="sc-action-extra">
                <div class="sc-row">
                    <label for="sc-assign-course-id">دوره</label>
                    <select name="assign_course_id" id="sc-assign-course-id">
                        <option value="">انتخاب دوره</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-row">
                    <label for="sc-assign-coach-id">مربی دوره</label>
                    <select name="assign_coach_id" id="sc-assign-coach-id">
                        <option value="">ابتدا دوره را انتخاب کنید</option>
                    </select>
                </div>
                <p class="description">فقط بازیکن‌هایی که در همین دوره ثبت‌نام دارند به مربی انتخاب‌شده منتسب می‌شوند.</p>
            </div>
        </div>

        <div class="sc-users-export-card">
            <h2>۴) تایید و اجرا</h2>
            <p class="description">قبل از اجرا، پیش نمایش را بررسی کنید. عملیات حذف با تایید نهایی انجام می شود.</p>
            <p class="submit">
                <button class="button button-primary" type="submit" id="sc-bulk-submit-btn">اجرای عملیات</button>
            </p>
        </div>
    </form>
</div>
