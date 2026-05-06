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
                        <label>پدینگ محتوا (پیکسل)</label>
                        <div class="sc-padding-grid">
                            <div class="sc-padding-item">
                                <small>بالا</small>
                                <input type="number" min="0" name="templates[<?php echo esc_attr($template['key']); ?>][padding_top]" value="<?php echo esc_attr((string) ($template['padding_top'] ?? 200)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>راست</small>
                                <input type="number" min="0" name="templates[<?php echo esc_attr($template['key']); ?>][padding_right]" value="<?php echo esc_attr((string) ($template['padding_right'] ?? 56)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>پایین</small>
                                <input type="number" min="0" name="templates[<?php echo esc_attr($template['key']); ?>][padding_bottom]" value="<?php echo esc_attr((string) ($template['padding_bottom'] ?? 28)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>چپ</small>
                                <input type="number" min="0" name="templates[<?php echo esc_attr($template['key']); ?>][padding_left]" value="<?php echo esc_attr((string) ($template['padding_left'] ?? 56)); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="sc-row">
                        <label>فاصله امضا از پایین (پیکسل)</label>
                        <input type="number" min="0" name="templates[<?php echo esc_attr($template['key']); ?>][signature_bottom_offset]" value="<?php echo esc_attr((string) ($template['signature_bottom_offset'] ?? 80)); ?>">
                    </div>
                    <div class="sc-row">
                        <label>تنظیمات متن و بک‌گراند</label>
                        <div class="sc-padding-grid">
                            <div class="sc-padding-item">
                                <small>Opacity بک‌گراند (0 تا 1)</small>
                                <input type="number" step="0.05" min="0" max="1" name="templates[<?php echo esc_attr($template['key']); ?>][background_opacity]" value="<?php echo esc_attr((string) ($template['background_opacity'] ?? 1)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>تراز متن</small>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][content_text_align]">
                                    <?php $align = (string) ($template['content_text_align'] ?? 'center'); ?>
                                    <option value="right" <?php selected($align, 'right'); ?>>راست</option>
                                    <option value="center" <?php selected($align, 'center'); ?>>وسط</option>
                                    <option value="justify" <?php selected($align, 'justify'); ?>>جاستیفای</option>
                                    <option value="left" <?php selected($align, 'left'); ?>>چپ</option>
                                </select>
                            </div>
                            <div class="sc-padding-item">
                                <small>سایز فونت متن</small>
                                <input type="number" min="10" max="40" name="templates[<?php echo esc_attr($template['key']); ?>][content_font_size]" value="<?php echo esc_attr((string) ($template['content_font_size'] ?? 17)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>Line-height</small>
                                <input type="number" step="0.05" min="1" max="3" name="templates[<?php echo esc_attr($template['key']); ?>][content_line_height]" value="<?php echo esc_attr((string) ($template['content_line_height'] ?? 1.85)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>فاصله پاراگراف (em)</small>
                                <input type="number" step="0.05" min="0" max="3" name="templates[<?php echo esc_attr($template['key']); ?>][content_paragraph_spacing]" value="<?php echo esc_attr((string) ($template['content_paragraph_spacing'] ?? 0.6)); ?>">
                            </div>
                            <div class="sc-padding-item">
                                <small>فونت</small>
                                <?php $ff = (string) ($template['content_font_family'] ?? 'IRANYekanXFaNum'); ?>
                                <select name="templates[<?php echo esc_attr($template['key']); ?>][content_font_family]">
                                    <option value="IRANYekanXFaNum" <?php selected($ff, 'IRANYekanXFaNum'); ?>>IRANYekanXFaNum</option>
                                    <option value="Vazir" <?php selected($ff, 'Vazir'); ?>>Vazir</option>
                                    <option value="Shabnam" <?php selected($ff, 'Shabnam'); ?>>Shabnam</option>
                                    <option value="Morabba" <?php selected($ff, 'Morabba'); ?>>Morabba</option>
                                    <option value="Tahoma" <?php selected($ff, 'Tahoma'); ?>>Tahoma</option>
                                    <option value="Arial" <?php selected($ff, 'Arial'); ?>>Arial</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="sc-row sc-preview-row">
                        <label>پیش‌نمایش زنده</label>
                        <div class="sc-certificate-preview-box">
                            <div class="sc-certificate-preview-canvas">
                                <div class="sc-certificate-preview-bg"></div>
                                <div class="sc-certificate-preview-content">
                                    <div class="sc-certificate-preview-body">
                                        <h3 class="sc-certificate-preview-title"><?php echo esc_html($template['certificate_title'] ?? $template['title']); ?></h3>
                                        <div class="sc-certificate-preview-message"><?php echo wp_kses_post((string) ($template['message_text'] ?? '')); ?></div>
                                    </div>
                                    <div class="sc-certificate-preview-signatures">
                                        <div class="sc-certificate-preview-signature">
                                            <img class="sc-certificate-preview-signature-image" src="<?php echo esc_url((string) ($template['signature_one_image'] ?? '')); ?>" alt="" style="<?php echo empty($template['signature_one_image']) ? 'display:none;' : ''; ?>">
                                            <div class="sc-certificate-preview-signature-text"><?php echo wp_kses_post((string) ($template['signature_one_text'] ?? '')); ?></div>
                                        </div>
                                        <div class="sc-certificate-preview-signature">
                                            <img class="sc-certificate-preview-signature-image" src="<?php echo esc_url((string) ($template['signature_two_image'] ?? '')); ?>" alt="" style="<?php echo empty($template['signature_two_image']) ? 'display:none;' : ''; ?>">
                                            <div class="sc-certificate-preview-signature-text"><?php echo wp_kses_post((string) ($template['signature_two_text'] ?? '')); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                        <?php
                        $msg_editor_id = 'msg_' . sanitize_key((string) $template['key']);
                        wp_editor(
                            (string) ($template['message_text'] ?? ''),
                            $msg_editor_id,
                            [
                                'textarea_name' => 'templates[' . $template['key'] . '][message_text]',
                                'textarea_rows' => 6,
                                'textarea_class' => 'sc-certificate-message-text',
                                'media_buttons' => false,
                            ]
                        );
                        ?>
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
                                'textarea_class' => 'sc-cert-signature-editor',
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
                                'textarea_class' => 'sc-cert-signature-editor',
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

<style>
@font-face {
    font-family: Vazir;
    src: url('<?php echo esc_url(SC_ASSETS_URL . 'fonts/Woff/Vazir.woff'); ?>') format('woff'),
         url('<?php echo esc_url(SC_ASSETS_URL . 'fonts/Woff2/Vazir.woff2'); ?>') format('woff2');
    font-weight: normal;
    font-style: normal;
}
@font-face {
    font-family: Shabnam;
    src: url('<?php echo esc_url(SC_ASSETS_URL . 'fonts/Woff/Shabnam.woff'); ?>') format('woff'),
         url('<?php echo esc_url(SC_ASSETS_URL . 'fonts/Woff2/Shabnam.woff2'); ?>') format('woff2');
    font-weight: normal;
    font-style: normal;
}
@font-face {
    font-family: Morabba;
    src: url('<?php echo esc_url(SC_ASSETS_URL . 'fonts/ttf/Morabba-Regular.ttf'); ?>') format('truetype');
    font-weight: normal;
    font-style: normal;
}
.sc-template-manager-grid { display: block !important; }
.sc-template-manager-grid > .sc-users-export-card { width: 100% !important; max-width: 100% !important; margin-bottom: 16px; }
.sc-padding-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.sc-padding-item small { display: block; margin-bottom: 4px; color: #666; }
.sc-padding-item input { width: 100%; max-width: 100%; box-sizing: border-box; }
.sc-preview-row label { display: block; margin-bottom: 6px; }
.sc-certificate-preview-box {
    position: relative;
    width: 100%;
    min-height: 220px;
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 8px;
    overflow: auto;
    padding: 8px;
    box-sizing: border-box;
    display: flex;
    align-items: flex-start;
    justify-content: center;
}
.sc-certificate-preview-canvas {
    position: relative;
    width: min(100%, 980px);
    aspect-ratio: 210 / 297;
    border: 1px solid #e2e2e2;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}
.sc-certificate-preview-box.is-landscape .sc-certificate-preview-canvas {
    aspect-ratio: 297 / 210;
}
.sc-certificate-preview-bg {
    position: absolute;
    inset: 0;
    z-index: 1;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 1;
}
.sc-certificate-preview-content {
    position: relative;
    z-index: 2;
    height: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}
.sc-certificate-preview-body {
    flex: 1;
    min-height: 0;
    box-sizing: border-box;
    overflow: hidden;
}
.sc-certificate-preview-title { text-align: center; margin: 0 0 10px; font-size: 22px; line-height: 1.5; }
.sc-certificate-preview-message { flex: 1; line-height: 2.0; font-size: 16px; overflow: hidden; }
.sc-certificate-preview-message p,
.sc-certificate-preview-signature-text p { margin: 0.2em 0; }
.sc-certificate-preview-signatures {
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    gap: 6px;
    flex-shrink: 0;
}
.sc-certificate-preview-signature { width: 48%; text-align: center; }
.sc-certificate-preview-signature-image { max-height: 54px; max-width: 100%; object-fit: contain; display: block; margin: 0 auto 6px; }
.sc-certificate-preview-signature-text { text-align: center; font-size: 13px; line-height: 1.6; overflow: hidden; }
.sc-certificate-preview-box {
    --sc-preview-font-family: "IRANYekanXFaNum", Tahoma, Arial, sans-serif;
}
.sc-certificate-preview-box .sc-certificate-preview-title,
.sc-certificate-preview-box .sc-certificate-preview-message,
.sc-certificate-preview-box .sc-certificate-preview-message *,
.sc-certificate-preview-box .sc-certificate-preview-signature-text,
.sc-certificate-preview-box .sc-certificate-preview-signature-text * {
    font-family: var(--sc-preview-font-family) !important;
}
@media (max-width: 782px) {
    .sc-padding-grid { grid-template-columns: 1fr; }
}
</style>

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
            <div class="sc-row"><label>پدینگ محتوا (پیکسل)</label><div class="sc-padding-grid"><div class="sc-padding-item"><small>بالا</small><input type="number" min="0" name="templates[${key}][padding_top]" value="200"></div><div class="sc-padding-item"><small>راست</small><input type="number" min="0" name="templates[${key}][padding_right]" value="56"></div><div class="sc-padding-item"><small>پایین</small><input type="number" min="0" name="templates[${key}][padding_bottom]" value="28"></div><div class="sc-padding-item"><small>چپ</small><input type="number" min="0" name="templates[${key}][padding_left]" value="56"></div></div></div>
            <div class="sc-row"><label>فاصله امضا از پایین (پیکسل)</label><input type="number" min="0" name="templates[${key}][signature_bottom_offset]" value="80"></div>
            <div class="sc-row"><label>تنظیمات متن و بک‌گراند</label><div class="sc-padding-grid"><div class="sc-padding-item"><small>Opacity بک‌گراند (0 تا 1)</small><input type="number" step="0.05" min="0" max="1" name="templates[${key}][background_opacity]" value="1"></div><div class="sc-padding-item"><small>تراز متن</small><select name="templates[${key}][content_text_align]"><option value="right">راست</option><option value="center" selected>وسط</option><option value="justify">جاستیفای</option><option value="left">چپ</option></select></div><div class="sc-padding-item"><small>سایز فونت متن</small><input type="number" min="10" max="40" name="templates[${key}][content_font_size]" value="17"></div><div class="sc-padding-item"><small>Line-height</small><input type="number" step="0.05" min="1" max="3" name="templates[${key}][content_line_height]" value="1.85"></div><div class="sc-padding-item"><small>فاصله پاراگراف (em)</small><input type="number" step="0.05" min="0" max="3" name="templates[${key}][content_paragraph_spacing]" value="0.6"></div><div class="sc-padding-item"><small>فونت</small><select name="templates[${key}][content_font_family]"><option value="IRANYekanXFaNum" selected>IRANYekanXFaNum</option><option value="Vazir">Vazir</option><option value="Shabnam">Shabnam</option><option value="Morabba">Morabba</option><option value="Tahoma">Tahoma</option><option value="Arial">Arial</option></select></div></div></div>
            <div class="sc-row sc-preview-row"><label>پیش‌نمایش زنده</label><div class="sc-certificate-preview-box"><div class="sc-certificate-preview-canvas"><div class="sc-certificate-preview-bg"></div><div class="sc-certificate-preview-content"><div class="sc-certificate-preview-body"><h3 class="sc-certificate-preview-title">گواهینامه</h3><div class="sc-certificate-preview-message"></div></div><div class="sc-certificate-preview-signatures"><div class="sc-certificate-preview-signature"><img class="sc-certificate-preview-signature-image" src="" alt="" style="display:none;"><div class="sc-certificate-preview-signature-text"></div></div><div class="sc-certificate-preview-signature"><img class="sc-certificate-preview-signature-image" src="" alt="" style="display:none;"><div class="sc-certificate-preview-signature-text"></div></div></div></div></div></div></div>
            <div class="sc-row"><label>تصویر بک‌گراند</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][background_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <div class="sc-row"><label>متن گواهینامه</label><textarea id="msg_${key}" class="sc-certificate-message-text" name="templates[${key}][message_text]" rows="6">${defaultText}</textarea></div>
            <div class="sc-row"><label>متغیرها (کلیک برای درج)</label><div class="sc-cert-placeholders-row" style="display:flex;flex-wrap:wrap;gap:8px;"><?php foreach ($placeholders as $phKey => $phLabel) : ?><button type="button" class="button sc-cert-var-btn" data-var="%<?php echo esc_attr($phKey); ?>%"><?php echo esc_html($phLabel . ' (%' . $phKey . '%)'); ?></button><?php endforeach; ?></div></div>
            <div class="sc-row"><label>امضا اول - متن</label><textarea id="emza1_${key}" class="sc-cert-signature-editor" name="templates[${key}][signature_one_text]" rows="4"></textarea></div>
            <div class="sc-row"><label>امضا اول - تصویر</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[${key}][signature_one_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>
            <div class="sc-row"><label>امضا دوم - متن</label><textarea id="emza2_${key}" class="sc-cert-signature-editor" name="templates[${key}][signature_two_text]" rows="4"></textarea></div>
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

    function scSanitizeKey(key) {
        return String(key || '').toLowerCase().replace(/[^a-z0-9_\-]/g, '');
    }

    function getEditorHtml(editorId) {
        if (window.tinymce) {
            const ed = window.tinymce.get(editorId);
            if (ed && !ed.isHidden()) {
                return ed.getContent();
            }
        }
        const textarea = document.getElementById(editorId);
        return textarea ? textarea.value : '';
    }

    function setPreviewImage($img, url) {
        const clean = (url || '').trim();
        if (clean) {
            $img.attr('src', clean).show();
        } else {
            $img.attr('src', '').hide();
        }
    }

    function normalizePreviewHtml(rawHtml) {
        const html = String(rawHtml || '');
        return html
            .replace(/font-family\s*:\s*[^;"]+;?/gi, '')
            .replace(/line-height\s*:\s*[^;"]+;?/gi, '')
            .replace(/font-size\s*:\s*[^;"]+;?/gi, '');
    }

    function resolvePreviewFontFamily(fontKey) {
        const map = {
            IRANYekanXFaNum: '"IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Vazir: '"Vazir", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Shabnam: '"Shabnam", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Morabba: '"Morabba", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
            Tahoma: 'Tahoma, Arial, sans-serif',
            Arial: 'Arial, Tahoma, sans-serif'
        };
        const key = String(fontKey || 'IRANYekanXFaNum');
        return map[key] || map.IRANYekanXFaNum;
    }

    function updateTemplatePreview(card) {
        const $card = $(card);
        const key = String($card.attr('data-template-key') || '');
        const sanitized = scSanitizeKey(key);
        const $preview = $card.find('.sc-certificate-preview-box');
        if (!$preview.length) return;

        const title = ($card.find('input[name$="[certificate_title]"]').val() || '').trim() || 'گواهینامه';
        const bg = ($card.find('input[name$="[background_image]"]').val() || '').trim();
        const bgOpacity = parseFloat($card.find('input[name$="[background_opacity]"]').val()) || 1;
        const contentFontFamily = ($card.find('select[name$="[content_font_family]"]').val() || 'IRANYekanXFaNum').trim();
        const contentFontSize = parseInt($card.find('input[name$="[content_font_size]"]').val(), 10) || 17;
        const contentLineHeight = parseFloat($card.find('input[name$="[content_line_height]"]').val()) || 1.85;
        const contentParagraphSpacing = parseFloat($card.find('input[name$="[content_paragraph_spacing]"]').val()) || 0.6;
        const contentTextAlign = ($card.find('select[name$="[content_text_align]"]').val() || 'center').trim();
        const orientation = ($card.find('select[name$="[orientation]"]').val() || 'portrait').trim();
        const pt = parseInt($card.find('input[name$="[padding_top]"]').val(), 10) || 0;
        const pr = parseInt($card.find('input[name$="[padding_right]"]').val(), 10) || 0;
        const pb = parseInt($card.find('input[name$="[padding_bottom]"]').val(), 10) || 0;
        const pl = parseInt($card.find('input[name$="[padding_left]"]').val(), 10) || 0;
        const sbo = parseInt($card.find('input[name$="[signature_bottom_offset]"]').val(), 10) || 0;

        const msgHtml = normalizePreviewHtml(getEditorHtml('msg_' + sanitized));
        const sig1Html = normalizePreviewHtml(getEditorHtml('emza1_' + sanitized));
        const sig2Html = normalizePreviewHtml(getEditorHtml('emza2_' + sanitized));
        const sig1Img = ($card.find('input[name$="[signature_one_image]"]').val() || '').trim();
        const sig2Img = ($card.find('input[name$="[signature_two_image]"]').val() || '').trim();

        const canvasEl = $preview.find('.sc-certificate-preview-canvas').get(0);
        const rect = canvasEl ? canvasEl.getBoundingClientRect() : { width: 0, height: 0 };
        const canvasW = rect.width || 800;
        const canvasH = rect.height || 600;
        const scale = 1;
        let ptPx = Math.max(0, Math.round(pt / scale));
        let prPx = Math.max(0, Math.round(pr / scale));
        let pbPx = Math.max(0, Math.round(pb / scale));
        let plPx = Math.max(0, Math.round(pl / scale));
        let sboPx = Math.max(0, Math.round(sbo / scale));

        const maxVertical = Math.max(40, canvasH - 120);
        const verticalUsed = ptPx + pbPx + sboPx;
        if (verticalUsed > maxVertical) {
            const r = maxVertical / verticalUsed;
            ptPx = Math.floor(ptPx * r);
            pbPx = Math.floor(pbPx * r);
            sboPx = Math.floor(sboPx * r);
        }
        const maxHorizontal = Math.max(40, canvasW - 120);
        const horizontalUsed = prPx + plPx;
        if (horizontalUsed > maxHorizontal) {
            const r = maxHorizontal / horizontalUsed;
            prPx = Math.floor(prPx * r);
            plPx = Math.floor(plPx * r);
        }

        $preview.toggleClass('is-landscape', orientation === 'landscape');
        $preview.find('.sc-certificate-preview-bg').css({
            'background-image': bg ? `url("${bg}")` : 'none',
            'background-size': 'cover',
            'background-position': 'center',
            'opacity': Math.max(0, Math.min(1, bgOpacity))
        });
        $preview.get(0).style.setProperty('--sc-preview-font-family', resolvePreviewFontFamily(contentFontFamily));
        $preview.find('.sc-certificate-preview-body').css('padding', `${ptPx}px ${prPx}px ${pbPx}px ${plPx}px`);
        $preview.find('.sc-certificate-preview-signatures').css({
            'padding-right': `${prPx}px`,
            'padding-left': `${plPx}px`
        });
        $preview.find('.sc-certificate-preview-title').text(title);
        const $previewMessage = $preview.find('.sc-certificate-preview-message');
        $previewMessage.html(msgHtml);
        $previewMessage.css({
            'font-size': `${contentFontSize}px`,
            'line-height': String(contentLineHeight),
            'text-align': contentTextAlign
        });
        $previewMessage.find('*').css({
            'line-height': 'inherit'
        });
        $previewMessage.find('p, li, div, span').css('font-size', `${contentFontSize}px`);
        $previewMessage.find('p').css('margin-bottom', `${contentParagraphSpacing}em`);
        $preview.find('.sc-certificate-preview-signature-text').eq(0).html(sig1Html);
        $preview.find('.sc-certificate-preview-signature-text').eq(1).html(sig2Html);
        $preview.find('.sc-certificate-preview-signatures').css('margin-bottom', `${sboPx}px`);
        setPreviewImage($preview.find('.sc-certificate-preview-signature-image').eq(0), sig1Img);
        setPreviewImage($preview.find('.sc-certificate-preview-signature-image').eq(1), sig2Img);
    }

    function editorInitSettings(height) {
        return {
            mediaButtons: false,
            quicktags: true,
            tinymce: {
                wpautop: true,
                branding: false,
                toolbar1: 'bold,italic,bullist,numlist,link,unlink',
                toolbar2: '',
                height: height || 160,
                setup: function(editor) {
                    editor.on('input keyup change SetContent', function() {
                        const $ta = $('#' + editor.id);
                        if (!$ta.length) return;
                        updateTemplatePreview($ta.closest('.sc-template-item'));
                    });
                }
            }
        };
    }

    function initCertificateEditors(key) {
        if (typeof wp === 'undefined' || !wp.editor || typeof wp.editor.initialize !== 'function') {
            return;
        }
        const ids = ['msg_' + key, 'emza1_' + key, 'emza2_' + key];
        const heights = [220, 150, 150];
        ids.forEach(function(id, i) {
            if (!document.getElementById(id)) {
                return;
            }
            if (typeof wp.editor.remove === 'function') {
                try {
                    wp.editor.remove(id);
                } catch (err) {}
            }
            wp.editor.initialize(id, editorInitSettings(heights[i]));
        });
        setTimeout(function(){
            updateTemplatePreview($(`.sc-template-item[data-template-key="${key}"]`));
        }, 100);
    }

    function removeCertificateEditors(key) {
        if (typeof wp === 'undefined' || !wp.editor || typeof wp.editor.remove !== 'function') {
            return;
        }
        ['msg_' + key, 'emza1_' + key, 'emza2_' + key].forEach(function(id) {
            try {
                wp.editor.remove(id);
            } catch (err) {}
        });
    }

    $('#sc-add-certificate-template').on('click', function(){
        const key = makeUniqueTemplateKey();
        container.append(createTemplateCard(key));
        templatesList.append(createTemplateListItem(key, 'قالب جدید'));
        activateTemplate(key);
        initCertificateEditors(key);
    });

    $(document).on('click', '.sc-remove-certificate-template', function(){
        const listItem = $(this).closest('.sc-template-list-item');
        const key = listItem.attr('data-template-key');
        if (!confirm('این قالب حذف شود؟')) {
            return;
        }
        removeCertificateEditors(key);
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
        updateTemplatePreview(card);
    });

    $(document).on('input change keyup', '.sc-template-item input, .sc-template-item select, .sc-template-item textarea', function(){
        updateTemplatePreview($(this).closest('.sc-template-item'));
    });

    $(document).on('focus click keyup', '.sc-certificate-message-text', function(){
        this.dataset.scSelStart = String(this.selectionStart || 0);
        this.dataset.scSelEnd = String(this.selectionEnd || 0);
    });

    $(document).on('focus click keyup', 'textarea[id^="msg_"]', function(){
        this.dataset.scSelStart = String(this.selectionStart || 0);
        this.dataset.scSelEnd = String(this.selectionEnd || 0);
    });

    // Real-time update for wp_editor textarea (Text tab)
    $(document).on('keyup input paste', '.sc-template-item .wp-editor-area', function(){
        updateTemplatePreview($(this).closest('.sc-template-item'));
    });

    // Real-time update for already-initialized TinyMCE editors (existing templates)
    function bindExistingTinyMcePreview() {
        if (!window.tinymce || !window.tinymce.editors) return;
        window.tinymce.editors.forEach(function(ed){
            if (!ed || !ed.id) return;
            if (!/^msg_|^emza1_|^emza2_/.test(ed.id)) return;
            if (ed._scPreviewBound) return;
            ed._scPreviewBound = true;
            ed.on('input keyup change SetContent', function(){
                const $ta = $('#' + ed.id);
                if (!$ta.length) return;
                updateTemplatePreview($ta.closest('.sc-template-item'));
            });
        });
    }
    bindExistingTinyMcePreview();
    setInterval(bindExistingTinyMcePreview, 1500);

    $(document).on('click', '.sc-cert-var-btn', function(e){
        e.preventDefault();
        const variable = $(this).data('var');
        const card = $(this).closest('.sc-certificate-template-item');
        const templateKey = card.attr('data-template-key');
        const editorId = 'msg_' + scSanitizeKey(templateKey);
        const textarea = document.getElementById(editorId) || card.find('textarea.sc-certificate-message-text').first().get(0);
        if (!textarea) {
            return;
        }
        if (window.tinymce && editorId) {
            const ed = window.tinymce.get(editorId);
            if (ed && !ed.isHidden()) {
                ed.focus();
                ed.execCommand('mceInsertContent', false, variable);
                updateTemplatePreview(card);
                return;
            }
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
        updateTemplatePreview(card);
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
                updateTemplatePreview(button.closest('.sc-template-item'));
            }
        });
        frame.open();
    });

    container.find('.sc-template-item').each(function(){
        updateTemplatePreview(this);
    });

    if (window.tinymce) {
        window.tinymce.on('AddEditor', function(e){
            const editor = e.editor;
            const $textarea = $('#' + editor.id);
            if (!$textarea.length) return;
            const $card = $textarea.closest('.sc-template-item');
            editor.on('input keyup change SetContent', function(){
                updateTemplatePreview($card);
            });
        });
    }
});
</script>
