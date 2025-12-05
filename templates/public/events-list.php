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

// دریافت فیلترها
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'latest');
$filter_event_type = isset($filter_event_type) ? $filter_event_type : (isset($_GET['filter_event_type']) ? sanitize_text_field($_GET['filter_event_type']) : 'all');
?>

<div class="sc-events-page">
    <h2>رویدادها / مسابقات</h2>
    
    <!-- فیلترها -->
    <div class="sc-events-filters" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? esc_attr($_GET['page']) : ''; ?>">
            
            <div style="flex: 1; min-width: 200px;">
                <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                <select name="filter_status" id="filter_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="latest" <?php selected($filter_status, 'latest'); ?>>آخرین</option>
                    <option value="past" <?php selected($filter_status, 'past'); ?>>برگزار شده</option>
                    <option value="upcoming" <?php selected($filter_status, 'upcoming'); ?>>به زودی</option>
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 200px;">
                <label for="filter_event_type" style="display: block; margin-bottom: 5px; font-weight: 600;">نوع:</label>
                <select name="filter_event_type" id="filter_event_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?php selected($filter_event_type, 'all'); ?>>همه</option>
                    <option value="event" <?php selected($filter_event_type, 'event'); ?>>رویداد</option>
                    <option value="competition" <?php selected($filter_event_type, 'competition'); ?>>مسابقه</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary" style="padding: 8px 20px; height: auto;">اعمال فیلتر</button>
            </div>
        </form>
    </div>
    
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
                $can_view_details = true;
                $tooltip_message = '';
                $is_upcoming = false;
                
                // بررسی اینکه آیا در فیلتر "به زودی" هستیم
                if ($filter_status === 'upcoming') {
                    $is_upcoming = true;
                    $can_enroll = false;
                    $can_view_details = false;
                    $tooltip_message = 'این ' . ($event->event_type === 'competition' ? 'مسابقه' : 'رویداد') . ' به زودی برگزار می‌شود.';
                } else {
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
                
                // بررسی ثبت‌نام قبلی و وضعیت
                $is_enrolled = false;
                $enrollment_status = null;
                $enrollment_status_label = '';
                $enrollment_tooltip = '';
                $event_type_label = ($event->event_type === 'competition') ? 'مسابقه' : 'رویداد';
                
                if (!empty($player->id)) {
                    global $wpdb;
                    $invoices_table = $wpdb->prefix . 'sc_invoices';
                    $existing_invoice = $wpdb->get_row($wpdb->prepare(
                        "SELECT status FROM $invoices_table WHERE member_id = %d AND event_id = %d ORDER BY created_at DESC LIMIT 1",
                        $player->id,
                        $event->id
                    ));
                    
                    if ($existing_invoice) {
                        $enrollment_status = $existing_invoice->status;
                        
                        if (in_array($enrollment_status, ['paid', 'completed', 'processing'])) {
                            $is_enrolled = true;
                            $can_enroll = false;
                            $enrollment_status_label = 'ثبت‌نام شده';
                            $enrollment_tooltip = 'شما در این ' . $event_type_label . ' ثبت‌نام کرده‌اید.';
                        } elseif ($enrollment_status === 'cancelled') {
                            $is_enrolled = false;
                            $can_enroll = false;
                            $enrollment_status_label = 'لغو شده';
                            $enrollment_tooltip = 'ثبت‌نام شما در این ' . $event_type_label . ' لغو شده است.';
                        } elseif (in_array($enrollment_status, ['pending', 'on-hold'])) {
                            $is_enrolled = false;
                            $can_enroll = false;
                            $enrollment_status_label = 'در انتظار پرداخت';
                            $enrollment_tooltip = 'ثبت‌نام شما در این ' . $event_type_label . ' انجام شده است. لطفاً برای تکمیل ثبت‌نام، پرداخت را انجام دهید.';
                        }
                    }
                }
                
                $formatted_price = '';
                if (function_exists('wc_price')) {
                    $formatted_price = wc_price($event->price);
                } else {
                    $formatted_price = number_format((float)$event->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }
                
                $event_detail_url = $can_view_details ? wc_get_endpoint_url('sc-event-detail', $event->id) : '#';
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
                            <?php if ($can_view_details) : ?>
                                <a href="<?php echo esc_url($event_detail_url); ?>"><?php echo esc_html($event->name); ?></a>
                            <?php else : ?>
                                <span><?php echo esc_html($event->name); ?></span>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if ($is_upcoming) : ?>
                            <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; text-align: center; font-weight: 600;">
                                به زودی
                            </div>
                        <?php endif; ?>
                        
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
                            <?php if ($is_enrolled) : ?>
                                <span class="sc-event-enrolled-badge" 
                                      data-tooltip="<?php echo esc_attr($enrollment_tooltip); ?>">
                                    شما در این <?php echo esc_html($event_type_label); ?> ثبت‌نام کرده‌اید
                                </span>
                            <?php elseif ($enrollment_status) : ?>
                                <span class="sc-event-status-badge" 
                                      data-tooltip="<?php echo esc_attr($enrollment_tooltip); ?>">
                                    <?php echo esc_html($enrollment_status_label); ?>
                                </span>
                            <?php else : ?>
                                <?php if ($can_view_details) : ?>
                                    <a href="<?php echo esc_url($event_detail_url); ?>" class="button sc-event-view-btn">
                                        مشاهده جزئیات
                                    </a>
                                <?php else : ?>
                                    <span class="button sc-event-view-btn" style="opacity: 0.6; cursor: not-allowed;" 
                                          data-tooltip="<?php echo esc_attr($tooltip_message); ?>">
                                        مشاهده جزئیات
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php endif; ?>
</div>




