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
function sc_coach_notifications_styles() {
    ?>
<style>
/* صفحه اطلاعیه‌های مربی - ظاهر مدرن و پویا */
.sc-coach-notif-wrap { --sc-notif-bg: #f8fafc; --sc-notif-card: #fff; --sc-notif-border: #e2e8f0; --sc-notif-primary: #0ea5e9; --sc-notif-primary-hover: #0284c7; --sc-notif-unread-bg: #eff6ff; --sc-notif-radius: 12px; --sc-notif-shadow: 0 1px 3px rgba(0,0,0,.06); --sc-notif-shadow-hover: 0 4px 12px rgba(0,0,0,.08); }
.sc-coach-notif-wrap * { box-sizing: border-box; }

.sc-coach-notif-header { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--sc-notif-border); }
.sc-coach-notif-page-title { margin: 0; font-size: 1.5rem; font-weight: 700; color: #1e293b; }
.sc-coach-notif-back { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: var(--sc-notif-card); border: 1px solid var(--sc-notif-border); border-radius: 8px; color: #475569; text-decoration: none; font-size: 13px; transition: background .2s, color .2s, border-color .2s; }
.sc-coach-notif-back:hover { background: var(--sc-notif-unread-bg); color: var(--sc-notif-primary); border-color: var(--sc-notif-primary); }
.sc-coach-notif-back .dashicons { font-size: 18px; width: 18px; height: 18px; }

.sc-coach-notif-badge { display: inline-flex; align-items: center; padding: 4px 10px; background: var(--sc-notif-primary); color: #fff; border-radius: 20px; font-size: 12px; font-weight: 600; }

/* تب‌ها */
.sc-coach-notif-tabs { list-style: none; margin: 0 0 20px 0; padding: 0; display: flex; gap: 4px; border-bottom: 1px solid var(--sc-notif-border); }
.sc-coach-notif-tabs li { margin: 0; }
.sc-coach-notif-tabs a { display: block; padding: 10px 18px; color: #64748b; text-decoration: none; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color .2s, border-color .2s; }
.sc-coach-notif-tabs a:hover { color: var(--sc-notif-primary); }
.sc-coach-notif-tabs li.active a { color: var(--sc-notif-primary); font-weight: 600; border-bottom-color: var(--sc-notif-primary); }

/* لیست اطلاعیه‌ها */
.sc-coach-notif-list { display: flex; flex-direction: column; gap: 0; background: var(--sc-notif-card); border: 1px solid var(--sc-notif-border); border-radius: var(--sc-notif-radius); overflow: hidden; box-shadow: var(--sc-notif-shadow); }
.sc-coach-notif-item { border-bottom: 1px solid var(--sc-notif-border); transition: background .15s; }
.sc-coach-notif-item:last-child { border-bottom: none; }
.sc-coach-notif-item:hover { background: var(--sc-notif-bg); }
.sc-coach-notif-item.is-unread { background: var(--sc-notif-unread-bg); }
.sc-coach-notif-item.is-unread:hover { background: #dbeafe; }

.sc-coach-notif-item-inner { display: flex; align-items: center; gap: 14px; padding: 16px 20px; }
.sc-coach-notif-dot { flex-shrink: 0; width: 8px; height: 8px; border-radius: 50%; background: var(--sc-notif-primary); }
.sc-coach-notif-item-content { flex: 1; min-width: 0; }
.sc-coach-notif-item-title { margin: 0 0 6px 0; font-size: 15px; font-weight: 600; line-height: 1.4; }
.sc-coach-notif-item-title a { color: #1e293b; text-decoration: none; }
.sc-coach-notif-item-title a:hover { color: var(--sc-notif-primary); }
.sc-coach-notif-item-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 13px; color: #64748b; }
.sc-coach-notif-btn-read { padding: 4px 10px; background: transparent; border: 1px solid var(--sc-notif-primary); color: var(--sc-notif-primary); border-radius: 6px; cursor: pointer; font-size: 12px; transition: background .2s, color .2s; }
.sc-coach-notif-btn-read:hover { background: var(--sc-notif-primary); color: #fff; }
.sc-coach-notif-item-action { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: var(--sc-notif-card); border: 1px solid var(--sc-notif-border); border-radius: 8px; color: #475569; text-decoration: none; font-size: 13px; transition: background .2s, color .2s, border-color .2s; flex-shrink: 0; }
.sc-coach-notif-item-action:hover { background: var(--sc-notif-primary); color: #fff; border-color: var(--sc-notif-primary); }
.sc-coach-notif-item-action .dashicons { font-size: 18px; width: 18px; height: 18px; }

/* حالت خالی */
.sc-coach-notif-empty { text-align: center; padding: 48px 24px; background: var(--sc-notif-card); border: 1px dashed var(--sc-notif-border); border-radius: var(--sc-notif-radius); margin-top: 20px; }
.sc-coach-notif-empty-icon { display: inline-block; font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 16px; }
.sc-coach-notif-empty-text { margin: 0; color: #64748b; font-size: 15px; }

/* صفحه جزئیات */
.sc-coach-notif-detail-card { background: var(--sc-notif-card); border: 1px solid var(--sc-notif-border); border-radius: var(--sc-notif-radius); overflow: hidden; box-shadow: var(--sc-notif-shadow); margin-top: 20px; }
.sc-coach-notif-detail-header { padding: 24px 24px 16px; border-bottom: 1px solid var(--sc-notif-border); }
.sc-coach-notif-detail-title { margin: 0 0 8px 0; font-size: 1.35rem; font-weight: 700; color: #1e293b; line-height: 1.4; }
.sc-coach-notif-detail-date { display: block; font-size: 13px; color: #64748b; }
.sc-coach-notif-detail-body { padding: 24px; font-size: 15px; line-height: 1.8; color: #334155; background: var(--sc-notif-bg); white-space: pre-wrap; }

/* صفحه‌بندی */
.sc-coach-notif-pagination { margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--sc-notif-border); }
.sc-coach-notif-pagination .pagination-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.sc-coach-notif-pagination a, .sc-coach-notif-pagination span { display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 14px; text-decoration: none; border: 1px solid var(--sc-notif-border); background: var(--sc-notif-card); color: #475569; transition: background .2s, border-color .2s, color .2s; }
.sc-coach-notif-pagination a:hover { background: var(--sc-notif-unread-bg); border-color: var(--sc-notif-primary); color: var(--sc-notif-primary); }
.sc-coach-notif-pagination .current { background: var(--sc-notif-primary); border-color: var(--sc-notif-primary); color: #fff; }
</style>
    <?php
}
?>
