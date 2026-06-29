<?php
/**
 * Player panel portal – /portal/{tab}/
 * Reuses the same tab content handlers and templates as WooCommerce My Account.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the current request is the new /portal/ panel.
 */
function sc_is_portal_page() {
    return (bool) get_query_var('sc_portal');
}

/**
 * My Account page or /portal/ panel (shared tab logic, assets, POST handlers).
 */
function sc_is_player_panel_context() {
    if (function_exists('is_account_page') && is_account_page()) {
        return true;
    }
    return sc_is_portal_page();
}

/**
 * URL for a panel tab on /portal/.
 */
function sc_portal_endpoint_url($slug) {
    if ($slug === 'shop') {
        return home_url('/shop/');
    }
    if ($slug === 'customer-logout') {
        return wp_logout_url(home_url('/'));
    }
    return trailingslashit(home_url('/portal/' . $slug));
}

/**
 * Panel tab URL – portal when on portal, otherwise WooCommerce My Account.
 */
function sc_panel_endpoint_url($slug) {
    if (sc_is_portal_page()) {
        return sc_portal_endpoint_url($slug);
    }
    if (isset($_COOKIE['sc_panel_mode']) && $_COOKIE['sc_panel_mode'] === 'portal') {
        return sc_portal_endpoint_url($slug);
    }
    if (function_exists('wc_get_account_endpoint_url')) {
        return wc_get_account_endpoint_url($slug);
    }
    return sc_portal_endpoint_url($slug);
}

/**
 * Same menu items as WooCommerce My Account sidebar.
 */
function sc_get_player_panel_menu_items() {
    $base = ['customer-logout' => __('Logout', 'woocommerce')];
    $items = apply_filters('woocommerce_account_menu_items', $base);
    return is_array($items) ? $items : [];
}

/**
 * Premium section hero header (shared portal + my-account templates).
 */
function sc_panel_render_section_hero($title, $desc = '', $type = 'default') {
    $type = sanitize_key($type);
    ?>
    <div class="sc-panel-section__hero sc-panel-section__hero--<?php echo esc_attr($type); ?>">
        <div class="sc-panel-section__hero-glow" aria-hidden="true"></div>
        <div class="sc-panel-section__hero-inner">
            <span class="sc-panel-section__hero-icon" aria-hidden="true"></span>
            <div class="sc-panel-section__hero-text">
                <h2 class="sc-panel-section__title"><?php echo esc_html($title); ?></h2>
                <?php if ($desc !== '') : ?>
                    <p class="sc-panel-section__desc"><?php echo esc_html($desc); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Title + description + hero icon type for each panel tab.
 * Tabs that already render their own hero inside the template
 * (submit-documents, faq, support-tickets, edit-account) are excluded.
 * Dashboard keeps its personalized greeting and is excluded too.
 */
function sc_panel_get_tab_hero_map() {
    return apply_filters('sc_panel_tab_hero_map', [
        'sc-enroll-course'   => ['ثبت‌نام در دوره', 'دوره مورد نظر را انتخاب کنید، شعبه و مربی را مشخص کنید و ثبت‌نام را تکمیل نمایید.', 'enroll'],
        'sc-my-courses'      => ['دوره‌های من و برنامه هفتگی', 'دوره‌های ثبت‌نامی و برنامه تمرینی هفتگی خود را در این بخش مشاهده کنید.', 'courses'],
        'sc-my-attendances'  => ['حضور و غیاب من', 'لیست جلسات ثبت‌شده همراه با وضعیت حضور، شعبه و مربی شما.', 'attendances'],
        'sc-invoices'        => ['صورت‌حساب‌ها', 'لیست پرداخت‌های دوره، رویداد و سایر هزینه‌های شما در باشگاه.', 'invoices'],
        'my-orders'          => ['سفارش‌های فروشگاه', 'لیست سفارش‌های ثبت‌شده شما در فروشگاه باشگاه.', 'orders'],
        'sc-events'          => ['رویدادها و مسابقات', 'رویدادها و مسابقات فعال باشگاه را مشاهده و در آن‌ها ثبت‌نام کنید.', 'events'],
        'sc-my-events'       => ['رویدادهای من', 'رویدادهایی که در آن‌ها ثبت‌نام کرده‌اید، همراه با زمان و محل برگزاری.', 'events'],
        'sc-wallet'          => ['کیف پول من', 'موجودی، شارژ و تراکنش‌های کیف پول خود را مدیریت کنید.', 'wallet'],
        'sc-notifications'   => ['اطلاعیه‌ها', 'آخرین اطلاعیه‌ها و پیام‌های باشگاه را در این بخش دنبال کنید.', 'notifications'],
        'sc-surveys'         => ['نظرسنجی‌ها', 'در نظرسنجی‌های فعال باشگاه شرکت کنید و دیدگاه خود را ثبت کنید.', 'surveys'],
        'sc-private-notes'   => ['یادداشت‌های من', 'گفتگوها و یادداشت‌های خصوصی شما با کادر باشگاه.', 'notes'],
        'sc-private-classes' => ['کلاس‌های خصوصی', 'درخواست و پیگیری جلسات کلاس خصوصی خود را انجام دهید.', 'private'],
        'sc-my-honors'       => ['افتخارات من', 'افتخارات و دستاوردهای ورزشی ثبت‌شده برای شما.', 'honors'],
        'sc-my-certificates' => ['گواهینامه‌های من', 'گواهینامه‌های صادرشده خود را مشاهده و دانلود کنید.', 'certificates'],
    ]);
}

/**
 * Register the shared hero on every mapped tab endpoint (portal + my-account).
 */
add_action('init', 'sc_panel_register_tab_heroes', 20);
function sc_panel_register_tab_heroes() {
    foreach (array_keys(sc_panel_get_tab_hero_map()) as $tab) {
        add_action('woocommerce_account_' . $tab . '_endpoint', 'sc_panel_render_current_tab_hero', 1);
    }
}

/**
 * Render the org-colored hero for the tab whose endpoint action is firing.
 */
function sc_panel_render_current_tab_hero() {
    static $done = [];
    $action = current_action();
    $tab = preg_replace('/^woocommerce_account_(.+)_endpoint$/', '$1', $action);
    if ($tab === '' || isset($done[$tab])) {
        return;
    }
    $done[$tab] = true;
    $map = sc_panel_get_tab_hero_map();
    if (!isset($map[$tab]) || !function_exists('sc_panel_render_section_hero')) {
        return;
    }
    list($title, $desc, $type) = $map[$tab];
    echo '<div class="sc-panel-tab-hero">';
    sc_panel_render_section_hero($title, $desc, $type);
    echo '</div>';
}

/**
 * Icon map for portal sidebar (existing SVG assets).
 */
function sc_portal_menu_icon($slug) {
    $map = [
        'sc-dashboard'        => 'home.svg',
        'sc-submit-documents' => 'user.svg',
        'sc-enroll-course'    => 'plus-circle.svg',
        'sc-my-courses'       => 'layers.svg',
        'sc-my-attendances'   => 'dafter.svg',
        'sc-events'           => 'adv.svg',
        'sc-my-events'        => 'adv.svg',
        'sc-invoices'         => 'dafter.svg',
        'shop'                => 'shop.svg',
        'my-orders'           => 'cartbag.svg',
        'sc-my-honors'        => 'member.svg',
        'sc-my-certificates'  => 'folder.svg',
        'sc-notifications'    => 'massege.svg',
        'sc-surveys'          => 'dafter.svg',
        'sc-wallet'           => 'wallet.svg',
        'sc-support-tickets'  => 'ticket-1.svg',
        'sc-private-notes'    => 'dafter.svg',
        'sc-private-classes'  => 'member.svg',
        'sc-faq'              => 'dafter.svg',
        'bot-connect'         => 'massege.svg',
        'customer-logout'     => 'login.svg',
        'edit-account'        => 'setting.svg',
        'downloads'           => 'folder.svg',
    ];
    $file = isset($map[$slug]) ? $map[$slug] : 'home.svg';
    $path = SC_ASSETS_DIR . 'img/icons/' . $file;
    if (!file_exists($path)) {
        return '';
    }
    return file_get_contents($path);
}


/**
 * Quick links shown in the profile chip dropdown.
 */
function sc_portal_get_profile_dropdown_links($verification_gate_locked = false) {
    $links = [
        ['slug' => 'sc-submit-documents', 'label' => 'اطلاعات من'],
    ];
    if (!$verification_gate_locked) {
        $links[] = ['slug' => 'sc-my-courses', 'label' => 'دوره ها + برنامه هفتگی من'];
        if (sc_get_setting('pro_feature_shop')) {
            $links[] = ['slug' => 'my-orders', 'label' => 'سفارش های من'];
        }
        $links[] = ['slug' => 'sc-my-events', 'label' => 'رویداد های من'];
        $links[] = ['slug' => 'sc-private-notes', 'label' => 'یادداشت های من'];
    }
    $links[] = ['slug' => 'edit-account', 'label' => 'تغییر رمز ورود', 'hash' => '#password_current'];
    $links[] = ['slug' => 'home', 'label' => 'بازگشت به سایت', 'url' => home_url('/')];
    $links[] = ['slug' => 'customer-logout', 'label' => 'خروج از پنل', 'class' => 'is-logout'];
    return $links;
}

/**
 * Whether a portal menu slug should show in header tab dropdown.
 */
function sc_portal_header_tab_visible($slug, $verification_gate_locked = false) {
    if ($slug === 'customer-logout') {
        return true;
    }
    $locked_slugs = [
        'edit-account', 'bot-connect', 'sc-enroll-course', 'sc-private-classes',
        'sc-my-courses', 'sc-my-attendances', 'sc-events', 'sc-my-events', 'sc-invoices',
        'sc-my-honors', 'sc-my-certificates', 'sc-private-notes', 'sc-notifications',
        'sc-wallet', 'sc-support-tickets', 'sc-faq', 'sc-surveys',
    ];
    if ($verification_gate_locked && in_array($slug, $locked_slugs, true)) {
        return false;
    }
    if ($slug === 'sc-notifications' && !(function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled())) {
        return false;
    }
    if ($slug === 'sc-wallet' && !sc_get_setting('pro_feature_players_wallet')) {
        return false;
    }
    if (in_array($slug, ['shop', 'my-orders'], true) && !sc_get_setting('pro_feature_shop')) {
        return false;
    }
    return true;
}

/**
 * Whether a specific tab is active (portal or My Account).
 */
function sc_panel_active_tab_is($tab) {
    if (sc_is_portal_page()) {
        return sc_portal_get_current_tab() === $tab;
    }
    return get_query_var($tab, false) !== false;
}

add_action('init', 'sc_portal_register_rewrite_rules');
function sc_portal_register_rewrite_rules() {
    add_rewrite_rule('^portal/?$', 'index.php?sc_portal=1', 'top');
    add_rewrite_rule('^portal/([^/]+)/?$', 'index.php?sc_portal=1&sc_portal_tab=$matches[1]', 'top');

    if (get_option('sc_portal_rewrite_flushed') !== 'yes') {
        flush_rewrite_rules(false);
        update_option('sc_portal_rewrite_flushed', 'yes');
    }
}

add_filter('query_vars', 'sc_portal_query_vars');
function sc_portal_query_vars($vars) {
    $vars[] = 'sc_portal';
    $vars[] = 'sc_portal_tab';
    return $vars;
}

add_filter('body_class', 'sc_portal_body_class');
function sc_portal_body_class($classes) {
    if (!sc_is_portal_page()) {
        return $classes;
    }
    $classes[] = 'sc-portal-page';
    $classes[] = 'woocommerce-account';
    $tab = sc_portal_get_current_tab();
    if ($tab) {
        $classes[] = 'sc-portal-tab-' . sanitize_html_class($tab);
    }
    return $classes;
}

add_filter('redirect_canonical', 'sc_portal_preserve_filter_query_args', 10, 2);
function sc_portal_preserve_filter_query_args($redirect_url, $requested_url) {
    if (!sc_is_portal_page()) {
        return $redirect_url;
    }
    $preserve_keys = [
        'filter_course',
        'filter_date_from',
        'filter_date_to',
        'filter_date_from_shamsi',
        'filter_date_to_shamsi',
        'filter_status',
        'invoice_search',
        'course_search',
        'pag',
        'event_id',
        'cancel_invoice',
        'invoice_id',
        'cancel_order',
        'order_id',
        'pay_from_wallet',
        '_wpnonce',
    ];
    foreach ($preserve_keys as $key) {
        if (array_key_exists($key, $_GET)) {
            return false;
        }
    }
    return $redirect_url;
}

/**
 * Active portal tab slug.
 */
function sc_portal_get_current_tab() {
    $tab = get_query_var('sc_portal_tab');
    if (empty($tab)) {
        return 'sc-dashboard';
    }
    return sanitize_key($tab);
}

add_action('template_redirect', 'sc_portal_template_redirect', 4);
function sc_portal_template_redirect() {
    if (!sc_is_portal_page()) {
        return;
    }

    if (!is_user_logged_in()) {
        $login_page_id = function_exists('sc_get_setting') ? (int) sc_get_setting('sc_login_page_id', 0) : 0;
        if ($login_page_id > 0) {
            $login_page_url = get_permalink($login_page_id);
            if ($login_page_url) {
                wp_safe_redirect($login_page_url);
                exit;
            }
        }
        wp_safe_redirect(wp_login_url(sc_portal_endpoint_url(sc_portal_get_current_tab())));
        exit;
    }

    if (current_user_can('manage_options')) {
        wp_safe_redirect(admin_url());
        exit;
    }

    $tab = sc_portal_get_current_tab();

    if ($tab === 'shop') {
        wp_safe_redirect(home_url('/shop/'), 301);
        exit;
    }

    if ($tab === 'customer-logout') {
        wp_logout();
        wp_safe_redirect(home_url('/'));
        exit;
    }

    if (!headers_sent()) {
        setcookie('sc_panel_mode', 'portal', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }

    $menu_items = sc_get_player_panel_menu_items();
    $action     = 'woocommerce_account_' . $tab . '_endpoint';
    if (!isset($menu_items[$tab]) && !has_action($action)) {
        wp_safe_redirect(sc_portal_endpoint_url('sc-dashboard'));
        exit;
    }

    sc_portal_render_page($tab, $menu_items);
    exit;
}

/**
 * Render portal shell and tab content.
 */
function sc_portal_render_page($tab, $menu_items) {
    $color_org     = sc_get_setting('sc_org_bg_color', '#6D34FF');
    $color_org_txt = sc_get_setting('sc_txt_bg_color', '#FFFFFF');
    $club_logo     = sc_get_setting('sc_club_logo_url', '');
    $tab_label     = isset($menu_items[$tab]) ? $menu_items[$tab] : '';
    if ($tab_label === '') {
        $tab_label = apply_filters('woocommerce_endpoint_' . $tab . '_title', '');
    }
    $tab_label_clean = wp_strip_all_tags($tab_label);

    $current_user = wp_get_current_user();
    $player       = function_exists('sc_get_current_member_for_account_user') ? sc_get_current_member_for_account_user() : null;
    $avatar       = '';
    if ($player && !empty($player->personal_photo)) {
        $avatar = esc_url($player->personal_photo);
    } else {
        $avatar = get_avatar_url(get_current_user_id(), ['size' => 80]);
    }
    $display_name = $current_user->display_name;
    if ($player) {
        $name = trim($player->first_name . ' ' . $player->last_name);
        if ($name !== '') {
            $display_name = $name;
        }
    }

    $unread_notif = function_exists('sc_count_unread_notifications')
        ? (int) sc_count_unread_notifications(get_current_user_id())
        : 0;
    $unread_ticket = function_exists('sc_count_user_tickets')
        ? (int) sc_count_user_tickets(get_current_user_id(), 'pending_reply')
        : 0;

    $verification_gate_locked = function_exists('sc_is_member_verification_gate_enabled_for_user')
        ? sc_is_member_verification_gate_enabled_for_user($player)
        : false;
    $player_level = ($player && !empty($player->skill_level)) ? trim((string) $player->skill_level) : '';
    $identity_verified = $player && !empty($player->identity_verified);
    $identity_label = $identity_verified ? __('احراز شده', 'sportclub-manager') : __('در انتظار احراز', 'sportclub-manager');
    $identity_class = $identity_verified ? 'is-verified' : 'is-pending';
    $cart_count = function_exists('get_cart_item_count') ? (int) get_cart_item_count()['count'] : 0;
    $cart_sum   = function_exists('get_cart_item_count') ? (string) get_cart_item_count()['sum'] : '';
    $user_icon_svg = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.9"/><path d="M4.5 20c1.4-3.4 4.4-5.1 7.5-5.1s6.1 1.7 7.5 5.1" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>';
    $shop_icon_svg = file_exists(SC_ASSETS_DIR . 'img/icons/shop.svg')
        ? file_get_contents(SC_ASSETS_DIR . 'img/icons/shop.svg')
        : '';
    $cat_icon_svg = file_exists(SC_ASSETS_DIR . 'img/icons/charkhone.svg')
        ? file_get_contents(SC_ASSETS_DIR . 'img/icons/charkhone.svg')
        : '';
    $has_cat_menu  = function_exists('has_nav_menu') && has_nav_menu('maga_menu_product') && sc_get_setting('pro_feature_shop');
    $has_main_menu = function_exists('has_nav_menu') && has_nav_menu('main_menu_header');

    $nav_main  = [];
    $nav_logout = null;
    foreach ($menu_items as $slug => $label) {
        if ($slug === 'customer-logout') {
            $nav_logout = ['slug' => $slug, 'label' => $label];
            continue;
        }
        $nav_main[$slug] = $label;
    }

    get_header();
    ?>
    <div class="sc-portal-app" style="--sc-portal-primary: <?php echo esc_attr($color_org); ?>; --sc-portal-primary-text: <?php echo esc_attr($color_org_txt); ?>; --sc-portal-primary-mid: <?php echo esc_attr($color_org); ?>;">
        <header class="sc-portal-header">
            <div class="sc-portal-header__start">
                <h1 class="sc-portal-header__title"><?php echo esc_html($tab_label_clean); ?></h1>
                <span class="sc-portal-header__crumb">
                    <?php esc_html_e('پنل کاربری', 'sportclub-manager'); ?>
                    <span> / <?php echo esc_html($tab_label_clean); ?></span>
                </span>
            </div>
            <div class="sc-portal-header__end">

                <div class="sc-portal-header__actions">
                    

                    <div class="sc-portal-header__menu sc-portal-header__menu--profile">
                        <button type="button" class="sc-portal-header__profile sc-portal-header__menu-trigger" aria-expanded="false" aria-haspopup="true">
                            <div class="info_user_portal">
                                <img src="<?php echo esc_url($avatar); ?>" alt="" class="sc-portal-header__avatar" width="36" height="36" />
                                <span class="sc-portal-header__profile-info">
                                    <span class="sc-portal-header__name"><?php echo esc_html($display_name); ?></span>
                                    <span class="sc-portal-header__meta">
                                        <?php if ($player_level !== '') : ?>
                                        <?php endif; ?>
                                        <span class="sc-portal-header__verify <?php echo esc_attr($identity_class); ?>"><?php echo esc_html($identity_label); ?></span>
                                    </span>
                                </span>
                            </div>
                            <span class="sc-portal-header__menu-caret sc-portal-header__menu-caret--profile" aria-hidden="true"></span>
                        </button>
                        <div class="sc-portal-header__dropdown sc-portal-header__dropdown--profile" hidden>
                            <div class="sc-portal-header__dropdown-head">
                                <img src="<?php echo esc_url($avatar); ?>" alt="" class="sc-portal-header__dropdown-avatar" width="48" height="48" />
                                <div>
                                    <strong class="sc-portal-header__dropdown-name"><?php echo esc_html($display_name); ?></strong>
                                    <?php if ($player_level !== '') : ?>
                                        <span class="sc-portal-header__dropdown-phone"><?php echo esc_html(sprintf(__('سطح: %s', 'sportclub-manager'), $player_level)); ?></span>
                                    <?php endif; ?>
                                    <span class="sc-portal-header__dropdown-role <?php echo esc_attr($identity_class); ?>"><?php echo esc_html($identity_label); ?></span>
                                </div>
                            </div>
                            <ul class="sc-portal-header__dropdown-list sc-portal-header__dropdown-list--quick">
                                <?php foreach (sc_portal_get_profile_dropdown_links($verification_gate_locked) as $link) : ?>
                                    <?php
                                    $href = !empty($link['url']) ? $link['url'] : sc_portal_endpoint_url($link['slug']);
                                    if (!empty($link['hash'])) {
                                        $href .= $link['hash'];
                                    }
                                    $item_class = 'sc-portal-header__dropdown-item sc-portal-header__dropdown-item--' . sanitize_html_class($link['slug']);
                                    if (!empty($link['class'])) {
                                        $item_class .= ' ' . sanitize_html_class($link['class']);
                                    }
                                    $onclick = ($link['slug'] === 'customer-logout')
                                        ? ' onclick="return typeof scConfirmInline === \'function\' ? scConfirmInline(event, { type: \'warning\', message: \'شما در حال خروج از پنل هستید؛ از این کار اطمینان دارید؟\' }) : true;"'
                                        : '';
                                    ?>
                                    <li class="<?php echo esc_attr($item_class); ?>">
                                        <a href="<?php echo esc_url($href); ?>"<?php echo $onclick; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($link['label']); ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($has_cat_menu || $has_main_menu) : ?>
        <nav class="sc-portal-subnav" aria-label="<?php esc_attr_e('منوی سایت', 'sportclub-manager'); ?>">
            <button type="button" class="sc-portal-subnav__toggle" aria-expanded="false" aria-controls="sc-portal-subnav-inner">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
                <span><?php esc_html_e('منوی سایت', 'sportclub-manager'); ?></span>
            </button>
            <div class="sc-portal-subnav__inner" id="sc-portal-subnav-inner">
                <?php if ($has_cat_menu) : ?>
                <div class="sc-portal-megamenu">
                    <button type="button" class="sc-portal-megamenu__trigger" aria-expanded="false">
                        <span class="sc-portal-megamenu__icon" aria-hidden="true"><?php echo $cat_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span><?php esc_html_e('دسته‌بندی', 'sportclub-manager'); ?></span>
                        <span class="sc-portal-megamenu__caret" aria-hidden="true"></span>
                    </button>
                    <div class="sc-portal-megamenu__panel">
                        <?php wp_nav_menu(array(
                            'theme_location' => 'maga_menu_product',
                            'container'      => '',
                            'menu_class'     => 'sc-portal-megamenu__list',
                            'fallback_cb'    => '__return_empty_string',
                        )); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($has_main_menu) : ?>
                <div class="sc-portal-mainmenu">
                    <?php wp_nav_menu(array(
                        'theme_location' => 'main_menu_header',
                        'container'      => '',
                        'menu_class'     => 'sc-portal-mainmenu__list',
                        'fallback_cb'    => '__return_empty_string',
                    )); ?>
                </div>
                <?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>

        <div class="sc-portal-layout">
            <aside class="sc-portal-sidebar" aria-label="<?php esc_attr_e('منوی پنل', 'sportclub-manager'); ?>">
                <div class="sc-portal-sidebar__brand">
                    <?php if ($club_logo) : ?>
                        <img src="<?php echo esc_url($club_logo); ?>" alt="" />
                    <?php endif; ?>
                    <div>
                        <span class="sc-portal-sidebar__brand-text"><?php echo esc_html(sc_get_setting('sc_name_club', 'پنل بازیکن')); ?></span>
                        <span class="sc-portal-sidebar__brand-sub"><?php esc_html_e('پنل کاربری', 'sportclub-manager'); ?></span>
                    </div>
                </div>
                <nav class="sc-portal-nav">
                    <ul>
                        <?php foreach ($nav_main as $slug => $label) : ?>
                            <?php
                            $is_active = ($slug === $tab);
                            $url       = sc_portal_endpoint_url($slug);
                            $icon      = sc_portal_menu_icon($slug);
                            ?>
                            <li class="sc-portal-nav__item sc-portal-nav__item--<?php echo esc_attr($slug); ?><?php echo $is_active ? ' is-active' : ''; ?>">
                                <a href="<?php echo esc_url($url); ?>">
                                    <?php if ($icon) : ?>
                                        <span class="sc-portal-nav__icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <?php endif; ?>
                                    <span class="sc-portal-nav__label"><?php echo esc_html(wp_strip_all_tags($label)); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($nav_logout) : ?>
                            <li class="sc-portal-nav__item sc-portal-nav__item--customer-logout">
                                <a href="<?php echo esc_url(sc_portal_endpoint_url('customer-logout')); ?>">
                                    <?php $logout_icon = sc_portal_menu_icon('customer-logout'); ?>
                                    <?php if ($logout_icon) : ?>
                                        <span class="sc-portal-nav__icon" aria-hidden="true"><?php echo $logout_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <?php endif; ?>
                                    <span class="sc-portal-nav__label"><?php echo esc_html(wp_strip_all_tags($nav_logout['label'])); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </aside>

            <main class="sc-portal-main">
                <div class="sc-portal-content ">
                    <?php sc_portal_render_tab_content($tab); ?>
                </div>
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var menus = document.querySelectorAll('.sc-portal-header__menu');
        menus.forEach(function (menu) {
            var trigger = menu.querySelector('.sc-portal-header__menu-trigger');
            var panel = menu.querySelector('.sc-portal-header__dropdown');
            if (!trigger || !panel) {
                return;
            }
            function closeMenu() {
                menu.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('hidden', 'hidden');
            }
            function openMenu() {
                menus.forEach(function (other) {
                    if (other !== menu) {
                        var ot = other.querySelector('.sc-portal-header__menu-trigger');
                        var op = other.querySelector('.sc-portal-header__dropdown');
                        if (ot && op) {
                            other.classList.remove('is-open');
                            ot.setAttribute('aria-expanded', 'false');
                            op.setAttribute('hidden', 'hidden');
                        }
                    }
                });
                menu.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                panel.removeAttribute('hidden');
            }
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (menu.classList.contains('is-open')) {
                    closeMenu();
                } else {
                    openMenu();
                }
            });
            menu.addEventListener('mouseenter', function () {
                if (window.matchMedia('(min-width: 901px)').matches) {
                    openMenu();
                }
            });
            menu.addEventListener('mouseleave', function () {
                if (window.matchMedia('(min-width: 901px)').matches) {
                    closeMenu();
                }
            });
        });
        document.addEventListener('click', function () {
            menus.forEach(function (menu) {
                var trigger = menu.querySelector('.sc-portal-header__menu-trigger');
                var panel = menu.querySelector('.sc-portal-header__dropdown');
                if (!trigger || !panel) {
                    return;
                }
                menu.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('hidden', 'hidden');
            });
        });
        document.querySelectorAll('.sc-portal-header__dropdown').forEach(function (panel) {
            panel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });

        // Secondary site nav (categories + main menu)
        var subnav = document.querySelector('.sc-portal-subnav');
        if (subnav) {
            var subToggle = subnav.querySelector('.sc-portal-subnav__toggle');
            if (subToggle) {
                subToggle.addEventListener('click', function () {
                    var open = subnav.classList.toggle('is-open');
                    subToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            }
            var megaTrigger = subnav.querySelector('.sc-portal-megamenu__trigger');
            var mega = subnav.querySelector('.sc-portal-megamenu');
            if (megaTrigger && mega) {
                megaTrigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var open = mega.classList.toggle('is-open');
                    megaTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.addEventListener('click', function (e) {
                    if (!mega.contains(e.target)) {
                        mega.classList.remove('is-open');
                        megaTrigger.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        }
    });
    </script>
    <?php
    get_footer();
}

/**
 * Invoke the same WooCommerce endpoint action used by My Account tabs.
 */
function sc_portal_render_tab_content($tab) {
    if (function_exists('sc_display_incomplete_profile_message')) {
        sc_display_incomplete_profile_message();
    }
    if (function_exists('wc_print_notices')) {
        wc_print_notices();
    }

    $action = 'woocommerce_account_' . $tab . '_endpoint';
    if (has_action($action)) {
        do_action($action);
        return;
    }

    echo '<div class="sc-portal-notice sc-portal-notice--error">';
    echo esc_html__('صفحه درخواستی یافت نشد.', 'sportclub-manager');
    echo '</div>';
}

add_action('template_redirect', 'sc_my_account_set_panel_mode_cookie', 3);
function sc_my_account_set_panel_mode_cookie() {
    if (is_admin() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    if (!is_user_logged_in() || headers_sent()) {
        return;
    }
    setcookie('sc_panel_mode', 'my-account', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
}
