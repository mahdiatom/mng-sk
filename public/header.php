<?php 
if ( ! defined('ABSPATH') ) exit;
add_action('wp_head', 'custom_header_output');
function custom_header_output() {
    global $wpdb;
$unread = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications(get_current_user_id()) : 0;
 $members_table = $wpdb->prefix . 'sc_members';
 $user_id = get_current_user_id();
 $results = $wpdb->get_results(
    "SELECT CONCAT( first_name , ' ' , last_name) AS name , personal_photo AS photo , user_id , player_phone
     FROM $members_table
     WHERE user_id = $user_id " , ARRAY_A
);
// مبلغ کل با تخفیف (مثلاً تخفیف کد تخفیف)

$count_product_card = get_cart_item_count()['count'];
$sum_price_card = get_cart_item_count()['sum'];

    ?>
    <header class="custom-header header_top" >
        
        
    <div class="logo_custom_gym">
        <img src="<?php echo sc_get_setting('sc_club_logo_url'); ?>" alt="">
    
    </div>
  
    <div class="woo_panel_mini">
        <nav class="menu_custom_gym menu-header  menu_header_left" >
            <ul  class="menu">
                 <?php if(is_user_logged_in()){ ?>
                <li class="menu-item-has-children "> 
                    <span class="icon_user">
                    <?php echo file_get_contents(SC_ASSETS_DIR . '/img/icons/user3.svg'); echo '<span class=arrow-b>' . file_get_contents(SC_ASSETS_DIR . '/img/icons/arrow-down.svg') . '</span>';   ?>
                    </span>
                   
                    <ul class="sub-menu">
                        <li class="icon_name" >
                            <div class="sc-user-avatar_megamenu" ><img src="<?php echo $results[0]['photo']; ?>"></div>
                            <div class="info_megamenu">
                                <span href="#"> <?php echo $results[0]['name'] ?? ''; ?></apan>
                                <span href="#"> <?php echo $results[0]['player_phone'] ?? ''; ?></apan>
                            </div>
                            
                        </li>
                        <li><a href="<?php echo home_url('my-account/'); ?>edit-account#account_display_name" class="edit-account"> تغییر رمز ورود </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>bot-connect" class="bot-connect">اتصال به ربات </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-submit-documents" class="sc-submit-documents"> اطلاعات بازیکن  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-enroll-course" class="sc-enroll-course"> دوره ها  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-courses" class="sc-my-courses"> دوره های من  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-attendances" class="sc-my-attendances"> حضور و غیاب های من </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-events" class="sc-events"> رویداد / مسابقات</a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-events" class="sc-my-events"> رویداد های من  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-invoices" class="sc-invoices"> صورتحساب </a></li>
                        <?php 
                         if(sc_get_setting('pro_feature_shop')){ ?>
                        <li><a href="<?php echo home_url('/'); ?>shop" class="shop"> فروشگاه  </a></li>
                        <li><a href="<?php echo home_url('/'); ?>my-account/my-orders" class="my-orders"> سفارش های فروشگاه </a></li>
                        <?php } ?>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-honors" class="sc-my-honors">افتخارات من </a></li>
                        <?php if(sc_get_setting('pro_feature_notifications')){ ?>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-notifications" class="sc-notifications"> اطلاعیه ها <?php  if($unread > 0) { echo '<span class="count_unread_notif_mini">' . $unread .'</span>' ;} ?> </a></li>
                        <?php }
                         if(sc_get_setting('pro_feature_players_wallet')){?>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-wallet" class="sc-wallet"> کیف پول </a></li>
                        <?php } ?>
                
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-support-tickets" class="sc-support-tickets">  تیکت پشتیبانی </a></li>
                     
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-faq" class="sc-faq"> سوالات متداول </a></li>
                        <li><a href="<?php echo wp_logout_url( wc_get_page_permalink( 'myaccount' ) ); ?>  " onclick="confirm('شما در حال خروج از حساب کاربری هستید از این کار اطمنیان دارید؟')" >خروج از حساب کاربری</a></li>    
                    </ul>
                    
                </li>
                <?php }
                else{ ?>
                    <li class="li_btn_login">
                        <a href="<?php echo site_url(); ?>" class="btn_login" > <?php echo file_get_contents(SC_ASSETS_DIR . '/img/icons/login.svg');  ?> ورود | ثبت نام</a>
                    </li>
                    <?php } ?>
            </ul>
        </nav>
          <?php if(is_user_logged_in() && sc_get_setting('pro_feature_notifications') ){ ?>
    <a class="icon_adv" href="<?php echo site_url('my-account/sc-notifications/');  ?>">                      
                      <?php echo file_get_contents(SC_ASSETS_DIR . '/img/icons/adv.svg');  if($unread > 0) { echo '<span class="count_unread_notif">' . $unread .'</span>' ;}   ?>  
                    </a>
                    <?php } ?>

        <a class="icon_shop" href="<?php echo site_url('cart'); ?>">
             <?php if($count_product_card > 0) { echo '<span class="count_card">' . $count_product_card .'</span>' ;}   ?>  
             <?php if($sum_price_card > 0) { echo '<span class="sum_card">  ' . $sum_price_card .' تومان </span>' ;}   ?>  
                      <?php echo file_get_contents(SC_ASSETS_DIR . '/img/icons/shop.svg');  ?>  
                    </a>
      
    </div>

    </header>
      <header class="custom-header header_bottom" >
       
        <div class="menus">
                <div class="menus_header" >
                   <?php  if(sc_get_setting('pro_feature_shop')){ ?>
                    <div class="megamenu-box">
                    
                        <div class="t-m">
                           
                         <?php echo file_get_contents(SC_ASSETS_DIR . '/img/icons/charkhone.svg');  ?>   <span>دسته بندی</span>
                        </div>
                        <div class="pishro-megamenu">
                            <?php wp_nav_menu( array( 'theme_location' => 'maga_menu_product' , 'container' => ''  ) ); ?>
                        </div>
                </div>
                <?php }

                ?>
                
                <nav class="menu_custom_gym menu-header menu-header_dl"><?php wp_nav_menu(array('theme_location'=>'main_menu_header')); ?></nav>
                <?php  if(sc_get_setting('sc_phone_club')){?>
                <a href="tel:<?php echo sc_get_setting('sc_phone_club'); ?>" class="header_phone"><?php  echo sc_get_setting('sc_phone_club');  echo file_get_contents(SC_ASSETS_DIR . '/img/icons/call.svg'); ?> </a>
                <?php } ?>
            </div>
                <nav class="menu_custom_gym menu-header_moblie "> <?php echo file_get_contents(SC_ASSETS_DIR . '/img/icons/hamberher-menu.svg');  ?></nav>
                

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
                    <nav class="menu_custom_gym_popup menu-header"><?php wp_nav_menu(array('theme_location'=>'main_menu_header')); ?></nav>

            </div>

  
  </div>

</div>
</div>

    <?php
}



