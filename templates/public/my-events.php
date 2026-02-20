<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// دریافت متغیرهای فیلتر و صفحه‌بندی (اگر از my-account.php فراخوانی شده باشد)
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all');
$current_page = isset($current_page) ? $current_page : (isset($_GET['paged']) ? absint($_GET['paged']) : 1);
$total_pages = isset($total_pages) ? $total_pages : 1;
$total_events = isset($total_events) ? $total_events : 0;
?>

<div class="sc-my-event-registers-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">🏃‍♂️</span>
        رویداد های ثبت نامی های من
    </h2>
    
  
    
    <?php 
    if (empty($user_events)) : ?>
        <div class="sc-message sc-message-info" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <?php if ($filter_status !== 'all') : ?>
                رویدادی با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز در هیچ رویدادی ثبت‌نام نکرده‌اید.
            <?php endif; ?>
        </div>
    <?php else : ?>
    
    <!-- نمایش رویداد ها به صورت کارت -->
    <div class="sc-my-events_register-grid">
    <?php foreach ($user_events as $user) :
        $event_id = $user['event_id'];
    ?>
    <div class="sc-event-card" id="event_<?php echo esc_attr($event_id); ?>">
        <div class="sc-event-header">
            <h3><?php echo esc_html($user['name']); ?></h3>
        </div>
        
        <div class="sc-event-dates">
            <span class="sc-event-time">🕐 <strong> زمان: </strong> <?php echo esc_html($user['event_time'] ?? 'مشخص نشده'); ?></span>
            <span class="sc-event-holding">📅 <strong> تاریخ: </strong> <?php echo esc_html($user['holding_date_shamsi'] ?? 'مشخص نشده'); ?></span>
        </div>
        
        <div class="sc-event-actions">
            <a href="<?php echo esc_url(home_url("/my-account/sc-event-detail/$event_id")); ?>" class="button button-primary">
                مشاهده جزئیات رویداد
            </a>
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
        const el = document.querySelector('.sc-my-event-registers-page h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>
