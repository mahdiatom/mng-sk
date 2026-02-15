/**
 * تب‌های اطلاعیه‌ها (همه / خوانده نشده / خوانده شده) و جستجو با AJAX
 * با event delegation روی document تا بعد از لود AJAX محتوا هم کار کند.
 */
(function($) {
    'use strict';

    var baseUrl = (window.scNotificationsAjax && window.scNotificationsAjax.baseUrl) || '';
    var ajaxUrl = (window.scNotificationsAjax && window.scNotificationsAjax.ajaxurl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    var nonceMarkRead = (window.scNotificationsAjax && window.scNotificationsAjax.nonceMarkRead) || '';

    function getUrlParams() {
        var params = new URLSearchParams(window.location.search);
        return {
            filter: params.get('filter') || 'all',
            s: params.get('s') || '',
            page: parseInt(params.get('notif_page'), 10) || 1
        };
    }

    function buildQueryString(filter, s, page) {
        var q = [];
        if (filter && filter !== 'all') q.push('filter=' + encodeURIComponent(filter));
        if (s) q.push('s=' + encodeURIComponent(s));
        if (page && page > 1) q.push('notif_page=' + page);
        return q.length ? '?' + q.join('&') : '';
    }

    function updateTabsActive(filter) {
        $('.sc-notif-tab').removeClass('active').css({'background': '#f0f0f1', 'color': '#1d2327'});
        $('.sc-notif-tab[data-filter="' + filter + '"]').addClass('active').css({'background': '#2271b1', 'color': '#fff'});
        var $val = $('#sc-notif-filter-value');
        if ($val.length) $val.val(filter);
    }

    function updateTabCounts(counts) {
        if (!counts) return;
        if (typeof counts.count_all !== 'undefined') $('.sc-notif-tab-count[data-count="all"]').text('(' + counts.count_all + ')');
        if (typeof counts.count_unread !== 'undefined') $('.sc-notif-tab-count[data-count="unread"]').text('(' + counts.count_unread + ')');
        if (typeof counts.count_read !== 'undefined') $('.sc-notif-tab-count[data-count="read"]').text('(' + counts.count_read + ')');
    }

    function renderList(data, filter, s, page) {
        filter = filter || 'all';
        s = s || '';
        page = page || 1;
        var html = '';
        var $container = $('#sc-notifications-ajax-container');
        if (!$container.length) return;

        if (!data.items || data.items.length === 0) {
            html = '<div class="sc-notifications-empty-state"><span class="sc-notifications-empty-icon" aria-hidden="true"></span><p class="sc-notifications-empty-text">' + (data.empty_message || '') + '</p></div>';
        } else {
            html = '<div class="sc-notifications-grid">';
            $.each(data.items, function(i, n) {
                var cardClass = n.is_read ? 'sc-notification-read' : 'sc-notification-unread';
                var badge = n.is_read ? '<span class="sc-notification-card-badge sc-notification-card-badge-read">خوانده شده</span>' : '<span class="sc-notification-card-badge">جدید</span>';
                var btnRead = n.is_read ? '' : '<button type="button" class="sc-notification-btn sc-notification-btn-secondary sc-btn-mark-read" data-id="' + n.id + '">خواندم</button>';
                var currentListUrl = baseUrl + buildQueryString(filter, s, page);
                var viewUrl = (n.view_url || '').indexOf('?') >= 0 ? (n.view_url + '&back=' + encodeURIComponent(currentListUrl)) : (n.view_url + '?back=' + encodeURIComponent(currentListUrl));
                html += '<article class="sc-notification-card ' + cardClass + '"><div class="sc-notification-card-inner">';
                html += '<h3 class="sc-notification-card-title"><a href="' + (n.view_url || '') + '" data-back-url="' + encodeURIComponent(currentListUrl) + '">' + (n.title || '') + '</a></h3>';
                html += '<div class="sc-notification-card-meta"><span class="sc-notification-card-date">' + (n.created_at || '') + '</span>' + badge + '</div>';
                html += '<div class="sc-notification-card-actions">' + btnRead + ' <a href="' + (n.view_url || '') + '" class="sc-notification-btn sc-notification-btn-primary" data-back-url="' + encodeURIComponent(currentListUrl) + '">مشاهده</a></div>';
                html += '</div></article>';
            });
            html += '</div>';
            if (data.total_pages > 1) {
                html += '<nav class="sc-notifications-pagination" aria-label="صفحه‌بندی اطلاعیه‌ها">';
                for (var p = 1; p <= data.total_pages; p++) {
                    var link = (data.base_url_with_filter || baseUrl) + ((data.base_url_with_filter || baseUrl).indexOf('?') >= 0 ? '&' : '?') + 'notif_page=' + p;
                    var cls = p === data.page ? ' class="current"' : '';
                    html += (p === data.page) ? '<span' + cls + '>' + p + '</span> ' : '<a href="' + link + '" class="sc-notif-page-link" data-page="' + p + '">' + p + '</a> ';
                }
                html += '</nav>';
            }
        }
        $container.html(html);
    }

    function loadNotifications(filter, s, page) {
        filter = filter || 'all';
        page = page || 1;
        var $container = $('#sc-notifications-ajax-container');
        if (!$container.length || !ajaxUrl) return;
        $container.css('opacity', '0.6');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'sc_notifications_filter',
                filter: filter,
                s: s || '',
                notif_page: page
            },
            dataType: 'json'
        }).done(function(res) {
            if (res && res.success && res.data) {
                updateTabsActive(filter);
                updateTabCounts({ count_all: res.data.count_all, count_unread: res.data.count_unread, count_read: res.data.count_read });
                $('#sc-notif-search-input').val(s || '');
                $('.sc-notif-clear-search').toggle(!!s);
                renderList(res.data, filter, s, page);
                var newUrl = baseUrl + buildQueryString(filter, s, page);
                if (window.history && window.history.pushState) {
                    window.history.pushState({ filter: filter, s: s, page: page }, '', newUrl);
                }
            }
        }).fail(function() {
            $container.css('opacity', '1');
        }).always(function() {
            $container.css('opacity', '1');
        });
    }

    function setCardAsRead($card) {
        if (!$card || !$card.length) return;
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

    $(function() {
        // لینک بازگشت در صفحه view
        var $backLink = $('.sc-notification-back-link');
        if ($backLink.length && baseUrl) {
            var savedBackUrl = null;
            try { savedBackUrl = localStorage.getItem('sc_notifications_back_url'); } catch (e) {}
            if (savedBackUrl && savedBackUrl.indexOf('sc-notifications') >= 0) {
                $backLink.attr('href', savedBackUrl);
                try { localStorage.removeItem('sc_notifications_back_url'); } catch (e) {}
            } else if (document.referrer && document.referrer.indexOf('sc-notifications') >= 0) {
                try {
                    var refUrl = new URL(document.referrer);
                    var refParams = { filter: refUrl.searchParams.get('filter') || 'all', s: refUrl.searchParams.get('s') || '', page: parseInt(refUrl.searchParams.get('notif_page'), 10) || 1 };
                    $backLink.attr('href', baseUrl + buildQueryString(refParams.filter, refParams.s, refParams.page));
                } catch (e) {
                    $backLink.attr('href', baseUrl);
                }
            } else {
                $backLink.attr('href', baseUrl);
            }
            var href = $backLink.attr('href');
            if (href && href.indexOf('sc-notifications') === -1) $backLink.attr('href', baseUrl);
        }

        // ذخیره URL قبل از رفتن به view
        $(document).on('click', '#sc-notifications-ajax-container a[href*="view="]', function(e) {
            var $link = $(this);
            if (!$link.data('back-url')) {
                var currentParams = new URLSearchParams(window.location.search);
                var backUrl = window.location.href.split('?view=')[0].split('?')[0];
                var params = [];
                if (currentParams.get('s')) params.push('s=' + encodeURIComponent(currentParams.get('s')));
                if (currentParams.get('filter') && currentParams.get('filter') !== 'all') params.push('filter=' + encodeURIComponent(currentParams.get('filter')));
                if (currentParams.get('notif_page') && parseInt(currentParams.get('notif_page'), 10) > 1) params.push('notif_page=' + currentParams.get('notif_page'));
                if (params.length) backUrl += '?' + params.join('&');
                if (backUrl.indexOf('sc-notifications') >= 0) {
                    $link.attr('href', $link.attr('href') + ($link.attr('href').indexOf('?') >= 0 ? '&' : '?') + 'back=' + encodeURIComponent(backUrl));
                    try { localStorage.setItem('sc_notifications_back_url', backUrl); } catch (e) {}
                }
            }
        });

        $(document).on('submit', '#sc-notifications-search-form', function(e) {
            e.preventDefault();
            loadNotifications($('#sc-notif-filter-value').val(), $('#sc-notif-search-input').val().trim(), 1);
        });

        $(document).on('click', '.sc-notif-tab', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var filter = $(this).data('filter');
            if (filter) loadNotifications(filter, $('#sc-notif-search-input').val().trim(), 1);
            return false;
        });

        $(document).on('click', '.sc-notif-clear-search', function() {
            $('#sc-notif-search-input').val('');
            loadNotifications($('#sc-notif-filter-value').val(), '', 1);
            $(this).hide();
        });

        $(document).on('click', '.sc-btn-mark-read', function() {
            var btn = $(this), id = btn.data('id');
            var $card = btn.closest('.sc-notification-card');
            var btnText = btn.text();
            btn.prop('disabled', true).text('...');
            $.post(ajaxUrl, { action: 'sc_mark_notification_read', notification_id: id, nonce: nonceMarkRead })
                .done(function(res) {
                    if (res && res.success) setCardAsRead($card);
                    else btn.prop('disabled', false).text(btnText);
                })
                .fail(function() {
                    btn.prop('disabled', false).text(btnText);
                });
        });

        $(document).on('click', '#sc-notifications-ajax-container .sc-notif-page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            loadNotifications($('#sc-notif-filter-value').val(), $('#sc-notif-search-input').val(), page);
            return false;
        });

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.filter) {
                loadNotifications(e.state.filter, e.state.s || '', e.state.page || 1);
            } else {
                var urlParams = getUrlParams();
                loadNotifications(urlParams.filter, urlParams.s, urlParams.page);
            }
        });

        // اگر صفحه لیست با پارامترهای فیلتر لود شده، همگام با URL (اختیاری)
        var urlParams = getUrlParams();
        if ($('#sc-notifications-ajax-container').length && (urlParams.s || urlParams.filter !== 'all' || urlParams.page > 1)) {
            if (window.location.search.indexOf('notif_page') >= 0 || urlParams.s || urlParams.filter !== 'all') {
                loadNotifications(urlParams.filter, urlParams.s, urlParams.page);
            }
        }
    });
})(jQuery);
