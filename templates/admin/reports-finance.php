<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('sc_finance_reports_access') && !current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

sc_check_and_create_tables();
global $wpdb;

$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
$allowed_tabs = ['overview', 'course_income', 'event_income', 'coach_share', 'store_income', 'receivables', 'cashflow', 'ledger'];
if (!in_array($tab, $allowed_tabs, true)) {
    $tab = 'overview';
}

$courses_table = $wpdb->prefix . 'sc_courses';
$chapter_categories_table = $wpdb->prefix . 'sc_chapter_categories';
$coaches_table = $wpdb->prefix . 'sc_coaches';

$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_coach = isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
$filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field($_GET['filter_chapter']) : '';
$filter_group_raw = isset($_GET['filter_group']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_group'])) : '';
$filter_group = function_exists('sc_finance_normalize_group_filter')
    ? sc_finance_normalize_group_filter($filter_course, $filter_group_raw)
    : '';
$filter_event_type = isset($_GET['filter_event_type']) ? sanitize_text_field($_GET['filter_event_type']) : '';
$filter_store_tag = isset($_GET['filter_store_tag']) ? absint($_GET['filter_store_tag']) : 0;
$filter_store_cat = isset($_GET['filter_store_cat']) ? absint($_GET['filter_store_cat']) : 0;
$filter_ledger_type = isset($_GET['filter_ledger_type']) ? sanitize_text_field($_GET['filter_ledger_type']) : 'all';
$filter_cashflow_type = isset($_GET['filter_cashflow_type']) ? sanitize_text_field($_GET['filter_cashflow_type']) : 'all';

$filter_date_from = '';
$filter_date_to = '';
$filter_date_from_shamsi = '';
$filter_date_to_shamsi = '';
if (!empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from_shamsi = sanitize_text_field($_GET['filter_date_from_shamsi']);
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if (!empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to_shamsi = sanitize_text_field($_GET['filter_date_to_shamsi']);
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}
if (empty($filter_date_from) && !empty($_GET['filter_date_from'])) {
    $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
}
if (empty($filter_date_to) && !empty($_GET['filter_date_to'])) {
    $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
}
if (empty($filter_date_from) || empty($filter_date_to)) {
    $today = new DateTime();
    $from = clone $today;
    $from->modify('-6 months');
    if (empty($filter_date_from)) {
        $filter_date_from = $from->format('Y-m-d');
        $j = gregorian_to_jalali((int) $from->format('Y'), (int) $from->format('m'), (int) $from->format('d'));
        $filter_date_from_shamsi = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
    }
    if (empty($filter_date_to)) {
        $filter_date_to = $today->format('Y-m-d');
        $j = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
        $filter_date_to_shamsi = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
    }
}

$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL ORDER BY title ASC");
$finance_course_groups_map = function_exists('sc_finance_course_groups_map_for_ui')
    ? sc_finance_course_groups_map_for_ui($courses)
    : [];
$finance_tabs_with_group_filter = ['course_income', 'receivables', 'cashflow', 'ledger'];
$chapters = $wpdb->get_results("SELECT name FROM $chapter_categories_table ORDER BY name ASC");
$coaches = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
$store_tags = taxonomy_exists('product_tag') ? get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']) : [];
$store_categories = taxonomy_exists('product_cat') ? get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']) : [];
if (is_wp_error($store_tags)) { $store_tags = []; }
if (is_wp_error($store_categories)) { $store_categories = []; }

$base_tab_url = admin_url('admin.php?page=sc-reports-income-expenses');
$finance_chart_config = null;
$finance_result_titles = [
    'course_income' => 'درآمد دوره‌ها',
    'event_income' => 'درآمد رویدادها',
    'coach_share' => 'درآمد مربی / سهم مجموعه',
    'store_income' => 'درآمد فروشگاه',
    'receivables' => 'مطالبات',
    'cashflow' => 'جریان نقدی',
    'ledger' => 'دفتر تراکنش‌ها',
];
?>
<div class="wrap sc-finance-reports-page-header sc-finance-page-header sc_setting_section">
    <h1 class="wp-heading-inline">گزارشات باشگاه - مالی و حسابداری</h1>
    <hr class="wp-header-end">
    <p class="sc-finance-reports-subtitle">خلاصه درآمد و هزینه، جریان نقدی، مطالبات و گزارش‌های تفصیلی مالی باشگاه.</p>
</div>
<div class="wrap sc-finance-reports-page-body sc-finance-page-body sc_setting_section">
    <nav class="nav-tab-wrapper sc-finance-reports-nav-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', 'overview', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">نمای کلی</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'course_income', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'course_income' ? 'nav-tab-active' : ''; ?>">درآمد دوره‌ها</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'event_income', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'event_income' ? 'nav-tab-active' : ''; ?>">درآمد رویدادها</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'coach_share', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'coach_share' ? 'nav-tab-active' : ''; ?>">درآمد مربی / سهم مجموعه</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'store_income', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'store_income' ? 'nav-tab-active' : ''; ?>">درآمد فروشگاه</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'receivables', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'receivables' ? 'nav-tab-active' : ''; ?>">مطالبات</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'cashflow', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'cashflow' ? 'nav-tab-active' : ''; ?>">جریان نقدی</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'ledger', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'ledger' ? 'nav-tab-active' : ''; ?>">دفتر تراکنش‌ها</a>
    </nav>

    <div class="tab-content sc-finance-reports-tab-content">
        <?php if ($tab === 'overview') : ?>
            <?php include SC_TEMPLATES_ADMIN_DIR . 'reports-income-expenses.php'; ?>
        <?php else : ?>
            <div class="sc-finance-panel postbox sc-finance-reports-filter-panel">
                <div class="postbox-header"><h2>فیلتر گزارش</h2></div>
                <div class="inside">
            <form method="GET" action="" class="form_fillter_attendance form_fillter_attendance_tab1 sc-finance-reports-filter-form">
                <input type="hidden" name="page" value="sc-reports-income-expenses">
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">

                <div class="sc-filter-grid">
                    <?php if (in_array($tab, ['course_income', 'coach_share', 'receivables', 'cashflow', 'ledger'], true)) : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_course">دوره</label>
                            <select name="filter_course" id="filter_course" class="sc-filter-control">
                                <option value="0">همه دوره‌ها</option>
                                <?php foreach ($courses as $course) : ?>
                                    <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>><?php echo esc_html($course->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if (in_array($tab, $finance_tabs_with_group_filter, true)) : ?>
                        <div class="sc-filter-field sc-finance-group-field" id="sc-finance-group-field" style="<?php echo ($filter_course > 0 && isset($finance_course_groups_map[$filter_course])) ? '' : 'display:none;'; ?>">
                            <label class="sc-filter-label" for="filter_group">گروه</label>
                            <select name="filter_group" id="filter_group" class="sc-filter-control">
                                <option value="">همه گروه‌ها</option>
                                <option value="__none__" <?php selected($filter_group, '__none__'); ?>>بدون گروه</option>
                                <?php
                                if ($filter_course > 0 && !empty($finance_course_groups_map[$filter_course])) :
                                    foreach ($finance_course_groups_map[$filter_course] as $gname) :
                                        ?>
                                        <option value="<?php echo esc_attr($gname); ?>" <?php selected($filter_group, $gname); ?>><?php echo esc_html($gname); ?></option>
                                    <?php endforeach;
                                endif;
                                ?>
                            </select>
                            <p class="description sc-finance-group-help">پس از انتخاب دوره با گروه‌بندی فعال می‌شود.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($tab === 'coach_share') : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_coach">مربی</label>
                            <select name="filter_coach" id="filter_coach" class="sc-filter-control">
                                <option value="0">همه مربیان</option>
                                <?php foreach ($coaches as $coach) : ?>
                                    <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach, $coach->id); ?>>
                                        <?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($tab !== 'store_income') : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_chapter">شعبه</label>
                            <select name="filter_chapter" id="filter_chapter" class="sc-filter-control">
                                <option value="">همه شعبه‌ها</option>
                                <?php foreach ($chapters as $chapter_item) : ?>
                                    <option value="<?php echo esc_attr($chapter_item->name); ?>" <?php selected($filter_chapter, $chapter_item->name); ?>>
                                        <?php echo esc_html($chapter_item->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($tab === 'event_income') : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_event_type">نوع</label>
                            <select name="filter_event_type" id="filter_event_type" class="sc-filter-control">
                                <option value="">همه</option>
                                <option value="event" <?php selected($filter_event_type, 'event'); ?>>رویداد</option>
                                <option value="competition" <?php selected($filter_event_type, 'competition'); ?>>مسابقه</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($tab === 'store_income') : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_store_tag">برچسب محصول</label>
                            <select name="filter_store_tag" id="filter_store_tag" class="sc-filter-control">
                                <option value="0">همه برچسب‌ها</option>
                                <?php foreach ($store_tags as $store_tag) : ?>
                                    <option value="<?php echo esc_attr((string) $store_tag->term_id); ?>" <?php selected($filter_store_tag, (int) $store_tag->term_id); ?>>
                                        <?php echo esc_html($store_tag->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_store_cat">دسته محصول</label>
                            <select name="filter_store_cat" id="filter_store_cat" class="sc-filter-control">
                                <option value="0">همه دسته‌ها</option>
                                <?php foreach ($store_categories as $store_cat) : ?>
                                    <option value="<?php echo esc_attr((string) $store_cat->term_id); ?>" <?php selected($filter_store_cat, (int) $store_cat->term_id); ?>>
                                        <?php echo esc_html($store_cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($tab === 'ledger') : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_ledger_type">نوع تراکنش</label>
                            <select name="filter_ledger_type" id="filter_ledger_type" class="sc-filter-control">
                                <option value="all" <?php selected($filter_ledger_type, 'all'); ?>>همه</option>
                                <option value="income" <?php selected($filter_ledger_type, 'income'); ?>>ورودی</option>
                                <option value="expense" <?php selected($filter_ledger_type, 'expense'); ?>>خروجی</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($tab === 'cashflow') : ?>
                        <div class="sc-filter-field">
                            <label class="sc-filter-label" for="filter_cashflow_type">نوع</label>
                            <select name="filter_cashflow_type" id="filter_cashflow_type" class="sc-filter-control">
                                <option value="all" <?php selected($filter_cashflow_type, 'all'); ?>>همه</option>
                                <option value="course" <?php selected($filter_cashflow_type, 'course'); ?>>دوره</option>
                                <option value="event" <?php selected($filter_cashflow_type, 'event'); ?>>رویداد</option>
                                <option value="store" <?php selected($filter_cashflow_type, 'store'); ?>>فروشگاه</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="sc-filter-field sc-filter-date">
                        <label class="sc-filter-label">بازه تاریخ (شمسی)</label>
                        <div class="sc-date-range">
                            <input type="text"
                                   name="filter_date_from_shamsi"
                                   id="filter_date_from_shamsi"
                                   value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                                   class="persian-date-input sc-filter-control"
                                   readonly>
                            <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                            <input type="text"
                                   name="filter_date_to_shamsi"
                                   id="filter_date_to_shamsi"
                                   value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                                   class="persian-date-input sc-filter-control"
                                   readonly>
                            <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">اعمال فیلتر</button>
                    <a href="<?php echo esc_url(add_query_arg('tab', $tab, admin_url('admin.php?page=sc-reports-income-expenses'))); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                    <?php
                    $export_map = [
                        'course_income' => 'finance_course_income',
                        'event_income' => 'finance_event_income',
                        'coach_share' => 'finance_coach_share',
                        'store_income' => 'finance_store_income',
                        'receivables' => 'finance_receivables',
                        'cashflow' => 'finance_cashflow',
                        'ledger' => 'finance_ledger',
                    ];
                    $export_url = isset($export_map[$tab]) ? admin_url('admin.php?page=sc-reports-income-expenses&tab=' . $tab . '&sc_export=excel&export_type=' . $export_map[$tab]) : '';
                    foreach (['filter_date_from','filter_date_to','filter_date_from_shamsi','filter_date_to_shamsi','filter_course','filter_coach','filter_chapter','filter_group','filter_event_type','filter_store_tag','filter_store_cat','filter_ledger_type','filter_cashflow_type'] as $param) {
                        if (isset($_GET[$param]) && $_GET[$param] !== '') {
                            $export_url = add_query_arg($param, sanitize_text_field(wp_unslash($_GET[$param])), $export_url);
                        }
                    }
                    if ($export_url !== '') {
                        $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                    }
                    ?>
                    <?php if ($export_url !== '') : ?>
                        <a class="button button_export" href="<?php echo esc_url($export_url); ?>">خروجی اکسل</a>
                    <?php endif; ?>
                </p>
            </form>
                </div>
            </div>

            <div class="sc-finance-panel postbox sc-finance-reports-data-panel">
                <div class="postbox-header"><h2><?php echo esc_html($finance_result_titles[$tab] ?? 'نتایج گزارش'); ?></h2></div>
                <div class="inside sc-finance-reports-data-inside">

            <?php if ($tab === 'course_income') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $where = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.course_id > 0"];
                $args = [$filter_date_from, $filter_date_to];
                if ($filter_course > 0) { $where[] = "i.course_id = %d"; $args[] = $filter_course; }
                if ($filter_chapter !== '') { $where[] = "c.chapter = %s"; $args[] = $filter_chapter; }
                $mc_join = function_exists('sc_finance_apply_invoice_group_filter')
                    ? sc_finance_apply_invoice_group_filter($where, $args, $filter_course, $filter_group)
                    : '';
                $sql = "SELECT c.id, c.title, c.chapter, COUNT(i.id) AS paid_count, SUM(i.amount) AS income_total
                        FROM $invoices_table i
                        INNER JOIN $courses_table c ON c.id = i.course_id
                        {$mc_join}
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY c.id, c.title, c.chapter
                        ORDER BY income_total DESC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                ?>
                <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                    <thead><tr><th>دوره</th><th>شعبه</th><th>تعداد پرداخت</th><th>درآمد (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) : ?>
                        <tr><td><?php echo esc_html($r->title); ?></td><td><?php echo esc_html($r->chapter ?: '-'); ?></td><td><?php echo (int) $r->paid_count; ?></td><td><?php echo esc_html(number_format((float) $r->income_total, 0, '.', ',')); ?></td></tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="4">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'event_income') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $events_table = $wpdb->prefix . 'sc_events';
                $where = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.event_id IS NOT NULL", "i.event_id > 0"];
                $args = [$filter_date_from, $filter_date_to];
                if ($filter_event_type !== '') { $where[] = "e.event_type = %s"; $args[] = $filter_event_type; }
                if ($filter_chapter !== '') { $where[] = "e.chapter = %s"; $args[] = $filter_chapter; }
                $sql = "SELECT e.id, e.name, e.event_type, e.chapter, COUNT(i.id) AS paid_count, SUM(i.amount) AS income_total
                        FROM $invoices_table i
                        INNER JOIN $events_table e ON e.id = i.event_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY e.id, e.name, e.event_type, e.chapter
                        ORDER BY income_total DESC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                ?>
                <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                    <thead><tr><th>رویداد/مسابقه</th><th>نوع</th><th>شعبه</th><th>تعداد پرداخت</th><th>درآمد (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) : ?>
                        <tr><td><?php echo esc_html($r->name); ?></td><td><?php echo esc_html($r->event_type === 'competition' ? 'مسابقه' : 'رویداد'); ?></td><td><?php echo esc_html($r->chapter ?: '-'); ?></td><td><?php echo (int) $r->paid_count; ?></td><td><?php echo esc_html(number_format((float) $r->income_total, 0, '.', ',')); ?></td></tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'coach_share') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
                $salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
                $salary_where = ["w.status = 'completed'", "w.transaction_type IN ('salary_percentage','salary_fixed')", "DATE(w.created_at) BETWEEN %s AND %s"];
                $salary_args = [$filter_date_from, $filter_date_to];
                if ($filter_coach > 0) { $salary_where[] = "w.coach_id = %d"; $salary_args[] = $filter_coach; }
                if ($filter_course > 0) { $salary_where[] = "w.related_course_id = %d"; $salary_args[] = $filter_course; }
                if ($filter_chapter !== '') {
                    $salary_where[] = "(sr.chapter_name = %s OR (sr.id IS NULL AND co.chapter = %s))";
                    $salary_args[] = $filter_chapter;
                    $salary_args[] = $filter_chapter;
                }

                $income_where = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.course_id > 0"];
                $income_args = [$filter_date_from, $filter_date_to];
                if ($filter_course > 0) { $income_where[] = "i.course_id = %d"; $income_args[] = $filter_course; }
                if ($filter_chapter !== '') { $income_where[] = "c.chapter = %s"; $income_args[] = $filter_chapter; }

                $sql = "SELECT coach_rows.coach_id, coach_rows.first_name, coach_rows.last_name,
                               SUM(coach_rows.coach_income) AS coach_income,
                               SUM(COALESCE(course_income.course_income, 0)) AS total_class_income
                        FROM (
                            SELECT w.coach_id, cc.first_name, cc.last_name, w.related_course_id, SUM(w.amount) AS coach_income
                            FROM $wallet_table w
                            INNER JOIN $coaches_table cc ON cc.id = w.coach_id
                            LEFT JOIN $courses_table co ON co.id = w.related_course_id
                            LEFT JOIN $salary_records_table sr ON sr.id = w.related_salary_record_id
                            WHERE " . implode(' AND ', $salary_where) . "
                            GROUP BY w.coach_id, cc.first_name, cc.last_name, w.related_course_id
                        ) coach_rows
                        LEFT JOIN (
                            SELECT i.course_id, SUM(i.amount) AS course_income
                            FROM $invoices_table i
                            INNER JOIN $courses_table c ON c.id = i.course_id
                            WHERE " . implode(' AND ', $income_where) . "
                            GROUP BY i.course_id
                        ) course_income ON course_income.course_id = coach_rows.related_course_id
                        GROUP BY coach_rows.coach_id, coach_rows.first_name, coach_rows.last_name
                        ORDER BY coach_income DESC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($salary_args, $income_args)));
                $labels = [];
                $coach_series = [];
                $club_series = [];
                ?>
                <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                    <thead><tr><th>مربی</th><th>درآمد مربی (تومان)</th><th>سهم مجموعه (تومان)</th><th>درآمد کل کلاس (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) :
                        $coach_income = (float) $r->coach_income;
                        $class_income = (float) $r->total_class_income;
                        $club_share = $class_income - $coach_income;
                        $labels[] = trim($r->first_name . ' ' . $r->last_name);
                        $coach_series[] = $coach_income;
                        $club_series[] = $club_share;
                    ?>
                        <tr>
                            <td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?></td>
                            <td><?php echo esc_html(number_format($coach_income, 0, '.', ',')); ?></td>
                            <td><?php echo esc_html(number_format($club_share, 0, '.', ',')); ?></td>
                            <td><?php echo esc_html(number_format($class_income, 0, '.', ',')); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="4">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="sc-finance-chart-wrap sc-finance-chart-wrap--wide"><canvas id="financeChart"></canvas></div>
                <?php
                $finance_chart_config = [
                    'type' => 'bar',
                    'labels' => $labels,
                    'datasets' => [
                        ['label' => 'درآمد مربی', 'data' => $coach_series, 'backgroundColor' => 'rgba(34, 113, 177, 0.7)'],
                        ['label' => 'سهم مجموعه', 'data' => $club_series, 'backgroundColor' => 'rgba(0, 163, 42, 0.7)'],
                    ],
                ];
                ?>
            <?php elseif ($tab === 'store_income') :
                $rows = [];
                $total_store_income = 0.0;
                $daily_income = [];
                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders([
                        'status' => ['processing', 'completed'],
                        'limit' => -1,
                        'type' => 'shop_order',
                        'date_created' => $filter_date_from . '...' . $filter_date_to,
                        'return' => 'objects',
                    ]);
                    foreach ($orders as $order) {
                        $order_total = 0.0;
                        foreach ($order->get_items() as $item) {
                            $product = $item->get_product();
                            if (!$product) {
                                continue;
                            }
                            $product_id = (int) $product->get_id();
                            $taxonomy_object_id = $product->is_type('variation') ? (int) $product->get_parent_id() : $product_id;
                            if ($taxonomy_object_id <= 0) {
                                $taxonomy_object_id = $product_id;
                            }
                            if ($filter_store_tag > 0 && !has_term($filter_store_tag, 'product_tag', $taxonomy_object_id)) {
                                continue;
                            }
                            if ($filter_store_cat > 0 && !has_term($filter_store_cat, 'product_cat', $taxonomy_object_id)) {
                                continue;
                            }
                            $order_total += (float) $item->get_total();
                        }
                        if ($order_total <= 0) {
                            continue;
                        }
                        $order_date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : current_time('Y-m-d');
                        $first_name = trim((string) $order->get_billing_first_name());
                        $last_name = trim((string) $order->get_billing_last_name());
                        $customer_name = trim($first_name . ' ' . $last_name);
                        if ($customer_name === '') {
                            $customer_name = trim((string) $order->get_formatted_billing_full_name());
                        }
                        if ($customer_name === '') {
                            $customer_name = trim((string) $order->get_billing_phone());
                        }
                        if ($customer_name === '') {
                            $customer_name = '#'. (string) $order->get_customer_id();
                        }
                        $item_names = [];
                        foreach ($order->get_items() as $order_item_label) {
                            $item_name = trim((string) $order_item_label->get_name());
                            if ($item_name !== '') {
                                $item_names[] = $item_name;
                            }
                        }
                        $rows[] = (object) [
                            'order_id' => $order->get_id(),
                            'order_date' => $order_date,
                            'customer_name' => $customer_name,
                            'items_text' => implode(' | ', $item_names),
                            'income_total' => $order_total,
                        ];
                    }
                }
                ?>
                <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                    <thead><tr><th>شماره سفارش</th><th>تاریخ</th><th>سفارش‌دهنده</th><th>اقلام</th><th>درآمد (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) :
                        $total_store_income += (float) $r->income_total;
                        $daily_income[$r->order_date] = ($daily_income[$r->order_date] ?? 0) + (float) $r->income_total;
                    ?>
                        <tr>
                            <td>#<?php echo esc_html($r->order_id); ?></td>
                            <td><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($r->order_date) : $r->order_date); ?></td>
                            <td><?php echo esc_html($r->customer_name); ?></td>
                            <td><?php echo esc_html($r->items_text); ?></td>
                            <td><?php echo esc_html(number_format((float) $r->income_total, 0, '.', ',')); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="sc-dashboard-stats sc-finance-reports-stats sc-finance-reports-stats--compact">
                    <div class="sc-stat-box"><h3>جمع درآمد فروشگاه</h3><div><?php echo esc_html(number_format($total_store_income, 0, '.', ',')); ?></div></div>
                </div>
                <div class="sc-finance-chart-wrap"><canvas id="financeChart"></canvas></div>
                <?php
                ksort($daily_income);
                $finance_chart_config = [
                    'type' => 'line',
                    'labels' => array_map(static function ($d) { return function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($d) : $d; }, array_keys($daily_income)),
                    'datasets' => [
                        ['label' => 'درآمد فروشگاه', 'data' => array_values($daily_income), 'borderColor' => 'rgba(34,113,177,1)', 'backgroundColor' => 'rgba(34,113,177,0.2)', 'fill' => true],
                    ],
                ];
                ?>
            <?php elseif ($tab === 'receivables') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $members_table = $wpdb->prefix . 'sc_members';
                $wallet_table = $wpdb->prefix . 'sc_wallet_transactions';
                $where = ["i.status IN ('pending','under_review')", "DATE(i.created_at) BETWEEN %s AND %s"];
                $args = [$filter_date_from, $filter_date_to];
                if ($filter_course > 0) { $where[] = "i.course_id = %d"; $args[] = $filter_course; }
                if ($filter_chapter !== '') { $where[] = "c.chapter = %s"; $args[] = $filter_chapter; }
                $mc_join = function_exists('sc_finance_apply_invoice_group_filter')
                    ? sc_finance_apply_invoice_group_filter($where, $args, $filter_course, $filter_group)
                    : '';
                $sql = "SELECT i.id, i.member_id, i.amount, i.created_at, m.first_name, m.last_name, c.title AS course_title, c.chapter, 'invoice' AS debt_type
                        FROM $invoices_table i
                        LEFT JOIN $members_table m ON m.id = i.member_id
                        LEFT JOIN $courses_table c ON c.id = i.course_id
                        {$mc_join}
                        WHERE " . implode(' AND ', $where) . "
                        ORDER BY i.created_at DESC";
                $invoice_rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                $wallet_rows = $wpdb->get_results("SELECT m.id AS member_id, m.first_name, m.last_name, MIN(w.created_at) AS created_at, ABS(MIN(w.balance_after)) AS amount
                    FROM $wallet_table w
                    INNER JOIN $members_table m ON m.id = w.member_id
                    GROUP BY m.id, m.first_name, m.last_name
                    HAVING MIN(w.balance_after) < 0");
                $rows = $invoice_rows ?: [];
                if (empty($filter_group) && !empty($wallet_rows)) {
                    foreach ($wallet_rows as $wallet_row) {
                        $wallet_row->course_title = 'بدهی کیف پول';
                        $wallet_row->chapter = '-';
                        $wallet_row->debt_type = 'wallet';
                        $rows[] = $wallet_row;
                    }
                }
                $receivable_chart = ['فاکتور معوق' => 0, 'بدهی کیف پول' => 0];
                ?>
                <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                    <thead><tr><th>تاریخ</th><th>بازیکن</th><th>نوع</th><th>دوره/شرح</th><th>شعبه</th><th>مبلغ مطالبه (تومان)</th><th>جزئیات</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) : ?>
                        <?php
                        $shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(substr((string) $r->created_at, 0, 10)) : substr((string) $r->created_at, 0, 10);
                        $receivable_chart[$r->debt_type === 'wallet' ? 'بدهی کیف پول' : 'فاکتور معوق'] += (float) $r->amount;
                        $details_url = admin_url('admin.php?page=sc-invoices&filter_member=' . absint($r->member_id));
                        ?>
                        <tr>
                            <td><?php echo esc_html($shamsi); ?></td>
                            <td><?php echo esc_html(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''))); ?></td>
                            <td><?php echo esc_html($r->debt_type === 'wallet' ? 'کیف پول' : 'فاکتور'); ?></td>
                            <td><?php echo esc_html($r->course_title ?: '-'); ?></td>
                            <td><?php echo esc_html($r->chapter ?: '-'); ?></td>
                            <td><?php echo esc_html(number_format((float) $r->amount, 0, '.', ',')); ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url($details_url); ?>">مشاهده صورت‌حساب‌ها</a></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="7">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="sc-finance-chart-wrap sc-finance-chart-wrap--narrow"><canvas id="financeChart"></canvas></div>
                <?php
                $finance_chart_config = [
                    'type' => 'doughnut',
                    'labels' => array_keys($receivable_chart),
                    'datasets' => [
                        ['label' => 'مطالبات', 'data' => array_values($receivable_chart), 'backgroundColor' => ['rgba(240,160,0,0.8)', 'rgba(214,54,56,0.8)']],
                    ],
                ];
                ?>
            <?php elseif ($tab === 'cashflow') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $expenses_table = $wpdb->prefix . 'sc_expenses';
                $events_table = $wpdb->prefix . 'sc_events';
                $where_in = [
                    "i.status IN ('paid','completed','processing')",
                    'i.payment_date IS NOT NULL',
                    'DATE(i.payment_date) BETWEEN %s AND %s',
                    '(i.course_id > 0 OR (i.event_id IS NOT NULL AND i.event_id > 0))',
                ];
                $where_out = ["e.expense_date_gregorian IS NOT NULL", "DATE(e.expense_date_gregorian) BETWEEN %s AND %s"];
                $args_in = [$filter_date_from, $filter_date_to];
                $args_out = [$filter_date_from, $filter_date_to];
                if ($filter_chapter !== '') {
                    $where_in[] = '((i.course_id > 0 AND c.chapter = %s) OR (i.event_id > 0 AND ev.chapter = %s))';
                    $args_in[] = $filter_chapter;
                    $args_in[] = $filter_chapter;
                    $where_out[] = 'e.chapter = %s';
                    $args_out[] = $filter_chapter;
                }
                if ($filter_course > 0) {
                    $where_in[] = 'i.course_id = %d';
                    $args_in[] = $filter_course;
                }
                $mc_join = function_exists('sc_finance_apply_invoice_group_filter')
                    ? sc_finance_apply_invoice_group_filter($where_in, $args_in, $filter_course, $filter_group)
                    : '';
                if ($filter_cashflow_type === 'course') {
                    $where_in[] = 'i.course_id > 0';
                } elseif ($filter_cashflow_type === 'event') {
                    $where_in[] = 'i.event_id IS NOT NULL AND i.event_id > 0';
                }
                $cash_in_academy_raw = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(i.amount),0) FROM $invoices_table i
                     LEFT JOIN $courses_table c ON c.id = i.course_id
                     LEFT JOIN $events_table ev ON ev.id = i.event_id
                     {$mc_join}
                     WHERE " . implode(' AND ', $where_in),
                    $args_in
                ));
                $cash_in_store_raw = function_exists('sc_finance_sum_store_orders_items_total')
                    ? sc_finance_sum_store_orders_items_total($filter_date_from, $filter_date_to, 0, 0)
                    : 0.0;
                $cash_in = $cash_in_store_raw;
                if ($filter_cashflow_type === 'course' || $filter_cashflow_type === 'event') {
                    $cash_in = $cash_in_academy_raw;
                } elseif ($filter_cashflow_type === 'all') {
                    $cash_in = $cash_in_academy_raw + $cash_in_store_raw;
                }
                $cash_out = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(e.amount),0) FROM $expenses_table e WHERE " . implode(' AND ', $where_out), $args_out));
                $net_cashflow = $cash_in - $cash_out;
                ?>
                <p class="description sc-finance-reports-info-note">
                    ورودی نقدی بر اساس نوع انتخابی (همه/دوره/رویداد/فروشگاه) محاسبه می‌شود؛ فیلتر دوره و شعبه فقط روی داده‌های آکادمی اثر دارد.
                </p>
                <div class="sc-dashboard-stats sc-finance-reports-stats">
                    <div class="sc-stat-box"><h3>ورودی نقدی</h3><div><?php echo esc_html(number_format($cash_in, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>خروجی نقدی</h3><div><?php echo esc_html(number_format($cash_out, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>خالص جریان نقدی</h3><div><?php echo esc_html(number_format($net_cashflow, 0, '.', ',')); ?></div></div>
                </div>
                <div class="sc-finance-chart-wrap"><canvas id="financeChart"></canvas></div>
                <?php
                $finance_chart_config = [
                    'type' => 'bar',
                    'labels' => ['ورودی', 'خروجی', 'خالص'],
                    'datasets' => [
                        ['label' => 'جریان نقدی', 'data' => [$cash_in, $cash_out, $net_cashflow], 'backgroundColor' => ['rgba(0,163,42,0.7)', 'rgba(214,54,56,0.7)', 'rgba(34,113,177,0.7)']],
                    ],
                ];
                ?>
            <?php elseif ($tab === 'ledger') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $members_table = $wpdb->prefix . 'sc_members';
                $events_table = $wpdb->prefix . 'sc_events';
                $expenses_table = $wpdb->prefix . 'sc_expenses';
                $where_in = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s"];
                $args_in = [$filter_date_from, $filter_date_to];
                if ($filter_chapter !== '') {
                    $where_in[] = "((i.course_id > 0 AND c.chapter = %s) OR (i.event_id > 0 AND ev.chapter = %s))";
                    $args_in[] = $filter_chapter;
                    $args_in[] = $filter_chapter;
                }
                if ($filter_course > 0) { $where_in[] = "i.course_id = %d"; $args_in[] = $filter_course; }
                $mc_join = function_exists('sc_finance_apply_invoice_group_filter')
                    ? sc_finance_apply_invoice_group_filter($where_in, $args_in, $filter_course, $filter_group)
                    : '';
                $income_rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(i.payment_date) AS tx_date, 'income' AS tx_type, i.amount, CONCAT(m.first_name, ' ', m.last_name) AS person_name,
                    CASE
                        WHEN i.course_id > 0 THEN c.title
                        WHEN i.event_id > 0 THEN ev.name
                        ELSE '-'
                    END AS ref_title,
                    CASE
                        WHEN i.course_id > 0 THEN c.chapter
                        WHEN i.event_id > 0 THEN ev.chapter
                        ELSE '-'
                    END AS chapter
                    FROM $invoices_table i
                    LEFT JOIN $members_table m ON m.id = i.member_id
                    LEFT JOIN $courses_table c ON c.id = i.course_id
                    LEFT JOIN $events_table ev ON ev.id = i.event_id
                    {$mc_join}
                    WHERE " . implode(' AND ', $where_in), $args_in));
                $store_income_rows = [];
                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders([
                        'status' => ['processing', 'completed'],
                        'limit' => -1,
                        'type' => 'shop_order',
                        'date_created' => $filter_date_from . '...' . $filter_date_to,
                        'return' => 'objects',
                    ]);
                    foreach ($orders as $order) {
                        $order_total = 0.0;
                        foreach ($order->get_items() as $item) {
                            $order_total += (float) $item->get_total();
                        }
                        if ($order_total <= 0) {
                            continue;
                        }
                        $order_date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : current_time('Y-m-d');
                        $store_income_rows[] = (object) [
                            'tx_date' => $order_date,
                            'tx_type' => 'income',
                            'amount' => $order_total,
                            'person_name' => trim((string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name()),
                            'ref_title' => 'سفارش فروشگاه #' . $order->get_id(),
                            'chapter' => '-',
                        ];
                    }
                }

                $where_out = ["e.expense_date_gregorian IS NOT NULL", "DATE(e.expense_date_gregorian) BETWEEN %s AND %s"];
                $args_out = [$filter_date_from, $filter_date_to];
                if ($filter_chapter !== '') { $where_out[] = "e.chapter = %s"; $args_out[] = $filter_chapter; }
                $expense_rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(e.expense_date_gregorian) AS tx_date, 'expense' AS tx_type, e.amount, '' AS person_name, e.name AS ref_title, e.chapter
                    FROM $expenses_table e
                    WHERE " . implode(' AND ', $where_out), $args_out));
                if ($filter_ledger_type === 'income') {
                    $expense_rows = [];
                } elseif ($filter_ledger_type === 'expense') {
                    $income_rows = [];
                    $store_income_rows = [];
                }
                $ledger_rows = array_merge($income_rows ?: [], $store_income_rows ?: [], $expense_rows ?: []);
                usort($ledger_rows, static function($a, $b) {
                    return strcmp((string) $b->tx_date, (string) $a->tx_date);
                });
                ?>
                <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                    <thead><tr><th>تاریخ</th><th>نوع</th><th>شرح</th><th>شخص</th><th>شعبه</th><th>مبلغ (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($ledger_rows)) : foreach ($ledger_rows as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($r->tx_date) : $r->tx_date); ?></td>
                            <td><?php echo esc_html($r->tx_type === 'income' ? 'ورودی' : 'خروجی'); ?></td>
                            <td><?php echo esc_html($r->ref_title ?: '-'); ?></td>
                            <td><?php echo esc_html($r->person_name ?: '-'); ?></td>
                            <td><?php echo esc_html($r->chapter ?: '-'); ?></td>
                            <td><?php echo esc_html(number_format((float) $r->amount, 0, '.', ',')); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="6">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php
                $income_sum = 0.0;
                $expense_sum = 0.0;
                foreach ($ledger_rows as $ledger_row) {
                    if ($ledger_row->tx_type === 'income') {
                        $income_sum += (float) $ledger_row->amount;
                    } else {
                        $expense_sum += (float) $ledger_row->amount;
                    }
                }
                $finance_chart_config = [
                    'type' => 'pie',
                    'labels' => ['ورودی', 'خروجی'],
                    'datasets' => [
                        ['label' => 'دفتر تراکنش‌ها', 'data' => [$income_sum, $expense_sum], 'backgroundColor' => ['rgba(0,163,42,0.75)', 'rgba(214,54,56,0.75)']],
                    ],
                ];
                ?>
                <div class="sc-dashboard-stats sc-finance-reports-stats sc-finance-reports-stats--compact">
                    <div class="sc-stat-box"><h3>جمع ورودی فیلترشده</h3><div><?php echo esc_html(number_format($income_sum, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>جمع خروجی فیلترشده</h3><div><?php echo esc_html(number_format($expense_sum, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>خالص فیلترشده</h3><div><?php echo esc_html(number_format($income_sum - $expense_sum, 0, '.', ',')); ?></div></div>
                </div>
                <div class="sc-finance-chart-wrap sc-finance-chart-wrap--narrow"><canvas id="financeChart"></canvas></div>
            <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($tab !== 'overview' && in_array($tab, $finance_tabs_with_group_filter, true)) : ?>
<script type="text/javascript">
jQuery(function ($) {
    var financeCourseGroups = <?php echo wp_json_encode($finance_course_groups_map, JSON_UNESCAPED_UNICODE); ?>;
    var selectedGroup = <?php echo wp_json_encode($filter_group, JSON_UNESCAPED_UNICODE); ?>;

    function refreshFinanceGroupField() {
        var $field = $('#sc-finance-group-field');
        var $sel = $('#filter_group');
        if (!$field.length || !$sel.length) {
            return;
        }
        var courseId = parseInt($('#filter_course').val(), 10) || 0;
        var groups = financeCourseGroups[courseId] || financeCourseGroups[String(courseId)] || [];
        $sel.find('option').not('[value=""], [value="__none__"]').remove();
        if (!courseId || !groups.length) {
            $field.hide();
            $sel.val('');
            return;
        }
        $field.show();
        groups.forEach(function (name) {
            $sel.append($('<option></option>').val(name).text(name));
        });
        if (selectedGroup && $sel.find('option[value="' + selectedGroup.replace(/"/g, '\\"') + '"]').length) {
            $sel.val(selectedGroup);
        } else {
            $sel.val('');
        }
    }

    $('#filter_course').on('change', function () {
        selectedGroup = '';
        refreshFinanceGroupField();
    });
    refreshFinanceGroupField();
});
</script>
<?php endif; ?>
<?php if ($tab !== 'overview' && !empty($finance_chart_config)) : ?>
<script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('financeChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }
    var cfg = <?php echo wp_json_encode($finance_chart_config); ?>;
    cfg.options = cfg.options || {};
    cfg.options.responsive = true;
    cfg.options.plugins = cfg.options.plugins || {};
    cfg.options.plugins.legend = cfg.options.plugins.legend || { position: 'top' };
    new Chart(canvas, cfg);
});
</script>
<?php endif; ?>
