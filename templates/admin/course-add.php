<?php
$title = '';
$description = '';
$price = '';
$capacity = '';
$sessions_count = '';
$start_date = '';
$end_date = '';
$is_active = 1;

if ($course && isset($_GET['course_id'])) {
    $title = $course->title ?? '';
    $description = $course->description ?? '';
    $price = $course->price ?? '';
    $capacity = $course->capacity ?? '';
    $sessions_count = $course->sessions_count ?? '';
    $start_date = $course->start_date ?? '';
    $end_date = $course->end_date ?? '';
    $is_active = $course->is_active ?? 1;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo isset($_GET['course_id']) ? 'بروزرسانی دوره' : 'افزودن دوره جدید'; ?>
    </h1>
    <?php 
    if (isset($_GET['course_id'])) {
        ?>
        <a href="<?php echo admin_url('admin.php?page=sc-add-course'); ?>" class="page-title-action">افزودن دوره جدید</a>
        <?php 
    }
    ?>

    <form action="" method="POST">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="title">عنوان دوره <span style="color:red;">*</span></label></th>
                    <td><input name="title" type="text" id="title" value="<?php echo esc_attr($title ?? ''); ?>" class="regular-text" required></td>
                </tr>

                <tr>
                    <th scope="row"><label for="description">توضیحات</label></th>
                    <td>
                        <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea($description ?? ''); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="price">قیمت <span style="color:red;">*</span></label></th>
                    <td>
                        <?php
                        // استفاده از تنظیمات WooCommerce برای تعداد اعشار و جداکننده‌ها
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
                        
                        // نمایش قیمت با اعشار در صورت نیاز
                        $price_display = $price ?? '';
                        // اگر قیمت وجود دارد و اعشار دارد، آن را حفظ می‌کنیم
                        // اما برای نمایش در input، از فرمت عددی استفاده می‌کنیم
                        if ($price_display !== '') {
                            // اگر اعشار در تنظیمات WooCommerce فعال است، از آن استفاده می‌کنیم
                            // در غیر این صورت، اگر قیمت اعشار دارد، آن را حفظ می‌کنیم
                            if ($decimal_places > 0) {
                                // نمایش با تعداد اعشار مشخص شده در WooCommerce
                                $price_display = number_format((float)$price_display, $decimal_places, '.', '');
                            } else {
                                // اگر اعشار در تنظیمات غیرفعال است، اما قیمت اعشار دارد، آن را حفظ می‌کنیم
                                $price_display = (float)$price_display;
                            }
                        }
                        // محاسبه step برای input number
                        $step_value = $decimal_places > 0 ? '0.' . str_repeat('0', $decimal_places - 1) . '1' : '0.01';
                        
                        // نمایش فرمت شده قیمت برای نمایش
                        $formatted_price_display = '';
                        if ($price_display !== '') {
                            if (function_exists('wc_price')) {
                                $formatted_price_display = wc_price($price_display);
                            } else {
                                $formatted_price_display = number_format((float)$price_display, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                            }
                        }
                        ?>
                        <input name="price" type="number" id="price" value="<?php echo esc_attr($price_display); ?>" class="regular-text" step="<?php echo esc_attr($step_value); ?>" min="0" required>
                        <span id="price-formatted-display" style="margin-right: 10px; color: #2271b1; font-weight: bold;">
                            <?php echo $formatted_price_display ? $formatted_price_display : ''; ?>
                        </span>
                        <p class="description">
                            مبلغ دوره 
                            <?php if (function_exists('wc_price')) : ?>
                                (از تنظیمات WooCommerce استفاده می‌شود - <?php echo $decimal_places > 0 ? $decimal_places . ' رقم اعشار' : 'بدون اعشار'; ?>)
                            <?php else : ?>
                                (تومان - می‌توانید اعشار وارد کنید)
                            <?php endif; ?>
                        </p>
                        <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            // تنظیمات WooCommerce برای فرمت کردن قیمت
                            var wcPriceSettings = {
                                decimals: <?php echo $decimal_places; ?>,
                                decimalSep: '<?php echo esc_js($decimal_separator); ?>',
                                thousandSep: '<?php echo esc_js($thousand_separator); ?>',
                                currencySymbol: '<?php echo function_exists('get_woocommerce_currency_symbol') ? esc_js(get_woocommerce_currency_symbol()) : 'تومان'; ?>',
                                currencyPos: '<?php echo function_exists('get_option') ? esc_js(get_option('woocommerce_currency_pos', 'right')) : 'right'; ?>'
                            };
                            
                            // تابع فرمت کردن قیمت با استفاده از تنظیمات WooCommerce
                            function formatPrice(value) {
                                if (!value || value === '') {
                                    return '';
                                }
                                
                                var numValue = parseFloat(value);
                                if (isNaN(numValue)) {
                                    return '';
                                }
                                
                                // فرمت کردن با تنظیمات WooCommerce
                                var formatted = numValue.toFixed(wcPriceSettings.decimals);
                                var parts = formatted.split('.');
                                
                                // اضافه کردن جداکننده هزارگان (سه رقم سه رقم)
                                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, wcPriceSettings.thousandSep);
                                
                                // ترکیب قسمت صحیح و اعشار
                                var result = parts.join(wcPriceSettings.decimalSep);
                                
                                // اضافه کردن نماد واحد پولی بر اساس موقعیت
                                var priceFormatted = '';
                                if (wcPriceSettings.currencyPos === 'left') {
                                    priceFormatted = wcPriceSettings.currencySymbol + ' ' + result;
                                } else if (wcPriceSettings.currencyPos === 'right') {
                                    priceFormatted = result + ' ' + wcPriceSettings.currencySymbol;
                                } else if (wcPriceSettings.currencyPos === 'left_space') {
                                    priceFormatted = wcPriceSettings.currencySymbol + ' ' + result;
                                } else if (wcPriceSettings.currencyPos === 'right_space') {
                                    priceFormatted = result + ' ' + wcPriceSettings.currencySymbol;
                                } else {
                                    priceFormatted = result + ' ' + wcPriceSettings.currencySymbol;
                                }
                                
                                return priceFormatted;
                            }
                            
                            // به‌روزرسانی نمایش فرمت شده هنگام تغییر قیمت
                            $('#price').on('input change', function() {
                                var priceValue = $(this).val();
                                var formatted = formatPrice(priceValue);
                                
                                if (formatted) {
                                    $('#price-formatted-display').html(formatted).show();
                                } else {
                                    $('#price-formatted-display').html('').hide();
                                }
                            });
                            
                            // نمایش اولیه
                            if ($('#price').val()) {
                                $('#price').trigger('change');
                            }
                        });
                        </script>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="capacity">ظرفیت</label></th>
                    <td>
                        <input name="capacity" type="number" id="capacity" value="<?php echo esc_attr($capacity ?? ''); ?>" class="regular-text" min="1">
                        <p class="description">تعداد مجاز ثبت‌نام. در صورت خالی بودن، نامحدود خواهد بود.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="sessions_count">تعداد جلسات</label></th>
                    <td>
                        <input name="sessions_count" type="number" id="sessions_count" value="<?php echo esc_attr($sessions_count ?? ''); ?>" class="regular-text" min="1">
                        <p class="description">تعداد جلسات دوره. در صورت خالی بودن، می‌توانید بعداً مشخص کنید.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="start_date">تاریخ شروع</label></th>
                    <td>
                        <input name="start_date" type="date" id="start_date" value="<?php echo esc_attr($start_date ?? ''); ?>" class="regular-text">
                        <p class="description">برای دوره‌های متناوب می‌توانید خالی بگذارید.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="end_date">تاریخ پایان</label></th>
                    <td>
                        <input name="end_date" type="date" id="end_date" value="<?php echo esc_attr($end_date ?? ''); ?>" class="regular-text">
                        <p class="description">برای دوره‌های متناوب می‌توانید خالی بگذارید.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">وضعیت</th>
                    <td>
                        <label class="switch">
                            <input name="is_active" type="checkbox" <?php checked($is_active, 1); ?> value="1">
                            <span class="slider round"></span> فعال
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" name="submit_course" class="button button-primary">
                <?php echo isset($_GET['course_id']) ? 'بروزرسانی دوره' : 'ثبت دوره جدید'; ?>
            </button>
        </p>
    </form>
</div>

