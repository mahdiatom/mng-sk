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
$coach_photo = !empty($coach->personal_photo) ? $coach->personal_photo : '';
$coach_certificate_photo = !empty($coach->coaching_certificate_photo) ? $coach->coaching_certificate_photo : '';
$coach_certificate_expiry = !empty($coach->coaching_certificate_expiry_date_shamsi) ? $coach->coaching_certificate_expiry_date_shamsi : (function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '');
$coach_certificate_notice = function_exists('sc_get_coach_certificate_expiry_notice_data')
    ? sc_get_coach_certificate_expiry_notice_data($coach)
    : [];
$coach_info_page = function_exists('sc_get_coach_info_page_post') ? sc_get_coach_info_page_post() : null;
$coach_info_page_html = function_exists('sc_get_coach_info_page_content_html') ? sc_get_coach_info_page_content_html() : '';
$coach_rules_accepted = !empty($coach->club_rules_accepted);
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
<div class="wrap sc-coach-panel-wrap sc-coach-my-profile-page">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? 'ویرایش اطلاعات من' : 'اطلاعات من'; ?>
    </h1>
    <?php if (!$is_edit) : ?>
        <a href="<?php echo esc_url(add_query_arg('edit', '1', $base_url)); ?>" class="page-title-action">ویرایش اطلاعات من</a>
    <?php else : ?>
        <a href="<?php echo esc_url($base_url); ?>" class="page-title-action">بازگشت به اطلاعات من</a>
    <?php endif; ?>
    <hr class="wp-header-end">
    <p class="sc-coach-my-profile-subtitle">
        <?php echo $is_edit
            ? 'فیلدهای زیر را ویرایش کرده و ذخیره کنید. نوع تسویه، دوره‌ها، سطح مربی‌گری و وضعیت فقط توسط مدیر قابل تغییر است.'
            : 'اطلاعات پروفایل شما. برای ویرایش روی دکمه بالا کلیک کنید.'; ?>
    </p>

    <?php if ($sc_status === 'updated') : ?>
        <div class="notice notice-success is-dismissible"><p>اطلاعات با موفقیت به‌روزرسانی شد.</p></div>
    <?php elseif ($sc_status === 'upload_error') : ?>
        <div class="notice notice-error is-dismissible"><p>آپلود تصویر انجام نشد. فقط تصاویر JPG، PNG، GIF یا WEBP با حداکثر حجم ۱ مگابایت مجاز هستند.</p></div>
    <?php elseif ($sc_status === 'error') : ?>
        <div class="notice notice-error is-dismissible"><p>خطا در ذخیره. لطفاً فیلدهای اجباری را پر کنید.</p></div>
    <?php endif; ?>
    <?php if (!empty($coach_certificate_notice['message'])) : ?>
        <div class="notice <?php echo ($coach_certificate_notice['status'] ?? '') === 'expired' ? 'notice-error' : 'notice-warning'; ?>">
            <p><?php echo esc_html($coach_certificate_notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_edit) : ?>
        <form method="post" action="" enctype="multipart/form-data" class="sc-coach-my-profile-form" id="sc-coach-my-profile-form">
            <?php wp_nonce_field('sc_coach_profile_edit', 'sc_coach_profile_nonce'); ?>
            <table class="form-table sc_form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="first_name">نام <span class="required">*</span></label></th>
                        <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($coach->first_name); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="last_name">نام خانوادگی <span class="required">*</span></label></th>
                        <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($coach->last_name); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="national_id">کد ملی <span class="required">*</span></label></th>
                        <td><input type="text" name="national_id" id="national_id" value="<?php echo esc_attr($coach->national_id); ?>" required maxlength="10" pattern="[0-9]{10}" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mobile_phone">شماره موبایل <span class="required">*</span></label></th>
                        <td><input type="text" name="mobile_phone" id="mobile_phone" value="<?php echo esc_attr($coach->mobile_phone); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gender">جنسیت</label></th>
                        <td>
                            <select name="gender" id="gender" class="regular-text">
                                <option value="">انتخاب کنید</option>
                                <option value="male" <?php selected($coach->gender, 'male'); ?>>مرد</option>
                                <option value="female" <?php selected($coach->gender, 'female'); ?>>زن</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="specialization">تخصص</label></th>
                        <td><input type="text" name="specialization" id="specialization" value="<?php echo esc_attr($coach->specialization); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">سطح مربی‌گری</th>
                        <td>
                            <input type="text" class="regular-text" value="<?php echo esc_attr($coach->coaching_level ?: '-'); ?>" disabled>
                            <p class="description">سطح مربی‌گری فقط توسط مدیر قابل تغییر است.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coaching_experience">سابقه مربی‌گری (سال)</label></th>
                        <td><input type="number" name="coaching_experience" id="coaching_experience" value="<?php echo esc_attr($coach->coaching_experience); ?>" min="0" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sports_history">سوابق ورزشی</label></th>
                        <td><textarea name="sports_history" id="sports_history" rows="4" class="large-text"><?php echo esc_textarea($coach->sports_history); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="personal_photo_file">عکس پرسنلی</label></th>
                        <td>
                            <div class="sc-coach-direct-upload">
                                <input type="file" name="personal_photo_file" id="personal_photo_file" class="sc-coach-direct-upload__input" accept="image/jpeg,image/png,image/gif,image/webp" data-preview-target="#personal_photo_preview">
                                <label for="personal_photo_file" class="button-secondary sc-upload-btn">انتخاب تصویر</label>
                            </div>
                            <p class="description">فرمت‌های JPG، PNG، GIF و WEBP تا حداکثر حجم ۱ مگابایت مجاز هستند.</p>
                            <div class="img_photo_prev" id="personal_photo_preview"<?php echo $coach_photo === '' ? ' hidden' : ''; ?>>
                                <?php if ($coach_photo !== '') : ?>
                                    <img src="<?php echo esc_url($coach_photo); ?>" alt="عکس پرسنلی">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coaching_certificate_photo_file">عکس مدرک مربیگری</label></th>
                        <td>
                            <div class="sc-coach-direct-upload">
                                <input type="file" name="coaching_certificate_photo_file" id="coaching_certificate_photo_file" class="sc-coach-direct-upload__input" accept="image/jpeg,image/png,image/gif,image/webp" data-preview-target="#certificate_photo_preview">
                                <label for="coaching_certificate_photo_file" class="button-secondary sc-upload-btn">انتخاب تصویر</label>
                            </div>
                            <p class="description">فرمت‌های JPG، PNG، GIF و WEBP تا حداکثر حجم ۱ مگابایت مجاز هستند.</p>
                            <div class="img_photo_prev" id="certificate_photo_preview"<?php echo $coach_certificate_photo === '' ? ' hidden' : ''; ?>>
                                <?php if ($coach_certificate_photo !== '') : ?>
                                    <img src="<?php echo esc_url($coach_certificate_photo); ?>" alt="عکس مدرک مربیگری">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coaching_certificate_expiry_date_shamsi">تاریخ انقضای مدرک مربیگری</label></th>
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
                        <th scope="row"><label for="password">تغییر رمز عبور</label></th>
                        <td>
                            <input type="password" name="password" id="password" value="" class="regular-text" autocomplete="new-password">
                            <p class="description">در صورت تمایل به تغییر رمز عبور، فیلد را پر کنید؛ در غیر این صورت خالی بگذارید.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ($coach_info_page_html !== '') : ?>
                <div class="sc-coach-my-profile-extra-card">
                    <h3><?php echo esc_html($coach_info_page ? $coach_info_page->post_title : 'توضیحات تکمیلی'); ?></h3>
                    <div class="sc-coach-info-page-box__content">
                        <?php echo wp_kses_post($coach_info_page_html); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="sc-coach-my-profile-extra-card sc-coach-my-profile-rules">
                <?php if ($coach_rules_accepted) : ?>
                    <label>
                        <input type="checkbox" checked disabled>
                        <span>قوانین و مقررات باشگاه قبلاً توسط شما تایید شده است.</span>
                    </label>
                    <input type="hidden" name="club_rules_accepted" value="1">
                <?php else : ?>
                    <label>
                        <input type="checkbox" name="club_rules_accepted" value="1" required>
                        <span>تایید قوانین و مقررات باشگاه</span>
                    </label>
                    <p class="description">این تایید فقط یک بار ثبت می‌شود و بعد از آن دیگر قابل تغییر نیست.</p>
                <?php endif; ?>
            </div>

            <p class="submit">
                <input type="submit" name="submit_coach_profile" class="button button-primary" value="ذخیره تغییرات">
                <a href="<?php echo esc_url($base_url); ?>" class="button">انصراف</a>
            </p>
        </form>
        <script>
        (function () {
            var inputs = document.querySelectorAll('.sc-coach-direct-upload__input');
            inputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    var target = document.querySelector(input.getAttribute('data-preview-target') || '');
                    if (!target) return;
                    var file = input.files && input.files[0];
                    if (!file) return;
                    var url = URL.createObjectURL(file);
                    target.hidden = false;
                    target.innerHTML = '<img src="' + url + '" alt="">';
                });
            });
        })();
        </script>
    <?php else : ?>
        <div class="sc-coach-profile-hero">
            <?php if ($coach_photo !== '') : ?>
                <span class="sc-coach-profile-avatar"><img src="<?php echo esc_url($coach_photo); ?>" alt=""></span>
            <?php else : ?>
                <span class="sc-coach-profile-avatar sc-coach-profile-avatar--initials" aria-hidden="true"><?php echo esc_html($view_initials); ?></span>
            <?php endif; ?>
            <div class="sc-coach-profile-hero-text">
                <h2><?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?></h2>
                <p><?php echo esc_html($coach->mobile_phone ?: $coach->national_id); ?></p>
            </div>
        </div>

        <table class="form-table sc_form-table sc-coach-my-profile-view-table">
            <tbody>
                <tr>
                    <th scope="row">نام</th>
                    <td><?php echo esc_html($coach->first_name); ?></td>
                </tr>
                <tr>
                    <th scope="row">نام خانوادگی</th>
                    <td><?php echo esc_html($coach->last_name); ?></td>
                </tr>
                <tr>
                    <th scope="row">کد ملی</th>
                    <td><?php echo esc_html($coach->national_id); ?></td>
                </tr>
                <tr>
                    <th scope="row">موبایل</th>
                    <td><?php echo esc_html($coach->mobile_phone ?: '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">جنسیت</th>
                    <td><?php echo esc_html($coach->gender === 'male' ? 'مرد' : ($coach->gender === 'female' ? 'زن' : '-')); ?></td>
                </tr>
                <tr>
                    <th scope="row">تخصص</th>
                    <td><?php echo esc_html($coach->specialization ?: '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">سطح مربی‌گری</th>
                    <td><?php echo esc_html($coach->coaching_level ?: '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">سابقه مربی‌گری (سال)</th>
                    <td><?php echo esc_html($coach->coaching_experience !== null && $coach->coaching_experience !== '' ? $coach->coaching_experience : '-'); ?></td>
                </tr>
                <tr>
                    <th scope="row">سوابق ورزشی</th>
                    <td><?php echo $coach->sports_history ? nl2br(esc_html($coach->sports_history)) : '-'; ?></td>
                </tr>
                <tr>
                    <th scope="row">تاریخ انقضای مدرک مربیگری</th>
                    <td>
                        <?php echo esc_html($coach_certificate_expiry ?: '-'); ?>
                        <?php if (!empty($coach_certificate_notice['status'])) : ?>
                            <span class="sc-badge <?php echo ($coach_certificate_notice['status'] === 'expired' || $coach_certificate_notice['status'] === 'today') ? 'sc-badge--danger' : 'sc-badge--soft'; ?>">
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
                    <th scope="row">وضعیت</th>
                    <td><?php echo $coach->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                </tr>
                <?php if ($coach_certificate_photo !== '') : ?>
                    <tr>
                        <th scope="row">مدرک مربیگری</th>
                        <td>
                            <div class="img_photo_prev">
                                <a href="<?php echo esc_url($coach_certificate_photo); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo esc_url($coach_certificate_photo); ?>" alt="عکس مدرک مربیگری">
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($coach->user_id) && function_exists('sc_staff_qr_render_user_card')) : ?>
            <div class="sc-coach-my-profile-extra-card sc-coach-qr-panel">
                <h3>QR تردد من</h3>
                <p class="description">این QR برای ثبت تردد شماست. امکان تغییر یا تولید مجدد توسط خودتان وجود ندارد.</p>
                <?php sc_staff_qr_render_user_card((int) $coach->user_id, 'coach-my-profile', 220); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
