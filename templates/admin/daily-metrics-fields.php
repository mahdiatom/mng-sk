<?php
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

$message = '';
$message_type = '';

if (isset($_POST['sc_save_metric_fields']) && check_admin_referer('sc_save_metric_fields', 'sc_save_metric_fields_nonce')) {
    $raw_fields = isset($_POST['fields']) ? (array) wp_unslash($_POST['fields']) : [];
    $fields_to_save = [];
    foreach ($raw_fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $title = isset($field['title']) ? trim((string) $field['title']) : '';
        if ($title === '') {
            continue;
        }
        $fields_to_save[] = [
            'id'           => isset($field['id']) ? absint($field['id']) : 0,
            'title'        => $title,
            'field_type'   => isset($field['field_type']) ? sanitize_key($field['field_type']) : 'number',
            'unit'         => isset($field['unit']) ? sanitize_text_field($field['unit']) : '',
            'options_text' => isset($field['options_text']) ? sanitize_textarea_field($field['options_text']) : '',
            'is_active'    => !empty($field['is_active']) ? 1 : 0,
        ];
    }

    $result = sc_metric_save_fields($fields_to_save);
    if (!empty($result['success'])) {
        $message = 'فیلدها با موفقیت ذخیره شد.';
        $message_type = 'success';
    } else {
        $message = isset($result['message']) ? $result['message'] : 'خطا در ذخیره فیلدها.';
        $message_type = 'error';
    }
}

$fields = sc_metric_get_fields();
$field_types = sc_metric_get_field_types();
$total_fields = count($fields);
$active_fields = 0;
$chartable_fields = 0;
foreach ($fields as $field) {
    if ((int) $field->is_active) {
        $active_fields++;
    }
    if (sc_metric_field_is_chartable($field)) {
        $chartable_fields++;
    }
}
?>

<div class="wrap sc-reports-list-wrap sc-daily-metrics-admin-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">تعریف فیلدهای ثبت اطلاعات</h1>
            <p class="sc-reports-list-desc">فیلدهایی که کاربران باید روزانه ثبت کنند (مثل قد، وزن و ...) را اینجا تعریف کنید. فیلدهای نوع «عدد» در نمودار منحنی نمایش داده می‌شوند.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <button type="button" class="sc-reports-list-add-btn" id="sc-metric-add-row">+ افزودن فیلد</button>
        </div>
    </div>

    <div class="sc-daily-metrics-admin-stats">
        <div class="sc-daily-metrics-stat-card">
            <span class="sc-daily-metrics-stat-value"><?php echo (int) $total_fields; ?></span>
            <span class="sc-daily-metrics-stat-label">کل فیلدها</span>
        </div>
        <div class="sc-daily-metrics-stat-card sc-daily-metrics-stat-card--active">
            <span class="sc-daily-metrics-stat-value"><?php echo (int) $active_fields; ?></span>
            <span class="sc-daily-metrics-stat-label">فیلد فعال</span>
        </div>
        <div class="sc-daily-metrics-stat-card sc-daily-metrics-stat-card--chart">
            <span class="sc-daily-metrics-stat-value"><?php echo (int) $chartable_fields; ?></span>
            <span class="sc-daily-metrics-stat-label">قابل نمودار (عددی)</span>
        </div>
    </div>

    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type === 'error' ? 'error' : 'success'); ?> is-dismissible sc-daily-metrics-admin-notice">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" id="sc-metric-fields-form">
        <?php wp_nonce_field('sc_save_metric_fields', 'sc_save_metric_fields_nonce'); ?>

        <div class="sc-reports-list-table-card sc-daily-metrics-fields-card">
            <div class="sc-daily-metrics-card-head">
                <h2>فیلدهای تعریف‌شده</h2>
                <p class="sc-reports-note">هر فیلد را با عنوان، نوع و در صورت نیاز واحد اندازه‌گیری مشخص کنید.</p>
            </div>

            <div class="sc-daily-metrics-table-scroll">
                <table class="widefat sc-metric-fields-table">
                    <thead>
                        <tr>
                            <th>عنوان فیلد</th>
                            <th>نوع</th>
                            <th>واحد</th>
                            <th>گزینه‌ها</th>
                            <th class="sc-metric-col-center">فعال</th>
                            <th class="sc-metric-col-center">حذف</th>
                        </tr>
                    </thead>
                    <tbody id="sc-metric-fields-rows">
                        <?php if (!empty($fields)) : ?>
                            <?php foreach ($fields as $index => $field) : ?>
                                <?php
                                $options = sc_metric_decode_options($field->options_json);
                                $options_text = implode("\n", $options);
                                $type_badge = 'muted';
                                if ($field->field_type === 'number') {
                                    $type_badge = 'success';
                                } elseif ($field->field_type === 'select') {
                                    $type_badge = 'warning';
                                }
                                ?>
                                <tr class="sc-metric-field-row">
                                    <td data-label="عنوان فیلد">
                                        <input type="hidden" name="fields[<?php echo (int) $index; ?>][id]" value="<?php echo esc_attr($field->id); ?>">
                                        <input type="text" name="fields[<?php echo (int) $index; ?>][title]" value="<?php echo esc_attr($field->title); ?>" class="sc-metric-input" required placeholder="مثلاً قد">
                                    </td>
                                    <td data-label="نوع">
                                        <select name="fields[<?php echo (int) $index; ?>][field_type]" class="sc-metric-input sc-metric-field-type">
                                            <?php foreach ($field_types as $type_key => $type_label) : ?>
                                                <option value="<?php echo esc_attr($type_key); ?>" <?php selected($field->field_type, $type_key); ?>><?php echo esc_html($type_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td data-label="واحد">
                                        <input type="text" name="fields[<?php echo (int) $index; ?>][unit]" value="<?php echo esc_attr($field->unit); ?>" class="sc-metric-input sc-metric-input--unit" placeholder="cm">
                                    </td>
                                    <td data-label="گزینه‌ها">
                                        <textarea name="fields[<?php echo (int) $index; ?>][options_text]" rows="2" class="sc-metric-input sc-metric-textarea sc-metric-options-input" placeholder="هر خط یک گزینه"><?php echo esc_textarea($options_text); ?></textarea>
                                        <span class="sc-metric-field-hint">فقط برای نوع انتخابی</span>
                                    </td>
                                    <td class="sc-metric-col-center" data-label="فعال">
                                        <label class="sc-metric-toggle">
                                            <input type="checkbox" name="fields[<?php echo (int) $index; ?>][is_active]" value="1" <?php checked((int) $field->is_active, 1); ?>>
                                            <span class="sc-metric-toggle-ui" aria-hidden="true"></span>
                                        </label>
                                    </td>
                                    <td class="sc-metric-col-center" data-label="حذف">
                                        <button type="button" class="button sc-metric-remove-row sc-metric-remove-btn" title="حذف فیلد">×</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="sc-metric-empty-row">
                                <td colspan="6">
                                    <div class="sc-reports-empty sc-daily-metrics-empty">
                                        هنوز فیلدی تعریف نشده است. روی «افزودن فیلد» کلیک کنید.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sc-daily-metrics-admin-footer">
            <button type="submit" name="sc_save_metric_fields" class="button button-primary sc-daily-metrics-save-btn">ذخیره فیلدها</button>
        </div>
    </form>
</div>

<script type="text/html" id="sc-metric-field-row-template">
<tr class="sc-metric-field-row">
    <td data-label="عنوان فیلد">
        <input type="hidden" name="fields[__INDEX__][id]" value="0">
        <input type="text" name="fields[__INDEX__][title]" value="" class="sc-metric-input" required placeholder="مثلاً وزن">
    </td>
    <td data-label="نوع">
        <select name="fields[__INDEX__][field_type]" class="sc-metric-input sc-metric-field-type">
            <?php foreach ($field_types as $type_key => $type_label) : ?>
                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td data-label="واحد">
        <input type="text" name="fields[__INDEX__][unit]" value="" class="sc-metric-input sc-metric-input--unit" placeholder="kg">
    </td>
    <td data-label="گزینه‌ها">
        <textarea name="fields[__INDEX__][options_text]" rows="2" class="sc-metric-input sc-metric-textarea sc-metric-options-input" placeholder="هر خط یک گزینه"></textarea>
        <span class="sc-metric-field-hint">فقط برای نوع انتخابی</span>
    </td>
    <td class="sc-metric-col-center" data-label="فعال">
        <label class="sc-metric-toggle">
            <input type="checkbox" name="fields[__INDEX__][is_active]" value="1" checked>
            <span class="sc-metric-toggle-ui" aria-hidden="true"></span>
        </label>
    </td>
    <td class="sc-metric-col-center" data-label="حذف">
        <button type="button" class="button sc-metric-remove-row sc-metric-remove-btn" title="حذف فیلد">×</button>
    </td>
</tr>
</script>

<script>
jQuery(function($) {
    var rowIndex = $('#sc-metric-fields-rows tr.sc-metric-field-row').length;

    function reindexRows() {
        $('#sc-metric-fields-rows tr.sc-metric-field-row').each(function(i) {
            $(this).find('[name^="fields["]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/fields\[\d+\]/, 'fields[' + i + ']'));
                }
            });
        });
        rowIndex = $('#sc-metric-fields-rows tr.sc-metric-field-row').length;
        toggleEmptyState();
    }

    function toggleEmptyState() {
        var $empty = $('#sc-metric-fields-rows .sc-metric-empty-row');
        if ($('#sc-metric-fields-rows tr.sc-metric-field-row').length === 0) {
            if (!$empty.length) {
                $('#sc-metric-fields-rows').html(
                    '<tr class="sc-metric-empty-row"><td colspan="6"><div class="sc-reports-empty sc-daily-metrics-empty">هنوز فیلدی تعریف نشده است. روی «افزودن فیلد» کلیک کنید.</div></td></tr>'
                );
            }
        } else {
            $empty.remove();
        }
    }

    $('#sc-metric-add-row').on('click', function() {
        var tpl = $('#sc-metric-field-row-template').html().replace(/__INDEX__/g, rowIndex);
        $('#sc-metric-fields-rows .sc-metric-empty-row').remove();
        $('#sc-metric-fields-rows').append(tpl);
        rowIndex++;
    });

    $(document).on('click', '.sc-metric-remove-row', function() {
        $(this).closest('tr').remove();
        reindexRows();
    });
});
</script>
