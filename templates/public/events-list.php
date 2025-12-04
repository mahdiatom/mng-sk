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

// دریافت متغیرهای pagination (اگر از my-account.php فراخوانی شده باشد)
$current_page = isset($current_page) ? $current_page : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
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
                                <a href="<?php echo esc_url($event_detail_url); ?>" class="button sc-event-view-btn">
                                    مشاهده جزئیات
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1) : ?>
            <div class="sc-events-pagination" style="margin-top: 30px; text-align: center;">
                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg(['paged' => '%#%']),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'type' => 'plain',
                    'end_size' => 2,
                    'mid_size' => 2
                ]);
                echo $page_links;
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>




