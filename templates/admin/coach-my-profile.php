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
$coach_certificate_photo = !empty($coach->coaching_certificate_photo) ? $coach->coaching_certificate_photo : '';
$coach_certificate_expiry = !empty($coach->coaching_certificate_expiry_date_shamsi) ? $coach->coaching_certificate_expiry_date_shamsi : (function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '');
$coach_certificate_notice = function_exists('sc_get_coach_certificate_expiry_notice_data')
    ? sc_get_coach_certificate_expiry_notice_data($coach)
    : [];
$coach_info_page = function_exists('sc_get_coach_info_page_post') ? sc_get_coach_info_page_post() : null;
$coach_info_page_html = function_exists('sc_get_coach_info_page_content_html') ? sc_get_coach_info_page_content_html() : '';
$coach_rules_accepted = !empty($coach->club_rules_accepted);
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <div class="sc-coach-panel-title-row">
            <h1 class="sc-coach-panel-title">اطلاعات من</h1>
            <?php if (!$is_edit) : ?>
                <a href="<?php echo esc_url(add_query_arg('edit', '1', $base_url)); ?>" class="sc_button button-primary">ویرایش اطلاعات من</a>
            <?php endif; ?>
        </div>
        <p class="sc-coach-panel-desc"><?php echo $is_edit ? 'فیلدهای زیر را ویرایش کرده و ذخیره کنید. نوع تسویه، دوره‌ها، سطح مربی‌گری و وضعیت فقط توسط مدیر قابل تغییر است.' : 'اطلاعات پروفایل شما. برای ویرایش روی دکمه بالا کلیک کنید.'; ?></p>
    </div>
    <?php if ($sc_status === 'updated') : ?>
        <div class="notice notice-success is-dismissible"><p>اطلاعات با موفقیت به‌روزرسانی شد.</p></div>
    <?php elseif ($sc_status === 'error') : ?>
        <div class="notice notice-error is-dismissible"><p>خطا در ذخیره. لطفاً فیلدهای اجباری را پر کنید.</p></div>
    <?php endif; ?>
    <?php if (!empty($coach_certificate_notice['message'])) : ?>
        <div class="notice <?php echo ($coach_certificate_notice['status'] ?? '') === 'expired' ? 'notice-error' : 'notice-warning'; ?>">
            <p><?php echo esc_html($coach_certificate_notice['message']); ?></p>
        </div>
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
                    <th>سطح مربی‌گری</th>
                    <td>
                        <?php echo esc_html($coach->coaching_level ?: '-'); ?>
                        <p class="description">سطح مربی‌گری فقط توسط مدیر قابل تغییر است.</p>
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
                    <th><label for="coaching_certificate_photo_txt">عکس مدرک مربیگری</label></th>
                    <td>
                        <input type="text" name="coaching_certificate_photo" id="coaching_certificate_photo_txt" class="regular-text" value="<?php echo esc_attr($coach_certificate_photo); ?>" placeholder="آدرس تصویر یا آپلود کنید">
                        <button type="button" class="button-secondary sc-upload-btn" id="btn_coaching_certificate_photo">انتخاب تصویر</button>
                        <?php if ($coach_certificate_photo !== '') : ?>
                            <div class="sc-image-preview img_photo_prev" style="margin-top: 10px;">
                                <img src="<?php echo esc_url($coach_certificate_photo); ?>" alt="عکس مدرک مربیگری" style="max-width: 160px; height: auto; border-radius: 10px;">
                            </div>
                        <?php endif; ?>
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
                        <p class="description">برای یادآوری انقضا و هشدارهای مربوط به مدرک مربیگری استفاده می‌شود.</p>
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
            <?php if ($coach_info_page_html !== '') : ?>
                <div class="sc-coach-info-page-box" style="margin: 24px 0; padding: 18px 20px; border: 1px solid #dcdcde; border-radius: 12px; background: #fff; font-family: IRANYekanXFaNum, Tahoma, sans-serif; line-height: 2;">
                    <h3 style="margin-top: 0;"><?php echo esc_html($coach_info_page ? $coach_info_page->post_title : 'توضیحات تکمیلی'); ?></h3>
                    <div class="sc-coach-info-page-box__content">
                        <?php echo wp_kses_post($coach_info_page_html); ?>
                    </div>
                </div>
            <?php endif; ?>
            <div style="margin: 18px 0 8px; padding: 14px 16px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px;">
                <?php if ($coach_rules_accepted) : ?>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" checked disabled>
                        <span>قوانین و مقررات باشگاه قبلاً توسط شما تایید شده است.</span>
                    </label>
                    <input type="hidden" name="club_rules_accepted" value="1">
                <?php else : ?>
                    <label style="display:flex; gap:8px; align-items:center;">
                        <input type="checkbox" name="club_rules_accepted" value="1" required>
                        <span>تایید قوانین و مقررات باشگاه</span>
                    </label>
                    <p class="description" style="margin:8px 0 0;">این تایید فقط یک بار ثبت می‌شود و بعد از آن دیگر قابل تغییر نیست.</p>
                <?php endif; ?>
            </div>
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
                    <th>تاریخ انقضای مدرک مربیگری</th>
                    <td>
                        <?php echo esc_html($coach_certificate_expiry ?: '-'); ?>
                        <?php if (!empty($coach_certificate_notice['status'])) : ?>
                            <span class="sc-badge <?php echo ($coach_certificate_notice['status'] === 'expired' || $coach_certificate_notice['status'] === 'today') ? 'sc-badge--danger' : 'sc-badge--soft'; ?>" style="margin-right:8px;">
                                <?php
                                if ($coach_certificate_notice['status'] === 'expired') {
                                    echo 'منقضی شده';
                                } elseif ($coach_certificate_notice['status'] === 'today') {
                                    echo 'امروز';
                                } else {
                                    echo 'نزدیک به انقضا';
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>وضعیت</th>
                    <td><?php echo $coach->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                </tr>
            </table>
            <?php if ($coach_certificate_photo !== '') : ?>
                <div style="margin-top: 24px;">
                    <h3 style="margin-bottom: 12px;">مدرک مربیگری</h3>
                    <a href="<?php echo esc_url($coach_certificate_photo); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url($coach_certificate_photo); ?>" alt="عکس مدرک مربیگری" style="max-width: 220px; height: auto; border-radius: 12px; border: 1px solid #dcdcde;">
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
