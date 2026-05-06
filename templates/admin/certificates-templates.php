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
$first_template_key = !empty($templates) ? array_key_first($templates) : '';
?>

<div class="wrap sc-users-export-wrap">
    <h1>تعریف قالب گواهینامه</h1>

    <?php if ($notice) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post" id="sc-certificate-templates-form">
        <?php wp_nonce_field('sc_save_certificate_templates_nonce'); ?>

        <div class="sc-template-manager-grid">
            <div class="sc-users-export-card sc-templates-list-card">
                <div class="sc-template-list-header">
                    <h2>لیست قالب‌ها</h2>
                    <button type="button" class="button button-primary" id="sc-add-certificate-template">افزودن قالب جدید</button>
                </div>
                <div id="sc-certificate-templates-list">
                    <?php foreach ($templates as $template) : ?>
                        <div class="sc-template-list-item<?php echo $template['key'] === $first_template_key ? ' is-active' : ''; ?>" data-template-key="<?php echo esc_attr($template['key']); ?>">
                            <div class="sc-template-list-title"><?php echo esc_html($template['title']); ?></div>
                            <div class="sc-template-list-actions">
                                <button type="button" class="button sc-edit-certificate-template">ویرایش</button>
                                <button type="button" class="button sc-remove-certificate-template">حذف</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sc-users-export-card">
                <p class="description">برای هر قالب، متغیرها را از زیر بخش «متن گواهینامه» درج کنید.</p>
                <div id="sc-certificate-templates-container">
            <?php foreach ($templates as $template) : ?>
                <div class="sc-certificate-template-item sc-template-item<?php echo $template['key'] === $first_template_key ? ' is-active' : ''; ?>" data-template-key="<?php echo esc_attr($template['key']); ?>">
                    <input type="hidden" name="templates[<?php echo esc_attr($template['key']); ?>][key]" value="<?php echo esc_attr($template['key']); ?>">
                    <div class="sc-row">
                        <label>نام قالب</label>
                        <input type="text" class="sc-certificate-title-input" name="templates[<?php echo esc_attr($template['key']); ?>][title]" value="<?php echo esc_attr($template['title']); ?>">
                    </div>
                    <div class="sc-row">
                        <label>عنوان گواهینامه</label>
                        <input type="text" name="templates[<?php echo esc_attr($template['key']); ?>][certificate_title]" value="<?php echo esc_attr($template['certificate_title'] ?? $template['title']); ?>">
                    </div>
                    <div class="sc-row">
                        <label>توضیحات</label>
                        <textarea name="templates[<?php echo esc_attr($template['key']); ?>][description]" rows="2"><?php echo esc_textarea($template['description']); ?></textarea>
                    </div>
                    <div class="sc-row">
                        <label>حالت صفحه</label>
                        <select name="templates[<?php echo esc_attr($template['key']); ?>][orientation]">
                            <option value="portrait" <?php selected(($template['orientation'] ?? 'portrait'), 'portrait'); ?>>عمودی</option>
                            <option value="landscape" <?php selected(($template['orientation'] ?? 'portrait'), 'landscape'); ?>>افقی</option>
                        </select>
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
                        <label>متغیرها (کلیک برای درج)</label>
                        <div class="sc-cert-placeholders-row" style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($placeholders as $key => $label) : ?>
                                <button type="button" class="button sc-cert-var-btn" data-var="%<?php echo esc_attr($key); ?>%"><?php echo esc_html($label . ' (%' . $key . '%)'); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="sc-row">
                        <label>امضا اول - متن</label>
                        <?php
                        $editor_one_id = 'emza1_' . sanitize_key((string) $template['key']);
                        wp_editor(
                            (string) ($template['signature_one_text'] ?? ''),
                            $editor_one_id,
                            [
                                'textarea_name' => 'templates[' . $template['key'] . '][signature_one_text]',
                                'textarea_rows' => 4,
                                'media_buttons' => false,
                            ]
                        );
                        ?>
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
                        <?php
                        $editor_two_id = 'emza2_' . sanitize_key((string) $template['key']);
                        wp_editor(
                            (string) ($template['signature_two_text'] ?? ''),
                            $editor_two_id,
                            [
                                'textarea_name' => 'templates[' . $template['key'] . '][signature_two_text]',
                                'textarea_rows' => 4,
                                'media_buttons' => false,
                            ]
                        );
                        ?>
                    </div>
                    <div class="sc-row">
                        <label>امضا دوم - تصویر</label>
                        <div class="sc-bg-image-picker">
                            <input type="text" class="sc-bg-image-input" name="templates[<?php echo esc_attr($template['key']); ?>][signature_two_image]" value="<?php echo esc_attr($template['signature_two_image']); ?>" placeholder="URL تصویر">
                            <button type="button" class="button sc-select-bg-image">انتخاب تصویر</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p class="submit">
            <button type="submit" name="sc_save_certificate_templates" class="button button-primary">ذخیره قالب‌ها</button>
        </p>
    </form>
</div>

<script>
jQuery(function($){
    const container = $('#sc-certificate-templates-container');
    const templatesList = $('#sc-certificate-templates-list');
    const defaultText = 'بازیکن عزیز %name%، یک گواهینامه برای شما صادر شد.';

    function createTemplateCard(key) {
        return `<div class="sc-certificate-template-item sc-template-item" data-template-key="${key}">
            <input type="hidden" name="templates[${key}][key]" value="${key}">
            <div class="sc-row"><label>نام قالب</label><input type="text" class="sc-certificate-title-input" name="templates[${key}][title]" value="قالب جدید"></div>
            <div class="sc-row"><label>عنوان گواهینامه</label><input type="text" name="templates[${key}][certificate_title]" value="گواهینامه"></div>
            <div class="sc-row"><label>توضیحات</label><textarea name="templates[${key}][description]" rows="2"></textarea></div>
            <div class="sc-row"><label>حالت صفحه</label><select name="templates[${key}][orientation]"><option value="portrait">عمودی</option><option value="landscape">افقی</option></select></div>
            <div class="sc-row"><label>تصویر بک‌گراند</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][background_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <div class="sc-row"><label>متن گواهینامه</label><textarea class="sc-certificate-message-text" name="templates[${key}][message_text]" rows="5">${defaultText}</textarea></div>
            <div class="sc-row"><label>متغیرها (کلیک برای درج)</label><div class="sc-cert-placeholders-row" style="display:flex;flex-wrap:wrap;gap:8px;"><?php foreach ($placeholders as $phKey => $phLabel) : ?><button type="button" class="button sc-cert-var-btn" data-var="%<?php echo esc_attr($phKey); ?>%"><?php echo esc_html($phLabel . ' (%' . $phKey . '%)'); ?></button><?php endforeach; ?></div></div>
            <div class="sc-row"><label>امضا اول - متن</label><textarea name="templates[${key}][signature_one_text]" rows="4"></textarea></div>
            <div class="sc-row"><label>امضا اول - تصویر</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][signature_one_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <div class="sc-row"><label>امضا دوم - متن</label><textarea name="templates[${key}][signature_two_text]" rows="3"></textarea></div>
            <div class="sc-row"><label>امضا دوم - تصویر</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][signature_two_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
        </div>`;
    }

    function createTemplateListItem(key, title) {
        return `<div class="sc-template-list-item" data-template-key="${key}">
            <div class="sc-template-list-title">${title}</div>
            <div class="sc-template-list-actions">
                <button type="button" class="button sc-edit-certificate-template">ویرایش</button>
                <button type="button" class="button sc-remove-certificate-template">حذف</button>
            </div>
        </div>`;
    }

    function activateTemplate(key) {
        $('.sc-template-list-item, .sc-template-item').removeClass('is-active');
        $(`.sc-template-list-item[data-template-key="${key}"]`).addClass('is-active');
        $(`.sc-template-item[data-template-key="${key}"]`).addClass('is-active');
    }

    function makeUniqueTemplateKey() {
        let key = '';
        do {
            key = `certificate_${Date.now()}_${Math.floor(Math.random() * 10000)}`;
        } while ($(`.sc-template-item[data-template-key="${key}"]`).length > 0);
        return key;
    }

    $('#sc-add-certificate-template').on('click', function(){
        const key = makeUniqueTemplateKey();
        container.append(createTemplateCard(key));
        templatesList.append(createTemplateListItem(key, 'قالب جدید'));
        activateTemplate(key);
    });

    $(document).on('click', '.sc-remove-certificate-template', function(){
        const listItem = $(this).closest('.sc-template-list-item');
        const key = listItem.attr('data-template-key');
        if (!confirm('این قالب حذف شود؟')) {
            return;
        }
        listItem.remove();
        $(`.sc-template-item[data-template-key="${key}"]`).remove();
        const first = $('.sc-template-list-item').first();
        if (first.length) {
            activateTemplate(first.attr('data-template-key'));
        }
    });

    $(document).on('click', '.sc-edit-certificate-template', function(){
        const key = $(this).closest('.sc-template-list-item').attr('data-template-key');
        activateTemplate(key);
    });

    $(document).on('input', '.sc-certificate-title-input', function(){
        const card = $(this).closest('.sc-template-item');
        const key = card.attr('data-template-key');
        const title = ($(this).val() || '').trim() || 'قالب جدید';
        $(`.sc-template-list-item[data-template-key="${key}"] .sc-template-list-title`).text(title);
    });

    $(document).on('focus click keyup', '.sc-certificate-message-text', function(){
        this.dataset.scSelStart = String(this.selectionStart || 0);
        this.dataset.scSelEnd = String(this.selectionEnd || 0);
    });

    $(document).on('click', '.sc-cert-var-btn', function(e){
        e.preventDefault();
        const variable = $(this).data('var');
        const card = $(this).closest('.sc-certificate-template-item');
        const textarea = card.find('.sc-certificate-message-text').first().get(0);
        if (!textarea) {
            return;
        }
        const text = textarea.value || '';
        const start = Number.isFinite(parseInt(textarea.dataset.scSelStart, 10)) ? parseInt(textarea.dataset.scSelStart, 10) : text.length;
        const end = Number.isFinite(parseInt(textarea.dataset.scSelEnd, 10)) ? parseInt(textarea.dataset.scSelEnd, 10) : text.length;
        textarea.value = text.substring(0, start) + variable + text.substring(end);
        const newPos = start + variable.length;
        textarea.focus();
        textarea.selectionStart = newPos;
        textarea.selectionEnd = newPos;
        textarea.dataset.scSelStart = String(newPos);
        textarea.dataset.scSelEnd = String(newPos);
        $(textarea).trigger('input');
    });

    $(document).on('click', '.sc-select-bg-image', function(e){
        e.preventDefault();
        const button = $(this);
        const input = button.closest('.sc-bg-image-picker').find('.sc-bg-image-input');
        if (typeof wp === 'undefined' || !wp.media) {
            alert('کتابخانه رسانه وردپرس بارگذاری نشده است.');
            return;
        }
        const frame = wp.media({
            title: 'انتخاب تصویر',
            button: { text: 'انتخاب تصویر' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function(){
            const attachment = frame.state().get('selection').first().toJSON();
            if (attachment && attachment.url) {
                input.val(attachment.url).trigger('change');
            }
        });
        frame.open();
    });
});
</script>
