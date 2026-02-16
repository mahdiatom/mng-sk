(function ($) {
    'use strict';

    var cfg = window.scLoginRegister || {};
    var ajaxurl = cfg.ajaxurl || '';
    var nonce = cfg.nonce || '';
    var strings = cfg.strings || {};

    function s(key) {
        return strings[key] || key;
    }

    function showStep(step) {
        $('#sc-login-register-form .sc-lr-step').hide();
        $('#sc-login-register-form .sc-lr-step[data-step="' + step + '"]').show();
        $('.sc-lr-error').text('');
    }

    function setBtnLoading(btn, loading) {
        var $btn = $(btn);
        if (loading) {
            $btn.prop('disabled', true).data('orig-text', $btn.text()).text(s('loading'));
        } else {
            $btn.prop('disabled', false).text($btn.data('orig-text') || $btn.text());
        }
    }

    function showError(selector, msg) {
        $(selector).text(msg || '');
    }

    function getPhone() {
        var raw = ($('#sc-lr-phone').val() || '').replace(/\D/g, '');
        if (/^09\d{9}$/.test(raw)) return raw;
        if (/^9\d{9}$/.test(raw)) return '0' + raw;
        return '';
    }

    $(function () {
        var currentPhone = '';

        $('#sc-lr-submit-phone').on('click', function () {
            var phone = getPhone();
            if (!phone) {
                showError('#sc-lr-phone-error', s('err_phone'));
                return;
            }
            showError('#sc-lr-phone-error', '');
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
                .fail(function () {
                    showError('#sc-lr-phone-error', 'خطا در ارتباط با سرور.');
                })
                .always(function () {
                    setBtnLoading('#sc-lr-submit-phone', false);
                });
        });

        $('#sc-lr-back-phone, #sc-lr-back-phone-reg').on('click', function () {
            currentPhone = '';
            showStep('phone');
            showError('#sc-lr-phone-error', '');
        });

        $('#sc-lr-login-password').on('click', function () {
            var pass = $('#sc-lr-password').val();
            if (!pass) {
                showError('#sc-lr-password-error', 'رمز عبور را وارد کنید.');
                return;
            }
            showError('#sc-lr-password-error', '');
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
                    showError('#sc-lr-password-error', res.data && res.data.message ? res.data.message : 'خطا');
                })
                .fail(function (xhr) {
                    var msg = 'خطا در ارتباط با سرور.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    showError('#sc-lr-password-error', msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-login-password', false);
                });
        });

        $('#sc-lr-send-otp').on('click', function () {
            showError('#sc-lr-password-error', '');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_send_otp',
                nonce: nonce,
                phone: currentPhone
            })
                .done(function (res) {
                    if (res.success) {
                        showStep('otp');
                        $('#sc-lr-otp').val('').focus();
                        showError('#sc-lr-otp-error', '');
                    } else {
                        showError('#sc-lr-password-error', (res.data && res.data.message) ? res.data.message : 'خطا در ارسال کد');
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'خطا در ارسال کد';
                    showError('#sc-lr-password-error', msg);
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
                showError('#sc-lr-reg-error', 'نام و نام خانوادگی را صحیح وارد کنید.');
                return;
            }
            if (!pass || pass.length < 6) {
                showError('#sc-lr-reg-error', 'رمز عبور حداقل ۶ کاراکتر باشد.');
                return;
            }
            showError('#sc-lr-reg-error', '');
            setBtnLoading(this, true);
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
                        showError('#sc-lr-otp-error', '');
                    } else {
                        showError('#sc-lr-reg-error', (res.data && res.data.message) ? res.data.message : 'خطا در ثبت‌نام');
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'خطا در ثبت‌نام';
                    showError('#sc-lr-reg-error', msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-register-submit', false);
                });
        });

        $('#sc-lr-verify-otp').on('click', function () {
            var code = ($('#sc-lr-otp').val() || '').replace(/\D/g, '');
            if (code.length < 5) {
                showError('#sc-lr-otp-error', 'کد تأیید را وارد کنید.');
                return;
            }
            showError('#sc-lr-otp-error', '');
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
                    showError('#sc-lr-otp-error', (res.data && res.data.message) ? res.data.message : 'کد نادرست است.');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'خطا در تأیید کد';
                    showError('#sc-lr-otp-error', msg);
                })
                .always(function () {
                    setBtnLoading('#sc-lr-verify-otp', false);
                });
        });

        $('#sc-lr-resend-otp').on('click', function () {
            showError('#sc-lr-otp-error', '');
            setBtnLoading(this, true);
            $.post(ajaxurl, {
                action: 'sc_login_register_send_otp',
                nonce: nonce,
                phone: currentPhone
            })
                .done(function (res) {
                    if (res.success) {
                        $('#sc-lr-otp-error').text(res.data && res.data.message ? res.data.message : 'کد مجدد ارسال شد.');
                    } else {
                        showError('#sc-lr-otp-error', (res.data && res.data.message) ? res.data.message : 'خطا در ارسال کد');
                    }
                })
                .fail(function (xhr) {
                    showError('#sc-lr-otp-error', (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'خطا در ارسال کد');
                })
                .always(function () {
                    setBtnLoading('#sc-lr-resend-otp', false);
                });
        });
    });
})(jQuery);
