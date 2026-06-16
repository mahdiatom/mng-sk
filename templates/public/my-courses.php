<?php
if (!defined('ABSPATH')) {
    exit;
}

$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all');
$current_page = isset($current_page) ? $current_page : (isset($_GET['pag']) ? absint($_GET['pag']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
$total_courses = isset($total_courses) ? $total_courses : 0;
$course_search = isset($course_search) ? $course_search : (isset($_GET['course_search']) ? sanitize_text_field(wp_unslash($_GET['course_search'])) : '');
$pending_invoices = isset($pending_invoices) && is_array($pending_invoices) ? $pending_invoices : [];
$under_review_invoices = isset($under_review_invoices) && is_array($under_review_invoices) ? $under_review_invoices : [];
$invoice_urls = isset($invoice_urls) && is_array($invoice_urls) ? $invoice_urls : [];
?>

<div class="sc-my-courses-page">
    <div class="sc-my-courses-header">
        <h2>دوره‌های من</h2>
        <p>هر ثبت‌نام با شعبه و مربی مشخص به‌صورت جداگانه نمایش داده می‌شود.</p>
    </div>

    <?php
    if (!isset($sc_weekly_schedule_matrix) || !is_array($sc_weekly_schedule_matrix)) {
        $sc_weekly_schedule_matrix = ['days' => [], 'cells' => []];
    }
    $ws_days = isset($sc_weekly_schedule_matrix['days']) && is_array($sc_weekly_schedule_matrix['days']) ? $sc_weekly_schedule_matrix['days'] : [];
    $ws_cells = isset($sc_weekly_schedule_matrix['cells']) && is_array($sc_weekly_schedule_matrix['cells']) ? $sc_weekly_schedule_matrix['cells'] : [];
    $ws_has_any = false;
    foreach (range(1, 7) as $d) {
        if (!empty($ws_cells[$d])) {
            $ws_has_any = true;
            break;
        }
    }
    ?>
    <div class="sc-weekly-schedule-card">
        <h3>برنامه هفتگی کلاس‌ها</h3>
        <?php if (!$ws_has_any) : ?>
            <p class="sc-private-panel-hint" style="margin:0;">هنوز برای دوره‌های شما زمان ثابت هفتگی تعریف نشده است.</p>
        <?php else : ?>
            <div style="overflow-x:auto;">
                <table class="sc-weekly-schedule-table" style="width:100%; min-width:640px; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr>
                            <?php foreach ($ws_days as $num => $lab) : ?>
                                <th style="padding:10px 8px; background:#2271b1; color:#fff; text-align:center; border:1px solid #1e5a96; font-weight:600;">
                                    <?php echo esc_html($lab); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach (range(1, 7) as $d) : ?>
                                <td style="vertical-align:top; padding:10px 8px; border:1px solid #ddd; background:#fafafa;">
                                    <?php if (empty($ws_cells[$d])) : ?>
                                        <span style="color:#bbb;">—</span>
                                    <?php else : ?>
                                        <?php foreach ($ws_cells[$d] as $slot) : ?>
                                            <div style="margin-bottom:10px; padding:10px; background:#eef6ff; border-radius:8px; border-right:3px solid #2271b1;">
                                                <div style="font-weight:700; color:#2271b1; margin-bottom:4px;">
                                                    <?php echo esc_html($slot['start'] ?? ''); ?> – <?php echo esc_html($slot['end'] ?? ''); ?>
                                                </div>
                                                <div style="color:#333; line-height:1.4;"><?php echo esc_html($slot['title'] ?? ''); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="sc-my-courses-filters">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-my-courses')); ?>">
            <input type="hidden" name="pag" value="1">
            <div class="sc-my-courses-filter-field">
                <label for="filter_status">وضعیت</label>
                <select name="filter_status" id="filter_status">
                    <option value="active" <?php selected($filter_status, 'active'); ?>>ثبت‌نام شده و در حال پرداخت</option>
                    <option value="canceled" <?php selected($filter_status, 'canceled'); ?>>لغو شده</option>
                    <option value="paused" <?php selected($filter_status, 'paused'); ?>>متوقف شده</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>تمام شده</option>
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                </select>
            </div>
            <div class="sc-my-courses-filter-field">
                <label for="course_search">جستجو</label>
                <input type="search" name="course_search" id="course_search" value="<?php echo esc_attr($course_search); ?>" placeholder="نام دوره...">
            </div>
            <div class="sc-my-courses-filter-actions">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
            </div>
        </form>
    </div>

    <?php if (empty($user_courses)) : ?>
        <div class="sc-message sc-message-info">
            <?php if ($filter_status !== 'all') : ?>
                دوره‌ای با این وضعیت یافت نشد.
            <?php elseif ($course_search !== '') : ?>
                دوره‌ای با این جستجو یافت نشد.
            <?php else : ?>
                شما هنوز در هیچ دوره‌ای ثبت‌نام نکرده‌اید.
            <?php endif; ?>
        </div>
    <?php else : ?>

    <div class="sc-my-courses-grid">
        <?php foreach ($user_courses as $user_course) :
            $mc_id = (int) $user_course->id;
            $flags = function_exists('sc_member_course_parse_flags')
                ? sc_member_course_parse_flags($user_course)
                : [];
            $has_flags = !empty($flags);
            $is_paused = in_array('paused', $flags, true);
            $is_completed = in_array('completed', $flags, true);
            $is_canceled = in_array('canceled', $flags, true);

            $is_under_review = !empty($under_review_invoices[$mc_id]);
            $has_pending_invoice = !empty($pending_invoices[$mc_id]);
            $is_pending_payment = (!$has_flags && $user_course->status === 'inactive' && $has_pending_invoice && !$is_under_review);

            $status_label = 'نامشخص';
            $status_class = 'sc-my-course-status-unknown';
            $display = true;
            $can_cancel = false;

            if ($is_canceled) {
                $status_label = 'لغو شده';
                $status_class = 'sc-my-course-status-canceled';
            } elseif ($is_paused) {
                $status_label = 'متوقف شده';
                $status_class = 'sc-my-course-status-paused';
            } elseif ($is_completed) {
                $status_label = 'تمام شده';
                $status_class = 'sc-my-course-status-completed';
            } elseif ($is_under_review) {
                $status_label = 'در انتظار بررسی';
                $status_class = 'sc-my-course-status-review';
            } elseif ($is_pending_payment) {
                $status_label = 'در انتظار پرداخت';
                $status_class = 'sc-my-course-status-pending';
            } elseif (!$is_under_review && !$is_pending_payment && $user_course->status === 'active' && !$has_flags) {
                $status_label = 'فعال';
                $status_class = 'sc-my-course-status-active';
                $can_cancel = true;
            } else {
                $display = false;
            }

            $coach_name = trim((string) ($user_course->coach_first_name ?? '') . ' ' . (string) ($user_course->coach_last_name ?? ''));
            if ($coach_name === '' && !empty($user_course->coach_id) && function_exists('sc_get_coach_display_name')) {
                $coach_name = sc_get_coach_display_name((int) $user_course->coach_id);
            }
            $chapter_name = trim((string) ($user_course->chapter ?? ''));
            $invoice_url = isset($invoice_urls[$mc_id]) ? $invoice_urls[$mc_id] : '';
        ?>
            <?php if (!$display) { continue; } ?>
            <article class="sc-my-course-card">
                <div class="sc-my-course-card-head">
                    <h3 class="sc-my-course-card-title"><?php echo esc_html($user_course->course_title); ?></h3>
                    <span class="sc-my-course-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                </div>

                <div class="sc-my-course-meta">
                    <?php if ($chapter_name !== '') : ?>
                        <span class="sc-my-course-meta-item"><strong>شعبه:</strong> <?php echo esc_html($chapter_name); ?></span>
                    <?php endif; ?>
                    <?php if ($coach_name !== '') : ?>
                        <span class="sc-my-course-meta-item"><strong>مربی:</strong> <?php echo esc_html($coach_name); ?></span>
                    <?php endif; ?>
                    <span class="sc-my-course-meta-item"><strong>تاریخ ثبت‌نام:</strong> <?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($user_course->created_at) : $user_course->created_at); ?></span>
                </div>

                <?php if ((int) $user_course->total_sessions > 0) : ?>
                <div class="sc-my-course-sessions">
                    <span class="sc-my-course-session-pill">کل جلسات: <?php echo esc_html((string) (int) $user_course->total_sessions); ?></span>
                    <span class="sc-my-course-session-pill">باقی‌مانده: <?php echo esc_html((string) (int) $user_course->remaining_sessions); ?></span>
                </div>
                <?php endif; ?>

                <div class="sc-my-course-actions">
                    <?php if ($is_pending_payment && $invoice_url !== '') : ?>
                        <a href="<?php echo esc_url($invoice_url); ?>" class="sc-my-course-btn sc-my-course-btn-pay">پرداخت صورت‌حساب</a>
                    <?php elseif ($is_under_review && $invoice_url !== '') : ?>
                        <a href="<?php echo esc_url($invoice_url); ?>" class="sc-my-course-btn sc-my-course-btn-muted">مشاهده صورت‌حساب</a>
                    <?php elseif ($can_cancel) : ?>
                        <form method="POST" action="" style="width:100%; margin:0;" onsubmit="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید که می‌خواهید این دوره را لغو کنید؟' });">
                            <?php wp_nonce_field('sc_cancel_course', 'sc_cancel_course_nonce'); ?>
                            <input type="hidden" name="cancel_course_id" value="<?php echo esc_attr($mc_id); ?>">
                            <button type="submit" name="sc_cancel_course" class="sc_button sc-my-course-btn sc-my-course-btn-cancel">لغو دوره</button>
                        </form>
                    <?php else : ?>
                        <span class="sc-my-course-btn sc-my-course-btn-disabled">عملیات در دسترس نیست</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc_paginate" style="margin: 20px 10px 50px 0;">
            <div class="tablenav-pages">
                <?php
                $pagination_add = ['filter_status' => $filter_status];
                if ($course_search !== '') {
                    $pagination_add['course_search'] = $course_search;
                }
                echo paginate_links([
                    'base' => add_query_arg(['pag' => '%#%']),
                    'format' => '',
                    'add_args' => $pagination_add,
                    'prev_text' => '< قبلی ',
                    'next_text' => ' بعدی >',
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-my-courses-header h2');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);
});
</script>
