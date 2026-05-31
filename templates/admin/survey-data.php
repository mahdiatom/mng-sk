<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';

$notice = '';
$notice_type = 'success';

$surveys = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}sc_surveys ORDER BY id DESC");
$survey_id = sc_survey_resolve_page_survey_id(isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0, $surveys);

if (isset($_GET['action'], $_GET['response_id']) && $_GET['action'] === 'delete_response' && $survey_id) {
    $response_id = absint($_GET['response_id']);
    check_admin_referer('delete_survey_response_' . $response_id);
    $response = sc_survey_get_response($response_id);
    if ($response && (int) $response->survey_id === (int) $survey_id) {
        $result = sc_survey_delete_response($response_id);
        $redirect = add_query_arg([
            'page' => 'sc-survey-data',
            'survey_id' => $survey_id,
            'sc_data_notice' => !empty($result['success']) ? 'deleted' : 'error',
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }
}

if (isset($_POST['sc_bulk_survey_responses_submit'], $_POST['bulk_action']) && check_admin_referer('sc_bulk_survey_responses', 'sc_bulk_survey_responses_nonce')) {
    $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action']));
    $response_ids = isset($_POST['response_ids']) ? array_map('absint', (array) $_POST['response_ids']) : [];
    $response_ids = array_values(array_filter($response_ids));
    $post_survey_id = isset($_POST['survey_id']) ? absint($_POST['survey_id']) : 0;

    if ($bulk_action === 'delete' && !empty($response_ids)) {
        $valid_ids = [];
        foreach ($response_ids as $rid) {
            $row = sc_survey_get_response($rid);
            if ($row && (int) $row->survey_id === $post_survey_id) {
                $valid_ids[] = $rid;
            }
        }
        $result = sc_survey_delete_responses($valid_ids);
        $redirect = add_query_arg([
            'page' => 'sc-survey-data',
            'survey_id' => $post_survey_id,
            'sc_data_notice' => !empty($result['success']) ? 'bulk_deleted' : 'error',
            'sc_data_count' => isset($result['deleted']) ? (int) $result['deleted'] : 0,
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    $notice = empty($response_ids) ? 'حداقل یک پاسخ را انتخاب کنید.' : 'عملیات دسته‌جمعی نامعتبر است.';
    $notice_type = 'error';
}

if (isset($_GET['sc_data_notice'])) {
    $code = sanitize_key(wp_unslash($_GET['sc_data_notice']));
    $count = isset($_GET['sc_data_count']) ? absint($_GET['sc_data_count']) : 0;
    if ($code === 'deleted') {
        $notice = 'پاسخ حذف شد.';
        $notice_type = 'success';
    } elseif ($code === 'bulk_deleted') {
        $notice = $count > 0 ? sprintf('%d پاسخ حذف شد.', $count) : 'پاسخی حذف نشد.';
        $notice_type = $count > 0 ? 'success' : 'warning';
    } elseif ($code === 'error') {
        $notice = 'خطا در حذف پاسخ.';
        $notice_type = 'error';
    }
}

$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$all_players = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

$today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
$filter_request = sc_survey_apply_default_date_filters($_GET);
$filter_date_from_shamsi = $filter_request['filter_date_from_shamsi'] ?? $today_shamsi;
$filter_date_to_shamsi = $filter_request['filter_date_to_shamsi'] ?? $today_shamsi;
$filters = sc_survey_parse_response_filters($filter_request);
$query_filters = $filters;
$responses = $survey_id ? sc_survey_build_responses_query($survey_id, $query_filters) : [];
$questions = $survey_id ? sc_get_survey_questions($survey_id) : [];
$answers_table = $wpdb->prefix . 'sc_survey_answers';
$active_survey = $survey_id ? sc_get_survey($survey_id) : null;

$page_slug = 'sc-survey-data';
$show_export = true;
$filter_title = 'فیلتر داده‌ها';
$filter_description = 'بازه تاریخی خود را جهت فیلتر در ابتدا حتما مشخص کنید.';
?>
<div class="wrap sc-users-export-wrap sc-survey-data-wrap">
    <h1>داده‌های نظرسنجی</h1>

    <?php if ($notice !== '') : ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php include SC_TEMPLATES_ADMIN_DIR . 'partials/survey-filters.php'; ?>

    <?php if (empty($surveys)) : ?>
        <div class="sc-users-export-card"><p>هنوز نظرسنجی ثبت نشده است.</p></div>
    <?php elseif ($survey_id) : ?>
        <form method="post" id="sc-survey-responses-form" class="sc-survey-responses-bulk-form">
            <?php wp_nonce_field('sc_bulk_survey_responses', 'sc_bulk_survey_responses_nonce'); ?>
            <input type="hidden" name="sc_bulk_survey_responses_submit" value="1">
            <input type="hidden" name="survey_id" value="<?php echo (int) $survey_id; ?>">

            <div class="sc-users-export-card sc-survey-table-card">
                <div class="tablenav top sc-survey-bulk-nav">
                    <div class="alignleft actions bulkactions">
                        <label for="sc-survey-response-bulk-action" class="screen-reader-text">عملیات دسته‌جمعی</label>
                        <select name="bulk_action" id="sc-survey-response-bulk-action">
                            <option value="">عملیات دسته‌جمعی...</option>
                            <option value="delete">حذف</option>
                        </select>
                        <input type="submit" class="button action sc-survey-bulk-submit" value="اجرا"
                               data-confirm="پاسخ‌های انتخاب‌شده حذف شوند؟">
                    </div>
                    <h2 class="sc-survey-data-table-title">
                        <?php echo esc_html($active_survey ? $active_survey->title : 'نظرسنجی'); ?>
                        — <?php echo esc_html(count($responses)); ?> پاسخ
                    </h2>
                </div>

                <?php if (empty($questions)) : ?>
                    <p class="description">این نظرسنجی سوالی ندارد.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped sc-survey-data-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="sc-survey-cb-select-all">
                                </td>
                                <th>نام</th>
                                <th>موبایل</th>
                                <th>تاریخ</th>
                                <?php foreach ($questions as $q) : ?>
                                    <th><?php echo esc_html(wp_trim_words(wp_strip_all_tags($q->question_text), 8)); ?></th>
                                <?php endforeach; ?>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($responses)) : ?>
                            <tr><td colspan="<?php echo 5 + count($questions); ?>">هنوز پاسخی ثبت نشده است.</td></tr>
                        <?php else : foreach ($responses as $r) :
                            $answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM $answers_table WHERE response_id = %d", (int) $r->id));
                            $map = [];
                            foreach ($answers as $a) {
                                $map[(int) $a->question_id] = $a;
                            }
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=sc-survey-data&survey_id=' . (int) $survey_id . '&action=delete_response&response_id=' . (int) $r->id),
                                'delete_survey_response_' . (int) $r->id
                            );
                            $pdf_url = function_exists('sc_survey_get_response_pdf_export_url')
                                ? sc_survey_get_response_pdf_export_url((int) $survey_id, (int) $r->id, $filter_request)
                                : '';
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="sc-survey-row-cb" name="response_ids[]" value="<?php echo (int) $r->id; ?>">
                                </th>
                                <td><?php echo esc_html(sc_survey_response_display_name($r)); ?></td>
                                <td><?php echo esc_html(sc_survey_response_display_phone($r)); ?></td>
                                <td><?php echo esc_html(function_exists('sc_date_shamsi') && !empty($r->completed_at) ? sc_date_shamsi($r->completed_at, 'Y/m/d H:i') : ($r->completed_at ?: '—')); ?></td>
                                <?php foreach ($questions as $q) :
                                    $cell = '—';
                                    if (isset($map[$q->id])) {
                                        $a = $map[$q->id];
                                        if ($a->answer_json) {
                                            $d = json_decode($a->answer_json, true);
                                            $cell = is_array($d) ? (isset($d['attachment_id']) ? 'فایل پیوست' : implode('، ', $d)) : (string) $d;
                                        } else {
                                            $cell = (string) $a->answer_text;
                                        }
                                    }
                                    ?><td><?php echo esc_html($cell); ?></td><?php endforeach; ?>
                                <td class="sc-survey-row-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-survey-data&survey_id=' . (int) $survey_id . '&edit_response=' . (int) $r->id)); ?>">ویرایش</a>
                                    <?php if ($pdf_url !== '') : ?>
                                        |
                                        <a href="<?php echo esc_url($pdf_url); ?>" class="sc-survey-row-pdf-link" target="_blank" rel="noopener">PDF</a>
                                    <?php endif; ?>
                                    |
                                    <a href="<?php echo esc_url($delete_url); ?>" class="delete sc-survey-delete-link"
                                       onclick="return confirm('این پاسخ حذف شود؟');">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>
