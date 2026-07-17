<?php
if ( ! defined('ABSPATH') ) exit;
if(!isset($_GET['coach_id'])){
    $sc_reg_blocked = function_exists('sc_registration_limit_can_create_user') && !sc_registration_limit_can_create_user();
?>
    <h1>افزودن مربی جدید</h1>
    <?php if ($sc_reg_blocked) : ?>
        <div class="sc-reg-limit-blocked-banner">
            <strong>ثبت مربی جدید غیرفعال است.</strong>
            <?php echo esc_html(function_exists('sc_registration_limit_admin_message') ? sc_registration_limit_admin_message() : 'ظرفیت کاربران سامانه تکمیل شده است.'); ?>
        </div>
        <p>
            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=pro_features')); ?>">تنظیمات محدودیت ثبت‌نام</a>
        </p>
    <?php else : ?>
        <p>لطفا برای افزودن مربی  جدید به بخش کاربران-> افزودن کاربر بروید و نقش کاربر را روی مربی قرار دهید.</p>
        <a class="sc_button" href="<?php echo admin_url('user-new.php'); ?>">بخش کاربران</a>
    <?php endif; ?>
<?php
    exit;
}
global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';

$coach_id = isset($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
$coach = $coach_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $coaches_table WHERE id = %d", $coach_id)) : null;

// دریافت تخصیص دوره/شعبه مربی
$coach_course_assignments = [];
if ($coach_id) {
    $coach_courses_data = $wpdb->get_results($wpdb->prepare(
        "SELECT course_id, chapter_name, salary_percentage FROM $course_coaches_table WHERE coach_id = %d",
        $coach_id
    ));
    foreach ($coach_courses_data as $cc) {
        $coach_course_assignments[(int) $cc->course_id][(string) $cc->chapter_name] = $cc;
    }
}

// دریافت تمام دوره‌های فعال
$all_courses = $wpdb->get_results(
    "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);

$user_id = $coach ? $coach->user_id : null;
$wp_user = $user_id ? get_userdata($user_id) : null;
$coach_photo = ($coach && !empty($coach->personal_photo)) ? $coach->personal_photo : '';
$coach_certificate_photo = ($coach && !empty($coach->coaching_certificate_photo)) ? $coach->coaching_certificate_photo : '';
$coach_certificate_expiry = ($coach && !empty($coach->coaching_certificate_expiry_date_shamsi)) ? $coach->coaching_certificate_expiry_date_shamsi : (function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '');
$coach_certificate_notice = ($coach && function_exists('sc_get_coach_certificate_expiry_notice_data'))
    ? sc_get_coach_certificate_expiry_notice_data($coach)
    : [];
$coach_info_page = function_exists('sc_get_coach_info_page_post') ? sc_get_coach_info_page_post() : null;
$coach_info_page_html = function_exists('sc_get_coach_info_page_content_html') ? sc_get_coach_info_page_content_html() : '';
$coach_rules_accepted = $coach ? !empty($coach->club_rules_accepted) : false;
$coach_settlement_type = $coach ? (string) ($coach->settlement_type ?? '') : 'fixed';
$coach_settlement_type_valid = in_array($coach_settlement_type, ['fixed', 'percentage', 'both'], true);
if (!$coach_settlement_type_valid) {
    // مقدار خراب (مثلاً 0.000000 به‌خاطر باگ فرمت ذخیره) — هیچ گزینه‌ای از قبل انتخاب نشود تا کاربر دوباره انتخاب کند
    $coach_settlement_type = '';
}
?>

<div class="wrap sc-coach-edit-header">
    <h1><?php echo $coach_id ? 'ویرایش مربی' : 'افزودن مربی'; ?></h1>
    <?php if ($coach_id && $coach) : ?>
        <p class="sc-coach-edit-subtitle">
            <?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?>
            <?php if (!empty($coach->specialization)) : ?>
                <span class="sc-coach-edit-badge"><?php echo esc_html($coach->specialization); ?></span>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
<div class="wrap sc-coach-edit-wrap">
    <?php if (!empty($coach_certificate_notice['message'])) : ?>
        <div class="notice <?php echo ($coach_certificate_notice['status'] ?? '') === 'expired' ? 'notice-error' : 'notice-warning'; ?>">
            <p><?php echo esc_html($coach_certificate_notice['message']); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($coach && !$coach_settlement_type_valid) : ?>
        <div class="notice notice-error">
            <p>نوع تسویه این مربی در دیتابیس خراب است (مقدار فعلی: <code><?php echo esc_html((string) ($coach->settlement_type ?? '')); ?></code>). لطفاً دوباره «درصدی» یا گزینه مناسب را انتخاب و ذخیره کنید.</p>
        </div>
    <?php endif; ?>
    <form method="post" action="" id="coach-form" class="sc-coach-edit-form">
        <?php wp_nonce_field('sc_add_coach', 'sc_coach_nonce'); ?>
        <input type="hidden" name="coach_id" value="<?php echo $coach_id; ?>">
        
        <table class="form-table table_add_coach sc_form-table">
            <tr>
                <th><label for="first_name">نام <span class="required">*</span></label></th>
                <td><input type="text" name="first_name" id="first_name" value="<?php echo $coach ? esc_attr($coach->first_name) : ''; ?>" required class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="last_name">نام خانوادگی <span class="required">*</span></label></th>
                <td><input type="text" name="last_name" id="last_name" value="<?php echo $coach ? esc_attr($coach->last_name) : ''; ?>" required class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="national_id">کد ملی <span class="required">*</span></label></th>
                <td><input type="text" name="national_id" id="national_id" value="<?php echo $coach ? esc_attr($coach->national_id) : ''; ?>" required maxlength="10" pattern="[0-9]{10}" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="mobile_phone">شماره موبایل <span class="required">*</span></label></th>
                <td><input type="text" name="mobile_phone" id="mobile_phone" value="<?php echo $coach ? esc_attr($coach->mobile_phone) : ''; ?>" required class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="gender">جنسیت</label></th>
                <td>
                    <select name="gender" id="gender" style="width: 350px;">
                        <option value="">انتخاب کنید</option>
                        <option value="male" <?php selected($coach ? $coach->gender : '', 'male'); ?>>مرد</option>
                        <option value="female" <?php selected($coach ? $coach->gender : '', 'female'); ?>>زن</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="specialization">تخصص</label></th>
                <td><input type="text" name="specialization" id="specialization" value="<?php echo $coach ? esc_attr($coach->specialization) : ''; ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="coaching_level" >سطح مربیگری</label></th>
                <td>
                    <select name="coaching_level" id="coaching_level "   style="width: 350px;">
                        <option value="">انتخاب کنید</option>
                        <?php
                        $coaching_levels = function_exists('sc_get_coaching_level_options') ? sc_get_coaching_level_options() : [];
                        foreach ($coaching_levels as $level_option) :
                        ?>
                            <option value="<?php echo esc_attr($level_option); ?>" <?php selected($coach ? $coach->coaching_level : '', $level_option); ?>><?php echo esc_html($level_option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="coaching_experience" >سابقه مربیگری (سال)</label></th>
                <td><input type="number" name="coaching_experience" id="coaching_experience" value="<?php echo $coach ? esc_attr($coach->coaching_experience) : ''; ?>" min="0" class="small-text"  style="width: 350px;"></td>
            </tr>
            
            <tr>
                <th><label for="sports_history">سابقه ورزشی</label></th>
                <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"><?php echo $coach ? esc_textarea($coach->sports_history) : ''; ?></textarea></td>
            </tr>
            <tr>
                <th><label for="personal_photo_txt">عکس پرسنلی</label></th>
                <td>
                    <input type="text" name="personal_photo" id="personal_photo_txt" class="regular-text" value="<?php echo esc_attr($coach_photo); ?>" placeholder="آدرس تصویر یا آپلود کنید">
                    <button type="button" class="button-secondary sc-upload-btn" id="btn_personal_photo">انتخاب تصویر</button>
                    <?php if ($coach_photo !== '') : ?>
                        <div class="sc-image-preview img_photo_prev" style="margin-top: 10px;">
                            <img src="<?php echo esc_url($coach_photo); ?>" alt="عکس پرسنلی" style="max-width: 160px; height: auto; border-radius: 10px;">
                        </div>
                    <?php endif; ?>
                    <p class="description">این عکس در لیست مربیان نمایش داده می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th><label for="coaching_certificate_photo_txt">عکس مدرک مربیگری</label></th>
                <td>
                    <input type="text" name="coaching_certificate_photo" id="coaching_certificate_photo_txt" class="regular-text" value="<?php echo esc_attr($coach_certificate_photo); ?>" placeholder="آدرس تصویر یا آپلود کنید">
                    <button type="button" class="button-secondary sc-upload-btn" id="btn_coaching_certificate_photo">انتخاب تصویر</button>
                    <?php if ($coach_certificate_photo !== '') : ?>
                        <div class="sc-image-preview img_photo_prev" style="margin-top: 10px;">
                            <img src="<?php echo esc_url($coach_certificate_photo); ?>" alt="عکس مدرک مربیگری" style="max-width: 160px; height: auto; border-radius: 10px;">
                        </div>
                    <?php endif; ?>
                    <p class="description">تصویر یا اسکن مدرک مربیگری مربی را در این بخش ذخیره کنید.</p>
                </td>
            </tr>
            <tr>
                <th><label for="coaching_certificate_expiry_date_shamsi">تاریخ انقضای مدرک مربیگری</label></th>
                <td>
                    <input type="text"
                           name="coaching_certificate_expiry_date_shamsi"
                           id="coaching_certificate_expiry_date_shamsi"
                           value="<?php echo esc_attr($coach_certificate_expiry); ?>"
                           class="regular-text persian-date-input"
                           placeholder="مثلاً 1405/12/29"
                           readonly>
                    <p class="description">این تاریخ برای هشدار سیستمی و پیامک یادآوری مدرک مربیگری استفاده می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th><label for="is_private_enabled">کلاس خصوصی</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="is_private_enabled" id="is_private_enabled" value="1" <?php checked($coach ? (int) $coach->is_private_enabled : 1, 1); ?>>
                        این مربی مجاز به پذیرش کلاس خصوصی است
                    </label>
                </td>
            </tr>
            
            <tr class="sc-coach-settlement-row">
                <th><label>نوع تسویه</label></th>
                <td>
                    <div class="sc-coach-settlement-radios">
                        <label class="sc-coach-settlement-option">
                            <input type="radio" name="settlement_type" value="fixed" id="settlement_type_fixed" <?php checked($coach_settlement_type, 'fixed'); ?> required>
                            <span>ثابت</span>
                        </label>
                        <label class="sc-coach-settlement-option">
                            <input type="radio" name="settlement_type" value="percentage" id="settlement_type_percentage" <?php checked($coach_settlement_type, 'percentage'); ?>>
                            <span>درصدی</span>
                        </label>
                        <label class="sc-coach-settlement-option">
                            <input type="radio" name="settlement_type" value="both" id="settlement_type_both" <?php checked($coach_settlement_type, 'both'); ?>>
                            <span>ثابت و درصدی</span>
                        </label>
                    </div>
                </td>
            </tr>
            
            <tr id="settlement_amount_row" style="<?php echo ($coach_settlement_type === 'percentage') ? 'display: none;' : ''; ?>">
                <th><label for="settlement_amount">مقدار تسویه ثابت ماهیانه (تومان)</label></th>
                <td>
                    <input type="text"
                           id="settlement_amount"
                           value="<?php echo ($coach && floatval($coach->settlement_amount) > 0) ? number_format($coach->settlement_amount, 0, '.', ',') : ''; ?>"
                           class="regular-text sc-coach-settlement-amount"
                           placeholder="0"
                           dir="ltr"
                           inputmode="numeric"
                           autocomplete="off">
                    <input type="hidden" name="coach_settlement_amount_save" id="coach_settlement_amount_save" value="<?php echo $coach ? esc_attr((string)(int)floatval($coach->settlement_amount)) : '0'; ?>">
                    <p class="description">مبلغ ثابت ماهیانه که در پایان هر ماه شمسی به مربی پرداخت می‌شود.</p>
                </td>
            </tr>
            
            <tr id="courses_row" class="sc-coach-courses-row">
                <th><label>دوره‌ها و شعبه‌ها</label></th>
                <td>
                    <div class="sc-coach-courses-panel">
                        <?php if (!empty($all_courses)): ?>
                            <table class="widefat striped sc-coach-courses-table">
                                <thead>
                                    <tr>
                                        <th>دوره</th>
                                        <th>شعبه</th>
                                        <th>فعال</th>
                                        <th class="course-percentage-col">درصد دستمزد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_courses as $course):
                                        $course_chapters = function_exists('sc_get_course_chapters') ? sc_get_course_chapters((int) $course->id) : [];
                                        if (empty($course_chapters)) {
                                            continue;
                                        }
                                        foreach ($course_chapters as $chapter_name):
                                            $assigned = $coach_course_assignments[(int) $course->id][(string) $chapter_name] ?? null;
                                            $is_checked = !empty($assigned);
                                            $pct_val = $assigned ? floatval($assigned->salary_percentage) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html($course->title); ?></td>
                                                <td><?php echo esc_html($chapter_name); ?></td>
                                                <td style="text-align:center;">
                                                    <input type="checkbox"
                                                           name="coach_course_assign[<?php echo (int) $course->id; ?>][<?php echo esc_attr($chapter_name); ?>]"
                                                           value="1"
                                                           class="coach-course-assign-cb"
                                                           <?php checked($is_checked); ?>>
                                                </td>
                                                <td class="course-percentage-col">
                                                    <input type="number"
                                                           name="coach_course_percentage[<?php echo (int) $course->id; ?>][<?php echo esc_attr($chapter_name); ?>]"
                                                           value="<?php echo esc_attr($pct_val); ?>"
                                                           step="0.01"
                                                           min="0"
                                                           max="100"
                                                           class="small-text course-percentage"
                                                           style="width:90px;">
                                                    <span>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>هیچ دوره فعالی وجود ندارد.</p>
                        <?php endif; ?>
                    </div>
                    <p class="description">برای نوع تسویه «درصدی» یا «ثابت و درصدی»، درصد دستمزد هر ردیف را وارد کنید.</p>
                </td>
            </tr>
            
            <?php if ($coach && $wp_user): ?>
            <tr>
                <th><label for="username">نام کاربری</label></th>
                <td>
                    <input type="text" name="username" id="username" value="<?php echo esc_attr($wp_user->user_login); ?>" disabled class="regular-text">
                    <p class="description">نام کاربری قابل تغییر نیست</p>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <th><label for="username">نام کاربری</label></th>
                <td>
                    <input type="text" name="username" id="username" value="" class="regular-text">
                    <p class="description">اگر خالی باشد، از کد ملی استفاده می‌شود</p>
                </td>
            </tr>
            <?php endif; ?>
            
            <tr>
                <th><label for="password">رمز عبور</label></th>
                <td>
                    <input type="password" name="password" id="password" value="" class="regular-text">
                    <p class="description"><?php echo $coach_id ? 'برای تغییر رمز عبور، فیلد را پر کنید' : 'اگر خالی باشد، به صورت خودکار ایجاد می‌شود'; ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="is_active">وضعیت</label></th>
                <td>

                
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1" <?php checked($coach ? $coach->is_active : 1, 1); ?> >
                        <span class="slider round"></span>
                        فعال
                    </label>
                </td>
            </tr>
        </table>

        <?php if ($coach_info_page_html !== '') : ?>
            <div class="sc-coach-info-page-box" style="margin: 24px 0; padding: 18px 20px; border: 1px solid #dcdcde; border-radius: 12px; background: #fff; font-family: IRANYekanXFaNum, Tahoma, sans-serif; line-height: 2;">
                <h3 style="margin-top: 0;">
                    <?php echo esc_html($coach_info_page ? $coach_info_page->post_title : 'توضیحات تکمیلی'); ?>
                </h3>
                <div class="sc-coach-info-page-box__content">
                    <?php echo wp_kses_post($coach_info_page_html); ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin: 18px 0 8px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px;">
            <?php if ($coach_rules_accepted) : ?>
                <label style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox" checked disabled>
                    <span>قوانین و مقررات باشگاه قبلاً توسط این مربی تایید شده است.</span>
                </label>
                <input type="hidden" name="club_rules_accepted" value="1">
            <?php else : ?>
                <label style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox" name="club_rules_accepted" value="1" required>
                    <span>تایید قوانین و مقررات باشگاه</span>
                </label>
                <p class="description" style="margin:8px 0 0;">این تایید فقط یک بار انجام می‌شود و بعد از ثبت دیگر قابل تغییر نیست.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($user_id && function_exists('sc_staff_qr_render_user_card')) : ?>
        <div class="sc-coach-qr-panel" style="margin: 24px 0; padding: 20px; background: #fff; border: 1px solid #dcdcde; border-radius: 12px;">
            <h2 style="margin-top:0;">QR تردد مربی</h2>
            <p class="description">QR اختصاصی این مربی برای ثبت تردد — فقط مشاهده (تغییر توسط مربی امکان‌پذیر نیست).</p>
            <?php sc_staff_qr_render_user_card((int) $user_id, 'coach-edit', 240); ?>
        </div>
        <?php endif; ?>

        <p class="submit">
            <input type="submit" name="submit_coach" class="button button-primary" value="<?php echo $coach_id ? 'به‌روزرسانی' : 'ذخیره'; ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // مقدار تسویه ثابت: فقط ارقام از فیلد نمایشی قبل از ارسال در hidden قرار می‌گیرد (بدون ضرب یا تبدیل)
    var $settlementInput = $('#settlement_amount');
    var $settlementHidden = $('#coach_settlement_amount_save');
    if ($settlementInput.length && $settlementHidden.length) {
        // مقدار اولیه از فیلد نمایشی (برای ویرایش)
        var initialVal = $settlementInput.val().replace(/[^\d]/g, '');
        if (initialVal) $settlementHidden.val(initialVal);
        $settlementInput.on('input', function() {
            var v = $(this).val().replace(/[^\d]/g, '');
            $settlementHidden.val(v === '' ? '0' : v);
            if (v.length > 0) {
                var formatted = v.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                $(this).val(formatted);
            }
        });
        $('#coach-form').on('submit', function() {
            var v = $settlementInput.val().replace(/[^\d]/g, '');
            $settlementHidden.val(v === '' ? '0' : v);
        });
    }

    // تابع برای نمایش/پنهان کردن فیلدها بر اساس نوع تسویه
    function toggleSettlementFields() {
        var settlementType = $('input[name="settlement_type"]:checked').val();
        
        if (settlementType === 'fixed') {
            $('#settlement_amount_row').show();
            $('#courses_row').show();
            $('.course-percentage-col').hide();
        } else if (settlementType === 'both') {
            $('#settlement_amount_row').show();
            $('#courses_row').show();
            $('.course-percentage-col').show();
        } else {
            $('#settlement_amount_row').hide();
            $('#courses_row').show();
            $('.course-percentage-col').show();
        }
    }
    
    $('input[name="settlement_type"]').on('change', function() {
        toggleSettlementFields();
    });
    
    toggleSettlementFields();
});
</script>

