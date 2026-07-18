(function ($) {
    'use strict';

    var cfg = window.scDataCleanup || {};
    var state = {
        module: '',
        label: '',
        step: '', // otp | super_admin
        jobToken: '',
        totalHint: 0,
        totalDone: 0,
        busy: false
    };

    function confirmTwice(label, danger) {
        var first = {
            type: 'danger',
            title: 'هشدار نهایی',
            message: 'پاکسازی «' + label + '» غیرقابل‌بازگشت است. ' + (danger || 'تمام اطلاعات این بخش حذف خواهد شد.'),
            confirmText: 'متوجه شدم، ادامه',
            cancelText: 'انصراف'
        };
        var second = {
            type: 'danger',
            title: 'تأیید دوباره',
            message: 'آیا واقعاً مطمئن هستید؟ پس از این مرحله احراز هویت پیامکی شروع می‌شود و داده‌ها قابل بازیابی نخواهند بود.',
            confirmText: 'بله، ادامه بده',
            cancelText: 'انصراف'
        };
        if (typeof window.scConfirm !== 'function') {
            return Promise.resolve(window.confirm(first.message) && window.confirm(second.message));
        }
        return window.scConfirm(first).then(function (ok1) {
            if (!ok1) {
                return false;
            }
            return window.scConfirm(second);
        });
    }

    function setModalError(msg) {
        var $err = $('.sc-data-cleanup-modal__error');
        if (!msg) {
            $err.attr('hidden', 'hidden').text('');
            return;
        }
        $err.removeAttr('hidden').text(msg);
    }

    function openOtpModal(step, maskedPhone) {
        state.step = step;
        var $modal = $('#sc-data-cleanup-otp-modal');
        var title = step === 'super_admin' ? 'تأیید مدیر کل' : 'احراز هویت پیامکی';
        var hint = step === 'super_admin'
            ? 'کد تأیید برای شماره مدیر کل ارسال شد. پس از دریافت کد از مدیر کل، آن را وارد کنید.'
            : 'کد تأیید به شماره ثبت‌شده برای QR ارسال شد.';
        $('#sc-data-cleanup-modal-title').text(title);
        $('.sc-data-cleanup-modal__hint').text(hint);
        $('.sc-data-cleanup-modal__phone').text(
            maskedPhone ? ('کد به شماره ' + maskedPhone + ' ارسال شد.') : ''
        );
        $('#sc-data-cleanup-otp-input').val('');
        setModalError('');
        $modal.removeAttr('hidden').attr('aria-hidden', 'false');
        $('#sc-data-cleanup-otp-input').trigger('focus');
    }

    function closeOtpModal() {
        $('#sc-data-cleanup-otp-modal').attr('hidden', 'hidden').attr('aria-hidden', 'true');
        state.step = '';
        setModalError('');
    }

    function showProgress(message) {
        var $p = $('#sc-data-cleanup-progress');
        $p.removeAttr('hidden');
        $('.sc-data-cleanup-progress__message').text(message || 'در حال پاکسازی…');
        updateProgressBar();
    }

    function hideProgress() {
        $('#sc-data-cleanup-progress').attr('hidden', 'hidden');
    }

    function updateProgressBar() {
        var pct = 8;
        if (state.totalHint > 0) {
            pct = Math.min(95, Math.round((state.totalDone / state.totalHint) * 100));
        } else if (state.totalDone > 0) {
            pct = 60;
        }
        $('.sc-data-cleanup-progress__track > span').css('width', pct + '%');
        $('.sc-data-cleanup-progress__count').text(
            'پردازش‌شده: ' + state.totalDone + (state.totalHint ? (' از حدود ' + state.totalHint) : '')
        );
    }

    function post(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = cfg.nonce;
        return $.post(cfg.ajaxUrl, data);
    }

    function requestOtp() {
        return post('sc_data_cleanup_request_otp', { module: state.module });
    }

    function verifyOtp(code) {
        return post('sc_data_cleanup_verify_otp', { otp_code: code });
    }

    function verifySuperAdmin(code) {
        return post('sc_data_cleanup_verify_super_admin', { otp_code: code });
    }

    function processBatch() {
        return post('sc_data_cleanup_process_batch', { job_token: state.jobToken });
    }

    function runBatches() {
        showProgress('پاکسازی «' + state.label + '» در حال اجراست…');
        processBatch().done(function (res) {
            if (!res || !res.success) {
                hideProgress();
                window.alert((res && res.data && res.data.message) ? res.data.message : 'خطا در پاکسازی');
                state.busy = false;
                return;
            }
            var data = res.data || {};
            state.totalDone = data.total_done || state.totalDone;
            if (data.message) {
                $('.sc-data-cleanup-progress__message').text(data.message);
            }
            updateProgressBar();
            if (data.done) {
                $('.sc-data-cleanup-progress__track > span').css('width', '100%');
                $('.sc-data-cleanup-progress__message').text(data.message || 'پاکسازی با موفقیت انجام شد.');
                setTimeout(function () {
                    hideProgress();
                    window.location.reload();
                }, 900);
                return;
            }
            setTimeout(runBatches, 120);
        }).fail(function () {
            hideProgress();
            window.alert('خطا در ارتباط با سرور');
            state.busy = false;
        });
    }

    function startCleanupFlow($btn) {
        if (state.busy) {
            return;
        }
        state.module = $btn.data('module') || '';
        state.label = $btn.data('label') || '';
        var danger = $btn.data('danger') || '';
        if (!state.module) {
            return;
        }
        confirmTwice(state.label, danger).then(function (ok) {
            if (!ok) {
                return;
            }
            state.busy = true;
            $btn.prop('disabled', true);
            requestOtp().done(function (res) {
                if (res && res.success) {
                    openOtpModal('otp', res.data && res.data.masked_phone ? res.data.masked_phone : '');
                } else {
                    window.alert((res && res.data && res.data.message) ? res.data.message : 'خطا در ارسال کد');
                    state.busy = false;
                }
            }).fail(function () {
                window.alert('خطا در ارتباط با سرور');
                state.busy = false;
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    $(document).on('click', '.sc-data-cleanup__button', function () {
        startCleanupFlow($(this));
    });

    $(document).on('click', '.sc-data-cleanup-modal__cancel, .sc-data-cleanup-modal__backdrop, .sc-data-cleanup-modal__close', function () {
        if (state.jobToken) {
            return;
        }
        closeOtpModal();
        state.busy = false;
    });

    $(document).on('click', '.sc-data-cleanup-modal__confirm', function () {
        var code = ($('#sc-data-cleanup-otp-input').val() || '').trim();
        if (!code) {
            setModalError('کد تأیید را وارد کنید.');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        setModalError('');
        var req = state.step === 'super_admin' ? verifySuperAdmin(code) : verifyOtp(code);
        req.done(function (res) {
            if (!(res && res.success)) {
                setModalError((res && res.data && res.data.message) ? res.data.message : 'کد نامعتبر است.');
                return;
            }
            if (state.step === 'otp') {
                openOtpModal('super_admin', res.data && res.data.masked_phone ? res.data.masked_phone : '');
                return;
            }
            closeOtpModal();
            state.jobToken = res.data.job_token || '';
            state.totalHint = parseInt(res.data.total_hint, 10) || 0;
            state.totalDone = 0;
            if (!state.jobToken) {
                window.alert('توکن عملیات ایجاد نشد.');
                state.busy = false;
                return;
            }
            runBatches();
        }).fail(function () {
            setModalError('خطا در ارتباط با سرور');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('keydown', '#sc-data-cleanup-otp-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('.sc-data-cleanup-modal__confirm').trigger('click');
        }
    });
})(jQuery);
