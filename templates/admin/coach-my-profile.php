<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
$coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}
global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$coach = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coaches_table WHERE id = %d", $coach_id));
if (!$coach) {
    wp_die('اطلاعات مربی یافت نشد.');
}
$is_edit = isset($_GET['edit']) && $_GET['edit'] === '1';
$base_url = admin_url('admin.php?page=sc-coach-my-profile');
$sc_status = isset($_GET['sc_status']) ? sanitize_text_field($_GET['sc_status']) : '';
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <div class="sc-coach-panel-title-row">
            <h1 class="sc-coach-panel-title">اطلاعات من</h1>
            <?php if (!$is_edit) : ?>
                <a href="<?php echo esc_url(add_query_arg('edit', '1', $base_url)); ?>" class="sc_button button-primary">ویرایش اطلاعات من</a>
            <?php endif; ?>
        </div>
        <p class="sc-coach-panel-desc"><?php echo $is_edit ? 'فیلدهای زیر را ویرایش کرده و ذخیره کنید. نوع تسویه، دوره‌ها و وضعیت فقط توسط مدیر قابل تغییر است.' : 'اطلاعات پروفایل شما. برای ویرایش روی دکمه بالا کلیک کنید.'; ?></p>
    </div>
    <?php if ($sc_status === 'updated') : ?>
        <div class="notice notice-success is-dismissible"><p>اطلاعات با موفقیت به‌روزرسانی شد.</p></div>
    <?php elseif ($sc_status === 'error') : ?>
        <div class="notice notice-error is-dismissible"><p>خطا در ذخیره. لطفاً فیلدهای اجباری را پر کنید.</p></div>
    <?php endif; ?>

    <?php if ($is_edit) : ?>
        <div class="sc-coach-panel-card" style="padding: 24px; margin-top: 0;">
        <form method="post" action="">
            <?php wp_nonce_field('sc_coach_profile_edit', 'sc_coach_profile_nonce'); ?>
            <table class="form-table table_list_info_coach_">
                <tr>
                    <th><label for="first_name">نام <span class="required">*</span></label></th>
                    <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($coach->first_name); ?>" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="last_name">نام خانوادگی <span class="required">*</span></label></th>
                    <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($coach->last_name); ?>" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="national_id">کد ملی <span class="required">*</span></label></th>
                    <td><input type="text" name="national_id" id="national_id" value="<?php echo esc_attr($coach->national_id); ?>" required maxlength="10" pattern="[0-9]{10}" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="mobile_phone">شماره موبایل <span class="required">*</span></label></th>
                    <td><input type="text" name="mobile_phone" id="mobile_phone" value="<?php echo esc_attr($coach->mobile_phone); ?>" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="gender">جنسیت</label></th>
                    <td>
                        <select name="gender" id="gender">
                            <option value="">انتخاب کنید</option>
                            <option value="male" <?php selected($coach->gender, 'male'); ?>>مرد</option>
                            <option value="female" <?php selected($coach->gender, 'female'); ?>>زن</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="specialization">تخصص</label></th>
                    <td><input type="text" name="specialization" id="specialization" value="<?php echo esc_attr($coach->specialization); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="coaching_level">سطح مربی‌گری</label></th>
                    <td>
                        <select name="coaching_level" id="coaching_level">
                            <option value="">انتخاب کنید</option>
                            <option value="مبتدی" <?php selected($coach->coaching_level, 'مبتدی'); ?>>مبتدی</option>
                            <option value="متوسط" <?php selected($coach->coaching_level, 'متوسط'); ?>>متوسط</option>
                            <option value="پیشرفته" <?php selected($coach->coaching_level, 'پیشرفته'); ?>>پیشرفته</option>
                            <option value="استاد" <?php selected($coach->coaching_level, 'استاد'); ?>>استاد</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="coaching_experience">سابقه مربی‌گری (سال)</label></th>
                    <td><input type="number" name="coaching_experience" id="coaching_experience" value="<?php echo esc_attr($coach->coaching_experience); ?>" min="0" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="sports_history">سوابق ورزشی</label></th>
                    <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"><?php echo esc_textarea($coach->sports_history); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="personal_photo_txt">عکس پرسنلی</label></th>
                    <td>
                        <?php $coach_photo = !empty($coach->personal_photo) ? $coach->personal_photo : ''; ?>
                        <input type="text" name="personal_photo" id="personal_photo_txt" class="regular-text" value="<?php echo esc_attr($coach_photo); ?>" placeholder="آدرس تصویر یا آپلود کنید">
                        <button type="button" class="button-secondary sc-upload-btn" id="btn_personal_photo">انتخاب تصویر</button>
                        <?php if ($coach_photo !== '') : ?>
                            <div class="sc-image-preview img_photo_prev" style="margin-top: 10px;">
                                <img src="<?php echo esc_url($coach_photo); ?>" alt="عکس پرسنلی" style="max-width: 160px; height: auto; border-radius: 10px;">
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="password">تغییر رمز عبور</label></th>
                    <td>
                        <input type="password" name="password" id="password" value="" class="regular-text" autocomplete="new-password">
                        <p class="description">در صورت تمایل به تغییر رمز عبور، فیلد را پر کنید؛ در غیر این صورت خالی بگذارید.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit_coach_profile" class="button button-primary" value="ذخیره تغییرات">
                <a href="<?php echo esc_url($base_url); ?>" class="button">انصراف</a>
            </p>
        </form>
        </div>
    <?php else : ?>
        <div class="sc-coach-panel-card sc-coach-profile-card" style="padding: 24px;">
            <div class="sc-coach-profile-hero">
                <?php
                $view_photo = !empty($coach->personal_photo) ? $coach->personal_photo : '';
                $view_initials = '';
                if (!empty($coach->first_name)) {
                    $view_initials .= mb_substr((string) $coach->first_name, 0, 1);
                }
                if (!empty($coach->last_name)) {
                    $view_initials .= mb_substr((string) $coach->last_name, 0, 1);
                }
                if ($view_initials === '') {
                    $view_initials = 'م';
                }
                ?>
                <?php if ($view_photo !== '') : ?>
                    <span class="sc-coach-profile-avatar"><img src="<?php echo esc_url($view_photo); ?>" alt=""></span>
                <?php else : ?>
                    <span class="sc-coach-profile-avatar sc-coach-profile-avatar--initials" aria-hidden="true"><?php echo esc_html($view_initials); ?></span>
                <?php endif; ?>
                <div class="sc-coach-profile-hero-text">
                    <h2><?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?></h2>
                    <p><?php echo esc_html($coach->mobile_phone ?: $coach->national_id); ?></p>
                </div>
            </div>
            <table class="form-table table_list_info_coach">
                <tr>
                    <th>نام</th>
                    <td><?php echo esc_html($coach->first_name); ?></td>
                </tr>
                <tr>
                    <th>نام خانوادگی</th>
                    <td><?php echo esc_html($coach->last_name); ?></td>
                </tr>
                <tr>
                    <th>کد ملی</th>
                    <td><?php echo esc_html($coach->national_id); ?></td>
                </tr>
                <tr>
                    <th>موبایل</th>
                    <td><?php echo esc_html($coach->mobile_phone ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>جنسیت</th>
                    <td><?php echo esc_html($coach->gender === 'male' ? 'مرد': 'زن'); ?></td>
                </tr>
                <tr>
                    <th>تخصص</th>
                    <td><?php echo esc_html($coach->specialization ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>سطح مربی‌گری</th>
                    <td><?php echo esc_html($coach->coaching_level ?: '-'); ?></td>
                </tr>
                <tr>
                    <th>سابقه مربی‌گری (سال)</th>
                    <td><?php echo esc_html($coach->coaching_experience !== null ? $coach->coaching_experience : '-'); ?></td>
                </tr>
                <tr>
                    <th>سوابق ورزشی</th>
                    <td><?php echo $coach->sports_history ? nl2br(esc_html($coach->sports_history)) : '-'; ?></td>
                </tr>
                <tr>
                    <th>وضعیت</th>
                    <td><?php echo $coach->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>
</div>
