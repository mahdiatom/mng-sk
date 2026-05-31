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

            <div class="sc-survey-progress-card sc-dashboard-card">
                <div class="sc-dashboard-card-body">
                    <div class="sc-survey-progress-wrap">
                        <div class="sc-survey-progress-bar"><span id="scSurveyProgressFill"></span></div>
                        <span id="scSurveyProgressText" class="sc-survey-progress-label"></span>
                    </div>
                </div>
            </div>

            <form id="sc-survey-wizard-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sc_submit_survey_' . (int) $survey->id, 'sc_survey_submit_nonce'); ?>
                <input type="hidden" name="survey_id" value="<?php echo esc_attr($survey->id); ?>">
                <input type="hidden" name="sc_submit_survey" value="1">
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
