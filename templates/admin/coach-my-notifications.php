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
                <?php
                $attachment_ids = isset($notification->attachment_ids) && $notification->attachment_ids ? json_decode($notification->attachment_ids, true) : [];
                if (!empty($attachment_ids) && is_array($attachment_ids) && function_exists('sc_notification_attachment_download_url')) :
                    ?>
                    <div class="sc-coach-notif-detail-attachments" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--sc-notif-border, #e2e8f0);">
                        <strong style="display: block; margin-bottom: 10px;">پیوست‌ها:</strong>
                        <ul style="list-style: none; margin: 0; padding: 0;">
                            <?php foreach (array_map('absint', $attachment_ids) as $aid) :
                                if (!$aid) continue;
                                $name = get_the_title($aid) ?: basename(get_attached_file($aid)) ?: 'پیوست';
                                $url = sc_notification_attachment_download_url($aid, $notification->id, $current_user_id);
                                ?>
                                <li style="margin-bottom: 8px;"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="sc-notification-attachment-link">📎 <?php echo esc_html($name); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
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
$count_all = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, false, false, '') : 0;
$count_read = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($current_user_id, false, true, '') : 0;
?>
<div class="wrap sc-coach-notif-wrap">
    <div class="sc-coach-notif-header">
        <h1 class="sc-coach-notif-page-title">اطلاعیه‌های من</h1>
        <span class="sc-coach-notif-badge sc-coach-notif-badge-unread" <?php echo $unread_count > 0 ? '' : ' style="display:none;"'; ?>><?php echo (int) $unread_count; ?> خوانده نشده</span>
    </div>

    <!-- فیلتر و جستجو (ایجکسی) -->
    <div class="sc-coach-notif-filters" style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px; margin-bottom: 20px;">
        <ul class="sc-coach-notif-tabs" style="margin: 0; flex: 1;">
            <li class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
                <a href="#" class="sc-coach-notif-tab" data-filter="all">همه <span class="sc-coach-notif-tab-count" data-count="all">(<?php echo (int) $count_all; ?>)</span></a>
            </li>
            <li class="<?php echo $filter === 'unread' ? 'active' : ''; ?>">
                <a href="#" class="sc-coach-notif-tab" data-filter="unread">خوانده نشده <span class="sc-coach-notif-tab-count" data-count="unread">(<?php echo (int) $unread_count; ?>)</span></a>
            </li>
            <li class="<?php echo $filter === 'read' ? 'active' : ''; ?>">
                <a href="#" class="sc-coach-notif-tab" data-filter="read">خوانده شده <span class="sc-coach-notif-tab-count" data-count="read">(<?php echo (int) $count_read; ?>)</span></a>
            </li>
        </ul>
        <form id="sc-coach-notif-search-form" class="sc-coach-notif-search" style="display: flex; gap: 8px; align-items: center;">
            <input type="hidden" name="filter" id="sc-coach-notif-filter-value" value="<?php echo esc_attr($filter); ?>">
            <input type="search" name="s" id="sc-coach-notif-search-input" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن..." class="regular-text" style="width: 220px;">
            <button type="submit" class="button">جستجو</button>
            <button type="button" class="button sc-coach-notif-clear-search" <?php echo $search === '' ? ' style="display:none;"' : ''; ?>>پاک کردن جستجو</button>
        </form>
    </div>

    <div id="sc-coach-notif-ajax-container">
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
                <?php for ($p = 1; $p <= $total_pages; $p++) :
                    $link = $base_url_with_filter . (strpos($base_url_with_filter, '?') !== false ? '&' : '?') . 'notif_page=' . $p;
                    if ($p === $page) : ?>
                        <span class="current"><?php echo (int) $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo esc_url($link); ?>" class="sc-coach-notif-page-link" data-page="<?php echo (int) $p; ?>"><?php echo (int) $p; ?></a>
                    <?php endif;
                endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var baseUrl = '<?php echo esc_js($base_url); ?>';
    var ajaxUrl = '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
    var nonceMarkRead = '<?php echo esc_js(wp_create_nonce("sc_mark_notification_read")); ?>';

    function buildQueryString(filter, s, page) {
        var q = [];
        if (filter && filter !== 'all') q.push('filter=' + encodeURIComponent(filter));
        if (s) q.push('s=' + encodeURIComponent(s));
        if (page && page > 1) q.push('notif_page=' + page);
        return q.length ? '?' + q.join('&') : '';
    }

    function updateTabsActive(filter) {
        $('.sc-coach-notif-tabs li').removeClass('active');
        $('.sc-coach-notif-tab[data-filter="' + filter + '"]').closest('li').addClass('active');
        $('#sc-coach-notif-filter-value').val(filter);
    }

    function updateTabCounts(counts) {
        if (!counts) return;
        if (typeof counts.count_all !== 'undefined') $('.sc-coach-notif-tab-count[data-count="all"]').text('(' + counts.count_all + ')');
        if (typeof counts.count_unread !== 'undefined') {
            $('.sc-coach-notif-tab-count[data-count="unread"]').text('(' + counts.count_unread + ')');
            var $badge = $('.sc-coach-notif-badge-unread');
            if (counts.count_unread > 0) $badge.text(counts.count_unread + ' خوانده نشده').show(); else $badge.hide();
        }
        if (typeof counts.count_read !== 'undefined') $('.sc-coach-notif-tab-count[data-count="read"]').text('(' + counts.count_read + ')');
    }

    function setItemAsRead($item) {
        if (!$item.length) return;
        $item.removeClass('is-unread');
        $item.find('.sc-coach-notif-dot').remove();
        $item.find('.sc-btn-mark-read').remove();
        var $unreadSpan = $('.sc-coach-notif-tab-count[data-count="unread"]');
        var $readSpan = $('.sc-coach-notif-tab-count[data-count="read"]');
        var u = parseInt($unreadSpan.text().replace(/\D/g, ''), 10) || 0;
        var r = parseInt($readSpan.text().replace(/\D/g, ''), 10) || 0;
        if (u > 0) u--;
        r++;
        $unreadSpan.text('(' + u + ')');
        $readSpan.text('(' + r + ')');
        var $badge = $('.sc-coach-notif-badge-unread');
        if (u > 0) $badge.text(u + ' خوانده نشده').show(); else $badge.hide();
    }

    function renderList(data) {
        var html = '';
        if (!data.items || data.items.length === 0) {
            html = '<div class="sc-coach-notif-empty"><span class="sc-coach-notif-empty-icon dashicons dashicons-bell"></span><p class="sc-coach-notif-empty-text">' + (data.empty_message || '') + '</p></div>';
        } else {
            html = '<div class="sc-coach-notif-list">';
            $.each(data.items, function(i, n) {
                var itemClass = n.is_read ? 'sc-coach-notif-item' : 'sc-coach-notif-item is-unread';
                var dot = n.is_read ? '' : '<span class="sc-coach-notif-dot" title="خوانده نشده"></span>';
                var btnRead = n.is_read ? '' : '<button type="button" class="sc-coach-notif-btn-read sc-btn-mark-read" data-id="' + n.id + '">خواندم</button>';
                html += '<div class="' + itemClass + '" data-id="' + n.id + '"><div class="sc-coach-notif-item-inner">' + dot +
                    '<div class="sc-coach-notif-item-content"><h3 class="sc-coach-notif-item-title"><a href="' + n.view_url + '">' + (n.title || '') + '</a></h3>' +
                    '<div class="sc-coach-notif-item-meta"><span class="sc-coach-notif-item-date">' + n.created_at + '</span>' + btnRead + '</div></div>' +
                    '<a href="' + n.view_url + '" class="sc-coach-notif-item-action"><span class="dashicons dashicons-visibility"></span> مشاهده</a></div></div>';
            });
            html += '</div>';
            if (data.total_pages > 1) {
                html += '<nav class="sc-coach-notif-pagination" aria-label="صفحه‌بندی">';
                for (var p = 1; p <= data.total_pages; p++) {
                    var link = data.base_url_with_filter + (data.base_url_with_filter.indexOf('?') !== false ? '&' : '?') + 'notif_page=' + p;
                    html += (p === data.page) ? '<span class="current">' + p + '</span> ' : '<a href="' + link + '" class="sc-coach-notif-page-link" data-page="' + p + '">' + p + '</a> ';
                }
                html += '</nav>';
            }
        }
        $('#sc-coach-notif-ajax-container').html(html);
    }

    function loadNotifications(filter, s, page) {
        filter = filter || 'all';
        page = page || 1;
        var $container = $('#sc-coach-notif-ajax-container');
        $container.css('opacity', '0.6');
        $.post(ajaxUrl, {
            action: 'sc_coach_notifications_filter',
            filter: filter,
            s: s || '',
            notif_page: page
        }, function(res) {
            $container.css('opacity', '1');
            if (res && res.success && res.data) {
                updateTabsActive(filter);
                updateTabCounts({ count_all: res.data.count_all, count_unread: res.data.count_unread, count_read: res.data.count_read });
                $('#sc-coach-notif-search-input').val(s || '');
                $('.sc-coach-notif-clear-search').toggle(!!s);
                renderList(res.data);
                var newUrl = baseUrl + buildQueryString(filter, s, page);
                if (window.history && window.history.pushState) {
                    window.history.pushState({ filter: filter, s: s, page: page }, '', newUrl);
                }
            }
        });
    }

    $('#sc-coach-notif-search-form').on('submit', function(e) {
        e.preventDefault();
        loadNotifications($('#sc-coach-notif-filter-value').val(), $('#sc-coach-notif-search-input').val().trim(), 1);
    });

    $(document).on('click', '.sc-coach-notif-tab', function(e) {
        e.preventDefault();
        loadNotifications($(this).data('filter'), $('#sc-coach-notif-search-input').val().trim(), 1);
    });

    $('.sc-coach-notif-clear-search').on('click', function() {
        $('#sc-coach-notif-search-input').val('');
        loadNotifications($('#sc-coach-notif-filter-value').val(), '', 1);
        $(this).hide();
    });

    $(document).on('click', '.sc-btn-mark-read', function() {
        var btn = $(this), id = btn.data('id');
        var $item = btn.closest('.sc-coach-notif-item');
        btn.prop('disabled', true).text('...');
        $.post(ajaxUrl, { action: 'sc_mark_notification_read', notification_id: id, nonce: nonceMarkRead })
            .done(function(res) {
                if (res && res.success) setItemAsRead($item);
                else btn.prop('disabled', false).text('خواندم');
            })
            .fail(function() { btn.prop('disabled', false).text('خواندم'); });
    });

    $(document).on('click', '#sc-coach-notif-ajax-container .sc-coach-notif-page-link', function(e) {
        e.preventDefault();
        loadNotifications($('#sc-coach-notif-filter-value').val(), $('#sc-coach-notif-search-input').val().trim(), $(this).data('page'));
    });

    window.addEventListener('popstate', function(e) {
        if (e.state) {
            loadNotifications(e.state.filter, e.state.s, e.state.page);
        } else {
            var params = new URLSearchParams(window.location.search);
            loadNotifications(params.get('filter') || 'all', params.get('s') || '', parseInt(params.get('notif_page'), 10) || 1);
        }
    });
});
</script>
<?php
/* استایل‌های اطلاعیه‌های من از فایل coach-admin.css لود می‌شوند */
?>
