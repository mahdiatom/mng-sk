<?php
if (!defined('ABSPATH')) {
    exit;
}
$is_coach = !empty($GLOBALS['sc_programs_is_coach']);
$report_page = $is_coach ? 'sc-coach-programs-report' : 'sc-programs-report';
$edit_page = $is_coach ? 'sc-coach-program-edit' : 'sc-program-edit';
$list_page = $is_coach ? 'sc-coach-programs' : 'sc-programs';

$program_id = isset($_GET['program_id']) ? absint($_GET['program_id']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$filter_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
$today = sc_program_today_ymd();
$date_from = ($filter_from_shamsi && function_exists('sc_shamsi_to_gregorian_date'))
    ? sc_shamsi_to_gregorian_date($filter_from_shamsi)
    : gmdate('Y-m-d', strtotime($today . ' -13 days'));
$date_to = ($filter_to_shamsi && function_exists('sc_shamsi_to_gregorian_date'))
    ? sc_shamsi_to_gregorian_date($filter_to_shamsi)
    : $today;

if ($program_id > 0) {
    $detail = sc_program_report_detail($program_id);
    if (!$detail) {
        echo '<div class="wrap sc-prog-pro"><div class="notice notice-error"><p>گزارش یافت نشد یا دسترسی ندارید.</p></div>';
        echo '<p><a class="sc_button" href="' . esc_url(admin_url('admin.php?page=' . $report_page)) . '">بازگشت</a></p></div>';
        return;
    }
    $program = $detail['program'];
    $progress = $detail['progress'];
    $progress_all = $detail['progress_all'];
    global $wpdb;
    $m = $wpdb->get_row($wpdb->prepare('SELECT first_name, last_name, national_id FROM ' . $wpdb->prefix . 'sc_members WHERE id = %d', (int) $program->member_id));
    $member_name = $m ? trim(($m->first_name ?: '') . ' ' . ($m->last_name ?: '')) : '';
    $member_label = $member_name !== '' ? $member_name : ('#' . (int) $program->member_id);
    if ($m && !empty($m->national_id)) {
        $member_label .= ' — ' . $m->national_id;
    }
    $start_s = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($program->start_date) : $program->start_date;
    $end_s = !empty($program->end_date) && function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($program->end_date) : ($program->end_date ?: '—');
    $mode_labels = ['fixed' => 'بازه ثابت', 'manual' => 'دستی', 'weekly' => 'هفتگی'];
    $mode_label = $mode_labels[$program->schedule_mode ?? ''] ?? ($program->schedule_mode ?? '—');
    $days_count = count($detail['days']);
    $done_all = 0;
    $total_all = 0;
    foreach ($detail['days'] as $block) {
        foreach ($block['items'] as $row) {
            $total_all++;
            if (!empty($row['done'])) {
                $done_all++;
            }
        }
    }
    ?>
    <div class="wrap sc-members-list-wrap sc-programs-wrap sc-prog-pro sc-prog-report-detail">
        <div class="sc-members-list-header">
            <div class="sc-members-list-header-text">
                <h1 class="sc-members-list-title">گزارش جزئی برنامه</h1>
                <p class="sc-members-list-desc"><?php echo esc_html($program->title); ?> · بازیکن: <?php echo esc_html($member_label); ?></p>
            </div>
            <div class="sc-members-list-header-actions">
                <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $report_page)); ?>">بازگشت به گزارش</a>
                <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page . '&filter_member=' . (int) $program->member_id)); ?>">برنامه‌های عضو</a>
                <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $edit_page . '&program_id=' . $program_id)); ?>">ویرایش برنامه</a>
            </div>
        </div>

        <div class="sc-prog-selected-bar sc-prog-report-meta-bar">
            <div class="sc-prog-report-meta-bar__info">
                <span class="sc-prog-pill"><?php echo esc_html($mode_label); ?></span>
                <span class="sc-prog-pill <?php echo ($program->status ?? '') === 'active' ? '' : 'sc-prog-pill--ghost'; ?>">
                    <?php echo ($program->status ?? '') === 'active' ? 'فعال' : 'غیرفعال'; ?>
                </span>
                <span class="sc-prog-hint">از <?php echo esc_html($start_s); ?> تا <?php echo esc_html($end_s); ?> · <?php echo (int) $days_count; ?> روز</span>
            </div>
        </div>

        <div class="sc-prog-progress-hero sc-prog-report-progress">
            <div class="sc-prog-progress-hero__meta">
                <strong><?php echo (int) $progress['percent']; ?>٪</strong>
                <span>تا امروز</span>
            </div>
            <div class="sc-program-progress sc-program-progress--lg" style="flex:1;">
                <div class="sc-program-progress-bar">
                    <span style="width:<?php echo (int) $progress['percent']; ?>%"></span>
                </div>
                <small><?php echo esc_html($progress['done'] . ' از ' . $progress['total'] . ' آیتم تا امروز'); ?></small>
            </div>
            <div class="sc-prog-report-progress__all">
                <strong><?php echo (int) $progress_all['percent']; ?>٪</strong>
                <span>کل برنامه</span>
            </div>
        </div>

        <div class="sc-program-stats-grid sc-prog-report-stats">
            <div class="sc-program-stat-card sc-stat-purple">
                <div class="sc-program-stat-value"><?php echo (int) $progress['percent']; ?>٪</div>
                <div class="sc-program-stat-label">پیشرفت تا امروز</div>
            </div>
            <div class="sc-program-stat-card sc-stat-teal">
                <div class="sc-program-stat-value"><?php echo (int) $progress['done']; ?>/<?php echo (int) $progress['total']; ?></div>
                <div class="sc-program-stat-label">انجام‌شده تا امروز</div>
            </div>
            <div class="sc-program-stat-card sc-stat-orange">
                <div class="sc-program-stat-value"><?php echo (int) $done_all; ?>/<?php echo (int) $total_all; ?></div>
                <div class="sc-program-stat-label">کل تیک‌ها در برنامه</div>
            </div>
        </div>

        <section class="sc-prog-section">
            <div class="sc-prog-section__head">
                <h2>روزها و تمرین‌ها</h2>
                <p>وضعیت انجام هر تمرین در هر روز برنامه</p>
            </div>

            <?php if (empty($detail['days'])) : ?>
                <div class="sc-prog-empty-hero sc-prog-empty-hero--soft">
                    <p>روزی برای این برنامه ثبت نشده است.</p>
                </div>
            <?php else : ?>
                <div class="sc-prog-report-days">
                    <?php foreach ($detail['days'] as $idx => $block) :
                        $day = $block['day'];
                        $items = $block['items'];
                        $chip = function_exists('sc_program_player_day_chip_label')
                            ? sc_program_player_day_chip_label($day->program_date)
                            : (function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($day->program_date) : $day->program_date);
                        $is_today = ((string) $day->program_date === $today);
                        $day_done = 0;
                        $day_total = count($items);
                        foreach ($items as $row) {
                            if (!empty($row['done'])) {
                                $day_done++;
                            }
                        }
                        $day_pct = $day_total > 0 ? (int) round(($day_done / $day_total) * 100) : 0;
                        ?>
                        <article class="sc-prog-report-day<?php echo $is_today ? ' is-today' : ''; ?>">
                            <header class="sc-prog-report-day__head">
                                <span class="sc-prog-day-col__badge"><?php echo (int) ($idx + 1); ?></span>
                                <div class="sc-prog-report-day__titles">
                                    <h3><?php echo esc_html($day->title ?: 'روز'); ?></h3>
                                    <small><?php echo esc_html($chip); ?><?php echo $is_today ? ' · امروز' : ''; ?></small>
                                </div>
                                <div class="sc-prog-report-day__pct">
                                    <?php if ($is_today) : ?><span class="sc-prog-badge">امروز</span><?php endif; ?>
                                    <span class="sc-prog-report-day__ratio"><?php echo (int) $day_done; ?>/<?php echo (int) $day_total; ?></span>
                                </div>
                            </header>
                            <div class="sc-program-progress sc-prog-report-day__bar">
                                <div class="sc-program-progress-bar"><span style="width:<?php echo (int) $day_pct; ?>%"></span></div>
                            </div>
                            <?php if (empty($items)) : ?>
                                <div class="sc-prog-report-rest">
                                    <span aria-hidden="true">☕</span>
                                    <p>تمرینی برای این روز ثبت نشده — روز ریکاوری</p>
                                </div>
                            <?php else : ?>
                                <ul class="sc-prog-report-items">
                                    <?php foreach ($items as $row) :
                                        $done = !empty($row['done']);
                                        ?>
                                        <li class="<?php echo $done ? 'is-done' : 'is-pending'; ?>">
                                            <span class="sc-prog-report-check" aria-hidden="true"><?php echo $done ? '✓' : ''; ?></span>
                                            <span class="sc-prog-report-item-title"><?php echo esc_html($row['item']->title); ?></span>
                                            <span class="sc-prog-report-item-status"><?php echo $done ? 'انجام شد' : 'باقی‌مانده'; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return;
}

$summary = sc_program_report_summary($date_from, $date_to);
$players = sc_program_report_players(['search' => $search]);
$chart_labels = [];
$chart_values = [];
foreach ($summary['trend'] as $t) {
    $chart_labels[] = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($t['date']) : $t['date'];
    $chart_values[] = (int) $t['count'];
}
?>
<div class="wrap sc-reports-list-wrap sc-programs-wrap sc-prog-pro">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">گزارش برنامه تخصصی</h1>
            <p class="sc-reports-list-desc">عملکرد بازیکنان در برنامه‌های تخصصی — جستجو و جزئیات هر نفر</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page)); ?>">لیست برنامه‌ها</a>
        </div>
    </div>

    <div class="sc-program-stats-grid">
        <div class="sc-program-stat-card sc-stat-purple">
            <div class="sc-program-stat-value"><?php echo (int) $summary['active_programs']; ?></div>
            <div class="sc-program-stat-label">برنامه فعال</div>
        </div>
        <div class="sc-program-stat-card sc-stat-teal">
            <div class="sc-program-stat-value"><?php echo esc_html((string) $summary['avg_percent']); ?>٪</div>
            <div class="sc-program-stat-label">میانگین تکمیل</div>
        </div>
        <div class="sc-program-stat-card sc-stat-orange">
            <div class="sc-program-stat-value"><?php echo (int) $summary['done_today']; ?></div>
            <div class="sc-program-stat-label">تیک امروز</div>
        </div>
    </div>

    <div class="sc-program-panel postbox">
        <h2 class="hndle">روند تکمیل روزانه</h2>
        <div class="inside">
            <canvas id="sc-program-trend-chart" height="120"></canvas>
        </div>
    </div>

    <div class="sc-members-list-filters-card is-open">
        <form method="get" class="sc-members-list-filters-panel">
            <input type="hidden" name="page" value="<?php echo esc_attr($report_page); ?>">
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">جستجوی بازیکن / برنامه</label>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" class="sc-filter-control">
                </div>
                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">از تاریخ</label>
                    <input type="text" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_from_shamsi); ?>" class="persian-date-input sc-filter-control" readonly>
                </div>
                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">تا تاریخ</label>
                    <input type="text" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_to_shamsi); ?>" class="persian-date-input sc-filter-control" readonly>
                </div>
                <div class="sc-filter-field">
                    <button type="submit" class="sc_button sc_button--primary">اعمال</button>
                </div>
            </div>
        </form>
    </div>

    <div class="sc-members-list-table-card">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>بازیکن</th>
                    <th>برنامه</th>
                    <th>پیشرفت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($players)) : ?>
                    <tr><td colspan="4">موردی یافت نشد.</td></tr>
                <?php else : ?>
                    <?php foreach ($players as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row->member_name ?: ('#' . $row->member_id)); ?></td>
                            <td><?php echo esc_html($row->title); ?></td>
                            <td>
                                <div class="sc-program-progress">
                                    <div class="sc-program-progress-bar"><span style="width:<?php echo (int) $row->progress_percent; ?>%"></span></div>
                                    <small><?php echo (int) $row->progress_percent; ?>٪ (<?php echo (int) $row->progress_done; ?>/<?php echo (int) $row->progress_total; ?>)</small>
                                </div>
                            </td>
                            <td>
                                <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=' . $report_page . '&program_id=' . (int) $row->id)); ?>">جزئیات</a>
                                <a class="sc_button sc_button--primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $edit_page . '&program_id=' . (int) $row->id)); ?>">ویرایش</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
window.scProgramReportChart = {
    labels: <?php echo wp_json_encode($chart_labels); ?>,
    values: <?php echo wp_json_encode($chart_values); ?>
};
</script>
