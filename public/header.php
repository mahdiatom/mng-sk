<?php 

add_action('wp_head', 'custom_header_output');
function custom_header_output() {
    ?>
    <header class="custom-header" >
        
    <div class="logo_custom_gym">
        <img src="<?php echo sc_get_setting('sc_login_logo_url'); ?>" alt="">
    
    </div>
    <div class="menus">
        <nav class="menu_custom_gym menu-header menu-header_dl"><?php wp_nav_menu() ?></nav>
        
        <nav class="menu_custom_gym menu-header_moblie "><img class="menu-header_moblie" src="<?php echo SC_PLUGIN_URL; ?>assets/img/icon_menu.png" width="32"></nav>

    </div>
    <div class="woo_panel_mini">
        <p>اطلاعیه ها</p>
        <p>ثبت تیکت</p>
    </div>

    </header>

    <!-- The Modal -->
<div id="myModal" class="modal" style="visibility : hidden; display: none;">

  <!-- Modal content -->
  <div class="modal-menu_mobile">
    <span class="close">&times;</span>
    <p class="sk-modal-content hide_before_data"></p>
    <p class="sk-modal-content_courses hide_before_data_courses"></p>
    <div class="sc-modal-body">
                    <nav class="menu_custom_gym_popup menu-header"><?php wp_nav_menu() ?></nav>

            </div>

  
  </div>

</div>
</div>
    <?php
}




// sql ="SELECT 
//     -- اسم کاربر: از فیلدهای پرداخت (billing) در سفارش گرفته شده است
//     CONCAT(billing_first_name, ' ', billing_last_name) AS `اسم_کاربر`,  

//     -- محصولات سفارش داده: لیست کاملاً خوانا و با تعداد هر محصول
//     GROUP_CONCAT(CONCAT(' - ', product_name, ' (تعداد: ', quantity, ')') SEPARATOR '; ') AS `محصولات_سفارش_داده`,

//     -- وضعیت سفارش
//     post_status AS `وضعیت`,  

//     -- تاریخ ثبت سفارش
//     post_date AS `تاریخ`,
    
//     -- قیمت کل صورت حساب
//     pm.meta_value AS `قیمت_کل_صورت_حساب`
    
// FROM 
//     wp_posts p  -- جدول اصلی پست‌ها، ما سفارشات را از اینجا می‌گیریم
    
// LEFT JOIN 
//     wp_postmeta pmeta ON p.ID = pmeta.post_id AND pmeta.meta_key IN ('_billing_first_name', '_billing_last_name') 

// -- برای نمایش لیست محصولاتی که در یک سفارش وجود دارد
// LEFT JOIN 
//     wp_woocommerce_order_items oi ON p.ID = oi.order_id

// LEFT JOIN 
//     wp_woocommerce_order_itemmeta oitem ON oi.order_item_id = oitem.order_item_id AND oitem.meta_key = '_product_name'

// LEFT JOIN 
//     wp_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_quantity' -- برای گرفتن تعداد

// -- هر سفارشی فقط یک ردیف خواهد داشت، بنابراین از GROUP BY استفاده می‌کنیم
// GROUP BY 
//     p.ID, p.post_status, p.post_date, pm.meta_value, billing_first_name, billing_last_name";