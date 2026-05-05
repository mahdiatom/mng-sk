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

    foreach ($posted_templates as $key => $template) {
        $safe_key = sanitize_key($key);
        if (!isset($templates[$safe_key])) {
            continue;
        }
        $templates[$safe_key]['title'] = isset($template['title']) ? sanitize_text_field($template['title']) : $templates[$safe_key]['title'];
        $templates[$safe_key]['description'] = isset($template['description']) ? sanitize_textarea_field($template['description']) : '';
        $templates[$safe_key]['page_size'] = (isset($template['page_size']) && in_array($template['page_size'], ['A4', 'A5'], true)) ? $template['page_size'] : 'A4';
        $templates[$safe_key]['cards_per_page'] = isset($template['cards_per_page']) ? max(1, min(6, (int) $template['cards_per_page'])) : 2;
        $template_fields = isset($template['fields']) && is_array($template['fields']) ? array_map('sanitize_text_field', $template['fields']) : [];
        $templates[$safe_key]['fields'] = array_values(array_intersect($template_fields, array_keys($field_labels)));
    }

    sc_users_export_save_templates($templates);
    $notice = 'تنظیمات قالب با موفقیت ذخیره شد.';
}
?>

<div class="wrap sc-users-export-wrap">
    <h1>تعریف قالب خروجی</h1>
    <p class="description">این قالب‌ها در صفحه خروجی اطلاعات کاربران قابل انتخاب هستند.</p>

    <?php if ($notice) : ?>
        <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('sc_save_export_templates_nonce'); ?>

        <?php foreach ($templates as $template) : ?>
            <div class="sc-users-export-card">
                <h2><?php echo esc_html($template['title']); ?> <small>(<?php echo esc_html($template['key']); ?>)</small></h2>
                <div class="sc-row">
                    <label>عنوان قالب</label>
                    <input type="text" name="templates[<?php echo esc_attr($template['key']); ?>][title]" value="<?php echo esc_attr($template['title']); ?>">
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
                <div class="sc-fields-grid">
                    <?php foreach ($field_labels as $field_key => $field_label) : ?>
                        <label class="sc-inline-check">
                            <input type="checkbox" name="templates[<?php echo esc_attr($template['key']); ?>][fields][]" value="<?php echo esc_attr($field_key); ?>" <?php checked(in_array($field_key, (array) ($template['fields'] ?? []), true)); ?>>
                            <?php echo esc_html($field_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <p class="submit">
            <button type="submit" name="sc_save_export_templates" class="button button-primary">ذخیره قالب‌ها</button>
        </p>
    </form>
</div>
