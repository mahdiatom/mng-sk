<?php
if (!defined('ABSPATH')) {
    exit;
}

$fields = sc_metric_get_fields(true);
$today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
$date_filters = sc_metric_parse_date_filters_from_request();

$chart_field_ids = [];
if (isset($_REQUEST['chart_field_ids'])) {
    $chart_field_ids = array_map('absint', (array) wp_unslash($_REQUEST['chart_field_ids']));
}
if (empty($chart_field_ids)) {
    foreach ($fields as $field) {
        if (sc_metric_field_is_chartable($field)) {
            $chart_field_ids[] = (int) $field->id;
        }
    }
}

$message = '';
$message_type = '';

if (isset($_POST['sc_save_daily_metric']) && check_admin_referer('sc_save_daily_metric', 'sc_save_daily_metric_nonce')) {
    $entry_shamsi = isset($_POST['entry_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['entry_date_shamsi'])) : $today_shamsi;
    $entry_date = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($entry_shamsi) : '';
    $field_id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;
    $value = isset($_POST['field_value']) ? wp_unslash($_POST['field_value']) : '';

    $result = sc_metric_save_entry($player->id, $field_id, $entry_date, $value);
    $message = $result['message'] ?? '';
    $message_type = !empty($result['success']) ? 'success' : 'error';
}

$recent_entries = sc_metric_get_member_entries($player->id, [
    'date_from' => $date_filters['from'],
    'date_to'   => $date_filters['to'],
    'field_ids' => $chart_field_ids,
]);

$chart_data = sc_metric_get_chart_data($player->id, $chart_field_ids, $date_filters['from'], $date_filters['to']);
$fields_js = sc_metric_fields_for_js($fields);
$chartable_fields = array_values(array_filter($fields, 'sc_metric_field_is_chartable'));
$metrics_page_url = function_exists('sc_panel_endpoint_url')
    ? sc_panel_endpoint_url('sc-daily-metrics')
    : (function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-daily-metrics') : '');

$entries_count = count($recent_entries);
$unique_dates = [];
foreach ($recent_entries as $entry) {
    $unique_dates[$entry->entry_date] = true;
}
$unique_dates_count = count($unique_dates);
?>

<div class="sc-daily-metrics-page sc-account-list-page">
    <div class="sc-invoices-page-header sc-daily-metrics-page-header">
        <div class="sc-invoices-page-icon">📊</div>
        <div>
            <h2 class="sc-invoices-page-title">ثبت اطلاعات</h2>
            <p class="sc-invoices-page-subtitle">اطلاعات روزانه خود را ثبت کنید و روند تغییرات را در نمودار پیگیری نمایید.</p>
        </div>
    </div>

    <?php if ($message) : ?>
        <div class="woocommerce-message woocommerce-message--<?php echo esc_attr($message_type); ?> woocommerce-<?php echo esc_attr($message_type); ?> sc-invoices-notice sc-daily-metrics-notice">
            <?php echo esc_html($message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($fields)) : ?>
        <div class="sc-invoices-empty sc-daily-metrics-empty-state">
            هنوز فیلدی برای ثبت اطلاعات تعریف نشده است. لطفاً با مدیر باشگاه تماس بگیرید.
        </div>
    <?php else : ?>

    <?php if ($entries_count > 0) : ?>
    <div class="sc-daily-metrics-quick-stats">
        <div class="sc-daily-metrics-quick-stat">
            <span class="sc-daily-metrics-quick-stat-value"><?php echo (int) $entries_count; ?></span>
            <span class="sc-daily-metrics-quick-stat-label">ثبت در بازه</span>
        </div>
        <div class="sc-daily-metrics-quick-stat">
            <span class="sc-daily-metrics-quick-stat-value"><?php echo (int) $unique_dates_count; ?></span>
            <span class="sc-daily-metrics-quick-stat-label">روز ثبت‌شده</span>
        </div>
        <div class="sc-daily-metrics-quick-stat">
            <span class="sc-daily-metrics-quick-stat-value"><?php echo count($fields); ?></span>
            <span class="sc-daily-metrics-quick-stat-label">فیلد فعال</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="sc-daily-metrics-entry-card">
        <div class="sc-daily-metrics-section-head">
            <span class="sc-daily-metrics-section-icon" aria-hidden="true">✏️</span>
            <div>
                <h3 class="sc-daily-metrics-section-title">ثبت اطلاعات روزانه</h3>
                <p class="sc-daily-metrics-section-desc">تاریخ، نوع فیلد و مقدار را انتخاب کنید.</p>
            </div>
        </div>

        <form method="post" class="sc-daily-metrics-entry-form">
            <?php wp_nonce_field('sc_save_daily_metric', 'sc_save_daily_metric_nonce'); ?>

            <div class="sc-daily-metrics-entry-grid">
                <div class="sc-daily-metrics-filter-field">
                    <label for="entry_date_shamsi">تاریخ</label>
                    <input type="text" id="entry_date_shamsi" name="entry_date_shamsi" class="persian-date-input sc-daily-metrics-control" value="<?php echo esc_attr($today_shamsi); ?>" readonly>
                </div>

                <div class="sc-daily-metrics-filter-field">
                    <label for="metric_field_id">نوع فیلد</label>
                    <select id="metric_field_id" name="field_id" class="sc-daily-metrics-control" required>
                        <option value="">انتخاب کنید...</option>
                        <?php foreach ($fields as $field) : ?>
                            <option value="<?php echo esc_attr($field->id); ?>">
                                <?php echo esc_html($field->title); ?>
                                <?php if (!empty($field->unit)) : ?>
                                    (<?php echo esc_html($field->unit); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-daily-metrics-filter-field sc-daily-metrics-value-field">
                    <label for="metric_field_value">مقدار</label>
                    <div id="metric_value_container">
                        <input type="text" id="metric_field_value" name="field_value" class="sc-daily-metrics-control sc-daily-metrics-value-input" placeholder="ابتدا نوع فیلد را انتخاب کنید" disabled>
                    </div>
                </div>
            </div>

            <div class="sc-daily-metrics-entry-actions">
                <button type="submit" name="sc_save_daily_metric" class="button button-primary sc-daily-metrics-btn-primary">ثبت اطلاعات</button>
            </div>
        </form>
    </div>

    <div class="sc-daily-metrics-chart-card">
        <div class="sc-daily-metrics-section-head sc-daily-metrics-section-head--chart">
            <span class="sc-daily-metrics-section-icon" aria-hidden="true">📈</span>
            <div>
                <h3 class="sc-daily-metrics-section-title">نمودار روند</h3>
                <p class="sc-daily-metrics-section-desc">روند تغییرات فیلدهای عددی در بازه زمانی انتخاب‌شده</p>
            </div>
        </div>

        <?php
        $selected_labels = [];
        foreach ($chartable_fields as $field) {
            if (in_array((int) $field->id, $chart_field_ids, true)) {
                $label = $field->title;
                if (!empty($field->unit)) {
                    $label .= ' (' . $field->unit . ')';
                }
                $selected_labels[] = $label;
            }
        }
        $selected_count = count($selected_labels);
        $filters_open = false;
        ?>
        <div class="sc-daily-metrics-filters-shell<?php echo $filters_open ? ' is-open' : ''; ?>" id="scChartFiltersShell">
            <button type="button"
                    class="sc-daily-metrics-filters-toggle"
                    id="scChartFiltersToggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="scChartFiltersBody">
                <span class="sc-daily-metrics-filters-toggle-title" data-label-open="بستن فیلتر" data-label-closed="مشاهده فیلتر">
                    مشاهده فیلتر
                </span>
                <span class="sc-daily-metrics-filters-badge"><?php echo (int) $selected_count; ?></span>
            </button>

            <div class="sc-daily-metrics-filters-body" id="scChartFiltersBody" <?php echo $filters_open ? '' : 'hidden'; ?>>
                <form method="get" action="<?php echo esc_url($metrics_page_url); ?>" class="sc-daily-metrics-chart-filters">
                    <div class="sc-daily-metrics-chart-filters-grid">
                        <div class="sc-daily-metrics-filter-field sc-daily-metrics-chart-dates">
                            <label for="filter_date_from_shamsi">بازه تاریخ</label>
                            <div class="sc-daily-metrics-date-range">
                                <input type="text" id="filter_date_from_shamsi" name="filter_date_from_shamsi" class="persian-date-input sc-no-default-date sc-daily-metrics-control" placeholder="از تاریخ" value="<?php echo esc_attr($date_filters['from_shamsi']); ?>" readonly>
                                <span class="sc-daily-metrics-date-sep">تا</span>
                                <input type="text" id="filter_date_to_shamsi" name="filter_date_to_shamsi" class="persian-date-input sc-no-default-date sc-daily-metrics-control" placeholder="تا تاریخ" value="<?php echo esc_attr($date_filters['to_shamsi']); ?>" readonly>
                            </div>
                        </div>

                        <?php if (!empty($chartable_fields)) : ?>
                        <div class="sc-daily-metrics-filter-field sc-daily-metrics-fields-picker-wrap">
                            <label>فیلدهای نمودار</label>
                            <div class="sc-daily-metrics-fields-accordion" id="scChartFieldsAccordion">
                                <button type="button"
                                        class="sc-daily-metrics-fields-trigger sc-daily-metrics-control"
                                        id="scChartFieldsTrigger"
                                        aria-expanded="false"
                                        aria-controls="scChartFieldsPanel">
                                    <span class="sc-daily-metrics-fields-trigger-inner">
                                        <span class="sc-daily-metrics-fields-summary" id="scChartFieldsSummary">
                                            <?php
                                            if ($selected_count === 0) {
                                                echo 'انتخاب فیلدها...';
                                            } elseif ($selected_count === 1) {
                                                echo esc_html($selected_labels[0]);
                                            } elseif ($selected_count <= 2) {
                                                echo esc_html(implode('، ', $selected_labels));
                                            } else {
                                                echo esc_html($selected_labels[0] . '، ' . $selected_labels[1] . ' و ' . ($selected_count - 2) . ' مورد دیگر');
                                            }
                                            ?>
                                        </span>
                                        <span class="sc-daily-metrics-fields-count" id="scChartFieldsCount"><?php echo (int) $selected_count; ?></span>
                                    </span>
                                    <span class="sc-daily-metrics-fields-chevron" aria-hidden="true"></span>
                                </button>

                                <div class="sc-daily-metrics-fields-panel" id="scChartFieldsPanel" hidden>
                                    <div class="sc-daily-metrics-fields-panel-toolbar">
                                        <span class="sc-daily-metrics-fields-panel-hint">فقط فیلدهای عددی</span>
                                        <span class="sc-daily-metrics-fields-panel-actions">
                                            <button type="button" class="sc-daily-metrics-fields-action" data-action="all">انتخاب همه</button>
                                            <button type="button" class="sc-daily-metrics-fields-action" data-action="none">پاک کردن</button>
                                        </span>
                                    </div>
                                    <ul class="sc-daily-metrics-fields-list">
                                        <?php foreach ($chartable_fields as $field) :
                                            $is_checked = in_array((int) $field->id, $chart_field_ids, true);
                                            $option_label = $field->title;
                                            if (!empty($field->unit)) {
                                                $option_label .= ' (' . $field->unit . ')';
                                            }
                                        ?>
                                            <li>
                                                <label class="sc-daily-metrics-field-option">
                                                    <input type="checkbox"
                                                           class="sc-daily-metrics-field-checkbox"
                                                           name="chart_field_ids[]"
                                                           value="<?php echo esc_attr($field->id); ?>"
                                                           data-label="<?php echo esc_attr($option_label); ?>"
                                                           <?php checked($is_checked); ?>>
                                                    <span class="sc-daily-metrics-field-option-box" aria-hidden="true"></span>
                                                    <span class="sc-daily-metrics-field-option-text"><?php echo esc_html($option_label); ?></span>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="sc-daily-metrics-chart-filter-actions">
                        <button type="submit" class="button button-primary sc-daily-metrics-btn-primary">اعمال فیلتر</button>
                        <a href="<?php echo esc_url($metrics_page_url); ?>" class="button sc-daily-metrics-btn-secondary">پاک کردن</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($chartable_fields)) : ?>
            <div class="sc-invoices-empty sc-daily-metrics-chart-empty">
                برای نمایش نمودار، حداقل یک فیلد از نوع «عدد» تعریف کنید.
            </div>
        <?php else : ?>
            <div class="sc-daily-metrics-chart-wrap">
                <canvas id="scDailyMetricsChart" height="140"></canvas>
            </div>
            <?php if (empty($chart_data['datasets'])) : ?>
                <p class="sc-daily-metrics-chart-no-data">در بازه انتخاب‌شده داده‌ای برای نمایش وجود ندارد.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($recent_entries)) : ?>
    <div class="sc-daily-metrics-history-card">
        <div class="sc-daily-metrics-section-head">
            <span class="sc-daily-metrics-section-icon" aria-hidden="true">📋</span>
            <div>
                <h3 class="sc-daily-metrics-section-title">سوابق ثبت‌شده</h3>
                <p class="sc-daily-metrics-section-desc">لیست ثبت‌های شما در بازه فیلتر شده</p>
            </div>
        </div>
        <div class="sc-invoices-table-wrap sc-daily-metrics-table-wrap">
            <table class="sc-invoices-table sc-daily-metrics-table">
                <thead>
                    <tr>
                        <th><span>تاریخ</span></th>
                        <th><span>فیلد</span></th>
                        <th><span>مقدار</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($recent_entries) as $entry) : ?>
                        <tr>
                            <td data-label="تاریخ"><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($entry->entry_date) : $entry->entry_date); ?></td>
                            <td data-label="فیلد">
                                <span class="sc-daily-metrics-field-pill"><?php echo esc_html($entry->field_title); ?></span>
                            </td>
                            <td data-label="مقدار">
                                <strong class="sc-daily-metrics-value-display">
                                <?php
                                if ($entry->field_type === 'number' && $entry->value_numeric !== null) {
                                    echo esc_html(rtrim(rtrim(number_format((float) $entry->value_numeric, 4, '.', ''), '0'), '.'));
                                    if (!empty($entry->unit)) {
                                        echo ' <span class="sc-daily-metrics-unit">' . esc_html($entry->unit) . '</span>';
                                    }
                                } else {
                                    echo esc_html($entry->value_text);
                                }
                                ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
jQuery(function($) {
    var fieldsData = <?php echo wp_json_encode($fields_js, JSON_UNESCAPED_UNICODE); ?>;

    function renderValueInput(fieldId) {
        var $container = $('#metric_value_container');
        $container.empty();
        var field = fieldsData.find(function(f) { return String(f.id) === String(fieldId); });
        if (!field) {
            $container.html('<input type="text" id="metric_field_value" name="field_value" class="sc-daily-metrics-control sc-daily-metrics-value-input" placeholder="ابتدا نوع فیلد را انتخاب کنید" disabled>');
            return;
        }

        if (field.field_type === 'select' && field.options && field.options.length) {
            var $select = $('<select id="metric_field_value" name="field_value" class="sc-daily-metrics-control sc-daily-metrics-value-input" required></select>');
            $select.append('<option value="">انتخاب کنید...</option>');
            field.options.forEach(function(opt) {
                $select.append($('<option></option>').val(opt).text(opt));
            });
            $container.append($select);
        } else if (field.field_type === 'number') {
            var placeholder = field.unit ? 'مثلاً 75 (' + field.unit + ')' : 'مقدار عددی';
            $container.append('<input type="number" step="any" id="metric_field_value" name="field_value" class="sc-daily-metrics-control sc-daily-metrics-value-input" required placeholder="' + placeholder + '">');
        } else {
            $container.append('<input type="text" id="metric_field_value" name="field_value" class="sc-daily-metrics-control sc-daily-metrics-value-input" required placeholder="مقدار را وارد کنید">');
        }
    }

    $('#metric_field_id').on('change', function() {
        renderValueInput($(this).val());
    });

    (function initChartFiltersShell() {
        var $shell = $('#scChartFiltersShell');
        if (!$shell.length) {
            return;
        }
        var $toggle = $('#scChartFiltersToggle');
        var $body = $('#scChartFiltersBody');
        var $title = $toggle.find('.sc-daily-metrics-filters-toggle-title');

        function setFiltersOpen(open) {
            $shell.toggleClass('is-open', open);
            $toggle.attr('aria-expanded', open ? 'true' : 'false');
            $body.prop('hidden', !open);
            $title.text(open ? $title.data('label-open') : $title.data('label-closed'));
        }

        $toggle.on('click', function(e) {
            e.preventDefault();
            setFiltersOpen(!$shell.hasClass('is-open'));
        });
    })();

    (function initChartFieldsAccordion() {
        var $accordion = $('#scChartFieldsAccordion');
        if (!$accordion.length) {
            return;
        }

        var $trigger = $('#scChartFieldsTrigger');
        var $panel = $('#scChartFieldsPanel');
        var $summary = $('#scChartFieldsSummary');
        var $count = $('#scChartFieldsCount');
        var $checkboxes = $panel.find('.sc-daily-metrics-field-checkbox');

        function formatSummary(selected) {
            if (!selected.length) {
                return 'انتخاب فیلدها...';
            }
            if (selected.length === 1) {
                return selected[0];
            }
            if (selected.length === 2) {
                return selected[0] + '، ' + selected[1];
            }
            return selected[0] + '، ' + selected[1] + ' و ' + (selected.length - 2) + ' مورد دیگر';
        }

        function updateSummary() {
            var selected = [];
            $checkboxes.filter(':checked').each(function() {
                selected.push($(this).data('label'));
            });
            $summary.text(formatSummary(selected));
            $count.text(selected.length);
            $count.toggle(selected.length > 0);
        }

        function setOpen(open) {
            $accordion.toggleClass('is-open', open);
            $trigger.attr('aria-expanded', open ? 'true' : 'false');
            $panel.prop('hidden', !open);
        }

        $trigger.on('click', function(e) {
            e.preventDefault();
            setOpen(!$accordion.hasClass('is-open'));
        });

        $checkboxes.on('change', updateSummary);

        $panel.on('click', '.sc-daily-metrics-fields-action', function() {
            var action = $(this).data('action');
            $checkboxes.prop('checked', action === 'all');
            updateSummary();
        });

        $(document).on('click', function(e) {
            if (!$accordion.hasClass('is-open')) {
                return;
            }
            if (!$(e.target).closest('#scChartFieldsAccordion').length) {
                setOpen(false);
            }
        });

        updateSummary();
    })();

    <?php if (!empty($chart_data['datasets'])) : ?>
    if (typeof Chart !== 'undefined') {
        var chartData = <?php echo wp_json_encode($chart_data, JSON_UNESCAPED_UNICODE); ?>;
        var canvas = document.getElementById('scDailyMetricsChart');
        if (canvas) {
            var ctx = canvas.getContext('2d');
            var palette = [
                { border: 'rgb(109, 52, 255)', fill: 'rgba(109, 52, 255, 0.18)' },
                { border: 'rgb(34, 197, 94)', fill: 'rgba(34, 197, 94, 0.15)' },
                { border: 'rgb(249, 115, 22)', fill: 'rgba(249, 115, 22, 0.15)' },
                { border: 'rgb(236, 72, 153)', fill: 'rgba(236, 72, 153, 0.15)' },
                { border: 'rgb(14, 165, 233)', fill: 'rgba(14, 165, 233, 0.15)' }
            ];

            chartData.datasets = chartData.datasets.map(function(ds, i) {
                var color = palette[i % palette.length];
                var gradient = ctx.createLinearGradient(0, 0, 0, 320);
                gradient.addColorStop(0, color.fill);
                gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
                return Object.assign({}, ds, {
                    borderColor: color.border,
                    backgroundColor: gradient,
                    tension: 0.42,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: color.border,
                    pointBorderWidth: 2
                });
            });

            new Chart(canvas, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: { usePointStyle: true, padding: 16, font: { size: 13, weight: '600' } }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 27, 46, 0.92)',
                            padding: 12,
                            cornerRadius: 10
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(109, 52, 255, 0.06)' },
                            ticks: { font: { size: 12 } }
                        },
                        y: {
                            beginAtZero: false,
                            grid: { color: 'rgba(109, 52, 255, 0.08)' },
                            ticks: { font: { size: 12 } }
                        }
                    }
                }
            });
        }
    }
    <?php endif; ?>
});
</script>
