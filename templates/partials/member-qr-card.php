<?php
/**
 * کارت QR حضور و غیاب — پیشخوان بازیکن یا مشاهده ادمین
 *
 * @var array $sc_qr_card keys: member_id, member_name, image_url, download_url, short_code, codes, can_manage, error, context
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
$sc_qr_short_code   = (string) ($sc_qr_card['short_code'] ?? '');
$sc_qr_error        = (string) ($sc_qr_card['error'] ?? '');
$sc_qr_codes        = is_array($sc_qr_card['codes'] ?? null) ? $sc_qr_card['codes'] : [];
$sc_qr_can_manage   = !empty($sc_qr_card['can_manage']);
$sc_qr_has_image    = $sc_qr_image_url !== '' && $sc_qr_error === '';

$sc_qr_status_labels = [
    'active'   => 'فعال',
    'inactive' => 'غیرفعال',
    'disabled' => 'غیرفعال موقت',
];

if ($sc_qr_has_image) {
    echo '<link rel="preload" as="image" href="' . esc_url($sc_qr_image_url) . '">' . "\n";
}

if ($sc_qr_context === 'admin') :
    ?>
    <div class="sc-member-view-card sc-member-view-qr-card sc-attendance-qr-admin-card" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>">
        <h3 class="sc-member-view-card-title">کد QR حضور و غیاب</h3>
        <?php if ($sc_qr_has_image) : ?>
            <div class="sc-attendance-qr-admin-preview sc-attendance-qr-admin-preview--active">
                <div class="sc-attendance-qr-admin-preview__visual">
                    <img src="<?php echo esc_url($sc_qr_image_url); ?>" width="200" height="200" alt="QR <?php echo esc_attr($sc_qr_member_name); ?>" decoding="async" fetchpriority="high">
                    <?php if ($sc_qr_short_code !== '') : ?>
                        <div class="sc-attendance-qr-short-code"><?php echo esc_html($sc_qr_short_code); ?></div>
                    <?php endif; ?>
                </div>
                <div class="sc-attendance-qr-admin-preview__actions">
                    <a href="<?php echo esc_url($sc_qr_download_url !== '' ? $sc_qr_download_url : $sc_qr_image_url); ?>" class="button" download="attendance-qr-<?php echo esc_attr((string) $sc_qr_member_id); ?>.png">دانلود PNG</a>
                    <?php if ($sc_qr_can_manage) :
                        $active_code = null;
                        foreach ($sc_qr_codes as $code_item) {
                            if (!empty($code_item['is_active'])) {
                                $active_code = $code_item;
                                break;
                            }
                        }
                        if ($active_code) : ?>
                            <button type="button" class="button sc-qr-toggle-disabled" data-qr-id="<?php echo esc_attr((string) $active_code['id']); ?>" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-action="disable">غیرفعال موقت</button>
                        <?php endif; ?>
                        <button type="button" class="button button-primary sc-regenerate-member-qr" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_attendance_qr_regenerate')); ?>">تولید QR جدید</button>
                    <?php endif; ?>
                </div>
                <p class="description">QR فعال برای ثبت حضور استفاده می‌شود. QRهای قبلی حفظ می‌شوند و قابل بازیابی هستند.</p>
            </div>

            <?php if ($sc_qr_can_manage && count($sc_qr_codes) > 1) : ?>
                <div class="sc-attendance-qr-codes-list">
                    <h4 class="sc-attendance-qr-codes-list__title">سایر QRهای ذخیره‌شده</h4>
                    <div class="sc-attendance-qr-codes-grid">
                        <?php foreach ($sc_qr_codes as $code_item) :
                            if (!empty($code_item['is_active'])) {
                                continue;
                            }
                            $status = (string) ($code_item['status'] ?? 'inactive');
                            $status_label = $sc_qr_status_labels[$status] ?? $status;
                            ?>
                            <div class="sc-attendance-qr-code-item sc-attendance-qr-code-item--<?php echo esc_attr($status); ?>" data-qr-id="<?php echo esc_attr((string) ($code_item['id'] ?? 0)); ?>">
                                <img src="<?php echo esc_url((string) ($code_item['image_url'] ?? '')); ?>" width="96" height="96" alt="">
                                <div class="sc-attendance-qr-code-item__meta">
                                    <strong class="sc-attendance-qr-short-code"><?php echo esc_html((string) ($code_item['short_code'] ?? '')); ?></strong>
                                    <span class="sc-attendance-qr-code-item__status"><?php echo esc_html($status_label); ?></span>
                                </div>
                                <div class="sc-attendance-qr-code-item__actions">
                                    <?php if ($status === 'disabled') : ?>
                                        <button type="button" class="button button-small sc-qr-toggle-disabled" data-qr-id="<?php echo esc_attr((string) $code_item['id']); ?>" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-action="enable">فعال کردن</button>
                                    <?php else : ?>
                                        <button type="button" class="button button-small sc-qr-set-active" data-qr-id="<?php echo esc_attr((string) $code_item['id']); ?>" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>">تنظیم به‌عنوان QR فعال</button>
                                        <button type="button" class="button button-small sc-qr-toggle-disabled" data-qr-id="<?php echo esc_attr((string) $code_item['id']); ?>" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-action="disable">غیرفعال موقت</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="sc-attendance-qr-admin-preview sc-attendance-qr-admin-preview--error">
                <p class="description" style="color:#b32d2e;"><?php echo esc_html($sc_qr_error !== '' ? $sc_qr_error : 'QR در دسترس نیست.'); ?></p>
                <?php if ($sc_qr_member_id > 0 && $sc_qr_can_manage) : ?>
                    <button type="button" class="button sc-regenerate-member-qr" data-member-id="<?php echo esc_attr((string) $sc_qr_member_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_attendance_qr_regenerate')); ?>">تلاش مجدد</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($sc_qr_can_manage) : ?>
    <div id="sc-qr-otp-modal" class="sc-qr-otp-modal" hidden aria-hidden="true">
        <div class="sc-qr-otp-modal__backdrop"></div>
        <div class="sc-qr-otp-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sc-qr-otp-modal-title">
            <h3 id="sc-qr-otp-modal-title">تأیید تولید QR جدید</h3>
            <p class="sc-qr-otp-modal__hint">کد تأیید به شماره تنظیم‌شده ارسال می‌شود.</p>
            <p class="sc-qr-otp-modal__phone" hidden></p>
            <label for="sc-qr-otp-input" class="screen-reader-text">کد تأیید</label>
            <input type="text" id="sc-qr-otp-input" class="regular-text sc-qr-otp-modal__input" inputmode="numeric" maxlength="6" placeholder="کد ۶ رقمی">
            <div class="sc-qr-otp-modal__actions">
                <button type="button" class="button sc-qr-otp-modal__cancel">انصراف</button>
                <button type="button" class="button button-primary sc-qr-otp-modal__confirm">تأیید و تولید QR</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
                <?php if ($sc_qr_short_code !== '') : ?>
                    <div class="sc-attendance-qr-short-code sc-attendance-qr-short-code--public"><?php echo esc_html($sc_qr_short_code); ?></div>
                <?php endif; ?>
                <?php if ($sc_qr_member_name !== '') : ?>
                    <div class="sc-player-qr-card__name"><?php echo esc_html($sc_qr_member_name); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
