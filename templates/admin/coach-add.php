<?php
if ( ! defined('ABSPATH') ) exit;
if(!isset($_GET['coach_id'])){
?>
    <h1>افزودن مربی جدید</h1>
    <p>لطفا برای افزودن مربی  جدید به بخش کاربران-> افزودن کاربر بروید و نقش کاربر را روی مربی قرار دهید</p>
    <a class="button" href="<?php echo admin_url('user-new.php'); ?>">بخش کاربران</a>
<?php
    exit;
}
global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';

$coach_id = isset($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
$coach = $coach_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $coaches_table WHERE id = %d", $coach_id)) : null;

// دریافت دوره‌های مربی با درصد دستمزد
$coach_courses = [];
$coach_courses_percentage = [];
if ($coach_id) {
    $coach_courses_data = $wpdb->get_results($wpdb->prepare(
        "SELECT course_id, salary_percentage FROM $course_coaches_table WHERE coach_id = %d",
        $coach_id
    ));
    foreach ($coach_courses_data as $cc) {
        $coach_courses[] = $cc->course_id;
        $coach_courses_percentage[$cc->course_id] = floatval($cc->salary_percentage);
    }
}

// دریافت تمام دوره‌های فعال
$all_courses = $wpdb->get_results(
    "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);

$user_id = $coach ? $coach->user_id : null;
$wp_user = $user_id ? get_userdata($user_id) : null;
?>

<div class="wrap">
    <h1><?php echo $coach_id ? 'ویرایش مربی' : 'افزودن مربی'; ?></h1>
    
    <form method="post" action="" id="coach-form">
        <?php wp_nonce_field('sc_add_coach', 'sc_coach_nonce'); ?>
        <input type="hidden" name="coach_id" value="<?php echo $coach_id; ?>">
        
        <table class="form-table">
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
                    <select name="gender" id="gender">
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
                <th><label for="coaching_level">سطح مربیگری</label></th>
                <td>
                    <select name="coaching_level" id="coaching_level">
                        <option value="">انتخاب کنید</option>
                        <option value="مبتدی" <?php selected($coach ? $coach->coaching_level : '', 'مبتدی'); ?>>مبتدی</option>
                        <option value="متوسط" <?php selected($coach ? $coach->coaching_level : '', 'متوسط'); ?>>متوسط</option>
                        <option value="پیشرفته" <?php selected($coach ? $coach->coaching_level : '', 'پیشرفته'); ?>>پیشرفته</option>
                        <option value="استاد" <?php selected($coach ? $coach->coaching_level : '', 'استاد'); ?>>استاد</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="coaching_experience">سابقه مربیگری (سال)</label></th>
                <td><input type="number" name="coaching_experience" id="coaching_experience" value="<?php echo $coach ? esc_attr($coach->coaching_experience) : ''; ?>" min="0" class="small-text"></td>
            </tr>
            
            <tr>
                <th><label for="sports_history">سابقه ورزشی</label></th>
                <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"><?php echo $coach ? esc_textarea($coach->sports_history) : ''; ?></textarea></td>
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
            
            <tr>
                <th><label>نوع تسویه</label></th>
                <td>
                    <label>
                        <input type="radio" name="settlement_type" value="fixed" id="settlement_type_fixed" <?php checked($coach ? $coach->settlement_type : 'fixed', 'fixed'); ?>>
                        ثابت
                    </label>
                    <label style="margin-right: 20px;">
                        <input type="radio" name="settlement_type" value="percentage" id="settlement_type_percentage" <?php checked($coach ? $coach->settlement_type : '', 'percentage'); ?>>
                        درصدی
                    </label>
                    <label style="margin-right: 20px;">
                        <input type="radio" name="settlement_type" value="both" id="settlement_type_both" <?php checked($coach ? $coach->settlement_type : '', 'both'); ?>>
                        ثابت و درصدی
                    </label>
                </td>
            </tr>
            
            <tr id="settlement_amount_row" style="<?php echo ($coach && $coach->settlement_type === 'percentage') ? 'display: none;' : ''; ?>">
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
            
            <tr id="courses_row">
                <th><label>دوره‌های مربی</label></th>
                <td>
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <?php if (!empty($all_courses)): ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #ddd;">
                                        <th style="text-align: center; padding: 4px; width: 60px;">انتخاب</th>
                                        <th style="text-align: right; padding: 8px;">نام دوره</th>
                                        <th class="course-percentage-col" style="text-align: right; padding: 8px;">درصد دستمزد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_courses as $course): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 4px; text-align: center; width: 60px;">
                                                <input type="checkbox" 
                                                       name="courses[]" 
                                                       value="<?php echo $course->id; ?>" 
                                                       class="course-checkbox"
                                                       data-course-id="<?php echo $course->id; ?>"
                                                       <?php checked(in_array($course->id, $coach_courses)); ?>>
                                            </td>
                                            <td style="padding: 8px;">
                                                <label for="course_<?php echo $course->id; ?>" style="cursor: pointer;">
                                                    <?php echo esc_html($course->title); ?>
                                                </label>
                                            </td>
                                            <td class="course-percentage-col" style="padding: 8px;">
                                                <input type="number" 
                                                       name="course_percentage[<?php echo $course->id; ?>]" 
                                                       id="course_percentage_<?php echo $course->id; ?>"
                                                       value="<?php echo isset($coach_courses_percentage[$course->id]) ? esc_attr($coach_courses_percentage[$course->id]) : '0'; ?>" 
                                                       step="0.01" 
                                                       min="0" 
                                                       max="100"
                                                       class="small-text course-percentage"
                                                       style="width: 100px;"
                                                       <?php echo !in_array($course->id, $coach_courses) ? 'disabled' : ''; ?>>
                                                <span>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>هیچ دوره فعالی وجود ندارد.</p>
                        <?php endif; ?>
                    </div>
                    <p class="description">برای نوع تسویه «درصدی» یا «ثابت و درصدی»، دوره‌ها را انتخاب کنید و درصد دستمزد هر دوره را وارد کنید. برای «ثابت» فقط حقوق ماهانه اعمال می‌شود و ستون درصد پنهان است.</p>
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
        
        <p class="submit">
            <input type="submit" name="submit_coach" class="button button-primary" value="<?php echo $coach_id ? 'به‌روزرسانی' : 'ذخیره'; ?>">
            <a href="<?php echo admin_url('admin.php?page=sc-coaches'); ?>" class="button">انصراف</a>
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
            $('.course-percentage').prop('disabled', true);
        } else if (settlementType === 'both') {
            $('#settlement_amount_row').show();
            $('#courses_row').show();
            $('.course-percentage-col').show();
            $('.course-checkbox').each(function() {
                var courseId = $(this).data('course-id');
                var $pct = $('#course_percentage_' + courseId);
                if ($(this).is(':checked')) {
                    $pct.prop('disabled', false);
                } else {
                    $pct.prop('disabled', true);
                    $pct.val('0');
                }
            });
        } else {
            $('#settlement_amount_row').hide();
            $('#courses_row').show();
            $('.course-percentage-col').show();
            $('.course-checkbox:checked').each(function() {
                var courseId = $(this).data('course-id');
                $('#course_percentage_' + courseId).prop('disabled', false);
            });
        }
    }
    
    // تغییر نوع تسویه
    $('input[name="settlement_type"]').on('change', function() {
        toggleSettlementFields();
    });
    
    // فعال/غیرفعال کردن فیلد درصد بر اساس انتخاب دوره
    $('.course-checkbox').on('change', function() {
        var courseId = $(this).data('course-id');
        var percentageField = $('#course_percentage_' + courseId);
        
        if ($(this).is(':checked')) {
            percentageField.prop('disabled', false);
        } else {
            percentageField.prop('disabled', true);
            percentageField.val('0');
        }
    });
    
    // مقداردهی اولیه
    toggleSettlementFields();
    $('.course-checkbox').each(function() {
        $(this).trigger('change');
    });
});
</script>

