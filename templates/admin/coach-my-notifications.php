<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
global $wpdb;
$current_user_id = get_current_user_id();
$base_url = admin_url('admin.php?page=sc-coach-notifications');
$view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all'; // all | unread | read
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

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
        return;
    }
}

$per_page = 15;
$page = isset($_GET['notif_page']) ? max(1, absint($_GET['notif_page'])) : 1;
$unread_count = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications($current_user_id) : 0;
$unread_only = ($filter === 'unread');
$read_only = ($filter === 'read');
$notifications = sc_get_user_notifications($current_user_id, $per_page, ($page - 1) * $per_page, $unread_only, $read_only, $search);
$total = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, $unread_only, $read_only, $search) : count($notifications);
if (!function_exists('sc_count_user_notifications') && ($filter === 'all' || $search === '')) {
    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_notification_recipients WHERE user_id = %d",
        $current_user_id
    ));
}
$total_pages = max(1, ceil($total / $per_page));

$base_url_with_filter = $base_url;
if ($filter !== 'all') {
    $base_url_with_filter = add_query_arg('filter', $filter, $base_url_with_filter);
}
if ($search !== '') {
    $base_url_with_filter = add_query_arg('s', $search, $base_url_with_filter);
}
?>
<div class="wrap sc-coach-notif-wrap">
    <div class="sc-coach-notif-header">
        <h1 class="sc-coach-notif-page-title">اطلاعیه‌های من</h1>
        <?php if ($unread_count > 0) : ?>
            <span class="sc-coach-notif-badge"><?php echo (int) $unread_count; ?> خوانده نشده</span>
        <?php endif; ?>
    </div>

    <!-- فیلتر و جستجو -->
    <div class="sc-coach-notif-filters" style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 20px;">
        <ul class="sc-coach-notif-tabs" style="margin: 0; flex: 1;">
            <li class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url($search !== '' ? add_query_arg(['filter' => 'all', 's' => $search], $base_url) : $base_url); ?>">همه</a>
            </li>
            <li class="<?php echo $filter === 'unread' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('filter', 'unread', $search !== '' ? add_query_arg('s', $search, $base_url) : $base_url)); ?>">خوانده نشده <?php echo $unread_count > 0 ? '(' . $unread_count . ')' : ''; ?></a>
            </li>
            <li class="<?php echo $filter === 'read' ? 'active' : ''; ?>">
                <a href="<?php echo esc_url(add_query_arg('filter', 'read', $search !== '' ? add_query_arg('s', $search, $base_url) : $base_url)); ?>">خوانده شده</a>
            </li>
        </ul>
        <form method="get" action="" class="sc-coach-notif-search" style="display: flex; gap: 8px; align-items: center;">
            <input type="hidden" name="page" value="sc-coach-notifications">
            <?php if ($filter !== 'all') : ?>
                <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
            <?php endif; ?>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن..." class="regular-text" style="width: 220px;">
            <button type="submit" class="button">جستجو</button>
            <?php if ($search !== '') : ?>
                <a href="<?php echo esc_url($filter !== 'all' ? add_query_arg('filter', $filter, $base_url) : $base_url); ?>" class="button">پاک کردن جستجو</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($notifications)) : ?>
        <div class="sc-coach-notif-empty">
            <span class="sc-coach-notif-empty-icon dashicons dashicons-bell"></span>
            <p class="sc-coach-notif-empty-text"><?php
                if ($search !== '') {
                    echo 'نتیجه‌ای برای جستجو یافت نشد.';
                } elseif ($filter === 'unread') {
                    echo 'همه اطلاعیه‌ها خوانده شده‌اند.';
                } elseif ($filter === 'read') {
                    echo 'هنوز اطلاعیه‌ای به عنوان خوانده شده ندارید.';
                } else {
                    echo 'هنوز اطلاعیه‌ای دریافت نکرده‌اید.';
                }
            ?></p>
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
                echo paginate_links([
                    'base'      => add_query_arg('notif_page', '%#%', $base_url_with_filter),
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
