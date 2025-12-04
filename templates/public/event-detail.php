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

// بررسی محدودیت تاریخ
$is_date_expired = false;
$can_enroll = true;
$tooltip_message = '';

if (!empty($event->start_date_gregorian) || !empty($event->end_date_gregorian)) {
    $start_date_shamsi = !empty($event->start_date_gregorian) ? sc_date_shamsi_date_only($event->start_date_gregorian) : '';
    $end_date_shamsi = !empty($event->end_date_gregorian) ? sc_date_shamsi_date_only($event->end_date_gregorian) : '';
    
    if (!empty($end_date_shamsi)) {
        if (sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
            $is_date_expired = true;
            $can_enroll = false;
            $tooltip_message = 'زمان ثبت نام این رویداد تمام شده است.';
        }
    }
    
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
?>

<div class="sc-event-detail-page">
    <div class="sc-event-detail-header">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('sc-events')); ?>" class="sc-back-link">← بازگشت به لیست رویدادها</a>
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
                        <p><?php echo $formatted_price; ?></p>
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
                            <?php 
                            if (!empty($event->start_date_gregorian)) {
                                echo 'شروع: ' . sc_date_shamsi_date_only($event->start_date_gregorian);
                            }
                            if (!empty($event->end_date_gregorian)) {
                                if (!empty($event->start_date_gregorian)) echo '<br>';
                                echo 'پایان: ' . sc_date_shamsi_date_only($event->end_date_gregorian);
                            }
                            ?>
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
                                    echo $event->min_age . ' تا ' . $event->max_age . ' سال';
                                } elseif ($event->min_age) {
                                    echo 'حداقل ' . $event->min_age . ' سال';
                                } elseif ($event->max_age) {
                                    echo 'حداکثر ' . $event->max_age . ' سال';
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
                        <iframe
                            width="100%"
                            height="400"
                            frameborder="0"
                            style="border:0; border-radius: 8px;"
                            src="https://www.google.com/maps/embed/v1/place?key=AIzaSyBFw0Qbyq9zTFTd-tUY6d_s6H4ZO0RzJ8E&q=<?php echo esc_attr($event->event_location_lat); ?>,<?php echo esc_attr($event->event_location_lng); ?>&zoom=15"
                            allowfullscreen>
                        </iframe>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sc-event-detail-actions">
            <?php if ($can_enroll && !$is_enrolled) : ?>
                <?php
                // دریافت فیلدهای سفارشی رویداد
                global $wpdb;
                $event_fields_table = $wpdb->prefix . 'sc_event_fields';
                $event_fields = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $event_fields_table WHERE event_id = %d ORDER BY field_order ASC, id ASC",
                    $event->id
                ));
                ?>
                <form method="POST" action="" class="sc-enroll-event-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('sc_enroll_event', 'sc_enroll_event_nonce'); ?>
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>">
                    
                    <!-- فیلدهای سفارشی رویداد -->
                    <?php if (!empty($event_fields)) : ?>
                    <div class="sc-event-custom-fields-section" style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3 style="margin-top: 0;">اطلاعات تکمیلی ثبت‌نام</h3>
                        <p class="description">لطفاً اطلاعات زیر را تکمیل کنید:</p>
                        
                        <div class="sc-event-fields-form" style="margin-top: 20px;">
                            <?php foreach ($event_fields as $field) : 
                                $field_options = !empty($field->field_options) ? json_decode($field->field_options, true) : [];
                                $field_id_attr = 'sc_event_field_' . $field->id;
                            ?>
                            <div class="sc-event-field-row" style="margin-bottom: 20px;">
                                <label for="<?php echo esc_attr($field_id_attr); ?>" style="display: block; margin-bottom: 5px; font-weight: bold;">
                                    <?php echo esc_html($field->field_name); ?>
                                    <?php if ($field->is_required) : ?>
                                        <span style="color: red;">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($field->field_type === 'text') : ?>
                                    <input type="text" 
                                           name="event_fields[<?php echo esc_attr($field->id); ?>]" 
                                           id="<?php echo esc_attr($field_id_attr); ?>" 
                                           class="regular-text" 
                                           <?php echo $field->is_required ? 'required' : ''; ?>>
                                
                                <?php elseif ($field->field_type === 'number') : ?>
                                    <input type="number" 
                                           name="event_fields[<?php echo esc_attr($field->id); ?>]" 
                                           id="<?php echo esc_attr($field_id_attr); ?>" 
                                           class="regular-text" 
                                           <?php echo $field->is_required ? 'required' : ''; ?>>
                                
                                <?php elseif ($field->field_type === 'date') : ?>
                                    <input type="text" 
                                           name="event_fields[<?php echo esc_attr($field->id); ?>]" 
                                           id="<?php echo esc_attr($field_id_attr); ?>" 
                                           class="regular-text persian-date-input" 
                                           placeholder="تاریخ (شمسی)" 
                                           readonly
                                           <?php echo $field->is_required ? 'required' : ''; ?>>
                                
                                <?php elseif ($field->field_type === 'file') : ?>
                                    <input type="file" 
                                           name="event_fields[<?php echo esc_attr($field->id); ?>][]" 
                                           id="<?php echo esc_attr($field_id_attr); ?>" 
                                           class="regular-text sc-event-file-input" 
                                           accept="image/*,.pdf"
                                           multiple
                                           data-max-files="10"
                                           <?php echo $field->is_required ? 'required' : ''; ?>>
                                    <p class="description">حداکثر 10 فایل (فقط تصویر و PDF)، حداکثر حجم هر فایل: 1 مگابایت</p>
                                    <div class="sc-event-file-preview" style="margin-top: 10px;"></div>
                                
                                <?php elseif ($field->field_type === 'select' && !empty($field_options['options'])) : ?>
                                    <select name="event_fields[<?php echo esc_attr($field->id); ?>]" 
                                            id="<?php echo esc_attr($field_id_attr); ?>" 
                                            class="regular-text"
                                            <?php echo $field->is_required ? 'required' : ''; ?>>
                                        <option value="">-- انتخاب کنید --</option>
                                        <?php foreach ($field_options['options'] as $option) : ?>
                                            <option value="<?php echo esc_attr(trim($option)); ?>"><?php echo esc_html(trim($option)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="sc_enroll_event" class="button button-primary sc-enroll-event-btn">
                        ثبت‌نام در <?php echo esc_html($event_type_label); ?>
                    </button>
                </form>
            <?php elseif ($is_enrolled) : ?>
                <div class="sc-event-enrolled-message" 
                     data-tooltip="<?php echo esc_attr($enrollment_tooltip); ?>">
                    <p>✅ شما در این <?php echo esc_html($event_type_label); ?> ثبت‌نام کرده‌اید.</p>
                </div>
            <?php elseif ($enrollment_status) : ?>
                <div class="sc-event-status-message" 
                     data-tooltip="<?php echo esc_attr($enrollment_tooltip); ?>">
                    <p>
                        <?php if ($enrollment_status === 'cancelled') : ?>
                            ❌ <?php echo esc_html($enrollment_status_label); ?>
                        <?php else : ?>
                            ⏳ <?php echo esc_html($enrollment_status_label); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="sc-event-cannot-enroll" 
                     <?php if ($tooltip_message) : ?>
                         data-tooltip="<?php echo esc_attr($tooltip_message); ?>"
                     <?php endif; ?>>
                    <button type="button" class="button sc-enroll-event-btn" disabled>
                        ثبت‌نام در <?php echo esc_html($event_type_label); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

