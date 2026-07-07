(function ($) {
    'use strict';

    var cfg = window.scSecretaryQuick || {};

    $('.sc-secretary-qa-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.sc-secretary-qa-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.sc-secretary-qa-panel').hide();
        $('#sc-qa-' + tab).show();
    });

    var searchTimer;
    $('#sc_qa_member_search').on('input', function () {
        var q = $(this).val().trim();
        clearTimeout(searchTimer);
        if (q.length < 2) {
            $('#sc_qa_member_results').empty();
            return;
        }
        searchTimer = setTimeout(function () {
            $.get(cfg.ajaxUrl, {
                action: 'sc_secretary_search_members',
                nonce: cfg.nonce,
                q: q
            }).done(function (res) {
                var $box = $('#sc_qa_member_results');
                $box.empty();
                if (!res.success || !res.data.items || !res.data.items.length) {
                    $box.html('<p class="description">نتیجه‌ای یافت نشد.</p>');
                    return;
                }
                var $ul = $('<ul></ul>');
                res.data.items.forEach(function (item) {
                    $('<li></li>').text(item.label).data('id', item.id).appendTo($ul);
                });
                $box.append($ul);
            });
        }, 300);
    });

    $(document).on('click', '#sc_qa_member_results li', function () {
        var id = $(this).data('id');
        var label = $(this).text();
        $('#sc_qa_member_id').val(id);
        $('#sc_qa_member_search').val(label);
        $('#sc_qa_member_results').empty();
    });

    function showMsg($el, text, ok) {
        $el.removeClass('is-error is-success').addClass(ok ? 'is-success' : 'is-error').text(text);
    }

    $('#sc_qa_existing_submit').on('click', function () {
        var $msg = $('#sc_qa_existing_message');
        var memberId = $('#sc_qa_member_id').val();
        if (!memberId) {
            showMsg($msg, 'بازیکن را انتخاب کنید.', false);
            return;
        }
        $.post(cfg.ajaxUrl, {
            action: 'sc_secretary_quick_enroll',
            nonce: cfg.nonce,
            member_id: memberId,
            course_id: $('#sc_qa_existing_course').val(),
            chapter: $('#sc_qa_existing_chapter').val(),
            payment_status: $('#sc_qa_existing_payment').val()
        }).done(function (res) {
            if (res.success) {
                showMsg($msg, res.data.message || 'انجام شد.', true);
            } else {
                showMsg($msg, (res.data && res.data.message) || 'خطا', false);
            }
        }).fail(function () {
            showMsg($msg, 'خطای ارتباط با سرور.', false);
        });
    });

    $('#sc_qa_new_submit').on('click', function () {
        var $msg = $('#sc_qa_new_message');
        $.post(cfg.ajaxUrl, {
            action: 'sc_secretary_quick_register',
            nonce: cfg.nonce,
            mobile: $('#sc_qa_new_mobile').val(),
            first_name: $('#sc_qa_new_first').val(),
            last_name: $('#sc_qa_new_last').val(),
            course_id: $('#sc_qa_new_course').val(),
            chapter: $('#sc_qa_new_chapter').val(),
            payment_status: $('#sc_qa_new_payment').val()
        }).done(function (res) {
            if (res.success) {
                showMsg($msg, res.data.message || 'انجام شد.', true);
                $('#sc_qa_new_mobile, #sc_qa_new_first, #sc_qa_new_last').val('');
            } else {
                showMsg($msg, (res.data && res.data.message) || 'خطا', false);
            }
        }).fail(function () {
            showMsg($msg, 'خطای ارتباط با سرور.', false);
        });
    });
})(jQuery);
