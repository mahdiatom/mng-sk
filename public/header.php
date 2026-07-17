<?php 
if ( ! defined('ABSPATH') ) exit;
add_action('wp_head', 'custom_header_output');
function custom_header_output() {
    // نمایش اطلاعیه عمومی بالای هدر
    if (function_exists('sc_render_public_announcement')) {
        sc_render_public_announcement();
    }

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
  .woo_panel_mini .count_unread_notif_mini ,
  .sc-panel-faq-item__toggle

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
.woocommerce-account,
.sc-portal-app {
    --sc-panel-primary: <?php echo $color_org; ?>;
    --sc-panel-primary-text: <?php echo $color_org_txt; ?>;
    --sc-panel-primary-soft: <?php echo $color_org; ?>1a;
    --sc-panel-gradient: linear-gradient(135deg, <?php echo $color_org; ?> 0%, <?php echo $color_org; ?>cc 55%, #a78bfa 100%);
}
</style>

    <?php
}

/**
 * رندر اطلاعیه عمومی شیک بالای هدر (سایت + پورتال)
 * - بدون عنوان
 * - ارتفاع عددی یا auto
 * - marquee با سرعت قابل تنظیم
 * - جلوه‌های ویژه + ripple همرنگ متن
 * - responsive موبایل (وسط‌چین)
 */
function sc_render_public_announcement() {
    $active_id = (int) get_option('sc_active_public_announcement_id', 0);
    if (!$active_id) return;

    $announcements = get_option('sc_public_announcements', []);
    if (!is_array($announcements)) return;

    $ann = null;
    foreach ($announcements as $a) {
        if ((int)$a['id'] === $active_id) { $ann = $a; break; }
    }
    if (!$ann) return;

    $bg = esc_attr($ann['bg_color'] ?? '#6D34FF');
    $txt = esc_attr($ann['text_color'] ?? '#fff');
    $img = !empty($ann['bg_image']) ? esc_url($ann['bg_image']) : '';
    $height_val = $ann['height'] ?? 'auto';
    $dismissable = !empty($ann['dismissable']);
    $marquee = !empty($ann['marquee']);
    $marquee_speed = isset($ann['marquee_speed']) ? max(5, (int)$ann['marquee_speed']) : 18;
    $effects = isset($ann['effects']) && is_array($ann['effects']) ? $ann['effects'] : [];
    $content = wp_kses_post($ann['content'] ?? '');

    $style = 'background-color: ' . $bg . '; color: ' . $txt . ';';
    if ($img) {
        $style .= ' background-image: linear-gradient(rgba(0,0,0,0.28), rgba(0,0,0,0.28)), url(\'' . $img . '\'); background-size: cover; background-position: center;';
    }

    $height_style = '';
    if ($height_val !== 'auto' && is_numeric($height_val)) {
        $height_style = 'min-height:' . (int)$height_val . 'px;';
    } else {
        $height_style = 'padding-top:14px; padding-bottom:14px;';
    }

    $effect_classes = '';
    foreach ($effects as $e) {
        $effect_classes .= ' sc-pa-effect-' . sanitize_html_class($e);
    }
    if ($marquee) $effect_classes .= ' sc-pa-marquee';

    ?>
    <style>
    .sc-public-announcement { font-family: IRANYekanXFaNum, sans-serif; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position:relative; z-index:99999; width:100%; display:flex; align-items:center; justify-content:center; }
    .sc-pa-inner { max-width:1200px; width:100%; padding:0 22px; display:flex; align-items:center; gap:16px; }
    .sc-pa-content { flex:1; font-size:0.97rem; line-height:1.55; opacity:0.97; display:flex; align-items:center; gap:14px; min-width:0; }
    .sc-pa-text { display:inline-block; }
    
    /* Ripple - مشکی، کوچیک و متحرک (موج پشت دایره) */
    .sc-pa-ripple { 
        position:relative; 
        width:18px; height:18px; 
        border-radius:50%; 
        background:#000; 
        opacity:0.75; 
        flex-shrink:0; 
        z-index:2;
        box-shadow: 0 0 0 1px rgba(0,0,0,0.15);
    }
    .sc-pa-ripple::before, 
    .sc-pa-ripple::after { 
        content:''; 
        position:absolute; 
        inset:-4px; 
        border-radius:50%; 
        background:#000; 
        opacity:0.28; 
        z-index:1; 
        animation:sc-ripple-wave 1.65s infinite ease-out; 
    }
    .sc-pa-ripple::after { animation-delay:0.55s; }
    @keyframes sc-ripple-wave { 
        0%{transform:scale(0.4); opacity:0.28;} 
        65%{transform:scale(1.9); opacity:0;} 
        100%{transform:scale(2.4); opacity:0;} 
    }
    
    /* Marquee - کامل از یک طرف خارج شود و از طرف دیگر وارد شود */
    .sc-pa-marquee { overflow: hidden; }
    .sc-pa-marquee .sc-pa-text { 
        white-space:nowrap; 
        animation:sc-marquee <?php echo (int)$marquee_speed; ?>s linear infinite;
        will-change: transform;
    }
    @keyframes sc-marquee { 
        0%   { transform: translateX(-100%); } 
        100% { transform: translateX(100%); } 
    }
    
    /* جلوه‌ها (با marquee تداخل کمتری داشته باشند) */
    .sc-pa-effect-pulse .sc-pa-text { animation:sc-text-pulse 2.2s ease-in-out infinite; }
    @keyframes sc-text-pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.025);} }
    
    .sc-pa-effect-glow .sc-pa-text { text-shadow: 0 0 8px currentColor, 0 0 18px currentColor; animation:sc-text-glow 2.5s ease-in-out infinite alternate; }
    @keyframes sc-text-glow { from { text-shadow:0 0 6px currentColor; } to { text-shadow:0 0 14px currentColor, 0 0 26px currentColor; } }
    
    .sc-pa-effect-shine .sc-pa-text { position:relative; overflow:hidden; }
    .sc-pa-effect-shine .sc-pa-text::after { content:''; position:absolute; top:-50%; left:-150%; width:45%; height:200%; background:linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent); animation:sc-text-shine 2.6s linear infinite; pointer-events:none; }
    @keyframes sc-text-shine { 0%{left:-150%;} 28%{left:220%;} 100%{left:220%;} }
    
    .sc-pa-effect-bounce .sc-pa-text { animation:sc-text-bounce 1.35s cubic-bezier(0.28,0.84,0.42,1) infinite; }
    @keyframes sc-text-bounce { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-3.5px);} }
    
    .sc-pa-effect-flash .sc-pa-text { animation:sc-text-flash 2.3s ease-in-out infinite; }
    @keyframes sc-text-flash { 0%,100%{opacity:1;} 42%{opacity:0.35;} 58%{opacity:0.35;} }
    
    .sc-pa-effect-swing .sc-pa-text { transform-origin:50% 10%; animation:sc-text-swing 2.05s ease-in-out infinite; }
    @keyframes sc-text-swing { 0%,100%{transform:rotate(0deg);} 22%{transform:rotate(1.8deg);} 78%{transform:rotate(-1.8deg);} }
    
    /* موبایل - رسپانسیو و وسط‌چین */
    @media (max-width: 640px) {
        .sc-pa-inner { padding: 0 14px; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .sc-pa-content { justify-content: center; text-align: center; }
        .sc-pa-ripple { order: -1; }
        .sc-pa-marquee .sc-pa-text { padding-right: 60px; }
    }
    </style>

    <div class="sc-public-announcement<?php echo esc_attr($effect_classes); ?>" style="<?php echo $style . $height_style; ?> color:<?php echo $txt; ?>; display:flex; align-items:center; justify-content:center;">
        <div class="sc-pa-inner">
            <span class="sc-pa-ripple" style="color:<?php echo $txt; ?>;" aria-hidden="true"></span>
            
            <div class="sc-pa-content">
                <span class="sc-pa-text"><?php echo $content; ?></span>
            </div>

            <?php if ($dismissable): ?>
            <button type="button" onclick="this.closest('.sc-public-announcement').style.display='none'; localStorage.setItem('sc_pa_dismissed_<?php echo (int)$active_id; ?>', Date.now());" 
                    style="background:rgba(255,255,255,0.18); border:none; color:inherit; width:30px; height:30px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; margin-right:4px;">×</button>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function(){
        var dismissed = localStorage.getItem('sc_pa_dismissed_<?php echo (int)$active_id; ?>');
        if (dismissed) {
            var el = document.querySelector('.sc-public-announcement');
            if (el) el.style.display = 'none';
        }
    })();
    </script>
    <?php
}




