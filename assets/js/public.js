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
                } else {
                    var previewHtml = '<div class="sc-image-preview" style="margin-top: 10px;">' +
                        '<img src="' + e.target.result + '" alt="پیش‌نمایش" style="max-width: 200px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9;">' +
                        '<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">پیش‌نمایش</p>' +
                        '</div>';
                    $(input).after(previewHtml);
                }
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
    $('.sc-course-accordion-header').on('click', function(e) {
        var $radio = $(this).prev('input');
        if ($radio.is(':disabled')) {
            return;
        }
        
        var $item = $(this).closest('.sc-course-accordion-item');
        var $content = $item.find('.sc-course-accordion-content');
        
        // انتخاب radio button
        $radio.prop('checked', true);
        
        // بستن سایر آکاردئون‌ها
        $('.sc-course-accordion-item').not($item).find('.sc-course-accordion-content').slideUp();
        $('.sc-course-accordion-item').not($item).find('input[type="radio"]').prop('checked', false);
        
        // باز/بسته کردن آکاردئون فعلی
        if ($content.is(':visible')) {
            $content.slideUp();
        } else {
            $content.slideDown();
        }
    });
    
    // تغییر آیکون هنگام باز/بسته شدن
    $('.sc-course-accordion-item input[type="radio"]').on('change', function() {
        var $item = $(this).closest('.sc-course-accordion-item');
        var $icon = $item.find('.sc-accordion-icon');
        var $content = $item.find('.sc-course-accordion-content');
        
        if ($(this).is(':checked')) {
            $icon.css('transform', 'rotate(180deg)');
            $content.slideDown();
        } else {
            $icon.css('transform', 'rotate(0deg)');
            $content.slideUp();
        }
    });
});



//مدال 

jQuery(document).ready(function($) {
    
    $(document).on('click', '.details_info_user_pannel', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
       
        
        var $modal = $('#scRegistrationModal');
       
        
        $modal.css({
            'display': 'block',
            'visibility': 'visible',
            'position': 'fixed',
            'top': '8%',
            'width': '95%',
            'z-index':' 1000'
        }).addClass('show-modal');
        
       
        
      
    });
    
    // بستن modal
    $(document).on('click', '.sc-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $modal = $('#scRegistrationModal');
        $modal.removeClass('show-modal');
        $modal.css({
            'display': 'none',
            'visibility': 'hidden'
        });
    });
    
    $(document).on('click', '#scRegistrationModal', function(e) {
        if ($(e.target).is('#scRegistrationModal')) {
            var $modal = $(this);
            $modal.removeClass('show-modal');
            $modal.css({
                'display': 'none',
                'visibility': 'hidden'
            });
        }
    });
    
    $(document).on('click', '.sc-modal-content', function(e) {
        e.stopPropagation();
    });
    
});

















