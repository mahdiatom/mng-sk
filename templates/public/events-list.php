<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/** @var stdClass|null $events */
/** @var stdClass|null $event */
//ادامه تنظمات صفحه بندی 

//استفاده از تنظیمات WooCommerce برای فرمت قیمت


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
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'latest');
$filter_event_type = isset($filter_event_type) ? $filter_event_type : (isset($_GET['filter_event_type']) ? sanitize_text_field(wp_unslash($_GET['filter_event_type'])) : 'all');
$event_s = isset($event_s) ? $event_s : (isset($_GET['event_s']) ? sanitize_text_field(wp_unslash($_GET['event_s'])) : '');
$event_s = trim((string) $event_s);

$sc_events_list_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-events') : home_url('/my-account/sc-events/');
$sc_events_paginate_q = [
    'filter_status'     => $filter_status,
    'filter_event_type' => $filter_event_type,
];
if ($event_s !== '') {
    $sc_events_paginate_q['event_s'] = $event_s;
}
$sc_events_paginate_base = add_query_arg($sc_events_paginate_q, $sc_events_list_url);
$sc_events_paginate_sep = (strpos($sc_events_paginate_base, '?') !== false) ? '&' : '?';
?>

<div class="sc-events-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">🎯</span>
        رویدادها / مسابقات
    </h2>
    
    <!-- فیلترها -->
    <div class="sc-events-filters" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <form method="GET" action="<?php echo esc_url($sc_events_list_url); ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1.2; min-width: 200px;">
                <label for="event_s" style="display: block; margin-bottom: 5px; font-weight: 600;">جستجو:</label>
                <input type="search" name="event_s" id="event_s" value="<?php echo esc_attr($event_s); ?>"
                       placeholder="نام رویداد، توضیحات، محل یا آدرس…"
                       style="width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
            </div>

            <div style="flex: 1; min-width: 160px;">
                <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                <select name="filter_status" id="filter_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="latest" <?php selected($filter_status, 'latest'); ?>>آخرین</option>
                    <option value="past" <?php selected($filter_status, 'past'); ?>>برگزار شده</option>
                    <option value="is_upcoming" <?php selected($filter_status, 'is_upcoming'); ?>>به زودی</option>
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 160px;">
                <label for="filter_event_type" style="display: block; margin-bottom: 5px; font-weight: 600;">نوع:</label>
                <select name="filter_event_type" id="filter_event_type" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?php selected($filter_event_type, 'all'); ?>>همه</option>
                    <option value="event" <?php selected($filter_event_type, 'event'); ?>>رویداد</option>
                    <option value="competition" <?php selected($filter_event_type, 'competition'); ?>>مسابقه</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <button type="submit" class="button button-primary" style="padding: 8px 20px; height: auto;">اعمال فیلتر</button>
                <?php
                $clear_url = $sc_events_list_url;
                if ($event_s !== '' || $filter_status !== 'latest' || $filter_event_type !== 'all') :
                ?>
                    <a class="button" href="<?php echo esc_url($clear_url); ?>" style="height: auto; padding: 8px 16px;">پاک کردن فیلترها</a>
                <?php endif; ?>
            </div>
        </form>
          
    </div>
    
    <?php if (empty($events)) : ?>
        <div class="sc-message sc-message-info" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <?php if ($event_s !== '') : ?>
                نتیجه‌ای برای «<?php echo esc_html($event_s); ?>» با فیلترهای انتخاب‌شده یافت نشد.
            <?php else : ?>
                در حال حاضر رویدادی برای ثبت نام موجود نیست.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate top_pagination" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base'      => esc_url_raw($sc_events_paginate_base) . '%_%',
                            'format'    => $sc_events_paginate_sep . 'pag=%#%',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >' ,
                            'total'     => $total_pages,
                            'current'   => $current_page,
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <div class="sc-events-grid">
            
            <?php foreach ($events as $event) : 
                // بررسی محدودیت تاریخ
                $is_date_expired = false;
                $can_enroll = true;
                $can_view_details = true;
                $tooltip_message = '';
                $is_upcoming = false;
              
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
                                
                                $can_enroll = false;
                                $is_upcoming = true;
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
               // بررسی ثبت‌نام قبلی و وضعیت
                $is_enrolled = false;
                $enrollment_status = null;
                $enrollment_status_label = '';
                $enrollment_tooltip = '';
                $event_type_label = ($event->event_type === 'competition') ? 'مسابقه' : 'رویداد';

                if (!empty($player->id)) {
                    global $wpdb;
                    $invoices_table = $wpdb->prefix . 'sc_invoices';
                    $registrations_table = $wpdb->prefix . 'sc_event_registrations';

                    /**
                     * 1️⃣ بررسی ثبت‌نام مستقیم (رویدادهای رایگان)
                     */
                    $existing_registration = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM $registrations_table WHERE member_id = %d AND event_id = %d",
                        $player->id,
                        $event->id
                    ));

                    if ($existing_registration) {
                        $is_enrolled = true;
                        $can_enroll = false;
                        $enrollment_status_label = 'ثبت‌نام شده';
                        $enrollment_tooltip = 'شما در این ' . $event_type_label . ' ثبت‌نام کرده‌اید.';
                    } else {

                        /**
                         * 2️⃣ بررسی invoice (رویدادهای پولی)
                         */
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
                                $can_enroll = true;
                            } elseif (in_array($enrollment_status, ['pending', 'under_review', 'on-hold'])) {
                                $is_enrolled = false;
                                $can_enroll = false;
                                $enrollment_status_label = 'در حال پرداخت';
                                $enrollment_tooltip = 'ثبت‌نام شما انجام شده و صورت حساب در حال پرداخت است.';
                            }
                        }
                    }
                }

                
                $formatted_price = '';
                if (function_exists('wc_price')) {
                    $formatted_price = wc_price($event->price);
                    
                } else {
                    $formatted_price = number_format((float)$event->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';

                }
              $price_free = number_format((float)$event->price, $decimal_places, $decimal_separator, $thousand_separator);
               
                if($price_free == 0 ){
                    $formatted_price = 'رایگان';
                }
                
                // تاریخ برگزاری
                $event_date_shamsi = $event->holding_date_shamsi;
               
                
                // بازه ثبت نام (از start_date و end_date استفاده می‌کنیم)
                $registration_start = '';
                $registration_end = '';
                if (!empty($event->start_date_gregorian)) {
                    $registration_start = sc_date_shamsi_date_only($event->start_date_gregorian);
                }
                if (!empty($event->end_date_gregorian)) {
                    $registration_end = sc_date_shamsi_date_only($event->end_date_gregorian);
                }
                
                $event_detail_url = $can_view_details ? wc_get_endpoint_url('sc-event-detail', $event->id) : '#';
           
           
           ?>
                <div class="sc-event-card">
                    <div class="sc-event-card-header">
                        <h3 class="sc-event-card-title">
                            <?php echo esc_html($event->name); ?>
                        </h3>
                        <?php if ($is_upcoming) : ?>
                            <div class="sc-event-upcoming-badge">
                                به زودی
                            </div>
                        <?php endif; ?>
                        
                        <!-- 4 ویژگی مرتب شده -->
                        <div class="sc-event-features-grid">
                            <!-- تاریخ برگزاری -->
                            <?php if ($event_date_shamsi) : ?>
                                <div class="sc-event-feature-item">
                                    <div class="sc-event-feature-icon">📅</div>
                                    <div class="sc-event-feature-content">
                                        <div class="sc-event-feature-label">تاریخ برگزاری</div>
                                        <div class="sc-event-feature-value"><?php echo esc_html($event_date_shamsi); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- زمان -->
                            <?php if (!empty($event->event_time)) : ?>
                                <div class="sc-event-feature-item">
                                    <div class="sc-event-feature-icon">🕐</div>
                                    <div class="sc-event-feature-content">
                                        <div class="sc-event-feature-label">زمان</div>
                                        <div class="sc-event-feature-value"><?php echo esc_html($event->event_time); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- مکان -->
                            <?php if (!empty($event->event_location)) : ?>
                                <div class="sc-event-feature-item">
                                    <div class="sc-event-feature-icon">📍</div>
                                    <div class="sc-event-feature-content">
                                        <div class="sc-event-feature-label">مکان</div>
                                        <div class="sc-event-feature-value" style="word-break: break-word;"><?php echo esc_html($event->event_location); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- قیمت -->
                            <div class="sc-event-feature-price">
                                <div class="sc-event-feature-icon">💰</div>
                                <div class="sc-event-feature-content">
                                    <div class="sc-event-feature-label">قیمت</div>
                                    <div class="sc-event-feature-price-value"><?php echo  $formatted_price ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- مهلت ثبت نام -->
                        <?php if ($registration_start || $registration_end) : ?>
                            <div class="sc-event-registration-period <?php echo $is_date_expired ? 'expired' : 'active'; ?>">
                                <span class="sc-event-registration-icon">⏰</span>
                                <div class="sc-event-registration-content">
                                    <strong class="sc-event-registration-label">مهلت ثبت نام:</strong>
                                    <span class="sc-event-registration-value">
                                        <?php 
                                        if ($registration_start && $registration_end) {
                                            echo esc_html($registration_start) . ' تا ' . esc_html($registration_end);
                                        } elseif ($registration_start) {
                                            echo 'از ' . esc_html($registration_start);
                                        } elseif ($registration_end) {
                                            echo 'تا ' . esc_html($registration_end);
                                        }
                                        ?>
                                    </span>
                                    <?php if ($is_date_expired) : ?>
                                        <span class="sc-event-registration-expired">
                                            (تمام شده)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- دکمه عملیات -->
                    <div class="sc-event-actions-wrapper">
                        <?php if ($is_enrolled) : ?>
                            <div class="sc-event-status-badge enrolled" title="<?php echo esc_attr($enrollment_tooltip); ?>">
                                ✅ ثبت‌نام شده
                            </div>
                        <?php elseif ($enrollment_status && $enrollment_status !== 'cancelled') : ?>
                            <div class="sc-event-status-badge pending" title="<?php echo esc_attr($enrollment_tooltip); ?>">
                                <?php echo esc_html($enrollment_status_label); ?>
                            </div>
                        <?php else : ?>
                            <?php if ($can_view_details) : ?>
                                <a href="<?php echo esc_url($event_detail_url); ?>" class="sc-event-action-btn">
                                    <?php echo ($can_enroll && !$is_date_expired) ? 'مشاهده جزئیات و ثبت نام' : 'مشاهده جزئیات'; ?>
                                </a>
                            <?php else : ?>
                                <div class="sc-event-action-btn disabled" title="<?php echo esc_attr($tooltip_message); ?>">
                                    مشاهده جزئیات
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
  <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base'      => esc_url_raw($sc_events_paginate_base) . '%_%',
                            'format'    => $sc_events_paginate_sep . 'pag=%#%',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >' ,
                            'total'     => $total_pages,
                            'current'   => $current_page,
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-events-page h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>