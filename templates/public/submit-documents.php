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
}

// دریافت اطلاعات کاربر از ووکامرس
$user = wp_get_current_user();
$billing_phone = get_user_meta($user->ID, 'billing_phone', true);
if (empty($player_phone) && $billing_phone) {
    $player_phone = $billing_phone;
}

  // نمایش دوره‌های بازیکن
        global $wpdb;
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $courses_table = $wpdb->prefix . 'sc_courses';
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d AND mc.status = 'active' AND c.deleted_at IS NULL 
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
    <h2>ارسال مدارک و اطلاعات</h2>
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
                <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
            </p>
            
            <p class="form-row form-row-last">
                <label for="birth_date_gregorian">تاریخ تولد (میلادی)</label>
                <input type="date" name="birth_date_gregorian" id="birth_date_gregorian" value="<?php echo esc_attr($birth_date_gregorian); ?>">
            </p>
            
            <p class="form-row form-row-first">
                <label for="insurance_expiry_date_shamsi">تاریخ انقضا بیمه (شمسی)</label>
                <input type="text" name="insurance_expiry_date_shamsi" id="insurance_expiry_date_shamsi" value="<?php echo esc_attr($insurance_expiry_date_shamsi); ?>" class="persian-date-input" placeholder="مثلاً 1403/12/29" readonly>
                <input type="hidden" name="insurance_expiry_date_gregorian" id="insurance_expiry_date_gregorian" value="">
                <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
            </p>
        </div>
        
        <div class="sc-form-section">
            <h3>اطلاعات تماس</h3>
            
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
                <input type="file" name="personal_photo" id="personal_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <?php if (!empty($personal_photo)) : ?>
                    <div class="sc-image-preview" style="margin-top: 10px;">
                        <img src="<?php echo esc_url($personal_photo); ?>" alt="عکس پرسنلی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                        <p class="description">عکس فعلی</p>
                    </div>
                <?php endif; ?>
            </p>
            
            <p class="form-row">
                <label for="id_card_photo">عکس کارت ملی</label>
                <input type="file" name="id_card_photo" id="id_card_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <?php if (!empty($id_card_photo)) : ?>
                    <div class="sc-image-preview" style="margin-top: 10px;">
                        <img src="<?php echo esc_url($id_card_photo); ?>" alt="عکس کارت ملی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                        <p class="description">عکس فعلی</p>
                    </div>
                <?php endif; ?>
            </p>
            
            <p class="form-row">
                <label for="sport_insurance_photo">عکس بیمه ورزشی</label>
                <input type="file" name="sport_insurance_photo" id="sport_insurance_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <?php if (!empty($sport_insurance_photo)) : ?>
                    <div class="sc-image-preview" style="margin-top: 10px;">
                        <img src="<?php echo esc_url($sport_insurance_photo); ?>" alt="عکس بیمه ورزشی" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px;">
                        <p class="description">عکس فعلی</p>
                    </div>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="sc-form-section">
            <h3>اطلاعات تکمیلی</h3>
            
            <p class="form-row">
                <label for="medical_condition">مشکلات پزشکی</label>
                <textarea name="medical_condition" id="medical_condition" rows="4" class="input-text"><?php echo esc_textarea($medical_condition); ?></textarea>
            </p>
            
            <p class="form-row">
                <label for="sports_history">سوابق ورزشی</label>
                <textarea name="sports_history" id="sports_history" rows="4" class="input-text"><?php echo esc_textarea($sports_history); ?></textarea>
            </p>
            
            <p class="form-row">
                <label for="additional_info">توضیحات اضافی</label>
                <textarea name="additional_info" id="additional_info" rows="3" class="input-text"><?php echo esc_textarea($additional_info); ?></textarea>
            </p>

            <p class="form-row">
                <label>وضعیت سلامت تأیید شده</label>
                <label><input name="health_verified" type="checkbox" <?php checked($health_verified, 1); ?> value="1"> بله</label>
            </p>

            <p class="form-row">
                <label>اطلاعات تأیید شده</label>
                <label><input name="info_verified" type="checkbox" <?php checked($info_verified, 1); ?> value="1"> بله</label>
            </p>
            <p class="form-row">
                    <span class="slider round"> وضعیت بازیکن  :   <?php echo $is_active ? "فعال" : "غیرفعال" ?></span> 
                
            </p>
            <p class="form-row">
                
                    <span class="slider round"> دوره های فعال :   <?php echo !empty($courses_text) ?   $courses_text : "شما هنوز در دوره ای ثبت نام نکردید یا دوره فعالی ندارید" ?></span> 
                
            </p>
          
        </div>
        
        <p class="form-row">
            <button type="submit" name="sc_submit_documents" class="button" value="1">
                <?php echo $player ? 'بروزرسانی اطلاعات' : 'ثبت اطلاعات'; ?>
            </button>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // تبدیل تاریخ انقضا بیمه شمسی به میلادی
    $('#insurance_expiry_date_shamsi').on('change', function() {
        var shamsiDate = $(this).val();
        if (shamsiDate && shamsiDate.includes('/')) {
            var parts = shamsiDate.split('/');
            if (parts.length === 3) {
                var jy = parseInt(parts[0]);
                var jm = parseInt(parts[1]);
                var jd = parseInt(parts[2]);
                
                // تبدیل به میلادی (تابع JavaScript)
                var gregorian = jalaliToGregorian(jy, jm, jd);
                if (gregorian && gregorian.length === 3) {
                    var gregorianDate = gregorian[0] + '-' + 
                                       (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '-' + 
                                       (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
                    $('#insurance_expiry_date_gregorian').val(gregorianDate);
                }
            }
        }
    });
    
    // تبدیل قبل از ارسال فرم
    $('form').on('submit', function() {
        $('#insurance_expiry_date_shamsi').trigger('change');
    });
    
    // تابع تبدیل شمسی به میلادی (JavaScript)
    function jalaliToGregorian(jy, jm, jd) {
        var gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;
        var days = (365 * jy) + (parseInt(jy / 33) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 
                   78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        gy += 400 * (parseInt(days / 146097));
        days = days % 146097;
        if (days > 36524) {
            gy += 100 * (parseInt(--days / 36524));
            days = days % 36524;
            if (days >= 365) days++;
        }
        gy += 4 * (parseInt(days / 1461));
        days = days % 1461;
        if (days > 365) {
            gy += parseInt((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var gd = days + 1;
        var sal_a = [0, 31, ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0)) ? 29 : 28,
                     31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > sal_a[gm]) {
            gd -= sal_a[gm];
            gm++;
        }
        return [gy, gm, gd];
    }
});
</script>
