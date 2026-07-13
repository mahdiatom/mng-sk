/**
 * فرم اطلاعات بازیکن: آپلود فوری عکس‌ها + ارسال ایجکسی
 */
(function($) {
    'use strict';

    var $form = $('#sc-documents-form');
    if (!$form.length || typeof scDocuments === 'undefined') {
        return;
    }

    var ajaxurl = scDocuments.ajaxurl;
    var isLocked = !!scDocuments.isLocked;
    var lockedMessage = scDocuments.lockedMessage || 'امکان ویرایش اطلاعات وجود ندارد.';
    var birthDateInvalidMessage = scDocuments.birthDateInvalidMessage || 'لطفاً تاریخ تولد خود را وارد کنید.';
    var todayShamsi = scDocuments.todayShamsi || '';

    function scShowPluginAlert(message, options) {
        options = options || {};
        if (typeof window.scConfirm === 'function') {
            return window.scConfirm({
                type: options.type || 'warning',
                title: options.title || 'توجه',
                message: message,
                confirmText: options.confirmText || 'باشه',
                hideCancel: true
            });
        }
        if (typeof wc_add_notice === 'function') {
            wc_add_notice(message, 'error');
            if (typeof wc_refresh_notices === 'function') {
                wc_refresh_notices();
            }
            return Promise.resolve(true);
        }
        window.alert(message);
        return Promise.resolve(true);
    }

    function scCalculateAgeYears(birthDateShamsi) {
        if (!birthDateShamsi || !todayShamsi) {
            return null;
        }
        var birthParts = String(birthDateShamsi).split('/');
        var todayParts = String(todayShamsi).split('/');
        if (birthParts.length !== 3 || todayParts.length !== 3) {
            return null;
        }
        var birthYear = parseInt(birthParts[0], 10);
        var birthMonth = parseInt(birthParts[1], 10);
        var birthDay = parseInt(birthParts[2], 10);
        var todayYear = parseInt(todayParts[0], 10);
        var todayMonth = parseInt(todayParts[1], 10);
        var todayDay = parseInt(todayParts[2], 10);
        if (!birthYear || !birthMonth || !birthDay || !todayYear || !todayMonth || !todayDay) {
            return null;
        }
        var age = todayYear - birthYear;
        if (todayMonth < birthMonth || (todayMonth === birthMonth && todayDay < birthDay)) {
            age--;
        }
        return age;
    }

    function scIsBirthDateAgeValid() {
        var birthDate = ($form.find('#birth_date_shamsi').val() || '').trim();
        if (!birthDate) {
            return false;
        }
        var age = scCalculateAgeYears(birthDate);
        return age !== null && age >= 1;
    }

    if (isLocked) {
        $form.find('input, select, textarea, button').not('[type="hidden"]').prop('disabled', true);
        $form.find('.sc-btn-remove-image').hide();
        $form.on('submit', function(e) {
            e.preventDefault();
            scShowPluginAlert(lockedMessage, { type: 'warning', title: 'توجه' });
            return false;
        });
        return;
    }

    // --- آپلود فوری عکس بلافاصله بعد از انتخاب فایل ---
    $form.find('input[type="file"][name="personal_photo"], input[type="file"][name="id_card_photo"], input[type="file"][name="sport_insurance_photo"]').on('change', function() {
        var input = this;
        var $input = $(input);
        var fieldName = input.name;
        var $wrap = $input.closest('.sc-upload-field');
        var $progress = $wrap.find('.sc-upload-progress');
        var $preview = $wrap.find('.sc-image-preview');
        var $hidden = $form.find('input[name="' + fieldName + '_url"]');
        var label = $wrap.find('label').text().trim();

        if (!input.files || !input.files[0]) {
            return;
        }

        var file = input.files[0];
        if (file.size > 1 * 1024 * 1024) {
            alert('حجم فایل بیش از ۱ مگابایت است.');
            input.value = '';
            return;
        }

        var formData = new FormData();
        formData.append('action', 'sc_upload_player_photo');
        formData.append('sc_documents_nonce', $form.find('input[name="sc_documents_nonce"]').val());
        formData.append('field_name', fieldName);
        formData.append(fieldName, file);

        $progress.removeClass('sc-upload-done sc-upload-error').addClass('sc-uploading').find('.sc-upload-text').text('در حال آپلود...');
        $input.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        $wrap.find('.sc-upload-bar').css('width', pct + '%');
                    }
                }, false);
                return xhr;
            }
        })
        .done(function(res) {
            if (res.success && res.data && res.data.url) {
                $hidden.val(res.data.url);
                if ($preview.length) {
                    $preview.find('img').attr('src', res.data.url);
                    $preview.show();
                } else {
                    $wrap.append('<div class="sc-image-preview"><img src="' + res.data.url + '" alt="" style="max-width:200px;border-radius:8px;margin-top:8px;"></div>');
                }
                $progress.removeClass('sc-uploading').addClass('sc-upload-done').find('.sc-upload-text').text('آپلود شد');
            } else {
                $progress.removeClass('sc-uploading').addClass('sc-upload-error').find('.sc-upload-text').text(res.data && res.data.message ? res.data.message : 'خطا در آپلود');
                $hidden.val('');
            }
        })
        .fail(function(xhr, status, err) {
            $progress.removeClass('sc-uploading').addClass('sc-upload-error').find('.sc-upload-text').text('خطا در ارتباط با سرور');
            $hidden.val('');
        })
        .always(function() {
            $input.prop('disabled', false).val('');
        });
    });

    // --- ارسال فرم به صورت ایجکسی ---
    $form.on('submit', function(e) {
        e.preventDefault();

        if (!scIsBirthDateAgeValid()) {
            scShowPluginAlert(birthDateInvalidMessage, {
                type: 'warning',
                title: 'تاریخ تولد نامعتبر'
            });
            return false;
        }

        var $btn = $form.find('button[type="submit"]');
        var btnText = $btn.text();
        $btn.prop('disabled', true).text('در حال ذخیره...');

        var formData = new FormData($form[0]);
        formData.set('action', 'sc_submit_documents_ajax');
        formData.delete('sc_submit_documents');
        formData.delete('personal_photo');
        formData.delete('id_card_photo');
        formData.delete('sport_insurance_photo');

        formData.append('personal_photo_url', $form.find('input[name="personal_photo_url"]').val() || '');
        formData.append('id_card_photo_url', $form.find('input[name="id_card_photo_url"]').val() || '');
        formData.append('sport_insurance_photo_url', $form.find('input[name="sport_insurance_photo_url"]').val() || '');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        })
        .done(function(res) {
            if (res.success) {
                scShowPluginAlert(
                    res.data && res.data.message ? res.data.message : 'اطلاعات ذخیره شد.',
                    { type: 'success', title: 'موفق', confirmText: 'باشه' }
                );
                $form.find('.sc-upload-progress').removeClass('sc-uploading sc-upload-error').addClass('sc-upload-done').find('.sc-upload-text').text('آپلود شد');
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'خطا در ذخیره اطلاعات.';
                scShowPluginAlert(msg, { type: 'warning', title: 'خطا' });
            }
        })
        .fail(function() {
            scShowPluginAlert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.', {
                type: 'warning',
                title: 'خطا'
            });
        })
        .always(function() {
            $btn.prop('disabled', false).text(btnText);
        });

        return false;
    });

})(jQuery);
