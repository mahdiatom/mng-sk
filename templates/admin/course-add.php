<?php
if ( ! defined('ABSPATH') ) exit;
$title = '';
$description = '';
$price = '';
$price_per_session = '';
$capacity = '';
$sessions_count = '';
$start_date = '';
$end_date = '';
$is_active = 1;
$chapter = '';

if ($course && isset($_GET['course_id'])) {
    $title = $course->title ?? '';
    $description = $course->description ?? '';
    $price = $course->price ?? '';
    $price_per_session = $course->price_per_session ?? '';
    $capacity = $course->capacity ?? '';
    $sessions_count = $course->sessions_count ?? '';
    $start_date = $course->start_date ?? '';
    $end_date = $course->end_date ?? '';
    $chapter = $course->chapter ?? '';
    $is_active = $course->is_active ?? 1;
}
global $wpdb;
$chapter_table = $wpdb->prefix . 'sc_chapter_categories';
$chapters = $wpdb->get_results(
                    "SELECT * FROM $chapter_table ORDER BY id ASC"
                );
$course_packages = [];
if (!empty($course->id) && function_exists('sc_get_course_packages')) {
    $course_packages = sc_get_course_packages((int) $course->id);
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
    </div>
<div class="wrap"> 
    <form action="" method="POST">
        <table class="form-table sc_form-table">
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
                        // تبدیل به عدد برای اطمینان از صحت
                        $price_display = is_numeric($price_display) ? floatval($price_display) : 0;
                        $price_per_session_display = is_numeric($price_per_session) ? floatval($price_per_session) : 0;
                        ?>
                        <div style="margin-bottom: 10px;">
                            <input type="text" 
                                   name="price" 
                                   id="price" 
                                   value="<?php echo $price_display > 0 ? number_format($price_display, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   placeholder="قیمت کل دوره"
                                   style="width: 300px;"
                                   dir="ltr"
                                   inputmode="numeric">
                            <p class="description" style="margin-top: 5px;">مبلغ کل دوره به تومان</p>
                        </div>
                        <div>
                            <input type="text" 
                                   name="price_per_session" 
                                   id="price_per_session" 
                                   value="<?php echo $price_per_session_display > 0 ? number_format($price_per_session_display, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   placeholder="قیمت هر جلسه"
                                   style="width: 300px;"
                                   dir="ltr"
                                   inputmode="numeric">
                            <p class="description" style="margin-top: 5px;">مبلغ هر جلسه به تومان (اختیاری)</p>
                        </div>
                        <input type="hidden" name="price_raw" id="price_raw" value="<?php echo esc_attr($price_display); ?>">
                        <input type="hidden" name="price_per_session_raw" id="price_per_session_raw" value="<?php echo esc_attr($price_per_session_display); ?>">
                        
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
                        <p class="description">حالت ساده: اگر پکیج تعریف نشده باشد، این تعداد جلسه استفاده می‌شود.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">پکیج‌های قیمت دوره</th>
                    <td>
                        <div id="sc-course-packages-wrap">
                            <p class="description" style="margin-bottom:10px;">
                                حالت حرفه‌ای: برای هر تعداد جلسه یک قیمت تعیین کن. در صورت داشتن حداقل یک پکیج، قیمت و تعداد جلسه حالت ساده در ثبت‌نام نادیده گرفته می‌شود.
                            </p>
                            <table class="widefat striped" style="max-width:760px;">
                                <thead>
                                    <tr>
                                        <th style="width:180px;">تعداد جلسه</th>
                                        <th style="width:260px;">قیمت (تومان)</th>
                                        <th style="width:120px;">حذف</th>
                                    </tr>
                                </thead>
                                <tbody id="sc-course-packages-body">
                                <?php if (!empty($course_packages)) : ?>
                                    <?php foreach ($course_packages as $pkg) : ?>
                                        <tr class="sc-course-package-row">
                                            <td>
                                                <input type="number" min="1" class="regular-text sc-pkg-sessions-input" name="pkg_sessions[]" value="<?php echo esc_attr((int) $pkg->sessions_count); ?>" style="max-width:150px;">
                                            </td>
                                            <td>
                                                <input type="text" class="regular-text sc-pkg-price-input" name="pkg_price[]" value="<?php echo esc_attr(number_format((float) $pkg->price, 0, '.', ',')); ?>" style="max-width:220px;" dir="ltr" inputmode="numeric">
                                                <input type="hidden" class="sc-pkg-price-raw" name="pkg_price_raw[]" value="<?php echo esc_attr((float) $pkg->price); ?>">
                                            </td>
                                            <td>
                                                <button type="button" class="button sc-remove-package-row">حذف</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            <p style="margin-top:10px;">
                                <button type="button" class="button button-secondary" id="sc-add-course-package-row">+ افزودن ردیف پکیج</button>
                            </p>
                            <p class="description">تعداد جلسه در هر دوره باید یکتا باشد. تکراری بودن سمت سرور رد می‌شود.</p>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="start_date_shamsi">تاریخ شروع</label></th>
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
                               placeholder="تاریخ شروع " 
                               readonly
                                >
                        <input type="hidden" name="start_date" id="start_date" value="<?php echo esc_attr($start_date ?? ''); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد بالا کلیک کنید</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="end_date_shamsi">تاریخ پایان</label></th>
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
                                >
                        <input type="hidden" name="end_date" id="end_date" value="<?php echo esc_attr($end_date ?? ''); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد بالا کلیک کنید </p>
                    </td>
                </tr>
                <th>
                    <td class="description">توجه: در صورتی که تاریخ دوره گذشته باشد امکان ثبت نام برای کاربر وجود ندارد در ثبت تاریخ دقت کنید.</td>

                    </th> 
                    
                    <tr>
                <th scope="row"><label for="chapter">شعبه</label></th>
                    <td>

                        <select name="chapter" id="chapter" class="regular-text">
                          <?php 
                      
                          
                          foreach($chapters as $ch){ ?>
                            <option value="<?php echo $ch->name; ?>" <?php selected($chapter,  $ch->name); ?>><?php echo $ch->name; ?></option>
                           <?php } ?>
                        </select>
                        <p class="description">برای افزودن شعبه از بخش شعبه های باشگاه شعبه های  خودرا اضافه کنید.</p>
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

<script type="text/javascript">
jQuery(document).ready(function($) {
    // فرمت کردن فیلد قیمت کل دوره
    if ($('#price').length && $('#price_raw').length) {
        scFormatPrice('#price', '#price_raw');
    }
    
    // فرمت کردن فیلد قیمت هر جلسه
    if ($('#price_per_session').length && $('#price_per_session_raw').length) {
        scFormatPrice('#price_per_session', '#price_per_session_raw');
    }

    if (typeof window.scInitCoursePackagesUI === 'function') {
        window.scInitCoursePackagesUI();
    }
});
</script>

