<?php
if (!defined('ABSPATH')) {
    exit;
}
$is_coach = !empty($GLOBALS['sc_programs_is_coach']);
$base_page = $is_coach ? 'sc-coach-program-templates' : 'sc-program-templates';
$list_page = $is_coach ? 'sc-coach-programs' : 'sc-programs';
$edit_tpl_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : -1;
$is_new = isset($_GET['new']) && (string) $_GET['new'] === '1';
$is_edit_mode = $is_new || $edit_tpl_id > 0;
$message = '';
$error = '';

if (isset($_POST['sc_save_template']) && check_admin_referer('sc_save_program_template', 'sc_template_nonce')) {
    $id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    $mask = isset($_POST['weekly_mask']) ? array_map('intval', (array) $_POST['weekly_mask']) : [];
    $result = sc_program_template_save([
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'schedule_mode' => $_POST['schedule_mode'] ?? 'fixed',
        'duration_days' => $_POST['duration_days'] ?? 7,
        'weekly_mask' => $mask,
        'status' => $_POST['status'] ?? 'active',
    ], $id);
    if (is_wp_error($result)) {
        $error = $result->get_error_message();
        $edit_tpl_id = $id;
        $is_edit_mode = true;
    } else {
        $edit_tpl_id = (int) $result;
        $is_edit_mode = true;
        $mode = sc_program_normalize_schedule_mode($_POST['schedule_mode'] ?? 'fixed');
        $existing = sc_program_template_get_days($edit_tpl_id);
        if (empty($existing)) {
            if ($mode === 'fixed') {
                $duration = max(1, (int) ($_POST['duration_days'] ?? 7));
                for ($i = 0; $i < $duration; $i++) {
                    sc_program_template_ensure_day($edit_tpl_id, $i);
                }
            } elseif ($mode === 'weekly') {
                $slots = max(1, count($mask) ?: 6);
                for ($i = 0; $i < $slots; $i++) {
                    sc_program_template_ensure_day($edit_tpl_id, $i, 'روز ' . ($i + 1));
                }
            } else {
                sc_program_template_ensure_day($edit_tpl_id, 0, 'روز ۱');
            }
        }
        $message = 'مشخصات قالب ذخیره شد. تمرین‌های روزها را تنظیم و در انتها ذخیره کنید.';
        wp_safe_redirect(admin_url('admin.php?page=' . $base_page . '&template_id=' . $edit_tpl_id . '&saved=1'));
        exit;
    }
}

if (isset($_GET['delete_template']) && check_admin_referer('sc_delete_template_' . absint($_GET['delete_template']))) {
    sc_program_template_delete(absint($_GET['delete_template']));
    wp_safe_redirect(admin_url('admin.php?page=' . $base_page . '&deleted=1'));
    exit;
}

if (isset($_GET['saved'])) {
    $message = 'مشخصات قالب ذخیره شد.';
}
if (isset($_GET['deleted'])) {
    $message = 'قالب حذف شد.';
}

$template = ($edit_tpl_id > 0) ? sc_program_template_get($edit_tpl_id) : null;
$templates = sc_program_template_query(['per_page' => 100]);
$library = sc_program_library_query(['per_page' => 200]);
$week_labels = sc_program_weekday_labels_ir();
$current_mask = $template ? sc_program_normalize_weekly_mask($template->weekly_mask) : [1, 2, 3, 4, 5, 6];
$days_js = ($template) ? sc_program_template_export_structure_for_js((int) $template->id) : [];
$mode_labels = ['fixed' => 'بازه ثابت', 'manual' => 'دستی', 'weekly' => 'هفتگی'];
?>
<div class="wrap sc-members-list-wrap sc-programs-wrap sc-prog-pro" data-sc-program-target="template">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title"><?php echo $is_edit_mode ? ($template ? 'ویرایش قالب' : 'قالب جدید') : 'قالب‌های برنامه تخصصی'; ?></h1>
            <p class="sc-members-list-desc"><?php echo $is_edit_mode ? 'تعریف مشخصات، سپس چیدمان تمرین‌ها روی روزها — بدون رفرش، ذخیره در انتها' : 'مجموعه قالب‌های آماده برای اختصاص به بازیکنان'; ?></p>
        </div>
        <div class="sc-members-list-header-actions">
            <?php if ($is_edit_mode) : ?>
                <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $base_page)); ?>">لیست قالب‌ها</a>
            <?php else : ?>
                <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page)); ?>">برنامه‌های اعضا</a>
                <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $base_page . '&new=1')); ?>">قالب جدید</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
    <?php if ($error) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

    <?php if (!$is_edit_mode) : ?>
        <div class="sc-prog-card-grid">
            <?php if (empty($templates['rows'])) : ?>
                <div class="sc-prog-empty-hero">
                    <h3>هنوز قالبی ندارید</h3>
                    <p>اولین قالب چندروزه را بسازید و تمرین‌ها را روی روزها بچینید.</p>
                    <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $base_page . '&new=1')); ?>">ساخت قالب</a>
                </div>
            <?php else : ?>
                <?php foreach ($templates['rows'] as $tpl) :
                    $day_count = count(sc_program_template_get_days((int) $tpl->id));
                    ?>
                    <article class="sc-prog-entity-card">
                        <div class="sc-prog-entity-card__accent"></div>
                        <div class="sc-prog-entity-card__body">
                            <span class="sc-prog-pill"><?php echo esc_html($mode_labels[$tpl->schedule_mode] ?? $tpl->schedule_mode); ?></span>
                            <h3 class="sc-prog-entity-card__title"><?php echo esc_html($tpl->title); ?></h3>
                            <p class="sc-prog-entity-card__meta"><?php echo esc_html($day_count . ' روز نسبی'); ?> · <?php echo $tpl->status === 'active' ? 'فعال' : 'غیرفعال'; ?></p>
                            <?php if (!empty($tpl->description)) : ?>
                                <p class="sc-prog-entity-card__desc"><?php echo esc_html(wp_html_excerpt($tpl->description, 90, '…')); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="sc-prog-entity-card__foot">
                            <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $base_page . '&template_id=' . (int) $tpl->id)); ?>">ویرایش</a>
                            <a class="sc_button sc_button--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=' . $base_page . '&delete_template=' . (int) $tpl->id), 'sc_delete_template_' . (int) $tpl->id)); ?>" onclick="return confirm('حذف این قالب؟');">حذف</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <section class="sc-prog-section sc-prog-section--meta">
            <div class="sc-prog-section__head">
                <h2>۱) مشخصات قالب</h2>
                <p>عنوان، حالت زمان‌بندی و روزهای هفته</p>
            </div>
            <form method="post" class="sc-prog-form-card" id="sc-program-template-form">
                <?php wp_nonce_field('sc_save_program_template', 'sc_template_nonce'); ?>
                <input type="hidden" name="template_id" value="<?php echo (int) ($template->id ?? 0); ?>">
                <div class="sc-prog-form-grid">
                    <div class="sc-prog-field sc-prog-field--full">
                        <label for="sc-tpl-title">عنوان قالب</label>
                        <input type="text" id="sc-tpl-title" name="title" required value="<?php echo esc_attr($template->title ?? ''); ?>" placeholder="مثلاً برنامه آماده‌سازی هفته اول">
                    </div>
                    <div class="sc-prog-field sc-prog-field--full">
                        <label for="sc-tpl-desc">توضیح کوتاه</label>
                        <textarea id="sc-tpl-desc" name="description" rows="2" placeholder="اختیاری"><?php echo esc_textarea($template->description ?? ''); ?></textarea>
                    </div>
                    <div class="sc-prog-field">
                        <label for="sc-schedule-mode">حالت زمان‌بندی</label>
                        <select name="schedule_mode" id="sc-schedule-mode">
                            <?php $sm = $template->schedule_mode ?? 'fixed'; ?>
                            <option value="fixed" <?php selected($sm, 'fixed'); ?>>بازه ثابت (تعداد روز)</option>
                            <option value="manual" <?php selected($sm, 'manual'); ?>>افزودن دستی روزانه</option>
                            <option value="weekly" <?php selected($sm, 'weekly'); ?>>تکرار هفتگی</option>
                        </select>
                    </div>
                    <div class="sc-prog-field">
                        <label for="sc-tpl-status">وضعیت</label>
                        <select name="status" id="sc-tpl-status">
                            <option value="active" <?php selected(($template->status ?? 'active'), 'active'); ?>>فعال</option>
                            <option value="inactive" <?php selected(($template->status ?? ''), 'inactive'); ?>>غیرفعال</option>
                        </select>
                    </div>
                    <div class="sc-prog-field sc-schedule-field sc-schedule-fixed">
                        <label for="sc-duration-days">تعداد روزها</label>
                        <input type="number" id="sc-duration-days" name="duration_days" min="1" max="366" value="<?php echo (int) ($template->duration_days ?? 7); ?>">
                    </div>
                    <div class="sc-prog-field sc-prog-field--full sc-schedule-field sc-schedule-weekly" style="display:none;">
                        <label>روزهای هفته</label>
                        <div class="sc-prog-week-pills">
                            <?php foreach ($week_labels as $num => $lab) : ?>
                                <label class="sc-prog-week-pill">
                                    <input type="checkbox" name="weekly_mask[]" value="<?php echo (int) $num; ?>" <?php checked(in_array((int) $num, $current_mask, true)); ?>>
                                    <span><?php echo esc_html($lab); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="sc-prog-hint">از شنبه شروع می‌شود. هنگام اختصاص روی تاریخ واقعی نگاشت می‌شود.</p>
                    </div>
                    <div class="sc-prog-field sc-prog-field--full sc-schedule-field sc-schedule-manual" style="display:none;">
                        <p class="sc-prog-hint">بعد از ذخیره مشخصات، در بخش روزها می‌توانید روز نسبی اضافه کنید.</p>
                    </div>
                </div>
                <div class="sc-prog-form-actions">
                    <button type="submit" name="sc_save_template" class="sc_button sc_button--primary">ذخیره مشخصات قالب</button>
                </div>
            </form>
        </section>

        <?php if ($template) : ?>
            <section class="sc-prog-section sc-prog-section--days" id="sc-prog-days-workspace">
                <div class="sc-prog-section__head sc-prog-section__head--row">
                    <div>
                        <h2>۲) چیدمان روزها و تمرین‌ها</h2>
                        <p>از کتابخانه بکشید روی روز، یا روی تمرین کلیک کنید و روز را انتخاب کنید — در انتها ذخیره کنید</p>
                    </div>
                    <div class="sc-prog-days-toolbar">
                        <button type="button" class="sc_button" id="sc-prog-add-day-btn">+ روز جدید</button>
                        <button type="button" class="sc_button sc_button--primary" id="sc-prog-save-days-btn">ذخیره روزها</button>
                        <span class="sc-prog-save-status" id="sc-prog-save-status" hidden></span>
                    </div>
                </div>

                <div class="sc-prog-days-workspace">
                    <aside class="sc-prog-lib-panel">
                        <div class="sc-prog-lib-panel__head">
                            <h3>کتابخانه تمرین</h3>
                            <input type="search" id="sc-program-library-search" class="sc-prog-input" placeholder="جستجو...">
                        </div>
                        <ul class="sc-prog-lib-list" id="sc-prog-lib-list">
                            <?php foreach ($library['rows'] as $lib) : ?>
                                <li class="sc-prog-lib-item"
                                    draggable="true"
                                    data-library-id="<?php echo (int) $lib->id; ?>"
                                    data-title="<?php echo esc_attr($lib->title); ?>"
                                    data-media="<?php echo esc_attr($lib->media_type); ?>">
                                    <span class="sc-prog-lib-item__title"><?php echo esc_html($lib->title); ?></span>
                                    <span class="sc-prog-pill sc-prog-pill--ghost"><?php echo esc_html($lib->media_type); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="sc-prog-hint">کلیک روی تمرین → انتخاب روز مقصد</p>
                    </aside>

                    <div class="sc-prog-days-board" id="sc-prog-days-board" data-template-id="<?php echo (int) $template->id; ?>">
                        <!-- rendered by JS -->
                    </div>
                </div>
            </section>

            <div class="sc-prog-modal" id="sc-prog-day-picker" hidden>
                <div class="sc-prog-modal__backdrop" data-close-modal></div>
                <div class="sc-prog-modal__card">
                    <h3>انتخاب روز مقصد</h3>
                    <p id="sc-prog-day-picker-label" class="sc-prog-hint"></p>
                    <div class="sc-prog-day-picker-list" id="sc-prog-day-picker-list"></div>
                    <button type="button" class="sc_button" data-close-modal>انصراف</button>
                </div>
            </div>

            <script type="application/json" id="sc-prog-days-boot"><?php echo wp_json_encode($days_js); ?></script>
        <?php else : ?>
            <div class="sc-prog-empty-hero sc-prog-empty-hero--soft">
                <p>ابتدا مشخصات قالب را ذخیره کنید تا بخش روزها فعال شود.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
