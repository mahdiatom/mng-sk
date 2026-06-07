(function ($) {
    'use strict';

    var cfg = window.scBaleBot || {};

    function showStatus($el, message, isOk) {
        $el.removeClass('is-ok is-error')
            .addClass(isOk ? 'is-ok' : 'is-error')
            .text(message)
            .show();
    }

    function ajaxAction(action, $status) {
        $status.text('در حال پردازش...').show();
        $.post(cfg.ajaxurl, {
            action: action,
            nonce: cfg.nonce
        }).done(function (res) {
            if (res.success) {
                showStatus($status, res.data.message || 'انجام شد.', true);
                if (action === 'sc_bale_set_webhook' || action === 'sc_bale_get_webhook_info') {
                    $('#sc-bale-refresh-webhook').trigger('click');
                }
            } else {
                showStatus($status, (res.data && res.data.message) || 'خطا رخ داد.', false);
            }
        }).fail(function () {
            showStatus($status, 'خطا در ارتباط با سرور.', false);
        });
    }

    $(function () {
        var $webhookStatus = $('#sc-bale-webhook-status');

        $('#sc-bale-set-webhook').on('click', function (e) {
            e.preventDefault();
            ajaxAction('sc_bale_set_webhook', $webhookStatus);
        });

        $('#sc-bale-delete-webhook').on('click', function (e) {
            e.preventDefault();
            if (!window.confirm('وب‌هوک حذف شود؟')) return;
            ajaxAction('sc_bale_delete_webhook', $webhookStatus);
        });

        $('#sc-bale-refresh-webhook').on('click', function (e) {
            e.preventDefault();
            ajaxAction('sc_bale_get_webhook_info', $webhookStatus);
        });

    });
})(jQuery);
