(function ($) {
    'use strict';

    var selectedMemberIds = [];
    var excludedMemberIds = [];

    function toggleFilterBlocks() {
        var targetType = $('#sc-invoice-target-type').val();
        $('#sc-invoice-filter-specific, #sc-invoice-filter-course, #sc-invoice-filter-event, #sc-invoice-filter-team, #sc-invoice-filter-level').hide();
        if (targetType === 'specific') {
            $('#sc-invoice-filter-specific').show();
        } else if (targetType === 'course') {
            $('#sc-invoice-filter-course').show();
        } else if (targetType === 'event') {
            $('#sc-invoice-filter-event').show();
        } else if (targetType === 'team') {
            $('#sc-invoice-filter-team').show();
        } else if (targetType === 'level') {
            $('#sc-invoice-filter-level').show();
        } else if (targetType === 'team_level') {
            $('#sc-invoice-filter-team, #sc-invoice-filter-level').show();
        }
    }

    function renderSelectedMembers() {
        var $tags = $('#sc-invoice-selected-members');
        var $inputs = $('#sc-invoice-member-hidden-inputs');
        var $count = $('#sc-invoice-selected-members-count');
        $tags.empty();
        $inputs.empty();
        $count.text(selectedMemberIds.length + ' کاربر انتخاب شده');

        if (!selectedMemberIds.length) {
            $tags.html('<em>هنوز کاربری انتخاب نشده است.</em>');
            return;
        }

        selectedMemberIds.forEach(function (item) {
            $tags.append('<span class="sc-tag" data-id="' + item.id + '">' + item.label + ' <button type="button" class="sc-remove-tag">&times;</button></span>');
            $inputs.append('<input type="hidden" name="member_ids[]" value="' + item.id + '">');
        });
    }

    function addMember(id, label) {
        var exists = selectedMemberIds.some(function (item) {
            return item.id === id;
        });
        if (exists) {
            return;
        }
        selectedMemberIds.push({ id: id, label: label });
        renderSelectedMembers();
    }

    function bindSearchableDropdown() {
        var $dropdown = $('#sc-invoice-member-dropdown');
        var $toggle = $dropdown.find('.sc-users-dropdown-toggle');
        var $menu = $dropdown.find('.sc-users-dropdown-menu');
        var $search = $dropdown.find('.sc-users-search-input');

        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $menu.slideToggle(150);
            setTimeout(function () {
                $search.trigger('focus');
            }, 200);
        });

        $search.on('input', function () {
            var term = ($(this).val() || '').toLowerCase().trim();
            $('#sc-invoice-member-options .sc-users-dropdown-option').each(function () {
                var text = ($(this).attr('data-search') || '').toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        $(document).on('click', '#sc-invoice-member-options .sc-users-dropdown-option', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).attr('data-id'), 10);
            var label = $(this).attr('data-label') || '';
            if (id) {
                addMember(id, label);
            }
            $search.val('');
            $('#sc-invoice-member-options .sc-users-dropdown-option').show();
        });

        $(document).on('click', '#sc-invoice-selected-members .sc-remove-tag', function () {
            var $tag = $(this).closest('.sc-tag');
            var id = parseInt($tag.attr('data-id'), 10);
            selectedMemberIds = selectedMemberIds.filter(function (item) {
                return item.id !== id;
            });
            renderSelectedMembers();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#sc-invoice-member-dropdown').length) {
                $menu.slideUp(150);
            }
        });
    }

    function buildPreviewPayload() {
        var form = $('#sc-invoice-add-form');
        var data = form.serializeArray();
        data.push({ name: 'action', value: 'sc_invoice_members_preview' });
        data.push({ name: 'nonce', value: (window.scInvoiceAdd && scInvoiceAdd.nonce) ? scInvoiceAdd.nonce : '' });
        return data;
    }

    function syncExcludedMemberInputs() {
        var $inputs = $('#sc-invoice-excluded-members-inputs');
        if (!$inputs.length) {
            return;
        }
        $inputs.empty();
        excludedMemberIds.forEach(function (id) {
            $inputs.append('<input type="hidden" name="excluded_member_ids[]" value="' + id + '">');
        });
    }

    function initPreviewSelectionBindings() {
        var $checks = $('.sc-invoice-preview-member-check');
        if (!$checks.length) {
            return;
        }

        $checks.each(function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            if (id && excludedMemberIds.indexOf(id) !== -1) {
                $(this).prop('checked', false);
            }
        });

        var checkedCount = $('.sc-invoice-preview-member-check:checked').length;
        $('#sc-invoice-preview-select-all').prop('checked', checkedCount === $checks.length);

        $(document).off('change.scInvoicePreviewMember').on('change.scInvoicePreviewMember', '.sc-invoice-preview-member-check', function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            if (!id) {
                return;
            }
            if ($(this).is(':checked')) {
                excludedMemberIds = excludedMemberIds.filter(function (x) { return x !== id; });
            } else if (excludedMemberIds.indexOf(id) === -1) {
                excludedMemberIds.push(id);
            }
            syncExcludedMemberInputs();

            var allCount = $('.sc-invoice-preview-member-check').length;
            var selectedCount = $('.sc-invoice-preview-member-check:checked').length;
            $('#sc-invoice-preview-select-all').prop('checked', allCount > 0 && selectedCount === allCount);
        });

        $(document).off('change.scInvoicePreviewSelectAll').on('change.scInvoicePreviewSelectAll', '#sc-invoice-preview-select-all', function () {
            var shouldCheck = $(this).is(':checked');
            $('.sc-invoice-preview-member-check').prop('checked', shouldCheck).trigger('change');
        });

        syncExcludedMemberInputs();
    }

    function validateBeforeSubmit() {
        var hasRows = $('.sc-invoice-preview-member-check').length > 0;
        if (!hasRows) {
            alert('ابتدا پیش نمایش کاربران را دریافت کنید.');
            return false;
        }
        if ($('.sc-invoice-preview-member-check:checked').length === 0) {
            alert('حداقل یک کاربر را در پیش نمایش انتخاب کنید.');
            return false;
        }
        return true;
    }

    $(document).ready(function () {
        toggleFilterBlocks();
        renderSelectedMembers();
        bindSearchableDropdown();
        syncExcludedMemberInputs();

        $('#sc-invoice-target-type').on('change', toggleFilterBlocks);

        $('#sc-invoice-preview-btn').on('click', function () {
            var $btn = $(this);
            var $result = $('#sc-invoice-preview-result');
            $btn.prop('disabled', true);
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');

            $.post(
                (window.scInvoiceAdd && scInvoiceAdd.ajaxurl) ? scInvoiceAdd.ajaxurl : ajaxurl,
                buildPreviewPayload()
            ).done(function (res) {
                if (res && res.success && res.data) {
                    $result.html(res.data.html || '');
                    initPreviewSelectionBindings();
                } else {
                    $result.html('<p class="description">خطا در دریافت پیش نمایش.</p>');
                }
            }).fail(function () {
                $result.html('<p class="description">خطا در ارتباط با سرور.</p>');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $('#sc-invoice-add-form').on('submit', function (e) {
            if (!validateBeforeSubmit()) {
                e.preventDefault();
            }
        });
    });
})(jQuery);
