<?php
/**
 * Business intelligence / analytics helpers for club reports.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse date filters from GET (Shamsi + Gregorian).
 *
 * @return array{from:string,to:string,from_shamsi:string,to_shamsi:string}
 */
function sc_bi_parse_date_filters($default_months = 6) {
    $from        = '';
    $to          = '';
    $from_shamsi = '';
    $to_shamsi   = '';

    if (!empty($_GET['filter_date_from_shamsi'])) {
        $from_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi']));
        $from        = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($from_shamsi) : '';
    }
    if (!empty($_GET['filter_date_to_shamsi'])) {
        $to_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi']));
        $to        = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($to_shamsi) : '';
    }
    if ($from === '' && !empty($_GET['filter_date_from'])) {
        $from = sanitize_text_field(wp_unslash($_GET['filter_date_from']));
    }
    if ($to === '' && !empty($_GET['filter_date_to'])) {
        $to = sanitize_text_field(wp_unslash($_GET['filter_date_to']));
    }

    if ($from === '' || $to === '') {
        $today = new DateTimeImmutable('today', wp_timezone());
        $start = $today->modify('-' . max(1, (int) $default_months) . ' months');
        $from  = $start->format('Y-m-d');
        $to    = $today->format('Y-m-d');
        if (function_exists('gregorian_to_jalali')) {
            $fj = gregorian_to_jalali((int) $start->format('Y'), (int) $start->format('m'), (int) $start->format('d'));
            $tj = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
            $from_shamsi = $fj[0] . '/' . str_pad((string) $fj[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $fj[2], 2, '0', STR_PAD_LEFT);
            $to_shamsi   = $tj[0] . '/' . str_pad((string) $tj[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $tj[2], 2, '0', STR_PAD_LEFT);
        }
    } elseif ($from_shamsi === '' && function_exists('sc_date_shamsi_date_only')) {
        $from_shamsi = sc_date_shamsi_date_only($from);
        $to_shamsi   = sc_date_shamsi_date_only($to);
    }

    return [
        'from'        => $from,
        'to'          => $to,
        'from_shamsi' => $from_shamsi,
        'to_shamsi'   => $to_shamsi,
    ];
}

/**
 * Build month buckets inside a date range.
 *
 * @return array<int, array{label:string,start:string,end:string}>
 */
function sc_bi_month_buckets($date_from, $date_to) {
    $buckets     = [];
    $range_start = new DateTimeImmutable($date_from, wp_timezone());
    $range_end   = new DateTimeImmutable($date_to, wp_timezone());
    $cursor      = $range_start->modify('first day of this month');
    $last_month  = $range_end->modify('first day of this month');

    while ($cursor <= $last_month) {
        $month_start = $cursor > $range_start ? $cursor : $range_start;
        $month_end   = $cursor->modify('last day of this month');
        if ($month_end > $range_end) {
            $month_end = $range_end;
        }
        $ms = $month_start->format('Y-m-d');
        $buckets[] = [
            'label' => function_exists('sc_date_shamsi') ? sc_date_shamsi($ms, 'Y/m') : $ms,
            'start' => $ms,
            'end'   => $month_end->format('Y-m-d'),
        ];
        $cursor = $cursor->modify('+1 month');
    }

    return $buckets;
}

/**
 * Member IDs with at least one active course enrollment at a given date (club-wide player).
 *
 * @param int[] $course_ids Optional restrict to courses.
 * @return int[]
 */
function sc_bi_get_active_member_ids_at_date($date_ymd, array $course_ids = []) {
    global $wpdb;
    $mc      = $wpdb->prefix . 'sc_member_courses';
    $members = $wpdb->prefix . 'sc_members';

    $where  = [
        'DATE(COALESCE(NULLIF(mc.enrollment_date, \'0000-00-00\'), DATE(mc.created_at))) <= %s',
        '(mc.status = \'active\' OR (mc.status <> \'active\' AND DATE(mc.updated_at) > %s))',
    ];
    $params = [$date_ymd, $date_ymd];

    if (empty($course_ids)
        && function_exists('sc_user_is_secretary_only')
        && sc_user_is_secretary_only()
        && function_exists('sc_secretary_get_branch_course_ids')
    ) {
        $course_ids = sc_secretary_get_branch_course_ids();
        if (empty($course_ids)) {
            return [];
        }
    }

    if (!empty($course_ids)) {
        $holders = implode(',', array_fill(0, count($course_ids), '%d'));
        $where[] = "mc.course_id IN ($holders)";
        $params  = array_merge($params, $course_ids);
    }

    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
        && function_exists('sc_secretary_member_enrollment_match_sql')) {
        $match = sc_secretary_member_enrollment_match_sql('mc');
        if ($match['sql'] === '1=0') {
            return [];
        }
        $where[] = $match['sql'];
        $params = array_merge($params, $match['args']);
    }

    $sql = "SELECT DISTINCT mc.member_id
            FROM $mc mc
            INNER JOIN $members m ON m.id = mc.member_id AND m.is_active = 1
            WHERE " . implode(' AND ', $where);

    $rows = $wpdb->get_col($wpdb->prepare($sql, ...$params));
    return array_map('absint', $rows ?: []);
}

/**
 * Count club-active members at date.
 */
function sc_bi_count_club_active_members_at_date($date_ymd, array $course_ids = []) {
    return count(sc_bi_get_active_member_ids_at_date($date_ymd, $course_ids));
}

/**
 * Monthly club member metrics: active snapshot, new, churn, renewal rate.
 *
 * @return array<int, array{month:string,active:int,new:int,churn:int,renewed:int,renewal_rate:float,churn_rate:float}>
 */
function sc_bi_club_monthly_member_metrics($date_from, $date_to) {
    global $wpdb;
    $mc       = $wpdb->prefix . 'sc_member_courses';
    $members  = $wpdb->prefix . 'sc_members';
    $invoices = $wpdb->prefix . 'sc_invoices';
    $rows     = [];

    foreach (sc_bi_month_buckets($date_from, $date_to) as $bucket) {
        $ms           = $bucket['start'];
        $me           = $bucket['end'];
        $active_start = sc_bi_get_active_member_ids_at_date($ms);
        $active_end   = sc_bi_get_active_member_ids_at_date($me);
        $active_count = count($active_end);

        // New club members: first enrollment ever falls in this month.
        $new_where = ['1=1'];
        $new_args = [$ms, $me];
        if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
            && function_exists('sc_secretary_member_enrollment_match_sql')) {
            $match = sc_secretary_member_enrollment_match_sql('mc');
            if ($match['sql'] === '1=0') {
                $new_count = 0;
            } else {
                $new_where[] = $match['sql'];
                $new_args = array_merge($match['args'], $new_args);
                $new_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM (
                        SELECT mc.member_id,
                               MIN(DATE(COALESCE(NULLIF(mc.enrollment_date, '0000-00-00'), DATE(mc.created_at)))) AS first_enroll
                        FROM $mc mc
                        INNER JOIN $members m ON m.id = mc.member_id
                        WHERE " . implode(' AND ', $new_where) . "
                        GROUP BY mc.member_id
                        HAVING first_enroll >= %s AND first_enroll <= %s
                     ) t",
                    ...$new_args
                ));
            }
        } else {
            $new_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM (
                    SELECT mc.member_id,
                           MIN(DATE(COALESCE(NULLIF(mc.enrollment_date, '0000-00-00'), DATE(mc.created_at)))) AS first_enroll
                    FROM $mc mc
                    INNER JOIN $members m ON m.id = mc.member_id
                    GROUP BY mc.member_id
                    HAVING first_enroll >= %s AND first_enroll <= %s
                 ) t",
                $ms,
                $me
            ));
        }

        $start_set = array_flip($active_start);
        $end_set   = array_flip($active_end);
        $churned   = 0;
        foreach ($active_start as $mid) {
            if (!isset($end_set[$mid])) {
                $churned++;
            }
        }

        // Renewed: paid invoice in month where member had a prior paid invoice before month start.
        $renew_where = [
            "i.status IN ('paid','completed','processing')",
            'i.payment_date IS NOT NULL',
            'DATE(i.payment_date) >= %s',
            'DATE(i.payment_date) <= %s',
            "EXISTS (
                   SELECT 1 FROM $invoices i2
                   WHERE i2.member_id = i.member_id
                     AND i2.status IN ('paid','completed','processing')
                     AND i2.payment_date IS NOT NULL
                     AND DATE(i2.payment_date) < %s
               )",
        ];
        $renew_args = [$ms, $me, $ms];
        if (function_exists('sc_secretary_merge_invoice_where')) {
            sc_secretary_merge_invoice_where($renew_where, $renew_args, 'i');
        }
        $renewed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT i.member_id)
             FROM $invoices i
             WHERE " . implode(' AND ', $renew_where),
            ...$renew_args
        ));

        $eligible_renewal = count($active_start);
        $renewal_rate     = $eligible_renewal > 0 ? round(($renewed / $eligible_renewal) * 100, 1) : 0.0;
        $churn_rate       = count($active_start) > 0 ? round(($churned / count($active_start)) * 100, 1) : 0.0;

        $rows[] = [
            'month'         => $bucket['label'],
            'active'        => $active_count,
            'new'           => $new_count,
            'churn'         => $churned,
            'renewed'       => $renewed,
            'renewal_rate'  => $renewal_rate,
            'churn_rate'    => $churn_rate,
        ];
    }

    return $rows;
}

/**
 * Revenue by branch (chapter) in date range.
 *
 * @return array<int, object{chapter:string,revenue:float,invoice_count:int}>
 */
function sc_bi_branch_revenue_rows($date_from, $date_to) {
    global $wpdb;
    $invoices = $wpdb->prefix . 'sc_invoices';
    $courses  = $wpdb->prefix . 'sc_courses';
    $incomes  = $wpdb->prefix . 'sc_incomes';

    $where = [
        "i.status IN ('paid','completed','processing')",
        "i.payment_date IS NOT NULL",
        "i.course_id > 0",
        "DATE(i.payment_date) >= %s",
        "DATE(i.payment_date) <= %s",
    ];
    $args = [$date_from, $date_to];
    if (function_exists('sc_secretary_merge_invoice_where')) {
        sc_secretary_merge_invoice_where($where, $args, 'i');
    }

    $invoice_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT COALESCE(NULLIF(TRIM(c.chapter), ''), 'بدون شعبه') AS chapter,
                COUNT(i.id) AS invoice_count,
                COALESCE(SUM(i.amount), 0) AS revenue
         FROM $invoices i
         INNER JOIN $courses c ON c.id = i.course_id
         WHERE " . implode(' AND ', $where) . "
         GROUP BY chapter
         ORDER BY revenue DESC",
        $args
    )) ?: [];

    $manual_where = [
        'mi.income_date_gregorian IS NOT NULL',
        'DATE(mi.income_date_gregorian) >= %s',
        'DATE(mi.income_date_gregorian) <= %s',
    ];
    $manual_args = [$date_from, $date_to];
    if (function_exists('sc_secretary_merge_income_where')) {
        sc_secretary_merge_income_where($manual_where, $manual_args, 'mi');
    }
    $manual_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT COALESCE(NULLIF(TRIM(mi.chapter), ''), 'بدون شعبه') AS chapter,
                COUNT(mi.id) AS invoice_count,
                COALESCE(SUM(mi.amount), 0) AS revenue
         FROM $incomes mi
         WHERE " . implode(' AND ', $manual_where) . "
         GROUP BY chapter",
        $manual_args
    )) ?: [];

    $merged = [];
    foreach (array_merge($invoice_rows, $manual_rows) as $row) {
        $ch = (string) $row->chapter;
        if (!isset($merged[$ch])) {
            $merged[$ch] = (object) [
                'chapter' => $ch,
                'invoice_count' => 0,
                'revenue' => 0.0,
            ];
        }
        $merged[$ch]->invoice_count += (int) $row->invoice_count;
        $merged[$ch]->revenue += (float) $row->revenue;
    }

    $out = array_values($merged);
    usort($out, static function ($a, $b) {
        return $b->revenue <=> $a->revenue;
    });
    return $out;
}

/**
 * Monthly revenue by branch for line chart.
 *
 * @return array<string, array<int, array{month:string,revenue:float}>>
 */
function sc_bi_branch_monthly_revenue($date_from, $date_to) {
    global $wpdb;
    $invoices = $wpdb->prefix . 'sc_invoices';
    $courses  = $wpdb->prefix . 'sc_courses';
    $incomes  = $wpdb->prefix . 'sc_incomes';
    $by_chapter = [];

    foreach (sc_bi_month_buckets($date_from, $date_to) as $bucket) {
        $where = [
            "i.status IN ('paid','completed','processing')",
            "i.payment_date IS NOT NULL",
            "i.course_id > 0",
            "DATE(i.payment_date) >= %s",
            "DATE(i.payment_date) <= %s",
        ];
        $args = [$bucket['start'], $bucket['end']];
        if (function_exists('sc_secretary_merge_invoice_where')) {
            sc_secretary_merge_invoice_where($where, $args, 'i');
        }
        $branch_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(TRIM(c.chapter), ''), 'بدون شعبه') AS chapter,
                    COALESCE(SUM(i.amount), 0) AS revenue
             FROM $invoices i
             INNER JOIN $courses c ON c.id = i.course_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY chapter",
            $args
        )) ?: [];

        $manual_where = [
            'mi.income_date_gregorian IS NOT NULL',
            'DATE(mi.income_date_gregorian) >= %s',
            'DATE(mi.income_date_gregorian) <= %s',
        ];
        $manual_args = [$bucket['start'], $bucket['end']];
        if (function_exists('sc_secretary_merge_income_where')) {
            sc_secretary_merge_income_where($manual_where, $manual_args, 'mi');
        }
        $manual_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(NULLIF(TRIM(mi.chapter), ''), 'بدون شعبه') AS chapter,
                    COALESCE(SUM(mi.amount), 0) AS revenue
             FROM $incomes mi
             WHERE " . implode(' AND ', $manual_where) . "
             GROUP BY chapter",
            $manual_args
        )) ?: [];

        $month_map = [];
        foreach (array_merge($branch_rows, $manual_rows) as $row) {
            $ch = (string) $row->chapter;
            if (!isset($month_map[$ch])) {
                $month_map[$ch] = 0.0;
            }
            $month_map[$ch] += (float) $row->revenue;
        }

        foreach ($month_map as $ch => $revenue) {
            if (!isset($by_chapter[$ch])) {
                $by_chapter[$ch] = [];
            }
            $by_chapter[$ch][] = [
                'month'   => $bucket['label'],
                'revenue' => $revenue,
            ];
        }
    }

    // Fill missing months with zero for each branch.
    $labels = array_column(sc_bi_month_buckets($date_from, $date_to), 'label');
    foreach ($by_chapter as $ch => $series) {
        $map = [];
        foreach ($series as $point) {
            $map[$point['month']] = $point['revenue'];
        }
        $filled = [];
        foreach ($labels as $label) {
            $filled[] = ['month' => $label, 'revenue' => isset($map[$label]) ? (float) $map[$label] : 0.0];
        }
        $by_chapter[$ch] = $filled;
    }

    return $by_chapter;
}

/**
 * Popular courses by metric.
 *
 * @param string $metric enrolled|revenue|attendance
 * @return array<int, object>
 */
function sc_bi_popular_courses($date_from, $date_to, $metric = 'enrolled', $limit = 15) {
    global $wpdb;
    $courses  = $wpdb->prefix . 'sc_courses';
    $mc       = $wpdb->prefix . 'sc_member_courses';
    $invoices = $wpdb->prefix . 'sc_invoices';
    $att      = $wpdb->prefix . 'sc_attendances';
    $limit    = max(1, min(50, (int) $limit));

    if ($metric === 'revenue') {
        $where = [
            "i.status IN ('paid','completed','processing')",
            "i.payment_date IS NOT NULL",
            "DATE(i.payment_date) >= %s",
            "DATE(i.payment_date) <= %s",
        ];
        $args = [$date_from, $date_to];
        if (function_exists('sc_secretary_merge_finance_course_scope')) {
            sc_secretary_merge_finance_course_scope($where, $args, 'i.course_id');
        }
        if (function_exists('sc_secretary_merge_invoice_where')) {
            sc_secretary_merge_invoice_where($where, $args, 'i');
        }
        $args[] = $limit;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.title, c.chapter,
                    COUNT(i.id) AS metric_count,
                    COALESCE(SUM(i.amount), 0) AS metric_value
             FROM $invoices i
             INNER JOIN $courses c ON c.id = i.course_id AND c.deleted_at IS NULL
             WHERE " . implode(' AND ', $where) . "
             GROUP BY c.id, c.title, c.chapter
             ORDER BY metric_value DESC
             LIMIT %d",
            $args
        )) ?: [];
    }

    if ($metric === 'attendance') {
        $where = [
            'a.attendance_date >= %s',
            'a.attendance_date <= %s',
            "a.status = 'present'",
        ];
        $args = [$date_from, $date_to];
        if (function_exists('sc_secretary_merge_course_ids_where')) {
            sc_secretary_merge_course_ids_where($where, $args, 'c.id');
        }
        $args[] = $limit;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.title, c.chapter,
                    COUNT(a.id) AS metric_count,
                    COUNT(a.id) AS metric_value
             FROM $att a
             INNER JOIN $courses c ON c.id = a.course_id AND c.deleted_at IS NULL
             WHERE " . implode(' AND ', $where) . "
             GROUP BY c.id, c.title, c.chapter
             ORDER BY metric_value DESC
             LIMIT %d",
            $args
        )) ?: [];
    }

    $where = ['c.deleted_at IS NULL', 'c.is_active = 1', "mc.status = 'active'"];
    $params = [];
    if (function_exists('sc_secretary_merge_course_ids_where')) {
        sc_secretary_merge_course_ids_where($where, $params, 'c.id');
    }
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
        && function_exists('sc_secretary_member_enrollment_match_sql')) {
        $match = sc_secretary_member_enrollment_match_sql('mc');
        if ($match['sql'] === '1=0') {
            return [];
        }
        $where[] = $match['sql'];
        $params = array_merge($params, $match['args']);
    }
    $params[] = $limit;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.title, c.chapter,
                COUNT(DISTINCT mc.member_id) AS metric_count,
                COUNT(DISTINCT mc.member_id) AS metric_value
         FROM $courses c
         INNER JOIN $mc mc ON mc.course_id = c.id AND mc.status = 'active'
         WHERE " . implode(' AND ', $where) . "
         GROUP BY c.id, c.title, c.chapter
         ORDER BY metric_value DESC
         LIMIT %d",
        $params
    )) ?: [];
}

/**
 * Coach summary rows for BI ranking.
 *
 * @return array<int, object>
 */
function sc_bi_coaches_summary($date_from, $date_to) {
    global $wpdb;
    $coaches  = $wpdb->prefix . 'sc_coaches';
    $cc       = $wpdb->prefix . 'sc_course_coaches';
    $wallet   = $wpdb->prefix . 'sc_coach_wallet_transactions';
    $invoices = $wpdb->prefix . 'sc_invoices';

    $branch_coach_ids = null;
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_coach_ids')) {
        $branch_coach_ids = array_fill_keys(sc_secretary_get_branch_coach_ids(), true);
    }

    if ($branch_coach_ids !== null) {
        if (empty($branch_coach_ids)) {
            return [];
        }
        $coach_id_list = array_keys($branch_coach_ids);
        $holders = implode(',', array_fill(0, count($coach_id_list), '%d'));
        $coach_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name FROM $coaches WHERE is_active = 1 AND id IN ($holders) ORDER BY first_name, last_name",
            ...$coach_id_list
        )) ?: [];
    } else {
        $coach_rows = $wpdb->get_results(
            "SELECT id, first_name, last_name FROM $coaches WHERE is_active = 1 ORDER BY first_name, last_name"
        ) ?: [];
    }

    $summaries = [];
    foreach ($coach_rows as $coach) {
        $coach_id   = (int) $coach->id;
        $course_ids = array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT course_id FROM $cc WHERE coach_id = %d",
            $coach_id
        )) ?: []);
        if (function_exists('sc_secretary_filter_course_ids')) {
            $course_ids = sc_secretary_filter_course_ids($course_ids);
        }
        if ($branch_coach_ids !== null && empty($course_ids)) {
            continue;
        }

        $active_now = !empty($course_ids)
            ? sc_bi_count_club_active_members_at_date($date_to, $course_ids)
            : 0;

        $wallet_where = [
            'coach_id = %d',
            "status = 'completed'",
            "transaction_type IN ('salary_percentage','salary_fixed')",
            'DATE(created_at) >= %s',
            'DATE(created_at) <= %s',
        ];
        $wallet_args = [$coach_id, $date_from, $date_to];
        if (function_exists('sc_secretary_merge_coach_wallet_course_scope')) {
            sc_secretary_merge_coach_wallet_course_scope($wallet_where, $wallet_args);
        }
        $coach_income = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $wallet w
             WHERE " . implode(' AND ', $wallet_where),
            ...$wallet_args
        ));

        $class_revenue = 0.0;
        if (!empty($course_ids)) {
            $holders = implode(',', array_fill(0, count($course_ids), '%d'));
            $invoice_where = [
                "i.status IN ('paid','completed','processing')",
                'i.payment_date IS NOT NULL',
                'DATE(i.payment_date) >= %s',
                'DATE(i.payment_date) <= %s',
                "i.course_id IN ($holders)",
            ];
            $invoice_args = array_merge([$date_from, $date_to], $course_ids);
            if (function_exists('sc_secretary_merge_invoice_where')) {
                sc_secretary_merge_invoice_where($invoice_where, $invoice_args, 'i');
            }
            $class_revenue = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(i.amount), 0) FROM $invoices i
                 WHERE " . implode(' AND ', $invoice_where),
                ...$invoice_args
            ));
        }

        $summaries[] = (object) [
            'coach_id'      => $coach_id,
            'name'          => trim($coach->first_name . ' ' . $coach->last_name),
            'active_now'    => $active_now,
            'coach_income'  => $coach_income,
            'class_revenue' => $class_revenue,
            'course_count'  => count($course_ids),
        ];
    }

    usort($summaries, static function ($a, $b) {
        return $b->active_now <=> $a->active_now;
    });

    return $summaries;
}

/**
 * Monthly coach student & performance metrics.
 *
 * @param int[] $course_ids
 * @return array<int, array{month:string,active:int,new:int,churn:int,income:float,avg_attendance:float}>
 */
function sc_bi_coach_monthly_metrics($coach_id, array $course_ids, $date_from, $date_to) {
    global $wpdb;
    $mc     = $wpdb->prefix . 'sc_member_courses';
    $wallet = $wpdb->prefix . 'sc_coach_wallet_transactions';
    $salary = $wpdb->prefix . 'sc_coach_salary_records';
    $rows   = [];

    foreach (sc_bi_month_buckets($date_from, $date_to) as $bucket) {
        $ms = $bucket['start'];
        $me = $bucket['end'];

        $active_start_ids = sc_bi_get_active_member_ids_at_date($ms, $course_ids);
        $active_end_ids   = sc_bi_get_active_member_ids_at_date($me, $course_ids);
        $active_count     = count($active_end_ids);

        $new_count = 0;
        if (!empty($course_ids)) {
            $holders = implode(',', array_fill(0, count($course_ids), '%d'));
            $args    = array_merge($course_ids, [$ms, $me]);
            $new_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT mc.member_id) FROM $mc mc
                 WHERE mc.course_id IN ($holders)
                   AND DATE(COALESCE(NULLIF(mc.enrollment_date, '0000-00-00'), DATE(mc.created_at))) >= %s
                   AND DATE(COALESCE(NULLIF(mc.enrollment_date, '0000-00-00'), DATE(mc.created_at))) <= %s",
                ...$args
            ));
        }

        $end_set = array_flip($active_end_ids);
        $churn   = 0;
        foreach ($active_start_ids as $mid) {
            if (!isset($end_set[$mid])) {
                $churn++;
            }
        }

        $wallet_where = [
            'coach_id = %d',
            "status = 'completed'",
            "transaction_type IN ('salary_percentage','salary_fixed')",
            'DATE(created_at) >= %s',
            'DATE(created_at) <= %s',
        ];
        $wallet_args = [(int) $coach_id, $ms, $me];
        if (function_exists('sc_secretary_merge_coach_wallet_course_scope')) {
            sc_secretary_merge_coach_wallet_course_scope($wallet_where, $wallet_args);
        } elseif (!empty($course_ids)) {
            $holders = implode(',', array_fill(0, count($course_ids), '%d'));
            $wallet_where[] = "related_course_id IN ($holders)";
            $wallet_args = array_merge($wallet_args, $course_ids);
        }
        $income = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $wallet w
             WHERE " . implode(' AND ', $wallet_where),
            ...$wallet_args
        ));

        $salary_where = [
            'coach_id = %d',
            'attendance_date >= %s',
            'attendance_date <= %s',
        ];
        $salary_args = [(int) $coach_id, $ms, $me];
        if (function_exists('sc_secretary_merge_salary_course_scope')) {
            sc_secretary_merge_salary_course_scope($salary_where, $salary_args);
        } elseif (!empty($course_ids)) {
            $holders = implode(',', array_fill(0, count($course_ids), '%d'));
            $salary_where[] = "course_id IN ($holders)";
            $salary_args = array_merge($salary_args, $course_ids);
        }
        $avg_attendance = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(AVG(attendance_count), 0) FROM $salary sr
             WHERE " . implode(' AND ', $salary_where),
            ...$salary_args
        ));

        $rows[] = [
            'month'          => $bucket['label'],
            'active'         => $active_count,
            'new'            => $new_count,
            'churn'          => $churn,
            'income'         => $income,
            'avg_attendance' => round($avg_attendance, 1),
        ];
    }

    return $rows;
}
