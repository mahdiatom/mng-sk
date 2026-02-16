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

    function showStep(step) {
        $('#sc-login-register-form .sc-lr-step').hide();
        $('#sc-login-register-form .sc-lr-step[data-step="' + step + '"]').show();
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

    function getPhone() {
        var raw = ($('#sc-lr-phone').val() || '').replace(/\D/g, '');
        if (/^09\d{9}$/.test(raw)) return raw;
        if (/^9\d{9}$/.test(raw)) return '0' + raw;
        return '';
    }

    var OTP_SECONDS = 120; // 2 دقیقه
    var otpTimerInterval = null;
    var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toPersianNum(n) {
        return String(n).replace(/\d/g, function (d) { return persianDigits[parseInt(d, 10)]; });
    }

    function formatOtpTime(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        var ss = s < 10 ? '0' + s : '' + s;
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
        var otpSource = ''; // 'exists' | 'register'

        $('#sc-lr-submit-phone').on('click', function () {
            var phone = getPhone();
            if (!phone) {
                showGlobalError(s('err_phone'));
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_check_phone',
                nonce: nonce,
                phone: phone
            })
                .done(function (res) {
                    currentPhone = phone;
                    if (res.data && res.data.exists) {
                        showStep('exists');
                        $('#sc-lr-password').val('');
                    } else {
                        showStep('register');
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
            currentPhone = '';
            showStep('phone');
            showGlobalError('');
        });

        $('#sc-lr-login-password').on('click', function () {
            var pass = $('#sc-lr-password').val();
            if (!pass) {
                showGlobalError('رمز عبور را وارد کنید.');
                return;
            }
            showGlobalError('');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_login_password',
                nonce: nonce,
                phone: currentPhone,
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
            showGlobalError('');
            setBtnLoading(this, true);
            otpSource = 'exists';
            $.post(ajaxurl, {
                action: 'sc_login_register_send_otp',
                nonce: nonce,
                phone: currentPhone
            })
                .done(function (res) {
                    if (res.success) {
                        showStep('otp');
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
            otpSource = 'register';
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
                        showStep('otp');
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

        $('#sc-lr-otp-back').on('click', function () {
            stopOtpCountdown();
            showGlobalError('');
            if (otpSource === 'exists') {
                showStep('exists');
            } else {
                showStep('register');
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
            $.post(ajaxurl, {
                action: 'sc_login_register_verify_otp',
                nonce: nonce,
                phone: currentPhone,
                code: code
            })
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
            $.post(ajaxurl, {
                action: 'sc_login_register_send_otp',
                nonce: nonce,
                phone: currentPhone
            })
                .done(function (res) {
                    if (res.success) {
                        showGlobalError('');
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
    });
})(jQuery);
