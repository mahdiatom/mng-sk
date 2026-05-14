(function ($) {
    'use strict';

    var selectedMemberIds = [];
    var excludedMemberIds = [];

    function toggleFilterBlocks() {
        var targetType = $('#sc-target-type').val();
        $('.sc-filter-block').hide();
        if (targetType === 'team_level') {
            $('#sc-filter-team, #sc-filter-level').show();
        } else {
            $('#sc-filter-' + targetType).show();
        }
    }

    function renderSelectedMembers() {
        var $tags = $('#sc-selected-members');
        var $inputs = $('#sc-member-hidden-inputs');
        var $count = $('#sc-selected-members-count');
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
        var exists = selectedMemberIds.some(function (item) { return item.id === id; });
        if (exists) return;
        selectedMemberIds.push({ id: id, label: label });
        renderSelectedMembers();
        updateThreadControls();
    }

    function buildPayload() {
        var data = $('#sc-private-note-form').serializeArray();
        data.push({ name: 'action', value: 'sc_private_notes_preview_recipients' });
        data.push({ name: 'nonce', value: (window.scPrivateNotesAdmin && scPrivateNotesAdmin.nonce) ? scPrivateNotesAdmin.nonce : '' });
        return data;
    }

    function syncExcludedMemberInputs() {
        var $inputs = $('#sc-private-notes-excluded-members-inputs');
        if (!$inputs.length) {
            return;
        }
        $inputs.empty();
        excludedMemberIds.forEach(function (id) {
            $inputs.append('<input type="hidden" name="excluded_member_ids[]" value="' + id + '">');
        });
    }

    function initPreviewSelectionBindings() {
        var $checks = $('.sc-private-notes-preview-member-check');
        if (!$checks.length) {
            return;
        }

        $checks.each(function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            if (id && excludedMemberIds.indexOf(id) !== -1) {
                $(this).prop('checked', false);
            }
        });

        var checkedCount = $('.sc-private-notes-preview-member-check:checked').length;
        $('#sc-private-notes-preview-select-all').prop('checked', checkedCount === $checks.length);

        $(document).off('change.scPrivateNotesPreviewMember').on('change.scPrivateNotesPreviewMember', '.sc-private-notes-preview-member-check', function () {
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

            var allCount = $('.sc-private-notes-preview-member-check').length;
            var selectedCount = $('.sc-private-notes-preview-member-check:checked').length;
            $('#sc-private-notes-preview-select-all').prop('checked', allCount > 0 && selectedCount === allCount);
        });

        $(document).off('change.scPrivateNotesPreviewSelectAll').on('change.scPrivateNotesPreviewSelectAll', '#sc-private-notes-preview-select-all', function () {
            var shouldCheck = $(this).is(':checked');
            $('.sc-private-notes-preview-member-check').prop('checked', shouldCheck).trigger('change');
        });

        syncExcludedMemberInputs();
    }

    function toggleSendModeFields() {
        var mode = $('#sc-send-mode').val();
        $('#sc-row-thread-title').toggle(mode === 'create_new_thread');
        $('#sc-row-selected-thread').toggle(mode === 'append_to_selected_thread');
    }

    function updateThreadControls() {
        var targetType = $('#sc-target-type').val();
        var mode = $('#sc-send-mode').val();
        if (mode !== 'append_to_selected_thread') return;
        if (targetType !== 'specific' || selectedMemberIds.length !== 1) {
            $('#sc-selected-thread-id').html('<option value="0">برای انتخاب پرونده، فقط یک کاربر خاص انتخاب کنید</option>');
            return;
        }
        var memberId = selectedMemberIds[0].id;
        $('#sc-selected-thread-id').html('<option value="0">در حال بارگذاری پرونده‌ها...</option>');
        $.post((window.scPrivateNotesAdmin && scPrivateNotesAdmin.ajaxurl) ? scPrivateNotesAdmin.ajaxurl : ajaxurl, {
            action: 'sc_private_notes_member_threads',
            nonce: (window.scPrivateNotesAdmin && scPrivateNotesAdmin.nonce) ? scPrivateNotesAdmin.nonce : '',
            member_id: memberId
        }).done(function (res) {
            if (!res || !res.success || !res.data) {
                $('#sc-selected-thread-id').html('<option value="0">خطا در دریافت پرونده‌ها</option>');
                return;
            }
            var threads = res.data.threads || [];
            var lastThreadId = parseInt(res.data.last_thread_id || 0, 10);
            if (!threads.length) {
                $('#sc-selected-thread-id').html('<option value="0">پرونده‌ای وجود ندارد (از حالت پرونده جدید استفاده کنید)</option>');
                return;
            }
            var html = '<option value="0">انتخاب پرونده...</option>';
            threads.forEach(function (t) {
                var selected = (lastThreadId > 0 && parseInt(t.id, 10) === lastThreadId) ? ' selected' : '';
                html += '<option value="' + t.id + '"' + selected + '>' + (t.label || ('پرونده #' + t.id)) + '</option>';
            });
            $('#sc-selected-thread-id').html(html);
        }).fail(function () {
            $('#sc-selected-thread-id').html('<option value="0">خطا در ارتباط با سرور</option>');
        });
    }

    $(document).ready(function () {
        var $dropdown = $('#sc-users-member-dropdown');
        var $toggle = $dropdown.find('.sc-users-dropdown-toggle');
        var $menu = $dropdown.find('.sc-users-dropdown-menu');
        var $search = $dropdown.find('.sc-users-search-input');

        toggleFilterBlocks();
        renderSelectedMembers();
        toggleSendModeFields();
        updateThreadControls();
        syncExcludedMemberInputs();

        $('#sc-target-type').on('change', function () {
            toggleFilterBlocks();
            updateThreadControls();
        });
        $('#sc-send-mode').on('change', function () {
            toggleSendModeFields();
            updateThreadControls();
        });

        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $menu.slideToggle(150);
        });

        $search.on('input', function () {
            var term = ($(this).val() || '').toLowerCase().trim();
            $('#sc-member-options .sc-users-dropdown-option').each(function () {
                var text = ($(this).attr('data-search') || '').toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        $(document).on('click', '#sc-member-options .sc-users-dropdown-option', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).attr('data-id'), 10);
            var label = $(this).attr('data-label') || '';
            if (id) addMember(id, label);
            $search.val('');
            $('#sc-member-options .sc-users-dropdown-option').show();
        });

        $(document).on('click', '#sc-selected-members .sc-remove-tag', function () {
            var id = parseInt($(this).closest('.sc-tag').attr('data-id'), 10);
            selectedMemberIds = selectedMemberIds.filter(function (item) { return item.id !== id; });
            renderSelectedMembers();
            updateThreadControls();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#sc-users-member-dropdown').length) {
                $menu.slideUp(150);
            }
        });

        $('#sc-private-notes-preview-btn').on('click', function () {
            var $btn = $(this);
            var $result = $('#sc-private-notes-preview-result');
            $btn.prop('disabled', true);
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');
            $.post((window.scPrivateNotesAdmin && scPrivateNotesAdmin.ajaxurl) ? scPrivateNotesAdmin.ajaxurl : ajaxurl, buildPayload())
                .done(function (res) {
                    if (res && res.success && res.data) {
                        $result.html(res.data.html || '');
                        initPreviewSelectionBindings();
                    } else {
                        $result.html('<p class="description">خطا در دریافت پیش‌نمایش.</p>');
                    }
                })
                .fail(function () {
                    $result.html('<p class="description">خطا در ارتباط با سرور.</p>');
                })
                .always(function () { $btn.prop('disabled', false); });
        });
    });
})(jQuery);
