(function ($) {
    'use strict';

    function getRoleSelect() {
        return $('#role, select[name="role"]');
    }

    function toggleSecretaryChapters() {
        var $wrap = $('#sc-secretary-chapters-wrap');
        if (!$wrap.length) {
            return;
        }
        var role = getRoleSelect().val();
        if (role === 'secretary') {
            $wrap.show();
        } else {
            $wrap.hide();
        }
    }

    $(document).ready(function () {
        toggleSecretaryChapters();
        getRoleSelect().on('change', toggleSecretaryChapters);
    });
})(jQuery);
