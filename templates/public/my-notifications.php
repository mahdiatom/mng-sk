<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$current_user_id = get_current_user_id();
$view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;

if ($view_id > 0) {
    $notification = sc_get_user_notification_detail($view_id, $current_user_id);
    if ($notification) {
        sc_mark_notification_read($view_id, $current_user_id);
        ?>
        <div class="sc-notification-detail">
            <p><a href="<?php echo esc_url(wc_get_account_endpoint_url('sc-notifications')); ?>" class="button">← بازگشت به لیست</a></p>
            <h2><?php echo esc_html($notification->title); ?></h2>
            <p class="sc-notification-date"><?php echo esc_html(sc_date_shamsi($notification->created_at, 'Y/m/d H:i')); ?></p>
            <div class="sc-notification-content"><?php echo nl2br(esc_html($notification->content)); ?></div>
        </div>
        <?php
        return;
    }
}

$per_page = 15;
$page = isset($_GET['notif_page']) ? max(1, absint($_GET['notif_page'])) : 1;
$notifications = sc_get_user_notifications($current_user_id, $per_page, ($page - 1) * $per_page);
$total = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}sc_notification_recipients nr WHERE nr.user_id = %d",
    $current_user_id
));
$total_pages = ceil($total / $per_page);
?>
<div class="woocommerce-MyAccount-content sc-notifications-content">
    <h2>اطلاعیه‌ها</h2>
    <?php wc_print_notices(); ?>
    <?php if (empty($notifications)) : ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-info">هنوز اطلاعیه‌ای دریافت نکرده‌اید.</div>
    <?php else : ?>
        <table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders" style="width:100%;">
            <thead>
                <tr>
                    <th style="text-align:right;">عنوان</th>
                    <th style="text-align:right; width:120px;">تاریخ</th>
                    <th style="text-align:right; width:100px;">وضعیت</th>
                    <th style="text-align:right; width:140px;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n) : ?>
                    <tr class="<?php echo $n->is_read ? '' : 'sc-notification-unread'; ?>">
                        <td data-title="عنوان">
                            <a href="<?php echo esc_url(add_query_arg('view', $n->id, wc_get_account_endpoint_url('sc-notifications'))); ?>"><?php echo esc_html($n->title); ?></a>
                        </td>
                        <td data-title="تاریخ"><?php echo esc_html(sc_date_shamsi($n->created_at, 'Y/m/d')); ?></td>
                        <td data-title="وضعیت"><?php echo $n->is_read ? 'خوانده شده' : '<span style="color:#d63638;">خوانده نشده</span>'; ?></td>
                        <td data-title="عملیات">
                            <?php if (!$n->is_read) : ?>
                                <button type="button" class="button sc-mark-read-btn" data-id="<?php echo $n->id; ?>" style="padding:5px 10px; font-size:12px;">خوانده شده</button>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(add_query_arg('view', $n->id, wc_get_account_endpoint_url('sc-notifications'))); ?>" class="button" style="padding:5px 10px; font-size:12px;">مشاهده</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top:20px;">
                <?php echo paginate_links([
                    'base' => add_query_arg('notif_page', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $page
                ]); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.sc-mark-read-btn').on('click', function() {
        var btn = $(this);
        var id = btn.data('id');
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
            action: 'sc_mark_notification_read',
            notification_id: id,
            nonce: '<?php echo wp_create_nonce("sc_mark_notification_read"); ?>'
        }, function(res) {
            if (res && res.success) {
                btn.closest('tr').removeClass('sc-notification-unread');
                btn.closest('td').find('span').replaceWith('خوانده شده');
                btn.remove();
                if (typeof location.reload === 'function') location.reload();
            }
        });
    });
});
</script>
<style>
.sc-notification-badge { background:#d63638; color:#fff; padding:2px 6px; border-radius:10px; font-size:11px; margin-right:4px; }
.sc-notification-unread { background:#fff8e6; }
.sc-notification-detail .sc-notification-date { color:#666; font-size:14px; margin-bottom:15px; }
.sc-notification-detail .sc-notification-content { background:#f9f9f9; padding:20px; border-radius:8px; margin-top:15px; line-height:1.8; }
</style>
