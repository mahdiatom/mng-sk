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
$private_variable_coach_pricing = 0;
if (!isset($has_grouping)) {
    $has_grouping = 0;
}
if (!isset($player_can_select_group)) {
    $player_can_select_group = 0;
}
if (!isset($course_group_rows) || !is_array($course_group_rows)) {
    $course_group_rows = [];
}

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
    $private_variable_coach_pricing = !empty($course->private_variable_coach_pricing) ? 1 : 0;
    if (function_exists('sc_course_has_grouping_enabled') && !empty($course->id)) {
        $has_grouping = sc_course_has_grouping_enabled((int) $course->id) ? 1 : 0;
    } elseif (isset($course->has_grouping)) {
        $has_grouping = (int) $course->has_grouping;
    }
    if (function_exists('sc_course_player_can_select_group') && !empty($course->id)) {
        $player_can_select_group = sc_course_player_can_select_group((int) $course->id) ? 1 : 0;
    } elseif (isset($course->player_can_select_group)) {
        $player_can_select_group = (int) $course->player_can_select_group;
    }
    if (empty($course_group_rows) && function_exists('sc_get_course_groups') && !empty($course->id)) {
        foreach (sc_get_course_groups((int) $course->id) as $grow) {
            $course_group_rows[] = [
                'name' => isset($grow->group_name) ? (string) $grow->group_name : '',
                'description' => isset($grow->description) ? (string) $grow->description : '',
            ];
        }
    }
    $allowed_teams = !empty($course->allowed_teams) ? json_decode($course->allowed_teams, true) : [];
    $allowed_levels = !empty($course->allowed_levels) ? json_decode($course->allowed_levels, true) : [];
    if (!is_array($allowed_teams)) {
        $allowed_teams = [];
    }
    if (!is_array($allowed_levels)) {
        $allowed_levels = [];
    }
}
if (empty($course_group_rows)) {
    $course_group_rows = [['name' => '', 'description' => '']];
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
$course_coach_branch_meta_map = (!empty($course->id) && function_exists('sc_get_course_coach_branch_meta_map'))
    ? sc_get_course_coach_branch_meta_map((int) $course->id)
    : [];
$private_session_options = [];
if (!empty($course->id) && $course_type === 'private' && $private_variable_coach_pricing && !empty($course_packages)) {
    foreach ($course_packages as $pkg) {
        $private_session_options[] = (int) $pkg->sessions_count;
    }
}

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
    <div id="sc-course-form-summary-error" class="notice notice-error inline" style="display:none;margin:16px 0 20px;"></div>
    <?php
    $form_action = add_query_arg('page', 'sc-add-course', admin_url('admin.php'));
    $edit_course_id = 0;
    if (!empty($course) && !empty($course->id)) {
        $edit_course_id = (int) $course->id;
    } elseif (!empty($_GET['course_id'])) {
        $edit_course_id = absint($_GET['course_id']);
    }
    if ($edit_course_id) {
        $form_action = add_query_arg('course_id', $edit_course_id, $form_action);
    }
    ?>
    <form action="<?php echo esc_url($form_action); ?>" method="POST" class="sc-course-add-form">
        <?php if ($edit_course_id) : ?>
            <input type="hidden" name="course_id" value="<?php echo (int) $edit_course_id; ?>">
        <?php endif; ?>
        <input type="hidden" name="course_groups_json" id="course_groups_json" value="">
        <div class="sc-course-form-panels">

                <section class="sc-course-panel sc-course-panel--basic">
                    <header class="sc-course-panel__header">
                        <div class="sc-course-panel__intro">
                            <div class="sc-course-panel__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h10M4 18h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </div>
                            <div>
                                <h3 class="sc-course-panel__title">اطلاعات پایه دوره</h3>
                                <p class="sc-course-panel__desc">عنوان، نوع و توضیحات دوره را مشخص کنید.</p>
                            </div>
                        </div>
                    </header>
                    <div class="sc-course-panel__body">
                        <div class="sc-course-fields sc-course-fields--2">
                            <div class="sc-course-field">
                                <label class="sc-course-field__label" for="title">عنوان دوره <span class="sc-required">*</span></label>
                                <input name="title" type="text" id="title" value="<?php echo esc_attr($title ?? ''); ?>" class="sc-course-input" required>
                                <p id="sc-course-title-error" class="sc-course-field__error" style="display:none;">لطفاً عنوان دوره را وارد کنید.</p>
                            </div>
                            <div class="sc-course-field">
                                <label class="sc-course-field__label" for="course_type">نوع دوره</label>
                                <select name="course_type" id="course_type" class="sc-course-input sc-course-select">
                                    <option value="group" <?php selected($course_type, 'group'); ?>>گروهی</option>
                                    <option value="private" <?php selected($course_type, 'private'); ?>>خصوصی / نیمه خصوصی</option>
                                </select>
                                <p class="sc-course-field__hint">دوره خصوصی در تب جداگانه کلاس خصوصی برای کاربر نمایش داده می‌شود.</p>
                            </div>
                            <div class="sc-course-field sc-course-field--full">
                                <label class="sc-course-field__label" for="description">توضیحات</label>
                                <textarea name="description" id="description" rows="4" class="sc-course-input sc-course-textarea"><?php echo esc_textarea($description ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="sc-course-panel sc-course-panel--pricing">
                    <header class="sc-course-panel__header">
                        <div class="sc-course-panel__intro">
                            <div class="sc-course-panel__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 100 7h5a3.5 3.5 0 110 7H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div>
                                <h3 class="sc-course-panel__title">قیمت، ظرفیت و پکیج‌ها</h3>
                                <p class="sc-course-panel__desc">تعرفه ثبت‌نام، ظرفیت و پکیج‌های جلسه را در این بخش تنظیم کنید.</p>
                            </div>
                        </div>
                    </header>
                    <div class="sc-course-panel__body">
                        <div class="sc-course-panel__sub sc-private-course-row" style="<?php echo $course_type === 'private' ? '' : 'display:none;'; ?>">
                            <label class="sc-course-toggle-chip">
                                <input type="checkbox" name="private_variable_coach_pricing" id="private_variable_coach_pricing" value="1" <?php checked($private_variable_coach_pricing, 1); ?>>
                                <span>قیمت‌ها برای هر مربی متفاوت است</span>
                            </label>
                            <p class="sc-course-field__hint">با فعال بودن این گزینه، قیمت و ظرفیت هر مربی در هر شعبه جداگانه تعیین می‌شود و گزینه‌های تعداد جلسه جایگزین پکیج‌های قیمت می‌شود.</p>
                        </div>

                        <div class="sc-course-fields sc-course-fields--2 sc-standard-pricing-field">
                            <div class="sc-course-field sc-course-price-row">
                                <?php
                                $price_display = $price ?? 0;
                                $price_display = is_numeric($price_display) ? floatval($price_display) : 0;
                                $price_per_session_display = is_numeric($price_per_session) ? floatval($price_per_session) : 0;
                                ?>
                                <label class="sc-course-field__label" for="price">قیمت <span class="sc-required sc-price-required-mark">*</span></label>
                                <div class="sc-course-price-field">
                                    <input type="text" name="price" id="price"
                                           value="<?php echo $price_display > 0 ? number_format($price_display, 0, '.', ',') : ''; ?>"
                                           class="sc-course-input" placeholder="قیمت کل دوره" dir="ltr" inputmode="numeric">
                                    <p class="sc-course-field__hint">مبلغ کل دوره به تومان</p>
                                </div>
                                <div class="sc-course-price-field">
                                    <input type="text" name="price_per_session" id="price_per_session"
                                           value="<?php echo $price_per_session_display > 0 ? number_format($price_per_session_display, 0, '.', ',') : ''; ?>"
                                           class="sc-course-input" placeholder="قیمت هر جلسه" dir="ltr" inputmode="numeric">
                                    <p class="sc-course-field__hint">مبلغ هر جلسه به تومان (اختیاری)</p>
                                </div>
                                <input type="hidden" name="price_raw" id="price_raw" value="<?php echo esc_attr($price_display); ?>">
                                <input type="hidden" name="price_per_session_raw" id="price_per_session_raw" value="<?php echo esc_attr($price_per_session_display); ?>">
                                <p id="sc-course-price-error" class="sc-course-field__error" style="display:none;"></p>
                            </div>
                            <div class="sc-course-field sc-standard-pricing-field">
                                <label class="sc-course-field__label" for="capacity">ظرفیت</label>
                                <input name="capacity" type="number" id="capacity" value="<?php echo esc_attr($capacity ?? ''); ?>" class="sc-course-input" min="1">
                                <p class="sc-course-field__hint">تعداد مجاز ثبت‌نام. در صورت خالی بودن، نامحدود خواهد بود.</p>
                            </div>
                            <div class="sc-course-field sc-standard-pricing-field">
                                <label class="sc-course-field__label" for="sessions_count">تعداد جلسات (حالت ساده)</label>
                                <input name="sessions_count" type="number" id="sessions_count" value="<?php echo esc_attr($sessions_count ?? ''); ?>" class="sc-course-input" min="1">
                                <p class="sc-course-field__hint">اگر پکیج تعریف نشده باشد، این تعداد جلسه استفاده می‌شود.</p>
                            </div>
                        </div>

                        <div id="sc-course-packages-row" class="sc-course-panel__sub sc-standard-pricing-field">
                            <div id="sc-course-packages-wrap">
                                <h4 class="sc-course-subtitle">پکیج‌های قیمت دوره</h4>
                                <p class="sc-course-field__hint">برای هر تعداد جلسه یک قیمت تعیین کنید. در صورت داشتن حداقل یک پکیج، قیمت و تعداد جلسه حالت ساده نادیده گرفته می‌شود.</p>
                                <div id="sc-course-packages-body" class="sc-course-pkg-list">
                                <?php if (!empty($course_packages)) : ?>
                                    <?php foreach ($course_packages as $pkg) : ?>
                                        <article class="sc-course-pkg-card sc-course-package-row">
                                            <div class="sc-course-field">
                                                <label class="sc-course-field__label">تعداد جلسه</label>
                                                <input type="number" min="1" class="sc-course-input sc-pkg-sessions-input sc-course-pkg-field" name="pkg_sessions[]" value="<?php echo esc_attr((int) $pkg->sessions_count); ?>">
                                            </div>
                                            <div class="sc-course-field">
                                                <label class="sc-course-field__label">قیمت (تومان)</label>
                                                <input type="text" class="sc-course-input sc-pkg-price-input sc-course-pkg-field" name="pkg_price[]" value="<?php echo esc_attr(number_format((float) $pkg->price, 0, '.', ',')); ?>" dir="ltr" inputmode="numeric">
                                                <input type="hidden" class="sc-pkg-price-raw" name="pkg_price_raw[]" value="<?php echo esc_attr((float) $pkg->price); ?>">
                                            </div>
                                            <button type="button" class="sc-course-pkg-remove sc-remove-package-row" title="حذف پکیج">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                            </button>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </div>
                                <button type="button" class="sc-course-add-btn" id="sc-add-course-package-row">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    افزودن پکیج
                                </button>
                                <p class="sc-course-field__hint">تعداد جلسه در هر دوره باید یکتا باشد.</p>
                                <p id="sc-course-packages-error" class="sc-course-field__error" style="display:none;"></p>
                            </div>
                        </div>

                        <div id="sc-private-session-options-row" class="sc-course-panel__sub" style="display:none;">
                            <h4 class="sc-course-subtitle">گزینه‌های تعداد جلسه (خصوصی)</h4>
                            <p class="sc-course-field__hint">برای کلاس خصوصی با قیمت متفاوت مربی: فقط تعداد جلسات را تعیین کنید.</p>
                            <div id="sc-private-session-options-body" class="sc-course-sess-list">
                            <?php
                            $sess_opts = !empty($private_session_options) ? $private_session_options : [10];
                            foreach ($sess_opts as $sess_n) : ?>
                                <article class="sc-course-sess-card">
                                    <div class="sc-course-field">
                                        <label class="sc-course-field__label">تعداد جلسه</label>
                                        <input type="number" min="1" name="private_sess_counts[]" value="<?php echo esc_attr((int) $sess_n); ?>" class="sc-course-input">
                                    </div>
                                    <button type="button" class="sc-course-pkg-remove sc-remove-private-sess-row" title="حذف">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    </button>
                                </article>
                            <?php endforeach; ?>
                            </div>
                            <button type="button" class="sc-course-add-btn" id="sc-add-private-sess-row">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                افزودن تعداد جلسه
                            </button>
                            <p id="sc-private-sess-error" class="sc-course-field__error" style="display:none;"></p>
                        </div>
                    </div>
                </section>

                <section class="sc-course-panel sc-course-panel--dates">
                    <header class="sc-course-panel__header">
                        <div class="sc-course-panel__intro">
                            <div class="sc-course-panel__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="17" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </div>
                            <div>
                                <h3 class="sc-course-panel__title">بازه زمانی دوره</h3>
                                <p class="sc-course-panel__desc">تاریخ شروع و پایان دوره را به شمسی انتخاب کنید.</p>
                            </div>
                        </div>
                    </header>
                    <div class="sc-course-panel__body">
                        <div class="sc-course-fields sc-course-fields--2">
                            <div class="sc-course-field">
                                <label class="sc-course-field__label" for="start_date_shamsi">تاریخ شروع</label>
                                <?php
                                $start_date_shamsi = '';
                                if (!empty($start_date)) {
                                    $start_date_shamsi = sc_date_shamsi_date_only($start_date);
                                } else {
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
                                       class="sc-course-input persian-date-input" placeholder="تاریخ شروع" readonly>
                                <input type="hidden" name="start_date" id="start_date" value="<?php echo esc_attr($start_date ?? ''); ?>">
                            </div>
                            <div class="sc-course-field">
                                <label class="sc-course-field__label" for="end_date_shamsi">تاریخ پایان</label>
                                <?php
                                $end_date_shamsi = '';
                                if (!empty($end_date)) {
                                    $end_date_shamsi = sc_date_shamsi_date_only($end_date);
                                } else {
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
                                       class="sc-course-input persian-date-input" placeholder="تاریخ پایان (شمسی)" readonly>
                                <input type="hidden" name="end_date" id="end_date" value="<?php echo esc_attr($end_date ?? ''); ?>">
                            </div>
                        </div>
                        <div class="sc-course-alert sc-course-alert--info sc-course-date-notice">
                            توجه: در صورتی که تاریخ دوره گذشته باشد امکان ثبت‌نام برای کاربر وجود ندارد؛ در ثبت تاریخ دقت کنید.
                        </div>
                    </div>
                </section>

                <section class="sc-course-panel sc-course-panel--branches">
                    <header class="sc-course-panel__header">
                        <div class="sc-course-panel__intro">
                            <div class="sc-course-panel__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div>
                                <h3 class="sc-course-panel__title">شعبه‌ها و مربی‌ها <span class="sc-required">*</span></h3>
                                <p class="sc-course-panel__desc">شعبه‌های برگزاری دوره و مربی هر شعبه را انتخاب کنید.</p>
                            </div>
                        </div>
                    </header>
                    <div class="sc-course-panel__body">
                        <div id="sc-course-chapters-box" class="sc-course-chapters-box">
                            <?php if (!empty($chapters)) : ?>
                                <?php foreach ($chapters as $ch) : ?>
                                    <label class="sc-course-chapter-pill sc-course-chapter-item">
                                        <input type="checkbox"
                                               name="course_chapters[]"
                                               class="sc-course-chapter-cb"
                                               value="<?php echo esc_attr($ch->name); ?>"
                                               <?php checked(in_array($ch->name, $course_chapters_selected, true) || ($chapter === $ch->name && empty($course_chapters_selected))); ?>>
                                        <span class="sc-course-chapter-pill__text"><?php echo esc_html($ch->name); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="sc-course-field__hint">هنوز شعبه‌ای تعریف نشده است.</p>
                            <?php endif; ?>
                        </div>
                        <p class="sc-course-field__hint">می‌توانید چند شعبه برای یک دوره انتخاب کنید. بازیکن هنگام ثبت‌نام یکی را انتخاب می‌کند.</p>
                        <p id="sc-course-chapters-error" class="sc-course-field__error" style="display:none;">لطفاً حداقل یک شعبه را انتخاب کنید.</p>

                        <?php if (!empty($chapters) && !empty($all_active_coaches)) : ?>
                        <div id="sc-course-coaches-box" class="sc-course-coaches-box">
                            <input type="hidden" name="course_coach_assign_present" value="1">
                            <h4 class="sc-course-subtitle">مربی‌های هر شعبه</h4>
                            <?php foreach ($chapters as $ch) : ?>
                                <div class="sc-course-chapter-coaches" data-chapter="<?php echo esc_attr($ch->name); ?>">
                                    <span class="sc-chapter-coaches-heading">شعبه «<?php echo esc_html($ch->name); ?>»</span>
                                    <div class="sc-chapter-coach-list">
                                    <?php foreach ($all_active_coaches as $co) :
                                        $co_label = isset($sc_coach_labels_for_js[(int) $co->id]) ? $sc_coach_labels_for_js[(int) $co->id] : ('مربی #' . (int) $co->id);
                                        $is_assigned = isset($course_coach_assignments_map[(string) $ch->name][(int) $co->id]);
                                        ?>
                                        <label class="sc-chapter-coach-pill sc-chapter-coach-item">
                                            <input type="checkbox"
                                                   class="sc-course-coach-assign-cb"
                                                   name="course_coach_assign[<?php echo esc_attr($ch->name); ?>][<?php echo (int) $co->id; ?>]"
                                                   value="1"
                                                   data-coach-id="<?php echo (int) $co->id; ?>"
                                                   <?php checked($is_assigned); ?>>
                                            <span class="sc-chapter-coach-pill__text"><?php echo esc_html($co_label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <p class="sc-course-field__hint">با ذخیره دوره، این انتخاب در «دوره‌های» همان مربی هم به‌صورت خودکار فعال/غیرفعال می‌شود.</p>
                        </div>

                        <div id="sc-coach-branch-pricing-wrap" class="sc-course-panel__sub sc-private-course-row" style="margin-top:4px;<?php echo ($course_type === 'private') ? '' : 'display:none;'; ?>">
                            <h4 class="sc-course-subtitle">قیمت و ظرفیت هر مربی در شعبه</h4>
                            <p class="sc-course-field__hint">برای کلاس خصوصی، ظرفیت هر بازه زمانی بر اساس مربی+شعبه محاسبه می‌شود.</p>
                            <div class="sc-course-table-wrap">
                            <table class="sc-course-modern-table">
                                <thead>
                                    <tr>
                                        <th>شعبه</th>
                                        <th>مربی</th>
                                        <th class="sc-branch-price-col">قیمت هر جلسه</th>
                                        <th>ظرفیت</th>
                                    </tr>
                                </thead>
                                <tbody id="sc-coach-branch-pricing-body">
                                <?php foreach ($chapters as $ch) :
                                    foreach ($all_active_coaches as $co) :
                                        $ch_name = (string) $ch->name;
                                        $co_id = (int) $co->id;
                                        if (!isset($course_coach_assignments_map[$ch_name][$co_id])) {
                                            continue;
                                        }
                                        $meta = isset($course_coach_branch_meta_map[$ch_name][$co_id]) ? $course_coach_branch_meta_map[$ch_name][$co_id] : null;
                                        $branch_price = $meta ? (float) $meta['price_per_session'] : 0;
                                        $branch_capacity = ($meta && $meta['capacity'] !== null) ? (int) $meta['capacity'] : '';
                                        $co_label = isset($sc_coach_labels_for_js[$co_id]) ? $sc_coach_labels_for_js[$co_id] : ('مربی #' . $co_id);
                                        ?>
                                        <tr data-chapter="<?php echo esc_attr($ch_name); ?>" data-coach-id="<?php echo (int) $co_id; ?>">
                                            <td><?php echo esc_html($ch_name); ?></td>
                                            <td><?php echo esc_html($co_label); ?></td>
                                            <td class="sc-branch-price-col"<?php echo ($course_type === 'private' && $private_variable_coach_pricing) ? '' : ' style="display:none;"'; ?>>
                                                <input type="text" dir="ltr" class="sc-course-input sc-branch-price-input"
                                                       name="coach_branch_price[<?php echo esc_attr($ch_name); ?>][<?php echo (int) $co_id; ?>]"
                                                       value="<?php echo $branch_price > 0 ? esc_attr(number_format($branch_price, 0, '.', ',')) : ''; ?>">
                                                <input type="hidden" class="sc-branch-price-raw"
                                                       name="coach_branch_price_raw[<?php echo esc_attr($ch_name); ?>][<?php echo (int) $co_id; ?>]"
                                                       value="<?php echo esc_attr($branch_price); ?>">
                                            </td>
                                            <td>
                                                <input type="number" min="1" class="sc-course-input sc-course-input--sm"
                                                       name="coach_branch_capacity[<?php echo esc_attr($ch_name); ?>][<?php echo (int) $co_id; ?>]"
                                                       value="<?php echo esc_attr($branch_capacity); ?>" placeholder="۱">
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <p id="sc-coach-branch-pricing-hint" class="sc-course-field__hint" style="margin-top:8px;<?php echo ($course_type === 'private') ? '' : 'display:none;'; ?>">پس از انتخاب شعبه و مربی، ردیف‌های این جدول به‌صورت خودکار ساخته می‌شوند.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="sc-course-panel sc-course-panel--groups">
                    <header class="sc-course-panel__header">
                        <div class="sc-course-panel__intro">
                            <div class="sc-course-panel__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div>
                                <h3 class="sc-course-panel__title">گروه‌بندی دوره</h3>
                                <p class="sc-course-panel__desc">بازیکنان را به گروه‌ها یا بخش‌های جدا داخل یک دوره تقسیم کنید.</p>
                            </div>
                        </div>
                    </header>
                    <div class="sc-course-panel__body">
                        <label class="sc-course-toggle-chip sc-course-toggle-chip--lg">
                            <input type="checkbox" name="has_grouping" id="has_grouping" value="1" <?php checked($has_grouping, 1); ?>>
                            <span>دوره دارای گروه‌بندی است</span>
                        </label>
                        <div id="sc-course-groups-box" class="sc-course-groups-box" style="<?php echo $has_grouping ? '' : 'display:none;'; ?>">
                            <div id="sc-course-groups-tbody" class="sc-course-group-list">
                                <?php
                                if (!isset($course_group_rows) || !is_array($course_group_rows) || empty($course_group_rows)) {
                                    $course_group_rows = [['name' => '', 'description' => '']];
                                }
                                foreach ($course_group_rows as $gi => $grow) :
                                    $gname = isset($grow['name']) ? (string) $grow['name'] : '';
                                    $gdesc = isset($grow['description']) ? (string) $grow['description'] : '';
                                    ?>
                                    <article class="sc-course-group-card sc-course-group-row">
                                        <div class="sc-course-field">
                                            <label class="sc-course-field__label">نام گروه</label>
                                            <input type="text" class="sc-course-input sc-course-group-name" name="course_group_row[<?php echo (int) $gi; ?>][name]" value="<?php echo esc_attr($gname); ?>" placeholder="مثلاً گروه ۱">
                                        </div>
                                        <div class="sc-course-field">
                                            <label class="sc-course-field__label">توضیحات</label>
                                            <input type="text" class="sc-course-input" name="course_group_row[<?php echo (int) $gi; ?>][description]" value="<?php echo esc_attr($gdesc); ?>" placeholder="توضیح کوتاه (اختیاری)">
                                        </div>
                                        <button type="button" class="sc-course-pkg-remove sc-course-group-remove" title="حذف گروه">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </button>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="sc-course-add-btn" id="sc-course-group-add">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                افزودن گروه
                            </button>
                            <div class="sc-course-panel__sub" style="margin-top:14px;">
                                <label class="sc-course-toggle-chip">
                                    <input type="checkbox" name="player_can_select_group" id="player_can_select_group" value="1" <?php checked($player_can_select_group, 1); ?>>
                                    <span>امکان انتخاب گروه توسط بازیکن هنگام ثبت‌نام</span>
                                </label>
                                <p class="sc-course-field__hint">در صورت فعال بودن، بازیکن در ثبت‌نام گروه خود را انتخاب می‌کند و برنامه هفتگی همان گروه نمایش داده می‌شود.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <?php
                $wd_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
                $wd_short = [1 => 'ش', 2 => 'ی', 3 => 'د', 4 => 'س', 5 => 'چ', 6 => 'پ', 7 => 'ج'];
                if (!isset($course_schedule_blocks) || !is_array($course_schedule_blocks)) {
                    $course_schedule_blocks = [['wd' => [], 'start' => '08:00:00', 'end' => '10:00:00']];
                }
                ?>
                <section class="sc-course-panel sc-csched-panel">
                            <div class="sc-csched-panel__header">
                                <div class="sc-csched-panel__intro">
                                    <div class="sc-csched-panel__icon" aria-hidden="true">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="4" width="18" height="17" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M3 9h18M8 3v3M16 3v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="sc-csched-panel__title">برنامه هفتگی کلاس</h3>
                                        <p class="sc-csched-panel__desc">روزهای برگزاری را انتخاب کنید و بازهٔ ساعت هر سانس را مشخص کنید. این اطلاعات در حضور و غیاب و نمایش برنامه به بازیکن استفاده می‌شود.</p>
                                    </div>
                                </div>
                                <div class="sc-csched-panel__badge">
                                    <span class="sc-csched-panel__badge-num" id="sc-csched-count">0</span>
                                    <span class="sc-csched-panel__badge-label">سانس فعال</span>
                                </div>
                            </div>

                            <div class="sc-csched-week-preview" aria-hidden="true">
                                <?php foreach ($wd_labels as $num => $lab) : ?>
                                    <div class="sc-csched-week-day" data-wd="<?php echo esc_attr((string) $num); ?>">
                                        <span class="sc-csched-week-day__label"><?php echo esc_html($wd_short[$num] ?? $lab); ?></span>
                                        <div class="sc-csched-week-day__track">
                                            <div class="sc-csched-week-day__bar"></div>
                                        </div>
                                        <span class="sc-csched-week-day__name"><?php echo esc_html($lab); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="sc-csched-schedule-error" class="sc-csched-alert sc-csched-alert--error" style="display:none;"></div>

                            <div id="sc-csched-list" class="sc-csched-list">
                                <?php foreach ($course_schedule_blocks as $bi => $block) :
                                    $sel = isset($block['wd']) && is_array($block['wd']) ? array_map('intval', $block['wd']) : [];
                                    $st = isset($block['start']) ? substr((string) $block['start'], 0, 5) : '';
                                    $en = isset($block['end']) ? substr((string) $block['end'], 0, 5) : '';
                                    $block_chapter = isset($block['chapter']) ? (string) $block['chapter'] : '';
                                    $block_coach = isset($block['coach_id']) ? (int) $block['coach_id'] : 0;
                                    $block_uses_group = !empty($block['uses_group']);
                                    $block_group = isset($block['group_name']) ? (string) $block['group_name'] : '';
                                    ?>
                                <article class="sc-csched-card sc-csched-row">
                                    <header class="sc-csched-card__head">
                                        <span class="sc-csched-card__num">سانس <?php echo (int) $bi + 1; ?></span>
                                        <button type="button" class="sc-csched-remove-row" title="حذف سانس">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                            <span>حذف</span>
                                        </button>
                                    </header>

                                    <div class="sc-csched-card__body">
                                        <div class="sc-csched-field sc-csched-field--days">
                                            <label class="sc-csched-field__label">روزهای هفته</label>
                                            <div class="sc-csched-wd-grid">
                                            <?php foreach ($wd_labels as $num => $lab) : ?>
                                                <label class="sc-csched-wd-pill">
                                                    <input type="checkbox" name="csched_row[<?php echo (int) $bi; ?>][wd][]" value="<?php echo esc_attr((string) $num); ?>" <?php checked(in_array((int) $num, $sel, true)); ?>>
                                                    <span class="sc-csched-wd-pill__text"><?php echo esc_html($lab); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="sc-csched-card__grid">
                                            <div class="sc-csched-field sc-csched-field--time">
                                                <label class="sc-csched-field__label">ساعت شروع</label>
                                                <input type="time" class="sc-csched-time-input" name="csched_row[<?php echo (int) $bi; ?>][start]" value="<?php echo esc_attr($st); ?>">
                                            </div>
                                            <div class="sc-csched-time-sep" aria-hidden="true">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </div>
                                            <div class="sc-csched-field sc-csched-field--time">
                                                <label class="sc-csched-field__label">ساعت پایان</label>
                                                <input type="time" class="sc-csched-time-input" name="csched_row[<?php echo (int) $bi; ?>][end]" value="<?php echo esc_attr($en); ?>">
                                            </div>
                                            <div class="sc-csched-field">
                                                <label class="sc-csched-field__label">شعبه</label>
                                                <select name="csched_row[<?php echo (int) $bi; ?>][chapter]" class="sc-csched-chapter-select">
                                                    <option value="">همه شعبه‌ها</option>
                                                    <?php foreach ($schedule_chapter_options as $sch_ch) : ?>
                                                        <option value="<?php echo esc_attr($sch_ch); ?>" <?php selected($block_chapter, $sch_ch); ?>><?php echo esc_html($sch_ch); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="sc-csched-field">
                                                <label class="sc-csched-field__label">مربی</label>
                                                <?php $row_coach_ids = $sc_schedule_coach_ids_for_chapter($block_chapter); ?>
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
                                            </div>
                                        </div>

                                        <div class="sc-csched-group-cell sc-csched-field sc-csched-field--group">
                                            <label class="sc-csched-uses-group-label sc-csched-toggle-chip">
                                                <input type="checkbox" class="sc-csched-uses-group-cb" name="csched_row[<?php echo (int) $bi; ?>][uses_group]" value="1" <?php checked($block_uses_group); ?>>
                                                <span>این سانس مخصوص یک گروه است</span>
                                            </label>
                                            <select name="csched_row[<?php echo (int) $bi; ?>][group]" class="sc-csched-group-select" <?php echo $block_uses_group ? '' : 'disabled'; ?>>
                                                <option value="">انتخاب گروه</option>
                                                <?php foreach ($course_group_rows as $gopt) :
                                                    $gopt_name = isset($gopt['name']) ? (string) $gopt['name'] : '';
                                                    if ($gopt_name === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <option value="<?php echo esc_attr($gopt_name); ?>" <?php selected($block_group, $gopt_name); ?>><?php echo esc_html($gopt_name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <footer class="sc-csched-card__foot"></footer>
                                </article>
                                <?php endforeach; ?>
                            </div>

                            <div class="sc-csched-panel__footer">
                                <button type="button" class="sc-csched-add-btn" id="sc-csched-add">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    افزودن سانس جدید
                                </button>
                            </div>
                </section>

                <section class="sc-course-panel sc-course-panel--restrictions">
                    <header class="sc-course-panel__header">
                        <div class="sc-course-panel__intro">
                            <div class="sc-course-panel__icon" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div>
                                <h3 class="sc-course-panel__title">محدودیت ثبت‌نام</h3>
                                <p class="sc-course-panel__desc">در صورت نیاز، نمایش و ثبت‌نام دوره را به گروه خاصی محدود کنید.</p>
                            </div>
                        </div>
                    </header>
                    <div class="sc-course-panel__body">
                        <label class="sc-course-toggle-chip sc-course-toggle-chip--lg">
                            <input type="checkbox" name="restriction_enabled" id="restriction_enabled" value="1" <?php checked($restriction_enabled, 1); ?>>
                            <span>اعمال محدودیت برای نمایش/ثبت‌نام</span>
                        </label>
                        <div id="course-restrictions-box" class="sc-course-restrictions-box" style="<?php echo $restriction_enabled ? '' : 'display:none;'; ?>">
                            <div class="sc-course-field">
                                <label class="sc-course-field__label">جنسیت مجاز</label>
                                <select name="allowed_gender" class="sc-course-input sc-course-select">
                                    <option value="both" <?php selected($allowed_gender, 'both'); ?>>هردو</option>
                                    <option value="male" <?php selected($allowed_gender, 'male'); ?>>مرد</option>
                                    <option value="female" <?php selected($allowed_gender, 'female'); ?>>زن</option>
                                </select>
                            </div>
                            <div class="sc-course-field">
                                <label class="sc-course-field__label">تیم‌های مجاز</label>
                                <div class="sc-course-pill-grid">
                                <?php if (!empty($teams)) : foreach ($teams as $team) : ?>
                                    <label class="sc-course-filter-pill">
                                        <input type="checkbox" name="allowed_teams[]" value="<?php echo esc_attr($team->name); ?>" <?php checked(in_array($team->name, $allowed_teams, true)); ?>>
                                        <span><?php echo esc_html($team->name); ?></span>
                                    </label>
                                <?php endforeach; else : ?>
                                    <p class="sc-course-field__hint">تیمی تعریف نشده است.</p>
                                <?php endif; ?>
                                </div>
                            </div>
                            <div class="sc-course-field">
                                <label class="sc-course-field__label">سطح‌های مجاز</label>
                                <div class="sc-course-pill-grid">
                                <?php if (!empty($levels)) : foreach ($levels as $level) : ?>
                                    <label class="sc-course-filter-pill">
                                        <input type="checkbox" name="allowed_levels[]" value="<?php echo esc_attr($level->name); ?>" <?php checked(in_array($level->name, $allowed_levels, true)); ?>>
                                        <span><?php echo esc_html($level->name); ?></span>
                                    </label>
                                <?php endforeach; else : ?>
                                    <p class="sc-course-field__hint">سطحی تعریف نشده است.</p>
                                <?php endif; ?>
                                </div>
                            </div>
                            <p class="sc-course-field__hint">در صورت فعال بودن محدودیت، فقط بازیکنان سازگار دوره را می‌بینند و می‌توانند ثبت‌نام کنند.</p>
                        </div>
                    </div>
                </section>

                <section class="sc-course-panel sc-course-panel--status">
                    <div class="sc-course-panel__body sc-course-status-bar">
                        <div class="sc-course-status-bar__info">
                            <h3 class="sc-course-panel__title">وضعیت دوره</h3>
                            <p class="sc-course-panel__desc">دوره‌های غیرفعال در لیست ثبت‌نام نمایش داده نمی‌شوند.</p>
                        </div>
                        <label class="sc-course-status-switch switch">
                            <input name="is_active" type="checkbox" <?php checked($is_active, 1); ?> value="1">
                            <span class="slider round"></span>
                            <span class="sc-course-status-switch__label">فعال</span>
                        </label>
                    </div>
                </section>

        </div>

        <p class="submit sc-course-submit-bar">
            <button type="submit" name="submit_course" class="button button-primary sc-course-submit-btn">
                <?php echo isset($_GET['course_id']) ? 'بروزرسانی دوره' : 'ثبت دوره جدید'; ?>
            </button>
        </p>
    </form>
</div>

<script type="text/javascript">
var scCourseCoachLabels = <?php echo wp_json_encode($sc_coach_labels_for_js, JSON_UNESCAPED_UNICODE); ?>;
var scCourseCoachBranchMeta = <?php echo wp_json_encode($course_coach_branch_meta_map, JSON_UNESCAPED_UNICODE); ?>;
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
        var $list = jQuery('#sc-csched-list');
        if (!$list.length) {
            return;
        }

        function scRefreshCschedCardLabels() {
            $list.find('.sc-csched-row').each(function (idx) {
                jQuery(this).find('.sc-csched-card__num').text('سانس ' + (idx + 1));
            });
        }

        window.scRefreshCschedWeekPreview = function () {
            var dayCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0 };
            var activeSlots = 0;
            $list.find('.sc-csched-row').each(function () {
                var $row = jQuery(this);
                var days = $row.find('input[type=checkbox][name*="[wd]"]:checked');
                if (!days.length) {
                    return;
                }
                activeSlots++;
                days.each(function () {
                    var v = parseInt(jQuery(this).val(), 10);
                    if (dayCounts[v] !== undefined) {
                        dayCounts[v]++;
                    }
                });
            });
            jQuery('#sc-csched-count').text(activeSlots);
            jQuery('.sc-csched-week-day').each(function () {
                var $day = jQuery(this);
                var wd = parseInt($day.attr('data-wd'), 10);
                var count = dayCounts[wd] || 0;
                $day.toggleClass('is-active', count > 0);
                $day.attr('data-count', count);
                var height = count ? Math.min(100, 24 + count * 22) : 8;
                $day.find('.sc-csched-week-day__bar').css('height', height + '%');
            });
        };

        function reindexCschedRows() {
            $list.find('.sc-csched-row').each(function (idx) {
                jQuery(this).find('input[name^="csched_row["], select[name^="csched_row["]').each(function () {
                    var $el = jQuery(this);
                    var n = $el.attr('name');
                    if (!n) {
                        return;
                    }
                    $el.attr('name', n.replace(/csched_row\[\d+\]/, 'csched_row[' + idx + ']'));
                });
            });
            scRefreshCschedCardLabels();
            scRefreshCschedWeekPreview();
        }

        jQuery('#sc-csched-add').on('click', function () {
            var $rows = $list.find('.sc-csched-row');
            var $clone = $rows.last().clone();
            $clone.find('input[type="checkbox"]').prop('checked', false);
            $clone.find('input[type="time"]').val('');
            $clone.find('select').prop('selectedIndex', 0);
            $clone.find('.sc-csched-group-select').prop('disabled', true);
            $clone.find('.sc-csched-inline-error').remove();
            $clone.find('.sc-csched-time-input').removeClass('is-invalid');
            $clone.find('.sc-csched-card__foot').empty();
            $list.append($clone);
            reindexCschedRows();
            if (typeof scSyncScheduleSelects === 'function') {
                scSyncScheduleSelects();
            }
            if (typeof scSyncScheduleGroupSelects === 'function') {
                scSyncScheduleGroupSelects();
            }
        });
        $list.on('click', '.sc-csched-remove-row', function () {
            if ($list.find('.sc-csched-row').length <= 1) {
                return;
            }
            jQuery(this).closest('.sc-csched-row').remove();
            reindexCschedRows();
        });

        jQuery(document).on('change', '#sc-csched-list input[type=checkbox][name*="[wd]"]', scRefreshCschedWeekPreview);
        scRefreshCschedWeekPreview();
    })();

    function scValidateCourseScheduleRows(scrollToError) {
        var valid = true;
        var $firstBad = null;
        jQuery('#sc-csched-schedule-error').hide().text('');
        jQuery('#sc-csched-list .sc-csched-row').each(function () {
            var $row = jQuery(this);
            var days = $row.find('input[type=checkbox][name*="[wd]"]:checked').length;
            $row.find('.sc-csched-time-input').removeClass('is-invalid');
            $row.find('.sc-csched-inline-error').remove();
            if (!days) {
                return;
            }
            var start = jQuery.trim($row.find('input[name*="[start]"]').val() || '');
            var end = jQuery.trim($row.find('input[name*="[end]"]').val() || '');
            var errMsg = '';
            if (!start || !end) {
                errMsg = 'ساعت شروع و پایان را وارد کنید.';
            } else if (start >= end) {
                errMsg = 'ساعت پایان باید بعد از شروع باشد.';
            }
            if (errMsg) {
                valid = false;
                if (!$firstBad) {
                    $firstBad = $row;
                }
                $row.find('.sc-csched-time-input').addClass('is-invalid');
                $row.find('.sc-csched-card__foot').append(
                    jQuery('<div class="sc-csched-inline-error"></div>').text(errMsg)
                );
            }
        });
        if (!valid) {
            jQuery('#sc-csched-schedule-error').text('برنامه هفتگی: برای هر ردیفی که روز انتخاب شده، ساعت شروع و پایان الزامی است.').show();
            if (scrollToError !== false && $firstBad && $firstBad.length) {
                if (typeof scScrollToFirstError === 'function') {
                    scScrollToFirstError($firstBad);
                } else if ($firstBad[0].scrollIntoView) {
                    $firstBad[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
        return valid;
    }

    jQuery(document).on('change blur', '#sc-csched-list input[type=time], #sc-csched-list input[type=checkbox]', function () {
        scValidateCourseScheduleRows(false);
    });

    $('#restriction_enabled').on('change', function () {
        if ($(this).is(':checked')) {
            $('#course-restrictions-box').slideDown(150);
        } else {
            $('#course-restrictions-box').slideUp(150);
        }
    });

    $('#has_grouping').on('change', function () {
        if ($(this).is(':checked')) {
            $('#sc-course-groups-box').slideDown(150);
        } else {
            $('#sc-course-groups-box').slideUp(150);
            $('#player_can_select_group').prop('checked', false);
        }
        scSyncScheduleGroupSelects();
        scToggleScheduleGroupColumns();
    });

    function scSerializeCourseGroupsPayload() {
        var groups = [];
        $('#sc-course-groups-tbody .sc-course-group-row').each(function () {
            var name = $.trim($(this).find('.sc-course-group-name').val() || '');
            if (name === '') {
                return;
            }
            var desc = $.trim($(this).find('input[name*="[description]"]').val() || '');
            groups.push({ name: name, description: desc });
        });
        $('#course_groups_json').val(JSON.stringify(groups));
    }

    function scReindexCourseGroupRows() {
        $('#sc-course-groups-tbody .sc-course-group-row').each(function (idx) {
            $(this).find('[name^="course_group_row"]').each(function () {
                var n = $(this).attr('name') || '';
                $(this).attr('name', n.replace(/course_group_row\[\d+\]/, 'course_group_row[' + idx + ']'));
            });
        });
    }

    $('#sc-course-group-add').on('click', function () {
        var $row = $('<article class="sc-course-group-card sc-course-group-row">' +
            '<div class="sc-course-field"><label class="sc-course-field__label">نام گروه</label>' +
            '<input type="text" class="sc-course-input sc-course-group-name" name="course_group_row[0][name]" value="" placeholder="مثلاً گروه ۱"></div>' +
            '<div class="sc-course-field"><label class="sc-course-field__label">توضیحات</label>' +
            '<input type="text" class="sc-course-input" name="course_group_row[0][description]" value="" placeholder="توضیح کوتاه (اختیاری)"></div>' +
            '<button type="button" class="sc-course-pkg-remove sc-course-group-remove" title="حذف گروه">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>' +
            '</article>');
        $('#sc-course-groups-tbody').append($row);
        scReindexCourseGroupRows();
        scSyncScheduleGroupSelects();
    });

    $(document).on('click', '.sc-course-group-remove', function () {
        var $body = $('#sc-course-groups-tbody');
        if ($body.find('.sc-course-group-row').length <= 1) {
            $(this).closest('.sc-course-group-row').find('input').val('');
            scSyncScheduleGroupSelects();
            return;
        }
        $(this).closest('.sc-course-group-row').remove();
        scReindexCourseGroupRows();
        scSyncScheduleGroupSelects();
    });

    $(document).on('input', '.sc-course-group-name', function () {
        var hasName = false;
        $('#sc-course-groups-tbody .sc-course-group-name').each(function () {
            if ($.trim($(this).val() || '') !== '') {
                hasName = true;
            }
        });
        if (hasName) {
            $('#has_grouping').prop('checked', true);
            $('#sc-course-groups-box').show();
        }
        scSyncScheduleGroupSelects();
    });

    function scGetCourseGroupNames() {
        var names = [];
        $('#sc-course-groups-tbody .sc-course-group-name').each(function () {
            var n = $.trim($(this).val() || '');
            if (n && names.indexOf(n) === -1) {
                names.push(n);
            }
        });
        return names;
    }

    window.scSyncScheduleGroupSelects = function () {
        var names = scGetCourseGroupNames();
        $('.sc-csched-group-select').each(function () {
            var $sel = $(this);
            var current = String($sel.val() || '');
            $sel.find('option:not(:first)').remove();
            names.forEach(function (name) {
                $sel.append($('<option></option>').val(name).text(name));
            });
            if (current && names.indexOf(current) !== -1) {
                $sel.val(current);
            } else {
                $sel.val('');
            }
        });
    };

    function scToggleScheduleGroupColumns() {
        var show = $('#has_grouping').is(':checked');
        $('.sc-csched-col-group, .sc-csched-group-cell').toggle(show);
        if (!show) {
            $('.sc-csched-uses-group-cb').prop('checked', false);
            $('.sc-csched-group-select').prop('disabled', true).val('');
        } else {
            $('.sc-csched-uses-group-cb').each(function () {
                var $row = $(this).closest('.sc-csched-row');
                $row.find('.sc-csched-group-select').prop('disabled', !$(this).is(':checked'));
            });
        }
    }

    $(document).on('change', '.sc-csched-uses-group-cb', function () {
        var $row = $(this).closest('.sc-csched-row');
        var $sel = $row.find('.sc-csched-group-select');
        if ($(this).is(':checked')) {
            $sel.prop('disabled', false);
            scSyncScheduleGroupSelects();
        } else {
            $sel.prop('disabled', true).val('');
        }
    });

    scSyncScheduleGroupSelects();
    scToggleScheduleGroupColumns();

    function scTogglePrivateCourseFields() {
        var isPrivate = ($('#course_type').val() || 'group') === 'private';
        var variablePricing = isPrivate && $('#private_variable_coach_pricing').is(':checked');

        $('.sc-private-course-row').toggle(isPrivate);
        $('#sc-coach-branch-pricing-wrap').toggle(isPrivate);
        $('#sc-coach-branch-pricing-hint').toggle(isPrivate);

        // فیلدهای قیمت/ظرفیت/پکیج عادی — در حالت قیمت متفاوت مربی پنهان
        $('.sc-standard-pricing-field').toggle(!variablePricing);

        $('#sc-private-session-options-row').toggle(variablePricing);
        $('#sc-coach-branch-pricing-wrap .sc-branch-price-col').toggle(variablePricing);

        if (variablePricing) {
            $('.sc-price-required-mark').hide();
        } else if (!isPrivate || $('#sc-course-packages-body .sc-course-package-row').length === 0) {
            $('.sc-price-required-mark').show();
        } else {
            $('.sc-price-required-mark').hide();
        }

        scSyncCoachBranchPricingTable();
    }

    function scReadCoachIdFromEl($el) {
        var coachId = parseInt($el.attr('data-coach-id') || $el.data('coachId') || '0', 10);
        if (!coachId) {
            var nm = String($el.attr('name') || '');
            var m = nm.match(/\[(\d+)\]\s*$/);
            if (m) {
                coachId = parseInt(m[1], 10);
            }
        }
        return coachId;
    }

    function scGetSavedBranchMeta(chapter, coachId) {
        var map = window.scCourseCoachBranchMeta || {};
        var ch = map[chapter];
        if (!ch) {
            return null;
        }
        return ch[coachId] || ch[String(coachId)] || null;
    }

    function scFormatBranchPriceDisplay(num) {
        var n = parseFloat(num || 0);
        if (isNaN(n) || n <= 0) {
            return '';
        }
        return n.toLocaleString('en-US');
    }

    function scBranchPricingRowValues(chapter, coachId, preserved) {
        var prev = (preserved[chapter] && preserved[chapter][coachId]) ? $.extend({}, preserved[chapter][coachId]) : {};
        var hasPrice = prev.price !== undefined && prev.price !== null && String(prev.price) !== '';
        var hasCap = prev.capacity !== undefined && prev.capacity !== null && String(prev.capacity) !== '';

        if (!hasPrice || !hasCap) {
            var meta = scGetSavedBranchMeta(chapter, coachId);
            if (meta) {
                if (!hasPrice && meta.price_per_session !== undefined && meta.price_per_session !== null) {
                    var pps = parseFloat(meta.price_per_session);
                    if (!isNaN(pps)) {
                        prev.price = String(pps);
                        prev.priceDisplay = scFormatBranchPriceDisplay(pps);
                    }
                }
                if (!hasCap && meta.capacity !== undefined && meta.capacity !== null && meta.capacity !== '') {
                    prev.capacity = String(meta.capacity);
                }
            }
        }

        return {
            priceVal: (prev.price !== undefined && prev.price !== null && String(prev.price) !== '') ? prev.price : '',
            priceDisplay: (prev.priceDisplay !== undefined && prev.priceDisplay !== null && String(prev.priceDisplay) !== '') ? prev.priceDisplay : scFormatBranchPriceDisplay(prev.price),
            capVal: (prev.capacity !== undefined && prev.capacity !== null && String(prev.capacity) !== '') ? prev.capacity : ''
        };
    }

    function scCollectBranchPricingValues() {
        var values = {};
        $('#sc-coach-branch-pricing-body tr[data-chapter][data-coach-id]').each(function () {
            var ch = String($(this).attr('data-chapter') || '');
            var coachId = scReadCoachIdFromEl($(this));
            if (!ch || !coachId) {
                return;
            }
            values[ch] = values[ch] || {};
            values[ch][coachId] = {
                price: $(this).find('.sc-branch-price-raw').val() || '',
                priceDisplay: $(this).find('.sc-branch-price-input').val() || '',
                capacity: $(this).find('input[name^="coach_branch_capacity"]').val() || ''
            };
        });
        return values;
    }

    function scSyncCoachBranchPricingTable() {
        var isPrivate = ($('#course_type').val() || 'group') === 'private';
        var variablePricing = $('#private_variable_coach_pricing').is(':checked');
        var $tbody = $('#sc-coach-branch-pricing-body');
        if (!$tbody.length || !isPrivate) {
            return;
        }

        var preserved = scCollectBranchPricingValues();
        $tbody.empty();

        var rowCount = 0;
        $('.sc-course-chapter-coaches').each(function () {
            var $grp = $(this);
            if (!$grp.is(':visible')) {
                return;
            }
            var chapter = String($grp.attr('data-chapter') || '');
            if (!chapter) {
                return;
            }
            $grp.find('.sc-course-coach-assign-cb:checked').each(function () {
                var coachId = scReadCoachIdFromEl($(this));
                if (!coachId) {
                    return;
                }
                rowCount++;
                var label = (window.scCourseCoachLabels && scCourseCoachLabels[coachId]) ? scCourseCoachLabels[coachId] : ('مربی #' + coachId);
                var rowValues = scBranchPricingRowValues(chapter, coachId, preserved);
                var priceVal = rowValues.priceVal;
                var priceDisplay = rowValues.priceDisplay;
                var capVal = rowValues.capVal;

                var $tr = $('<tr></tr>').attr('data-chapter', chapter).attr('data-coach-id', coachId);
                $tr.append($('<td></td>').text(chapter));
                $tr.append($('<td></td>').text(label));

                var $priceTd = $('<td class="sc-branch-price-col"></td>');
                if (!variablePricing) {
                    $priceTd.hide();
                }
                $priceTd.append(
                    $('<input type="text" dir="ltr" class="sc-course-input sc-branch-price-input">')
                        .attr('name', 'coach_branch_price[' + chapter + '][' + coachId + ']')
                        .val(priceDisplay),
                    $('<input type="hidden" class="sc-branch-price-raw">')
                        .attr('name', 'coach_branch_price_raw[' + chapter + '][' + coachId + ']')
                        .val(priceVal)
                );
                $tr.append($priceTd);
                $tr.append(
                    $('<td></td>').append(
                        $('<input type="number" min="1" class="sc-course-input sc-course-input--sm">')
                            .attr('name', 'coach_branch_capacity[' + chapter + '][' + coachId + ']')
                            .attr('placeholder', 'پیش‌فرض: ۱')
                            .val(capVal)
                    )
                );
                $tbody.append($tr);
            });
        });

        if (rowCount === 0) {
            $tbody.append(
                $('<tr class="sc-coach-branch-pricing-empty"></tr>').append(
                    $('<td colspan="4"></td>').html('<em>ابتدا شعبه و مربی را انتخاب کنید تا ردیف‌های جدول ساخته شوند.</em>')
                )
            );
        }
    }

    $('#course_type, #private_variable_coach_pricing').on('change', scTogglePrivateCourseFields);

    $('#sc-add-private-sess-row').on('click', function () {
        var $row = $('<article class="sc-course-sess-card">' +
            '<div class="sc-course-field"><label class="sc-course-field__label">تعداد جلسه</label>' +
            '<input type="number" min="1" name="private_sess_counts[]" value="" class="sc-course-input"></div>' +
            '<button type="button" class="sc-course-pkg-remove sc-remove-private-sess-row" title="حذف">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>' +
            '</article>');
        $('#sc-private-session-options-body').append($row);
    });
    $(document).on('click', '.sc-remove-private-sess-row', function () {
        var $body = $('#sc-private-session-options-body');
        if ($body.find('.sc-course-sess-card').length <= 1) {
            return;
        }
        $(this).closest('.sc-course-sess-card').remove();
    });

    $(document).on('input', '.sc-branch-price-input', function () {
        var raw = String($(this).val() || '').replace(/,/g, '').replace(/[^\d.]/g, '');
        $(this).closest('td').find('.sc-branch-price-raw').val(raw);
    });

    function scScrollToEl($el) {
        if ($el && $el.length && $el[0].scrollIntoView) {
            $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function scScrollToFirstError($el) {
        if (!window.scCourseFormScrolledToError && $el && $el.length) {
            window.scCourseFormScrolledToError = true;
            scScrollToEl($el);
        }
    }

    function scSyncCoursePriceRawFields() {
        ['#price', '#price_per_session'].forEach(function (sel) {
            var $display = $(sel);
            if (!$display.length) {
                return;
            }
            var rawId = sel === '#price' ? '#price_raw' : '#price_per_session_raw';
            var cleaned = String($display.val() || '').replace(/,/g, '').replace(/[^\d.]/g, '');
            $(rawId).val(cleaned || '0');
        });
        $('#sc-course-packages-body .sc-course-package-row').each(function () {
            var $row = $(this);
            var raw = String($row.find('.sc-pkg-price-input').val() || '').replace(/,/g, '').replace(/[^\d.]/g, '');
            $row.find('.sc-pkg-price-raw').val(raw || '0');
        });
    }

    function scClearCourseFormErrors() {
        $('#sc-course-title-error, #sc-course-price-error, #sc-course-packages-error, #sc-private-sess-error').hide().text('');
        $('#title, #price, #price_per_session').css('border-color', '');
        $('#sc-course-packages-wrap').css('border-color', '');
        $('#sc-course-packages-body .sc-course-package-row').css('outline', '');
        $('#sc-course-form-summary-error').hide().empty();
    }

    function scShowCourseValidationSummary() {
        var msgs = [];
        var fields = [
            '#sc-course-title-error',
            '#sc-csched-schedule-error',
            '#sc-course-chapters-error',
            '#sc-course-price-error',
            '#sc-course-packages-error',
            '#sc-private-sess-error'
        ];

        fields.forEach(function (sel) {
            var $el = $(sel);
            if (!$el.length) {
                return;
            }
            var text = $.trim($el.text() || '');
            if ($el.is(':visible') && text && msgs.indexOf(text) === -1) {
                msgs.push(text);
            }
        });

        var $box = $('#sc-course-form-summary-error');
        if (!msgs.length) {
            $box.hide().empty();
            return;
        }

        var html = '<p><strong>ثبت دوره انجام نشد. موارد زیر را اصلاح کنید:</strong></p><ul style="margin:0.5em 0 0 1.2em;list-style:disc;">';
        msgs.forEach(function (msg) {
            html += '<li>' + $('<div>').text(msg).html() + '</li>';
        });
        html += '</ul>';
        $box.html(html).show();
    }

    function scValidateCourseTitle() {
        var title = $.trim($('#title').val() || '');
        var $err = $('#sc-course-title-error');
        if (!title) {
            $err.show();
            $('#title').css('border-color', '#d63638');
            scScrollToFirstError($('#title'));
            return false;
        }
        return true;
    }

    function scParseCoursePackages() {
        var valid = [];
        var incomplete = false;
        var duplicates = false;
        var seen = {};
        var $firstBad = null;

        $('#sc-course-packages-body .sc-course-package-row').each(function () {
            var $row = $(this);
            var sessions = parseInt($row.find('.sc-pkg-sessions-input').val() || '0', 10);
            var price = parseFloat($row.find('.sc-pkg-price-raw').val() || '0');

            if (sessions < 1 && price <= 0) {
                return;
            }
            if (sessions < 1 || price <= 0) {
                incomplete = true;
                if (!$firstBad) {
                    $firstBad = $row;
                }
                $row.css('outline', '2px solid #d63638');
                return;
            }
            if (seen[sessions]) {
                duplicates = true;
                if (!$firstBad) {
                    $firstBad = $row;
                }
                $row.css('outline', '2px solid #d63638');
                return;
            }
            seen[sessions] = true;
            valid.push({ sessions: sessions, price: price });
        });

        return { valid: valid, incomplete: incomplete, duplicates: duplicates, $firstBad: $firstBad };
    }

    function scValidateCoursePackages() {
        var $err = $('#sc-course-packages-error');
        var result = scParseCoursePackages();

        if (result.incomplete) {
            $err.text('در هر ردیف پکیج، تعداد جلسه و قیمت باید هر دو وارد شوند.').show();
            $('#sc-course-packages-wrap').css('border-color', '#d63638');
            scScrollToFirstError(result.$firstBad || $('#sc-course-packages-wrap'));
            return false;
        }
        if (result.duplicates) {
            $err.text('تعداد جلسه در پکیج‌ها نباید تکراری باشد.').show();
            $('#sc-course-packages-wrap').css('border-color', '#d63638');
            scScrollToFirstError(result.$firstBad || $('#sc-course-packages-wrap'));
            return false;
        }
        return result.valid;
    }

    function scValidateCoursePrice(courseType, variablePricing, validPackages) {
        var $err = $('#sc-course-price-error');
        var hasValidPackages = validPackages && validPackages.length > 0;
        var priceVal = parseFloat($('#price_raw').val() || '0');
        var ppsVal = parseFloat($('#price_per_session_raw').val() || '0');

        if (variablePricing) {
            return true;
        }

        if (courseType === 'private') {
            if (!hasValidPackages && priceVal <= 0 && ppsVal <= 0) {
                $err.text('برای دوره خصوصی، قیمت کل، قیمت هر جلسه یا حداقل یک پکیج معتبر وارد کنید.').show();
                $('#price, #price_per_session').css('border-color', '#d63638');
                scScrollToFirstError($('#price'));
                return false;
            }
            return true;
        }

        if (!hasValidPackages && priceVal <= 0) {
            $err.text('برای دوره گروهی، قیمت کل دوره یا حداقل یک پکیج معتبر الزامی است.').show();
            $('#price').css('border-color', '#d63638');
            scScrollToFirstError($('#price'));
            return false;
        }

        return true;
    }

    function scValidatePrivateSessionOptions() {
        var $err = $('#sc-private-sess-error');
        var counts = [];
        var seen = {};
        var hasValid = false;
        var duplicate = false;

        $('input[name="private_sess_counts[]"]').each(function () {
            var val = parseInt($(this).val() || '0', 10);
            if (val < 1) {
                return;
            }
            if (seen[val]) {
                duplicate = true;
            }
            seen[val] = true;
            counts.push(val);
            hasValid = true;
        });

        if (!hasValid) {
            $err.text('حداقل یک گزینه تعداد جلسه وارد کنید.').show();
            scScrollToFirstError($('#sc-private-session-options-row'));
            return false;
        }
        if (duplicate) {
            $err.text('تعداد جلسه نباید تکراری باشد.').show();
            scScrollToFirstError($('#sc-private-session-options-row'));
            return false;
        }
        return true;
    }

    function scValidateCourseChapters(courseType) {
        if (courseType !== 'group' && courseType !== 'private') {
            return true;
        }
        var $cbs = $('.sc-course-chapter-cb');
        if (!$cbs.length || $cbs.filter(':checked').length) {
            return true;
        }
        var $box = $('#sc-course-chapters-box');
        var $err = $('#sc-course-chapters-error');
        $err.show();
        $box.css('border-color', '#d63638');
        scScrollToFirstError($box);
        return false;
    }

    $(document).on('input change', '#title', function () {
        if ($.trim($(this).val() || '')) {
            $('#sc-course-title-error').hide();
            $(this).css('border-color', '');
        }
    });

    $(document).on('input change', '#price, #price_per_session', function () {
        $('#sc-course-price-error').hide();
        $('#price, #price_per_session').css('border-color', '');
    });

    $(document).on('change', '.sc-course-chapter-cb', function () {
        if ($('.sc-course-chapter-cb:checked').length) {
            $('#sc-course-chapters-error').hide();
            $('#sc-course-chapters-box').css('border-color', '');
        }
    });

    $(document).on('input change', '.sc-pkg-sessions-input, .sc-pkg-price-input', function () {
        $('#sc-course-packages-error').hide();
        $('#sc-course-packages-wrap').css('border-color', '');
        $(this).closest('.sc-course-package-row').css('outline', '');
    });

    $(document).on('input change', 'input[name="private_sess_counts[]"]', function () {
        $('#sc-private-sess-error').hide();
    });

    // اعتبارسنجی سمت کاربر قبل از ارسال فرم — جلوگیری از ریدایرکت سرور و از دست رفتن داده‌ها
    $('.sc-course-add-form').on('submit', function (e) {
        window.scCourseFormScrolledToError = false;
        scSyncCoursePriceRawFields();
        scClearCourseFormErrors();

        var courseType = $('#course_type').val() || 'group';
        var variablePricing = courseType === 'private' && $('#private_variable_coach_pricing').is(':checked');
        var isValid = true;

        if (!scValidateCourseTitle()) {
            isValid = false;
        }

        if (!scValidateCourseScheduleRows()) {
            isValid = false;
        }

        if (!scValidateCourseChapters(courseType)) {
            isValid = false;
        }

        var validPackages = [];
        if (!variablePricing) {
            var pkgResult = scValidateCoursePackages();
            if (pkgResult === false) {
                isValid = false;
            } else {
                validPackages = pkgResult;
            }
        }

        if (!scValidateCoursePrice(courseType, variablePricing, validPackages)) {
            isValid = false;
        }

        if (variablePricing && !scValidatePrivateSessionOptions()) {
            isValid = false;
        }

        if (!isValid) {
            scShowCourseValidationSummary();
            e.preventDefault();
            return false;
        }

        scSerializeCourseGroupsPayload();
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
            return String($(this).attr('data-chapter') || '') === chapterName;
        });
        $grp.find('.sc-course-coach-assign-cb:checked').each(function () {
            var id = scReadCoachIdFromEl($(this));
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
        var $row = $coachSel.closest('.sc-csched-row');
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
            scSyncScheduleCoachSelect($sel.closest('.sc-csched-row').find('.sc-csched-coach-select'));
        });
        if (typeof scSyncScheduleGroupSelects === 'function') {
            scSyncScheduleGroupSelects();
        }
    };

    function scSyncChapterCoachGroups() {
        var checkedChapters = {};
        $('.sc-course-chapter-cb:checked').each(function () {
            checkedChapters[$(this).val()] = true;
        });
        $('.sc-course-chapter-coaches').each(function () {
            var $grp = $(this);
            var chapterKey = String($grp.attr('data-chapter') || '');
            var visible = !!checkedChapters[chapterKey];
            $grp.toggle(visible);
            if (!visible) {
                $grp.find('input[type="checkbox"]').prop('checked', false);
            }
        });
        scSyncScheduleSelects();
        scSyncCoachBranchPricingTable();
    }

    // ابتدا شعبه/مربی همگام شود، بعد فیلدهای کلاس خصوصی (جلوگیری از پاک شدن قیمت/ظرفیت)
    scSyncChapterCoachGroups();
    scTogglePrivateCourseFields();

    $(document).on('change', '.sc-course-chapter-cb', function () {
        if ($('.sc-course-chapter-cb:checked').length) {
            $('#sc-course-chapters-error').hide();
            $('#sc-course-chapters-box').css('border-color', '#ddd');
        }
        scSyncChapterCoachGroups();
    });

    $(document).on('change', '.sc-course-coach-assign-cb', function () {
        scSyncScheduleSelects();
        scSyncCoachBranchPricingTable();
    });

    $(document).on('change', '.sc-csched-chapter-select', function () {
        scSyncScheduleCoachSelect($(this).closest('.sc-csched-row').find('.sc-csched-coach-select'));
    });
});
</script>

