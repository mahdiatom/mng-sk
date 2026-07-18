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
    
    // اضافه کردن چکمارک (بدون حذف متن نام کاربر)
    $dropdown.find('.sc-option-check').remove();
    jQuery(element).append('<span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

// تابع انتخاب رویداد برای فیلتر
function scSelectEventFilter(element, eventId, eventText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $hiddenInput = $dropdown.find('input[type="hidden"]');
    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');
    var $menu = $dropdown.find('.sc-dropdown-menu');
    
    // تنظیم مقدار
    $hiddenInput.val(eventId);
    if (eventId == '0') {
        $placeholder.show();
        $selected.hide();
    } else {
        $placeholder.hide();
        $selected.text(eventText).show();
    }
    
    // بستن منو
    $menu.slideUp(200);
    
    // حذف انتخاب قبلی و اضافه کردن انتخاب جدید
    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
    jQuery(element).addClass('sc-selected').css('background', '#f0f6fc');
    
    // اضافه کردن چکمارک (بدون حذف متن رویداد)
    $dropdown.find('.sc-option-check').remove();
    jQuery(element).append('<span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

// تابع انتخاب دوره در ثبت حضور و غیاب (data-value داخلی: course_id|chapter|group)
function scAttendanceSplitCourseValue(courseValue) {
    var val = String(courseValue || '');
    if (!val) {
        return { courseId: '', chapter: '', group: '' };
    }
    var parts = val.split('|');
    return {
        courseId: parts[0] || '',
        chapter: parts[1] || '',
        group: parts[2] || ''
    };
}

function scAttendanceApplyCourseFields($dropdown, courseValue, courseText) {
    var parts = scAttendanceSplitCourseValue(courseValue);
    $dropdown.find('input[name="attendance_course_id"], #attendance_course_id').val(parts.courseId);
    $dropdown.find('input[name="attendance_chapter"], #attendance_chapter').val(parts.chapter);
    $dropdown.find('input[name="attendance_group"], #attendance_group').val(parts.group);
    $dropdown.find('#attendance_course_option_value').val(courseValue || '');

    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');

    if (!courseValue) {
        $placeholder.show();
        $selected.hide().text('');
    } else {
        $placeholder.hide();
        $selected.text(courseText).show();
    }
}

function scSelectCourseAttendance(element, courseValue, courseText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $menu = $dropdown.find('.sc-dropdown-menu');

    scAttendanceApplyCourseFields($dropdown, courseValue, courseText);

    $menu.slideUp(200);

    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
    jQuery(element).addClass('sc-selected').css('background', '#f0f6fc');

    $dropdown.find('.sc-option-check').remove();
    jQuery(element).append('<span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
}

// تابع انتخاب دوره در فیلتر لیست حضور و غیاب
function scSelectCourseFilter(element, courseId, courseText) {
    var $dropdown = jQuery(element).closest('.sc-searchable-dropdown');
    var $hiddenInput = $dropdown.find('input[type="hidden"]');
    var $toggle = $dropdown.find('.sc-dropdown-toggle');
    var $placeholder = $toggle.find('.sc-dropdown-placeholder');
    var $selected = $toggle.find('.sc-dropdown-selected');
    var $menu = $dropdown.find('.sc-dropdown-menu');

    $hiddenInput.val(courseId);
    if (courseId == '0') {
        $placeholder.show();
        $selected.hide();
    } else {
        $placeholder.hide();
        $selected.text(courseText).show();
    }

    $menu.slideUp(200);

    $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
    jQuery(element).addClass('sc-selected').css('background', '#f0f6fc');

    $dropdown.find('.sc-option-check').remove();
    jQuery(element).append('<span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
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
    
    // اضافه کردن چکمارک (بدون حذف نام کاربر)
    $dropdown.find('.sc-option-check').remove();
    jQuery(element).find('.sc-option-check').remove();
    jQuery(element).append('<span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span>');
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
            $menu.slideDown(200, function () {
                if (window.scAudienceDropdownStack) {
                    window.scAudienceDropdownStack.sync($menu.closest('.sc-searchable-dropdown, .sc-audience-course-picker'));
                }
            });
            // فوکوس به input جستجو
            setTimeout(function() {
                $menu.find('.sc-search-input').focus();
            }, 250);
        } else if (window.scAudienceDropdownStack) {
            window.scAudienceDropdownStack.clear();
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
            if (window.scAudienceDropdownStack) {
                window.scAudienceDropdownStack.clear();
            }
        }
    });

    // انتخاب دوره در dropdown حضور و غیاب (capture — قبل از stopPropagation منو)
    document.addEventListener('click', function(e) {
        var option = e.target.closest('.sc-dropdown-option');
        if (!option) {
            return;
        }

        var filterDropdown = option.closest('.sc-attendance-course-filter-dropdown');
        var addDropdown = option.closest('.sc-attendance-course-dropdown');

        if (!filterDropdown && !addDropdown) {
            return;
        }

        var value = option.getAttribute('data-value') || '';
        var label = option.getAttribute('data-label') || (option.textContent || '').replace('✓', '').trim();

        if (filterDropdown && typeof scSelectCourseFilter === 'function') {
            scSelectCourseFilter(option, value, label);
        } else if (addDropdown && typeof scSelectCourseAttendance === 'function') {
            scSelectCourseAttendance(option, value, label);
        }
    }, true);
    
    // جلوگیری از بستن dropdown با کلیک داخل
    $('.sc-dropdown-menu').on('click', function(e) {
        e.stopPropagation();
    });
    
    // نمایش مقدار انتخاب شده در صورت وجود (برای invoice-add و ثبت حضور)
    $('.sc-searchable-dropdown').each(function() {
        var $dropdown = $(this);
        var selectedValue = $dropdown.find('#attendance_course_option_value').val();
        if (!selectedValue) {
            selectedValue = $dropdown.find('input[type="hidden"]').first().val();
        }
        if (selectedValue) {
            var $selectedOption = $dropdown.find('.sc-dropdown-option').filter(function() {
                return String($(this).attr('data-value')) === String(selectedValue);
            }).first();
            if ($selectedOption.length) {
                var selectedText = $selectedOption.attr('data-label') || $selectedOption.text().replace('✓', '').trim();
                $dropdown.find('.sc-dropdown-placeholder').hide();
                $dropdown.find('.sc-dropdown-selected').text(selectedText).show();
            }
        }
    });
});

// ============================================
// تابع مشترک فرمت کردن قیمت/مبلغ
// ============================================

// Event Delegation برای فرمت کردن قیمت/مبلغ (برای اطمینان از کارکرد)
jQuery(document).ready(function($) {
    // فرمت کردن برای فیلدهای قیمت/مبلغ با event delegation
    $(document).on('input', '#price, #amount, #price_per_session', function() {
        var $this = $(this);
        var inputId = $this.attr('id');
        var rawSelector = '';
        if (inputId === 'price') {
            rawSelector = '#price_raw';
        } else if (inputId === 'price_per_session') {
            rawSelector = '#price_per_session_raw';
        } else {
            rawSelector = '#amount_raw';
        }
        var $raw = $(rawSelector);
        
        if (!$raw.length) {
            return;
        }
        
        var value = $this.val();
        var cleaned = value.toString().replace(/,/g, '').replace(/[^\d]/g, '');
        
        if (cleaned === '' || cleaned === '0') {
            $this.val('');
            $raw.val('0');
            return;
        }
        
        // تبدیل به عدد برای اطمینان از صحت
        var numValue = parseInt(cleaned, 10);
        if (isNaN(numValue) || numValue < 0) {
            $this.val('');
            $raw.val('0');
            return;
        }
        
        // فرمت کردن با کاما (سه رقم سه رقم) - استفاده از روش دستی
        var formatted = numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var cursorPos = this.selectionStart || 0;
        var originalLength = value.length;
        
        $this.val(formatted);
        $raw.val(cleaned);
        
        // حفظ موقعیت cursor
        var digitsBeforeCursor = value.substring(0, cursorPos).replace(/,/g, '').replace(/\D/g, '').length;
        var newCursorPos = formatted.length;
        var digitCount = 0;
        for (var i = 0; i < formatted.length; i++) {
            if (formatted[i] !== ',') {
                digitCount++;
                if (digitCount >= digitsBeforeCursor) {
                    newCursorPos = i + 1;
                    break;
                }
            }
        }
        if (cursorPos >= originalLength) {
            newCursorPos = formatted.length;
        }
        
        setTimeout(function() {
            if (this.setSelectionRange) {
                this.setSelectionRange(newCursorPos, newCursorPos);
            }
        }.bind(this), 0);
    });
});

// تابع فرمت کردن مقدار (مشترک)
function scFormatValue(value) {
    // حذف تمام کاماها و کاراکترهای غیر عددی
    var cleaned = value.toString().replace(/,/g, '').replace(/[^\d]/g, '');
    
    // اگر خالی است
    if (cleaned === '' || cleaned === '0') {
        return { formatted: '', raw: '0' };
    }
    
    // تبدیل به عدد برای اطمینان از صحت
    var numValue = parseInt(cleaned, 10);
    if (isNaN(numValue) || numValue < 0) {
        return { formatted: '', raw: '0' };
    }
    
    // فرمت کردن با کاما (سه رقم سه رقم) - استفاده از روش دستی برای اطمینان
    var formatted = numValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return { formatted: formatted, raw: numValue.toString() };
}

// تابع فرمت کردن فیلد قیمت/مبلغ
function scFormatPrice(inputSelector, rawInputSelector) {
    var $input = jQuery(inputSelector);
    var $raw = jQuery(rawInputSelector);
    
    if (!$input.length || !$raw.length) {
        return;
    }
    
    // تابع فرمت کردن با حفظ موقعیت cursor
    function formatInputField(inputElement) {
        var $this = jQuery(inputElement);
        var value = $this.val();
        
        // ذخیره موقعیت cursor
        var cursorPos = inputElement.selectionStart || 0;
        var originalLength = value.length;
        
        // فرمت کردن
        var result = scFormatValue(value);
        var newValue = result.formatted;
        var newLength = newValue.length;
        
        // تنظیم مقدار جدید
        $this.val(newValue);
        $raw.val(result.raw);
        
        // محاسبه موقعیت جدید cursor
        var digitsBeforeCursor = value.substring(0, cursorPos).replace(/,/g, '').replace(/\D/g, '').length;
        
        // پیدا کردن موقعیت جدید cursor
        var newCursorPos = newLength;
        var digitCount = 0;
        for (var i = 0; i < newValue.length; i++) {
            if (newValue[i] !== ',') {
                digitCount++;
                if (digitCount >= digitsBeforeCursor) {
                    newCursorPos = i + 1;
                    break;
                }
            }
        }
        
        // اگر cursor در انتها بود، آن را در انتها نگه دار
        if (cursorPos >= originalLength) {
            newCursorPos = newLength;
        }
        
        // تنظیم موقعیت cursor
        setTimeout(function() {
            if (inputElement.setSelectionRange) {
                inputElement.setSelectionRange(newCursorPos, newCursorPos);
            }
        }, 0);
    }
    
    // حذف event handler های قبلی (برای جلوگیری از duplicate)
    $input.off('input.scFormatPrice keyup.scFormatPrice paste.scFormatPrice');
    
    // هنگام تایپ (input event) - فرمت کردن همزمان با تایپ
    $input.on('input.scFormatPrice', function() {
        formatInputField(this);
    });
    
    // هنگام keyup (برای اطمینان بیشتر)
    $input.on('keyup.scFormatPrice', function(e) {
        // فقط برای اعداد و کلیدهای خاص
        var key = e.keyCode || e.which;
        if ((key >= 48 && key <= 57) || 
            (key >= 96 && key <= 105) || 
            key === 8 || key === 46 || key === 37 || key === 39 || 
            key === 35 || key === 36) {
            formatInputField(this);
        }
    });
    
    // هنگام keydown برای اعداد
    $input.on('keydown.scFormatPrice', function(e) {
        var key = e.keyCode || e.which;
        // اجازه دادن به اعداد و کلیدهای خاص
        if ((key >= 48 && key <= 57) || 
            (key >= 96 && key <= 105) || 
            key === 8 || key === 46 || key === 37 || key === 39 || 
            key === 35 || key === 36 || key === 9 || key === 13) {
            return true;
        }
        // جلوگیری از کاراکترهای غیر عددی
        if (key >= 65 && key <= 90) {
            e.preventDefault();
            return false;
        }
    });
    
    // هنگام paste
    $input.on('paste.scFormatPrice', function() {
        var $this = jQuery(this);
        setTimeout(function() {
            formatInputField($this[0]);
        }, 10);
    });
    
    // هنگام blur
    $input.on('blur.scFormatPrice', function() {
        var value = jQuery(this).val();
        var cleaned = value.replace(/,/g, '');
        if (cleaned === '' || cleaned === '0') {
            jQuery(this).val('');
            $raw.val('0');
    }
    });
    
    // قبل از submit: مقدار نهایی فقط از فیلد قابل‌مشاهده (منبع حقیقت) تا از ذخیرهٔ اشتباه جلوگیری شود
    $input.closest('form').on('submit.scFormatPrice', function() {
        var value = $input.val() || '';
        var cleaned = value.replace(/,/g, '').replace(/\D/g, '');
        var rawValue = cleaned === '' ? '0' : cleaned;
        $raw.val(rawValue);
        $input.val(rawValue);
    });
    
    // فرمت کردن مقدار اولیه در صورت وجود (بعد از بارگذاری کامل صفحه)
    setTimeout(function() {
        var currentValue = $input.val();
        if (currentValue) {
            // بررسی اینکه آیا مقدار فرمت شده است یا نه
            var hasComma = currentValue.indexOf(',') !== -1;
            // اگر مقدار raw وجود دارد، از آن استفاده کن
            var rawValue = $raw.val();
            
            if (rawValue && rawValue !== '0') {
                // اگر مقدار raw وجود دارد، از آن برای فرمت کردن استفاده کن
                var result = scFormatValue(rawValue);
                $input.val(result.formatted);
                $raw.val(result.raw);
            } else if (!hasComma && /^\d+$/.test(currentValue.replace(/,/g, ''))) {
                // اگر مقدار فرمت نشده (بدون کاما و فقط عدد است)، فرمت کن
                var result = scFormatValue(currentValue);
                $input.val(result.formatted);
                $raw.val(result.raw);
            } else if (hasComma) {
                // اگر مقدار فرمت شده است، فقط raw را تنظیم کن
                var cleaned = currentValue.replace(/,/g, '').replace(/\D/g, '');
                $raw.val(cleaned || '0');
            }
        }
    }, 100);
}

// مدیریت ردیف‌های پکیج قیمت دوره در صفحه افزودن/ویرایش دوره
window.scInitCoursePackagesUI = function () {
    var $ = jQuery;
    var $wrap = $('#sc-course-packages-wrap');
    if (!$wrap.length) {
        return;
    }

    var $body = $('#sc-course-packages-body');
    var $addBtn = $('#sc-add-course-package-row');

    function bindRowPriceFormatter($row) {
        var $price = $row.find('.sc-pkg-price-input');
        var $raw = $row.find('.sc-pkg-price-raw');
        if ($price.length && $raw.length) {
            scFormatPrice($price, $raw);
        }
    }

    function makeRow() {
        return $(
            '<article class="sc-course-pkg-card sc-course-package-row">' +
                '<div class="sc-course-field"><label class="sc-course-field__label">تعداد جلسه</label>' +
                '<input type="number" min="1" class="sc-course-input sc-pkg-sessions-input sc-course-pkg-field" name="pkg_sessions[]"></div>' +
                '<div class="sc-course-field"><label class="sc-course-field__label">قیمت (تومان)</label>' +
                    '<input type="text" class="sc-course-input sc-pkg-price-input sc-course-pkg-field" name="pkg_price[]" dir="ltr" inputmode="numeric">' +
                    '<input type="hidden" class="sc-pkg-price-raw" name="pkg_price_raw[]" value="0">' +
                '</div>' +
                '<button type="button" class="sc-course-pkg-remove sc-remove-package-row" title="حذف پکیج">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
                '</button>' +
            '</article>'
        );
    }

    $body.find('.sc-course-package-row').each(function () {
        bindRowPriceFormatter($(this));
    });

    $addBtn.off('click.scCoursePkg').on('click.scCoursePkg', function () {
        var $row = makeRow();
        $body.append($row);
        bindRowPriceFormatter($row);
    });

    $body.off('click.scCoursePkg', '.sc-remove-package-row').on('click.scCoursePkg', '.sc-remove-package-row', function () {
        $(this).closest('.sc-course-package-row').remove();
    });
};

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
    
    // فرمت کردن قیمت - فقط اگر مقدار موجود باشد
    if ($('#price').length && $('#price_raw').length) {
        // اگر قیمت از قبل فرمت شده است (دارای کاما است)، آن را به raw تبدیل کن
        var currentPrice = $('#price').val();
        if (currentPrice && currentPrice.includes(',')) {
            var cleanedPrice = currentPrice.replace(/,/g, '');
            if (!isNaN(cleanedPrice) && cleanedPrice !== '') {
                $('#price_raw').val(cleanedPrice);
        }
        } else if (currentPrice && !isNaN(currentPrice) && currentPrice !== '') {
            // اگر قیمت بدون کاما است، آن را در raw هم ذخیره کن
            $('#price_raw').val(currentPrice.replace(/[^\d]/g, ''));
    }
        scFormatPrice('#price', '#price_raw');
    }

    $('#btn_course_image').on('click', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }
        var inputField = $('#course_image_url');
        var imageUploader = wp.media({
            title: 'انتخاب عکس دوره',
            button: {
                text: 'استفاده از این عکس'
            },
            multiple: false
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            var previewContainer = inputField.closest('.sc-course-field').find('.sc-course-image-preview');
            if (previewContainer.length === 0) {
                inputField.closest('.sc-course-field').append(
                    '<div class="sc-course-image-preview img_photo_prev"><img src="' + attachment.url + '" alt="عکس دوره"></div>'
                );
            } else {
                previewContainer.show();
                if (previewContainer.find('img').length === 0) {
                    previewContainer.append('<img src="' + attachment.url + '" alt="عکس دوره">');
                } else {
                    previewContainer.find('img').attr('src', attachment.url);
                }
            }
        });

        imageUploader.open();
    });
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

    $('#income_date_shamsi').on('change', function() {
        var shamsiDate = $(this).val();
        if (shamsiDate && shamsiDate.includes('/')) {
            var gregorianDate = convertShamsiToGregorian(shamsiDate);
            if (gregorianDate) {
                $('#income_date_gregorian').val(gregorianDate);
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
// تنظیمات باشگاه - فیلدهای مبلغ
// ============================================
jQuery(document).ready(function($) {
    scFormatPrice('#penalty_amount', '#penalty_amount_raw');
    scFormatPrice('#wallet_min_charge', '#wallet_min_charge_raw');
    scFormatPrice('#wallet_max_charge', '#wallet_max_charge_raw');
    scFormatPrice('#wallet_max_negative_balance', '#wallet_max_negative_balance_raw');
    scFormatPrice('#wallet_min_balance_alert', '#wallet_min_balance_alert_raw');
    scFormatPrice('#coach_min_withdrawal_amount', '#coach_min_withdrawal_amount_raw');
    scFormatPrice('#coach_max_negative_balance', '#coach_max_negative_balance_raw');
});

// ============================================
// افزودن فاکتور (Invoice Add)
// ============================================

jQuery(document).ready(function($) {
    // فرمت کردن مبلغ فاکتور / هزینه / شارژ / کسر (هر صفحه‌ای که این فیلدها را داشته باشد)
    scFormatPrice('#amount', '#amount_raw');
    // مقدار تسویه ثابت ماهیانه مربی فقط در قالب coach-add.php با اسکریپت همان صفحه مدیریت می‌شود
    scFormatPrice('#filter_amount_min', '#filter_amount_min_raw');
    scFormatPrice('#filter_amount_max', '#filter_amount_max_raw');
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
        if (content) {
            content.style.display = "block";
        }
    };
    
    jQuery(document).ready(function($) {
        var $coursesContent = $("#sc-courses-content");
        if ($coursesContent.length) {
            $coursesContent.show();
        }
        $("input[name='courses[]']").each(function () {
            $(this).trigger("change");
        });
        $("input[name='courses[]']").change(function () {
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

    // انتخاب عکس پرسنلی
    $('#btn_personal_photo').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var inputField = $('#personal_photo_txt');
        
        var imageUploader = wp.media({
            title: 'انتخاب عکس پرسنلی',
            button: {
                text: 'استفاده از این عکس'
            },
            multiple: false
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
    
            // نمایش پیش‌نمایش
            var previewContainer = inputField.closest('td').find('.sc-image-preview');
            if (previewContainer.length === 0) {
                inputField.after('<div class="sc-image-preview" style="margin-top: 10px;"><img src="' + attachment.url + '" alt="عکس پرسنلی" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;"></div>');
    } else {
                previewContainer.find('img').attr('src', attachment.url);
    }
        });

        imageUploader.open();
    });
    
    // انتخاب عکس کارت ملی
    $('#btn_id_card_photo').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var inputField = $('#id_card_photo_txt');
        
        var imageUploader = wp.media({
            title: 'انتخاب عکس کارت ملی',
            button: {
                text: 'استفاده از این عکس'
            },
            multiple: false
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
    
            // نمایش پیش‌نمایش
            var previewContainer = inputField.closest('td').find('.sc-image-preview');
            if (previewContainer.length === 0) {
                inputField.after('<div class="sc-image-preview" style="margin-top: 10px;"><img src="' + attachment.url + '" alt="عکس کارت ملی" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;"></div>');
            } else {
                previewContainer.find('img').attr('src', attachment.url);
            }
        });

        imageUploader.open();
    });
    
    // انتخاب عکس بیمه ورزشی
    $('#btn_sport_insurance_photo').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var inputField = $('#sport_insurance_photo_txt');
        
        var imageUploader = wp.media({
            title: 'انتخاب عکس بیمه ورزشی',
            button: {
                text: 'استفاده از این عکس'
            },
            multiple: false
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            
            // نمایش پیش‌نمایش
            var previewContainer = inputField.closest('td').find('.sc-image-preview');
            if (previewContainer.length === 0) {
                inputField.after('<div class="sc-image-preview" style="margin-top: 10px;"><img src="' + attachment.url + '" alt="عکس بیمه ورزشی" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;"></div>');
                } else {
                previewContainer.find('img').attr('src', attachment.url);
                }
            });
            
        imageUploader.open();
    });

    $('#btn_coaching_certificate_photo').on('click', function(e) {
        e.preventDefault();
        var inputField = $('#coaching_certificate_photo_txt');

        var imageUploader = wp.media({
            title: 'انتخاب مدرک مربیگری',
            button: {
                text: 'استفاده از این فایل'
            },
            multiple: false,
            library: {
                type: ['image', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
            }
        });

        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);

            var isImage = (attachment.type === 'image') || /\.(jpe?g|png|gif|webp)$/i.test(attachment.url || '');
            var previewHtml = isImage
                ? '<img src="' + attachment.url + '" alt="مدرک مربیگری" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 4px;">'
                : '<a href="' + attachment.url + '" target="_blank" rel="noopener noreferrer">مشاهده فایل مدرک مربیگری</a>';

            var previewContainer = inputField.closest('td').find('.sc-image-preview');
            if (previewContainer.length === 0) {
                inputField.after('<div class="sc-image-preview" style="margin-top: 10px;">' + previewHtml + '</div>');
            } else {
                previewContainer.html(previewHtml);
            }
        });

        imageUploader.open();
    });

    $(document).on('click', '.sc-player-custom-upload-btn', function(e) {
        e.preventDefault();
        var inputField = $($(this).data('target'));
        if (!inputField.length || typeof wp === 'undefined' || !wp.media) {
            return;
        }
        var imageUploader = wp.media({
            title: 'انتخاب تصویر',
            button: { text: 'استفاده از این عکس' },
            multiple: false
        });
        imageUploader.on('select', function() {
            var attachment = imageUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url);
            var previewContainer = inputField.closest('td').find('.sc-image-preview');
            if (previewContainer.length === 0) {
                inputField.after('<div class="img_photo_prev sc-image-preview"><img src="' + attachment.url + '" alt=""></div>');
            } else {
                previewContainer.find('img').attr('src', attachment.url);
            }
        });
        imageUploader.open();
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

// ============================================
// مدیریت فیلدهای سفارشی رویداد
// ============================================

// شمارنده برای فیلدهای جدید (بدون ID)
var scEventFieldCounter = 0;

jQuery(document).ready(function($) {
    // افزودن فیلد جدید
    $(document).on('click', '#sc-add-event-field-btn', function() {
        scEventFieldCounter++;
        var fieldHtml = '<div class="sc-event-field-item" data-field-temp-id="' + scEventFieldCounter + '" style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">' +
            '<div class="item_field_event_add" >' +
            '<div style="flex: 1;">' +
            '<label style="display: block; margin-bottom: 5px; font-weight: bold;">نام فیلد:</label>' +
            '<input type="text" name="event_fields[new_' + scEventFieldCounter + '][field_name]" class="regular-text sc-field-name" placeholder="مثال: نام تیم" required>' +
            '</div>' +
            '<div style="flex: 1;">' +
            '<label style="display: block; margin-bottom: 5px; font-weight: bold;">نوع فیلد:</label>' +
            '<select name="event_fields[new_' + scEventFieldCounter + '][field_type]" class="sc-field-type" required>' +
            '<option value="text">متن</option>' +
            '<option value="number">عدد</option>' +
            '<option value="date">تاریخ</option>' +
            '<option value="file">فایل (عکس/PDF)</option>' +
            '<option value="select">انتخاب لیستی</option>' +
            '</select>' +
            '</div>' +
            '<div class="sc-field-options-container" style="flex: 1; display: none;">' +
            '<label style="display: block; margin-bottom: 5px; font-weight: bold;">گزینه‌های لیست (با کاما جدا کنید):</label>' +
            '<input type="text" name="event_fields[new_' + scEventFieldCounter + '][field_options]" class="regular-text sc-field-options" placeholder="گزینه 1, گزینه 2, گزینه 3">' +
            '</div>' +
            '<div style="flex-shrink: 0; padding-top: 25px;">' +
            '<label style="display: flex; align-items: center; gap: 5px;">' +
            '<input type="checkbox" name="event_fields[new_' + scEventFieldCounter + '][is_required]" value="1">' +
            '<span>اجباری</span>' +
            '</label>' +
            '</div>' +
            '<div style="flex-shrink: 0; padding-top: 25px;">' +
            '<button type="button" class="button sc-remove-field-btn" style="color: #d63638;">حذف</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('#sc-event-fields-container').append(fieldHtml);
    });

    // حذف فیلد
    $(document).on('click', '.sc-remove-field-btn', function() {
        var $btn = $(this);
        window.scConfirm({ type: 'danger', message: 'آیا از حذف این فیلد اطمینان دارید؟' }).then(function (ok) {
            if (ok) {
                $btn.closest('.sc-event-field-item').remove();
            }
        });
    });

    // نمایش/مخفی کردن فیلد گزینه‌ها برای نوع select
    $(document).on('change', '.sc-field-type', function() {
        var $container = $(this).closest('.sc-event-field-item').find('.sc-field-options-container');
        if ($(this).val() === 'select') {
            $container.show();
    } else {
            $container.hide();
        }
    });
    
    // برای فیلدهای موجود (در صورت ویرایش)
    $('.sc-field-type').each(function() {
        if ($(this).val() === 'select') {
            $(this).closest('.sc-event-field-item').find('.sc-field-options-container').show();
}
    });
    
    // مدیریت modal ثبت‌نامی‌های رویداد
    $(document).on('click', '.sc-view-registration', function(e) {
        e.preventDefault();
        var registrationId = $(this).data('registration-id');
        
        if (!registrationId) {
            alert('شناسه ثبت‌نام معتبر نیست');
            return;
        }
        
        // دریافت nonce از data attribute یا global variable
        var nonce = typeof scRegistrationNonce !== 'undefined' ? scRegistrationNonce : '';
        
        // نمایش loading
        $('#sc-registration-modal .sc-modal-body').html('<p>در حال بارگذاری...</p>');
        $('#sc-registration-modal').fadeIn(300);
        
        // درخواست Ajax
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'sc_get_registration_details',
                registration_id: registrationId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#sc-registration-modal .sc-modal-body').html(response.data.html);
                } else {
                    $('#sc-registration-modal .sc-modal-body').html('<p style="color: red;">خطا: ' + (response.data.message || 'خطای نامشخص') + '</p>');
                }
            },
            error: function() {
                $('#sc-registration-modal .sc-modal-body').html('<p style="color: red;">خطا در ارتباط با سرور</p>');
            }
        });
    });
    
    // بستن modal
    $(document).on('click', '.sc-modal-close, .sc-modal', function(e) {
        if (e.target === this) {
            $('#sc-registration-modal').fadeOut(300);
        }
    });
    
    // جلوگیری از بستن modal با کلیک روی محتوا
    $(document).on('click', '.sc-modal-content', function(e) {
        e.stopPropagation();
    });
    
    // تغییر وضعیت به تایید پرداخت
    $(document).on('click', '.sc-complete-registration', function(e) {
        e.preventDefault();
        var registrationId = $(this).data('registration-id');
        var invoiceId = $(this).data('invoice-id');
        
        window.scConfirm({ type: 'warning', message: 'آیا از تایید پرداخت این ثبت‌نام اطمینان دارید؟' }).then(function (ok) {
        if (!ok) {
            return;
        }
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'sc_change_registration_status',
                registration_id: registrationId,
                invoice_id: invoiceId,
                new_status: 'completed',
                nonce: typeof scChangeStatusNonce !== 'undefined' ? scChangeStatusNonce : ''
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا: ' + (response.data.message || 'خطای نامشخص'));
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
            }
        });
        });
    });
    
    // تغییر وضعیت به لغو شده
    $(document).on('click', '.sc-cancel-registration', function(e) {
        e.preventDefault();
        var registrationId = $(this).data('registration-id');
        var invoiceId = $(this).data('invoice-id');
        
        window.scConfirm({ type: 'danger', message: 'آیا از لغو این ثبت‌نام اطمینان دارید؟' }).then(function (ok) {
        if (!ok) {
            return;
        }
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'sc_change_registration_status',
                registration_id: registrationId,
                invoice_id: invoiceId,
                new_status: 'cancelled',
                nonce: typeof scChangeStatusNonce !== 'undefined' ? scChangeStatusNonce : ''
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا: ' + (response.data.message || 'خطای نامشخص'));
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
            }
        });
        });
    });
    
    // تغییر وضعیت به تایید پرداخت
    $(document).on('click', '.sc-complete-registration', function(e) {
        e.preventDefault();
        var registrationId = $(this).data('registration-id');
        var invoiceId = $(this).data('invoice-id');
        
        window.scConfirm({ type: 'warning', message: 'آیا از تایید پرداخت این ثبت‌نام اطمینان دارید؟' }).then(function (ok) {
        if (!ok) {
            return;
        }
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'sc_change_registration_status',
                registration_id: registrationId,
                invoice_id: invoiceId,
                new_status: 'completed',
                nonce: typeof scChangeStatusNonce !== 'undefined' ? scChangeStatusNonce : ''
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
        } else {
                    alert('خطا: ' + (response.data.message || 'خطای نامشخص'));
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
        }
        });
        });
    });
    
    // تغییر وضعیت به لغو شده
    $(document).on('click', '.sc-cancel-registration', function(e) {
        e.preventDefault();
        var registrationId = $(this).data('registration-id');
        var invoiceId = $(this).data('invoice-id');
        
        window.scConfirm({ type: 'danger', message: 'آیا از لغو این ثبت‌نام اطمینان دارید؟' }).then(function (ok) {
        if (!ok) {
            return;
        }
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'sc_change_registration_status',
                registration_id: registrationId,
                invoice_id: invoiceId,
                new_status: 'cancelled',
                nonce: typeof scChangeStatusNonce !== 'undefined' ? scChangeStatusNonce : ''
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('خطا: ' + (response.data.message || 'خطای نامشخص'));
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور');
            }
        });
        });
    });
});

//فعال سازی رویداد رایگان

document.addEventListener('DOMContentLoaded', function () {
    const freeCheckbox = document.getElementById('is_free_event');
    const priceInput = document.getElementById('price');

    function togglePrice() {
        if (freeCheckbox.checked) {
            priceInput.value = 0;
            priceInput.setAttribute('disabled', 'disabled');
        } else {
            priceInput.removeAttribute('disabled');
            priceInput.focus();
        }
    }

    freeCheckbox.addEventListener('change', togglePrice);
    togglePrice(); // حالت اولیه
});




    if (
        currentPath === '/shop/' || 
        currentPath.startsWith('/shop/') || 
        currentPath.includes('/product-category/') || 
        currentPath.includes('/product-tag/')
    ) {

jQuery(document).ready(function($) {
    
    // فیلتر زنده با AJAX
    function applyFilter() {
        const $search = $('#filter-search').val();
        const $category = $('#filter-category').val();
        const $tag = $('#filter-tag').val();
// ساخت URL جدید
        const url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('product_cat');
        url.searchParams.delete('product_tag');
if ($search) url.searchParams.set('s', $search);
        if ($category) url.searchParams.set('product_cat', $category);
        if ($tag) url.searchParams.set('product_tag', $tag);
window.history.pushState({}, '', url.toString());
// ارسال درخواست AJAX
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'GET',
            data: {
                action: 'filter_products_ajax',
                search: $search,
                category: $category,
                tag: $tag,
                nonce: ajax_object.nonce
            },
            success: function(response) {
                
                $('.products').html(response.data.html).show();
                $('p.woocommerce-result-count').html(response.data.count);
                window.history.pushState({}, '', url.toString());
                $('html, body').animate({ scrollTop: 0 }, 300);
            },
            error: function() {
                alert('خطا در بارگذاری محصولات. لطفاً دوباره تلاش کنید.');
            }
        });
    }
// اعمال فیلتر وقتی کاربر تایپ یا تغییر دهد
    $('#filter-search, #filter-category, #filter-tag').on('change keyup', function(e) {
        if (e.type === 'keyup' && e.which !== 13) return;
        applyFilter();
    });
// اعمال فیلتر وقتی دکمه بزنند
    $('button[type="submit"]').on('click', function(e) {
        e.preventDefault();
        applyFilter();
    });
// اعمال فیلتر وقتی از لینک دسته‌بندی/برچسب کلیک کنند
    $(document).on('click', 'a.product-category-link, a.product-tag-link', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const url = new URL(href, window.location.origin);
        const category = url.searchParams.get('product_cat');
        const tag = url.searchParams.get('product_tag');
        const search = url.searchParams.get('s');
$('#filter-search').val(search || '');
        $('#filter-category').val(category || '');
        $('#filter-tag').val(tag || '');
applyFilter();
    });
});

    }

/**
 * لیست برگه‌ها / نوشته‌های وردپرس — چیدمان مشابه لیست صورت‌حساب‌ها
 */
function scInitWpContentListAdminLayout() {
    var $body = jQuery('body');
    if (!$body.hasClass('sc-wp-content-list-page') || !$body.hasClass('edit-php')) {
        return;
    }

    var $wrap = jQuery('#wpbody-content > .wrap').first();
    if (!$wrap.length || $wrap.data('scWpContentListReady')) {
        return;
    }
    $wrap.data('scWpContentListReady', 1);
    $wrap.addClass('sc-reports-list-wrap sc-wp-content-list-wrap');

    var isPage = $body.hasClass('post-type-page');
    var listUrl = isPage ? 'edit.php?post_type=page' : 'edit.php';
    var desc = isPage
        ? 'مدیریت برگه‌های سایت. برای ویرایش روی عنوان کلیک کنید.'
        : 'مدیریت نوشته‌های سایت. برای ویرایش روی عنوان کلیک کنید.';

    var $h1 = $wrap.children('h1.wp-heading-inline, h1').first();
    var $addBtn = $wrap.children('.page-title-action').first();
    if ($h1.length && !$wrap.children('.sc-wp-content-list-header').length) {
        var $header = jQuery('<div class="sc-wp-content-list-header sc-reports-list-header"></div>');
        var $text = jQuery('<div class="sc-wp-content-list-header-text sc-reports-list-header-text"></div>');
        $h1.removeClass('wp-heading-inline').addClass('sc-wp-content-list-title sc-reports-list-title');
        $text.append($h1).append(
            jQuery('<p class="sc-wp-content-list-desc sc-reports-list-desc"></p>').text(desc)
        );
        var $actions = jQuery('<div class="sc-wp-content-list-header-actions sc-reports-list-header-actions"></div>');
        if ($addBtn.length) {
            $addBtn.addClass('sc-wp-content-list-add-btn sc-reports-list-add-btn');
            $actions.append($addBtn);
        }
        $header.append($text).append($actions);
        $wrap.prepend($header);
        $wrap.children('hr.wp-header-end').remove();
    }

    var $form = $wrap.find('#posts-filter').first();
    if (!$form.length) {
        return;
    }

    var formId = $form.attr('id') || 'posts-filter';

    var $subsub = $wrap.children('.subsubsub').first();
    if ($subsub.length && !$form.children('.subsubsub').length) {
        $form.prepend($subsub.detach());
    }

    $form.addClass('sc-wp-content-list-table-card sc-reports-list-table-card');

    if ($wrap.find('.sc-wp-content-list-filters-card').length) {
        return;
    }

    var $searchBox = $form.find('p.search-box').first();
    var $topNav = $form.find('.tablenav.top').first();
    var $filterActions = $topNav.find('.alignleft.actions').not('.bulkactions').first();
    var $searchInput = $searchBox.find('input[type="search"], input[name="s"]').first();
    var $monthSelect = $filterActions.find('select[name="m"]').first();
    var $catSelect = $filterActions.find('select[name="cat"]').first();

    var activeFiltersCount = 0;
    if ($searchInput.length && jQuery.trim($searchInput.val()) !== '') {
        activeFiltersCount++;
    }
    if ($monthSelect.length && String($monthSelect.val()) !== '0') {
        activeFiltersCount++;
    }
    if ($catSelect.length && String($catSelect.val()) !== '0') {
        activeFiltersCount++;
    }

    var hasFilters = activeFiltersCount > 0;

    if (!$searchBox.length && !$filterActions.length) {
        return;
    }

    var $card = jQuery('<div class="sc-wp-content-list-filters-card sc-reports-list-filters-card"></div>');
    if (hasFilters) {
        $card.addClass('is-open');
    }

    var $toolbar = jQuery(
        '<div class="sc-reports-list-filters-toolbar">'
        + '<button type="button" class="sc-reports-list-filters-toggle" id="sc-wp-content-filters-toggle" aria-expanded="' + (hasFilters ? 'true' : 'false') + '" aria-controls="sc-wp-content-filters-panel">'
        + '<span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">'
        + '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
        + '<path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
        + '</svg></span>'
        + '<span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">'
        + (hasFilters ? 'بستن فیلترها' : 'مشاهده فیلترها')
        + '</span>'
        + '<span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>'
        + '</button></div>'
    );

    if (activeFiltersCount > 0) {
        $toolbar.find('button').append(
            jQuery('<span class="sc-reports-list-filters-badge"></span>').text(String(activeFiltersCount))
        );
        $toolbar.append(
            jQuery('<a class="sc-reports-list-filters-clear" href="' + listUrl + '">پاک کردن فیلترها</a>')
        );
    }

    var $panel = jQuery('<div class="sc-reports-list-filters-panel sc-wp-content-list-filters-panel" id="sc-wp-content-filters-panel"></div>');
    if (!hasFilters) {
        $panel.attr('hidden', true);
    }

    var $grid = jQuery('<div class="sc-filter-grid sc-wp-content-filter-grid"></div>');

    if ($searchInput.length) {
        var $searchField = jQuery(
            '<div class="sc-filter-field sc-wp-content-search-field">'
            + '<label class="sc-filter-label" for="' + ($searchInput.attr('id') || 'post-search-input') + '">جستجو</label>'
            + '</div>'
        );
        $searchInput.addClass('sc-filter-control').attr('form', formId);
        $searchField.append($searchInput);
        $grid.append($searchField);
        $searchBox.remove();
    }

    if ($monthSelect.length) {
        var $monthField = jQuery(
            '<div class="sc-filter-field">'
            + '<label class="sc-filter-label" for="' + ($monthSelect.attr('id') || 'filter-by-date') + '">ماه</label>'
            + '</div>'
        );
        $monthSelect.addClass('sc-filter-control').attr('form', formId);
        $monthField.append($monthSelect);
        $grid.append($monthField);
    }

    if ($catSelect.length) {
        var $catField = jQuery(
            '<div class="sc-filter-field">'
            + '<label class="sc-filter-label" for="' + ($catSelect.attr('id') || 'cat') + '">دسته‌بندی</label>'
            + '</div>'
        );
        $catSelect.addClass('sc-filter-control').attr('form', formId);
        $catField.append($catSelect);
        $grid.append($catField);
    }

    $panel.append($grid);

    var $filterBtn = $filterActions.find('input[type="submit"], button[type="submit"]').first();
    var $actionsRow = jQuery('<div class="sc-reports-list-filters-actions"></div>');
    if ($filterBtn.length) {
        $filterBtn.addClass('button-primary').val('اعمال فیلتر').attr('form', formId);
        $actionsRow.append($filterBtn);
    } else {
        $actionsRow.append(
            jQuery('<input type="submit" class="button button-primary" value="اعمال فیلتر">').attr('form', formId)
        );
    }
    $panel.append($actionsRow);

    $card.append($toolbar).append($panel);
    $form.before($card);

    if ($filterActions.length) {
        $filterActions.remove();
    }

    var $toggle = $card.find('#sc-wp-content-filters-toggle');
    var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });
}

/**
 * صفحه پیوندهای یکتا — چیدمان مشابه لیست صورت‌حساب‌ها
 */
function scInitPermalinksAdminLayout() {
    var $body = jQuery('body');
    if (!$body.hasClass('sc-permalinks-admin-page')) {
        return;
    }

    var $wrap = jQuery('#wpbody-content > .wrap').first();
    if (!$wrap.length || $wrap.data('scPermalinksReady')) {
        return;
    }
    $wrap.data('scPermalinksReady', 1);
    $wrap.addClass('sc-reports-list-wrap sc-permalinks-wrap');

    var $form = $wrap.find('form#form').first();
    var $h1 = $wrap.children('h1').first();

    if ($h1.length && !$wrap.children('.sc-reports-list-header').length) {
        var $header = jQuery('<div class="sc-reports-list-header"></div>');
        var $text = jQuery('<div class="sc-reports-list-header-text"></div>');
        $h1.addClass('sc-reports-list-title');

        var $intro = $form.length ? $form.children('p').not('.submit').not('.description').first() : jQuery();
        $text.append($h1);
        if ($intro.length) {
            $intro.addClass('sc-reports-list-desc');
            $text.append($intro);
        } else {
            $text.append(
                jQuery('<p class="sc-reports-list-desc"></p>').text(
                    'ساختار آدرس صفحات و نوشته‌های سایت را تنظیم کنید.'
                )
            );
        }
        $header.append($text);
        $wrap.prepend($header);
    }

    $wrap.children('hr.wp-header-end').hide();

    if ($form.length) {
        if (!$form.parent().hasClass('sc-reports-list-table-card')) {
            $form.wrap('<div class="sc-reports-list-table-card sc-permalinks-form-card"></div>');
        }
        $form.addClass('sc-permalinks-form');
        $form.find('h2.title').addClass('sc-permalinks-section-title');
        $form.find('p.submit').addClass('sc-permalinks-submit');
        $form.find('.permalink-structure-optional-description').addClass('sc-permalinks-section-desc');
    }

    $wrap.children('form').not('#form').each(function () {
        var $extraForm = jQuery(this);
        if (!$extraForm.parent().hasClass('sc-reports-list-table-card')) {
            $extraForm.wrap('<div class="sc-reports-list-table-card sc-permalinks-extra-card"></div>');
        }
    });
}

/**
 * لیست کاربران وردپرس — چیدمان مشابه لیست بازیکن‌ها
 */
function scInitWpUsersListAdminLayout() {
    var $body = jQuery('body');
    if (!$body.hasClass('sc-wp-users-list-page') || !$body.hasClass('users-php')) {
        return;
    }

    var $wrap = jQuery('#wpbody-content > .wrap').first();
    if (!$wrap.length || $wrap.data('scWpUsersListReady')) {
        return;
    }
    $wrap.data('scWpUsersListReady', 1);
    $wrap.addClass('sc-members-list-wrap');

    var $h1 = $wrap.children('h1.wp-heading-inline').first();
    var $addBtn = $wrap.children('.page-title-action').first();
    if ($h1.length && !$wrap.children('.sc-members-list-header').length) {
        var $header = jQuery('<div class="sc-members-list-header"></div>');
        var $text = jQuery('<div class="sc-members-list-header-text"></div>');
        $h1.removeClass('wp-heading-inline').addClass('sc-members-list-title');
        $text.append($h1).append(
            jQuery('<p class="sc-members-list-desc"></p>').text(
                'مدیریت کاربران سایت. برای ویرایش روی نام کاربر کلیک کنید.'
            )
        );
        var $actions = jQuery('<div class="sc-members-list-header-actions"></div>');
        if ($addBtn.length) {
            $addBtn.addClass('sc-members-list-add-btn');
            $actions.append($addBtn);
        }
        $header.append($text).append($actions);
        $wrap.prepend($header);
        $wrap.children('hr.wp-header-end').remove();
    }

    var $form = $wrap.find('#users-filter').first();
    if (!$form.length) {
        return;
    }

    $form.addClass('sc-members-list-table-card');

    var $subsub = $wrap.children('.subsubsub').first();
    if ($subsub.length && !$form.children('.subsubsub').length) {
        $form.prepend($subsub.detach());
    }

    if ($form.find('.sc-members-list-filters-card').length) {
        return;
    }

    var $searchBox = $form.find('p.search-box').first();
    var $topNav = $form.find('.tablenav.top').first();
    var $filterActions = $topNav.find('.alignleft.actions').not('.bulkactions').first();
    var $searchInput = $searchBox.find('input[type="search"], input[name="s"]').first();
    var $roleSelect = $filterActions.find('select[name="role"]').first();
    var hasFilters = ($searchInput.length && jQuery.trim($searchInput.val()) !== '')
        || ($roleSelect.length && String($roleSelect.val()) !== '');

    if (!$searchBox.length && !$filterActions.length) {
        return;
    }

    var $card = jQuery('<div class="sc-members-list-filters-card"></div>');
    if (hasFilters) {
        $card.addClass('is-open');
    }

    var $toolbar = jQuery(
        '<div class="sc-members-list-filters-toolbar">'
        + '<button type="button" class="sc-members-list-filters-toggle" id="sc-wp-users-filters-toggle" aria-expanded="' + (hasFilters ? 'true' : 'false') + '" aria-controls="sc-wp-users-filters-panel">'
        + '<span class="sc-members-list-filters-toggle-icon" aria-hidden="true">'
        + '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
        + '<path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
        + '</svg></span>'
        + '<span class="sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">'
        + (hasFilters ? 'بستن فیلترها' : 'مشاهده فیلترها')
        + '</span>'
        + '<span class="sc-members-list-filters-chevron" aria-hidden="true"></span>'
        + '</button></div>'
    );

    var $panel = jQuery('<div class="sc-members-list-filters-panel" id="sc-wp-users-filters-panel"></div>');
    if (!hasFilters) {
        $panel.attr('hidden', true);
    }

    var $grid = jQuery('<div class="sc-filter-grid"></div>');

    if ($searchInput.length) {
        var $searchField = jQuery(
            '<div class="sc-filter-field">'
            + '<label class="sc-filter-label" for="' + ($searchInput.attr('id') || 'user-search-input') + '">جستجو</label>'
            + '</div>'
        );
        $searchInput.addClass('sc-filter-control');
        $searchField.append($searchInput);
        $grid.append($searchField);
        $searchBox.remove();
    }

    if ($roleSelect.length) {
        var $roleField = jQuery(
            '<div class="sc-filter-field">'
            + '<label class="sc-filter-label" for="' + ($roleSelect.attr('id') || 'filter-by-role') + '">نقش</label>'
            + '</div>'
        );
        $roleSelect.addClass('sc-filter-control');
        $roleField.append($roleSelect);
        $grid.append($roleField);
    }

    $panel.append($grid);

    var $filterBtn = $filterActions.find('input[type="submit"], button[type="submit"]').first();
    var $actionsRow = jQuery('<div class="sc-members-list-filters-actions"></div>');
    if ($filterBtn.length) {
        $filterBtn.addClass('button-primary').val('اعمال فیلتر');
        $actionsRow.append($filterBtn);
    } else {
        $actionsRow.append(jQuery('<input type="submit" class="button button-primary" value="اعمال فیلتر">'));
    }
    $panel.append($actionsRow);

    $card.append($toolbar).append($panel);
    $form.prepend($card);

    if ($filterActions.length) {
        $filterActions.remove();
    }

    var $toggle = $card.find('#sc-wp-users-filters-toggle');
    var $label = $toggle.find('.sc-members-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });
}

/**
 * ویرایش / افزودن کاربر وردپرس — چیدمان مشابه ویرایش بازیکن
 */
function scInitWpUserEditAdminLayout() {
    var $body = jQuery('body');
    if (!$body.hasClass('sc-wp-user-edit-page')) {
        return;
    }

    var $wrap = jQuery('#wpbody-content > .wrap').first();
    if (!$wrap.length || $wrap.data('scWpUserEditReady')) {
        return;
    }
    $wrap.data('scWpUserEditReady', 1);
    $wrap.addClass('sc-wp-user-edit-wrap');

    var isNew = $body.hasClass('user-new-php');
    var isProfile = $body.hasClass('profile-php');
    var desc = isNew
        ? 'اطلاعات کاربر جدید را وارد کنید و نقش مناسب را انتخاب کنید.'
        : (isProfile
            ? 'اطلاعات حساب کاربری خود را در این بخش ویرایش کنید.'
            : 'اطلاعات کاربر را ویرایش کنید. پس از ذخیره تغییرات اعمال می‌شوند.');

    var $h1 = $wrap.children('h1').first();
    if ($h1.length && !$wrap.children('.sc-wp-user-edit-header').length) {
        var $header = jQuery('<div class="sc-wp-user-edit-header"></div>');
        var $text = jQuery('<div class="sc-wp-user-edit-header-text"></div>');
        $h1.addClass('sc-wp-user-edit-title');
        $text.append($h1).append(
            jQuery('<p class="sc-wp-user-edit-desc"></p>').text(desc)
        );
        $header.append($text);
        $wrap.prepend($header);
        $wrap.children('hr.wp-header-end').hide();
    }

    jQuery('#your-profile, #createuser').each(function () {
        jQuery(this).find('table.form-table').addClass('sc_form-table');
    });

    jQuery('#your-profile, #createuser').find('p.submit').addClass('sc-wp-user-edit-submit');
}

/**
 * لیست حضور و غیاب — فیلتر تاشو در هر ۴ تب (مثل لیست صورت‌حساب‌ها)
 */
function scCountAttendanceListActiveFilters($form) {
    var count = 0;
    var $course = $form.find('[name="filter_course"]').first();
    if ($course.length && parseInt($course.val(), 10) > 0) {
        count++;
    }
    var $member = $form.find('[name="filter_member"]').first();
    if ($member.length && parseInt($member.val(), 10) > 0) {
        count++;
    }
    var $coach = $form.find('[name="filter_coach"]').first();
    if ($coach.length && parseInt($coach.val(), 10) > 0) {
        count++;
    }
    var $status = $form.find('[name="filter_status"]').first();
    if ($status.length && String($status.val()) !== '' && String($status.val()) !== 'all') {
        count++;
    }
    $form.find('input[name="filter_date_from"], input[name="filter_date_to"]').each(function () {
        if (jQuery.trim(jQuery(this).val()) !== '') {
            count++;
        }
    });
    return count;
}

function scInitAttendanceListCollapsibleFilters() {
    var $body = jQuery('body');
    if (!$body.is('[class*="_page_sc-attendance-list"]') && !$body.hasClass('sc-attendance-add_page_sc-attendance-list')) {
        return;
    }

    jQuery('.sc-attendance-list-body .form_fillter_attendance, [class*="_page_sc-attendance-list"] .form_fillter_attendance').each(function (formIndex) {
        var $form = jQuery(this);
        if ($form.data('scAttendanceFiltersReady') || $form.closest('.sc-attendance-list-filters-card').length) {
            return;
        }
        $form.data('scAttendanceFiltersReady', 1);

        var activeCount = scCountAttendanceListActiveFilters($form);
        var hasFilters = activeCount > 0;
        var tab = $form.find('input[name="tab"]').val() || ('tab' + formIndex);
        var panelId = 'sc-attendance-filters-panel-' + tab;
        var toggleId = 'sc-attendance-filters-toggle-' + tab;

        var $card = jQuery('<div class="sc-attendance-list-filters-card sc-reports-list-filters-card"></div>');
        if (hasFilters) {
            $card.addClass('is-open');
        }

        var $toolbar = jQuery(
            '<div class="sc-reports-list-filters-toolbar">'
            + '<button type="button" class="sc-reports-list-filters-toggle" id="' + toggleId + '" aria-expanded="' + (hasFilters ? 'true' : 'false') + '" aria-controls="' + panelId + '">'
            + '<span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">'
            + '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
            + '<path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            + '</svg></span>'
            + '<span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">'
            + (hasFilters ? 'بستن فیلترها' : 'مشاهده فیلترها')
            + '</span>'
            + '<span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>'
            + '</button></div>'
        );

        if (activeCount > 0) {
            $toolbar.find('button').append(
                jQuery('<span class="sc-reports-list-filters-badge"></span>').text(String(activeCount))
            );
        }

        var $clearLink = $form.find('a.delete_fillter').first();
        if ($clearLink.length && activeCount > 0) {
            $toolbar.append(
                $clearLink.clone().removeClass('button').addClass('sc-reports-list-filters-clear')
            );
        }

        $form.addClass('sc-reports-list-filters-panel');
        $form.attr('id', panelId);
        if (!hasFilters) {
            $form.attr('hidden', true);
        }

        var $submit = $form.find('p.submit').first();
        if ($submit.length) {
            $submit.addClass('sc-reports-list-filters-actions');
            $submit.find('input[type="submit"]').addClass('button-primary');
            if ($clearLink.length) {
                $clearLink.remove();
            }
        }

        $form.before($card);
        $card.append($toolbar).append($form);

        var $toggle = $card.find('#' + toggleId);
        var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
        $toggle.on('click', function () {
            var isOpen = $card.hasClass('is-open');
            if (isOpen) {
                $card.removeClass('is-open');
                $form.attr('hidden', true);
                $toggle.attr('aria-expanded', 'false');
                $label.text($label.data('label-closed'));
            } else {
                $card.addClass('is-open');
                $form.removeAttr('hidden');
                $toggle.attr('aria-expanded', 'true');
                $label.text($label.data('label-open'));
            }
        });
    });
}

jQuery(function () {
    scInitWpContentListAdminLayout();
    scInitPermalinksAdminLayout();
    scInitWpUsersListAdminLayout();
    scInitWpUserEditAdminLayout();
    scInitAttendanceListCollapsibleFilters();
});

