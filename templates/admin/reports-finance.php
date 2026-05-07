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
$allowed_tabs = ['overview', 'course_income', 'event_income', 'coach_income', 'club_share', 'receivables', 'cashflow', 'ledger'];
if (!in_array($tab, $allowed_tabs, true)) {
    $tab = 'overview';
}

$courses_table = $wpdb->prefix . 'sc_courses';
$chapter_categories_table = $wpdb->prefix . 'sc_chapter_categories';
$coaches_table = $wpdb->prefix . 'sc_coaches';

$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_coach = isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
$filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field($_GET['filter_chapter']) : '';
$filter_event_type = isset($_GET['filter_event_type']) ? sanitize_text_field($_GET['filter_event_type']) : '';

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
$chapters = $wpdb->get_results("SELECT name FROM $chapter_categories_table ORDER BY name ASC");
$coaches = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");

$base_tab_url = admin_url('admin.php?page=sc-reports-income-expenses');
?>
<div class="wrap sc_setting_section">
    <h1 class="wp-heading-inline">گزارشات باشگاه - مالی و حسابداری</h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg('tab', 'overview', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">نمای کلی</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'course_income', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'course_income' ? 'nav-tab-active' : ''; ?>">درآمد دوره‌ها</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'event_income', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'event_income' ? 'nav-tab-active' : ''; ?>">درآمد رویدادها</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'coach_income', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'coach_income' ? 'nav-tab-active' : ''; ?>">درآمد مربیان</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'club_share', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'club_share' ? 'nav-tab-active' : ''; ?>">سهم مجموعه</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'receivables', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'receivables' ? 'nav-tab-active' : ''; ?>">مطالبات</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'cashflow', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'cashflow' ? 'nav-tab-active' : ''; ?>">جریان نقدی</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'ledger', $base_tab_url)); ?>" class="nav-tab <?php echo $tab === 'ledger' ? 'nav-tab-active' : ''; ?>">دفتر تراکنش‌ها</a>
    </nav>

    <div class="tab-content" style="margin-top:20px;">
        <?php if ($tab === 'overview') : ?>
            <?php include SC_TEMPLATES_ADMIN_DIR . 'reports-income-expenses.php'; ?>
        <?php else : ?>
            <form method="GET" action="" class="form_filter_general">
                <input type="hidden" name="page" value="sc-reports-income-expenses">
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
                <div class="sc-form-flex">
                    <div class="sc-form-field sc-full">
                        <label>بازه تاریخ (شمسی)</label>
                        <div class="sc-form-row">
                            <input type="text" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" class="regular-text persian-date-input" readonly>
                            <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                            <span>تا</span>
                            <input type="text" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" class="regular-text persian-date-input" readonly>
                            <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                        </div>
                    </div>
                    <?php if (in_array($tab, ['course_income', 'club_share', 'receivables', 'cashflow', 'ledger'], true)) : ?>
                        <div class="sc-form-field">
                            <label for="filter_course">دوره</label>
                            <select name="filter_course" id="filter_course">
                                <option value="0">همه دوره‌ها</option>
                                <?php foreach ($courses as $course) : ?>
                                    <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>><?php echo esc_html($course->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if (in_array($tab, ['coach_income', 'club_share'], true)) : ?>
                        <div class="sc-form-field">
                            <label for="filter_coach">مربی</label>
                            <select name="filter_coach" id="filter_coach">
                                <option value="0">همه مربیان</option>
                                <?php foreach ($coaches as $coach) : ?>
                                    <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach, $coach->id); ?>>
                                        <?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="sc-form-field">
                        <label for="filter_chapter">شعبه</label>
                        <select name="filter_chapter" id="filter_chapter">
                            <option value="">همه شعبه‌ها</option>
                            <?php foreach ($chapters as $chapter_item) : ?>
                                <option value="<?php echo esc_attr($chapter_item->name); ?>" <?php selected($filter_chapter, $chapter_item->name); ?>>
                                    <?php echo esc_html($chapter_item->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($tab === 'event_income') : ?>
                        <div class="sc-form-field">
                            <label for="filter_event_type">نوع</label>
                            <select name="filter_event_type" id="filter_event_type">
                                <option value="">همه</option>
                                <option value="event" <?php selected($filter_event_type, 'event'); ?>>رویداد</option>
                                <option value="competition" <?php selected($filter_event_type, 'competition'); ?>>مسابقه</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary">اعمال فیلتر</button>
                    <?php
                    $export_map = [
                        'course_income' => 'finance_course_income',
                        'event_income' => 'finance_event_income',
                        'coach_income' => 'finance_coach_income',
                        'club_share' => 'finance_club_share',
                        'receivables' => 'finance_receivables',
                        'cashflow' => 'finance_cashflow',
                        'ledger' => 'finance_ledger',
                    ];
                    $export_url = isset($export_map[$tab]) ? admin_url('admin.php?page=sc-reports-income-expenses&tab=' . $tab . '&sc_export=excel&export_type=' . $export_map[$tab]) : '';
                    foreach (['filter_date_from','filter_date_to','filter_date_from_shamsi','filter_date_to_shamsi','filter_course','filter_coach','filter_chapter','filter_event_type'] as $param) {
                        if (isset($_GET[$param]) && $_GET[$param] !== '') {
                            $export_url = add_query_arg($param, sanitize_text_field(wp_unslash($_GET[$param])), $export_url);
                        }
                    }
                    if ($export_url !== '') {
                        $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                    }
                    ?>
                    <?php if ($export_url !== '') : ?>
                        <a class="button" href="<?php echo esc_url($export_url); ?>">خروجی اکسل</a>
                    <?php endif; ?>
                </p>
            </form>

            <?php if ($tab === 'course_income') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $where = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.course_id > 0"];
                $args = [$filter_date_from, $filter_date_to];
                if ($filter_course > 0) { $where[] = "i.course_id = %d"; $args[] = $filter_course; }
                if ($filter_chapter !== '') { $where[] = "c.chapter = %s"; $args[] = $filter_chapter; }
                $sql = "SELECT c.id, c.title, c.chapter, COUNT(i.id) AS paid_count, SUM(i.amount) AS income_total
                        FROM $invoices_table i
                        INNER JOIN $courses_table c ON c.id = i.course_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY c.id, c.title, c.chapter
                        ORDER BY income_total DESC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                ?>
                <table class="wp-list-table widefat fixed striped">
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
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>رویداد/مسابقه</th><th>نوع</th><th>شعبه</th><th>تعداد پرداخت</th><th>درآمد (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) : ?>
                        <tr><td><?php echo esc_html($r->name); ?></td><td><?php echo esc_html($r->event_type === 'competition' ? 'مسابقه' : 'رویداد'); ?></td><td><?php echo esc_html($r->chapter ?: '-'); ?></td><td><?php echo (int) $r->paid_count; ?></td><td><?php echo esc_html(number_format((float) $r->income_total, 0, '.', ',')); ?></td></tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'coach_income') :
                $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
                $where = ["w.status = 'completed'", "w.transaction_type IN ('salary_percentage','salary_fixed')", "DATE(w.created_at) BETWEEN %s AND %s"];
                $args = [$filter_date_from, $filter_date_to];
                if ($filter_coach > 0) { $where[] = "w.coach_id = %d"; $args[] = $filter_coach; }
                if ($filter_chapter !== '') { $where[] = "co.chapter = %s"; $args[] = $filter_chapter; }
                $sql = "SELECT c.id, c.first_name, c.last_name, COUNT(w.id) AS tx_count, SUM(w.amount) AS coach_income
                        FROM $wallet_table w
                        INNER JOIN $coaches_table c ON c.id = w.coach_id
                        LEFT JOIN $courses_table co ON co.id = w.related_course_id
                        WHERE " . implode(' AND ', $where) . "
                        GROUP BY c.id, c.first_name, c.last_name
                        ORDER BY coach_income DESC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>مربی</th><th>تعداد تراکنش دستمزد</th><th>درآمد مربی (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) : ?>
                        <tr><td><?php echo esc_html(trim($r->first_name . ' ' . $r->last_name)); ?></td><td><?php echo (int) $r->tx_count; ?></td><td><?php echo esc_html(number_format((float) $r->coach_income, 0, '.', ',')); ?></td></tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="3">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'club_share') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
                $where_income = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.course_id > 0"];
                $where_salary = ["w.status = 'completed'", "w.transaction_type IN ('salary_percentage','salary_fixed')", "DATE(w.created_at) BETWEEN %s AND %s"];
                $args_income = [$filter_date_from, $filter_date_to];
                $args_salary = [$filter_date_from, $filter_date_to];
                if ($filter_course > 0) { $where_income[] = "i.course_id = %d"; $args_income[] = $filter_course; $where_salary[] = "w.related_course_id = %d"; $args_salary[] = $filter_course; }
                if ($filter_coach > 0) { $where_salary[] = "w.coach_id = %d"; $args_salary[] = $filter_coach; }
                if ($filter_chapter !== '') { $where_income[] = "c.chapter = %s"; $args_income[] = $filter_chapter; $where_salary[] = "co.chapter = %s"; $args_salary[] = $filter_chapter; }
                $total_income = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(i.amount),0) FROM $invoices_table i INNER JOIN $courses_table c ON c.id = i.course_id WHERE " . implode(' AND ', $where_income), $args_income));
                $coach_income = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(w.amount),0) FROM $wallet_table w LEFT JOIN $courses_table co ON co.id = w.related_course_id WHERE " . implode(' AND ', $where_salary), $args_salary));
                $club_share = $total_income - $coach_income;
                ?>
                <div class="sc-dashboard-stats">
                    <div class="sc-stat-box"><h3>درآمد کل دوره‌ها</h3><div><?php echo esc_html(number_format($total_income, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>درآمد مربیان</h3><div><?php echo esc_html(number_format($coach_income, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>درآمد مجموعه</h3><div><?php echo esc_html(number_format($club_share, 0, '.', ',')); ?></div></div>
                </div>
            <?php elseif ($tab === 'receivables') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $members_table = $wpdb->prefix . 'sc_members';
                $where = ["i.status = 'pending'", "DATE(i.created_at) BETWEEN %s AND %s"];
                $args = [$filter_date_from, $filter_date_to];
                if ($filter_course > 0) { $where[] = "i.course_id = %d"; $args[] = $filter_course; }
                if ($filter_chapter !== '') { $where[] = "c.chapter = %s"; $args[] = $filter_chapter; }
                $sql = "SELECT i.id, i.amount, i.created_at, m.first_name, m.last_name, c.title AS course_title, c.chapter
                        FROM $invoices_table i
                        LEFT JOIN $members_table m ON m.id = i.member_id
                        LEFT JOIN $courses_table c ON c.id = i.course_id
                        WHERE " . implode(' AND ', $where) . "
                        ORDER BY i.created_at DESC";
                $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>تاریخ</th><th>بازیکن</th><th>دوره</th><th>شعبه</th><th>مبلغ مطالبه (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($rows)) : foreach ($rows as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(substr((string) $r->created_at, 0, 10)); ?></td>
                            <td><?php echo esc_html(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''))); ?></td>
                            <td><?php echo esc_html($r->course_title ?: '-'); ?></td>
                            <td><?php echo esc_html($r->chapter ?: '-'); ?></td>
                            <td><?php echo esc_html(number_format((float) $r->amount, 0, '.', ',')); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5">موردی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'cashflow') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $expenses_table = $wpdb->prefix . 'sc_expenses';
                $where_in = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s"];
                $where_out = ["e.expense_date_gregorian IS NOT NULL", "DATE(e.expense_date_gregorian) BETWEEN %s AND %s"];
                $args_in = [$filter_date_from, $filter_date_to];
                $args_out = [$filter_date_from, $filter_date_to];
                if ($filter_chapter !== '') { $where_in[] = "c.chapter = %s"; $args_in[] = $filter_chapter; $where_out[] = "e.chapter = %s"; $args_out[] = $filter_chapter; }
                if ($filter_course > 0) { $where_in[] = "i.course_id = %d"; $args_in[] = $filter_course; }
                $cash_in = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(i.amount),0) FROM $invoices_table i LEFT JOIN $courses_table c ON c.id = i.course_id WHERE " . implode(' AND ', $where_in), $args_in));
                $cash_out = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(e.amount),0) FROM $expenses_table e WHERE " . implode(' AND ', $where_out), $args_out));
                $net_cashflow = $cash_in - $cash_out;
                ?>
                <div class="sc-dashboard-stats">
                    <div class="sc-stat-box"><h3>ورودی نقدی</h3><div><?php echo esc_html(number_format($cash_in, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>خروجی نقدی</h3><div><?php echo esc_html(number_format($cash_out, 0, '.', ',')); ?></div></div>
                    <div class="sc-stat-box"><h3>خالص جریان نقدی</h3><div><?php echo esc_html(number_format($net_cashflow, 0, '.', ',')); ?></div></div>
                </div>
            <?php elseif ($tab === 'ledger') :
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                $members_table = $wpdb->prefix . 'sc_members';
                $expenses_table = $wpdb->prefix . 'sc_expenses';
                $where_in = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s"];
                $args_in = [$filter_date_from, $filter_date_to];
                if ($filter_chapter !== '') { $where_in[] = "c.chapter = %s"; $args_in[] = $filter_chapter; }
                if ($filter_course > 0) { $where_in[] = "i.course_id = %d"; $args_in[] = $filter_course; }
                $income_rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(i.payment_date) AS tx_date, 'income' AS tx_type, i.amount, CONCAT(m.first_name, ' ', m.last_name) AS person_name, c.title AS ref_title, c.chapter
                    FROM $invoices_table i
                    LEFT JOIN $members_table m ON m.id = i.member_id
                    LEFT JOIN $courses_table c ON c.id = i.course_id
                    WHERE " . implode(' AND ', $where_in), $args_in));

                $where_out = ["e.expense_date_gregorian IS NOT NULL", "DATE(e.expense_date_gregorian) BETWEEN %s AND %s"];
                $args_out = [$filter_date_from, $filter_date_to];
                if ($filter_chapter !== '') { $where_out[] = "e.chapter = %s"; $args_out[] = $filter_chapter; }
                $expense_rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(e.expense_date_gregorian) AS tx_date, 'expense' AS tx_type, e.amount, '' AS person_name, e.name AS ref_title, e.chapter
                    FROM $expenses_table e
                    WHERE " . implode(' AND ', $where_out), $args_out));
                $ledger_rows = array_merge($income_rows ?: [], $expense_rows ?: []);
                usort($ledger_rows, static function($a, $b) {
                    return strcmp((string) $b->tx_date, (string) $a->tx_date);
                });
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>تاریخ</th><th>نوع</th><th>شرح</th><th>شخص</th><th>شعبه</th><th>مبلغ (تومان)</th></tr></thead>
                    <tbody>
                    <?php if (!empty($ledger_rows)) : foreach ($ledger_rows as $r) : ?>
                        <tr>
                            <td><?php echo esc_html($r->tx_date); ?></td>
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
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
