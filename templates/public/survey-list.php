<?php
if (!defined('ABSPATH')) {
    exit;
}
$user_id = get_current_user_id();
$base_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-surveys') : home_url('/');

$all_list = sc_survey_list_for_user($user_id);

$survey_s = isset($_GET['survey_s']) ? sanitize_text_field(wp_unslash($_GET['survey_s'])) : '';
$survey_status = isset($_GET['survey_status']) ? sanitize_key(wp_unslash($_GET['survey_status'])) : 'all';
if (!in_array($survey_status, ['all', 'pending', 'completed'], true)) {
    $survey_status = 'all';
}

$list = sc_survey_filter_user_list($all_list, [
    'search' => $survey_s,
    'status' => $survey_status,
]);

$total = count($all_list);
$completed_count = 0;
foreach ($all_list as $row) {
    if (!empty($row['completed'])) {
        $completed_count++;
    }
}
$pending_count = $total - $completed_count;
$filtered_count = count($list);

$has_filters = ($survey_s !== '' || $survey_status !== 'all');

$fill_survey_id = isset($_GET['sc_fill_survey']) ? absint($_GET['sc_fill_survey']) : 0;
$survey_to_fill = null;
if ($fill_survey_id > 0) {
    foreach ($all_list as $row) {
        if ((int) $row['survey']->id === $fill_survey_id && empty($row['completed'])) {
            $survey_to_fill = $row['survey'];
            break;
        }
    }
}
?>
<div class=" sc-surveys-account-content">
<div class="wrap_attendace_user sc-private-notes-content sc-private-notes-user sc-surveys-user">

    <?php if ($survey_to_fill) : ?>
        <div class="sc-survey-inline-wizard">
            <a href="<?php echo esc_url($base_url); ?>" class="sc-pn-back-link">← بازگشت به لیست نظرسنجی‌ها</a>

            <?php
            $survey = $survey_to_fill;
            $questions = sc_get_survey_questions($survey->id);
            $already_completed = false;
            include SC_TEMPLATES_PUBLIC_DIR . 'survey-wizard-inline.php';
            ?>
        </div>

    <?php else : ?>
        <div class="sc-pn-page-header">
            <div class="sc-pn-page-titles">
                <h2 class="sc-pn-page-title">نظرسنجی‌ها</h2>
                <p class="sc-pn-page-subtitle">نظرسنجی‌های فعال باشگاه را مشاهده کنید و در آن‌ها شرکت کنید.</p>
            </div>
            <?php if ($total > 0) : ?>
                <span class="sc-pn-count-badge"><?php echo (int) $total; ?> نظرسنجی</span>
            <?php endif; ?>
        </div>

        <?php if ($total > 0) : ?>
            <div class="sc-surveys-summary">
                <div class="sc-surveys-summary-item">
                    <span class="sc-surveys-summary-label">همه</span>
                    <strong class="sc-surveys-summary-value"><?php echo (int) $total; ?></strong>
                </div>
                <div class="sc-surveys-summary-item sc-surveys-summary-pending">
                    <span class="sc-surveys-summary-label">در انتظار پاسخ</span>
                    <strong class="sc-surveys-summary-value"><?php echo (int) $pending_count; ?></strong>
                </div>
                <div class="sc-surveys-summary-item sc-surveys-summary-done">
                    <span class="sc-surveys-summary-label">پاسخ داده‌ام</span>
                    <strong class="sc-surveys-summary-value"><?php echo (int) $completed_count; ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url($base_url); ?>" class="sc-pn-filter-card">
            <div class="sc-pn-filter-grid sc-pn-filter-grid--surveys">
                <div class="sc-pn-field sc-pn-field-search">
                    <label for="sc-survey-s" class="sc-pn-label">جستجو</label>
                    <div class="sc-pn-input-wrap">
                        <span class="sc-pn-input-icon" aria-hidden="true">🔍</span>
                        <input type="search" name="survey_s" id="sc-survey-s" class="sc-pn-input"
                               value="<?php echo esc_attr($survey_s); ?>"
                               placeholder="جستجو در عنوان یا توضیحات نظرسنجی...">
                    </div>
                </div>
                <div class="sc-pn-field">
                    <label for="sc-survey-status" class="sc-pn-label">وضعیت پاسخ</label>
                    <select name="survey_status" id="sc-survey-status" class="sc-pn-input">
                        <option value="all" <?php selected($survey_status, 'all'); ?>>همه نظرسنجی‌ها</option>
                        <option value="pending" <?php selected($survey_status, 'pending'); ?>>در انتظار پاسخ من</option>
                        <option value="completed" <?php selected($survey_status, 'completed'); ?>>پاسخ داده‌ام</option>
                    </select>
                </div>
            </div>
            <div class="sc-pn-filter-actions">
                <button type="submit" class="sc-pn-btn sc-pn-btn-primary">
                    <span aria-hidden="true">🔍</span> جستجو
                </button>
                <?php if ($has_filters) : ?>
                    <a href="<?php echo esc_url($base_url); ?>" class="sc-pn-btn sc-pn-btn-ghost">پاک کردن</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($has_filters && $filtered_count !== $total) : ?>
            <p class="sc-survey-filter-result-note"><?php echo (int) $filtered_count; ?> مورد از <?php echo (int) $total; ?> نظرسنجی یافت شد</p>
        <?php endif; ?>

        <?php if (empty($all_list)) : ?>
            <div class="sc-pn-empty">
                <span class="sc-pn-empty-icon" aria-hidden="true">📭</span>
                <p class="sc-pn-empty-text">در حال حاضر نظرسنجی فعالی برای شما وجود ندارد.</p>
            </div>
        <?php elseif (empty($list)) : ?>
            <div class="sc-pn-empty">
                <span class="sc-pn-empty-icon" aria-hidden="true">🔎</span>
                <p class="sc-pn-empty-text">نظرسنجی‌ای با این جستجو یا فیلتر یافت نشد.</p>
            </div>
        <?php else : ?>
            <div class="sc-dashboard-grid sc-surveys-grid">
                <?php foreach ($list as $row) :
                    $s = $row['survey'];
                    $fill_url = function_exists('sc_survey_fill_url_for_current_user')
                        ? sc_survey_fill_url_for_current_user((int) $s->id)
                        : sc_survey_account_fill_url((int) $s->id);
                    $is_done = !empty($row['completed']);
                    $done_date = '';
                    if ($is_done && !empty($row['completed_at']) && function_exists('sc_date_shamsi')) {
                        $done_date = sc_date_shamsi($row['completed_at'], 'Y/m/d H:i');
                    }
                    ?>
                    <details class="sc-dashboard-card sc-survey-card-item <?php echo $is_done ? 'is-completed' : 'is-pending'; ?>" <?php echo !$is_done ? 'open' : ''; ?>>
                        <summary class="sc-dashboard-card-header">
                            <span class="sc-dashboard-card-title">
                                <span class="sc-dashboard-card-icon"><?php echo $is_done ? '✅' : '📝'; ?></span>
                                <?php echo esc_html($s->title); ?>
                                <?php if ($is_done) : ?>
                                    <span class="sc-dashboard-badge sc-dashboard-badge-blue">پاسخ داده‌ام</span>
                                <?php else : ?>
                                    <span class="sc-dashboard-badge sc-dashboard-badge-red">در انتظار پاسخ</span>
                                <?php endif; ?>
                            </span>
                            <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
                        </summary>
                        <div class="sc-dashboard-card-body">
                            <?php if (!empty($s->description)) : ?>
                                <p class="sc-survey-card-desc"><?php echo wp_kses_post($s->description); ?></p>
                            <?php endif; ?>
                            <?php if ($is_done) : ?>
                                <div class="sc-dashboard-empty sc-dashboard-empty-success">
                                    پاسخ شما ثبت شده است.
                                    <?php if ($done_date !== '') : ?>
                                        <br><small>تاریخ پاسخ: <?php echo esc_html($done_date); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php else : ?>
                                <div class="sc-dashboard-toolbar">
                                    <a href="<?php echo esc_url($fill_url); ?>" class="sc-dashboard-btn sc-survey-start-btn">
                                        شروع نظرسنجی
                                        <span aria-hidden="true">←</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
</div>
