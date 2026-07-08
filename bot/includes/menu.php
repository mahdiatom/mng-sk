<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_get_connect_keyboard() {
    return [
        [['text' => '🔗 اتصال به حساب']],
    ];
}

function bale_get_role_switch_row(array $ctx) {
    $roles = $ctx['available_roles'] ?? [];
    if (count($roles) <= 1) {
        return [];
    }
    return [['text' => '🔄 تعویض نقش']];
}

function bale_get_footer_rows(array $ctx) {
    $rows = [];
    $switch = bale_get_role_switch_row($ctx);
    if (!empty($switch)) {
        $rows[] = $switch[0];
    }
    $rows[] = [['text' => 'خروج از حساب کاربری']];
    return $rows;
}

function bale_get_player_keyboard(array $ctx = []) {
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

    return array_merge($rows, bale_get_footer_rows($ctx));
}

function bale_get_coach_keyboard(array $ctx = []) {
    $rows = [
        [
            ['text' => 'پیشخوان مربی'],
            ['text' => 'دوره های من'],
            ['text' => 'بازیکنان من'],
        ],
        [
            ['text' => 'ثبت حضور و غیاب'],
            ['text' => 'برنامه هفتگی'],
            ['text' => 'کلاس خصوصی'],
        ],
    ];

    $row2 = [];
    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()) {
        $row2[] = ['text' => 'اطلاعیه ها'];
    }
    $row2[] = ['text' => 'تیکت پشتیبانی'];
    if (function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        $row2[] = ['text' => 'دستمزد و کیف پول'];
    }
    if (!empty($row2)) {
        $rows[] = $row2;
    }

    if (function_exists('sc_is_pro_feature_honors_enabled') && sc_is_pro_feature_honors_enabled()) {
        $rows[] = [['text' => 'افتخارات من']];
    }

    $rows[] = [['text' => 'اطلاعات من']];

    return array_merge($rows, bale_get_footer_rows($ctx));
}

function bale_get_manager_keyboard(array $ctx = []) {
    $rows = [
        [
            ['text' => 'پیشخوان مدیریت'],
            ['text' => 'بازیکنان'],
            ['text' => 'صورتحساب ها'],
        ],
        [
            ['text' => 'حضور و غیاب'],
            ['text' => 'دوره ها'],
            ['text' => 'اطلاعیه و پیامک'],
        ],
        [
            ['text' => 'تیکت پشتیبانی'],
            ['text' => 'ارسال پیام ربات'],
        ],
    ];

    if (function_exists('sc_is_pro_feature_user_alerts_enabled') && sc_is_pro_feature_user_alerts_enabled()) {
        $rows[] = [['text' => 'هشدارهای کاربر']];
    }

    $rows[] = [['text' => 'اطلاعات من']];

    return array_merge($rows, bale_get_footer_rows($ctx));
}

function bale_get_secretary_keyboard(array $ctx = []) {
    $rows = [
        [
            ['text' => 'پیشخوان منشی'],
            ['text' => 'بازیکنان شعبه'],
            ['text' => 'صورتحساب ها'],
        ],
        [
            ['text' => 'حضور و غیاب'],
            ['text' => 'تیکت پشتیبانی'],
        ],
        [['text' => 'اطلاعات من']],
    ];

    return array_merge($rows, bale_get_footer_rows($ctx));
}

/**
 * @param array<string,mixed>|null $ctx
 */
function bale_get_main_keyboard($ctx = null) {
    if (!$ctx || empty($ctx['connected'])) {
        return bale_get_connect_keyboard();
    }

    $role = $ctx['active_role'] ?? 'player';
    switch ($role) {
        case 'manager':
            return bale_get_manager_keyboard($ctx);
        case 'secretary':
            return bale_get_secretary_keyboard($ctx);
        case 'coach':
            return bale_get_coach_keyboard($ctx);
        default:
            return bale_get_player_keyboard($ctx);
    }
}

/**
 * @param array<string,mixed>|null $ctx
 * @return array<string, array{file: string, func: string}>
 */
function bale_get_text_commands($ctx = null) {
    $role = ($ctx && !empty($ctx['connected'])) ? ($ctx['active_role'] ?? 'player') : '';

    $player = [
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

    $coach = [
        'پیشخوان مربی'     => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_dashboard'],
        'دوره های من'      => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_courses'],
        'بازیکنان من'      => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_players'],
        'ثبت حضور و غیاب'  => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_attendance_link'],
        'برنامه هفتگی'     => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_schedule_link'],
        'کلاس خصوصی'       => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_private_link'],
        'اطلاعیه ها'       => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_notifications_link'],
        'تیکت پشتیبانی'    => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_tickets_link'],
        'دستمزد و کیف پول' => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_wallet_link'],
        'افتخارات من'      => ['file' => 'staff.php', 'func' => 'bale_cmd_coach_honors_link'],
    ];

    $manager = [
        'پیشخوان مدیریت'   => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_dashboard'],
        'بازیکنان'         => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_members_link'],
        'صورتحساب ها'      => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_invoices_link'],
        'حضور و غیاب'      => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_attendance_link'],
        'دوره ها'          => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_courses_link'],
        'اطلاعیه و پیامک'  => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_notifications_link'],
        'تیکت پشتیبانی'    => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_tickets_link'],
        'ارسال پیام ربات'  => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_bot_send_link'],
        'هشدارهای کاربر'   => ['file' => 'staff.php', 'func' => 'bale_cmd_manager_alerts_link'],
    ];

    $secretary = [
        'پیشخوان منشی'     => ['file' => 'staff.php', 'func' => 'bale_cmd_secretary_dashboard'],
        'بازیکنان شعبه'    => ['file' => 'staff.php', 'func' => 'bale_cmd_secretary_members_link'],
        'صورتحساب ها'      => ['file' => 'staff.php', 'func' => 'bale_cmd_secretary_invoices_link'],
        'حضور و غیاب'      => ['file' => 'staff.php', 'func' => 'bale_cmd_secretary_attendance_link'],
        'تیکت پشتیبانی'    => ['file' => 'staff.php', 'func' => 'bale_cmd_secretary_tickets_link'],
    ];

    $common = [
        'اطلاعات من'           => ['file' => 'staff.php', 'func' => 'bale_cmd_profile_unified'],
        '🔄 تعویض نقش'         => ['file' => 'staff.php', 'func' => 'bale_cmd_role_switch'],
        'خروج از حساب کاربری'  => ['file' => 'staff.php', 'func' => 'bale_cmd_logout_confirm'],
        '🔗 اتصال به حساب'     => ['file' => 'staff.php', 'func' => 'bale_cmd_connect_prompt'],
    ];

    $commands = $common;
    switch ($role) {
        case 'coach':
            $commands = array_merge($commands, $coach);
            break;
        case 'manager':
            $commands = array_merge($commands, $manager);
            break;
        case 'secretary':
            $commands = array_merge($commands, $secretary);
            break;
        default:
            $commands = array_merge($commands, $player);
    }

    if ($role === 'player' || $role === '') {
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
    }

    if ($role === 'coach') {
        if (!function_exists('sc_is_pro_feature_notifications_enabled') || !sc_is_pro_feature_notifications_enabled()) {
            unset($commands['اطلاعیه ها']);
        }
        if (!function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') || !sc_is_pro_feature_coaches_wallet_salary_enabled()) {
            unset($commands['دستمزد و کیف پول']);
        }
        if (!function_exists('sc_is_pro_feature_honors_enabled') || !sc_is_pro_feature_honors_enabled()) {
            unset($commands['افتخارات من']);
        }
    }

    if ($role === 'manager') {
        if (!function_exists('sc_is_pro_feature_user_alerts_enabled') || !sc_is_pro_feature_user_alerts_enabled()) {
            unset($commands['هشدارهای کاربر']);
        }
    }

    return $commands;
}

function bale_get_welcome_message(array $ctx) {
    if (empty($ctx['connected'])) {
        return "سلام!\n\nبرای استفاده از ربات، ابتدا از پنل سایت وارد شوید و حساب خود را متصل کنید.\n\nروی «اتصال به حساب» بزنید.";
    }

    $role_label = sc_bot_get_role_label($ctx['active_role'] ?? 'player');
    $name = $ctx['full_name'] !== '' ? $ctx['full_name'] : 'کاربر گرامی';
    return "سلام {$name}!\n\nنقش فعال: <b>{$role_label}</b>\nیکی از گزینه‌ها را انتخاب کنید:";
}
