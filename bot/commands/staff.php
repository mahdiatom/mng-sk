<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_staff_require_ctx($chat_id) {
    return sc_bot_require_connected_context($chat_id);
}

function bale_cmd_connect_prompt($chat_id) {
    $ctx = sc_bot_resolve_user_context($chat_id);
    $link = $ctx['connect_url'] ?? sc_bot_get_connect_url_for_visitor();
    $text = "برای اتصال حساب:\n\n"
        . "۱. از سایت وارد پنل خود شوید\n"
        . "۲. به بخش «اتصال به ربات» بروید\n"
        . "۳. روی دکمه اتصال بزنید";
    $buttons = [[bale_make_link_button('🔗 اتصال به حساب', $link)]];
    bale_send_message_with_buttons($chat_id, $text, $buttons);
}

function bale_cmd_logout_confirm($chat_id) {
    if (!sc_bot_require_connected_context($chat_id)) {
        return;
    }
    bale_present_logout_confirm($chat_id);
}

function bale_cmd_role_switch($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_role_switch($chat_id, $ctx);
}

function bale_cmd_profile_unified($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_unified_profile($chat_id, $ctx);
}

function bale_cmd_coach_dashboard($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_coach_dashboard($chat_id, $ctx);
}

function bale_cmd_coach_courses($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_coach_courses($chat_id, $ctx);
}

function bale_cmd_coach_players($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_coach_players($chat_id, $ctx);
}

function bale_cmd_coach_attendance_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'ثبت حضور و غیاب', 'sc-attendance-add');
}

function bale_cmd_coach_schedule_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'برنامه هفتگی', 'sc-coach-weekly-schedule');
}

function bale_cmd_coach_private_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'کلاس‌های خصوصی', 'sc-coach-private-classes');
}

function bale_cmd_coach_notifications_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'اطلاعیه‌ها', 'sc-coach-notifications');
}

function bale_cmd_coach_tickets_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'تیکت پشتیبانی', 'sc-coach-support-tickets');
}

function bale_cmd_coach_wallet_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'دستمزد و کیف پول', 'sc-coach-wallet');
}

function bale_cmd_coach_honors_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'افتخارات من', 'sc-coach-honors');
}

function bale_cmd_manager_dashboard($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_manager_dashboard($chat_id, $ctx);
}

function bale_cmd_manager_members_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'بازیکنان', 'sc-members');
}

function bale_cmd_manager_invoices_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'صورتحساب‌ها', 'sc-invoices');
}

function bale_cmd_manager_attendance_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'حضور و غیاب', 'sc-attendance-add');
}

function bale_cmd_manager_courses_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'دوره‌ها', 'sc-courses');
}

function bale_cmd_manager_notifications_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'اطلاعیه و پیامک', 'sc-notifications');
}

function bale_cmd_manager_tickets_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'تیکت پشتیبانی', 'sc-support-tickets');
}

function bale_cmd_manager_bot_send_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'ارسال پیام ربات', 'sc-bale-bot-send');
}

function bale_cmd_manager_alerts_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'هشدارهای کاربر', 'sc-user-alerts');
}

function bale_cmd_secretary_dashboard($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return;
    }
    bale_present_secretary_dashboard($chat_id, $ctx);
}

function bale_cmd_secretary_members_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'بازیکنان شعبه', 'sc-members');
}

function bale_cmd_secretary_invoices_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'صورتحساب‌ها', 'sc-invoices');
}

function bale_cmd_secretary_attendance_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'حضور و غیاب', 'sc-attendance-add');
}

function bale_cmd_secretary_tickets_link($chat_id) {
    bale_staff_admin_link_message($chat_id, 'تیکت پشتیبانی', 'sc-support-tickets');
}

function bale_staff_admin_link_message($chat_id, $title, $page) {
    if (!sc_bot_require_connected_context($chat_id)) {
        return;
    }
    $text = "برای <b>{$title}</b> از پنل مدیریت استفاده کنید.\n\nعملیات فقط در سایت انجام می‌شود.";
    $buttons = [sc_bot_admin_link_button('ورود به پنل', $page)];
    bale_send_message_with_buttons($chat_id, $text, [sc_bot_admin_link_button('ورود به پنل', $page)]);
}
