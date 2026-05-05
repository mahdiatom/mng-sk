jQuery(function ($) {
    'use strict';

    var $form = $('#sc-users-export-form');
    var $templatesContainer = $('#sc-templates-container');

    // --------------------------
    // Export page logic
    // --------------------------
    if ($form.length) {
        var selectedMemberIds = [];

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
            $menu.slideToggle(150);
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
                $menu.slideUp(150);
            }
        });
    }

    function updatePreview() {
        var pageSize = $('#sc-page-size').val();
        var cards = parseInt($('#sc-cards-per-page').val(), 10) || 2;
        $('#sc-template-preview-meta').text(pageSize + ' - ' + cards + ' کارت در صفحه');
        renderLayoutPreview(pageSize, cards);
    }

    function renderLayoutPreview(pageSize, cards) {
        var $grid = $('#sc-template-preview-grid');
        $grid.empty();
        $grid.attr('data-size', pageSize);
        $grid.attr('data-cards', cards);

        var maxCards = Math.min(cards, 4);
        for (var i = 0; i < maxCards; i++) {
            $grid.append(
                '<div class="sc-mini-card">' +
                '  <div class="sc-mini-photo"></div>' +
                '  <div class="sc-mini-lines">' +
                '    <span></span><span></span><span></span>' +
                '  </div>' +
                '</div>'
            );
        }
    }

    function enforceFormatRules() {
        var hasPhoto = $('input[name="fields[]"][value="personal_photo"]').is(':checked');
        var $format = $('#sc-export-format');
        if (hasPhoto) {
            $format.val('pdf');
            $format.find('option[value="excel"]').prop('disabled', true);
        } else {
            $format.find('option[value="excel"]').prop('disabled', false);
        }
    }

    function applyTemplateToForm(template) {
        if (!template) {
            return;
        }
        if (!$('#sc-override-template-fields').is(':checked') && Array.isArray(template.fields)) {
            $('input[name="fields[]"]').prop('checked', false);
            template.fields.forEach(function (fieldKey) {
                $('input[name="fields[]"][value="' + fieldKey + '"]').prop('checked', true);
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

        $('#sc-target-type').on('change', toggleFilterBlocks);
        $('input[name="fields[]"]').on('change', enforceFormatRules);
        $('#sc-page-size, #sc-cards-per-page').on('change', updatePreview);

        $(document).on('click', '.sc-remove-tag', function () {
        var id = parseInt($(this).closest('.sc-tag').attr('data-id'), 10);
        selectedMemberIds = selectedMemberIds.filter(function (item) {
            return item.id !== id;
        });
        renderSelectedMembers();
    });

        $('#sc-template-key').on('change', function () {
        var selected = $(this).find(':selected');
        var raw = selected.attr('data-template');
        if (!raw) {
            return;
        }
        try {
            var template = JSON.parse(raw);
            applyTemplateToForm(template);
        } catch (err) {
            // ignore invalid json
        }
    });

        $form.on('submit', function () {
        var targetType = $('#sc-target-type').val();
        if (targetType === 'specific' && !selectedMemberIds.length) {
            alert('حداقل یک کاربر انتخاب کنید.');
            return false;
        }
        return true;
    });

        bindSearchableDropdown();
        toggleFilterBlocks();
        renderSelectedMembers();
        updatePreview();
        enforceFormatRules();
    }

    // --------------------------
    // Templates page logic
    // --------------------------
    if ($templatesContainer.length) {
        var fieldLabelsRaw = $('#sc-template-field-labels').attr('data-fields') || '{}';
        var fieldLabels = {};
        try {
            fieldLabels = JSON.parse(fieldLabelsRaw);
        } catch (e) {
            fieldLabels = {};
        }

        function syncDropzoneInputs($templateItem) {
            var templateKey = $templateItem.attr('data-template-key');
            if (!templateKey) {
                return;
            }
            $templateItem.find('.sc-layout-dropzone').each(function () {
                var side = $(this).attr('data-side');
                $(this).find('input[type="hidden"]').remove();
                $(this).find('.sc-layout-chip').each(function () {
                    var field = $(this).attr('data-field');
                    $(this).after(
                        '<input type="hidden" name="templates[' + templateKey + '][layout][' + side + '_fields][]" value="' + field + '">'
                    );
                });
            });
        }

        function ensureChipInRight($templateItem, fieldKey) {
            var $right = $templateItem.find('.sc-layout-dropzone[data-side="right"]');
            var exists = $templateItem.find('.sc-layout-chip[data-field="' + fieldKey + '"]').length > 0;
            if (!exists) {
                $right.append('<div class="sc-layout-chip" draggable="true" data-field="' + fieldKey + '">' + (fieldLabels[fieldKey] || fieldKey) + '</div>');
            }
            syncDropzoneInputs($templateItem);
        }

        function removeChipEverywhere($templateItem, fieldKey) {
            $templateItem.find('.sc-layout-chip[data-field="' + fieldKey + '"]').remove();
            syncDropzoneInputs($templateItem);
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
            });
            $scope.on('dragleave', '.sc-layout-dropzone', function () {
                $(this).removeClass('is-over');
            });
            $scope.on('drop', '.sc-layout-dropzone', function (e) {
                e.preventDefault();
                $(this).removeClass('is-over');
                if (dragged) {
                    this.appendChild(dragged);
                    var $templateItem = $(this).closest('.sc-template-item');
                    syncDropzoneInputs($templateItem);
                }
            });
        }

        bindDnd($templatesContainer);

        $templatesContainer.on('change', '.sc-fields-grid input[type="checkbox"]', function () {
            var $templateItem = $(this).closest('.sc-template-item');
            var fieldKey = $(this).val();
            if ($(this).is(':checked')) {
                ensureChipInRight($templateItem, fieldKey);
            } else {
                removeChipEverywhere($templateItem, fieldKey);
            }
        });

        function buildTemplateCard(templateKey) {
            var fieldsHtml = '';
            Object.keys(fieldLabels).forEach(function (fieldKey) {
                fieldsHtml += '<label class="sc-inline-check"><input type="checkbox" name="templates[' + templateKey + '][fields][]" value="' + fieldKey + '"> ' + fieldLabels[fieldKey] + '</label>';
            });

            return '' +
                '<div class="sc-users-export-card sc-template-item" data-template-key="' + templateKey + '">' +
                '  <h2>قالب جدید <small>(' + templateKey + ')</small></h2>' +
                '  <input type="hidden" name="templates[' + templateKey + '][key]" value="' + templateKey + '">' +
                '  <div class="sc-row"><label>عنوان قالب</label><input type="text" name="templates[' + templateKey + '][title]" value="قالب جدید"></div>' +
                '  <div class="sc-row"><label>توضیحات</label><textarea name="templates[' + templateKey + '][description]" rows="3"></textarea></div>' +
                '  <div class="sc-row"><label>اندازه صفحه</label><select name="templates[' + templateKey + '][page_size]"><option value="A4">A4</option><option value="A5">A5</option></select></div>' +
                '  <div class="sc-row"><label>تعداد کارت در صفحه</label><select name="templates[' + templateKey + '][cards_per_page]"><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select></div>' +
                '  <div class="sc-row"><label>جایگاه عکس</label><select name="templates[' + templateKey + '][layout][photo_position]"><option value="left">سمت چپ</option><option value="right">سمت راست</option><option value="none">بدون عکس</option></select></div>' +
                '  <div class="sc-fields-grid">' + fieldsHtml + '</div>' +
                '  <div class="sc-template-layout-builder" data-template="' + templateKey + '">' +
                '    <h3>چیدمان گرافیکی فیلدها</h3>' +
                '    <p class="description">فیلدها را با Drag & Drop بین ستون‌ها جابه‌جا کنید.</p>' +
                '    <div class="sc-layout-columns">' +
                '      <div class="sc-layout-column"><h4>ستون راست</h4><div class="sc-layout-dropzone" data-side="right"></div></div>' +
                '      <div class="sc-layout-column"><h4>ستون چپ</h4><div class="sc-layout-dropzone" data-side="left"></div></div>' +
                '    </div>' +
                '  </div>' +
                '</div>';
        }

        $('#sc-add-new-template').on('click', function () {
            var templateKey = 'tpl_' + Date.now();
            $templatesContainer.append(buildTemplateCard(templateKey));
        });
    }
});
