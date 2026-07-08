<?php
/**
 * کارت QR پرسنل — ویرایش کاربر وردپرس
 *
 * @var array $sc_staff_qr_card
 */
if (!defined('ABSPATH')) {
    exit;
}
if (empty($sc_staff_qr_card) || !is_array($sc_staff_qr_card)) {
    return;
}
$user_id = (int) ($sc_staff_qr_card['user_id'] ?? 0);
$user_name = (string) ($sc_staff_qr_card['user_name'] ?? '');
$image_url = (string) ($sc_staff_qr_card['image_url'] ?? '');
$short_code = (string) ($sc_staff_qr_card['short_code'] ?? '');
$error = (string) ($sc_staff_qr_card['error'] ?? '');
$has_image = $image_url !== '' && $error === '';
?>
<div class="sc-member-view-card sc-member-view-qr-card sc-staff-qr-card">
    <?php if ($has_image) : ?>
        <div class="sc-attendance-qr-admin-preview sc-attendance-qr-admin-preview--active">
            <div class="sc-attendance-qr-admin-preview__visual">
                <img src="<?php echo esc_url($image_url); ?>" width="200" height="200" alt="QR <?php echo esc_attr($user_name); ?>" decoding="async">
                <?php if ($short_code !== '') : ?>
                    <div class="sc-attendance-qr-short-code"><?php echo esc_html($short_code); ?></div>
                <?php endif; ?>
            </div>
            <p class="description">QR پرسنل — برای ثبت تردد در جلسات اسکن شود. پیشوند: <code>SC2:</code><?php if (!empty($sc_staff_qr_card['readonly'])) : ?> — امکان تغییر توسط کاربر وجود ندارد.<?php endif; ?></p>
        </div>
    <?php else : ?>
        <p class="description" style="color:#b32d2e;"><?php echo esc_html($error !== '' ? $error : 'QR در دسترس نیست.'); ?></p>
    <?php endif; ?>
</div>
