<?php
/**
 * کنترل دسترسی به صفحات ویرایش کاربر وردپرس (user-edit.php / profile.php)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقش‌هایی که از طریق سامانه باشگاه مدیریت می‌شوند (نه user-edit وردپرس)
 */
function sc_user_profile_staff_roles() {
    return array_merge(['administrator', 'accountantt', 'shop_manager'], function_exists('sc_get_club_manager_role_slugs') ? sc_get_club_manager_role_slugs() : ['club_coach']);
}

function sc_user_profile_user_has_staff_role($user) {
    if (!$user instanceof WP_User) {
        return false;
    }
    foreach (sc_user_profile_staff_roles() as $role) {
        if (in_array($role, (array) $user->roles, true)) {
            return true;
        }
    }
    return false;
}

function sc_user_profile_is_coach_user($user) {
    return $user instanceof WP_User && in_array('coach', (array) $user->roles, true);
}

function sc_user_profile_is_player_user($user) {
    return $user instanceof WP_User && in_array('subscriber', (array) $user->roles, true);
}

/**
 * نقش‌هایی که به پنل کاربری بازیکن دسترسی ندارند.
 *
 * @return string[]
 */
function sc_get_player_panel_blocked_roles() {
    $roles = ['coach', 'administrator', 'accountantt', 'secretary', 'shop_manager'];
    if (function_exists('sc_get_club_manager_role_slugs')) {
        $roles = array_merge($roles, sc_get_club_manager_role_slugs());
    } else {
        $roles[] = 'club_coach';
        $roles[] = 'system_manager';
    }
    return array_values(array_unique($roles));
}

/**
 * آیا کاربر فعلی (یا داده‌شده) مجاز به پنل بازیکن است؟
 * فقط نقش «بازیکن» (subscriber) و بدون نقش staff.
 *
 * @param WP_User|null $user
 * @return bool
 */
function sc_user_is_player_panel_allowed($user = null) {
    if ($user === null) {
        $user = wp_get_current_user();
    }
    if (!$user instanceof WP_User || !$user->exists()) {
        return false;
    }

    $roles = (array) $user->roles;
    foreach (sc_get_player_panel_blocked_roles() as $blocked) {
        if (in_array($blocked, $roles, true)) {
            return false;
        }
    }

    return in_array('subscriber', $roles, true);
}

/**
 * برچسب فارسی نقش کاربر برای پیام‌های دسترسی.
 *
 * @param WP_User|null $user
 * @return string
 */
function sc_get_user_role_display_label($user = null) {
    if ($user === null) {
        $user = wp_get_current_user();
    }
    if (!$user instanceof WP_User || empty($user->roles)) {
        return 'نامشخص';
    }

    $labels = [
        'subscriber'     => 'بازیکن',
        'coach'          => 'مربی',
        'club_coach'     => 'مدیر باشگاه',
        'system_manager' => 'مدیر سامانه',
        'administrator'  => 'مدیر کل',
        'accountantt'    => 'حسابدار',
        'secretary'      => 'منشی',
        'shop_manager'   => 'مدیر فروشگاه',
    ];

    $role = (string) $user->roles[0];
    if (isset($labels[$role])) {
        return $labels[$role];
    }

    global $wp_roles;
    if ($wp_roles instanceof WP_Roles && isset($wp_roles->role_names[$role])) {
        return translate_user_role($wp_roles->role_names[$role]);
    }

    return $role;
}

/**
 * آدرس خانه پنل مربی در ادمین.
 *
 * @return string
 */
function sc_get_coach_panel_home_url() {
    return admin_url('admin.php?page=sc-coach-my-profile');
}

/**
 * پیام زیبای عدم دسترسی نقش غیر بازیکن به پنل کاربری.
 */
function sc_render_player_panel_access_denied() {
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    $role_label = sc_get_user_role_display_label();
    $admin_url = admin_url();
    $is_coach = function_exists('sc_user_profile_is_coach_user') && sc_user_profile_is_coach_user(wp_get_current_user());
    if ($is_coach) {
        $admin_url = sc_get_coach_panel_home_url();
    }
    ?>
    <div class="sc-panel-access-denied" role="alert">
        <div class="sc-panel-access-denied__card">
            <div class="sc-panel-access-denied__icon" aria-hidden="true">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="32" fill="currentColor" opacity="0.1"/>
                    <circle cx="32" cy="32" r="22" stroke="currentColor" stroke-width="2.5" opacity="0.35"/>
                    <path d="M32 18v18" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
                    <circle cx="32" cy="44" r="2.5" fill="currentColor"/>
                </svg>
            </div>
            <h2 class="sc-panel-access-denied__title">دسترسی مجاز نیست</h2>
            <p class="sc-panel-access-denied__text">
                نقش شما (<strong><?php echo esc_html($role_label); ?></strong>) دسترسی به این بخش را ندارد.
            </p>
            <p class="sc-panel-access-denied__hint">
                این پنل فقط برای بازیکنان باشگاه در دسترس است. لطفاً از پنل مربوط به نقش خود استفاده کنید.
            </p>
            <div class="sc-panel-access-denied__actions">
                <a class="sc-panel-access-denied__btn" href="<?php echo esc_url($admin_url); ?>">
                    <?php echo $is_coach ? 'بازگشت به پنل مربی' : 'بازگشت به پنل مدیریت'; ?>
                </a>
                <a class="sc-panel-access-denied__btn sc-panel-access-denied__btn--ghost" href="<?php echo esc_url(home_url('/')); ?>">
                    صفحه اصلی
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * اگر کاربر بازیکن نباشد پیام عدم دسترسی را نشان می‌دهد.
 *
 * @return bool
 */
function sc_require_player_panel_access() {
    if (sc_user_is_player_panel_allowed()) {
        return true;
    }
    sc_render_player_panel_access_denied();
    return false;
}

/**
 * بازیکن و مربی (بدون نقش staff) از user-edit وردپرس مسدود می‌شوند.
 */
function sc_user_profile_is_restricted_user($user) {
    if (!$user instanceof WP_User || sc_user_profile_user_has_staff_role($user)) {
        return false;
    }
    return sc_user_profile_is_coach_user($user) || sc_user_profile_is_player_user($user);
}

function sc_user_profile_get_target_user_id() {
    global $pagenow;

    if ($pagenow === 'profile.php') {
        return get_current_user_id();
    }
    if ($pagenow === 'user-edit.php' && isset($_GET['user_id'])) {
        return absint($_GET['user_id']);
    }
    return 0;
}

function sc_user_profile_get_member_id_by_user_id($user_id) {
    global $wpdb;
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_members WHERE user_id = %d LIMIT 1",
        $user_id
    ));
}

function sc_user_profile_get_coach_id_by_user_id($user_id) {
    global $wpdb;
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d LIMIT 1",
        $user_id
    ));
}

function sc_user_profile_render_block_notice($title, $message, $links = []) {
    $html = '<div style="max-width:640px;margin:40px auto;padding:24px 28px;background:#fff;border:1px solid #dcdcde;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.04);font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;">';
    $html .= '<h2 style="margin:0 0 12px;font-size:20px;color:#1d2327;">' . esc_html($title) . '</h2>';
    $html .= '<p style="margin:0 0 18px;line-height:1.9;color:#50575e;">' . wp_kses_post($message) . '</p>';

    if (!empty($links)) {
        $html .= '<p style="margin:0;">';
        foreach ($links as $index => $link) {
            if (empty($link['url']) || empty($link['label'])) {
                continue;
            }
            $class = !empty($link['primary']) ? 'button button-primary' : 'button';
            if ($index > 0) {
                $html .= ' ';
            }
            $html .= '<a class="' . esc_attr($class) . '" href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a>';
        }
        $html .= '</p>';
    }

    $html .= '</div>';

    wp_die($html, esc_html($title), ['response' => 403, 'back_link' => false]);
}

function sc_user_profile_block_restricted_edit_screen() {
    global $pagenow;

    if (!is_admin() || !in_array($pagenow, ['user-edit.php', 'profile.php'], true)) {
        return;
    }

    $user_id = sc_user_profile_get_target_user_id();
    if ($user_id <= 0) {
        return;
    }

    $user = get_userdata($user_id);
    if (!$user || !sc_user_profile_is_restricted_user($user)) {
        return;
    }

    if (sc_user_profile_is_coach_user($user)) {
        $coach_id = sc_user_profile_get_coach_id_by_user_id($user_id);
        $links = [];

        if ($pagenow === 'profile.php' && (int) get_current_user_id() === $user_id) {
            $links[] = [
                'label'   => 'رفتن به «اطلاعات من»',
                'url'     => admin_url('admin.php?page=sc-coach-my-profile&edit=1'),
                'primary' => true,
            ];
            sc_user_profile_render_block_notice(
                'ویرایش پروفایل از این بخش امکان‌پذیر نیست',
                'اطلاعات مربی (نام، تماس، رمز عبور و سایر جزئیات) فقط از بخش <strong>اطلاعات من</strong> در پنل مربی قابل ویرایش است. لطفاً از همان بخش استفاده کنید.',
                $links
            );
        }

        if ($coach_id > 0) {
            $links[] = [
                'label'   => 'ویرایش این مربی',
                'url'     => admin_url('admin.php?page=sc-add-coach&coach_id=' . $coach_id),
                'primary' => true,
            ];
        }
        $links[] = [
            'label' => 'بازگشت به لیست مربیان',
            'url'   => admin_url('admin.php?page=sc-coaches'),
        ];

        sc_user_profile_render_block_notice(
            'ویرایش مربی از بخش کاربران وردپرس امکان‌پذیر نیست',
            'اطلاعات مربیان (دوره‌ها، دستمزد، پروفایل، رمز عبور و …) فقط از بخش <strong>مدیریت مربیان</strong> در سامانه باشگاه قابل ویرایش است.',
            $links
        );
    }

    if (sc_user_profile_is_player_user($user)) {
        $member_id = sc_user_profile_get_member_id_by_user_id($user_id);
        $links = [];

        if ($pagenow === 'profile.php' && (int) get_current_user_id() === $user_id) {
            $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');
            $links[] = [
                'label'   => 'رفتن به پنل کاربری',
                'url'     => $account_url,
                'primary' => true,
            ];
            sc_user_profile_render_block_notice(
                'ویرایش پروفایل از این بخش امکان‌پذیر نیست',
                'اطلاعات بازیکن (مشخصات فردی، مدارک، دوره‌ها و …) فقط از <strong>پنل کاربری</strong> در سایت قابل ویرایش است. لطفاً وارد پنل کاربری خود شوید.',
                $links
            );
        }

        if ($member_id > 0) {
            $links[] = [
                'label'   => 'ویرایش این بازیکن',
                'url'     => admin_url('admin.php?page=sc-add-member&player_id=' . $member_id),
                'primary' => true,
            ];
        }
        $links[] = [
            'label' => 'بازگشت به لیست اعضا',
            'url'   => admin_url('admin.php?page=sc-members'),
        ];

        sc_user_profile_render_block_notice(
            'ویرایش بازیکن از بخش کاربران وردپرس امکان‌پذیر نیست',
            'اطلاعات بازیکنان (نام، کد ملی، دوره‌ها، مدارک، وضعیت عضویت و …) فقط از بخش <strong>اعضا → لیست اعضا</strong> در سامانه باشگاه قابل ویرایش است.',
            $links
        );
    }
}

add_action('load-user-edit.php', 'sc_user_profile_block_restricted_edit_screen');
add_action('load-profile.php', 'sc_user_profile_block_restricted_edit_screen');

/**
 * کلاس body برای صفحات ویرایش کاربر مجاز
 */
function sc_user_profile_admin_body_class($classes) {
    global $pagenow;

    if (!in_array($pagenow, ['user-edit.php', 'profile.php'], true)) {
        return $classes;
    }
    if (function_exists('sc_user_profile_should_hide_wp_fields') && sc_user_profile_should_hide_wp_fields()) {
        return $classes . ' sc-wp-user-profile-restricted';
    }
    return $classes . ' sc-wp-user-profile-editable';
}
add_filter('admin_body_class', 'sc_user_profile_admin_body_class');

/**
 * نمایش عنوان بخش «اطلاعات تماس» برای کاربران مجاز
 */
function sc_user_profile_show_contact_heading() {
    if (!function_exists('sc_user_profile_should_hide_wp_fields') || sc_user_profile_should_hide_wp_fields()) {
        return;
    }
    ?>
    <style>
        body.sc-wp-user-profile-editable #your-profile h2:first-of-type {
            display: block !important;
        }
    </style>
    <?php
}
add_action('admin_head-user-edit.php', 'sc_user_profile_show_contact_heading', 20);
add_action('admin_head-profile.php', 'sc_user_profile_show_contact_heading', 20);

/**
 * آیا فیلدهای user-edit باید مخفی شوند؟ (فقط برای بازیکن/مربی — که عملاً مسدود می‌شوند)
 */
function sc_user_profile_should_hide_wp_fields() {
    global $pagenow;

    if ($pagenow === 'user-new.php') {
        return false;
    }
    if (!in_array($pagenow, ['user-edit.php', 'profile.php'], true)) {
        return false;
    }

    $user_id = sc_user_profile_get_target_user_id();
    if ($user_id <= 0) {
        return false;
    }

    $user = get_userdata($user_id);
    return $user && sc_user_profile_is_restricted_user($user);
}
