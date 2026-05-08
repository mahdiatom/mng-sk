<?php
if (!defined('ABSPATH')) {
    exit;
}

$decimal_places = 0;
$decimal_separator = '.';
$thousand_separator = ',';
if (function_exists('wc_get_price_decimals')) {
    $decimal_places = wc_get_price_decimals();
}
if (function_exists('wc_get_price_decimal_separator')) {
    $decimal_separator = wc_get_price_decimal_separator();
}
if (function_exists('wc_get_price_thousand_separator')) {
    $thousand_separator = wc_get_price_thousand_separator();
}

$public_status = isset($_GET['public_status']) ? sanitize_text_field($_GET['public_status']) : '';
$event_type_label = ($event->event_type === 'competition') ? 'مسابقه' : 'رویداد';
$public_link = add_query_arg('sc_public_event', (int) $event->id, home_url('/'));

$can_enroll = true;
$tooltip_message = '';
$today_shamsi = sc_get_today_shamsi();
if (!empty($event->start_date_gregorian) || !empty($event->end_date_gregorian)) {
    $start_date_shamsi = !empty($event->start_date_gregorian) ? sc_date_shamsi_date_only($event->start_date_gregorian) : '';
    $end_date_shamsi = !empty($event->end_date_gregorian) ? sc_date_shamsi_date_only($event->end_date_gregorian) : '';
    if (!empty($end_date_shamsi) && sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
        $can_enroll = false;
        $tooltip_message = 'زمان ثبت نام این رویداد تمام شده است.';
    }
    if (!empty($start_date_shamsi) && sc_compare_shamsi_dates($today_shamsi, $start_date_shamsi) < 0) {
        $can_enroll = false;
        $tooltip_message = 'زمان ثبت نام این رویداد هنوز شروع نشده است.';
    }
}

$enrolled_count = 0;
$remaining = 0;
if ($event->capacity) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $paid_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $invoices_table WHERE event_id = %d AND status IN ('paid', 'completed', 'processing')",
        $event->id
    ));
    $free_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $registrations_table WHERE event_id = %d AND invoice_id IS NULL",
        $event->id
    ));
    $enrolled_count = $paid_count + $free_count;
    $remaining = (int) $event->capacity - $enrolled_count;
    if ($remaining <= 0) {
        $can_enroll = false;
        $tooltip_message = 'ظرفیت این رویداد تکمیل شده است.';
    }
}

$formatted_price = number_format((float) $event->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
if ((float) $event->price <= 0) {
    $formatted_price = 'رایگان';
}
get_header();
?>

<div class="sc-event-detail-page">
    <div class="sc-event-detail-header">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="sc-back-link">بازگشت به صفحه اصلی</a>
        <h2><?php echo esc_html($event->name); ?></h2>
    </div>

    <div class="sc-event-detail-content">
        <?php if (!empty($event->image)) : ?>
            <div class="sc-event-detail-image">
                <img src="<?php echo esc_url($event->image); ?>" alt="<?php echo esc_attr($event->name); ?>">
            </div>
        <?php endif; ?>

        <div class="sc-event-detail-info">
            <?php if (!empty($event->description)) : ?>
                <div class="sc-event-detail-section">
                    <h3>توضیحات</h3>
                    <div class="sc-event-description"><?php echo wp_kses_post($event->description); ?></div>
                </div>
            <?php endif; ?>

            <div class="sc-event-detail-meta-grid">
                <div class="sc-event-detail-meta-item">
                    <span class="sc-event-meta-icon">💰</span>
                    <div>
                        <strong>قیمت</strong>
                        <p><?php echo esc_html($formatted_price); ?></p>
                    </div>
                </div>

                <?php if (!empty($event->event_time)) : ?>
                    <div class="sc-event-detail-meta-item">
                        <span class="sc-event-meta-icon">🕐</span>
                        <div>
                            <strong>زمان مسابقه / رویداد</strong>
                            <div class="sc-event-time"><?php echo wp_kses_post($event->event_time); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="sc-event-detail-meta-item">
                    <span class="sc-event-meta-icon">📅</span>
                    <div>
                        <strong>تاریخ</strong>
                        <p>
                            <?php if (!empty($event->start_date_gregorian)) : ?>
                                <?php echo 'شروع: ' . esc_html(sc_date_shamsi_date_only($event->start_date_gregorian)); ?>
                            <?php endif; ?>
                            <?php if (!empty($event->end_date_gregorian)) : ?>
                                <?php if (!empty($event->start_date_gregorian)) echo '<br>'; ?>
                                <?php echo 'پایان: ' . esc_html(sc_date_shamsi_date_only($event->end_date_gregorian)); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($event->event_location)) : ?>
                    <div class="sc-event-detail-meta-item">
                        <span class="sc-event-meta-icon">📍</span>
                        <div>
                            <strong>مکان</strong>
                            <p><?php echo esc_html($event->event_location); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($event->capacity) : ?>
                    <div class="sc-event-detail-meta-item">
                        <span class="sc-event-meta-icon">👥</span>
                        <div>
                            <strong>ظرفیت</strong>
                            <p><?php echo esc_html($remaining); ?> / <?php echo esc_html($event->capacity); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($event->has_age_limit) : ?>
                    <div class="sc-event-detail-meta-item">
                        <span class="sc-event-meta-icon">🎂</span>
                        <div>
                            <strong>شرط سنی</strong>
                            <p>
                                <?php
                                if ($event->min_age && $event->max_age) {
                                    echo esc_html($event->min_age . ' تا ' . $event->max_age . ' سال');
                                } elseif ($event->min_age) {
                                    echo esc_html('حداقل ' . $event->min_age . ' سال');
                                } elseif ($event->max_age) {
                                    echo esc_html('حداکثر ' . $event->max_age . ' سال');
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($event->event_location_address)) : ?>
                <div class="sc-event-detail-section">
                    <h3>آدرس</h3>
                    <p><?php echo nl2br(esc_html($event->event_location_address)); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($event->event_location_lat) && !empty($event->event_location_lng)) : ?>
                <div class="sc-event-detail-section">
                    <h3>نقشه</h3>
                    <div class="sc-event-map">
                        <iframe width="100%" height="400" frameborder="0" style="border:0; border-radius: 8px;" src="https://www.google.com/maps?q=<?php echo esc_attr($event->event_location_lat); ?>,<?php echo esc_attr($event->event_location_lng); ?>&output=embed" allowfullscreen></iframe>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="sc-event-detail-actions">
            <div class="sc-event-detail-section" style="margin-bottom:16px;">
                <p>اگر عضو آکادمی هستید، لطفاً ابتدا به حساب خود وارد شوید.</p>
                <a class="button" href="<?php echo esc_url(home_url('/')); ?>">ورود به حساب</a>
            </div>

            <?php if ($public_status === 'success') : ?>
                <div class="sc-event-enrolled-message">
                    <p>✅ ثبت‌نام با موفقیت انجام شد.</p>
                </div>
            <?php elseif ($public_status === 'already_registered') : ?>
                <div class="sc-event-status-message"><p>✅ شما قبلاً ثبت‌نام کرده‌اید.</p></div>
            <?php endif; ?>

            <?php if ($can_enroll) : ?>
                <form method="post" action="" class="sc-enroll-event-form">
                    <?php wp_nonce_field('sc_enroll_public_event', 'sc_enroll_public_event_nonce'); ?>
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>">

                    <?php if (!is_user_logged_in()) : ?>
                        <div class="sc-event-custom-fields-section" style="margin-bottom: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
                            <h3 style="margin-top:0;">اطلاعات تکمیلی ثبت‌نام</h3>
                            <p><label>نام</label><input type="text" name="guest_first_name" class="regular-text" style="width:100%;" required></p>
                            <p><label>نام خانوادگی</label><input type="text" name="guest_last_name" class="regular-text" style="width:100%;" required></p>
                            <p><label>شماره تماس</label><input type="text" name="guest_phone" class="regular-text" style="width:100%;" placeholder="09xxxxxxxxx" required></p>
                            <p><label>کد ملی</label><input type="text" name="guest_national_id" class="regular-text" style="width:100%;" required></p>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="sc_enroll_public_event" class="button button-primary sc-enroll-event-btn">
                        ثبت‌نام در <?php echo esc_html($event_type_label); ?>
                    </button>
                </form>
            <?php else : ?>
                <div class="sc-event-cannot-enroll" data-tooltip="<?php echo esc_attr($tooltip_message); ?>">
                    <button type="button" class="button sc-enroll-event-btn" disabled>
                        ثبت‌نام در <?php echo esc_html($event_type_label); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="sc-event-detail-section" style="margin-top:16px;">
                <p><strong>لینک ثبت‌نام عمومی:</strong></p>
                <a href="<?php echo esc_url($public_link); ?>"><?php echo esc_html($public_link); ?></a>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
