(function ($) {
    'use strict';

    var cfg = window.scAttendanceQrAdmin || {};
    var pendingMemberId = 0;
    var pendingNonce = '';

    function showMessage(msg, isError) {
        if (window.alert) {
            window.alert(msg);
        }
    }

    function refreshQrCard(card) {
        if (!card) {
            window.location.reload();
            return;
        }
        var $wrap = $('.sc-attendance-qr-admin-card');
        if (!$wrap.length) {
            window.location.reload();
            return;
        }
        var img = $wrap.find('.sc-attendance-qr-admin-preview--active img').first();
        if (img.length && card.image_url) {
            img.attr('src', card.image_url + (card.image_url.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now());
        }
        var shortCode = $wrap.find('.sc-attendance-qr-admin-preview--active .sc-attendance-qr-short-code').first();
        if (shortCode.length && card.short_code) {
            shortCode.text(card.short_code);
        }
        var dl = $wrap.find('.sc-attendance-qr-admin-preview__actions a[download]').first();
        if (dl.length && card.download_url) {
            dl.attr('href', card.download_url);
        }
        window.location.reload();
    }

    function openOtpModal(maskedPhone) {
        var $modal = $('#sc-qr-otp-modal');
        if (!$modal.length) {
            return;
        }
        $modal.removeAttr('hidden').attr('aria-hidden', 'false');
        $('#sc-qr-otp-input').val('').trigger('focus');
        var $phone = $modal.find('.sc-qr-otp-modal__phone');
        if (maskedPhone) {
            $phone.text('کد به شماره ' + maskedPhone + ' ارسال شد.').removeAttr('hidden');
        } else {
            $phone.attr('hidden', 'hidden').text('');
        }
    }

    function closeOtpModal() {
        var $modal = $('#sc-qr-otp-modal');
        $modal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
        pendingMemberId = 0;
        pendingNonce = '';
    }

    function requestOtp(memberId, nonce) {
        pendingMemberId = memberId;
        pendingNonce = nonce;
        return $.post(cfg.ajaxUrl, {
            action: 'sc_attendance_qr_request_regenerate_otp',
            nonce: cfg.adminNonce
        });
    }

    function confirmRegenerate(otpCode) {
        return $.post(cfg.ajaxUrl, {
            action: 'sc_attendance_qr_regenerate',
            member_id: pendingMemberId,
            otp_code: otpCode,
            nonce: pendingNonce
        });
    }

    $(document).on('click', '.sc-regenerate-member-qr', function () {
        var $btn = $(this);
        var memberId = parseInt($btn.data('member-id'), 10) || 0;
        var nonce = $btn.data('nonce') || '';
        if (!memberId || !nonce) {
            return;
        }
        $btn.prop('disabled', true);
        requestOtp(memberId, nonce).done(function (res) {
            if (res && res.success) {
                openOtpModal(res.data && res.data.masked_phone ? res.data.masked_phone : '');
            } else {
                showMessage((res && res.data && res.data.message) ? res.data.message : 'خطا در ارسال کد تأیید', true);
            }
        }).fail(function () {
            showMessage('خطا در ارتباط با سرور', true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.sc-qr-otp-modal__cancel, .sc-qr-otp-modal__backdrop', function () {
        closeOtpModal();
    });

    $(document).on('click', '.sc-qr-otp-modal__confirm', function () {
        var otp = ($('#sc-qr-otp-input').val() || '').trim();
        if (!otp) {
            showMessage('کد تأیید را وارد کنید.', true);
            return;
        }
        var $btn = $(this).prop('disabled', true);
        confirmRegenerate(otp).done(function (res) {
            if (res && res.success) {
                closeOtpModal();
                showMessage(res.data.message || 'QR جدید تولید شد.');
                refreshQrCard(res.data);
            } else {
                showMessage((res && res.data && res.data.message) ? res.data.message : 'خطا در تولید QR', true);
            }
        }).fail(function () {
            showMessage('خطا در ارتباط با سرور', true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.sc-qr-set-active', function () {
        var $btn = $(this);
        var qrId = parseInt($btn.data('qr-id'), 10) || 0;
        var memberId = parseInt($btn.data('member-id'), 10) || 0;
        if (!qrId || !memberId) {
            return;
        }
        $btn.prop('disabled', true);
        $.post(cfg.ajaxUrl, {
            action: 'sc_attendance_qr_set_active',
            qr_id: qrId,
            member_id: memberId,
            nonce: cfg.adminNonce
        }).done(function (res) {
            if (res && res.success) {
                showMessage(res.data.message || 'QR فعال شد.');
                refreshQrCard(res.data.card || null);
            } else {
                showMessage((res && res.data && res.data.message) ? res.data.message : 'خطا', true);
            }
        }).fail(function () {
            showMessage('خطا در ارتباط با سرور', true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.sc-qr-toggle-disabled', function () {
        var $btn = $(this);
        var qrId = parseInt($btn.data('qr-id'), 10) || 0;
        var memberId = parseInt($btn.data('member-id'), 10) || 0;
        var toggleAction = $btn.data('action') || 'disable';
        if (!qrId || !memberId) {
            return;
        }
        $btn.prop('disabled', true);
        $.post(cfg.ajaxUrl, {
            action: 'sc_attendance_qr_toggle_disabled',
            qr_id: qrId,
            member_id: memberId,
            toggle_action: toggleAction,
            nonce: cfg.adminNonce
        }).done(function (res) {
            if (res && res.success) {
                showMessage(res.data.message || 'انجام شد.');
                refreshQrCard(res.data.card || null);
            } else {
                showMessage((res && res.data && res.data.message) ? res.data.message : 'خطا', true);
            }
        }).fail(function () {
            showMessage('خطا در ارتباط با سرور', true);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
})(jQuery);
