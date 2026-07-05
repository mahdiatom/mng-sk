(function ($) {
    'use strict';

    function getSelectedValues($picker) {
        var values = [];
        $picker.find('.sc-audience-course-tag').each(function () {
            var storeVal = $(this).attr('data-store-value') || $(this).attr('data-value') || '';
            if (storeVal !== '') {
                values.push(storeVal);
            }
        });
        return values;
    }

    function syncHiddenInputs($picker) {
        var name = $picker.attr('data-input-name') || 'course_ids[]';
        var $wrap = $picker.find('.sc-audience-course-hidden-inputs');
        $wrap.empty();
        $picker.find('.sc-audience-course-tag').each(function () {
            var storeVal = $(this).attr('data-store-value') || $(this).attr('data-value') || '';
            if (storeVal !== '') {
                $wrap.append(
                    $('<input>', { type: 'hidden', name: name, value: storeVal })
                );
            }
        });
    }

    function renderTags($picker) {
        var $tags = $picker.find('.sc-audience-course-tags');
        var hasTags = $picker.find('.sc-audience-course-tag').length > 0;
        $tags.find('.sc-audience-course-tags-empty').remove();
        if (!hasTags) {
            $tags.html('<span class="sc-audience-course-tags-empty">هنوز دوره‌ای انتخاب نشده است.</span>');
        }
        syncHiddenInputs($picker);
        $picker.trigger('sc-audience-course-change', [getSelectedValues($picker)]);
    }

    function isValueSelected($picker, pickerValue) {
        var found = false;
        $picker.find('.sc-audience-course-tag').each(function () {
            if (($(this).attr('data-value') || '') === pickerValue) {
                found = true;
                return false;
            }
        });
        return found;
    }

    function addSelection($picker, pickerValue, storeValue, label) {
        if (!pickerValue || isValueSelected($picker, pickerValue)) {
            return;
        }
        var storeCourseIdOnly = $picker.attr('data-store-course-id-only') === '1';
        if (storeCourseIdOnly) {
            var existingCourseIds = {};
            $picker.find('.sc-audience-course-tag').each(function () {
                existingCourseIds[$(this).attr('data-store-value') || ''] = true;
            });
            if (existingCourseIds[storeValue]) {
                return;
            }
        }

        $picker.find('.sc-audience-course-tags-empty').remove();
        var $tag = $(
            '<span class="sc-audience-course-tag"></span>'
        )
            .attr('data-value', pickerValue)
            .attr('data-store-value', storeValue)
            .append($('<span class="sc-audience-course-tag__label"></span>').text(label))
            .append(
                $('<button type="button" class="sc-audience-course-tag__remove" aria-label="حذف">&times;</button>')
            );
        $picker.find('.sc-audience-course-tags').append($tag);
        renderTags($picker);
    }

    function markSelectedOptions($picker) {
        $picker.find('.sc-audience-course-option').each(function () {
            var val = $(this).attr('data-value') || '';
            if (isValueSelected($picker, val)) {
                $(this).addClass('sc-audience-course-option--picked');
            } else {
                $(this).removeClass('sc-audience-course-option--picked');
            }
        });
    }

    function initPicker($picker) {
        if (!$picker.length || $picker.data('scAudienceCourseInit')) {
            return;
        }
        $picker.data('scAudienceCourseInit', true);
        markSelectedOptions($picker);
        syncHiddenInputs($picker);
    }

    function initAll() {
        $('.sc-audience-course-picker').each(function () {
            initPicker($(this));
        });
    }

    $(document).on('click', '.sc-audience-course-picker .sc-dropdown-toggle', function (e) {
        e.stopPropagation();
        var $menu = $(this).siblings('.sc-dropdown-menu');
        var isOpen = $menu.is(':visible');
        $('.sc-audience-course-picker .sc-dropdown-menu').slideUp(150);
        if (!isOpen) {
            $menu.slideDown(150);
            setTimeout(function () {
                $menu.find('.sc-audience-course-search, .sc-search-input').first().trigger('focus');
            }, 160);
            markSelectedOptions($(this).closest('.sc-audience-course-picker'));
        }
    });

    $(document).on('input', '.sc-audience-course-picker .sc-audience-course-search, .sc-audience-course-picker .sc-search-input', function () {
        var term = ($(this).val() || '').toLowerCase().trim();
        var $options = $(this).closest('.sc-dropdown-menu').find('.sc-audience-course-option');
        var visible = 0;
        var maxVisible = 12;
        $options.closest('.sc-dropdown-options').find('.sc-audience-course-no-results').remove();

        if (term === '') {
            $options.each(function (index) {
                if (index < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visible++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
            return;
        }

        $options.each(function () {
            var search = ($(this).attr('data-search') || '').toLowerCase();
            if (search.indexOf(term) !== -1 && visible < maxVisible) {
                $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                visible++;
            } else {
                $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
            }
        });

        if (visible === 0) {
            $(this).closest('.sc-dropdown-options').append(
                '<div class="sc-audience-course-no-results" style="padding:12px;text-align:center;color:#646970;">نتیجه‌ای یافت نشد</div>'
            );
        }
    });

    document.addEventListener('click', function (e) {
        var option = e.target.closest('.sc-audience-course-option');
        if (!option) {
            return;
        }
        var $picker = $(option).closest('.sc-audience-course-picker');
        if (!$picker.length) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        var pickerValue = option.getAttribute('data-value') || '';
        var storeValue = option.getAttribute('data-store-value') || pickerValue;
        var label = option.getAttribute('data-label') || $(option).find('.sc-audience-course-option__label').text() || pickerValue;

        addSelection($picker, pickerValue, storeValue, label);
        markSelectedOptions($picker);
        $picker.find('.sc-dropdown-menu').slideUp(150);
        $picker.find('.sc-audience-course-search, .sc-search-input').val('');
        $picker.find('.sc-audience-course-option').removeClass('sc-hidden sc-visible').each(function (i) {
            $(this).toggle(i < 12);
        });
        $picker.find('.sc-audience-course-no-results').remove();
    }, true);

    $(document).on('click', '.sc-audience-course-tag__remove', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.sc-audience-course-picker');
        $(this).closest('.sc-audience-course-tag').remove();
        renderTags($picker);
        markSelectedOptions($picker);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.sc-audience-course-picker').length) {
            $('.sc-audience-course-picker .sc-dropdown-menu').slideUp(150);
        }
    });

    $(document).on('click', '.sc-audience-course-picker .sc-dropdown-menu', function (e) {
        e.stopPropagation();
    });

    var AUDIENCE_WRAP = '.sc-users-export-wrap, .sc-bulk-actions-wrap, .sc-notification-add-wrap, .sc-finance-page-body, .sc-discount-add-page-body, .sc-private-notes-wrap, [class*="_page_sc-bale-bot"]';
    var AUDIENCE_PANEL = '.sc-bulk-panel--filter, .sc-notification-filter-panel, .sc-bale-filter-panel, .sc-invoice-panel--filter, .sc-private-note-filter-panel, .sc-users-export-card, .sc-cert-card, .sc-discount-add-panel--limits, .sc-discount-add-panel--basic';
    var AUDIENCE_PREVIEW_PANEL = '.sc-bulk-panel--preview, .sc-notification-preview-panel, .sc-bale-preview-panel, .sc-invoice-panel--preview, .sc-private-note-filter-panel, .sc-users-export-wrap .sc-users-export-card';

    function inAudienceContext($el) {
        return $el.closest(AUDIENCE_WRAP).length > 0;
    }

    function findAudienceSurface($el) {
        if ($el.closest('.sc-audience-preview-add-wrap, .sc-audience-preview-add-dropdown').length) {
            var $previewPanel = $el.closest(AUDIENCE_PREVIEW_PANEL);
            if ($previewPanel.length) {
                return $previewPanel;
            }
        }

        var $panel = $el.closest(AUDIENCE_PANEL);
        if ($panel.length) {
            return $panel;
        }
        return $el.closest('.sc-filter-block, .target-row, .course-ids-row, .sc-bulk-field-row, .sc-discount-field-row');
    }

    function clearAudienceDropdownStack() {
        $(AUDIENCE_WRAP).find('.sc-audience-dropdown-active').removeClass('sc-audience-dropdown-active');
        $(AUDIENCE_WRAP).find('.sc-dropdown-open').removeClass('sc-dropdown-open');
    }

    function activateAudienceDropdown($root) {
        if (!$root || !$root.length || !inAudienceContext($root)) {
            return;
        }
        clearAudienceDropdownStack();
        $root.addClass('sc-dropdown-open');
        findAudienceSurface($root).addClass('sc-audience-dropdown-active');
    }

    function syncAudienceDropdownState($root) {
        if (!$root || !$root.length) {
            return;
        }
        var $menu = $root.find('.sc-dropdown-menu, .sc-users-dropdown-menu').first();
        if ($menu.length && $menu.is(':visible')) {
            activateAudienceDropdown($root);
        } else {
            $root.removeClass('sc-dropdown-open');
            var $surface = findAudienceSurface($root);
            if (!$surface.find('.sc-dropdown-open').length) {
                $surface.removeClass('sc-audience-dropdown-active');
            }
        }
    }

    window.scAudienceDropdownStack = {
        activate: activateAudienceDropdown,
        clear: clearAudienceDropdownStack,
        sync: syncAudienceDropdownState
    };

    $(document).on('click', '.sc-audience-course-picker .sc-dropdown-toggle', function () {
        var $root = $(this).closest('.sc-audience-course-picker');
        setTimeout(function () {
            syncAudienceDropdownState($root);
        }, 180);
    });

    $(document).on('click', AUDIENCE_WRAP + ' .sc-dropdown-toggle', function () {
        var $root = $(this).closest('.sc-searchable-dropdown, .sc-audience-course-picker');
        if (!$root.length || $root.hasClass('sc-attendance-course-dropdown') || $root.hasClass('sc-attendance-course-filter-dropdown')) {
            return;
        }
        setTimeout(function () {
            syncAudienceDropdownState($root);
        }, 220);
    });

    $(document).on('click', AUDIENCE_WRAP + ' .sc-users-dropdown-toggle', function () {
        var $root = $(this).closest('.sc-users-member-dropdown, .sc-audience-preview-add-dropdown');
        setTimeout(function () {
            syncAudienceDropdownState($root);
        }, 180);
    });

    $(document).on('click', function (e) {
        if ($(e.target).closest('.sc-audience-course-picker, .sc-users-member-dropdown, .sc-searchable-dropdown, .sc-audience-preview-add-dropdown').length) {
            return;
        }
        clearAudienceDropdownStack();
    });

    window.scAudienceCoursePickerGetValues = function (root) {
        var $root = root ? $(root) : $('.sc-audience-course-picker').first();
        if ($root.hasClass('sc-audience-course-picker')) {
            return getSelectedValues($root);
        }
        return getSelectedValues($root.find('.sc-audience-course-picker').first());
    };

    $(initAll);
})(jQuery);
