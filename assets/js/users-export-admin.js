jQuery(function ($) {
    'use strict';

    var $form = $('#sc-users-export-form');
    var $templatesContainer = $('#sc-templates-container');

    // --------------------------
    // Export page logic
    // --------------------------
    if ($form.length) {
        var selectedMemberIds = [];
        var currentTemplate = null;

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

    function getSelectedFields() {
        var selected = [];
        $('input[name="fields[]"]:checked').each(function () {
            selected.push($(this).val());
        });
        return selected;
    }

    function resolveTemplateLayout(fields) {
        var filtered = fields.filter(function (f) { return f !== 'personal_photo'; });
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
                fields.indexOf('sport_insurance_photo') !== -1
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
            $('input[name="fields[]"][value="sport_insurance_photo"]').is(':checked');
        var $format = $('#sc-export-format');
        if (hasPhoto) {
            $format.val('pdf');
            $format.find('option[value="excel"]').prop('disabled', true);
        } else {
            $format.find('option[value="excel"]').prop('disabled', false);
        }
    }

    function applyTemplateToForm(template) {
        currentTemplate = template || null;
        if (!template) {
            updatePreview();
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
            var $first = $templateItem.find('.sc-layout-dropzone').first();
            var exists = $templateItem.find('.sc-layout-chip[data-field="' + fieldKey + '"]').length > 0;
            if (!exists) {
                $first.append('<div class="sc-layout-chip" draggable="true" data-field="' + fieldKey + '">' + (fieldLabels[fieldKey] || fieldKey) + '</div>');
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
                    .append('<div class="sc-layout-chip" draggable="true" data-field="' + fieldKey + '">' + (fieldLabels[fieldKey] || fieldKey) + '</div>');
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
                '  <div class="sc-fields-grid">' + fieldsHtml + '</div>' +
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
            if (!window.confirm('این قالب حذف شود؟')) {
                return;
            }
            $item.remove();
            $card.remove();
            var $first = $('.sc-template-list-item').first();
            if ($first.length) {
                activateTemplate($first.attr('data-template-key'));
            }
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
    }
});
