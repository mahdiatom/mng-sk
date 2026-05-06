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



  $query_string = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
    //$path_only = trim(parse_url($request_uri, PHP_URL_PATH), '/');
    print_r($_SERVER['QUERY_STRING']);

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
            echo '<p class="form-row sc-custom-player-field-row sc-custom-type-' . esc_attr($type) . '">';
            echo '<label for="sc_custom_' . esc_attr($key) . '">' . esc_html($label) . $required_mark . '</label>';
            if ($type === 'image') {
                $image_url = is_string($current_val) ? $current_val : '';
                echo '<div class="sc-upload-field">';
                echo '<input type="hidden" name="player_custom_fields_images[' . esc_attr($key) . ']" id="sc_custom_' . esc_attr($key) . '_url" value="' . esc_attr($image_url) . '">';
                echo '<input type="file" name="player_custom_fields_files[' . esc_attr($key) . ']" id="sc_custom_' . esc_attr($key) . '" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"' . $required_attr . '>';
                if ($image_url !== '') {
                    echo '<div class="sc-image-preview" style="margin-top:10px;"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '" style="max-width:200px;border:1px solid #ddd;border-radius:4px;">';
                    echo '<button type="button" class="sc-btn-remove-image button-primary" data-target="#sc_custom_' . esc_attr($key) . '_url">حذف عکس</button></div>';
                }
                echo '</div>';
            } elseif ($type === 'multiselect') {
                $selected = is_array($current_val) ? $current_val : [];
                $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
                echo '<select id="sc_custom_' . esc_attr($key) . '" name="player_custom_fields_multi[' . esc_attr($key) . '][]" multiple size="4"' . $required_attr . '>';
                foreach ($options as $option) {
                    $option = (string) $option;
                    echo '<option value="' . esc_attr($option) . '" ' . selected(in_array($option, $selected, true), true, false) . '>' . esc_html($option) . '</option>';
                }
                echo '</select>';
            } else {
                $text_value = is_string($current_val) ? $current_val : '';
                echo '<input type="text" name="player_custom_fields[' . esc_attr($key) . ']" id="sc_custom_' . esc_attr($key) . '" value="' . esc_attr($text_value) . '"' . $required_attr . '>';
            }
            echo '</p>';
        }
    }
}
?>

<div class="sc-submit-documents-form">
    <h2>اطلاعات بازیکن</h2>
    <p class="description">لطفاً اطلاعات و مدارک خود را با دقت وارد کنید. پس از بررسی توسط مدیر، حساب شما فعال خواهد شد.</p>
    
    <?php wc_print_notices(); ?>
    
    <form id="sc-documents-form" method="POST" enctype="multipart/form-data" class="woocommerce-form">
        <?php wp_nonce_field('sc_submit_documents', 'sc_documents_nonce'); ?>
        <input type="hidden" id="sc_player_builtin_rules_json" value="<?php echo esc_attr(wp_json_encode($player_builtin_rules)); ?>">
        <input type="hidden" name="personal_photo_url" id="personal_photo_url" value="<?php echo esc_attr($personal_photo); ?>">
        <input type="hidden" name="id_card_photo_url" id="id_card_photo_url" value="<?php echo esc_attr($id_card_photo); ?>">
        <input type="hidden" name="sport_insurance_photo_url" id="sport_insurance_photo_url" value="<?php echo esc_attr($sport_insurance_photo); ?>">
        
        <div class="sc-form-section">
            <h3>اطلاعات شخصی</h3>
            
            <p class="form-row form-row-first">
                <label for="first_name">نام <span class="required">*</span></label>
                <input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($first_name); ?>" required>
            </p>
            
            <p class="form-row form-row-last">
                <label for="last_name">نام خانوادگی <span class="required">*</span></label>
                <input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($last_name); ?>" required>
            </p>
            
            <p class="form-row form-row-first">
                <label for="father_name">نام پدر</label>
                <input type="text" name="father_name" id="father_name" value="<?php echo esc_attr($father_name); ?>">
            </p>
            
            <p class="form-row form-row-last">
                <label for="national_id">کد ملی <span class="required">*</span></label>
                <input type="text" name="national_id" id="national_id" value="<?php echo esc_attr($national_id); ?>" maxlength="10" required>
            </p>
            
            <p class="form-row form-row-first">
                <label for="birth_date_shamsi">تاریخ تولد (شمسی)</label>
                <input type="text" name="birth_date_shamsi" id="birth_date_shamsi" value="<?php echo esc_attr($birth_date_shamsi); ?>" class="persian-date-input" placeholder="مثلاً 1400/02/15" readonly>
            </p>
             <!-- <tr>
                    <th scope="row"><label for="birth_date_shamsi">تاریخ تولد (شمسی)</label></th>
                    <td>
                        <input name="birth_date_shamsi" type="text" id="birth_date_shamsi" value="<?php //echo esc_attr($birth_date_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلاً 1400/02/15" readonly>
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr> -->
            <p class="form-row form-row-last">
                <label for="birth_date_gregorian">تاریخ تولد (میلادی)</label>
                <span style="font-size: 12px;">تاریخ تولد میلادی شما به صورت اتوماتیک توسط سیستم از تاریخ تولد شمسی شما تبدیل می شود  </span><br>
                <input type="text" name="birth_date_gregorian_display" id="birth_date_gregorian" value="<?php echo esc_attr($birth_date_gregorian); ?>" class="gregorian-date-input" placeholder="مثلاً 2021/05/05" readonly>
                <input type="hidden" name="birth_date_gregorian" id="birth_date_gregorian_hidden" value="<?php echo esc_attr($birth_date_gregorian); ?>">
            </p>
            
            <p class="form-row form-row-first">
                <label for="insurance_expiry_date_shamsi">تاریخ انقضا بیمه (شمسی)</label>
                <input type="text" name="insurance_expiry_date_shamsi" id="insurance_expiry_date_shamsi" value="<?php echo esc_attr($insurance_expiry_date_shamsi); ?>" class="persian-date-input" placeholder="مثلاً 1403/12/29" readonly>
                <strong><a href="https://athlete.ifsm.ir/Login" target="_blank" class="insurance_link">جهت تمدید بیمه روی لینک کلیک کنید : athlete.ifsm.ir/Login</a></strong>
                
    
            </p>
            

            


            <?php sc_render_player_custom_fields_block($player_custom_fields, 'personal', $member_extra_fields); ?>
        </div>
        
        <div class="sc-form-section">
            <h3>اطلاعات تماس</h3>
            <p>لطفا در انتخاب شماره همراه دقت فرمایید و به صورت فرمت *********09 وارد کنید - تمامی اطلاع رسانی ها به شماره موبایل بازیکن ارسال خواهد شد.</p>
            
            <p class="form-row form-row-first">
                <label for="player_phone">شماره موبایل بازیکن - اطلاع رسانی SMS</label>
                <input type="text" name="player_phone" id="player_phone" value="<?php echo esc_attr($player_phone); ?>">
            </p>
            
            <p class="form-row form-row-last">
                <label for="father_phone">شماره موبایل پدر</label>
                <input type="text" name="father_phone" id="father_phone" value="<?php echo esc_attr($father_phone); ?>">
            </p>
            
            <p class="form-row form-row-first">
                <label for="mother_phone">شماره موبایل مادر</label>
                <input type="text" name="mother_phone" id="mother_phone" value="<?php echo esc_attr($mother_phone); ?>">
            </p>
            
            <p class="form-row form-row-last">
                <label for="landline_phone">تلفن ثابت</label>
                <input type="text" name="landline_phone" id="landline_phone" value="<?php echo esc_attr($landline_phone); ?>">
            </p>
            <p class="form-row form-row-first">
                <label for="province">استان</label>
                <input type="text" name="province" id="province" value="<?php echo esc_attr($province); ?>">
            </p>
            <p class="form-row form-row-last">
                <label for="city">شهر</label>
                <input type="text" name="city" id="city" value="<?php echo esc_attr($city); ?>">
            </p>
            <p class="form-row form-row-first">
                <label for="gender">جنسیت</label>
                <select name="gender" id="gender">
                    <option value="">انتخاب کنید</option>
                    <option value="male" <?php selected($gender, 'male'); ?>>مرد</option>
                    <option value="female" <?php selected($gender, 'female'); ?>>زن</option>
                </select>
            </p>
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'contact', $member_extra_fields); ?>
        </div>
        
        <div class="sc-form-section">
            <h3>مدارک و تصاویر</h3>
            <p class="description"> حداکثر حجم هر فایل: ۵ مگابایت. فرمت‌های مجاز: JPG, PNG, GIF, WEBP</p>
        <div class="col_photos">
            <div class="form-row sc-upload-field">
                <label for="personal_photo">عکس پرسنلی</label>
                <input type="file" name="personal_photo" id="personal_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <div class="sc-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div>
                <div class="sc-image-preview" style="margin-top: 10px;<?php echo empty($personal_photo) ? ' display:none;' : ''; ?>">
                    <img src="<?php echo esc_url($personal_photo); ?>" alt="عکس پرسنلی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" class="sc-btn-remove-image button-primary" data-target="#personal_photo_url"> حذف عکس</button>

                </div>
    </div>
            
            <div class="form-row sc-upload-field">
                <label for="id_card_photo">عکس کارت ملی</label>
                <input type="file" name="id_card_photo" id="id_card_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <div class="sc-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div>
                <div class="sc-image-preview" style="margin-top: 10px;<?php echo empty($id_card_photo) ? ' display:none;' : ''; ?>">
                    <img src="<?php echo esc_url($id_card_photo); ?>" alt="عکس کارت ملی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" class="sc-btn-remove-image button-primary" data-target="#id_card_photo">حذف عکس</button>

                </div>
    </div>
            
            <div class="form-row sc-upload-field">
                <label for="sport_insurance_photo">عکس بیمه ورزشی</label>
                <input type="file" name="sport_insurance_photo" id="sport_insurance_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <div class="sc-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div>
                <div class="sc-image-preview" style="margin-top: 10px;<?php echo empty($sport_insurance_photo) ? ' display:none;' : ''; ?>">
                    <img src="<?php echo esc_url($sport_insurance_photo); ?>" alt="عکس بیمه ورزشی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                     <button type="button" class="sc-btn-remove-image button-primary" data-target="#sport_insurance_photo" >حذف عکس </button>

                </div>

    </div>
        </div>
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'documents', $member_extra_fields); ?>
        </div>
        
        <div class="sc-form-section">
            <h3>اطلاعات تکمیلی</h3>
            
            <p class="form-row">
                <label for="medical_condition">مشکلات پزشکی</label>
                <textarea name="medical_condition" placeholder="تمامی موارد پزشکی را به صورت کامل شرح دهید" id="medical_condition" rows="4" class="input-text"><?php echo esc_textarea($medical_condition); ?></textarea>
            </p>
            
            <p class="form-row">
                <label for="sports_history">سوابق ورزشی</label>
                <textarea name="sports_history" placeholder="سوابق ورزشی مرتبط را به صورت موردی بنویسید " id="sports_history" rows="4" class="input-text"><?php echo esc_textarea($sports_history); ?></textarea>
            </p>
            
            <p class="form-row">
                <label for="additional_info">توضیحات اضافی</label>
                <textarea name="additional_info" placeholder="هر آنچه که در موارد فوق نبودند و نیاز هست به مربی  گفته شود را اینجا بنویسید.." id="additional_info" rows="3" class="input-text"><?php echo esc_textarea($additional_info); ?></textarea>
            </p>

            <p class="form-row checkbox_woo_sc">
                <label> من هیچ مشکل قلبی عروقی ندارم</label>
                <input name="health_verified" type="checkbox" <?php checked($health_verified, 1); ?> value="1">
            </p>

            <p class="form-row checkbox_woo_sc">
                <label>تایید میکنم که اطلاعات فوق را به درستی پر کرده ام.</label>
                <input name="info_verified" type="checkbox" <?php checked($info_verified, 1); ?> value="1">
            </p>
            
            <?php if (current_user_can('manage_options')) : ?>
                <p class="form-row">
                    <label for="skill_level">سطح شما</label>
                    <input type="text" name="skill_level" id="skill_level" value="<?php echo esc_attr($skill_level); ?>" class="input-text">
                    <p class="description">این فیلد فقط توسط مدیر قابل ویرایش است.</p>
                </p>
            <?php elseif (!empty($skill_level)) : ?>
                <p class="form-row level">
                    <label>سطح شما ( این فیلد فقط توسط مدیر قابل ویرایش است )</label>
                    
                    <div style="padding: 10px; background: #f9f9f9; border-radius: 4px; color: #333; font-weight: 600;">
                        <?php echo esc_html($skill_level); ?>
                    </div>
                    
                </p>
            <?php endif; ?>
            <?php if (current_user_can('manage_options')) : ?>
                <p class="form-row">
                    <label for="skill_level">تیم شما</label>
                    <input type="text" name="skill_level" id="skill_level" value="<?php echo esc_attr($team_player); ?>" class="input-text">
                    <p class="description">این فیلد فقط توسط مدیر قابل ویرایش است.</p>
                </p>
            <?php elseif (!empty($team_player)) : ?>
                <p class="form-row level">
                    <label>تیم شما ( این فیلد فقط توسط مدیر قابل ویرایش است )</label>
                    
                    <div style="padding: 10px; background: #f9f9f9; border-radius: 4px; color: #333; font-weight: 600;">
                        <?php echo esc_html($team_player); ?>
                    </div>
                    
                </p>
            <?php endif; ?>
            
            <div class="sc-status-cards">
                <div class="sc-status-card">
                    <div class="sc-status-content">
                        <strong>وضعیت بازیکن :  </strong>
                        <span class="sc-status-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><br>
                            <?php echo $is_active ? "فعال" : "غیرفعال"; ?>
                        </span>
                    </div>
                </div>
                
                <div class="sc-status-card">
                    <div class="sc-status-content" >
                        <strong>دوره‌های فعال بازیکن: </strong>
                        <div class="sc-courses-list">
                            <?php echo !empty($courses_text) ? $courses_text : "<span style='color: #999;'>در حال حاضر هیچ دوره فعالی ندارید.</span>"; ?>
                        </div>
                    </div>
                </div>
            </div>
          
            <?php sc_render_player_custom_fields_block($player_custom_fields, 'additional', $member_extra_fields); ?>
        </div>
        
        <p class="form-row">
            <button type="submit" name="sc_submit_documents" class="button" value="1">
                <?php echo $player ? 'بروزرسانی اطلاعات' : 'ثبت اطلاعات'; ?>
            </button>
        </p>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-submit-documents-form h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
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
        var row = input.closest('.form-row') || input.closest('.sc-upload-field') || input.closest('p');
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