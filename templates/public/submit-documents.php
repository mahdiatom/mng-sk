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


// اگر اطلاعات قبلی وجود دارد
if ($player) {
    $player_id = $player->id ?? '';
    $first_name = $player->first_name ?? '';
    $last_name = $player->last_name ?? '';
    $father_name = $player->father_name ?? '';
    $national_id = $player->national_id ?? '';
    $player_phone = $player->player_phone ?? '';
    $father_phone = $player->father_phone ?? '';
    $mother_phone = $player->mother_phone ?? '';
    $landline_phone = $player->landline_phone ?? '';
    $birth_date_shamsi = $player->birth_date_shamsi ?? '';
    $birth_date_gregorian = $player->birth_date_gregorian ?? '';
    $insurance_expiry_date_shamsi = $player->insurance_expiry_date_shamsi ?? '';
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

<div class="sc-submit-documents-form">
    <h2>اطلاعات بازیکن</h2>
    <p class="description">لطفاً اطلاعات و مدارک خود را با دقت وارد کنید. پس از بررسی توسط مدیر، حساب شما فعال خواهد شد.</p>
    
    <?php wc_print_notices(); ?>
    
    <form method="POST" enctype="multipart/form-data" class="woocommerce-form">
        <?php wp_nonce_field('sc_submit_documents', 'sc_documents_nonce'); ?>
        
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
            
            <p class="form-row form-row-last">
                <label for="birth_date_gregorian">تاریخ تولد (میلادی)</label>
                <span style="font-size: 12px;">تاریخ تولد میلادی شما به صورت اتوماتیک توسط سیستم از تاریخ تولد شمسی شما تبدیل می شود  </span><br>
                <input type="text" name="birth_date_gregorian_display" id="birth_date_gregorian" value="<?php echo esc_attr($birth_date_gregorian); ?>" class="gregorian-date-input" placeholder="مثلاً 2021/05/05" readonly>
                <input type="hidden" name="birth_date_gregorian" id="birth_date_gregorian_hidden" value="<?php echo esc_attr($birth_date_gregorian); ?>">
            </p>
            
            <p class="form-row form-row-first">
                <label for="insurance_expiry_date_shamsi">تاریخ انقضا بیمه (شمسی)</label>
                <input type="text" name="insurance_expiry_date_shamsi" id="insurance_expiry_date_shamsi" value="<?php echo esc_attr($insurance_expiry_date_shamsi); ?>" class="persian-date-input" placeholder="مثلاً 1403/12/29" readonly>
                <input type="hidden" name="insurance_expiry_date_gregorian" id="insurance_expiry_date_gregorian" value="">
    
            </p>
        </div>
        
        <div class="sc-form-section">
            <h3>اطلاعات تماس</h3>
            <p>لطفا در انتخاب شماره همراه دقت فرمایید و به صورت فرمت *********09 وارد کنید - تمامی اطلاع رسانی ها به شماره های زیر ارسال خواهد شد.</p>
            
            <p class="form-row form-row-first">
                <label for="player_phone">شماره موبایل بازیکن</label>
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
        </div>
        
        <div class="sc-form-section">
            
            <h3>مدارک و تصاویر</h3>
            <p class="description">حداکثر حجم هر فایل: 5 مگابایت. فرمت‌های مجاز: JPG, PNG, GIF, WEBP</p>
            
            <p class="form-row">
                <label for="personal_photo">عکس پرسنلی</label>
                <input type="file"  name="personal_photo" id="personal_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <?php if (!empty($personal_photo)) : ?>
                    <div class="sc-image-preview" style="margin-top: 10px;">
                        <img src="<?php echo esc_url($personal_photo); ?>" alt="عکس پرسنلی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                <?php endif; ?>
            </p>
            
            <p class="form-row">
                <label for="id_card_photo">عکس کارت ملی</label>
                <input type="file" name="id_card_photo" id="id_card_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <?php if (!empty($id_card_photo)) : ?>
                    <div class="sc-image-preview" style="margin-top: 10px;">
                        <img src="<?php echo esc_url($id_card_photo); ?>" alt="عکس کارت ملی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                <?php endif; ?>
            </p>
            
            <p class="form-row">
                <label for="sport_insurance_photo">عکس بیمه ورزشی</label>
                <input type="file" name="sport_insurance_photo" id="sport_insurance_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" >
                <?php if (!empty($sport_insurance_photo)) : ?>
                    <div class="sc-image-preview" style="margin-top: 10px;">
                        <img src="<?php echo esc_url($sport_insurance_photo); ?>" alt="عکس بیمه ورزشی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                <?php endif; ?>
            </p>
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
                <textarea name="additional_info" placeholder="هر آنچه که در موارد فوق نبودند و نیاز هست به مربی  گفته شود را اینجا ذکر" id="additional_info" rows="3" class="input-text"><?php echo esc_textarea($additional_info); ?></textarea>
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
                            <?php echo !empty($courses_text) ? $courses_text : "<span style='color: #999;'>شما هنوز در دوره‌ای ثبت نام نکردید</span>"; ?>
                        </div>
                    </div>
                </div>
            </div>
          
        </div>
        
        <p class="form-row">
            <button type="submit" name="sc_submit_documents" class="button" value="1">
                <?php echo $player ? 'بروزرسانی اطلاعات' : 'ثبت اطلاعات'; ?>
            </button>
        </p>
    </form>
</div>