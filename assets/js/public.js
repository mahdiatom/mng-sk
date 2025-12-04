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












