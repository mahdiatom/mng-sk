<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('sc_finance_reports_access') && !current_user_can('manage_options') && !(function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only())) {
    wp_die('دسترسی غیرمجاز.');
}

sc_check_and_create_tables();

$tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
$allowed_tabs = ['overview', 'branches', 'coaches', 'courses', 'members'];
if (!in_array($tab, $allowed_tabs, true)) {
    $tab = 'overview';
}

$dates = sc_bi_parse_date_filters(6);
$filter_date_from        = $dates['from'];
$filter_date_to          = $dates['to'];
$filter_date_from_shamsi = $dates['from_shamsi'];
$filter_date_to_shamsi   = $dates['to_shamsi'];

$course_metric = isset($_GET['course_metric']) ? sanitize_text_field(wp_unslash($_GET['course_metric'])) : 'enrolled';
if (!in_array($course_metric, ['enrolled', 'revenue', 'attendance'], true)) {
    $course_metric = 'enrolled';
}

$club_monthly       = sc_bi_club_monthly_member_metrics($filter_date_from, $filter_date_to);
$branch_rows        = sc_bi_branch_revenue_rows($filter_date_from, $filter_date_to);
$branch_monthly     = sc_bi_branch_monthly_revenue($filter_date_from, $filter_date_to);
$popular_courses    = sc_bi_popular_courses($filter_date_from, $filter_date_to, $course_metric, 15);
$coach_summaries    = sc_bi_coaches_summary($filter_date_from, $filter_date_to);

$today_active = sc_bi_count_club_active_members_at_date($filter_date_to);
$period_new   = array_sum(array_column($club_monthly, 'new'));
$period_churn = array_sum(array_column($club_monthly, 'churn'));
$period_renew = array_sum(array_column($club_monthly, 'renewed'));
$avg_renewal  = !empty($club_monthly)
    ? round(array_sum(array_column($club_monthly, 'renewal_rate')) / count($club_monthly), 1)
    : 0.0;
$avg_churn    = !empty($club_monthly)
    ? round(array_sum(array_column($club_monthly, 'churn_rate')) / count($club_monthly), 1)
    : 0.0;
$total_branch_revenue = 0.0;
foreach ($branch_rows as $br) {
    $total_branch_revenue += (float) $br->revenue;
}

$base_url = admin_url('admin.php?page=sc-reports-bi-analytics');
$chart_configs = [];

$active_filters_count = 0;
if (!empty($_GET['filter_date_from']) || !empty($_GET['filter_date_from_shamsi'])) {
    $active_filters_count++;
}
if (!empty($_GET['filter_date_to']) || !empty($_GET['filter_date_to_shamsi'])) {
    $active_filters_count++;
}
if ($tab === 'courses' && $course_metric !== 'enrolled' && !empty($_GET['course_metric'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
$bi_clear_url = add_query_arg('tab', $tab, admin_url('admin.php?page=sc-reports-bi-analytics'));
?>
<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">تحلیل و هوش تجاری</h1>
            <p class="sc-reports-list-desc">نمای کلی باشگاه، درآمد شعب، عملکرد مربیان، محبوب‌ترین کلاس‌ها و روند تمدید و ریزش اعضا.</p>
        </div>
    </div>

    <nav class="nav-tab-wrapper sc-reports-nav-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', 'overview', $base_url)); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">نمای کلی</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'branches', $base_url)); ?>" class="nav-tab <?php echo $tab === 'branches' ? 'nav-tab-active' : ''; ?>">درآمد شعب</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'coaches', $base_url)); ?>" class="nav-tab <?php echo $tab === 'coaches' ? 'nav-tab-active' : ''; ?>">عملکرد مربیان</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'courses', $base_url)); ?>" class="nav-tab <?php echo $tab === 'courses' ? 'nav-tab-active' : ''; ?>">محبوب‌ترین کلاس‌ها</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'members', $base_url)); ?>" class="nav-tab <?php echo $tab === 'members' ? 'nav-tab-active' : ''; ?>">تمدید و ریزش اعضا</a>
    </nav>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-reports-bi-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-reports-bi-filters-panel">
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
                <a href="<?php echo esc_url($bi_clear_url); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>
    <form method="get" action="" class="sc-reports-list-filters-panel" id="sc-reports-bi-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
        <input type="hidden" name="page" value="sc-reports-bi-analytics">
        <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
        <?php if ($tab === 'courses') : ?>
            <input type="hidden" name="course_metric" value="<?php echo esc_attr($course_metric); ?>">
        <?php endif; ?>
        <div class="sc-filter-grid">
            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">از تاریخ</label>
                <input type="text" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" class="persian-date-input sc-filter-control" readonly>
                <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
            </div>
            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">تا تاریخ</label>
                <input type="text" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" class="persian-date-input sc-filter-control" readonly>
                <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            </div>
        </div>
        <div class="sc-reports-list-filters-actions">
            <button type="submit" class="button button-primary">اعمال فیلتر</button>
            <a href="<?php echo esc_url($bi_clear_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </div>
    </form>
    </div>

    <div class="tab-content">
        <?php if ($tab === 'overview') : ?>
            <div class="sc-reports-list-stats">
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">بازیکنان فعال (فعلی)</div><div class="sc-reports-list-stat-value is-purple"><?php echo (int) $today_active; ?></div></div>
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">عضو جدید در بازه</div><div class="sc-reports-list-stat-value is-blue"><?php echo (int) $period_new; ?></div></div>
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">ریزش در بازه</div><div class="sc-reports-list-stat-value is-debit"><?php echo (int) $period_churn; ?></div></div>
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">تمدید پرداخت در بازه</div><div class="sc-reports-list-stat-value is-credit"><?php echo (int) $period_renew; ?></div></div>
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">میانگین نرخ تمدید ماهانه</div><div class="sc-reports-list-stat-value"><?php echo esc_html($avg_renewal); ?>%</div></div>
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">میانگین نرخ ریزش ماهانه</div><div class="sc-reports-list-stat-value"><?php echo esc_html($avg_churn); ?>%</div></div>
                <div class="sc-reports-list-stat-card"><div class="sc-reports-list-stat-label">درآمد دوره‌ها (کل شعب)</div><div class="sc-reports-list-stat-value"><?php echo esc_html(number_format($total_branch_revenue, 0, '.', ',')); ?> تومان</div></div>
            </div>

            <div class="sc-reports-chart-grid">
                <div class="sc-reports-chart-card sc-bi-chart-wrap">
                    <h2>روند بازیکنان فعال (ماهانه)</h2>
                    <div class="sc-bi-chart-canvas">
                        <canvas id="biChartActiveTrend"></canvas>
                    </div>
                </div>
                <div class="sc-reports-chart-card sc-bi-chart-wrap">
                    <h2>عضو جدید vs ریزش</h2>
                    <div class="sc-bi-chart-canvas">
                        <canvas id="biChartNewChurn"></canvas>
                    </div>
                </div>
            </div>
            <?php
            $chart_configs['biChartActiveTrend'] = [
                'type' => 'line',
                'labels' => array_column($club_monthly, 'month'),
                'datasets' => [[
                    'label' => 'بازیکنان فعال',
                    'data' => array_map('intval', array_column($club_monthly, 'active')),
                    'borderColor' => 'rgba(34, 113, 177, 1)',
                    'backgroundColor' => 'rgba(34, 113, 177, 0.1)',
                    'borderWidth' => 2,
                    'tension' => 0.4,
                    'fill' => true,
                    'pointRadius' => 4,
                ]],
            ];
            $chart_configs['biChartNewChurn'] = [
                'type' => 'bar',
                'labels' => array_column($club_monthly, 'month'),
                'datasets' => [
                    ['label' => 'عضو جدید', 'data' => array_map('intval', array_column($club_monthly, 'new')), 'backgroundColor' => 'rgba(0, 163, 42, 0.7)'],
                    ['label' => 'ریزش', 'data' => array_map('intval', array_column($club_monthly, 'churn')), 'backgroundColor' => 'rgba(214, 54, 56, 0.7)'],
                ],
            ];
            ?>

        <?php elseif ($tab === 'branches') : ?>
            <div class="sc-reports-list-panel">
                <div class="sc-reports-list-panel-header"><h2>درآمد هر شعبه</h2></div>
                <div class="sc-reports-list-table-card" style="margin:0;box-shadow:none;border:none;padding:0;">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>شعبه</th><th>تعداد فاکتور</th><th>درآمد (تومان)</th><th>سهم</th></tr></thead>
                    <tbody>
                    <?php if (empty($branch_rows)) : ?>
                        <tr><td colspan="4">داده‌ای یافت نشد.</td></tr>
                    <?php else : foreach ($branch_rows as $br) :
                        $share = $total_branch_revenue > 0 ? round(((float) $br->revenue / $total_branch_revenue) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><span class="sc-member-name"><?php echo esc_html($br->chapter); ?></span></td>
                            <td><?php echo (int) $br->invoice_count; ?></td>
                            <td><span class="sc-reports-amount-credit"><?php echo esc_html(number_format((float) $br->revenue, 0, '.', ',')); ?></span></td>
                            <td><span class="sc-badge sc-badge--purple"><?php echo esc_html($share); ?>%</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <div class="sc-reports-chart-grid">
                <div class="sc-reports-chart-card sc-bi-chart-wrap">
                    <h2>سهم درآمد شعب</h2>
                    <div class="sc-bi-chart-canvas">
                        <canvas id="biChartBranchPie"></canvas>
                    </div>
                </div>
                <div class="sc-reports-chart-card sc-bi-chart-wrap">
                    <h2>روند ماهانه درآمد شعب</h2>
                    <div class="sc-bi-chart-canvas">
                        <canvas id="biChartBranchLines"></canvas>
                    </div>
                </div>
            </div>
            <?php
            $branch_labels = array_column($branch_rows, 'chapter');
            $branch_values = array_map(static function ($r) { return (float) $r->revenue; }, $branch_rows);
            $palette = ['rgba(34,113,177,0.8)','rgba(0,163,42,0.8)','rgba(240,160,0,0.8)','rgba(214,54,56,0.8)','rgba(114,46,209,0.8)','rgba(0,150,136,0.8)','rgba(233,30,99,0.8)'];
            $line_datasets = [];
            $i = 0;
            foreach ($branch_monthly as $ch => $series) {
                $line_datasets[] = [
                    'label' => $ch,
                    'data' => array_map(static function ($p) { return (float) $p['revenue']; }, $series),
                    'borderColor' => $palette[$i % count($palette)],
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'fill' => false,
                    'pointRadius' => 3,
                ];
                $i++;
            }
            $month_labels = !empty($club_monthly) ? array_column($club_monthly, 'month') : [];
            $chart_configs['biChartBranchPie'] = [
                'type' => 'doughnut',
                'labels' => $branch_labels,
                'datasets' => [['label' => 'درآمد', 'data' => $branch_values, 'backgroundColor' => array_slice($palette, 0, max(1, count($branch_values)))]],
            ];
            $chart_configs['biChartBranchLines'] = [
                'type' => 'line',
                'labels' => $month_labels,
                'datasets' => $line_datasets,
            ];
            ?>

        <?php elseif ($tab === 'coaches') : ?>
            <p class="sc-reports-note">عضو = یک بازیکن در کل باشگاه. برای جزئیات هر مربی به صفحه «عملکرد مربی» بروید.</p>
            <div class="sc-reports-list-table-card">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>مربی</th>
                        <th>دوره‌ها</th>
                        <th>بازیکنان فعال</th>
                        <th>درآمد مربی</th>
                        <th>درآمد کل دوره‌ها</th>
                        <th>جزئیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($coach_summaries)) : ?>
                    <tr><td colspan="6">مربی فعالی یافت نشد.</td></tr>
                <?php else : foreach ($coach_summaries as $cs) :
                    $detail_url = add_query_arg([
                        'page' => 'sc-reports-coach-performance',
                        'filter_coach_id' => (int) $cs->coach_id,
                        'filter_date_from' => $filter_date_from,
                        'filter_date_to' => $filter_date_to,
                        'filter_date_from_shamsi' => $filter_date_from_shamsi,
                        'filter_date_to_shamsi' => $filter_date_to_shamsi,
                    ], admin_url('admin.php'));
                    $coach_initials = $cs->name !== '' ? mb_substr($cs->name, 0, 1) : 'م';
                ?>
                    <tr>
                        <td>
                            <span class="sc-member-identity">
                                <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($coach_initials); ?></span>
                                <span class="sc-member-identity-text"><span class="sc-member-name"><?php echo esc_html($cs->name); ?></span></span>
                            </span>
                        </td>
                        <td><?php echo (int) $cs->course_count; ?></td>
                        <td><span class="sc-badge sc-badge--purple"><?php echo (int) $cs->active_now; ?></span></td>
                        <td><span class="sc-reports-amount-credit"><?php echo esc_html(number_format((float) $cs->coach_income, 0, '.', ',')); ?></span></td>
                        <td><?php echo esc_html(number_format((float) $cs->class_revenue, 0, '.', ',')); ?></td>
                        <td><a class="sc-reports-action-btn" href="<?php echo esc_url($detail_url); ?>">گزارش کامل</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div class="sc-reports-chart-card sc-bi-chart-wrap" style="margin-top:16px;">
                <h2>رتبه‌بندی — بازیکنان فعال تحت هر مربی</h2>
                <div class="sc-bi-chart-canvas" style="min-height:360px;">
                    <canvas id="biChartCoachRank"></canvas>
                </div>
            </div>
            <?php
            $top_coaches = array_slice($coach_summaries, 0, 12);
            $chart_configs['biChartCoachRank'] = [
                'type' => 'bar',
                'labels' => array_map(static function ($c) { return $c->name; }, $top_coaches),
                'datasets' => [
                    [
                        'label' => 'بازیکنان فعال',
                        'data' => array_map(static function ($c) { return (int) $c->active_now; }, $top_coaches),
                        'backgroundColor' => 'rgba(34, 113, 177, 0.75)',
                        'yAxisID' => 'y',
                    ],
                    [
                        'label' => 'درآمد مربی (هزار تومان)',
                        'data' => array_map(static function ($c) { return round((float) $c->coach_income / 1000); }, $top_coaches),
                        'backgroundColor' => 'rgba(0, 163, 42, 0.75)',
                        'yAxisID' => 'y1',
                    ],
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'type' => 'linear',
                            'position' => 'right',
                            'beginAtZero' => true,
                            'title' => ['display' => true, 'text' => 'بازیکنان فعال'],
                            'ticks' => ['stepSize' => 1],
                        ],
                        'y1' => [
                            'type' => 'linear',
                            'position' => 'left',
                            'beginAtZero' => true,
                            'grid' => ['drawOnChartArea' => false],
                            'title' => ['display' => true, 'text' => 'درآمد (هزار تومان)'],
                        ],
                    ],
                ],
            ];
            ?>

        <?php elseif ($tab === 'courses') : ?>
            <div class="sc-reports-metric-pills">
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'courses', 'course_metric' => 'enrolled', 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to, 'filter_date_from_shamsi' => $filter_date_from_shamsi, 'filter_date_to_shamsi' => $filter_date_to_shamsi], $base_url)); ?>" class="<?php echo $course_metric === 'enrolled' ? 'is-active' : ''; ?>">ثبت‌نام فعال</a>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'courses', 'course_metric' => 'revenue', 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to, 'filter_date_from_shamsi' => $filter_date_from_shamsi, 'filter_date_to_shamsi' => $filter_date_to_shamsi], $base_url)); ?>" class="<?php echo $course_metric === 'revenue' ? 'is-active' : ''; ?>">درآمد</a>
                <a href="<?php echo esc_url(add_query_arg(['tab' => 'courses', 'course_metric' => 'attendance', 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to, 'filter_date_from_shamsi' => $filter_date_from_shamsi, 'filter_date_to_shamsi' => $filter_date_to_shamsi], $base_url)); ?>" class="<?php echo $course_metric === 'attendance' ? 'is-active' : ''; ?>">حضور</a>
            </div>
            <div class="sc-reports-list-table-card">
            <table class="wp-list-table widefat fixed striped sc-bi-courses-table">
                <thead>
                    <tr>
                        <th class="sc-bi-rank-col">رتبه</th>
                        <th>دوره</th>
                        <th>شعبه</th>
                        <th><?php echo $course_metric === 'revenue' ? 'درآمد (تومان)' : ($course_metric === 'attendance' ? 'تعداد حضور' : 'ثبت‌نام فعال'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($popular_courses)) : ?>
                    <tr><td colspan="4">داده‌ای یافت نشد.</td></tr>
                <?php else : $rank = 1; foreach ($popular_courses as $pc) :
                    $course_initials = $pc->title !== '' ? mb_substr($pc->title, 0, 1) : 'د';
                ?>
                    <tr>
                        <td class="sc-bi-rank-col"><span class="sc-bi-rank-num"><?php echo (int) $rank++; ?></span></td>
                        <td>
                            <span class="sc-member-identity">
                                <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($course_initials); ?></span>
                                <span class="sc-member-identity-text"><span class="sc-member-name"><?php echo esc_html($pc->title); ?></span></span>
                            </span>
                        </td>
                        <td><?php echo esc_html($pc->chapter ?: '—'); ?></td>
                        <td><?php echo $course_metric === 'revenue' ? '<span class="sc-reports-amount-credit">' . esc_html(number_format((float) $pc->metric_value, 0, '.', ',')) . '</span>' : (int) $pc->metric_value; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div class="sc-reports-chart-card sc-bi-chart-wrap" style="margin-top:16px;">
                <h2>نمودار محبوب‌ترین کلاس‌ها</h2>
                <div class="sc-bi-chart-canvas" style="min-height:<?php echo max(280, min(600, count($popular_courses) * 36)); ?>px;">
                    <canvas id="biChartCourses"></canvas>
                </div>
            </div>
            <?php
            $metric_label = $course_metric === 'revenue' ? 'درآمد' : ($course_metric === 'attendance' ? 'حضور' : 'ثبت‌نام');
            $chart_configs['biChartCourses'] = [
                'type' => 'bar',
                'labels' => array_map(static function ($c) { return $c->title; }, $popular_courses),
                'datasets' => [[
                    'label' => $metric_label,
                    'data' => array_map(static function ($c) { return (float) $c->metric_value; }, $popular_courses),
                    'backgroundColor' => 'rgba(34, 113, 177, 0.75)',
                ]],
                'options' => ['indexAxis' => 'y'],
            ];
            ?>

        <?php elseif ($tab === 'members') : ?>
            <p class="sc-reports-note">
                تعریف عضو: یک بازیکن در کل باشگاه.
                <strong>نرخ تمدید</strong> = بازیکنانی که در آن ماه فاکتور پرداخت کردند و قبلاً هم پرداخت داشته‌اند ÷ بازیکنان فعال ابتدای ماه.
                <strong>نرخ ریزش</strong> = بازیکنانی که ابتدای ماه فعال بودند ولی پایان ماه دیگر فعال نیستند ÷ بازیکنان فعال ابتدای ماه.
            </p>
            <div class="sc-reports-list-table-card">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ماه</th>
                        <th>فعال (پایان ماه)</th>
                        <th>عضو جدید</th>
                        <th>ریزش</th>
                        <th>تمدید (پرداخت)</th>
                        <th>نرخ تمدید</th>
                        <th>نرخ ریزش</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($club_monthly)) : ?>
                    <tr><td colspan="7">داده‌ای یافت نشد.</td></tr>
                <?php else : foreach ($club_monthly as $row) : ?>
                    <tr>
                        <td><span class="sc-member-name"><?php echo esc_html($row['month']); ?></span></td>
                        <td><?php echo (int) $row['active']; ?></td>
                        <td><span class="sc-badge sc-badge--success"><?php echo (int) $row['new']; ?></span></td>
                        <td><span class="sc-badge sc-badge--danger"><?php echo (int) $row['churn']; ?></span></td>
                        <td><?php echo (int) $row['renewed']; ?></td>
                        <td><span class="sc-reports-amount-credit"><?php echo esc_html($row['renewal_rate']); ?>%</span></td>
                        <td><span class="sc-reports-amount-debit"><?php echo esc_html($row['churn_rate']); ?>%</span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <div class="sc-reports-chart-grid">
                <div class="sc-reports-chart-card sc-bi-chart-wrap">
                    <h2>نرخ تمدید ماهانه (%)</h2>
                    <div class="sc-bi-chart-canvas">
                        <canvas id="biChartRenewal"></canvas>
                    </div>
                </div>
                <div class="sc-reports-chart-card sc-bi-chart-wrap">
                    <h2>نرخ ریزش ماهانه (%)</h2>
                    <div class="sc-bi-chart-canvas">
                        <canvas id="biChartChurnRate"></canvas>
                    </div>
                </div>
            </div>
            <?php
            $chart_configs['biChartRenewal'] = [
                'type' => 'line',
                'labels' => array_column($club_monthly, 'month'),
                'datasets' => [[
                    'label' => 'نرخ تمدید %',
                    'data' => array_map('floatval', array_column($club_monthly, 'renewal_rate')),
                    'borderColor' => 'rgba(0, 163, 42, 1)',
                    'backgroundColor' => 'rgba(0, 163, 42, 0.12)',
                    'borderWidth' => 2,
                    'tension' => 0.4,
                    'fill' => true,
                    'pointRadius' => 4,
                ]],
            ];
            $chart_configs['biChartChurnRate'] = [
                'type' => 'line',
                'labels' => array_column($club_monthly, 'month'),
                'datasets' => [[
                    'label' => 'نرخ ریزش %',
                    'data' => array_map('floatval', array_column($club_monthly, 'churn_rate')),
                    'borderColor' => 'rgba(214, 54, 56, 1)',
                    'backgroundColor' => 'rgba(214, 54, 56, 0.12)',
                    'borderWidth' => 2,
                    'tension' => 0.4,
                    'fill' => true,
                    'pointRadius' => 4,
                ]],
            ];
            ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chart_configs)) : ?>
<script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    function scBiHasChartData(labels, datasets) {
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

    function scBiBuildChartConfig(raw) {
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
                        ds.tension = 0.35;
                    }
                    if (typeof ds.fill === 'undefined') {
                        ds.fill = false;
                    }
                    if (typeof ds.pointRadius === 'undefined') {
                        ds.pointRadius = 4;
                    }
                });
            }
        }
        return cfg;
    }

    function scBiShowEmpty(canvas, message) {
        var wrap = canvas.closest('.sc-bi-chart-canvas') || canvas.parentElement;
        if (wrap) {
            wrap.innerHTML = '<p style="text-align:center;padding:40px 16px;color:#666;">' + message + '</p>';
        }
    }

    var configs = <?php echo wp_json_encode($chart_configs); ?>;
    Object.keys(configs).forEach(function (id) {
        var canvas = document.getElementById(id);
        if (!canvas) {
            return;
        }
        var raw = configs[id];
        if (!scBiHasChartData(raw.labels, raw.datasets)) {
            scBiShowEmpty(canvas, 'داده‌ای برای نمایش نمودار وجود ندارد.');
            return;
        }
        try {
            new Chart(canvas, scBiBuildChartConfig(raw));
        } catch (err) {
            scBiShowEmpty(canvas, 'خطا در رسم نمودار.');
            if (window.console && console.error) {
                console.error('BI chart error:', id, err);
            }
        }
    });
});
</script>
<?php endif; ?>
<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-reports-bi-filters-toggle');
    var $panel = $('#sc-reports-bi-filters-panel');
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
});
</script>
