<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// استفاده از تنظیمات WooCommerce برای فرمت قیمت
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

$today_shamsi = sc_get_today_shamsi();
?>

<div class="sc-events-page">
    <h2>رویدادها / مسابقات</h2>
    
    <?php if (empty($events)) : ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-info">
            در حال حاضر رویدادی برای ثبت نام موجود نیست.
        </div>
    <?php else : ?>
        <div class="sc-events-grid">
            <?php foreach ($events as $event) : 
                // بررسی محدودیت تاریخ
                $is_date_expired = false;
                $can_enroll = true;
                $tooltip_message = '';
                
                if (!empty($event->start_date_gregorian) || !empty($event->end_date_gregorian)) {
                    $start_date_shamsi = !empty($event->start_date_gregorian) ? sc_date_shamsi_date_only($event->start_date_gregorian) : '';
                    $end_date_shamsi = !empty($event->end_date_gregorian) ? sc_date_shamsi_date_only($event->end_date_gregorian) : '';
                    
                    // اگر تاریخ پایان وارد شده باشد و تاریخ امروز بعد از تاریخ پایان باشد
                    if (!empty($end_date_shamsi)) {
                        if (sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
                            $is_date_expired = true;
                            $can_enroll = false;
                            $tooltip_message = 'زمان ثبت نام این رویداد تمام شده است.';
                        }
                    }
                    
                    // اگر تاریخ شروع وارد شده باشد و تاریخ امروز قبل از تاریخ شروع باشد
                    if (!empty($start_date_shamsi) && !$is_date_expired) {
                        if (sc_compare_shamsi_dates($today_shamsi, $start_date_shamsi) < 0) {
                            $is_date_expired = true;
                            $can_enroll = false;
                            $tooltip_message = 'زمان ثبت نام این رویداد هنوز شروع نشده است.';
                        }
                    }
                }
                
                // بررسی شرط سنی
                $age_check_passed = true;
                if ($event->has_age_limit && !empty($player->birth_date_shamsi)) {
                    $user_age = sc_calculate_age($player->birth_date_shamsi);
                    $age_number = (int)str_replace(' سال', '', $user_age);
                    
                    if ($event->min_age && $age_number < $event->min_age) {
                        $age_check_passed = false;
                        $can_enroll = false;
                        $tooltip_message = 'شما سن لازم برای شرکت در این رویداد را ندارید. حداقل سن: ' . $event->min_age . ' سال';
                    }
                    if ($event->max_age && $age_number > $event->max_age) {
                        $age_check_passed = false;
                        $can_enroll = false;
                        $tooltip_message = 'شما سن لازم برای شرکت در این رویداد را ندارید. حداکثر سن: ' . $event->max_age . ' سال';
                    }
                } elseif ($event->has_age_limit && empty($player->birth_date_shamsi)) {
                    $age_check_passed = false;
                    $can_enroll = false;
                    $tooltip_message = 'لطفاً ابتدا تاریخ تولد خود را در بخش اطلاعات بازیکن تکمیل کنید.';
                }
                
                // بررسی ظرفیت
                $enrolled_count = 0;
                $remaining = 0;
                $is_capacity_full = false;
                if ($event->capacity) {
                    global $wpdb;
                    $invoices_table = $wpdb->prefix . 'sc_invoices';
                    $enrolled_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $invoices_table WHERE event_id = %d AND status IN ('paid', 'completed', 'processing')",
                        $event->id
                    ));
                    $remaining = $event->capacity - $enrolled_count;
                    $is_capacity_full = ($remaining <= 0);
                    
                    if ($is_capacity_full) {
                        $can_enroll = false;
                        $tooltip_message = 'ظرفیت این رویداد تکمیل شده است.';
                    }
                }
                
                // بررسی ثبت‌نام قبلی
                $is_enrolled = false;
                if (!empty($player->id)) {
                    global $wpdb;
                    $invoices_table = $wpdb->prefix . 'sc_invoices';
                    $existing_invoice = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $invoices_table WHERE member_id = %d AND event_id = %d AND status IN ('paid', 'completed', 'processing')",
                        $player->id,
                        $event->id
                    ));
                    if ($existing_invoice > 0) {
                        $is_enrolled = true;
                        $can_enroll = false;
                        $tooltip_message = 'شما قبلاً در این رویداد ثبت نام کرده‌اید.';
                    }
                }
                
                $formatted_price = '';
                if (function_exists('wc_price')) {
                    $formatted_price = wc_price($event->price);
                } else {
                    $formatted_price = number_format((float)$event->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }
                
                $event_detail_url = wc_get_endpoint_url('sc-event-detail', $event->id);
            ?>
                <div class="sc-event-card <?php echo !$can_enroll ? 'disabled' : ''; ?>" 
                     <?php if ($tooltip_message) : ?>
                         data-tooltip="<?php echo esc_attr($tooltip_message); ?>"
                     <?php endif; ?>>
                    <?php if (!empty($event->image)) : ?>
                        <div class="sc-event-image">
                            <img src="<?php echo esc_url($event->image); ?>" alt="<?php echo esc_attr($event->name); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="sc-event-content">
                        <h3 class="sc-event-title">
                            <a href="<?php echo esc_url($event_detail_url); ?>"><?php echo esc_html($event->name); ?></a>
                        </h3>
                        
                        <?php if (!empty($event->description)) : ?>
                            <p class="sc-event-description"><?php echo esc_html(wp_trim_words($event->description, 20)); ?></p>
                        <?php endif; ?>
                        
                        <div class="sc-event-meta">
                            <?php if (!empty($event->event_time)) : ?>
                                <div class="sc-event-meta-item">
                                    <span class="sc-event-icon">🕐</span>
                                    <span><?php echo esc_html($event->event_time); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="sc-event-meta-item">
                                <span class="sc-event-icon">📅</span>
                                <span>
                                    <?php 
                                    if (!empty($event->start_date_gregorian)) {
                                        echo 'شروع: ' . sc_date_shamsi_date_only($event->start_date_gregorian);
                                    }
                                    if (!empty($event->end_date_gregorian)) {
                                        if (!empty($event->start_date_gregorian)) echo ' - ';
                                        echo 'پایان: ' . sc_date_shamsi_date_only($event->end_date_gregorian);
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($event->event_location)) : ?>
                                <div class="sc-event-meta-item">
                                    <span class="sc-event-icon">📍</span>
                                    <span><?php echo esc_html($event->event_location); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="sc-event-meta-item">
                                <span class="sc-event-icon">💰</span>
                                <span class="sc-event-price"><?php echo $formatted_price; ?></span>
                            </div>
                            
                            <?php if ($event->capacity) : ?>
                                <div class="sc-event-meta-item">
                                    <span class="sc-event-icon">👥</span>
                                    <span>ظرفیت: <?php echo esc_html($remaining); ?> / <?php echo esc_html($event->capacity); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="sc-event-actions">
                            <a href="<?php echo esc_url($event_detail_url); ?>" class="button sc-event-view-btn">
                                مشاهده جزئیات
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.sc-events-page {
    padding: 20px 0;
}

.sc-events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 25px;
}

.sc-event-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
}

.sc-event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    border-color: #2271b1;
}

.sc-event-card.disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.sc-event-card.disabled:hover {
    transform: none;
    border-color: transparent;
}

.sc-event-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: #f5f5f5;
}

.sc-event-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.sc-event-card:hover .sc-event-image img {
    transform: scale(1.05);
}

.sc-event-content {
    padding: 20px;
}

.sc-event-title {
    margin: 0 0 15px 0;
    font-size: 20px;
    font-weight: 600;
    color: #1a1a1a;
}

.sc-event-title a {
    color: inherit;
    text-decoration: none;
    transition: color 0.3s ease;
}

.sc-event-title a:hover {
    color: #2271b1;
}

.sc-event-description {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin: 0 0 15px 0;
}

.sc-event-meta {
    margin: 15px 0;
}

.sc-event-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 14px;
    color: #555;
}

.sc-event-icon {
    font-size: 18px;
}

.sc-event-price {
    font-weight: 600;
    color: #2271b1;
    font-size: 16px;
}

.sc-event-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e5e5e5;
}

.sc-event-view-btn {
    width: 100%;
    text-align: center;
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    color: #fff;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: block;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3);
}

.sc-event-view-btn:hover {
    background: linear-gradient(135deg, #135e96 0%, #0a4d75 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(34, 113, 177, 0.4);
    color: #fff;
}

.sc-event-card[data-tooltip] {
    position: relative;
}

.sc-event-card[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    right: 0;
    padding: 12px 16px;
    background-color: #000;
    color: #fff;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.6;
    white-space: normal;
    width: 250px;
    max-width: 90vw;
    z-index: 99999;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    text-align: right;
    font-weight: normal;
    opacity: 0;
    transform: translateY(10px);
    animation: tooltipFadeIn 0.3s ease-out 0.2s forwards;
    pointer-events: none;
}

.sc-event-card[data-tooltip]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 20px;
    border: 7px solid transparent;
    border-top-color: #000;
    margin-bottom: 3px;
    z-index: 99999;
    opacity: 0;
    animation: tooltipFadeIn 0.3s ease-out 0.2s forwards;
    pointer-events: none;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .sc-events-grid {
        grid-template-columns: 1fr;
    }
}
</style>


