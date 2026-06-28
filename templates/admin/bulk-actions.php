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
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");
$course_flags = sc_bulk_actions_get_course_flag_options();

$course_coaches_map = function_exists('sc_get_bulk_course_branch_coaches_map')
    ? sc_get_bulk_course_branch_coaches_map()
    : array();
?>

<div class="wrap sc-bulk-actions-page-header sc-users-export-wrap sc-bulk-actions-wrap">
    <h1 class="wp-heading-inline">کارهای دسته‌جمعی</h1>
    <hr class="wp-header-end">
    <p class="sc-bulk-actions-subtitle">کاربران را فیلتر کنید، پیش‌نمایش بگیرید و عملیات گروهی را با تأیید نهایی اجرا کنید.</p>
</div>
<div class="wrap sc-bulk-actions-page-body sc-users-export-wrap sc-bulk-actions-wrap">

    <?php
    $sc_bulk_report_data = null;
    if (isset($_GET['sc_bulk_notice'], $_GET['sc_bulk_report']) && $_GET['sc_bulk_notice'] === 'report') {
        $rk = sanitize_text_field(wp_unslash($_GET['sc_bulk_report']));
        if ($rk !== '') {
            $sc_bulk_report_data = get_transient($rk);
            if ($sc_bulk_report_data !== false) {
                delete_transient($rk);
            } else {
                $sc_bulk_report_data = null;
            }
        }
    }
    ?>
    <?php if (isset($_GET['sc_bulk_notice']) && $_GET['sc_bulk_notice'] === 'report' && is_array($sc_bulk_report_data)) : ?>
        <div class="notice notice-info is-dismissible sc-bulk-report-notice">
            <p>
                <strong>گزارش عملیات دسته‌جمعی</strong>
                <?php if (!empty($sc_bulk_report_data['action_title'])) : ?>
                    — <?php echo esc_html((string) $sc_bulk_report_data['action_title']); ?>
                <?php endif; ?>
            </p>
            <p class="description">
                موفق: <?php echo esc_html((string) (int) ($sc_bulk_report_data['ok_count'] ?? 0)); ?> —
                ناموفق: <?php echo esc_html((string) (int) ($sc_bulk_report_data['fail_count'] ?? 0)); ?> —
                کاربران در فیلتر: <?php echo esc_html((string) absint($_GET['total'] ?? 0)); ?>
            </p>
            <?php if (!empty($sc_bulk_report_data['successes'])) : ?>
                <div class="sc-bulk-report-block sc-bulk-report-success">
                    <strong>موفق:</strong>
                    <ul class="sc-bulk-report-list">
                        <?php foreach ($sc_bulk_report_data['successes'] as $item) :
                            $line = is_array($item) && isset($item['line']) ? $item['line'] : (string) $item;
                            ?>
                            <li><?php echo esc_html($line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($sc_bulk_report_data['failures'])) : ?>
                <div class="sc-bulk-report-block sc-bulk-report-fail">
                    <strong>ناموفق یا رد شده:</strong>
                    <ul class="sc-bulk-report-list">
                        <?php foreach ($sc_bulk_report_data['failures'] as $item) :
                            $line = is_array($item) && isset($item['line']) ? $item['line'] : (string) $item;
                            ?>
                            <li><?php echo esc_html($line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (isset($_GET['sc_bulk_notice']) && $_GET['sc_bulk_notice'] === 'done') : ?>
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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sc-bulk-actions-form" class="sc-bulk-actions-form" data-course-coaches="<?php echo esc_attr(wp_json_encode($course_coaches_map)); ?>">
        <?php wp_nonce_field('sc_bulk_actions_execute_action', 'sc_bulk_actions_execute_nonce'); ?>
        <input type="hidden" name="action" value="sc_bulk_actions_execute">
        <div id="sc-bulk-excluded-members-inputs"></div>

        <div class="sc-bulk-panel sc-bulk-panel--filter sc-users-export-card postbox">
            <div class="postbox-header">
                <h2>۱) فیلتر کاربران</h2>
            </div>
            <div class="inside sc-bulk-panel-fields">
            <div class="sc-row sc-bulk-field-row">
                <label for="sc-target-type">نوع انتخاب</label>
                <select name="target_type" id="sc-target-type">
                    <option value="all">همه کاربران</option>
                    <option value="free_users">کاربران آزاد (بدون هیچ دوره تا امروز)</option>
                    <option value="specific">انتخاب کاربران خاص (جستجو)</option>
                    <option value="course">بر اساس دوره</option>
                    <option value="event">بر اساس رویداد</option>
                    <option value="team">بر اساس تیم</option>
                    <option value="level">بر اساس سطح</option>
                    <option value="team_level">بر اساس تیم + سطح</option>
                </select>
            </div>

            <div class="sc-row sc-bulk-field-row">
                <label for="sc-member-status">وضعیت کاربر</label>
                <select name="member_status" id="sc-member-status">
                    <option value="all">همه</option>
                    <option value="active">فقط فعال</option>
                    <option value="inactive">فقط غیرفعال</option>
                </select>
            </div>

            <div class="sc-row sc-bulk-field-row">
                <label for="sc-member-type">دسته‌بندی بازیکن</label>
                <select name="member_type" id="sc-member-type">
                    <option value="all">همه</option>
                    <option value="normal">بازیکن عادی</option>
                    <option value="team">بازیکن تیم</option>
                </select>
            </div>

            <div class="sc-filter-block sc-bulk-field-row" id="sc-filter-specific">
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

            <div class="sc-filter-block sc-bulk-field-row" id="sc-filter-course">
                <label for="sc-course-ids">دوره‌ها</label>
                <select name="course_ids[]" id="sc-course-ids" multiple size="7">
                    <?php foreach ($courses as $course) : ?>
                        <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block sc-bulk-field-row" id="sc-filter-event">
                <label for="sc-event-ids">رویدادها</label>
                <select name="event_ids[]" id="sc-event-ids" multiple size="7">
                    <?php foreach ($events as $event) : ?>
                        <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block sc-bulk-field-row" id="sc-filter-team">
                <label for="sc-team-names">تیم‌ها</label>
                <select name="team_names[]" id="sc-team-names" multiple size="7">
                    <?php foreach ($teams as $team) : ?>
                        <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block sc-bulk-field-row" id="sc-filter-level">
                <label for="sc-level-names">سطح‌ها</label>
                <select name="level_names[]" id="sc-level-names" multiple size="7">
                    <?php foreach ($levels as $level) : ?>
                        <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="submit sc-bulk-preview-submit">
                <button type="button" class="button button-secondary" id="sc-bulk-preview-btn">پیش‌نمایش کاربران فیلترشده</button>
            </p>
            </div>
        </div>

        <div class="sc-bulk-panel sc-bulk-panel--preview sc-users-export-card postbox">
            <div class="postbox-header">
                <h2>۲) پیش‌نمایش کاربران</h2>
            </div>
            <div class="inside">
            <div id="sc-bulk-preview-result" class="sc-bulk-preview-result back_table_list">
                <p class="description">بعد از انتخاب فیلتر، روی «پیش‌نمایش کاربران فیلترشده» کلیک کنید.</p>
            </div>
            </div>
        </div>

        <div class="sc-bulk-panel sc-bulk-panel--action sc-users-export-card postbox">
            <div class="postbox-header">
                <h2>۳) انتخاب عملیات گروهی</h2>
            </div>
            <div class="inside sc-bulk-panel-fields">
            <div class="sc-row sc-bulk-field-row">
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
                    <option value="assign_course_coach">اختصاص بازیکن‌های انتخاب‌شده به مربی دوره</option>
                    <option value="remaining_sessions_adjust">تغییر جلسات باقی‌مانده</option>
                    <option value="delete_members">حذف بازیکن‌ها</option>
                </select>
            </div>

            <div id="sc-action-change-team" class="sc-action-extra">
                <div class="sc-row sc-bulk-field-row">
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
                <div class="sc-row sc-bulk-field-row">
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
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-action-member-type">نوع بازیکن</label>
                    <select name="action_member_type" id="sc-action-member-type">
                        <option value="normal">بازیکن عادی</option>
                        <option value="team">بازیکن تیم</option>
                    </select>
                </div>
            </div>

            <div id="sc-action-course-common" class="sc-action-extra">
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-action-course-ids">دوره‌های هدف</label>
                    <select name="action_course_ids[]" id="sc-action-course-ids" multiple size="7">
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="sc-action-course-activate-branch" class="sc-action-extra">
                <p class="description sc-bulk-action-hint">برای هر دوره انتخاب‌شده، شعبه و مربی ثبت‌نام را مشخص کنید.</p>
                <div id="sc-action-course-activate-branch-list" class="sc-bulk-activate-branch-list">
                    <p class="description">ابتدا یک یا چند دوره را از لیست «دوره‌های هدف» انتخاب کنید.</p>
                </div>
            </div>

            <div id="sc-action-course-flag" class="sc-action-extra">
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-action-course-flag-select">فلگ دوره</label>
                    <select name="action_course_flag" id="sc-action-course-flag-select">
                        <option value="">انتخاب فلگ</option>
                        <?php foreach ($course_flags as $flag_key => $flag_label) : ?>
                            <option value="<?php echo esc_attr($flag_key); ?>"><?php echo esc_html($flag_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="description">فلگ انتخابی به فلگ‌های قبلی اضافه می‌شود و مقادیر قبلی حذف نمی‌شوند.</p>
            </div>

            <div id="sc-action-assign-course-coach" class="sc-action-extra">
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-assign-course-id">دوره</label>
                    <select name="assign_course_id" id="sc-assign-course-id">
                        <option value="">انتخاب دوره</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-assign-chapter-name">شعبه</label>
                    <select name="assign_chapter_name" id="sc-assign-chapter-name">
                        <option value="">ابتدا دوره را انتخاب کنید</option>
                    </select>
                </div>
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-assign-coach-id">مربی دوره</label>
                    <select name="assign_coach_id" id="sc-assign-coach-id">
                        <option value="">ابتدا شعبه را انتخاب کنید</option>
                    </select>
                </div>
                <p class="description">فقط بازیکن‌هایی که در همین دوره ثبت‌نام دارند به مربی انتخاب‌شده در همان شعبه منتسب می‌شوند.</p>
            </div>

            <div id="sc-action-remaining-sessions" class="sc-action-extra">
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-remaining-sessions-mode">نحوهٔ تغییر جلسات باقی‌مانده</label>
                    <select name="remaining_sessions_mode" id="sc-remaining-sessions-mode">
                        <option value="set">تغییر به مقدار مشخص (همان مقدار ثبت می‌شود)</option>
                        <option value="add">افزایش به مقدار مشخص (به عدد فعلی اضافه می‌شود)</option>
                        <option value="subtract">کاهش به مقدار مشخص (از عدد فعلی کم می‌شود؛ حداقل صفر)</option>
                    </select>
                </div>
                <div class="sc-row sc-bulk-field-row">
                    <label for="sc-remaining-sessions-amount">مقدار (عدد صحیح از ۰ به بالا)</label>
                    <input type="number" name="remaining_sessions_amount" id="sc-remaining-sessions-amount" class="small-text" min="0" step="1" inputmode="numeric" placeholder="مثلاً ۳">
                </div>
                <p class="description">ابتدا در بالا «دوره‌های هدف» را انتخاب کنید. برای هر بازیکن، فقط ردیف ثبت‌نام همان دوره‌ها به‌روز می‌شود.</p>
            </div>
            </div>
        </div>

        <div class="sc-bulk-panel sc-bulk-panel--confirm sc-users-export-card postbox">
            <div class="postbox-header">
                <h2>۴) تأیید و اجرا</h2>
            </div>
            <div class="inside">
            <p class="description">قبل از اجرا، پیش‌نمایش را بررسی کنید. عملیات حذف با تأیید نهایی انجام می‌شود.</p>
            <p class="submit sc-bulk-actions-submit">
                <button class="button button-primary" type="submit" id="sc-bulk-submit-btn">اجرای عملیات</button>
            </p>
            </div>
        </div>
    </form>
</div>
