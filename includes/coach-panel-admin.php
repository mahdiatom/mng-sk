<?php
/**
 * استایل و کلاس body صفحات پنل مربی
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string[]
 */
function sc_coach_panel_members_list_pages() {
    return [
        'sc-coach-my-players',
        'sc-coach-notifications',
        'sc-coach-my-courses',
    ];
}

/**
 * @return string[]
 */
function sc_coach_panel_wallet_list_pages() {
    return [
        'sc-coach-salary',
        'sc-coach-wallet',
        'sc-coach-withdrawals',
    ];
}

/**
 * @param string $classes
 * @return string
 */
function sc_coach_panel_admin_body_class($classes) {
    if (!is_admin()) {
        return $classes;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

    if ($page !== '' && in_array($page, sc_coach_panel_wallet_list_pages(), true)) {
        return $classes . ' sc-coach-wallet-list-page';
    }

    if ($page !== '' && in_array($page, sc_coach_panel_members_list_pages(), true)) {
        return $classes . ' sc-coach-members-list-page';
    }

    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && !empty($screen->id)) {
            if (strpos($screen->id, 'sc-coach-wallet') !== false
                || strpos($screen->id, 'sc-coach-salary') !== false
                || strpos($screen->id, 'sc-coach-withdrawals') !== false) {
                if (strpos($classes, 'sc-coach-wallet-list-page') === false) {
                    return $classes . ' sc-coach-wallet-list-page';
                }
            }
        }
    }

    return $classes;
}

add_filter('admin_body_class', 'sc_coach_panel_admin_body_class');
