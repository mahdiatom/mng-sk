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
    <h2>دوره‌های من</h2>
    
    <!-- فیلتر وضعیت -->
    <div class="sc-my-courses-filters" style="margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-my-courses')); ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="paged" value="1">
            
            <div style="flex: 1; min-width: 200px;">
                <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                <select name="filter_status" id="filter_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>>فعال</option>
                    <option value="canceled" <?php selected($filter_status, 'canceled'); ?>>لغو شده</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary" style="padding: 8px 20px; height: auto;">اعمال فیلتر</button>
            </div>
        </form>
    </div>
    
    <?php if (empty($user_courses)) : ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-info">
            <?php if ($filter_status !== 'all') : ?>
                دوره‌ای با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز در هیچ دوره‌ای ثبت‌نام نکرده‌اید.
            <?php endif; ?>
        </div>
    <?php else : ?>
    
    <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
        <thead>
            <tr>
                <th class="woocommerce-orders-table__header">
                    <span class="nobr">نام دوره</span>
                </th>
                <th class="woocommerce-orders-table__header">
                    <span class="nobr">وضعیت</span>
                </th>
                <th class="woocommerce-orders-table__header">
                    <span class="nobr">عملیات</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($user_courses as $user_course) : 
                // پردازش course_status_flags
                $flags = [];
                if (!empty($user_course->course_status_flags)) {
                    $flags = explode(',', $user_course->course_status_flags);
                    $flags = array_map('trim', $flags);
                }
                
                $is_paused = in_array('paused', $flags);
                $is_completed = in_array('completed', $flags);
                $is_canceled = in_array('canceled', $flags);
                
                // تعیین برچسب وضعیت
                $status_labels = [];
                if ($is_paused) {
                    $status_labels[] = 'متوقف شده';
                }
                if ($is_completed) {
                    $status_labels[] = 'تمام شده';
                }
                if ($is_canceled) {
                    $status_labels[] = 'لغو شده';
                }
                
                if (empty($status_labels)) {
                    $status_labels[] = 'فعال';
                }
                
                $status_display = implode('، ', $status_labels);
                $can_cancel = !$is_canceled && !$is_completed;
            ?>
                <tr class="woocommerce-orders-table__row">
                    <td class="woocommerce-orders-table__cell" data-title="نام دوره">
                        <strong><?php echo esc_html($user_course->course_title); ?></strong>
                    </td>
                    <td class="woocommerce-orders-table__cell" data-title="وضعیت">
                        <span class="woocommerce-orders-table__status" style="
                            padding: 5px 10px;
                            border-radius: 4px;
                            font-weight: bold;
                            background-color: #d4edda;
                            color: #155724;
                        ">
                            <?php echo esc_html($status_display); ?>
                        </span>
                    </td>
                    <td class="woocommerce-orders-table__cell" data-title="عملیات">
                        <?php if ($can_cancel) : ?>
                            <form method="POST" action="" style="display: inline-block;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این دوره را لغو کنید؟');">
                                <?php wp_nonce_field('sc_cancel_course', 'sc_cancel_course_nonce'); ?>
                                <input type="hidden" name="cancel_course_id" value="<?php echo esc_attr($user_course->id); ?>">
                                <button type="submit" name="sc_cancel_course" class="button" style="background-color: #d63638; color: #fff; border-color: #d63638;">
                                    لغو دوره
                                </button>
                            </form>
                        <?php else : ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
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

<style>
.sc-my-courses-page {
    margin-top: 20px;
}

.sc-my-courses-page table {
    width: 100%;
}

.sc-my-courses-page .button:hover {
    opacity: 0.9;
}
</style>
