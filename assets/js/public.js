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








