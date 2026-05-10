/**
 * Background bulk-action processor for the invoices admin list.
 *
 * Intercepts the WP_List_Table bulk-action submit on the sc-invoices page,
 * sends the selected invoice IDs to the server in small batches via AJAX,
 * and displays real-time progress in a modal so the operation never times out.
 *
 * Falls back silently to the regular synchronous form submission when the
 * required scInvoicesBulkBg config is missing.
 */
(function ($) {
    'use strict';

    if (typeof scInvoicesBulkBg === 'undefined') {
        return;
    }

    var cfg = scInvoicesBulkBg;
    var i18n = cfg.i18n || {};
    var supported = cfg.actions || {};

    var state = {
        running: false,
        cancelRequested: false,
        action: '',
        ids: [],
        total: 0,
        processed: 0,
        success: 0,
        errors: 0,
        startedAt: 0,
        timer: null,
        $modal: null
    };

    /**
     * Detect the bulk-action form (the one wrapping the WP_List_Table).
     */
    function findListForm() {
        var $forms = $('form');
        var $found = null;
        $forms.each(function () {
            var $f = $(this);
            if ($f.find('input[name="page"][value="sc-invoices"]').length &&
                $f.find('input[name="invoice[]"]').length) {
                $found = $f;
                return false;
            }
        });
        return $found;
    }

    /**
     * Pick the action chosen in either the top or bottom bulk dropdown.
     * The clicked button tells us which one to read first.
     */
    function pickActionFromButton($btn, $form) {
        var topVal = $form.find('select[name="action"]').val();
        var bottomVal = $form.find('select[name="action2"]').val();
        var btnId = $btn.attr('id') || '';

        if (btnId === 'doaction2') {
            if (bottomVal && bottomVal !== '-1') return bottomVal;
            if (topVal && topVal !== '-1') return topVal;
        } else {
            if (topVal && topVal !== '-1') return topVal;
            if (bottomVal && bottomVal !== '-1') return bottomVal;
        }
        return '';
    }

    function getSelectedIds($form) {
        var ids = [];
        $form.find('input[name="invoice[]"]:checked').each(function () {
            var v = parseInt($(this).val(), 10);
            if (v > 0) ids.push(v);
        });
        return ids;
    }

    function buildModal() {
        var html = '' +
            '<div class="sc-ibb-overlay" role="dialog" aria-modal="true">' +
                '<div class="sc-ibb-modal">' +
                    '<div class="sc-ibb-header">' +
                        '<h2 class="sc-ibb-title">' + escapeHtml(i18n.title || 'پردازش') + '</h2>' +
                        '<div class="sc-ibb-action-label"></div>' +
                    '</div>' +
                    '<div class="sc-ibb-body">' +
                        '<div class="sc-ibb-progress-wrap">' +
                            '<div class="sc-ibb-progress-bar"><span></span></div>' +
                            '<div class="sc-ibb-progress-text">0 / 0</div>' +
                        '</div>' +
                        '<div class="sc-ibb-stats">' +
                            '<div class="sc-ibb-stat sc-ibb-stat-total">' +
                                '<span class="sc-ibb-stat-label">' + escapeHtml(i18n.processing || 'پردازش') + '</span>' +
                                '<span class="sc-ibb-stat-value sc-ibb-val-processed">0</span>' +
                            '</div>' +
                            '<div class="sc-ibb-stat sc-ibb-stat-success">' +
                                '<span class="sc-ibb-stat-label">' + escapeHtml(i18n.success || 'موفق') + '</span>' +
                                '<span class="sc-ibb-stat-value sc-ibb-val-success">0</span>' +
                            '</div>' +
                            '<div class="sc-ibb-stat sc-ibb-stat-error">' +
                                '<span class="sc-ibb-stat-label">' + escapeHtml(i18n.errors || 'ناموفق') + '</span>' +
                                '<span class="sc-ibb-stat-value sc-ibb-val-errors">0</span>' +
                            '</div>' +
                            '<div class="sc-ibb-stat sc-ibb-stat-remaining">' +
                                '<span class="sc-ibb-stat-label">' + escapeHtml(i18n.remaining || 'باقی‌مانده') + '</span>' +
                                '<span class="sc-ibb-stat-value sc-ibb-val-remaining">0</span>' +
                            '</div>' +
                            '<div class="sc-ibb-stat sc-ibb-stat-time">' +
                                '<span class="sc-ibb-stat-label">' + escapeHtml(i18n.elapsed || 'زمان') + '</span>' +
                                '<span class="sc-ibb-stat-value sc-ibb-val-elapsed">00:00</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="sc-ibb-status">' + escapeHtml(i18n.preparing || '...') + '</div>' +
                        '<div class="sc-ibb-log-wrap">' +
                            '<ul class="sc-ibb-log"></ul>' +
                        '</div>' +
                    '</div>' +
                    '<div class="sc-ibb-footer">' +
                        '<button type="button" class="button sc-ibb-cancel">' + escapeHtml(i18n.cancel || 'توقف') + '</button>' +
                        '<button type="button" class="button button-primary sc-ibb-reload" style="display:none;">' + escapeHtml(i18n.reload || 'بارگذاری مجدد') + '</button>' +
                        '<button type="button" class="button sc-ibb-close" style="display:none;">' + escapeHtml(i18n.close || 'بستن') + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var $modal = $(html);
        $('body').append($modal);
        return $modal;
    }

    function escapeHtml(s) {
        if (s === null || typeof s === 'undefined') return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtTime(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function updateUI() {
        if (!state.$modal) return;
        var pct = state.total > 0 ? Math.round((state.processed / state.total) * 100) : 0;
        state.$modal.find('.sc-ibb-progress-bar > span').css('width', pct + '%');
        state.$modal.find('.sc-ibb-progress-text').text(state.processed + ' / ' + state.total + '  (' + pct + '%)');
        state.$modal.find('.sc-ibb-val-processed').text(state.processed);
        state.$modal.find('.sc-ibb-val-success').text(state.success);
        state.$modal.find('.sc-ibb-val-errors').text(state.errors);
        state.$modal.find('.sc-ibb-val-remaining').text(Math.max(0, state.total - state.processed));
    }

    function startTimer() {
        state.startedAt = Date.now();
        state.timer = setInterval(function () {
            if (!state.$modal) return;
            var elapsed = (Date.now() - state.startedAt) / 1000;
            state.$modal.find('.sc-ibb-val-elapsed').text(fmtTime(elapsed));
        }, 1000);
    }

    function stopTimer() {
        if (state.timer) {
            clearInterval(state.timer);
            state.timer = null;
        }
    }

    function appendLog(entry) {
        if (!state.$modal) return;
        var $li = $('<li/>').addClass(entry.success ? 'sc-ibb-log-ok' : 'sc-ibb-log-err');
        var $idTag = $('<span/>').addClass('sc-ibb-log-id').text((i18n.idLabel || '#') + entry.id);
        var $msg = $('<span/>').addClass('sc-ibb-log-msg').text(entry.message || '');
        $li.append($idTag).append($msg);
        var $log = state.$modal.find('.sc-ibb-log');
        $log.prepend($li);
        // Keep DOM lean: cap log entries
        var $items = $log.children();
        if ($items.length > 200) {
            $items.slice(200).remove();
        }
    }

    function setStatus(text) {
        if (state.$modal) state.$modal.find('.sc-ibb-status').text(text);
    }

    function processNextBatch() {
        if (!state.running) return;

        if (state.cancelRequested) {
            finalize(true);
            return;
        }

        if (state.processed >= state.total) {
            finalize(false);
            return;
        }

        var size = parseInt(cfg.batchSize, 10) || 3;
        var batch = state.ids.slice(state.processed, state.processed + size);

        setStatus((i18n.processing || 'در حال پردازش') + '... (' + (state.processed + 1) + ' - ' + (state.processed + batch.length) + ')');

        $.ajax({
            url: cfg.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sc_invoices_bulk_process_batch',
                nonce: cfg.nonce,
                bulk_action: state.action,
                invoice_ids: batch
            },
            timeout: 120000
        }).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.results) {
                resp.data.results.forEach(function (r) {
                    state.processed++;
                    if (r.success) state.success++;
                    else state.errors++;
                    appendLog(r);
                });
                updateUI();
                // Schedule next batch (small gap so UI repaints)
                setTimeout(processNextBatch, 50);
            } else {
                // Server-side validation error: mark this batch as failed and continue
                var errMsg = (resp && resp.data && resp.data.message)
                    ? resp.data.message
                    : (i18n.networkError || 'خطا');
                batch.forEach(function (id) {
                    state.processed++;
                    state.errors++;
                    appendLog({ id: id, success: false, message: (i18n.errorPrefix || '') + errMsg });
                });
                updateUI();
                setTimeout(processNextBatch, 200);
            }
        }).fail(function () {
            // Network or transient failure: retry the same batch once after a short delay,
            // but if it keeps failing, mark and skip to keep the job moving.
            if (!state._retried) state._retried = {};
            var key = batch.join(',');
            state._retried[key] = (state._retried[key] || 0) + 1;
            if (state._retried[key] < 3) {
                setStatus(i18n.networkError || 'خطای ارتباط، تلاش مجدد...');
                setTimeout(processNextBatch, 1500);
            } else {
                batch.forEach(function (id) {
                    state.processed++;
                    state.errors++;
                    appendLog({ id: id, success: false, message: (i18n.errorPrefix || '') + (i18n.networkError || 'خطای شبکه') });
                });
                updateUI();
                setTimeout(processNextBatch, 200);
            }
        });
    }

    function finalize(cancelled) {
        state.running = false;
        stopTimer();
        if (!state.$modal) return;

        state.$modal.find('.sc-ibb-cancel').hide();
        state.$modal.find('.sc-ibb-reload').show();
        state.$modal.find('.sc-ibb-close').show();

        if (cancelled) {
            setStatus(i18n.cancelled || 'متوقف شد');
        } else if (state.errors > 0) {
            setStatus(i18n.completedWithErr || 'پایان یافت با خطا');
        } else {
            setStatus(i18n.completed || 'پایان یافت');
        }

        // Best-effort finalize log; ignore failures.
        $.ajax({
            url: cfg.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sc_invoices_bulk_finalize',
                nonce: cfg.nonce,
                bulk_action: state.action,
                success_count: state.success,
                error_count: state.errors,
                invoice_ids: state.ids
            }
        });
    }

    function startJob(action, ids) {
        state.running = true;
        state.cancelRequested = false;
        state.action = action;
        state.ids = ids.slice();
        state.total = ids.length;
        state.processed = 0;
        state.success = 0;
        state.errors = 0;
        state._retried = {};

        state.$modal = buildModal();
        state.$modal.find('.sc-ibb-action-label').text(supported[action] || action);

        // Cancel button
        state.$modal.on('click', '.sc-ibb-cancel', function () {
            state.cancelRequested = true;
            $(this).prop('disabled', true).text(i18n.cancelling || 'در حال توقف...');
        });

        // Close button (after job finished)
        state.$modal.on('click', '.sc-ibb-close', function () {
            closeModal();
        });

        // Reload table
        state.$modal.on('click', '.sc-ibb-reload', function () {
            window.location.reload();
        });

        startTimer();
        updateUI();
        processNextBatch();
    }

    function closeModal() {
        if (state.$modal) {
            state.$modal.remove();
            state.$modal = null;
        }
    }

    function showInlineNotice($form, message, type) {
        var $notice = $('<div class="notice notice-' + (type || 'warning') + ' is-dismissible sc-ibb-inline-notice"><p></p></div>');
        $notice.find('p').text(message);
        $('.wrap').first().prepend($notice);
        setTimeout(function () { $notice.fadeOut(400, function () { $(this).remove(); }); }, 4000);
    }

    /**
     * Hook: intercept the WP "Apply" buttons before WP submits the form.
     */
    $(document).on('click', '#doaction, #doaction2', function (e) {
        var $btn = $(this);
        var $form = $btn.closest('form');
        if (!$form.length) {
            $form = findListForm();
        }
        if (!$form.length) return;

        // Make sure this is the invoices list form
        if (!$form.find('input[name="page"][value="sc-invoices"]').length) return;
        if (!$form.find('input[name="invoice[]"]').length) return;

        var action = pickActionFromButton($btn, $form);
        if (!action || action === '-1') {
            return; // let WP handle (it will probably do nothing)
        }
        if (!supported.hasOwnProperty(action)) {
            return; // let the original sync handler run
        }

        var ids = getSelectedIds($form);
        if (ids.length === 0) {
            e.preventDefault();
            showInlineNotice($form, i18n.noSelection || 'انتخابی وجود ندارد');
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        // Confirm destructive actions
        if (action === 'delete') {
            if (!window.confirm(i18n.confirmDelete || 'حذف؟')) return;
        }

        startJob(action, ids);
    });

})(jQuery);
