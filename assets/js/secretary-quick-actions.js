(function ($) {
    'use strict';

    var cfg = window.scSecretaryQuick || {};
    var sounds = {};
    var audioUnlocked = false;
    var qaState = {
        existing: { validated: false, lastData: null },
        new: { validated: false, lastData: null }
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

    function setButtonState($btn, state, text) {
        $btn.removeClass('is-checking is-ready is-error-flash');
        if (state) {
            $btn.addClass(state);
        }
        if (typeof text === 'string') {
            $btn.text(text);
        }
    }

    function resetButton($btn, tab) {
        qaState[tab].validated = false;
        qaState[tab].lastData = null;
        $btn.prop('disabled', false);
        setButtonState($btn, '', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
    }

    function setLoading($btn, loading, loadingText, loadingClass) {
        if (loading) {
            if (!$btn.data('orig-text')) {
                $btn.data('orig-text', $btn.text());
            }
            $btn.prop('disabled', true);
            setButtonState($btn, loadingClass || 'is-checking', loadingText || 'لطفاً صبر کنید...');
        } else {
            $btn.prop('disabled', false);
        }
    }

    function refreshCourses($select, chapter) {
        if (!$select.length) {
            return;
        }
        $select.prop('disabled', true);
        $.get(cfg.ajaxUrl, {
            action: 'sc_secretary_quick_action_courses',
            nonce: cfg.nonce,
            chapter: chapter || ''
        }).done(function (res) {
            $select.empty().append($('<option value="">— انتخاب دوره —</option>'));
            if (res.success && res.data.items && res.data.items.length) {
                res.data.items.forEach(function (item) {
                    $('<option></option>').val(String(item.id)).text(item.title).appendTo($select);
                });
            } else {
                $select.append($('<option value="" disabled>دوره‌ای با برنامه هفتگی فعال برای این شعبه نیست</option>'));
            }
        }).fail(function () {
            $select.empty().append($('<option value="">خطا در بارگذاری دوره‌ها</option>'));
        }).always(function () {
            $select.prop('disabled', false);
        });
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
        resetButton($('#sc_qa_existing_submit'), 'existing');
        $('#sc_qa_existing_validation').hide().empty();
        $('#sc_qa_existing_message').empty();
    }

    function collectExistingData() {
        return {
            member_id: $('#sc_qa_member_id').val(),
            course_id: $('#sc_qa_existing_course').val(),
            chapter: $('#sc_qa_existing_chapter').val(),
            payment_status: $('#sc_qa_existing_payment').val()
        };
    }

    function collectNewData() {
        return {
            mobile: $('#sc_qa_new_mobile').val(),
            first_name: $('#sc_qa_new_first').val(),
            last_name: $('#sc_qa_new_last').val(),
            course_id: $('#sc_qa_new_course').val(),
            chapter: $('#sc_qa_new_chapter').val(),
            payment_status: $('#sc_qa_new_payment').val()
        };
    }

    function runValidate(tab, $btn, collectData, $msg, $validation) {
        unlockAudio();
        var data = collectData();
        data.action = 'sc_secretary_validate_enroll';
        data.nonce = cfg.nonce;
        data.tab = tab;

        $validation.hide().empty();
        showMsg($msg, '', null);
        setLoading($btn, true, 'در حال بررسی...', 'is-checking');

        $.post(cfg.ajaxUrl, data).done(function (res) {
            if (!res.success) {
                var payload = res.data || {};
                renderValidation($validation, payload.messages || [{ type: 'error', text: payload.message || 'امکان ثبت‌نام وجود ندارد.' }]);
                showMsg($msg, 'بررسی ناموفق — موارد زیر را اصلاح کنید.', false);
                playSound(payload.sound || 'error');
                qaState[tab].validated = false;
                setButtonState($btn, 'is-error-flash', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
                $btn.prop('disabled', false);
                setTimeout(function () {
                    $btn.removeClass('is-error-flash');
                }, 1200);
                return;
            }

            renderValidation($validation, res.data.messages || []);
            qaState[tab].validated = true;
            qaState[tab].lastData = collectData();
            showMsg($msg, 'بررسی موفق بود. برای ثبت نهایی دوباره روی دکمه بزنید.', 'info');
            playSound(res.data.sound || 'success');
            setButtonState($btn, 'is-ready', cfg.labels && cfg.labels.confirm ? cfg.labels.confirm : 'تایید و ثبت در دوره');
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            var payload = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : {};
            renderValidation($validation, payload.messages || [{ type: 'error', text: payload.message || 'خطای ارتباط با سرور.' }]);
            showMsg($msg, payload.message || 'خطای ارتباط با سرور.', false);
            playSound(payload.sound || 'error');
            qaState[tab].validated = false;
            setButtonState($btn, 'is-error-flash', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
            $btn.prop('disabled', false);
        });
    }

    function runEnroll(tab, $btn, collectData, $msg, $validation) {
        unlockAudio();
        var enrollData = collectData();
        enrollData.action = tab === 'new' ? 'sc_secretary_quick_register' : 'sc_secretary_quick_enroll';
        enrollData.nonce = cfg.nonce;

        setLoading($btn, true, 'در حال ثبت نهایی...', 'is-checking');
        showMsg($msg, 'در حال ثبت نهایی...', 'info');

        $.post(cfg.ajaxUrl, enrollData).done(function (enrollRes) {
            if (enrollRes.success) {
                showMsg($msg, enrollRes.data.message || 'انجام شد.', true);
                playSound('success');
                $validation.show();
                if (tab === 'new') {
                    $('#sc_qa_new_mobile, #sc_qa_new_first, #sc_qa_new_last').val('');
                }
                if (tab === 'existing') {
                    clearSelectedMember();
                }
                resetButton($btn, tab);
            } else {
                var errText = (enrollRes.data && enrollRes.data.message) || 'خطا در ثبت نهایی';
                renderValidation($validation, [{ type: 'error', text: errText }]);
                showMsg($msg, errText, false);
                playSound('error');
                qaState[tab].validated = false;
                setButtonState($btn, 'is-error-flash', cfg.labels && cfg.labels.check ? cfg.labels.check : 'بررسی اطلاعات');
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            showMsg($msg, 'خطای ارتباط با سرور هنگام ثبت نهایی.', false);
            playSound('error');
            $btn.prop('disabled', false);
        });
    }

    function handleSubmit(tab, $btn, collectData, $msg, $validation) {
        if (qaState[tab].validated) {
            runEnroll(tab, $btn, collectData, $msg, $validation);
            return;
        }
        runValidate(tab, $btn, collectData, $msg, $validation);
    }

    initSounds();

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
    });

    $('#sc_qa_existing_chapter, #sc_qa_new_chapter').on('change', function () {
        var chapter = $(this).val();
        var isExisting = $(this).attr('id') === 'sc_qa_existing_chapter';
        refreshCourses(isExisting ? $('#sc_qa_existing_course') : $('#sc_qa_new_course'), chapter);
        if (isExisting) {
            resetButton($('#sc_qa_existing_submit'), 'existing');
        } else {
            resetButton($('#sc_qa_new_submit'), 'new');
        }
    });

    $('#sc_qa_existing_course, #sc_qa_existing_payment, #sc_qa_new_course, #sc_qa_new_payment, #sc_qa_new_mobile, #sc_qa_new_first, #sc_qa_new_last').on('change input', function () {
        var id = $(this).attr('id') || '';
        if (id.indexOf('existing') !== -1 || id === 'sc_qa_member_search') {
            resetButton($('#sc_qa_existing_submit'), 'existing');
        }
        if (id.indexOf('new') !== -1) {
            resetButton($('#sc_qa_new_submit'), 'new');
        }
    });

    var searchTimer;
    $('#sc_qa_member_search').on('focus', unlockAudio);
    $('#sc_qa_member_search').on('input', function () {
        var q = $(this).val().trim();
        clearTimeout(searchTimer);
        resetButton($('#sc_qa_existing_submit'), 'existing');
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

    $('#sc_qa_existing_submit').on('click', function () {
        var $btn = $(this);
        var $msg = $('#sc_qa_existing_message');
        var $validation = $('#sc_qa_existing_validation');
        if (!$('#sc_qa_member_id').val()) {
            showMsg($msg, 'ابتدا بازیکن را از نتایج جستجو انتخاب کنید.', false);
            playSound('error');
            setButtonState($btn, 'is-error-flash');
            setTimeout(function () { $btn.removeClass('is-error-flash'); }, 1200);
            return;
        }
        handleSubmit('existing', $btn, collectExistingData, $msg, $validation);
    });

    $('#sc_qa_new_submit').on('click', function () {
        var $btn = $(this);
        var $msg = $('#sc_qa_new_message');
        var $validation = $('#sc_qa_new_validation');
        handleSubmit('new', $btn, collectNewData, $msg, $validation);
    });
})(jQuery);
