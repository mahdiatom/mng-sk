<?php
/**
 * کتابخانه JDF برای تبدیل تاریخ میلادی به شمسی
 * نسخه بهبود یافته
 */

if (!function_exists('gregorian_to_jalali')) {
    /**
     * تبدیل تاریخ میلادی به شمسی
     * @param int $gy سال میلادی
     * @param int $gm ماه میلادی
     * @param int $gd روز میلادی
     * @return array [سال شمسی, ماه شمسی, روز شمسی]
     */
    function gregorian_to_jalali($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
        $jy += 33 * ((int)($days / 12053));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

if (!function_exists('jalali_to_gregorian')) {
    /**
     * تبدیل تاریخ شمسی به میلادی
     * @param int $jy سال شمسی
     * @param int $jm ماه شمسی
     * @param int $jd روز شمسی
     * @return array [سال میلادی, ماه میلادی, روز میلادی]
     */
    function jalali_to_gregorian($jy, $jm, $jd) {
        if ($jy > 979) {
            $gy = 1600;
            $jy -= 979;
        } else {
            $gy = 621;
        }
        $days = (365 * $jy) + ((int)($jy / 33)) * 8 + ((int)(($jy % 33 + 3) / 4)) + 78 + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy += 400 * ((int)($days / 146097));
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * ((int)(--$days / 36524));
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $gy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
        return [$gy, $gm, $gd];
    }
}

if (!function_exists('jdate')) {
    /**
     * فرمت کردن تاریخ شمسی (مشابه date در PHP)
     * @param string $format فرمت تاریخ (مثل 'Y/m/d H:i')
     * @param int|null $timestamp تایم‌استمپ (null = زمان فعلی)
     * @return string تاریخ فرمت شده
     */
    function jdate($format, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $date = getdate($timestamp);
        $jdate = gregorian_to_jalali($date['year'], $date['mon'], $date['mday']);
        
        $jy = $jdate[0];
        $jm = $jdate[1];
        $jd = $jdate[2];
        
        $hour = $date['hours'];
        $minute = $date['minutes'];
        $second = $date['seconds'];
        
        $weekday = $date['wday'];
        $month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $weekday_names = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
        
        // تبدیل فرمت به صورت صحیح (جلوگیری از جایگزینی چندباره)
        $result = $format;
        $result = str_replace('Y', $jy, $result);
        $result = str_replace('y', substr($jy, -2), $result);
        $result = str_replace('F', $month_names[$jm], $result);
        $result = str_replace('M', $month_names[$jm], $result);
        $result = str_replace('m', str_pad($jm, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('n', $jm, $result);
        $result = str_replace('d', str_pad($jd, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('j', $jd, $result);
        $result = str_replace('H', str_pad($hour, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('G', $hour, $result);
        $result = str_replace('i', str_pad($minute, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('s', str_pad($second, 2, '0', STR_PAD_LEFT), $result);
        $result = str_replace('l', $weekday_names[$weekday], $result);
        $result = str_replace('D', mb_substr($weekday_names[$weekday], 0, 1, 'UTF-8'), $result);
        
        return $result;
    }
}

if (!function_exists('sc_date_shamsi')) {
    /**
     * تابع helper برای تبدیل تاریخ میلادی به شمسی
     * @param string $date_string تاریخ میلادی (مثل '2024-01-15 10:30:00')
     * @param string $format فرمت خروجی (پیش‌فرض: 'Y/m/d H:i')
     * @return string تاریخ شمسی فرمت شده
     */
    function sc_date_shamsi($date_string, $format = 'Y/m/d H:i') {
        if (empty($date_string) || $date_string === '0000-00-00 00:00:00' || $date_string === '0000-00-00') {
            return '-';
        }
        
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return $date_string;
        }
        
        return jdate($format, $timestamp);
    }
}

if (!function_exists('sc_date_shamsi_date_only')) {
    /**
     * تبدیل فقط تاریخ (بدون ساعت) به شمسی
     * @param string $date_string تاریخ میلادی
     * @return string تاریخ شمسی (Y/m/d)
     */
    function sc_date_shamsi_date_only($date_string) {
        return sc_date_shamsi($date_string, 'Y/m/d');
    }
}

if (!function_exists('sc_date_shamsi_time_only')) {
    /**
     * تبدیل فقط ساعت به فرمت شمسی
     * @param string $date_string تاریخ میلادی
     * @return string ساعت (H:i)
     */
    function sc_date_shamsi_time_only($date_string) {
        return sc_date_shamsi($date_string, 'H:i');
    }
}

if (!function_exists('sc_calculate_age')) {
    /**
     * محاسبه سن بر اساس تاریخ تولد شمسی
     * @param string $birth_date_shamsi تاریخ تولد شمسی (مثل 1400/02/15)
     * @return string سن به صورت "XX سال"
     */
    function sc_calculate_age($birth_date_shamsi) {
        if (empty($birth_date_shamsi) || $birth_date_shamsi === '0000-00-00') {
            return '-';
        }
        
        // تبدیل تاریخ شمسی به آرایه
        $birth_parts = explode('/', $birth_date_shamsi);
        if (count($birth_parts) !== 3) {
            return '-';
        }
        
        $birth_year = (int)$birth_parts[0];
        $birth_month = (int)$birth_parts[1];
        $birth_day = (int)$birth_parts[2];
        
        // تاریخ امروز به شمسی
        $today_timestamp = time();
        $today_date = getdate($today_timestamp);
        $today_jalali = gregorian_to_jalali($today_date['year'], $today_date['mon'], $today_date['mday']);
        
        $current_year = $today_jalali[0];
        $current_month = $today_jalali[1];
        $current_day = $today_jalali[2];
        
        // محاسبه سن
        $age = $current_year - $birth_year;
        
        // اگر هنوز سالگرد تولد نرسیده، یک سال کم کن
        if ($current_month < $birth_month || ($current_month == $birth_month && $current_day < $birth_day)) {
            $age--;
        }
        
        if ($age < 0) {
            return '-';
        }
        
        return $age . ' سال';
    }
}

if (!function_exists('sc_shamsi_to_gregorian_date')) {
    /**
     * تبدیل تاریخ شمسی به میلادی برای استفاده در دیتابیس
     * @param string $shamsi_date تاریخ شمسی (مثل 1403/02/15)
     * @return string تاریخ میلادی (مثل 2024-05-04)
     */
    function sc_shamsi_to_gregorian_date($shamsi_date) {
        if (empty($shamsi_date) || $shamsi_date === '0000-00-00') {
            return '';
        }
        
        // تبدیل تاریخ شمسی به آرایه
        $parts = explode('/', $shamsi_date);
        if (count($parts) !== 3) {
            return '';
        }
        
        $jy = (int)$parts[0];
        $jm = (int)$parts[1];
        $jd = (int)$parts[2];
        
        $gregorian = jalali_to_gregorian($jy, $jm, $jd);
        
        return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    }
}

if (!function_exists('sc_compare_shamsi_dates')) {
    /**
     * مقایسه دو تاریخ شمسی
     * @param string $date1 تاریخ شمسی اول (مثل 1403/02/15)
     * @param string $date2 تاریخ شمسی دوم (مثل 1403/02/20)
     * @return int -1 اگر date1 < date2, 0 اگر برابر باشند, 1 اگر date1 > date2
     */
    function sc_compare_shamsi_dates($date1, $date2) {
        if (empty($date1) || empty($date2)) {
            return 0;
        }
        
        $parts1 = explode('/', $date1);
        $parts2 = explode('/', $date2);
        
        if (count($parts1) !== 3 || count($parts2) !== 3) {
            return 0;
        }
        
        $year1 = (int)$parts1[0];
        $month1 = (int)$parts1[1];
        $day1 = (int)$parts1[2];
        
        $year2 = (int)$parts2[0];
        $month2 = (int)$parts2[1];
        $day2 = (int)$parts2[2];
        
        if ($year1 < $year2) return -1;
        if ($year1 > $year2) return 1;
        
        if ($month1 < $month2) return -1;
        if ($month1 > $month2) return 1;
        
        if ($day1 < $day2) return -1;
        if ($day1 > $day2) return 1;
        
        return 0;
    }
}

if (!function_exists('sc_get_today_shamsi')) {
    /**
     * دریافت تاریخ امروز به صورت شمسی
     * @return string تاریخ امروز شمسی (مثل 1403/02/15)
     */
    function sc_get_today_shamsi() {
        $today_timestamp = time();
        $today_date = getdate($today_timestamp);
        $today_jalali = gregorian_to_jalali($today_date['year'], $today_date['mon'], $today_date['mday']);
        
        return $today_jalali[0] . '/' . 
               ($today_jalali[1] < 10 ? '0' . $today_jalali[1] : $today_jalali[1]) . '/' . 
               ($today_jalali[2] < 10 ? '0' . $today_jalali[2] : $today_jalali[2]);
    }
}

