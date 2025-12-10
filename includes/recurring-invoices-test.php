<?php
/**
 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>


 * Recurring Invoices Test File
 * این فایل برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری استفاده می‌شود
 * 
 * دسترسی: فقط برای مدیران (manage_options)
 * 
 * استفاده:
 * 1. این فایل را در مرورگر باز کنید: /wp-content/plugins/AI sportclub/includes/recurring-invoices-test.php
 * 2. یا از طریق admin panel به آن دسترسی داشته باشید
 */

// بررسی دسترسی
if (!defined('ABSPATH')) {
    // اگر از طریق مرورگر مستقیم باز شده، WordPress را لود کن
    require_once('../../../wp-load.php');
}

// بررسی دسترسی مدیر
if (!current_user_can('manage_options')) {
    wp_die('شما اجازه دسترسی به این صفحه را ندارید.');
}

// لود کردن توابع افزونه
if (!function_exists('sc_check_and_create_tables')) {
    require_once(plugin_dir_path(__FILE__) . '../sportclub_manager.php');
}

sc_check_and_create_tables();

// دریافت تنظیمات
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش عملیات تست
$test_result = null;
$created_count = 0;
$errors = [];

if (isset($_GET['action']) && $_GET['action'] === 'create_recurring_invoices') {
    // فراخوانی تابع ایجاد صورت حساب‌های تکراری
    ob_start();
    sc_create_recurring_invoices();
    ob_end_clean();
    
    // بررسی نتایج
    $test_result = ['success' => true, 'message' => 'بررسی و اعمال صورت حساب‌های تکراری انجام شد. لطفاً نتایج را در لاگ بررسی کنید.'];
}

// دریافت دوره‌هایی که باید برای آن‌ها صورت حساب ایجاد شود
$courses_need_invoice = $wpdb->get_results(
    "SELECT mc.*, c.price, c.title as course_title, 
            m.first_name, m.last_name, m.id as member_id,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status = 'paid') as last_paid_invoice_date,
            (SELECT MAX(i.created_at) FROM $invoices_table i WHERE i.member_course_id = mc.id) as last_invoice_date,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id) as invoice_count,
            (SELECT COUNT(*) FROM $invoices_table i WHERE i.member_course_id = mc.id AND i.status IN ('pending', 'under_review')) as pending_invoice_count,
            (SELECT MAX(i.status) FROM $invoices_table i WHERE i.member_course_id = mc.id ORDER BY i.created_at DESC LIMIT 1) as last_invoice_status
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     INNER JOIN $members_table m ON mc.member_id = m.id
     WHERE mc.status = 'active'
     AND c.deleted_at IS NULL
     AND c.is_active = 1
     AND m.is_active = 1
     AND (
         mc.course_status_flags IS NULL
         OR mc.course_status_flags = ''
         OR (
             mc.course_status_flags NOT LIKE '%paused%'
             AND mc.course_status_flags NOT LIKE '%completed%'
             AND mc.course_status_flags NOT LIKE '%canceled%'
         )
     )
     ORDER BY last_paid_invoice_date ASC, mc.id ASC
     LIMIT 50"
);

// بررسی اینکه کدام دوره‌ها باید صورت حساب دریافت کنند
$courses_to_create = [];
foreach ($courses_need_invoice as $course) {
    $should_create = false;
    $reason = '';
    
    // بررسی اول: اگر صورت حساب pending یا under_review دارد، نباید صورت حساب جدید ایجاد شود
    if ($course->pending_invoice_count > 0) {
        $should_create = false;
        $reason = "⚠️ دارای $course->pending_invoice_count صورت حساب pending/under_review - باید ابتدا پرداخت شود";
    }
    // اگر هیچ صورت حسابی ندارد
    elseif ($course->invoice_count == 0) {
        $should_create = true;
        $reason = '✅ اولین صورت حساب';
    } 
    // بررسی زمان آخرین صورت حساب paid
    else {
        // فقط آخرین صورت حساب paid را بررسی می‌کنیم
        if ($course->last_paid_invoice_date) {
            $last_paid_invoice_time = strtotime($course->last_paid_invoice_date);
            $current_time = current_time('timestamp');
            $minutes_passed = floor(($current_time - $last_paid_invoice_time) / 60);
            
            if ($minutes_passed >= $invoice_interval_minutes) {
                $should_create = true;
                $hours_passed = floor($minutes_passed / 60);
                $days_passed = floor($hours_passed / 24);
                $reason = "✅ زمان گذشته: " . ($days_passed > 0 ? "$days_passed روز و " : "") . ($hours_passed % 24 > 0 ? ($hours_passed % 24) . " ساعت و " : "") . ($minutes_passed % 60) . " دقیقه از آخرین پرداخت";
            } else {
                $hours_remaining = floor(($invoice_interval_minutes - $minutes_passed) / 60);
                $days_remaining = floor($hours_remaining / 24);
                $reason = "⏳ زمان باقی‌مانده: " . ($days_remaining > 0 ? "$days_remaining روز و " : "") . ($hours_remaining % 24 > 0 ? ($hours_remaining % 24) . " ساعت و " : "") . (($invoice_interval_minutes - $minutes_passed) % 60) . " دقیقه تا صورت حساب بعدی";
            }
        } else {
            // اگر هیچ صورت حساب paid ندارد، بررسی می‌کنیم که آیا pending دارد یا نه
            if ($course->pending_invoice_count == 0) {
                $should_create = true;
                $reason = '✅ هیچ صورت حساب paid وجود ندارد و pending هم نیست';
            } else {
                $should_create = false;
                $reason = '⚠️ دارای صورت حساب pending - باید ابتدا پرداخت شود';
            }
        }
    }
    
    $courses_to_create[] = [
        'course' => $course,
        'should_create' => $should_create,
        'reason' => $reason
    ];
}

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست صورت حساب‌های تکراری - SportClub Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin: 20px 0 10px 0;
        }
        .info-box {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .info-item {
            margin: 8px 0;
            padding: 5px 0;
        }
        .info-item strong {
            color: #333;
            display: inline-block;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            font-size: 13px;
        }
        th, td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #46b450;
        }
        .btn-success:hover {
            background: #3a9b42;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-yes {
            background: #46b450;
            color: white;
        }
        .badge-no {
            background: #dc3232;
            color: white;
        }
        .badge-wait {
            background: #f0a000;
            color: white;
        }
        .time-info {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }
        .stats-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1;
            min-width: 200px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .stat-item h4 {
            color: #333;
            margin-bottom: 10px;
        }
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 تست صورت حساب‌های تکراری - SportClub Manager</h1>
        
        <?php if ($test_result): ?>
            <div class="alert alert-success">
                <?php echo esc_html($test_result['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>📊 وضعیت تنظیمات</h3>
            <div class="info-item">
                <strong>مدت زمان فاصله (دقیقه):</strong>
                <span><?php echo esc_html($invoice_interval_minutes); ?> دقیقه 
                (<?php echo esc_html(number_format($invoice_interval_minutes / 60, 2)); ?> ساعت)
                (<?php echo esc_html(number_format($invoice_interval_minutes / 1440, 2)); ?> روز)
                </span>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <a href="?action=create_recurring_invoices" 
               class="btn btn-success" 
               onclick="return confirm('آیا مطمئن هستید که می‌خواهید صورت حساب‌های تکراری را بررسی و اعمال کنید؟');">
                🔄 بررسی و اعمال صورت حساب‌های تکراری
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" class="btn">
                ⚙️ تنظیمات
            </a>
        </div>
        
        <?php
        $should_create_count = 0;
        $should_wait_count = 0;
        foreach ($courses_to_create as $item) {
            if ($item['should_create']) {
                $should_create_count++;
            } else {
                $should_wait_count++;
            }
        }
        ?>
        
        <div class="stats-box">
            <div class="stat-item">
                <h4>کل دوره‌های بررسی شده</h4>
                <div class="number"><?php echo count($courses_to_create); ?></div>
            </div>
            <div class="stat-item">
                <h4>نیاز به ایجاد صورت حساب</h4>
                <div class="number" style="color: #46b450;"><?php echo $should_create_count; ?></div>
            </div>
            <div class="stat-item">
                <h4>در انتظار زمان</h4>
                <div class="number" style="color: #f0a000;"><?php echo $should_wait_count; ?></div>
            </div>
        </div>
        
        <h2>📋 لیست دوره‌های فعال</h2>
        
        <?php if (empty($courses_to_create)): ?>
            <div class="alert alert-info">
                هیچ دوره فعالی یافت نشد که نیاز به بررسی داشته باشد.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>عضو</th>
                        <th>دوره</th>
                        <th>مبلغ</th>
                        <th>تعداد صورت حساب</th>
                        <th>آخرین صورت حساب</th>
                        <th>وضعیت</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses_to_create as $item): 
                        $course = $item['course'];
                        $member_name = $course->first_name . ' ' . $course->last_name;
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->id); ?></td>
                            <td><?php echo esc_html($member_name); ?></td>
                            <td><?php echo esc_html($course->course_title); ?></td>
                            <td><?php echo esc_html(number_format($course->price, 0)); ?> تومان</td>
                            <td><?php echo esc_html($course->invoice_count); ?></td>
                            <td>
                                <?php if ($course->last_paid_invoice_date): ?>
                                    <strong>Paid:</strong> <?php echo esc_html($course->last_paid_invoice_date); ?><br>
                                <?php endif; ?>
                                <?php if ($course->last_invoice_date && $course->last_invoice_date != $course->last_paid_invoice_date): ?>
                                    <span style="color: #f0a000;"><strong>Last:</strong> <?php echo esc_html($course->last_invoice_date); ?> (<?php echo esc_html($course->last_invoice_status); ?>)</span>
                                <?php elseif (!$course->last_paid_invoice_date && !$course->last_invoice_date): ?>
                                    <span style="color: #999;">ندارد</span>
                                <?php endif; ?>
                                <?php if ($course->pending_invoice_count > 0): ?>
                                    <br><span style="color: #dc3232;"><strong>⚠️ Pending:</strong> <?php echo esc_html($course->pending_invoice_count); ?> عدد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['should_create']): ?>
                                    <span class="badge badge-yes">✅ باید ایجاد شود</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">⏳ در انتظار</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($item['reason']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
            <h3>📝 راهنمای استفاده:</h3>
            <ul style="margin-right: 20px; line-height: 2;">
                <li>این صفحه برای تست و بررسی عملکرد سیستم صورت حساب‌های تکراری طراحی شده است.</li>
                <li>می‌توانید دوره‌های فعال را مشاهده کنید و ببینید کدام‌ها نیاز به ایجاد صورت حساب دارند.</li>
                <li>با کلیک بر روی دکمه "بررسی و اعمال"، سیستم تمام شرایط را بررسی می‌کند و صورت حساب‌های لازم را ایجاد می‌کند.</li>
                <li>سیستم فقط برای دوره‌هایی که زمان فاصله گذشته باشد، صورت حساب ایجاد می‌کند.</li>
                <li>برای تنظیمات بیشتر، به <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>">صفحه تنظیمات</a> بروید.</li>
            </ul>
        </div>
    </div>
</body>
</html>

