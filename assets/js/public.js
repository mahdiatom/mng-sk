
jQuery(document).ready(function($) {
    // پیش‌نمایش تصاویر قبل از آپلود
    $('input[type="file"]').on('change', function(e) {
        var input = this;
        var fieldName = $(this).attr('name');
        var previewContainer = $(this).siblings('.sc-image-preview');
        
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            // بررسی اندازه فایل (5MB)
            if (input.files[0].size > 5 * 1024 * 1024) {
                alert('حجم فایل بیش از 5 مگابایت است.');
                $(this).val('');
                return;
            }
            
            reader.onload = function(e) {
                if (previewContainer.length) {
                    previewContainer.find('img').attr('src', e.target.result);
                } 
                // else {
                //     var previewHtml = '';
                //     $(input).after(previewHtml);
                // }
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    });
    
    // اعتبارسنجی فرم
    $('form.woocommerce-form').on('submit', function(e) {
        var isValid = true;
        var errorMessages = [];
        
        // بررسی فیلدهای اجباری
        if (!$('#first_name').val().trim()) {
            isValid = false;
            errorMessages.push('نام الزامی است.');
        }
        
        if (!$('#last_name').val().trim()) {
            isValid = false;
            errorMessages.push('نام خانوادگی الزامی است.');
        }
        
        if (!$('#national_id').val().trim()) {
            isValid = false;
            errorMessages.push('کد ملی الزامی است.');
        } else if ($('#national_id').val().length !== 10) {
            isValid = false;
            errorMessages.push('کد ملی باید 10 رقم باشد.');
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('لطفاً خطاهای زیر را برطرف کنید:\n' + errorMessages.join('\n'));
            return false;
        }
    });
});

jQuery(document).ready(function($) {
    // تبدیل تاریخ انقضا بیمه شمسی به میلادی
    $('#insurance_expiry_date_shamsi').on('change', function() {
        var shamsiDate = $(this).val();
        if (shamsiDate && shamsiDate.includes('/')) {
            var parts = shamsiDate.split('/');
            if (parts.length === 3) {
                var jy = parseInt(parts[0]);
                var jm = parseInt(parts[1]);
                var jd = parseInt(parts[2]);
                
                // تبدیل به میلادی (تابع JavaScript)
                var gregorian = jalaliToGregorian(jy, jm, jd);
                if (gregorian && gregorian.length === 3) {
                    var gregorianDate = gregorian[0] + '-' + 
                                       (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '-' + 
                                       (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
                    $('#insurance_expiry_date_gregorian').val(gregorianDate);
                }
            }
        }
    });
    
    // تبدیل تاریخ تولد شمسی به میلادی (یک طرفه - فقط شمسی → میلادی)
    $('#birth_date_shamsi').on('change', function() {
        var shamsiDate = $(this).val();
        if (shamsiDate && shamsiDate.includes('/')) {
            var parts = shamsiDate.split('/');
            if (parts.length === 3) {
                var jy = parseInt(parts[0]);
                var jm = parseInt(parts[1]);
                var jd = parseInt(parts[2]);
                
                // تبدیل به میلادی (تابع JavaScript)
                var gregorian = jalaliToGregorian(jy, jm, jd);
                if (gregorian && gregorian.length === 3) {
                    var gregorianDate = gregorian[0] + '-' + 
                                       (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '-' + 
                                       (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
                    
                    // نمایش تاریخ میلادی به فرمت YYYY/MM/DD
                    var gregorianDisplay = gregorian[0] + '/' + 
                                         (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '/' + 
                                         (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
                    $('#birth_date_gregorian').val(gregorianDisplay);
                    $('#birth_date_gregorian_hidden').val(gregorianDate);
                }
            }
        }
    });
    
    // تبدیل قبل از ارسال فرم
    $('form').on('submit', function() {
        $('#insurance_expiry_date_shamsi').trigger('change');
        $('#birth_date_shamsi').trigger('change');
    });
    
    // اعتبارسنجی فرم ثبت‌نام رویداد
    $('form.sc-enroll-event-form').on('submit', function(e) {
        var isValid = true;
        var firstErrorField = null;
        
        // پاک کردن پیام‌های خطای قبلی
        $('.sc-field-error').hide().text('');
        $('.sc-event-field-input').removeClass('sc-field-error-border');
        
        // بررسی فیلدهای اجباری
        $('.sc-event-field-input').each(function() {
            var $field = $(this);
            var isRequired = $field.data('is-required') == '1' || $field.prop('required');
            var fieldName = $field.data('field-name') || $field.attr('name');
            var fieldType = $field.attr('type') || ($field.is('select') ? 'select' : 'text');
            var fieldValue = '';
            var $errorDiv = $field.siblings('.sc-field-error');
            
            if (!$errorDiv.length) {
                $errorDiv = $field.closest('.sc-event-field-row').find('.sc-field-error');
            }
            
            // بررسی نوع فیلد
            if (fieldType === 'file') {
                // برای فایل‌ها، بررسی تعداد فایل‌ها
                if (isRequired && this.files.length === 0) {
                    isValid = false;
                    $field.addClass('sc-field-error-border');
                    $errorDiv.text('فیلد "' + fieldName + '" الزامی است.').show();
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                } else if (this.files.length > 10) {
                    isValid = false;
                    $field.addClass('sc-field-error-border');
                    $errorDiv.text('حداکثر 10 فایل مجاز است.').show();
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                } else {
                    // بررسی حجم فایل‌ها
                    for (var i = 0; i < this.files.length; i++) {
                        if (this.files[i].size > 1048576) { // 1MB
                            isValid = false;
                            $field.addClass('sc-field-error-border');
                            $errorDiv.text('فایل "' + this.files[i].name + '" بیش از 1 مگابایت است.').show();
                            if (!firstErrorField) {
                                firstErrorField = $field;
                            }
                            break;
                        }
                    }
                }
            } else if ($field.is('select')) {
                fieldValue = $field.val();
                if (isRequired && (!fieldValue || fieldValue.trim() === '')) {
                    isValid = false;
                    $field.addClass('sc-field-error-border');
                    $errorDiv.text('فیلد "' + fieldName + '" الزامی است.').show();
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                }
            } else {
                fieldValue = $field.val();
                if (isRequired && (!fieldValue || fieldValue.trim() === '')) {
                    isValid = false;
                    $field.addClass('sc-field-error-border');
                    $errorDiv.text('فیلد "' + fieldName + '" الزامی است.').show();
                    if (!firstErrorField) {
                        firstErrorField = $field;
                    }
                }
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            
            // اسکرول به اولین فیلد خطادار
            if (firstErrorField) {
                $('html, body').animate({
                    scrollTop: firstErrorField.offset().top - 100
                }, 500);
                firstErrorField.focus();
            }
            
            return false;
        }
        
        return true;
    });
    
    // تابع تبدیل شمسی به میلادی (JavaScript)
    function jalaliToGregorian(jy, jm, jd) {
        var gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;
        var days = (365 * jy) + ((parseInt(jy / 33)) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 
                   78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        gy += 400 * (parseInt(days / 146097));
        days = days % 146097;
        if (days > 36524) {
            gy += 100 * (parseInt(--days / 36524));
            days = days % 36524;
            if (days >= 365) days++;
        }
        gy += 4 * (parseInt(days / 1461));
        days = days % 1461;
        if (days > 365) {
            gy += parseInt((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var gd = days + 1;
        var sal_a = [0, 31, ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0)) ? 29 : 28,
                     31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > sal_a[gm]) {
            gd -= sal_a[gm];
            gm++;
        }
        return [gy, gm, gd];
    }
});


//start merge 

jQuery(document).ready(function($) {
    // نمایش/مخفی کردن جزئیات دوره با کلیک روی header
    function scPreserveScrollY(fn) {
        var y = window.pageYOffset || document.documentElement.scrollTop || 0;
        fn();
        requestAnimationFrame(function () {
            window.scrollTo(0, y);
        });
        setTimeout(function () {
            window.scrollTo(0, y);
        }, 0);
        setTimeout(function () {
            window.scrollTo(0, y);
        }, 220);
    }

    function scFocusCourseRadioNoScroll(radioEl) {
        if (!radioEl || typeof radioEl.focus !== 'function') {
            return;
        }
        try {
            radioEl.focus({ preventScroll: true });
        } catch (err) {
            radioEl.focus();
        }
    }

    $('.sc-course-accordion-header').on('click', function(e) {
        var $radio = $(this).prev('input');
        if (!$radio.length || !$radio.is('input[name="course_id"]')) {
            return;
        }
        if ($radio.is(':disabled')) {
            return;
        }

        var $item = $(this).closest('.sc-course-accordion-item');
        var $content = $item.find('.sc-course-accordion-content');

        scPreserveScrollY(function () {
            $('.sc-course-accordion-item').not($item).find('input[name="course_id"]').prop('checked', false);
            $radio.prop('checked', true);

            $('.sc-course-accordion-item').not($item).find('.sc-course-accordion-content').stop(true, true).slideUp(200);
            $content.stop(true, true).slideDown(200);
        });

        scFocusCourseRadioNoScroll($radio[0]);
    });

    $('.sc-course-accordion-item input[name="course_id"]').on('change', function() {
        var $item = $(this).closest('.sc-course-accordion-item');
        var $icon = $item.find('.sc-accordion-icon');
        var $content = $item.find('.sc-course-accordion-content');

        if ($(this).is(':checked')) {
            var radioEl = this;
            scPreserveScrollY(function () {
                $('.sc-course-accordion-item').not($item).find('input[name="course_id"]').prop('checked', false);
                $icon.css('transform', 'rotate(180deg)');
                $('.sc-course-accordion-item').not($item).find('.sc-course-accordion-content').stop(true, true).slideUp(200);
                $content.stop(true, true).slideDown(200);
            });
            scFocusCourseRadioNoScroll(radioEl);
        } else {
            $icon.css('transform', 'rotate(0deg)');
            $content.stop(true, true).slideUp(200);
        }
    });
});



//مدال 

jQuery(document).ready(function($) {
    
    $(document).on('click', '.details_info_user_pannel', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $modal = $('#scRegistrationModal');
        $modal.attr('aria-hidden', 'false').css('display', 'flex');
        requestAnimationFrame(function() {
            $modal.addClass('show-modal');
        });
    });
    
    function closeScRegistrationModal() {
        var $modal = $('#scRegistrationModal');
        $modal.removeClass('show-modal');
        $modal.attr('aria-hidden', 'true');
        $modal.one('transitionend', function(e) {
            if (e.target === $modal[0]) {
                $modal.css('display', '');
            }
        });
        setTimeout(function() {
            if (!$modal.hasClass('show-modal')) {
                $modal.css('display', '');
            }
        }, 350);
    }
    
    $(document).on('click', '.sc-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeScRegistrationModal();
    });
    
    $(document).on('click', '#scRegistrationModal', function(e) {
        if ($(e.target).is('#scRegistrationModal')) {
            closeScRegistrationModal();
        }
    });
    
    $(document).on('click', '#scRegistrationModal .sc-modal-inner', function(e) {
        e.stopPropagation();
    });
    
});








jQuery(document).ready(function($) {
    // ---------- نمایش پاپ آپ اطلاعات منو ----------
    $(document).on('click', '.menu-header_moblie', function(e){
        e.preventDefault();
        e.stopPropagation();
        
        
        
        
        let $modal = $('#myModal');
        
        if (!$modal.length) {
            alert('خطا: المان Modal پیدا نشد');
            return;
        }
        let $loading = $modal.find('.sc-modal-loading');
        let $CourseList = $modal.find('.sc-modal-users-list');
        
        $loading.show();
        $CourseList.hide().empty();
        
        $modal.css({
            'display': 'flex',
            'visibility': 'visible'
        }).addClass('show-modal');
    $(document).on('click', '.close', function(e){
        e.preventDefault();
        $(".sk-modal-content ").addClass('hide_before_data');
        $('#myModal').fadeOut();
        
    });
    
    $(window).on('click', function(e){
        if ($(e.target).is('#myModal')) {
            $(".sk-modal-content").addClass('hide_before_data');
            $('#myModal').fadeOut();
            
        }
    });
});

});

