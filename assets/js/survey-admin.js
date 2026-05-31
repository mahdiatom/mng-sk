(function ($) {
    'use strict';

    var questions = (window.scSurveyInitialQuestions || []).slice();
    var types = window.scSurveyQuestionTypes || {};
    var $builder = $('#sc-survey-questions-builder');
    var uid = 0;
    var selectedMemberIds = [];

    function escHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseQid(raw) {
        var s = String(raw || '');
        if (s.indexOf('new_') === 0) {
            return 0;
        }
        var n = parseInt(s, 10);
        return isNaN(n) ? 0 : n;
    }

    function qKey(q, idx) {
        if (q.id) {
            return String(q.id);
        }
        return 'new_' + idx;
    }

    function previousQuestions(currentIndex) {
        return questions.slice(0, currentIndex).filter(function (q, i) {
            return String(q.question_text || '').trim() !== '' || i < currentIndex;
        });
    }

    function buildDepOptions(currentIndex, selectedId) {
        var prev = previousQuestions(currentIndex);
        if (!prev.length) {
            return '<option value="">— سوال قبلی وجود ندارد —</option>';
        }
        return '<option value="">انتخاب سوال وابسته</option>' + prev.map(function (qq, i) {
            var key = qKey(qq, i);
            var label = (qq.question_text || 'سوال ' + (i + 1)).substring(0, 80);
            return '<option value="' + escHtml(String(key)) + '"' + (String(selectedId) === String(key) ? ' selected' : '') + '>' + escHtml((i + 1) + '. ' + label) + '</option>';
        }).join('');
    }

    function typeOptions(selected) {
        return Object.keys(types).map(function (k) {
            return '<option value="' + k + '"' + (selected === k ? ' selected' : '') + '>' + escHtml(types[k]) + '</option>';
        }).join('');
    }

    function tpl(q, index) {
        q = q || { question_type: 'text', question_text: '', options: { choices: [], min: 1, max: 5 }, settings: { required: false, conditional: { enabled: false } } };
        var id = q.id ? String(q.id) : ('new_' + (++uid));
        var choices = (q.options && q.options.choices) ? q.options.choices.join('\n') : '';
        var minVal = (q.options && q.options.min != null) ? q.options.min : 1;
        var maxVal = (q.options && q.options.max != null) ? q.options.max : 5;
        var cond = (q.settings && q.settings.conditional) ? q.settings.conditional : {};
        var condEnabled = !!cond.enabled;
        var showChoices = ['single_choice', 'multiple_choice'].indexOf(q.question_type) !== -1;
        var showRating = q.question_type === 'rating';

        return '<div class="sc-survey-q-card" data-qid="' + escHtml(String(id)) + '" data-index="' + index + '">' +
            '<div class="sc-survey-q-card-head">' +
                '<span class="sc-survey-q-num">سوال ' + (index + 1) + '</span>' +
                '<button type="button" class="button-link-delete sc-remove-q" title="حذف سوال">حذف</button>' +
            '</div>' +
            '<div class="sc-survey-q-grid">' +
                '<div class="sc-survey-q-field sc-survey-q-field-full">' +
                    '<label>متن سوال</label>' +
                    '<textarea class="large-text sc-q-text" rows="2" placeholder="متن سوال را وارد کنید">' + escHtml(q.question_text || '') + '</textarea>' +
                '</div>' +
                '<div class="sc-survey-q-field">' +
                    '<label>نوع پاسخ</label>' +
                    '<select class="sc-q-type">' + typeOptions(q.question_type) + '</select>' +
                '</div>' +
                '<div class="sc-survey-q-field sc-survey-q-field-check">' +
                    '<label class="sc-inline-check"><input type="checkbox" class="sc-q-required"' + (q.settings && q.settings.required ? ' checked' : '') + '> پاسخ اجباری</label>' +
                '</div>' +
            '</div>' +
            '<div class="sc-survey-q-options sc-q-choices-wrap"' + (showChoices ? '' : ' style="display:none;"') + '>' +
                '<label>گزینه‌ها <small>(هر خط یک گزینه)</small></label>' +
                '<textarea class="large-text sc-q-choices" rows="4" placeholder="گزینه ۱&#10;گزینه ۲">' + escHtml(choices) + '</textarea>' +
            '</div>' +
            '<div class="sc-survey-q-options sc-q-rating-wrap"' + (showRating ? '' : ' style="display:none;"') + '>' +
                '<label>تعداد ستاره <input type="number" class="small-text sc-q-max" min="1" max="10" value="' + escHtml(maxVal) + '"></label>' +
            '</div>' +
            '<details class="sc-survey-q-conditional"' + (condEnabled ? ' open' : '') + '>' +
                '<summary class="sc-survey-q-conditional-title">منطق شرطی</summary>' +
                '<div class="sc-survey-q-conditional-body">' +
                    '<label class="sc-inline-check"><input type="checkbox" class="sc-q-cond-enabled"' + (condEnabled ? ' checked' : '') + '> نمایش این سوال فقط در صورت برقراری شرط</label>' +
                    '<div class="sc-q-cond-fields"' + (condEnabled ? '' : ' style="display:none;"') + '>' +
                        '<div class="sc-survey-q-field">' +
                            '<label>سوال وابسته</label>' +
                            '<select class="sc-q-cond-qid">' + buildDepOptions(index, cond.question_id || '') + '</select>' +
                        '</div>' +
                        '<div class="sc-survey-q-field">' +
                            '<label>عملگر</label>' +
                            '<select class="sc-q-cond-op">' +
                                '<option value="equals"' + ((cond.operator || 'equals') === 'equals' ? ' selected' : '') + '>برابر باشد با</option>' +
                                '<option value="not_equals"' + (cond.operator === 'not_equals' ? ' selected' : '') + '>برابر نباشد با</option>' +
                                '<option value="contains"' + (cond.operator === 'contains' ? ' selected' : '') + '>شامل باشد</option>' +
                                '<option value="gt"' + (cond.operator === 'gt' ? ' selected' : '') + '>بزرگ‌تر از</option>' +
                                '<option value="gte"' + (cond.operator === 'gte' ? ' selected' : '') + '>بزرگ‌تر یا مساوی</option>' +
                                '<option value="lt"' + (cond.operator === 'lt' ? ' selected' : '') + '>کوچک‌تر از</option>' +
                                '<option value="lte"' + (cond.operator === 'lte' ? ' selected' : '') + '>کوچک‌تر یا مساوی</option>' +
                                '<option value="gt"' + (cond.operator === 'gt' ? ' selected' : '') + '>بزرگتر از</option>' +
                                '<option value="gte"' + (cond.operator === 'gte' ? ' selected' : '') + '>بزرگتر یا مساوی</option>' +
                                '<option value="lt"' + (cond.operator === 'lt' ? ' selected' : '') + '>کوچکتر از</option>' +
                                '<option value="lte"' + (cond.operator === 'lte' ? ' selected' : '') + '>کوچکتر یا مساوی</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="sc-survey-q-field">' +
                            '<label>مقدار</label>' +
                            '<input type="text" class="regular-text sc-q-cond-val" placeholder="مقدار مورد انتظار" value="' + escHtml(cond.value || '') + '">' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</details>' +
        '</div>';
    }

    function syncQuestionsFromDom() {
        if (!$builder.length) {
            return;
        }
        var out = [];
        $builder.find('.sc-survey-q-card').each(function () {
            var $el = $(this);
            var qid = $el.attr('data-qid');
            var type = $el.find('.sc-q-type').val() || 'text';
            var choices = $el.find('.sc-q-choices').val().split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
            var min = parseInt($el.find('.sc-q-min').val(), 10);
            var max = parseInt($el.find('.sc-q-max').val(), 10);
            if (isNaN(min)) min = 1;
            if (isNaN(max)) max = 5;
            out.push({
                id: parseQid(qid),
                _key: qid,
                question_type: type,
                question_text: $el.find('.sc-q-text').val() || '',
                options: { choices: choices, min: min, max: max },
                settings: {
                    required: $el.find('.sc-q-required').is(':checked'),
                    conditional: {
                        enabled: $el.find('.sc-q-cond-enabled').is(':checked'),
                        question_id: $el.find('.sc-q-cond-qid').val(),
                        operator: $el.find('.sc-q-cond-op').val(),
                        value: $el.find('.sc-q-cond-val').val()
                    }
                }
            });
        });
        questions = out;
    }

    function render(skipSync) {
        if (!skipSync) {
            syncQuestionsFromDom();
        }
        $builder.empty();
        questions.forEach(function (q, i) {
            $builder.append(tpl(q, i));
        });
        toggleAudienceBlocks();
        toggleTriggerBlocks();
        toggleRestrictionBox();
    }

    function collect() {
        syncQuestionsFromDom();
        return questions.map(function (q) {
            return {
                id: parseQid(q._key || q.id),
                question_type: q.question_type,
                question_text: q.question_text,
                options: q.options || { choices: [], min: 1, max: 5 },
                settings: q.settings || { required: false, conditional: { enabled: false } }
            };
        });
    }

    function toggleAudienceBlocks() {
        var includePlayers = $('#include_players').is(':checked') || $('input[name="include_players"]').is(':checked');
        var targetType = $('#sc-survey-target-type').val();

        // Player-only rows and filter blocks
        var playerOnlySelectors = '#sc-survey-player-target-row, #sc-survey-player-status-row, #sc-survey-player-type-row, #sc-survey-filter-specific, #sc-survey-filter-course, #sc-survey-filter-event, #sc-survey-filter-team, #sc-survey-filter-level';
        $(playerOnlySelectors).hide();

        if (includePlayers) {
            // Show target type, status, and category rows
            $('#sc-survey-player-target-row, #sc-survey-player-status-row, #sc-survey-player-type-row').show();

            // Show filter blocks based on target type
            if (targetType === 'specific') {
                $('#sc-survey-filter-specific').show();
            } else if (targetType === 'course') {
                $('#sc-survey-filter-course').show();
            } else if (targetType === 'event') {
                $('#sc-survey-filter-event').show();
            } else if (targetType === 'team') {
                $('#sc-survey-filter-team').show();
            } else if (targetType === 'level') {
                $('#sc-survey-filter-level').show();
            } else if (targetType === 'team_level') {
                $('#sc-survey-filter-team, #sc-survey-filter-level').show();
            }
        }
    }

    function toggleTriggerBlocks() {
        var t = $('#trigger_type').val();
        $('.sc-trigger-on-date').toggle(t === 'on_date');
        $('.sc-trigger-last-session').toggle(t === 'last_session');
    }

    function toggleRestrictionBox() {
        $('#sc-survey-restrictions-box').toggle($('#sc-survey-restriction-enabled').is(':checked'));
    }

    function renderSelectedMembers() {
        var $tags = $('#sc-survey-selected-tags');
        var $inputs = $('#sc-survey-member-hidden-inputs');
        var $count = $('#sc-survey-selected-count');
        if (!$tags.length) return;
        $tags.empty();
        $inputs.empty();
        $count.text(selectedMemberIds.length + ' کاربر انتخاب شده');
        if (!selectedMemberIds.length) {
            $tags.html('<em class="sc-survey-no-tags">هنوز کاربری انتخاب نشده است.</em>');
            return;
        }
        selectedMemberIds.forEach(function (item) {
            $tags.append('<span class="sc-tag" data-id="' + item.id + '">' + escHtml(item.label) + ' <button type="button" class="sc-remove-tag">&times;</button></span>');
            $inputs.append('<input type="hidden" name="member_ids[]" value="' + item.id + '">');
        });
    }

    function addMember(id, label) {
        id = parseInt(id, 10);
        if (!id) return;
        if (selectedMemberIds.some(function (item) { return item.id === id; })) return;
        selectedMemberIds.push({ id: id, label: label });
        renderSelectedMembers();
    }

    function initMemberPicker() {
        var $dropdown = $('#sc-survey-member-dropdown');
        if (!$dropdown.length) return;

        $('#sc-survey-member-hidden-inputs input[name="member_ids[]"]').each(function () {
            var id = parseInt($(this).val(), 10);
            if (!id) return;
            var $opt = $('#sc-survey-member-options .sc-users-dropdown-option[data-id="' + id + '"]');
            var label = $opt.length ? ($opt.attr('data-label') || $opt.text()) : ('کاربر #' + id);
            if (!selectedMemberIds.some(function (item) { return item.id === id; })) {
                selectedMemberIds.push({ id: id, label: $.trim(label) });
            }
        });
        renderSelectedMembers();

        $dropdown.find('.sc-users-dropdown-toggle').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropdown.find('.sc-users-dropdown-menu').slideToggle(150);
            setTimeout(function () { $dropdown.find('.sc-users-search-input').trigger('focus'); }, 200);
        });

        $dropdown.find('.sc-users-search-input').on('input', function () {
            var term = ($(this).val() || '').toLowerCase().trim();
            $('#sc-survey-member-options .sc-users-dropdown-option').each(function () {
                var text = ($(this).attr('data-search') || '').toLowerCase();
                $(this).toggle(term === '' || text.indexOf(term) !== -1);
            });
        });

        $(document).on('click', '#sc-survey-member-options .sc-users-dropdown-option', function (e) {
            e.preventDefault();
            e.stopPropagation();
            addMember($(this).attr('data-id'), $(this).attr('data-label') || $(this).text());
            $dropdown.find('.sc-users-dropdown-menu').slideUp(150);
        });

        $(document).on('click', '#sc-survey-selected-tags .sc-remove-tag', function (e) {
            e.preventDefault();
            var id = parseInt($(this).closest('.sc-tag').attr('data-id'), 10);
            selectedMemberIds = selectedMemberIds.filter(function (item) { return item.id !== id; });
            renderSelectedMembers();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#sc-survey-member-dropdown').length) {
                $('#sc-survey-member-dropdown .sc-users-dropdown-menu').slideUp(150);
            }
        });
    }

    function bindQuestionBuilder() {
        if (!$builder.length) {
            return;
        }

        $(document).on('click', '#sc-add-question', function (e) {
            e.preventDefault();
            syncQuestionsFromDom();
            questions.push({
                question_type: 'text',
                question_text: '',
                options: { choices: [], min: 1, max: 5 },
                settings: { required: false, conditional: { enabled: false } }
            });
            render(true);
        });

        $builder.on('click', '.sc-remove-q', function (e) {
            e.preventDefault();
            var idx = $(this).closest('.sc-survey-q-card').index();
            syncQuestionsFromDom();
            questions.splice(idx, 1);
            render(true);
        });

        $builder.on('change', '.sc-q-type', function () {
            var $card = $(this).closest('.sc-survey-q-card');
            var type = $(this).val();
            $card.find('.sc-q-choices-wrap').toggle(['single_choice', 'multiple_choice'].indexOf(type) !== -1);
            $card.find('.sc-q-rating-wrap').toggle(type === 'rating');
        });

        $builder.on('change', '.sc-q-cond-enabled', function () {
            var $card = $(this).closest('.sc-survey-q-card');
            var on = $(this).is(':checked');
            $card.find('.sc-q-cond-fields').toggle(on);
            if (on) {
                $card.find('.sc-survey-q-conditional').prop('open', true);
            }
        });

        if (!questions.length) {
            questions.push({
                question_type: 'text',
                question_text: '',
                options: { choices: [], min: 1, max: 5 },
                settings: { required: false, conditional: { enabled: false } }
            });
        }
        render(true);
    }

    $('#sc-survey-target-type, #include_players, #include_coaches').on('change', toggleAudienceBlocks);
    $('#trigger_type').on('change', toggleTriggerBlocks);
    $('#sc-survey-restriction-enabled').on('change', toggleRestrictionBox);

    $('#sc-survey-form').on('submit', function () {
        $('#survey_questions_json').val(JSON.stringify(collect()));
    });

    $('#sc-survey-form').on('keydown', function (e) {
        if (e.key === 'Enter' && !$(e.target).is('textarea')) {
            e.preventDefault();
        }
    });

    bindQuestionBuilder();
    initMemberPicker();

    if (!$builder.length) {
        toggleAudienceBlocks();
        toggleTriggerBlocks();
        toggleRestrictionBox();
    }

    if (typeof initPersianDatePicker === 'function') {
        initPersianDatePicker();
    }

    var $selectAll = $('#sc-survey-cb-select-all');
    if ($selectAll.length) {
        $selectAll.on('change', function () {
            var checked = $(this).prop('checked');
            $(this).closest('table').find('.sc-survey-row-cb').prop('checked', checked);
        });

        $(document).on('change', '.sc-survey-row-cb', function () {
            var $table = $(this).closest('table');
            var total = $table.find('.sc-survey-row-cb').length;
            var checked = $table.find('.sc-survey-row-cb:checked').length;
            $table.find('#sc-survey-cb-select-all').prop('checked', total > 0 && total === checked);
        });
    }

    $(document).on('click', '.sc-survey-bulk-submit', function (e) {
        var $form = $(this).closest('form');
        var action = $form.find('[name="bulk_action"]').val();
        if (!action || action === '-1') {
            alert('لطفاً یک عملیات دسته‌جمعی انتخاب کنید.');
            e.preventDefault();
            return false;
        }
        if ($form.find('.sc-survey-row-cb:checked').length === 0) {
            alert('حداقل یک مورد را انتخاب کنید.');
            e.preventDefault();
            return false;
        }
        var confirmMsg = $(this).data('confirm');
        if (!confirmMsg && action === 'delete') {
            confirmMsg = 'موارد انتخاب‌شده حذف شوند؟';
        }
        if (confirmMsg && !window.confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
        if (!confirmMsg && (action === 'activate' || action === 'deactivate')) {
            if (!window.confirm('عملیات دسته‌جمعی روی موارد انتخاب‌شده اعمال شود؟')) {
                e.preventDefault();
                return false;
            }
        }
    });
})(jQuery);
