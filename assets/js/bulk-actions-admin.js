(function ($) {
    'use strict';

    var selectedMemberIds = [];
    var excludedMemberIds = [];
    var courseCoachesMap = {};

    function loadCourseCoachesMap() {
        var raw = $('#sc-bulk-actions-form').attr('data-course-coaches');
        if (!raw) {
            courseCoachesMap = {};
            return;
        }
        try {
            courseCoachesMap = JSON.parse(raw) || {};
        } catch (e) {
            courseCoachesMap = {};
        }
    }

    function refreshAssignCoachOptions() {
        var $courseSelect = $('#sc-assign-course-id');
        var $coachSelect = $('#sc-assign-coach-id');
        if (!$courseSelect.length || !$coachSelect.length) {
            return;
        }

        var courseId = $courseSelect.val();
        var coaches = (courseId && courseCoachesMap[courseId]) ? courseCoachesMap[courseId] : [];
        $coachSelect.empty();

        if (!courseId) {
            $coachSelect.append('<option value="">ابتدا دوره را انتخاب کنید</option>');
            return;
        }
        if (!coaches.length) {
            $coachSelect.append('<option value="">مربی فعالی برای این دوره یافت نشد</option>');
            return;
        }

        $coachSelect.append('<option value="">انتخاب مربی</option>');
        coaches.forEach(function (coach) {
            $coachSelect.append('<option value="' + coach.id + '">' + coach.label + '</option>');
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
            $('#sc-filter-team, #sc-filter-level').show();
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

        $(document).on('click', '#sc-selected-members .sc-remove-tag', function () {
            var $tag = $(this).closest('.sc-tag');
            var id = parseInt($tag.attr('data-id'), 10);
            selectedMemberIds = selectedMemberIds.filter(function (item) {
                return item.id !== id;
            });
            renderSelectedMembers();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#sc-users-member-dropdown').length) {
                $menu.slideUp(150);
            }
        });
    }

    function toggleActionFields() {
        var action = $('#sc-bulk-action-type').val();
        $('.sc-action-extra').hide();

        if (action === 'change_team') {
            $('#sc-action-change-team').show();
        } else if (action === 'change_level') {
            $('#sc-action-change-level').show();
        } else if (action === 'change_member_type') {
            $('#sc-action-change-type').show();
        } else if (action === 'assign_course_coach') {
            $('#sc-action-assign-course-coach').show();
            refreshAssignCoachOptions();
        } else if (action === 'course_activate' || action === 'course_deactivate') {
            $('#sc-action-course-common').show();
        } else if (action === 'course_set_flag') {
            $('#sc-action-course-common').show();
            $('#sc-action-course-flag').show();
        } else if (action === 'remaining_sessions_adjust') {
            $('#sc-action-course-common').show();
            $('#sc-action-remaining-sessions').show();
        }
    }

    function buildFormPayload() {
        var form = $('#sc-bulk-actions-form');
        var data = form.serializeArray();
        data.push({ name: 'action', value: 'sc_bulk_actions_preview' });
        data.push({ name: 'nonce', value: (window.scBulkActions && scBulkActions.nonce) ? scBulkActions.nonce : '' });
        return data;
    }

    function syncExcludedMemberInputs() {
        var $inputs = $('#sc-bulk-excluded-members-inputs');
        if (!$inputs.length) {
            return;
        }
        $inputs.empty();
        excludedMemberIds.forEach(function (id) {
            $inputs.append('<input type="hidden" name="excluded_member_ids[]" value="' + id + '">');
        });
    }

    function initPreviewSelectionBindings() {
        var $checks = $('.sc-bulk-preview-member-check');
        if (!$checks.length) {
            return;
        }

        $checks.each(function () {
            var id = parseInt($(this).attr('data-member-id'), 10);
            if (id && excludedMemberIds.indexOf(id) !== -1) {
                $(this).prop('checked', false);
            }
        });

        var checkedCount = $('.sc-bulk-preview-member-check:checked').length;
        $('#sc-bulk-preview-select-all').prop('checked', checkedCount === $checks.length);

        $(document).off('change.scBulkPreviewMember').on('change.scBulkPreviewMember', '.sc-bulk-preview-member-check', function () {
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

            var allCount = $('.sc-bulk-preview-member-check').length;
            var selectedCount = $('.sc-bulk-preview-member-check:checked').length;
            $('#sc-bulk-preview-select-all').prop('checked', allCount > 0 && selectedCount === allCount);
        });

        $(document).off('change.scBulkPreviewSelectAll').on('change.scBulkPreviewSelectAll', '#sc-bulk-preview-select-all', function () {
            var shouldCheck = $(this).is(':checked');
            $('.sc-bulk-preview-member-check').prop('checked', shouldCheck).trigger('change');
        });

        syncExcludedMemberInputs();
    }

    function validateBeforeSubmit() {
        var action = $('#sc-bulk-action-type').val();
        if (!action) {
            alert('نوع عملیات را انتخاب کنید.');
            return false;
        }

        if (action === 'change_team' && !$('#sc-action-team-player').val()) {
            alert('تیم جدید را انتخاب کنید.');
            return false;
        }
        if (action === 'change_level' && !$('#sc-action-skill-level').val()) {
            alert('سطح جدید را انتخاب کنید.');
            return false;
        }
        if ((action === 'course_activate' || action === 'course_deactivate' || action === 'course_set_flag' || action === 'remaining_sessions_adjust')
            && ($('#sc-action-course-ids').val() || []).length === 0) {
            alert('حداقل یک دوره برای عملیات دوره ای انتخاب کنید.');
            return false;
        }
        if (action === 'remaining_sessions_adjust') {
            var mode = $('#sc-remaining-sessions-mode').val();
            if (!mode || ['set', 'add', 'subtract'].indexOf(mode) === -1) {
                alert('نحوهٔ تغییر جلسات را انتخاب کنید.');
                return false;
            }
            var rawAmt = ($('#sc-remaining-sessions-amount').val() || '').toString().trim();
            if (rawAmt === '' || !/^\d+$/.test(rawAmt)) {
                alert('مقدار جلسات را به صورت عدد صحیح غیرمنفی (۰ یا بیشتر) وارد کنید.');
                return false;
            }
        }
        if (action === 'course_set_flag' && !$('#sc-action-course-flag-select').val()) {
            alert('فلگ دوره را انتخاب کنید.');
            return false;
        }
        if (action === 'assign_course_coach') {
            if (!$('#sc-assign-course-id').val()) {
                alert('برای اختصاص مربی، ابتدا دوره را انتخاب کنید.');
                return false;
            }
            if (!$('#sc-assign-coach-id').val()) {
                alert('لطفاً مربی دوره را انتخاب کنید.');
                return false;
            }
        }
        return true;
    }

    $(document).ready(function () {
        loadCourseCoachesMap();
        toggleFilterBlocks();
        renderSelectedMembers();
        bindSearchableDropdown();
        toggleActionFields();
        syncExcludedMemberInputs();
        $('#sc-target-type').on('change', toggleFilterBlocks);
        $('#sc-bulk-action-type').on('change', toggleActionFields);
        $('#sc-assign-course-id').on('change', refreshAssignCoachOptions);

        $('#sc-bulk-preview-btn').on('click', function () {
            var $btn = $(this);
            var $result = $('#sc-bulk-preview-result');
            $btn.prop('disabled', true);
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');

            $.post(
                (window.scBulkActions && scBulkActions.ajaxurl) ? scBulkActions.ajaxurl : ajaxurl,
                buildFormPayload()
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

        $('#sc-bulk-actions-form').on('submit', function (e) {
            if (!validateBeforeSubmit()) {
                e.preventDefault();
                return;
            }

            if ($('#sc-bulk-action-type').val() === 'delete_members') {
                e.preventDefault();
                var form = this;
                window.scConfirm({
                    type: 'danger',
                    message: 'حذف گروهی انتخاب شده است. آیا مطمئن هستید؟ این عمل قابل بازگشت نیست.'
                }).then(function (ok) {
                    if (ok) {
                        form.submit();
                    }
                });
            }
        });
    });
})(jQuery);
