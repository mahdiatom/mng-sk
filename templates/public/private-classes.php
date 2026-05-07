<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$member_id = function_exists('sc_private_get_member_id_for_current_user') ? sc_private_get_member_id_for_current_user() : 0;
$sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
$courses_table = $wpdb->prefix . 'sc_courses';
$my_sessions = [];
if ($member_id > 0) {
    $my_sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT ps.*, c.title AS course_title
         FROM {$sessions_table} ps
         INNER JOIN {$courses_table} c ON c.id = ps.course_id
         WHERE ps.member_id = %d
           AND ps.session_date >= %s
         ORDER BY ps.session_date ASC, ps.time_start ASC
         LIMIT 120",
        $member_id,
        current_time('Y-m-d')
    ));
}
?>
<div class="sc-enroll-course-page">
    <h2>رزرو کلاس خصوصی</h2>
    <?php if (empty($courses)) : ?>
        <div class="sc-message sc-message-info" style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:15px;margin-bottom:20px;color:#856404;">
            در حال حاضر کلاس خصوصی فعالی برای رزرو وجود ندارد.
        </div>
    <?php else : ?>
        <form method="post" action="">
            <?php wp_nonce_field('sc_book_private_class', 'sc_private_class_nonce'); ?>
            <input type="hidden" name="sc_book_private_class" value="1">
            <table class="form-table">
                <tr>
                    <th><label for="sc_private_course_id">انتخاب کلاس خصوصی</label></th>
                    <td>
                        <select name="course_id" id="sc_private_course_id" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($courses as $course) : ?>
                                <option value="<?php echo esc_attr((int) $course->id); ?>">
                                    <?php echo esc_html($course->title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sc_private_coach_id">انتخاب مربی</label></th>
                    <td>
                        <select name="coach_id" id="sc_private_coach_id" required disabled>
                            <option value="">ابتدا کلاس را انتخاب کنید</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>انتخاب اسلات هفتگی</th>
                    <td>
                        <div id="sc_private_slots_wrap" style="display:grid;gap:8px;">
                            <p class="description">ابتدا کلاس را انتخاب کنید.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="sc_private_pkg_sessions">پکیج جلسات</label></th>
                    <td>
                        <select name="enrollment_sessions" id="sc_private_pkg_sessions" required disabled>
                            <option value="">ابتدا کلاس را انتخاب کنید</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sc_private_start_date">تاریخ شروع</label></th>
                    <td>
                        <input type="date" name="start_date" id="sc_private_start_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                        <p class="description">کل بازه پکیج از این تاریخ بر اساس برنامه هفتگی تولید می‌شود.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">رزرو و ایجاد صورت حساب</button>
            </p>
        </form>
    <?php endif; ?>

    <div style="margin-top:28px;">
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
                            <td><?php echo esc_html($session->status); ?></td>
                            <td>
                                <?php if ($session->status === 'scheduled') : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('sc_private_session_action', 'sc_private_session_nonce'); ?>
                                        <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $session->id); ?>">
                                        <input type="hidden" name="sc_private_session_action" value="cancel">
                                        <button type="submit" class="button">لغو جلسه</button>
                                    </form>
                                <?php elseif ($session->status === 'absent') : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('sc_private_session_action', 'sc_private_session_nonce'); ?>
                                        <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $session->id); ?>">
                                        <input type="hidden" name="sc_private_session_action" value="excuse_absence">
                                        <button type="submit" class="button">مجاز کردن غیبت</button>
                                    </form>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                    $packages[] = [
                        'sessions' => (int) $pkg->sessions_count,
                        'label' => ((int) $pkg->sessions_count) . ' جلسه - ' . (function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $pkg->price)) : number_format((float) $pkg->price, 0, '.', ',') . ' تومان'),
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
