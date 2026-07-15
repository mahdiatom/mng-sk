(function ($) {
    'use strict';

    var cfg = window.scSecretaryQuick || {};
    var sounds = {};
    var audioUnlocked = false;
    var qaState = {
        existing: { validated: false, lastData: null, options: null },
        new: { validated: false, lastData: null, options: null }
    };

    function unlockAudio() {
        if (audioUnlocked) {
            return;
        }
        Object.keys(sounds).forEach(function (key) {
            try {
                sounds[key].play().then(function () {
                    sounds[key].pause();
                    sounds[key].currentTime = 0;
                }).catch(function () {});
            } catch (e) {}
        });
        audioUnlocked = true;
    }

    function initSounds() {
        var map = cfg.sounds || {};
        Object.keys(map).forEach(function (type) {
            if (map[type]) {
                sounds[type] = new Audio(map[type]);
            }
        });
    }

    function playSound(type) {
        if (!sounds[type]) {
            return;
        }
        try {
            sounds[type].currentTime = 0;
            sounds[type].play().catch(function () {});
        } catch (e) {}
    }

    function showMsg($el, text, ok) {
        $el.removeClass('is-error is-success is-info is-warning');
        if (ok === true) {
            $el.addClass('is-success');
        } else if (ok === 'info') {
            $el.addClass('is-info');
        } else if (ok === 'warning') {
            $el.addClass('is-warning');
        } else if (ok === false) {
            $el.addClass('is-error');
        }
        $el.html(text || '');
    }

    function renderValidation($box, messages) {
        $box.empty().show();
        if (!messages || !messages.length) {
            return;
        }
        var $ul = $('<ul class="sc-qa-validation-list"></ul>');
        messages.forEach(function (msg) {
            var type = msg.type || 'info';
            $('<li></li>')
                .addClass('sc-qa-validation-item sc-qa-validation-item--' + type)
                .text(msg.text || '')
                .appendTo($ul);
        });
        $box.append($ul);
    }

    function tabButtons(tab) {
        if (tab === 'new') {
            return {
                $check: $('#sc_qa_new_check'),
                $confirm: $('#sc_qa_new_confirm')
            };
        }
        return {
            $check: $('#sc_qa_existing_check'),
            $confirm: $('#sc_qa_existing_confirm')
        };
    }

    function setButtonState($btn, state, text) {
        $btn.removeClass('is-checking is-ready is-error-flash');
        if (state) {
            $btn.addClass(state);
        }
        if (typeof text === 'string') {
            $btn.text(text);
        }
    }

    function resetValidationState(tab) {
        qaState[tab].validated = false;
        qaState[tab].lastData = null;
        var btns = tabButtons(tab);
        btns.$check.data('sc-busy', false).prop('disabled', false);
        setButtonState(btns.$check, '', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
        btns.$confirm.data('sc-busy', false).prop('disabled', true);
        setButtonState(btns.$confirm, '', cfg.labels && cfg.labels.confirm ? cfg.labels.confirm : 'تایید و ثبت در دوره');
    }

    function setLoading($btn, loading, loadingText, loadingClass) {
        if (loading) {
            if (!$btn.data('orig-text')) {
                $btn.data('orig-text', $btn.text());
            }
            $btn.data('sc-busy', true);
            $btn.prop('disabled', true);
            setButtonState($btn, loadingClass || 'is-checking', loadingText || 'لطفاً صبر کنید...');
        } else {
            $btn.data('sc-busy', false);
            $btn.prop('disabled', false);
            if ($btn.data('orig-text')) {
                $btn.text($btn.data('orig-text'));
            }
            $btn.removeClass('is-checking');
        }
    }

    function groupMatchesBranch(group, chapterName, coachId) {
        chapterName = String(chapterName || '');
        coachId = parseInt(coachId, 10) || 0;
        if (group.chapter_name && group.chapter_name !== '' && (chapterName === '' || group.chapter_name !== chapterName)) {
            return false;
        }
        if (group.coach_id > 0 && (coachId <= 0 || parseInt(group.coach_id, 10) !== coachId)) {
            return false;
        }
        return true;
    }

    function getGroupsForOptions(options, chapter, coachId) {
        var groups = (options && options.groups) ? options.groups : [];
        return groups.filter(function (group) {
            return groupMatchesBranch(group, chapter, coachId);
        });
    }

    function clearCoachGroup(prefix) {
        var $coach = $('#sc_qa_' + prefix + '_coach');
        var $group = $('#sc_qa_' + prefix + '_group');
        var $groupRow = $group.closest('tr');
        $coach.prop('disabled', true).empty().append($('<option value="">ابتدا شعبه را انتخاب کنید</option>'));
        $group.prop('disabled', true).empty().append($('<option value="">ابتدا مربی را انتخاب کنید</option>'));
        $groupRow.hide();
        qaState[prefix].options = null;
    }

    function resetChapterSelect(prefix) {
        var $chapter = $('#sc_qa_' + prefix + '_chapter');
        $chapter.prop('disabled', true).empty().append($('<option value="">ابتدا دوره را انتخاب کنید</option>'));
        clearCoachGroup(prefix);
        fillPackageSelect(prefix, null);
        $('#sc_qa_' + prefix + '_remaining').val('').removeData('auto-filled');
    }

    function fillChapterSelect(prefix, items, preferredChapter) {
        var $chapter = $('#sc_qa_' + prefix + '_chapter');
        $chapter.empty();

        if (!items || !items.length) {
            $chapter.append($('<option value="">شعبه‌ای برای این دوره یافت نشد</option>')).prop('disabled', true);
            clearCoachGroup(prefix);
            return;
        }

        $chapter.append($('<option value="">— انتخاب شعبه —</option>'));
        items.forEach(function (item) {
            var val = item.id || item.title || item;
            var label = item.title || item.id || item;
            $('<option></option>').val(String(val)).text(String(label)).appendTo($chapter);
        });
        $chapter.prop('disabled', false);

        var preferred = preferredChapter || cfg.defaultChapter || '';
        if (preferred) {
            var found = items.some(function (item) {
                return String(item.id || item.title || item) === String(preferred);
            });
            if (found) {
                $chapter.val(String(preferred));
            }
        }
        if (!$chapter.val() && items.length === 1) {
            $chapter.val(String(items[0].id || items[0].title || items[0]));
        }

        if ($chapter.val()) {
            loadEnrollmentOptions(prefix);
        } else {
            clearCoachGroup(prefix);
        }
    }

    function loadChaptersForCourse(prefix) {
        var courseId = $('#sc_qa_' + prefix + '_course').val();
        var $chapter = $('#sc_qa_' + prefix + '_chapter');

        if (!courseId) {
            resetChapterSelect(prefix);
            return;
        }

        $chapter.prop('disabled', true).empty().append($('<option value="">در حال بارگذاری شعبه‌ها...</option>'));
        clearCoachGroup(prefix);
        fillPackageSelect(prefix, null);

        $.get(cfg.ajaxUrl, {
            action: 'sc_secretary_quick_action_chapters',
            nonce: cfg.nonce,
            course_id: courseId
        }).done(function (res) {
            if (!res.success) {
                $chapter.empty().append($('<option value="">خطا در بارگذاری شعبه‌ها</option>')).prop('disabled', true);
                return;
            }
            fillChapterSelect(prefix, res.data.items || [], cfg.defaultChapter || '');
        }).fail(function () {
            $chapter.empty().append($('<option value="">خطا در بارگذاری شعبه‌ها</option>')).prop('disabled', true);
        });
    }

    function loadEnrollmentOptions(prefix) {
        var courseId = $('#sc_qa_' + prefix + '_course').val();
        var chapter = $('#sc_qa_' + prefix + '_chapter').val();
        var $coach = $('#sc_qa_' + prefix + '_coach');
        var $group = $('#sc_qa_' + prefix + '_group');

        if (!courseId || !chapter) {
            clearCoachGroup(prefix);
            if (!courseId) {
                fillPackageSelect(prefix, null);
            }
            return;
        }

        $coach.prop('disabled', true).empty().append($('<option value="">در حال بارگذاری...</option>'));
        $group.prop('disabled', true).empty().append($('<option value="">...</option>'));

        $.get(cfg.ajaxUrl, {
            action: 'sc_secretary_quick_enrollment_options',
            nonce: cfg.nonce,
            course_id: courseId,
            chapter: chapter
        }).done(function (res) {
            if (!res.success || !res.data) {
                clearCoachGroup(prefix);
                fillPackageSelect(prefix, null);
                $coach.empty().append($('<option value="">خطا در بارگذاری مربی‌ها</option>'));
                return;
            }
            qaState[prefix].options = res.data;
            fillCoachSelect(prefix, res.data);
            fillPackageSelect(prefix, res.data);
            var coachId = parseInt($coach.val(), 10) || 0;
            fillGroupSelect(prefix, chapter, coachId);
        }).fail(function () {
            clearCoachGroup(prefix);
            fillPackageSelect(prefix, null);
            $coach.empty().append($('<option value="">خطا در بارگذاری مربی‌ها</option>'));
        });
    }

    function fillCoachSelect(prefix, options) {
        var $coach = $('#sc_qa_' + prefix + '_coach');
        var coaches = (options && options.coaches) ? options.coaches : [];
        $coach.empty();

        if (!coaches.length) {
            $coach.append($('<option value="0">بدون مربی</option>')).prop('disabled', false);
            return;
        }

        if (coaches.length === 1) {
            $coach.append(
                $('<option></option>').val(String(coaches[0].id)).text(coaches[0].label)
            ).prop('disabled', false);
            return;
        }

        $coach.append($('<option value="">— انتخاب مربی —</option>'));
        coaches.forEach(function (coach) {
            $('<option></option>').val(String(coach.id)).text(coach.label).appendTo($coach);
        });
        $coach.prop('disabled', false);
    }

    function fillGroupSelect(prefix, chapter, coachId) {
        var options = qaState[prefix].options;
        var $group = $('#sc_qa_' + prefix + '_group');
        var $groupRow = $group.closest('tr');
        var hasGrouping = !!(options && options.has_grouping);

        if (!hasGrouping) {
            $groupRow.hide();
            $group.prop('disabled', true).empty().append($('<option value=""></option>'));
            return;
        }

        $groupRow.show();
        $group.empty();

        if (!coachId && options.coach_required) {
            $group.append($('<option value="">ابتدا مربی را انتخاب کنید</option>')).prop('disabled', true);
            return;
        }

        var groups = getGroupsForOptions(options, chapter, coachId);
        if (!groups.length) {
            $group.append($('<option value="">گروهی برای این مربی/شعبه نیست</option>')).prop('disabled', false);
            return;
        }

        $group.append($('<option value="">— انتخاب گروه —</option>'));
        groups.forEach(function (group) {
            $('<option></option>').val(String(group.name)).text(group.name).appendTo($group);
        });
        $group.prop('disabled', false);
    }

    function setDefaultRemaining(prefix, value) {
        var $remaining = $('#sc_qa_' + prefix + '_remaining');
        if (!$remaining.length) {
            return;
        }
        var def = (value !== undefined && value !== null && value !== '') ? String(value) : '';
        if ($remaining.val() === '' || $remaining.data('auto-filled')) {
            $remaining.val(def).data('auto-filled', true);
        }
        $remaining.attr('placeholder', def !== '' ? ('پیش‌فرض: ' + def) : 'پیش‌فرض دوره');
    }

    function fillPackageSelect(prefix, options) {
        var $package = $('#sc_qa_' + prefix + '_package');
        var $packageRow = $package.closest('tr.sc-qa-package-row');
        if (!$package.length) {
            return;
        }

        $package.empty();
        var hasPackages = !!(options && options.has_packages && options.packages && options.packages.length);
        if (!hasPackages) {
            $packageRow.hide();
            $package.append($('<option value=""></option>'));
            setDefaultRemaining(prefix, options ? options.default_remaining_sessions : '');
            return;
        }

        $packageRow.show();
        $package.append($('<option value="">— انتخاب پکیج —</option>'));
        options.packages.forEach(function (pkg) {
            $('<option></option>')
                .val(String(pkg.sessions))
                .text(pkg.label || (pkg.sessions + ' جلسه'))
                .appendTo($package);
        });
        $package.append($('<option value="custom">دلخواه (تعداد جلسات دستی)</option>'));

        if (options.packages.length === 1) {
            $package.val(String(options.packages[0].sessions));
            setDefaultRemaining(prefix, options.packages[0].sessions);
        } else {
            setDefaultRemaining(prefix, '');
        }
    }

    function applyPackageSelection(prefix) {
        var options = qaState[prefix].options;
        var $package = $('#sc_qa_' + prefix + '_package');
        var val = $package.val();
        if (!$package.closest('tr.sc-qa-package-row').is(':visible')) {
            return;
        }
        if (val === 'custom') {
            $('#sc_qa_' + prefix + '_remaining').val('').removeData('auto-filled').attr('placeholder', 'تعداد دلخواه را وارد کنید');
            return;
        }
        if (val) {
            setDefaultRemaining(prefix, val);
            $('#sc_qa_' + prefix + '_remaining').val(String(val)).data('auto-filled', true);
            return;
        }
        if (options && options.default_remaining_sessions) {
            setDefaultRemaining(prefix, options.default_remaining_sessions);
        }
    }

    function clearSelectedMember() {
        $('#sc_qa_member_id').val('');
        $('#sc_qa_member_selected').hide().empty();
        $('#sc_qa_member_search').val('').attr('placeholder', 'نام، موبایل یا کد ملی را تایپ کنید...');
    }

    function selectMember(item) {
        $('#sc_qa_member_id').val(item.id);
        $('#sc_qa_member_search').val('');
        $('#sc_qa_member_results').empty();
        var $sel = $('#sc_qa_member_selected');
        $sel.html(
            '<span class="sc-qa-selected-chip">' +
            '<strong>بازیکن انتخاب‌شده:</strong> ' + $('<span>').text(item.label).html() +
            ' <button type="button" class="sc-qa-clear-member" aria-label="حذف انتخاب">×</button></span>'
        ).show();
        resetValidationState('existing');
        $('#sc_qa_existing_validation').hide().empty();
        $('#sc_qa_existing_message').empty();
    }

    function collectExistingData() {
        return {
            member_id: $('#sc_qa_member_id').val(),
            course_id: $('#sc_qa_existing_course').val(),
            chapter: $('#sc_qa_existing_chapter').val(),
            coach_id: $('#sc_qa_existing_coach').val() || 0,
            group_name: $('#sc_qa_existing_group').is(':visible') ? ($('#sc_qa_existing_group').val() || '') : '',
            package_sessions: $('#sc_qa_existing_package').closest('tr').is(':visible') ? ($('#sc_qa_existing_package').val() || '') : '',
            remaining_sessions: $('#sc_qa_existing_remaining').val(),
            payment_status: $('#sc_qa_existing_payment').val()
        };
    }

    function collectNewData() {
        return {
            mobile: $('#sc_qa_new_mobile').val(),
            first_name: $('#sc_qa_new_first').val(),
            last_name: $('#sc_qa_new_last').val(),
            password: $('#sc_qa_new_password').val(),
            course_id: $('#sc_qa_new_course').val(),
            chapter: $('#sc_qa_new_chapter').val(),
            coach_id: $('#sc_qa_new_coach').val() || 0,
            group_name: $('#sc_qa_new_group').is(':visible') ? ($('#sc_qa_new_group').val() || '') : '',
            package_sessions: $('#sc_qa_new_package').closest('tr').is(':visible') ? ($('#sc_qa_new_package').val() || '') : '',
            remaining_sessions: $('#sc_qa_new_remaining').val(),
            payment_status: $('#sc_qa_new_payment').val()
        };
    }

    function dataFingerprint(data) {
        // وضعیت پرداخت در fingerprint نیست تا بعد از بررسی بتوان آن را عوض کرد
        var copy = $.extend({}, data || {});
        delete copy.payment_status;
        return JSON.stringify(copy);
    }

    function runValidate(tab, collectData, $msg, $validation) {
        unlockAudio();
        var btns = tabButtons(tab);
        var $check = btns.$check;
        var $confirm = btns.$confirm;
        if ($check.data('sc-busy')) {
            return;
        }

        var data = collectData();
        data.action = 'sc_secretary_validate_enroll';
        data.nonce = cfg.nonce;
        data.tab = tab;

        $validation.hide().empty();
        showMsg($msg, '', null);
        $confirm.prop('disabled', true);
        setLoading($check, true, 'در حال بررسی...', 'is-checking');

        $.post(cfg.ajaxUrl, data).done(function (res) {
            setLoading($check, false);
            setButtonState($check, '', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
            if (!res.success) {
                var payload = res.data || {};
                renderValidation($validation, payload.messages || [{ type: 'error', text: payload.message || 'امکان ثبت‌نام وجود ندارد.' }]);
                showMsg($msg, 'بررسی ناموفق — موارد زیر را اصلاح کنید. هنوز هیچ فاکتوری ساخته نشده است.', false);
                playSound(payload.sound || 'error');
                qaState[tab].validated = false;
                $confirm.prop('disabled', true);
                setButtonState($check, 'is-error-flash');
                setTimeout(function () { $check.removeClass('is-error-flash'); }, 1200);
                return;
            }

            renderValidation($validation, res.data.messages || []);
            qaState[tab].validated = true;
            qaState[tab].lastData = collectData();
            showMsg($msg, 'بررسی موفق بود. هنوز فاکتوری ساخته نشده — برای صدور فاکتور روی «تایید و ثبت در دوره» بزنید.', 'info');
            playSound(res.data.sound || 'success');
            $confirm.prop('disabled', false);
            setButtonState($confirm, 'is-ready');
        }).fail(function (xhr) {
            setLoading($check, false);
            setButtonState($check, '', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
            var payload = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : {};
            renderValidation($validation, payload.messages || [{ type: 'error', text: payload.message || 'خطای ارتباط با سرور.' }]);
            showMsg($msg, payload.message || 'خطای ارتباط با سرور.', false);
            playSound(payload.sound || 'error');
            qaState[tab].validated = false;
            $confirm.prop('disabled', true);
        });
    }

    function runEnroll(tab, collectData, $msg, $validation) {
        unlockAudio();
        var btns = tabButtons(tab);
        var $check = btns.$check;
        var $confirm = btns.$confirm;
        if ($confirm.data('sc-busy') || $confirm.prop('disabled')) {
            return;
        }
        if (!qaState[tab].validated) {
            showMsg($msg, 'ابتدا «بررسی اطلاعات» را بزنید.', 'warning');
            playSound('error');
            return;
        }

        var enrollData = collectData();
        if (qaState[tab].lastData && dataFingerprint(enrollData) !== dataFingerprint(qaState[tab].lastData)) {
            qaState[tab].validated = false;
            $confirm.prop('disabled', true);
            showMsg($msg, 'اطلاعات تغییر کرده است. دوباره «بررسی اطلاعات» را بزنید. هنوز فاکتوری ساخته نشده است.', 'warning');
            playSound('debt_warning');
            return;
        }

        qaState[tab].validated = false;
        enrollData.action = tab === 'new' ? 'sc_secretary_quick_register' : 'sc_secretary_quick_enroll';
        enrollData.nonce = cfg.nonce;

        $check.prop('disabled', true);
        setLoading($confirm, true, 'در حال ثبت نهایی...', 'is-checking');
        showMsg($msg, 'در حال ثبت نهایی و صدور فاکتور...', 'info');

        $.post(cfg.ajaxUrl, enrollData).done(function (enrollRes) {
            if (enrollRes.success) {
                showMsg($msg, enrollRes.data.message || 'انجام شد.', true);
                playSound('success');
                $validation.hide().empty();
                if (tab === 'new') {
                    $('#sc_qa_new_mobile, #sc_qa_new_first, #sc_qa_new_last, #sc_qa_new_password').val('');
                    $('#sc_qa_new_remaining').val('').removeData('auto-filled');
                }
                if (tab === 'existing') {
                    clearSelectedMember();
                    $('#sc_qa_existing_remaining').val('').removeData('auto-filled');
                }
                resetValidationState(tab);
            } else {
                var errText = (enrollRes.data && enrollRes.data.message) || 'خطا در ثبت نهایی';
                renderValidation($validation, [{ type: 'error', text: errText }]);
                showMsg($msg, errText, false);
                playSound('error');
                setLoading($confirm, false);
                setButtonState($confirm, '', cfg.labels && cfg.labels.confirm ? cfg.labels.confirm : 'تایید و ثبت در دوره');
                $confirm.prop('disabled', true);
                $check.prop('disabled', false);
                qaState[tab].validated = false;
            }
        }).fail(function () {
            showMsg($msg, 'خطای ارتباط با سرور هنگام ثبت نهایی.', false);
            playSound('error');
            setLoading($confirm, false);
            setButtonState($confirm, '', cfg.labels && cfg.labels.confirm ? cfg.labels.confirm : 'تایید و ثبت در دوره');
            $confirm.prop('disabled', true);
            $check.prop('disabled', false);
            qaState[tab].validated = false;
        });
    }

    initSounds();
    resetValidationState('existing');
    resetValidationState('new');

    $('.sc-secretary-qa-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.sc-secretary-qa-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.sc-secretary-qa-panel').hide();
        $('#sc-qa-' + tab).show();
    });

    $(document).on('click', '.sc-qa-clear-member', function (e) {
        e.preventDefault();
        clearSelectedMember();
        resetValidationState('existing');
    });

    $('#sc_qa_existing_course, #sc_qa_new_course').on('change', function () {
        var isExisting = $(this).attr('id') === 'sc_qa_existing_course';
        var prefix = isExisting ? 'existing' : 'new';
        $('#sc_qa_' + prefix + '_remaining').val('').removeData('auto-filled');
        loadChaptersForCourse(prefix);
        resetValidationState(prefix);
    });

    $('#sc_qa_existing_chapter, #sc_qa_new_chapter').on('change', function () {
        var isExisting = $(this).attr('id') === 'sc_qa_existing_chapter';
        var prefix = isExisting ? 'existing' : 'new';
        loadEnrollmentOptions(prefix);
        resetValidationState(prefix);
    });

    $('#sc_qa_existing_coach, #sc_qa_new_coach').on('change', function () {
        var isExisting = $(this).attr('id') === 'sc_qa_existing_coach';
        var prefix = isExisting ? 'existing' : 'new';
        var chapter = $('#sc_qa_' + prefix + '_chapter').val();
        var coachId = parseInt($(this).val(), 10) || 0;
        fillGroupSelect(prefix, chapter, coachId);
        resetValidationState(prefix);
    });

    $('#sc_qa_existing_package, #sc_qa_new_package').on('change', function () {
        var isExisting = $(this).attr('id') === 'sc_qa_existing_package';
        var prefix = isExisting ? 'existing' : 'new';
        applyPackageSelection(prefix);
        resetValidationState(prefix);
    });

    $('#sc_qa_existing_group, #sc_qa_existing_remaining, #sc_qa_existing_chapter, #sc_qa_new_mobile, #sc_qa_new_first, #sc_qa_new_last, #sc_qa_new_password, #sc_qa_new_group, #sc_qa_new_remaining, #sc_qa_new_chapter').on('change input', function () {
        var id = $(this).attr('id') || '';
        if (id.indexOf('existing') !== -1 || id === 'sc_qa_member_search') {
            resetValidationState('existing');
        }
        if (id.indexOf('new') !== -1) {
            resetValidationState('new');
        }
        if (id.indexOf('_remaining') !== -1) {
            $(this).removeData('auto-filled');
        }
    });

    // تغییر وضعیت پرداخت بررسی را باطل نمی‌کند (فقط روی فاکتور نهایی اثر دارد)
    $('#sc_qa_existing_payment, #sc_qa_new_payment').on('change', function () {
        var tab = $(this).attr('id') === 'sc_qa_new_payment' ? 'new' : 'existing';
        if (qaState[tab].validated && qaState[tab].lastData) {
            qaState[tab].lastData.payment_status = $(this).val();
        }
    });

    var searchTimer;
    $('#sc_qa_member_search').on('focus', unlockAudio);
    $('#sc_qa_member_search').on('input', function () {
        var q = $(this).val().trim();
        clearTimeout(searchTimer);
        resetValidationState('existing');
        var $box = $('#sc_qa_member_results');
        if (q.length < 1) {
            $box.empty();
            return;
        }
        $box.html('<p class="sc-qa-search-hint">در حال جستجو...</p>');
        searchTimer = setTimeout(function () {
            $.get(cfg.ajaxUrl, {
                action: 'sc_secretary_search_members',
                nonce: cfg.nonce,
                q: q
            }).done(function (res) {
                $box.empty();
                if (!res.success) {
                    $box.html('<p class="sc-qa-search-error">خطا در جستجو. دوباره تلاش کنید.</p>');
                    return;
                }
                if (!res.data.items || !res.data.items.length) {
                    $box.html('<p class="sc-qa-search-empty">نتیجه‌ای یافت نشد. نام، موبایل یا کد ملی را بررسی کنید.</p>');
                    return;
                }
                var $ul = $('<ul></ul>');
                res.data.items.forEach(function (item) {
                    $('<li></li>').text(item.label).data('item', item).appendTo($ul);
                });
                $box.append($ul);
            }).fail(function () {
                $box.html('<p class="sc-qa-search-error">خطا در ارتباط با سرور.</p>');
            });
        }, 280);
    });

    $(document).on('click', '#sc_qa_member_results li', function () {
        var item = $(this).data('item');
        if (item) {
            selectMember(item);
        }
    });

    $('#sc_qa_existing_check').on('click', function () {
        var $msg = $('#sc_qa_existing_message');
        var $validation = $('#sc_qa_existing_validation');
        if (!$('#sc_qa_member_id').val()) {
            showMsg($msg, 'ابتدا بازیکن را از نتایج جستجو انتخاب کنید.', false);
            playSound('error');
            return;
        }
        runValidate('existing', collectExistingData, $msg, $validation);
    });

    $('#sc_qa_existing_confirm').on('click', function () {
        runEnroll('existing', collectExistingData, $('#sc_qa_existing_message'), $('#sc_qa_existing_validation'));
    });

    $('#sc_qa_new_check').on('click', function () {
        runValidate('new', collectNewData, $('#sc_qa_new_message'), $('#sc_qa_new_validation'));
    });

    $('#sc_qa_new_confirm').on('click', function () {
        runEnroll('new', collectNewData, $('#sc_qa_new_message'), $('#sc_qa_new_validation'));
    });

    if ($('#sc_qa_existing_course').val()) {
        loadChaptersForCourse('existing');
    }
    if ($('#sc_qa_new_course').val()) {
        loadChaptersForCourse('new');
    }
})(jQuery);
