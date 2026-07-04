<?php
if ( ! defined('ABSPATH') ) exit;
if(!isset($_GET['coach_id'])){
?>
    <h1>افزودن مربی جدید</h1>
    <p>لطفا برای افزودن مربی  جدید به بخش کاربران-> افزودن کاربر بروید و نقش کاربر را روی مربی قرار دهید.</p>
    <a class="sc_button" href="<?php echo admin_url('user-new.php'); ?>">بخش کاربران</a>
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
                        <option value="مبتدی" <?php selected($coach ? $coach->coaching_level : '', 'مبتدی'); ?>>مبتدی</option>
                        <option value="متوسط" <?php selected($coach ? $coach->coaching_level : '', 'متوسط'); ?>>متوسط</option>
                        <option value="پیشرفته" <?php selected($coach ? $coach->coaching_level : '', 'پیشرفته'); ?>>پیشرفته</option>
                        <option value="استاد" <?php selected($coach ? $coach->coaching_level : '', 'استاد'); ?>>استاد</option>
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
                    <?php $coach_photo = ($coach && !empty($coach->personal_photo)) ? $coach->personal_photo : ''; ?>
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
                            <input type="radio" name="settlement_type" value="fixed" id="settlement_type_fixed" <?php checked($coach ? $coach->settlement_type : 'fixed', 'fixed'); ?>>
                            <span>ثابت</span>
                        </label>
                        <label class="sc-coach-settlement-option">
                            <input type="radio" name="settlement_type" value="percentage" id="settlement_type_percentage" <?php checked($coach ? $coach->settlement_type : '', 'percentage'); ?>>
                            <span>درصدی</span>
                        </label>
                        <label class="sc-coach-settlement-option">
                            <input type="radio" name="settlement_type" value="both" id="settlement_type_both" <?php checked($coach ? $coach->settlement_type : '', 'both'); ?>>
                            <span>ثابت و درصدی</span>
                        </label>
                    </div>
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

