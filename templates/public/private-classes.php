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
$ajax_url = admin_url('admin-ajax.php');
$ajax_nonce = wp_create_nonce('sc_private_check_slots');
?>

<div class="sc-enroll-course-page sc-private-wrap">
    <h2>رزرو کلاس خصوصی</h2>
    <?php if (empty($courses)) : ?>
        <div class="sc-message sc-message-info" style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:15px;margin-bottom:20px;color:#856404;">
            در حال حاضر کلاس خصوصی فعالی برای رزرو وجود ندارد.
        </div>
    <?php else : ?>
        <form method="post" action="" id="sc-private-booking-form">
            <?php wp_nonce_field('sc_book_private_class', 'sc_private_class_nonce'); ?>
            <input type="hidden" name="sc_book_private_class" value="1">
            <div class="sc-private-grid">
                <div class="sc-private-field">
                    <label for="sc_private_course_id">انتخاب دوره</label>
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
                    <label for="sc_private_chapter">انتخاب شعبه</label>
                    <select name="chapter" id="sc_private_chapter" required disabled>
                        <option value="">ابتدا دوره را انتخاب کنید</option>
                    </select>
                </div>
                <div class="sc-private-field">
                    <label for="sc_private_coach_id">انتخاب مربی</label>
                    <select name="coach_id" id="sc_private_coach_id" required disabled>
                        <option value="">ابتدا شعبه را انتخاب کنید</option>
                    </select>
                </div>
                <div class="sc-private-field">
                    <label>انتخاب اسلات هفتگی</label>
                    <div id="sc_private_slots_wrap" class="sc-private-slots">
                        <p class="description">ابتدا دوره، شعبه و مربی را انتخاب کنید.</p>
                    </div>
                </div>
                <div class="sc-private-field">
                    <label for="sc_private_sessions_count">تعداد جلسات</label>
                    <select name="enrollment_sessions" id="sc_private_sessions_count" required disabled>
                        <option value="">ابتدا دوره را انتخاب کنید</option>
                    </select>
                </div>
                <div class="sc-private-field">
                    <label for="sc_private_start_date_shamsi">تاریخ شروع</label>
                    <input type="text" name="start_date_shamsi" id="sc_private_start_date_shamsi" value="<?php echo esc_attr($today_shamsi); ?>" class="regular-text persian-date-input" placeholder="مثلا 1405/02/17" readonly required>
                    <p class="description">کل بازه از این تاریخ بر اساس برنامه هفتگی تولید می‌شود.</p>
                </div>
                <div class="sc-private-field">
                    <label>مبلغ قابل پرداخت</label>
                    <div id="sc_private_price_preview" class="sc-private-price-preview">—</div>
                </div>
            </div>
            <p class="submit">
                <button type="submit" class="button button-primary" id="sc_private_submit_btn">رزرو و ایجاد صورت حساب</button>
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
        const data = <?php echo wp_json_encode($booking_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        const ajaxNonce = <?php echo wp_json_encode($ajax_nonce); ?>;

        const courseSel = document.getElementById('sc_private_course_id');
        const chapterSel = document.getElementById('sc_private_chapter');
        const coachSel = document.getElementById('sc_private_coach_id');
        const slotsWrap = document.getElementById('sc_private_slots_wrap');
        const sessionsSel = document.getElementById('sc_private_sessions_count');
        const startDateEl = document.getElementById('sc_private_start_date_shamsi');
        const pricePreview = document.getElementById('sc_private_price_preview');
        let slotCheckTimer = null;

        function resetSelect(sel, placeholder, disabled) {
            sel.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            sel.appendChild(opt);
            sel.disabled = !!disabled;
        }

        function formatPrice(amount) {
            if (!amount || amount <= 0) {
                return '—';
            }
            try {
                return Number(amount).toLocaleString('fa-IR') + ' تومان';
            } catch (e) {
                return String(amount) + ' تومان';
            }
        }

        function slotMatchesSelection(slot, chapter, coachId) {
            if (!chapter || !coachId) {
                return false;
            }
            const rowChapter = String(slot.chapter || '').trim();
            const rowCoach = parseInt(slot.coach_id || '0', 10);

            if (rowChapter !== '' && rowChapter !== chapter) {
                return false;
            }
            if (rowCoach > 0 && rowCoach !== coachId) {
                return false;
            }
            // ردیف عمومی (همه شعبه/همه مربی) در انتخاب مشخص نشان داده نشود
            if (rowChapter === '' && rowCoach === 0) {
                return false;
            }
            return true;
        }

        function getSelectedSlotIds() {
            return Array.from(slotsWrap.querySelectorAll('input[name="schedule_slot_ids[]"]:checked:not(:disabled)')).map(function (el) {
                return parseInt(el.value, 10);
            }).filter(function (id) { return id > 0; });
        }

        function updatePricePreview() {
            const cid = parseInt(courseSel.value || '0', 10);
            const chapter = String(chapterSel.value || '');
            const coachId = parseInt(coachSel.value || '0', 10);
            const sessions = parseInt(sessionsSel.value || '0', 10);
            const info = cid && data[cid] ? data[cid] : null;
            if (!info || !chapter || !coachId || sessions <= 0) {
                pricePreview.textContent = '—';
                return;
            }
            let amount = 0;
            if (info.variable_coach_pricing) {
                (info.chapters || []).forEach(function (ch) {
                    if (ch.name !== chapter) {
                        return;
                    }
                    (ch.coaches || []).forEach(function (co) {
                        if (parseInt(co.id, 10) === coachId) {
                            amount = parseFloat(co.price_per_session || 0) * sessions;
                        }
                    });
                });
            } else if ((info.packages || []).length) {
                (info.packages || []).forEach(function (pkg) {
                    if (parseInt(pkg.sessions, 10) === sessions) {
                        amount = parseFloat(pkg.price || 0);
                    }
                });
            } else if (parseFloat(info.price_per_session || 0) > 0) {
                amount = parseFloat(info.price_per_session) * sessions;
            } else {
                amount = parseFloat(info.price || 0);
            }
            pricePreview.textContent = amount > 0 ? formatPrice(amount) : '—';
        }

        function renderSlots(chapter, coachId) {
            slotsWrap.innerHTML = '';
            const cid = parseInt(courseSel.value || '0', 10);
            const info = cid && data[cid] ? data[cid] : null;
            if (!info || !chapter || !coachId) {
                slotsWrap.innerHTML = '<p class="description">ابتدا شعبه و مربی را انتخاب کنید.</p>';
                return;
            }
            const rows = (info.slots || []).filter(function (slot) {
                return slotMatchesSelection(slot, chapter, coachId);
            });
            if (!rows.length) {
                slotsWrap.innerHTML = '<p class="description" style="color:#d63638;">برای این شعبه/مربی اسلات زمانی تعریف نشده است.</p>';
                return;
            }
            rows.forEach(function (slot) {
                const label = document.createElement('label');
                label.style.display = 'block';
                label.className = 'sc-private-slot-item';
                label.dataset.slotId = String(slot.id);
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'schedule_slot_ids[]';
                cb.value = String(slot.id);
                cb.addEventListener('change', scheduleSlotAvailabilityCheck);
                label.appendChild(cb);
                const text = document.createElement('span');
                text.className = 'sc-private-slot-label';
                text.textContent = ' ' + slot.label;
                label.appendChild(text);
                const status = document.createElement('span');
                status.className = 'sc-private-slot-status';
                label.appendChild(status);
                slotsWrap.appendChild(label);
            });
            scheduleSlotAvailabilityCheck();
        }

        function scheduleSlotAvailabilityCheck() {
            clearTimeout(slotCheckTimer);
            slotCheckTimer = setTimeout(runSlotAvailabilityCheck, 350);
        }

        function runSlotAvailabilityCheck() {
            const cid = parseInt(courseSel.value || '0', 10);
            const chapter = String(chapterSel.value || '');
            const coachId = parseInt(coachSel.value || '0', 10);
            const sessions = parseInt(sessionsSel.value || '0', 10);
            const slotIds = getSelectedSlotIds();
            const allSlotInputs = slotsWrap.querySelectorAll('input[name="schedule_slot_ids[]"]');

            if (!cid || !chapter || !coachId || sessions <= 0) {
                allSlotInputs.forEach(function (cb) {
                    cb.disabled = false;
                    const statusEl = cb.closest('.sc-private-slot-item')?.querySelector('.sc-private-slot-status');
                    if (statusEl) {
                        statusEl.textContent = '';
                    }
                });
                return;
            }

            const body = new URLSearchParams();
            body.append('action', 'sc_private_check_slots');
            body.append('nonce', ajaxNonce);
            body.append('course_id', String(cid));
            body.append('chapter', chapter);
            body.append('coach_id', String(coachId));
            body.append('enrollment_sessions', String(sessions));
            body.append('start_date_shamsi', String(startDateEl.value || ''));
            allSlotInputs.forEach(function (cb) {
                body.append('schedule_slot_ids[]', String(cb.value));
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (res) { return res.json(); }).then(function (json) {
                if (!json || !json.success || !json.data || !json.data.slots) {
                    return;
                }
                allSlotInputs.forEach(function (cb) {
                    const slotId = parseInt(cb.value, 10);
                    const slotInfo = json.data.slots[slotId] || json.data.slots[String(slotId)];
                    const statusEl = cb.closest('.sc-private-slot-item')?.querySelector('.sc-private-slot-status');
                    if (!slotInfo) {
                        cb.disabled = false;
                        if (statusEl) {
                            statusEl.textContent = '';
                        }
                        return;
                    }
                    if (slotInfo.full) {
                        cb.checked = false;
                        cb.disabled = true;
                        if (statusEl) {
                            statusEl.textContent = ' (تکمیل ظرفیت)';
                            statusEl.style.color = '#d63638';
                        }
                    } else {
                        cb.disabled = false;
                        if (statusEl) {
                            statusEl.textContent = '';
                        }
                    }
                });
            }).catch(function () {});
        }

        function onCourseChange() {
            const cid = parseInt(courseSel.value || '0', 10);
            const info = cid && data[cid] ? data[cid] : null;

            resetSelect(chapterSel, info ? 'شعبه را انتخاب کنید' : 'ابتدا دوره را انتخاب کنید', !info);
            resetSelect(coachSel, 'ابتدا شعبه را انتخاب کنید', true);
            resetSelect(sessionsSel, info ? 'تعداد جلسات' : 'ابتدا دوره را انتخاب کنید', !info);
            slotsWrap.innerHTML = '<p class="description">ابتدا دوره، شعبه و مربی را انتخاب کنید.</p>';
            pricePreview.textContent = '—';

            if (!info) {
                return;
            }

            (info.chapters || []).forEach(function (ch) {
                const opt = document.createElement('option');
                opt.value = ch.name;
                opt.textContent = ch.name;
                chapterSel.appendChild(opt);
            });
            if ((info.chapters || []).length === 1) {
                chapterSel.value = info.chapters[0].name;
                onChapterChange();
            }

            (info.session_options || []).forEach(function (n) {
                const opt = document.createElement('option');
                opt.value = String(n);
                opt.textContent = String(n) + ' جلسه';
                sessionsSel.appendChild(opt);
            });
            if ((info.session_options || []).length === 1) {
                sessionsSel.value = String(info.session_options[0]);
            }
            updatePricePreview();
        }

        function onChapterChange() {
            const cid = parseInt(courseSel.value || '0', 10);
            const chapter = String(chapterSel.value || '');
            const info = cid && data[cid] ? data[cid] : null;
            resetSelect(coachSel, chapter ? 'مربی را انتخاب کنید' : 'ابتدا شعبه را انتخاب کنید', !chapter);
            slotsWrap.innerHTML = '<p class="description">ابتدا مربی را انتخاب کنید.</p>';
            if (!info || !chapter) {
                updatePricePreview();
                return;
            }
            let coaches = [];
            (info.chapters || []).forEach(function (ch) {
                if (ch.name === chapter) {
                    coaches = ch.coaches || [];
                }
            });
            coaches.forEach(function (co) {
                const opt = document.createElement('option');
                opt.value = String(co.id);
                opt.textContent = co.name;
                coachSel.appendChild(opt);
            });
            coachSel.disabled = false;
            if (coaches.length === 1) {
                coachSel.value = String(coaches[0].id);
                onCoachChange();
            } else {
                updatePricePreview();
            }
        }

        function onCoachChange() {
            const chapter = String(chapterSel.value || '');
            const coachId = parseInt(coachSel.value || '0', 10);
            renderSlots(chapter, coachId);
            updatePricePreview();
        }

        courseSel.addEventListener('change', onCourseChange);
        chapterSel.addEventListener('change', onChapterChange);
        coachSel.addEventListener('change', onCoachChange);
        sessionsSel.addEventListener('change', function () {
            updatePricePreview();
            scheduleSlotAvailabilityCheck();
        });
        if (startDateEl) {
            startDateEl.addEventListener('change', scheduleSlotAvailabilityCheck);
            startDateEl.addEventListener('blur', scheduleSlotAvailabilityCheck);
        }
    });
    </script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-private-wrap h2');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);
});
</script>
