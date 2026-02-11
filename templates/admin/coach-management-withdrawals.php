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

if (isset($_POST['approve_request']) && check_admin_referer('approve_withdrawal_' . $_POST['request_id'])) {
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

if (isset($_POST['mark_paid']) && check_admin_referer('mark_paid_withdrawal_' . $_POST['request_id'])) {
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
if (isset($_POST['bulk_action']) && isset($_POST['request_ids']) && is_array($_POST['request_ids']) && check_admin_referer('bulk_withdrawals')) {
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
                <option value="approved" <?php selected($filter_status, 'approved'); ?>>تایید شده</option>
                <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>رد شده</option>
                <option value="paid" <?php selected($filter_status, 'paid'); ?>>پرداخت شده</option>
            </select>
        </form>
    </div>
    
    <!-- اکشن دسته‌جمعی -->
    <form method="POST" action="" id="bulk-withdrawals-form">
        <?php wp_nonce_field('bulk_withdrawals'); ?>
        <div class="tablenav top" style="margin: 15px 0;">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-select">
                    <option value="">عملیات دسته‌جمعی...</option>
                    <option value="approve">تایید</option>
                    <option value="reject">رد</option>
                    <option value="mark_paid">علامت‌گذاری به عنوان پرداخت شده</option>
                </select>
                <input type="submit" class="button action" value="اعمال" id="bulk-apply-btn" style="margin-right: 5px;">
            </div>
            <div id="bulk-reject-reason-wrap" style="display: none; margin-top: 10px;">
                <label>دلیل رد (برای اکشن «رد»):</label>
                <textarea name="bulk_rejection_reason" id="bulk_rejection_reason" rows="2" class="large-text" style="width: 400px; margin-right: 10px;"></textarea>
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
                        'approved' => ['label' => 'تایید شده', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
                        'rejected' => ['label' => 'رد شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                        'paid' => ['label' => 'پرداخت شده', 'color' => '#00a32a', 'bg' => '#d4edda']
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
                        <td><strong><?php echo number_format($request->amount, 0, '.', ','); ?> تومان</strong></td>
                        <td><?php echo number_format($request->balance_before, 0, '.', ','); ?> تومان</td>
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
                                <form method="POST" action="" style="display: inline-block; margin-left: 5px;">
                                    <?php wp_nonce_field('approve_withdrawal_' . $request->id); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $request->id; ?>">
                                    <input type="submit" name="approve_request" class="button button-primary button-small" value="تایید" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این درخواست را تایید کنید؟ مبلغ از کیف پول مربی کسر خواهد شد.');">
                                </form>
                                <button type="button" class="button button-small reject-btn" data-request-id="<?php echo $request->id; ?>" data-reject-nonce="<?php echo esc_attr(wp_create_nonce('reject_withdrawal_' . $request->id)); ?>" style="margin-right: 5px;">رد</button>
                            <?php elseif ($request->status === 'approved'): ?>
                                <form method="POST" action="" style="display: inline-block;">
                                    <?php wp_nonce_field('mark_paid_withdrawal_' . $request->id); ?>
                                    <input type="hidden" name="request_id" value="<?php echo $request->id; ?>">
                                    <input type="submit" name="mark_paid" class="button button-primary button-small" value="علامت‌گذاری به عنوان پرداخت شده">
                                </form>
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

<!-- فرم رد درخواست (مخفی) -->
<div id="reject-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border: 2px solid #ddd; border-radius: 8px; z-index: 10000; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
    <h3>رد درخواست برداشت</h3>
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
            <button type="button" class="button" onclick="jQuery('#reject-modal').hide();">انصراف</button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.reject-btn').on('click', function() {
        var requestId = $(this).data('request-id');
        var nonce = $(this).data('reject-nonce');
        $('#reject_request_id').val(requestId);
        $('#reject_nonce_input').val(nonce).attr('name', 'reject_nonce');
        $('#reject-modal').show();
    });
    
    // بستن modal با کلیک خارج از آن
    $(document).on('click', function(e) {
        if ($(e.target).closest('#reject-modal').length === 0 && $(e.target).closest('.reject-btn').length === 0) {
            $('#reject-modal').hide();
        }
    });

    // Select All
    $('#cb-select-all').on('change', function() {
        $('.cb-request').prop('checked', $(this).prop('checked'));
    });

    // نمایش فیلد دلیل رد هنگام انتخاب اکشن «رد»
    $('#bulk-action-select').on('change', function() {
        var v = $(this).val();
        if (v === 'reject') {
            $('#bulk-reject-reason-wrap').show();
        } else {
            $('#bulk-reject-reason-wrap').hide();
        }
    });

    // تأیید قبل از اعمال اکشن دسته‌جمعی
    $('#bulk-withdrawals-form').on('submit', function(e) {
        var action = $('#bulk-action-select').val();
        if (!action) {
            e.preventDefault();
            alert('لطفاً یک عملیات انتخاب کنید.');
            return false;
        }
        var checked = $('.cb-request:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('لطفاً حداقل یک مورد را انتخاب کنید.');
            return false;
        }
        var msg = 'تایید';
        if (action === 'approve') msg = 'آیا از تایید ' + checked + ' درخواست انتخاب‌شده اطمینان دارید؟ مبلغ از کیف پول مربیان کسر خواهد شد.';
        else if (action === 'reject') msg = 'آیا از رد ' + checked + ' درخواست انتخاب‌شده اطمینان دارید؟';
        else if (action === 'mark_paid') msg = 'آیا از علامت‌گذاری ' + checked + ' درخواست به عنوان پرداخت شده اطمینان دارید؟';
        if (!confirm(msg)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
