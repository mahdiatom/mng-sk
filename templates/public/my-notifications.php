<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$current_user_id = get_current_user_id();
$view_id = isset($_GET['view']) ? absint($_GET['view']) : 0;

if ($view_id > 0) {
    $notification = sc_get_user_notification_detail($view_id, $current_user_id);
    if ($notification) {
        sc_mark_notification_read($view_id, $current_user_id);
        
        // خواندن پارامترهای جستجو برای لینک بازگشت
        // همیشه به sc-notifications برمی‌گردیم (نه sc-submit-documents)
        $back_url = wc_get_account_endpoint_url('sc-notifications');
        
        // اول از پارامتر back در URL استفاده می‌کنیم (که توسط JavaScript تنظیم می‌شود)
        if (isset($_GET['back']) && !empty($_GET['back'])) {
            $back_param = esc_url_raw(urldecode($_GET['back']));
            if (strpos($back_param, 'sc-notifications') !== false) {
                $back_url = $back_param;
            }
        } else {
            // اگر پارامتر back وجود نداشت، از referrer استفاده می‌کنیم
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            
            if ($referrer && strpos($referrer, 'sc-notifications') !== false) {
                $referrer_parts = parse_url($referrer);
                if (isset($referrer_parts['query'])) {
                    parse_str($referrer_parts['query'], $referrer_params);
                    // فقط پارامترهای مربوط به notifications را اضافه می‌کنیم
                    if (isset($referrer_params['s']) && !empty(trim($referrer_params['s']))) {
                        $back_url = add_query_arg('s', sanitize_text_field($referrer_params['s']), $back_url);
                    }
                    if (isset($referrer_params['filter']) && $referrer_params['filter'] !== 'all') {
                        $back_url = add_query_arg('filter', sanitize_text_field($referrer_params['filter']), $back_url);
                    }
                    if (isset($referrer_params['notif_page']) && absint($referrer_params['notif_page']) > 1) {
                        $back_url = add_query_arg('notif_page', absint($referrer_params['notif_page']), $back_url);
                    }
                }
            }
        }
        
        // اطمینان حاصل می‌کنیم که URL به sc-notifications است نه جای دیگر
        if (strpos($back_url, '/sc-notifications') === false && strpos($back_url, 'sc-notifications') === false) {
            // اگر نیست، دوباره می‌سازیم
            $base_url = wc_get_account_endpoint_url('sc-notifications');
            $back_query = parse_url($back_url, PHP_URL_QUERY);
            if ($back_query) {
                $back_url = $base_url . '?' . $back_query;
            } else {
                $back_url = $base_url;
            }
        }
        ?>
        <div class="woocommerce-MyAccount-content sc-notifications-content">
            <div class="sc-notification-detail-card">
                <a href="<?php echo esc_url($back_url); ?>" class="sc-notification-back-link">← بازگشت به لیست</a>
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
        <ul class="sc-notif-tabs" style="list-style: none; margin: 0; padding: 0; display: flex; gap: 4px; flex: 1;">
            <li><a href="#" class="sc-notif-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" data-filter="all" style="padding: 8px 14px; border-radius: 6px; text-decoration: none; <?php echo $filter === 'all' ? 'background: #2271b1; color: #fff;' : 'background: #f0f0f1; color: #1d2327;'; ?>">همه <span class="sc-notif-tab-count" data-count="all">(<?php echo (int) $count_all; ?>)</span></a></li>
            <li><a href="#" class="sc-notif-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>" data-filter="unread" style="padding: 8px 14px; border-radius: 6px; text-decoration: none; <?php echo $filter === 'unread' ? 'background: #2271b1; color: #fff;' : 'background: #f0f0f1; color: #1d2327;'; ?>">خوانده نشده <span class="sc-notif-tab-count" data-count="unread">(<?php echo (int) $count_unread; ?>)</span></a></li>
            <li><a href="#" class="sc-notif-tab <?php echo $filter === 'read' ? 'active' : ''; ?>" data-filter="read" style="padding: 8px 14px; border-radius: 6px; text-decoration: none; <?php echo $filter === 'read' ? 'background: #2271b1; color: #fff;' : 'background: #f0f0f1; color: #1d2327;'; ?>">خوانده شده <span class="sc-notif-tab-count" data-count="read">(<?php echo (int) $count_read; ?>)</span></a></li>
        </ul>
        <form id="sc-notifications-search-form" style="display: flex; gap: 8px;">
            <input type="hidden" name="filter" id="sc-notif-filter-value" value="<?php echo esc_attr($filter); ?>">
            <input type="search" name="s" id="sc-notif-search-input" value="<?php echo esc_attr($search); ?>" placeholder="جستجو..." >
            <button type="submit" class="button">جستجو</button>
            <button type="button" class="button sc-notif-clear-search" <?php echo $search === '' ? ' style="display:none;"' : ''; ?>>پاک کردن جستجو</button>
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
jQuery(document).ready(function($) {
    var baseUrl = '<?php echo esc_js($base_url); ?>';
    var ajaxUrl = '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
    var nonceMarkRead = '<?php echo esc_js(wp_create_nonce("sc_mark_notification_read")); ?>';
    
    // خواندن پارامترهای جستجو از URL (برای حفظ هنگام برگشت)
    function getUrlParams() {
        var params = new URLSearchParams(window.location.search);
        return {
            filter: params.get('filter') || 'all',
            s: params.get('s') || '',
            page: parseInt(params.get('notif_page')) || 1
        };
    }

    function buildQueryString(filter, s, page) {
        var q = [];
        if (filter && filter !== 'all') q.push('filter=' + encodeURIComponent(filter));
        if (s) q.push('s=' + encodeURIComponent(s));
        if (page && page > 1) q.push('notif_page=' + page);
        return q.length ? '?' + q.join('&') : '';
    }
    
    // ذخیره URL فعلی قبل از رفتن به view و اضافه کردن به لینک
    $(document).on('click', 'a[href*="view="]', function(e) {
        var $link = $(this);
        var backUrl = $link.data('back-url');
        if (!backUrl) {
            // اگر data-back-url وجود نداشت، از URL فعلی استفاده می‌کنیم
            backUrl = window.location.href.split('?view=')[0];
            if (backUrl.indexOf('?') >= 0) {
                backUrl = backUrl.split('?')[0];
            }
            // اضافه کردن پارامترهای فعلی
            var currentParams = new URLSearchParams(window.location.search);
            var params = [];
            if (currentParams.get('s')) params.push('s=' + encodeURIComponent(currentParams.get('s')));
            if (currentParams.get('filter') && currentParams.get('filter') !== 'all') params.push('filter=' + encodeURIComponent(currentParams.get('filter')));
            if (currentParams.get('notif_page') && parseInt(currentParams.get('notif_page')) > 1) params.push('notif_page=' + currentParams.get('notif_page'));
            if (params.length > 0) {
                backUrl += '?' + params.join('&');
            }
        }
        if (backUrl && backUrl.indexOf('sc-notifications') >= 0) {
            // اضافه کردن پارامتر back به لینک view
            var viewHref = $link.attr('href');
            var separator = viewHref.indexOf('?') >= 0 ? '&' : '?';
            $link.attr('href', viewHref + separator + 'back=' + encodeURIComponent(backUrl));
            localStorage.setItem('sc_notifications_back_url', backUrl);
        }
    });
    
    // اگر در صفحه view هستیم، لینک بازگشت را از localStorage می‌خوانیم
    <?php if ($view_id > 0) : ?>
    var $backLink = $('.sc-notification-back-link');
    if ($backLink.length) {
        // اول از localStorage تلاش می‌کنیم
        var savedBackUrl = localStorage.getItem('sc_notifications_back_url');
        if (savedBackUrl && savedBackUrl.indexOf('sc-notifications') >= 0) {
            $backLink.attr('href', savedBackUrl);
            // بعد از استفاده، پاک می‌کنیم
            localStorage.removeItem('sc_notifications_back_url');
        } else {
            // اگر localStorage وجود نداشت، از referrer استفاده می‌کنیم
            var referrer = document.referrer;
            if (referrer && referrer.indexOf('sc-notifications') >= 0) {
                try {
                    var referrerUrl = new URL(referrer);
                    var refParams = {
                        filter: referrerUrl.searchParams.get('filter') || 'all',
                        s: referrerUrl.searchParams.get('s') || '',
                        page: parseInt(referrerUrl.searchParams.get('notif_page')) || 1
                    };
                    if (refParams.s || refParams.filter !== 'all' || refParams.page > 1) {
                        var backUrl = baseUrl + buildQueryString(refParams.filter, refParams.s, refParams.page);
                        $backLink.attr('href', backUrl);
                    } else {
                        $backLink.attr('href', baseUrl);
                    }
                } catch(e) {
                    $backLink.attr('href', baseUrl);
                }
            } else {
                // اگر هیچ کدام وجود نداشت، فقط baseUrl
                $backLink.attr('href', baseUrl);
            }
        }
        
        // اطمینان حاصل می‌کنیم که لینک همیشه به sc-notifications است
        var finalHref = $backLink.attr('href');
        if (finalHref && finalHref.indexOf('sc-notifications') === -1) {
            $backLink.attr('href', baseUrl);
        }
    }
    <?php endif; ?>

    function updateTabsActive(filter) {
        $('.sc-notif-tab').removeClass('active').css({'background':'#f0f0f1','color':'#1d2327'});
        $('.sc-notif-tab[data-filter="' + filter + '"]').addClass('active').css({'background':'#2271b1','color':'#fff'});
        $('#sc-notif-filter-value').val(filter);
    }

    function updateTabCounts(counts) {
        if (!counts) return;
        if (typeof counts.count_all !== 'undefined') $('.sc-notif-tab-count[data-count="all"]').text('(' + counts.count_all + ')');
        if (typeof counts.count_unread !== 'undefined') $('.sc-notif-tab-count[data-count="unread"]').text('(' + counts.count_unread + ')');
        if (typeof counts.count_read !== 'undefined') $('.sc-notif-tab-count[data-count="read"]').text('(' + counts.count_read + ')');
    }

    function renderList(data) {
        var html = '';
        if (!data.items || data.items.length === 0) {
            html = '<div class="sc-notifications-empty-state"><span class="sc-notifications-empty-icon" aria-hidden="true"></span><p class="sc-notifications-empty-text">' + (data.empty_message || '') + '</p></div>';
        } else {
            html = '<div class="sc-notifications-grid">';
            $.each(data.items, function(i, n) {
                var cardClass = n.is_read ? 'sc-notification-read' : 'sc-notification-unread';
                var badge = n.is_read ? '<span class="sc-notification-card-badge sc-notification-card-badge-read">خوانده شده</span>' : '<span class="sc-notification-card-badge">جدید</span>';
                var btnRead = n.is_read ? '' : '<button type="button" class="sc-notification-btn sc-notification-btn-secondary sc-btn-mark-read" data-id="' + n.id + '">خواندم</button>';
                html += '<article class="sc-notification-card ' + cardClass + '"><div class="sc-notification-card-inner">';
            // ذخیره URL فعلی قبل از رفتن به view
            var currentListUrl = baseUrl + buildQueryString(filter, s, page);
            var viewUrlWithBack = n.view_url + (n.view_url.indexOf('?') >= 0 ? '&' : '?') + 'back=' + encodeURIComponent(currentListUrl);
            html += '<h3 class="sc-notification-card-title"><a href="' + n.view_url + '" data-back-url="' + encodeURIComponent(currentListUrl) + '">' + (n.title || '') + '</a></h3>';
            html += '<div class="sc-notification-card-meta"><span class="sc-notification-card-date">' + n.created_at + '</span>' + badge + '</div>';
            html += '<div class="sc-notification-card-actions">' + btnRead + ' <a href="' + n.view_url + '" class="sc-notification-btn sc-notification-btn-primary" data-back-url="' + encodeURIComponent(currentListUrl) + '">مشاهده</a></div>';
                html += '</div></article>';
            });
            html += '</div>';
            if (data.total_pages > 1) {
                html += '<nav class="sc-notifications-pagination" aria-label="صفحه‌بندی اطلاعیه‌ها">';
                for (var p = 1; p <= data.total_pages; p++) {
                    var link = data.base_url_with_filter + (data.base_url_with_filter.indexOf('?') >= 0 ? '&' : '?') + 'notif_page=' + p;
                    var cls = p === data.page ? ' class="current"' : '';
                    html += (p === data.page) ? '<span' + cls + '>' + p + '</span> ' : '<a href="' + link + '" class="sc-notif-page-link" data-page="' + p + '">' + p + '</a> ';
                }
                html += '</nav>';
            }
        }
        $('#sc-notifications-ajax-container').html(html);
    }

    function loadNotifications(filter, s, page) {
        filter = filter || 'all';
        page = page || 1;
        var $container = $('#sc-notifications-ajax-container');
        $container.css('opacity', '0.6');
        $.post(ajaxUrl, {
            action: 'sc_notifications_filter',
            filter: filter,
            s: s,
            notif_page: page
        }, function(res) {
            $container.css('opacity', '1');
            if (res && res.success && res.data) {
                updateTabsActive(filter);
                updateTabCounts({ count_all: res.data.count_all, count_unread: res.data.count_unread, count_read: res.data.count_read });
                $('#sc-notif-search-input').val(s || '');
                $('.sc-notif-clear-search').toggle(!!s);
                renderList(res.data);
                var newUrl = baseUrl + buildQueryString(filter, s, page);
                if (window.history && window.history.pushState) {
                    window.history.pushState({ filter: filter, s: s, page: page }, '', newUrl);
                }
            }
        });
    }

    $('#sc-notifications-search-form').on('submit', function(e) {
        e.preventDefault();
        loadNotifications($('#sc-notif-filter-value').val(), $('#sc-notif-search-input').val().trim(), 1);
    });

    $(document).on('click', '.sc-notif-tab', function(e) {
        e.preventDefault();
        var filter = $(this).data('filter');
        loadNotifications(filter, $('#sc-notif-search-input').val().trim(), 1);
    });

    $('.sc-notif-clear-search').on('click', function() {
        $('#sc-notif-search-input').val('');
        loadNotifications($('#sc-notif-filter-value').val(), '', 1);
        $(this).hide();
    });

    /** کارت را در DOM بدون رفرش به حالت «خوانده شده» می‌برد و شمارنده تب‌ها را به‌روز می‌کند */
    function setCardAsRead($card) {
        if (!$card.length) return;
        $card.removeClass('sc-notification-unread').addClass('sc-notification-read');
        $card.find('.sc-notification-card-meta .sc-notification-card-badge:not(.sc-notification-card-badge-read)')
            .replaceWith('<span class="sc-notification-card-badge sc-notification-card-badge-read">خوانده شده</span>');
        $card.find('.sc-btn-mark-read').remove();
        var $unreadSpan = $('.sc-notif-tab-count[data-count="unread"]');
        var $readSpan = $('.sc-notif-tab-count[data-count="read"]');
        var u = parseInt($unreadSpan.text().replace(/\D/g, ''), 10) || 0;
        var r = parseInt($readSpan.text().replace(/\D/g, ''), 10) || 0;
        if (u > 0) u--;
        r++;
        $unreadSpan.text('(' + u + ')');
        $readSpan.text('(' + r + ')');
    }

    $(document).on('click', '.sc-btn-mark-read', function() {
        var btn = $(this), id = btn.data('id');
        var $card = btn.closest('.sc-notification-card');
        var btnText = btn.text();
        btn.prop('disabled', true).text('...');
        $.post(ajaxUrl, { action: 'sc_mark_notification_read', notification_id: id, nonce: nonceMarkRead })
            .done(function(res) {
                if (res && res.success) {
                    setCardAsRead($card);
                } else {
                    btn.prop('disabled', false).text(btnText);
                }
            })
            .fail(function() {
                btn.prop('disabled', false).text(btnText);
            });
    });

    $(document).on('click', '#sc-notifications-ajax-container .sc-notif-page-link', function(e) {
        e.preventDefault();
        loadNotifications($('#sc-notif-filter-value').val(), $('#sc-notif-search-input').val(), $(this).data('page'));
    });
    
    // مدیریت برگشت مرورگر (popstate)
    window.addEventListener('popstate', function(e) {
        if (e.state) {
            // اگر state وجود دارد، از آن استفاده می‌کنیم
            loadNotifications(e.state.filter, e.state.s, e.state.page);
        } else {
            // در غیر این صورت، از URL پارامترها را می‌خوانیم
            var urlParams = getUrlParams();
            loadNotifications(urlParams.filter, urlParams.s, urlParams.page);
        }
    });
    
    // اگر صفحه بدون view لود شد و پارامترهای جستجو در URL وجود دارند، لیست را با همان فیلترها لود می‌کنیم
    <?php if ($view_id == 0) : ?>
    var urlParams = getUrlParams();
    if (urlParams.s || urlParams.filter !== 'all' || urlParams.page > 1) {
        // فقط اگر با AJAX لود شده باشد (نه اولین بار)
        if (window.location.search.indexOf('notif_page') >= 0 || urlParams.s || urlParams.filter !== 'all') {
            loadNotifications(urlParams.filter, urlParams.s, urlParams.page);
        }
    }
    <?php endif; ?>
});
</script>
