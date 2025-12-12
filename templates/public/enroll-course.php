<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// دریافت متغیرهای فیلتر و صفحه‌بندی (اگر از my-account.php فراخوانی شده باشد)
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'latest');
$current_page = isset($current_page) ? $current_page : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
$total_courses = isset($total_courses) ? $total_courses : 0;

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
?>

<div class="sc-enroll-course-page">
    <h2>ثبت‌نام در دوره</h2>
    
    <!-- فیلتر وضعیت -->
    <div class="sc-enroll-course-filters" style="margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-enroll-course')); ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="paged" value="1">
            
            <div style="flex: 1; min-width: 200px;">
                <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                <select name="filter_status" id="filter_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="latest" <?php selected($filter_status, 'latest'); ?>>آخرین دوره‌ها</option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>>دوره‌های ثبت نام شده</option>
                    <option value="paused" <?php selected($filter_status, 'paused'); ?>>دوره‌های متوقف شده</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>دوره‌های به اتمام رسیده</option>
                    <option value="canceled" <?php selected($filter_status, 'canceled'); ?>>دوره‌های لغو شده</option>
                    <option value="expired" <?php selected($filter_status, 'expired'); ?>>مهلت ثبت نام تمام شده</option>
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه دوره‌ها</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary" style="padding: 8px 20px; height: auto;">اعمال فیلتر</button>
            </div>
        </form>
    </div>
    
    <?php if (empty($courses)) : ?>
        <div class="sc-message sc-message-info" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <?php if ($filter_status === 'latest') : ?>
                در حال حاضر دوره‌ای برای ثبت نام موجود نیست.
            <?php elseif ($filter_status === 'all') : ?>
                در حال حاضر دوره‌ای موجود نیست.
            <?php else : ?>
                دوره‌ای با این وضعیت یافت نشد.
            <?php endif; ?>
        </div>
    <?php else : ?>
    
    <form method="POST" action="" class="sc-enroll-course-form">
        <?php wp_nonce_field('sc_enroll_course', 'sc_enroll_course_nonce'); ?>
        
        <div class="sc-courses-accordion">
            <?php foreach ($courses as $index => $course) : 
                $is_enrolled = isset($enrolled_courses_data[$course->id]);
                $course_status = null;
                $status_label = '';
                $status_color = '';
                $status_bg = '';
                $tooltip_message = '';
                
                if ($is_enrolled) {
                    $course_data = $enrolled_courses_data[$course->id];
                    if ($course_data['is_under_review']) {
                        $course_status = 'under_review';
                        $status_label = 'در انتظار بررسی';
                        $status_color = '#856404';
                        $status_bg = '#fff3cd';
                        $tooltip_message = 'شما برای این دوره ثبت‌نام کرده‌اید و صورت حساب آن در حال بررسی است. پس از تایید مدیر و تبدیل به پرداخت شده، دوره فعال خواهد شد.';
                    } elseif ($course_data['is_pending_payment']) {
                        $course_status = 'pending_payment';
                        $status_label = 'در انتظار پرداخت';
                        $status_color = '#856404';
                        $status_bg = '#fff3cd';
                        $tooltip_message = 'شما برای این دوره ثبت‌نام کرده‌اید و صورت حساب آن در انتظار پرداخت است. لطفاً به بخش صورت حساب‌ها مراجعه کنید.';
                    } elseif ($course_data['is_canceled']) {
                        $course_status = 'canceled';
                        $status_label = 'لغو شده';
                        $status_color = '#d63638';
                        $status_bg = '#ffeaea';
                        $tooltip_message = 'این دوره توسط شما یا مدیریت لغو شده است. در صورتی که نیاز به ثبت نام و فعال شدن این دوره دارید با پشتیبان سایت و مربی ارتباط بگیرید.';
                    } elseif ($course_data['is_completed']) {
                        $course_status = 'completed';
                        $status_label = 'تمام شده';
                        $status_color = '#666';
                        $status_bg = '#f5f5f5';
                        $tooltip_message = 'این دوره توسط شما یا مدیریت تمام شده است. در صورتی که نیاز به ثبت نام مجدد در این دوره دارید با پشتیبان سایت و مربی ارتباط بگیرید.';
                    } elseif ($course_data['is_paused']) {
                        $course_status = 'paused';
                        $status_label = 'متوقف شده';
                        $status_color = '#f0a000';
                        $status_bg = '#fff8e1';
                        $tooltip_message = 'این دوره توسط شما یا مدیریت متوقف شده است. در صورتی که نیاز به فعال شدن مجدد این دوره دارید با پشتیبان سایت و مربی ارتباط بگیرید.';
                    } else {
                        $course_status = 'active';
                        $status_label = 'ثبت‌نام شده';
                        $status_color = '#00a32a';
                        $status_bg = '#d4edda';
                        $tooltip_message = 'تبریک شما اکنون در این دوره ثبت نام کردید و عضو کاربران فعال هستید';
                    }
                }
                
                $formatted_price = '';
                if (function_exists('wc_price')) {
                    $formatted_price = wc_price($course->price);
                } else {
                    $formatted_price = number_format((float)$course->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }
                
                // محاسبه ظرفیت
                $enrolled_count = 0;
                $remaining = 0;
                $is_capacity_full = false;
                if ($course->capacity) {
                    $enrolled_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $member_courses_table WHERE course_id = %d AND status = 'active'",
                        $course->id
                    ));
                    $remaining = $course->capacity - $enrolled_count;
                    $is_capacity_full = ($remaining <= 0);
                }
                
                // بررسی محدودیت تاریخ
                $is_date_expired = false;
                $today_shamsi = sc_get_today_shamsi();
                
                // اگر تاریخ شروع و پایان وجود داشته باشد
                if (!empty($course->start_date) || !empty($course->end_date)) {
                    $start_date_shamsi = !empty($course->start_date) ? sc_date_shamsi_date_only($course->start_date) : '';
                    $end_date_shamsi = !empty($course->end_date) ? sc_date_shamsi_date_only($course->end_date) : '';
                    
                    // اگر تاریخ پایان وارد شده باشد و تاریخ امروز بعد از تاریخ پایان باشد
                    if (!empty($end_date_shamsi)) {
                        if (sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
                            $is_date_expired = true;
                        }
                    }
                    
                    // اگر تاریخ شروع وارد شده باشد و تاریخ امروز قبل از تاریخ شروع باشد
                    if (!empty($start_date_shamsi) && !$is_date_expired) {
                        if (sc_compare_shamsi_dates($today_shamsi, $start_date_shamsi) < 0) {
                            $is_date_expired = true;
                        }
                    }
                }
                
                // اگر ظرفیت تکمیل شده باشد، برچسب و tooltip اضافه می‌کنیم
                if ($is_capacity_full && !$is_enrolled) {
                    $status_label = 'ظرفیت تکمیل شده';
                    $status_color = '#d63638';
                    $status_bg = '#ffeaea';
                    $tooltip_message = 'ظرفیت دوره تکمیل شده است برای امکان ثبت نام در این دوره با مدیر باشگاه ارتباط بگرید.';
                    $course_status = 'capacity_full';
                }
                
                // اگر تاریخ تمام شده باشد، برچسب و tooltip اضافه می‌کنیم
                if ($is_date_expired && !$is_enrolled) {
                    $status_label = 'زمان ثبت‌نام تمام شده';
                    $status_color = '#d63638';
                    $status_bg = '#ffeaea';
                    $tooltip_message = 'زمان ثبت نام این دوره تمام شده است.';
                    $course_status = 'date_expired';
                }
            ?>
                <div class="sc-course-accordion-item" style="border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; overflow: visible; position: relative;">
                    <input type="radio" 
                           name="course_id" 
                           id="course_<?php echo esc_attr($course->id); ?>" 
                           value="<?php echo esc_attr($course->id); ?>" 
                           class="sc-course-radio"
                           <?php echo ($is_enrolled || $is_capacity_full || $is_date_expired || (isset($course_data['is_under_review']) && $course_data['is_under_review'])) ? 'disabled' : ''; ?>
                           required>
                    
                    <label for="course_<?php echo esc_attr($course->id); ?>" 
                           class="sc-course-accordion-header" 
                           <?php if ($tooltip_message) : ?>
                               data-tooltip="<?php echo esc_attr($tooltip_message); ?>"
                           <?php endif; ?>
                           style="display: flex; align-items: center; padding: 15px; cursor: <?php echo ($is_enrolled || $is_capacity_full || $is_date_expired || (isset($course_data['is_under_review']) && $course_data['is_under_review'])) ? 'not-allowed' : 'pointer'; ?>; background-color: <?php echo ($is_enrolled || $is_capacity_full || $is_date_expired || (isset($course_data['is_under_review']) && $course_data['is_under_review'])) ? '#f5f5f5' : '#fff'; ?>; transition: background-color 0.3s; position: relative;">
                        <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 20px;">
                            <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                <span class="sc-accordion-icon" style="font-size: 18px; color: #666;">▼</span>
                                <strong style="font-size: 16px; color: #333;"><?php echo esc_html($course->title); ?></strong>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 20px; white-space: nowrap;">
                                <span style="color: #2271b1; font-weight: bold; font-size: 16px;">
                                    <?php echo $formatted_price; ?>
                                </span>
                                
                                <?php if ($course->sessions_count) : ?>
                                    <span style="color: #666; font-size: 14px;">
                                        <strong>تعداد جلسات:</strong> <?php echo esc_html($course->sessions_count); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($course->capacity) : ?>
                                    <span style="color: <?php echo ($is_capacity_full && !$is_enrolled) ? '#d63638' : '#666'; ?>; font-size: 14px; font-weight: <?php echo ($is_capacity_full && !$is_enrolled) ? 'bold' : 'normal'; ?>;">
                                        <strong>ظرفیت:</strong> <?php echo esc_html($remaining); ?> / <?php echo esc_html($course->capacity); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($is_enrolled || $is_capacity_full || $is_date_expired || (isset($course_data['is_under_review']) && $course_data['is_under_review'])) : ?>
                                    <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: bold; background-color: <?php echo esc_attr($status_bg); ?>; padding: 5px 10px; border-radius: 4px;">
                                        <?php if ($course_status == 'active') : ?>
                                            ✓ <?php echo esc_html($status_label); ?>
                                        <?php else : ?>
                                            <?php echo esc_html($status_label); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                    
                    <div class="sc-course-accordion-content" style="display: none; padding: 0 15px 15px 50px; background-color: #f9f9f9; border-top: 1px solid #eee; overflow: hidden;">
                        <?php if ($course->description) : ?>
                            <p style="margin: 10px 0 0 0; color: #666; line-height: 1.6;">
                                <?php echo nl2br(esc_html($course->description)); ?>
                            </p>
                        <?php else : ?>
                            <p style="margin: 10px 0 0 0; color: #999; font-style: italic;">توضیحاتی برای این دوره ثبت نشده است.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php 
        // دریافت متغیرهای صفحه‌بندی (اگر از my-account.php فراخوانی شده باشد)
        $current_page = isset($current_page) ? $current_page : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $total_pages = isset($total_pages) ? $total_pages : 1;
        $total_courses = isset($total_courses) ? $total_courses : 0;
        
        if ($total_pages > 1) : ?>
            <div class="sc-enroll-course-pagination" style="margin-top: 30px; text-align: center;">
                <?php
                // ساخت URL base با حفظ فیلترها
                $pagination_args = ['paged' => '%#%'];
                if ($filter_status !== 'latest') {
                    $pagination_args['filter_status'] = $filter_status;
                }
                
                $page_links = paginate_links([
                    'base' => add_query_arg($pagination_args),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'type' => 'plain',
                    'end_size' => 2,
                    'mid_size' => 2
                ]);
                
                if ($page_links) {
                    echo '<div class="pagination-wrapper" style="display: inline-block;">';
                    echo $page_links;
                    echo '</div>';
                    echo '<div style="margin-top: 10px; color: #666; font-size: 14px;">';
                    echo 'نمایش ' . (($current_page - 1) * 10 + 1) . ' تا ' . min($current_page * 10, $total_courses) . ' از ' . $total_courses . ' دوره';
                    echo '</div>';
                }
                ?>
            </div>
        <?php endif; ?>
        
        <p class="form-row" style="margin-top: 20px;">
            <button type="submit" name="sc_enroll_course" class="button button-primary" style="padding: 12px 30px; font-size: 16px;">
                ثبت نام و ایجاد صورت حساب
            </button>
        </p>
    </form>
    <?php endif; ?>
</div>

<style>
.sc-course-accordion-item:hover .sc-course-accordion-header:not(:has(+ input:disabled)) {
    background-color: #f0f0f0 !important;
}

.sc-course-accordion-item input[type="radio"]:checked + .sc-course-accordion-header {
    background-color: #e7f3ff !important;
    border-left: 4px solid #2271b1;
}

.sc-course-accordion-item input[type="radio"]:checked + .sc-course-accordion-header .sc-accordion-icon {
    transform: rotate(180deg);
}

.sc-course-accordion-item input[type="radio"]:checked ~ .sc-course-accordion-content {
    display: block !important;
}

.sc-course-accordion-item input[type="radio"]:disabled + .sc-course-accordion-header {
    opacity: 0.6;
    cursor: not-allowed;
}

.sc-course-accordion-item input[type="radio"]:disabled + .sc-course-accordion-header:hover {
    background-color: #f5f5f5 !important;
}

.sc-accordion-icon {
    transition: transform 0.3s ease;
}

/* Tooltip Styles */
.sc-course-accordion-header[data-tooltip] {
    position: relative;
}

.sc-course-accordion-header[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    right: 15px;
    padding: 12px 16px;
    background-color: #000000;
    color: #fff;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.6;
    white-space: normal;
    width: 320px;
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

.sc-course-accordion-header[data-tooltip]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 30px;
    border: 7px solid transparent;
    border-top-color: #000000;
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
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // نمایش/مخفی کردن جزئیات دوره با کلیک روی header
    $('.sc-course-accordion-header').on('click', function(e) {
        var $radio = $(this).prev('input');
        if ($radio.is(':disabled')) {
            return;
        }
        
        var $item = $(this).closest('.sc-course-accordion-item');
        var $content = $item.find('.sc-course-accordion-content');
        
        // انتخاب radio button
        $radio.prop('checked', true);
        
        // بستن سایر آکاردئون‌ها
        $('.sc-course-accordion-item').not($item).find('.sc-course-accordion-content').slideUp();
        $('.sc-course-accordion-item').not($item).find('input[type="radio"]').prop('checked', false);
        
        // باز/بسته کردن آکاردئون فعلی
        if ($content.is(':visible')) {
            $content.slideUp();
        } else {
            $content.slideDown();
        }
    });
    
    // تغییر آیکون هنگام باز/بسته شدن
    $('.sc-course-accordion-item input[type="radio"]').on('change', function() {
        var $item = $(this).closest('.sc-course-accordion-item');
        var $icon = $item.find('.sc-accordion-icon');
        var $content = $item.find('.sc-course-accordion-content');
        
        if ($(this).is(':checked')) {
            $icon.css('transform', 'rotate(180deg)');
            $content.slideDown();
        } else {
            $icon.css('transform', 'rotate(0deg)');
            $content.slideUp();
        }
    });
});
</script>
