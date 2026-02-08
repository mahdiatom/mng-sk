<?php
global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';

$coach_id = isset($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
$coach = $coach_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $coaches_table WHERE id = %d", $coach_id)) : null;

// دریافت دوره‌های مربی
$coach_courses = [];
if ($coach_id) {
    $coach_courses = $wpdb->get_col($wpdb->prepare(
        "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
        $coach_id
    ));
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
                <th><label>نوع تسویه</label></th>
                <td>
                    <label>
                        <input type="radio" name="settlement_type" value="fixed" <?php checked($coach ? $coach->settlement_type : 'fixed', 'fixed'); ?>>
                        مقداری
                    </label>
                    <label style="margin-right: 20px;">
                        <input type="radio" name="settlement_type" value="percentage" <?php checked($coach ? $coach->settlement_type : '', 'percentage'); ?>>
                        درصدی
                    </label>
                </td>
            </tr>
            
            <tr>
                <th><label for="settlement_amount">مبلغ / درصد تسویه</label></th>
                <td><input type="number" name="settlement_amount" id="settlement_amount" value="<?php echo $coach ? esc_attr($coach->settlement_amount) : '0'; ?>" step="0.01" min="0" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label>دوره‌های مربی</label></th>
                <td>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <?php if (!empty($all_courses)): ?>
                            <?php foreach ($all_courses as $course): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="courses[]" value="<?php echo $course->id; ?>" 
                                           <?php checked(in_array($course->id, $coach_courses)); ?>>
                                    <?php echo esc_html($course->title); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>هیچ دوره فعالی وجود ندارد.</p>
                        <?php endif; ?>
                    </div>
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

