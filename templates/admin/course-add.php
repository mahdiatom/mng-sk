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
$course_chapters_selected = [];
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
    if (function_exists('sc_get_course_chapters')) {
        $course_chapters_selected = sc_get_course_chapters((int) $course->id);
    }
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

// مربی‌های فعال + تخصیص‌های فعلی این دوره (شعبه => مربی‌ها)
$coaches_table = $wpdb->prefix . 'sc_coaches';
$all_active_coaches = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
$course_coach_assignments_map = (!empty($course->id) && function_exists('sc_get_course_coach_assignments_map'))
    ? sc_get_course_coach_assignments_map((int) $course->id)
    : [];

$schedule_chapter_options = !empty($course_chapters_selected) ? $course_chapters_selected : [];
if (empty($schedule_chapter_options) && !empty($chapter)) {
    $schedule_chapter_options = [(string) $chapter];
}

$sc_coach_labels_for_js = [];
foreach ((array) $all_active_coaches as $sc_co) {
    $sc_label = trim((string) $sc_co->first_name . ' ' . (string) $sc_co->last_name);
    $sc_coach_labels_for_js[(int) $sc_co->id] = $sc_label !== '' ? $sc_label : ('مربی #' . (int) $sc_co->id);
}

$sc_schedule_coach_ids_for_chapter = static function ($chapter_name) use ($schedule_chapter_options, $course_coach_assignments_map) {
    $ids = [];
    $chapters = ($chapter_name !== '') ? [$chapter_name] : $schedule_chapter_options;
    foreach ($chapters as $ch_name) {
        if (!isset($course_coach_assignments_map[$ch_name]) || !is_array($course_coach_assignments_map[$ch_name])) {
            continue;
        }
        foreach (array_keys($course_coach_assignments_map[$ch_name]) as $coach_id) {
            $ids[(int) $coach_id] = (int) $coach_id;
        }
    }
    return array_values($ids);
};


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
                <th scope="row"><label>شعبه‌ها <span style="color:red;">*</span></label></th>
                    <td>
                        <div id="sc-course-chapters-box" class="sc-course-chapters-box">
                            <?php if (!empty($chapters)) : ?>
                                <?php foreach ($chapters as $ch) : ?>
                                    <label class="sc-course-chapter-item">
                                        <input type="checkbox"
                                               name="course_chapters[]"
                                               class="sc-course-chapter-cb"
                                               value="<?php echo esc_attr($ch->name); ?>"
                                               <?php checked(in_array($ch->name, $course_chapters_selected, true) || ($chapter === $ch->name && empty($course_chapters_selected))); ?>>
                                        <?php echo esc_html($ch->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p>هنوز شعبه‌ای تعریف نشده است.</p>
                            <?php endif; ?>
                        </div>
                        <p class="description">می‌توانید چند شعبه برای یک دوره انتخاب کنید. بازیکن هنگام ثبت‌نام یکی را انتخاب می‌کند.</p>
                        <p id="sc-course-chapters-error" style="display:none;color:#d63638;font-weight:600;margin:8px 0 0;">لطفاً حداقل یک شعبه را انتخاب کنید.</p>

                        <?php if (!empty($chapters) && !empty($all_active_coaches)) : ?>
                        <div id="sc-course-coaches-box" class="sc-course-coaches-box">
                            <input type="hidden" name="course_coach_assign_present" value="1">
                            <strong class="sc-course-coaches-title">مربی‌های هر شعبه</strong>
                            <?php foreach ($chapters as $ch) : ?>
                                <div class="sc-course-chapter-coaches"
                                     data-chapter="<?php echo esc_attr($ch->name); ?>">
                                    <span class="sc-chapter-coaches-heading">شعبه «<?php echo esc_html($ch->name); ?>»:</span>
                                    <div class="sc-chapter-coach-list">
                                    <?php foreach ($all_active_coaches as $co) :
                                        $co_label = isset($sc_coach_labels_for_js[(int) $co->id]) ? $sc_coach_labels_for_js[(int) $co->id] : ('مربی #' . (int) $co->id);
                                        $is_assigned = isset($course_coach_assignments_map[(string) $ch->name][(int) $co->id]);
                                        ?>
                                        <label class="sc-chapter-coach-item">
                                            <input type="checkbox"
                                                   class="sc-course-coach-assign-cb"
                                                   name="course_coach_assign[<?php echo esc_attr($ch->name); ?>][<?php echo (int) $co->id; ?>]"
                                                   value="1"
                                                   data-coach-id="<?php echo (int) $co->id; ?>"
                                                   <?php checked($is_assigned); ?>>
                                            <?php echo esc_html($co_label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <p class="description">با ذخیره دوره، این انتخاب در «دوره‌های» همان مربی هم به‌صورت خودکار فعال/غیرفعال می‌شود. درصد دستمزد از فرم مربی تنظیم می‌شود.</p>
                        </div>
                        <?php endif; ?>
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
                                    <th class="sc-csched-col-chapter">شعبه</th>
                                    <th class="sc-csched-col-coach">مربی</th>
                                    <th class="sc-csched-col-actions"></th>
                                </tr>
                            </thead>
                            <tbody id="sc-csched-tbody">
                                <?php foreach ($course_schedule_blocks as $bi => $block) :
                                    $sel = isset($block['wd']) && is_array($block['wd']) ? array_map('intval', $block['wd']) : [];
                                    $st = isset($block['start']) ? substr((string) $block['start'], 0, 5) : '';
                                    $en = isset($block['end']) ? substr((string) $block['end'], 0, 5) : '';
                                    $block_chapter = isset($block['chapter']) ? (string) $block['chapter'] : '';
                                    $block_coach = isset($block['coach_id']) ? (int) $block['coach_id'] : 0;
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
                                    <td data-label="شعبه">
                                        <select name="csched_row[<?php echo (int) $bi; ?>][chapter]" class="sc-csched-chapter-select">
                                            <option value="">همه شعبه‌ها</option>
                                            <?php foreach ($schedule_chapter_options as $sch_ch) : ?>
                                                <option value="<?php echo esc_attr($sch_ch); ?>" <?php selected($block_chapter, $sch_ch); ?>><?php echo esc_html($sch_ch); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td data-label="مربی">
                                        <?php
                                        $row_coach_ids = $sc_schedule_coach_ids_for_chapter($block_chapter);
                                        ?>
                                        <select name="csched_row[<?php echo (int) $bi; ?>][coach]" class="sc-csched-coach-select">
                                            <option value="0">همه مربی‌ها</option>
                                            <?php foreach ($row_coach_ids as $row_coach_id) :
                                                if (!isset($sc_coach_labels_for_js[$row_coach_id])) {
                                                    continue;
                                                }
                                                ?>
                                                <option value="<?php echo (int) $row_coach_id; ?>" <?php selected($block_coach, (int) $row_coach_id); ?>><?php echo esc_html($sc_coach_labels_for_js[$row_coach_id]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
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
var scCourseCoachLabels = <?php echo wp_json_encode($sc_coach_labels_for_js, JSON_UNESCAPED_UNICODE); ?>;
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
                jQuery(this).find('input[name^="csched_row["], select[name^="csched_row["]').each(function () {
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
            $clone.find('select').prop('selectedIndex', 0);
            $tb.append($clone);
            reindexCschedRows();
            if (typeof scSyncScheduleSelects === 'function') {
                scSyncScheduleSelects();
            }
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

    // اعتبارسنجی: برای دوره گروهی حداقل یک شعبه باید انتخاب شود
    $('.sc-course-add-form').on('submit', function (e) {
        var courseType = $('#course_type').val() || 'group';
        if (courseType !== 'group') {
            return true;
        }
        var $cbs = $('.sc-course-chapter-cb');
        if (!$cbs.length) {
            return true;
        }
        if (!$cbs.filter(':checked').length) {
            e.preventDefault();
            var $box = $('#sc-course-chapters-box');
            var $err = $('#sc-course-chapters-error');
            $err.show();
            $box.css('border-color', '#d63638');
            if ($box.length && $box[0].scrollIntoView) {
                $box[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }
        return true;
    });

    // نمایش گروه مربی‌های هر شعبه فقط وقتی آن شعبه انتخاب شده باشد
    function scGetSelectedChapters() {
        var chapters = [];
        $('.sc-course-chapter-cb:checked').each(function () {
            chapters.push(String($(this).val() || ''));
        });
        return chapters;
    }

    function scGetCoachesForChapter(chapterName) {
        var coaches = [];
        var seen = {};
        if (!chapterName) {
            scGetSelectedChapters().forEach(function (ch) {
                scGetCoachesForChapter(ch).forEach(function (c) {
                    if (!seen[c.id]) {
                        seen[c.id] = true;
                        coaches.push(c);
                    }
                });
            });
            return coaches;
        }
        var $grp = $('.sc-course-chapter-coaches').filter(function () {
            return String($(this).data('chapter') || '') === chapterName;
        });
        $grp.find('.sc-course-coach-assign-cb:checked').each(function () {
            var id = parseInt($(this).data('coach-id') || $(this).attr('name').replace(/.*\[(\d+)\]$/, '$1'), 10);
            if (!id || seen[id]) {
                return;
            }
            seen[id] = true;
            coaches.push({
                id: id,
                label: (window.scCourseCoachLabels && scCourseCoachLabels[id]) ? scCourseCoachLabels[id] : ('مربی #' + id)
            });
        });
        return coaches;
    }

    function scSyncScheduleCoachSelect($coachSel) {
        if (!$coachSel || !$coachSel.length) {
            return;
        }
        var $row = $coachSel.closest('tr');
        var chapter = String($row.find('.sc-csched-chapter-select').val() || '');
        var current = String($coachSel.val() || '0');
        var coaches = scGetCoachesForChapter(chapter);
        $coachSel.find('option:not(:first)').remove();
        coaches.forEach(function (c) {
            $coachSel.append($('<option></option>').val(String(c.id)).text(c.label));
        });
        if (current !== '0' && coaches.some(function (c) { return String(c.id) === current; })) {
            $coachSel.val(current);
        } else {
            $coachSel.val('0');
        }
    }

    window.scSyncScheduleSelects = function () {
        var chapters = scGetSelectedChapters();
        $('.sc-csched-chapter-select').each(function () {
            var $sel = $(this);
            var current = String($sel.val() || '');
            $sel.find('option:not(:first)').remove();
            chapters.forEach(function (ch) {
                $sel.append($('<option></option>').val(ch).text(ch));
            });
            if (current && chapters.indexOf(current) !== -1) {
                $sel.val(current);
            } else {
                $sel.val('');
            }
            scSyncScheduleCoachSelect($sel.closest('tr').find('.sc-csched-coach-select'));
        });
    };

    function scSyncChapterCoachGroups() {
        var checkedChapters = {};
        $('.sc-course-chapter-cb:checked').each(function () {
            checkedChapters[$(this).val()] = true;
        });
        $('.sc-course-chapter-coaches').each(function () {
            var $grp = $(this);
            var visible = !!checkedChapters[$grp.data('chapter')];
            $grp.toggle(visible);
            if (!visible) {
                $grp.find('input[type="checkbox"]').prop('checked', false);
            }
        });
        scSyncScheduleSelects();
    }
    scSyncChapterCoachGroups();

    $(document).on('change', '.sc-course-chapter-cb', function () {
        if ($('.sc-course-chapter-cb:checked').length) {
            $('#sc-course-chapters-error').hide();
            $('#sc-course-chapters-box').css('border-color', '#ddd');
        }
        scSyncChapterCoachGroups();
    });

    $(document).on('change', '.sc-course-coach-assign-cb', function () {
        scSyncScheduleSelects();
    });

    $(document).on('change', '.sc-csched-chapter-select', function () {
        scSyncScheduleCoachSelect($(this).closest('tr').find('.sc-csched-coach-select'));
    });
});
</script>

