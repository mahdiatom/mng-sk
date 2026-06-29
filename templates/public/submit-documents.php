<?php
//this is file for form user in WooCommerce My Account (template)
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$player_id = '';
$first_name = '';
$last_name = '';
$father_name = '';
$national_id = '';
$player_phone = '';
$father_phone = '';
$mother_phone = '';
$landline_phone = '';
$province = '';
$city = '';
$gender = '';
$birth_date_shamsi = '';
$birth_date_gregorian = '';
$insurance_expiry_date_shamsi = '';
$personal_photo = '';
$id_card_photo = '';
$sport_insurance_photo = '';
$medical_condition = '';
$sports_history = '';
$additional_info = '';
$info_verified = 0;
$health_verified = 0;
$is_active = 0;
$skill_level = '';
$team_player = '';
$member_extra_fields = [];

$player_builtin_rules = function_exists('sc_get_player_info_field_rules') ? sc_get_player_info_field_rules() : [];
$player_custom_fields = function_exists('sc_get_player_info_custom_fields') ? sc_get_player_info_custom_fields() : [];



// اگر اطلاعات قبلی وجود دارد
if ($player) {

     function sc_today_shamsi() {
            $today = getdate(time());
            $jalali = gregorian_to_jalali(
                $today['year'],
                $today['mon'],
                $today['mday']
            );

            return $jalali[0] . '/' .
                str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
                str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
        }

        if ($player) {
    $birth_date_shamsi = $player->birth_date_shamsi ?? '';
    $insurance_expiry_date_shamsi = $player->insurance_expiry_date_shamsi ?? '';
        }
        // === ست کردن تاریخ امروز مثل start_date رویداد ===

        if (empty($birth_date_shamsi)) {
            $birth_date_shamsi = sc_today_shamsi();
        }

        if (empty($insurance_expiry_date_shamsi)) {
            $insurance_expiry_date_shamsi = sc_today_shamsi();
        }
    $player_id = $player->id ?? '';
    $first_name = $player->first_name ?? '';
    $last_name = $player->last_name ?? '';
    $father_name = $player->father_name ?? '';
    $national_id = $player->national_id ?? '';
    $player_phone = $player->player_phone ?? '';
    $father_phone = $player->father_phone ?? '';
    $mother_phone = $player->mother_phone ?? '';
    $landline_phone = $player->landline_phone ?? '';
    $province = $player->province ?? '';
    $city = $player->city ?? '';
    $gender = $player->gender ?? '';
        
    if (empty($birth_date_shamsi)) {
            $today = new DateTime();
            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
            $birth_date_shamsi = $today_jalali[0] . '/' . 
                               str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                               str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        }
    $birth_date_gregorian = $player->birth_date_gregorian ?? '';
    //$insurance_expiry_date_shamsi = $player->insurance_expiry_date_shamsi ?? '';
    if (empty($insurance_expiry_date_shamsi)) {
            $insurance_expiry_date_shamsi = sc_today_shamsi();
        }
    $personal_photo = $player->personal_photo ?? '';
    $id_card_photo = $player->id_card_photo ?? '';
    $sport_insurance_photo = $player->sport_insurance_photo ?? '';
    $medical_condition = $player->medical_condition ?? '';
    $sports_history = $player->sports_history ?? '';
    $additional_info = $player->additional_info ?? '';
    $health_verified = $player->health_verified;
    $info_verified = $player->info_verified;
    $is_active = $player->is_active;
    $skill_level = $player->skill_level ?? '';
    $team_player = $player->team_player ?? '';
    $member_extra_fields = !empty($player->member_extra_fields) ? json_decode((string) $player->member_extra_fields, true) : [];
    if (!is_array($member_extra_fields)) {
        $member_extra_fields = [];
    }
    
       
}

// دریافت اطلاعات کاربر از ووکامرس
$user = wp_get_current_user();
$billing_phone = get_user_meta($user->ID, 'billing_phone', true);
if (empty($player_phone) && $billing_phone) {
    $player_phone = $billing_phone;
}

  // نمایش دوره‌های بازیکن (فقط دوره‌های فعال و بدون flag)
        global $wpdb;
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $courses_table = $wpdb->prefix . 'sc_courses';
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d 
             AND mc.status = 'active' 
             AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '')
             AND c.deleted_at IS NULL 
             AND c.is_active = 1
             LIMIT 10",
            $player_id
        ));
        
        $course_names = [];
        if ($courses) {
            foreach ($courses as $course) {
                $course_names[] = $course->title;
            }
        }
        $courses_text = !empty($course_names) ? '<br><small> ' . implode(', ', $course_names) . '</small>' : '';

?>

<?php
if (!function_exists('sc_render_player_custom_fields_block')) {
    function sc_render_player_custom_fields_block($fields, $section, $values = []) {
        if (empty($fields) || !is_array($fields)) {
            return;
        }
        foreach ($fields as $field) {
            if (!is_array($field) || ($field['section'] ?? '') !== $section || empty($field['visible'])) {
                continue;
            }
            $key = isset($field['key']) ? sanitize_key($field['key']) : '';
            if ($key === '') {
                continue;
            }
            $type = $field['type'] ?? 'text';
            $label = $field['label'] ?? $key;
            $is_required = !empty($field['required']);
            $required_attr = $is_required ? ' required' : '';
            $required_mark = $is_required ? ' <span class="required">*</span>' : '';
            $current_val = $values[$key] ?? ($type === 'multiselect' ? [] : '');
            $full_class = ($type === 'image' || $type === 'multiselect') ? ' sc-player-field-card--full' : '';
            echo '<div class="sc-player-field-card' . esc_attr($full_class) . ' sc-custom-type-' . esc_attr($type) . '">';
            echo '<label class="sc-player-field-card__label" for="sc_custom_' . esc_attr($key) . '">' . esc_html($label) . $required_mark . '</label>';
            echo '<div class="sc-player-field-card__control">';
            if ($type === 'image') {
                $image_url = is_string($current_val) ? $current_val : '';
                echo '<div class="sc-upload-field sc-player-upload-field">';
                echo '<input type="hidden" name="player_custom_fields_images[' . esc_attr($key) . ']" id="sc_custom_' . esc_attr($key) . '_url" value="' . esc_attr($image_url) . '">';
                echo '<input type="file" name="player_custom_fields_files[' . esc_attr($key) . ']" id="sc_custom_' . esc_attr($key) . '" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"' . $required_attr . '>';
                if ($image_url !== '') {
                    echo '<div class="sc-image-preview img_photo_prev"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '">';
                    echo '<button type="button" class="sc-btn-remove-image button" data-target="#sc_custom_' . esc_attr($key) . '_url">حذف عکس</button></div>';
                }
                echo '</div>';
            } elseif ($type === 'multiselect') {
                $selected = is_array($current_val) ? $current_val : [];
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                echo '<select class="regular-text" id="sc_custom_' . esc_attr($key) . '" name="player_custom_fields_multi[' . esc_attr($key) . '][]" multiple size="4"' . $required_attr . '>';
                foreach ($options as $option) {
                    $option = (string) $option;
                    echo '<option value="' . esc_attr($option) . '" ' . selected(in_array($option, $selected, true), true, false) . '>' . esc_html($option) . '</option>';
                }
                echo '</select>';
            } else {
                $text_value = is_string($current_val) ? $current_val : '';
                echo '<input class="regular-text" type="text" name="player_custom_fields[' . esc_attr($key) . ']" id="sc_custom_' . esc_attr($key) . '" value="' . esc_attr($text_value) . '"' . $required_attr . '>';
            }
            echo '</div></div>';
        }
    }
}

if (!function_exists('sc_player_field_card_open')) {
    function sc_player_field_card_open($label, $required = false, $extra_class = '') {
        $class = 'sc-player-field-card' . ($extra_class ? ' ' . $extra_class : '');
        echo '<div class="' . esc_attr(trim($class)) . '">';
        echo '<label class="sc-player-field-card__label">' . esc_html($label);
        if ($required) {
            echo ' <span class="required">*</span>';
        }
        echo '</label><div class="sc-player-field-card__control">';
    }
}

if (!function_exists('sc_player_field_card_close')) {
    function sc_player_field_card_close($description = '') {
        if ($description !== '') {
            echo '<p class="sc-player-field-card__desc">' . wp_kses_post($description) . '</p>';
        }
        echo '</div></div>';
    }
}

if (!function_exists('sc_player_form_group_open')) {
    function sc_player_form_group_open($title, $slug = '') {
        $slug = $slug ? sanitize_html_class($slug) : '';
        echo '<div class="sc-player-form-group' . ($slug ? ' sc-player-form-group--' . esc_attr($slug) : '') . '">';
        echo '<div class="sc-player-form-group__head"><h3 class="sc-player-form-group__title">' . esc_html($title) . '</h3></div>';
        echo '<div class="sc-player-form-grid">';
    }
}

if (!function_exists('sc_player_form_group_close')) {
    function sc_player_form_group_close() {
        echo '</div></div>';
    }
}
?>

<div class="sc-panel-section sc-panel-player-info">
    <?php
    if (function_exists('sc_panel_render_section_hero')) {
        sc_panel_render_section_hero(
            'اطلاعات بازیکن',
            'لطفاً اطلاعات و مدارک خود را با دقت وارد کنید. پس از بررسی توسط مدیر، حساب شما فعال خواهد شد.',
            'player'
        );
    }
    ?>
    <div class="sc-panel-section__body">
        <p class="sc-panel-alert sc-panel-alert--info">با هر بار تغییر اطلاعات، وضعیت احراز هویت شما در انتظار بررسی می‌شود و ممکن است به برخی بخش‌ها دسترسی نداشته باشید.</p>
<div class="sc-submit-documents-form">
    <?php wc_print_notices(); ?>
    
    <form id="sc-documents-form" method="POST" enctype="multipart/form-data" class="woocommerce-form sc-player-admin-form">
        <?php wp_nonce_field('sc_submit_documents', 'sc_documents_nonce'); ?>
        <input type="hidden" id="sc_player_builtin_rules_json" value="<?php echo esc_attr(wp_json_encode($player_builtin_rules)); ?>">
        <input type="hidden" name="personal_photo_url" id="personal_photo_url" value="<?php echo esc_attr($personal_photo); ?>">
        <input type="hidden" name="id_card_photo_url" id="id_card_photo_url" value="<?php echo esc_attr($id_card_photo); ?>">
        <input type="hidden" name="sport_insurance_photo_url" id="sport_insurance_photo_url" value="<?php echo esc_attr($sport_insurance_photo); ?>">
        
        <?php sc_player_form_group_open('اطلاعات شخصی', 'personal'); ?>
            <?php sc_player_field_card_open('نام', true); ?>
                <input class="regular-text" type="text" name="first_name" id="first_name" value="<?php echo esc_attr($first_name); ?>" required>
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('نام خانوادگی', true); ?>
                <input class="regular-text" type="text" name="last_name" id="last_name" value="<?php echo esc_attr($last_name); ?>" required>
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('نام پدر'); ?>
                <input class="regular-text" type="text" name="father_name" id="father_name" value="<?php echo esc_attr($father_name); ?>">
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('کد ملی', true); ?>
                <input class="regular-text" type="text" name="national_id" id="national_id" value="<?php echo esc_attr($national_id); ?>" maxlength="10" required>
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('جنسیت'); ?>
                <select class="regular-text" name="gender" id="gender">
                    <option value="">انتخاب کنید</option>
                    <option value="male" <?php selected($gender, 'male'); ?>>مرد</option>
                    <option value="female" <?php selected($gender, 'female'); ?>>زن</option>
                </select>
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('تاریخ تولد (شمسی)'); ?>
                <input class="regular-text persian-date-input" type="text" name="birth_date_shamsi" id="birth_date_shamsi" value="<?php echo esc_attr($birth_date_shamsi); ?>" placeholder="مثلاً 1400/02/15" readonly>
            <?php sc_player_field_card_close('برای انتخاب تاریخ، روی فیلد کلیک کنید'); ?>
            <?php sc_player_field_card_open('تاریخ تولد (میلادی)', false, 'sc-player-field-card--full'); ?>
                <input class="regular-text gregorian-date-input" type="text" name="birth_date_gregorian_display" id="birth_date_gregorian" value="<?php echo esc_attr($birth_date_gregorian); ?>" placeholder="مثلاً 2021/05/05" readonly>
                <input type="hidden" name="birth_date_gregorian" id="birth_date_gregorian_hidden" value="<?php echo esc_attr($birth_date_gregorian); ?>">
            <?php sc_player_field_card_close('تاریخ میلادی به‌صورت خودکار از شمسی تبدیل می‌شود؛ در صورت مغایرت می‌توانید ویرایش کنید.'); ?>
            <?php sc_player_field_card_open('تاریخ انقضا بیمه', false, 'sc-player-field-card--full'); ?>
                <input class="regular-text persian-date-input" type="text" name="insurance_expiry_date_shamsi" id="insurance_expiry_date_shamsi" value="<?php echo esc_attr($insurance_expiry_date_shamsi); ?>" placeholder="مثلاً 1403/12/29" readonly>
            <?php sc_player_field_card_close('<a href="https://athlete.ifsm.ir/Login" target="_blank" rel="noopener" class="insurance_link">جهت تمدید بیمه: athlete.ifsm.ir/Login</a>'); ?>
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'personal', $member_extra_fields); ?>
        <?php sc_player_form_group_close(); ?>
        
        <?php sc_player_form_group_open('اطلاعات تماس', 'contact'); ?>
            <p class="sc-player-form-group__hint">شماره موبایل را با فرمت 09********* وارد کنید. تمامی اطلاع‌رسانی‌ها به شماره بازیکن ارسال می‌شود.</p>
            <?php sc_player_field_card_open('شماره موبایل بازیکن'); ?>
                <input class="regular-text" type="text" name="player_phone" id="player_phone" value="<?php echo esc_attr($player_phone); ?>">
            <?php sc_player_field_card_close('فقط برای شماره بازیکن پیامک ارسال می‌شود.'); ?>
            <?php sc_player_field_card_open('شماره موبایل پدر'); ?>
                <input class="regular-text" type="text" name="father_phone" id="father_phone" value="<?php echo esc_attr($father_phone); ?>">
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('شماره موبایل مادر'); ?>
                <input class="regular-text" type="text" name="mother_phone" id="mother_phone" value="<?php echo esc_attr($mother_phone); ?>">
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('تلفن ثابت'); ?>
                <input class="regular-text" type="text" name="landline_phone" id="landline_phone" value="<?php echo esc_attr($landline_phone); ?>">
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('استان'); ?>
                <input class="regular-text" type="text" name="province" id="province" value="<?php echo esc_attr($province); ?>">
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('شهر'); ?>
                <input class="regular-text" type="text" name="city" id="city" value="<?php echo esc_attr($city); ?>">
            <?php sc_player_field_card_close(); ?>
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'contact', $member_extra_fields); ?>
        <?php sc_player_form_group_close(); ?>
        
        <?php sc_player_form_group_open('مدارک و تصاویر', 'docs'); ?>
            <p class="sc-player-form-group__hint">حداکثر حجم هر فایل: ۵ مگابایت — فرمت‌های مجاز: JPG, PNG, GIF, WEBP</p>
            <div class="sc-player-photos-grid">
                <?php sc_player_field_card_open('عکس پرسنلی', false, 'sc-player-field-card--photo'); ?>
                    <div class="sc-upload-field sc-player-upload-field">
                        <input type="file" name="personal_photo" id="personal_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <div class="sc-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div>
                        <div class="sc-image-preview img_photo_prev"<?php echo empty($personal_photo) ? ' style="display:none;"' : ''; ?>>
                            <img src="<?php echo esc_url($personal_photo); ?>" alt="عکس پرسنلی">
                            <button type="button" class="sc-btn-remove-image button" data-target="#personal_photo_url">حذف عکس</button>
                        </div>
                    </div>
                <?php sc_player_field_card_close(); ?>
                <?php sc_player_field_card_open('عکس کارت ملی', false, 'sc-player-field-card--photo'); ?>
                    <div class="sc-upload-field sc-player-upload-field">
                        <input type="file" name="id_card_photo" id="id_card_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <div class="sc-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div>
                        <div class="sc-image-preview img_photo_prev"<?php echo empty($id_card_photo) ? ' style="display:none;"' : ''; ?>>
                            <img src="<?php echo esc_url($id_card_photo); ?>" alt="عکس کارت ملی">
                            <button type="button" class="sc-btn-remove-image button" data-target="#id_card_photo_url">حذف عکس</button>
                        </div>
                    </div>
                <?php sc_player_field_card_close(); ?>
                <?php sc_player_field_card_open('عکس بیمه ورزشی', false, 'sc-player-field-card--photo'); ?>
                    <div class="sc-upload-field sc-player-upload-field">
                        <input type="file" name="sport_insurance_photo" id="sport_insurance_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <div class="sc-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div>
                        <div class="sc-image-preview img_photo_prev"<?php echo empty($sport_insurance_photo) ? ' style="display:none;"' : ''; ?>>
                            <img src="<?php echo esc_url($sport_insurance_photo); ?>" alt="عکس بیمه ورزشی">
                            <button type="button" class="sc-btn-remove-image button" data-target="#sport_insurance_photo_url">حذف عکس</button>
                        </div>
                    </div>
                <?php sc_player_field_card_close(); ?>
            </div>
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'documents', $member_extra_fields); ?>
        <?php sc_player_form_group_close(); ?>
        
        <?php sc_player_form_group_open('اطلاعات تکمیلی', 'extra'); ?>
            <?php sc_player_field_card_open('مشکلات پزشکی', false, 'sc-player-field-card--full'); ?>
                <textarea class="large-text" name="medical_condition" id="medical_condition" rows="4" placeholder="تمامی موارد پزشکی را به صورت کامل شرح دهید"><?php echo esc_textarea($medical_condition); ?></textarea>
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('سوابق ورزشی', false, 'sc-player-field-card--full'); ?>
                <textarea class="large-text" name="sports_history" id="sports_history" rows="4" placeholder="سوابق ورزشی مرتبط را به صورت موردی بنویسید"><?php echo esc_textarea($sports_history); ?></textarea>
            <?php sc_player_field_card_close(); ?>
            <?php sc_player_field_card_open('توضیحات اضافی', false, 'sc-player-field-card--full'); ?>
                <textarea class="large-text" name="additional_info" id="additional_info" rows="3" placeholder="موارد دیگری که لازم است به مربی گفته شود"><?php echo esc_textarea($additional_info); ?></textarea>
            <?php sc_player_field_card_close(); ?>
            <div class="sc-player-field-card sc-player-field-card--full sc-player-field-card--checkbox">
                <label class="sc-player-checkbox-pill">
                    <input name="health_verified" type="checkbox" <?php checked($health_verified, 1); ?> value="1">
                    <span>من هیچ مشکل قلبی عروقی ندارم</span>
                </label>
            </div>
            <div class="sc-player-field-card sc-player-field-card--full sc-player-field-card--checkbox">
                <label class="sc-player-checkbox-pill">
                    <input name="info_verified" type="checkbox" <?php checked($info_verified, 1); ?> value="1">
                    <span>تأیید می‌کنم اطلاعات فوق را به‌درستی پر کرده‌ام</span>
                </label>
            </div>
            <?php if (current_user_can('manage_options')) : ?>
                <?php sc_player_field_card_open('سطح شما'); ?>
                    <input class="regular-text" type="text" name="skill_level" id="skill_level" value="<?php echo esc_attr($skill_level); ?>">
                <?php sc_player_field_card_close('این فیلد فقط توسط مدیر قابل ویرایش است.'); ?>
                <?php sc_player_field_card_open('تیم شما'); ?>
                    <input class="regular-text" type="text" name="team_player" id="team_player" value="<?php echo esc_attr($team_player); ?>">
                <?php sc_player_field_card_close('این فیلد فقط توسط مدیر قابل ویرایش است.'); ?>
            <?php else : ?>
                <?php if (!empty($skill_level)) : ?>
                    <?php sc_player_field_card_open('سطح شما'); ?>
                        <div class="sc-player-readonly-value"><?php echo esc_html($skill_level); ?></div>
                    <?php sc_player_field_card_close('این فیلد فقط توسط مدیر قابل ویرایش است.'); ?>
                <?php endif; ?>
                <?php if (!empty($team_player)) : ?>
                    <?php sc_player_field_card_open('تیم شما'); ?>
                        <div class="sc-player-readonly-value"><?php echo esc_html($team_player); ?></div>
                    <?php sc_player_field_card_close('این فیلد فقط توسط مدیر قابل ویرایش است.'); ?>
                <?php endif; ?>
            <?php endif; ?>
            <div class="sc-player-status-grid">
                <div class="sc-player-status-card">
                    <span class="sc-player-status-card__label">وضعیت بازیکن</span>
                    <span class="sc-player-status-card__value sc-player-status-card__value--<?php echo $is_active ? 'active' : 'inactive'; ?>">
                        <?php echo $is_active ? 'فعال' : 'غیرفعال'; ?>
                    </span>
                </div>
                <div class="sc-player-status-card">
                    <span class="sc-player-status-card__label">دوره‌های فعال</span>
                    <span class="sc-player-status-card__value sc-player-status-card__value--courses">
                        <?php echo !empty($course_names) ? esc_html(implode('، ', $course_names)) : 'بدون دوره فعال'; ?>
                    </span>
                </div>
            </div>
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'additional', $member_extra_fields); ?>
        <?php sc_player_form_group_close(); ?>

        <div class="sc-player-form-actions">
            <button type="submit" name="sc_submit_documents" class="button sc-panel-btn-primary sc-player-submit-btn" value="1">
                <?php echo $player ? 'بروزرسانی اطلاعات' : 'ثبت اطلاعات'; ?>
            </button>
        </div>
    </form>
</div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const removeBtns = document.querySelectorAll('.sc-btn-remove-image');
    removeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const previewDiv = btn.closest('.sc-image-preview');
            const targetInput = document.querySelector(btn.getAttribute('data-target'));
            if(previewDiv) previewDiv.style.display = 'none';
            if(targetInput) targetInput.value = '';
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    var rulesInput = document.getElementById('sc_player_builtin_rules_json');
    if (!rulesInput || !rulesInput.value) {
        return;
    }
    var rules = {};
    try {
        rules = JSON.parse(rulesInput.value);
    } catch (e) {
        rules = {};
    }
    Object.keys(rules).forEach(function(fieldKey) {
        var input = document.querySelector('[name="' + fieldKey + '"]');
        if (!input) {
            return;
        }
        var rule = rules[fieldKey] || {};
        var row = input.closest('.sc-player-field-card') || input.closest('.form-row') || input.closest('.sc-upload-field') || input.closest('p');
        if (rule.visible === 0 || rule.visible === '0') {
            if (row) {
                row.style.display = 'none';
            }
            input.disabled = true;
            input.required = false;
            return;
        }
        input.disabled = false;
        input.required = !!(rule.required == 1);
    });
});
</script>