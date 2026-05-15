<?php
/**
 * Player Dashboard (پیشخوان) Template
 *
 * Expects $player (sc_members row) from sc_my_account_dashboard_content()
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$current_user_id = get_current_user_id();
$member_id       = (int) $player->id;

$members_table         = $wpdb->prefix . 'sc_members';
$member_courses_table  = $wpdb->prefix . 'sc_member_courses';
$courses_table         = $wpdb->prefix . 'sc_courses';
$invoices_table        = $wpdb->prefix . 'sc_invoices';
$attendances_table     = $wpdb->prefix . 'sc_attendances';
$tickets_table         = $wpdb->prefix . 'sc_support_tickets';

/* ====================================================================
 * 1) کارهای ناتمام
 * ================================================================= */
$tasks = [];

// 1-a) تکمیل اطلاعات
$is_profile_completed = function_exists('sc_check_profile_completed')
    ? sc_check_profile_completed($member_id)
    : true;
if (!$is_profile_completed) {
    $tasks[] = [
        'icon'        => '👤',
        'title'       => 'تکمیل اطلاعات پروفایل',
        'description' => 'برای استفاده کامل از خدمات باشگاه، اطلاعات پروفایل خود را تکمیل کنید.',
        'btn_label'   => 'تکمیل اطلاعات',
        'btn_url'     => wc_get_account_endpoint_url('sc-submit-documents'),
        'color'       => 'red',
    ];
}

// 1-b) تمدید بیمه ورزشی
if (!empty($player->insurance_expiry_date_shamsi)
    && function_exists('sc_get_today_shamsi')
    && function_exists('sc_compare_shamsi_dates')) {
    $today_shamsi    = sc_get_today_shamsi();
    $insurance_expiry = $player->insurance_expiry_date_shamsi;
    $expiry_compare  = sc_compare_shamsi_dates($today_shamsi, $insurance_expiry);
    if ($expiry_compare >= 0) {
        $tasks[] = [
            'icon'        => '🛡️',
            'title'       => 'تمدید بیمه ورزشی',
            'description' => 'اعتبار بیمه ورزشی شما به پایان رسیده است. لطفاً نسبت به تمدید آن اقدام کنید.',
            'btn_label'   => 'به‌روزرسانی بیمه',
            'btn_url'     => wc_get_account_endpoint_url('sc-submit-documents'),
            'color'       => 'red',
        ];
    }
}

// 1-c) ثبت نام در دوره (هیچ دوره فعال و بدون فلگ ندارد)
$active_courses_count = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*)
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     WHERE mc.member_id = %d
       AND mc.status = 'active'
       AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR mc.course_status_flags = ' ')
       AND c.deleted_at IS NULL
       AND c.is_active = 1",
    $member_id
));
if ($active_courses_count === 0) {
    $tasks[] = [
        'icon'        => '🏃',
        'title'       => 'ثبت نام در دوره',
        'description' => 'در حال حاضر در هیچ دوره فعالی ثبت‌نام نشده‌اید. برای شروع تمرین در یک دوره ثبت‌نام کنید.',
        'btn_label'   => 'ثبت نام در دوره',
        'btn_url'     => wc_get_account_endpoint_url('sc-enroll-course'),
        'color'       => 'blue',
    ];
}

// 1-d) خواندن اطلاعیه‌های خوانده نشده
$has_unread_notifications = false;
$unread_notif_count = 0;
if (function_exists('sc_is_pro_feature_notifications_enabled')
    && sc_is_pro_feature_notifications_enabled()
    && function_exists('sc_count_unread_notifications')) {
    $unread_notif_count = (int) sc_count_unread_notifications($current_user_id);
    if ($unread_notif_count > 0) {
        $has_unread_notifications = true;
        $tasks[] = [
            'icon'        => '🔔',
            'title'       => 'مطالعه اطلاعیه‌ها',
            'description' => sprintf('شما %s اطلاعیه خوانده نشده دارید.', number_format_i18n($unread_notif_count)),
            'btn_label'   => 'مشاهده اطلاعیه‌ها',
            'btn_url'     => wc_get_account_endpoint_url('sc-notifications'),
            'color'       => 'orange',
        ];
    }
}

// 1-e) اتصال به ربات بله
$bot_id_value = isset($player->bot_id) ? trim((string) $player->bot_id) : '';
if ($bot_id_value === '') {
    // اگر افزونه ربات نصب باشد، endpoint مربوطه را داریم
    $bot_connect_url = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('bot-connect')
        : '';
    if ($bot_connect_url) {
        $tasks[] = [
            'icon'        => '🤖',
            'title'       => 'اتصال به ربات بله',
            'description' => 'حساب کاربری شما هنوز به ربات بله متصل نشده است. برای دریافت اعلان‌ها از طریق پیام‌رسان بله، حساب خود را متصل کنید.',
            'btn_label'   => 'اتصال به ربات',
            'btn_url'     => $bot_connect_url,
            'color'       => 'purple',
        ];
    }
}

// 1-f) پرداخت صورت‌حساب‌های در انتظار پرداخت
$pending_invoices_summary = $wpdb->get_row($wpdb->prepare(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount + COALESCE(penalty_amount, 0)), 0) AS total_amount
     FROM $invoices_table
     WHERE member_id = %d
       AND status IN ('pending', 'under_review')
       AND (course_id > 0 OR event_id > 0 OR invoice_description IS NOT NULL)",
    $member_id
));
$pending_invoices_count  = (int) ($pending_invoices_summary->cnt ?? 0);
$pending_invoices_total  = (float) ($pending_invoices_summary->total_amount ?? 0);
if ($pending_invoices_count > 0) {
    $amount_fmt = function_exists('sc_format_amount_display')
        ? sc_format_amount_display($pending_invoices_total)
        : number_format($pending_invoices_total);
    $tasks[] = [
        'icon'        => '💳',
        'title'       => 'پرداخت صورت حساب',
        'description' => sprintf(
            'شما %s صورت‌حساب در انتظار پرداخت دارید (جمع: %s تومان).',
            number_format_i18n($pending_invoices_count),
            $amount_fmt
        ),
        'btn_label'   => 'پرداخت صورت‌حساب',
        'btn_url'     => wc_get_account_endpoint_url('sc-invoices'),
        'color'       => 'yellow',
    ];
}

/* ====================================================================
 * 2) آخرین تیکت‌ها (۳ مورد)
 * ================================================================= */
$last_tickets = [];
if (function_exists('sc_support_get_tickets_for_user')) {
    $last_tickets = sc_support_get_tickets_for_user($current_user_id, [
        'per_page' => 3,
        'offset'   => 0,
        'orderby'  => 'updated_at',
        'order'    => 'DESC',
    ]);
}
$tickets_url = wc_get_account_endpoint_url('sc-support-tickets');

/* ====================================================================
 * 3) آخرین اطلاعیه‌ها (۳ مورد)
 * ================================================================= */
$last_notifications     = [];
$notifications_enabled  = function_exists('sc_is_pro_feature_notifications_enabled')
    && sc_is_pro_feature_notifications_enabled();
if ($notifications_enabled && function_exists('sc_get_user_notifications')) {
    $last_notifications = sc_get_user_notifications($current_user_id, 3, 0, false, false, '');
}
$notifications_url = wc_get_account_endpoint_url('sc-notifications');

/* ====================================================================
 * 4) صورت‌حساب‌های در انتظار پرداخت
 * ================================================================= */
$pending_invoices = $wpdb->get_results($wpdb->prepare(
    "SELECT i.id, i.amount, i.penalty_amount, i.status, i.created_at,
            i.course_id, i.event_id, i.expense_name, i.invoice_description,
            c.title AS course_title, e.name AS event_name
     FROM $invoices_table i
     LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
     LEFT JOIN {$wpdb->prefix}sc_events e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
     WHERE i.member_id = %d
       AND i.status IN ('pending', 'under_review')
     ORDER BY i.created_at DESC
     LIMIT 10",
    $member_id
));
$invoices_url = wc_get_account_endpoint_url('sc-invoices');

/* ====================================================================
 * 5) غیبت‌های ۳ ماه اخیر در دوره‌های فعال (بدون فلگ)
 *    شامل هر دو نوع: 'absent' (غیبت) و 'excused' (غیبت مجاز)
 * ================================================================= */
$absences_from_date = gmdate('Y-m-d', strtotime('-3 months', current_time('timestamp')));
$my_absences = $wpdb->get_results($wpdb->prepare(
    "SELECT a.attendance_date, a.status, c.id AS course_id, c.title AS course_title
     FROM $attendances_table a
     LEFT JOIN $member_courses_table mc
            ON mc.member_id = a.member_id AND mc.course_id = a.course_id
     INNER JOIN $courses_table c ON c.id = a.course_id
     WHERE a.member_id = %d
       AND a.status IN ('absent', 'excused')
       AND a.attendance_date >= %s
       AND (mc.id IS NULL OR (
            mc.status = 'active'
            AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR mc.course_status_flags = ' ')
       ))
       AND c.deleted_at IS NULL
     ORDER BY a.attendance_date DESC",
    $member_id,
    $absences_from_date
));
$absences_url = wc_get_account_endpoint_url('sc-my-attendances');

// شمارش جداگانه دو نوع غیبت برای نمایش در badge
$absent_count_total  = 0;
$excused_count_total = 0;
foreach ($my_absences as $_ab) {
    if ($_ab->status === 'excused') {
        $excused_count_total++;
    } else {
        $absent_count_total++;
    }
}

/* ====================================================================
 * 6) جلسات باقی‌مانده دوره‌های فعال (بدون فلگ)
 * ================================================================= */
$remaining_sessions_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT mc.course_id,
            mc.total_sessions,
            mc.remaining_sessions,
            c.title AS course_title
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON c.id = mc.course_id
     WHERE mc.member_id = %d
       AND mc.status = 'active'
       AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR mc.course_status_flags = ' ')
       AND c.deleted_at IS NULL
       AND c.is_active = 1
     ORDER BY c.title ASC",
    $member_id
));
$my_courses_url = wc_get_account_endpoint_url('sc-my-courses');

/**
 * Helper برای رندر badge وضعیت تیکت
 */
$ticket_status_label = function ($status) {
    if (function_exists('sc_support_status_label')) {
        return sc_support_status_label($status);
    }
    $labels = [
        'pending_reply' => 'در انتظار پاسخ',
        'answered'      => 'پاسخ داده شده',
        'closed'        => 'بسته شده',
    ];
    return $labels[$status] ?? $status;
};

$invoice_status_label = function ($status) {
    $labels = [
        'pending'      => 'در انتظار پرداخت',
        'under_review' => 'در حال بررسی',
        'paid'         => 'پرداخت شده',
        'completed'    => 'تکمیل شده',
        'processing'   => 'در حال پردازش',
        'cancelled'    => 'لغو شده',
    ];
    return $labels[$status] ?? $status;
};
?>

<div class="sc-player-dashboard">

    <h2 class="sc-dashboard-page-title">
        <span class="sc-dashboard-page-icon">🏠</span>
        پیشخوان من
    </h2>
    <p class="sc-dashboard-page-subtitle">خلاصه‌ای از وضعیت حساب کاربری، کارهای ناتمام و آخرین فعالیت‌های شما در باشگاه.</p>

    <div class="sc-dashboard-grid">

        <!-- ============ 1) کارهای ناتمام ============ -->
        <details class="sc-dashboard-card sc-dash-card-tasks" open>
            <summary class="sc-dashboard-card-header">
                <span class="sc-dashboard-card-title">
                    <span class="sc-dashboard-card-icon">✅</span>
                    کارهای ناتمام
                    <?php if (!empty($tasks)) : ?>
                        <span class="sc-dashboard-badge sc-dashboard-badge-red"><?php echo esc_html(number_format_i18n(count($tasks))); ?></span>
                    <?php endif; ?>
                </span>
                <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
            </summary>
            <div class="sc-dashboard-card-body">
                <?php if (empty($tasks)) : ?>
                    <div class="sc-dashboard-empty sc-dashboard-empty-success">
                        🎉 آفرین! هیچ کار ناتمامی ندارید.
                    </div>
                <?php else : ?>
                    <ul class="sc-dashboard-tasks-list">
                        <?php foreach ($tasks as $task) : ?>
                            <li class="sc-dashboard-task-row sc-task-<?php echo esc_attr($task['color']); ?>">
                                <div class="sc-task-info">
                                    <div class="sc-task-title">
                                        <span class="sc-task-icon"><?php echo esc_html($task['icon']); ?></span>
                                        <?php echo esc_html($task['title']); ?>
                                    </div>
                                    <?php if (!empty($task['description'])) : ?>
                                        <div class="sc-task-desc"><?php echo esc_html($task['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="sc-task-action">
                                    <a href="<?php echo esc_url($task['btn_url']); ?>" class="sc-dashboard-btn">
                                        <?php echo esc_html($task['btn_label']); ?>
                                        <span aria-hidden="true">←</span>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>

        <!-- ============ 2) تیکت‌ها ============ -->
        <details class="sc-dashboard-card sc-dash-card-tickets" open>
            <summary class="sc-dashboard-card-header">
                <span class="sc-dashboard-card-title">
                    <span class="sc-dashboard-card-icon">🎫</span>
                    آخرین تیکت‌ها
                </span>
                <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
            </summary>
            <div class="sc-dashboard-card-body">
                <div class="sc-dashboard-toolbar">
                    <a href="<?php echo esc_url($tickets_url); ?>" class="sc-dashboard-link-btn">مشاهده همه تیکت‌ها</a>
                </div>

                <?php if (empty($last_tickets)) : ?>
                    <div class="sc-dashboard-empty">هیچ تیکتی ثبت نکرده‌اید.</div>
                <?php else : ?>
                    <ul class="sc-dashboard-list">
                        <?php foreach ($last_tickets as $ticket) :
                            $view_url = add_query_arg('view_ticket', (int) $ticket->id, $tickets_url); ?>
                            <li class="sc-dashboard-list-item">
                                <a href="<?php echo esc_url($view_url); ?>" class="sc-dashboard-list-link">
                                    <div class="sc-dashboard-list-main">
                                        <div class="sc-dashboard-list-title">
                                            #<?php echo esc_html($ticket->id); ?> - <?php echo esc_html($ticket->subject); ?>
                                        </div>
                                        <div class="sc-dashboard-list-meta">
                                            <?php echo esc_html(sc_date_shamsi($ticket->updated_at, 'Y/m/d - H:i')); ?>
                                        </div>
                                    </div>
                                    <span class="sc-dashboard-status-badge sc-ticket-status-<?php echo esc_attr($ticket->status); ?>">
                                        <?php echo esc_html($ticket_status_label($ticket->status)); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>

        <!-- ============ 3) اطلاعیه‌ها ============ -->
        <details class="sc-dashboard-card sc-dash-card-notifications" open>
            <summary class="sc-dashboard-card-header">
                <span class="sc-dashboard-card-title">
                    <span class="sc-dashboard-card-icon">🔔</span>
                    آخرین اطلاعیه‌ها
                    <?php if ($unread_notif_count > 0) : ?>
                        <span class="sc-dashboard-badge sc-dashboard-badge-orange"><?php echo esc_html(number_format_i18n($unread_notif_count)); ?> خوانده نشده</span>
                    <?php endif; ?>
                </span>
                <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
            </summary>
            <div class="sc-dashboard-card-body">
                <?php if (!$notifications_enabled) : ?>
                    <div class="sc-dashboard-empty">سیستم اطلاعیه‌ها فعال نیست.</div>
                <?php else : ?>
                    <div class="sc-dashboard-toolbar">
                        <a href="<?php echo esc_url($notifications_url); ?>" class="sc-dashboard-link-btn">مشاهده همه اطلاعیه‌ها</a>
                    </div>

                    <?php if (empty($last_notifications)) : ?>
                        <div class="sc-dashboard-empty">اطلاعیه‌ای برای شما ثبت نشده است.</div>
                    <?php else : ?>
                        <ul class="sc-dashboard-list">
                            <?php foreach ($last_notifications as $notif) :
                                $view_url = add_query_arg('view', (int) $notif->id, $notifications_url);
                                $is_read  = !empty($notif->is_read);
                                ?>
                                <li class="sc-dashboard-list-item <?php echo $is_read ? 'is-read' : 'is-unread'; ?>">
                                    <a href="<?php echo esc_url($view_url); ?>" class="sc-dashboard-list-link">
                                        <div class="sc-dashboard-list-main">
                                            <div class="sc-dashboard-list-title">
                                                <?php if (!$is_read) : ?>
                                                    <span class="sc-dashboard-dot" aria-hidden="true"></span>
                                                <?php endif; ?>
                                                <?php echo esc_html($notif->title); ?>
                                            </div>
                                            <div class="sc-dashboard-list-meta">
                                                <?php echo esc_html(sc_date_shamsi($notif->created_at, 'Y/m/d - H:i')); ?>
                                            </div>
                                        </div>
                                        <span class="sc-dashboard-status-badge <?php echo $is_read ? 'sc-status-neutral' : 'sc-status-warning'; ?>">
                                            <?php echo $is_read ? 'خوانده شده' : 'خوانده نشده'; ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </details>

        <!-- ============ 4) صورت‌حساب‌های در انتظار پرداخت ============ -->
        <details class="sc-dashboard-card sc-dash-card-invoices" open>
            <summary class="sc-dashboard-card-header">
                <span class="sc-dashboard-card-title">
                    <span class="sc-dashboard-card-icon">💳</span>
                    صورت‌حساب‌های در انتظار پرداخت
                    <?php if ($pending_invoices_count > 0) : ?>
                        <span class="sc-dashboard-badge sc-dashboard-badge-red"><?php echo esc_html(number_format_i18n($pending_invoices_count)); ?></span>
                    <?php endif; ?>
                </span>
                <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
            </summary>
            <div class="sc-dashboard-card-body">
                <div class="sc-dashboard-toolbar">
                    <a href="<?php echo esc_url($invoices_url); ?>" class="sc-dashboard-link-btn">مشاهده صفحه صورت‌حساب‌ها</a>
                </div>

                <?php if (empty($pending_invoices)) : ?>
                    <div class="sc-dashboard-empty sc-dashboard-empty-success">
                        ✅ صورت‌حساب پرداخت‌نشده‌ای ندارید.
                    </div>
                <?php else : ?>
                    <ul class="sc-dashboard-list">
                        <?php foreach ($pending_invoices as $inv) :
                            $title = $inv->course_title ?: ($inv->event_name ?: ($inv->expense_name ?: ($inv->invoice_description ?: 'صورت حساب')));
                            $inv_total = (float) $inv->amount + (float) ($inv->penalty_amount ?? 0);
                            $amount_fmt = function_exists('sc_format_amount_display')
                                ? sc_format_amount_display($inv_total)
                                : number_format($inv_total);
                            ?>
                            <li class="sc-dashboard-list-item">
                                <a href="<?php echo esc_url($invoices_url); ?>" class="sc-dashboard-list-link">
                                    <div class="sc-dashboard-list-main">
                                        <div class="sc-dashboard-list-title">
                                            #<?php echo esc_html($inv->id); ?> - <?php echo esc_html(wp_trim_words($title, 8, '…')); ?>
                                        </div>
                                        <div class="sc-dashboard-list-meta">
                                            مبلغ: <?php echo esc_html($amount_fmt); ?> تومان
                                            <span class="sc-dashboard-meta-sep">|</span>
                                            <?php echo esc_html(sc_date_shamsi_date_only($inv->created_at)); ?>
                                        </div>
                                    </div>
                                    <span class="sc-dashboard-status-badge sc-invoice-status-<?php echo esc_attr($inv->status); ?>">
                                        <?php echo esc_html($invoice_status_label($inv->status)); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>

        <!-- ============ 5) غیبت‌های من (۳ ماه اخیر) ============ -->
        <details class="sc-dashboard-card sc-dash-card-absences" open>
            <summary class="sc-dashboard-card-header">
                <span class="sc-dashboard-card-title">
                    <span class="sc-dashboard-card-icon">📅</span>
                    غیبت‌های من (۳ ماه اخیر)
                    <?php if ($absent_count_total > 0) : ?>
                        <span class="sc-dashboard-badge sc-dashboard-badge-red" title="غیبت غیرمجاز"><?php echo esc_html(number_format_i18n($absent_count_total)); ?></span>
                    <?php endif; ?>
                    <?php if ($excused_count_total > 0) : ?>
                        <span class="sc-dashboard-badge sc-dashboard-badge-orange" title="غیبت مجاز"><?php echo esc_html(number_format_i18n($excused_count_total)); ?></span>
                    <?php endif; ?>
                </span>
                <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
            </summary>
            <div class="sc-dashboard-card-body">
                <div class="sc-dashboard-toolbar">
                    <a href="<?php echo esc_url($absences_url); ?>" class="sc-dashboard-link-btn">مشاهده گزارش حضور و غیاب</a>
                </div>

                <?php if (empty($my_absences)) : ?>
                    <div class="sc-dashboard-empty sc-dashboard-empty-success">
                        ✅ در ۳ ماه اخیر هیچ غیبتی ثبت نشده است.
                    </div>
                <?php else : ?>
                    <ul class="sc-dashboard-list">
                        <?php foreach ($my_absences as $abs) :
                            $is_excused = ($abs->status === 'excused');
                            $abs_label  = $is_excused ? 'غیبت مجاز' : 'غیبت';
                            $abs_class  = $is_excused ? 'sc-status-warning' : 'sc-status-danger';
                            ?>
                            <li class="sc-dashboard-list-item">
                                <div class="sc-dashboard-list-link" style="cursor:default;">
                                    <div class="sc-dashboard-list-main">
                                        <div class="sc-dashboard-list-title">
                                            <?php echo esc_html($abs->course_title ?: '—'); ?>
                                        </div>
                                        <div class="sc-dashboard-list-meta">
                                            <?php echo esc_html(sc_date_shamsi_date_only($abs->attendance_date)); ?>
                                        </div>
                                    </div>
                                    <span class="sc-dashboard-status-badge <?php echo esc_attr($abs_class); ?>">
                                        <?php echo esc_html($abs_label); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>

        <!-- ============ 6) جلسات باقی‌مانده دوره‌ها ============ -->
        <details class="sc-dashboard-card sc-dash-card-sessions" open>
            <summary class="sc-dashboard-card-header">
                <span class="sc-dashboard-card-title">
                    <span class="sc-dashboard-card-icon">⏱️</span>
                    جلسات دوره‌های من
                </span>
                <span class="sc-dashboard-card-toggle" aria-hidden="true"></span>
            </summary>
            <div class="sc-dashboard-card-body">
                <div class="sc-dashboard-toolbar">
                    <a href="<?php echo esc_url($my_courses_url); ?>" class="sc-dashboard-link-btn">مشاهده دوره‌های من</a>
                </div>

                <?php if (empty($remaining_sessions_rows)) : ?>
                    <div class="sc-dashboard-empty">دوره فعالی برای شما ثبت نشده است.</div>
                <?php else : ?>
                    <ul class="sc-dashboard-list sc-dashboard-sessions-list">
                        <?php foreach ($remaining_sessions_rows as $row) :
                            $total     = (int) $row->total_sessions;
                            $remaining = (int) $row->remaining_sessions;
                            $done      = max(0, $total - $remaining);
                            $percent   = $total > 0 ? min(100, max(0, round(($done / $total) * 100))) : 0;
                            ?>
                            <li class="sc-dashboard-list-item">
                                <div class="sc-dashboard-list-link" style="cursor:default; flex-direction:column; align-items:stretch; gap:8px;">
                                    <div class="sc-dashboard-list-main" style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                        <div class="sc-dashboard-list-title"><?php echo esc_html($row->course_title); ?></div>
                                        <div class="sc-dashboard-list-meta">
                                            <?php if ($total > 0) : ?>
                                                باقی‌مانده: <strong><?php echo esc_html(number_format_i18n($remaining)); ?></strong>
                                                از <?php echo esc_html(number_format_i18n($total)); ?>
                                            <?php else : ?>
                                                <span>بدون محدودیت جلسه</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($total > 0) : ?>
                                        <div class="sc-dashboard-progress">
                                            <div class="sc-dashboard-progress-bar" style="width: <?php echo esc_attr($percent); ?>%;"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>

    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-player-dashboard h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>