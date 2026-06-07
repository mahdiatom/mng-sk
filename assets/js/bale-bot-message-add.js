(function ($) {
    'use strict';

    var cfg = window.scBaleBotMessage || {};
    var ajaxUrl = cfg.ajaxurl || '';
    var previewLoaded = false;
    var excelPhoneCount = 0;

    var recipientIds = [];
    var excludeRecipientIds = [];
    var eventRecipientIds = [];
    var phoneNumbers = [];
    var recipientLabels = {};

    function getDeliveryMode() {
        var m = $('input[name="delivery_mode"]:checked').val();
        return m || 'bot_only';
    }

    function getTargetConfig() {
        var targetType = $('#target_type').val();
        var config = {};

        if (targetType === 'all') {
            config.user_type = $('#user_type').val() || 'all';
            config.course_scope = $('#course_scope').val() || 'all';
            if (config.course_scope === 'specific') {
                config.course_ids = ($('select[name="course_ids[]"]').val() || []).map(Number);
            }
        } else if (targetType === 'free_users') {
            config.user_type = 'player';
        } else if (targetType === 'specific') {
            config.recipient_ids = recipientIds;
        } else if (targetType === 'course') {
            config.course_ids = ($('#course-ids-course').val() || []).map(Number);
        } else if (targetType === 'team') {
            config.team_names = ($('#team-names-select').val() || []);
        } else if (targetType === 'level') {
            config.level_names = ($('#level-names-select').val() || []);
        } else if (targetType === 'debtors') {
            config.course_ids = ($('#debtors-course-ids').val() || []).map(Number);
        } else if (targetType === 'event') {
            config.event_ids = ($('#event-ids-select').val() || []).map(Number);
            config.recipient_ids = eventRecipientIds;
        } else if (targetType === 'phone') {
            config.phone_numbers = phoneNumbers;
        } else if (targetType === 'team_level') {
            config.team_names = ($('#team-level-team-select').val() || []);
            config.level_names = ($('#team-level-level-select').val() || []);
        }

        if (targetType !== 'specific') {
            config.exclude_recipient_ids = excludeRecipientIds;
        }
        return config;
    }

    function serializeConfig(cfg) {
        var out = $.extend({}, cfg);
        if (out.recipient_ids && Array.isArray(out.recipient_ids)) {
            out.recipient_ids = out.recipient_ids.join(',');
        }
        if (out.exclude_recipient_ids && Array.isArray(out.exclude_recipient_ids)) {
            out.exclude_recipient_ids = out.exclude_recipient_ids.join(',');
        }
        if (out.course_ids && Array.isArray(out.course_ids)) {
            out.course_ids = out.course_ids.join(',');
        }
        if (out.event_ids && Array.isArray(out.event_ids)) {
            out.event_ids = out.event_ids.join(',');
        }
        if (out.phone_numbers && Array.isArray(out.phone_numbers)) {
            out.phone_numbers = out.phone_numbers.join(',');
        }
        return out;
    }

    function updateLiveCounts(data) {
        if (!data) {
            $('#sc-bale-live-counts').hide();
            return;
        }
        $('#sc-bale-count-with').text(data.with_chat_id || 0);
        $('#sc-bale-count-without').text(data.without_chat_id || 0);
        $('#sc-bale-count-send').text(data.send_total || 0);
        $('#sc-bale-live-counts').show();
    }

    function rowSendableInMode($row, mode) {
        var hasChat = String($row.data('has-chat')) === '1';
        var hasPhone = String($row.data('has-phone')) === '1';
        if (mode === 'bot_only') {
            return hasChat;
        }
        if (mode === 'safir_only') {
            return !hasChat && hasPhone;
        }
        if (mode === 'both') {
            return hasChat || hasPhone;
        }
        return false;
    }

    function recalculatePreviewCounts() {
        var mode = getDeliveryMode();
        var sendable = 0;
        var bot = 0;
        var safir = 0;
        var withChat = 0;
        var withoutChat = 0;

        $('.sc-bale-preview-member-check').each(function () {
            var $row = $(this).closest('tr');
            var hasChat = String($row.data('has-chat')) === '1';
            if (hasChat) {
                withChat++;
            } else {
                withoutChat++;
            }
        });

        $('.sc-bale-preview-member-check:checked').each(function () {
            var $row = $(this).closest('tr');
            var hasChat = String($row.data('has-chat')) === '1';
            var hasPhone = String($row.data('has-phone')) === '1';
            if (!rowSendableInMode($row, mode)) {
                return;
            }
            sendable++;
            if (mode === 'bot_only' && hasChat) {
                bot++;
            } else if (mode === 'safir_only' && !hasChat && hasPhone) {
                safir++;
            } else if (mode === 'both') {
                if (hasChat) {
                    bot++;
                } else if (hasPhone) {
                    safir++;
                }
            }
        });

        $('#sc-bale-preview-send, #sc-bale-count-send').text(sendable);
        $('#sc-bale-preview-bot').text(bot);
        $('#sc-bale-preview-safir').text(safir);
        $('#sc-bale-preview-with, #sc-bale-count-with').text(withChat);
        $('#sc-bale-preview-without, #sc-bale-count-without').text(withoutChat);
        $('#sc-bale-live-counts').show();
    }

    function initBalePreviewSelectionBindings() {
        var $checks = $('.sc-bale-preview-member-check');
        if (!$checks.length) {
            return;
        }

        $checks.each(function () {
            var rid = String($(this).attr('data-recipient-id') || '');
            $(this).prop('checked', rid !== '' && excludeRecipientIds.indexOf(rid) === -1);
        });

        var checkedCount = $('.sc-bale-preview-member-check:checked').length;
        $('#sc-bale-preview-select-all').prop('checked', checkedCount === $checks.length);

        $(document).off('change.scBalePreviewMember').on('change.scBalePreviewMember', '.sc-bale-preview-member-check', function () {
            var rid = String($(this).attr('data-recipient-id') || '');
            if (!rid) {
                return;
            }
            if ($(this).is(':checked')) {
                excludeRecipientIds = excludeRecipientIds.filter(function (x) { return x !== rid; });
            } else if (excludeRecipientIds.indexOf(rid) === -1) {
                excludeRecipientIds.push(rid);
            }
            $('#exclude-recipient-ids-input').val(excludeRecipientIds.join(','));
            renderExcludeRecipientList();
            recalculatePreviewCounts();

            var allCount = $('.sc-bale-preview-member-check').length;
            var selectedCount = $('.sc-bale-preview-member-check:checked').length;
            $('#sc-bale-preview-select-all').prop('checked', allCount > 0 && selectedCount === allCount);
        });

        $(document).off('change.scBalePreviewSelectAll').on('change.scBalePreviewSelectAll', '#sc-bale-preview-select-all', function () {
            var shouldCheck = $(this).is(':checked');
            $('.sc-bale-preview-member-check').each(function () {
                var rid = String($(this).attr('data-recipient-id') || '');
                $(this).prop('checked', shouldCheck);
                if (!rid) {
                    return;
                }
                if (shouldCheck) {
                    excludeRecipientIds = excludeRecipientIds.filter(function (x) { return x !== rid; });
                } else if (excludeRecipientIds.indexOf(rid) === -1) {
                    excludeRecipientIds.push(rid);
                }
            });
            $('#exclude-recipient-ids-input').val(excludeRecipientIds.join(','));
            renderExcludeRecipientList();
            recalculatePreviewCounts();
        });

        recalculatePreviewCounts();
    }

    function runPreview() {
        var $btn = $('#sc-bale-preview-btn');
        var $result = $('#sc-bale-preview-result');
        if (!$btn.length) {
            return;
        }

        $btn.prop('disabled', true);
        $result.html('<p class="description">در حال دریافت پیش‌نمایش...</p>');

        $.post(ajaxUrl, {
            action: 'sc_bale_preview_recipients',
            nonce: $('#sc-bale-preview-nonce').val() || cfg.nonce,
            target_type: $('#target_type').val(),
            target_config: serializeConfig(getTargetConfig()),
            delivery_mode: getDeliveryMode()
        }).done(function (res) {
            if (res && res.success && res.data) {
                $result.html(res.data.html || '');
                $('.sc-bale-preview-member-check').each(function () {
                    var rid = String($(this).attr('data-recipient-id') || '');
                    var name = $(this).closest('tr').find('td').eq(1).text().trim();
                    if (rid && name) {
                        recipientLabels[rid] = name;
                    }
                });
                previewLoaded = true;
                updateLiveCounts(res.data);
                initBalePreviewSelectionBindings();
            } else {
                $result.html('<p class="description">خطا در دریافت پیش‌نمایش.</p>');
            }
        }).fail(function () {
            $result.html('<p class="description">خطا در ارتباط با سرور.</p>');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    function toggleTargetRows() {
        var t = $('#target_type').val();
        $('.target-row').hide();
        if ($('#row-target-' + t).length) {
            $('#row-target-' + t).show();
        }
        if (t === 'all') {
            $('#row-course-ids-all').toggle($('#course_scope').val() === 'specific');
        }
        $('#row-target-exclude').toggle(t !== 'specific' && t !== 'phone');
        $('#sc-bale-preview-submit-wrap, #sc-bale-preview-bulk-cards').toggle(true);

        if (t === 'phone') {
            $('input[name="delivery_mode"][value="safir_only"]').prop('checked', true);
            $('input[name="delivery_mode"][value="bot_only"]').prop('disabled', true);
        } else {
            $('input[name="delivery_mode"][value="bot_only"]').prop('disabled', false);
        }
    }

    function renderRecipientList() {
        var html = '';
        recipientIds.forEach(function (id) {
            var lbl = recipientLabels[id] || id;
            html += '<span class="recipient-tag" data-id="' + id + '">' + lbl + ' <button type="button" class="recipient-remove">&times;</button></span> ';
        });
        $('#recipient-list').html(html || '<em style="color:#999;">هنوز کسی انتخاب نشده</em>');
        $('#recipient-ids-input').val(recipientIds.join(','));
    }

    function renderExcludeRecipientList() {
        var html = '';
        excludeRecipientIds.forEach(function (id) {
            var lbl = recipientLabels[id] || id;
            html += '<span class="recipient-tag" data-id="' + id + '">' + lbl + ' <button type="button" class="exclude-recipient-remove">&times;</button></span> ';
        });
        $('#exclude-recipient-list').html(html || '<em style="color:#999;">هیچ استثنایی ثبت نشده</em>');
        $('#exclude-recipient-ids-input').val(excludeRecipientIds.join(','));
    }

    function renderPhoneList() {
        var html = '';
        phoneNumbers.forEach(function (ph) {
            html += '<span class="recipient-tag phone-tag" data-phone="' + ph.replace(/"/g, '&quot;') + '">' + ph + ' <button type="button" class="recipient-remove">&times;</button></span> ';
        });
        $('#phone-list').html(html || '<em style="color:#999;">هنوز شماره‌ای اضافه نشده</em>');
        $('#phone-numbers-input').val(phoneNumbers.join(','));
    }

    $(function () {
        if (!$('#bale-bot-message-form').length) {
            return;
        }

        toggleTargetRows();
        renderRecipientList();
        renderExcludeRecipientList();
        renderPhoneList();

        $('#target_type, #course_scope, #user_type').on('change', function () {
            toggleTargetRows();
            previewLoaded = false;
        });

        $('input[name="delivery_mode"]').on('change', function () {
            if (!$('.sc-bale-preview-member-check').length) {
                previewLoaded = false;
                return;
            }
            var mode = getDeliveryMode();
            $('.sc-bale-preview-member-check').each(function () {
                var $row = $(this).closest('tr');
                var canSend = rowSendableInMode($row, mode);
                $row.attr('data-can-send', canSend ? '1' : '0');
                $row.find('td:last').html(canSend
                    ? '<span style="color:#15803d;">✓ قابل ارسال</span>'
                    : '<span style="color:#9ca3af;">— در این حالت</span>');
            });
            recalculatePreviewCounts();
        });

        $(document).on('change', 'select[name="course_ids[]"], #debtors-course-ids, #event-ids-select, #team-names-select, #level-names-select, #team-level-team-select, #team-level-level-select', function () {
            previewLoaded = false;
        });

        $('#sc-bale-preview-btn').on('click', runPreview);

        $(document).on('click', '.recipient-remove', function () {
            var id = $(this).closest('.recipient-tag').data('id');
            recipientIds = recipientIds.filter(function (x) { return x !== id; });
            renderRecipientList();
            previewLoaded = false;
        });

        $(document).on('click', '.exclude-recipient-remove', function () {
            var id = String($(this).closest('.recipient-tag').data('id') || '');
            excludeRecipientIds = excludeRecipientIds.filter(function (x) { return x !== id; });
            renderExcludeRecipientList();
            if (id) {
                $('.sc-bale-preview-member-check[data-recipient-id="' + id.replace(/"/g, '\\"') + '"]').prop('checked', true);
                recalculatePreviewCounts();
            } else {
                previewLoaded = false;
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var opt = e.target.closest('.sc-dropdown-option');
            if (!opt) return;
            e.preventDefault();
            e.stopPropagation();
            var $opt = $(opt);
            if (!$opt.closest('.sc-notification-recipient-dropdown').length && !$opt.closest('.sc-exclude-recipient-dropdown').length) return;
            var val = $opt.data('value');
            var lbl = $opt.data('label') || $opt.text().trim();
            var isExclude = $opt.closest('.sc-exclude-recipient-dropdown').length;
            if (isExclude) {
                if (val && excludeRecipientIds.indexOf(val) === -1) {
                    excludeRecipientIds.push(val);
                    recipientLabels[val] = lbl;
                }
                renderExcludeRecipientList();
                $('.sc-bale-preview-member-check[data-recipient-id="' + String(val).replace(/"/g, '\\"') + '"]').prop('checked', false);
                recalculatePreviewCounts();
            } else {
                if (val && recipientIds.indexOf(val) === -1) {
                    recipientIds.push(val);
                    recipientLabels[val] = lbl;
                }
                renderRecipientList();
            }
            $opt.closest('.sc-dropdown-menu').slideUp(200);
            previewLoaded = false;
        }, true);

        $('#phone-add-btn').on('click', function () {
            var inp = $('#phone-input').val().trim().replace(/\D/g, '');
            if (inp.length === 10 && inp.startsWith('9')) inp = '0' + inp;
            else if (inp.length === 12 && inp.startsWith('98')) inp = '0' + inp.slice(2);
            if (inp.length !== 11 || !inp.startsWith('09')) {
                alert('شماره موبایل معتبر وارد کنید (مثال: ۰۹۱۲۳۴۵۶۷۸۹)');
                return;
            }
            if (phoneNumbers.indexOf(inp) === -1) {
                phoneNumbers.push(inp);
                renderPhoneList();
                $('#phone-input').val('');
                previewLoaded = false;
            }
        });

        $(document).on('click', '#phone-list .recipient-remove', function () {
            var ph = $(this).closest('.phone-tag').data('phone');
            phoneNumbers = phoneNumbers.filter(function (x) { return x !== ph; });
            renderPhoneList();
            previewLoaded = false;
        });

        $('#bale-bot-message-form').on('submit', function (e) {
            $('#recipient-ids-input').val(recipientIds.join(','));
            $('#exclude-recipient-ids-input').val(excludeRecipientIds.join(','));
            $('#phone-numbers-input').val(phoneNumbers.join(','));

            var targetType = $('#target_type').val();
            var mode = getDeliveryMode();

            if (targetType === 'phone') {
                var hasExcel = $('#phone-excel-file').length && $('#phone-excel-file')[0].files.length > 0;
                if (phoneNumbers.length === 0 && !hasExcel) {
                    e.preventDefault();
                    alert('لطفاً حداقل یک شماره موبایل وارد کنید یا فایل اکسل انتخاب کنید.');
                    return false;
                }
                if (mode === 'bot_only') {
                    e.preventDefault();
                    alert('ارسال به شماره مشخص فقط از طریق سفیر (هزینه‌دار) امکان‌پذیر است.');
                    return false;
                }
            }

            var sendableCount = 0;
            var mode = getDeliveryMode();
            $('.sc-bale-preview-member-check:checked').each(function () {
                if (rowSendableInMode($(this).closest('tr'), mode)) {
                    sendableCount++;
                }
            });
            if (!previewLoaded) {
                e.preventDefault();
                if (typeof scConfirm === 'function') {
                    scConfirm({ type: 'warning', message: 'پیش‌نمایش مخاطبین اجرا نشده است. ادامه می‌دهید؟' }).then(function (ok) {
                        if (ok) {
                            $('#bale-bot-message-form').off('submit').trigger('submit');
                        }
                    });
                } else if (window.confirm('پیش‌نمایش مخاطبین اجرا نشده است. ادامه می‌دهید؟')) {
                    $('#bale-bot-message-form').off('submit').trigger('submit');
                }
                return false;
            }
            if (previewLoaded && sendableCount === 0) {
                e.preventDefault();
                alert('با حالت ارسال فعلی، هیچ مخاطب انتخاب‌شده‌ای قابل ارسال نیست. حالت ارسال را تغییر دهید یا مخاطبین دیگری انتخاب کنید.');
                return false;
            }
        });
    });
})(jQuery);
