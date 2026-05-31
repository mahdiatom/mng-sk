<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();
$user_id = get_current_user_id();
$list = function_exists('sc_survey_list_for_user') ? sc_survey_list_for_user($user_id) : [];
?>
<div class="wrap sc-users-export-wrap sc-survey-list-wrap">
    <h1>نظرسنجی‌ها</h1>
    <p class="description">نظرسنجی‌های فعال مربوط به شما را مشاهده و تکمیل کنید.</p>

    <div class="sc-users-export-card">
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
                    $fill_url = sc_survey_account_fill_url((int) $s->id);
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
                                <a class="button button-primary" href="<?php echo esc_url($fill_url); ?>">شرکت در نظرسنجی</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
