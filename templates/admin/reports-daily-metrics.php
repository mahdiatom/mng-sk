<?php
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';

$fields = sc_metric_get_fields(true);
$chartable_fields = array_values(array_filter($fields, 'sc_metric_field_is_chartable'));
$date_filters = sc_metric_parse_date_filters_from_request();

$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;

$chart_field_ids = [];
if (isset($_GET['chart_field_ids'])) {
    $chart_field_ids = array_values(array_filter(array_map('absint', (array) wp_unslash($_GET['chart_field_ids']))));
}
if (empty($chart_field_ids) && !isset($_GET['chart_field_ids'])) {
    foreach ($fields as $field) {
        $chart_field_ids[] = (int) $field->id;
    }
}

$all_members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);

$query_args = [
    'date_from' => $date_filters['from'],
    'date_to'   => $date_filters['to'],
];
if ($filter_member > 0) {
    $query_args['member_id'] = $filter_member;
}
if (!empty($chart_field_ids)) {
    $query_args['field_ids'] = $chart_field_ids;
}

$stats = sc_metric_get_admin_stats($query_args);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_items = sc_metric_get_admin_entries(array_merge($query_args, ['count_only' => true]));
$total_pages = max(1, (int) ceil($total_items / $per_page));
$offset = ($current_page - 1) * $per_page;

$entries = sc_metric_get_admin_entries(array_merge($query_args, [
    'limit'  => $per_page,
    'offset' => $offset,
]));

$chart_payloads = sc_metric_get_admin_chart_payloads(array_merge($query_args, [
    'chart_field_ids' => array_values(array_filter(array_map(static function ($fid) use ($chartable_fields) {
        foreach ($chartable_fields as $field) {
            if ((int) $field->id === (int) $fid) {
                return (int) $fid;
            }
        }
        return 0;
    }, $chart_field_ids))),
]));

$base_url = admin_url('admin.php?page=sc-reports-daily-metrics');
$clear_url = $base_url;

$export_url = add_query_arg([
    'page'                     => 'sc-reports-daily-metrics',
    'sc_export'                => 'excel',
    'export_type'              => 'daily_metrics',
    'filter_date_from_shamsi'  => $date_filters['from_shamsi'],
    'filter_date_to_shamsi'    => $date_filters['to_shamsi'],
], admin_url('admin.php'));
if ($filter_member > 0) {
    $export_url = add_query_arg('filter_member', $filter_member, $export_url);
}
foreach ($chart_field_ids as $fid) {
    $export_url = add_query_arg('chart_field_ids[]', $fid, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');

$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($all_members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}

$selected_labels = [];
foreach ($fields as $field) {
    if (in_array((int) $field->id, $chart_field_ids, true)) {
        $label = $field->title;
        if (!empty($field->unit)) {
            $label .= ' (' . $field->unit . ')';
        }
        $selected_labels[] = $label;
    }
}
$selected_count = count($selected_labels);

$active_filters_count = 0;
if ($filter_member > 0) {
    $active_filters_count++;
}
if (!empty($_GET['filter_date_from_shamsi']) || !empty($_GET['filter_date_to_shamsi'])) {
    $active_filters_count++;
}
if (isset($_GET['chart_field_ids'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$has_member_trend = $filter_member > 0 && !empty($chart_payloads['member_trend']['datasets']);
$palette = [
    'rgba(109, 52, 255, 0.75)',
    'rgba(34, 197, 94, 0.75)',
    'rgba(249, 115, 22, 0.75)',
    'rgba(236, 72, 153, 0.75)',
    'rgba(14, 165, 233, 0.75)',
    'rgba(234, 179, 8, 0.75)',
    'rgba(20, 184, 166, 0.75)',
];

$chart_configs = [];
$chart_configs['scDmChartDaily'] = [
    'type' => 'bar',
    'labels' => $chart_payloads['daily_activity']['labels'],
    'datasets' => [[
        'label' => 'تعداد ثبت',
        'data' => $chart_payloads['daily_activity']['counts'],
        'backgroundColor' => 'rgba(109, 52, 255, 0.7)',
        'borderRadius' => 6,
    ]],
];
$chart_configs['scDmChartByField'] = [
    'type' => 'doughnut',
    'labels' => $chart_payloads['by_field']['labels'],
    'datasets' => [[
        'label' => 'تعداد ثبت',
        'data' => $chart_payloads['by_field']['counts'],
        'backgroundColor' => array_slice($palette, 0, max(1, count($chart_payloads['by_field']['counts']))),
    ]],
];
if ($has_member_trend) {
    $chart_configs['scDmChartMemberTrend'] = [
        'type' => 'line',
        'labels' => $chart_payloads['member_trend']['labels'],
        'datasets' => $chart_payloads['member_trend']['datasets'],
        'options' => [
            'scales' => [
                'y' => ['beginAtZero' => false],
            ],
        ],
    ];
}

$pagination_args = [
    'page'                    => 'sc-reports-daily-metrics',
    'filter_date_from_shamsi' => $date_filters['from_shamsi'],
    'filter_date_to_shamsi'   => $date_filters['to_shamsi'],
];
if ($filter_member > 0) {
    $pagination_args['filter_member'] = $filter_member;
}
foreach ($chart_field_ids as $fid) {
    $pagination_args['chart_field_ids'][] = $fid;
}
?>

<div class="wrap sc-reports-list-wrap sc-daily-metrics-report-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">گزارش ثبت اطلاعات</h1>
            <p class="sc-reports-list-desc">مشاهده ثبت‌های روزانه بازیکنان با فیلتر تاریخ، کاربر و فیلد — همراه نمودار روند و آمار.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="sc-reports-list-export-btn">خروجی Excel</a>
        </div>
    </div>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-reports-daily-metrics-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-reports-daily-metrics-filters-panel">
                <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($clear_url); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-reports-list-filters-panel" id="sc-reports-daily-metrics-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-reports-daily-metrics">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
                                <?php echo esc_html($selected_member_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible"
                                     data-value="0"
                                     data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                    همه کاربران
                                </div>
                                <?php
                                $display_count = 0;
                                $max_display = 10;
                                foreach ($all_members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                    $member_label = $member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id;
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member_label); ?>')">
                                        <?php echo esc_html($member_label); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label" for="filter_date_from_shamsi">از تاریخ</label>
                    <input type="text"
                           id="filter_date_from_shamsi"
                           name="filter_date_from_shamsi"
                           value="<?php echo esc_attr($date_filters['from_shamsi']); ?>"
                           class="persian-date-input sc-no-default-date sc-filter-control"
                           readonly>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label" for="filter_date_to_shamsi">تا تاریخ</label>
                    <input type="text"
                           id="filter_date_to_shamsi"
                           name="filter_date_to_shamsi"
                           value="<?php echo esc_attr($date_filters['to_shamsi']); ?>"
                           class="persian-date-input sc-no-default-date sc-filter-control"
                           readonly>
                </div>

                <?php if (!empty($fields)) : ?>
                <div class="sc-filter-field sc-daily-metrics-fields-picker-wrap" style="grid-column: 1 / -1;">
                    <label class="sc-filter-label">فیلدها</label>
                    <div class="sc-daily-metrics-fields-accordion" id="scReportFieldsAccordion">
                        <button type="button"
                                class="sc-daily-metrics-fields-trigger sc-filter-control"
                                id="scReportFieldsTrigger"
                                aria-expanded="false"
                                aria-controls="scReportFieldsPanel">
                            <span class="sc-daily-metrics-fields-trigger-inner">
                                <span class="sc-daily-metrics-fields-summary" id="scReportFieldsSummary">
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
                                <span class="sc-daily-metrics-fields-count" id="scReportFieldsCount"><?php echo (int) $selected_count; ?></span>
                            </span>
                            <span class="sc-daily-metrics-fields-chevron" aria-hidden="true"></span>
                        </button>

                        <div class="sc-daily-metrics-fields-panel" id="scReportFieldsPanel" hidden>
                            <div class="sc-daily-metrics-fields-panel-toolbar">
                                <span class="sc-daily-metrics-fields-panel-hint">فیلتر فیلدهای گزارش و نمودار</span>
                                <span class="sc-daily-metrics-fields-panel-actions">
                                    <button type="button" class="sc-daily-metrics-fields-action" data-action="all">انتخاب همه</button>
                                    <button type="button" class="sc-daily-metrics-fields-action" data-action="none">پاک کردن</button>
                                </span>
                            </div>
                            <ul class="sc-daily-metrics-fields-list">
                                <?php foreach ($fields as $field) :
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

            <div class="sc-reports-list-filters-actions">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($export_url); ?>" class="button button_export">خروجی Excel</a>
                <a href="<?php echo esc_url($clear_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-reports-list-stats">
        <div class="sc-reports-list-stat-card">
            <div class="sc-reports-list-stat-label">تعداد ثبت</div>
            <div class="sc-reports-list-stat-value is-purple"><?php echo (int) $stats['total_entries']; ?></div>
        </div>
        <div class="sc-reports-list-stat-card">
            <div class="sc-reports-list-stat-label">کاربران ثبت‌کننده</div>
            <div class="sc-reports-list-stat-value is-blue"><?php echo (int) $stats['unique_members']; ?></div>
        </div>
        <div class="sc-reports-list-stat-card">
            <div class="sc-reports-list-stat-label">روزهای دارای ثبت</div>
            <div class="sc-reports-list-stat-value"><?php echo (int) $stats['unique_dates']; ?></div>
        </div>
        <div class="sc-reports-list-stat-card">
            <div class="sc-reports-list-stat-label">فیلدهای استفاده‌شده</div>
            <div class="sc-reports-list-stat-value is-credit"><?php echo (int) $stats['unique_fields']; ?></div>
        </div>
    </div>

    <?php if (empty($fields)) : ?>
        <div class="sc-reports-list-table-card">
            <div class="sc-reports-empty">هنوز فیلدی تعریف نشده است. ابتدا از بخش «تعریف فیلدها» فیلدها را بسازید.</div>
        </div>
    <?php else : ?>

    <div class="sc-reports-chart-grid">
        <div class="sc-reports-chart-card sc-bi-chart-wrap">
            <h2>روند تعداد ثبت روزانه</h2>
            <div class="sc-bi-chart-canvas">
                <canvas id="scDmChartDaily"></canvas>
            </div>
        </div>
        <div class="sc-reports-chart-card sc-bi-chart-wrap">
            <h2>توزیع ثبت‌ها بر اساس فیلد</h2>
            <div class="sc-bi-chart-canvas">
                <canvas id="scDmChartByField"></canvas>
            </div>
        </div>
        <?php if ($filter_member > 0) : ?>
        <div class="sc-reports-chart-card sc-bi-chart-wrap" style="grid-column: 1 / -1;">
            <h2>نمودار روند کاربر انتخاب‌شده</h2>
            <div class="sc-bi-chart-canvas" style="min-height: 280px;">
                <canvas id="scDmChartMemberTrend"></canvas>
            </div>
            <?php if (!$has_member_trend) : ?>
                <p class="sc-reports-note" style="text-align:center;padding:8px 16px 20px;">برای نمایش روند، حداقل یک فیلد عددی با داده در بازه انتخاب کنید.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="sc-reports-list-table-card">
        <?php if (empty($entries)) : ?>
            <div class="sc-reports-empty">در بازه و فیلتر انتخاب‌شده ثبتی یافت نشد.</div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>تاریخ</th>
                        <th>کاربر</th>
                        <th>فیلد</th>
                        <th>مقدار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($entries as $index => $entry) :
                        $row_number = $start_number + $index + 1;
                        $full_name = trim(($entry->first_name ?? '') . ' ' . ($entry->last_name ?? ''));
                        $photo = !empty($entry->personal_photo) ? $entry->personal_photo : '';
                        $initials = '';
                        if (!empty($entry->first_name)) {
                            $initials .= mb_substr((string) $entry->first_name, 0, 1);
                        }
                        if (!empty($entry->last_name)) {
                            $initials .= mb_substr((string) $entry->last_name, 0, 1);
                        }
                        if ($initials === '') {
                            $initials = '؟';
                        }
                        $date_label = function_exists('sc_date_shamsi_date_only')
                            ? sc_date_shamsi_date_only($entry->entry_date)
                            : $entry->entry_date;
                    ?>
                        <tr>
                            <td><?php echo (int) $row_number; ?></td>
                            <td><?php echo esc_html($date_label); ?></td>
                            <td>
                                <span class="sc-member-identity">
                                    <?php if ($photo) : ?>
                                        <span class="sc-member-avatar"><img src="<?php echo esc_url($photo); ?>" alt="" loading="lazy"></span>
                                    <?php else : ?>
                                        <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                    <?php endif; ?>
                                    <span class="sc-member-identity-text">
                                        <span class="sc-member-name"><?php echo esc_html($full_name); ?></span>
                                        <?php if (!empty($entry->national_id)) : ?>
                                            <span class="sc-member-meta"><span class="sc-member-meta-item"><?php echo esc_html($entry->national_id); ?></span></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td>
                                <span class="sc-badge sc-badge--purple"><?php echo esc_html($entry->field_title); ?></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html(sc_metric_format_entry_value($entry)); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base'      => add_query_arg(array_merge($pagination_args, ['paged' => '%#%']), admin_url('admin.php')),
                            'format'    => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >',
                            'total'     => $total_pages,
                            'current'   => $current_page,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-reports-daily-metrics-filters-toggle');
    var $panel = $('#sc-reports-daily-metrics-filters-panel');
    var $card = $toggle.closest('.sc-reports-list-filters-card');
    var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });

    (function initReportFieldsAccordion() {
        var $accordion = $('#scReportFieldsAccordion');
        if (!$accordion.length) {
            return;
        }
        var $trigger = $('#scReportFieldsTrigger');
        var $panelFields = $('#scReportFieldsPanel');
        var $summary = $('#scReportFieldsSummary');
        var $count = $('#scReportFieldsCount');
        var $checkboxes = $panelFields.find('.sc-daily-metrics-field-checkbox');

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
            $checkboxes.filter(':checked').each(function () {
                selected.push($(this).data('label'));
            });
            $summary.text(formatSummary(selected));
            $count.text(selected.length);
            $count.toggle(selected.length > 0);
        }

        function setOpen(open) {
            $accordion.toggleClass('is-open', open);
            $trigger.attr('aria-expanded', open ? 'true' : 'false');
            $panelFields.prop('hidden', !open);
        }

        $trigger.on('click', function (e) {
            e.preventDefault();
            setOpen(!$accordion.hasClass('is-open'));
        });

        $checkboxes.on('change', updateSummary);
        $panelFields.on('click', '.sc-daily-metrics-fields-action', function () {
            var action = $(this).data('action');
            $checkboxes.prop('checked', action === 'all');
            updateSummary();
        });

        $(document).on('click', function (e) {
            if (!$accordion.hasClass('is-open')) {
                return;
            }
            if (!$(e.target).closest('#scReportFieldsAccordion').length) {
                setOpen(false);
            }
        });

        updateSummary();
    })();
});

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    function hasChartData(labels, datasets) {
        if (!labels || !labels.length) {
            return false;
        }
        if (!datasets || !datasets.length) {
            return false;
        }
        return datasets.some(function (ds) {
            return Array.isArray(ds.data) && ds.data.length > 0;
        });
    }

    function showEmpty(canvas, message) {
        var wrap = canvas.closest('.sc-bi-chart-canvas') || canvas.parentElement;
        if (wrap) {
            wrap.innerHTML = '<p style="text-align:center;padding:40px 16px;color:#666;">' + message + '</p>';
        }
    }

    var configs = <?php echo wp_json_encode($chart_configs, JSON_UNESCAPED_UNICODE); ?>;
    Object.keys(configs).forEach(function (id) {
        var canvas = document.getElementById(id);
        if (!canvas) {
            return;
        }
        var raw = configs[id];
        if (!hasChartData(raw.labels, raw.datasets)) {
            showEmpty(canvas, 'داده‌ای برای نمایش نمودار وجود ندارد.');
            return;
        }

        var cfg = {
            type: raw.type || 'bar',
            data: {
                labels: raw.labels || [],
                datasets: raw.datasets || []
            },
            options: raw.options || {}
        };
        cfg.options.responsive = true;
        cfg.options.maintainAspectRatio = false;
        cfg.options.plugins = cfg.options.plugins || {};
        cfg.options.plugins.legend = cfg.options.plugins.legend || { position: 'top' };

        if (cfg.type === 'line' || cfg.type === 'bar') {
            cfg.options.scales = cfg.options.scales || {};
            if (!cfg.options.scales.y) {
                cfg.options.scales.y = { beginAtZero: true };
            }
            if (cfg.type === 'line') {
                cfg.data.datasets.forEach(function (ds) {
                    if (typeof ds.tension === 'undefined') {
                        ds.tension = 0.4;
                    }
                    if (typeof ds.fill === 'undefined') {
                        ds.fill = false;
                    }
                    if (typeof ds.spanGaps === 'undefined') {
                        ds.spanGaps = true;
                    }
                    if (typeof ds.pointRadius === 'undefined') {
                        ds.pointRadius = 4;
                    }
                });
            }
        }

        new Chart(canvas, cfg);
    });
});
</script>
