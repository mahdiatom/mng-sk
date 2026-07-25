<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$events_table = $wpdb->prefix . 'sc_events';
$team_table = $wpdb->prefix . 'sc_team_categories';
$level_table = $wpdb->prefix . 'sc_level_categories';

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table ORDER BY last_name, first_name");
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL ORDER BY title ASC");
$events = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') ORDER BY name");
$teams = $wpdb->get_results("SELECT id, name FROM $team_table ORDER BY name");
$levels = $wpdb->get_results("SELECT id, name FROM $level_table ORDER BY name");

$survey_id = isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0;
$survey = $survey_id ? sc_get_survey($survey_id) : null;
$questions = $survey_id ? sc_get_survey_questions($survey_id) : [];

if (isset($_POST['sc_save_survey']) && check_admin_referer('sc_save_survey', 'sc_survey_nonce')) {
    $audience = sc_survey_parse_audience_from_post($_POST);

    $activation = [
        'trigger_type' => isset($_POST['trigger_type']) ? sanitize_key($_POST['trigger_type']) : 'manual',
        'trigger_date' => isset($_POST['trigger_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['trigger_date_shamsi'])) : '',
        'auto_enable' => !empty($_POST['auto_enable_on_date']) ? 1 : 0,
        'course_ids' => isset($_POST['activation_course_ids']) ? array_map('absint', (array) $_POST['activation_course_ids']) : [],
    ];

    $start_at = null;
    $end_at = null;
    if (!empty($_POST['start_at_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
        $g = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_POST['start_at_shamsi'])));
        if ($g) {
            $start_at = $g . ' 00:00:00';
        }
    }
    if (!empty($_POST['end_at_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
        $g = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_POST['end_at_shamsi'])));
        if ($g) {
            $end_at = $g . ' 23:59:59';
        }
    }

    $raw_questions = [];
    if (!empty($_POST['survey_questions_json'])) {
        $decoded = json_decode(stripslashes((string) $_POST['survey_questions_json']), true);
        if (is_array($decoded)) {
            $raw_questions = $decoded;
        }
    }

    $result = sc_survey_save([
        'id' => $survey_id,
        'title' => isset($_POST['title']) ? $_POST['title'] : '',
        'description' => isset($_POST['description']) ? $_POST['description'] : '',
        'is_active' => !empty($_POST['is_active']),
        'is_public' => !empty($_POST['is_public']),
        'audience_config' => $audience,
        'activation_config' => $activation,
        'thank_you_message' => isset($_POST['thank_you_message']) ? $_POST['thank_you_message'] : '',
        'start_at' => $start_at,
        'end_at' => $end_at,
    ], $raw_questions);

    if (!empty($result['success'])) {
        wp_safe_redirect(admin_url('admin.php?page=sc-add-survey&survey_id=' . absint($result['survey_id']) . '&saved=1'));
        exit;
    }
    echo '<div class="notice notice-error"><p>' . esc_html($result['message'] ?? 'خطا در ذخیره') . '</p></div>';
}

if (isset($_GET['saved'])) {
    echo '<div class="notice notice-success is-dismissible"><p>نظرسنجی ذخیره شد.</p></div>';
}

$audience = $survey ? sc_survey_decode_json($survey->audience_config) : [
    'target_type' => 'all',
    'include_players' => 1,
    'include_coaches' => 1,
    'restriction' => ['enabled' => 0, 'allowed_gender' => 'both', 'allowed_teams' => [], 'allowed_levels' => []],
];
$activation = $survey ? sc_survey_decode_json($survey->activation_config) : ['trigger_type' => 'manual'];
$target_config = isset($audience['target_config']) ? (array) $audience['target_config'] : [];

$start_shamsi = '';
$end_shamsi = '';
$today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
if ($survey && !empty($survey->start_at) && function_exists('sc_date_shamsi_date_only')) {
    $start_shamsi = sc_date_shamsi_date_only(substr($survey->start_at, 0, 10));
}
if ($survey && !empty($survey->end_at) && function_exists('sc_date_shamsi_date_only')) {
    $end_shamsi = sc_date_shamsi_date_only(substr($survey->end_at, 0, 10));
}
if (!$survey && $today_shamsi) {
    $start_shamsi = $today_shamsi;
    if (empty($end_shamsi) && function_exists('sc_shamsi_add_days')) {
        $end_shamsi = sc_shamsi_add_days($today_shamsi, 30);
    }
    if (empty($activation['trigger_date'])) {
        $activation['trigger_date'] = $today_shamsi;
    }
}

$questions_json = wp_json_encode(array_map(function ($q) {
    return [
        'id' => (int) $q->id,
        'question_type' => $q->question_type,
        'question_text' => $q->question_text,
        'options' => sc_survey_decode_json($q->options_json),
        'settings' => sc_survey_decode_json($q->settings_json),
    ];
}, $questions), JSON_UNESCAPED_UNICODE);
?>
<div class="wrap sc-members-list-wrap sc-survey-admin-wrap sc-survey-admin-shell">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title"><?php echo $survey ? 'ویرایش نظرسنجی' : 'افزودن نظرسنجی'; ?></h1>
            <p class="sc-members-list-desc">تنظیمات، مخاطبان، زمان‌بندی و سوالات نظرسنجی را در کارت‌های زیر مدیریت کنید.</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-surveys')); ?>" class="sc-members-list-export-btn">بازگشت به لیست</a>
        </div>
    </div>

    <?php if ($survey && !empty($survey->is_public) && function_exists('sc_survey_public_url')) :
        $pub = sc_survey_public_url($survey); ?>
        <div class="notice notice-info"><p><strong>لینک عمومی:</strong> <a href="<?php echo esc_url($pub); ?>" target="_blank" rel="noopener"><?php echo esc_html($pub); ?></a></p></div>
    <?php endif; ?>

    <form method="post" id="sc-survey-form">
        <?php wp_nonce_field('sc_save_survey', 'sc_survey_nonce'); ?>
        <input type="hidden" name="sc_save_survey" value="1">
        <input type="hidden" name="survey_questions_json" id="survey_questions_json" value="">

        <div class="sc-users-export-card sc-members-list-table-card">
            <h2>اطلاعات پایه</h2>
            <div class="sc-row">
                <label for="sc-survey-title">عنوان</label>
                <input type="text" name="title" id="sc-survey-title" class="regular-text" required value="<?php echo esc_attr($survey->title ?? ''); ?>">
            </div>
            <div class="sc-row">
                <label for="sc-survey-description">توضیحات</label>
                <textarea name="description" id="sc-survey-description" rows="3" class="large-text"><?php echo esc_textarea($survey->description ?? ''); ?></textarea>
            </div>
            <div class="sc-row">
                <label for="sc-survey-thankyou">پیام تشکر</label>
                <textarea name="thank_you_message" id="sc-survey-thankyou" rows="2" class="large-text" placeholder="از مشارکت شما سپاسگزاریم."><?php echo esc_textarea($survey->thank_you_message ?? ''); ?></textarea>
            </div>
            <div class="sc-row sc-survey-status-row">
                <label>وضعیت</label>
                <label class="sc-inline-check"><input type="checkbox" name="is_active" value="1" <?php checked(isset($survey->is_active) ? (int)$survey->is_active : 1, 1); ?>> فعال</label>
                <label class="sc-inline-check"><input type="checkbox" name="is_public" id="sc-survey-is-public" value="1" <?php checked($survey->is_public ?? 0, 1); ?>> لینک عمومی (بدون نیاز به ورود)</label>
            </div>

            <div id="sc-survey-guest-settings" class="sc-survey-guest-settings" style="<?php echo empty($survey->is_public) ? 'display:none;' : ''; ?>">
                <h4>تنظیمات ثبت‌نام مهمان</h4>
                <div class="sc-row">
                    <label class="sc-inline-check">
                        <input type="checkbox" name="guest_show_info" value="1" <?php checked(!empty($audience['guest_settings']['show_guest_info'])); ?>>
                        نمایش فیلدهای اطلاعات ثبت‌نام (نام، تلفن، کد ملی)
                    </label>
                </div>
                <div class="sc-row">
                    <label class="sc-inline-check">
                        <input type="checkbox" name="guest_require_info" value="1" <?php checked(!empty($audience['guest_settings']['require_guest_info'])); ?>>
                        اجباری کردن اطلاعات ثبت‌نام
                    </label>
                </div>
                <div class="sc-row">
                    <label class="sc-inline-check">
                        <input type="checkbox" name="guest_verify_national_id" value="1" <?php checked(!empty($audience['guest_settings']['verify_national_id'])); ?>>
                        احراز هویت با کد ملی (بررسی تکراری + اتصال به عضو)
                    </label>
                </div>
                <div class="sc-row">
                    <label class="sc-inline-check">
                        <input type="checkbox" name="guest_verify_mobile" value="1" <?php checked(!empty($audience['guest_settings']['verify_mobile'])); ?>>
                        احراز هویت با موبایل (ارسال پیامک قبل از نمایش سوالات)
                    </label>
                </div>
            </div>
            <div class="sc-row sc-survey-date-row">
                <div class="sc-survey-date-field">
                    <label for="start_at_shamsi">شروع (شمسی)</label>
                    <input type="text" name="start_at_shamsi" id="start_at_shamsi"
                           class="regular-text persian-date-input"
                           placeholder="1403/01/01" value="<?php echo esc_attr($start_shamsi); ?>" readonly>
                </div>
                <div class="sc-survey-date-field">
                    <label for="end_at_shamsi">پایان (شمسی)</label>
                    <input type="text" name="end_at_shamsi" id="end_at_shamsi"
                           class="regular-text persian-date-input"
                           placeholder="1403/12/29" value="<?php echo esc_attr($end_shamsi); ?>" readonly>
                </div>
            </div>
        </div>

        <div class="sc-users-export-card sc-members-list-table-card">
            <h2>فعال‌سازی خودکار</h2>
            <div class="sc-row">
                <label for="trigger_type">نوع فعال‌سازی</label>
                <select name="trigger_type" id="trigger_type">
                    <option value="manual" <?php selected($activation['trigger_type'] ?? 'manual', 'manual'); ?>>دستی (مدیر فعال می‌کند)</option>
                    <option value="on_date" <?php selected($activation['trigger_type'] ?? '', 'on_date'); ?>>در تاریخ مشخص</option>
                    <option value="last_session" <?php selected($activation['trigger_type'] ?? '', 'last_session'); ?>>پایان دوره (آخرین جلسه)</option>
                </select>
            </div>
            <div class="sc-row sc-trigger-on-date">
                <label for="trigger_date_shamsi">تاریخ فعال‌سازی</label>
                <input type="text" name="trigger_date_shamsi" id="trigger_date_shamsi"
                       class="regular-text persian-date-input"
                       value="<?php echo esc_attr($activation['trigger_date'] ?? ''); ?>" readonly>
                <label class="sc-inline-check"><input type="checkbox" name="auto_enable_on_date" value="1" <?php checked(!empty($activation['auto_enable'])); ?>> خودکار فعال شود</label>
            </div>
            <div class="sc-row sc-trigger-last-session">
                <label for="activation_course_ids">دوره‌ها</label>
                <select name="activation_course_ids[]" id="activation_course_ids" multiple size="7">
                    <?php foreach ($courses as $c) : ?>
                        <option value="<?php echo esc_attr($c->id); ?>" <?php echo in_array((int) $c->id, array_map('intval', (array) ($activation['course_ids'] ?? [])), true) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">وقتی بازیکن به آخرین جلسه دوره رسید، نظرسنجی برای او فعال می‌شود.</p>
            </div>
        </div>

        <?php include SC_TEMPLATES_ADMIN_DIR . 'partials/survey-audience-filters.php'; ?>

        <div class="sc-users-export-card sc-survey-questions-card sc-members-list-table-card">
            <div class="sc-survey-questions-head">
                <h2>سوالات</h2>
                <button type="button" class="button button-primary sc-members-list-add-btn" id="sc-add-question">+ افزودن سوال</button>
            </div>
            <p class="description">هر سوال در یک کارت جداگانه است. منطق شرطی فقط به سوالات قبلی وابسته می‌شود و در فرم کاربر به‌صورت آنی اعمال می‌شود.</p>
            <div id="sc-survey-questions-builder"></div>
        </div>

       
            <p class="submit sc-survey-form-actions">
                <button type="submit" class="button button-primary sc-members-list-add-btn">ذخیره نظرسنجی</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-surveys')); ?>" class="sc_button sc-members-list-export-btn">بازگشت به لیست</a>
            </p>
    
    </form>
</div>
<script>
window.scSurveyInitialQuestions = <?php echo $questions_json ?: '[]'; ?>;
window.scSurveyQuestionTypes = <?php echo wp_json_encode(sc_get_survey_question_types(), JSON_UNESCAPED_UNICODE); ?>;
</script>
