<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_present_role_switch($chat_id, array $ctx) {
    $roles = $ctx['available_roles'] ?? [];
    if (count($roles) <= 1) {
        bale_send_message($chat_id, 'فقط یک نقش برای حساب شما فعال است.');
        return;
    }

    $lines = ["<b>تعویض نقش</b>", '', 'نقش فعلی: ' . esc_html(sc_bot_get_role_label($ctx['active_role'] ?? '')), '', 'یک نقش را انتخاب کنید:'];
    $buttons = [];
    foreach ($roles as $role) {
        $label = sc_bot_get_role_label($role);
        if ($role === ($ctx['active_role'] ?? '')) {
            $label = '✓ ' . $label;
        }
        $buttons[] = [['text' => $label, 'callback_data' => 'brole:' . $role]];
    }
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_unified_profile($chat_id, array $ctx) {
    $role = $ctx['active_role'] ?? 'player';
    $lines = ['<b>اطلاعات حساب</b>', ''];
    $lines[] = '👤 ' . esc_html($ctx['full_name'] !== '' ? $ctx['full_name'] : '-');
    $lines[] = '🔑 نقش فعال: ' . esc_html(sc_bot_get_role_label($role));

    $buttons = [];
    if ($role === 'player' && !empty($ctx['member_id'])) {
        $member = sc_get_member_by_chatid($chat_id);
        if ($member) {
            if (!empty($member->player_phone)) {
                $lines[] = '📱 ' . esc_html($member->player_phone);
            }
            if (!empty($member->national_id)) {
                $lines[] = '🪪 ' . esc_html($member->national_id);
            }
        }
        $buttons[] = sc_bot_site_link_button('ویرایش پروفایل', '/my-account/sc-submit-documents');
    } elseif ($role === 'coach' && !empty($ctx['coach_id'])) {
        global $wpdb;
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sc_coaches WHERE id = %d LIMIT 1",
            (int) $ctx['coach_id']
        ));
        if ($coach) {
            if (!empty($coach->mobile_phone)) {
                $lines[] = '📱 ' . esc_html($coach->mobile_phone);
            }
            if (!empty($coach->coaching_level)) {
                $lines[] = '📊 سطح: ' . esc_html($coach->coaching_level);
            }
        }
        $buttons[] = sc_bot_admin_link_button('پروفایل مربی', 'sc-coach-my-profile');
    } else {
        $user = get_userdata((int) $ctx['user_id']);
        if ($user && !empty($user->user_email)) {
            $lines[] = '✉️ ' . esc_html($user->user_email);
        }
        $buttons[] = sc_bot_admin_link_button('ورود به پنل', 'sc-dashboard');
    }

    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_coach_dashboard($chat_id, array $ctx) {
    $coach_id = (int) ($ctx['coach_id'] ?? 0);
    $stats = sc_bot_get_coach_dashboard_stats($coach_id);
    $lines = ['<b>پیشخوان مربی</b>', ''];

    if ($ctx['full_name'] !== '') {
        $lines[] = '👤 ' . esc_html($ctx['full_name']);
        $lines[] = '';
    }

    $lines[] = '📚 دوره‌های فعال: ' . (int) ($stats['courses_count'] ?? 0);
    $lines[] = '👥 بازیکنان: ' . (int) ($stats['players_count'] ?? 0);
    $lines[] = '🎫 تیکت در انتظار: ' . (int) ($stats['pending_tickets'] ?? 0);

    if (isset($stats['wallet_balance'])) {
        $lines[] = '💰 موجودی کیف پول: ' . sc_bot_format_amount($stats['wallet_balance']);
    }
    if (!empty($stats['cert_warning'])) {
        $lines[] = '';
        $lines[] = '⚠️ ' . esc_html($stats['cert_warning']);
    }

    $buttons = [sc_bot_admin_link_button('ورود به پنل مربی', 'sc-coach-my-profile')];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_coach_courses($chat_id, array $ctx) {
    $courses = sc_bot_get_coach_courses_summary((int) ($ctx['coach_id'] ?? 0));
    $lines = ['<b>دوره‌های من</b>', ''];

    if (empty($courses)) {
        $lines[] = 'دوره‌ای به شما اختصاص داده نشده است.';
    } else {
        foreach ($courses as $c) {
            $lines[] = '▫️ <b>' . esc_html($c->title) . '</b>';
            if (!empty($c->chapter_name)) {
                $lines[] = '   شعبه: ' . esc_html($c->chapter_name);
            }
            $lines[] = '   ' . ((int) $c->is_active === 1 ? '✅ فعال' : '⏸ غیرفعال');
        }
    }

    $buttons = [sc_bot_admin_link_button('مشاهده در پنل', 'sc-coach-my-courses')];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_coach_players($chat_id, array $ctx) {
    $players = sc_bot_get_coach_players_summary((int) ($ctx['coach_id'] ?? 0));
    $lines = ['<b>بازیکن‌های من</b>', ''];

    if (empty($players)) {
        $lines[] = 'بازیکنی در دوره‌های شما ثبت نشده است.';
    } else {
        foreach ($players as $p) {
            $name = trim((string) (($p->first_name ?? '') . ' ' . ($p->last_name ?? '')));
            $lines[] = '▫️ ' . esc_html($name !== '' ? $name : 'بازیکن');
            if (!empty($p->course_title)) {
                $lines[] = '   📚 ' . esc_html($p->course_title);
            }
        }
    }

    $buttons = [sc_bot_admin_link_button('لیست کامل', 'sc-coach-my-players')];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_manager_dashboard($chat_id, array $ctx) {
    $stats = sc_bot_get_manager_dashboard_stats((int) $ctx['user_id']);
    $lines = ['<b>پیشخوان مدیریت</b>', ''];

    $lines[] = '👥 بازیکنان فعال: ' . (int) ($stats['active_members'] ?? 0);
    $lines[] = '📋 ثبت‌نام فعال: ' . (int) ($stats['active_enrollments'] ?? 0);
    $lines[] = '💳 صورتحساب معوق: ' . (int) ($stats['pending_invoices'] ?? 0);
    $lines[] = '🎫 تیکت در انتظار: ' . (int) ($stats['pending_tickets'] ?? 0);
    $lines[] = '';
    $lines[] = '<i>برای عملیات از پنل مدیریت استفاده کنید.</i>';

    $buttons = [sc_bot_admin_link_button('ورود به پنل', 'sc-dashboard')];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_secretary_dashboard($chat_id, array $ctx) {
    $stats = sc_bot_get_manager_dashboard_stats((int) $ctx['user_id']);
    $lines = ['<b>پیشخوان منشی شعبه</b>', ''];

    if (!empty($stats['chapters_label'])) {
        $lines[] = '🏢 شعب: ' . esc_html($stats['chapters_label']);
        $lines[] = '';
    }

    $lines[] = '👥 بازیکنان شعبه (فعال): ' . (int) ($stats['active_members'] ?? 0);
    $lines[] = '💳 صورتحساب معوق: ' . (int) ($stats['pending_invoices'] ?? 0);
    $lines[] = '🎫 تیکت در انتظار: ' . (int) ($stats['pending_tickets'] ?? 0);
    $lines[] = '';
    $lines[] = '<i>فقط بازیکنان شعبه(های) شما نمایش داده می‌شوند.</i>';

    $buttons = [sc_bot_admin_link_button('ورود به پنل', 'sc-dashboard')];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_bot_route_role_callback($chat_id, $data, $callback_query_id = '') {
    if ($callback_query_id !== '') {
        bale_answer_callback_query($callback_query_id);
    }

    if (!preg_match('/^brole:(player|coach|manager|secretary)$/', $data, $m)) {
        return false;
    }

    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return true;
    }

    $role = $m[1];
    if (!sc_bot_set_active_role((int) $ctx['user_id'], $role)) {
        bale_send_message($chat_id, '❌ امکان تعویض به این نقش وجود ندارد.');
        return true;
    }

    $ctx = sc_bot_resolve_user_context($chat_id);
    $label = sc_bot_get_role_label($role);
    bale_send_message($chat_id, "✅ نقش فعال به «{$label}» تغییر کرد.");
    require_once SC_BOT_COMMANDS_DIR . 'start.php';
    bale_cmd_start($chat_id, $ctx);
    return true;
}
