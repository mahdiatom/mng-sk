<?php
if (!defined('ABSPATH')) {
    exit;
}

$tpl_key = isset($template['key']) ? (string) $template['key'] : '';
$card_presets = sc_users_export_get_card_size_presets();
$card_preset = isset($template['card_size_preset']) ? (string) $template['card_size_preset'] : 'id_card';
if (!isset($card_presets[$card_preset])) {
    $card_preset = 'id_card';
}
$layout_columns_count = max(1, min(4, (int) ($template['layout_columns_count'] ?? ($template['columns_count'] ?? 2))));
$column_paddings = sc_users_export_normalize_column_paddings($template['column_paddings'] ?? [], $layout_columns_count);
$column_widths = sc_users_export_normalize_column_widths($template['column_widths'] ?? [], $layout_columns_count);
$default_col_width = round(100 / $layout_columns_count, 2);
$image_style = isset($template['image_style']) && in_array($template['image_style'], ['circle', 'rounded'], true)
    ? $template['image_style']
    : 'rounded';
$image_size_defaults = sc_users_export_get_default_image_sizes();
$image_sizes = sc_users_export_normalize_image_sizes($template['image_sizes'] ?? []);
$image_field_labels = [
    'personal_photo' => 'عکس پرسنلی',
    'id_card_photo' => 'عکس کارت ملی',
    'sport_insurance_photo' => 'عکس بیمه ورزشی',
    'attendance_qr' => 'QR حضور و غیاب',
];
$tpl_selected_image_fields = array_values(array_intersect(
    (array) ($template['fields'] ?? []),
    array_keys($image_field_labels)
));
?>
<div class="sc-row">
    <label>اندازه کارت</label>
    <select class="sc-card-size-preset" name="templates[<?php echo esc_attr($tpl_key); ?>][card_size_preset]">
        <?php foreach ($card_presets as $preset_key => $preset) : ?>
            <option value="<?php echo esc_attr($preset_key); ?>" <?php selected($card_preset, $preset_key); ?>><?php echo esc_html($preset['label']); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="sc-row sc-card-size-custom-row" style="<?php echo $card_preset === 'custom' ? '' : 'display:none;'; ?>">
    <label>ابعاد سفارشی (سانتی‌متر)</label>
    <div class="sc-padding-grid sc-card-size-custom-grid">
        <div class="sc-padding-item">
            <small>عرض</small>
            <input type="number" class="sc-card-width-cm" min="1" max="30" step="0.1" name="templates[<?php echo esc_attr($tpl_key); ?>][card_width_cm]" value="<?php echo esc_attr((string) ($template['card_width_cm'] ?? 8.5)); ?>">
        </div>
        <div class="sc-padding-item">
            <small>ارتفاع</small>
            <input type="number" class="sc-card-height-cm" min="1" max="30" step="0.1" name="templates[<?php echo esc_attr($tpl_key); ?>][card_height_cm]" value="<?php echo esc_attr((string) ($template['card_height_cm'] ?? 5.4)); ?>">
        </div>
    </div>
</div>
<div class="sc-row">
    <label>فاصله داخلی کارت (میلی‌متر)</label>
    <div class="sc-padding-grid">
        <div class="sc-padding-item">
            <small>بالا</small>
            <input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][card_padding_top]" value="<?php echo esc_attr((string) ($template['card_padding_top'] ?? 3)); ?>">
        </div>
        <div class="sc-padding-item">
            <small>راست</small>
            <input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][card_padding_right]" value="<?php echo esc_attr((string) ($template['card_padding_right'] ?? 3)); ?>">
        </div>
        <div class="sc-padding-item">
            <small>پایین</small>
            <input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][card_padding_bottom]" value="<?php echo esc_attr((string) ($template['card_padding_bottom'] ?? 3)); ?>">
        </div>
        <div class="sc-padding-item">
            <small>چپ</small>
            <input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][card_padding_left]" value="<?php echo esc_attr((string) ($template['card_padding_left'] ?? 3)); ?>">
        </div>
    </div>
</div>
<div class="sc-row">
    <label>استایل تصاویر</label>
    <select class="sc-image-style-select" name="templates[<?php echo esc_attr($tpl_key); ?>][image_style]">
        <option value="rounded" <?php selected($image_style, 'rounded'); ?>>مربع با گوشه‌های گرد (۱۰px)</option>
        <option value="circle" <?php selected($image_style, 'circle'); ?>>دایره‌ای</option>
    </select>
    <p class="description">برای عکس پرسنلی، کارت ملی، بیمه ورزشی و QR در صورت انتخاب در قالب.</p>
</div>
<div class="sc-row">
    <label>اندازه فونت محتوا (پیکسل)</label>
    <input type="number" class="sc-content-font-size-input" min="8" max="32" step="1" name="templates[<?php echo esc_attr($tpl_key); ?>][content_font_size]" value="<?php echo esc_attr((string) ($template['content_font_size'] ?? 13)); ?>">
</div>
<div class="sc-row sc-image-sizes-section">
    <label>ابعاد تصاویر انتخاب‌شده (پیکسل)</label>
    <p class="description">فقط برای فیلدهای تصویری که در قالب فعال هستند نمایش داده می‌شود.</p>
    <div class="sc-image-sizes-wrap">
        <?php foreach ($image_field_labels as $img_field => $img_label) :
            $size = $image_sizes[$img_field] ?? $image_size_defaults[$img_field];
            $is_selected = in_array($img_field, $tpl_selected_image_fields, true);
            ?>
            <div class="sc-image-size-group" data-image-field="<?php echo esc_attr($img_field); ?>" style="<?php echo $is_selected ? '' : 'display:none;'; ?>">
                <h4><?php echo esc_html($img_label); ?></h4>
                <div class="sc-padding-grid sc-image-size-grid">
                    <div class="sc-padding-item">
                        <small>عرض</small>
                        <input type="number" class="sc-image-width-input" min="20" max="600" step="1" name="templates[<?php echo esc_attr($tpl_key); ?>][image_sizes][<?php echo esc_attr($img_field); ?>][width]" value="<?php echo esc_attr((string) $size['width']); ?>">
                    </div>
                    <div class="sc-padding-item">
                        <small>ارتفاع</small>
                        <input type="number" class="sc-image-height-input" min="20" max="600" step="1" name="templates[<?php echo esc_attr($tpl_key); ?>][image_sizes][<?php echo esc_attr($img_field); ?>][height]" value="<?php echo esc_attr((string) $size['height']); ?>">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="sc-row">
    <label>CSS سفارشی</label>
    <textarea class="sc-export-custom-css-input" name="templates[<?php echo esc_attr($tpl_key); ?>][custom_css]" rows="8" placeholder="مثال: .sc-card { border-radius: 16px; }"><?php echo esc_textarea($template['custom_css'] ?? ''); ?></textarea>
    <p class="description">روی پیش‌نمایش و خروجی چاپ/PDF اعمال می‌شود. از تگ style یا script استفاده نکنید.</p>
</div>
<div class="sc-column-paddings-section">
    <label>تنظیمات هر ستون چیدمان</label>
    <p class="description">عرض نسبی (درصد) و فاصله داخلی هر ستون — مجموع عرض‌ها به‌صورت نسبی اعمال می‌شود.</p>
    <div class="sc-column-paddings-wrap" data-template-key="<?php echo esc_attr($tpl_key); ?>">
        <?php for ($col_i = 1; $col_i <= $layout_columns_count; $col_i++) :
            $col_key = 'column_' . $col_i;
            $col_pad = $column_paddings[$col_key] ?? ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
            $col_width = $column_widths[$col_key] ?? $default_col_width;
            ?>
            <div class="sc-column-padding-group" data-column="<?php echo esc_attr($col_key); ?>">
                <h4>ستون <?php echo (int) $col_i; ?></h4>
                <div class="sc-padding-grid sc-column-settings-grid">
                    <div class="sc-padding-item sc-column-width-item">
                        <small>عرض (درصد نسبی)</small>
                        <input type="number" class="sc-column-width-input" min="5" max="95" step="1" name="templates[<?php echo esc_attr($tpl_key); ?>][column_widths][<?php echo esc_attr($col_key); ?>]" value="<?php echo esc_attr((string) $col_width); ?>">
                    </div>
                    <div class="sc-padding-item">
                        <small>فاصله بالا (mm)</small>
                        <input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][column_paddings][<?php echo esc_attr($col_key); ?>][top]" value="<?php echo esc_attr((string) $col_pad['top']); ?>">
                    </div>
                    <div class="sc-padding-item">
                        <small>فاصله راست (mm)</small>
                        <input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][column_paddings][<?php echo esc_attr($col_key); ?>][right]" value="<?php echo esc_attr((string) $col_pad['right']); ?>">
                    </div>
                    <div class="sc-padding-item">
                        <small>فاصله پایین (mm)</small>
                        <input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][column_paddings][<?php echo esc_attr($col_key); ?>][bottom]" value="<?php echo esc_attr((string) $col_pad['bottom']); ?>">
                    </div>
                    <div class="sc-padding-item">
                        <small>فاصله چپ (mm)</small>
                        <input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[<?php echo esc_attr($tpl_key); ?>][column_paddings][<?php echo esc_attr($col_key); ?>][left]" value="<?php echo esc_attr((string) $col_pad['left']); ?>">
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>
