<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();

global $wpdb;
$table = $wpdb->prefix . 'sc_sms_log';

// برچسب بخش‌ها برای نمایش
$context_labels = [
    'ticket_new' => 'تیکت جدید',
    'ticket_reply' => 'پاسخ تیکت',
    'invoice' => 'صورت‌حساب',
    'enrollment' => 'ثبت‌نام دوره',
    'reminder' => 'یادآور پرداخت',
    'absence' => 'غیبت',
    'wallet_charge_success' => 'شارژ کیف پول',
    'wallet_payment' => 'پرداخت از کیف پول',
    'wallet_low_balance' => 'موجودی کم کیف پول',
    'wallet_negative_balance' => 'موجودی منفی کیف پول',
    'insurance_expiry' => 'انقضای بیمه',
    'birthday' => 'تولد',
    'notification' => 'اطلاعیه',
    '' => '—',
];

// فیلترها
$filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$filter_date_to   = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$filter_context  = isset($_GET['context']) ? sanitize_text_field($_GET['context']) : '';
$filter_status   = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : ''; // '', '1', '0'

$where = ['1=1'];
$prepare_args = [];

if ($filter_date_from !== '') {
    $where[] = 'created_at >= %s';
    $prepare_args[] = $filter_date_from . ' 00:00:00';
}
if ($filter_date_to !== '') {
    $where[] = 'created_at <= %s';
    $prepare_args[] = $filter_date_to . ' 23:59:59';
}
if ($filter_context !== '') {
    $where[] = 'context = %s';
    $prepare_args[] = $filter_context;
}
if ($filter_status === '1') {
    $where[] = 'success = 1';
} elseif ($filter_status === '0') {
    $where[] = 'success = 0';
}

$where_sql = implode(' AND ', $where);

$per_page = 25;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM `$table` WHERE $where_sql";
if (!empty($prepare_args)) {
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare_args));
} else {
    $total_items = (int) $wpdb->get_var($count_sql);
}

$total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $per_page;

$list_sql = "SELECT * FROM `$table` WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
$prepare_args[] = $per_page;
$prepare_args[] = $offset;
$logs = $wpdb->get_results($wpdb->prepare($list_sql, $prepare_args));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه – گزارشات ارسال پیامک</h1>
    <hr class="wp-header-end">

    <form method="get" action="" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="sc-reports-sms-log">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div>
                <label for="date_from" style="display: block; margin-bottom: 4px; font-size: 12px;">از تاریخ</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filter_date_from); ?>">
            </div>
            <div>
                <label for="date_to" style="display: block; margin-bottom: 4px; font-size: 12px;">تا تاریخ</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            </div>
            <div>
                <label for="context" style="display: block; margin-bottom: 4px; font-size: 12px;">بخش</label>
                <select name="context" id="context">
                    <option value="">همه</option>
                    <?php foreach ($context_labels as $ctx => $label) : if ($ctx === '') continue; ?>
                        <option value="<?php echo esc_attr($ctx); ?>" <?php selected($filter_context, $ctx); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="status" style="display: block; margin-bottom: 4px; font-size: 12px;">وضعیت</label>
                <select name="status" id="status">
                    <option value="" <?php selected($filter_status, ''); ?>>همه</option>
                    <option value="1" <?php selected($filter_status, '1'); ?>>موفق</option>
                    <option value="0" <?php selected($filter_status, '0'); ?>>ناموفق</option>
                </select>
            </div>
            <div>
                <button type="submit" class="button button-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-sms-log')); ?>" class="button">پاک کردن</a>
            </div>
        </div>
    </form>

    <p style="color: #646970; margin-bottom: 12px;">تعداد کل: <strong><?php echo number_format($total_items); ?></strong> رکورد</p>

    <?php if (empty($logs)) : ?>
        <p>رکوردی یافت نشد.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 50px;">ردیف</th>
                    <th scope="col" style="width: 140px;">تاریخ و زمان</th>
                    <th scope="col" style="width: 110px;">شماره</th>
                    <th scope="col" style="width: 120px;">بخش</th>
                    <th scope="col">متن / توضیح</th>
                    <th scope="col" style="width: 70px;">وضعیت</th>
                    <th scope="col" style="width: 200px;">پیام خطا / توضیحات</th>
                    <th scope="col" style="width: 90px;">شناسه پیام</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row = $offset + 1;
                foreach ($logs as $log) :
                    $msg_preview = $log->message_text;
                    if (mb_strlen($msg_preview) > 80) {
                        $msg_preview = mb_substr($msg_preview, 0, 77) . '…';
                    }
                    $context_label = isset($context_labels[$log->context]) ? $context_labels[$log->context] : $log->context;
                ?>
                    <tr>
                        <td><?php echo (int) $row; ?></td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->mobile); ?></td>
                        <td><?php echo esc_html($context_label); ?></td>
                        <td title="<?php echo esc_attr($log->message_text); ?>"><?php echo esc_html($msg_preview); ?></td>
                        <td>
                            <?php if ((int) $log->success === 1) : ?>
                                <span style="color: #00a32a; font-weight: 600;">موفق</span>
                            <?php else : ?>
                                <span style="color: #d63638; font-weight: 600;">ناموفق</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ((int) $log->success === 1 && $log->response_message) {
                                echo esc_html($log->response_message);
                            } elseif ($log->error_message) {
                                echo esc_html($log->error_message);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td><?php echo $log->message_id ? esc_html($log->message_id) : '—'; ?></td>
                    </tr>
                <?php
                    $row++;
                endforeach;
                ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top: 12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_items); ?> مورد</span>
                    <span class="pagination-links">
                        <?php
                        $base = add_query_arg('paged', '%#%');
                        $base = remove_query_arg('paged', $base);
                        if (!empty($filter_date_from)) $base = add_query_arg('date_from', $filter_date_from, $base);
                        if (!empty($filter_date_to))   $base = add_query_arg('date_to', $filter_date_to, $base);
                        if (!empty($filter_context))  $base = add_query_arg('context', $filter_context, $base);
                        if ($filter_status !== '')    $base = add_query_arg('status', $filter_status, $base);
                        $base = add_query_arg('page', 'sc-reports-sms-log', $base);
                        echo paginate_links([
                            'base' => $base,
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ]);
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
