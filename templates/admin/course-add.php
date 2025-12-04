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
                        
                        $price_display = $price ?? 0;
                        ?>
                        <input type="text" 
                               name="price" 
                               id="price" 
                               value="<?php echo $price_display > 0 ? number_format($price_display, 0, '.', ',') : ''; ?>" 
                               class="regular-text" 
                               placeholder="0"
                               style="width: 300px;"
                               dir="ltr"
                               inputmode="numeric"
                               required>
                        <input type="hidden" name="price_raw" id="price_raw" value="<?php echo esc_attr($price_display); ?>">
                        <p class="description">مبلغ دوره به تومان (با جدا کردن سه رقم سه رقم)</p>
                        <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            // فرمت کردن قیمت به صورت سه رقم سه رقم (روش مستقیم)
                            var $priceInput = $('#price');
                            var $priceRaw = $('#price_raw');
                            
                            $priceInput.on('input', function() {
                                var $this = $(this);
                                var value = $this.val();
                                
                                // حذف تمام کاماها و کاراکترهای غیر عددی
                                var cleaned = value.replace(/,/g, '').replace(/\D/g, '');
                                
                                // اگر خالی است
                                if (cleaned === '' || cleaned === '0') {
                                    $this.val('');
                                    $priceRaw.val('0');
                                    return;
                                }
                                
                                // فرمت کردن با کاما (سه رقم سه رقم)
                                var formatted = parseInt(cleaned, 10).toLocaleString('en-US');
                                $this.val(formatted);
                                
                                // ذخیره مقدار خالص در hidden input
                                $priceRaw.val(cleaned);
                            });
                            
                            // هنگام blur
                            $priceInput.on('blur', function() {
                                var value = $(this).val();
                                var cleaned = value.replace(/,/g, '');
                                if (cleaned === '' || cleaned === '0') {
                                    $(this).val('');
                                    $priceRaw.val('0');
                                }
                            });
                            
                            // قبل از submit
                            $priceInput.closest('form').on('submit', function() {
                                var rawValue = $priceRaw.val() || '0';
                                $priceInput.val(rawValue);
                            });
                            
                            // فرمت کردن مقدار اولیه در صورت وجود
                            if ($priceInput.val()) {
                                $priceInput.trigger('input');
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
                    <th scope="row"><label for="start_date_shamsi">تاریخ شروع (شمسی)</label></th>
                    <td>
                        <?php
                        $start_date_shamsi = '';
                        if (!empty($start_date)) {
                            $start_date_shamsi = sc_date_shamsi_date_only($start_date);
                        } else {
                            // اگر دوره جدید است، تاریخ امروز را به صورت پیش‌فرض قرار می‌دهیم
                            $today_timestamp = time();
                            $today_date = getdate($today_timestamp);
                            $today_jalali = gregorian_to_jalali($today_date['year'], $today_date['mon'], $today_date['mday']);
                            $start_date_shamsi = $today_jalali[0] . '/' . 
                                                 ($today_jalali[1] < 10 ? '0' . $today_jalali[1] : $today_jalali[1]) . '/' . 
                                                 ($today_jalali[2] < 10 ? '0' . $today_jalali[2] : $today_jalali[2]);
                        }
                        ?>
                        <input name="start_date_shamsi" type="text" id="start_date_shamsi" 
                               value="<?php echo esc_attr($start_date_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تاریخ شروع (شمسی)" 
                               readonly
                               style="width: 300px; padding: 5px;">
                        <input type="hidden" name="start_date" id="start_date" value="<?php echo esc_attr($start_date ?? ''); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="end_date_shamsi">تاریخ پایان (شمسی)</label></th>
                    <td>
                        <?php
                        $end_date_shamsi = '';
                        if (!empty($end_date)) {
                            $end_date_shamsi = sc_date_shamsi_date_only($end_date);
                        } else {
                            // اگر دوره جدید است، تاریخ امروز را به صورت پیش‌فرض قرار می‌دهیم
                            $today_timestamp = time();
                            $today_date = getdate($today_timestamp);
                            $today_jalali = gregorian_to_jalali($today_date['year'], $today_date['mon'], $today_date['mday']);
                            $end_date_shamsi = $today_jalali[0] . '/' . 
                                               ($today_jalali[1] < 10 ? '0' . $today_jalali[1] : $today_jalali[1]) . '/' . 
                                               ($today_jalali[2] < 10 ? '0' . $today_jalali[2] : $today_jalali[2]);
                        }
                        ?>
                        <input name="end_date_shamsi" type="text" id="end_date_shamsi" 
                               value="<?php echo esc_attr($end_date_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تاریخ پایان (شمسی)" 
                               readonly
                               style="width: 300px; padding: 5px;">
                        <input type="hidden" name="end_date" id="end_date" value="<?php echo esc_attr($end_date ?? ''); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید (اختیاری - در صورت خالی بودن محدودیتی برای ثبت‌نام وجود ندارد)</p>
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

<script>
jQuery(document).ready(function($) {
    // تابع تبدیل تاریخ شمسی به میلادی
    function jalaliToGregorian(jy, jm, jd) {
        var gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;
        var days = (365 * jy) + ((parseInt(jy / 33)) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 
                   78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        gy += 400 * (parseInt(days / 146097));
        days = days % 146097;
        if (days > 36524) {
            gy += 100 * (parseInt(--days / 36524));
            days = days % 36524;
            if (days >= 365) days++;
        }
        gy += 4 * (parseInt(days / 1461));
        days = days % 1461;
        if (days > 365) {
            gy += parseInt((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var gd = days + 1;
        var sal_a = [0, 31, ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0)) ? 29 : 28,
                     31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > sal_a[gm]) {
            gd -= sal_a[gm];
            gm++;
        }
        return [gy, gm, gd];
    }
    
    function convertShamsiToGregorian(shamsiDate) {
        if (!shamsiDate || shamsiDate === '') return '';
        var parts = shamsiDate.split('/');
        if (parts.length !== 3) return '';
        var jy = parseInt(parts[0]);
        var jm = parseInt(parts[1]);
        var jd = parseInt(parts[2]);
        var gregorian = jalaliToGregorian(jy, jm, jd);
        return gregorian[0] + '-' + 
               (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '-' + 
               (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
    }
    
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    $('#start_date_shamsi, #end_date_shamsi').on('change', function() {
        var inputId = $(this).attr('id');
        var shamsiDate = $(this).val();
        
        if (shamsiDate) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                if (inputId === 'start_date_shamsi') {
                    $('#start_date').val(gregorianDate);
                } else if (inputId === 'end_date_shamsi') {
                    $('#end_date').val(gregorianDate);
                }
            }
        } else {
            // اگر تاریخ خالی شد، فیلد میلادی را هم خالی کن
            if (inputId === 'start_date_shamsi') {
                $('#start_date').val('');
            } else if (inputId === 'end_date_shamsi') {
                $('#end_date').val('');
            }
        }
    });
    
    // تبدیل اولیه اگر تاریخ وجود دارد یا تاریخ پیش‌فرض را تنظیم کنیم
    if ($('#start_date_shamsi').val()) {
        $('#start_date_shamsi').trigger('change');
    } else {
        // اگر دوره جدید است و تاریخ پیش‌فرض تنظیم شده، آن را تبدیل کن
        setTimeout(function() {
            if ($('#start_date_shamsi').val() && !$('#start_date').val()) {
                $('#start_date_shamsi').trigger('change');
            }
        }, 100);
    }
    
    if ($('#end_date_shamsi').val()) {
        $('#end_date_shamsi').trigger('change');
    } else {
        // اگر دوره جدید است و تاریخ پیش‌فرض تنظیم شده، آن را تبدیل کن
        setTimeout(function() {
            if ($('#end_date_shamsi').val() && !$('#end_date').val()) {
                $('#end_date_shamsi').trigger('change');
            }
        }, 100);
    }
});
</script>

