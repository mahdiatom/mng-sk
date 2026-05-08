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
$sel_genders = [];
$sel_members_allow = [];
$sel_members_deny = [];

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
        $sel_genders = $wpdb->get_col($wpdb->prepare(
            "SELECT gender FROM {$wpdb->prefix}sc_discount_code_genders WHERE discount_code_id = %d",
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
        $sel_members_allow = array_map('intval', $allow_ids);
        $sel_members_deny = array_map('intval', $deny_ids);
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
$members_all = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id FROM {$wpdb->prefix}sc_members WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);
$genders_all = [
    ['value' => 'male', 'label' => 'مرد'],
    ['value' => 'female', 'label' => 'زن'],
];

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

if (!function_exists('sc_discount_edit_tpl_shamsi_datetime_parts')) {
    /**
     * @param string|null $mysql
     * @return array{date:string,time:string}
     */
    function sc_discount_edit_tpl_shamsi_datetime_parts($mysql) {
        if (empty($mysql)) {
            return ['date' => '', 'time' => '00:00'];
        }
        $ts = strtotime($mysql);
        if (!$ts) {
            return ['date' => '', 'time' => '00:00'];
        }
        $g = explode('-', date('Y-m-d', $ts));
        $time = date('H:i', $ts);
        if (count($g) !== 3 || !function_exists('gregorian_to_jalali')) {
            return ['date' => '', 'time' => $time];
        }
        $j = gregorian_to_jalali((int) $g[0], (int) $g[1], (int) $g[2]);
        $date = $j[0] . '/' . str_pad((string) $j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $j[2], 2, '0', STR_PAD_LEFT);
        return ['date' => $date, 'time' => $time];
    }
}

$page_title = $discount_id && $row ? 'ویرایش کد تخفیف' : 'افزودن کد تخفیف';
$starts_parts = $row ? sc_discount_edit_tpl_shamsi_datetime_parts($row->starts_at) : ['date' => '', 'time' => '00:00'];
$ends_parts = $row ? sc_discount_edit_tpl_shamsi_datetime_parts($row->ends_at) : ['date' => '', 'time' => '23:59'];
if (!$row) {
    $today_ts = current_time('timestamp');
    $plus_ten_ts = strtotime('+10 days', $today_ts);
    if (function_exists('gregorian_to_jalali')) {
        $g_today = explode('-', date('Y-m-d', $today_ts));
        $j_today = gregorian_to_jalali((int) $g_today[0], (int) $g_today[1], (int) $g_today[2]);
        $starts_parts['date'] = $j_today[0] . '/' . str_pad((string) $j_today[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $j_today[2], 2, '0', STR_PAD_LEFT);

        $g_plus_ten = explode('-', date('Y-m-d', $plus_ten_ts));
        $j_plus_ten = gregorian_to_jalali((int) $g_plus_ten[0], (int) $g_plus_ten[1], (int) $g_plus_ten[2]);
        $ends_parts['date'] = $j_plus_ten[0] . '/' . str_pad((string) $j_plus_ten[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $j_plus_ten[2], 2, '0', STR_PAD_LEFT);
    }
}
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
                    <th scope="row"><label for="starts_at_date_shamsi">تاریخ شروع</label></th>
                    <td>
                        <div class="sc-discount-datetime-row">
                            <input name="starts_at_date_shamsi" id="starts_at_date_shamsi" type="text" class="regular-text persian-date-input" readonly
                                   value="<?php echo esc_attr($starts_parts['date']); ?>" placeholder="مثلاً 1405/02/18">
                            <input name="starts_at_time" id="starts_at_time" type="time" class="small-text"
                                   value="<?php echo esc_attr($starts_parts['time']); ?>">
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ends_at_date_shamsi">تاریخ پایان</label></th>
                    <td>
                        <div class="sc-discount-datetime-row">
                            <input name="ends_at_date_shamsi" id="ends_at_date_shamsi" type="text" class="regular-text persian-date-input" readonly
                                   value="<?php echo esc_attr($ends_parts['date']); ?>" placeholder="مثلاً 1405/03/10">
                            <input name="ends_at_time" id="ends_at_time" type="time" class="small-text"
                                   value="<?php echo esc_attr($ends_parts['time']); ?>">
                        </div>
                    </td>
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
                        <label class="sc-switch-label"><span class="switch"><input type="checkbox" name="allow_course" value="1" <?php checked(!$row || (int) $row->allow_course === 1); ?>><span class="slider round"></span></span><span>ثبت‌نام دوره</span></label><br>
                        <label class="sc-switch-label"><span class="switch"><input type="checkbox" name="allow_event" value="1" <?php checked(!$row || (int) $row->allow_event === 1); ?>><span class="slider round"></span></span><span>ثبت‌نام رویداد</span></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_active">فعال</label></th>
                    <td><label class="sc-switch-label"><span class="switch"><input type="checkbox" name="is_active" id="is_active" value="1" <?php checked(!$row || (int) $row->is_active === 1); ?>><span class="slider round"></span></span><span>بله</span></label></td>
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
                        <p class="description">خالی = همهٔ شعبه‌ها. در دوره و رویداد، شعبهٔ آیتم باید داخل لیست باشد.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gender_values">محدودیت جنسیت بازیکن</label></th>
                    <td>
                        <select name="gender_values[]" id="gender_values" multiple size="4" style="min-width:280px;">
                            <?php foreach ($genders_all as $gender_opt) : ?>
                                <option value="<?php echo esc_attr($gender_opt['value']); ?>" <?php echo in_array($gender_opt['value'], $sel_genders, true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($gender_opt['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">خالی = هر جنسیت.</p>
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
                    <th scope="row">فقط اعضا</th>
                    <td>
                        <div id="sc-discount-allow-tags" class="sc-notification-recipient-tags"></div>
                        <div class="sc-searchable-dropdown sc-notification-recipient-dropdown" data-target="allow">
                            <div class="sc-dropdown-toggle">
                                <span class="sc-dropdown-placeholder">جستجو یا انتخاب بازیکن برای افزودن...</span>
                                <span class="sc-dropdown-arrow">▼</span>
                            </div>
                            <div class="sc-dropdown-menu">
                                <div class="sc-dropdown-search">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                </div>
                                <div class="sc-dropdown-options">
                                    <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                    <?php foreach ($members_all as $m) : ?>
                                        <?php
                                        $label = trim((string) $m->first_name . ' ' . (string) $m->last_name) . ' (بازیکن)';
                                        $search = strtolower(trim((string) $m->first_name . ' ' . (string) $m->last_name . ' ' . (string) ($m->national_id ?: '')));
                                        $value = 'member_' . (int) $m->id;
                                        ?>
                                        <div class="sc-dropdown-option"
                                             data-value="<?php echo esc_attr($value); ?>"
                                             data-label="<?php echo esc_attr($label); ?>"
                                             data-search="<?php echo esc_attr($search); ?>">
                                            <?php echo esc_html(trim((string) $m->first_name . ' ' . (string) $m->last_name) . ' - ' . ($m->national_id ?: (int) $m->id)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="member_allow_ids_str" id="member-allow-ids-input" value="">
                        <p class="description">خالی = بدون محدودیت لیست سفید.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">به‌جز اعضا</th>
                    <td>
                        <div id="sc-discount-deny-tags" class="sc-notification-recipient-tags"></div>
                        <div class="sc-searchable-dropdown sc-exclude-recipient-dropdown" data-target="deny">
                            <div class="sc-dropdown-toggle">
                                <span class="sc-dropdown-placeholder">جستجو یا انتخاب بازیکن برای حذف از خروجی...</span>
                                <span class="sc-dropdown-arrow">▼</span>
                            </div>
                            <div class="sc-dropdown-menu">
                                <div class="sc-dropdown-search">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                </div>
                                <div class="sc-dropdown-options">
                                    <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                    <?php foreach ($members_all as $m) : ?>
                                        <?php
                                        $label = trim((string) $m->first_name . ' ' . (string) $m->last_name) . ' (بازیکن)';
                                        $search = strtolower(trim((string) $m->first_name . ' ' . (string) $m->last_name . ' ' . (string) ($m->national_id ?: '')));
                                        $value = 'member_' . (int) $m->id;
                                        ?>
                                        <div class="sc-dropdown-option"
                                             data-value="<?php echo esc_attr($value); ?>"
                                             data-label="<?php echo esc_attr($label); ?>"
                                             data-search="<?php echo esc_attr($search); ?>">
                                            <?php echo esc_html(trim((string) $m->first_name . ' ' . (string) $m->last_name) . ' - ' . ($m->national_id ?: (int) $m->id)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="member_deny_ids_str" id="member-deny-ids-input" value="">
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('ذخیره کد تخفیف'); ?>
    </form>
    <?php endif; ?>
</div>
<style>
    .sc-switch-label { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .sc-discount-datetime-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    #sc-discount-allow-tags, #sc-discount-deny-tags { margin-bottom: 8px; }
</style>
<script>
jQuery(function($){
    var allowIds = <?php echo wp_json_encode(array_map(static function($id){ return 'member_' . (int) $id; }, array_values(array_map('intval', $sel_members_allow)))); ?>;
    var denyIds = <?php echo wp_json_encode(array_map(static function($id){ return 'member_' . (int) $id; }, array_values(array_map('intval', $sel_members_deny)))); ?>;
    var labelsMap = {};
    $('.sc-dropdown-option').each(function(){
        var val = $(this).data('value');
        var label = $(this).data('label');
        if (val) {
            labelsMap[val] = label || val;
        }
    });

    function renderAllowList() {
        var html = '';
        allowIds.forEach(function(id){
            var lbl = labelsMap[id] || id;
            html += '<span class="recipient-tag" data-id="' + id + '">' + lbl + ' <button type="button" class="recipient-remove">&times;</button></span> ';
        });
        $('#sc-discount-allow-tags').html(html || '<em style="color:#999;">هنوز کسی انتخاب نشده</em>');
        $('#member-allow-ids-input').val(allowIds.join(','));
    }

    function renderDenyList() {
        var html = '';
        denyIds.forEach(function(id){
            var lbl = labelsMap[id] || id;
            html += '<span class="recipient-tag" data-id="' + id + '">' + lbl + ' <button type="button" class="exclude-recipient-remove">&times;</button></span> ';
        });
        $('#sc-discount-deny-tags').html(html || '<em style="color:#999;">هیچ استثنایی ثبت نشده</em>');
        $('#member-deny-ids-input').val(denyIds.join(','));
    }

    $(document).on('click', '#sc-discount-allow-tags .recipient-remove', function(){
        var id = $(this).closest('.recipient-tag').data('id');
        allowIds = allowIds.filter(function(x){ return x !== id; });
        renderAllowList();
    });

    $(document).on('click', '#sc-discount-deny-tags .exclude-recipient-remove', function(){
        var id = $(this).closest('.recipient-tag').data('id');
        denyIds = denyIds.filter(function(x){ return x !== id; });
        renderDenyList();
    });

    document.addEventListener('click', function(e){
        if (!e.target || !e.target.closest) return;
        var opt = e.target.closest('.sc-dropdown-option');
        if (!opt || !jQuery(opt).length) return;
        e.preventDefault();
        e.stopPropagation();
        var $opt = jQuery(opt);
        var val = $opt.data('value');
        var lbl = $opt.data('label') || $opt.text().trim();
        if (!val) return;
        labelsMap[val] = lbl;
        if ($opt.closest('.sc-exclude-recipient-dropdown').length) {
            if (denyIds.indexOf(val) === -1) {
                denyIds.push(val);
            }
            renderDenyList();
        } else if ($opt.closest('.sc-notification-recipient-dropdown').length) {
            if (allowIds.indexOf(val) === -1) {
                allowIds.push(val);
            }
            renderAllowList();
        } else {
            return;
        }
        var $menu = $opt.closest('.sc-dropdown-menu');
        $menu.slideUp(200);
        $menu.find('.sc-search-input').val('').trigger('input');
    }, true);

    renderAllowList();
    renderDenyList();
});
</script>
