<?php
if (!defined('ABSPATH')) {
    exit;
}

$discount_id = isset($_GET['discount_id']) ? absint($_GET['discount_id']) : 0;
global $wpdb;

$row = null;
$sel_courses = [];
$sel_events = [];
$sel_chapters = [];
$sel_teams = [];
$sel_levels = [];
$members_allow_str = '';
$members_deny_str = '';

$codes_tbl = $wpdb->prefix . 'sc_discount_codes';
if ($discount_id && function_exists('sc_sc_discount_tables_ready') && sc_sc_discount_tables_ready()) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $codes_tbl WHERE id = %d", $discount_id));
    if ($row) {
        $sel_courses = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}sc_discount_code_courses WHERE discount_code_id = %d",
            $discount_id
        ));
        $sel_events = $wpdb->get_col($wpdb->prepare(
            "SELECT event_id FROM {$wpdb->prefix}sc_discount_code_events WHERE discount_code_id = %d",
            $discount_id
        ));
        $sel_chapters = $wpdb->get_col($wpdb->prepare(
            "SELECT chapter_name FROM {$wpdb->prefix}sc_discount_code_chapters WHERE discount_code_id = %d",
            $discount_id
        ));
        $sel_teams = $wpdb->get_col($wpdb->prepare(
            "SELECT team_name FROM {$wpdb->prefix}sc_discount_code_teams WHERE discount_code_id = %d",
            $discount_id
        ));
        $sel_levels = $wpdb->get_col($wpdb->prepare(
            "SELECT level_name FROM {$wpdb->prefix}sc_discount_code_levels WHERE discount_code_id = %d",
            $discount_id
        ));
        $allow_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$wpdb->prefix}sc_discount_code_members_allow WHERE discount_code_id = %d ORDER BY member_id ASC",
            $discount_id
        ));
        $deny_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$wpdb->prefix}sc_discount_code_members_deny WHERE discount_code_id = %d ORDER BY member_id ASC",
            $discount_id
        ));
        $members_allow_str = implode(', ', array_map('intval', $allow_ids));
        $members_deny_str = implode(', ', array_map('intval', $deny_ids));
    }
}

$courses_all = $wpdb->get_results(
    "SELECT id, title FROM {$wpdb->prefix}sc_courses WHERE deleted_at IS NULL ORDER BY title ASC"
);
$events_all = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}sc_events WHERE deleted_at IS NULL ORDER BY name ASC"
);
$chapters_all = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sc_chapter_categories ORDER BY id ASC");
$teams_all = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sc_team_categories ORDER BY id ASC");
$levels_all = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sc_level_categories ORDER BY id ASC");

$sc_err = isset($_GET['sc_err']) ? sanitize_key(wp_unslash($_GET['sc_err'])) : '';
$err_msg = '';
if ($sc_err === 'code') {
    $err_msg = 'کد تخفیف را وارد کنید.';
} elseif ($sc_err === 'duplicate') {
    $err_msg = 'این کد قبلاً ثبت شده است.';
} elseif ($sc_err === 'save') {
    $err_msg = 'خطا در ذخیرهٔ دیتابیس.';
} elseif ($sc_err === 'no_tables') {
    $err_msg = 'جداول کد تخفیف ایجاد نشده‌اند؛ یک‌بار افزونه را به‌روز کنید.';
}

if (!function_exists('sc_discount_edit_tpl_datetime_local')) {
    /**
     * @param string|null $mysql
     */
    function sc_discount_edit_tpl_datetime_local($mysql) {
        if (empty($mysql)) {
            return '';
        }
        $ts = strtotime($mysql);
        return $ts ? date('Y-m-d\TH:i', $ts) : '';
    }
}

$page_title = $discount_id && $row ? 'ویرایش کد تخفیف' : 'افزودن کد تخفیف';
?>
<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-discount-codes')); ?>" class="page-title-action">← بازگشت به لیست</a>

    <?php if ($err_msg) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($err_msg); ?></p></div>
    <?php endif; ?>

    <?php if ($discount_id && !$row) : ?>
        <div class="notice notice-error"><p>کد تخفیف یافت نشد.</p></div>
    <?php else : ?>

    <form method="post" action="">
        <?php wp_nonce_field('sc_save_discount_code'); ?>
        <input type="hidden" name="discount_id" value="<?php echo esc_attr((string) $discount_id); ?>">
        <input type="hidden" name="sc_save_discount_code" value="1">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="sc_dc_code">کد تخفیف <span style="color:red">*</span></label></th>
                    <td>
                        <input name="code" id="sc_dc_code" type="text" class="regular-text" required
                               value="<?php echo $row ? esc_attr($row->code) : ''; ?>"
                               placeholder="مثال SUMMER">
                        <p class="description">بدون فاصله؛ هنگام ورود کاربر نادیده گرفته می‌شود.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sc_dc_desc">توضیحات داخلی</label></th>
                    <td><textarea name="description" id="sc_dc_desc" rows="3" class="large-text"><?php echo $row ? esc_textarea($row->description) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row">نوع تخفیف</th>
                    <td>
                        <label><input type="radio" name="discount_type" value="percent" <?php checked(!$row || $row->discount_type !== 'fixed'); ?>> درصد</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="discount_type" value="fixed" <?php checked($row && $row->discount_type === 'fixed'); ?>> مبلغ ثابت (تومان)</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="discount_value_raw">مقدار</label></th>
                    <td>
                        <input name="discount_value_raw" id="discount_value_raw" type="text" class="regular-text"
                               value="<?php echo $row ? esc_attr((string) $row->discount_value) : ''; ?>">
                        <p class="description">درصد یا مبلغ ثابت به تومان.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_discount_amount_raw">سقف مبلغ تخفیف (درصدی)</label></th>
                    <td>
                        <input name="max_discount_amount_raw" id="max_discount_amount_raw" type="text" class="regular-text"
                               value="<?php echo ($row && $row->max_discount_amount !== null && $row->max_discount_amount !== '') ? esc_attr((string) $row->max_discount_amount) : ''; ?>">
                        <p class="description">فقط برای نوع درصد؛ خالی = بدون سقف.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="min_subtotal_raw">حداقل مبلغ صورت‌حساب</label></th>
                    <td>
                        <input name="min_subtotal_raw" id="min_subtotal_raw" type="text" class="regular-text"
                               value="<?php echo $row ? esc_attr((string) $row->min_subtotal) : ''; ?>">
                        <p class="description">خالی یا صفر = بدون حداقل.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="starts_at">تاریخ شروع</label></th>
                    <td><input name="starts_at" id="starts_at" type="datetime-local" class="regular-text"
                               value="<?php echo $row ? esc_attr(sc_discount_edit_tpl_datetime_local($row->starts_at)) : ''; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ends_at">تاریخ پایان</label></th>
                    <td><input name="ends_at" id="ends_at" type="datetime-local" class="regular-text"
                               value="<?php echo $row ? esc_attr(sc_discount_edit_tpl_datetime_local($row->ends_at)) : ''; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="usage_limit_total">سقف استفاده (کل)</label></th>
                    <td>
                        <input name="usage_limit_total" id="usage_limit_total" type="number" min="0" class="small-text"
                               value="<?php echo ($row && $row->usage_limit_total !== null) ? esc_attr((string) $row->usage_limit_total) : ''; ?>">
                        <p class="description">خالی = نامحدود.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="usage_limit_per_member">سقف برای هر عضو</label></th>
                    <td>
                        <input name="usage_limit_per_member" id="usage_limit_per_member" type="number" min="0" class="small-text"
                               value="<?php echo ($row && $row->usage_limit_per_member !== null) ? esc_attr((string) $row->usage_limit_per_member) : ''; ?>">
                        <p class="description">خالی = نامحدود برای هر نفر.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">اعمال روی</th>
                    <td>
                        <label><input type="checkbox" name="allow_course" value="1" <?php checked(!$row || (int) $row->allow_course === 1); ?>> ثبت‌نام دوره</label><br>
                        <label><input type="checkbox" name="allow_event" value="1" <?php checked(!$row || (int) $row->allow_event === 1); ?>> ثبت‌نام رویداد</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_active">فعال</label></th>
                    <td><label><input type="checkbox" name="is_active" id="is_active" value="1" <?php checked(!$row || (int) $row->is_active === 1); ?>></label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="course_ids">محدودیت دوره‌ها</label></th>
                    <td>
                        <select name="course_ids[]" id="course_ids" multiple size="8" style="min-width:320px;width:100%;max-width:520px;">
                            <?php foreach ($courses_all as $c) : ?>
                                <option value="<?php echo esc_attr((string) $c->id); ?>" <?php echo in_array((int) $c->id, array_map('intval', $sel_courses), true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($c->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">بدون انتخاب = همهٔ دوره‌ها (در صورت فعال بودن ثبت‌نام دوره).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="event_ids">محدودیت رویدادها</label></th>
                    <td>
                        <select name="event_ids[]" id="event_ids" multiple size="8" style="min-width:320px;width:100%;max-width:520px;">
                            <?php foreach ($events_all as $e) : ?>
                                <option value="<?php echo esc_attr((string) $e->id); ?>" <?php echo in_array((int) $e->id, array_map('intval', $sel_events), true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($e->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">بدون انتخاب = همهٔ رویدادها.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="chapter_names">محدودیت شعبه (نام شعبه دوره)</label></th>
                    <td>
                        <select name="chapter_names[]" id="chapter_names" multiple size="6" style="min-width:280px;">
                            <?php foreach ($chapters_all as $ch) : ?>
                                <option value="<?php echo esc_attr($ch->name); ?>" <?php echo in_array($ch->name, $sel_chapters, true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($ch->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">خالی = همهٔ شعبه‌ها. برای رویداد، اگر شعبه انتخاب شده باشد این کد برای رویداد قابل استفاده نیست.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="team_names">محدودیت تیم بازیکن</label></th>
                    <td>
                        <select name="team_names[]" id="team_names" multiple size="6" style="min-width:280px;">
                            <?php foreach ($teams_all as $t) : ?>
                                <option value="<?php echo esc_attr($t->name); ?>" <?php echo in_array($t->name, $sel_teams, true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($t->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="level_names">محدودیت سطح</label></th>
                    <td>
                        <select name="level_names[]" id="level_names" multiple size="6" style="min-width:280px;">
                            <?php foreach ($levels_all as $lv) : ?>
                                <option value="<?php echo esc_attr($lv->name); ?>" <?php echo in_array($lv->name, $sel_levels, true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($lv->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="members_allow">فقط اعضای (شناسه)</label></th>
                    <td>
                        <textarea name="members_allow" id="members_allow" rows="2" class="large-text" placeholder="مثال: 12, 45, 88"><?php echo esc_textarea($members_allow_str); ?></textarea>
                        <p class="description">خالی = بدون محدودیت لیست سفید.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="members_deny">به‌جز اعضای (شناسه)</label></th>
                    <td>
                        <textarea name="members_deny" id="members_deny" rows="2" class="large-text"><?php echo esc_textarea($members_deny_str); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('ذخیره کد تخفیف'); ?>
    </form>
    <?php endif; ?>
</div>
