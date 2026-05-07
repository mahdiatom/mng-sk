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
?>
<style>
.sc-private-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
.sc-private-grid{display:grid;gap:12px}
.sc-private-field label{display:block;font-weight:600;margin-bottom:6px}
.sc-private-field select,.sc-private-field input[type="text"]{width:100%;max-width:460px}
.sc-private-slots{display:grid;gap:8px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa}
.sc-private-slots label{display:block}
.sc-private-card{margin-top:28px;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}
.sc-private-status{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
.sc-private-status-scheduled{background:#dbeafe;color:#1e40af}
.sc-private-status-cancelled{background:#fee2e2;color:#991b1b}
.sc-private-status-absent{background:#ffedd5;color:#9a3412}
.sc-private-status-excused{background:#ede9fe;color:#5b21b6}
.sc-private-status-rescheduled{background:#e0f2fe;color:#075985}
.sc-private-status-done{background:#dcfce7;color:#166534}
.sc-private-disabled-note{color:#9ca3af;font-size:12px}
.sc-private-cancel-btn{background:#ef4444 !important;border-color:#ef4444 !important;color:#fff !important;border-radius:8px !important;padding:4px 10px !important}
.sc-private-cancel-btn:hover{background:#dc2626 !important;border-color:#dc2626 !important}
</style>
<div class="sc-enroll-course-page sc-private-wrap">
    <h2>رزرو کلاس خصوصی</h2>
    <?php if (empty($courses)) : ?>
        <div class="sc-message sc-message-info" style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:15px;margin-bottom:20px;color:#856404;">
            در حال حاضر کلاس خصوصی فعالی برای رزرو وجود ندارد.
        </div>
    <?php else : ?>
        <form method="post" action="">
            <?php wp_nonce_field('sc_book_private_class', 'sc_private_class_nonce'); ?>
            <input type="hidden" name="sc_book_private_class" value="1">
            <div class="sc-private-grid">
                <div class="sc-private-field">
                    <label for="sc_private_course_id">انتخاب کلاس خصوصی</label>
                    <select name="course_id" id="sc_private_course_id" required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr((int) $course->id); ?>">
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-private-field">
                    <label for="sc_private_coach_id">انتخاب مربی</label>
                    <select name="coach_id" id="sc_private_coach_id" required disabled>
                        <option value="">ابتدا کلاس را انتخاب کنید</option>
                    </select>
                </div>
                <div class="sc-private-field">
                    <label>انتخاب اسلات هفتگی</label>
                    <div id="sc_private_slots_wrap" class="sc-private-slots">
                        <p class="description">ابتدا کلاس را انتخاب کنید.</p>
                    </div>
                </div>
                <div class="sc-private-field">
                    <label for="sc_private_pkg_sessions">پکیج جلسات</label>
                    <select name="enrollment_sessions" id="sc_private_pkg_sessions" required disabled>
                        <option value="">ابتدا کلاس را انتخاب کنید</option>
                    </select>
                </div>
                <div class="sc-private-field">
                    <label for="sc_private_start_date_shamsi">تاریخ شروع</label>
                    <input type="text" name="start_date_shamsi" id="sc_private_start_date_shamsi" value="<?php echo esc_attr($today_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلا 1405/02/17" readonly required>
                    <p class="description">کل بازه پکیج از این تاریخ بر اساس برنامه هفتگی تولید می‌شود.</p>
                </div>
            </div>
            <p class="submit">
                <button type="submit" class="button button-primary">رزرو و ایجاد صورت حساب</button>
            </p>
        </form>
    <?php endif; ?>

    <div class="sc-private-card">
        <h3>جلسات خصوصی من</h3>
        <?php if (empty($my_sessions)) : ?>
            <p class="description">جلسه‌ای برای نمایش وجود ندارد.</p>
        <?php else : ?>
            <table class="shop_table shop_table_responsive my_account_orders">
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
                            <td><?php echo esc_html($session->course_title); ?></td>
                            <td><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($session->session_date) : $session->session_date); ?></td>
                            <td><?php echo esc_html(substr((string) $session->time_start, 0, 5) . ' تا ' . substr((string) $session->time_end, 0, 5)); ?></td>
                            <?php
                            $status_key = (string) $session->status;
                            $status_class = 'sc-private-status sc-private-status-' . preg_replace('/[^a-z_]/', '', $status_key);
                            $status_label = function_exists('sc_private_session_status_label') ? sc_private_session_status_label($status_key) : $status_key;
                            ?>
                            <td><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td>
                                <?php
                                $session_start_ts = strtotime((string) $session->session_date . ' ' . substr((string) $session->time_start, 0, 8));
                                $is_past_session = ((string) $session->session_date < current_time('Y-m-d')) || ($session_start_ts > 0 && $session_start_ts <= current_time('timestamp'));
                                ?>
                                <?php if ($session->status === 'scheduled' && !$is_past_session) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('sc_private_session_action', 'sc_private_session_nonce'); ?>
                                        <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $session->id); ?>">
                                        <input type="hidden" name="sc_private_session_action" value="cancel">
                                        <button type="submit" class="button sc-private-cancel-btn" onclick="return confirm('از لغو این جلسه مطمئن هستید؟');">لغو جلسه</button>
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
        const data = <?php
        $map = [];
        foreach ($courses as $course) {
            $course_id = (int) $course->id;
            $coaches = function_exists('sc_get_private_course_coaches') ? sc_get_private_course_coaches($course_id) : [];
            $schedule_rows = function_exists('sc_get_course_weekly_schedule_rows') ? sc_get_course_weekly_schedule_rows($course_id) : [];
            $slots = [];
            $weekday_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
            foreach ($schedule_rows as $row) {
                $slots[] = [
                    'id' => (int) $row->id,
                    'label' => (isset($weekday_labels[(int) $row->weekday]) ? $weekday_labels[(int) $row->weekday] : '-') . ' | ' . substr((string) $row->time_start, 0, 5) . ' تا ' . substr((string) $row->time_end, 0, 5),
                ];
            }
            $packages = [];
            if (function_exists('sc_get_course_packages')) {
                $pkg_rows = sc_get_course_packages($course_id);
                foreach ($pkg_rows as $pkg) {
                    $price_label = number_format((float) $pkg->price, 0, '.', ',') . ' تومان';
                    if (function_exists('wc_price')) {
                        $price_raw = html_entity_decode(wc_price((float) $pkg->price), ENT_QUOTES, 'UTF-8');
                        $price_raw = wp_strip_all_tags($price_raw);
                        $price_raw = preg_replace('/\x{00A0}/u', ' ', $price_raw);
                        $price_label = trim((string) $price_raw);
                    }
                    $packages[] = [
                        'sessions' => (int) $pkg->sessions_count,
                        'label' => ((int) $pkg->sessions_count) . ' جلسه - ' . $price_label,
                    ];
                }
            }
            $map[$course_id] = [
                'coaches' => array_map(function ($c) {
                    return ['id' => (int) $c->id, 'name' => trim((string) $c->first_name . ' ' . $c->last_name)];
                }, $coaches),
                'slots' => $slots,
                'packages' => $packages,
            ];
        }
        echo wp_json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;

        const courseSel = document.getElementById('sc_private_course_id');
        const coachSel = document.getElementById('sc_private_coach_id');
        const slotsWrap = document.getElementById('sc_private_slots_wrap');
        const pkgSel = document.getElementById('sc_private_pkg_sessions');

        function resetSelect(sel, placeholder) {
            sel.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            sel.appendChild(opt);
        }

        function onCourseChange() {
            const cid = parseInt(courseSel.value || '0', 10);
            const info = cid && data[cid] ? data[cid] : null;
            resetSelect(coachSel, info ? 'مربی را انتخاب کنید' : 'ابتدا کلاس را انتخاب کنید');
            resetSelect(pkgSel, info ? 'پکیج را انتخاب کنید' : 'ابتدا کلاس را انتخاب کنید');
            coachSel.disabled = !info;
            pkgSel.disabled = !info;
            slotsWrap.innerHTML = '';
            if (!info) {
                slotsWrap.innerHTML = '<p class="description">ابتدا کلاس را انتخاب کنید.</p>';
                return;
            }
            info.coaches.forEach(function (item) {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.name;
                coachSel.appendChild(opt);
            });
            info.packages.forEach(function (item) {
                const opt = document.createElement('option');
                opt.value = String(item.sessions);
                opt.textContent = item.label;
                pkgSel.appendChild(opt);
            });
            if (info.slots.length === 0) {
                slotsWrap.innerHTML = '<p class="description" style="color:#d63638;">برای این دوره اسلات زمانی تعریف نشده است.</p>';
                return;
            }
            info.slots.forEach(function (slot) {
                const label = document.createElement('label');
                label.style.display = 'block';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'schedule_slot_ids[]';
                cb.value = String(slot.id);
                label.appendChild(cb);
                label.append(' ' + slot.label);
                slotsWrap.appendChild(label);
            });
        }
        courseSel.addEventListener('change', onCourseChange);
    });
    </script>
<?php endif; ?>
