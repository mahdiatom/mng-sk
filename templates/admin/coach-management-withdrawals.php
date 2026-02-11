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

if (isset($_POST['reject_request']) && isset($_POST['request_id']) && check_admin_referer('reject_withdrawal_' . $_POST['request_id'], 'reject_nonce')) {
    $request_id = absint($_POST['request_id']);
    $rejection_reason = sanitize_text_field($_POST['rejection_reason']);
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
    $result = sc_mark_coach_withdrawal_paid($request_id);
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

// دریافت فیلتر وضعیت
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'pending';

// ساخت WHERE clause
$where_conditions = ['1=1'];
$where_values = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "w.status = %s";
    $where_values[] = $filter_status;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت درخواست‌ها
$query = "SELECT w.*, c.first_name, c.last_name 
          FROM $withdrawal_table w
          INNER JOIN $coaches_table c ON w.coach_id = c.id
          WHERE $where_clause
          ORDER BY w.created_at DESC";

if (!empty($where_values)) {
    $requests = $wpdb->get_results($wpdb->prepare($query, $where_values));
} else {
    $requests = $wpdb->get_results($query);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">درخواست‌های برداشت مربیان</h1>
    <hr class="wp-header-end">
    
    <?php if ($action_message): ?>
        <div class="notice notice-<?php echo $action_message_type; ?> is-dismissible">
            <p><?php echo esc_html($action_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- فیلتر وضعیت -->
    <div class="sc-filter-wrapper" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 8px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="sc-coach-management-withdrawals">
            <label>فیلتر وضعیت:</label>
            <select name="filter_status" onchange="this.form.submit();" style="margin-right: 10px;">
                <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار تایید</option>
                <option value="approved" <?php selected($filter_status, 'approved'); ?>>تایید شده (منتظر پرداخت)</option>
                <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>رد شده</option>
                <option value="paid" <?php selected($filter_status, 'paid'); ?>>تایید و پرداخت شده</option>
            </select>
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
                    <option value="mark_paid">علامت‌گذاری به عنوان پرداخت شده</option>
                </select>
                <button type="button" class="button action" id="bulk-apply-btn" style="margin-right: 5px;">اعمال</button>
            </div>
        </div>

    <!-- جدول درخواست‌ها -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <td class="check-column"><input type="checkbox" id="cb-select-all"></td>
                <th>ردیف</th>
                <th>تاریخ درخواست</th>
                <th>مربی</th>
                <th>مبلغ</th>
                <th>موجودی قبل</th>
                <th>وضعیت</th>
                <th>یادداشت</th>
                <th>عملیات</th>
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
                <?php $row = 1; ?>
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
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="request_ids[]" value="<?php echo $request->id; ?>" class="cb-request">
                        </th>
                        <td><?php echo $row++; ?></td>
                        <td><?php echo sc_date_shamsi($request->created_at, 'Y/m/d H:i'); ?></td>
                        <td><strong><?php echo esc_html($request->first_name . ' ' . $request->last_name); ?></strong></td>
                        <td><strong><?php echo esc_html(sc_format_amount_display($request->amount)); ?> تومان</strong></td>
                        <td><?php echo esc_html(sc_format_amount_display($request->balance_before)); ?> تومان</td>
                        <td>
                            <span style="padding: 5px 10px; border-radius: 4px; font-weight: bold; background-color: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                                <?php echo $status_info['label']; ?>
                            </span>
                            <?php if ($request->status === 'rejected' && $request->rejection_reason): ?>
                                <br><small style="color: #d63638;">دلیل: <?php echo esc_html($request->rejection_reason); ?></small>
                            <?php endif; ?>
                            <?php if ($request->status === 'paid' && $request->paid_at): ?>
                                <br><small>پرداخت شده در: <?php echo sc_date_shamsi($request->paid_at, 'Y/m/d H:i'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($request->notes ?: '-'); ?></td>
                        <td>
                            <?php if ($request->status === 'pending'): ?>
                                <button type="button" class="button button-primary button-small sc-approve-btn" data-request-id="<?php echo $request->id; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('approve_withdrawal_' . $request->id)); ?>" style="margin-left: 5px;">تایید</button>
                                <button type="button" class="button button-small reject-btn" data-request-id="<?php echo $request->id; ?>" data-reject-nonce="<?php echo esc_attr(wp_create_nonce('reject_withdrawal_' . $request->id)); ?>" style="margin-right: 5px;">رد</button>
                            <?php elseif ($request->status === 'approved'): ?>
                                <button type="button" class="button button-primary button-small sc-mark-paid-btn" data-request-id="<?php echo $request->id; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mark_paid_withdrawal_' . $request->id)); ?>">علامت‌گذاری به عنوان پرداخت شده</button>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </form>
</div>

<!-- پس‌زمینه مودال -->
<div id="reject-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100049;"></div>
<!-- مودال رد درخواست (تکی و دسته‌جمعی) -->
<div id="reject-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border: 2px solid #ddd; border-radius: 8px; z-index: 100050; box-shadow: 0 4px 20px rgba(0,0,0,0.3); min-width: 400px;">
    <h3 id="reject-modal-title">رد درخواست برداشت</h3>
    <!-- فرم رد تکی -->
    <form method="POST" action="" id="reject-form">
        <input type="hidden" name="request_id" id="reject_request_id">
        <input type="hidden" name="reject_nonce" id="reject_nonce_input" value="">
        <table class="form-table">
            <tr>
                <th><label for="rejection_reason">دلیل رد:</label></th>
                <td>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" class="large-text" required></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="reject_request" class="button button-primary" value="رد درخواست">
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
        <p class="submit">
            <button type="button" class="button button-primary" id="bulk-reject-submit">اعمال رد</button>
            <button type="button" class="button reject-modal-close">انصراف</button>
        </p>
    </div>
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
            $('#reject_request_id').val('');
            $('#rejection_reason').val('');
        }
        $('#reject-modal-overlay').show();
        $('#reject-modal').show();
    }

    function hideRejectModal() {
        $('#reject-modal-overlay').hide();
        $('#reject-modal').hide();
    }

    // رد تکی
    $(document).on('click', '.reject-btn', function() {
        var requestId = $(this).data('request-id');
        var nonce = $(this).data('reject-nonce');
        $('#reject_request_id').val(requestId);
        $('#reject_nonce_input').val(nonce).attr('name', 'reject_nonce');
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

    // علامت پرداخت تکی
    $(document).on('click', '.sc-mark-paid-btn', function() {
        if (!confirm('آیا مطمئن هستید؟ مبلغ از کیف پول مربی کسر و به عنوان پرداخت شده علامت‌گذاری می‌شود.')) return;
        var fid = document.createElement('form');
        fid.method = 'POST';
        fid.innerHTML = '<input type="hidden" name="mark_paid" value="1"><input type="hidden" name="request_id" value="' + $(this).data('request-id') + '"><input type="hidden" name="_wpnonce" value="' + $(this).data('nonce') + '">';
        document.body.appendChild(fid);
        fid.submit();
    });

    // بستن مودال
    $('#reject-modal-overlay').on('click', hideRejectModal);
    $(document).on('click', '#reject-modal .reject-modal-close', hideRejectModal);

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
