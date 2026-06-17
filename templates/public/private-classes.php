<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$member_id = function_exists('sc_private_get_member_id_for_current_user') ? sc_private_get_member_id_for_current_user() : 0;
$sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
$courses_table = $wpdb->prefix . 'sc_courses';
$my_sessions = [];
$session_courses = [];
$sessions_per_page = 10;
$sessions_page = isset($_GET['private_sessions_paged']) ? max(1, absint($_GET['private_sessions_paged'])) : 1;
$sessions_offset = ($sessions_page - 1) * $sessions_per_page;
$sessions_total = 0;
$sessions_total_pages = 1;
$session_filter_course = isset($_GET['session_filter_course']) ? absint($_GET['session_filter_course']) : 0;
$session_filter_status = isset($_GET['session_filter_status']) ? sanitize_text_field(wp_unslash($_GET['session_filter_status'])) : 'all';
$session_filter_date_from_shamsi = '';
$session_filter_date_to_shamsi = '';
$session_filter_date_from = '';
$session_filter_date_to = '';
if (!empty($_GET['session_filter_date_from_shamsi'])) {
    $session_filter_date_from_shamsi = sanitize_text_field(wp_unslash($_GET['session_filter_date_from_shamsi']));
    $session_filter_date_from = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($session_filter_date_from_shamsi) : '';
}
if (!empty($_GET['session_filter_date_to_shamsi'])) {
    $session_filter_date_to_shamsi = sanitize_text_field(wp_unslash($_GET['session_filter_date_to_shamsi']));
    $session_filter_date_to = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($session_filter_date_to_shamsi) : '';
}
$session_filters_active = (
    $session_filter_course > 0
    || $session_filter_status !== 'all'
    || $session_filter_date_from_shamsi !== ''
    || $session_filter_date_to_shamsi !== ''
);
$session_status_options = [
    'all' => 'همه وضعیت‌ها',
    'scheduled' => 'برنامه‌ریزی‌شده',
    'done' => 'برگزار شده',
    'cancelled' => 'لغو شده',
    'absent' => 'غایب',
    'excused' => 'غیبت مجاز',
    'rescheduled' => 'جابجا شده',
];
$sessions_endpoint_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-private-classes') : '';
if ($member_id > 0) {
    $session_courses = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT c.id, c.title
         FROM {$sessions_table} ps
         INNER JOIN {$courses_table} c ON c.id = ps.course_id
         WHERE ps.member_id = %d
         ORDER BY c.title ASC",
        $member_id
    ));
    $session_where = ['ps.member_id = %d'];
    $session_values = [$member_id];
    if ($session_filter_course > 0) {
        $session_where[] = 'ps.course_id = %d';
        $session_values[] = $session_filter_course;
    }
    if ($session_filter_status !== 'all') {
        $session_where[] = 'ps.status = %s';
        $session_values[] = $session_filter_status;
    }
    if ($session_filter_date_from !== '') {
        $session_where[] = 'ps.session_date >= %s';
        $session_values[] = $session_filter_date_from;
    } elseif (!$session_filters_active) {
        $session_where[] = 'ps.session_date >= %s';
        $session_values[] = current_time('Y-m-d');
    }
    if ($session_filter_date_to !== '') {
        $session_where[] = 'ps.session_date <= %s';
        $session_values[] = $session_filter_date_to;
    }
    $session_where_clause = implode(' AND ', $session_where);
    $sessions_count_sql = "SELECT COUNT(*)
                           FROM {$sessions_table} ps
                           INNER JOIN {$courses_table} c ON c.id = ps.course_id
                           WHERE {$session_where_clause}";
    $sessions_total = !empty($session_values)
        ? (int) $wpdb->get_var($wpdb->prepare($sessions_count_sql, $session_values))
        : (int) $wpdb->get_var($sessions_count_sql);
    $sessions_total_pages = max(1, (int) ceil($sessions_total / $sessions_per_page));
    $sessions_list_sql = "SELECT ps.*, c.title AS course_title
                          FROM {$sessions_table} ps
                          INNER JOIN {$courses_table} c ON c.id = ps.course_id
                          WHERE {$session_where_clause}
                          ORDER BY ps.session_date ASC, ps.time_start ASC
                          LIMIT %d OFFSET %d";
    $sessions_list_values = $session_values;
    $sessions_list_values[] = $sessions_per_page;
    $sessions_list_values[] = $sessions_offset;
    $my_sessions = $wpdb->get_results($wpdb->prepare($sessions_list_sql, $sessions_list_values));
}
$today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
$booking_config = isset($private_booking_config) && is_array($private_booking_config) ? $private_booking_config : [];
$booking_mode = isset($private_booking_mode) ? $private_booking_mode : (function_exists('sc_get_private_booking_mode') ? sc_get_private_booking_mode() : 'direct_payment');
$user_fields = isset($private_booking_user_fields) && is_array($private_booking_user_fields)
    ? $private_booking_user_fields
    : array_fill_keys(['course', 'chapter', 'coach', 'slots', 'sessions', 'start_date'], 1);
$is_admin_approval = ($booking_mode === 'admin_approval');
$member_bookings = isset($private_member_bookings) && is_array($private_member_bookings) ? $private_member_bookings : [];
$ajax_url = admin_url('admin-ajax.php');
$ajax_nonce = wp_create_nonce('sc_private_check_slots');
$show_price = !$is_admin_approval || (!empty($user_fields['sessions']) && !empty($user_fields['slots']) && !empty($user_fields['coach']) && !empty($user_fields['chapter']) && !empty($user_fields['course']));
$page_description = function_exists('sc_get_private_class_page_description') ? sc_get_private_class_page_description() : '';
?>

<div class="sc-private-page sc-enroll-course-page">
    <?php if ($page_description !== '') : ?>
        <div class="sc-private-page-notice" role="status" aria-live="polite">
            <?php echo wp_kses_post(wpautop($page_description)); ?>
        </div>
    <?php endif; ?>
    <div class="sc-private-page-header">
        <h2>رزرو کلاس خصوصی</h2>
        <?php if ($is_admin_approval) : ?>
            <p>درخواست خود را ثبت کنید؛ پس از بررسی مدیر، صورت‌حساب صادر می‌شود.</p>
        <?php else : ?>
            <p>دوره، زمان و تعداد جلسات را انتخاب کنید و پس از پرداخت، جلسات فعال می‌شوند.</p>
        <?php endif; ?>
    </div>

    <?php if (empty($courses)) : ?>
        <div class="sc-message sc-message-info">در حال حاضر کلاس خصوصی فعالی برای رزرو وجود ندارد.</div>
    <?php else : ?>
        <div class="sc-private-wrap">
        <form method="post" action="" id="sc-private-booking-form" class="sc-private-booking-form">
            <?php wp_nonce_field('sc_book_private_class', 'sc_private_class_nonce'); ?>
            <?php if ($is_admin_approval) : ?>
                <input type="hidden" name="sc_book_private_class_request" value="1">
            <?php else : ?>
                <input type="hidden" name="sc_book_private_class" value="1">
            <?php endif; ?>

            <div class="sc-private-form-layout">
                <?php if (!empty($user_fields['course']) || !empty($user_fields['chapter']) || !empty($user_fields['coach']) || !empty($user_fields['start_date'])) : ?>
                <div class="sc-enroll-panel sc-private-panel sc-private-panel-basic">
                    <div class="sc-enroll-panel-title">اطلاعات رزرو</div>
                    <div class="sc-enroll-fields">
                        <?php if (!empty($user_fields['course'])) : ?>
                        <div class="sc-private-field-wrap sc-private-field-course">
                            <label class="sc-enroll-field-label" for="sc_private_course_id">انتخاب دوره</label>
                            <select name="course_id" id="sc_private_course_id" class="sc-enroll-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($courses as $course) : ?>
                                    <option value="<?php echo esc_attr((int) $course->id); ?>"><?php echo esc_html($course->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_fields['chapter'])) : ?>
                        <div class="sc-private-field-wrap sc-private-field-chapter">
                            <label class="sc-enroll-field-label" for="sc_private_chapter">انتخاب شعبه</label>
                            <select name="chapter" id="sc_private_chapter" class="sc-enroll-select" <?php echo !empty($user_fields['course']) ? 'required disabled' : 'required'; ?>>
                                <option value=""><?php echo !empty($user_fields['course']) ? 'ابتدا دوره را انتخاب کنید' : 'انتخاب کنید'; ?></option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_fields['coach'])) : ?>
                        <div class="sc-private-field-wrap sc-private-field-coach">
                            <label class="sc-enroll-field-label" for="sc_private_coach_id">انتخاب مربی</label>
                            <select name="coach_id" id="sc_private_coach_id" class="sc-enroll-select" <?php echo !empty($user_fields['chapter']) ? 'required disabled' : 'required'; ?>>
                                <option value=""><?php echo !empty($user_fields['chapter']) ? 'ابتدا شعبه را انتخاب کنید' : 'انتخاب کنید'; ?></option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user_fields['start_date'])) : ?>
                        <div class="sc-private-field-wrap sc-private-field-start-date">
                            <label class="sc-enroll-field-label" for="sc_private_start_date_shamsi">تاریخ شروع</label>
                            <input type="text" name="start_date_shamsi" id="sc_private_start_date_shamsi" value="<?php echo esc_attr($today_shamsi); ?>" class="sc-enroll-select persian-date-input" placeholder="مثلا 1405/02/17" readonly required>
                            <p class="sc-private-panel-hint">کل بازه از این تاریخ بر اساس برنامه هفتگی تولید می‌شود.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($user_fields['sessions'])) : ?>
                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-enroll-panel-title">تعداد جلسات</div>
                    <p class="sc-private-panel-hint">ابتدا تعداد جلسات را انتخاب کنید؛ پیش‌نمایش جلسات آینده و بررسی ظرفیت بازه‌ها بر اساس این عدد انجام می‌شود.</p>
                    <div id="sc_private_sessions_wrap" class="sc-private-sessions-grid"></div>
                    <input type="hidden" name="enrollment_sessions" id="sc_private_enrollment_sessions_value" value="">
                    <select id="sc_private_sessions_count" class="sc-private-sessions-native" tabindex="-1" aria-hidden="true">
                        <option value=""><?php echo !empty($user_fields['course']) ? 'ابتدا دوره را انتخاب کنید' : 'انتخاب کنید'; ?></option>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (!empty($user_fields['slots'])) : ?>
                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-enroll-panel-title">انتخاب زمان هفتگی</div>
                    <p class="sc-private-panel-hint">یک یا چند بازه زمانی هفتگی را انتخاب کنید. بازه‌های «پر شده» قابل رزرو نیستند. با تغییر تاریخ شروع، وضعیت رزرو به‌روز می‌شود.</p>
                    <div class="sc-private-slots-preview-bar">
                        <div id="sc_private_slots_preview" class="sc-private-slots-preview" hidden></div>
                        <button type="button" id="sc_private_slots_preview_refresh" class="sc-private-slots-preview-refresh" hidden>
                            <span class="sc-private-slots-preview-refresh-icon" aria-hidden="true">↻</span>
                            <span class="sc-private-slots-preview-refresh-label">بروزرسانی پیش‌نمایش</span>
                        </button>
                    </div>
                    <div id="sc_private_slots_wrap" class="sc-private-slot-grid">
                        <?php if (!empty($user_fields['course'])) : ?>
                        <div class="sc-private-slot-empty">ابتدا دوره<?php echo !empty($user_fields['chapter']) ? '، شعبه' : ''; ?><?php echo !empty($user_fields['coach']) ? ' و مربی' : ''; ?><?php echo !empty($user_fields['sessions']) ? ' و تعداد جلسات' : ''; ?> را انتخاب کنید.</div>
                        <?php else : ?>
                        <div class="sc-private-slot-empty">در حال بارگذاری بازه‌های زمانی...</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sc-enroll-panel sc-private-panel sc-private-panel-schedule-preview">
                    <div class="sc-enroll-panel-title">پیش‌نمایش جلسات آینده</div>
                    <p class="sc-private-panel-hint">پس از انتخاب تعداد جلسات و بازه‌های زمانی، برنامه جلسات در این بخش نمایش داده می‌شود.</p>
                    <div id="sc_private_sessions_schedule_preview" class="sc-private-sessions-schedule-preview" hidden></div>
                </div>
                <?php endif; ?>

                <?php if ($show_price) : ?>
                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-private-price-box">
                        <span class="sc-private-price-box-label">مبلغ قابل پرداخت</span>
                        <div id="sc_private_price_preview" class="sc-private-price-preview">—</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="sc-private-form-actions">
                <button type="submit" class="button button-primary sc-private-submit-btn" id="sc_private_submit_btn">
                    <?php echo $is_admin_approval ? 'ارسال درخواست رزرو' : 'رزرو و ایجاد صورت حساب'; ?>
                </button>
                <?php if ($is_admin_approval) : ?>
                    <p class="sc-private-form-note">پس از بررسی مدیر، صورت‌حساب برای شما صادر می‌شود و پس از پرداخت، جلسات فعال خواهند شد.</p>
                <?php endif; ?>
            </div>
        </form>
        </div>
    <?php endif; ?>

    <?php if (!empty($member_bookings)) : ?>
    <div class="sc-private-requests-section">
        <div class="sc-private-section-head">
            <h3><?php echo $is_admin_approval ? 'درخواست‌های رزرو من' : 'رزروهای من'; ?></h3>
        </div>
        <div class="sc-private-requests-grid">
            <?php foreach ($member_bookings as $brow) :
                $blabel = function_exists('sc_private_booking_status_label') ? sc_private_booking_status_label((string) $brow->status) : $brow->status;
                $badge_class = function_exists('sc_private_booking_status_badge_class') ? sc_private_booking_status_badge_class((string) $brow->status) : 'sc-pb-status';
                $invoice_url = !empty($brow->invoice_id)
                    ? add_query_arg(['invoice_search' => (int) $brow->invoice_id], wc_get_account_endpoint_url('sc-invoices'))
                    : '';
            ?>
                <article class="sc-private-request-card">
                    <div class="sc-private-request-card-head">
                        <div class="sc-private-request-course"><?php echo esc_html($brow->course_title ?: '—'); ?></div>
                        <span class="<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($blabel); ?></span>
                    </div>
                    <div class="sc-private-request-meta">
                        <?php if ($brow->chapter !== '') : ?>
                            <span class="sc-private-request-meta-item"><strong>شعبه:</strong> <?php echo esc_html($brow->chapter); ?></span>
                        <?php endif; ?>
                        <?php if ((int) $brow->package_sessions > 0) : ?>
                            <span class="sc-private-request-meta-item"><strong>جلسات:</strong> <?php echo esc_html((string) (int) $brow->package_sessions); ?></span>
                        <?php endif; ?>
                        <span class="sc-private-request-meta-item"><strong>تاریخ:</strong> <?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($brow->created_at) : $brow->created_at); ?></span>
                    </div>
                    <?php if ((string) $brow->status === 'rejected' && !empty($brow->rejected_reason)) : ?>
                        <div class="sc-private-request-reason"><?php echo esc_html($brow->rejected_reason); ?></div>
                    <?php endif; ?>
                    <div class="sc-private-request-actions">
                        <?php if (!empty($brow->invoice_id) && (string) $brow->status === 'pending_payment') : ?>
                            <a href="<?php echo esc_url($invoice_url); ?>" class="sc-private-btn sc-private-btn-pay">پرداخت صورت‌حساب #<?php echo esc_html((string) $brow->invoice_id); ?></a>
                        <?php elseif (!empty($brow->invoice_id)) : ?>
                            <a href="<?php echo esc_url($invoice_url); ?>" class="sc-private-btn sc-private-btn-muted">مشاهده صورت‌حساب #<?php echo esc_html((string) $brow->invoice_id); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="sc-private-card">
        <div class="sc-private-section-head">
            <h3>جلسات خصوصی من</h3>
        </div>
        <?php if ($member_id > 0) : ?>
            <form method="get" action="<?php echo esc_url($sessions_endpoint_url); ?>" class="sc-private-sessions-filters">
                <div class="sc-enroll-fields sc-private-sessions-filter-fields">
                    <div class="sc-private-field-wrap">
                        <label class="sc-enroll-field-label" for="session_filter_course">نام دوره</label>
                        <select class="sc-enroll-select" id="session_filter_course" name="session_filter_course">
                            <option value="0">همه دوره‌ها</option>
                            <?php foreach ($session_courses as $session_course) : ?>
                                <option value="<?php echo esc_attr((int) $session_course->id); ?>" <?php selected($session_filter_course, (int) $session_course->id); ?>><?php echo esc_html($session_course->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-private-field-wrap">
                        <label class="sc-enroll-field-label" for="session_filter_status">وضعیت</label>
                        <select class="sc-enroll-select" id="session_filter_status" name="session_filter_status">
                            <?php foreach ($session_status_options as $status_key => $status_label) : ?>
                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($session_filter_status, $status_key); ?>><?php echo esc_html($status_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-private-field-wrap sc-private-sessions-filter-date-wrap">
                        <label class="sc-enroll-field-label">بازه تاریخ</label>
                        <div class="sc-private-sessions-filter-date-range">
                            <input type="text" name="session_filter_date_from_shamsi" class="sc-enroll-select persian-date-input sc-no-default-date" value="<?php echo esc_attr($session_filter_date_from_shamsi); ?>" placeholder="از تاریخ" readonly autocomplete="off">
                            <span class="sc-private-sessions-filter-date-sep">تا</span>
                            <input type="text" name="session_filter_date_to_shamsi" class="sc-enroll-select persian-date-input sc-no-default-date" value="<?php echo esc_attr($session_filter_date_to_shamsi); ?>" placeholder="تا تاریخ" readonly autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="sc-private-sessions-filter-actions">
                    <button type="submit" class="button button-primary sc-private-submit-btn">اعمال فیلتر</button>
                    <?php if ($session_filters_active) : ?>
                        <a href="<?php echo esc_url($sessions_endpoint_url); ?>" class="sc-private-btn sc-private-btn-muted">پاک کردن فیلترها</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
        <?php if (empty($my_sessions)) : ?>
            <p class="sc-private-panel-hint">
                <?php echo $session_filters_active ? 'جلسه‌ای با این فیلترها یافت نشد.' : 'جلسه‌ای برای نمایش وجود ندارد.'; ?>
            </p>
        <?php else : ?>
            <div class="sc-private-sessions-table-wrap">
            <table class="sc-private-sessions-table">
                <thead>
                    <tr>
                        <th>دوره</th>
                        <th>تاریخ</th>
                        <th>ساعت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_sessions as $session) : ?>
                        <tr>
                            <td data-title="نام دوره" class="td_name_course_privet"><?php echo esc_html($session->course_title); ?></td>
                            <td data-title="تاریخ"><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($session->session_date) : $session->session_date); ?></td>
                            <td data-title="ساعت" ><?php echo esc_html(substr((string) $session->time_start, 0, 5) . ' تا ' . substr((string) $session->time_end, 0, 5)); ?></td>
                            <?php
                            $status_key = (string) $session->status;
                            $status_class = 'sc-private-status sc-private-status-' . preg_replace('/[^a-z_]/', '', $status_key);
                            $status_label = function_exists('sc_private_session_status_label') ? sc_private_session_status_label($status_key) : $status_key;
                            ?>
                            <td data-title="وضعیت"><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td data-title="عملیات" class="td_session_privet">
                                <?php
                                $session_start_ts = strtotime((string) $session->session_date . ' ' . substr((string) $session->time_start, 0, 8));
                                $is_past_session = ((string) $session->session_date < current_time('Y-m-d')) || ($session_start_ts > 0 && $session_start_ts <= current_time('timestamp'));
                                ?>
                                <?php if ($session->status === 'scheduled' && !$is_past_session) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('sc_private_session_action', 'sc_private_session_nonce'); ?>
                                        <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $session->id); ?>">
                                        <input type="hidden" name="sc_private_session_action" value="cancel">
                                        <button type="submit" class="button sc-private-cancel-btn" onclick="return scConfirmInline(event, { type: 'warning', message: 'از لغو این جلسه مطمئن هستید؟' });">لغو جلسه</button>
                                    </form>
                                <?php elseif ($is_past_session && $session->status === 'scheduled') : ?>
                                    <span class="sc-private-disabled-note">جلسه گذشته است</span>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ($sessions_total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin-top:12px;">
                    <div class="tablenav-pages">
                        <?php
                        $sessions_paginate_args = ['private_sessions_paged' => '%#%'];
                        if ($session_filter_course > 0) {
                            $sessions_paginate_args['session_filter_course'] = $session_filter_course;
                        }
                        if ($session_filter_status !== 'all') {
                            $sessions_paginate_args['session_filter_status'] = $session_filter_status;
                        }
                        if ($session_filter_date_from_shamsi !== '') {
                            $sessions_paginate_args['session_filter_date_from_shamsi'] = $session_filter_date_from_shamsi;
                        }
                        if ($session_filter_date_to_shamsi !== '') {
                            $sessions_paginate_args['session_filter_date_to_shamsi'] = $session_filter_date_to_shamsi;
                        }
                        echo paginate_links([
                            'base' => add_query_arg($sessions_paginate_args, $sessions_endpoint_url),
                            'format' => '',
                            'prev_text' => '< قبلی',
                            'next_text' => 'بعدی >',
                            'total' => $sessions_total_pages,
                            'current' => $sessions_page,
                            'type' => 'plain',
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($courses)) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.scInitPrivateBookingForm === 'function') {
        window.scInitPrivateBookingForm({
            data: <?php echo wp_json_encode($booking_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            ajaxUrl: <?php echo wp_json_encode($ajax_url); ?>,
            ajaxNonce: <?php echo wp_json_encode($ajax_nonce); ?>,
            userFields: <?php echo wp_json_encode(array_map('intval', $user_fields), JSON_UNESCAPED_UNICODE); ?>,
            prefill: {}
        });
    }
});
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-private-page-header h2');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);
});
</script>
