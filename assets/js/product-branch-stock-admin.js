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

    /**
     * برای محصول متغیر تب فقط نمایشی است؛ فیلدهای ساده نباید submit شوند.
     */
    function syncProductTypeFields() {
        var type = $('#product-type').val();
        var isVariable = (type === 'variable');
        var $simple = $('#sc_branch_stock_product_data .show_if_simple');
        var $variable = $('#sc_branch_stock_product_data .show_if_variable');

        $simple.find('input, select, textarea').prop('disabled', isVariable);
        if (!isVariable) {
            $simple.find('.sc-pbs-admin').each(function () {
                syncEnableState($(this));
            });
        }
        $variable.find('input, select, textarea').prop('disabled', false);
    }

    $(document).on('change', '.sc-pbs-enable', function () {
        syncEnableState($(this).closest('.sc-pbs-admin'));
    });

    $(document).on('change', '#product-type', syncProductTypeFields);

    $(function () {
        $('.sc-pbs-admin').each(function () {
            syncEnableState($(this));
        });
        syncProductTypeFields();
    });

    // بعد از لود variationهای ووکامرس
    $(document).on('woocommerce_variations_loaded woocommerce_variations_added', function () {
        $('.sc-pbs-admin').each(function () {
            syncEnableState($(this));
        });
        syncProductTypeFields();
    });
})(jQuery);
