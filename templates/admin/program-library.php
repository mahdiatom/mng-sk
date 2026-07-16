<?php
if (!defined('ABSPATH')) {
    exit;
}
$is_coach = !empty($GLOBALS['sc_programs_is_coach']);
$base_page = $is_coach ? 'sc-coach-program-library' : 'sc-program-library';
$list_page = $is_coach ? 'sc-coach-programs' : 'sc-programs';
$templates_page = $is_coach ? 'sc-coach-program-templates' : 'sc-program-templates';

$message = '';
$error = '';

if (isset($_POST['sc_save_library_item']) && check_admin_referer('sc_save_library_item', 'sc_library_nonce')) {
    $id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
    $result = sc_program_library_save([
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'media_type' => $_POST['media_type'] ?? 'none',
        'media_url' => $_POST['media_url'] ?? '',
        'file_path' => $_POST['file_path'] ?? '',
        'file_name' => $_POST['file_name'] ?? '',
    ], $id);
    if (is_wp_error($result)) {
        $error = $result->get_error_message();
    } else {
        $message = $id ? 'آیتم به‌روز شد.' : 'آیتم به کتابخانه اضافه شد.';
        if (!$id) {
            wp_safe_redirect(admin_url('admin.php?page=' . $base_page . '&saved=1'));
            exit;
        }
    }
}

if (isset($_GET['delete']) && check_admin_referer('sc_delete_library_' . absint($_GET['delete']))) {
    sc_program_library_delete(absint($_GET['delete']));
    wp_safe_redirect(admin_url('admin.php?page=' . $base_page . '&deleted=1'));
    exit;
}
if (isset($_GET['saved'])) {
    $message = 'آیتم ذخیره شد.';
}
if (isset($_GET['deleted'])) {
    $message = 'آیتم حذف شد.';
}

$edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$edit_item = $edit_id ? sc_program_library_get($edit_id) : null;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$result = sc_program_library_query(['search' => $search, 'per_page' => 100]);
$media_labels = ['none' => 'بدون رسانه', 'aparat' => 'آپارات', 'url' => 'لینک', 'file' => 'فایل'];
?>
<div class="wrap sc-members-list-wrap sc-programs-wrap sc-prog-pro">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">کتابخانه تمرین</h1>
            <p class="sc-members-list-desc">تمرین‌های قابل استفاده مجدد با عنوان، توضیح و رسانه</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $templates_page)); ?>">قالب‌ها</a>
            <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page)); ?>">برنامه‌ها</a>
        </div>
    </div>

    <?php if ($message) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
    <?php if ($error) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

    <div class="sc-prog-lib-layout">
        <section class="sc-prog-section">
            <div class="sc-prog-section__head">
                <h2><?php echo $edit_item ? 'ویرایش تمرین' : 'تمرین جدید'; ?></h2>
            </div>
            <form method="post" id="sc-program-library-form" class="sc-prog-form-card">
                <?php wp_nonce_field('sc_save_library_item', 'sc_library_nonce'); ?>
                <input type="hidden" name="item_id" value="<?php echo (int) $edit_id; ?>">
                <input type="hidden" name="file_path" id="sc-lib-file-path" value="<?php echo esc_attr($edit_item->file_path ?? ''); ?>">
                <input type="hidden" name="file_name" id="sc-lib-file-name" value="<?php echo esc_attr($edit_item->file_name ?? ''); ?>">
                <div class="sc-prog-form-grid">
                    <div class="sc-prog-field sc-prog-field--full">
                        <label for="sc-lib-title">عنوان</label>
                        <input type="text" id="sc-lib-title" name="title" required value="<?php echo esc_attr($edit_item->title ?? ''); ?>" placeholder="نام تمرین">
                    </div>
                    <div class="sc-prog-field sc-prog-field--full">
                        <label for="sc-lib-desc">توضیحات</label>
                        <textarea id="sc-lib-desc" name="description" rows="4" placeholder="توضیح کوتاه برای بازیکن"><?php echo esc_textarea($edit_item->description ?? ''); ?></textarea>
                    </div>
                    <div class="sc-prog-field">
                        <label for="sc-lib-media-type">نوع رسانه</label>
                        <select name="media_type" id="sc-lib-media-type">
                            <?php
                            $mt = $edit_item->media_type ?? 'none';
                            foreach ($media_labels as $k => $label) :
                                ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($mt, $k); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-prog-field sc-lib-media-url-wrap">
                        <label for="sc-lib-media-url">آدرس لینک</label>
                        <input type="url" name="media_url" id="sc-lib-media-url" value="<?php echo esc_attr($edit_item->media_url ?? ''); ?>" placeholder="https://www.aparat.com/...">
                    </div>
                    <div class="sc-prog-field sc-prog-field--full sc-lib-media-file-wrap" style="display:none;">
                        <label for="sc-lib-file-input">آپلود فایل (حداکثر ۵۰MB)</label>
                        <div class="sc-prog-upload-row">
                            <input type="file" id="sc-lib-file-input" accept="video/*,image/*,.pdf">
                            <span id="sc-lib-file-status" class="sc-prog-hint"><?php echo !empty($edit_item->file_name) ? esc_html($edit_item->file_name) : 'فایلی انتخاب نشده'; ?></span>
                        </div>
                    </div>
                </div>
                <div class="sc-prog-form-actions">
                    <button type="submit" name="sc_save_library_item" class="sc_button sc_button--primary">ذخیره</button>
                    <?php if ($edit_item) : ?>
                        <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $base_page)); ?>">انصراف</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="sc-prog-section">
            <div class="sc-prog-section__head sc-prog-section__head--row">
                <div>
                    <h2>لیست تمرین‌ها</h2>
                    <p><?php echo (int) $result['total']; ?> مورد</p>
                </div>
                <form method="get" class="sc-prog-inline-search">
                    <input type="hidden" name="page" value="<?php echo esc_attr($base_page); ?>">
                    <input type="search" name="s" class="sc-prog-input" value="<?php echo esc_attr($search); ?>" placeholder="جستجو...">
                    <button type="submit" class="sc_button">جستجو</button>
                </form>
            </div>
            <div class="sc-prog-card-grid sc-prog-card-grid--compact">
                <?php foreach ($result['rows'] as $row) : ?>
                    <article class="sc-prog-entity-card sc-prog-entity-card--sm">
                        <div class="sc-prog-entity-card__body">
                            <span class="sc-prog-pill"><?php echo esc_html($media_labels[$row->media_type] ?? $row->media_type); ?></span>
                            <h3 class="sc-prog-entity-card__title"><?php echo esc_html($row->title); ?></h3>
                            <?php if (!empty($row->description)) : ?>
                                <p class="sc-prog-entity-card__desc"><?php echo esc_html(wp_html_excerpt(wp_strip_all_tags($row->description), 70, '…')); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="sc-prog-entity-card__foot">
                            <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $base_page . '&edit=' . (int) $row->id)); ?>">ویرایش</a>
                            <a class="sc_button sc_button--danger" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=' . $base_page . '&delete=' . (int) $row->id), 'sc_delete_library_' . (int) $row->id)); ?>" onclick="return confirm('حذف شود؟');">حذف</a>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($result['rows'])) : ?>
                    <div class="sc-prog-empty-hero sc-prog-empty-hero--soft"><p>تمرینی یافت نشد.</p></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
