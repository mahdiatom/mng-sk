<?php
/**
 * Template Name: صفحه تشکر بعد از خرید
 * 
 * این فایل برای نمایش صفحه تشکر بعد از خرید WooCommerce استفاده می‌شود.
 * مسیر فایل: templates/public/order-received.php
 * 
 * برای تغییرات بیشتر، می‌توانید این فایل را ویرایش کنید.
 * استایل‌های CSS در فایل assets/css/public.css قرار دارند.
 * 
 * @package SportClub Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// دریافت اطلاعات سفارش
global $wp, $wpdb;
$order_id = 0;

// بررسی از طریق query var (اولویت اول)
if (isset($wp->query_vars['order-received'])) {
    $order_id = absint($wp->query_vars['order-received']);
} 
// بررسی از طریق GET parameter
elseif (isset($_GET['order'])) {
    $order_id = absint($_GET['order']);
}

$order = wc_get_order($order_id);

if (!$order) {
    return;
}

// دریافت invoice از order_id
$invoices_table = $wpdb->prefix . 'sc_invoices';
$invoice = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $invoices_table WHERE woocommerce_order_id = %d LIMIT 1",
    $order_id
));

// دریافت اطلاعات دوره یا رویداد
$course_info = null;
$event_info = null;
$item_type = 'other';
$item_name = 'سایر';
$item_description = '';

if ($invoice) {
    // اگر دوره باشد
    if (!empty($invoice->course_id)) {
        $courses_table = $wpdb->prefix . 'sc_courses';
        $course_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL",
            $invoice->course_id
        ));
        
        if ($course_info) {
            $item_type = 'course';
            $item_name = $course_info->title;
            $item_description = !empty($course_info->description) ? wp_trim_words($course_info->description, 30) : '';
        }
    }
    // اگر رویداد باشد
    elseif (!empty($invoice->event_id)) {
        $events_table = $wpdb->prefix . 'sc_events';
        $event_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $events_table WHERE id = %d AND deleted_at IS NULL",
            $invoice->event_id
        ));
        
        if ($event_info) {
            $item_type = 'event';
            $item_name = $event_info->name;
            $item_description = !empty($event_info->description) ? wp_trim_words($event_info->description, 30) : '';
        }
    }
}

// دریافت اطلاعات سفارش
$order_number = $order->get_order_number();
$order_date = $order->get_date_created();
$order_status = $order->get_status();
$order_total = $order->get_total();
$status_labels = array(
    'pending'    => 'در انتظار پرداخت',
    'processing' => 'پرداخت شده',
    'on-hold'    => 'در انتظار بررسی',
    'completed'  => 'تایید پرداخت',
    'cancelled'  => 'لغو شده',
    'refunded'   => 'بازپرداخت شده',
    'failed'     => 'ناموفق'
);

$order_status = isset($status_labels[$order_status]) 
    ? $status_labels[$order_status] 
    : $order_status;





// تبدیل تاریخ به شمسی
$order_date_shamsi = '';
if ($order_date) {
    $order_date_shamsi = sc_date_shamsi_date_only($order_date->date('Y-m-d H:i:s'));
}

// دریافت آیتم‌های سفارش
$order_items = $order->get_items();
?>

<div class="sc-thankyou-page">
    <!-- هدر تشکر -->
    <div class="sc-thankyou-header">
        <div class="sc-thankyou-icon">
            <svg width="80" style="width: 80px;" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="40" cy="40" r="40" fill="#6D34FF" opacity="0.1"/>
                <path d="M30 40L35 45L50 30" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1 class="sc-thankyou-title">سپاس از اعتماد شما</h1>
        <p class="sc-thankyou-message">
            درخواست شما با موفقیت ثبت شد .
            <br>
            شماره سفارش شما: <strong><?php echo esc_html($order_number); ?></strong>
        </p>
                    <p class="after_do_pay">خب حالا بگو چیکار کنیم؟ <br> میتونی جزئیات سفارش ات رو پایین تر ببینی و در صورت نیاز یه اسکرین شات بگیری. از دکمه های زیر هم برای دسترسی سریع تر میتونی استفاده کنی.</p>

        <div class="boxs_do_after_pay">
            <div class="go_to_pannel">
                <a href="<?php echo site_url(); ?>/my-account/sc-wallet/">برو به کیف پول من </a>
            </div>
            <div class="go_to_courses">
                <a href="<?php echo site_url(); ?>/my-account/sc-enroll-course/">برو به بخش دوره ها</a>
            </div>
            <div class="go_to_my_courses">
                <a href="<?php echo site_url(); ?>/my-account/sc-my-courses/">برو به دوره های ثبت نامی من </a>
            </div>
            <div class="go_to_invoice">
                <a href="<?php echo site_url(); ?>/my-account/sc-invoices/">برو به صورت حساب های من </a>
            </div>

        </div>
    </div>


    <!-- کارت‌های اطلاعات اصلی -->
    <div class="sc-thankyou-cards">
        <div class="sc-thankyou-card sc-thankyou-card-order">
            <div class="sc-thankyou-card-icon">📦</div>
            <div class="sc-thankyou-card-content">
                <div class="sc-thankyou-card-label">شماره سفارش</div>
                <div class="sc-thankyou-card-value"><?php echo esc_html($order_number); ?></div>
            </div>
        </div>

        <div class="sc-thankyou-card sc-thankyou-card-date">
            <div class="sc-thankyou-card-icon">📅</div>
            <div class="sc-thankyou-card-content">
                <div class="sc-thankyou-card-label">تاریخ سفارش</div>
                <div class="sc-thankyou-card-value"><?php echo esc_html($order_date_shamsi); ?></div>
            </div>
        </div>

        <div class="sc-thankyou-card sc-thankyou-card-status">
            <div class="sc-thankyou-card-icon">✅</div>
            <div class="sc-thankyou-card-content">
                <div class="sc-thankyou-card-label">وضعیت</div>
                <div class="sc-thankyou-card-value"><?php echo wc_get_order_status_name($order_status); ?></div>
            </div>
        </div>

        <div class="sc-thankyou-card sc-thankyou-card-total">
            <div class="sc-thankyou-card-icon">💰</div>
            <div class="sc-thankyou-card-content">
                <div class="sc-thankyou-card-label">مبلغ کل</div>
                <div class="sc-thankyou-card-value"><?php echo $order->get_formatted_order_total(); ?></div>
            </div>
        </div>
    </div>

    <!-- پیام‌های WooCommerce -->
    <?php wc_print_notices(); ?>

    <!-- اطلاعات دوره یا رویداد -->
    <?php if ($item_type !== 'other') : ?>
        <div class="sc-thankyou-item-info">
            <h2 class="sc-thankyou-section-title">
                <span class="sc-thankyou-section-icon"><?php echo $item_type === 'course' ? '📚' : '🎯'; ?></span>
                <?php echo $item_type === 'course' ? 'اطلاعات دوره' : 'اطلاعات رویداد'; ?>
            </h2>
            
            <div class="sc-thankyou-item-details">
                <div class="sc-thankyou-item-name">
                    <span class="sc-thankyou-item-label"><?php echo $item_type === 'course' ? 'نام دوره:' : 'نام رویداد:'; ?></span>
                    <span class="sc-thankyou-item-value"><?php echo esc_html($item_name); ?></span>
                </div>
                
                <?php if ($item_type === 'course' && $course_info) : ?>
                    <div class="sc-thankyou-item-row">
                        <span class="sc-thankyou-item-label">قیمت دوره:</span>
                        <span class="sc-thankyou-item-value"><?php echo number_format($course_info->price, 0) . ' تومان'; ?></span>
                    </div>
                    
                    <?php if (!empty($course_info->start_date)) : 
                        $start_date_shamsi = sc_date_shamsi_date_only($course_info->start_date);
                    ?>
                        <div class="sc-thankyou-item-row">
                            <span class="sc-thankyou-item-label">تاریخ شروع:</span>
                            <span class="sc-thankyou-item-value"><?php echo esc_html($start_date_shamsi); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($course_info->end_date)) : 
                        $end_date_shamsi = sc_date_shamsi_date_only($course_info->end_date);
                    ?>
                        <div class="sc-thankyou-item-row">
                            <span class="sc-thankyou-item-label">تاریخ پایان:</span>
                            <span class="sc-thankyou-item-value"><?php echo esc_html($end_date_shamsi); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($course_info->capacity)) : ?>
                        <div class="sc-thankyou-item-row">
                            <span class="sc-thankyou-item-label">ظرفیت:</span>
                            <span class="sc-thankyou-item-value"><?php echo esc_html($course_info->capacity) . ' نفر'; ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($item_type === 'event' && $event_info) : ?>
                    <?php if (!empty($event_info->event_date)) : 
                        $event_date_shamsi = sc_date_shamsi_date_only($event_info->event_date);
                    ?>
                        <div class="sc-thankyou-item-row">
                            <span class="sc-thankyou-item-label">تاریخ برگزاری:</span>
                            <span class="sc-thankyou-item-value"><?php echo esc_html($event_date_shamsi); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event_info->event_time)) : ?>
                        <div class="sc-thankyou-item-row">
                            <span class="sc-thankyou-item-label">زمان:</span>
                            <span class="sc-thankyou-item-value"><?php echo esc_html($event_info->event_time); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event_info->event_location)) : ?>
                        <div class="sc-thankyou-item-row">
                            <span class="sc-thankyou-item-label">مکان:</span>
                            <span class="sc-thankyou-item-value"><?php echo esc_html($event_info->event_location); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (!empty($item_description)) : ?>
                    <div class="sc-thankyou-item-description">
                        <span class="sc-thankyou-item-label">توضیحات:</span>
                        <p class="sc-thankyou-item-value"><?php echo esc_html($item_description); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- جزئیات سفارش (محصولات) -->
    <div class="sc-thankyou-order-items">
        <h2 class="sc-thankyou-section-title">
            <span class="sc-thankyou-section-icon">🛒</span>
            جزئیات سفارش
        </h2>
        
        <div class="sc-thankyou-items-list">
            <?php foreach ($order_items as $item_id => $item) : 
                $product = $item->get_product();
                $item_name = $item->get_name();
                $item_quantity = $item->get_quantity();
                $item_total = $item->get_total();
            ?>
                <div class="sc-thankyou-item">
                    <div class="sc-thankyou-item-product">
                        <div class="sc-thankyou-item-product-name"><?php echo esc_html($item_name); ?></div>
                        <div class="sc-thankyou-item-product-meta">
                            <span class="sc-thankyou-item-quantity">تعداد: <?php echo esc_html($item_quantity); ?></span>
                            <span class="sc-thankyou-item-price"><?php echo wc_price($item_total); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- خلاصه قیمت -->
        <div class="sc-thankyou-order-summary">
            <div class="sc-thankyou-summary-row">
                <span class="sc-thankyou-summary-label">جمع کل:</span>
                <span class="sc-thankyou-summary-value"><?php echo $order->get_formatted_order_total(); ?></span>
            </div>
        </div>
    </div>

  
       
    </div>
</div>

