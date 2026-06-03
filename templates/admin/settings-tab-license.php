<?php
/**
 * تب لایسنس — تنظیمات SportClub Manager
 *
 * @package SportClub
 */

if (!defined('ABSPATH')) {
    exit;
}

$sc_license_active   = function_exists('sc_is_license_active') && sc_is_license_active();
$sc_license_response = function_exists('sc_license_get_response') ? sc_license_get_response() : null;
$sc_license_message  = function_exists('sc_license_get_message') ? sc_license_get_message() : '';
$sc_show_license_err = function_exists('sc_license_should_show_error') && sc_license_should_show_error();
$sc_license_email    = sc_license_get_stored_email();

$sc_lic_notice = isset($_GET['sc_lic']) ? sanitize_text_field(wp_unslash($_GET['sc_lic'])) : '';
if ($sc_lic_notice === 'activated' && $sc_license_active) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('لایسنس با موفقیت فعال شد.', 'sportclub-manager') . '</p></div>';
}
if ($sc_lic_notice === 'deactivated' && !$sc_license_active) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('لایسنس غیرفعال شد.', 'sportclub-manager') . '</p></div>';
}
if ($sc_lic_notice === 'failed') {
    $sc_fail_msg = get_transient('sc_license_activation_error_' . get_current_user_id());
    if ($sc_fail_msg === false || $sc_fail_msg === '') {
        $sc_fail_msg = $sc_license_message !== '' ? $sc_license_message : __('فعال‌سازی لایسنس ناموفق بود. کد یا ایمیل را بررسی کنید.', 'sportclub-manager');
    } else {
        delete_transient('sc_license_activation_error_' . get_current_user_id());
    }
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($sc_fail_msg) . '</p></div>';
}
?>

<?php if ($sc_license_active && $sc_license_response) : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="sc_license_deactivate"/>
        <div class="el-license-container sc-license-rtl">
            <h3 class="el-license-title">
                <span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
                <?php esc_html_e('اطلاعات لایسنس', 'sportclub-manager'); ?>
            </h3>
            <hr>
            <ul class="el-license-info">
                <li>
                    <div>
                        <span class="el-license-info-title"><?php esc_html_e('وضعیت', 'sportclub-manager'); ?></span>
                        <?php if (!empty($sc_license_response->is_valid)) : ?>
                            <span class="el-license-valid"><?php esc_html_e('معتبر', 'sportclub-manager'); ?></span>
                        <?php else : ?>
                            <span class="el-license-invalid"><?php esc_html_e('نامعتبر', 'sportclub-manager'); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <li>
                    <div>
                        <span class="el-license-info-title"><?php esc_html_e('نوع لایسنس', 'sportclub-manager'); ?></span>
                        <?php echo esc_html($sc_license_response->license_title ?? '—'); ?>
                    </div>
                </li>
                <li>
                    <div>
                        <span class="el-license-info-title"><?php esc_html_e('تاریخ انقضای لایسنس', 'sportclub-manager'); ?></span>
                        <?php echo esc_html($sc_license_response->expire_date ?? '—'); ?>
                        <?php if (!empty($sc_license_response->expire_renew_link)) : ?>
                            <a target="_blank" rel="noopener noreferrer" class="el-blue-btn" href="<?php echo esc_url($sc_license_response->expire_renew_link); ?>">
                                <?php esc_html_e('تمدید', 'sportclub-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </li>
                <li>
                    <div>
                        <span class="el-license-info-title"><?php esc_html_e('پایان پشتیبانی', 'sportclub-manager'); ?></span>
                        <?php echo esc_html($sc_license_response->support_end ?? '—'); ?>
                        <?php if (!empty($sc_license_response->support_renew_link)) : ?>
                            <a target="_blank" rel="noopener noreferrer" class="el-blue-btn" href="<?php echo esc_url($sc_license_response->support_renew_link); ?>">
                                <?php esc_html_e('تمدید پشتیبانی', 'sportclub-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </li>
                <li>
                    <div>
                        <span class="el-license-info-title"><?php esc_html_e('کلید لایسنس شما', 'sportclub-manager'); ?></span>
                        <?php
                        $lk = $sc_license_response->license_key ?? '';
                        if (strlen($lk) > 18) {
                            $masked = substr($lk, 0, 9) . 'XXXXXXXX-XXXXXXXX' . substr($lk, -9);
                        } else {
                            $masked = $lk;
                        }
                        ?>
                        <span class="el-license-key"><?php echo esc_html($masked); ?></span>
                    </div>
                </li>
            </ul>
            <p class="description">
                <?php esc_html_e('با لایسنس فعال، تمام بخش‌های افزونه (بازیکنان، صورتحساب، دوره‌ها و …) در دسترس است.', 'sportclub-manager'); ?>
            </p>
            <div class="el-license-active-btn">
                <?php wp_nonce_field('sc-license'); ?>
                <?php submit_button(__('غیرفعال‌سازی لایسنس', 'sportclub-manager'), 'delete', 'submit', false); ?>
            </div>
        </div>
    </form>
<?php else : ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="sc_license_activate"/>
        <div class="el-license-container sc-license-rtl">
            <h3 class="el-license-title">
                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                <?php esc_html_e('فعال‌سازی لایسنس SportClub Manager', 'sportclub-manager'); ?>
            </h3>
            <hr>

            <?php if ($sc_show_license_err && $sc_license_message !== '' && $sc_lic_notice !== 'failed') : ?>
                <div class="notice notice-error inline">
                    <p><?php echo esc_html($sc_license_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!sc_is_license_active()) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php esc_html_e('توجه:', 'sportclub-manager'); ?></strong>
                        <?php echo esc_html(sc_license_get_notice_text()); ?>
                    </p>
                    <p><?php esc_html_e('تا زمانی که لایسنس معتبر و فعال نباشد، بخش‌های اصلی افزونه (بازیکنان، صورتحساب، دوره‌ها، کیف پول، گزارش‌ها و …) کار نمی‌کنند. هشدار در تمام صفحات پیشخوان نمایش داده می‌شود.', 'sportclub-manager'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e('کد لایسنس و ایمیل خرید را وارد کنید تا افزونه فعال شود و به‌روزرسانی‌ها و پشتیبانی دریافت کنید.', 'sportclub-manager'); ?></p>

            <ol class="sc-license-help-list">
                <li><?php esc_html_e('کد لایسنس را پس از خرید از فروشنده دریافت کنید.', 'sportclub-manager'); ?></li>
                <li><?php esc_html_e('ایمیل باید همان ایمیلی باشد که هنگام خرید ثبت شده است.', 'sportclub-manager'); ?></li>
                <li><?php esc_html_e('هر لایسنس معمولاً به یک دامنه (آدرس سایت) محدود است.', 'sportclub-manager'); ?></li>
            </ol>

            <div class="el-license-field">
                <label for="sc_license_key"><?php esc_html_e('کد لایسنس', 'sportclub-manager'); ?></label>
                <input type="text" class="regular-text code" id="sc_license_key" name="sc_license_key" size="50"
                       placeholder="xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx" required="required" dir="ltr" autocomplete="off">
            </div>
            <div class="el-license-field">
                <label for="sc_license_email"><?php esc_html_e('ایمیل', 'sportclub-manager'); ?></label>
                <input type="email" class="regular-text" id="sc_license_email" name="sc_license_email" size="50"
                       value="<?php echo esc_attr($sc_license_email); ?>" required="required" dir="ltr">
                <p class="description"><?php esc_html_e('اخبار به‌روزرسانی محصول به این ایمیل ارسال می‌شود.', 'sportclub-manager'); ?></p>
            </div>
            <div class="el-license-active-btn">
                <?php wp_nonce_field('sc-license'); ?>
                <?php submit_button(__('فعال‌سازی لایسنس', 'sportclub-manager'), 'primary', 'submit', false); ?>
            </div>
        </div>
    </form>
<?php endif; ?>
