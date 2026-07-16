/**
 * Specialized programs — player portal
 */
(function ($) {
    'use strict';

    var cfg = window.scProgramsPublic || {};
    var dayCache = {};

    function alertBox(opts) {
        opts = opts || {};
        if (typeof window.scConfirm === 'function') {
            return window.scConfirm({
                type: opts.type || 'info',
                title: opts.title || 'توجه',
                message: opts.message || '',
                confirmText: opts.confirmText || 'متوجه شدم',
                hideCancel: true
            });
        }
        window.alert(opts.message || '');
        return $.Deferred().resolve(true).promise();
    }

    function setProgress($card, progress) {
        if (!$card || !progress) {
            return;
        }
        var pct = parseInt(progress.percent, 10) || 0;
        var done = parseInt(progress.done, 10) || 0;
        var total = parseInt(progress.total, 10) || 0;
        $card.find('.sc-program-progress-bar > span').css('width', pct + '%');
        $card.find('.sc-program-progress-pct').text(pct + '٪');
        $card.find('.sc-program-progress-meta').text(done + ' از ' + total);

        var $enc = $card.find('.sc-my-progress-encourage');
        if (total > 0 && pct >= 100) {
            $enc.addClass('is-complete').text('🌟 عالی بود! نوار پر شد — به خودتان افتخار کنید و برای برنامه بعدی آماده باشید.');
            if (!$card.find('.sc-my-congrats').length) {
                $card.prepend(
                    '<div class="sc-my-congrats is-pop" role="status">' +
                    '<span class="sc-my-congrats__burst" aria-hidden="true">🎉</span>' +
                    '<div><strong>آفرین! این برنامه را کامل کردید</strong>' +
                    '<p>همه تمرین‌های تا امروز انجام شده‌اند. همین نظم، مسیر قهرمانی شماست — ادامه بدهید.</p></div></div>'
                );
            }
        } else if (pct >= 70) {
            $enc.removeClass('is-complete').text('💪 خیلی نزدیکید! چند تیک دیگر تا تکمیل امروز فاصله دارید.');
        } else if (pct >= 30) {
            $enc.removeClass('is-complete').text('🔥 خوب پیش می‌روید — هر تیک، یک قدم محکم‌تر.');
        } else {
            $enc.removeClass('is-complete').text('✨ شروع کنید؛ همین امروز می‌تواند بهترین روز تمرینتان باشد.');
        }
    }

    function post(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = cfg.nonce || '';
        return $.post(cfg.ajaxurl || window.ajaxurl || '', data);
    }

    function loadProgram(programId) {
        var $stage = $('#sc-my-program-stage');
        $stage.addClass('is-loading').html('<div class="sc-my-loading">در حال بارگذاری برنامه…</div>');
        dayCache = {};
        return post('sc_program_player_get_program', { program_id: programId }).done(function (res) {
            if (res && res.success && res.data && res.data.html) {
                $stage.html(res.data.html);
            } else {
                $stage.html('<div class="sc-prog-empty-hero sc-prog-empty-hero--soft"><p>' +
                    ((res && res.data && res.data.message) || 'بارگذاری ناموفق بود.') + '</p></div>');
            }
        }).fail(function () {
            $stage.html('<div class="sc-prog-empty-hero sc-prog-empty-hero--soft"><p>خطا در ارتباط با سرور</p></div>');
        }).always(function () {
            $stage.removeClass('is-loading');
        });
    }

    function loadDay($card, dayYmd, dayId) {
        var programId = parseInt($card.data('program-id'), 10) || 0;
        var cacheKey = programId + ':' + dayYmd;
        var $panels = $card.find('.sc-my-day-panels');

        $card.find('.sc-my-day-chip').removeClass('is-active').attr('aria-selected', 'false');
        $card.find('.sc-my-day-chip[data-day="' + dayYmd + '"]').addClass('is-active').attr('aria-selected', 'true');
        $card.attr('data-active-day', dayYmd);

        if (dayCache[cacheKey]) {
            $panels.html(dayCache[cacheKey]);
            return;
        }

        $panels.html('<div class="sc-my-loading">در حال بارگذاری روز…</div>');
        post('sc_program_player_get_day', {
            program_id: programId,
            day: dayYmd,
            day_id: dayId || 0
        }).done(function (res) {
            if (res && res.success && res.data && res.data.html) {
                dayCache[cacheKey] = res.data.html;
                $panels.html(res.data.html);
            } else {
                $panels.html('<p class="sc-my-day-banner sc-my-day-banner--past">' +
                    ((res && res.data && res.data.message) || 'روز بارگذاری نشد.') + '</p>');
            }
        }).fail(function () {
            $panels.html('<p class="sc-my-day-banner sc-my-day-banner--past">خطا در بارگذاری روز</p>');
        });
    }

    // Program selector
    $(document).on('click', '.sc-my-program-pill', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if (String($btn.data('selectable')) === '0' || $btn.hasClass('is-disabled')) {
            alertBox({
                type: 'warning',
                title: 'برنامه قابل انتخاب نیست',
                message: 'این برنامه فعلاً غیرفعال است و نمی‌توانید آن را باز کنید. در صورت نیاز با مربی یا باشگاه هماهنگ کنید.'
            });
            return;
        }
        var id = parseInt($btn.data('program'), 10) || 0;
        if (!id) {
            return;
        }
        $('.sc-my-program-pill').removeClass('is-active').attr('aria-selected', 'false');
        $btn.addClass('is-active').attr('aria-selected', 'true');
        loadProgram(id);
    });

    // Day chips
    $(document).on('click', '.sc-my-day-chip', function (e) {
        e.preventDefault();
        var $chip = $(this);
        var $card = $chip.closest('.sc-my-program-card');
        var day = String($chip.data('day') || '');
        var dayId = parseInt($chip.data('day-id'), 10) || 0;
        if (!day || !$card.length) {
            return;
        }
        if ($chip.hasClass('is-active') && $card.find('.sc-my-program-day-panel.is-loaded').length) {
            return;
        }
        loadDay($card, day, dayId);
    });

    // Locked tick click
    $(document).on('click', '.sc-my-program-item.is-locked .sc-my-tick, .sc-my-program-check:disabled', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $panel = $(this).closest('.sc-my-program-day-panel');
        var day = String($panel.data('day') || '');
        var today = String($('.sc-my-programs-wrap').data('today') || '');
        var msg;
        if (day && today && day < today) {
            msg = 'این روز مربوط به گذشته است. فقط می‌توانید وضعیت را ببینید؛ تغییر تیک فقط برای امروز امکان‌پذیر است.';
        } else if (day && today && day > today) {
            msg = 'هنوز به این روز نرسیده‌اید. برنامه را مرور کنید؛ ثبت انجام از امروز به‌وقت باشگاه فعال می‌شود.';
        } else {
            msg = 'ثبت یا برداشتن تیک فقط برای تمرین‌های امروز فعال است.';
        }
        alertBox({
            type: 'warning',
            title: 'تغییر تیک ممکن نیست',
            message: msg
        });
        return false;
    });

    // Toggle completion
    $(document).on('change', '.sc-my-program-check', function () {
        var $cb = $(this);
        if ($cb.prop('disabled')) {
            return;
        }
        var itemId = $cb.data('item-id');
        var done = $cb.is(':checked') ? 1 : 0;
        var $card = $cb.closest('.sc-my-program-card');
        var $item = $cb.closest('.sc-my-program-item');
        $cb.prop('disabled', true);
        $item.toggleClass('is-done', !!done);
        post('sc_program_toggle_completion', {
            item_id: itemId,
            done: done
        }).done(function (res) {
            if (res && res.success) {
                setProgress($card, res.data.progress);
                $item.toggleClass('is-done', !!(res.data && res.data.done));
                // invalidate day cache for re-render consistency
                var pid = $card.data('program-id');
                var day = $card.attr('data-active-day');
                delete dayCache[pid + ':' + day];
            } else {
                $cb.prop('checked', !done);
                $item.toggleClass('is-done', !done);
                alertBox({
                    type: 'danger',
                    title: 'ثبت نشد',
                    message: (res && res.data && res.data.message) || 'خطا در ثبت تیک'
                });
            }
        }).fail(function () {
            $cb.prop('checked', !done);
            $item.toggleClass('is-done', !done);
            alertBox({ type: 'danger', title: 'خطا', message: 'ارتباط با سرور برقرار نشد.' });
        }).always(function () {
            if (!$item.hasClass('is-locked')) {
                $cb.prop('disabled', false);
            }
        });
    });

    // Media accordion (AJAX)
    $(document).on('click', '.sc-my-media-toggle', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var itemId = parseInt($btn.data('item-id'), 10) || 0;
        var $panel = $btn.siblings('.sc-my-media-panel[data-item-id="' + itemId + '"]');
        if (!$panel.length) {
            return;
        }
        var open = $panel.prop('hidden');
        if (!open) {
            $panel.prop('hidden', true);
            $btn.attr('aria-expanded', 'false').text('جزئیات و رسانه');
            return;
        }
        $panel.prop('hidden', false);
        $btn.attr('aria-expanded', 'true').text('بستن جزئیات');
        if ($panel.data('loaded')) {
            return;
        }
        $panel.find('.sc-my-media-panel__loading').show();
        $panel.find('.sc-my-media-panel__content').empty();
        post('sc_program_player_get_item_media', { item_id: itemId }).done(function (res) {
            if (res && res.success && res.data && res.data.html) {
                $panel.find('.sc-my-media-panel__content').html(res.data.html);
                $panel.data('loaded', true);
            } else {
                $panel.find('.sc-my-media-panel__content').html(
                    '<p class="sc-my-media-empty">' + ((res && res.data && res.data.message) || 'بارگذاری ناموفق') + '</p>'
                );
            }
        }).fail(function () {
            $panel.find('.sc-my-media-panel__content').html('<p class="sc-my-media-empty">خطا در بارگذاری رسانه</p>');
        }).always(function () {
            $panel.find('.sc-my-media-panel__loading').hide();
        });
    });
})(jQuery);
