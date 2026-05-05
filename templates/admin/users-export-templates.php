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
                        $columns_count = max(1, min(4, (int) ($template['columns_count'] ?? 2)));
                        $layout_columns_count = max(1, min(4, (int) ($template['layout_columns_count'] ?? $columns_count)));
                        $layout_columns = isset($layout['columns']) && is_array($layout['columns']) ? $layout['columns'] : [];
                        if (empty($layout_columns)) {
                            $layout_columns = [
                                'column_1' => isset($layout['right_fields']) ? (array) $layout['right_fields'] : [],
                                'column_2' => isset($layout['left_fields']) ? (array) $layout['left_fields'] : [],
                            ];
                        }
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
                                <label>تعداد ستون خروجی</label>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][columns_count]">
                                    <?php for ($i = 1; $i <= 4; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($columns_count, $i); ?>><?php echo $i; ?> ستونه</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="sc-row">
                                <label>تعداد ستون چیدمان فیلدها</label>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][layout_columns_count]" class="sc-layout-columns-count-select">
                                    <?php for ($i = 1; $i <= 4; $i++) : ?>
                                        <option value="<?php echo $i; ?>" <?php selected($layout_columns_count, $i); ?>><?php echo $i; ?> ستونه</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="sc-row">
                                <label>رنگ پس‌زمینه کارت</label>
                                <input type="color" name="templates[<?php echo esc_attr($template['key']); ?>][background_color]" value="<?php echo esc_attr($template['background_color'] ?? '#ffffff'); ?>">
                            </div>
                            <div class="sc-row">
                                <label>تصویر پس‌زمینه کارت</label>
                                <div class="sc-bg-image-picker">
                                    <input type="text" class="sc-bg-image-input" name="templates[<?php echo esc_attr($template['key']); ?>][background_image]" value="<?php echo esc_attr($template['background_image'] ?? ''); ?>" placeholder="URL تصویر">
                                    <button type="button" class="button sc-select-bg-image">انتخاب تصویر</button>
                                </div>
                            </div>
                            <div class="sc-row">
                                <label>شفافیت تصویر پس‌زمینه (0 تا 1)</label>
                                <input type="number" name="templates[<?php echo esc_attr($template['key']); ?>][background_opacity]" min="0" max="1" step="0.05" value="<?php echo esc_attr($template['background_opacity'] ?? 0.2); ?>">
                            </div>
                            <div class="sc-row">
                                <label class="sc-inline-check">
                                    <input type="checkbox" name="templates[<?php echo esc_attr($template['key']); ?>][image_only_mode]" value="1" <?php checked(!empty($template['image_only_mode'])); ?>>
                                    خروجی فقط عکس باشد (تمام عرض، زیر هم، بک‌گراند شفاف)
                                </label>
                            </div>
                            <div class="sc-row">
                                <label>متن اضافی زیر کارت</label>
                                <textarea name="templates[<?php echo esc_attr($template['key']); ?>][card_footer_text]" rows="3" placeholder="متن دلخواه برای نمایش پایین هر کارت"><?php echo esc_textarea($template['card_footer_text'] ?? ''); ?></textarea>
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
                                <div class="sc-layout-columns" data-columns-count="<?php echo (int) $layout_columns_count; ?>">
                                    <?php for ($i = 1; $i <= $layout_columns_count; $i++) :
                                        $col_key = 'column_' . $i;
                                        $col_fields = isset($layout_columns[$col_key]) && is_array($layout_columns[$col_key]) ? $layout_columns[$col_key] : [];
                                        ?>
                                        <div class="sc-layout-column">
                                            <h4>ستون <?php echo (int) $i; ?></h4>
                                            <div class="sc-layout-dropzone" data-column="<?php echo esc_attr($col_key); ?>">
                                                <?php foreach ($col_fields as $field_key) :
                                                    if (!isset($field_labels[$field_key])) {
                                                        continue;
                                                    }
                                                    ?>
                                                    <div class="sc-layout-chip" draggable="true" data-field="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field_labels[$field_key]); ?></div>
                                                    <input type="hidden" name="templates[<?php echo esc_attr($template['key']); ?>][layout][columns][<?php echo esc_attr($col_key); ?>][]" value="<?php echo esc_attr($field_key); ?>">
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
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
