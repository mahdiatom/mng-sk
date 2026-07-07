<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$events_table = $wpdb->prefix . 'sc_events';
$team_table = $wpdb->prefix . 'sc_team_categories';
$level_table = $wpdb->prefix . 'sc_level_categories';

$members = (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_members_for_picker'))
    ? sc_secretary_get_branch_members_for_picker()
    : $wpdb->get_results(
        "SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
    );
$courses = $wpdb->get_results("SELECT id, title, course_type, chapter AS chapter_name FROM $courses_table WHERE deleted_at IS NULL ORDER BY title");
if (function_exists('sc_secretary_finance_filter_courses_list')) {
    $courses = sc_secretary_finance_filter_courses_list($courses);
}
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
?>

<div class="wrap sc-invoice-add-page-header sc-finance-page-header sc-notification-add-wrap sc-users-export-wrap sc-bulk-actions-wrap">
    <h1 class="wp-heading-inline sc-notification-add-title">ایجاد صورت حساب جدید</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-invoices')); ?>" class="page-title-action">لیست صورت حساب‌ها</a>
    <hr class="wp-header-end">
    <p class="sc-invoice-add-subtitle">کاربران را فیلتر کنید، پیش‌نمایش بگیرید و صورت‌حساب گروهی ثبت کنید.</p>
</div>
<div class="wrap sc-invoice-add-page-body sc-finance-page-body sc-invoice-add-wrap sc-notification-add-wrap sc-users-export-wrap sc-bulk-actions-wrap">
    <?php if (isset($_GET['sc_status']) && $_GET['sc_status'] === 'invoice_add_true') : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                صورت حساب با موفقیت ثبت شد.
                (<?php echo esc_html((string) absint($_GET['created_count'] ?? 1)); ?> مورد از
                <?php echo esc_html((string) absint($_GET['total'] ?? 1)); ?> کاربر انتخابی)
            </p>
        </div>
    <?php elseif (isset($_GET['sc_status']) && $_GET['sc_status'] === 'invoice_add_empty_selection') : ?>
        <div class="notice notice-warning is-dismissible">
            <p>هیچ کاربری برای صدور صورت حساب انتخاب نشده است. ابتدا فیلتر و پیش‌نمایش را بررسی کنید.</p>
        </div>
    <?php elseif (isset($_GET['sc_status']) && $_GET['sc_status'] === 'invoice_add_error') : ?>
        <div class="notice notice-error is-dismissible">
            <p>خطا در ثبت صورت حساب. لطفاً دوباره تلاش کنید.</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="sc-invoice-add-form" class="sc-invoice-add-form sc-notification-form">
        <?php wp_nonce_field('sc_add_invoice', 'sc_invoice_nonce'); ?>
        <div id="sc-invoice-excluded-members-inputs"></div>

        <div class="sc-notification-filter-panel sc-invoice-filter-panel sc-finance-panel postbox">
            <div class="postbox-header">
                <h2>۱) فیلتر کاربران</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-invoice-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="sc-invoice-target-type">نوع انتخاب</label></th>
                        <td>
                            <select name="target_type" id="sc-invoice-target-type" class="sc-notification-select">
                                <option value="all">همه کاربران</option>
                                <option value="free_users">کاربران آزاد (بدون هیچ دوره تا امروز)</option>
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
                        <th scope="row"><label for="sc-invoice-member-status">وضعیت کاربر</label></th>
                        <td>
                            <select name="member_status" id="sc-invoice-member-status" class="sc-notification-select">
                                <option value="all">همه</option>
                                <option value="active" selected>فقط فعال</option>
                                <option value="inactive">فقط غیرفعال</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc-invoice-member-type">دسته‌بندی بازیکن</label></th>
                        <td>
                            <select name="member_type" id="sc-invoice-member-type" class="sc-notification-select">
                                <option value="all">همه</option>
                                <option value="normal">بازیکن عادی</option>
                                <option value="team">بازیکن تیم</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-invoice-filter-specific" class="sc-invoice-target-row" style="display:none;">
                        <th scope="row">انتخاب کاربران</th>
                        <td>
                            <div id="sc-invoice-selected-members" class="sc-notification-recipient-tags"></div>
                            <div class="sc-searchable-dropdown sc-notification-recipient-dropdown" id="sc-invoice-member-dropdown">
                                <div class="sc-dropdown-toggle">
                                    <span class="sc-dropdown-placeholder">جستجو یا انتخاب بازیکن برای افزودن...</span>
                                    <span class="sc-dropdown-arrow">▼</span>
                                </div>
                                <div class="sc-dropdown-menu">
                                    <div class="sc-dropdown-search">
                                        <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                    </div>
                                    <div class="sc-dropdown-options" id="sc-invoice-member-options">
                                        <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                        <?php foreach ($members as $member) :
                                            $name = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                                            $search = strtolower($name . ' ' . ($member->national_id ?: ''));
                                            ?>
                                            <div class="sc-dropdown-option"
                                                 data-id="<?php echo (int) $member->id; ?>"
                                                 data-label="<?php echo esc_attr($name . ' - ' . ($member->national_id ?: $member->id)); ?>"
                                                 data-search="<?php echo esc_attr($search); ?>">
                                                <?php echo esc_html($name . ' - ' . ($member->national_id ?: $member->id)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div id="sc-invoice-member-hidden-inputs"></div>
                        </td>
                    </tr>
                    <tr class="sc-invoice-target-row sc-invoice-exclude-row">
                        <th scope="row">استثنا از فیلتر (اختیاری)</th>
                        <td>
                            <div id="sc-invoice-exclude-members" class="sc-notification-recipient-tags"></div>
                            <div class="sc-searchable-dropdown sc-exclude-recipient-dropdown" id="sc-invoice-exclude-dropdown">
                                <div class="sc-dropdown-toggle">
                                    <span class="sc-dropdown-placeholder">جستجو یا انتخاب بازیکن برای حذف از خروجی...</span>
                                    <span class="sc-dropdown-arrow">▼</span>
                                </div>
                                <div class="sc-dropdown-menu">
                                    <div class="sc-dropdown-search">
                                        <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                    </div>
                                    <div class="sc-dropdown-options" id="sc-invoice-exclude-options">
                                        <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                        <?php foreach ($members as $member) :
                                            $name = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                                            $search = strtolower($name . ' ' . ($member->national_id ?: ''));
                                            ?>
                                            <div class="sc-dropdown-option"
                                                 data-id="<?php echo (int) $member->id; ?>"
                                                 data-label="<?php echo esc_attr($name . ' - ' . ($member->national_id ?: $member->id)); ?>"
                                                 data-search="<?php echo esc_attr($search); ?>">
                                                <?php echo esc_html($name . ' - ' . ($member->national_id ?: $member->id)); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="description">کاربرانی که اینجا انتخاب شوند، حتی اگر در فیلتر باشند برای آن‌ها صورت‌حساب ثبت نمی‌شود.</p>
                        </td>
                    </tr>
                    <tr id="sc-invoice-filter-course" class="sc-invoice-target-row" style="display:none;">
                        <th scope="row">دوره‌ها</th>
                        <td>
                            <?php
                            echo function_exists('sc_render_audience_course_picker')
                                ? sc_render_audience_course_picker((array) $courses, [], [
                                    'id' => 'sc-invoice-course-picker',
                                    'name' => 'course_ids[]',
                                ])
                                : '';
                            ?>
                        </td>
                    </tr>
                    <tr id="sc-invoice-filter-event" class="sc-invoice-target-row" style="display:none;">
                        <th scope="row"><label for="sc-invoice-event-ids">رویدادها</label></th>
                        <td>
                            <select name="event_ids[]" id="sc-invoice-event-ids" class="sc-notification-select" multiple size="7">
                                <?php foreach ($events as $event) : ?>
                                    <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-invoice-filter-team" class="sc-invoice-target-row" style="display:none;">
                        <th scope="row"><label for="sc-invoice-team-names">تیم‌ها</label></th>
                        <td>
                            <select name="team_names[]" id="sc-invoice-team-names" class="sc-notification-select" multiple size="7">
                                <?php foreach ($teams as $team) : ?>
                                    <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="sc-invoice-filter-level" class="sc-invoice-target-row" style="display:none;">
                        <th scope="row"><label for="sc-invoice-level-names">سطح‌ها</label></th>
                        <td>
                            <select name="level_names[]" id="sc-invoice-level-names" class="sc-notification-select" multiple size="7">
                                <?php foreach ($levels as $level) : ?>
                                    <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p class="submit sc-invoice-preview-submit sc-notification-preview-submit-wrap">
                    <button type="button" class="button button-secondary" id="sc-invoice-preview-btn">پیش‌نمایش کاربران فیلتر شده</button>
                </p>
            </div>
        </div>

        <div class="sc-notification-preview-panel sc-invoice-panel--preview sc-finance-panel postbox">
            <div class="postbox-header">
                <h2>۲) پیش‌نمایش کاربران</h2>
            </div>
            <div class="inside">
                <div id="sc-invoice-preview-result" class="sc-bulk-preview-result back_table_list sc-invoice-preview-result">
                    <p class="description">بعد از انتخاب فیلتر، روی «پیش‌نمایش کاربران فیلتر شده» کلیک کنید.</p>
                </div>
                <?php
                if (function_exists('sc_audience_render_preview_add_members_block')) {
                    sc_audience_render_preview_add_members_block(
                        function_exists('sc_audience_get_members_for_preview_add_picker') ? sc_audience_get_members_for_preview_add_picker() : $members,
                        [
                            'wrap_id' => 'sc-invoice-preview-add-wrap',
                            'dropdown_id' => 'sc-invoice-preview-add-dropdown',
                            'options_id' => 'sc-invoice-preview-add-options',
                            'hidden_inputs_id' => 'sc-invoice-preview-add-inputs',
                            'mode' => 'invoice',
                        ]
                    );
                }
                ?>
            </div>
        </div>

        <div class="sc-notification-add-panel sc-invoice-info-panel sc-finance-panel postbox">
            <div class="postbox-header">
                <h2>۳) اطلاعات صورت‌حساب</h2>
            </div>
            <div class="inside">
                <table class="form-table sc-notification-form-table sc-invoice-form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="expense_name">نام هزینه <span class="required">*</span></label></th>
                        <td>
                            <input type="text"
                                   name="expense_name"
                                   id="expense_name"
                                   class="regular-text sc-notification-input"
                                   value="<?php echo esc_attr(isset($_POST['expense_name']) ? $_POST['expense_name'] : ''); ?>"
                                   placeholder="مثلاً: هزینه ماهانه، هزینه تغذیه و..."
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="amount">مبلغ (تومان) <span class="required">*</span></label></th>
                        <td>
                            <input type="text"
                                   name="amount"
                                   id="amount"
                                   class="regular-text sc-notification-input"
                                   value="<?php echo $amount > 0 ? number_format($amount, 0, '.', ',') : ''; ?>"
                                   placeholder="0"
                                   required
                                   dir="ltr"
                                   inputmode="numeric">
                            <input type="hidden" name="amount_raw" id="amount_raw" value="<?php echo esc_attr($amount); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="invoice_description">توضیحات <span class="required">*</span></label></th>
                        <td>
                            <textarea required
                                      name="invoice_description"
                                      id="invoice_description"
                                      class="large-text sc-notification-textarea"
                                      rows="4"
                                      placeholder="توضیحات برای نمایش به کاربر (۲ تا ۴ خط)"><?php echo esc_textarea(isset($_POST['invoice_description']) ? $_POST['invoice_description'] : ''); ?></textarea>
                            <p class="description">در صورت پر کردن، در بخش صورت‌حساب‌های کاربر نمایش داده می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">جریمه</th>
                        <td>
                            <label>
                                <input type="checkbox" name="disable_penalty" value="1" <?php checked(isset($_POST['disable_penalty'])); ?>>
                                این صورت‌حساب شامل جریمه نشود
                            </label>
                            <p class="description">اگر تیک زده شود، برای این صورت‌حساب هیچ جریمه‌ای محاسبه نخواهد شد.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="submit sc-invoice-add-submit sc-notification-add-submit">
            <input type="submit" name="submit_invoice" class="button button-primary" value="ثبت صورت‌حساب">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-invoices')); ?>" class="button button-secondary">انصراف</a>
        </p>
    </form>
</div>
