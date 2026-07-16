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
    var pvcPhoneFields = ['player_phone', 'father_phone', 'mother_phone', 'landline_phone'];
    var exportFieldLabels = {};
    try {
        exportFieldLabels = JSON.parse($('#sc-export-field-labels-data').text() || '{}');
    } catch (pvcLabelErr) {
        exportFieldLabels = {};
    }

    function getSelectedExportFields() {
        var selected = [];
        $('input[name="fields[]"]:checked').each(function () {
            selected.push($(this).val());
        });
        return selected;
    }

    function isPvcExportEnabled() {
        return $('#sc-pvc-export').length && $('#sc-pvc-export').is(':checked');
    }

    function isExcelImagesExportEnabled() {
        var $format = $('#sc-export-format');
        return $format.length && $format.val() === 'excel_images';
    }

    function isAdminZipExportEnabled() {
        return isPvcExportEnabled() || isExcelImagesExportEnabled();
    }

    function refreshAdminZipImageNameOptions($select) {
        if (!$select || !$select.length) {
            return;
        }
        var selectedFields = getSelectedExportFields();
        var current = $select.val() || 'full_name';
        var options = [];
        selectedFields.forEach(function (fieldKey) {
            if (exportImageFields.indexOf(fieldKey) !== -1) {
                return;
            }
            if (pvcPhoneFields.indexOf(fieldKey) === -1) {
                options.push({ key: fieldKey, label: exportFieldLabels[fieldKey] || fieldKey });
            }
        });
        selectedFields.forEach(function (fieldKey) {
            if (exportImageFields.indexOf(fieldKey) !== -1) {
                return;
            }
            if (pvcPhoneFields.indexOf(fieldKey) !== -1) {
                options.push({ key: fieldKey, label: exportFieldLabels[fieldKey] || fieldKey });
            }
        });
        $select.empty();
        if (!options.length) {
            $select.append('<option value="full_name">نام و نام خانوادگی</option>');
            return;
        }
        var hasCurrent = false;
        options.forEach(function (item) {
            if (item.key === current) {
                hasCurrent = true;
            }
            $select.append('<option value="' + item.key + '">' + item.label + '</option>');
        });
        if (hasCurrent) {
            $select.val(current);
        } else if (options.some(function (item) { return item.key === 'full_name'; })) {
            $select.val('full_name');
        }
    }

    function refreshPvcImageNameOptions() {
        refreshAdminZipImageNameOptions($('#sc-pvc-image-name-field'));
    }

    function refreshExcelImagesNameOptions() {
        refreshAdminZipImageNameOptions($('#sc-excel-images-name-field'));
    }

    function toggleAdminZipExportUi() {
        var pvcEnabled = isPvcExportEnabled();
        var excelImagesEnabled = isExcelImagesExportEnabled();
        $('#sc-pvc-options').toggle(pvcEnabled);
        $('#sc-excel-images-options').toggle(excelImagesEnabled);
        $('#sc-export-format-desc-default').toggle(!excelImagesEnabled);
        $('#sc-export-format-desc-excel-images').toggle(excelImagesEnabled);
        var adminZipEnabled = pvcEnabled || excelImagesEnabled;
        $('.sc-users-export-output-panel tr')
            .not('.sc-pvc-export-row, .sc-export-format-row')
            .toggle(!adminZipEnabled);
        $('#sc-template-preview').toggle(!adminZipEnabled);
        if (pvcEnabled) {
            refreshPvcImageNameOptions();
        }
        if (excelImagesEnabled) {
            refreshExcelImagesNameOptions();
        }
    }

    function togglePvcExportUi() {
        toggleAdminZipExportUi();
    }

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
        if (isPvcExportEnabled()) {
            return;
        }
        var hasPhoto =
            $('input[name="fields[]"][value="personal_photo"]').is(':checked') ||
            $('input[name="fields[]"][value="id_card_photo"]').is(':checked') ||
            $('input[name="fields[]"][value="sport_insurance_photo"]').is(':checked') ||
            $('input[name="fields[]"][value="attendance_qr"]').is(':checked');
        var $format = $('#sc-export-format');
        var current = $format.val();
        var $excelOption = $format.find('option[value="excel"]');
        var $cardsZipOption = $format.find('option[value="cards_zip"]');
        var $excelImagesOption = $format.find('option[value="excel_images"]');
        if (hasPhoto) {
            if (current === 'excel') {
                $format.val('pdf');
            }
            $excelOption.prop('disabled', true);
            if ($cardsZipOption.length) {
                $cardsZipOption.prop('disabled', false);
            }
            if ($excelImagesOption.length) {
                $excelImagesOption.prop('disabled', false);
            }
        } else {
            $excelOption.prop('disabled', false);
            if ($cardsZipOption.length) {
                $cardsZipOption.prop('disabled', false);
            }
            if ($excelImagesOption.length) {
                $excelImagesOption.prop('disabled', true);
                if (current === 'excel_images') {
                    $format.val($excelOption.length ? 'excel' : 'pdf');
                }
            }
        }
        toggleAdminZipExportUi();
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
            refreshPvcImageNameOptions();
            refreshExcelImagesNameOptions();
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
            refreshPvcImageNameOptions();
            refreshExcelImagesNameOptions();
            updatePreview();
        });
        $('#sc-pvc-export').on('change', function () {
            if ($(this).is(':checked') && $('#sc-export-format').val() === 'excel_images') {
                $('#sc-export-format').val('pdf');
            }
            toggleAdminZipExportUi();
            enforceFormatRules();
        });
        $('#sc-export-format').on('change', function () {
            if ($(this).val() === 'excel_images') {
                $('#sc-pvc-export').prop('checked', false);
            }
            enforceFormatRules();
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
        if (isPvcExportEnabled()) {
            var selectedFields = getSelectedExportFields();
            var hasImageField = selectedFields.some(function (field) {
                return exportImageFields.indexOf(field) !== -1;
            });
            var hasTextField = selectedFields.some(function (field) {
                return exportImageFields.indexOf(field) === -1;
            });
            if (!hasImageField) {
                alert('برای خروجی کارت PVC حداقل یک فیلد تصویری انتخاب کنید.');
                return false;
            }
            if (!hasTextField) {
                alert('برای خروجی کارت PVC حداقل یک فیلد متنی علاوه بر تصویر انتخاب کنید.');
                return false;
            }
        }
        if (isExcelImagesExportEnabled()) {
            var selectedFieldsExcel = getSelectedExportFields();
            var hasImageFieldExcel = selectedFieldsExcel.some(function (field) {
                return exportImageFields.indexOf(field) !== -1;
            });
            var hasTextFieldExcel = selectedFieldsExcel.some(function (field) {
                return exportImageFields.indexOf(field) === -1;
            });
            if (!hasImageFieldExcel) {
                alert('برای خروجی ترکیب اکسل و فایل حداقل یک فیلد تصویری انتخاب کنید.');
                return false;
            }
            if (!hasTextFieldExcel) {
                alert('برای خروجی ترکیب اکسل و فایل حداقل یک فیلد متنی علاوه بر تصویر انتخاب کنید.');
                return false;
            }
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
        refreshPvcImageNameOptions();
        refreshExcelImagesNameOptions();
        toggleAdminZipExportUi();
    }

    // --------------------------
    // Templates page logic
    // --------------------------
    if ($templatesContainer.length) {
        var templatesConfig = (typeof window.scUsersExportTemplates !== 'undefined' && window.scUsersExportTemplates) ? window.scUsersExportTemplates : {};
        function getTemplatesAjaxUrl() {
            if (templatesConfig.ajaxurl) {
                return templatesConfig.ajaxurl;
            }
            if (typeof scAdmin !== 'undefined' && scAdmin.ajaxurl) {
                return scAdmin.ajaxurl;
            }
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }
            return '';
        }
        function getTemplatesSaveNonce() {
            return $('#sc-export-template-save-nonce').val() || templatesConfig.saveNonce || $('input[name="_wpnonce"]').val() || '';
        }
        function getTemplatesPreviewNonce() {
            return $('#sc-export-template-preview-nonce').val() || templatesConfig.previewNonce || '';
        }
        function showTemplateNotice(message, type) {
            var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
            var $notice = $('#sc-export-templates-notice');
            if (!$notice.length) {
                $notice = $('<div id="sc-export-templates-notice" class="notice is-dismissible"><p></p></div>');
                $('.sc-cert-templates-wrap').first().prepend($notice);
            }
            $notice.removeClass('notice-success notice-error').addClass(noticeClass);
            $notice.find('p').text(message || '');
            $notice.show();
            $('html, body').animate({ scrollTop: $notice.offset().top - 80 }, 200);
        }

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
                    updateTemplatePreview($templateItem);
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
            rebalanceColumnPaddings($templateItem, columnsCount);
            updateTemplatePreview($templateItem);
        }

        var cardSizePresets = {};
        try {
            cardSizePresets = JSON.parse($('#sc-export-card-size-presets').text() || '{}');
        } catch (presetErr) {
            cardSizePresets = {};
        }

        var exportImageFields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo', 'attendance_qr'];
        var fieldsWithoutDisplayLabels = exportImageFields.concat(['attendance_qr_short_code', 'attendance_qr_all_codes']);
        function shouldRenderFieldLabel(field, showLabelsSetting) {
            if (fieldsWithoutDisplayLabels.indexOf(field) !== -1) {
                return false;
            }
            return !!showLabelsSetting;
        }
        var exportImageFieldLabels = {
            personal_photo: 'عکس پرسنلی',
            id_card_photo: 'عکس کارت ملی',
            sport_insurance_photo: 'عکس بیمه ورزشی',
            attendance_qr: 'QR حضور و غیاب'
        };
        var defaultImageSizes = {
            personal_photo: { width: 150, height: 190 },
            id_card_photo: { width: 260, height: 150 },
            sport_insurance_photo: { width: 260, height: 150 },
            attendance_qr: { width: 180, height: 180 }
        };
        function collectImageSizeValues($templateItem) {
            var values = {};
            $templateItem.find('.sc-image-size-group').each(function () {
                var fieldKey = $(this).attr('data-image-field');
                var width = parseInt($(this).find('.sc-image-width-input').val(), 10);
                var height = parseInt($(this).find('.sc-image-height-input').val(), 10);
                var defaults = defaultImageSizes[fieldKey] || { width: 150, height: 150 };
                values[fieldKey] = {
                    width: !Number.isNaN(width) && width > 0 ? width : defaults.width,
                    height: !Number.isNaN(height) && height > 0 ? height : defaults.height
                };
            });
            return values;
        }

        function syncImageSizesVisibility($templateItem) {
            var selected = getSelectedTemplateFields($templateItem);
            $templateItem.find('.sc-image-size-group').each(function () {
                var fieldKey = $(this).attr('data-image-field');
                $(this).toggle(selected.indexOf(fieldKey) !== -1);
            });
            var $section = $templateItem.find('.sc-image-sizes-section');
            if ($section.length) {
                var hasAny = selected.some(function (field) {
                    return exportImageFields.indexOf(field) !== -1;
                });
                $section.toggle(hasAny);
            }
        }

        function buildImageSizeGroupHtml(templateKey, fieldKey, label, size) {
            size = size || defaultImageSizes[fieldKey] || { width: 150, height: 150 };
            return '' +
                '<div class="sc-image-size-group" data-image-field="' + fieldKey + '" style="display:none;">' +
                '  <h4>' + label + '</h4>' +
                '  <div class="sc-padding-grid sc-image-size-grid">' +
                '    <div class="sc-padding-item"><small>عرض</small><input type="number" class="sc-image-width-input" min="20" max="600" step="1" name="templates[' + templateKey + '][image_sizes][' + fieldKey + '][width]" value="' + size.width + '"></div>' +
                '    <div class="sc-padding-item"><small>ارتفاع</small><input type="number" class="sc-image-height-input" min="20" max="600" step="1" name="templates[' + templateKey + '][image_sizes][' + fieldKey + '][height]" value="' + size.height + '"></div>' +
                '  </div>' +
                '</div>';
        }

        function buildImageSizesSectionHtml(templateKey) {
            var html = '';
            Object.keys(exportImageFieldLabels).forEach(function (fieldKey) {
                html += buildImageSizeGroupHtml(templateKey, fieldKey, exportImageFieldLabels[fieldKey], defaultImageSizes[fieldKey]);
            });
            return html;
        }

        function getImageSizeStyle(template, imgField) {
            var sizes = template.image_sizes && template.image_sizes[imgField] ? template.image_sizes[imgField] : (defaultImageSizes[imgField] || null);
            if (!sizes) {
                return '';
            }
            return 'max-width:' + sizes.width + 'px;width:' + sizes.width + 'px;height:' + sizes.height + 'px;max-height:' + sizes.height + 'px;';
        }

        function applyPreviewCustomCss($templateItem, css) {
            $templateItem.find('.sc-export-preview-inline-css').text(css || '');
        }

        function formatPreviewHtmlCode(html) {
            return String(html || '')
                .replace(/></g, '>\n<')
                .replace(/\n\s*\n/g, '\n')
                .trim();
        }

        var previewSearchTimer = null;

        function buildColumnPaddingGroupHtml(templateKey, colIndex, values) {
            var colKey = 'column_' + colIndex;
            values = values || { width: 50, top: 0, right: 0, bottom: 0, left: 0 };
            return '' +
                '<div class="sc-column-padding-group" data-column="' + colKey + '">' +
                '  <h4>ستون ' + colIndex + '</h4>' +
                '  <div class="sc-padding-grid sc-column-settings-grid">' +
                '    <div class="sc-padding-item sc-column-width-item"><small>عرض (درصد نسبی)</small><input type="number" class="sc-column-width-input" min="5" max="95" step="1" name="templates[' + templateKey + '][column_widths][' + colKey + ']" value="' + (values.width || 50) + '"></div>' +
                '    <div class="sc-padding-item"><small>فاصله بالا (mm)</small><input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][column_paddings][' + colKey + '][top]" value="' + (values.top || 0) + '"></div>' +
                '    <div class="sc-padding-item"><small>فاصله راست (mm)</small><input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][column_paddings][' + colKey + '][right]" value="' + (values.right || 0) + '"></div>' +
                '    <div class="sc-padding-item"><small>فاصله پایین (mm)</small><input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][column_paddings][' + colKey + '][bottom]" value="' + (values.bottom || 0) + '"></div>' +
                '    <div class="sc-padding-item"><small>فاصله چپ (mm)</small><input type="number" class="sc-column-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][column_paddings][' + colKey + '][left]" value="' + (values.left || 0) + '"></div>' +
                '  </div>' +
                '</div>';
        }

        function collectColumnPaddingValues($templateItem) {
            var values = {};
            $templateItem.find('.sc-column-padding-group').each(function () {
                var colKey = $(this).attr('data-column');
                values[colKey] = {
                    top: parseFloat($(this).find('input[name$="[top]"]').val()) || 0,
                    right: parseFloat($(this).find('input[name$="[right]"]').val()) || 0,
                    bottom: parseFloat($(this).find('input[name$="[bottom]"]').val()) || 0,
                    left: parseFloat($(this).find('input[name$="[left]"]').val()) || 0
                };
            });
            return values;
        }

        function collectColumnWidthValues($templateItem) {
            var values = {};
            $templateItem.find('.sc-column-padding-group').each(function () {
                var colKey = $(this).attr('data-column');
                var width = parseFloat($(this).find('.sc-column-width-input').val());
                values[colKey] = !Number.isNaN(width) && width > 0 ? width : 50;
            });
            return values;
        }

        function buildLayoutGridTemplateColumns(columnWidths, columnsCount) {
            var parts = [];
            var total = 0;
            for (var i = 1; i <= columnsCount; i++) {
                var colKey = 'column_' + i;
                var weight = columnWidths && columnWidths[colKey] ? parseFloat(columnWidths[colKey]) : (100 / columnsCount);
                if (Number.isNaN(weight) || weight <= 0) {
                    weight = 100 / columnsCount;
                }
                parts.push(weight);
                total += weight;
            }
            if (total <= 0) {
                return 'repeat(' + columnsCount + ', minmax(0, 1fr))';
            }
            return parts.map(function (weight) {
                return 'minmax(0, ' + weight + 'fr)';
            }).join(' ');
        }

        function collectColumnSettingsValues($templateItem) {
            var merged = {};
            $templateItem.find('.sc-column-padding-group').each(function () {
                var colKey = $(this).attr('data-column');
                var width = parseFloat($(this).find('.sc-column-width-input').val());
                merged[colKey] = {
                    width: !Number.isNaN(width) && width > 0 ? width : 50,
                    top: parseFloat($(this).find('input[name$="[top]"]').val()) || 0,
                    right: parseFloat($(this).find('input[name$="[right]"]').val()) || 0,
                    bottom: parseFloat($(this).find('input[name$="[bottom]"]').val()) || 0,
                    left: parseFloat($(this).find('input[name$="[left]"]').val()) || 0
                };
            });
            return merged;
        }

        function rebalanceColumnPaddings($templateItem, columnsCount) {
            var templateKey = $templateItem.attr('data-template-key');
            var existing = collectColumnSettingsValues($templateItem);
            var defaultWidth = Math.round((100 / columnsCount) * 100) / 100;
            var html = '';
            for (var i = 1; i <= columnsCount; i++) {
                var colKey = 'column_' + i;
                var values = existing[colKey] || { width: defaultWidth, top: 0, right: 0, bottom: 0, left: 0 };
                if (!existing[colKey]) {
                    values.width = defaultWidth;
                }
                html += buildColumnPaddingGroupHtml(templateKey, i, values);
            }
            $templateItem.find('.sc-column-paddings-wrap').html(html);
        }

        function buildTemplateExtraSettingsHtml(templateKey) {
            var presetOptions = '';
            Object.keys(cardSizePresets).forEach(function (presetKey) {
                var selected = presetKey === 'id_card' ? ' selected' : '';
                presetOptions += '<option value="' + presetKey + '"' + selected + '>' + (cardSizePresets[presetKey].label || presetKey) + '</option>';
            });
            var columnPaddingHtml = buildColumnPaddingGroupHtml(templateKey, 1, { width: 50, top: 0, right: 0, bottom: 0, left: 0 }) +
                buildColumnPaddingGroupHtml(templateKey, 2, { width: 50, top: 0, right: 0, bottom: 0, left: 0 });
            return '' +
                '<div class="sc-row"><label>اندازه کارت</label><select class="sc-card-size-preset" name="templates[' + templateKey + '][card_size_preset]">' + presetOptions + '</select></div>' +
                '<div class="sc-row sc-card-size-custom-row" style="display:none;"><label>ابعاد سفارشی (سانتی‌متر)</label><div class="sc-padding-grid sc-card-size-custom-grid"><div class="sc-padding-item"><small>عرض</small><input type="number" class="sc-card-width-cm" min="1" max="30" step="0.1" name="templates[' + templateKey + '][card_width_cm]" value="8.5"></div><div class="sc-padding-item"><small>ارتفاع</small><input type="number" class="sc-card-height-cm" min="1" max="30" step="0.1" name="templates[' + templateKey + '][card_height_cm]" value="5.4"></div></div></div>' +
                '<div class="sc-row"><label>فاصله داخلی کارت (میلی‌متر)</label><div class="sc-padding-grid"><div class="sc-padding-item"><small>بالا</small><input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][card_padding_top]" value="3"></div><div class="sc-padding-item"><small>راست</small><input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][card_padding_right]" value="3"></div><div class="sc-padding-item"><small>پایین</small><input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][card_padding_bottom]" value="3"></div><div class="sc-padding-item"><small>چپ</small><input type="number" class="sc-card-padding-input" min="0" max="50" step="0.5" name="templates[' + templateKey + '][card_padding_left]" value="3"></div></div></div>' +
                '<div class="sc-row"><label>استایل تصاویر</label><select class="sc-image-style-select" name="templates[' + templateKey + '][image_style]"><option value="rounded" selected>مربع با گوشه‌های گرد (۱۰px)</option><option value="circle">دایره‌ای</option></select><p class="description">برای عکس پرسنلی، کارت ملی، بیمه ورزشی و QR در صورت انتخاب در قالب.</p></div>' +
                '<div class="sc-row"><label>اندازه فونت محتوا (پیکسل)</label><input type="number" class="sc-content-font-size-input" min="8" max="32" step="1" name="templates[' + templateKey + '][content_font_size]" value="13"></div>' +
                '<div class="sc-row sc-image-sizes-section" style="display:none;"><label>ابعاد تصاویر انتخاب‌شده (پیکسل)</label><p class="description">فقط برای فیلدهای تصویری که در قالب فعال هستند نمایش داده می‌شود.</p><div class="sc-image-sizes-wrap">' + buildImageSizesSectionHtml(templateKey) + '</div></div>' +
                '<div class="sc-row"><label>CSS سفارشی</label><textarea class="sc-export-custom-css-input" name="templates[' + templateKey + '][custom_css]" rows="8" placeholder="مثال: .sc-card { border-radius: 16px; }"></textarea><p class="description">روی پیش‌نمایش و خروجی چاپ/PDF اعمال می‌شود. از تگ style یا script استفاده نکنید.</p></div>' +
                '<div class="sc-column-paddings-section"><label>تنظیمات هر ستون چیدمان</label><p class="description">عرض نسبی (درصد) و فاصله داخلی هر ستون — مجموع عرض‌ها به‌صورت نسبی اعمال می‌شود.</p><div class="sc-column-paddings-wrap" data-template-key="' + templateKey + '">' + columnPaddingHtml + '</div></div>';
        }

        function getSamplePreviewRow() {
            return {
                full_name: 'علی محمدی',
                father_name: 'حسین',
                player_phone: '09121234567',
                skill_level: 'مبتدی',
                insurance_expiry_date_shamsi: '1405/06/15',
                national_id: '0012345678',
                personal_photo: ''
            };
        }

        function collectTemplatePreviewConfig($templateItem) {
            var templateKey = $templateItem.attr('data-template-key');
            var layoutColumnsCount = parseInt($templateItem.find('select[name$="[layout_columns_count]"]').val(), 10) || 2;
            layoutColumnsCount = Math.max(1, Math.min(4, layoutColumnsCount));
            var layoutColumns = {};
            for (var i = 1; i <= layoutColumnsCount; i++) {
                var colKey = 'column_' + i;
                layoutColumns[colKey] = [];
                $templateItem.find('.sc-layout-dropzone[data-column="' + colKey + '"] .sc-layout-chip').each(function () {
                    layoutColumns[colKey].push($(this).attr('data-field'));
                });
            }
            var preset = $templateItem.find('.sc-card-size-preset').val() || 'id_card';
            var dims = cardSizePresets[preset] || { width: 8.5, height: 5.4 };
            return {
                key: templateKey,
                background_color: $templateItem.find('input[name$="[background_color]"]').val() || '#ffffff',
                background_image: $templateItem.find('.sc-bg-image-input').val() || '',
                background_opacity: $templateItem.find('input[name$="[background_opacity]"]').val() || '0.2',
                content_font_family: $templateItem.find('select[name$="[content_font_family]"]').val() || 'IRANYekanXFaNum',
                image_only_mode: $templateItem.find('input[name$="[image_only_mode]"]').is(':checked') ? 1 : 0,
                card_footer_text: $templateItem.find('textarea[name$="[card_footer_text]"]').val() || '',
                card_size_preset: preset,
                card_width_cm: parseFloat($templateItem.find('.sc-card-width-cm').val()) || (dims.width || 8.5),
                card_height_cm: parseFloat($templateItem.find('.sc-card-height-cm').val()) || (dims.height || 5.4),
                card_padding_top: parseFloat($templateItem.find('input[name$="[card_padding_top]"]').val()) || 3,
                card_padding_right: parseFloat($templateItem.find('input[name$="[card_padding_right]"]').val()) || 3,
                card_padding_bottom: parseFloat($templateItem.find('input[name$="[card_padding_bottom]"]').val()) || 3,
                card_padding_left: parseFloat($templateItem.find('input[name$="[card_padding_left]"]').val()) || 3,
                column_paddings: collectColumnPaddingValues($templateItem),
                column_widths: collectColumnWidthValues($templateItem),
                image_style: $templateItem.find('.sc-image-style-select').val() || 'rounded',
                content_font_size: parseInt($templateItem.find('.sc-content-font-size-input').val(), 10) || 13,
                image_sizes: collectImageSizeValues($templateItem),
                custom_css: $templateItem.find('.sc-export-custom-css-input').val() || '',
                show_field_labels: $templateItem.find('.sc-show-field-labels-input').is(':checked') ? 1 : 0,
                layout_columns_count: layoutColumnsCount,
                layout: { columns: layoutColumns }
            };
        }

        function resolvePreviewFontFamily(fontKey) {
            var map = {
                IRANYekanXFaNum: '"IRANYekanXFaNum", Tahoma, Arial, sans-serif',
                Vazir: '"Vazir", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
                Shabnam: '"Shabnam", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
                Morabba: '"Morabba", "IRANYekanXFaNum", Tahoma, Arial, sans-serif',
                Tahoma: 'Tahoma, Arial, sans-serif',
                Arial: 'Arial, Tahoma, sans-serif'
            };
            return map[fontKey] || map.IRANYekanXFaNum;
        }

        function escapePreviewHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function buildPreviewFieldHtml(field, value, labels, showLabels) {
            var displayValue = value == null || value === '' ? '-' : String(value);
            var showLabel = shouldRenderFieldLabel(field, showLabels);
            var labelHtml = showLabel
                ? '<span class="sc-card-field-label">' + escapePreviewHtml(labels[field] || field) + ':</span> '
                : '';
            var extraClass = showLabel ? '' : ' sc-field-no-label';
            return '<div class="sc-card-field sc-export-field' + extraClass + '" id="sc-export-field-' + field + '" data-field="' + escapePreviewHtml(field) + '">' +
                labelHtml +
                '<span class="sc-card-field-value">' + escapePreviewHtml(displayValue) + '</span>' +
                '</div>';
        }

        function renderPreviewCard($templateItem, row, fields, labels) {
            var template = collectTemplatePreviewConfig($templateItem);
            var $host = $templateItem.find('.sc-export-template-preview-card-host');
            if (!$host.length) {
                return;
            }
            labels = labels || getTemplateFieldLabels($templateItem);
            row = row || getSamplePreviewRow();
            fields = fields && fields.length ? fields : getSelectedTemplateFields($templateItem);
            if (!fields.length) {
                fields = ['full_name', 'player_phone'];
            }

            var preset = template.card_size_preset;
            var dims = cardSizePresets[preset] || null;
            var widthCm = preset !== 'custom' && dims && dims.width ? parseFloat(dims.width) : template.card_width_cm;
            var heightCm = preset !== 'custom' && dims && dims.height ? parseFloat(dims.height) : template.card_height_cm;
            var imageOnlyMode = Number(template.image_only_mode) === 1;
            var layoutColumnsCount = template.layout_columns_count;
            var layoutColumns = template.layout.columns;
            var imageStyleClass = template.image_style === 'circle' ? 'sc-image-style-circle' : 'sc-image-style-rounded';
            var showLabels = Number(template.show_field_labels) !== 0;

            var rawFields = fields.filter(function (f) { return exportImageFields.indexOf(f) === -1; });
            var used = {};
            var columnsFields = [];
            for (var c = 0; c < layoutColumnsCount; c++) {
                columnsFields.push([]);
            }
            for (var ci = 1; ci <= layoutColumnsCount; ci++) {
                var colArr = Array.isArray(layoutColumns['column_' + ci]) ? layoutColumns['column_' + ci] : [];
                colArr.forEach(function (f) {
                    if (rawFields.indexOf(f) !== -1 && !used[f]) {
                        columnsFields[ci - 1].push(f);
                        used[f] = true;
                    }
                });
            }
            rawFields.forEach(function (f) {
                if (!used[f]) {
                    var minIdx = 0;
                    for (var j = 1; j < columnsFields.length; j++) {
                        if (columnsFields[j].length < columnsFields[minIdx].length) {
                            minIdx = j;
                        }
                    }
                    columnsFields[minIdx].push(f);
                }
            });

            var colsHtml = [];
            if (!imageOnlyMode) {
                columnsFields.forEach(function (fieldsInCol, colIndex) {
                    var colKey = 'column_' + (colIndex + 1);
                    var pad = template.column_paddings[colKey] || { top: 0, right: 0, bottom: 0, left: 0 };
                    var colHtml = '<div class="sc-card-col sc-export-card-col" id="sc-export-card-col-' + colKey + '" data-column="' + colKey + '" style="padding:' + pad.top + 'mm ' + pad.right + 'mm ' + pad.bottom + 'mm ' + pad.left + 'mm;">';
                    fieldsInCol.forEach(function (field) {
                        colHtml += buildPreviewFieldHtml(field, row[field], labels, showLabels);
                    });
                    if (!fieldsInCol.length) {
                        colHtml += '<div class="sc-card-field">&nbsp;</div>';
                    }
                    colHtml += '</div>';
                    colsHtml.push(colHtml);
                });
            }

            function buildImageBlock(imgField) {
                var value = row[imgField];
                var sizeStyle = getImageSizeStyle(template, imgField);
                var block = '<div class="sc-card-field sc-card-inline-image sc-card-inline-image--' + imgField + ' sc-export-field sc-export-field--' + imgField + ' sc-field-no-label" id="sc-export-field-' + imgField + '" data-field="' + imgField + '">';
                if (value && value !== '-') {
                    block += '<img class="' + imageStyleClass + '" style="' + sizeStyle + '" src="' + escapePreviewHtml(value) + '" alt="">';
                } else if (imgField === 'personal_photo') {
                    block += '<div class="sc-photo-placeholder ' + imageStyleClass + '" style="' + sizeStyle + '"><span class="sc-photo-placeholder-icon" aria-hidden="true">👤</span><span class="sc-photo-placeholder-text">عکس ندارد</span></div>';
                } else if (imgField === 'attendance_qr') {
                    block += '<div class="sc-photo-placeholder ' + imageStyleClass + '" style="' + sizeStyle + '"><span class="sc-photo-placeholder-icon" aria-hidden="true">▦</span><span class="sc-photo-placeholder-text">QR موجود نیست</span></div>';
                } else {
                    return '';
                }
                block += '</div>';
                return block;
            }

            exportImageFields.forEach(function (imgField) {
                if (fields.indexOf(imgField) === -1) {
                    return;
                }
                var block = buildImageBlock(imgField);
                if (!block) {
                    return;
                }
                if (imageOnlyMode) {
                    colsHtml.push(block);
                    return;
                }
                var targetCol = 0;
                for (var ti = 1; ti <= layoutColumnsCount; ti++) {
                    var arr = layoutColumns['column_' + ti] || [];
                    if (arr.indexOf(imgField) !== -1) {
                        targetCol = ti - 1;
                        break;
                    }
                }
                if (!colsHtml[targetCol]) {
                    colsHtml[targetCol] = '<div class="sc-card-col"></div>';
                }
                colsHtml[targetCol] = colsHtml[targetCol].replace(/<\/div>$/, block + '</div>');
            });

            var contentHtml;
            var gridColumns = buildLayoutGridTemplateColumns(template.column_widths, layoutColumnsCount);
            if (imageOnlyMode) {
                contentHtml = '<div class="sc-card-content sc-export-card-content" id="sc-export-card-content">' + colsHtml.join('') + '</div>';
            } else {
                contentHtml = '<div class="sc-card-content sc-export-card-content" id="sc-export-card-content" style="grid-template-columns:' + gridColumns + ';">' + colsHtml.join('') + '</div>';
            }

            var cardClass = 'sc-card sc-export-card' + (imageOnlyMode ? ' image-only' : '');
            var fontSize = parseInt(template.content_font_size, 10) || 13;
            var cardStyle = 'width:' + widthCm + 'cm;height:' + heightCm + 'cm;min-height:' + heightCm + 'cm;box-sizing:border-box;padding:' +
                template.card_padding_top + 'mm ' + template.card_padding_right + 'mm ' + template.card_padding_bottom + 'mm ' + template.card_padding_left + 'mm;font-size:' + fontSize + 'px;';
            if (!template.background_image) {
                cardStyle += 'background-color:' + template.background_color + ';';
            } else {
                cardStyle += 'background-color:transparent;';
            }

            var bgLayer = '';
            if (template.background_image) {
                bgLayer = '<div class="sc-card-background-layer" style="background-image:url(\'' + escapePreviewHtml(template.background_image) + '\');opacity:' + template.background_opacity + ';"></div>';
            }
            var footerHtml = '';
            if (template.card_footer_text && String(template.card_footer_text).trim() !== '') {
                footerHtml = '<div class="sc-card-additional-note sc-export-card-footer" id="sc-export-card-footer">' + escapePreviewHtml(template.card_footer_text).replace(/\n/g, '<br>') + '</div>';
            }

            $host.attr('data-image-style', template.image_style);
            $host.css('--sc-export-font-family', resolvePreviewFontFamily(template.content_font_family));
            $host.css('--sc-export-font-size', fontSize + 'px');
            var cardHtml = '<div class="' + cardClass + '" id="sc-export-card" style="' + cardStyle + '">' + bgLayer + contentHtml + footerHtml + '</div>';
            $host.html(cardHtml);
            applyPreviewCustomCss($templateItem, template.custom_css || '');
            $templateItem.find('.sc-export-template-html-code').val(formatPreviewHtmlCode(cardHtml));
        }

        function updateTemplatePreview($templateItem) {
            if (!$templateItem || !$templateItem.length || !$templateItem.hasClass('is-active')) {
                return;
            }
            syncImageSizesVisibility($templateItem);
            var fields = getSelectedTemplateFields($templateItem);
            var labels = getTemplateFieldLabels($templateItem);
            var previewRow = $templateItem.data('previewRow');
            var previewFields = $templateItem.data('previewFields');
            if (previewRow && previewFields) {
                renderPreviewCard($templateItem, previewRow, previewFields, labels);
            } else {
                renderPreviewCard($templateItem, getSamplePreviewRow(), fields, labels);
            }
        }

        function fetchPreviewMemberData($templateItem, memberId) {
            var fields = getSelectedTemplateFields($templateItem);
            var eventId = 0;
            if ($templateItem.find('.sc-is-event-template').is(':checked')) {
                eventId = parseInt($templateItem.find('.sc-template-event-id').val(), 10) || 0;
            }
            $.post(getTemplatesAjaxUrl(), {
                action: 'sc_users_export_template_preview_data',
                nonce: getTemplatesPreviewNonce(),
                member_id: memberId,
                fields: fields,
                event_id: eventId
            }).done(function (res) {
                if (!res || !res.success || !res.data) {
                    showTemplateNotice((res && res.data && res.data.message) ? res.data.message : 'خطا در دریافت داده پیش‌نمایش.', 'error');
                    return;
                }
                $templateItem.data('previewRow', res.data.row || {});
                $templateItem.data('previewFields', res.data.fields || fields);
                $templateItem.find('.sc-export-preview-member-label').text('داده نمونه: ' + (res.data.member_label || ('کاربر #' + memberId)));
                $templateItem.find('.sc-export-preview-member-clear').show();
                updateTemplatePreview($templateItem);
            });
        }

        function clearPreviewMember($templateItem) {
            $templateItem.removeData('previewRow');
            $templateItem.removeData('previewFields');
            $templateItem.find('.sc-export-preview-member-id').val('');
            $templateItem.find('.sc-export-preview-member-label').text('داده نمونه: اطلاعات پیش‌فرض');
            $templateItem.find('.sc-export-preview-member-clear').hide();
            $templateItem.find('.sc-export-preview-member-results').hide().empty();
            updateTemplatePreview($templateItem);
        }

        function activateTemplate(templateKey) {
            if (!templateKey) {
                return;
            }
            $('.sc-template-list-item, .sc-template-item').removeClass('is-active');
            $('.sc-template-list-item[data-template-key="' + templateKey + '"]').addClass('is-active');
            var $item = $('.sc-template-item[data-template-key="' + templateKey + '"]');
            $item.addClass('is-active');
            updateTemplatePreview($item);
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
            var memberId = parseInt($templateItem.find('.sc-export-preview-member-id').val(), 10);
            syncImageSizesVisibility($templateItem);
            if (memberId) {
                fetchPreviewMemberData($templateItem, memberId);
            } else {
                updateTemplatePreview($templateItem);
            }
        });

        $templatesContainer.on('change input', 'input, select, textarea', function () {
            if ($(this).hasClass('sc-export-preview-member-search') || $(this).hasClass('sc-export-template-html-code')) {
                return;
            }
            var $templateItem = $(this).closest('.sc-template-item');
            if (!$templateItem.length) {
                return;
            }
            updateTemplatePreview($templateItem);
        });

        $templatesContainer.on('input', '.sc-content-font-size-input', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            if ($templateItem.length) {
                updateTemplatePreview($templateItem);
            }
        });

        $templatesContainer.on('change', '.sc-card-size-preset', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            var isCustom = $(this).val() === 'custom';
            $templateItem.find('.sc-card-size-custom-row').toggle(isCustom);
            if (!isCustom) {
                var preset = cardSizePresets[$(this).val()];
                if (preset && preset.width) {
                    $templateItem.find('.sc-card-width-cm').val(preset.width);
                }
                if (preset && preset.height) {
                    $templateItem.find('.sc-card-height-cm').val(preset.height);
                }
            }
            updateTemplatePreview($templateItem);
        });

        $templatesContainer.on('input', '.sc-export-preview-member-search', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            var $results = $templateItem.find('.sc-export-preview-member-results');
            var q = ($(this).val() || '').trim();
            clearTimeout(previewSearchTimer);
            if (q.length < 2) {
                $results.hide().empty();
                return;
            }
            previewSearchTimer = setTimeout(function () {
                $.post(getTemplatesAjaxUrl(), {
                    action: 'sc_users_export_template_search_member',
                    nonce: getTemplatesPreviewNonce(),
                    q: q
                }).done(function (res) {
                    if (!res || !res.success) {
                        $results.html('<div class="sc-export-preview-member-empty">خطا در جستجو.</div>').show();
                        return;
                    }
                    var items = res.data.items || [];
                    if (!items.length) {
                        $results.html('<div class="sc-export-preview-member-empty">نتیجه‌ای یافت نشد.</div>').show();
                        return;
                    }
                    var html = '';
                    items.forEach(function (item) {
                        html += '<button type="button" class="sc-export-preview-member-option" data-member-id="' + item.id + '">' + escapePreviewHtml(item.label) + '</button>';
                    });
                    $results.html(html).show();
                }).fail(function () {
                    $results.html('<div class="sc-export-preview-member-empty">خطا در ارتباط با سرور.</div>').show();
                });
            }, 300);
        });

        $templatesContainer.on('mousedown', '.sc-export-preview-member-option', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $templateItem = $(this).closest('.sc-template-item');
            var memberId = parseInt($(this).attr('data-member-id'), 10);
            if (!memberId) {
                return;
            }
            $templateItem.find('.sc-export-preview-member-id').val(String(memberId));
            $templateItem.find('.sc-export-preview-member-search').val('');
            $templateItem.find('.sc-export-preview-member-results').hide().empty();
            fetchPreviewMemberData($templateItem, memberId);
        });

        $templatesContainer.on('click', '.sc-export-preview-member-clear', function () {
            clearPreviewMember($(this).closest('.sc-template-item'));
        });

        $(document).on('mousedown', function (e) {
            if (!$(e.target).closest('.sc-export-template-preview-member-picker').length) {
                $('.sc-export-preview-member-results').hide();
            }
        });

        function collectTemplateFormData($templateItem) {
            syncDropzoneInputs($templateItem);
            var preview = collectTemplatePreviewConfig($templateItem);
            return {
                key: preview.key,
                title: $templateItem.find('.sc-template-title-input').val() || '',
                description: $templateItem.find('textarea[name$="[description]"]').val() || '',
                is_event_template: $templateItem.find('.sc-is-event-template').is(':checked') ? 1 : 0,
                event_id: parseInt($templateItem.find('.sc-template-event-id').val(), 10) || 0,
                page_size: $templateItem.find('select[name$="[page_size]"]').val() || 'A4',
                cards_per_page: parseInt($templateItem.find('select[name$="[cards_per_page]"]').val(), 10) || 2,
                columns_count: parseInt($templateItem.find('select[name$="[columns_count]"]').val(), 10) || 2,
                layout_columns_count: preview.layout_columns_count,
                fields: getSelectedTemplateFields($templateItem),
                layout: preview.layout,
                background_color: preview.background_color,
                background_image: preview.background_image,
                background_opacity: preview.background_opacity,
                content_font_family: preview.content_font_family,
                image_only_mode: preview.image_only_mode,
                card_footer_text: preview.card_footer_text,
                card_size_preset: preview.card_size_preset,
                card_width_cm: preview.card_width_cm,
                card_height_cm: preview.card_height_cm,
                card_padding_top: preview.card_padding_top,
                card_padding_right: preview.card_padding_right,
                card_padding_bottom: preview.card_padding_bottom,
                card_padding_left: preview.card_padding_left,
                column_paddings: preview.column_paddings,
                column_widths: preview.column_widths,
                image_style: preview.image_style,
                content_font_size: preview.content_font_size,
                image_sizes: preview.image_sizes,
                custom_css: preview.custom_css,
                show_field_labels: preview.show_field_labels
            };
        }

        $('#sc-export-templates-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[name="sc_save_export_templates"]');
            var templates = {};
            $templatesContainer.find('.sc-template-item').each(function () {
                var data = collectTemplateFormData($(this));
                if (data && data.key) {
                    templates[data.key] = data;
                }
            });
            if (!Object.keys(templates).length) {
                showTemplateNotice('هیچ قالبی برای ذخیره یافت نشد.', 'error');
                return false;
            }
            var ajaxUrl = getTemplatesAjaxUrl();
            if (!ajaxUrl) {
                showTemplateNotice('آدرس AJAX در دسترس نیست.', 'error');
                return false;
            }
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('در حال ذخیره...');
            $.post(ajaxUrl, {
                action: 'sc_users_export_save_templates',
                nonce: getTemplatesSaveNonce(),
                templates_json: JSON.stringify(templates)
            }).done(function (res) {
                if (res && res.success) {
                    showTemplateNotice((res.data && res.data.message) ? res.data.message : 'ذخیره شد.', 'success');
                } else {
                    showTemplateNotice((res && res.data && res.data.message) ? res.data.message : 'خطا در ذخیره قالب.', 'error');
                }
            }).fail(function () {
                showTemplateNotice('خطا در ارتباط با سرور هنگام ذخیره.', 'error');
            }).always(function () {
                $btn.prop('disabled', false).text(originalText);
            });
            return false;
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
                buildTemplateExtraSettingsHtml(templateKey) +
                '  <div class="sc-row"><label>تعداد کارت در صفحه</label><select name="templates[' + templateKey + '][cards_per_page]"><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select></div>' +
                '  <div class="sc-row"><label>تعداد ستون خروجی</label><select name="templates[' + templateKey + '][columns_count]"><option value="1">1 ستونه</option><option value="2" selected>2 ستونه</option><option value="3">3 ستونه</option><option value="4">4 ستونه</option></select></div>' +
                '  <div class="sc-row"><label>تعداد ستون چیدمان فیلدها</label><select class="sc-layout-columns-count-select" name="templates[' + templateKey + '][layout_columns_count]"><option value="1">1 ستونه</option><option value="2" selected>2 ستونه</option><option value="3">3 ستونه</option><option value="4">4 ستونه</option></select></div>' +
                '  <div class="sc-row"><label>رنگ پس‌زمینه کارت</label><input type="color" name="templates[' + templateKey + '][background_color]" value="#ffffff"></div>' +
                '  <div class="sc-row"><label>تصویر پس‌زمینه کارت</label><div class="sc-bg-image-picker"><input type="text" class="sc-bg-image-input" name="templates[' + templateKey + '][background_image]" value="" placeholder="URL تصویر"><button type="button" class="button sc-select-bg-image">انتخاب تصویر</button></div></div>' +
                '  <div class="sc-row"><label>شفافیت تصویر پس‌زمینه (0 تا 1)</label><input type="number" min="0" max="1" step="0.05" name="templates[' + templateKey + '][background_opacity]" value="0.2"></div>' +
                '  <div class="sc-row"><label>فونت خروجی</label><select class="sc-content-font-family-select" name="templates[' + templateKey + '][content_font_family]"><option value="IRANYekanXFaNum" selected>IRANYekanXFaNum</option><option value="Vazir">Vazir</option><option value="Shabnam">Shabnam</option><option value="Morabba">Morabba</option><option value="Tahoma">Tahoma</option><option value="Arial">Arial</option></select></div>' +
                '  <div class="sc-row"><label class="sc-inline-check"><input type="checkbox" class="sc-show-field-labels-input" name="templates[' + templateKey + '][show_field_labels]" value="1" checked> نمایش عنوان فیلدها (مثل «نام و نام خانوادگی:»)</label></div>' +
                '  <div class="sc-row"><label class="sc-inline-check"><input type="checkbox" name="templates[' + templateKey + '][image_only_mode]" value="1"> خروجی فقط عکس باشد (تمام عرض، زیر هم، بک‌گراند شفاف)</label></div>' +
                '  <div class="sc-row"><label>متن اضافی زیر کارت</label><textarea name="templates[' + templateKey + '][card_footer_text]" rows="3" placeholder="متن دلخواه برای نمایش پایین هر کارت"></textarea></div>' +
                '  <div class="sc-fields-grid sc-template-base-fields">' + fieldsHtml + '</div>' +
                '  <div class="sc-fields-grid sc-template-event-fields-grid" style="display:none;"></div>' +
                '  <div class="sc-template-layout-builder" data-template="' + templateKey + '">' +
                '    <h3>چیدمان گرافیکی فیلدها</h3>' +
                '    <p class="description">فیلدها را با Drag & Drop بین ستون‌ها جابه‌جا کنید.</p>' +
                '    <div class="sc-layout-columns" data-columns-count="2">' + buildLayoutColumnsHtml(2) + '</div>' +
                '  </div>' +
                '  <div class="sc-row sc-export-template-preview-row"><label>پیش‌نمایش زنده کارت</label><div class="sc-export-template-preview-member-picker"><input type="search" class="sc-export-preview-member-search" placeholder="جستجوی بازیکن برای پیش‌نمایش..." autocomplete="off"><div class="sc-export-preview-member-results" style="display:none;"></div><div class="sc-export-preview-member-selected"><span class="sc-export-preview-member-label">داده نمونه: اطلاعات پیش‌فرض</span><button type="button" class="button-link sc-export-preview-member-clear" style="display:none;">حذف انتخاب</button></div><input type="hidden" class="sc-export-preview-member-id" value=""></div><div class="sc-export-template-preview-box"><style class="sc-export-preview-inline-css"></style><div class="sc-export-template-preview-scale"><div class="sc-export-template-preview-card-host"></div></div></div><div class="sc-export-template-html-preview"><label>کد HTML کارت (فقط نمایش)</label><textarea class="sc-export-template-html-code" rows="10" readonly dir="ltr" spellcheck="false" placeholder="پس از تنظیم قالب، HTML تولیدشده اینجا نمایش داده می‌شود..."></textarea></div></div>' +
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
            syncImageSizesVisibility($newCard);
            updateTemplatePreview($newCard);
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
                var memberId = parseInt($templateItem.find('.sc-export-preview-member-id').val(), 10);
                if (memberId) {
                    fetchPreviewMemberData($templateItem, memberId);
                } else {
                    updateTemplatePreview($templateItem);
                }
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
            syncDropzoneInputs($item);
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
            if ($item.find('.sc-card-size-preset').val() === 'custom') {
                $item.find('.sc-card-size-custom-row').show();
            }
            syncImageSizesVisibility($item);
        });

        var $activeTemplate = $templatesContainer.find('.sc-template-item.is-active').first();
        if ($activeTemplate.length) {
            updateTemplatePreview($activeTemplate);
        }
    }
});
