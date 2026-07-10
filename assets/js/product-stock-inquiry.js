(function ($) {
    'use strict';

    var cfg = window.scProductStockInquiry || {};
    var labels = cfg.labels || {};
    var timer = null;
    var xhr = null;
    var selectedId = 0;
    var reqSeq = 0;
    var currentData = null;

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setSpinner(on) {
        $('#sc_pbs_search_spinner').prop('hidden', !on);
        $('.sc-pbs-search-box').toggleClass('is-searching', !!on);
    }

    function hideResults() {
        $('#sc_pbs_inquiry_results').empty().prop('hidden', true).removeClass('is-open');
        setSpinner(false);
    }

    function openResults(html) {
        $('#sc_pbs_inquiry_results')
            .html(html)
            .prop('hidden', false)
            .addClass('is-open');
    }

    function showSearching() {
        setSpinner(true);
        openResults(
            '<div class="sc-pbs-results-status">' +
            '<span class="sc-pbs-inline-spinner"></span>' +
            '<span>' + escapeHtml(labels.searching || 'در حال جستجو…') + '</span>' +
            '</div>'
        );
    }

    function showResults(items) {
        setSpinner(false);
        if (!items || !items.length) {
            openResults('<div class="sc-pbs-results-status is-empty">' + escapeHtml(labels.noResults || 'محصولی یافت نشد.') + '</div>');
            return;
        }

        var html = '<ul class="sc-pbs-results-list">';
        items.forEach(function (item) {
            var thumb = item.thumb
                ? '<img class="sc-pbs-results-img" src="' + escapeHtml(item.thumb) + '" alt="" width="40" height="40" loading="lazy" />'
                : '<span class="sc-pbs-results-img-ph" aria-hidden="true"></span>';
            var typeLabel = item.type === 'variation' ? 'متغیر' : (item.type === 'simple' ? 'ساده' : (item.type || ''));
            html +=
                '<li class="sc-pbs-results-item" data-id="' + escapeHtml(String(item.id)) + '" data-label="' + escapeHtml(item.label || item.name || '') + '" tabindex="0" role="option">' +
                '<span class="sc-pbs-results-thumb">' + thumb + '</span>' +
                '<span class="sc-pbs-results-body">' +
                '<span class="sc-pbs-results-title">' + escapeHtml(item.label || item.name || '') + '</span>' +
                (typeLabel ? '<span class="sc-pbs-results-meta">' + escapeHtml(typeLabel) + '</span>' : '') +
                '</span>' +
                '</li>';
        });
        html += '</ul>';
        openResults(html);
    }

    function setSelectedChip(item) {
        var $sel = $('#sc_pbs_inquiry_selected');
        if (!item) {
            $sel.prop('hidden', true).empty();
            return;
        }
        $sel.prop('hidden', false).html(
            '<span class="sc-pbs-chip">' +
            '<span class="sc-pbs-chip-text">' + escapeHtml(item.label || item.name) + '</span>' +
            '<button type="button" class="sc-pbs-chip-clear" aria-label="پاک کردن">&times;</button>' +
            '</span>'
        );
    }

    function showMsg($el, text, ok) {
        $el.removeClass('is-ok is-err').prop('hidden', !text);
        if (!text) {
            return;
        }
        $el.addClass(ok ? 'is-ok' : 'is-err').html(text);
    }

    function fillChapterSelects(data) {
        var branches = data.branches || [];
        var all = data.all_chapters && data.all_chapters.length
            ? data.all_chapters
            : branches.map(function (b) { return b.chapter; });

        var $from = $('#sc_pbs_tr_from').empty();
        var $to = $('#sc_pbs_tr_to').empty();
        var $ord = $('#sc_pbs_ord_chapter').empty();

        branches.forEach(function (b) {
            var qty = parseInt(b.qty, 10) || 0;
            $from.append(
                $('<option/>').val(b.chapter).text(b.chapter + ' (' + qty + ')')
            );
            if (qty > 0) {
                $ord.append(
                    $('<option/>').val(b.chapter).text(b.chapter + ' — موجودی ' + qty)
                );
            }
        });

        all.forEach(function (ch) {
            $to.append($('<option/>').val(ch).text(ch));
        });

        if (!$ord.children().length) {
            $ord.append($('<option/>').val('').text('شعبه‌ای با موجودی نیست'));
        }

        // مقصد پیش‌فرض: اولین شعبه غیر از مبدأ
        var fromVal = $from.val();
        $to.find('option').each(function () {
            if ($(this).val() !== fromVal) {
                $to.val($(this).val());
                return false;
            }
        });
    }

    function renderBranchRows(branches, maxQty) {
        maxQty = Math.max(1, parseInt(maxQty, 10) || 1);
        var $branches = $('#sc_pbs_branches').empty();
        if (!branches || !branches.length) {
            $branches.prop('hidden', true);
            return;
        }
        $branches.prop('hidden', false);
        branches.forEach(function (b) {
            var qty = parseInt(b.qty, 10) || 0;
            var pct = Math.min(100, Math.round((qty / maxQty) * 100));
            var ok = qty > 0;
            $branches.append(
                '<div class="sc-pbs-branch-row' + (ok ? ' is-instock' : ' is-outofstock') + '">' +
                '<div class="sc-pbs-branch-main">' +
                '<strong class="sc-pbs-branch-name">' + escapeHtml(b.chapter) + '</strong>' +
                '<span class="sc-pbs-badge ' + (ok ? 'is-ok' : 'is-off') + '">' +
                escapeHtml(ok ? (labels.inStock || 'موجود') : (labels.outOfStock || 'ناموجود')) +
                '</span></div>' +
                '<div class="sc-pbs-branch-stats">' +
                '<span class="sc-pbs-branch-qty">' + qty + '</span>' +
                '<div class="sc-pbs-bar" title="' + pct + '%"><span style="width:' + pct + '%"></span></div>' +
                '<span class="sc-pbs-branch-price">' + (b.price_html || b.price_formatted || '—') + '</span>' +
                '</div></div>'
            );
        });
    }

    function applyBranchMap(map) {
        if (!currentData || !map) {
            return;
        }
        var priceHtml = (currentData.product && currentData.product.price_html) || '';
        var branches = [];
        var maxQty = 0;
        Object.keys(map).forEach(function (ch) {
            var qty = parseInt(map[ch], 10) || 0;
            if (qty > maxQty) {
                maxQty = qty;
            }
            branches.push({
                chapter: ch,
                qty: qty,
                price_html: priceHtml
            });
        });
        currentData.branches = branches;
        currentData.max_qty = maxQty;
        renderBranchRows(branches, maxQty);
        fillChapterSelects(currentData);
    }

    function renderInquiry(data) {
        currentData = data;
        var product = data.product || {};
        var branches = data.branches || [];
        var maxQty = Math.max(1, parseInt(data.max_qty, 10) || 1);

        $('#sc_pbs_inquiry_loading').prop('hidden', true);
        $('#sc_pbs_inquiry_placeholder').prop('hidden', true);
        $('#sc_pbs_inquiry_panel').prop('hidden', false);

        var $thumb = $('#sc_pbs_inquiry_thumb').empty();
        if (product.thumb) {
            $thumb.html('<img class="sc-pbs-thumb-img" src="' + escapeHtml(product.thumb) + '" alt="" width="56" height="56" />');
        } else {
            $thumb.html('<span class="sc-pbs-thumb-ph" aria-hidden="true"></span>');
        }

        $('#sc_pbs_inquiry_name').text(product.name || '');
        var sub = [];
        if (product.sku) {
            sub.push('<span>SKU: ' + escapeHtml(product.sku) + '</span>');
        }
        if (product.type === 'variation') {
            sub.push('<span class="sc-pbs-tag">متغیر</span>');
        } else if (product.type === 'simple') {
            sub.push('<span class="sc-pbs-tag">ساده</span>');
        }
        if (product.edit_url) {
            sub.push('<a class="sc-pbs-edit-link" href="' + escapeHtml(product.edit_url) + '">ویرایش</a>');
        }
        $('#sc_pbs_inquiry_sub').html(sub.join(''));
        $('#sc_pbs_inquiry_price').html(product.price_html || '');

        var $empty = $('#sc_pbs_inquiry_empty');
        if (!product.has_branch_config || !branches.length) {
            $('#sc_pbs_branches').prop('hidden', true).empty();
            $empty.prop('hidden', false).text(labels.noBranches || 'برای این محصول موجودی شعبه‌ای تعریف نشده است.');
            $('#sc_pbs_actions').prop('hidden', true);
            return;
        }

        $empty.prop('hidden', true);
        renderBranchRows(branches, maxQty);
        fillChapterSelects(data);
        $('#sc_pbs_actions').prop('hidden', false);
        showMsg($('#sc_pbs_tr_msg'), '', true);
        showMsg($('#sc_pbs_ord_msg'), '', true);
        if (typeof window.initPersianDatePicker === 'function') {
            window.initPersianDatePicker();
        }
    }

    function loadInquiry(productId, meta) {
        selectedId = productId;
        currentData = null;
        hideResults();
        $('#sc_pbs_inquiry_placeholder').prop('hidden', true);
        $('#sc_pbs_inquiry_panel').prop('hidden', false);
        $('#sc_pbs_inquiry_empty').prop('hidden', true);
        $('#sc_pbs_branches').prop('hidden', true).empty();
        $('#sc_pbs_actions').prop('hidden', true);
        $('#sc_pbs_inquiry_loading').prop('hidden', false);
        $('#sc_pbs_inquiry_name').text(meta && meta.label ? meta.label : (labels.loading || '…'));
        $('#sc_pbs_inquiry_sub').empty();
        $('#sc_pbs_inquiry_price').empty();
        $('#sc_pbs_inquiry_thumb').html('<span class="sc-pbs-thumb-ph" aria-hidden="true"></span>');

        if (meta) {
            setSelectedChip(meta);
        }

        $.get(cfg.ajaxUrl, {
            action: 'sc_product_stock_inquiry',
            nonce: cfg.nonce,
            product_id: productId
        }).done(function (res) {
            if (!res || !res.success) {
                $('#sc_pbs_inquiry_loading').prop('hidden', true);
                $('#sc_pbs_inquiry_empty').prop('hidden', false)
                    .text((res && res.data && res.data.message) || labels.error || 'خطا');
                return;
            }
            renderInquiry(res.data || {});
        }).fail(function () {
            $('#sc_pbs_inquiry_loading').prop('hidden', true);
            $('#sc_pbs_inquiry_empty').prop('hidden', false).text(labels.error || 'خطا');
        });
    }

    function search(q) {
        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
        }
        if (!q) {
            hideResults();
            return;
        }
        if (q.length < 2) {
            setSpinner(false);
            openResults('<div class="sc-pbs-results-status">' + escapeHtml(labels.minChars || 'حداقل ۲ کاراکتر تایپ کنید.') + '</div>');
            return;
        }

        var seq = ++reqSeq;
        showSearching();

        xhr = $.get(cfg.ajaxUrl, {
            action: 'sc_product_stock_search',
            nonce: cfg.nonce,
            q: q
        }).done(function (res) {
            if (seq !== reqSeq) {
                return;
            }
            if (!res || !res.success) {
                setSpinner(false);
                openResults('<div class="sc-pbs-results-status is-error">' + escapeHtml(labels.searchError || 'خطا در جستجو.') + '</div>');
                return;
            }
            showResults((res.data && res.data.items) || []);
        }).fail(function (jqXHR, textStatus) {
            if (textStatus === 'abort' || seq !== reqSeq) {
                return;
            }
            setSpinner(false);
            openResults('<div class="sc-pbs-results-status is-error">' + escapeHtml(labels.searchError || 'خطا در جستجو.') + '</div>');
        });
    }

    function pickResult($li) {
        var id = parseInt($li.attr('data-id'), 10) || 0;
        var label = $li.attr('data-label') || $li.find('.sc-pbs-results-title').text();
        if (!id) {
            return;
        }
        $('#sc_pbs_inquiry_q').val('');
        loadInquiry(id, { id: id, label: label, name: label });
    }

    function setBusy($btn, on, text) {
        if (on) {
            if (!$btn.data('orig')) {
                $btn.data('orig', $btn.text());
            }
            $btn.prop('disabled', true).text(text || labels.working || '…');
        } else {
            $btn.prop('disabled', false).text($btn.data('orig') || $btn.text());
        }
    }

    function doTransfer() {
        if (!selectedId) {
            showMsg($('#sc_pbs_tr_msg'), labels.selectProduct || 'محصول انتخاب نشده', false);
            return;
        }
        var $btn = $('#sc_pbs_tr_submit');
        var from = $('#sc_pbs_tr_from').val();
        var to = $('#sc_pbs_tr_to').val();
        var qty = parseInt($('#sc_pbs_tr_qty').val(), 10) || 0;
        setBusy($btn, true);
        showMsg($('#sc_pbs_tr_msg'), '', true);

        $.post(cfg.ajaxUrl, {
            action: 'sc_product_stock_transfer',
            nonce: cfg.nonce,
            product_id: selectedId,
            from_chapter: from,
            to_chapter: to,
            qty: qty
        }).done(function (res) {
            setBusy($btn, false);
            if (!res || !res.success) {
                showMsg($('#sc_pbs_tr_msg'), (res && res.data && res.data.message) || labels.transferFail, false);
                return;
            }
            showMsg($('#sc_pbs_tr_msg'), (res.data && res.data.message) || labels.transferOk, true);
            if (res.data && res.data.branches) {
                applyBranchMap(res.data.branches);
            } else {
                loadInquiry(selectedId);
            }
        }).fail(function () {
            setBusy($btn, false);
            showMsg($('#sc_pbs_tr_msg'), labels.transferFail, false);
        });
    }

    function doInstantOrder() {
        if (!selectedId) {
            showMsg($('#sc_pbs_ord_msg'), labels.selectProduct || 'محصول انتخاب نشده', false);
            return;
        }
        var $btn = $('#sc_pbs_ord_submit');
        setBusy($btn, true);
        showMsg($('#sc_pbs_ord_msg'), '', true);

        $.post(cfg.ajaxUrl, {
            action: 'sc_product_stock_instant_order',
            nonce: cfg.nonce,
            product_id: selectedId,
            first_name: $.trim($('#sc_pbs_ord_first').val()),
            last_name: $.trim($('#sc_pbs_ord_last').val()),
            mobile: $.trim($('#sc_pbs_ord_mobile').val()),
            chapter: $('#sc_pbs_ord_chapter').val(),
            qty: parseInt($('#sc_pbs_ord_qty').val(), 10) || 1,
            payment_status: $('#sc_pbs_ord_pay').val(),
            order_date: $.trim($('#sc_pbs_ord_date').val())
        }).done(function (res) {
            setBusy($btn, false);
            if (!res || !res.success) {
                showMsg($('#sc_pbs_ord_msg'), (res && res.data && res.data.message) || labels.orderFail, false);
                return;
            }
            var msg = (res.data && res.data.message) || labels.orderOk;
            if (res.data && res.data.order_url) {
                msg += ' <a href="' + escapeHtml(res.data.order_url) + '" target="_blank">مشاهده سفارش</a>';
            } else if (cfg.ordersUrl) {
                msg += ' <a href="' + escapeHtml(cfg.ordersUrl) + '">لیست سفارشات</a>';
            }
            showMsg($('#sc_pbs_ord_msg'), msg, true);
            if (res.data && res.data.branches) {
                applyBranchMap(res.data.branches);
            } else if ($('#sc_pbs_ord_pay').val() !== 'pending') {
                loadInquiry(selectedId);
            }
        }).fail(function () {
            setBusy($btn, false);
            showMsg($('#sc_pbs_ord_msg'), labels.orderFail, false);
        });
    }

    $(function () {
        var $input = $('#sc_pbs_inquiry_q');

        $input.on('input', function () {
            var q = $.trim($input.val());
            clearTimeout(timer);
            timer = setTimeout(function () {
                search(q);
            }, 260);
        });

        $input.on('focus', function () {
            var q = $.trim($input.val());
            if (q.length >= 2 && !$('#sc_pbs_inquiry_results').hasClass('is-open')) {
                search(q);
            }
        });

        $input.on('keydown', function (e) {
            if (e.key === 'Escape') {
                hideResults();
                return;
            }
            if (e.key === 'Enter') {
                var $first = $('#sc_pbs_inquiry_results .sc-pbs-results-item').first();
                if ($first.length) {
                    e.preventDefault();
                    pickResult($first);
                }
            }
        });

        $(document).on('click', '.sc-pbs-results-item', function () {
            pickResult($(this));
        });

        $(document).on('click', '.sc-pbs-chip-clear', function () {
            selectedId = 0;
            currentData = null;
            setSelectedChip(null);
            $('#sc_pbs_inquiry_panel').prop('hidden', true);
            $('#sc_pbs_inquiry_placeholder').prop('hidden', false);
            hideResults();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.sc-pbs-search-field').length) {
                hideResults();
            }
        });

        $('#sc_pbs_tr_submit').on('click', doTransfer);
        $('#sc_pbs_ord_submit').on('click', doInstantOrder);

        if (typeof window.initPersianDatePicker === 'function') {
            window.initPersianDatePicker();
        }
    });
})(jQuery);
