<?php
/**
 * کارت QR حضور و غیاب — پیشخوان بازیکن یا مشاهده ادمین
 *
 * @var array $sc_qr_card keys: member_id, member_name, image_url, download_url, error, context
 */
if (!defined('ABSPATH')) {
    exit;
}

if (empty($sc_qr_card) || !is_array($sc_qr_card)) {
    return;
}

$sc_qr_context      = ($sc_qr_card['context'] ?? 'public') === 'admin' ? 'admin' : 'public';
$sc_qr_member_id    = (int) ($sc_qr_card['member_id'] ?? 0);
$sc_qr_member_name  = (string) ($sc_qr_card['member_name'] ?? '');
$sc_qr_image_url    = (string) ($sc_qr_card['image_url'] ?? '');
$sc_qr_download_url = (string) ($sc_qr_card['download_url'] ?? '');
$sc_qr_error        = (string) ($sc_qr_card['error'] ?? '');
$sc_qr_has_image    = $sc_qr_image_url !== '' && $sc_qr_error === '';

if ($sc_qr_has_image) {
    echo '<link rel="preload" as="image" href="' . esc_url($sc_qr_image_url) . '">' . "\n";
}

if ($sc_qr_context === 'admin') :
    ?>
    <div class="sc-member-view-card sc-member-view-qr-card">
        <h3 class="sc-member-view-card-title">کد QR حضور و غیاب</h3>
        <?php if ($sc_qr_has_image) : ?>
            <div class="sc-attendance-qr-admin-preview">
                <img src="<?php echo esc_url($sc_qr_image_url); ?>" width="200" height="200" alt="QR <?php echo esc_attr($sc_qr_member_name); ?>" decoding="async" fetchpriority="high">
                <div class="sc-attendance-qr-admin-preview__actions">
                    <a href="<?php echo esc_url($sc_qr_download_url !== '' ? $sc_qr_download_url : $sc_qr_image_url); ?>" class="button" download="attendance-qr-<?php echo esc_attr((string) $sc_qr_member_id); ?>.png">دانلود PNG</a>
                    <button type="button" class="button sc-regenerate-member-qr" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_attendance_qr_regenerate')); ?>">تولید QR جدید</button>
                </div>
                <p class="description">هش اختصاصی در پایگاه داده ذخیره شده و داخل QR به‌صورت <code>SC1:…</code> قرار دارد.</p>
            </div>
        <?php else : ?>
            <div class="sc-attendance-qr-admin-preview sc-attendance-qr-admin-preview--error">
                <p class="description" style="color:#b32d2e;"><?php echo esc_html($sc_qr_error !== '' ? $sc_qr_error : 'QR در دسترس نیست.'); ?></p>
                <?php if ($sc_qr_member_id > 0) : ?>
                    <button type="button" class="button sc-regenerate-member-qr" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_attendance_qr_regenerate')); ?>">تلاش مجدد</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return;
endif;
?>
<section class="sc-player-qr-card" aria-label="کد QR حضور و غیاب">
    <div class="sc-player-qr-card__inner">
        <div class="sc-player-qr-card__content">
            <span class="sc-player-qr-card__badge">📱 کارت حضور دیجیتال</span>
            <h3 class="sc-player-qr-card__title">QR اختصاصی شما</h3>
            <p class="sc-player-qr-card__desc">این کد را هنگام ورود به باشگاه به مربی نشان دهید تا حضور شما به‌صورت خودکار ثبت شود. کد منحصربه‌فرد است و قابل جعل نیست.</p>
            <?php if ($sc_qr_has_image) : ?>
                <div class="sc-player-qr-card__actions">
                    <a href="<?php echo esc_url($sc_qr_download_url !== '' ? $sc_qr_download_url : $sc_qr_image_url); ?>"
                       class="sc-player-qr-card__btn sc-player-qr-card__btn--primary sc-player-qr-download"
                       data-filename="attendance-qr-<?php echo esc_attr((string) $sc_qr_member_id); ?>.png">دانلود QR</a>
                    <button type="button" class="sc-player-qr-card__btn sc-player-qr-print">چاپ</button>
                </div>
            <?php else : ?>
                <p class="sc-player-qr-card__error"><?php echo esc_html($sc_qr_error !== '' ? $sc_qr_error : 'QR در دسترس نیست.'); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($sc_qr_has_image) : ?>
            <div class="sc-player-qr-card__visual">
                <img src="<?php echo esc_url($sc_qr_image_url); ?>" width="220" height="220" alt="QR حضور <?php echo esc_attr($sc_qr_member_name); ?>" class="sc-player-qr-image" decoding="async" fetchpriority="high">
                <?php if ($sc_qr_member_name !== '') : ?>
                    <div class="sc-player-qr-card__name"><?php echo esc_html($sc_qr_member_name); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
