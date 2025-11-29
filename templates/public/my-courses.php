<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$courses_table = $wpdb->prefix . 'sc_courses';

// دریافت دوره‌های کاربر (فقط دوره‌هایی که active هستند)
$user_courses = $wpdb->get_results($wpdb->prepare(
    "SELECT mc.*, c.title as course_title
     FROM $member_courses_table mc
     INNER JOIN $courses_table c ON mc.course_id = c.id
     WHERE mc.member_id = %d
     AND mc.status = 'active'
     ORDER BY mc.created_at DESC",
    $player->id
));

if (empty($user_courses)) {
    echo '<div class="woocommerce-message woocommerce-message--info woocommerce-info">';
    echo 'شما هنوز در هیچ دوره‌ای ثبت‌نام نکرده‌اید.';
    echo '</div>';
    return;
}
?>

<div class="sc-my-courses-page">
    <h2>دوره‌های من</h2>
    
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
