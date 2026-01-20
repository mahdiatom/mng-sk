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

<div class="sc-my-courses-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">📚</span>
        رویداد های ثبت نامی های من
    </h2>
    
  
    
    <?php if (empty($user_events)) : ?>
        <div class="sc-message sc-message-info" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <?php if ($filter_status !== 'all') : ?>
                رویدادی با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز در هیچ رویدادی ثبت‌نام نکرده‌اید.
            <?php endif; ?>
        </div>
    <?php else : ?>
    
    <!-- نمایش رویداد ها به صورت کارت -->
    <div class="sc-my-events_register-grid" >
        <?php foreach ($user_events as $user) :
        
        $event_id =$user['event_id'];
          ?>  
<div class="cart_event_register">
    <div class="name_event">
            <h2><?php echo $user['name'];  ?></h2>
    </div>
    <div class="dates">
        <div class="time_register">🕐 زمان : <?php echo $user['event_time'] ?? 'مشخص نشده';  ?></div>
        <div class="time_holding">📅 تاریخ :  <?php echo $user['holding_date_shamsi'] ?? 'مشخص نشده' ?></div>
    </div>
    <div class="btn_details">


            <a href="<?php echo home_url("/my-account/sc-event-detail/$event_id") ?>"> مشاهده جزئیات رویداد </a>
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

