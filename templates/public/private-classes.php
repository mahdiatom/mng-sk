<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$member_id = function_exists('sc_private_get_member_id_for_current_user') ? sc_private_get_member_id_for_current_user() : 0;
$sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
$courses_table = $wpdb->prefix . 'sc_courses';
$my_sessions = [];
$sessions_per_page = 10;
$sessions_page = isset($_GET['private_sessions_paged']) ? max(1, absint($_GET['private_sessions_paged'])) : 1;
$sessions_offset = ($sessions_page - 1) * $sessions_per_page;
$sessions_total = 0;
$sessions_total_pages = 1;
if ($member_id > 0) {
    $sessions_total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$sessions_table}
         WHERE member_id = %d
           AND session_date >= %s",
        $member_id,
        current_time('Y-m-d')
    ));
    $sessions_total_pages = max(1, (int) ceil($sessions_total / $sessions_per_page));
    $my_sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT ps.*, c.title AS course_title
         FROM {$sessions_table} ps
         INNER JOIN {$courses_table} c ON c.id = ps.course_id
         WHERE ps.member_id = %d
           AND ps.session_date >= %s
         ORDER BY ps.session_date ASC, ps.time_start ASC
         LIMIT %d OFFSET %d",
        $member_id,
        current_time('Y-m-d'),
        $sessions_per_page,
        $sessions_offset
    ));
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
?>

<div class="sc-private-page sc-enroll-course-page">
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

                <?php if (!empty($user_fields['slots'])) : ?>
                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-enroll-panel-title">انتخاب زمان هفتگی</div>
                    <p class="sc-private-panel-hint">می‌توانید یک یا چند اسلات را برای برنامه جلسات انتخاب کنید.</p>
                    <div id="sc_private_slots_wrap" class="sc-private-slot-grid">
                        <div class="sc-private-slot-empty">ابتدا دوره، شعبه و مربی را انتخاب کنید.</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($user_fields['sessions'])) : ?>
                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-enroll-panel-title">تعداد جلسات</div>
                    <div id="sc_private_sessions_wrap" class="sc-private-sessions-grid"></div>
                    <select name="enrollment_sessions" id="sc_private_sessions_count" class="sc-private-sessions-native" <?php echo !empty($user_fields['course']) ? 'required disabled' : 'required'; ?> tabindex="-1" aria-hidden="true">
                        <option value=""><?php echo !empty($user_fields['course']) ? 'ابتدا دوره را انتخاب کنید' : 'انتخاب کنید'; ?></option>
                    </select>
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
        <?php if (empty($my_sessions)) : ?>
            <p class="sc-private-panel-hint">جلسه‌ای برای نمایش وجود ندارد.</p>
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
                        echo paginate_links([
                            'base' => add_query_arg('private_sessions_paged', '%#%'),
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
