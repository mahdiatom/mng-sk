<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// دریافت متغیرهای فیلتر و صفحه‌بندی (اگر از my-account.php فراخوانی شده باشد)
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all');
$current_page = isset($current_page) ? $current_page : (isset($_GET['pag']) ? absint($_GET['pag']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
$total_courses = isset($total_courses) ? $total_courses : 0;
?>

<div class="sc-my-courses-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">📚</span>
        دوره‌های من
    </h2>

    <?php
    if (!isset($sc_weekly_schedule_matrix) || !is_array($sc_weekly_schedule_matrix)) {
        $sc_weekly_schedule_matrix = ['days' => [], 'cells' => []];
    }
    $ws_days = isset($sc_weekly_schedule_matrix['days']) && is_array($sc_weekly_schedule_matrix['days']) ? $sc_weekly_schedule_matrix['days'] : [];
    $ws_cells = isset($sc_weekly_schedule_matrix['cells']) && is_array($sc_weekly_schedule_matrix['cells']) ? $sc_weekly_schedule_matrix['cells'] : [];
    $ws_has_any = false;
    foreach (range(1, 7) as $d) {
        if (!empty($ws_cells[$d])) {
            $ws_has_any = true;
            break;
        }
    }
    ?>
    <div class="sc-weekly-schedule-card" style="margin-bottom: 28px; padding: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
        <h3 style="margin: 0 0 14px; font-size: 18px; font-weight: 700; color: #1a1a1a;">📅 برنامه هفتگی کلاس‌ها</h3>
        <?php if (!$ws_has_any) : ?>
            <p style="margin:0;color:#666;font-size:14px;">هنوز برای دوره‌های شما زمان ثابت هفتگی تعریف نشده است. پس از تعریف توسط باشگاه، اینجا نمایش داده می‌شود.</p>
        <?php else : ?>
            <div style="overflow-x:auto;">
                <table class="sc-weekly-schedule-table" style="width:100%; min-width:640px; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr>
                            <?php foreach ($ws_days as $num => $lab) : ?>
                                <th style="padding:10px 8px; background:#2271b1; color:#fff; text-align:center; border:1px solid #1e5a96; font-weight:600;">
                                    <?php echo esc_html($lab); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach (range(1, 7) as $d) : ?>
                                <td style="vertical-align:top; padding:10px 8px; border:1px solid #ddd; background:#fafafa; min-height:80px;">
                                    <?php if (empty($ws_cells[$d])) : ?>
                                        <span style="color:#bbb;">—</span>
                                    <?php else : ?>
                                        <?php foreach ($ws_cells[$d] as $slot) : ?>
                                            <div style="margin-bottom:10px; padding:10px; background:#eef6ff; border-radius:8px; border-right:3px solid #2271b1;">
                                                <div style="font-weight:700; color:#2271b1; margin-bottom:4px;">
                                                    <?php echo esc_html($slot['start'] ?? ''); ?> – <?php echo esc_html($slot['end'] ?? ''); ?>
                                                </div>
                                                <div style="color:#333; line-height:1.4;"><?php echo esc_html($slot['title'] ?? ''); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- فیلتر وضعیت -->
    <div class="sc-my-courses-filters" style="margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-my-courses')); ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="pag" value="1">
            
            <div style="flex: 1; min-width: 200px;">
                <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                <select name="filter_status" id="filter_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="active" <?php selected($filter_status, 'active'); ?>>ثبت نام شده و در حال پرداخت</option>
                    <option value="canceled" <?php selected($filter_status, 'canceled'); ?>>لغو شده</option>
                    <option value="paused" <?php selected($filter_status, 'paused'); ?>>متوقف شده</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>تمام شده</option>
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary" style="padding: 8px 20px; height: auto;">اعمال فیلتر</button>
            </div>
        </form>
    </div>
    
    <?php if (empty($user_courses)) : ?>
        <div class="sc-message sc-message-info" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <?php if ($filter_status !== 'all') : ?>
                دوره‌ای با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز در هیچ دوره‌ای ثبت‌نام نکرده‌اید.
            <?php endif; ?>
        </div>
    <?php else : ?>
    
    <!-- نمایش دوره‌ها به صورت کارت -->
    <div class="sc-my-courses-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <?php foreach ($user_courses as $user_course) : 
            // پردازش course_status_flags
            // مهم: وضعیت دوره فقط بر اساس course_status_flags تعیین می‌شود
            $flags = [];
            $has_flags = false;
            if (!empty($user_course->course_status_flags)) {
                $flags_string = trim($user_course->course_status_flags);
                if (!empty($flags_string)) {
                    $flags = explode(',', $flags_string);
                    $flags = array_map('trim', $flags);
                    $flags = array_filter($flags); // حذف مقادیر خالی
                    $has_flags = !empty($flags);
                }
            }
            
            // بررسی فلگ‌ها - فقط اگر در course_status_flags باشند
            $is_paused = in_array('paused', $flags);
            $is_completed = in_array('completed', $flags);
            $is_canceled = in_array('canceled', $flags);
            
            // بررسی اینکه آیا دوره در حال پرداخت است یا در انتظار بررسی
            // در انتظار پرداخت: وقتی کاربر برای آن صورت حساب ایجاد شده ولی هنوز پرداخت نکرده
            // در انتظار بررسی: وقتی invoice در حالت under_review است
            
            // ابتدا مستقیماً از دیتابیس بررسی کن (قابل اعتمادتر)
            $is_under_review = false;
            $has_pending_invoice = false;
            
            if (isset($player) && isset($player->id) && isset($user_course->course_id)) {
                global $wpdb;
                $invoices_table = $wpdb->prefix . 'sc_invoices';
                
                // بررسی invoice های pending و under_review برای این دوره
                $invoice_status = $wpdb->get_row($wpdb->prepare(
                    "SELECT status FROM $invoices_table 
                     WHERE member_id = %d AND course_id = %d AND status IN ('pending', 'under_review') 
                     ORDER BY created_at DESC LIMIT 1",
                    $player->id,
                    $user_course->course_id
                ));
                
                
                if ($invoice_status) {
                    if ($invoice_status->status === 'under_review') {
                        $is_under_review = true;
                    } else {
                        $has_pending_invoice = true;
                    }
                }
            }
            
            // اگر از آرایه‌ها هم set شده باشند، استفاده کن (fallback)
            if (!$is_under_review && isset($under_review_invoices) && is_array($under_review_invoices)) {
                $is_under_review = isset($under_review_invoices[$user_course->course_id]) && $under_review_invoices[$user_course->course_id] === true;
            }
            
            if (!$has_pending_invoice && isset($pending_invoices) && isset($pending_invoices[$user_course->course_id])) {
                $has_pending_invoice = true;
            }
            
            $is_pending_payment = (!$has_flags && $user_course->status === 'inactive' && $has_pending_invoice && !$is_under_review);
            
            // تعیین برچسب وضعیت و رنگ
            // اولویت: canceled > paused > completed > under_review > pending_payment > active
            $status_labels = [];
            $status_color = '#155724';
            $status_bg = '#d4edda';
            $status_icon = '✅';
            $status_tooltip = '';
            $display = true;
            
            if ($is_canceled) {
                // دوره لغو شده: در صورتی که دوره فعال باشه و flag لغو شده داشته باشه
                $status_labels[] = 'لغو شده';
                $status_color = '#d63638';
                $status_bg = '#ffeaea';
                $status_icon = '❌';
                $status_tooltip = 'این دوره لغو شده است.';
            } elseif ($is_paused) {
                // دوره متوقف شده: در صورتی که دوره فعال باشه و flag متوقف شده داشته باشه
                $status_labels[] = 'متوقف شده';
                $status_color = '#f0a000';
                $status_bg = '#fff8e1';
                $status_icon = '⏸️';
                $status_tooltip = 'این دوره متوقف شده است.';
            } elseif ($is_completed) {
                // دوره تمام شده: در صورتی که دوره فعال باشه و flag تمام شده داشته باشه
                $status_labels[] = 'تمام شده';
                $status_color = '#666';
                $status_bg = '#f5f5f5';
                $status_icon = '✔️';
                $status_tooltip = 'این دوره به اتمام رسیده است.';
            } elseif ($is_under_review) {
                // در انتظار بررسی: وقتی invoice در حالت under_review است
                $status_labels[] = 'در انتظار بررسی';
                $status_color = '#856404';
                $status_bg = '#fff3cd';
                $status_icon = '⏳';
                $status_tooltip = 'صورت حساب این دوره در حال بررسی است. پس از تایید مدیر و تبدیل به پرداخت شده، دوره فعال خواهد شد.';
            } elseif ($is_pending_payment) {
                // در انتظار پرداخت: وقتی کاربر برای آن صورت حساب ایجاد شده ولی هنوز پرداخت نکرده
                $status_labels[] = 'در انتظار پرداخت';
                $status_color = '#856404';
                $status_bg = '#fff3cd';
                $status_icon = '⏳';
                $status_tooltip = 'صورت حساب این دوره در حال پرداخت است. پس از پرداخت، دوره فعال خواهد شد.';
            } elseif (!$is_under_review && !$is_pending_payment && $user_course->status === 'active' && !$has_flags) {
                // دوره فعال: هیچ فلگی ندارد و status = 'active' و under_review نیست و pending_payment نیست
                $status_labels[] = 'فعال';
                $status_color = '#155724';
                $status_bg = '#d4edda';
                $status_icon = '✅';
                $status_tooltip = 'این دوره فعال است و شما در آن ثبت‌نام کرده‌اید.';
            } 
            else {
                // حالت پیش‌فرض (اگر هیچکدام از شرایط بالا برقرار نبود)
                // این حالت نباید اتفاق بیفتد، اما برای اطمینان اضافه شده
                $status_labels[] = 'نامشخص';
                $status_color = '#666';
                $status_bg = '#f5f5f5';
                $status_icon = '❓';
                $status_tooltip = 'وضعیت این دوره نامشخص است.';
                $display = false;
            }
            
            $status_display = implode('، ', $status_labels);
            // فقط دوره‌های فعال (بدون هیچ flag و بدون pending payment و بدون under_review) می‌توانند لغو شوند
            $can_cancel = !$has_flags && !$is_pending_payment && !$is_under_review && $user_course->status === 'active';
        ?>
        
            <div class="sc-course-card" style="
                background: #fff;
                border-radius: 13px;
                padding: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
                transition: all 0.3s ease;
                border: 2px solid transparent;
                position: relative;
                overflow: hidden;
                display : <?php echo $display === false ? 'none' : 'block' ?>;
            " onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.12)'; this.style.borderColor='#2271b1';" 
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 0, 0, 0.08)'; this.style.borderColor='transparent';">
                
                <!-- نوار رنگی بالای کارت -->
                <div style="
                    position: absolute;
                    top: 0;
                    right: 0;
                    width: 4px;
                    height: 100%;
                    background: linear-gradient(180deg, #2271b1 0%, #135e96 100%);
                "></div>
                
                <!-- عنوان دوره -->
                <div style="margin-bottom: 15px; padding-right: 10px;">
                    <h3 style="
                        margin: 0;
                        font-size: 20px;
                        font-weight: 600;
                        color: #1a1a1a;
                        line-height: 1.4;
                    ">
                        <?php echo esc_html($user_course->course_title); ?>
                    </h3>
                </div>
                
                <!-- وضعیت دوره -->
                <div style="margin-bottom: 20px;">
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 8px 14px;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 13px;
                        background-color: <?php echo esc_attr($status_bg); ?>;
                        color: <?php echo esc_attr($status_color); ?>;
                        cursor: <?php echo !empty($status_tooltip) ? 'help' : 'default'; ?>;
                        position: relative;
                    " 
                    <?php if (!empty($status_tooltip)) : ?>
                        title="<?php echo esc_attr($status_tooltip); ?>"
                        data-tooltip="<?php echo esc_attr($status_tooltip); ?>"
                    <?php endif; ?>
                    >
                        <span style="font-size: 16px;"><?php echo esc_html($status_icon); ?></span>
                        <?php echo esc_html($status_display); ?>
                    </span>
                </div>
                <div class="created_at_class">
                        <span class="key">تاریخ ثبت نام: </span>
                        <span class="val"> <?php echo sc_date_shamsi_date_only($user_course->created_at); ?> </span>
                </div>
                <?php if($user_course->total_sessions > 0): ?>
                <div class="total_sessions">
                        <span class="key">کل جلسات دوره : </span>
                        <span class="val"> <?php echo $user_course->total_sessions ?> </span>
                </div>
                <div class="remaining_sessions">
                        <span class="key">جلسات باقی مانده : </span>
                        <span class="val"> <?php echo $user_course->remaining_sessions  ?> </span>
                </div>
                <?php endif; ?>
                <!-- دکمه عملیات -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <?php if ($can_cancel) : ?>
                        <form method="POST" action="" style="margin: 0;" onsubmit="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید که می‌خواهید این دوره را لغو کنید؟' });">
                            <?php wp_nonce_field('sc_cancel_course', 'sc_cancel_course_nonce'); ?>
                            <input type="hidden" name="cancel_course_id" value="<?php echo esc_attr($user_course->id); ?>">
                            <button type="submit" name="sc_cancel_course" style="
                                width: 100%;
                                background: linear-gradient(135deg, #d63638 0%, #b32d2e 100%);
                                color: #fff;
                                border: none;
                                padding: 12px 20px;
                                border-radius: 8px;
                                font-size: 14px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                box-shadow: 0 2px 8px rgba(214, 54, 56, 0.3);
                            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(214, 54, 56, 0.4)';" 
                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(214, 54, 56, 0.3)';">
                                لغو دوره
                            </button>
                        </form>
                    <?php else : ?>
                        <div style="
                            text-align: center;
                            padding: 12px;
                            color: #999;
                            font-size: 14px;
                            background: #f9f9f9;
                            border-radius: 8px;
                        ">
                            عملیات در دسترس نیست
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- صفحه‌بندی -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin: 20px 10px 50px 0px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg(['pag' => '%#%']),
                            'format' => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >' ,
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-my-courses-page h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>
