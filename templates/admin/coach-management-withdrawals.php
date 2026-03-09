<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$withdrawal_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
$coaches_table = $wpdb->prefix . 'sc_coaches';

// پردازش عملیات
$action_message = '';
$action_message_type = '';

if (isset($_POST['approve_request']) && isset($_POST['request_id']) && check_admin_referer('approve_withdrawal_' . $_POST['request_id'])) {
    $request_id = absint($_POST['request_id']);
    $result = sc_approve_coach_withdrawal_request($request_id);
    if ($result['success']) {
        $action_message = $result['message'];
        $action_message_type = 'success';
    } else {
        $action_message = $result['message'];
        $action_message_type = 'error';
    }
}

if (isset($_POST['reject_request']) && isset($_POST['request_id']) && check_admin_referer('reject_withdrawal_action', 'reject_withdrawal_nonce')) {
    $request_id = absint($_POST['request_id']);
    $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_text_field($_POST['rejection_reason']) : '';
    $result = sc_reject_coach_withdrawal_request($request_id, $rejection_reason);
    if ($result['success']) {
        $action_message = $result['message'];
        $action_message_type = 'success';
    } else {
        $action_message = $result['message'];
        $action_message_type = 'error';
    }
}

if (isset($_POST['mark_paid']) && isset($_POST['request_id']) && check_admin_referer('mark_paid_withdrawal_' . $_POST['request_id'])) {
    $request_id = absint($_POST['request_id']);
    $payment_note = isset($_POST['payment_note']) ? sanitize_text_field($_POST['payment_note']) : '';
    $result = sc_mark_coach_withdrawal_paid($request_id, $payment_note);
    if ($result['success']) {
        $action_message = $result['message'];
        $action_message_type = 'success';
    } else {
        $action_message = $result['message'];
        $action_message_type = 'error';
    }
}

if (isset($_POST['delete_request']) && isset($_POST['request_id']) && check_admin_referer('delete_withdrawal_' . $_POST['request_id'])) {
    $request_id = absint($_POST['request_id']);
    $result = sc_delete_coach_withdrawal_request($request_id);
    if ($result['success']) {
        $action_message = $result['message'];
        $action_message_type = 'success';
    } else {
        $action_message = $result['message'];
        $action_message_type = 'error';
    }
}

// پردازش اکشن دسته‌جمعی
if (isset($_POST['bulk_action']) && isset($_POST['request_ids']) && is_array($_POST['request_ids']) && check_admin_referer('bulk_withdrawals', '_wpnonce_bulk')) {
    $bulk_action = sanitize_text_field($_POST['bulk_action']);
    $request_ids = array_map('absint', $_POST['request_ids']);
    $request_ids = array_filter($request_ids);
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($request_ids as $rid) {
        if ($bulk_action === 'approve') {
            $result = sc_approve_coach_withdrawal_request($rid);
        } elseif ($bulk_action === 'reject') {
            $reason = isset($_POST['bulk_rejection_reason']) ? sanitize_text_field($_POST['bulk_rejection_reason']) : '';
            $result = sc_reject_coach_withdrawal_request($rid, $reason);
        } elseif ($bulk_action === 'mark_paid') {
            $result = sc_mark_coach_withdrawal_paid($rid);
        } else {
            continue;
        }
        
        if ($result['success']) {
            $success_count++;
        } else {
            $error_count++;
            $errors[] = $result['message'];
        }
    }
    
    if ($success_count > 0) {
        $action_message = sprintf('%d مورد با موفقیت پردازش شد.', $success_count);
        $action_message_type = 'success';
    }
    if ($error_count > 0) {
        $action_message = ($action_message ? $action_message . ' ' : '') . sprintf('%d مورد خطا: %s', $error_count, implode('؛ ', array_slice(array_unique($errors), 0, 3)));
        $action_message_type = $error_count > 0 && $success_count === 0 ? 'error' : 'warning';
    }
}

// دریافت فیلترها
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
$filter_coach  = isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;

// پردازش فیلترهای تاریخ — فقط از GET، بدون اعمال پیش‌فرض در فیلتر
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
$filter_date_from = '';
$filter_date_to   = '';
if (!empty($filter_date_from_shamsi)) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
} elseif (isset($_GET['filter_date_from']) && $_GET['filter_date_from'] !== '') {
    $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    $filter_date_from_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_from) : '';
}
if (!empty($filter_date_to_shamsi)) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
} elseif (isset($_GET['filter_date_to']) && $_GET['filter_date_to'] !== '') {
    $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    $filter_date_to_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_to) : '';
}
// فقط برای نمایش در فیلدها: وقتی کاربر تاریخی نفرستاده امروز نشان بده
$today_shamsi_w = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
if (!$today_shamsi_w && function_exists('gregorian_to_jalali')) {
    $today = new DateTime(current_time('Y-m-d'));
    $jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
    $today_shamsi_w = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
}
$display_date_from_shamsi_w = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi_w;
$display_date_to_shamsi_w   = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi_w;

// ساخت WHERE clause
$where_conditions = ['1=1'];
$where_values = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "w.status = %s";
    $where_values[] = $filter_status;
}

if ($filter_coach > 0) {
    $where_conditions[] = "w.coach_id = %d";
    $where_values[] = $filter_coach;
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(w.created_at) >= %s";
    $where_values[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(w.created_at) <= %s";
    $where_values[] = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت لیست مربیان برای فیلتر
$coaches_for_filter = $wpdb->get_results(
    "SELECT id, first_name, last_name 
     FROM $coaches_table 
     WHERE is_active = 1 
     ORDER BY last_name ASC, first_name ASC"
);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

$count_sql = "SELECT COUNT(*) FROM $withdrawal_table w INNER JOIN $coaches_table c ON w.coach_id = c.id WHERE $where_clause";
$total_items = !empty($where_values) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values)) : (int) $wpdb->get_var($count_sql);
$total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $per_page;

$query = "SELECT w.*, c.first_name, c.last_name 
          FROM $withdrawal_table w
          INNER JOIN $coaches_table c ON w.coach_id = c.id
          WHERE $where_clause
          ORDER BY w.created_at DESC
          LIMIT %d OFFSET %d";

$query_values = array_merge($where_values, [$per_page, $offset]);
$requests = $wpdb->get_results($wpdb->prepare($query, $query_values));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">درخواست‌های برداشت مربیان</h1>
    <hr class="wp-header-end">
    
    <?php if ($action_message): ?>
        <div class=" notice-<?php echo $action_message_type; ?> is-dismissible">
            <p><?php echo esc_html($action_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- فیلترها -->
    <div class="sc-filter-wrapper" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 8px;">
        <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            <input type="hidden" name="page" value="sc-coach-management-withdrawals">

            <div style="min-width: 180px;">
                <label for="filter_status">وضعیت:</label><br>
                <select name="filter_status" id="filter_status" style="width: 100%;">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار تایید</option>
                    <option value="approved" <?php selected($filter_status, 'approved'); ?>>تایید شده (منتظر پرداخت)</option>
                    <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>رد شده</option>
                    <option value="paid" <?php selected($filter_status, 'paid'); ?>>تایید و پرداخت شده</option>
                </select>
            </div>

            <div style="min-width: 220px;">
                <label for="filter_coach">مربی:</label><br>
                <select name="filter_coach" id="filter_coach" style="width: 100%;">
                    <option value="0">همه مربیان</option>
                    <?php foreach ($coaches_for_filter as $c): ?>
                        <option value="<?php echo (int) $c->id; ?>" <?php selected($filter_coach, $c->id); ?>>
                            <?php echo esc_html($c->first_name . ' ' . $c->last_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="min-width: 180px;">
                <label for="filter_date_from_shamsi">از تاریخ:</label><br>
                <input type="text"
                       name="filter_date_from_shamsi"
                       id="filter_date_from_shamsi"
                       value="<?php echo esc_attr($display_date_from_shamsi_w); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="از تاریخ (شمسی)"
                       readonly
                       style="width: 100%;">
                <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
            </div>

            <div style="min-width: 180px;">
                <label for="filter_date_to_shamsi">تا تاریخ:</label><br>
                <input type="text"
                       name="filter_date_to_shamsi"
                       id="filter_date_to_shamsi"
                       value="<?php echo esc_attr($display_date_to_shamsi_w); ?>"
                       class="regular-text persian-date-input sc-no-default-date"
                       placeholder="تا تاریخ (شمسی)"
                       readonly
                       style="width: 100%;">
                <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            </div>

            <div style="min-width: 140px;">
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo admin_url('admin.php?page=sc-coach-management-withdrawals'); ?>" class="button">پاک کردن</a>
            </div>
        </form>
    </div>
    
    <!-- اکشن دسته‌جمعی و جدول -->
    <form method="POST" action="" id="bulk-withdrawals-form">
        <?php wp_nonce_field('bulk_withdrawals', '_wpnonce_bulk'); ?>
        <div class="tablenav top" style="margin: 15px 0;">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-select">
                    <option value="">عملیات دسته‌جمعی...</option>
                    <option value="approve">تایید</option>
                    <option value="reject">رد</option>
                    <option value="mark_paid">پرداخت شده</option>
                </select>
                <button type="button" class="button action" id="bulk-apply-btn" style="margin-right: 5px;">اعمال</button>
            </div>
        </div>

    <!-- جدول درخواست‌ها -->
    <div class="sc-withdrawals-table-wrapper">
    <table class="wp-list-table widefat fixed striped sc-withdrawals-table">
        <thead>
            <tr>
                <td class="check-column column-cb"><input type="checkbox" id="cb-select-all"></td>
                <th class="column-index">ردیف</th>
                <th class="column-date">تاریخ درخواست</th>
                <th class="column-coach">مربی</th>
                <th class="column-amount">مبلغ</th>
                <th class="column-status">وضعیت</th>
                <th class="column-notes">یادداشت</th>
                <th class="column-actions">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px;">
                        <p>هیچ درخواست برداشتی یافت نشد.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php $row = $offset + 1; ?>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $status_labels = [
                        'pending' => ['label' => 'در انتظار تایید', 'color' => '#f0a000', 'bg' => '#fff8e1'],
                        'approved' => ['label' => 'تایید شده (منتظر پرداخت)', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
                        'rejected' => ['label' => 'رد شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                        'paid' => ['label' => 'تایید و پرداخت شده', 'color' => '#00a32a', 'bg' => '#d4edda']
                    ];
                    $status_info = $status_labels[$request->status] ?? ['label' => $request->status, 'color' => '#666', 'bg' => '#f5f5f5'];
                    ?>
                    <tr>
                        <th scope="row" class="check-column column-cb">
                            <input type="checkbox" name="request_ids[]" value="<?php echo $request->id; ?>" class="cb-request">
                        </th>
                        <td class="column-index"><?php echo $row++; ?></td>
                        <td class="column-date"><?php echo sc_date_shamsi($request->created_at, 'Y/m/d H:i'); ?></td>
                        <td class="column-coach"><strong><?php echo esc_html($request->first_name . ' ' . $request->last_name); ?></strong></td>
                        <td class="column-amount"><strong><?php echo esc_html(sc_format_amount_display($request->amount)); ?> تومان</strong></td>
                        <td class="column-status">
                            <span style="padding: 5px 10px; border-radius: 4px; font-weight: bold; background-color: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                                <?php echo $status_info['label']; ?>
                            </span>
                            
                        </td>
                        <td class="column-notes">
                           <?php if ($request->status === 'rejected' && $request->rejection_reason): ?>
                               <br><small class="small-tag" style="color: #d63638;">دلیل رد درخواست: <?php echo esc_html($request->rejection_reason); ?></small>
                            <?php endif; ?>
                            <?php if ($request->status === 'paid' && $request->paid_at): ?>
                                <br><small class="small-tag">پرداخت شده در: <?php echo sc_date_shamsi($request->paid_at, 'Y/m/d H:i'); ?></small>
                            <?php endif; ?> 
                            <br>
                        <?php echo   esc_html( $request->notes ? 'یادداشت:' . $request->notes : ''); ?>
                    </td>
                        <td class="column-actions">
                            <?php if ($request->status === 'pending'): ?>
                                <!-- فقط برای در انتظار تایید: امکان تایید یا رد -->
                                 <div class="btns_actions">
                                <button type="button" class="button button-primary button-small sc-approve-btn"
                                        data-request-id="<?php echo $request->id; ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('approve_withdrawal_' . $request->id)); ?>"
                                        style="margin-left: 10px;">تایید</button>
                                
                                <button type="button" class="button button-small reject-btn"
                                        data-request-id="<?php echo $request->id; ?>"
                                        style="margin-right: 10px;">رد</button>
                                        </div>
                            <?php elseif ($request->status === 'approved'): ?>
                                <!-- برای تایید شده: فقط امکان پرداخت -->
                                <button type="button" class="button button-small sc-mark-paid-btn"
                                        data-request-id="<?php echo $request->id; ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('mark_paid_withdrawal_' . $request->id)); ?>"
                                        style="margin-right: 10px;">پرداخت شده</button>
                            <?php else: ?>
                                <!-- سایر وضعیت‌ها: بدون عملیات تکی -->
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc_paginate" style="margin-top: 15px;">
            <div class="tablenav-pages">
                <p class="pagination-links">
                    <?php
                    $pagination_args = ['page' => 'sc-coach-management-withdrawals', 'filter_status' => $filter_status];
                    if ($filter_coach > 0) $pagination_args['filter_coach'] = $filter_coach;
                    if (!empty($filter_date_from_shamsi)) $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                    if (!empty($filter_date_to_shamsi)) $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '&laquo; قبلی',
                        'next_text' => 'بعدی &raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => $pagination_args,
                    ]);
                    ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
    </form>
</div>



<!-- پس‌زمینه مودال -->
<div id="reject-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100049;"></div>
<!-- مودال رد درخواست (تکی و دسته‌جمعی) -->
<div id="reject-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px 24px; border-radius: 10px; z-index: 100050; box-shadow: 0 12px 30px rgba(0,0,0,0.25); min-width: 420px; max-width: 520px;">
    <h3 id="reject-modal-title" style="margin-top: 0; margin-bottom: 10px; font-size: 18px;">رد درخواست برداشت</h3>
    <p style="margin-top: 0; margin-bottom: 15px; font-size: 13px; color: #555;">
        لطفاً دلیل رد این درخواست را به‌صورت واضح وارد کنید تا در سوابق و برای مربی قابل مشاهده باشد.
    </p>
    <!-- فرم رد تکی -->
    <form method="POST" action="" id="reject-form">
        <input type="hidden" name="request_id" id="reject_request_id">
        <?php wp_nonce_field('reject_withdrawal_action', 'reject_withdrawal_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="rejection_reason">دلیل رد:</label></th>
                <td>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" class="large-text" required></textarea>
                </td>
            </tr>
        </table>
        <p class="submit" style="margin-top: 15px;">
            <input type="submit" name="reject_request" class="button button-primary" value="رد درخواست" style="margin-left: 8px;">
            <button type="button" class="button reject-modal-close">انصراف</button>
        </p>
    </form>
    <!-- بخش رد دسته‌جمعی -->
    <div id="bulk-reject-fields" style="display: none;">
        <table class="form-table">
            <tr>
                <th><label for="rejection_reason_bulk">دلیل رد:</label></th>
                <td>
                    <textarea id="rejection_reason_bulk" rows="4" class="large-text"></textarea>
                </td>
            </tr>
        </table>
        <p class="submit" style="margin-top: 15px;">
            <button type="button" class="button button-primary" id="bulk-reject-submit" style="margin-left: 8px;">اعمال رد</button>
            <button type="button" class="button reject-modal-close">انصراف</button>
        </p>
    </div>
</div>

<!-- مودال ثبت اطلاعات پرداخت -->
<div id="mark-paid-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px 24px; border-radius: 10px; z-index: 100051; box-shadow: 0 12px 30px rgba(0,0,0,0.25); min-width: 420px; max-width: 520px;">
    <h3 style="margin-top: 0; margin-bottom: 10px; font-size: 18px;">ثبت اطلاعات پرداخت</h3>
    <p style="margin-top: 0; margin-bottom: 15px; font-size: 13px; color: #555;">
        این درخواست به عنوان پرداخت شده علامت‌گذاری می‌شود. لطفاً اطلاعات پرداخت (مثل شماره پیگیری، روش پرداخت یا توضیحات تکمیلی) را وارد کنید.
    </p>
    <form method="POST" action="" id="mark-paid-form">
        <input type="hidden" name="request_id" id="mark_paid_request_id">
        <input type="hidden" name="_wpnonce" id="mark_paid_nonce_input" value="">
        <table class="form-table">
            <tr>
                <th><label for="payment_note">توضیحات / اطلاعات پرداخت:</label></th>
                <td>
                    <textarea id="payment_note" name="payment_note" rows="4" class="large-text" placeholder="مثال: پرداخت از طریق کارت به کارت، شماره پیگیری ۱۲۳۴۵۶، تاریخ پرداخت ۱۴۰۲/۰۱/۱۵"></textarea>
                </td>
            </tr>
        </table>
        <p class="submit" style="margin-top: 15px;">
            <input type="submit" name="mark_paid" class="button button-primary" value="ثبت به عنوان پرداخت شده" style="margin-left: 8px;">
            <button type="button" class="button mark-paid-modal-close">انصراف</button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function showRejectModal(title, mode) {
        mode = mode || 'single';
        $('#reject-modal-title').text(title || 'رد درخواست برداشت');
        if (mode === 'bulk') {
            $('#reject-form').hide();
            $('#bulk-reject-fields').show();
        } else {
            $('#reject-form').show();
            $('#bulk-reject-fields').hide();
            $('#rejection_reason').val('');
        }
        $('#reject-modal-overlay').show();
        $('#reject-modal').show();
    }

    function hideRejectModal() {
        $('#reject-modal-overlay').hide();
        $('#reject-modal').hide();
        $('#mark-paid-modal').hide();
    }

    // رد تکی
    $(document).on('click', '.reject-btn', function() {
        var requestId = $(this).data('request-id');
        $('#reject_request_id').val(requestId);
        showRejectModal('رد درخواست برداشت', 'single');
    });

    // تایید تکی
    $(document).on('click', '.sc-approve-btn', function() {
        if (!confirm('آیا از تایید این درخواست اطمینان دارید؟ وضعیت به «منتظر پرداخت» تغییر می‌کند.')) return;
        var fid = document.createElement('form');
        fid.method = 'POST';
        fid.innerHTML = '<input type="hidden" name="approve_request" value="1"><input type="hidden" name="request_id" value="' + $(this).data('request-id') + '"><input type="hidden" name="_wpnonce" value="' + $(this).data('nonce') + '">';
        document.body.appendChild(fid);
        fid.submit();
    });

    // علامت پرداخت تکی - نمایش مودال اطلاعات پرداخت
    $(document).on('click', '.sc-mark-paid-btn', function() {
        var requestId = $(this).data('request-id');
        var nonce = $(this).data('nonce');
        $('#mark_paid_request_id').val(requestId);
        $('#mark_paid_nonce_input').val(nonce);
        $('#payment_note').val('');
        $('#reject-modal-overlay').show();
        $('#mark-paid-modal').show();
    });


    // بستن مودال
    $('#reject-modal-overlay').on('click', hideRejectModal);
    $(document).on('click', '#reject-modal .reject-modal-close', hideRejectModal);
    $(document).on('click', '#mark-paid-modal .mark-paid-modal-close', hideRejectModal);

    // Select All
    $('#cb-select-all').on('change', function() {
        $('.cb-request').prop('checked', $(this).prop('checked'));
    });

    // اعمال دسته‌جمعی
    $('#bulk-apply-btn').on('click', function() {
        var action = $('#bulk-action-select').val();
        if (!action) {
            alert('لطفاً یک عملیات انتخاب کنید.');
            return;
        }
        var checked = $('.cb-request:checked');
        if (checked.length === 0) {
            alert('لطفاً حداقل یک مورد را انتخاب کنید.');
            return;
        }
        if (action === 'reject') {
            showRejectModal('رد دسته‌جمعی - دلیل رد را وارد کنید', 'bulk');
            $('#rejection_reason_bulk').val('');
        } else {
            var msg = action === 'approve' ? 'آیا از تایید ' + checked.length + ' درخواست اطمینان دارید؟' : 'آیا از علامت‌گذاری ' + checked.length + ' درخواست به عنوان پرداخت شده اطمینان دارید؟';
            if (confirm(msg)) $('#bulk-withdrawals-form').submit();
        }
    });

    // ارسال رد دسته‌جمعی
    $('#bulk-reject-submit').on('click', function() {
        var reason = $('#rejection_reason_bulk').val();
        $('#bulk-withdrawals-form').find('input[name="bulk_rejection_reason"]').remove();
        $('<input>').attr({ type: 'hidden', name: 'bulk_rejection_reason', value: reason }).appendTo('#bulk-withdrawals-form');
        $('#reject-modal-overlay').hide();
        $('#reject-modal').hide();
        $('#bulk-withdrawals-form').submit();
    });
});
</script>
