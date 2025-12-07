<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// دریافت متغیرهای فیلتر و صفحه‌بندی (اگر از my-account.php فراخوانی شده باشد)
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all');
$current_page = isset($current_page) ? $current_page : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
$total_courses = isset($total_courses) ? $total_courses : 0;
?>

<div class="sc-my-courses-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">📚</span>
        دوره‌های من
    </h2>
    
    <!-- فیلتر وضعیت -->
    <div class="sc-my-courses-filters" style="margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-my-courses')); ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="paged" value="1">
            
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
            
            // بررسی اینکه آیا دوره در حال پرداخت است
            // در انتظار پرداخت: وقتی کاربر برای آن صورت حساب ایجاد شده ولی هنوز پرداخت نکرده
            // شرط: status = 'inactive' و invoice pending دارد و هیچ فلگی ندارد
            $has_pending_invoice = isset($pending_invoices) && isset($pending_invoices[$user_course->course_id]);
            $is_pending_payment = (!$has_flags && $user_course->status === 'inactive' && $has_pending_invoice);
            
            // تعیین برچسب وضعیت و رنگ
            // اولویت: canceled > paused > completed > pending_payment > active
            $status_labels = [];
            $status_color = '#155724';
            $status_bg = '#d4edda';
            $status_icon = '✅';
            $status_tooltip = '';
            
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
            } elseif ($is_pending_payment) {
                // در انتظار پرداخت: وقتی کاربر برای آن صورت حساب ایجاد شده ولی هنوز پرداخت نکرده
                $status_labels[] = 'در انتظار پرداخت';
                $status_color = '#856404';
                $status_bg = '#fff3cd';
                $status_icon = '⏳';
                $status_tooltip = 'صورت حساب این دوره در حال پرداخت است. پس از پرداخت، دوره فعال خواهد شد.';
            } else {
                // دوره فعال: هیچ فلگی ندارد و status = 'active'
                $status_labels[] = 'فعال';
                $status_color = '#155724';
                $status_bg = '#d4edda';
                $status_icon = '✅';
                $status_tooltip = 'این دوره فعال است و شما در آن ثبت‌نام کرده‌اید.';
            }
            
            $status_display = implode('، ', $status_labels);
            // فقط دوره‌های فعال (بدون هیچ flag و بدون pending payment) می‌توانند لغو شوند
            $can_cancel = !$has_flags && !$is_pending_payment && $user_course->status === 'active';
        ?>
            <div class="sc-course-card" style="
                background: #fff;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
                transition: all 0.3s ease;
                border: 2px solid transparent;
                position: relative;
                overflow: hidden;
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
                
                <!-- دکمه عملیات -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <?php if ($can_cancel) : ?>
                        <form method="POST" action="" style="margin: 0;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این دوره را لغو کنید؟');">
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
        <div class="sc-my-courses-pagination" style="margin-top: 30px; text-align: center;">
            <?php
            // ساخت URL base با حفظ فیلترها
            $pagination_args = ['paged' => '%#%'];
            if ($filter_status !== 'all') {
                $pagination_args['filter_status'] = $filter_status;
            }
            
            $page_links = paginate_links([
                'base' => add_query_arg($pagination_args),
                'format' => '',
                'prev_text' => '&laquo; قبلی',
                'next_text' => 'بعدی &raquo;',
                'total' => $total_pages,
                'current' => $current_page,
                'type' => 'plain',
                'end_size' => 2,
                'mid_size' => 2
            ]);
            
            if ($page_links) {
                echo '<div class="pagination-wrapper" style="display: inline-block;">';
                echo $page_links;
                echo '</div>';
                echo '<div style="margin-top: 10px; color: #666; font-size: 14px;">';
                echo 'نمایش ' . (($current_page - 1) * 10 + 1) . ' تا ' . min($current_page * 10, $total_courses) . ' از ' . $total_courses . ' دوره';
                echo '</div>';
            }
            ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

