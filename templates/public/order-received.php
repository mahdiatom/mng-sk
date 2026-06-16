<?php
/**
 * Template Name: صفحه تشکر بعد از خرید
 *
 * @package SportClub Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wp, $wpdb;
$order_id = 0;

if (isset($wp->query_vars['order-received'])) {
    $order_id = absint($wp->query_vars['order-received']);
} elseif (isset($_GET['order'])) {
    $order_id = absint($_GET['order']);
}

$order = wc_get_order($order_id);

if (!$order) {
    return;
}

$sc_ctx = function_exists('sc_get_order_sportclub_context')
    ? sc_get_order_sportclub_context($order_id)
    : [
        'item_type' => 'other',
        'item_name' => '',
        'is_private' => false,
        'display_price' => 0,
    ];

$item_type = $sc_ctx['item_type'] ?? 'other';
$item_name = $sc_ctx['item_name'] ?: 'سایر';
$is_private = !empty($sc_ctx['is_private']);
$course_info = $sc_ctx['course'] ?? null;
$event_info = $sc_ctx['event'] ?? null;

$item_description = '';
if ($course_info && !empty($course_info->description)) {
    $item_description = wp_trim_words($course_info->description, 30);
} elseif ($event_info && !empty($event_info->description)) {
    $item_description = wp_trim_words($event_info->description, 30);
}

$order_number = $order->get_order_number();
$order_date = $order->get_date_created();
$raw_status = $order->get_status();
$status_labels = [
    'pending'    => 'در انتظار پرداخت',
    'processing' => 'پرداخت شده',
    'on-hold'    => 'در انتظار بررسی',
    'completed'  => 'تایید پرداخت',
    'cancelled'  => 'لغو شده',
    'refunded'   => 'بازپرداخت شده',
    'failed'     => 'ناموفق',
];
$order_status_label = isset($status_labels[$raw_status])
    ? $status_labels[$raw_status]
    : wc_get_order_status_name($raw_status);

$display_total_html = function_exists('sc_get_order_formatted_total')
    ? sc_get_order_formatted_total($order, $sc_ctx)
    : $order->get_formatted_order_total();

$order_date_shamsi = '';
if ($order_date && function_exists('sc_date_shamsi_date_only')) {
    $order_date_shamsi = sc_date_shamsi_date_only($order_date->date('Y-m-d H:i:s'));
}

$order_items = $order->get_items();
$thankyou_extra_class = $is_private ? ' sc-thankyou-page--private' : '';
?>

<div class="sc-thankyou-page<?php echo esc_attr($thankyou_extra_class); ?>">
    <div class="sc-thankyou-header">
        <div class="sc-thankyou-icon">
            <svg width="80" style="width: 80px;" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="40" cy="40" r="40" fill="#6D34FF" opacity="0.1"/>
                <path d="M30 40L35 45L50 30" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1 class="sc-thankyou-title">سپاس از اعتماد شما</h1>
        <p class="sc-thankyou-message">
            درخواست شما با موفقیت ثبت شد.
            <br>
            شماره سفارش شما: <strong><?php echo esc_html($order_number); ?></strong>
        </p>
        <p class="after_do_pay">خب حالا بگو چیکار کنیم؟<br>می‌توانی جزئیات سفارشت را پایین‌تر ببینی و در صورت نیاز اسکرین‌شات بگیری. از دکمه‌های زیر هم برای دسترسی سریع‌تر استفاده کن.</p>

        <div class="boxs_do_after_pay">
            <div class="go_to_pannel">
                <a href="<?php echo esc_url(site_url('/my-account/sc-wallet/')); ?>">برو به کیف پول من</a>
            </div>
            <?php if ($is_private) : ?>
            <div class="go_to_private_classes">
                <a href="<?php echo esc_url(site_url('/my-account/sc-private-classes/')); ?>">برو به کلاس‌های خصوصی</a>
            </div>
            <?php endif; ?>
            <div class="go_to_courses">
                <a href="<?php echo esc_url(site_url('/my-account/sc-enroll-course/')); ?>">برو به بخش دوره‌ها</a>
            </div>
            <div class="go_to_my_courses">
                <a href="<?php echo esc_url(site_url('/my-account/sc-my-courses/')); ?>">برو به دوره‌های ثبت‌نامی من</a>
            </div>
            <div class="go_to_invoice">
                <a href="<?php echo esc_url(site_url('/my-account/sc-invoices/')); ?>">برو به صورت‌حساب‌های من</a>
            </div>
        </div>
    </div>

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
                <div class="sc-thankyou-card-value"><?php echo esc_html($order_status_label); ?></div>
            </div>
        </div>

        <div class="sc-thankyou-card sc-thankyou-card-total">
            <div class="sc-thankyou-card-icon">💰</div>
            <div class="sc-thankyou-card-content">
                <div class="sc-thankyou-card-label">مبلغ کل</div>
                <div class="sc-thankyou-card-value"><?php echo wp_kses_post($display_total_html); ?></div>
            </div>
        </div>
    </div>

    <?php wc_print_notices(); ?>

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

                <?php
                if (function_exists('sc_render_order_item_detail_rows')) {
                    sc_render_order_item_detail_rows($sc_ctx, 'sc-thankyou-item-row', ['show_dates' => false]);
                }
                ?>

                <?php if (!empty($item_description)) : ?>
                    <div class="sc-thankyou-item-description">
                        <span class="sc-thankyou-item-label">توضیحات:</span>
                        <p class="sc-thankyou-item-value"><?php echo esc_html($item_description); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="sc-thankyou-order-items">
        <h2 class="sc-thankyou-section-title">
            <span class="sc-thankyou-section-icon">🛒</span>
            جزئیات سفارش
        </h2>

        <div class="sc-thankyou-items-list">
            <?php foreach ($order_items as $item_id => $item) :
                $line_name = $item->get_name();
                $item_quantity = $item->get_quantity();
                $line_amount = function_exists('sc_get_order_item_display_amount')
                    ? sc_get_order_item_display_amount($item, $sc_ctx)
                    : (float) $item->get_total();
            ?>
                <div class="sc-thankyou-item">
                    <div class="sc-thankyou-item-product">
                        <div class="sc-thankyou-item-product-name"><?php echo esc_html($line_name); ?></div>
                        <div class="sc-thankyou-item-product-meta">
                            <span class="sc-thankyou-item-quantity">تعداد: <?php echo esc_html($item_quantity); ?></span>
                            <span class="sc-thankyou-item-price"><?php echo wp_kses_post(wc_price($line_amount)); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sc-thankyou-order-summary">
            <div class="sc-thankyou-summary-row">
                <span class="sc-thankyou-summary-label">جمع کل:</span>
                <span class="sc-thankyou-summary-value"><?php echo wp_kses_post($display_total_html); ?></span>
            </div>
        </div>
    </div>
</div>
