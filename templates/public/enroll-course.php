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
?>

<div class="sc-enroll-course-page">
    <h2>ثبت نام در دوره</h2>
    
    <form method="POST" action="" class="sc-enroll-course-form">
        <?php wp_nonce_field('sc_enroll_course', 'sc_enroll_course_nonce'); ?>
        
        <div class="sc-courses-accordion">
            <?php foreach ($courses as $index => $course) : 
                $is_enrolled = in_array($course->id, $enrolled_courses);
                $formatted_price = '';
                if (function_exists('wc_price')) {
                    $formatted_price = wc_price($course->price);
                } else {
                    $formatted_price = number_format((float)$course->price, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }
                
                // محاسبه ظرفیت
                $enrolled_count = 0;
                $remaining = 0;
                if ($course->capacity) {
                    $enrolled_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $member_courses_table WHERE course_id = %d AND status = 'active'",
                        $course->id
                    ));
                    $remaining = $course->capacity - $enrolled_count;
                }
            ?>
                <div class="sc-course-accordion-item" style="border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; overflow: hidden;">
                    <input type="radio" 
                           name="course_id" 
                           id="course_<?php echo esc_attr($course->id); ?>" 
                           value="<?php echo esc_attr($course->id); ?>" 
                           class="sc-course-radio"
                           <?php echo $is_enrolled ? 'disabled' : ''; ?>
                           required>
                    
                    <label for="course_<?php echo esc_attr($course->id); ?>" 
                           class="sc-course-accordion-header" 
                           style="display: flex; align-items: center; padding: 15px; cursor: <?php echo $is_enrolled ? 'not-allowed' : 'pointer'; ?>; background-color: <?php echo $is_enrolled ? '#f5f5f5' : '#fff'; ?>; transition: background-color 0.3s;">
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
                                    <span style="color: #666; font-size: 14px;">
                                        <strong>ظرفیت:</strong> <?php echo esc_html($remaining); ?> / <?php echo esc_html($course->capacity); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($is_enrolled) : ?>
                                    <span style="color: #00a32a; font-weight: bold; background-color: #d4edda; padding: 5px 10px; border-radius: 4px;">
                                        ✓ ثبت‌نام شده
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                    
                    <div class="sc-course-accordion-content" style="display: none; padding: 0 15px 15px 50px; background-color: #f9f9f9; border-top: 1px solid #eee;">
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
        
        <p class="form-row" style="margin-top: 20px;">
            <button type="submit" name="sc_enroll_course" class="button button-primary" style="padding: 12px 30px; font-size: 16px;">
                ثبت نام و ایجاد صورت حساب
            </button>
        </p>
    </form>
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

