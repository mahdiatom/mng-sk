<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
$courses_table = $wpdb->prefix . 'sc_courses';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$members_table = $wpdb->prefix . 'sc_members';

$filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'pending_admin';
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;

$where = ['1=1'];
$values = [];
if ($filter_status !== 'all') {
    $where[] = 'b.status = %s';
    $values[] = $filter_status;
}
if ($filter_member > 0) {
    $where[] = 'b.member_id = %d';
    $values[] = $filter_member;
}
$where_clause = implode(' AND ', $where);

$count_sql = "SELECT COUNT(*) FROM {$bookings_table} b WHERE {$where_clause}";
$total_items = !empty($values)
    ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
    : (int) $wpdb->get_var($count_sql);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;
$total_pages = max(1, (int) ceil($total_items / $per_page));

$list_sql = "SELECT b.*, c.title AS course_title,
                    m.first_name, m.last_name, m.national_id,
                    co.first_name AS coach_first_name, co.last_name AS coach_last_name
             FROM {$bookings_table} b
             LEFT JOIN {$courses_table} c ON c.id = b.course_id
             LEFT JOIN {$members_table} m ON m.id = b.member_id
             LEFT JOIN {$coaches_table} co ON co.id = b.coach_id
             WHERE {$where_clause}
             ORDER BY b.created_at DESC
             LIMIT %d OFFSET %d";
$list_values = $values;
$list_values[] = $per_page;
$list_values[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_values));

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM {$members_table} WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");

$status_options = [
    'all' => 'همه وضعیت‌ها',
    'pending_admin' => 'در انتظار بررسی',
    'pending_payment' => 'منتظر پرداخت',
    'active' => 'فعال',
    'rejected' => 'رد شده',
    'paused' => 'متوقف',
    'cancelled' => 'لغو شده',
];
?>
<div class="wrap sc-private-admin-wrap sc-users-export-wrap">
    <div class="sc-private-admin-toolbar">
        <div>
            <h1 class="sc-private-admin-title">لیست رزرو کلاس‌های خصوصی</h1>
            <p class="sc-private-admin-subtitle">مدیریت درخواست‌ها و ثبت‌نام کلاس خصوصی بازیکنان</p>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-form')); ?>" class="page-title-action">ثبت‌نام کلاس خصوصی</a>
    </div>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p>رزرو با موفقیت ثبت شد و صورت‌حساب ایجاد گردید.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['rejected'])) : ?>
        <div class="notice notice-success is-dismissible"><p>درخواست رد شد.</p></div>
    <?php endif; ?>

    <div class="sc-private-admin-filters sc-users-export-card">
        <form method="get" action="">
            <input type="hidden" name="page" value="sc-private-booking-requests">
            <div class="sc-enroll-fields">
                <div class="sc-private-field-wrap">
                    <label class="sc-enroll-field-label" for="filter_status">وضعیت</label>
                    <select class="sc-enroll-select" id="filter_status" name="filter_status">
                        <?php foreach ($status_options as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_status, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-private-field-wrap">
                    <label class="sc-enroll-field-label" for="filter_member">بازیکن</label>
                    <select class="sc-enroll-select" id="filter_member" name="filter_member">
                        <option value="0">همه بازیکنان</option>
                        <?php foreach ($members as $member) : ?>
                            <option value="<?php echo esc_attr((int) $member->id); ?>" <?php selected($filter_member, (int) $member->id); ?>>
                                <?php echo esc_html(trim($member->first_name . ' ' . $member->last_name) . ' - ' . $member->national_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="submit" style="margin-bottom:0;">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a class="sc_button" href="<?php echo esc_url(admin_url('admin.php?page=sc-private-booking-requests')); ?>">پاک کردن</a>
            </p>
        </form>
    </div>

    <?php if (empty($rows)) : ?>
        <div class="sc-users-export-card"><p>رکوردی یافت نشد.</p></div>
    <?php else : ?>
        <div class="sc-private-admin-table-card">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>بازیکن</th>
                        <th>دوره</th>
                        <th>شعبه</th>
                        <th>مربی</th>
                        <th>جلسات</th>
                        <th>وضعیت</th>
                        <th>صورت‌حساب</th>
                        <th>تاریخ ثبت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $status_label = function_exists('sc_private_booking_status_label')
                            ? sc_private_booking_status_label((string) $row->status)
                            : $row->status;
                        $badge_class = function_exists('sc_private_booking_status_badge_class')
                            ? sc_private_booking_status_badge_class((string) $row->status)
                            : 'sc-pb-status';
                        $form_url = add_query_arg(['page' => 'sc-private-booking-form', 'booking_id' => (int) $row->id], admin_url('admin.php'));
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html((string) $row->id); ?></strong></td>
                            <td><?php echo esc_html(trim($row->first_name . ' ' . $row->last_name)); ?></td>
                            <td><?php echo esc_html($row->course_title ?: '—'); ?></td>
                            <td><?php echo esc_html($row->chapter !== '' ? $row->chapter : '—'); ?></td>
                            <td><?php echo esc_html(trim(($row->coach_first_name ?? '') . ' ' . ($row->coach_last_name ?? '')) ?: '—'); ?></td>
                            <td><?php echo (int) $row->package_sessions > 0 ? esc_html((string) $row->package_sessions) : '—'; ?></td>
                            <td><span class="<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td>
                                <?php if (!empty($row->invoice_id)) : ?>
                                    <a class="button button-small sc-private-invoice-btn" href="<?php echo esc_url(admin_url('admin.php?page=sc-invoices&invoice_id=' . (int) $row->invoice_id)); ?>">صورت‌حساب #<?php echo esc_html((string) $row->invoice_id); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($row->created_at) : $row->created_at); ?></td>
                            <td>
                                <?php if ((string) $row->status === 'pending_admin') : ?>
                                    <a class="sc_button button-primary button-small" href="<?php echo esc_url($form_url); ?>">تکمیل و ثبت</a>
                                <?php else : ?>
                                    <a class="button button-small" href="<?php echo esc_url($form_url); ?>">مشاهده</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '< قبلی',
                        'next_text' => 'بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => [
                            'page' => 'sc-private-booking-requests',
                            'filter_status' => $filter_status,
                            'filter_member' => $filter_member,
                        ],
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
