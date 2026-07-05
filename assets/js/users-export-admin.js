jQuery(function ($) {
    'use strict';

    var $form = $('#sc-users-export-form');
    var $templatesContainer = $('#sc-templates-container');

    // --------------------------
    // Export page logic
    // --------------------------
    if ($form.length) {
        var selectedMemberIds = [];
        var excludedMemberIds = [];
        var currentTemplate = null;
        var certificatesPreviewLoaded = false;
        var usersPreviewLoaded = false;
        var isSubmittingAfterConfirm = false;

    function getExcludedMemberIndexById(id) {
        return excludedMemberIds.findIndex(function (item) {
            return item.id === id;
        });
    }

    function addExcludedMember(id, label) {
        if (!id) {
            return;
        }
        var idx = getExcludedMemberIndexById(id);
        if (idx !== -1) {
            return;
        }
        excludedMemberIds.push({ id: id, label: label || ('کاربر #' + id) });
    }

    function removeExcludedMember(id) {
        excludedMemberIds = excludedMemberIds.filter(function (item) {
            return item.id !== id;
        });
    }

    function toggleFilterBlocks() {
        var targetType = $('#sc-target-type').val();
        $('#sc-filter-specific, #sc-filter-course, #sc-filter-event, #sc-filter-team, #sc-filter-level').hide();
        if (targetType === 'specific') {
            $('#sc-filter-specific').show();
        } else if (targetType === 'course') {
            $('#sc-filter-course').show();
        } else if (targetType === 'event') {
            $('#sc-filter-event').show();
        } else if (targetType === 'team') {
            $('#sc-filter-team').show();
        } else if (targetType === 'level') {
            $('#sc-filter-level').show();
        } else if (targetType === 'team_level') {
            $('#sc-filter-team').show();
            $('#sc-filter-level').show();
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
            $tags.append(
                '<span class="sc-tag" data-id="' + item.id + '">' +
                item.label +
                ' <button type="button" class="sc-remove-tag">&times;</button></span>'
            );
            $inputs.append('<input type="hidden" name="member_ids[]" value="' + item.id + '">');
        });
    }

    function renderExcludedMembers() {
        var $tags = $('#sc-excluded-members');
        var $inputs = $('#sc-excluded-member-hidden-inputs');
        var $count = $('#sc-excluded-members-count');
        if (!$tags.length) {
            return;
        }
        $tags.empty();
        $inputs.empty();
        $count.text(excludedMemberIds.length + ' کاربر از خروجی حذف شده');
        if (!excludedMemberIds.length) {
            $tags.html('<em>هیچ کاربری حذف نشده است.</em>');
            return;
        }
        excludedMemberIds.forEach(function (item) {
            $tags.append(
                '<span class="sc-tag sc-tag-exclude" data-id="' + item.id + '">' +
                item.label +
                ' <button type="button" class="sc-remove-exclude-tag">&times;</button></span>'
            );
            $inputs.append('<input type="hidden" name="excluded_member_ids[]" value="' + item.id + '">');
        });
    }

    function initCertificatesPreviewSelectionBindings() {
        var $checks = $('.sc-cert-preview-member-check');
        if (!$checks.length) {
            return;
        }

        $checks.each(function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            var idx = getExcludedMemberIndexById(id);
            if (idx !== -1) {
                $(this).prop('checked', false);
            } else {
                $(this).prop('checked', true);
            }
        });

        var checkedCount = $('.sc-cert-preview-member-check:checked').length;
        $('#sc-cert-preview-select-all').prop('checked', checkedCount === $checks.length);

        $(document).off('change.scCertPreviewMember').on('change.scCertPreviewMember', '.sc-cert-preview-member-check', function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            var label = $(this).attr('data-member-label') || ('کاربر #' + id);
            if (!id) {
                return;
            }
            if ($(this).is(':checked')) {
                removeExcludedMember(id);
            } else {
                addExcludedMember(id, label);
            }
            renderExcludedMembers();

            var allCount = $('.sc-cert-preview-member-check').length;
            var selectedCount = $('.sc-cert-preview-member-check:checked').length;
            $('#sc-cert-preview-select-all').prop('checked', allCount > 0 && selectedCount === allCount);
        });

        $(document).off('change.scCertPreviewSelectAll').on('change.scCertPreviewSelectAll', '#sc-cert-preview-select-all', function () {
            var shouldCheck = $(this).is(':checked');
            $('.sc-cert-preview-member-check').prop('checked', shouldCheck).trigger('change');
        });
    }

    function buildCertificatesPreviewPayload() {
        var data = $form.serializeArray();
        data.push({ name: 'action', value: 'sc_certificates_preview_members' });
        data.push({ name: 'nonce', value: $('#sc-cert-preview-nonce').val() || '' });
        return data;
    }

    function initCertificatesPreview() {
        var $btn = $('#sc-cert-preview-btn');
        var $result = $('#sc-cert-preview-result');
        if (!$btn.length || !$result.length) {
            return;
        }

        $btn.on('click', function () {
            $btn.prop('disabled', true);
            if (window.scAudiencePreviewAdd) {
                window.scAudiencePreviewAdd.reset('#sc-cert-preview-result');
            }
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');

            $.post((typeof ajaxurl !== 'undefined' ? ajaxurl : ''), buildCertificatesPreviewPayload())
                .done(function (res) {
                    if (res && res.success && res.data) {
                        $result.html(res.data.html || '');
                        certificatesPreviewLoaded = true;
                        initCertificatesPreviewSelectionBindings();
                        if (window.scAudiencePreviewAdd) {
                            window.scAudiencePreviewAdd.show('#sc-cert-preview-result');
                        }
                    } else {
                        $result.html('<p class="description">خطا در دریافت پیش نمایش.</p>');
                    }
                })
                .fail(function () {
                    $result.html('<p class="description">خطا در ارتباط با سرور.</p>');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    }

    function initUsersPreviewSelectionBindings() {
        var $checks = $('.sc-users-preview-member-check');
        if (!$checks.length) {
            return;
        }

        $checks.each(function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            var idx = getExcludedMemberIndexById(id);
            $(this).prop('checked', idx === -1);
        });

        var checkedCount = $('.sc-users-preview-member-check:checked').length;
        $('#sc-users-preview-select-all').prop('checked', checkedCount === $checks.length);

        $(document).off('change.scUsersPreviewMember').on('change.scUsersPreviewMember', '.sc-users-preview-member-check', function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            var isGuest = $(this).attr('data-is-guest') === '1';
            var label = $(this).attr('data-member-label') || ('کاربر #' + id);
            if (!id && !isGuest) {
                return;
            }
            if (isGuest) {
                return;
            }
            if ($(this).is(':checked')) {
                removeExcludedMember(id);
            } else {
                addExcludedMember(id, label);
            }
            renderExcludedMembers();

            var allCount = $('.sc-users-preview-member-check').length;
            var selectedCount = $('.sc-users-preview-member-check:checked').length;
            $('#sc-users-preview-select-all').prop('checked', allCount > 0 && selectedCount === allCount);
        });

        $(document).off('change.scUsersPreviewSelectAll').on('change.scUsersPreviewSelectAll', '#sc-users-preview-select-all', function () {
            var shouldCheck = $(this).is(':checked');
            $('.sc-users-preview-member-check').prop('checked', shouldCheck).trigger('change');
        });
    }

    function buildUsersPreviewPayload() {
        var data = $form.serializeArray();
        data.push({ name: 'action', value: 'sc_users_export_preview_members' });
        data.push({ name: 'nonce', value: $('#sc-users-preview-nonce').val() || '' });
        return data;
    }

    function initUsersPreview() {
        var $btn = $('#sc-users-preview-btn');
        var $result = $('#sc-users-preview-result');
        if (!$btn.length || !$result.length) {
            return;
        }

        $btn.on('click', function () {
            $btn.prop('disabled', true);
            if (window.scAudiencePreviewAdd) {
                window.scAudiencePreviewAdd.reset('#sc-users-preview-result');
            }
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');

            $.post((typeof ajaxurl !== 'undefined' ? ajaxurl : ''), buildUsersPreviewPayload())
                .done(function (res) {
                    if (res && res.success && res.data) {
                        $result.html(res.data.html || '');
                        usersPreviewLoaded = true;
                        initUsersPreviewSelectionBindings();
                        if (window.scAudiencePreviewAdd) {
                            window.scAudiencePreviewAdd.show('#sc-users-preview-result');
                        }
                    } else {
                        $result.html('<p class="description">خطا در دریافت پیش نمایش.</p>');
                    }
                })
                .fail(function () {
                    $result.html('<p class="description">خطا در ارتباط با سرور.</p>');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
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
        var $dropdown = $('#sc-users-member-dropdown');
        var $toggle = $dropdown.find('.sc-users-dropdown-toggle');
        var $menu = $dropdown.find('.sc-users-dropdown-menu');
        var $search = $dropdown.find('.sc-users-search-input');

        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $menu.slideToggle(150, function () {
                if (window.scAudienceDropdownStack) {
                    window.scAudienceDropdownStack.sync($dropdown);
                }
            });
            setTimeout(function () {
                $search.trigger('focus');
            }, 200);
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
            if (id) {
                addMember(id, label);
            }
            $search.val('');
            $('#sc-member-options .sc-users-dropdown-option').show();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#sc-users-member-dropdown').length) {
                $menu.slideUp(150, function () {
                    if (window.scAudienceDropdownStack) {
                        window.scAudienceDropdownStack.sync($dropdown);
                    }
                });
            }
        });
    }

    function bindExcludeDropdown() {
        var $dropdown = $('#sc-exclude-member-dropdown');
        if (!$dropdown.length) {
            return;
        }
        var $toggle = $dropdown.find('.sc-users-dropdown-toggle');
        var $menu = $dropdown.find('.sc-users-dropdown-menu');
        var $search = $dropdown.find('.sc-users-search-input');

        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $menu.slideToggle(150, function () {
                if (window.scAudienceDropdownStack) {
                    window.scAudienceDropdownStack.sync($dropdown);
                }
            });
            setTimeout(function () {
                $search.trigger('focus');
            }, 200);
        });

        $search.on('input', function () {
            var term = ($(this).val() || '').toLowerCase().trim();
            $('#sc-exclude-member-options .sc-users-dropdown-option').each(function () {
                var text = ($(this).attr('data-search') || '').toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        $(document).on('click', '#sc-exclude-member-options .sc-users-dropdown-option', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = parseInt($(this).attr('data-id'), 10);
            var label = $(this).attr('data-label') || '';
            var exists = excludedMemberIds.some(function (item) {
                return item.id === id;
            });
            if (id && !exists) {
                excludedMemberIds.push({ id: id, label: label });
                renderExcludedMembers();
            }
            $search.val('');
            $('#sc-exclude-member-options .sc-users-dropdown-option').show();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#sc-exclude-member-dropdown').length) {
                $menu.slideUp(150, function () {
                    if (window.scAudienceDropdownStack) {
                        window.scAudienceDropdownStack.sync($dropdown);
                    }
                });
            }
        });
    }

    function updatePreview() {
        var pageSize = $('#sc-page-size').val();
        var cards = parseInt($('#sc-cards-per-page').val(), 10) || 2;
        $('#sc-template-preview-meta').text(pageSize + ' - ' + cards + ' کارت در صفحه');
        renderLayoutPreview(pageSize, cards);
    }

    function getSelectedFields() {
        var selected = [];
        $('input[name="fields[]"]:checked').each(function () {
            selected.push($(this).val());
        });
        return selected;
    }

    var exportImageFields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo', 'attendance_qr'];

    function resolveTemplateLayout(fields) {
        var filtered = fields.filter(function (f) { return exportImageFields.indexOf(f) === -1; });
        var rightFields = [];
        var leftFields = [];
        if (currentTemplate && currentTemplate.layout) {
            var used = {};
            if (Array.isArray(currentTemplate.layout.right_fields)) {
                currentTemplate.layout.right_fields.forEach(function (field) {
                    if (filtered.indexOf(field) !== -1 && !used[field]) {
                        rightFields.push(field);
                        used[field] = true;
                    }
                });
            }
            if (Array.isArray(currentTemplate.layout.left_fields)) {
                currentTemplate.layout.left_fields.forEach(function (field) {
                    if (filtered.indexOf(field) !== -1 && !used[field]) {
                        leftFields.push(field);
                        used[field] = true;
                    }
                });
            }
            filtered.forEach(function (field) {
                if (!used[field]) {
                    if (rightFields.length <= leftFields.length) {
                        rightFields.push(field);
                    } else {
                        leftFields.push(field);
                    }
                }
            });
        } else {
            filtered.forEach(function (field, idx) {
                if (idx % 2 === 0) {
                    rightFields.push(field);
                } else {
                    leftFields.push(field);
                }
            });
        }
        return {
            right_fields: rightFields,
            left_fields: leftFields,
            has_photo:
                fields.indexOf('personal_photo') !== -1 ||
                fields.indexOf('id_card_photo') !== -1 ||
                fields.indexOf('sport_insurance_photo') !== -1 ||
                fields.indexOf('attendance_qr') !== -1
        };
    }

    function renderLayoutPreview(pageSize, cards) {
        var $grid = $('#sc-template-preview-grid');
        $grid.empty();
        $grid.attr('data-size', pageSize);
        $grid.attr('data-cards', cards);

        var maxCards = Math.min(cards, 4);
        var fields = getSelectedFields();
        var layout = resolveTemplateLayout(fields);
        var rightCount = Math.min(layout.right_fields.length, 4);
        var leftCount = Math.min(layout.left_fields.length, 4);
        var hasPhoto = layout.has_photo;
        for (var i = 0; i < maxCards; i++) {
            var photoHtml = hasPhoto ? '<div class="sc-mini-photo"></div>' : '';
            var rightLines = '';
            var leftLines = '';
            var j;
            for (j = 0; j < Math.max(1, rightCount); j++) {
                rightLines += '<span></span>';
            }
            for (j = 0; j < Math.max(1, leftCount); j++) {
                leftLines += '<span></span>';
            }
            var contentHtml = '' +
                '<div class="sc-mini-fields">' +
                '  <div class="sc-mini-col">' + rightLines + '</div>' +
                '  <div class="sc-mini-col">' + leftLines + '</div>' +
                '</div>';
            var cardClasses = 'sc-mini-card';
            if (hasPhoto) {
                cardClasses += ' has-photo';
            }
            $grid.append(
                '<div class="' + cardClasses + '">' +
                photoHtml + contentHtml +
                '</div>'
            );
        }
    }

    function enforceFormatRules() {
        var hasPhoto =
            $('input[name="fields[]"][value="personal_photo"]').is(':checked') ||
            $('input[name="fields[]"][value="id_card_photo"]').is(':checked') ||
            $('input[name="fields[]"][value="sport_insurance_photo"]').is(':checked') ||
            $('input[name="fields[]"][value="attendance_qr"]').is(':checked');
        var $format = $('#sc-export-format');
        if (hasPhoto) {
            $format.val('pdf');
            $format.find('option[value="excel"]').prop('disabled', true);
        } else {
            $format.find('option[value="excel"]').prop('disabled', false);
        }
    }

    function loadEventFieldsForExport(eventIds, selectedFields) {
        var $grid = $('#sc-event-fields-grid');
        if (!$grid.length) {
            return;
        }
        if (!eventIds || !eventIds.length) {
            $grid.empty();
            return;
        }
        $.post((typeof ajaxurl !== 'undefined' ? ajaxurl : ''), {
            action: 'sc_users_export_get_event_fields',
            nonce: $('#sc-event-fields-nonce').val() || '',
            event_ids: eventIds
        }).done(function (res) {
            if (!res || !res.success) {
                return;
            }
            $grid.html(res.data.html || '');
            if (Array.isArray(selectedFields)) {
                selectedFields.forEach(function (fieldKey) {
                    $grid.find('input[name="fields[]"][value="' + fieldKey + '"]').prop('checked', true);
                });
            }
            enforceFormatRules();
            updatePreview();
        });
    }

    function getSelectedEventIds() {
        var ids = [];
        $('#sc-event-ids option:selected').each(function () {
            var val = parseInt($(this).val(), 10);
            if (val) {
                ids.push(val);
            }
        });
        return ids;
    }

    function applyTemplateToForm(template) {
        currentTemplate = template || null;
        if (!template) {
            updatePreview();
            return;
        }
        if (template.is_event_template && template.event_id) {
            $('#sc-target-type').val('event');
            toggleFilterBlocks();
            $('#sc-event-ids').val([String(template.event_id)]);
            loadEventFieldsForExport([parseInt(template.event_id, 10)], template.fields || []);
        }
        if (!$('#sc-override-template-fields').is(':checked') && Array.isArray(template.fields)) {
            $('input[name="fields[]"]').prop('checked', false);
            template.fields.forEach(function (fieldKey) {
                $('#sc-fields-grid input[name="fields[]"][value="' + fieldKey + '"], #sc-event-fields-grid input[name="fields[]"][value="' + fieldKey + '"]').prop('checked', true);
            });
        }
        if (template.page_size) {
            $('#sc-page-size').val(template.page_size);
        }
        if (template.cards_per_page) {
            $('#sc-cards-per-page').val(String(template.cards_per_page));
        }
        updatePreview();
        enforceFormatRules();
    }

        $('#sc-target-type').on('change', function () {
            toggleFilterBlocks();
            if ($('#sc-target-type').val() === 'event') {
                loadEventFieldsForExport(getSelectedEventIds(), null);
            } else {
                $('#sc-event-fields-grid').empty();
            }
        });
        $('#sc-event-ids').on('change', function () {
            if ($('#sc-target-type').val() === 'event') {
                loadEventFieldsForExport(getSelectedEventIds(), null);
            }
        });
        $('#sc-target-type, #sc-member-type, #sc-event-ids, #sc-team-names, #sc-level-names').on('change', function () {
            usersPreviewLoaded = false;
            certificatesPreviewLoaded = false;
        });
        $(document).on('sc-audience-course-change', '.sc-audience-course-picker', function () {
            usersPreviewLoaded = false;
            certificatesPreviewLoaded = false;
        });
        $('input[name="fields[]"]').on('change', function () {
            enforceFormatRules();
            updatePreview();
        });
        $('#sc-page-size, #sc-cards-per-page').on('change', updatePreview);

        $(document).on('click', '.sc-remove-tag', function () {
        var id = parseInt($(this).closest('.sc-tag').attr('data-id'), 10);
        selectedMemberIds = selectedMemberIds.filter(function (item) {
            return item.id !== id;
        });
        renderSelectedMembers();
    });

        $(document).on('click', '.sc-remove-exclude-tag', function () {
        var id = parseInt($(this).closest('.sc-tag-exclude').attr('data-id'), 10);
        excludedMemberIds = excludedMemberIds.filter(function (item) {
            return item.id !== id;
        });
        renderExcludedMembers();
    });

        $('#sc-template-key').on('change', function () {
        var selected = $(this).find(':selected');
        var raw = selected.attr('data-template');
        if (!raw) {
            currentTemplate = null;
            updatePreview();
            return;
        }
        try {
            var template = JSON.parse(raw);
            applyTemplateToForm(template);
        } catch (err) {
            // ignore invalid json
        }
    });

        $form.on('submit', function (e) {
        if (isSubmittingAfterConfirm) {
            isSubmittingAfterConfirm = false;
            return true;
        }
        var targetType = $('#sc-target-type').val();
        if (targetType === 'specific' && !selectedMemberIds.length) {
            alert('حداقل یک کاربر انتخاب کنید.');
            return false;
        }
        if ($('#sc-cert-preview-btn').length && !certificatesPreviewLoaded) {
            e.preventDefault();
            window.scConfirm({ type: 'warning', message: 'پیش نمایش کاربران هنوز اجرا نشده است. ادامه می‌دهید؟' })
                .then(function (ok) {
                    if (ok) {
                        isSubmittingAfterConfirm = true;
                        $form.trigger('submit');
                    }
                });
            return false;
        }
        if ($('#sc-users-preview-btn').length && !usersPreviewLoaded) {
            e.preventDefault();
            window.scConfirm({ type: 'warning', message: 'پیش نمایش کاربران هنوز اجرا نشده است. ادامه می‌دهید؟' })
                .then(function (ok) {
                    if (ok) {
                        isSubmittingAfterConfirm = true;
                        $form.trigger('submit');
                    }
                });
            return false;
        }
        return true;
    });

        bindSearchableDropdown();
        bindExcludeDropdown();
        $(document).on('sc-audience-preview-member-added', function (e, payload) {
            if (!payload || !payload.mode) {
                return;
            }
            if (payload.mode === 'cert') {
                initCertificatesPreviewSelectionBindings();
            }
            if (payload.mode === 'users') {
                initUsersPreviewSelectionBindings();
            }
        });
        initCertificatesPreview();
        initUsersPreview();
        toggleFilterBlocks();
        renderSelectedMembers();
        renderExcludedMembers();
        updatePreview();
        enforceFormatRules();
    }

    // --------------------------
    // Templates page logic
    // --------------------------
    if ($templatesContainer.length) {
        var fieldLabelsRaw = $('#sc-template-field-labels').attr('data-fields') || '{}';
        var fieldLabels = {};
        var exportEvents = [];
        try {
            fieldLabels = JSON.parse(fieldLabelsRaw);
        } catch (e) {
            fieldLabels = {};
        }
        try {
            exportEvents = JSON.parse($('#sc-export-events-data').text() || '[]');
        } catch (e) {
            exportEvents = [];
        }

        function getTemplateFieldLabels($templateItem) {
            var labels = $.extend({}, fieldLabels);
            var eventLabelsRaw = $templateItem.attr('data-event-field-labels');
            if (eventLabelsRaw) {
                try {
                    $.extend(labels, JSON.parse(eventLabelsRaw));
                } catch (err) {
                    // ignore
                }
            }
            return labels;
        }

        function isEventSpecificField(fieldKey) {
            return fieldKey === 'registration_type' || (fieldKey && fieldKey.indexOf('event_field_') === 0);
        }

        function clearEventLayoutChips($templateItem) {
            $templateItem.find('.sc-layout-chip').each(function () {
                var fieldKey = $(this).attr('data-field');
                if (!isEventSpecificField(fieldKey)) {
                    return;
                }
                $(this).next('input[type="hidden"]').remove();
                $(this).remove();
            });
            syncDropzoneInputs($templateItem);
        }

        function getSelectedTemplateFields($templateItem) {
            var selected = [];
            $templateItem.find('.sc-template-base-fields input[type="checkbox"]:checked, .sc-template-event-fields-grid input[type="checkbox"]:checked').each(function () {
                selected.push($(this).val());
            });
            return selected;
        }

        function buildTemplateEventFieldsHtml(templateKey, eventLabels, selectedFields) {
            var html = '<h4 class="sc-event-fields-heading">فیلدهای اختصاصی رویداد</h4>';
            var labels = $.extend({ registration_type: 'نوع ثبت‌نام' }, eventLabels || {});
            Object.keys(labels).forEach(function (fieldKey) {
                var checked = selectedFields.indexOf(fieldKey) !== -1 ? ' checked' : '';
                html += '<label class="sc-inline-check sc-event-field-check">';
                html += '<input type="checkbox" name="templates[' + templateKey + '][fields][]" value="' + fieldKey + '"' + checked + '> ';
                html += labels[fieldKey];
                html += '</label>';
            });
            return html;
        }

        function buildEventSelectHtml(selectedId) {
            var html = '<option value="">انتخاب رویداد</option>';
            exportEvents.forEach(function (ev) {
                var selected = parseInt(selectedId, 10) === parseInt(ev.id, 10) ? ' selected' : '';
                html += '<option value="' + ev.id + '"' + selected + '>' + ev.name + '</option>';
            });
            return html;
        }

        function syncDropzoneInputs($templateItem) {
            var templateKey = $templateItem.attr('data-template-key');
            if (!templateKey) {
                return;
            }
            $templateItem.find('.sc-layout-dropzone').each(function () {
                var columnKey = $(this).attr('data-column');
                $(this).find('input[type="hidden"]').remove();
                $(this).find('.sc-layout-chip').each(function () {
                    var field = $(this).attr('data-field');
                    $(this).after(
                        '<input type="hidden" name="templates[' + templateKey + '][layout][columns][' + columnKey + '][]" value="' + field + '">'
                    );
                });
            });
        }

        function ensureChipInFirstColumn($templateItem, fieldKey) {
            var labels = getTemplateFieldLabels($templateItem);
            var $first = $templateItem.find('.sc-layout-dropzone').first();
            var exists = $templateItem.find('.sc-layout-chip[data-field="' + fieldKey + '"]').length > 0;
            if (!exists) {
                $first.append('<div class="sc-layout-chip" draggable="true" data-field="' + fieldKey + '">' + (labels[fieldKey] || fieldKey) + '</div>');
            }
            syncDropzoneInputs($templateItem);
        }

        function removeChipEverywhere($templateItem, fieldKey) {
            $templateItem.find('.sc-layout-chip[data-field="' + fieldKey + '"]').remove();
            syncDropzoneInputs($templateItem);
        }

        function getDragAfterElement($container, y) {
            var chips = $container.find('.sc-layout-chip').toArray();
            var closest = null;
            var closestOffset = Number.NEGATIVE_INFINITY;
            chips.forEach(function (chip) {
                var rect = chip.getBoundingClientRect();
                var offset = y - rect.top - rect.height / 2;
                if (offset < 0 && offset > closestOffset) {
                    closestOffset = offset;
                    closest = chip;
                }
            });
            return closest;
        }

        function bindDnd($scope) {
            var dragged = null;
            $scope.on('dragstart', '.sc-layout-chip', function () {
                dragged = this;
                $(this).addClass('is-dragging');
            });
            $scope.on('dragend', '.sc-layout-chip', function () {
                $(this).removeClass('is-dragging');
            });
            $scope.on('dragover', '.sc-layout-dropzone', function (e) {
                e.preventDefault();
                $(this).addClass('is-over');
                if (!dragged) {
                    return;
                }
                var afterElement = getDragAfterElement($(this), e.originalEvent.clientY);
                if (!afterElement) {
                    this.appendChild(dragged);
                } else if (afterElement !== dragged) {
                    this.insertBefore(dragged, afterElement);
                }
            });
            $scope.on('dragleave', '.sc-layout-dropzone', function () {
                $(this).removeClass('is-over');
            });
            $scope.on('drop', '.sc-layout-dropzone', function (e) {
                e.preventDefault();
                $(this).removeClass('is-over');
                if (dragged) {
                    var $templateItem = $(this).closest('.sc-template-item');
                    syncDropzoneInputs($templateItem);
                }
            });
        }

        function buildLayoutColumnsHtml(columnsCount) {
            var html = '';
            for (var i = 1; i <= columnsCount; i++) {
                var colKey = 'column_' + i;
                html += '' +
                    '<div class="sc-layout-column">' +
                    '  <h4>ستون ' + i + '</h4>' +
                    '  <div class="sc-layout-dropzone" data-column="' + colKey + '"></div>' +
                    '</div>';
            }
            return html;
        }

        function rebalanceLayoutColumns($templateItem, columnsCount) {
            var labels = getTemplateFieldLabels($templateItem);
            var chips = [];
            $templateItem.find('.sc-layout-chip').each(function () {
                chips.push($(this).attr('data-field'));
            });
            var $columnsWrap = $templateItem.find('.sc-layout-columns');
            $columnsWrap.attr('data-columns-count', columnsCount);
            $columnsWrap.html(buildLayoutColumnsHtml(columnsCount));
            chips.forEach(function (fieldKey, idx) {
                var target = (idx % columnsCount) + 1;
                $columnsWrap.find('.sc-layout-dropzone[data-column="column_' + target + '"]')
                    .append('<div class="sc-layout-chip" draggable="true" data-field="' + fieldKey + '">' + (labels[fieldKey] || fieldKey) + '</div>');
            });
            syncDropzoneInputs($templateItem);
        }

        function activateTemplate(templateKey) {
            if (!templateKey) {
                return;
            }
            $('.sc-template-list-item, .sc-template-item').removeClass('is-active');
            $('.sc-template-list-item[data-template-key="' + templateKey + '"]').addClass('is-active');
            $('.sc-template-item[data-template-key="' + templateKey + '"]').addClass('is-active');
        }

        function bindTitleSync($templateItem) {
            $templateItem.find('.sc-template-title-input').on('input', function () {
                var templateKey = $templateItem.attr('data-template-key');
                $('.sc-template-list-item[data-template-key="' + templateKey + '"] .sc-template-list-title').text($(this).val() || 'قالب جدید');
            });
        }

        bindDnd($templatesContainer);
        $templatesContainer.find('.sc-template-item').each(function () {
            bindTitleSync($(this));
        });

        $templatesContainer.on('change', '.sc-fields-grid input[type="checkbox"]', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            var fieldKey = $(this).val();
            if ($(this).is(':checked')) {
                ensureChipInFirstColumn($templateItem, fieldKey);
            } else {
                removeChipEverywhere($templateItem, fieldKey);
            }
        });

        $templatesContainer.on('change', 'select[name$="[layout_columns_count]"]', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            var columnsCount = parseInt($(this).val(), 10) || 2;
            columnsCount = Math.max(1, Math.min(4, columnsCount));
            rebalanceLayoutColumns($templateItem, columnsCount);
        });

        function buildTemplateCard(templateKey) {
            var fieldsHtml = '';
            Object.keys(fieldLabels).forEach(function (fieldKey) {
                fieldsHtml += '<label class="sc-inline-check"><input type="checkbox" name="templates[' + templateKey + '][fields][]" value="' + fieldKey + '"> ' + fieldLabels[fieldKey] + '</label>';
            });

            return '' +
                '<div class="sc-template-item" data-template-key="' + templateKey + '">' +
                '  <h2>قالب جدید <small>(' + templateKey + ')</small></h2>' +
                '  <input type="hidden" name="templates[' + templateKey + '][key]" value="' + templateKey + '">' +
                '  <div class="sc-row"><label>عنوان قالب</label><input type="text" class="sc-template-title-input" name="templates[' + templateKey + '][title]" value="قالب جدید"></div>' +
                '  <div class="sc-row"><label>توضیحات</label><textarea name="templates[' + templateKey + '][description]" rows="3"></textarea></div>' +
                '  <div class="sc-row"><label class="sc-inline-check"><input type="checkbox" class="sc-is-event-template" name="templates[' + templateKey + '][is_event_template]" value="1"> قالب مخصوص رویداد</label></div>' +
                '  <div class="sc-row sc-template-event-select-row" style="display:none;"><label>رویداد</label><select class="sc-template-event-id sc-template-event-select" name="templates[' + templateKey + '][event_id]" size="7">' + buildEventSelectHtml('') + '</select></div>' +
                '  <div class="sc-row"><label>اندازه صفحه</label><select name="templates[' + templateKey + '][page_size]"><option value="A4">A4</option><option value="A5">A5</option></select></div>' +
                '  <div class="sc-row"><label>تعداد کارت در صفحه</label><select name="templates[' + templateKey + '][cards_per_page]"><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select></div>' +
                '  <div class="sc-row"><label>تعداد ستون خروجی</label><select name="templates[' + templateKey + '][columns_count]"><option value="1">1 ستونه</option><option value="2" selected>2 ستونه</option><option value="3">3 ستونه</option><option value="4">4 ستونه</option></select></div>' +
                '  <div class="sc-row"><label>تعداد ستون چیدمان فیلدها</label><select class="sc-layout-columns-count-select" name="templates[' + templateKey + '][layout_columns_count]"><option value="1">1 ستونه</option><option value="2" selected>2 ستونه</option><option value="3">3 ستونه</option><option value="4">4 ستونه</option></select></div>' +
                '  <div class="sc-row"><label>رنگ پس‌زمینه کارت</label><input type="color" name="templates[' + templateKey + '][background_color]" value="#ffffff"></div>' +
                '  <div class="sc-row"><label>تصویر پس‌زمینه کارت</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[' + templateKey + '][background_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>' +
                '  <div class="sc-row"><label>شفافیت تصویر پس‌زمینه (0 تا 1)</label><input type="number" min="0" max="1" step="0.05" name="templates[' + templateKey + '][background_opacity]" value="0.2"></div>' +
                '  <div class="sc-row"><label>فونت خروجی</label><select name="templates[' + templateKey + '][content_font_family]"><option value="IRANYekanXFaNum" selected>IRANYekanXFaNum</option><option value="Vazir">Vazir</option><option value="Shabnam">Shabnam</option><option value="Morabba">Morabba</option><option value="Tahoma">Tahoma</option><option value="Arial">Arial</option></select></div>' +
                '  <div class="sc-row"><label class="sc-inline-check"><input type="checkbox" name="templates[' + templateKey + '][image_only_mode]" value="1"> خروجی فقط عکس باشد (تمام عرض، زیر هم، بک‌گراند شفاف)</label></div>' +
                '  <div class="sc-row"><label>متن اضافی زیر کارت</label><textarea name="templates[' + templateKey + '][card_footer_text]" rows="3" placeholder="متن دلخواه برای نمایش پایین هر کارت"></textarea></div>' +
                '  <div class="sc-fields-grid sc-template-base-fields">' + fieldsHtml + '</div>' +
                '  <div class="sc-fields-grid sc-template-event-fields-grid" style="display:none;"></div>' +
                '  <div class="sc-template-layout-builder" data-template="' + templateKey + '">' +
                '    <h3>چیدمان گرافیکی فیلدها</h3>' +
                '    <p class="description">فیلدها را با Drag & Drop بین ستون‌ها جابه‌جا کنید.</p>' +
                '    <div class="sc-layout-columns" data-columns-count="2">' + buildLayoutColumnsHtml(2) + '</div>' +
                '  </div>' +
                '</div>';
        }

        function buildTemplateListItem(templateKey, title) {
            return '' +
                '<div class="sc-template-list-item" data-template-key="' + templateKey + '">' +
                '  <div class="sc-template-list-title">' + title + '</div>' +
                '  <div class="sc-template-list-actions">' +
                '    <button type="button" class="button sc-edit-template">ویرایش</button>' +
                '    <button type="button" class="button sc-delete-template">حذف</button>' +
                '  </div>' +
                '</div>';
        }

        $('#sc-add-new-template').on('click', function () {
            var templateKey = 'tpl_' + Date.now();
            var $newCard = $(buildTemplateCard(templateKey));
            $templatesContainer.append($newCard);
            $('#sc-templates-list').append(buildTemplateListItem(templateKey, 'قالب جدید'));
            bindTitleSync($newCard);
            activateTemplate(templateKey);
        });

        $(document).on('click', '.sc-edit-template', function () {
            var templateKey = $(this).closest('.sc-template-list-item').attr('data-template-key');
            activateTemplate(templateKey);
        });

        $(document).on('click', '.sc-delete-template', function () {
            var $item = $(this).closest('.sc-template-list-item');
            var templateKey = $item.attr('data-template-key');
            var $card = $('.sc-template-item[data-template-key="' + templateKey + '"]');
            window.scConfirm({ type: 'danger', message: 'این قالب حذف شود؟' }).then(function (ok) {
                if (!ok) {
                    return;
                }
                $item.remove();
                $card.remove();
                var $first = $('.sc-template-list-item').first();
                if ($first.length) {
                    activateTemplate($first.attr('data-template-key'));
                }
            });
        });

        $(document).on('click', '.sc-select-bg-image', function (e) {
            e.preventDefault();
            var $button = $(this);
            var $input = $button.closest('.sc-bg-image-picker').find('.sc-bg-image-input');
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            var frame = wp.media({
                title: 'انتخاب تصویر پس‌زمینه',
                button: { text: 'انتخاب' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                if (attachment && attachment.url) {
                    $input.val(attachment.url).trigger('change');
                }
            });
            frame.open();
        });

        function loadTemplateEventFields($templateItem, eventId, keepSelected) {
            var $grid = $templateItem.find('.sc-template-event-fields-grid');
            var templateKey = $templateItem.attr('data-template-key');
            if (!eventId) {
                $templateItem.attr('data-event-field-labels', '{}');
                $grid.hide().empty();
                clearEventLayoutChips($templateItem);
                return;
            }
            var selectedBefore = keepSelected ? getSelectedTemplateFields($templateItem) : [];
            clearEventLayoutChips($templateItem);
            $.post((typeof ajaxurl !== 'undefined' ? ajaxurl : ''), {
                action: 'sc_users_export_get_event_fields',
                nonce: $('#sc-template-event-fields-nonce').val() || '',
                event_ids: [eventId]
            }).done(function (res) {
                if (!res || !res.success) {
                    return;
                }
                var eventLabels = res.data.labels || {};
                $templateItem.attr('data-event-field-labels', JSON.stringify(eventLabels));
                $grid.html(buildTemplateEventFieldsHtml(templateKey, eventLabels, selectedBefore)).show();
                selectedBefore.forEach(function (fieldKey) {
                    if (isEventSpecificField(fieldKey) && eventLabels[fieldKey]) {
                        ensureChipInFirstColumn($templateItem, fieldKey);
                    }
                });
                $templateItem.find('.sc-layout-chip').each(function () {
                    var fieldKey = $(this).attr('data-field');
                    var labels = getTemplateFieldLabels($templateItem);
                    if (isEventSpecificField(fieldKey) && !labels[fieldKey]) {
                        $(this).next('input[type="hidden"]').remove();
                        $(this).remove();
                    } else if (isEventSpecificField(fieldKey)) {
                        $(this).text(labels[fieldKey] || fieldKey);
                    }
                });
                syncDropzoneInputs($templateItem);
            });
        }

        $templatesContainer.on('change', '.sc-is-event-template', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            var checked = $(this).is(':checked');
            $templateItem.find('.sc-template-event-select-row').toggle(checked);
            if (!checked) {
                $templateItem.find('.sc-template-event-id').val('');
                $templateItem.attr('data-event-field-labels', '{}');
                $templateItem.find('.sc-template-event-fields-grid').hide().empty();
                clearEventLayoutChips($templateItem);
            } else {
                var eventId = parseInt($templateItem.find('.sc-template-event-id').val(), 10);
                if (eventId) {
                    loadTemplateEventFields($templateItem, eventId, true);
                }
            }
        });

        $templatesContainer.on('change', '.sc-template-event-id', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            if (!$templateItem.find('.sc-is-event-template').is(':checked')) {
                return;
            }
            var eventId = parseInt($(this).val(), 10);
            loadTemplateEventFields($templateItem, eventId || 0, true);
        });

        $templatesContainer.find('.sc-template-item').each(function () {
            var $item = $(this);
            var $eventGrid = $item.find('.sc-template-event-fields-grid');
            var gridLabels = $eventGrid.attr('data-event-field-labels');
            if (gridLabels) {
                $item.attr('data-event-field-labels', gridLabels);
            }
            if ($item.find('.sc-is-event-template').is(':checked')) {
                $item.find('.sc-template-event-select-row').show();
                var eventId = parseInt($item.find('.sc-template-event-id').val(), 10);
                if (eventId && !$item.find('.sc-template-event-fields-grid .sc-event-field-check').length) {
                    loadTemplateEventFields($item, eventId, true);
                }
            }
        });
    }
});
