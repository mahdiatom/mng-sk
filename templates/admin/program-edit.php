<?php
if (!defined('ABSPATH')) {
    exit;
}
$is_coach = !empty($GLOBALS['sc_programs_is_coach']);
$edit_page = $is_coach ? 'sc-coach-program-edit' : 'sc-program-edit';
$list_page = $is_coach ? 'sc-coach-programs' : 'sc-programs';
$assign_page = $is_coach ? 'sc-coach-programs-assign' : 'sc-programs-assign';
$program_id = isset($_GET['program_id']) ? absint($_GET['program_id']) : 0;
$program = $program_id ? sc_program_get($program_id) : null;
$message = '';
$error = '';

if (!$program || !sc_program_can_access_member((int) $program->member_id)) {
    echo '<div class="wrap sc-prog-pro"><div class="notice notice-error"><p>برنامه یافت نشد یا دسترسی ندارید.</p></div>';
    echo '<p><a class="sc_button" href="' . esc_url(admin_url('admin.php?page=' . $list_page)) . '">بازگشت</a></p></div>';
    return;
}

if (isset($_POST['sc_sync_template']) && check_admin_referer('sc_program_edit_' . $program_id, 'sc_program_edit_nonce')) {
    $sync = sc_program_sync_from_template($program_id);
    if (is_wp_error($sync)) {
        $error = $sync->get_error_message();
    } else {
        $message = 'همگام‌سازی از قالب انجام شد (تیک‌های منطبق حفظ شدند).';
        $program = sc_program_get($program_id);
    }
}

if (isset($_POST['sc_save_program_meta']) && check_admin_referer('sc_program_edit_' . $program_id, 'sc_program_edit_nonce')) {
    global $wpdb;
    $wpdb->update(sc_member_programs_table(), [
        'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
        'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
        'status' => in_array(($_POST['status'] ?? ''), ['active', 'inactive'], true) ? $_POST['status'] : 'active',
        'updated_at' => current_time('mysql'),
    ], ['id' => $program_id]);
    $message = 'اطلاعات برنامه ذخیره شد.';
    $program = sc_program_get($program_id);
}

if (isset($_POST['sc_add_member_day']) && check_admin_referer('sc_program_edit_' . $program_id, 'sc_program_edit_nonce')) {
    $date_shamsi = sanitize_text_field(wp_unslash($_POST['new_day_shamsi'] ?? ''));
    $date = ($date_shamsi && function_exists('sc_shamsi_to_gregorian_date'))
        ? sc_shamsi_to_gregorian_date($date_shamsi)
        : sanitize_text_field(wp_unslash($_POST['new_day_date'] ?? ''));
    $res = sc_program_add_member_day($program_id, $date, sanitize_text_field(wp_unslash($_POST['new_day_title'] ?? '')));
    if (is_wp_error($res)) {
        $error = $res->get_error_message();
    } else {
        $message = 'روز اضافه شد.';
        sc_program_notify_member((int) $program->member_id, (string) $program->title, 'updated');
    }
}

if (isset($_POST['sc_save_day_item']) && check_admin_referer('sc_program_edit_' . $program_id, 'sc_program_edit_nonce')) {
    $item_id = absint($_POST['item_id'] ?? 0);
    $res = sc_program_update_member_day_item($item_id, [
        'title' => $_POST['item_title'] ?? '',
        'description' => $_POST['item_description'] ?? '',
        'media_type' => $_POST['item_media_type'] ?? 'none',
        'media_url' => $_POST['item_media_url'] ?? '',
        'file_path' => $_POST['item_file_path'] ?? '',
        'file_name' => $_POST['item_file_name'] ?? '',
    ]);
    if (is_wp_error($res)) {
        $error = $res->get_error_message();
    } else {
        $message = 'آیتم شخصی‌سازی شد.';
        wp_safe_redirect(admin_url('admin.php?page=' . $edit_page . '&program_id=' . $program_id . '&saved_item=1'));
        exit;
    }
}

if (isset($_GET['delete_item']) && check_admin_referer('sc_del_mitem_' . absint($_GET['delete_item']))) {
    sc_program_delete_member_day_item(absint($_GET['delete_item']));
    wp_safe_redirect(admin_url('admin.php?page=' . $edit_page . '&program_id=' . $program_id . '&deleted=1'));
    exit;
}

if (isset($_GET['saved_item'])) {
    $message = 'آیتم شخصی‌سازی شد.';
}
if (isset($_GET['deleted'])) {
    $message = 'آیتم حذف شد.';
}

$days = sc_program_get_days($program_id);
$progress = sc_program_progress($program_id, true);
$library = sc_program_library_query(['per_page' => 100]);
$completed_ids = sc_program_get_completed_item_ids($program_id);
$edit_item_id = isset($_GET['edit_item']) ? absint($_GET['edit_item']) : 0;
$edit_item = $edit_item_id ? sc_program_get_day_item($edit_item_id) : null;
$member_name = '';
global $wpdb;
$m = $wpdb->get_row($wpdb->prepare('SELECT first_name, last_name, national_id FROM ' . $wpdb->prefix . 'sc_members WHERE id = %d', (int) $program->member_id));
if ($m) {
    $member_name = trim(($m->first_name ?: '') . ' ' . ($m->last_name ?: ''));
    if (!empty($m->national_id)) {
        $member_name .= ' — ' . $m->national_id;
    }
}
$today = sc_program_today_ymd();
$start_s = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($program->start_date) : $program->start_date;
$end_s = !empty($program->end_date) && function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($program->end_date) : ($program->end_date ?: '—');
$today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today) : $today;
$mode_labels = ['fixed' => 'بازه ثابت', 'manual' => 'دستی', 'weekly' => 'هفتگی'];
?>
<div class="wrap sc-members-list-wrap sc-programs-wrap sc-prog-pro sc-prog-edit-page"
     data-sc-program-target="member"
     data-program-id="<?php echo (int) $program_id; ?>">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">ویرایش برنامه تخصصی</h1>
            <p class="sc-members-list-desc">همان الگوی اختصاص برنامه — مشخصات را ویرایش کنید و تیک تمرین‌ها را بدون رفرش ثبت کنید</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page . '&filter_member=' . (int) $program->member_id)); ?>">لیست برنامه‌ها</a>
            <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $assign_page)); ?>">اختصاص جدید</a>
        </div>
    </div>

    <?php if ($message) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
    <?php if ($error) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

    <div class="sc-prog-progress-hero" id="sc-prog-progress-hero">
        <div class="sc-prog-progress-hero__meta">
            <strong id="sc-prog-progress-percent"><?php echo (int) $progress['percent']; ?>٪</strong>
            <span>پیشرفت تا امروز</span>
        </div>
        <div class="sc-program-progress sc-program-progress--lg" style="flex:1;">
            <div class="sc-program-progress-bar">
                <span id="sc-prog-progress-bar" style="width:<?php echo (int) $progress['percent']; ?>%"></span>
            </div>
            <small id="sc-prog-progress-label"><?php echo esc_html($progress['done'] . ' از ' . $progress['total'] . ' آیتم تا امروز'); ?></small>
        </div>
        <span class="sc-prog-save-status" id="sc-prog-toggle-status" hidden></span>
    </div>

    <section class="sc-prog-section">
        <div class="sc-prog-section__head">
            <h2>۱) مشخصات برنامه</h2>
            <p>بازیکن، عنوان و بازه تاریخ — مشابه فرم اختصاص</p>
        </div>
        <form method="post" class="sc-prog-form-card sc-prog-assign-form">
            <?php wp_nonce_field('sc_program_edit_' . $program_id, 'sc_program_edit_nonce'); ?>
            <div class="sc-prog-form-grid">
                <div class="sc-prog-field sc-prog-field--full">
                    <label>بازیکن</label>
                    <input type="text" class="sc-prog-input" value="<?php echo esc_attr($member_name ?: ('#' . $program->member_id)); ?>" readonly>
                </div>
                <div class="sc-prog-field sc-prog-field--full">
                    <label for="sc-edit-title">عنوان برنامه</label>
                    <input type="text" id="sc-edit-title" name="title" required value="<?php echo esc_attr($program->title); ?>">
                </div>
                <div class="sc-prog-field sc-prog-field--full">
                    <label for="sc-edit-desc">توضیح</label>
                    <textarea id="sc-edit-desc" name="description" rows="2"><?php echo esc_textarea($program->description); ?></textarea>
                </div>
                <div class="sc-prog-field">
                    <label>تاریخ شروع (شمسی)</label>
                    <input type="text" class="sc-prog-input" value="<?php echo esc_attr($start_s); ?>" readonly>
                </div>
                <div class="sc-prog-field">
                    <label>تاریخ پایان (شمسی)</label>
                    <input type="text" class="sc-prog-input" value="<?php echo esc_attr($end_s); ?>" readonly>
                </div>
                <div class="sc-prog-field">
                    <label>حالت زمان‌بندی</label>
                    <input type="text" class="sc-prog-input" value="<?php echo esc_attr($mode_labels[$program->schedule_mode] ?? $program->schedule_mode); ?>" readonly>
                </div>
                <div class="sc-prog-field">
                    <label for="sc-edit-status">وضعیت</label>
                    <select name="status" id="sc-edit-status">
                        <option value="active" <?php selected($program->status, 'active'); ?>>فعال</option>
                        <option value="inactive" <?php selected($program->status, 'inactive'); ?>>غیرفعال</option>
                    </select>
                </div>
            </div>
            <div class="sc-prog-form-actions">
                <button type="submit" name="sc_save_program_meta" class="sc_button sc_button--primary">ذخیره مشخصات</button>
                <?php if ((int) $program->template_id > 0) : ?>
                    <button type="submit" name="sc_sync_template" value="1" class="sc_button"
                            onclick="return confirm('ساختار از قالب بازنویسی می‌شود. تیک‌های منطبق حفظ می‌شوند. ادامه؟');">همگام‌سازی از قالب</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="sc-prog-section">
        <div class="sc-prog-section__head">
            <h2>۲) افزودن روز</h2>
            <p>تاریخ شمسی و عنوان روز جدید</p>
        </div>
        <form method="post" class="sc-prog-form-card">
            <?php wp_nonce_field('sc_program_edit_' . $program_id, 'sc_program_edit_nonce'); ?>
            <div class="sc-prog-form-grid">
                <div class="sc-prog-field">
                    <label>تاریخ (شمسی)</label>
                    <input type="text" name="new_day_shamsi" class="persian-date-input sc-prog-input" readonly value="<?php echo esc_attr($today_shamsi); ?>" placeholder="انتخاب تاریخ">
                    <input type="hidden" name="new_day_date" value="<?php echo esc_attr($today); ?>">
                </div>
                <div class="sc-prog-field">
                    <label>عنوان روز</label>
                    <input type="text" name="new_day_title" placeholder="مثلاً جلسه قدرتی">
                </div>
            </div>
            <div class="sc-prog-form-actions">
                <button type="submit" name="sc_add_member_day" value="1" class="sc_button sc_button--primary">افزودن روز</button>
            </div>
        </form>
    </section>

    <?php if ($edit_item) : ?>
        <section class="sc-prog-section">
            <div class="sc-prog-section__head">
                <h2>شخصی‌سازی تمرین</h2>
            </div>
            <form method="post" class="sc-prog-form-card" id="sc-program-item-edit-form">
                <?php wp_nonce_field('sc_program_edit_' . $program_id, 'sc_program_edit_nonce'); ?>
                <input type="hidden" name="item_id" value="<?php echo (int) $edit_item->id; ?>">
                <input type="hidden" name="item_file_path" value="<?php echo esc_attr($edit_item->file_path); ?>">
                <input type="hidden" name="item_file_name" value="<?php echo esc_attr($edit_item->file_name); ?>">
                <div class="sc-prog-form-grid">
                    <div class="sc-prog-field sc-prog-field--full">
                        <label>عنوان</label>
                        <input type="text" name="item_title" value="<?php echo esc_attr($edit_item->title); ?>">
                    </div>
                    <div class="sc-prog-field sc-prog-field--full">
                        <label>توضیح</label>
                        <textarea name="item_description" rows="3"><?php echo esc_textarea($edit_item->description); ?></textarea>
                    </div>
                    <div class="sc-prog-field">
                        <label>رسانه</label>
                        <select name="item_media_type">
                            <?php foreach (['none' => 'بدون', 'aparat' => 'آپارات', 'url' => 'لینک', 'file' => 'فایل'] as $k => $lab) : ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($edit_item->media_type, $k); ?>><?php echo esc_html($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-prog-field">
                        <label>لینک</label>
                        <input type="url" name="item_media_url" value="<?php echo esc_attr($edit_item->media_url); ?>">
                    </div>
                </div>
                <div class="sc-prog-form-actions">
                    <button type="submit" name="sc_save_day_item" class="sc_button sc_button--primary">ذخیره تمرین</button>
                    <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $edit_page . '&program_id=' . $program_id)); ?>">انصراف</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="sc-prog-section sc-prog-section--days" id="sc-prog-member-days">
        <div class="sc-prog-section__head sc-prog-section__head--row">
            <div>
                <h2>۳) چیدمان روزها و تمرین‌ها</h2>
                <p>تیک را بزنید یا بردارید — نوار پیشرفت بلافاصله به‌روز می‌شود. تمرین را از کتابخانه بکشید روی روز.</p>
            </div>
        </div>

        <div class="sc-prog-days-workspace">
            <aside class="sc-prog-lib-panel">
                <div class="sc-prog-lib-panel__head">
                    <h3>کتابخانه تمرین</h3>
                    <input type="search" id="sc-program-library-search" class="sc-prog-input" placeholder="جستجو...">
                </div>
                <ul class="sc-prog-lib-list sc-dnd-library-list">
                    <?php foreach ($library['rows'] as $lib) : ?>
                        <li class="sc-prog-lib-item sc-dnd-library-item"
                            draggable="true"
                            data-library-id="<?php echo (int) $lib->id; ?>"
                            data-title="<?php echo esc_attr($lib->title); ?>">
                            <span class="sc-prog-lib-item__title"><?php echo esc_html($lib->title); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="sc-prog-hint">بکشید و روی روز رها کنید</p>
            </aside>

            <div class="sc-prog-days-board">
                <?php foreach ($days as $idx => $day) :
                    $items = sc_program_get_day_items((int) $day->id);
                    $is_today = ((string) $day->program_date === $today);
                    $shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($day->program_date) : $day->program_date;
                    ?>
                    <div class="sc-prog-day-col sc-program-day-drop<?php echo $is_today ? ' is-today' : ''; ?>" data-day-id="<?php echo (int) $day->id; ?>">
                        <div class="sc-prog-day-col__head">
                            <span class="sc-prog-day-col__badge"><?php echo (int) ($idx + 1); ?></span>
                            <div style="flex:1;min-width:0;">
                                <h4 style="margin:0;"><?php echo esc_html($day->title ?: 'روز'); ?></h4>
                                <small class="sc-prog-hint"><?php echo esc_html($shamsi); ?><?php echo $is_today ? ' · امروز' : ''; ?></small>
                            </div>
                            <?php if ($is_today) : ?><span class="sc-prog-badge">امروز</span><?php endif; ?>
                        </div>
                        <ul class="sc-prog-day-items sc-dnd-day-items" data-day-id="<?php echo (int) $day->id; ?>">
                            <?php if (empty($items)) : ?>
                                <li class="sc-prog-day-empty">رها کردن تمرین اینجا</li>
                            <?php else : ?>
                                <?php foreach ($items as $it) :
                                    $done = in_array((int) $it->id, $completed_ids, true);
                                    ?>
                                    <li class="sc-prog-day-item<?php echo $done ? ' is-done' : ''; ?>" data-item-id="<?php echo (int) $it->id; ?>">
                                        <label class="sc-prog-tick">
                                            <input type="checkbox"
                                                   class="sc-prog-tick-input"
                                                   data-item-id="<?php echo (int) $it->id; ?>"
                                                   <?php checked($done); ?>>
                                            <span class="sc-prog-tick__box" aria-hidden="true"></span>
                                        </label>
                                        <span class="sc-prog-day-item__title"><?php echo esc_html($it->title); ?></span>
                                        <span class="sc-day-item-actions">
                                            <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $edit_page . '&program_id=' . $program_id . '&edit_item=' . (int) $it->id)); ?>">ویرایش</a>
                                            <a class="sc_button sc_button--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=' . $edit_page . '&program_id=' . $program_id . '&delete_item=' . (int) $it->id), 'sc_del_mitem_' . (int) $it->id)); ?>" onclick="return confirm('حذف؟');">×</a>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($days)) : ?>
                    <div class="sc-prog-empty-hero sc-prog-empty-hero--soft"><p>روزی ثبت نشده. از بخش ۲ یک روز اضافه کنید.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
