/**
 * ویرایش سفارش ووکامرس — تاریخ شمسی روی فیلد تاریخ + یادداشت‌ها
 */
(function ($) {
    'use strict';

    function isShamsiDate(val) {
        if (!val) {
            return false;
        }
        var m = String(val).trim().match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
        if (!m) {
            return false;
        }
        var y = parseInt(m[1], 10);
        return y >= 1200 && y < 1900;
    }

    function normalizeShamsiSlashes(val) {
        return String(val || '').trim().replace(/-/g, '/');
    }

    function setupOrderDateField() {
        var $input = $('input[name="order_date"]');
        if (!$input.length) {
            return;
        }

        // غیرفعال کردن datepicker میلادی ووکامرس/jQuery UI
        if ($input.hasClass('hasDatepicker')) {
            try {
                $input.datepicker('destroy');
            } catch (e) {}
        }
        $input.removeClass('date-picker hasDatepicker');
        $input.addClass('persian-date-input sc-no-default-date');
        $input.attr('placeholder', '۱۴۰۳/۰۱/۰۱');
        $input.attr('maxlength', '10');
        $input.attr('autocomplete', 'off');

        var val = normalizeShamsiSlashes($input.val());
        if (val && isShamsiDate(val)) {
            var parts = val.split('/');
            $input.val(
                parts[0] + '/' +
                (parts[1].length === 1 ? '0' + parts[1] : parts[1]) + '/' +
                (parts[2].length === 1 ? '0' + parts[2] : parts[2])
            );
        }

        if (typeof window.initPersianDatePicker === 'function') {
            window.initPersianDatePicker();
        }
    }

    function convertNotesIfNeeded() {
        // تاریخ یادداشت‌ها از طریق فیلتر PHP date_i18n شمسی می‌شود؛
        // فقط title میلادی را برای tooltip نگه می‌داریم.
        $('.order_notes .exact-date').each(function () {
            var $el = $(this);
            var title = $el.attr('title');
            if (title && !$el.data('sc-shamsi-title')) {
                $el.attr('title', 'میلادی: ' + title);
                $el.data('sc-shamsi-title', 1);
            }
        });
    }

    $(function () {
        setupOrderDateField();
        convertNotesIfNeeded();

        // بعد از لود مجدد یادداشت‌ها با AJAX
        $(document).on('DOMNodeInserted', '#woocommerce-order-notes', function () {
            convertNotesIfNeeded();
        });
        $(document.body).on('wc_order_notes_loaded updated_checkout', function () {
            convertNotesIfNeeded();
        });
    });
})(jQuery);
