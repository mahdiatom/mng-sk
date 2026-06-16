<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

$booking_id = isset($_GET['booking_id']) ? absint($_GET['booking_id']) : 0;
$booking = $booking_id > 0 && function_exists('sc_private_get_booking_row') ? sc_private_get_booking_row($booking_id) : null;
$is_readonly = $booking && (string) $booking->status !== 'pending_admin';

$courses = $wpdb->get_results(
    "SELECT * FROM {$courses_table}
     WHERE deleted_at IS NULL AND is_active = 1 AND course_type = 'private'
     ORDER BY title ASC"
);
$booking_config = function_exists('sc_private_build_booking_config') ? sc_private_build_booking_config($courses) : [];
$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM {$members_table} WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");

$selected_member_id = $booking ? (int) $booking->member_id : 0;
$selected_member_text = 'انتخاب بازیکن';
foreach ($members as $member_row) {
    if ((int) $member_row->id === $selected_member_id) {
        $selected_member_text = trim($member_row->first_name . ' ' . $member_row->last_name) . ' - ' . $member_row->national_id;
        break;
    }
}

$prefill = [
    'course_id' => $booking ? (int) $booking->course_id : 0,
    'chapter' => $booking ? (string) $booking->chapter : '',
    'coach_id' => $booking ? (int) $booking->coach_id : 0,
    'enrollment_sessions' => $booking ? (int) $booking->package_sessions : 0,
    'start_date_shamsi' => '',
];
if ($booking && $booking->start_date && $booking->start_date !== sc_private_booking_pending_placeholder_date()) {
    $prefill['start_date_shamsi'] = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($booking->start_date) : '';
}
if ($prefill['start_date_shamsi'] === '') {
    $prefill['start_date_shamsi'] = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
}

$prefill_slot_ids = [];
if ($booking_id > 0) {
    $payload = sc_private_get_pending_booking_payload($booking_id);
    if (!empty($payload['schedule_slot_ids']) && is_array($payload['schedule_slot_ids'])) {
        $prefill_slot_ids = array_map('intval', $payload['schedule_slot_ids']);
    }
}

$today_shamsi = $prefill['start_date_shamsi'];
$ajax_url = admin_url('admin-ajax.php');
$ajax_nonce = wp_create_nonce('sc_private_check_slots');
$is_admin_approval_mode = function_exists('sc_is_private_booking_admin_approval_mode') && sc_is_private_booking_admin_approval_mode();
$page_title = $booking_id > 0 ? 'تکمیل / ثبت رزرو کلاس خصوصی' : 'ثبت‌نام کلاس خصوصی';
$back_url = $is_admin_approval_mode
    ? admin_url('admin.php?page=sc-private-booking-requests')
    : admin_url('admin.php?page=sc-private-bookings-list');
$back_label = $is_admin_approval_mode ? 'بازگشت به لیست' : 'کلاس‌های خصوصی';
?>
<div class="wrap sc-private-admin-wrap sc-users-export-wrap">
    <div class="sc-private-admin-toolbar">
        <div>
            <h1 class="sc-private-admin-title"><?php echo esc_html($page_title); ?></h1>
            <p class="sc-private-admin-subtitle"><?php echo $is_admin_approval_mode ? 'اطلاعات رزرو را تکمیل کنید و صورت‌حساب برای بازیکن صادر شود.' : 'بازیکن را انتخاب کنید، اطلاعات رزرو را تکمیل کنید و صورت‌حساب صادر شود.'; ?></p>
        </div>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php echo esc_html($back_label); ?></a>
    </div>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success"><p>رزرو با موفقیت ثبت شد و صورت‌حساب ایجاد شد.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
    <?php endif; ?>
    <?php if ($is_admin_approval_mode && $booking && (string) $booking->status === 'pending_admin') : ?>
        <div class="notice notice-info"><p>این درخواست توسط کاربر ثبت شده است. فیلدهای ناقص را تکمیل کنید.</p></div>
    <?php endif; ?>
    <?php if ($booking && (string) $booking->status === 'rejected') : ?>
        <div class="notice notice-error"><p><strong>این درخواست رد شده است.</strong><?php if (!empty($booking->rejected_reason)) : ?> دلیل: <?php echo esc_html($booking->rejected_reason); ?><?php endif; ?></p></div>
    <?php endif; ?>
    <?php if ($is_readonly) : ?>
        <div class="notice notice-warning"><p>این رزرو در وضعیت «<?php echo esc_html(sc_private_booking_status_label((string) $booking->status)); ?>» است و قابل ویرایش نیست.</p></div>
    <?php endif; ?>

    <div class="sc-users-export-card sc-private-wrap">
        <form method="post" action="" id="sc-private-booking-form" class="sc-private-booking-form sc-private-admin-booking-form">
            <?php wp_nonce_field('sc_admin_private_booking', 'sc_admin_private_booking_nonce'); ?>
            <input type="hidden" name="sc_admin_finalize_private_booking" value="1">
            <?php if ($booking_id > 0) : ?>
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_id); ?>">
                <input type="hidden" name="member_id" id="sc_admin_member_id" value="<?php echo esc_attr($selected_member_id); ?>">
            <?php endif; ?>

            <div class="sc-private-form-layout">
                <?php if (!$booking_id) : ?>
                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-enroll-panel-title">انتخاب بازیکن</div>
                    <div class="sc-private-admin-member-picker">
                        <div class="sc-searchable-dropdown">
                            <input type="hidden" name="member_id" id="sc_admin_member_id" value="<?php echo esc_attr($selected_member_id); ?>" required>
                            <div class="sc-dropdown-toggle">
                                <span class="sc-dropdown-placeholder" <?php if ($selected_member_id) echo 'style="display:none"'; ?>>انتخاب بازیکن</span>
                                <span class="sc-dropdown-selected" <?php if (!$selected_member_id) echo 'style="display:none"'; ?>><?php echo esc_html($selected_member_text); ?></span>
                                <span class="sc-dropdown-arrow">▼</span>
                            </div>
                            <div class="sc-dropdown-menu">
                                <div class="sc-dropdown-search">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                </div>
                                <div class="sc-dropdown-options">
                                    <?php foreach ($members as $member) :
                                        $label = trim($member->first_name . ' ' . $member->last_name) . ' - ' . $member->national_id;
                                    ?>
                                        <div class="sc-dropdown-option sc-visible"
                                             data-value="<?php echo esc_attr((int) $member->id); ?>"
                                             data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                             onclick="scAdminSelectMember(this,'<?php echo esc_js((int) $member->id); ?>','<?php echo esc_js($label); ?>')">
                                            <?php echo esc_html($label); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="sc-enroll-panel sc-private-panel sc-private-panel-basic">
                    <div class="sc-enroll-panel-title">اطلاعات رزرو</div>
                    <div class="sc-enroll-fields">
                        <div class="sc-private-field-wrap">
                            <label class="sc-enroll-field-label" for="sc_private_course_id">انتخاب دوره</label>
                            <select name="course_id" id="sc_private_course_id" class="sc-enroll-select" required <?php disabled($is_readonly); ?>>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($courses as $course) : ?>
                                    <option value="<?php echo esc_attr((int) $course->id); ?>" <?php selected($prefill['course_id'], (int) $course->id); ?>>
                                        <?php echo esc_html($course->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sc-private-field-wrap">
                            <label class="sc-enroll-field-label" for="sc_private_chapter">انتخاب شعبه</label>
                            <select name="chapter" id="sc_private_chapter" class="sc-enroll-select" required <?php disabled($is_readonly); ?>>
                                <option value="">ابتدا دوره را انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="sc-private-field-wrap">
                            <label class="sc-enroll-field-label" for="sc_private_coach_id">انتخاب مربی</label>
                            <select name="coach_id" id="sc_private_coach_id" class="sc-enroll-select" required <?php disabled($is_readonly); ?>>
                                <option value="">ابتدا شعبه را انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="sc-private-field-wrap">
                            <label class="sc-enroll-field-label" for="sc_private_start_date_shamsi">تاریخ شروع</label>
                            <input type="text" name="start_date_shamsi" id="sc_private_start_date_shamsi"
                                   value="<?php echo esc_attr($today_shamsi); ?>"
                                   class="sc-enroll-select persian-date-input" readonly required <?php disabled($is_readonly); ?>>
                        </div>
                    </div>
                </div>

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
                        <div class="sc-private-slot-empty">ابتدا دوره، شعبه و مربی را انتخاب کنید.</div>
                    </div>
                    <div id="sc_private_sessions_schedule_preview" class="sc-private-sessions-schedule-preview" hidden></div>
                </div>

                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-enroll-panel-title">تعداد جلسات</div>
                    <div id="sc_private_sessions_wrap" class="sc-private-sessions-grid"></div>
                    <select name="enrollment_sessions" id="sc_private_sessions_count" class="sc-private-sessions-native" required <?php disabled($is_readonly); ?> tabindex="-1" aria-hidden="true">
                        <option value="">ابتدا دوره را انتخاب کنید</option>
                    </select>
                </div>

                <div class="sc-enroll-panel sc-private-panel">
                    <div class="sc-private-price-box">
                        <span class="sc-private-price-box-label">مبلغ قابل پرداخت</span>
                        <div id="sc_private_price_preview" class="sc-private-price-preview">—</div>
                    </div>
                </div>
            </div>

            <?php if (!$is_readonly) : ?>
            <div class="sc-private-form-actions">
                <button type="submit" class="button button-primary sc-private-submit-btn" id="sc_private_submit_btn">ثبت نهایی و ایجاد صورت‌حساب</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($is_admin_approval_mode && $booking && (string) $booking->status === 'pending_admin' && !$is_readonly) : ?>
        <div class="sc-users-export-card sc-private-admin-reject-box">
            <div class="sc-private-admin-reject-head">
                <div>
                    <h2>رد درخواست</h2>
                    <p class="sc-private-admin-reject-desc">در صورت عدم امکان رزرو، درخواست را رد کنید. دلیل رد برای بازیکن نمایش داده می‌شود.</p>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-form&booking_id=' . (int) $booking_id)); ?>" class="sc-private-admin-reject-form" onsubmit="return confirm('درخواست رد شود؟');">
                <?php wp_nonce_field('sc_admin_private_booking', 'sc_admin_private_booking_nonce'); ?>
                <input type="hidden" name="sc_reject_private_booking" value="1">
                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking_id); ?>">
                <label class="sc-enroll-field-label" for="sc_private_rejected_reason">دلیل رد (اختیاری)</label>
                <textarea name="rejected_reason" id="sc_private_rejected_reason" class="sc-private-admin-reject-textarea" rows="3" placeholder="مثلاً: ظرفیت این بازه تکمیل است یا اطلاعات درخواست ناقص بود."></textarea>
                <div class="sc-private-admin-reject-actions">
                    <button type="submit" class="sc-private-admin-reject-btn">رد درخواست</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function scAdminSelectMember(el, value, label) {
    var wrap = el.closest('.sc-searchable-dropdown');
    if (!wrap) return;
    wrap.querySelector('input[type="hidden"]').value = value;
    var ph = wrap.querySelector('.sc-dropdown-placeholder');
    var sel = wrap.querySelector('.sc-dropdown-selected');
    if (ph) ph.style.display = 'none';
    if (sel) { sel.style.display = ''; sel.textContent = label; }
    wrap.querySelector('.sc-dropdown-menu').style.display = 'none';
}
</script>

<?php if (!empty($courses) && !$is_readonly) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.scInitPrivateBookingForm === 'function') {
        window.scInitPrivateBookingForm({
            data: <?php echo wp_json_encode($booking_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            ajaxUrl: <?php echo wp_json_encode($ajax_url); ?>,
            ajaxNonce: <?php echo wp_json_encode($ajax_nonce); ?>,
            isAdmin: true,
            userFields: <?php echo wp_json_encode(array_fill_keys(['course', 'chapter', 'coach', 'slots', 'sessions', 'start_date'], 1), JSON_UNESCAPED_UNICODE); ?>,
            prefill: <?php echo wp_json_encode([
                'course_id' => $prefill['course_id'],
                'chapter' => $prefill['chapter'],
                'coach_id' => $prefill['coach_id'],
                'enrollment_sessions' => $prefill['enrollment_sessions'],
                'slot_ids' => $prefill_slot_ids,
            ], JSON_UNESCAPED_UNICODE); ?>
        });
    }
});
</script>
<?php endif; ?>
