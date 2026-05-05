<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

$templates = sc_certificates_get_saved_templates();
$notice = '';

if (isset($_POST['sc_save_certificate_templates'])) {
    check_admin_referer('sc_save_certificate_templates_nonce');
    $posted_templates = isset($_POST['templates']) && is_array($_POST['templates']) ? $_POST['templates'] : [];
    $new_templates = [];
    foreach ($posted_templates as $template) {
        if (!is_array($template)) {
            continue;
        }
        $normalized = sc_certificates_normalize_template($template, isset($template['key']) ? $template['key'] : '');
        $new_templates[$normalized['key']] = $normalized;
    }
    if (empty($new_templates)) {
        $new_templates = sc_certificates_get_default_templates();
    }
    sc_certificates_save_templates($new_templates);
    $templates = sc_certificates_get_saved_templates();
    $notice = 'قالب‌های گواهینامه با موفقیت ذخیره شد.';
}

$placeholders = sc_certificates_get_placeholders();
?>

<div class="wrap sc-users-export-wrap">
    <h1>تعریف قالب گواهینامه</h1>

    <?php if ($notice) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post" id="sc-certificate-templates-form">
        <?php wp_nonce_field('sc_save_certificate_templates_nonce'); ?>

        <div class="sc-users-export-card">
            <button type="button" class="button button-primary" id="sc-add-certificate-template">افزودن قالب جدید</button>
            <p class="description">در متن گواهینامه از متغیرهای زیر استفاده کنید.</p>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <?php foreach ($placeholders as $key => $label) : ?>
                    <button type="button" class="button sc-cert-var-btn" data-var="%<?php echo esc_attr($key); ?>%"><?php echo esc_html($label . ' (%' . $key . '%)'); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="sc-certificate-templates-container">
            <?php foreach ($templates as $template) : ?>
                <div class="sc-users-export-card sc-certificate-template-item">
                    <input type="hidden" name="templates[<?php echo esc_attr($template['key']); ?>][key]" value="<?php echo esc_attr($template['key']); ?>">
                    <div class="sc-row">
                        <label>عنوان قالب</label>
                        <input type="text" name="templates[<?php echo esc_attr($template['key']); ?>][title]" value="<?php echo esc_attr($template['title']); ?>">
                    </div>
                    <div class="sc-row">
                        <label>توضیحات</label>
                        <textarea name="templates[<?php echo esc_attr($template['key']); ?>][description]" rows="2"><?php echo esc_textarea($template['description']); ?></textarea>
                    </div>
                    <div class="sc-row">
                        <label>تصویر بک‌گراند</label>
                        <div class="sc-bg-image-picker">
                            <input type="text" class="sc-bg-image-input" name="templates[<?php echo esc_attr($template['key']); ?>][background_image]" value="<?php echo esc_attr($template['background_image']); ?>" placeholder="URL تصویر">
                            <button type="button" class="button sc-select-bg-image">انتخاب تصویر</button>
                        </div>
                    </div>
                    <div class="sc-row">
                        <label>متن گواهینامه</label>
                        <textarea class="sc-certificate-message-text" name="templates[<?php echo esc_attr($template['key']); ?>][message_text]" rows="5"><?php echo esc_textarea($template['message_text']); ?></textarea>
                    </div>
                    <div class="sc-row">
                        <label>امضا اول - متن</label>
                        <input type="text" name="templates[<?php echo esc_attr($template['key']); ?>][signature_one_text]" value="<?php echo esc_attr($template['signature_one_text']); ?>">
                    </div>
                    <div class="sc-row">
                        <label>امضا اول - تصویر</label>
                        <div class="sc-bg-image-picker">
                            <input type="text" class="sc-bg-image-input" name="templates[<?php echo esc_attr($template['key']); ?>][signature_one_image]" value="<?php echo esc_attr($template['signature_one_image']); ?>" placeholder="URL تصویر">
                            <button type="button" class="button sc-select-bg-image">انتخاب تصویر</button>
                        </div>
                    </div>
                    <div class="sc-row">
                        <label>امضا دوم - متن</label>
                        <input type="text" name="templates[<?php echo esc_attr($template['key']); ?>][signature_two_text]" value="<?php echo esc_attr($template['signature_two_text']); ?>">
                    </div>
                    <div class="sc-row">
                        <label>امضا دوم - تصویر</label>
                        <div class="sc-bg-image-picker">
                            <input type="text" class="sc-bg-image-input" name="templates[<?php echo esc_attr($template['key']); ?>][signature_two_image]" value="<?php echo esc_attr($template['signature_two_image']); ?>" placeholder="URL تصویر">
                            <button type="button" class="button sc-select-bg-image">انتخاب تصویر</button>
                        </div>
                    </div>
                    <p><button type="button" class="button sc-remove-certificate-template">حذف قالب</button></p>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="submit">
            <button type="submit" name="sc_save_certificate_templates" class="button button-primary">ذخیره قالب‌ها</button>
        </p>
    </form>
</div>

<script>
jQuery(function($){
    let index = <?php echo (int) count($templates); ?>;
    const container = $('#sc-certificate-templates-container');
    const defaultText = 'بازیکن عزیز %name%، یک گواهینامه برای شما صادر شد.';

    function createTemplateCard(key) {
        return `<div class="sc-users-export-card sc-certificate-template-item">
            <input type="hidden" name="templates[${key}][key]" value="${key}">
            <div class="sc-row"><label>عنوان قالب</label><input type="text" name="templates[${key}][title]" value="قالب جدید"></div>
            <div class="sc-row"><label>توضیحات</label><textarea name="templates[${key}][description]" rows="2"></textarea></div>
            <div class="sc-row"><label>تصویر بک‌گراند</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][background_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <div class="sc-row"><label>متن گواهینامه</label><textarea class="sc-certificate-message-text" name="templates[${key}][message_text]" rows="5">${defaultText}</textarea></div>
            <div class="sc-row"><label>امضا اول - متن</label><input type="text" name="templates[${key}][signature_one_text]" value=""></div>
            <div class="sc-row"><label>امضا اول - تصویر</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][signature_one_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <div class="sc-row"><label>امضا دوم - متن</label><input type="text" name="templates[${key}][signature_two_text]" value=""></div>
            <div class="sc-row"><label>امضا دوم - تصویر</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][signature_two_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <p><button type="button" class="button sc-remove-certificate-template">حذف قالب</button></p>
        </div>`;
    }

    $('#sc-add-certificate-template').on('click', function(){
        const key = `certificate_${index++}`;
        container.append(createTemplateCard(key));
    });

    $(document).on('click', '.sc-remove-certificate-template', function(){
        $(this).closest('.sc-certificate-template-item').remove();
    });

    $(document).on('click', '.sc-cert-var-btn', function(){
        const variable = $(this).data('var');
        const focused = $('.sc-certificate-message-text:focus');
        if (!focused.length) {
            alert('ابتدا داخل متن گواهینامه کلیک کنید.');
            return;
        }
        const input = focused.get(0);
        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const text = focused.val();
        focused.val(text.substring(0, start) + variable + text.substring(end));
        input.selectionStart = input.selectionEnd = start + variable.length;
        focused.trigger('input');
    });
});
</script>
