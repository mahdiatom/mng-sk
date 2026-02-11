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

if (isset($_POST['reject_request']) && check_admin_referer('reject_withdrawal_' . $_POST['request_id'])) {
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
    
    <!-- جدول درخواست‌ها -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
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
                    <td colspan="8" style="text-align: center; padding: 30px;">
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
                                <button type="button" class="button button-small reject-btn" data-request-id="<?php echo $request->id; ?>" style="margin-right: 5px;">رد</button>
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
</div>

<!-- فرم رد درخواست (مخفی) -->
<div id="reject-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border: 2px solid #ddd; border-radius: 8px; z-index: 10000; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
    <h3>رد درخواست برداشت</h3>
    <form method="POST" action="" id="reject-form">
        <?php wp_nonce_field('reject_withdrawal_0', 'reject_nonce'); ?>
        <input type="hidden" name="request_id" id="reject_request_id">
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
        $('#reject_request_id').val(requestId);
        $('#reject-form').attr('action', '');
        $('#reject-form input[name="reject_nonce"]').attr('name', 'reject_nonce').val('');
        $('#reject-form').append('<input type="hidden" name="reject_nonce" value="' + '<?php echo wp_create_nonce("reject_withdrawal_' + requestId + '"); ?>' + '">');
        $('#reject-modal').show();
    });
    
    // بستن modal با کلیک خارج از آن
    $(document).on('click', function(e) {
        if ($(e.target).closest('#reject-modal').length === 0 && $(e.target).closest('.reject-btn').length === 0) {
            $('#reject-modal').hide();
        }
    });
});
</script>
