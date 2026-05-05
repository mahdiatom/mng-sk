<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

$field_labels = sc_users_export_get_field_labels();
$templates = sc_users_export_get_saved_templates();
$notice = '';

if (isset($_POST['sc_save_export_templates'])) {
    check_admin_referer('sc_save_export_templates_nonce');
    $posted_templates = isset($_POST['templates']) && is_array($_POST['templates']) ? $_POST['templates'] : [];

    $new_templates = [];
    foreach ($posted_templates as $template) {
        if (!is_array($template)) {
            continue;
        }
        $normalized = sc_users_export_normalize_template($template, isset($template['key']) ? $template['key'] : '');
        $new_templates[$normalized['key']] = $normalized;
    }

    sc_users_export_save_templates($new_templates);
    $templates = sc_users_export_get_saved_templates();
    $notice = 'تنظیمات قالب با موفقیت ذخیره شد.';
}
?>

<div class="wrap sc-users-export-wrap">
    <h1>تعریف قالب خروجی</h1>
    <p class="description">این قالب‌ها در صفحه خروجی اطلاعات کاربران قابل انتخاب هستند.</p>

    <?php if ($notice) : ?>
        <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post" id="sc-export-templates-form">
        <?php wp_nonce_field('sc_save_export_templates_nonce'); ?>
        <div id="sc-template-field-labels" data-fields="<?php echo esc_attr(wp_json_encode($field_labels)); ?>"></div>
        <?php $first_template_key = !empty($templates) ? array_key_first($templates) : ''; ?>
        <div class="sc-template-manager-grid">
            <div class="sc-users-export-card sc-templates-list-card">
                <div class="sc-template-list-header">
                    <h2>لیست قالب‌ها</h2>
                    <button type="button" id="sc-add-new-template" class="button button-primary">افزودن قالب</button>
                </div>
                <div id="sc-templates-list">
                    <?php foreach ($templates as $template) : ?>
                        <div class="sc-template-list-item<?php echo $template['key'] === $first_template_key ? ' is-active' : ''; ?>" data-template-key="<?php echo esc_attr($template['key']); ?>">
                            <div class="sc-template-list-title"><?php echo esc_html($template['title']); ?></div>
                            <div class="sc-template-list-actions">
                                <button type="button" class="button sc-edit-template">ویرایش</button>
                                <button type="button" class="button sc-delete-template">حذف</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sc-users-export-card">
                <div id="sc-templates-container">
                    <?php foreach ($templates as $template) :
                        $layout = isset($template['layout']) && is_array($template['layout']) ? $template['layout'] : [];
                        $photo_position = isset($layout['photo_position']) ? $layout['photo_position'] : 'left';
                        $right_fields = isset($layout['right_fields']) ? (array) $layout['right_fields'] : [];
                        $left_fields = isset($layout['left_fields']) ? (array) $layout['left_fields'] : [];
                        ?>
                        <div class="sc-template-item<?php echo $template['key'] === $first_template_key ? ' is-active' : ''; ?>" data-template-key="<?php echo esc_attr($template['key']); ?>">
                            <h2><?php echo esc_html($template['title']); ?> <small>(<?php echo esc_html($template['key']); ?>)</small></h2>
                            <input type="hidden" name="templates[<?php echo esc_attr($template['key']); ?>][key]" value="<?php echo esc_attr($template['key']); ?>">
                            <div class="sc-row">
                                <label>عنوان قالب</label>
                                <input type="text" class="sc-template-title-input" name="templates[<?php echo esc_attr($template['key']); ?>][title]" value="<?php echo esc_attr($template['title']); ?>">
                            </div>
                            <div class="sc-row">
                                <label>توضیحات</label>
                                <textarea name="templates[<?php echo esc_attr($template['key']); ?>][description]" rows="3"><?php echo esc_textarea($template['description']); ?></textarea>
                            </div>
                            <div class="sc-row">
                                <label>اندازه صفحه</label>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][page_size]">
                                    <option value="A4" <?php selected(($template['page_size'] ?? 'A4'), 'A4'); ?>>A4</option>
                                    <option value="A5" <?php selected(($template['page_size'] ?? 'A4'), 'A5'); ?>>A5</option>
                                </select>
                            </div>
                            <div class="sc-row">
                                <label>تعداد کارت در صفحه</label>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][cards_per_page]">
                                    <?php for ($i = 1; $i <= 6; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected((int) ($template['cards_per_page'] ?? 2), $i); ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="sc-row">
                                <label>جایگاه عکس</label>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][layout][photo_position]">
                                    <option value="left" <?php selected($photo_position, 'left'); ?>>سمت چپ</option>
                                    <option value="right" <?php selected($photo_position, 'right'); ?>>سمت راست</option>
                                    <option value="none" <?php selected($photo_position, 'none'); ?>>بدون عکس</option>
                                </select>
                            </div>
                            <div class="sc-fields-grid">
                                <?php foreach ($field_labels as $field_key => $field_label) : ?>
                                    <label class="sc-inline-check">
                                        <input type="checkbox" name="templates[<?php echo esc_attr($template['key']); ?>][fields][]" value="<?php echo esc_attr($field_key); ?>" <?php checked(in_array($field_key, (array) ($template['fields'] ?? []), true)); ?>>
                                        <?php echo esc_html($field_label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="sc-template-layout-builder" data-template="<?php echo esc_attr($template['key']); ?>">
                                <h3>چیدمان گرافیکی فیلدها</h3>
                                <p class="description">فیلدها را با Drag & Drop بین ستون‌ها جابه‌جا کنید.</p>
                                <div class="sc-layout-columns">
                                    <div class="sc-layout-column">
                                        <h4>ستون راست</h4>
                                        <div class="sc-layout-dropzone" data-side="right">
                                            <?php foreach ($right_fields as $field_key) :
                                                if (!isset($field_labels[$field_key])) {
                                                    continue;
                                                }
                                                ?>
                                                <div class="sc-layout-chip" draggable="true" data-field="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_labels[$field_key]); ?></div>
                                                <input type="hidden" name="templates[<?php echo esc_attr($template['key']); ?>][layout][right_fields][]" value="<?php echo esc_attr($field_key); ?>">
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="sc-layout-column">
                                        <h4>ستون چپ</h4>
                                        <div class="sc-layout-dropzone" data-side="left">
                                            <?php foreach ($left_fields as $field_key) :
                                                if (!isset($field_labels[$field_key])) {
                                                    continue;
                                                }
                                                ?>
                                                <div class="sc-layout-chip" draggable="true" data-field="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_labels[$field_key]); ?></div>
                                                <input type="hidden" name="templates[<?php echo esc_attr($template['key']); ?>][layout][left_fields][]" value="<?php echo esc_attr($field_key); ?>">
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p class="submit">
            <button type="submit" name="sc_save_export_templates" class="button button-primary">ذخیره قالب‌ها</button>
        </p>
    </form>
</div>
