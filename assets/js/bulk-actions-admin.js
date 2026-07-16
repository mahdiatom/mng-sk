(function ($) {
    'use strict';

    var selectedMemberIds = [];
    var excludedMemberIds = [];
    var courseCoachesMap = {};
    var activateBranchState = {};

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

    function getSelectedActivateCourseIds() {
        return ($('#sc-action-course-ids').val() || [])
            .map(function (v) { return parseInt(v, 10); })
            .filter(function (id) { return id > 0; });
    }

    function getCourseOptionTitle(courseId) {
        var $opt = $('#sc-action-course-ids option[value="' + courseId + '"]');
        return $opt.length ? $opt.text() : ('دوره #' + courseId);
    }

    function getCourseData(courseId) {
        return courseCoachesMap[courseId] || courseCoachesMap[String(courseId)] || null;
    }

    function getCourseChapters(courseId) {
        var courseData = getCourseData(courseId);
        return courseData && courseData.chapters ? courseData.chapters : [];
    }

    function getCoachesForChapter(courseId, chapterName) {
        var courseData = getCourseData(courseId);
        if (!courseData || !courseData.coaches || !chapterName) {
            return [];
        }
        return courseData.coaches[chapterName] || [];
    }

    function getCourseGroups(courseId) {
        var courseData = getCourseData(courseId);
        if (!courseData || !courseData.groups) {
            return [];
        }
        return courseData.groups;
    }

    function courseHasGrouping(courseId) {
        var courseData = getCourseData(courseId);
        return !!(courseData && courseData.has_grouping && getCourseGroups(courseId).length);
    }

    function groupMatchesBranch(group, chapterName, coachId) {
        chapterName = String(chapterName || '');
        coachId = parseInt(coachId, 10) || 0;
        if (group.chapter_name && group.chapter_name !== '' && (chapterName === '' || group.chapter_name !== chapterName)) {
            return false;
        }
        // تا وقتی مربی انتخاب نشده، گروه‌های همان شعبه را نشان بده
        // (قبلاً گروه‌های دارای coach_id مخفی می‌شدند و لیست خالی به نظر می‌رسید)
        if (coachId <= 0) {
            return true;
        }
        if (group.coach_id > 0 && group.coach_id !== coachId) {
            return false;
        }
        return true;
    }

    function getGroupsForBranch(courseId, chapterName, coachId) {
        return getCourseGroups(courseId).filter(function (group) {
            return groupMatchesBranch(group, chapterName, coachId);
        });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function saveActivateBranchStateFromDom() {
        $('#sc-action-course-activate-branch-list .sc-bulk-activate-course-block').each(function () {
            var courseId = parseInt($(this).attr('data-course-id'), 10);
            if (!courseId) {
                return;
            }
            var $chapterSelect = $(this).find('.sc-bulk-activate-chapter-select');
            var $chapterHidden = $(this).find('input[name="course_chapter[' + courseId + ']"]');
            var chapter = $chapterSelect.length ? ($chapterSelect.val() || '') : ($chapterHidden.val() || '');
            var coachRaw = $(this).find('select[name="course_coach[' + courseId + ']"], input[name="course_coach[' + courseId + ']"]').val();
            activateBranchState[courseId] = {
                chapter: chapter,
                coachId: parseInt(coachRaw, 10) || 0,
                groupName: $(this).find('select[name="course_group[' + courseId + ']"]').val() || ''
            };
        });
    }

    function renderActivateCoachField($block, courseId, chapterName, selectedCoachId) {
        var $coField = $block.find('.sc-bulk-activate-coach-field');
        $coField.empty();

        if (!chapterName) {
            return;
        }

        var coaches = getCoachesForChapter(courseId, chapterName);
        if (!coaches.length) {
            $coField.html(
                '<span class="description">مربی برای این شعبه تعریف نشده — ثبت‌نام بدون مربی.</span>' +
                '<input type="hidden" name="course_coach[' + courseId + ']" value="0">'
            );
            return;
        }

        if (coaches.length === 1) {
            var onlyCoach = coaches[0];
            $coField.html(
                '<span><strong>مربی:</strong> ' + escapeHtml(onlyCoach.label) + '</span>' +
                '<input type="hidden" name="course_coach[' + courseId + ']" value="' + onlyCoach.id + '">'
            );
            return;
        }

        var html = '<label><strong>مربی:</strong> ' +
            '<select name="course_coach[' + courseId + ']" class="sc-bulk-activate-coach-select" data-course-id="' + courseId + '">' +
            '<option value="">انتخاب مربی</option>';
        coaches.forEach(function (coach) {
            html += '<option value="' + coach.id + '"' + (selectedCoachId === coach.id ? ' selected' : '') + '>' +
                escapeHtml(coach.label) + '</option>';
        });
        html += '</select></label>';
        $coField.html(html);
    }

    function renderActivateGroupField($block, courseId, chapterName, coachId, selectedGroupName) {
        var $groupField = $block.find('.sc-bulk-activate-group-field');
        $groupField.empty();

        if (!courseHasGrouping(courseId)) {
            return;
        }

        var groups = getGroupsForBranch(courseId, chapterName, coachId);
        var html = '<label><strong>گروه (اختیاری):</strong> ' +
            '<select name="course_group[' + courseId + ']" class="sc-bulk-activate-group-select" data-course-id="' + courseId + '">' +
            '<option value="">بدون گروه</option>';
        groups.forEach(function (group) {
            html += '<option value="' + escapeHtml(group.name) + '"' +
                (selectedGroupName === group.name ? ' selected' : '') + '>' +
                escapeHtml(group.name) + '</option>';
        });
        html += '</select></label>';
        $groupField.html(html);
    }

    function renderActivateCourseBranchBlocks() {
        var $list = $('#sc-action-course-activate-branch-list');
        if (!$list.length) {
            return;
        }

        saveActivateBranchStateFromDom();

        var courseIds = getSelectedActivateCourseIds();
        Object.keys(activateBranchState).forEach(function (key) {
            var cid = parseInt(key, 10);
            if (courseIds.indexOf(cid) === -1) {
                delete activateBranchState[cid];
            }
        });

        $list.empty();

        if (!courseIds.length) {
            $list.html('<p class="description">ابتدا یک یا چند دوره را از لیست «دوره‌های هدف» انتخاب کنید.</p>');
            return;
        }

        courseIds.forEach(function (courseId) {
            var chapters = getCourseChapters(courseId);
            var state = activateBranchState[courseId] || { chapter: '', coachId: 0, groupName: '' };
            var selChapter = state.chapter || '';
            var selCoach = state.coachId || 0;
            var selGroup = state.groupName || '';
            var title = getCourseOptionTitle(courseId);

            var $block = $('<div class="sc-bulk-activate-course-block" data-course-id="' + courseId + '"></div>');
            $block.append('<div class="sc-bulk-activate-course-title"><strong>' + escapeHtml(title) + '</strong></div>');

            var $chField = $('<div class="sc-bulk-activate-chapter-field"></div>');
            if (!chapters.length) {
                $chField.html('<span class="description">شعبه‌ای برای این دوره تعریف نشده است.</span>');
            } else if (chapters.length === 1) {
                selChapter = chapters[0];
                $chField.html(
                    '<span><strong>شعبه:</strong> ' + escapeHtml(selChapter) + '</span>' +
                    '<input type="hidden" name="course_chapter[' + courseId + ']" value="' + escapeHtml(selChapter) + '">'
                );
            } else {
                var chHtml = '<label><strong>شعبه:</strong> ' +
                    '<select name="course_chapter[' + courseId + ']" class="sc-bulk-activate-chapter-select" data-course-id="' + courseId + '">' +
                    '<option value="">انتخاب شعبه</option>';
                chapters.forEach(function (chapterName) {
                    chHtml += '<option value="' + escapeHtml(chapterName) + '"' +
                        (selChapter === chapterName ? ' selected' : '') + '>' +
                        escapeHtml(chapterName) + '</option>';
                });
                chHtml += '</select></label>';
                $chField.html(chHtml);
                if (!selChapter || chapters.indexOf(selChapter) === -1) {
                    selChapter = '';
                    selCoach = 0;
                }
            }

            $block.append($chField);
            $block.append('<div class="sc-bulk-activate-coach-field"></div>');
            $block.append('<div class="sc-bulk-activate-group-field"></div>');
            $list.append($block);
            renderActivateCoachField($block, courseId, selChapter, selCoach);
            var coachRawInit = $block.find('select[name="course_coach[' + courseId + ']"], input[name="course_coach[' + courseId + ']"]').val();
            var coachIdInit = parseInt(coachRawInit, 10) || selCoach || 0;
            renderActivateGroupField($block, courseId, selChapter, coachIdInit, selGroup);
        });
    }

    function validateActivateBranchAssignments() {
        var courseIds = getSelectedActivateCourseIds();
        var i;

        for (i = 0; i < courseIds.length; i++) {
            var courseId = courseIds[i];
            var chapters = getCourseChapters(courseId);
            var title = getCourseOptionTitle(courseId);
            var $block = $('#sc-action-course-activate-branch-list .sc-bulk-activate-course-block[data-course-id="' + courseId + '"]');
            var chapter = '';
            var coachId = 0;

            if ($block.length) {
                var $chapterSelect = $block.find('.sc-bulk-activate-chapter-select');
                if ($chapterSelect.length) {
                    chapter = $chapterSelect.val() || '';
                } else {
                    chapter = $block.find('input[name="course_chapter[' + courseId + ']"]').val() || '';
                }
                var coachRaw = $block.find('select[name="course_coach[' + courseId + ']"], input[name="course_coach[' + courseId + ']"]').val();
                coachId = parseInt(coachRaw, 10) || 0;
            }

            if (chapters.length > 1 && (!chapter || chapters.indexOf(chapter) === -1)) {
                alert('برای دوره «' + title + '» انتخاب شعبه الزامی است.');
                return false;
            }

            if (!chapter && chapters.length === 1) {
                chapter = chapters[0];
            }

            // مربی را از گروه انتخاب‌شده هم می‌توان استخراج کرد
            if (coachId <= 0 && $block.length) {
                var groupName = $block.find('select[name="course_group[' + courseId + ']"]').val() || '';
                if (groupName) {
                    var matchedGroup = getCourseGroups(courseId).filter(function (g) {
                        return g.name === groupName;
                    })[0];
                    if (matchedGroup && matchedGroup.coach_id > 0) {
                        coachId = matchedGroup.coach_id;
                    }
                }
            }

            var coaches = getCoachesForChapter(courseId, chapter);
            if (coaches.length > 1 && coachId <= 0) {
                alert('برای دوره «' + title + '» در شعبه «' + chapter + '» انتخاب مربی الزامی است.');
                return false;
            }
        }

        return true;
    }

    function refreshAssignChapterOptions() {
        var $courseSelect = $('#sc-assign-course-id');
        var $chapterSelect = $('#sc-assign-chapter-name');
        var $coachSelect = $('#sc-assign-coach-id');
        if (!$courseSelect.length || !$chapterSelect.length || !$coachSelect.length) {
            return;
        }

        var courseId = $courseSelect.val();
        var courseData = courseId ? getCourseData(courseId) : null;
        var chapters = courseData && courseData.chapters ? courseData.chapters : [];

        $chapterSelect.empty();
        $coachSelect.empty();

        if (!courseId) {
            $chapterSelect.append('<option value="">ابتدا دوره را انتخاب کنید</option>');
            $coachSelect.append('<option value="">ابتدا شعبه را انتخاب کنید</option>');
            return;
        }
        if (!chapters.length) {
            $chapterSelect.append('<option value="">شعبه‌ای برای این دوره تعریف نشده</option>');
            $coachSelect.append('<option value="">—</option>');
            return;
        }

        $chapterSelect.append('<option value="">انتخاب شعبه</option>');
        chapters.forEach(function (chapterName) {
            $chapterSelect.append('<option value="' + chapterName + '">' + chapterName + '</option>');
        });
        $coachSelect.append('<option value="">ابتدا شعبه را انتخاب کنید</option>');
    }

    function refreshAssignCoachOptions() {
        var $courseSelect = $('#sc-assign-course-id');
        var $chapterSelect = $('#sc-assign-chapter-name');
        var $coachSelect = $('#sc-assign-coach-id');
        if (!$courseSelect.length || !$chapterSelect.length || !$coachSelect.length) {
            return;
        }

        var courseId = $courseSelect.val();
        var chapterName = $chapterSelect.val();
        var coaches = getCoachesForChapter(courseId, chapterName);

        $coachSelect.empty();

        if (!courseId || !chapterName) {
            $coachSelect.append('<option value="">ابتدا شعبه را انتخاب کنید</option>');
            return;
        }
        if (!coaches.length) {
            $coachSelect.append('<option value="">مربی فعالی برای این شعبه یافت نشد</option>');
            return;
        }

        $coachSelect.append('<option value="">انتخاب مربی</option>');
        coaches.forEach(function (coach) {
            $coachSelect.append('<option value="' + coach.id + '">' + coach.label + '</option>');
        });
    }

    function refreshAssignCourseGroupChapterOptions() {
        var $courseSelect = $('#sc-assign-course-group-id');
        var $chapterSelect = $('#sc-assign-course-group-chapter');
        var $coachSelect = $('#sc-assign-course-group-coach');
        var $groupSelect = $('#sc-assign-course-group-name');
        if (!$courseSelect.length) {
            return;
        }

        var courseId = $courseSelect.val();
        var courseData = courseId ? getCourseData(courseId) : null;
        var chapters = courseData && courseData.chapters ? courseData.chapters : [];

        $chapterSelect.empty();
        $coachSelect.empty();
        $groupSelect.empty();
        $groupSelect.append('<option value="">بدون گروه</option>');

        if (!courseId) {
            $chapterSelect.append('<option value="">ابتدا دوره را انتخاب کنید</option>');
            $coachSelect.append('<option value="">ابتدا شعبه را انتخاب کنید</option>');
            return;
        }
        if (!chapters.length) {
            $chapterSelect.append('<option value="">شعبه‌ای برای این دوره تعریف نشده</option>');
            $coachSelect.append('<option value="">—</option>');
            refreshAssignCourseGroupNameOptions();
            return;
        }

        $chapterSelect.append('<option value="">همه / بدون فیلتر شعبه</option>');
        chapters.forEach(function (chapterName) {
            $chapterSelect.append('<option value="' + chapterName + '">' + chapterName + '</option>');
        });
        $coachSelect.append('<option value="">ابتدا شعبه را انتخاب کنید</option>');
        refreshAssignCourseGroupNameOptions();
    }

    function refreshAssignCourseGroupCoachOptions() {
        var $courseSelect = $('#sc-assign-course-group-id');
        var $chapterSelect = $('#sc-assign-course-group-chapter');
        var $coachSelect = $('#sc-assign-course-group-coach');
        if (!$courseSelect.length || !$chapterSelect.length || !$coachSelect.length) {
            return;
        }

        var courseId = $courseSelect.val();
        var chapterName = $chapterSelect.val();
        var coaches = getCoachesForChapter(courseId, chapterName);

        $coachSelect.empty();

        if (!courseId) {
            $coachSelect.append('<option value="">ابتدا دوره را انتخاب کنید</option>');
            refreshAssignCourseGroupNameOptions();
            return;
        }
        if (chapterName && !coaches.length) {
            $coachSelect.append('<option value="">مربی فعالی برای این شعبه یافت نشد</option>');
            refreshAssignCourseGroupNameOptions();
            return;
        }

        $coachSelect.append('<option value="">همه / بدون فیلتر مربی</option>');
        coaches.forEach(function (coach) {
            $coachSelect.append('<option value="' + coach.id + '">' + coach.label + '</option>');
        });
        refreshAssignCourseGroupNameOptions();
    }

    function refreshAssignCourseGroupNameOptions() {
        var $courseSelect = $('#sc-assign-course-group-id');
        var $chapterSelect = $('#sc-assign-course-group-chapter');
        var $coachSelect = $('#sc-assign-course-group-coach');
        var $groupSelect = $('#sc-assign-course-group-name');
        if (!$groupSelect.length) {
            return;
        }

        var courseId = parseInt($courseSelect.val(), 10) || 0;
        var chapterName = $chapterSelect.val() || '';
        var coachId = parseInt($coachSelect.val(), 10) || 0;
        var current = $groupSelect.val() || '';

        $groupSelect.empty();
        $groupSelect.append('<option value="">بدون گروه</option>');

        if (!courseId || !courseHasGrouping(courseId)) {
            return;
        }

        getGroupsForBranch(courseId, chapterName, coachId).forEach(function (group) {
            $groupSelect.append(
                '<option value="' + escapeHtml(group.name) + '"' +
                (current === group.name ? ' selected' : '') + '>' +
                escapeHtml(group.name) + '</option>'
            );
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
            refreshAssignChapterOptions();
            refreshAssignCoachOptions();
        } else if (action === 'assign_course_group') {
            $('#sc-action-assign-course-group').show();
            refreshAssignCourseGroupChapterOptions();
        } else if (action === 'course_activate') {
            $('#sc-action-course-common').show();
            $('#sc-action-course-activate-branch').show();
            renderActivateCourseBranchBlocks();
        } else if (action === 'course_deactivate') {
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
            if (!$('#sc-assign-chapter-name').val()) {
                alert('لطفاً شعبه را انتخاب کنید.');
                return false;
            }
            if (!$('#sc-assign-coach-id').val()) {
                alert('لطفاً مربی دوره را انتخاب کنید.');
                return false;
            }
        }
        if (action === 'assign_course_group') {
            if (!$('#sc-assign-course-group-id').val()) {
                alert('برای تخصیص گروه، ابتدا دوره را انتخاب کنید.');
                return false;
            }
        }
        if (action === 'course_activate' && !validateActivateBranchAssignments()) {
            return false;
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
        $('#sc-action-course-ids').on('change', function () {
            if ($('#sc-bulk-action-type').val() === 'course_activate') {
                renderActivateCourseBranchBlocks();
            }
        });
        $(document).on('change', '.sc-bulk-activate-chapter-select', function () {
            var courseId = parseInt($(this).data('course-id'), 10);
            var $block = $(this).closest('.sc-bulk-activate-course-block');
            if (!courseId || !$block.length) {
                return;
            }
            activateBranchState[courseId] = {
                chapter: $(this).val() || '',
                coachId: 0,
                groupName: ''
            };
            renderActivateCoachField($block, courseId, activateBranchState[courseId].chapter, 0);
            var coachRaw = $block.find('select[name="course_coach[' + courseId + ']"], input[name="course_coach[' + courseId + ']"]').val();
            activateBranchState[courseId].coachId = parseInt(coachRaw, 10) || 0;
            renderActivateGroupField(
                $block,
                courseId,
                activateBranchState[courseId].chapter,
                activateBranchState[courseId].coachId,
                ''
            );
        });
        $(document).on('change', '.sc-bulk-activate-coach-select', function () {
            var courseId = parseInt($(this).data('course-id'), 10);
            if (!courseId) {
                return;
            }
            var $block = $(this).closest('.sc-bulk-activate-course-block');
            activateBranchState[courseId] = activateBranchState[courseId] || { chapter: '', coachId: 0, groupName: '' };
            activateBranchState[courseId].coachId = parseInt($(this).val(), 10) || 0;
            renderActivateGroupField(
                $block,
                courseId,
                activateBranchState[courseId].chapter,
                activateBranchState[courseId].coachId,
                activateBranchState[courseId].groupName || ''
            );
        });
        $(document).on('change', '.sc-bulk-activate-group-select', function () {
            var courseId = parseInt($(this).data('course-id'), 10);
            if (!courseId) {
                return;
            }
            var $block = $(this).closest('.sc-bulk-activate-course-block');
            var groupName = $(this).val() || '';
            activateBranchState[courseId] = activateBranchState[courseId] || { chapter: '', coachId: 0, groupName: '' };
            activateBranchState[courseId].groupName = groupName;

            // اگر گروه به مربی خاصی وصل است، همان مربی را در فرم تنظیم کن
            if (groupName && $block.length) {
                var matched = getCourseGroups(courseId).filter(function (g) {
                    return g.name === groupName;
                })[0];
                if (matched && matched.coach_id > 0) {
                    var $coachSelect = $block.find('.sc-bulk-activate-coach-select');
                    var $coachHidden = $block.find('input[name="course_coach[' + courseId + ']"]');
                    if ($coachSelect.length) {
                        $coachSelect.val(String(matched.coach_id));
                    } else if ($coachHidden.length) {
                        $coachHidden.val(String(matched.coach_id));
                    }
                    activateBranchState[courseId].coachId = matched.coach_id;
                }
            }
        });
        $('#sc-assign-course-id').on('change', function () {
            refreshAssignChapterOptions();
            refreshAssignCoachOptions();
        });
        $('#sc-assign-chapter-name').on('change', refreshAssignCoachOptions);
        $('#sc-assign-course-group-id').on('change', refreshAssignCourseGroupChapterOptions);
        $('#sc-assign-course-group-chapter').on('change', refreshAssignCourseGroupCoachOptions);
        $('#sc-assign-course-group-coach').on('change', refreshAssignCourseGroupNameOptions);

        $('#sc-bulk-preview-btn').on('click', function () {
            var $btn = $(this);
            var $result = $('#sc-bulk-preview-result');
            $btn.prop('disabled', true);
            if (window.scAudiencePreviewAdd) {
                window.scAudiencePreviewAdd.reset('#sc-bulk-preview-result');
            }
            $result.html('<p class="description">در حال دریافت پیش نمایش...</p>');

            $.post(
                (window.scBulkActions && scBulkActions.ajaxurl) ? scBulkActions.ajaxurl : ajaxurl,
                buildFormPayload()
            ).done(function (res) {
                if (res && res.success && res.data) {
                    $result.html(res.data.html || '');
                    initPreviewSelectionBindings();
                    if (window.scAudiencePreviewAdd) {
                        window.scAudiencePreviewAdd.show('#sc-bulk-preview-result');
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
            if (payload && payload.mode === 'bulk') {
                initPreviewSelectionBindings();
            }
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
