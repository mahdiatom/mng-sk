<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();
$user_id = get_current_user_id();
$list = function_exists('sc_survey_list_for_user') ? sc_survey_list_for_user($user_id) : [];
$base_url = admin_url('admin.php?page=sc-coach-surveys');

$fill_survey_id = isset($_GET['sc_fill_survey']) ? absint($_GET['sc_fill_survey']) : 0;
$survey_to_fill = null;
if ($fill_survey_id > 0) {
    foreach ($list as $row) {
        if ((int) $row['survey']->id === $fill_survey_id && empty($row['completed'])) {
            $survey_to_fill = $row['survey'];
            break;
        }
    }
}
?>
<div class="wrap sc-members-list-wrap sc-survey-list-wrap sc-coach-surveys-admin sc-survey-admin-shell">
    <?php if ($survey_to_fill) : ?>
        <div class="sc-members-list-header">
            <div class="sc-members-list-header-text">
                <h1 class="sc-members-list-title">شرکت در نظرسنجی</h1>
                <p class="sc-members-list-desc">
                    <a href="<?php echo esc_url($base_url); ?>">← بازگشت به لیست نظرسنجی‌ها</a>
                </p>
            </div>
        </div>

        <div class="sc-users-export-card sc-coach-survey-fill-card sc-members-list-table-card">
            <?php
            $survey = $survey_to_fill;
            $questions = sc_get_survey_questions($survey->id);
            $already_completed = false;
            $survey_list_back_url = $base_url;
            include SC_TEMPLATES_PUBLIC_DIR . 'survey-wizard-inline.php';
            ?>
        </div>
    <?php else : ?>
        <div class="sc-members-list-header">
            <div class="sc-members-list-header-text">
                <h1 class="sc-members-list-title">نظرسنجی‌ها</h1>
                <p class="sc-members-list-desc">نظرسنجی‌های فعال مربوط به شما را مشاهده و تکمیل کنید.</p>
            </div>
        </div>

        <?php if ($fill_survey_id > 0) : ?>
            <div class="notice notice-warning is-dismissible"><p>این نظرسنجی در دسترس شما نیست یا قبلاً تکمیل شده است.</p></div>
        <?php endif; ?>

        <div class="sc-users-export-card sc-members-list-table-card">
            <?php if (empty($list)) : ?>
                <div class="sc-dashboard-empty">نظرسنجی فعالی برای شما وجود ندارد.</div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $row) :
                        $s = $row['survey'];
                        $fill_url = function_exists('sc_survey_coach_fill_url')
                            ? sc_survey_coach_fill_url((int) $s->id)
                            : add_query_arg(['page' => 'sc-coach-surveys', 'sc_fill_survey' => (int) $s->id], admin_url('admin.php'));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($s->title); ?></strong></td>
                            <td>
                                <?php if ($row['completed']) : ?>
                                    <span class="sc-survey-badge sc-survey-badge-active">شرکت کرده</span>
                                <?php else : ?>
                                    <span class="sc-survey-badge sc-survey-badge-inactive">در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['completed']) : ?>
                                    <span class="description">پاسخ شما ثبت شده است.</span>
                                <?php else : ?>
                                    <a class="button button-primary sc-members-list-add-btn" href="<?php echo esc_url($fill_url); ?>">شرکت در نظرسنجی</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
