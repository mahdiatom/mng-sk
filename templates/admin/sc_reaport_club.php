<?php
if ( ! defined('ABSPATH') ) exit;
sc_check_and_create_tables();
function sc_get_profile_completion_stats() {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_members';

    // گرفتن همه ID ها
    $member_ids = $wpdb->get_col(
        "SELECT id FROM {$table}"
    );

    $completed = 0;
    $incomplete = 0;

    foreach ($member_ids as $member_id) {
        if (sc_check_profile_completed($member_id)) {
            $completed++;
        } else {
            $incomplete++;
        }
    }

    return [
        'completed'  => $completed,
        'incomplete' => $incomplete,
        'total'      => count($member_ids),
    ];
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';

// آمار کلی
//کاربران
$total_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table");
$active_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 1");
$inactive_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 0");
$Incomplete_profile = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 0");
$stats = sc_get_profile_completion_stats();


//========================================

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';

// ساخت WHERE clause برای دریافت اعضای بدهکار
$where_conditions = ['m.is_active = 1'];
$where_values = [];





$where_clause = implode(' AND ', $where_conditions);

// دریافت اعضا
$query = "SELECT id
          FROM $members_table m 
          WHERE $where_clause";

if (!empty($where_values)) {
    $members = $wpdb->get_results($wpdb->prepare($query, $where_values));
} else {
    $members = $wpdb->get_results($query);
}


//محاسبه بدهی هر فرد 

$debtors = [];
foreach ($members as $member) {
    // محاسبه کل مبلغ و تعداد صورت حساب‌های پرداخت نشده
    $debt_info = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(amount) as total_debt, COUNT(*) as debt_count 
         FROM $invoices_table 
         WHERE member_id = %d 
         AND status IN ('pending')",
        $member->id
    ));
    
    $debt_amount = $debt_info && $debt_info->total_debt ? floatval($debt_info->total_debt) : 0;
    $debt_count = $debt_info && $debt_info->debt_count ? intval($debt_info->debt_count) : 0;
    
    // فقط اگر بدهی داشته باشد، به لیست اضافه می‌کنیم
    if ($debt_amount > 0) {
        $member->debt_amount = $debt_amount;
        $member->debt_count = $debt_count;

        
        $debtors[] = $member;
    }
}
$count_debtor =0;
foreach ($debtors as $debtor){
 $count_debtor += 1;
}
//========================================

global $wpdb;
$invoices_table = $wpdb->prefix . 'sc_invoices';
$expenses_table = $wpdb->prefix . 'sc_expenses';



// محاسبه کل درآمد (صورت حساب‌های پرداخت شده)
$income_where_conditions = ["status IN ('completed', 'paid')"];
$income_where_values = [];


$income_where_clause = implode(' AND ', $income_where_conditions);
if (!empty($income_where_values)) {
    $total_income_query = $wpdb->prepare(
        "SELECT SUM(amount) as total FROM $invoices_table WHERE $income_where_clause",
        $income_where_values
    );
} else {
    $total_income_query = "SELECT SUM(amount) as total FROM $invoices_table WHERE $income_where_clause";
}
$total_income_result = $wpdb->get_var($total_income_query);
$total_income = $total_income_result ? floatval($total_income_result) : 0;

// محاسبه تعداد صورت حساب‌های پرداخت شده
if (!empty($income_where_values)) {
    $paid_invoices_count_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $invoices_table WHERE $income_where_clause",
        $income_where_values
    );
} else {
    $paid_invoices_count_query = "SELECT COUNT(*) FROM $invoices_table WHERE $income_where_clause";
}
$paid_invoices_count = $wpdb->get_var($paid_invoices_count_query) ?: 0;

// محاسبه کل هزینه‌ها
$expenses_where_conditions = ["1=1"];
$expenses_where_values = [];

$expenses_where_clause = implode(' AND ', $expenses_where_conditions);
if (!empty($expenses_where_values)) {
    $total_expenses_query = $wpdb->prepare(
        "SELECT SUM(amount) as total FROM $expenses_table WHERE $expenses_where_clause",
        $expenses_where_values
    );
} else {
    $total_expenses_query = "SELECT SUM(amount) as total FROM $expenses_table WHERE $expenses_where_clause";
}
$total_expenses_result = $wpdb->get_var($total_expenses_query);
$total_expenses = $total_expenses_result ? floatval($total_expenses_result) : 0;

// محاسبه سود
$profit = $total_income - $total_expenses;


//============================================
//بخش دوره ها 


$courses = $wpdb->get_var("SELECT COUNT(*) FROM $courses_table");
$courses_active = $wpdb->get_var("SELECT COUNT(*) FROM $courses_table WHERE  is_active = '1' ");
$courses_inactive = $wpdb->get_var("SELECT COUNT(*) FROM $courses_table WHERE  is_active = '0' ");

$courses_private = 0;
$course_column_names = $wpdb->get_col("SHOW COLUMNS FROM `$courses_table`");
if (is_array($course_column_names) && in_array('course_type', $course_column_names, true)) {
    $courses_private = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $courses_table WHERE deleted_at IS NULL AND course_type = 'private'"
    );
}

//============================================
//بخش رویداد ها 
$event_table = $wpdb->prefix . 'sc_events';
$events = $wpdb->get_var("SELECT COUNT(*) FROM $event_table");
$events_active = $wpdb->get_var("SELECT COUNT(*) FROM $event_table WHERE  is_active = '1' ");
$events_inactive = $wpdb->get_var("SELECT COUNT(*) FROM $event_table WHERE  is_active = '0' ");
$events_free = $wpdb->get_var("SELECT COUNT(*) FROM $event_table WHERE  price = '0' ");

// حضور و غیاب — مجموع رکوردها بدون محدودیت زمانی
$att_table             = $wpdb->prefix . 'sc_attendances';
$att_present_count     = 0;
$att_absent_count      = 0;
$att_excused_count     = 0;
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $att_table)) === $att_table) {
    $att_present_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$att_table` WHERE status = 'present'");
    $att_absent_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$att_table` WHERE status = 'absent'");
    $att_excused_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$att_table` WHERE status = 'excused'");
}

// پیامک — از جدول لاگ گزارش ارسال (sc_sms_log)
$sms_table                    = $wpdb->prefix . 'sc_sms_log';
$sms_sent_accepted            = 0;
$sms_delivered_count          = 0;
$sms_failed_or_not_delivered  = 0;
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sms_table)) === $sms_table) {
    $sms_sent_accepted   = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$sms_table` WHERE success = 1");
    $sms_delivered_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$sms_table` WHERE success = 1 AND delivery_state = %s",
        'رسیده به گوشی'
    ));
    $delivery_failed_states = ['لیست سیاه', 'ناموفق', 'نرسیده به گوشی', 'نرسیده به مخابرات'];
    $in_delivery_failed = "'" . implode("','", array_map('esc_sql', $delivery_failed_states)) . "'";
    $sms_delivery_failed = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM `$sms_table` WHERE success = 1 AND delivery_state IN ($in_delivery_failed)"
    );
    $sms_api_failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$sms_table` WHERE success = 0");
    $sms_failed_or_not_delivered = $sms_api_failed + $sms_delivery_failed;
}

// صورت‌حساب‌های پرداخت‌نشده (هم‌معنی با فیلترهای «در انتظار پرداخت» و «در حال بررسی» در لیست صورت‌حساب)
$unpaid_invoices_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM $invoices_table WHERE status IN ('pending', 'under_review', 'on-hold')"
);

?>

<div class="wrap">
<div class="title_head_btn_report">
<h3>کاربران</h3>
<a href="<?php echo admin_url('admin.php?page=sc-members'); ?>">مدیریت لیست کاربران</a>
</div>
<div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>کل کاربران </h3>
            <div>
                <?php echo $total_members; ?>
            </div>
          
        </div>
        <div class="sc-stat-box">
            <h3>کاربران فعال </h3>
            <div>
                <?php echo $active_members;?>
            </div>
        
        </div>
        
        <div class="sc-stat-box">
            <h3>کاربران غیرفعال</h3>
            <div>
                <?php echo $inactive_members; ?>
            </div>
          
        </div>
        
        
        
        <div class="sc-stat-box">
            <h3>کاربران پروفایل کامل</h3>
            <div>
                <?php echo $stats['completed']; ?>
            </div>
        </div>
        <div class="sc-stat-box">
            <h3>کاربران پروفایل ناقص</h3>
            <div>
                <?php echo $stats['incomplete']; ?>
            </div>
        </div>
    </div>
<div class="title_head_btn_report">
<h3>مالی و حسابداری  </h3>
<a href="<?php echo admin_url('admin.php?page=sc-reports-income-expenses'); ?>">مشاهده جزئیات مالی باشگاه</a>

</div>
 <div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>کل درآمد</h3>
            <div>
                <?php echo number_format($total_income, 0, '.', ','); ?> تومان
            </div>
        
        </div>
        
        <div class="sc-stat-box">
            <h3>کل هزینه‌ها</h3>
            <div>
                <?php echo number_format($total_expenses, 0, '.', ','); ?> تومان
            </div>
          
        </div>
        
        <div class="sc-stat-box">
            <h3>سود نهایی</h3>
            <div>
                <?php echo number_format($profit, 0, '.', ','); ?> تومان
            </div>
          
        </div>
        
        <div class="sc-stat-box">
            <h3>تعداد صورتحساب‌های پرداخت شده</h3>
            <div>
                <?php echo $paid_invoices_count; ?>
            </div>
        </div>
        <div class="sc-stat-box">
            <h3>تعداد بدهکاران </h3>
            <div>
                <?php echo $count_debtor; ?>
            </div>
        </div>
        <div class="sc-stat-box">
            <h3>صورت‌حساب‌های پرداخت‌نشده</h3>
            <div>
                <?php echo (int) $unpaid_invoices_count; ?>
            </div>
        </div>
    </div>

    <div class="title_head_btn_report">
<h3> دوره</h3>
        <a href="<?php echo admin_url('admin.php?page=sc-courses'); ?>">مدیریت لیست دوره ها </a>
</div>
 <div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>کل دوره ها</h3>
            <div>
                <?php echo $courses; ?> 
            </div>
        
        </div>
        
        <div class="sc-stat-box">
            <h3>دوره فعال </h3>
            <div>
                <?php echo $courses_active; ?> 
            </div>
          
        </div>
        
        <div class="sc-stat-box">
            <h3>دوره غیرفعال</h3>
            <div>
                <?php echo $courses_inactive; ?> 
            </div>
          
        </div>

        <div class="sc-stat-box">
            <h3>دوره خصوصی</h3>
            <div>
                <?php echo (int) $courses_private; ?>
            </div>
        </div>
        
        <div class="sc-stat-box">
            <h3>برترین دوره  - تعداد ثبت نامی</h3>
            <div>
                به زودی...
            </div>
        </div>
        
    </div>


    <div class="title_head_btn_report">
<h3>رویداد ها </h3>
        <a href="<?php echo admin_url('admin.php?page=sc-events'); ?>">مدیریت لیست رویداد ها </a>
    </div>
 <div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>کل رویداد ها</h3>
            <div>
                <?php echo $events; ?> 
            </div>
        
        </div>
        
        <div class="sc-stat-box">
            <h3>رویداد فعال </h3>
            <div>
                <?php echo $events_active; ?> 
            </div>
          
        </div>
        
        <div class="sc-stat-box">
            <h3>رویداد غیرفعال</h3>
            <div>
                <?php echo $events_inactive; ?> 
            </div>
          
        </div>
        
        <div class="sc-stat-box">
            <h3>رویداد های رایگان</h3>
            <div>
                <?php echo $events_free; ?>
        </div>
        </div>
        
    </div>

    <div class="title_head_btn_report">
        <h3>حضور و غیاب</h3>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-list_report')); ?>">لیست حضور و غیاب</a>
    </div>
    <div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>تعداد حضور</h3>
            <div><?php echo (int) $att_present_count; ?></div>
        </div>
        <div class="sc-stat-box">
            <h3>تعداد غیبت</h3>
            <div><?php echo (int) $att_absent_count; ?></div>
        </div>
        <div class="sc-stat-box">
            <h3>غیبت‌های مجاز</h3>
            <div><?php echo (int) $att_excused_count; ?></div>
        </div>
    </div>

    <div class="title_head_btn_report">
        <h3>پیامک‌های ارسال‌شده</h3>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-reports-sms-log')); ?>">گزارش و لاگ ارسال پیامک</a>
    </div>
    <div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>کل پیامک‌های ارسال‌شده</h3>
            <div><?php echo (int) $sms_sent_accepted; ?></div>
        </div>
        <div class="sc-stat-box">
            <h3>کل پیامک‌های تحویل‌داده‌شده</h3>
            <div><?php echo (int) $sms_delivered_count; ?></div>
        </div>
        <div class="sc-stat-box">
            <h3>پیامک‌های ناموفق و نرسیده</h3>
            <div><?php echo (int) $sms_failed_or_not_delivered; ?></div>
        </div>
    </div>
    <p class="description" style="margin-top:-8px;color:#646970;font-size:12px;">
        آمار پیامک بر اساس جدول لاگ است؛ «تحویل» پس از بررسی وضعیت از سامانه در همان گزارش به‌روز می‌شود. ردیف‌های بدون بررسی تحویل در این عدد آخر لحاظ نمی‌شوند.
    </p>
</div>