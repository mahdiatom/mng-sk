/**
 * جستجوی هدر — فایل جدا تا بارگذاری و دیباگ مطمئن‌تر باشد.
 */
(function ($) {
    'use strict';

    if (typeof window.scHeaderSearch === 'undefined') {
        return;
    }

    var cfg = window.scHeaderSearch;
    var debounceTimer = null;
    var debounceMs = 280;

    function typeLabel(type) {
        if (type === 'page') return 'صفحه';
        if (type === 'post') return 'مطلب';
        if (type === 'product') return 'محصول';
        if (type === 'suggestion') return 'پیشنهاد';
        if (type === 'service') return 'خدمت';
        if (type === 'course') return 'دوره';
        if (type === 'event') return 'رویداد';
        if (type === 'shortcut') return (cfg.i18n && cfg.i18n.shortcutMeta) ? cfg.i18n.shortcutMeta : '';
        return '';
    }

    function escAttr(s) {
        return $('<div>').text(String(s || '')).html();
    }

    /**
     * میانبرها را طوری برمی‌گرداند که آدرس تکراری با پیشنهادها حذف شود.
     */
    function quickLinksDeduped(suggestions) {
        var quick = (cfg.quickLinks && Array.isArray(cfg.quickLinks)) ? cfg.quickLinks : [];
        var seen = {};
        (suggestions || []).forEach(function (it) {
            if (it && it.url) {
                seen[it.url] = true;
            }
        });
        return quick.filter(function (it) {
            return it && it.url && !seen[it.url];
        });
    }

    function appendLinksSection(title, items, metaStr, opts) {
        opts = opts || {};
        var h = '';
        if (!items || !items.length) {
            return h;
        }
        var itemCls = opts.tile
            ? 'sc-header-search__item sc-header-search__item--linktile'
            : 'sc-header-search__item';
        h += '<div class="sc-header-search__block">';
        if (title) {
            h += '<p class="sc-header-search__section-title">' + escAttr(title) + '</p>';
        }
        if (opts.grid) {
            h += '<div class="sc-header-search__link-grid">';
        }
        items.forEach(function (item) {
            h += '<a class="' + itemCls + '" href="' + escAttr(item.url) + '">';
            h += escAttr(item.title);
            h += '<span class="sc-header-search__meta">' + escAttr(metaStr || typeLabel(item.type)) + '</span></a>';
        });
        if (opts.grid) {
            h += '</div>';
        }
        h += '</div>';
        return h;
    }

    function renderDropdown($dd, data, query) {
        var sug = (data && data.suggestions) ? data.suggestions : [];
        var res = (data && data.results) ? data.results : [];
        var q = String(query || '').trim();
        var i18n = cfg.i18n || {};
        var html = '';

        $dd.removeClass('sc-header-search__dropdown--expanded sc-header-search__dropdown--help');

        var club = res.filter(function (r) {
            return r.type === 'course' || r.type === 'event';
        });
        var pages = res.filter(function (r) {
            return r.type === 'page' || r.type === 'post' || r.type === 'service';
        });
        var prods = res.filter(function (r) {
            return r.type === 'product';
        });
        var hasMatches = club.length || pages.length || prods.length;

        /* نتایج معمول — بدون پنل کمکی */
        if (q !== '' && hasMatches) {
            if (club.length) {
                html += '<p class="sc-header-search__section-title">' + (i18n.clubServicesTitle || '') + '</p>';
                club.forEach(function (item) {
                    html += '<a class="sc-header-search__item" href="' + escAttr(item.url) + '">';
                    html += escAttr(item.title);
                    html += '<span class="sc-header-search__meta">' + typeLabel(item.type) + '</span></a>';
                });
            }
            if (pages.length) {
                html += '<p class="sc-header-search__section-title">' + (i18n.pagesPostsTitle || '') + '</p>';
                pages.forEach(function (item) {
                    html += '<a class="sc-header-search__item" href="' + escAttr(item.url) + '">';
                    html += escAttr(item.title);
                    html += '<span class="sc-header-search__meta">' + typeLabel(item.type) + '</span></a>';
                });
            }
            if (prods.length) {
                html += '<p class="sc-header-search__section-title">' + (i18n.productsTitle || '') + '</p>';
                prods.forEach(function (item) {
                    html += '<a class="sc-header-search__item" href="' + escAttr(item.url) + '">';
                    html += escAttr(item.title);
                    var meta = typeLabel('product');
                    if (item.price) {
                        meta += ' · <span class="sc-header-search__price">' + escAttr(item.price) + '</span>';
                    }
                    html += '<span class="sc-header-search__meta">' + meta + '</span></a>';
                });
            }
            $dd.prop('hidden', false).html(html);
            return;
        }

        /* بدون نتیجه هنگام تایپ — پیشنهادها + میانبرها در پنل بزرگ */
        if (q !== '' && !hasMatches) {
            var quick = quickLinksDeduped(sug);
            var metaShortcut = (i18n.shortcutMeta || typeLabel('shortcut'));
            html = '<div class="sc-header-search__panel sc-header-search__panel--help">';
            html += '<div class="sc-header-search__empty-head">';
            html += '<p class="sc-header-search__empty-title">' + escAttr(i18n.noResults || '') + '</p>';
            if (i18n.noResultsHint) {
                html += '<p class="sc-header-search__empty-text">' + escAttr(i18n.noResultsHint) + '</p>';
            }
            html += '</div>';
            html += appendLinksSection(i18n.suggestionsTitle || '', sug, typeLabel('suggestion'), { grid: false, tile: false });
            html += appendLinksSection(i18n.quickLinksTitle || '', quick, metaShortcut, { grid: true, tile: true });
            if (!sug.length && !quick.length) {
                html += '<div class="sc-header-search__empty">' + escAttr(i18n.noResults || '') + '</div>';
            }
            html += '</div>';
            $dd.addClass('sc-header-search__dropdown--expanded sc-header-search__dropdown--help').prop('hidden', false).html(html);
            return;
        }

        /* فوکوس بدون متن — پیشنهادها + میانبرها */
        if (q === '') {
            var quickEmpty = quickLinksDeduped(sug);
            if (!sug.length && !quickEmpty.length) {
                $dd.prop('hidden', true).empty();
                return;
            }
            html = '<div class="sc-header-search__panel sc-header-search__panel--help">';
            html += appendLinksSection(i18n.suggestionsTitle || '', sug, typeLabel('suggestion'), { grid: false, tile: false });
            html += appendLinksSection(i18n.quickLinksTitle || '', quickEmpty, (i18n.shortcutMeta || typeLabel('shortcut')), { grid: true, tile: true });
            html += '</div>';
            $dd.addClass('sc-header-search__dropdown--expanded sc-header-search__dropdown--help').prop('hidden', false).html(html);
            return;
        }

        if (!html) {
            $dd.prop('hidden', true).empty();
        }
    }

    function fetchForBox($box, q) {
        var $dd = $box.find('.sc-header-search__dropdown');
        var i18n = cfg.i18n || {};
        $dd.prop('hidden', false).html('<div class="sc-header-search__loading">' + (i18n.loading || '') + '</div>');

        $.ajax({
            url: cfg.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sc_header_search',
                nonce: cfg.nonce,
                q: q
            }
        }).done(function (resp) {
            if (!resp || resp.success !== true || !resp.data) {
                $dd.prop('hidden', true).empty();
                return;
            }
            renderDropdown($dd, resp.data, q);
        }).fail(function () {
            $dd.prop('hidden', true).empty();
        });
    }

    function scheduleFetch($box, q) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            fetchForBox($box, q);
        }, debounceMs);
    }

    $(document).on('focus.scHs', '.sc-header-search__input', function () {
        var $box = $(this).closest('.sc-header-search__box');
        var q = $(this).val().trim();
        fetchForBox($box, q);
    });

    $(document).on('input.scHs', '.sc-header-search__input', function () {
        var $box = $(this).closest('.sc-header-search__box');
        var q = $(this).val().trim();
        scheduleFetch($box, q);
    });

    $(document).on('click.scHs', function (e) {
        var $t = $(e.target);
        if ($t.closest('.sc-header-search__box, .sc-header-search-toggle, #sc-header-search-popup').length) {
            return;
        }
        $('.sc-header-search__dropdown').prop('hidden', true).empty();
    });

    var $popup = $('#sc-header-search-popup');
    $(document).on('click.scHs', '.sc-header-search-toggle', function (e) {
        e.preventDefault();
        if (!$popup.length) {
            return;
        }
        $popup.addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
        setTimeout(function () {
            $popup.find('.sc-header-search__input--popup').trigger('focus');
        }, 80);
    });

    function closeSearchPopup() {
        if (!$popup.length) {
            return;
        }
        $popup.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
        $popup.find('.sc-header-search__dropdown').prop('hidden', true).empty();
    }

    $(document).on('click.scHs', '.sc-header-search-popup__backdrop, .sc-header-search-popup__close', function (e) {
        e.preventDefault();
        closeSearchPopup();
    });

    $(document).on('keydown.scHs', function (e) {
        if (e.key === 'Escape') {
            if ($popup.hasClass('is-open')) {
                closeSearchPopup();
            }
            $('.sc-header-search__dropdown').prop('hidden', true).empty();
        }
    });
})(jQuery);
