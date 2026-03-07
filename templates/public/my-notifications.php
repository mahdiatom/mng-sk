<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$current_user_id = get_current_user_id();
$view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;

if ($view_id > 0) {
    $notification = sc_get_user_notification_detail($view_id, $current_user_id);
    if ($notification) {
        sc_mark_notification_read($view_id, $current_user_id);
       
        $back_url = wc_get_account_endpoint_url('sc-notifications');
 
        ?>
        <div class="woocommerce-MyAccount-content sc-notifications-content">
            <div class="sc-notification-detail-card">
                <a href="<?php echo esc_url($back_url); ?>" class="sc-notification-back-link-new">← بازگشت به لیست</a>
                <h2 class="sc-notification-detail-title"><?php echo esc_html($notification->title); ?></h2>
                <p class="sc-notification-detail-meta"><?php echo esc_html(sc_date_shamsi($notification->created_at, 'l d F Y - H:i')); ?></p>
                <div class="sc-notification-detail-body"><?php echo nl2br(esc_html($notification->content)); ?></div>
                <?php
                $attachment_ids = isset($notification->attachment_ids) && $notification->attachment_ids ? json_decode($notification->attachment_ids, true) : [];
                if (!empty($attachment_ids) && is_array($attachment_ids) && function_exists('sc_notification_attachment_download_url')) :
                    ?>
                    <div class="sc-notification-detail-attachments" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <strong style="display: block; margin-bottom: 10px;">پیوست‌ها:</strong>
                        <ul style="list-style: none; margin: 0; padding: 0;">
                            <?php foreach (array_map('absint', $attachment_ids) as $aid) :
                                if (!$aid) continue;
                                $name = get_the_title($aid) ?: basename(get_attached_file($aid)) ?: 'پیوست';
                                $url = sc_notification_attachment_download_url($aid, $notification->id, $current_user_id);
                                ?>
                                <li style="margin-bottom: 8px;"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="sc-notification-attachment-link" style="display: inline-flex; align-items: center; gap: 6px;">📎 <?php echo esc_html($name); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
document.addEventListener('DOMContentLoaded', function() {
    // فقط وقتی view داریم، روی عنوان جزئیات اسکرول کند
    const el = document.querySelector('.sc-notification-detail-title');
    if(el){
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
        <?php
        return;
    }
}

$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$per_page = 15;
$page = isset($_GET['notif_page']) ? max(1, absint($_GET['notif_page'])) : 1;
$unread_only = ($filter === 'unread');
$read_only = ($filter === 'read');
$notifications = sc_get_user_notifications($current_user_id, $per_page, ($page - 1) * $per_page, $unread_only, $read_only, $search);
$total = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, $unread_only, $read_only, $search) : (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}sc_notification_recipients WHERE user_id = %d",
    $current_user_id
));
$total_pages = max(1, ceil($total / $per_page));
$base_url = wc_get_account_endpoint_url('sc-notifications');
$base_url_with_filter = $base_url;
if ($filter !== 'all') {
    $base_url_with_filter = add_query_arg('filter', $filter, $base_url_with_filter);
}
if ($search !== '') {
    $base_url_with_filter = add_query_arg('s', $search, $base_url_with_filter);
}
$count_all = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, false, false, '') : 0;
$count_unread = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, true, false, '') : 0;
$count_read = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, false, true, '') : 0;
?>
<div class="woocommerce-MyAccount-content sc-notifications-content">
    <h2 class="sc-notifications-heading">اطلاعیه‌ها</h2>
    <?php wc_print_notices(); ?>

    <div class="sc-notifications-filters" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 20px;">
        <ul class="sc-notif-tabs" >
            <li><a href="#" class="sc-notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" data-filter="all" style="padding: 8px 14px; border-radius: 6px; text-decoration: none; <?php echo $filter === 'all' ? 'background: #2271b1; color: #fff;' : 'background: #f0f0f1; color: #1d2327;'; ?>">همه <span class="sc-notif-tab-count" data-count="all">(<?php echo (int) $count_all; ?>)</span></a></li>
            <li><a href="#" class="sc-notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>" data-filter="unread" style="padding: 8px 14px; border-radius: 6px; text-decoration: none; <?php echo $filter === 'unread' ? 'background: #2271b1; color: #fff;' : 'background: #f0f0f1; color: #1d2327;'; ?>">خوانده نشده <span class="sc-notif-tab-count" data-count="unread">(<?php echo (int) $count_unread; ?>)</span></a></li>
            <li><a href="#" class="sc-notif-tab <?php echo $filter === 'read' ? 'active' : ''; ?>" data-filter="read" style="padding: 8px 14px; border-radius: 6px; text-decoration: none; <?php echo $filter === 'read' ? 'background: #2271b1; color: #fff;' : 'background: #f0f0f1; color: #1d2327;'; ?>">خوانده شده <span class="sc-notif-tab-count" data-count="read">(<?php echo (int) $count_read; ?>)</span></a></li>
        </ul>
        <form id="sc-notifications-search-form" style="display: flex; gap: 8px;">
            <input type="hidden" name="filter" id="sc-notif-filter-value" value="<?php echo esc_attr($filter); ?>">
            <input type="search" name="s" id="sc-notif-search-input" value="<?php echo esc_attr($search); ?>" placeholder="جستجو..." >
            <button type="submit" class="button button-primary">جستجو</button>
        </form>
    </div>

    <div id="sc-notifications-ajax-container">
    <?php if (empty($notifications)) : ?>
        <div class="sc-notifications-empty-state">
            <span class="sc-notifications-empty-icon" aria-hidden="true"></span>
            <p class="sc-notifications-empty-text"><?php
                echo esc_html($search !== '' ? 'نتیجه‌ای برای جستجو یافت نشد.' : ($filter === 'unread' ? 'همه اطلاعیه‌ها خوانده شده‌اند.' : ($filter === 'read' ? 'هنوز اطلاعیه‌ای به عنوان خوانده شده ندارید.' : 'هنوز اطلاعیه‌ای دریافت نکرده‌اید.')));
            ?></p>
        </div>
    <?php else : ?>
        <div class="sc-notifications-grid">
            <?php foreach ($notifications as $n) : ?>
                <article class="sc-notification-card <?php echo $n->is_read ? 'sc-notification-read' : 'sc-notification-unread'; ?>">
                    <div class="sc-notification-card-inner">
                        <h3 class="sc-notification-card-title">
                            <a href="<?php echo esc_url(add_query_arg('view', $n->id, $base_url)); ?>" data-back-url="<?php echo esc_attr($base_url_with_filter); ?>"><?php echo esc_html($n->title); ?></a>
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
                            <a href="<?php echo esc_url(add_query_arg('view', $n->id, $base_url)); ?>" class="sc-notification-btn sc-notification-btn-primary" data-back-url="<?php echo esc_attr($base_url_with_filter); ?>">مشاهده</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="sc-notifications-pagination" aria-label="صفحه‌بندی اطلاعیه‌ها">
                <?php for ($p = 1; $p <= $total_pages; $p++) :
                    $link = $base_url_with_filter . (strpos($base_url_with_filter, '?') !== false ? '&' : '?') . 'notif_page=' . $p;
                    if ($p === $page) : ?>
                        <span class="current"><?php echo (int) $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url($link); ?>" class="sc-notif-page-link" data-page="<?php echo (int) $p; ?>"><?php echo (int) $p; ?></a>
                    <?php endif;
                endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        // اگر صفحه جزئیات است
        let detailEl = document.querySelector('.sc-notification-detail-title');
        // اگر صفحه لیست است
        let listEl = document.querySelector('.sc-notifications-heading');

        if (detailEl) {
            detailEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        } else if (listEl) {
            listEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);
});
</script>
