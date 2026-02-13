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
        <div class="woocommerce-MyAccount-content sc-notifications-content">
            <div class="sc-notification-detail-card">
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('sc-notifications')); ?>" class="sc-notification-back-link">← بازگشت به لیست</a>
                <h2 class="sc-notification-detail-title"><?php echo esc_html($notification->title); ?></h2>
                <p class="sc-notification-detail-meta"><?php echo esc_html(sc_date_shamsi($notification->created_at, 'l d F Y - H:i')); ?></p>
                <div class="sc-notification-detail-body"><?php echo nl2br(esc_html($notification->content)); ?></div>
            </div>
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
    <h2 class="sc-notifications-heading">اطلاعیه‌ها</h2>
    <?php wc_print_notices(); ?>

    <?php if (empty($notifications)) : ?>
        <div class="sc-notifications-empty-state">
            <span class="sc-notifications-empty-icon" aria-hidden="true"></span>
            <p class="sc-notifications-empty-text">هنوز اطلاعیه‌ای دریافت نکرده‌اید.</p>
        </div>
    <?php else : ?>
        <div class="sc-notifications-grid">
            <?php foreach ($notifications as $n) : ?>
                <article class="sc-notification-card <?php echo $n->is_read ? 'sc-notification-read' : 'sc-notification-unread'; ?>">
                    <div class="sc-notification-card-inner">
                        <h3 class="sc-notification-card-title">
                            <a href="<?php echo esc_url(add_query_arg('view', $n->id, wc_get_account_endpoint_url('sc-notifications'))); ?>"><?php echo esc_html($n->title); ?></a>
                        </h3>
                        <div class="sc-notification-card-meta">
                            <span class="sc-notification-card-date"><?php echo esc_html(sc_date_shamsi($n->created_at, 'Y/m/d')); ?></span>
                            <?php if (!$n->is_read) : ?>
                                <span class="sc-notification-card-badge">جدید</span>
                            <?php else : ?>
                                <span class="sc-notification-card-badge sc-notification-card-badge-read">خوانده شده</span>
                            <?php endif; ?>
                        </div>
                        <div class="sc-notification-card-actions">
                            <?php if (!$n->is_read) : ?>
                                <button type="button" class="sc-notification-btn sc-notification-btn-secondary sc-btn-mark-read" data-id="<?php echo esc_attr($n->id); ?>">خواندم</button>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(add_query_arg('view', $n->id, wc_get_account_endpoint_url('sc-notifications'))); ?>" class="sc-notification-btn sc-notification-btn-primary">مشاهده</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="sc-notifications-pagination" aria-label="صفحه‌بندی اطلاعیه‌ها">
                <?php echo paginate_links([
                    'base' => add_query_arg('notif_page', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $page
                ]); ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.sc-btn-mark-read').on('click', function() {
        var btn = $(this);
        var id = btn.data('id');
        $.post('<?php echo admin_url("admin-ajax.php"); ?>', {
            action: 'sc_mark_notification_read',
            notification_id: id,
            nonce: '<?php echo wp_create_nonce("sc_mark_notification_read"); ?>'
        }, function(res) {
            if (res && res.success) {
                location.reload();
            }
        });
    });
});
</script>
