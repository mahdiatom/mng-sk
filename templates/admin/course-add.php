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
$restriction_enabled = 0;
$allowed_teams = [];
$allowed_levels = [];
$allowed_gender = 'both';
$course_type = 'group';

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
    $restriction_enabled = isset($course->restriction_enabled) ? (int)$course->restriction_enabled : 0;
    $allowed_gender = !empty($course->allowed_gender) ? $course->allowed_gender : 'both';
    $course_type = !empty($course->course_type) && in_array($course->course_type, ['group', 'private'], true) ? $course->course_type : 'group';
    $allowed_teams = !empty($course->allowed_teams) ? json_decode($course->allowed_teams, true) : [];
    $allowed_levels = !empty($course->allowed_levels) ? json_decode($course->allowed_levels, true) : [];
    if (!is_array($allowed_teams)) {
        $allowed_teams = [];
    }
    if (!is_array($allowed_levels)) {
        $allowed_levels = [];
    }
}
global $wpdb;
$chapter_table = $wpdb->prefix . 'sc_chapter_categories';
$chapters = $wpdb->get_results(
                    "SELECT * FROM $chapter_table ORDER BY id ASC"
                );
$team_table = $wpdb->prefix . 'sc_team_categories';
$teams = $wpdb->get_results("SELECT * FROM $team_table ORDER BY id ASC");
$level_table = $wpdb->prefix . 'sc_level_categories';
$levels = $wpdb->get_results("SELECT * FROM $level_table ORDER BY id ASC");
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
<div class="wrap sc-course-add-wrap">
    <form action="" method="POST" class="sc-course-add-form" >
        <table class="form-table sc_form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="title">عنوان دوره <span style="color:red;">*</span></label></th>
                    <td><input name="title" type="text" id="title" value="<?php echo esc_attr($title ?? ''); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="course_type">نوع دوره</label></th>
                    <td>
                        <select name="course_type" id="course_type" class="regular-text">
                            <option value="group" <?php selected($course_type, 'group'); ?>>گروهی</option>
                            <option value="private" <?php selected($course_type, 'private'); ?>>خصوصی / نیمه خصوصی</option>
                        </select>
                        <p class="description">دوره خصوصی در تب جداگانه کلاس خصوصی برای کاربر نمایش داده می‌شود.</p>
                    </td>
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
                        <div class="sc-course-price-field">
                            <input type="text" 
                                   name="price" 
                                   id="price" 
                                   value="<?php echo $price_display > 0 ? number_format($price_display, 0, '.', ',') : ''; ?>" 
                                   class="regular-text sc-input-full-width" 
                                   placeholder="قیمت کل دوره"
                                   dir="ltr"
                                   inputmode="numeric">
                            <p class="description" style="margin-top: 5px;">مبلغ کل دوره به تومان</p>
                        </div>
                        <div class="sc-course-price-field">
                            <input type="text" 
                                   name="price_per_session" 
                                   id="price_per_session" 
                                   value="<?php echo $price_per_session_display > 0 ? number_format($price_per_session_display, 0, '.', ',') : ''; ?>" 
                                   class="regular-text sc-input-full-width" 
                                   placeholder="قیمت هر جلسه"
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
                            <div class="sc-table-scroll sc-table-scroll--packages">
                            <table class="widefat striped sc-course-packages-table">
                                <thead>
                                    <tr>
                                        <th class="sc-pkg-col-sessions">تعداد جلسه</th>
                                        <th class="sc-pkg-col-price">قیمت (تومان)</th>
                                        <th class="sc-pkg-col-actions">حذف</th>
                                    </tr>
                                </thead>
                                <tbody id="sc-course-packages-body">
                                <?php if (!empty($course_packages)) : ?>
                                    <?php foreach ($course_packages as $pkg) : ?>
                                        <tr class="sc-course-package-row">
                                            <td data-label="تعداد جلسه">
                                                <input type="number" min="1" class="regular-text sc-pkg-sessions-input sc-course-pkg-field" name="pkg_sessions[]" value="<?php echo esc_attr((int) $pkg->sessions_count); ?>">
                                            </td>
                                            <td data-label="قیمت (تومان)">
                                                <input type="text" class="regular-text sc-pkg-price-input sc-course-pkg-field" name="pkg_price[]" value="<?php echo esc_attr(number_format((float) $pkg->price, 0, '.', ',')); ?>" dir="ltr" inputmode="numeric">
                                                <input type="hidden" class="sc-pkg-price-raw" name="pkg_price_raw[]" value="<?php echo esc_attr((float) $pkg->price); ?>">
                                            </td>
                                            <td data-label="">
                                                <button type="button" class="button sc-remove-package-row">حذف</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            </div>
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
                <tr>
                    <td colspan="2" class="description sc-course-date-notice">توجه: در صورتی که تاریخ دوره گذشته باشد امکان ثبت نام برای کاربر وجود ندارد در ثبت تاریخ دقت کنید.</td>
                </tr>

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
                    <th scope="row">برنامه هفتگی کلاس</th>
                    <td>
                        <p class="description" style="margin-bottom:10px;">
                            روزهای برگزاری را تیک بزنید و بازهٔ ساعت را وارد کنید (مثال ۰۸:۰۰ تا ۱۰:۰۰). می‌توانید چند ردیف برای زمان‌های مختلف داشته باشید. این داده بعداً برای حضور و غیاب و دستگاه قابل استفاده است.
                        </p>
                        <?php
                        $wd_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
                        if (!isset($course_schedule_blocks) || !is_array($course_schedule_blocks)) {
                            $course_schedule_blocks = [['wd' => [], 'start' => '08:00:00', 'end' => '10:00:00']];
                        }
                        ?>
                        <div class="sc-table-scroll sc-table-scroll--schedule">
                        <table class="widefat striped sc-course-schedule-table">
                            <thead>
                                <tr>
                                    <th class="sc-csched-col-days">روزهای هفته</th>
                                    <th class="sc-csched-col-time">شروع</th>
                                    <th class="sc-csched-col-time">پایان</th>
                                    <th class="sc-csched-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody id="sc-csched-tbody">
                                <?php foreach ($course_schedule_blocks as $bi => $block) :
                                    $sel = isset($block['wd']) && is_array($block['wd']) ? array_map('intval', $block['wd']) : [];
                                    $st = isset($block['start']) ? substr((string) $block['start'], 0, 5) : '';
                                    $en = isset($block['end']) ? substr((string) $block['end'], 0, 5) : '';
                                    ?>
                                <tr class="sc-csched-row">
                                    <td class="sc-csched-wd-cell" data-label="روزهای هفته">
                                        <div class="sc-csched-wd-grid">
                                        <?php foreach ($wd_labels as $num => $lab) : ?>
                                            <label class="sc-csched-wd-label">
                                                <input type="checkbox" name="csched_row[<?php echo (int) $bi; ?>][wd][]" value="<?php echo esc_attr((string) $num); ?>" <?php checked(in_array((int) $num, $sel, true)); ?>>
                                                <?php echo esc_html($lab); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td data-label="شروع"><input type="time" class="regular-text sc-csched-time-input" name="csched_row[<?php echo (int) $bi; ?>][start]" value="<?php echo esc_attr($st); ?>"></td>
                                    <td data-label="پایان"><input type="time" class="regular-text sc-csched-time-input" name="csched_row[<?php echo (int) $bi; ?>][end]" value="<?php echo esc_attr($en); ?>"></td>
                                    <td class="sc-csched-actions-cell" data-label=""><button type="button" class="sc_button sc-csched-remove-row" style="margin-right: 10px; ">حذف</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <p style="margin-top:10px;">
                            <button type="button" class="button" id="sc-csched-add">+ افزودن ردیف زمان</button>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">محدودیت دوره</th>
                    <td>
                        <label>
                            <input type="checkbox" name="restriction_enabled" id="restriction_enabled" value="1" <?php checked($restriction_enabled, 1); ?>>
                            اعمال محدودیت برای نمایش/ثبت نام
                        </label>
                        <div id="course-restrictions-box" style="margin-top:12px; <?php echo $restriction_enabled ? '' : 'display:none;'; ?>">
                            <p><strong>جنسیت مجاز</strong></p>
                            <select name="allowed_gender" class="regular-text">
                                <option value="both" <?php selected($allowed_gender, 'both'); ?>>هردو</option>
                                <option value="male" <?php selected($allowed_gender, 'male'); ?>>مرد</option>
                                <option value="female" <?php selected($allowed_gender, 'female'); ?>>زن</option>
                            </select>
                            <p style="margin-top:10px;"><strong>تیم‌های مجاز</strong></p>
                            <?php if (!empty($teams)) : foreach ($teams as $team) : ?>
                                <label style="display:inline-block;margin-left:12px; margin-top: 10px;">
                                    <input type="checkbox" name="allowed_teams[]" value="<?php echo esc_attr($team->name); ?>" <?php checked(in_array($team->name, $allowed_teams, true)); ?>>
                                    <?php echo esc_html($team->name); ?>
                                </label>
                            <?php endforeach; endif; ?>
                            <p style="margin-top:10px;"><strong>سطح‌های مجاز</strong></p>
                            <?php if (!empty($levels)) : foreach ($levels as $level) : ?>
                                <label style="display:inline-block;margin-left:12px; margin-top: 10px;">
                                    <input type="checkbox" name="allowed_levels[]" value="<?php echo esc_attr($level->name); ?>" <?php checked(in_array($level->name, $allowed_levels, true)); ?>>
                                    <?php echo esc_html($level->name); ?>
                                </label>
                            <?php endforeach; endif; ?>
                            <p class="description">در صورت فعال بودن محدودیت، فقط بازیکنانی که با شروط بالا سازگارند دوره را می‌بینند و می‌توانند ثبت‌نام کنند.</p>
                        </div>
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

    (function () {
        var $tb = jQuery('#sc-csched-tbody');
        if (!$tb.length) {
            return;
        }
        function reindexCschedRows() {
            $tb.find('tr.sc-csched-row').each(function (idx) {
                jQuery(this).find('input[name^="csched_row["]').each(function () {
                    var $el = jQuery(this);
                    var n = $el.attr('name');
                    if (!n) {
                        return;
                    }
                    $el.attr('name', n.replace(/csched_row\[\d+\]/, 'csched_row[' + idx + ']'));
                });
            });
        }
        jQuery('#sc-csched-add').on('click', function () {
            var $rows = $tb.find('tr.sc-csched-row');
            var $clone = $rows.last().clone();
            $clone.find('input[type="checkbox"]').prop('checked', false);
            $clone.find('input[type="time"]').val('');
            $tb.append($clone);
            reindexCschedRows();
        });
        $tb.on('click', '.sc-csched-remove-row', function () {
            if ($tb.find('tr.sc-csched-row').length <= 1) {
                return;
            }
            jQuery(this).closest('tr').remove();
            reindexCschedRows();
        });
    })();

    $('#restriction_enabled').on('change', function () {
        if ($(this).is(':checked')) {
            $('#course-restrictions-box').slideDown(150);
        } else {
            $('#course-restrictions-box').slideUp(150);
        }
    });
});
</script>

