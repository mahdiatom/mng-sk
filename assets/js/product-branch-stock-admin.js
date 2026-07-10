(function ($) {
    'use strict';

    function syncEnableState($box) {
        var on = $box.find('.sc-pbs-enable').is(':checked');
        $box.toggleClass('is-disabled', !on);
        $box.find('.sc-pbs-admin-rows input').prop('disabled', !on);
        // فیلد enable و hidden مربوط به خودش نباید disable شود
        $box.find('.sc-pbs-enable').prop('disabled', false);
        $box.find('input[type="hidden"][name$="_enable"], input[type="hidden"][name="sc_branch_stock_enable"]').prop('disabled', false);
        // hiddenهای chapter باید همیشه ارسال شوند وقتی enable روشن است
        if (!on) {
            $box.find('.sc-pbs-chapter-on').prop('checked', false);
        }
    }

    $(document).on('change', '.sc-pbs-enable', function () {
        syncEnableState($(this).closest('.sc-pbs-admin'));
    });

    $(function () {
        $('.sc-pbs-admin').each(function () {
            syncEnableState($(this));
        });
    });

    // بعد از لود variationهای ووکامرس
    $(document).on('woocommerce_variations_loaded woocommerce_variations_added', function () {
        $('.sc-pbs-admin').each(function () {
            syncEnableState($(this));
        });
    });
})(jQuery);
