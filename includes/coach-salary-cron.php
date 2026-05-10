<?php
/**
 * Coach Salary Cron Jobs
 * وظایف زمان‌بندی شده برای محاسبه دستمزد مربی
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calculate fixed salary for all coaches on settlement day
 * محاسبه دستمزد ثابت برای همه مربی‌ها در روز تسویه مشخص‌شده
 */
function sc_calculate_coach_fixed_salaries_monthly() {
    // دریافت تاریخ امروز به شمسی
    $today = new DateTime();
    $today_jalali = gregorian_to_jalali(
        (int)$today->format('Y'),
        (int)$today->format('m'),
        (int)$today->format('d')
    );
    
    $year_shamsi = $today_jalali[0];
    $month_shamsi = (int)$today_jalali[1];
    $day_shamsi = (int)$today_jalali[2];
    
    $settlement_day = (int) (function_exists('sc_get_setting') ? sc_get_setting('coach_fixed_salary_settlement_day', '0') : 0);
    $last_day_of_month = jalali_days_in_month($month_shamsi, $year_shamsi);
    
    // روز هدف: اگر تنظیم شده باشد همان روز، وگرنه آخر ماه
    $target_day = ($settlement_day > 0) ? min($settlement_day, $last_day_of_month) : $last_day_of_month;
    
    if ($day_shamsi != $target_day) {
        return;
    }
    
    // بررسی اینکه قبلاً برای این ماه محاسبه نشده باشد
    $month_year_key = sprintf('coach_fixed_salary_%04d_%02d', $year_shamsi, $month_shamsi);
    $last_calculated = get_option($month_year_key);
    
    if ($last_calculated) {
        error_log("SC Coach Salary: Fixed salary for {$year_shamsi}/{$month_shamsi} already calculated");
        return;
    }
    
    error_log("SC Coach Salary: Starting fixed salary calculation for {$year_shamsi}/{$month_shamsi}");
    
    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    
    // دریافت همه مربی‌های فعال با دستمزد ثابت
    $coaches = $wpdb->get_results(
        "SELECT id FROM $coaches_table 
         WHERE settlement_type IN ('fixed', 'both') AND is_active = 1 AND settlement_amount > 0"
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($coaches as $coach) {
        $month_year_shamsi = sprintf('%04d/%02d', $year_shamsi, $month_shamsi);
        $result = sc_calculate_coach_fixed_salary($coach->id, $month_year_shamsi);
        
        if ($result['success']) {
            $success_count++;
            error_log("SC Coach Salary: Fixed salary calculated for coach ID {$coach->id} - Amount: {$result['salary_amount']}");
        } else {
            $error_count++;
            error_log("SC Coach Salary: Failed to calculate fixed salary for coach ID {$coach->id} - Error: {$result['message']}");
        }
    }
    
    // ذخیره اینکه این ماه محاسبه شده است
    update_option($month_year_key, current_time('mysql'));
    
    error_log("SC Coach Salary: Fixed salary calculation completed - Success: $success_count, Errors: $error_count");
}

/**
 * Get number of days in a Shamsi month
 * دریافت تعداد روزهای یک ماه شمسی
 */
function jalali_days_in_month($month, $year) {
    if ($month <= 6) {
        return 31;
    } elseif ($month <= 11) {
        return 30;
    } else {
        // ماه اسفند - سال کبیسه یا عادی
        if (is_jalali_leap_year($year)) {
            return 30;
        } else {
            return 29;
        }
    }
}

/**
 * Check if a Shamsi year is leap year
 * بررسی کبیسه بودن سال شمسی
 */
function is_jalali_leap_year($year) {
    $a = ($year + 2346) % 128;
    $b = ($a < 30) ? $a : (($a < 60) ? ($a - 1) : ($a - 2));
    return ($b < 29);
}

/**
 * Schedule cron job for coach fixed salary
 * زمان‌بندی cron job برای دستمزد ثابت مربی
 */
add_action('sc_daily_coach_salary_check', 'sc_calculate_coach_fixed_salaries_monthly');

/**
 * Register cron schedule if not exists
 */
if (!wp_next_scheduled('sc_daily_coach_salary_check')) {
    wp_schedule_event(time(), 'daily', 'sc_daily_coach_salary_check');
}
