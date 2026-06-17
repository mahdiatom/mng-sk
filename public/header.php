<?php 
if ( ! defined('ABSPATH') ) exit;
add_action('wp_head', 'custom_header_output');
function custom_header_output() {
    global $wpdb;
$unread = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications(get_current_user_id()) : 0;
 $members_table = $wpdb->prefix . 'sc_members';
 $user_id = get_current_user_id();
 $results = $wpdb->get_results(
    "SELECT CONCAT( first_name , ' ' , last_name) AS name , personal_photo AS photo , user_id , player_phone , identity_verified 
     FROM $members_table
     WHERE user_id = $user_id " , ARRAY_A
);
// مبلغ کل با تخفیف (مثلاً تخفیف کد تخفیف)

$count_product_card = get_cart_item_count()['count'];
$sum_price_card = get_cart_item_count()['sum'];
$color_org = sc_get_setting('sc_org_bg_color', '#6D34FF');
$color_org_txt = sc_get_setting('sc_txt_bg_color', '#6D34FF');



$unread_ticket = sc_count_user_tickets(get_current_user_id(), 'pending_reply');
$player_for_access = function_exists('sc_get_current_member_for_account_user') ? sc_get_current_member_for_account_user() : null;
$verification_gate_locked = function_exists('sc_is_member_verification_gate_enabled_for_user') ? sc_is_member_verification_gate_enabled_for_user($player_for_access) : false;

    $sc_hs_placeholder = esc_attr( sc_get_setting( 'sc_header_search_placeholder', 'جستجو در خدمات، صفحات و فروشگاه…' ) );
    $sc_search_svg       = '<svg class="sc-header-search__icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    ?>
    <header class="custom-header header_top" >
        
            
        <div class="logo_custom_gym">
            <img src="<?php echo sc_get_setting('sc_club_logo_url'); ?>" alt="">
        
        </div>

        <div class="sc-header-search sc-header-search--desktop" role="search">
            <div class="sc-header-search__box">
                <?php echo $sc_search_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <input type="search" class="sc-header-search__input" placeholder="<?php echo $sc_hs_placeholder; ?>" autocomplete="off" aria-autocomplete="list" aria-expanded="false" />
                <div class="sc-header-search__dropdown" hidden></div>
            </div>
        </div>

        <button type="button" class="sc-header-search-toggle sc-header-search--mobile" aria-label="<?php esc_attr_e( 'جستجو', 'sportclub-manager' ); ?>">
            <?php echo $sc_search_svg;  ?>
        </button>
  
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
                                <span href="#"> <?php echo $results[0]['name'] ?? ''; ?></span>
                                <span href="#"> <?php echo $results[0]['player_phone'] ?? ''; ?></span>
                            </div>
                            
                        </li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-dashboard" class="sc-dashboard">  پیشخوان من  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-submit-documents" class="sc-submit-documents"> اطلاعات بازیکن  </a></li>
                        <?php if(sc_get_setting('pro_feature_shop')){ ?>
                        <li><a href="<?php echo home_url('/'); ?>shop" class="shop"> فروشگاه  </a></li>
                        <li><a href="<?php echo home_url('/'); ?>my-account/my-orders" class="my-orders"> سفارش های فروشگاه </a></li>
                        <?php } ?>
                        <?php if (!$verification_gate_locked) : ?>
                        <li><a href="<?php echo home_url('my-account/'); ?>edit-account#account_display_name" class="edit-account"> تغییر رمز ورود </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>bot-connect" class="bot-connect">اتصال به ربات </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-enroll-course" class="sc-enroll-course"> ثبت نام دوره  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-private-classes" class="sc-private-classes"> کلاس خصوصی </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-courses" class="sc-my-courses">  دوره های من + برنامه هفتگی </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-attendances" class="sc-my-attendances"> حضور و غیاب های من </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-events" class="sc-events"> رویداد / مسابقات</a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-events" class="sc-my-events"> رویداد های من  </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-invoices" class="sc-invoices"> صورتحساب </a></li>
                        <?php endif; ?>
                        <?php if (!$verification_gate_locked) : ?>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-honors" class="sc-my-honors">افتخارات من </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-my-certificates" class="sc-my-certificates">گواهینامه های من </a></li>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-private-notes" class="sc-private-notes">یادداشت های من </a></li>
                        <?php if(sc_get_setting('pro_feature_notifications')){ ?>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-notifications" class="sc-notifications"> اطلاعیه ها <?php  if($unread > 0) { echo '<span class="count_unread_notif_mini">' . $unread .'</span>' ;} ?> </a></li>
                        <?php }
                         if(sc_get_setting('pro_feature_players_wallet')){?>
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-wallet" class="sc-wallet"> کیف پول </a></li>
                        <?php } ?>
                
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-support-tickets" class="sc-support-tickets">  تیکت پشتیبانی  <?php if($unread_ticket > 0) {  echo '<span class="count_unread_notif_mini">' . $unread_ticket .'</span>' ; } ?> </a></li>
                     
                        <li><a href="<?php echo home_url('my-account/'); ?>sc-faq" class="sc-faq"> سوالات متداول </a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo wp_logout_url( wc_get_page_permalink( 'myaccount' ) ); ?>  " onclick=\"return scConfirmInline(event, { type: 'warning', message: 'شما در حال خروج از حساب کاربری هستید از این کار اطمنیان دارید؟' })\" >خروج از حساب کاربری</a></li>    
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
          <?php if(is_user_logged_in() && sc_get_setting('pro_feature_notifications') && !$verification_gate_locked ){ ?>
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

    <div id="sc-header-search-popup" class="sc-header-search-popup" aria-hidden="true">
        <div class="sc-header-search-popup__backdrop" tabindex="-1"></div>
        <div class="sc-header-search-popup__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'جستجو', 'sportclub-manager' ); ?>">
            <button type="button" class="sc-header-search-popup__close" aria-label="<?php esc_attr_e( 'بستن', 'sportclub-manager' ); ?>">&times;</button>
            <div class="sc-header-search sc-header-search--popup" role="search">
                <div class="sc-header-search__box">
                    <?php echo $sc_search_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <input type="search" class="sc-header-search__input sc-header-search__input--popup" placeholder="<?php echo $sc_hs_placeholder; ?>" autocomplete="off" aria-autocomplete="list" />
                    <div class="sc-header-search__dropdown" hidden></div>
                </div>
            </div>
        </div>
    </div>

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
<style>
.custom-header ,
 .woocommerce-MyAccount-navigation
  , .custom-footer , .button ,.button-primary , .sc-dashboard-btn , a.details_info_user_pannel ,
  .wp-block-woocommerce-checkout-order-summary-block
  ,.wc-block-components-checkout-place-order-button , .wc-block-components-address-card__edit,
  .woocommerce .woocommerce-cart-form button ,.woocommerce .calculated_shipping,
  .woocommerce .wc-empty-cart-message .woocommerce-info,
  .woocommerce .return-to-shop .wc-backward,
  .woocommerce .woocommerce-form-coupon-toggle .woocommerce-info,
.products .product .button,
.woocommerce-message,
.form-submit #submit,
.woocommerce-tabs ul .active,
.single_add_to_cart_button,
.wc-block-grid__product-add-to-cart a,
.sc-dashboard-progress-bar,
.sc-my-certificate-row-action .button.sc-my-certificate-action,
.table_privet_note thead tr,
.woocommerce-info a,
#button_filter_custom,
.honor-row .remove-row,
button[name="save_honors"],
.sc_paginate .tablenav-pages .current,
#order_review .shop_table,
.woocommerce-order-received .button.alt:hover,
.woocommerce-thankyou-order-received .button.alt:hover,
.woocommerce-order-received .button-primary,
.woocommerce-thankyou-order-received .button-primary,
.woocommerce-order-received .woocommerce-thankyou-order-received,
.woocommerce-thankyou-order-received .woocommerce-thankyou-order-received,
.sc-thankyou-page .woocommerce-table--order-details thead ,
.sc-thankyou-btn-secondary:hover,
.sc-thankyou-btn-primary,
.button_filter_custom,
.woocommerce-table--order-details thead,
.sc-invoice-btn-pay,
.sc-invoices-table thead,
.sc-enroll-event-btn,
.sc-enroll-course-filters button , 
.sc-my-courses-filters button ,
 .sc-events-filters button,
 .sc-invoices-filters button,
 .sc-event-action-btn,
 .form-row button , .sc-event-success-page a,
 .sc-bg-purple ,
  .sc-my-events-content .sc-mev-btn-primary ,
  .wc-block-components-button,
  .sc-private-notes-user .sc-pn-btn-primary,
  .sc-ticket-btn-primary,
  .sc-support-tabs .active,
  .sc-notification-btn-primary,
  .sc-notif-tabs .active,
  .woocommerce-tabs .wc-tab ,
  .custom-footer a ,
  .sc-notifications-pagination span,
  .sc-thankyou-header,
  .sc-order-pay-submit,
  .sc-order-pay-page #place_order,
  .woo_panel_mini .count_unread_notif_mini 

 {
 
    background-color: <?php echo $color_org;  ?> !important;
     color:<?php echo $color_org_txt;  ?>!important;
   
    
}
 .modal-menu_mobile{
        background-color: <?php echo $color_org;  ?>a6  !important;
        border: 1px solid <?php echo $color_org_txt;  ?>!important;
;

 }
.menu-header_dl .menu > li > a,
 .menus .header_phone ,
a.details_info_user_pannel,
.woocommerce-MyAccount-navigation ul li a,
.megamenu-box span


{
     color:<?php echo $color_org_txt;  ?>!important;
    
}

.sc-section-title{
    border-bottom:3px solid <?php echo $color_org;  ?> !important;
}
.sc-user-avatar, .sc-user-avatar_megamenu{
        border:3px solid <?php echo $color_org;  ?> !important;

}
</style>

    <?php
}




