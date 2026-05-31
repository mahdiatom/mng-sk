<?php
if (!defined('ABSPATH')) {
    exit;
}
/** @var object $survey */
/** @var array $questions */
/** @var bool $already_completed */
/** @var bool $is_public_guest */
$user_id = get_current_user_id();
$member_id = sc_survey_get_member_id_for_user($user_id);
$existing = sc_survey_get_user_response($survey->id, $user_id, $member_id);
$already_completed = $existing && $existing->status === 'completed';
$questions_for_js = array_map(function ($q) {
    return [
        'id' => (int) $q->id,
        'question_type' => $q->question_type,
        'question_text' => $q->question_text,
        'options' => sc_survey_decode_json($q->options_json),
        'settings' => sc_survey_decode_json($q->settings_json),
    ];
}, $questions);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($survey->title); ?> - نظرسنجی</title>
    <?php wp_head(); ?>
</head>
<body class="sc-survey-page">
<div class="sc-player-dashboard sc-survey-wizard-dashboard">
    <div class="sc-survey-shell">
        <?php if ($already_completed) : ?>
            <details class="sc-dashboard-card" open>
                <summary class="sc-dashboard-card-header">
                    <span class="sc-dashboard-card-title">
                        <span class="sc-dashboard-card-icon">✅</span>
                        <?php echo esc_html($survey->title); ?>
                    </span>
                    <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
                </summary>
                <div class="sc-dashboard-card-body">
                    <div class="sc-dashboard-empty sc-dashboard-empty-success">
                        شما قبلاً در این نظرسنجی شرکت کرده‌اید. از همکاری شما سپاسگزاریم.
                    </div>
                </div>
            </details>
        <?php else : ?>
            <h2 class="sc-dashboard-page-title">
                <span class="sc-dashboard-page-icon">📝</span>
                <?php echo esc_html($survey->title); ?>
            </h2>
            <?php if (!empty($survey->description)) : ?>
                <p class="sc-dashboard-page-subtitle"><?php echo wp_kses_post($survey->description); ?></p>
            <?php endif; ?>

            <?php
            $is_guest = !is_user_logged_in();
            $guest_settings = [];
            if (!empty($survey->audience_config)) {
                $aud = sc_survey_decode_json($survey->audience_config);
                $guest_settings = isset($aud['guest_settings']) ? (array) $aud['guest_settings'] : [];
            }
            $show_guest_fields = $is_guest && !empty($survey->is_public) && !empty($guest_settings['show_guest_info']);
            $require_guest_fields = !empty($guest_settings['require_guest_info']);
            $verify_national_id = !empty($guest_settings['verify_national_id']);
            $verify_mobile = !empty($guest_settings['verify_mobile']);
            ?>

            <?php if ($verify_mobile && $is_guest) : ?>
            <!-- Mobile Verification Step -->
            <div id="sc-survey-mobile-verification" class="sc-survey-verification-card">
                <div class="sc-survey-verification-header">
                    <h3>احراز هویت با موبایل</h3>
                    <p>برای شرکت در این نظرسنجی، لطفاً شماره موبایل خود را تأیید کنید.</p>
                </div>

                <div id="sc-survey-mobile-step-1">
                    <div class="sc-survey-guest-field">
                        <label>شماره موبایل</label>
                        <input type="tel" id="sc-survey-guest-phone-input" placeholder="09123456789" class="sc-survey-input">
                    </div>
                    <button type="button" id="sc-survey-send-code-btn" class="sc-dashboard-btn sc-survey-btn-primary">ارسال کد تأیید</button>
                    <p class="sc-survey-verification-note">کد تأیید به این شماره ارسال خواهد شد.</p>
                </div>

                <div id="sc-survey-mobile-step-2" style="display:none;">
                    <div class="sc-survey-guest-field">
                        <label>کد تأیید</label>
                        <input type="text" id="sc-survey-verification-code-input" placeholder="کد تایید پیامک شده را وارد کنید." class="sc-survey-input" maxlength="6">
                    </div>
                    <div class="sc-survey-verification-actions">
                        <button type="button" id="sc-survey-verify-code-btn" class="sc-dashboard-btn sc-survey-btn-primary">تأیید کد</button>
                        <button type="button" id="sc-survey-resend-code-btn" class="sc-dashboard-btn sc-survey-btn-secondary">ارسال مجدد کد</button>
                    </div>
                    <p id="sc-survey-verification-status" class="sc-survey-verification-status"></p>
                </div>
            </div>
            <?php endif; ?>

            <form id="sc-survey-wizard-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sc_submit_survey_' . (int) $survey->id, 'sc_survey_submit_nonce'); ?>
                <input type="hidden" name="survey_id" value="<?php echo esc_attr($survey->id); ?>">
                <input type="hidden" name="sc_submit_survey" value="1">

                <?php if ($show_guest_fields) : ?>
                <div class="sc-survey-guest-info">
                    <h4>اطلاعات شما <?php echo $require_guest_fields ? '(الزامی)' : '(اختیاری)'; ?></h4>
                    <div class="sc-survey-guest-grid">
                        <div class="sc-survey-guest-field">
                            <label>نام و نام خانوادگی <?php echo $require_guest_fields ? '<span class="req">*</span>' : ''; ?></label>
                            <input type="text" name="guest_name" placeholder="مثال: علی رضایی" <?php echo $require_guest_fields ? 'required' : ''; ?>>
                        </div>
                        <div class="sc-survey-guest-field">
                            <label>شماره تماس <?php echo $require_guest_fields ? '<span class="req">*</span>' : ''; ?></label>
                            <input type="tel" name="guest_phone" placeholder="مثال: 09123456789" <?php echo $require_guest_fields ? 'required' : ''; ?>>
                        </div>
                        <?php if ($verify_national_id) : ?>
                        <div class="sc-survey-guest-field">
                            <label>کد ملی <?php echo $require_guest_fields ? '<span class="req">*</span>' : ''; ?></label>
                            <input type="text" name="guest_national_id" placeholder="مثال: 0012345678" <?php echo $require_guest_fields ? 'required' : ''; ?>>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="sc-survey-guest-note">این اطلاعات فقط برای ثبت پاسخ شما استفاده می‌شود.</p>
                </div>
                <?php endif; ?>

                <div id="sc-survey-step-container"></div>
                <div class="sc-survey-nav sc-dashboard-toolbar">
                    <button type="button" class="sc-dashboard-btn sc-survey-btn-secondary" id="sc-survey-prev" disabled>قبلی</button>
                    <button type="button" class="sc-dashboard-btn" id="sc-survey-next">بعدی</button>
                    <button type="submit" class="sc-dashboard-btn sc-survey-btn-submit" id="sc-survey-submit" style="display:none;">ثبت نهایی</button>
                </div>
            </form>

            <div id="sc-survey-thankyou-panel" class="sc-survey-thankyou-card" style="display:none;" aria-live="polite">
                <div class="sc-survey-thankyou-inner">
                    <div class="sc-survey-thankyou-icon-wrap" aria-hidden="true">
                        <svg class="sc-survey-thankyou-icon" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2" opacity="0.2"/>
                            <path d="M20 33l8 8 16-18" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="sc-survey-thankyou-title">پاسخ شما با موفقیت ثبت شد</h3>
                    <p class="sc-survey-thankyou-fixed">از وقت و همراهی شما در بهبود خدمات باشگاه سپاسگزاریم.</p>
                    <?php
                    $custom_thank = !empty($survey->thank_you_message) ? trim((string) $survey->thank_you_message) : '';
                    if ($custom_thank !== '') : ?>
                        <div id="sc-survey-thankyou-custom" class="sc-survey-thankyou-custom"><?php echo wp_kses_post(wpautop($custom_thank)); ?></div>
                    <?php else : ?>
                        <div id="sc-survey-thankyou-custom" class="sc-survey-thankyou-custom" style="display:none;"></div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            window.scSurveyWizard = {
                questions: <?php echo wp_json_encode($questions_for_js, JSON_UNESCAPED_UNICODE); ?>,
                ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode(wp_create_nonce('sc_survey_wizard')); ?>,
                surveyId: <?php echo wp_json_encode((int) $survey->id); ?>,
                todayShamsi: <?php echo wp_json_encode(function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : ''); ?>,
                thankYouMessage: <?php echo wp_json_encode($custom_thank); ?>,
                thankYouFixed: <?php echo wp_json_encode('از وقت و همراهی شما در بهبود خدمات باشگاه سپاسگزاریم.'); ?>
            };
            </script>
        <?php endif; ?>
    </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
