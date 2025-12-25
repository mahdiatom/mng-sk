/**
 * Persian DatePicker - تقویم شمسی کامل با امکان انتخاب سال و ماه
 */
(function($) {
    'use strict';
    
    // تابع تبدیل تاریخ میلادی به شمسی
    function gregorianToJalali(gy, gm, gd) {
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var jy = (gy <= 1600) ? 0 : 979;
        var gy2 = (gy <= 1600) ? gy - 621 : gy - 1600;
        var days = (365 * gy) + (parseInt((gy2 + 3) / 4)) - (parseInt((gy2 + 99) / 100)) + 
                   (parseInt((gy2 + 399) / 400)) - 80 + gd + g_d_m[gm - 1];
        jy += 33 * (parseInt(days / 12053));
        days = days % 12053;
        jy += 4 * (parseInt(days / 1461));
        days = days % 1461;
        if (days > 365) {
            jy += parseInt((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
        var jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
        return [jy, jm, jd];
    }
    
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
    
    // نام ماه‌های شمسی
    var monthNames = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 
                      'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    
    // نام روزهای هفته
    var weekdayNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    
    // تعداد روزهای هر ماه
    function getDaysInMonth(year, month) {
        if (month <= 6) return 31;
        if (month <= 11) return 30;
        return isLeapYear(year) ? 30 : 29;
    }
    
    // بررسی سال کبیسه
    function isLeapYear(year) {
        var ary = [1, 5, 9, 13, 17, 22, 26, 30];
        var b = year % 33;
        return ary.indexOf(b) !== -1;
    }
    
    // دریافت تاریخ امروز به شمسی
    function getTodayJalali() {
        var today = new Date();
        return gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());
    }
    
    // ایجاد تقویم
    function initPersianDatePicker() {
        $('.persian-date-input').each(function() {
            var $input = $(this);
            
            // اگر مقدار پیش‌فرض خالی است، تاریخ امروز را بگذار
            if (!$input.val() || $input.val() === '') {
                var today = getTodayJalali();
                var todayStr = today[0] + '/' + 
                              (today[1] < 10 ? '0' + today[1] : today[1]) + '/' + 
                              (today[2] < 10 ? '0' + today[2] : today[2]);
                $input.val(todayStr);
            }
            
            // رویداد کلیک
            $input.off('click.persian-cal').on('click.persian-cal', function(e) {
                e.preventDefault();
                showCalendar($input);
            });
            
            // غیرفعال کردن تایپ مستقیم
            $input.attr('readonly', 'readonly');
        });
    }
    
    // نمایش تقویم
    function showCalendar($input) {
        // حذف تقویم قبلی
        $('.persian-calendar-popup').remove();
        $(document).off('click.persian-cal-close');
        
        // دریافت تاریخ امروز به عنوان پیش‌فرض
        var currentDate = getTodayJalali();
        var selectedYear = currentDate[0];
        var selectedMonth = currentDate[1];
        var selectedDay = currentDate[2];
        
        // اگر مقدار موجود بود، از آن استفاده کن
        if ($input.val() && $input.val().trim() !== '') {
            var parts = $input.val().split('/');
            if (parts.length === 3) {
                var inputYear = parseInt(parts[0]);
                var inputMonth = parseInt(parts[1]);
                var inputDay = parseInt(parts[2]);
                // بررسی معتبر بودن تاریخ
                if (!isNaN(inputYear) && !isNaN(inputMonth) && !isNaN(inputDay) && 
                    inputYear > 0 && inputMonth >= 1 && inputMonth <= 12 && inputDay >= 1 && inputDay <= 31) {
                    selectedYear = inputYear;
                    selectedMonth = inputMonth;
                    selectedDay = inputDay;
                }
            }
        }
        
        // ایجاد HTML تقویم
        var calendarHTML = createCalendarHTML(selectedYear, selectedMonth, selectedDay);
        
        // ایجاد popup
        var $popup = $('<div class="persian-calendar-popup"></div>');
        $popup.html(calendarHTML);
        
        // موقعیت تقویم
        var inputOffset = $input.offset();
        var inputHeight = $input.outerHeight();
        var popupWidth = 350;
        
        $popup.css({
            position: 'absolute',
            top: (inputOffset.top + inputHeight + 5) + 'px',
            left: inputOffset.left + 'px',
            zIndex: 10000,
            background: '#fff',
            border: '2px solid #2271b1',
            borderRadius: '8px',
            padding: '15px',
            boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
            fontFamily: 'Tahoma, Arial',
            direction: 'rtl',
            minWidth: 'fit-content'
        });
        
        $('body').append($popup);
        
        // بایند رویدادها
        bindCalendarEvents($popup, $input, selectedYear, selectedMonth, selectedDay);
        
        // بستن با کلیک خارج
        setTimeout(function() {
            $(document).on('click.persian-cal-close', function(e) {
                if (!$(e.target).closest('.persian-calendar-popup, .persian-date-input').length) {
                    $('.persian-calendar-popup').remove();
                    $(document).off('click.persian-cal-close');
                }
            });
        }, 100);
    }
    
    // ایجاد HTML تقویم
    function createCalendarHTML(year, month, selectedDay) {
        var daysInMonth = getDaysInMonth(year, month);
        var monthName = monthNames[month];
        
        // پیدا کردن اولین روز هفته
        var firstDayGregorian = jalaliToGregorian(year, month, 1);
        var firstDate = new Date(firstDayGregorian[0], firstDayGregorian[1] - 1, firstDayGregorian[2]);
        var firstDayOfWeek = firstDate.getDay();
        var firstDayPersian = (firstDayOfWeek + 2) % 7;
        
        // ایجاد dropdown برای سال (1350 تا 1410)
        var yearOptions = '';
        for (var y = 1350; y <= 1410; y++) {
            yearOptions += '<option value="' + y + '"' + (y == year ? ' selected' : '') + '>' + y + '</option>';
        }
        
        // ایجاد dropdown برای ماه
        var monthOptions = '';
        for (var m = 1; m <= 12; m++) {
            monthOptions += '<option value="' + m + '"' + (m == month ? ' selected' : '') + '>' + monthNames[m] + '</option>';
        }
        
        var html = '<div class="calendar-header" style="margin-bottom: 15px;">';
        
        // ردیف اول: dropdown های سال و ماه
        html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px;">';
        html += '<div style="flex: 1;">';
        html += '<label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666;">سال:</label>';
        html += '<select class="calendar-year-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">' + yearOptions + '</select>';
        html += '</div>';
        html += '<div style="flex: 1;">';
        html += '<label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666;">ماه:</label>';
        html += '<select class="calendar-month-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">' + monthOptions + '</select>';
        html += '</div>';
        html += '</div>';
        
        // ردیف دوم: دکمه‌های ناوبری
        html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #f5f5f5; border-radius: 5px;">';
        html += '<button type="button" class="prev-month-btn" style="background: #2271b1; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">« ماه قبل</button>';
        html += '<div style="font-weight: bold; font-size: 15px; color: #333;">' + monthName + ' ' + year + '</div>';
        html += '<button type="button" class="next-month-btn" style="background: #2271b1; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">ماه بعد »</button>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="calendar-weekdays" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-bottom: 8px; margin-top: 10px;">';
        weekdayNames.forEach(function(day) {
            html += '<div style="text-align: center; font-weight: bold; padding: 8px; background: #e5f5fa; color: #2271b1; border-radius: 4px; font-size: 13px;">' + day + '</div>';
        });
        html += '</div>';
        
        html += '<div class="calendar-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px;">';
        
        // روزهای خالی قبل از ماه
        for (var i = 0; i < firstDayPersian; i++) {
            html += '<div style="padding: 8px;"></div>';
        }
        
        // روزهای ماه
        for (var day = 1; day <= daysInMonth; day++) {
            var isSelected = (day == selectedDay);
            var dayStyle = 'text-align: center; padding: 10px; cursor: pointer; border-radius: 4px; font-size: 14px; transition: all 0.2s;';
            if (isSelected) {
                dayStyle += 'background: #2271b1; color: #fff; font-weight: bold;';
            } else {
                dayStyle += 'background: #f9f9f9; color: #333;';
            }
            html += '<div class="calendar-day" data-day="' + day + '" style="' + dayStyle + '">' + day + '</div>';
        }
        
        html += '</div>';
        
        return html;
    }
    
    // بایند رویدادها
    function bindCalendarEvents($popup, $input, year, month, selectedDay) {
        // کلیک روی روز
        $popup.find('.calendar-day').on('click', function() {
            var day = parseInt($(this).data('day'));
            var formattedDate = year + '/' + 
                              (month < 10 ? '0' + month : month) + '/' + 
                              (day < 10 ? '0' + day : day);
            $input.val(formattedDate);
            $('.persian-calendar-popup').remove();
            $(document).off('click.persian-cal-close');
            $input.trigger('change');
        });
        
        // تغییر سال
        $popup.find('.calendar-year-select').on('change', function(e) {
            e.stopPropagation();
            var newYear = parseInt($(this).val());
            var newMonth = parseInt($popup.find('.calendar-month-select').val());
            var currentDay = selectedDay || 1;
            $popup.html(createCalendarHTML(newYear, newMonth, currentDay));
            bindCalendarEvents($popup, $input, newYear, newMonth, currentDay);
        });
        
        // تغییر ماه
        $popup.find('.calendar-month-select').on('change', function(e) {
            e.stopPropagation();
            var newYear = parseInt($popup.find('.calendar-year-select').val());
            var newMonth = parseInt($(this).val());
            var currentDay = selectedDay || 1;
            // بررسی تعداد روزهای ماه جدید
            var daysInNewMonth = getDaysInMonth(newYear, newMonth);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createCalendarHTML(newYear, newMonth, currentDay));
            bindCalendarEvents($popup, $input, newYear, newMonth, currentDay);
        });
        
        // دکمه ماه قبل
        $popup.find('.prev-month-btn').on('click', function(e) {
            e.stopPropagation();
            month--;
            if (month < 1) {
                month = 12;
                year--;
            }
            var currentDay = selectedDay || 1;
            var daysInNewMonth = getDaysInMonth(year, month);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createCalendarHTML(year, month, currentDay));
            bindCalendarEvents($popup, $input, year, month, currentDay);
        });
        
        // دکمه ماه بعد
        $popup.find('.next-month-btn').on('click', function(e) {
            e.stopPropagation();
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
            var currentDay = selectedDay || 1;
            var daysInNewMonth = getDaysInMonth(year, month);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createCalendarHTML(year, month, currentDay));
            bindCalendarEvents($popup, $input, year, month, currentDay);
        });
        
        // هاور روی روزها
        $popup.find('.calendar-day').hover(
            function() {
                if (!$(this).hasClass('selected')) {
                    $(this).css('background', '#e5f5fa');
                }
            },
            function() {
                if (!$(this).hasClass('selected')) {
                    $(this).css('background', '#f9f9f9');
                }
            }
        );
    }
    
    // راه‌اندازی
    $(document).ready(function() {
        initPersianDatePicker();
    });
    
    // برای صفحاتی که محتوا بعداً لود می‌شود
    $(document).on('DOMContentLoaded', function() {
        initPersianDatePicker();
    });
    
    // برای آیتم‌های جدید که بعداً اضافه می‌شوند
    $(document).on('DOMNodeInserted', '.persian-date-input', function() {
        initPersianDatePicker();
    });
    
    // ============================================
    // DatePicker میلادی با همان استایل
    // ============================================
    
    // نام ماه‌های میلادی
    var gregorianMonthNames = ['', 'ژانویه', 'فوریه', 'مارس', 'آوریل', 'می', 'ژوئن', 
                               'جولای', 'آگوست', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'];
    
    // تعداد روزهای هر ماه میلادی
    function getDaysInGregorianMonth(year, month) {
        var daysInMonth = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        // بررسی سال کبیسه
        if (month === 2 && ((year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0))) {
            return 29;
        }
        return daysInMonth[month];
    }
    
    // دریافت تاریخ امروز به میلادی
    function getTodayGregorian() {
        var today = new Date();
        return [today.getFullYear(), today.getMonth() + 1, today.getDate()];
    }
    
    // ایجاد تقویم میلادی
    function initGregorianDatePicker() {
        $('.gregorian-date-input').each(function() {
            var $input = $(this);
            
            // اگر مقدار پیش‌فرض خالی است، تاریخ امروز را بگذار
            if (!$input.val() || $input.val() === '') {
                var today = getTodayGregorian();
                var todayStr = today[0] + '/' + 
                              (today[1] < 10 ? '0' + today[1] : today[1]) + '/' + 
                              (today[2] < 10 ? '0' + today[2] : today[2]);
                $input.val(todayStr);
            }
            
            // رویداد کلیک
            $input.off('click.gregorian-cal').on('click.gregorian-cal', function(e) {
                e.preventDefault();
                showGregorianCalendar($input);
            });
            
            // غیرفعال کردن تایپ مستقیم
            $input.attr('readonly', 'readonly');
        });
    }
    
    // نمایش تقویم میلادی
    function showGregorianCalendar($input) {
        // حذف تقویم قبلی
        $('.gregorian-calendar-popup').remove();
        $(document).off('click.gregorian-cal-close');
        
        // دریافت تاریخ امروز به عنوان پیش‌فرض
        var currentDate = getTodayGregorian();
        var selectedYear = currentDate[0];
        var selectedMonth = currentDate[1];
        var selectedDay = currentDate[2];
        
        // اگر مقدار موجود بود، از آن استفاده کن
        if ($input.val() && $input.val().trim() !== '') {
            var parts = $input.val().split('/');
            if (parts.length === 3) {
                var inputYear = parseInt(parts[0]);
                var inputMonth = parseInt(parts[1]);
                var inputDay = parseInt(parts[2]);
                // بررسی معتبر بودن تاریخ
                if (!isNaN(inputYear) && !isNaN(inputMonth) && !isNaN(inputDay) && 
                    inputYear > 0 && inputMonth >= 1 && inputMonth <= 12 && inputDay >= 1 && inputDay <= 31) {
                    selectedYear = inputYear;
                    selectedMonth = inputMonth;
                    selectedDay = inputDay;
                }
            }
        }
        
        // ایجاد HTML تقویم
        var calendarHTML = createGregorianCalendarHTML(selectedYear, selectedMonth, selectedDay);
        
        // ایجاد popup
        var $popup = $('<div class="gregorian-calendar-popup"></div>');
        $popup.html(calendarHTML);
        
        // موقعیت تقویم
        var inputOffset = $input.offset();
        var inputHeight = $input.outerHeight();
        var popupWidth = 350;
        
        $popup.css({
            position: 'absolute',
            top: (inputOffset.top + inputHeight + 5) + 'px',
            left: inputOffset.left + 'px',
            zIndex: 10000,
            background: '#fff',
            border: '2px solid #2271b1',
            borderRadius: '8px',
            padding: '15px',
            boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
            direction: 'rtl',
            minWidth: popupWidth + 'px'
        });
        
        $('body').append($popup);
        
        // بایند رویدادها
        bindGregorianCalendarEvents($popup, $input, selectedYear, selectedMonth, selectedDay);
        
        // بستن با کلیک خارج
        setTimeout(function() {
            $(document).on('click.gregorian-cal-close', function(e) {
                if (!$(e.target).closest('.gregorian-calendar-popup, .gregorian-date-input').length) {
                    $('.gregorian-calendar-popup').remove();
                    $(document).off('click.gregorian-cal-close');
                }
            });
        }, 100);
    }
    
    // ایجاد HTML تقویم میلادی
    function createGregorianCalendarHTML(year, month, selectedDay) {
        var daysInMonth = getDaysInGregorianMonth(year, month);
        var monthName = gregorianMonthNames[month];
        
        // پیدا کردن اولین روز هفته
        var firstDate = new Date(year, month - 1, 1);
        var firstDayOfWeek = firstDate.getDay();
        var firstDayPersian = (firstDayOfWeek + 2) % 7;
        
        // ایجاد dropdown برای سال (1910 تا 2030) - فقط برای تقویم میلادی
        var yearOptions = '';
        for (var y = 1910; y <= 2030; y++) {
            yearOptions += '<option value="' + y + '"' + (y == year ? ' selected' : '') + '>' + y + '</option>';
        }
        
        // ایجاد dropdown برای ماه (میلادی)
        var monthOptions = '';
        for (var m = 1; m <= 12; m++) {
            monthOptions += '<option value="' + m + '"' + (m == month ? ' selected' : '') + '>' + gregorianMonthNames[m] + '</option>';
        }
        
        var html = '<div class="calendar-header" style="margin-bottom: 15px;">';
        
        // ردیف اول: dropdown های سال و ماه
        html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px;">';
        html += '<div style="flex: 1;">';
        html += '<label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666;">سال:</label>';
        html += '<select class="calendar-year-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">' + yearOptions + '</select>';
        html += '</div>';
        html += '<div style="flex: 1;">';
        html += '<label style="display: block; margin-bottom: 5px; font-size: 12px; color: #666;">ماه:</label>';
        html += '<select class="calendar-month-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">' + monthOptions + '</select>';
        html += '</div>';
        html += '</div>';
        
        // ردیف دوم: دکمه‌های ناوبری
        html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #f5f5f5; border-radius: 5px;">';
        html += '<button type="button" class="prev-month-btn" style="background: #2271b1; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">« ماه قبل</button>';
        html += '<div style="font-weight: bold; font-size: 15px; color: #333;">' + monthName + ' ' + year + '</div>';
        html += '<button type="button" class="next-month-btn" style="background: #2271b1; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">ماه بعد »</button>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="calendar-weekdays" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; margin-bottom: 8px; margin-top: 10px;">';
        weekdayNames.forEach(function(day) {
            html += '<div style="text-align: center; font-weight: bold; padding: 8px; background: #e5f5fa; color: #2271b1; border-radius: 4px; font-size: 13px;">' + day + '</div>';
        });
        html += '</div>';
        
        html += '<div class="calendar-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px;">';
        
        // روزهای خالی قبل از ماه
        for (var i = 0; i < firstDayPersian; i++) {
            html += '<div style="padding: 8px;"></div>';
        }
        
        // روزهای ماه
        for (var day = 1; day <= daysInMonth; day++) {
            var isSelected = (day == selectedDay);
            var dayStyle = 'text-align: center; padding: 10px; cursor: pointer; border-radius: 4px; font-size: 14px; transition: all 0.2s;';
            if (isSelected) {
                dayStyle += 'background: #2271b1; color: #fff; font-weight: bold;';
            } else {
                dayStyle += 'background: #f9f9f9; color: #333;';
            }
            html += '<div class="calendar-day" data-day="' + day + '" style="' + dayStyle + '">' + day + '</div>';
        }
        
        html += '</div>';
        
        return html;
    }
    
    // بایند رویدادهای تقویم میلادی
    function bindGregorianCalendarEvents($popup, $input, year, month, selectedDay) {
        // کلیک روی روز
        $popup.find('.calendar-day').on('click', function() {
            var day = parseInt($(this).data('day'));
            var formattedDate = year + '/' + 
                              (month < 10 ? '0' + month : month) + '/' + 
                              (day < 10 ? '0' + day : day);
            $input.val(formattedDate);
            $('.gregorian-calendar-popup').remove();
            $(document).off('click.gregorian-cal-close');
            $input.trigger('change');
        });
        
        // تغییر سال
        $popup.find('.calendar-year-select').on('change', function(e) {
            e.stopPropagation();
            var newYear = parseInt($(this).val());
            var newMonth = parseInt($popup.find('.calendar-month-select').val());
            var currentDay = selectedDay || 1;
            var daysInNewMonth = getDaysInGregorianMonth(newYear, newMonth);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createGregorianCalendarHTML(newYear, newMonth, currentDay));
            bindGregorianCalendarEvents($popup, $input, newYear, newMonth, currentDay);
        });
        
        // تغییر ماه
        $popup.find('.calendar-month-select').on('change', function(e) {
            e.stopPropagation();
            var newYear = parseInt($popup.find('.calendar-year-select').val());
            var newMonth = parseInt($(this).val());
            var currentDay = selectedDay || 1;
            var daysInNewMonth = getDaysInGregorianMonth(newYear, newMonth);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createGregorianCalendarHTML(newYear, newMonth, currentDay));
            bindGregorianCalendarEvents($popup, $input, newYear, newMonth, currentDay);
        });
        
        // دکمه ماه قبل
        $popup.find('.prev-month-btn').on('click', function(e) {
            e.stopPropagation();
            month--;
            if (month < 1) {
                month = 12;
                year--;
            }
            var currentDay = selectedDay || 1;
            var daysInNewMonth = getDaysInGregorianMonth(year, month);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createGregorianCalendarHTML(year, month, currentDay));
            bindGregorianCalendarEvents($popup, $input, year, month, currentDay);
        });
        
        // دکمه ماه بعد
        $popup.find('.next-month-btn').on('click', function(e) {
            e.stopPropagation();
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
            var currentDay = selectedDay || 1;
            var daysInNewMonth = getDaysInGregorianMonth(year, month);
            if (currentDay > daysInNewMonth) {
                currentDay = daysInNewMonth;
            }
            $popup.html(createGregorianCalendarHTML(year, month, currentDay));
            bindGregorianCalendarEvents($popup, $input, year, month, currentDay);
        });
        
        // هاور روی روزها
        $popup.find('.calendar-day').hover(
            function() {
                if (!$(this).hasClass('selected')) {
                    $(this).css('background', '#e5f5fa');
                }
            },
            function() {
                if (!$(this).hasClass('selected')) {
                    $(this).css('background', '#f9f9f9');
                }
            }
        );
    }
    
    // راه‌اندازی datepicker میلادی
    $(document).ready(function() {
        initGregorianDatePicker();
    });
    
    // برای آیتم‌های جدید که بعداً اضافه می‌شوند
    $(document).on('DOMNodeInserted', '.gregorian-date-input', function() {
        initGregorianDatePicker();
    });
    
})(jQuery);