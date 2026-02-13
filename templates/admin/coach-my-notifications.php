<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
global $wpdb;
$current_user_id = get_current_user_id();
$base_url = admin_url('admin.php?page=sc-coach-notifications');
$view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all'; // all | unread

if ($view_id > 0) {
    $notification = sc_get_user_notification_detail($view_id, $current_user_id);
    if ($notification) {
        sc_mark_notification_read($view_id, $current_user_id);
        ?>
        <div class="wrap sc-coach-notif-wrap">
            <div class="sc-coach-notif-header">
                <a href="<?php echo esc_url($base_url); ?>" class="sc-coach-notif-back">
                    <span class="dashicons dashicons-arrow-right-alt"></span> بازگشت به لیست
                </a>
                <h1 class="sc-coach-notif-page-title">اطلاعیه‌های من</h1>
            </div>
            <article class="sc-coach-notif-detail-card">
                <header class="sc-coach-notif-detail-header">
                    <h2 class="sc-coach-notif-detail-title"><?php echo esc_html($notification->title); ?></h2>
                    <time class="sc-coach-notif-detail-date" datetime="<?php echo esc_attr($notification->created_at); ?>">
                        <?php echo esc_html(sc_date_shamsi($notification->created_at, 'l d F Y - H:i')); ?>
                    </time>
                </header>
                <div class="sc-coach-notif-detail-body"><?php echo nl2br(esc_html($notification->content)); ?></div>
            </article>
        </div>
        <?php
        sc_coach_notifications_styles();
        return;
    }
}

$per_page = 15;
$page = isset($_GET['notif_page']) ? max(1, absint($_GET['notif_page'])) : 1;
$unread_count = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications($current_user_id) : 0;
$notifications = sc_get_user_notifications($current_user_id, $per_page, ($page - 1) * $per_page, $filter === 'unread');
if ($filter === 'unread') {
    $total = $unread_count;
} else {
    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_notification_recipients WHERE user_id = %d",
        $current_user_id
    ));
}
$total_pages = max(1, ceil($total / $per_page));
?>
<div class="wrap sc-coach-notif-wrap">
    <div class="sc-coach-notif-header">
        <h1 class="sc-coach-notif-page-title">اطلاعیه‌های من</h1>
        <?php if ($unread_count > 0) : ?>
            <span class="sc-coach-notif-badge"><?php echo (int) $unread_count; ?> خوانده نشده</span>
        <?php endif; ?>
    </div>

    <ul class="sc-coach-notif-tabs">
        <li class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
            <a href="<?php echo esc_url($base_url); ?>">همه</a>
        </li>
        <li class="<?php echo $filter === 'unread' ? 'active' : ''; ?>">
            <a href="<?php echo esc_url(add_query_arg('filter', 'unread', $base_url)); ?>">خوانده نشده <?php echo $unread_count > 0 ? '(' . $unread_count . ')' : ''; ?></a>
        </li>
    </ul>

    <?php if (empty($notifications)) : ?>
        <div class="sc-coach-notif-empty">
            <span class="sc-coach-notif-empty-icon dashicons dashicons-bell"></span>
            <p class="sc-coach-notif-empty-text"><?php echo $filter === 'unread' ? 'همه اطلاعیه‌ها خوانده شده‌اند.' : 'هنوز اطلاعیه‌ای دریافت نکرده‌اید.'; ?></p>
        </div>
    <?php else : ?>
        <div class="sc-coach-notif-list">
            <?php foreach ($notifications as $n) : ?>
                <div class="sc-coach-notif-item <?php echo $n->is_read ? '' : 'is-unread'; ?>" data-id="<?php echo esc_attr($n->id); ?>">
                    <div class="sc-coach-notif-item-inner">
                        <?php if (!$n->is_read) : ?><span class="sc-coach-notif-dot" title="خوانده نشده"></span><?php endif; ?>
                        <div class="sc-coach-notif-item-content">
                            <h3 class="sc-coach-notif-item-title">
                                <a href="<?php echo esc_url(add_query_arg('view', $n->id, $base_url)); ?>"><?php echo esc_html($n->title); ?></a>
                            </h3>
                            <div class="sc-coach-notif-item-meta">
                                <span class="sc-coach-notif-item-date"><?php echo esc_html(sc_date_shamsi($n->created_at, 'Y/m/d - H:i')); ?></span>
                                <?php if (!$n->is_read) : ?>
                                    <button type="button" class="sc-coach-notif-btn-read sc-btn-mark-read" data-id="<?php echo esc_attr($n->id); ?>">خواندم</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?php echo esc_url(add_query_arg('view', $n->id, $base_url)); ?>" class="sc-coach-notif-item-action">
                            <span class="dashicons dashicons-visibility"></span> مشاهده
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <nav class="sc-coach-notif-pagination" aria-label="صفحه‌بندی">
                <?php
                $pagination_base = $base_url;
                if ($filter === 'unread') {
                    $pagination_base = add_query_arg('filter', 'unread', $pagination_base);
                }
                echo paginate_links([
                    'base'      => add_query_arg('notif_page', '%#%', $pagination_base),
                    'format'    => '',
                    'prev_text' => '&rarr; قبلی',
                    'next_text' => 'بعدی &larr;',
                    'total'     => $total_pages,
                    'current'   => $page,
                ]);
                ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.sc-btn-mark-read').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var id = btn.data('id');
        btn.prop('disabled', true).text('...');
        $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            action: 'sc_mark_notification_read',
            notification_id: id,
            nonce: '<?php echo esc_js(wp_create_nonce('sc_mark_notification_read')); ?>'
        }, function(res) {
            if (res && res.success) location.reload();
        });
    });
});
</script>
<?php
/* استایل‌های اطلاعیه‌های من از فایل coach-admin.css لود می‌شوند */
?>
