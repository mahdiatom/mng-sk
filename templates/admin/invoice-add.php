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

// دریافت لیست کاربران فعال
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL ORDER BY title");
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");

// مقادیر پیش‌فرض
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
?>

<div class="wrap create_invoice">
    <h1 class="wp-heading-inline">ایجاد صورت حساب جدید</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="page-title-action">بازگشت به لیست صورت حساب‌ها</a>
    
    <hr class="wp-header-end">
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
            <p>هیچ کاربری برای صدور صورت حساب انتخاب نشده است. ابتدا فیلتر و پیش نمایش را بررسی کنید.</p>
        </div>
    <?php elseif (isset($_GET['sc_status']) && $_GET['sc_status'] === 'invoice_add_error') : ?>
        <div class="notice notice-error is-dismissible">
            <p>خطا در ثبت صورت حساب. لطفاً دوباره تلاش کنید.</p>
        </div>
    <?php endif; ?>
    </div>
   <div class="wrap create_invoice sc-bulk-actions-wrap">
    <form method="POST" action="" id="sc-invoice-add-form">
        <?php wp_nonce_field('sc_add_invoice', 'sc_invoice_nonce'); ?>
        <div id="sc-invoice-excluded-members-inputs"></div>
        <div class="sc-users-export-card">
            <h2>۱) فیلتر کاربران</h2>
            <div class="sc-row">
                <label for="sc-invoice-target-type">نوع انتخاب</label>
                <select name="target_type" id="sc-invoice-target-type" style="width: 300px;">
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

            <div class="sc-row">
                <label for="sc-invoice-member-status" >وضعیت کاربر</label>
                <select name="member_status" id="sc-invoice-member-status" style="width: 300px;">
                    <option value="all">همه</option>
                    <option value="active" selected>فقط فعال</option>
                    <option value="inactive">فقط غیرفعال</option>
                </select>
            </div>

            <div class="sc-row">
                <label for="sc-invoice-member-type" >دسته بندی بازیکن</label>
                <select name="member_type" id="sc-invoice-member-type" >
                    <option value="all">همه</option>
                    <option value="normal">بازیکن عادی</option>
                    <option value="team">بازیکن تیم</option>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-invoice-filter-specific">
                <label>انتخاب کاربران</label>
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
            </div>

            <div class="sc-filter-block">
                <label>استثنا از فیلتر (اختیاری)</label>
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
            </div>

            <div class="sc-filter-block" id="sc-invoice-filter-course">
                <label for="sc-invoice-course-ids">دوره ها</label>
                <select name="course_ids[]" id="sc-invoice-course-ids" multiple size="7">
                    <?php foreach ($courses as $course) : ?>
                        <option value="<?php echo (int) $course->id; ?>"><?php echo esc_html($course->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-invoice-filter-event">
                <label for="sc-invoice-event-ids">رویدادها</label>
                <select name="event_ids[]" id="sc-invoice-event-ids" multiple size="7">
                    <?php foreach ($events as $event) : ?>
                        <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-invoice-filter-team">
                <label for="sc-invoice-team-names">تیم ها</label>
                <select name="team_names[]" id="sc-invoice-team-names" multiple size="7">
                    <?php foreach ($teams as $team) : ?>
                        <option value="<?php echo esc_attr($team->name); ?>"><?php echo esc_html($team->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-block" id="sc-invoice-filter-level">
                <label for="sc-invoice-level-names">سطح ها</label>
                <select name="level_names[]" id="sc-invoice-level-names" multiple size="7">
                    <?php foreach ($levels as $level) : ?>
                        <option value="<?php echo esc_attr($level->name); ?>"><?php echo esc_html($level->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="submit">
                <button type="button" class="button button-secondary" id="sc-invoice-preview-btn">پیش نمایش کاربران فیلتر شده</button>
            </p>
        </div>

        <div class="sc-users-export-card">
            <h2>۲) پیش نمایش کاربران</h2>
            <div id="sc-invoice-preview-result" class="sc-bulk-preview-result back_table_list">
                <p class="description">بعد از انتخاب فیلتر، روی «پیش نمایش کاربران فیلتر شده» کلیک کنید.</p>
            </div>
        </div>

        <div class="sc-users-export-card">
            <h2>۳) اطلاعات صورت حساب</h2>
            <div class="sc-form-flex">



    <!-- هزینه: نام + مبلغ -->
    <div class="sc-form-row">

        <div class="sc-form-field" style="width: 100%;">
            <label for="expense_name">نام هزینه:</label>
            <input type="text"
                   name="expense_name"
                   id="expense_name"
                   value="<?php echo esc_attr(isset($_POST['expense_name']) ? $_POST['expense_name'] : ''); ?>"
                   class=""
                   placeholder="مثلاً: هزینه ماهانه، هزینه تغذیه و..."
                   required>
        </div>

        <div class="sc-form-field" style="
    width: 100%;
">
            <label for="amount">مبلغ (تومان):</label>

            <input type="text"
                   name="amount"
                   id="amount"
                   value="<?php echo $amount > 0 ? number_format($amount, 0, '.', ',') : ''; ?>"
                   class=""
                   placeholder="0"
                   required
                   dir="ltr"
                   inputmode="numeric">

            <input type="hidden" name="amount_raw" id="amount_raw" value="<?php echo esc_attr($amount); ?>">
        </div>

    </div>


    <!-- توضیحات -->
    <div class="sc-form-field sc-full">
        <label for="invoice_description">توضیحات (اجباری):</label>

        <textarea required
                  name="invoice_description"
                  id="invoice_description"
                  class="large-text"
                  rows="3"
                  placeholder="توضیحات برای نمایش به کاربر (۲ تا ۴ خط)"
                  ><?php echo esc_textarea(isset($_POST['invoice_description']) ? $_POST['invoice_description'] : ''); ?></textarea>

        <p class="description">در صورت پر کردن، در بخش صورت حساب‌های کاربر نمایش داده می‌شود.</p>
    </div>



    <!-- جریمه -->
    <div class="sc-form-field sc-full">
        <label>
            <input type="checkbox" name="disable_penalty" value="1" <?php checked(isset($_POST['disable_penalty'])); ?>>
            این صورت‌حساب شامل جریمه نشود
        </label>

        <p class="description">
            اگر تیک زده شود، برای این صورت‌حساب هیچ جریمه‌ای محاسبه نخواهد شد.
        </p>
    </div>

            </div>
        </div>
        <div class="sc-users-export-card">
            <h2>۴) ثبت گروهی</h2>
            <p class="description">پس از پیش نمایش و کنترل تیک‌ها، برای کاربران انتخاب‌شده صورت حساب ثبت می‌شود.</p>
        <p class="submit">
            <input type="submit" name="submit_invoice" class="button button-primary" value="ثبت صورت حساب">
            <a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="sc_button">انصراف</a>
        </p>
        </div>
    </form>
</div>


<style>

</style>

