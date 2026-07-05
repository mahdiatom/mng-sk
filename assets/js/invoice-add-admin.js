(function ($) {
    'use strict';

    var selectedMemberIds = [];
    var selectedMemberLabels = {};
    var excludedMemberIds = [];
    var excludedMemberLabels = {};

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
        $tags.empty();
        $inputs.empty();

        if (!selectedMemberIds.length) {
            $tags.html('<em style="color:#999;">هنوز کسی انتخاب نشده</em>');
            return;
        }

        selectedMemberIds.forEach(function (id) {
            var label = selectedMemberLabels[id] || ('کاربر #' + id);
            $tags.append('<span class="recipient-tag" data-id="' + id + '">' + label + ' <button type="button" class="recipient-remove">&times;</button></span> ');
            $inputs.append('<input type="hidden" name="member_ids[]" value="' + id + '">');
        });
    }

    function addMember(id, label) {
        if (selectedMemberIds.indexOf(id) !== -1) {
            return;
        }
        selectedMemberIds.push(id);
        selectedMemberLabels[id] = label;
        renderSelectedMembers();
    }

    function renderExcludedMembers() {
        var $tags = $('#sc-invoice-exclude-members');
        $tags.empty();

        if (!excludedMemberIds.length) {
            $tags.html('<em style="color:#999;">هیچ استثنایی ثبت نشده</em>');
            return;
        }

        excludedMemberIds.forEach(function (id) {
            var label = excludedMemberLabels[id] || selectedMemberLabels[id] || ('کاربر #' + id);
            $tags.append('<span class="recipient-tag" data-id="' + id + '">' + label + ' <button type="button" class="exclude-recipient-remove">&times;</button></span> ');
        });
    }

    function bindSearchableDropdown() {
        $(document).on('input', '#sc-invoice-member-dropdown .sc-search-input, #sc-invoice-exclude-dropdown .sc-search-input', function () {
            var $input = $(this);
            var term = ($input.val() || '').toLowerCase().trim();
            var $root = $input.closest('.sc-searchable-dropdown');
            $root.find('.sc-dropdown-option').each(function () {
                var text = (String($(this).attr('data-search') || '')).toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        // از capture استفاده می‌کنیم چون admin.js در .sc-dropdown-menu جلوی bubble را می‌گیرد.
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) {
                return;
            }
            var opt = e.target.closest('.sc-dropdown-option');
            if (!opt || !jQuery(opt).length) {
                return;
            }
            var $opt = jQuery(opt);
            var isMemberDropdown = $opt.closest('#sc-invoice-member-dropdown').length > 0;
            var isExcludeDropdown = $opt.closest('#sc-invoice-exclude-dropdown').length > 0;
            if (!isMemberDropdown && !isExcludeDropdown) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            var id = parseInt($opt.attr('data-id'), 10);
            var label = $opt.attr('data-label') || $opt.text().trim();
            if (id > 0) {
                if (isExcludeDropdown) {
                    if (excludedMemberIds.indexOf(id) === -1) {
                        excludedMemberIds.push(id);
                        excludedMemberLabels[id] = label;
                        syncExcludedMemberInputs();
                        renderExcludedMembers();
                    }
                } else {
                    addMember(id, label);
                }
            }

            var $menu = $opt.closest('.sc-dropdown-menu');
            $menu.slideUp(150);
            $menu.find('.sc-search-input').val('').trigger('input');
        }, true);

        $(document).on('click', '#sc-invoice-selected-members .recipient-remove', function () {
            var $tag = $(this).closest('.recipient-tag');
            var id = parseInt($tag.attr('data-id'), 10);
            selectedMemberIds = selectedMemberIds.filter(function (x) {
                return x !== id;
            });
            renderSelectedMembers();
        });

        $(document).on('click', '#sc-invoice-exclude-members .exclude-recipient-remove', function () {
            var $tag = $(this).closest('.recipient-tag');
            var id = parseInt($tag.attr('data-id'), 10);
            excludedMemberIds = excludedMemberIds.filter(function (x) {
                return x !== id;
            });
            syncExcludedMemberInputs();
            renderExcludedMembers();
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
                if (!excludedMemberLabels[id]) {
                    var rowText = $(this).closest('tr').find('td').eq(1).text().trim();
                    excludedMemberLabels[id] = rowText || ('کاربر #' + id);
                }
            }
            syncExcludedMemberInputs();
            renderExcludedMembers();

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
        renderExcludedMembers();
        bindSearchableDropdown();
        syncExcludedMemberInputs();

        $('#sc-invoice-target-type').on('change', toggleFilterBlocks);

        $('#sc-invoice-preview-btn').on('click', function () {
            var $btn = $(this);
            var $result = $('#sc-invoice-preview-result');
            $btn.prop('disabled', true);
            if (window.scAudiencePreviewAdd) {
                window.scAudiencePreviewAdd.reset('#sc-invoice-preview-result');
            }
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');

            $.post(
                (window.scInvoiceAdd && scInvoiceAdd.ajaxurl) ? scInvoiceAdd.ajaxurl : ajaxurl,
                buildPreviewPayload()
            ).done(function (res) {
                if (res && res.success && res.data) {
                    $result.html(res.data.html || '');
                    initPreviewSelectionBindings();
                    if (window.scAudiencePreviewAdd) {
                        window.scAudiencePreviewAdd.show('#sc-invoice-preview-result');
                    }
                } else {
                    $result.html('<p class="description">خطا در دریافت پیش نمایش.</p>');
                }
            }).fail(function () {
                $result.html('<p class="description">خطا در ارتباط با سرور.</p>');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $(document).on('sc-audience-preview-member-added', function (e, payload) {
            if (payload && payload.mode === 'invoice') {
                initPreviewSelectionBindings();
            }
        });

        $('#sc-invoice-add-form').on('submit', function (e) {
            if (!validateBeforeSubmit()) {
                e.preventDefault();
            }
        });
    });
})(jQuery);
