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
                <div class="sc-portal-header__search" role="search">
                    <svg class="sc-portal-header__search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <input type="search" class="sc-portal-header-search-trigger" placeholder="<?php echo esc_attr(sc_get_setting('sc_header_search_placeholder', 'جستجو…')); ?>" readonly aria-label="<?php esc_attr_e('جستجو', 'sportclub-manager'); ?>" />
                </div>
                <?php if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()) : ?>
                <a href="<?php echo esc_url(sc_portal_endpoint_url('sc-notifications')); ?>" class="sc-portal-header__icon-btn" title="<?php esc_attr_e('اطلاعیه‌ها', 'sportclub-manager'); ?>">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 17H9c-2.2 0-4-1.8-4-4V9c0-2.2 1.8-4 4-4h6c2.2 0 4 1.8 4 4v4c0 2.2-1.8 4-4 4z" stroke="currentColor" stroke-width="1.8"/><path d="M12 21a2 2 0 002-2h-4a2 2 0 002 2z" fill="currentColor"/></svg>
                    <?php if ($unread_notif > 0) : ?>
                        <span class="sc-portal-header__badge"><?php echo esc_html((string) $unread_notif); ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(sc_portal_endpoint_url('sc-support-tickets')); ?>" class="sc-portal-header__icon-btn" title="<?php esc_attr_e('تیکت پشتیبانی', 'sportclub-manager'); ?>">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16v10H8l-4 4V6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    <?php if ($unread_ticket > 0) : ?>
                        <span class="sc-portal-header__badge"><?php echo esc_html((string) $unread_ticket); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(sc_portal_endpoint_url('sc-submit-documents')); ?>" class="sc-portal-header__profile">
                    <img src="<?php echo esc_url($avatar); ?>" alt="" class="sc-portal-header__avatar" width="36" height="36" />
                    <span class="sc-portal-header__profile-info">
                        <span class="sc-portal-header__name"><?php echo esc_html($display_name); ?></span>
                        <span class="sc-portal-header__role"><?php esc_html_e('بازیکن', 'sportclub-manager'); ?></span>
                    </span>
                </a>
            </div>
        </header>

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
        var trigger = document.querySelector('.sc-portal-header-search-trigger');
        var popup = document.getElementById('sc-header-search-popup');
        if (!trigger || !popup) {
            return;
        }
        function openPortalSearch(e) {
            if (e) {
                e.preventDefault();
            }
            popup.classList.add('is-open');
            popup.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var input = popup.querySelector('.sc-header-search__input--popup');
            if (input) {
                setTimeout(function () { input.focus(); }, 80);
            }
        }
        trigger.addEventListener('click', openPortalSearch);
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
