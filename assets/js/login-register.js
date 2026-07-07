(function ($) {
    'use strict';

    var cfg = window.scLoginRegister || {};
    var ajaxurl = cfg.ajaxurl || '';
    var nonce = cfg.nonce || '';
    var strings = cfg.strings || {};

    function s(key) {
        return strings[key] || key;
    }

    function showGlobalError(msg) {
        var $box = $('#sc-lr-global-error');
        var $text = $('#sc-lr-global-error-text');
        if (msg) {
            $text.text(msg);
            $box.addClass('is-visible').show();
        } else {
            $box.removeClass('is-visible').hide();
            $text.text('');
        }
    }

    function showStep(step, panel) {
        $('#sc-login-register-form .sc-lr-step').hide();
        $('#sc-login-register-form .sc-lr-panel').hide();
        if (step === 'otp') {
            $('#sc-login-register-form .sc-lr-step[data-step="otp"]').show();
            return;
        }
        var $panel = $('#sc-login-register-form .sc-lr-panel[data-panel="' + panel + '"]');
        $panel.show();
        $panel.find('.sc-lr-step[data-step="' + step + '"]').show();
        showGlobalError('');
    }

    function setBtnLoading(btn, loading) {
        var $btn = $(btn);
        if (loading) {
            $btn.prop('disabled', true).data('orig-text', $btn.text()).text(s('loading'));
        } else {
            $btn.prop('disabled', false).text($btn.data('orig-text') || $btn.text());
        }
    }

    var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function normalizeDigits(value) {
        var arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return String(value || '')
            .replace(/[۰-۹]/g, function (d) { return String(persianDigits.indexOf(d)); })
            .replace(/[٠-٩]/g, function (d) { return String(arabicIndic.indexOf(d)); })
            .replace(/\D/g, '');
    }

    function getPhone() {
        var result = validatePhoneInput($('#sc-lr-phone').val(), false);
        return result.valid ? result.phone : '';
    }

    function validatePhoneInput(rawValue, showFeedback) {
        var digits = normalizeDigits(rawValue);
        var message = '';

        if (!digits) {
            message = s('err_phone_empty');
        } else if (/^9\d{9}$/.test(digits)) {
            return { valid: true, phone: '0' + digits };
        } else if (digits.length < 11) {
            message = s('err_phone_short');
        } else if (digits.length > 11) {
            message = s('err_phone_invalid');
        } else if (!/^09/.test(digits)) {
            message = s('err_phone_prefix');
        } else if (!/^09\d{9}$/.test(digits)) {
            message = s('err_phone_invalid');
        } else {
            return { valid: true, phone: digits };
        }

        if (showFeedback) {
            showPhoneFieldError(message);
        }
        return { valid: false, phone: '', message: message };
    }

    function showPhoneFieldError(msg) {
        var $field = $('#sc-lr-phone-field');
        var $input = $('#sc-lr-phone');
        var $alert = $('#sc-lr-phone-alert');
        var $text = $('#sc-lr-phone-alert-text');
        $text.text(msg || s('err_phone'));
        $alert.addClass('is-visible').show();
        $input.addClass('is-invalid').attr('aria-invalid', 'true');
        $field.addClass('is-shake');
        showGlobalError(msg || s('err_phone'));
        setTimeout(function () {
            $field.removeClass('is-shake');
        }, 500);
    }

    function clearPhoneFieldError() {
        $('#sc-lr-phone-alert').removeClass('is-visible').hide();
        $('#sc-lr-phone-alert-text').text('');
        $('#sc-lr-phone').removeClass('is-invalid').removeAttr('aria-invalid');
        $('#sc-lr-phone-field').removeClass('is-shake');
    }

    function getNidPhone() {
        var raw = normalizeDigits($('#sc-lr-nid-phone').val());
        if (/^09\d{9}$/.test(raw)) return raw;
        if (/^9\d{9}$/.test(raw)) return '0' + raw;
        return '';
    }

    function getNationalId() {
        var raw = normalizeDigits($('#sc-lr-national-id').val());
        if (/^\d{10}$/.test(raw)) return raw;
        return '';
    }

    var OTP_SECONDS = 120;
    var otpTimerInterval = null;

    function toPersianNum(n) {
        return String(n).replace(/\d/g, function (d) { return persianDigits[parseInt(d, 10)]; });
    }

    function formatOtpTime(seconds) {
        var m = Math.floor(seconds / 60);
        var sec = seconds % 60;
        var ss = sec < 10 ? '0' + sec : '' + sec;
        return toPersianNum(m) + ':' + toPersianNum(ss);
    }

    function startOtpCountdown() {
        stopOtpCountdown();
        $('#sc-lr-resend-wrap').hide();
        var left = OTP_SECONDS;
        var $timer = $('#sc-lr-otp-timer');
        $timer.removeClass('sc-lr-otp-timer-expired').text(formatOtpTime(left));
        otpTimerInterval = setInterval(function () {
            left--;
            if (left <= 0) {
                stopOtpCountdown();
                $timer.addClass('sc-lr-otp-timer-expired').text('۰:۰۰ — زمان اعتبار کد تمام شد');
                $('#sc-lr-resend-wrap').show();
                return;
            }
            $timer.text(formatOtpTime(left));
        }, 1000);
    }

    function stopOtpCountdown() {
        if (otpTimerInterval) {
            clearInterval(otpTimerInterval);
            otpTimerInterval = null;
        }
    }

    $(function () {
        var currentPhone = '';
        var currentNationalId = '';
        var currentOtpPhone = '';
        var currentUserId = 0;
        var currentUserLabel = '';
        var currentTab = 'phone';
        var otpSource = '';
        var hadMultipleUsers = false;

        function switchTab(tab) {
            var $tabs = $('.sc-lr-tab');
            $tabs.removeClass('is-active').attr('aria-selected', 'false');
            $tabs.filter('[data-tab="' + tab + '"]').addClass('is-active').attr('aria-selected', 'true');
            stopOtpCountdown();
            showGlobalError('');
            currentUserId = 0;
            currentUserLabel = '';
            hadMultipleUsers = false;
            if (tab === 'phone') {
                showStep('phone', 'phone');
            } else {
                showStep('nid', 'nid');
            }
        }

        function renderUserList(users) {
            var $list = $('#sc-lr-user-list');
            $list.empty();
            (users || []).forEach(function (user) {
                var name = user.name || user.label || 'کاربر';
                var meta = '';
                if (user.label && user.label !== name) {
                    var dashIdx = user.label.indexOf('—');
                    if (dashIdx > -1) {
                        meta = 'کد ملی: ' + user.label.substring(dashIdx + 1).trim();
                    } else {
                        meta = user.label;
                    }
                }
                var $btn = $('<button type="button" class="sc-lr-user-pick" role="option"></button>');
                $btn.attr('data-user-id', user.user_id);
                $btn.append($('<span class="sc-lr-user-pick-name"></span>').text(name));
                if (meta) {
                    $btn.append($('<span class="sc-lr-user-pick-meta"></span>').text(meta));
                }
                $list.append($btn);
            });
        }

        function goToPhoneExists(userLabel) {
            currentUserLabel = userLabel || '';
            if (currentUserLabel) {
                $('#sc-lr-selected-user-label').text(currentUserLabel);
                $('#sc-lr-selected-user-wrap').show();
            } else {
                $('#sc-lr-selected-user-wrap').hide();
            }
            showStep('exists', 'phone');
            $('#sc-lr-password').val('').focus();
        }

        $('.sc-lr-tab').on('click', function () {
            var tab = $(this).data('tab');
            if (tab === currentTab) return;
            currentTab = tab;
            currentPhone = '';
            currentNationalId = '';
            currentOtpPhone = '';
            currentUserId = 0;
            currentUserLabel = '';
            otpSource = '';
            switchTab(tab);
        });

        $('#sc-lr-national-id').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#sc-lr-submit-nid').trigger('click');
            }
        });

        $(document).on('click', '.sc-lr-user-pick', function () {
            currentUserId = parseInt($(this).attr('data-user-id'), 10) || 0;
            var name = $(this).find('.sc-lr-user-pick-name').text();
            goToPhoneExists(name);
        });

        $('#sc-lr-back-pick-user').on('click', function () {
            currentUserId = 0;
            currentUserLabel = '';
            clearPhoneFieldError();
            showStep('phone', 'phone');
            showGlobalError('');
        });

        // ----- Phone tab -----
        $('#sc-lr-phone').on('input', function () {
            clearPhoneFieldError();
            showGlobalError('');
        });

        $('#sc-lr-phone').on('blur', function () {
            var raw = $(this).val();
            if (!normalizeDigits(raw)) {
                return;
            }
            validatePhoneInput(raw, true);
        });

        $('#sc-lr-phone').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#sc-lr-submit-phone').trigger('click');
            }
        });

        $('#sc-lr-submit-phone').on('click', function () {
            var validation = validatePhoneInput($('#sc-lr-phone').val(), true);
            if (!validation.valid) {
                $('#sc-lr-phone').focus();
                return;
            }
            var phone = validation.phone;
            clearPhoneFieldError();
            showGlobalError('');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_check_phone',
                nonce: nonce,
                phone: phone
            })
                .done(function (res) {
                    currentPhone = phone;
                    currentTab = 'phone';
                    currentUserId = 0;
                    currentUserLabel = '';
                    if (res.data && res.data.exists) {
                        if (res.data.multiple && res.data.users && res.data.users.length > 1) {
                            hadMultipleUsers = true;
                            renderUserList(res.data.users);
                            showStep('pick-user', 'phone');
                        } else {
                            hadMultipleUsers = false;
                            var single = (res.data.users && res.data.users[0]) ? res.data.users[0] : null;
                            currentUserId = res.data.user_id || (single ? single.user_id : 0);
                            goToPhoneExists(single ? (single.name || single.label) : '');
                        }
                    } else {
                        hadMultipleUsers = false;
                        showStep('register', 'phone');
                        $('#sc-lr-first-name, #sc-lr-last-name, #sc-lr-reg-password').val('');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-submit-phone', false);
                });
        });

        $('#sc-lr-back-phone, #sc-lr-back-phone-reg').on('click', function () {
            if ($(this).attr('id') === 'sc-lr-back-phone' && hadMultipleUsers) {
                currentUserId = 0;
                currentUserLabel = '';
                showStep('pick-user', 'phone');
                showGlobalError('');
                return;
            }
            currentPhone = '';
            currentUserId = 0;
            currentUserLabel = '';
            hadMultipleUsers = false;
            clearPhoneFieldError();
            showStep('phone', 'phone');
            showGlobalError('');
        });

        $('#sc-lr-login-password').on('click', function () {
            var pass = $('#sc-lr-password').val();
            if (!pass) {
                showGlobalError('رمز عبور را وارد کنید.');
                return;
            }
            if (!currentUserId) {
                showGlobalError('لطفاً حساب کاربری را انتخاب کنید.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_login_password',
                nonce: nonce,
                phone: currentPhone,
                user_id: currentUserId,
                password: pass
            })
                .done(function (res) {
                    if (res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                        return;
                    }
                    showGlobalError(res.data && res.data.message ? res.data.message : 'خطایی رخ داد.');
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-login-password', false);
                });
        });

        $('#sc-lr-send-otp').on('click', function () {
            if (!currentUserId) {
                showGlobalError('لطفاً حساب کاربری را انتخاب کنید.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            otpSource = 'phone-exists';
            currentOtpPhone = currentPhone;
            $.post(ajaxurl, {
                action: 'sc_login_register_send_otp',
                nonce: nonce,
                phone: currentPhone,
                user_id: currentUserId
            })
                .done(function (res) {
                    if (res.success) {
                        showStep('otp', 'phone');
                        $('#sc-lr-otp').val('').focus();
                        startOtpCountdown();
                    } else {
                        showGlobalError((res.data && res.data.message) ? res.data.message : 'خطا در ارسال کد.');
                    }
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ارسال کد. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-send-otp', false);
                });
        });

        $('#sc-lr-register-submit').on('click', function () {
            var first = ($('#sc-lr-first-name').val() || '').trim();
            var last = ($('#sc-lr-last-name').val() || '').trim();
            var pass = $('#sc-lr-reg-password').val();
            if (first.length < 2 || last.length < 2) {
                showGlobalError('نام و نام خانوادگی را صحیح وارد کنید.');
                return;
            }
            if (!pass || pass.length < 6) {
                showGlobalError('رمز عبور حداقل ۶ کاراکتر باشد.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            otpSource = 'phone-register';
            currentOtpPhone = currentPhone;
            $.post(ajaxurl, {
                action: 'sc_login_register_register',
                nonce: nonce,
                phone: currentPhone,
                first_name: first,
                last_name: last,
                password: pass
            })
                .done(function (res) {
                    if (res.success && res.data && res.data.step === 'verify_otp') {
                        showStep('otp', 'phone');
                        $('#sc-lr-otp').val('').focus();
                        startOtpCountdown();
                    } else {
                        showGlobalError((res.data && res.data.message) ? res.data.message : 'خطا در ثبت‌نام.');
                    }
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-register-submit', false);
                });
        });

        // ----- National ID tab -----
        $('#sc-lr-submit-nid').on('click', function () {
            var nid = getNationalId();
            if (!nid) {
                showGlobalError(s('err_nid'));
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_check_national_id',
                nonce: nonce,
                national_id: nid
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        showGlobalError((res && res.data && res.data.message) ? res.data.message : 'خطا در بررسی کد ملی.');
                        return;
                    }
                    currentNationalId = nid;
                    currentTab = 'nid';
                    if (res.data && res.data.exists) {
                        $('#sc-lr-nid-display-exists').text(nid);
                        showStep('nid-exists', 'nid');
                        $('#sc-lr-nid-password').val('');
                    } else {
                        $('#sc-lr-nid-display-register').text(nid);
                        showStep('nid-register', 'nid');
                        $('#sc-lr-nid-phone, #sc-lr-nid-first-name, #sc-lr-nid-last-name, #sc-lr-nid-reg-password').val('');
                    }
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-submit-nid', false);
                });
        });

        $('#sc-lr-back-nid, #sc-lr-back-nid-reg').on('click', function () {
            currentNationalId = '';
            showStep('nid', 'nid');
            showGlobalError('');
        });

        $('#sc-lr-nid-login-password').on('click', function () {
            var pass = $('#sc-lr-nid-password').val();
            if (!pass) {
                showGlobalError('رمز عبور را وارد کنید.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_login_password_nid',
                nonce: nonce,
                national_id: currentNationalId,
                password: pass
            })
                .done(function (res) {
                    if (res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                        return;
                    }
                    showGlobalError(res.data && res.data.message ? res.data.message : 'خطایی رخ داد.');
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-nid-login-password', false);
                });
        });

        $('#sc-lr-nid-send-otp').on('click', function () {
            showGlobalError('');
            setBtnLoading(this, true);
            otpSource = 'nid-exists';
            $.post(ajaxurl, {
                action: 'sc_login_register_send_otp_nid',
                nonce: nonce,
                national_id: currentNationalId
            })
                .done(function (res) {
                    if (res.success) {
                        currentOtpPhone = (res.data && res.data.phone) ? res.data.phone : '';
                        showStep('otp', 'nid');
                        $('#sc-lr-otp').val('').focus();
                        startOtpCountdown();
                    } else {
                        showGlobalError((res.data && res.data.message) ? res.data.message : 'خطا در ارسال کد.');
                    }
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ارسال کد. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-nid-send-otp', false);
                });
        });

        $('#sc-lr-nid-register-submit').on('click', function () {
            var phone = getNidPhone();
            var first = ($('#sc-lr-nid-first-name').val() || '').trim();
            var last = ($('#sc-lr-nid-last-name').val() || '').trim();
            var pass = $('#sc-lr-nid-reg-password').val();
            if (!phone) {
                showGlobalError(s('err_phone'));
                return;
            }
            if (first.length < 2 || last.length < 2) {
                showGlobalError('نام و نام خانوادگی را صحیح وارد کنید.');
                return;
            }
            if (!pass || pass.length < 6) {
                showGlobalError('رمز عبور حداقل ۶ کاراکتر باشد.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            otpSource = 'nid-register';
            $.post(ajaxurl, {
                action: 'sc_login_register_register_nid',
                nonce: nonce,
                national_id: currentNationalId,
                phone: phone,
                first_name: first,
                last_name: last,
                password: pass
            })
                .done(function (res) {
                    if (res.success && res.data && res.data.step === 'verify_otp') {
                        currentOtpPhone = (res.data && res.data.phone) ? res.data.phone : phone;
                        showStep('otp', 'nid');
                        $('#sc-lr-otp').val('').focus();
                        startOtpCountdown();
                    } else {
                        showGlobalError((res.data && res.data.message) ? res.data.message : 'خطا در ثبت‌نام.');
                    }
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-nid-register-submit', false);
                });
        });

        // ----- OTP (shared) -----
        $('#sc-lr-otp-back').on('click', function () {
            stopOtpCountdown();
            showGlobalError('');
            if (otpSource === 'phone-exists') {
                showStep('exists', 'phone');
            } else if (otpSource === 'phone-pick') {
                showStep('pick-user', 'phone');
            } else if (otpSource === 'phone-register') {
                showStep('register', 'phone');
            } else if (otpSource === 'nid-exists') {
                showStep('nid-exists', 'nid');
            } else if (otpSource === 'nid-register') {
                showStep('nid-register', 'nid');
            } else if (currentTab === 'nid') {
                showStep('nid-exists', 'nid');
            } else {
                showStep('exists', 'phone');
            }
        });

        $('#sc-lr-verify-otp').on('click', function () {
            var code = ($('#sc-lr-otp').val() || '').replace(/\D/g, '');
            if (code.length < 5) {
                showGlobalError('کد تأیید ۵ رقمی را وارد کنید.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);

            var isNidFlow = otpSource === 'nid-exists' || otpSource === 'nid-register';
            var postData = {
                nonce: nonce,
                code: code,
                phone: currentOtpPhone
            };

            if (isNidFlow) {
                postData.action = 'sc_login_register_verify_otp_nid';
                postData.national_id = currentNationalId;
            } else {
                postData.action = 'sc_login_register_verify_otp';
                if (currentUserId) {
                    postData.user_id = currentUserId;
                }
            }

            $.post(ajaxurl, postData)
                .done(function (res) {
                    if (res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                        return;
                    }
                    showGlobalError((res.data && res.data.message) ? res.data.message : 'کد نادرست یا منقضی است.');
                })
                .fail(function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در تأیید. لطفاً دوباره تلاش کنید.';
                    showGlobalError(msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-verify-otp', false);
                });
        });

        $('#sc-lr-resend-otp').on('click', function () {
            showGlobalError('');
            setBtnLoading(this, true);

            var postData = { nonce: nonce };

            if (otpSource === 'nid-exists') {
                postData.action = 'sc_login_register_send_otp_nid';
                postData.national_id = currentNationalId;
            } else if (otpSource === 'nid-register') {
                postData.action = 'sc_login_register_register_nid';
                postData.national_id = currentNationalId;
                postData.phone = currentOtpPhone;
                postData.first_name = ($('#sc-lr-nid-first-name').val() || '').trim();
                postData.last_name = ($('#sc-lr-nid-last-name').val() || '').trim();
                postData.password = $('#sc-lr-nid-reg-password').val();
            } else if (otpSource === 'phone-register') {
                postData.action = 'sc_login_register_register';
                postData.phone = currentPhone;
                postData.first_name = ($('#sc-lr-first-name').val() || '').trim();
                postData.last_name = ($('#sc-lr-last-name').val() || '').trim();
                postData.password = $('#sc-lr-reg-password').val();
            } else {
                postData.action = 'sc_login_register_send_otp';
                postData.phone = currentOtpPhone || currentPhone;
                if (currentUserId) {
                    postData.user_id = currentUserId;
                }
            }

            $.post(ajaxurl, postData)
                .done(function (res) {
                    if (res.success) {
                        showGlobalError('');
                        if (res.data && res.data.phone) {
                            currentOtpPhone = res.data.phone;
                        }
                        $('#sc-lr-otp').val('').focus();
                        $('#sc-lr-resend-wrap').hide();
                        startOtpCountdown();
                    } else {
                        showGlobalError((res.data && res.data.message) ? res.data.message : 'خطا در ارسال مجدد کد.');
                    }
                })
                .fail(function (xhr) {
                    showGlobalError(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : 'خطا در ارسال مجدد کد.');
                })
                .always(function () {
                    setBtnLoading('#sc-lr-resend-otp', false);
                });
        });

        switchTab('phone');
    });
})(jQuery);
