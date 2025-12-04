// ============================================
// توابع مشترک تبدیل تاریخ شمسی به میلادی
// ============================================

// تابع تبدیل تاریخ شمسی به میلادی
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

// تابع تبدیل تاریخ شمسی به میلادی (فرمت رشته)
function convertShamsiToGregorian(shamsiDate) {
    if (!shamsiDate || shamsiDate === '') return '';
    var parts = shamsiDate.split('/');
    if (parts.length !== 3) return '';
    var jy = parseInt(parts[0]);
    var jm = parseInt(parts[1]);
    var jd = parseInt(parts[2]);
    var gregorian = jalaliToGregorian(jy, jm, jd);
    return gregorian[0] + '-' + 
           (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '-' + 
           (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
}

// ============================================
// توابع مشترک انتخاب کاربر (Dropdown)
// ============================================

// تابع انتخاب کاربر در فیلتر (با پشتیبانی از "همه کاربران")
function scSelectMemberFilter(element, memberId, memberText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $hiddenInput = $dropdown.find('input[type="hidden"]');
    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');
    var $menu = $dropdown.find('.sc-dropdown-menu');
    
    // تنظیم مقدار
    $hiddenInput.val(memberId);
    if (memberId == '0') {
        $placeholder.show();
        $selected.hide();
    } else {
        $placeholder.hide();
        $selected.text(memberText).show();
    }
    
    // بستن منو
    $menu.slideUp(200);
    
    // حذف انتخاب قبلی و اضافه کردن انتخاب جدید
    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
    jQuery(element).addClass('sc-selected').css('background', '#f0f6fc');
    
    // اضافه کردن چکمارک
    $dropdown.find('.sc-dropdown-option span').remove();
    jQuery(element).append('<span style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

// تابع انتخاب کاربر (بدون پشتیبانی از "همه کاربران")
function scSelectMember(element, memberId, memberText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $hiddenInput = $dropdown.find('input[type="hidden"]');
    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');
    var $menu = $dropdown.find('.sc-dropdown-menu');
    
    // تنظیم مقدار
    $hiddenInput.val(memberId);
    $placeholder.hide();
    $selected.text(memberText).show();
    
    // بستن منو
    $menu.slideUp(200);
    
    // حذف انتخاب قبلی و اضافه کردن انتخاب جدید
    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected');
    jQuery(element).addClass('sc-selected');
    
    // تغییر background
    $dropdown.find('.sc-dropdown-option').css('background', '');
    jQuery(element).css('background', '#f0f6fc');
    
    // اضافه کردن چکمارک
    jQuery(element).find('span').remove();
    jQuery(element).append('<span style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

// ============================================
// مدیریت مشترک Dropdown
// ============================================

jQuery(document).ready(function($) {
    // مدیریت باز و بسته شدن dropdown
    $('.sc-dropdown-toggle').on('click', function(e) {
        e.stopPropagation();
        var $menu = $(this).siblings('.sc-dropdown-menu');
        var isOpen = $menu.is(':visible');
        
        // بستن همه dropdown‌ها
        $('.sc-dropdown-menu').slideUp(200);
        
        if (!isOpen) {
            $menu.slideDown(200);
            // فوکوس به input جستجو
            setTimeout(function() {
                $menu.find('.sc-search-input').focus();
            }, 250);
        }
    });
    
    // جستجو در dropdown
    $('.sc-search-input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase().trim();
        var $options = $(this).closest('.sc-dropdown-menu').find('.sc-dropdown-option');
        var visibleCount = 0;
        var maxVisible = 10;
        
        // حذف پیام "نتیجه‌ای یافت نشد" قبلی
        $options.closest('.sc-dropdown-options').find('div:not(.sc-dropdown-option)').remove();
        
        if (searchTerm === '') {
            // اگر جستجو خالی است، 10 مورد اول را نمایش بده
            $options.each(function(index) {
                if (index < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visibleCount++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
        } else {
            $options.each(function() {
                var searchText = $(this).attr('data-search') || '';
                var matches = searchText.includes(searchTerm);
                
                if (matches && visibleCount < maxVisible) {
                    $(this).removeClass('sc-hidden').addClass('sc-visible').show();
                    visibleCount++;
                } else {
                    $(this).addClass('sc-hidden').removeClass('sc-visible').hide();
                }
            });
            
            // اگر هیچ نتیجه‌ای پیدا نشد
            if (visibleCount === 0) {
                $options.closest('.sc-dropdown-options').append(
                    '<div style="padding: 15px; text-align: center; color: #757575; border-bottom: 1px solid #f0f0f1;">نتیجه‌ای یافت نشد</div>'
                );
            }
        }
    });
    
    // بستن dropdown با کلیک خارج
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sc-searchable-dropdown').length) {
            $('.sc-dropdown-menu').slideUp(200);
        }
    });
    
    // جلوگیری از بستن dropdown با کلیک داخل
    $('.sc-dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });
    
    // نمایش مقدار انتخاب شده در صورت وجود (برای invoice-add)
    $('.sc-searchable-dropdown').each(function() {
        var $dropdown = $(this);
        var selectedValue = $dropdown.find('input[type="hidden"]').val();
        if (selectedValue) {
            var $selectedOption = $dropdown.find('.sc-dropdown-option[data-value="' + selectedValue + '"]');
            if ($selectedOption.length) {
                var selectedText = $selectedOption.text().replace('✓', '').trim();
                $dropdown.find('.sc-dropdown-placeholder').hide();
                $dropdown.find('.sc-dropdown-selected').text(selectedText).show();
            }
        }
    });
});

// ============================================
// تابع مشترک فرمت کردن قیمت/مبلغ
// ============================================

function scFormatPrice(inputSelector, rawInputSelector) {
    var $input = jQuery(inputSelector);
    var $raw = jQuery(rawInputSelector);
    
    if (!$input.length || !$raw.length) {
        return;
    }
    
    $input.on('input', function() {
        var $this = $(this);
        var value = $this.val();
        
        // حذف تمام کاماها و کاراکترهای غیر عددی
        var cleaned = value.replace(/,/g, '').replace(/\D/g, '');
        
        // اگر خالی است
        if (cleaned === '' || cleaned === '0') {
            $this.val('');
            $raw.val('0');
            return;
        }
        
        // فرمت کردن با کاما (سه رقم سه رقم)
        var formatted = parseInt(cleaned, 10).toLocaleString('en-US');
        $this.val(formatted);
        
        // ذخیره مقدار خالص در hidden input
        $raw.val(cleaned);
    });
    
    // هنگام blur
    $input.on('blur', function() {
        var value = $(this).val();
        var cleaned = value.replace(/,/g, '');
        if (cleaned === '' || cleaned === '0') {
            $(this).val('');
            $raw.val('0');
        }
    });
    
    // قبل از submit
    $input.closest('form').on('submit', function() {
        var rawValue = $raw.val() || '0';
        $input.val(rawValue);
    });
    
    // فرمت کردن مقدار اولیه در صورت وجود
    if ($input.val()) {
        $input.trigger('input');
    }
}

// ============================================
// بخش افزودن حضور و غیاب
// ============================================

jQuery(document).ready(function($) {
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    $('#attendance_date').on('change', function() {
        var shamsiDate = $(this).val();
        if (shamsiDate) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                $('#attendance_date_hidden').val(gregorianDate);
                $('#attendance_date_hidden_form').val(gregorianDate);
                $('#attendance_date_shamsi_form').val(shamsiDate);
            }
        }
    });
    
    // تبدیل اولیه اگر تاریخ وجود دارد
    if ($('#attendance_date').val()) {
        $('#attendance_date').trigger('change');
    }
});

// ============================================
// لیست حضور و غیاب - فیلتر تاریخ
// ============================================

jQuery(document).ready(function($) {
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    function updateGregorianDate($shamsiInput) {
        var shamsiValue = $shamsiInput.val();
        var gregorianValue = convertShamsiToGregorian(shamsiValue);
        
        // پیدا کردن hidden input مربوطه
        var inputId = $shamsiInput.attr('id');
        if (inputId === 'filter_date_from_shamsi' || inputId === 'filter_date_from_shamsi_2') {
            var $hidden = (inputId === 'filter_date_from_shamsi') ? $('#filter_date_from') : $('#filter_date_from_2');
            $hidden.val(gregorianValue);
        } else if (inputId === 'filter_date_to_shamsi' || inputId === 'filter_date_to_shamsi_2') {
            var $hidden = (inputId === 'filter_date_to_shamsi') ? $('#filter_date_to') : $('#filter_date_to_2');
            $hidden.val(gregorianValue);
        } else if (inputId === 'filter_date_from_shamsi_3') {
            $('#filter_date_from_3').val(gregorianValue);
        } else if (inputId === 'filter_date_to_shamsi_3') {
            $('#filter_date_to_3').val(gregorianValue);
        }
    }
    
    $(document).on('change', '#filter_date_from_shamsi, #filter_date_to_shamsi, #filter_date_from_shamsi_2, #filter_date_to_shamsi_2, #filter_date_from_shamsi_3, #filter_date_to_shamsi_3', function() {
        updateGregorianDate($(this));
    });
});

// ============================================
// افزودن دوره (Course Add)
// ============================================

jQuery(document).ready(function($) {
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    $('#start_date_shamsi, #end_date_shamsi').on('change', function() {
        var inputId = $(this).attr('id');
        var shamsiDate = $(this).val();
        
        if (shamsiDate) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                if (inputId === 'start_date_shamsi') {
                    $('#start_date').val(gregorianDate);
                } else if (inputId === 'end_date_shamsi') {
                    $('#end_date').val(gregorianDate);
                }
            }
        } else {
            // اگر تاریخ خالی شد، فیلد میلادی را هم خالی کن
            if (inputId === 'start_date_shamsi') {
                $('#start_date').val('');
            } else if (inputId === 'end_date_shamsi') {
                $('#end_date').val('');
            }
        }
    });
    
    // تبدیل اولیه اگر تاریخ وجود دارد یا تاریخ پیش‌فرض را تنظیم کنیم
    if ($('#start_date_shamsi').val()) {
        $('#start_date_shamsi').trigger('change');
    } else {
        // اگر دوره جدید است و تاریخ پیش‌فرض تنظیم شده، آن را تبدیل کن
        setTimeout(function() {
            if ($('#start_date_shamsi').val() && !$('#start_date').val()) {
                $('#start_date_shamsi').trigger('change');
            }
        }, 100);
    }
    
    if ($('#end_date_shamsi').val()) {
        $('#end_date_shamsi').trigger('change');
    } else {
        // اگر دوره جدید است و تاریخ پیش‌فرض تنظیم شده، آن را تبدیل کن
        setTimeout(function() {
            if ($('#end_date_shamsi').val() && !$('#end_date').val()) {
                $('#end_date_shamsi').trigger('change');
            }
        }, 100);
    }
    
    // فرمت کردن قیمت
    scFormatPrice('#price', '#price_raw');
});

// ============================================
// افزودن رویداد (Event Add)
// ============================================

jQuery(document).ready(function($) {
    // مدیریت شرط سنی
    $('#has_age_limit').on('change', function() {
        if ($(this).is(':checked')) {
            $('#age_limit_fields').slideDown();
        } else {
            $('#age_limit_fields').slideUp(); // رفع باگ: slideDown به slideUp تغییر یافت
        }
    });

    // انتخاب عکس
    $('#upload_image_button').on('click', function(e) {
        e.preventDefault();
        var imageUploader = wp.media({
            title: 'انتخاب عکس رویداد',
            button: {
                text: 'استفاده از این عکس'
            },
            multiple: false
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            $('#image_url').val(attachment.url);
            if ($('#image_preview').length === 0) {
                $('#image_url').after('<div id="image_preview" style="margin-top: 10px;"><img src="' + attachment.url + '" alt="عکس رویداد" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;"></div>');
            } else {
                $('#image_preview img').attr('src', attachment.url);
            }
        });

        imageUploader.open();
    });

    // تبدیل تاریخ شمسی به میلادی
    $('#start_date_shamsi, #end_date_shamsi').on('change', function() {
        var inputId = $(this).attr('id');
        var shamsiDate = $(this).val();
        
        if (shamsiDate) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                if (inputId === 'start_date_shamsi') {
                    $('#start_date').val(gregorianDate);
                } else if (inputId === 'end_date_shamsi') {
                    $('#end_date').val(gregorianDate);
                }
            }
        } else {
            if (inputId === 'start_date_shamsi') {
                $('#start_date').val('');
            } else if (inputId === 'end_date_shamsi') {
                $('#end_date').val('');
            }
        }
    });
    
    // تبدیل اولیه اگر تاریخ وجود دارد
    if ($('#start_date_shamsi').val()) {
        $('#start_date_shamsi').trigger('change');
    }
    if ($('#end_date_shamsi').val()) {
        $('#end_date_shamsi').trigger('change');
    }
    
    // فرمت کردن قیمت
    scFormatPrice('#price', '#price_raw');
});

// ============================================
// افزودن هزینه (Expense Add)
// ============================================

jQuery(document).ready(function($) {
    // فرمت کردن مبلغ
    scFormatPrice('#amount', '#amount_raw');
    
    // تبدیل تاریخ شمسی به میلادی
    $('#expense_date_shamsi').on('change', function() {
        var shamsiDate = $(this).val();
        if (shamsiDate && shamsiDate.includes('/')) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                $('#expense_date_gregorian').val(gregorianDate);
            }
        }
    });
});

// ============================================
// لیست هزینه‌ها (Expense List) - فیلتر تاریخ
// ============================================

jQuery(document).ready(function($) {
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    function updateGregorianDate($shamsiInput) {
        var shamsiValue = $shamsiInput.val();
        var gregorianValue = convertShamsiToGregorian(shamsiValue);
        
        // پیدا کردن hidden input مربوطه
        var inputId = $shamsiInput.attr('id');
        if (inputId === 'filter_date_from_shamsi') {
            $('#filter_date_from').val(gregorianValue);
        } else {
            $('#filter_date_to').val(gregorianValue);
        }
    }
    
    $(document).on('change', '#filter_date_from_shamsi, #filter_date_to_shamsi', function() {
        updateGregorianDate($(this));
    });
});

// ============================================
// افزودن فاکتور (Invoice Add)
// ============================================

jQuery(document).ready(function($) {
    // فرمت کردن مبلغ
    scFormatPrice('#amount', '#amount_raw');
});

// ============================================
// لیست فاکتورها (Invoice List) - فیلتر تاریخ
// ============================================

jQuery(document).ready(function($) {
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    $('#filter_date_from_shamsi, #filter_date_to_shamsi').on('change', function() {
        var $shamsiInput = $(this);
        var shamsiValue = $shamsiInput.val();
        var gregorianValue = convertShamsiToGregorian(shamsiValue);
        
        // پیدا کردن hidden input مربوطه
        var inputId = $shamsiInput.attr('id');
        if (inputId === 'filter_date_from_shamsi') {
            $('#filter_date_from').val(gregorianValue);
        } else {
            $('#filter_date_to').val(gregorianValue);
        }
    });
});

// ============================================
// افزودن عضو (Member Add)
// ============================================

// تابع toggle برای آکاردئون دوره‌ها - باید در global scope باشد
window.toggleCoursesAccordion = function() {
    var content = document.getElementById("sc-courses-content");
    var icon = document.getElementById("courses-accordion-icon");
    
    if (!content || !icon) {
        console.error("Accordion elements not found");
        return;
    }
    
    if (content.style.display === "none" || content.style.display === "") {
        content.style.display = "block";
        icon.textContent = "▲";
    } else {
        content.style.display = "none";
        icon.textContent = "▼";
    }
};

jQuery(document).ready(function($) {
    $("input[name='courses[]']").change(function() {
        var courseId = $(this).val();
        var statusDiv = $("#course_status_" + courseId);
        var checkboxes = statusDiv.find("input[type=checkbox]");
        
        if ($(this).is(":checked")) {
            statusDiv.show();
            checkboxes.prop("disabled", false);
        } else {
            statusDiv.hide();
            checkboxes.prop("disabled", true);
            checkboxes.prop("checked", false);
        }
    });
});

// ============================================
// گزارش بدهکاران (Reports Debtors) - فیلتر تاریخ
// ============================================

jQuery(document).ready(function($) {
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    function updateGregorianDate($shamsiInput) {
        var shamsiValue = $shamsiInput.val();
        var gregorianValue = convertShamsiToGregorian(shamsiValue);
        
        var inputId = $shamsiInput.attr('id');
        if (inputId === 'filter_date_from_shamsi') {
            $('#filter_date_from').val(gregorianValue);
        } else {
            $('#filter_date_to').val(gregorianValue);
        }
    }
    
    $(document).on('change', '#filter_date_from_shamsi, #filter_date_to_shamsi', function() {
        updateGregorianDate($(this));
    });
});
