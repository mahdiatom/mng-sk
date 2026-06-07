<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * منوی اصلی ربات — فقط گزینه‌های فعال در افزونه
 */
function bale_get_main_keyboard() {
    $rows = [
        [
            ['text' => 'اطلاعات من'],
            ['text' => 'پیشخوان'],
            ['text' => 'صورتحساب'],
        ],
        [
            ['text' => 'بخش دوره ها'],
            ['text' => 'بخش حضور و غیاب'],
            ['text' => 'کلاس خصوصی'],
        ],
        [
            ['text' => 'بخش رویداد ها'],
            ['text' => 'بخش افتخارات'],
            ['text' => 'گواهینامه ها'],
        ],
    ];

    $row_shop = [];
    if (function_exists('sc_is_pro_feature_shop_enabled') && sc_is_pro_feature_shop_enabled()) {
        $row_shop[] = ['text' => 'فروشگاه'];
        $row_shop[] = ['text' => 'سفارش های فروشگاه'];
    }
    if (!empty($row_shop)) {
        if (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled()) {
            $row_shop[] = ['text' => 'کیف پول'];
        }
        $rows[] = $row_shop;
    } elseif (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled()) {
        $rows[] = [['text' => 'کیف پول']];
    }

    $row_extra = [];
    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()) {
        $row_extra[] = ['text' => 'بخش اطلاعیه ها'];
    }
    if (function_exists('sc_is_pro_feature_private_notes_enabled') && sc_is_pro_feature_private_notes_enabled()) {
        $row_extra[] = ['text' => 'یادداشت های من'];
    }
    if (function_exists('sc_is_pro_feature_surveys_enabled') && sc_is_pro_feature_surveys_enabled()) {
        $row_extra[] = ['text' => 'نظرسنجی ها'];
    }
    if (!empty($row_extra)) {
        $rows[] = $row_extra;
    }

    $rows[] = [
        ['text' => 'سوالات متداول کاربران'],
        ['text' => 'تیکت و پشتیبانی'],
    ];
    $rows[] = [
        ['text' => 'خروج از حساب کاربری'],
    ];

    return $rows;
}

/**
 * @return array<string, array{file: string, func: string}>
 */
function bale_get_text_commands() {
    $commands = [
        'پیشخوان'               => ['file' => 'dashboard.php', 'func' => 'bale_cmd_dashboard'],
        'صورتحساب'              => ['file' => 'invoices.php', 'func' => 'bale_cmd_invoices'],
        'بخش دوره ها'           => ['file' => 'course.php', 'func' => 'bale_cmd_course'],
        'بخش حضور و غیاب'       => ['file' => 'pr.php', 'func' => 'bale_cmd_pr'],
        'کلاس خصوصی'            => ['file' => 'private_classes.php', 'func' => 'bale_cmd_private_classes'],
        'بخش رویداد ها'         => ['file' => 'events.php', 'func' => 'bale_cmd_events'],
        'بخش افتخارات'          => ['file' => 'honors.php', 'func' => 'bale_cmd_honors'],
        'گواهینامه ها'          => ['file' => 'certificates.php', 'func' => 'bale_cmd_certificates'],
        'فروشگاه'               => ['file' => 'shop.php', 'func' => 'bale_cmd_shop'],
        'سفارش های فروشگاه'     => ['file' => 'orders.php', 'func' => 'bale_cmd_orders'],
        'کیف پول'               => ['file' => 'wallet.php', 'func' => 'bale_cmd_wallet'],
        'بخش اطلاعیه ها'        => ['file' => 'notifications_inbox.php', 'func' => 'bale_cmd_notifications_inbox'],
        'یادداشت های من'        => ['file' => 'private_notes.php', 'func' => 'bale_cmd_private_notes'],
        'نظرسنجی ها'            => ['file' => 'surveys.php', 'func' => 'bale_cmd_surveys'],
        'سوالات متداول کاربران' => ['file' => 'faq_bot.php', 'func' => 'bale_cmd_faq_bot'],
        'تیکت و پشتیبانی'       => ['file' => 'support.php', 'func' => 'bale_cmd_support'],
    ];

    if (!function_exists('sc_is_pro_feature_shop_enabled') || !sc_is_pro_feature_shop_enabled()) {
        unset($commands['فروشگاه'], $commands['سفارش های فروشگاه']);
    }
    if (!function_exists('sc_is_pro_feature_players_wallet_enabled') || !sc_is_pro_feature_players_wallet_enabled()) {
        unset($commands['کیف پول']);
    }
    if (!function_exists('sc_is_pro_feature_notifications_enabled') || !sc_is_pro_feature_notifications_enabled()) {
        unset($commands['بخش اطلاعیه ها']);
    }
    if (!function_exists('sc_is_pro_feature_private_notes_enabled') || !sc_is_pro_feature_private_notes_enabled()) {
        unset($commands['یادداشت های من']);
    }
    if (!function_exists('sc_is_pro_feature_surveys_enabled') || !sc_is_pro_feature_surveys_enabled()) {
        unset($commands['نظرسنجی ها']);
    }
    if (!function_exists('sc_is_pro_feature_certificates_enabled') || !sc_is_pro_feature_certificates_enabled()) {
        unset($commands['گواهینامه ها']);
    }

    return $commands;
}
