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
                        ثبت‌نام در رویداد
                    </button>
                </form>
            <?php elseif ($is_enrolled) : ?>
                <div class="sc-event-enrolled-message">
                    <p>✅ شما قبلاً در این رویداد ثبت نام کرده‌اید.</p>
                </div>
            <?php else : ?>
                <div class="sc-event-cannot-enroll" 
                     <?php if ($tooltip_message) : ?>
                         data-tooltip="<?php echo esc_attr($tooltip_message); ?>"
                     <?php endif; ?>>
                    <button type="button" class="button sc-enroll-event-btn" disabled>
                        ثبت‌نام در رویداد
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.sc-event-detail-page {
    padding: 20px 0;
}

.sc-event-detail-header {
    margin-bottom: 30px;
}

.sc-back-link {
    display: inline-block;
    margin-bottom: 15px;
    color: #2271b1;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.sc-back-link:hover {
    color: #135e96;
}

.sc-event-detail-header h2 {
    margin: 0;
    font-size: 28px;
    color: #1a1a1a;
}

.sc-event-detail-content {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.sc-event-detail-image {
    width: 100%;
    max-height: 400px;
    overflow: hidden;
    border-radius: 8px;
    margin-bottom: 30px;
}

.sc-event-detail-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sc-event-detail-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e5e5;
}

.sc-event-detail-section:last-child {
    border-bottom: none;
}

.sc-event-detail-section h3 {
    margin: 0 0 15px 0;
    font-size: 20px;
    color: #2271b1;
    font-weight: 600;
}

.sc-event-detail-section p {
    color: #555;
    line-height: 1.8;
    margin: 0;
}

.sc-event-detail-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.sc-event-detail-meta-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-right: 4px solid #2271b1;
}

.sc-event-meta-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.sc-event-detail-meta-item strong {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-size: 14px;
}

.sc-event-detail-meta-item p {
    margin: 0;
    color: #555;
    font-size: 14px;
    line-height: 1.6;
}

.sc-event-map {
    margin-top: 15px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.sc-event-detail-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e5e5e5;
    text-align: center;
}

.sc-enroll-event-btn {
    background: linear-gradient(135deg, #00a32a 0%, #008a20 100%);
    color: #fff;
    border: none;
    padding: 15px 40px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 163, 42, 0.3);
}

.sc-enroll-event-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #008a20 0%, #007318 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 163, 42, 0.4);
}

.sc-enroll-event-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sc-event-enrolled-message {
    padding: 20px;
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-radius: 8px;
    text-align: center;
    color: #155724;
    font-weight: 600;
}

.sc-event-cannot-enroll {
    position: relative;
    display: inline-block;
}

.sc-event-cannot-enroll[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    right: 50%;
    transform: translateX(50%);
    padding: 12px 16px;
    background-color: #000;
    color: #fff;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.6;
    white-space: normal;
    width: 300px;
    max-width: 90vw;
    z-index: 99999;
    margin-bottom: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    text-align: right;
    font-weight: normal;
    opacity: 0;
    animation: tooltipFadeIn 0.3s ease-out 0.2s forwards;
    pointer-events: none;
}

.sc-event-cannot-enroll[data-tooltip]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 50%;
    transform: translateX(50%);
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
        transform: translateX(50%) translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateX(50%) translateY(0);
    }
}

@media (max-width: 768px) {
    .sc-event-detail-meta-grid {
        grid-template-columns: 1fr;
    }
    
    .sc-event-detail-content {
        padding: 20px;
    }
}
</style>

