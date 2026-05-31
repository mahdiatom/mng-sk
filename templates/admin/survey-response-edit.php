<?php
if (!defined('ABSPATH')) {
    exit;
}

$response_id = isset($_GET['edit_response']) ? absint($_GET['edit_response']) : 0;
$survey_id = isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0;
$response = $response_id ? sc_survey_get_response($response_id) : null;

if (!$response || ($survey_id && (int) $response->survey_id !== $survey_id)) {
    echo '<div class="wrap"><div class="notice notice-error"><p>پاسخ یافت نشد.</p></div></div>';
    return;
}

$survey_id = (int) $response->survey_id;
$survey = sc_get_survey($survey_id);
$questions = sc_get_survey_questions($survey_id);
$answers_map = sc_survey_get_response_answers_map($response_id);
$back_url = admin_url('admin.php?page=sc-survey-data&survey_id=' . $survey_id);

if (isset($_POST['sc_save_survey_response']) && check_admin_referer('sc_save_survey_response_' . $response_id, 'sc_survey_response_nonce')) {
    $posted_answers = isset($_POST['answers']) && is_array($_POST['answers']) ? wp_unslash($_POST['answers']) : [];
    $result = sc_survey_admin_update_response($response_id, $posted_answers, $_FILES);
    if (!empty($result['success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message'] ?? 'ذخیره شد.') . '</p></div>';
        $answers_map = sc_survey_get_response_answers_map($response_id);
        $response = sc_survey_get_response($response_id);
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html($result['message'] ?? 'خطا در ذخیره') . '</p></div>';
    }
}

$respondent_name = sc_survey_response_display_name($response);
$completed_txt = function_exists('sc_date_shamsi') && !empty($response->completed_at)
    ? sc_date_shamsi($response->completed_at, 'Y/m/d H:i')
    : ($response->completed_at ?: '—');
?>
<div class="wrap sc-users-export-wrap sc-survey-response-edit-wrap">
    <h1>ویرایش پاسخ نظرسنجی</h1>
    <p class="description">
        <a href="<?php echo esc_url($back_url); ?>">← بازگشت به داده‌ها</a>
    </p>

    <div class="sc-users-export-card">
        <h2><?php echo esc_html($survey ? $survey->title : 'نظرسنجی'); ?></h2>
        <p class="description">
            <strong>پاسخ‌دهنده:</strong> <?php echo esc_html($respondent_name); ?>
            &nbsp;|&nbsp;
            <strong>موبایل:</strong> <?php echo esc_html(sc_survey_response_display_phone($response)); ?>
            &nbsp;|&nbsp;
            <strong>تاریخ ثبت:</strong> <?php echo esc_html($completed_txt); ?>
        </p>
    </div>

    <form method="post" enctype="multipart/form-data" class="sc-users-export-card">
        <?php wp_nonce_field('sc_save_survey_response_' . $response_id, 'sc_survey_response_nonce'); ?>
        <input type="hidden" name="sc_save_survey_response" value="1">

        <?php foreach ($questions as $q) :
            $opts = sc_survey_decode_json($q->options_json);
            $val = sc_survey_answer_to_form_value($answers_map[$q->id] ?? null, $q);
            $field_name = 'answers[' . (int) $q->id . ']';
            ?>
            <div class="sc-row sc-survey-edit-question">
                <label>
                    <?php echo esc_html(wp_strip_all_tags($q->question_text)); ?>
                    <span class="description">(<?php echo esc_html(sc_get_survey_question_types()[$q->question_type] ?? $q->question_type); ?>)</span>
                </label>

                <?php switch ($q->question_type) :
                    case 'textarea': ?>
                        <textarea name="<?php echo esc_attr($field_name); ?>" rows="4" class="large-text"><?php echo esc_textarea($val); ?></textarea>
                        <?php break;

                    case 'number': ?>
                        <input type="number" name="<?php echo esc_attr($field_name); ?>" class="regular-text" value="<?php echo esc_attr($val); ?>">
                        <?php break;

                    case 'single_choice':
                        foreach ((array) ($opts['choices'] ?? []) as $choice) : ?>
                            <label class="sc-inline-check">
                                <input type="radio" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($choice); ?>" <?php checked((string) $val, (string) $choice); ?>>
                                <?php echo esc_html($choice); ?>
                            </label>
                        <?php endforeach;
                        break;

                    case 'multiple_choice':
                        foreach ((array) ($opts['choices'] ?? []) as $choice) :
                            $checked = is_array($val) && in_array($choice, $val, true);
                            ?>
                            <label class="sc-inline-check">
                                <input type="checkbox" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_attr($choice); ?>" <?php checked($checked); ?>>
                                <?php echo esc_html($choice); ?>
                            </label>
                        <?php endforeach;
                        break;

                    case 'yes_no': ?>
                        <label class="sc-inline-check"><input type="radio" name="<?php echo esc_attr($field_name); ?>" value="yes" <?php checked($val, 'yes'); ?>> بله</label>
                        <label class="sc-inline-check"><input type="radio" name="<?php echo esc_attr($field_name); ?>" value="no" <?php checked($val, 'no'); ?>> خیر</label>
                        <?php break;

                    case 'rating':
                    case 'scale':
                        $max = isset($opts['max']) ? (int) $opts['max'] : 5;
                        if ($max < 1) {
                            $max = 5;
                        }
                        ?>
                        <select name="<?php echo esc_attr($field_name); ?>" class="regular-text">
                            <option value="">—</option>
                            <?php for ($i = 1; $i <= $max; $i++) : ?>
                                <option value="<?php echo (int) $i; ?>" <?php selected((string) $val, (string) $i); ?>><?php echo (int) $i; ?> ستاره</option>
                            <?php endfor; ?>
                        </select>
                        <?php break;

                    case 'date': ?>
                        <input type="text" name="<?php echo esc_attr($field_name); ?>"
                               class="regular-text persian-date-input sc-no-default-date"
                               value="<?php echo esc_attr($val); ?>" placeholder="انتخاب تاریخ" readonly>
                        <?php break;

                    case 'file':
                        $attach_id = absint($val);
                        if ($attach_id) :
                            $file_url = wp_get_attachment_url($attach_id);
                            ?>
                            <p class="description">
                                فایل فعلی:
                                <?php if ($file_url) : ?>
                                    <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($attach_id) ?: ('فایل #' . $attach_id)); ?></a>
                                <?php else : ?>
                                    #<?php echo (int) $attach_id; ?>
                                <?php endif; ?>
                            </p>
                            <input type="hidden" name="answers[file_keep][<?php echo (int) $q->id; ?>]" value="<?php echo (int) $attach_id; ?>">
                        <?php endif; ?>
                        <input type="file" name="survey_file_<?php echo (int) $q->id; ?>" class="regular-text">
                        <?php break;

                    default: ?>
                        <input type="text" name="<?php echo esc_attr($field_name); ?>" class="large-text" value="<?php echo esc_attr($val); ?>">
                <?php endswitch; ?>
            </div>
        <?php endforeach; ?>

        <p class="submit">
            <button type="submit" class="button button-primary">ذخیره تغییرات</button>
            <a href="<?php echo esc_url($back_url); ?>" class="sc_button">انصراف</a>
        </p>
    </form>
</div>
