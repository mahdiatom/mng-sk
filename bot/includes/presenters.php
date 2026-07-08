<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_bot_require_ctx_or_exit($chat_id) {
    $ctx = sc_bot_resolve_user_context($chat_id);
    if (!$ctx || empty($ctx['connected'])) {
        sc_bot_require_connected_context($chat_id);
        return false;
    }

    if (($ctx['active_role'] ?? '') !== 'player') {
        return [
            'user_id'    => (int) $ctx['user_id'],
            'member_id'  => (int) ($ctx['member_id'] ?? 0),
            'full_name'  => (string) ($ctx['full_name'] ?? ''),
            'chat_id'    => $chat_id,
        ];
    }

    $member_ctx = sc_bot_resolve_member($chat_id);
    if (!$member_ctx || empty($member_ctx['member_id'])) {
        bale_send_message($chat_id, 'اطلاعات بازیکن یافت نشد.');
        return false;
    }
    return $member_ctx;
}

function bale_present_dashboard($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $tasks = sc_bot_collect_dashboard_tasks($ctx['member_id'], $ctx['user_id']);
    $lines = ["<b>پیشخوان</b>", ''];
    if ($ctx['full_name'] !== '') {
        $lines[] = '👤 ' . esc_html($ctx['full_name']);
        $lines[] = '';
    }

    if (!empty($tasks)) {
        $lines[] = '<b>✅ کارهای ناتمام (' . count($tasks) . ')</b>';
        foreach ($tasks as $task) {
            $lines[] = $task['icon'] . ' <b>' . esc_html($task['title']) . '</b>';
            $lines[] = '   ' . esc_html($task['description']);
        }
    } else {
        $lines[] = '✅ کار ناتمامی ندارید — همه چیز به‌روز است!';
    }

    $buttons = [
        sc_bot_site_link_button('ورود به پیشخوان', '/my-account/sc-dashboard'),
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_logout_confirm($chat_id) {
    $text = "⚠️ <b>خروج از حساب کاربری</b>\n\n"
        . "آیا مطمئن هستید که می‌خواهید حساب خود را از ربات خارج کنید؟\n\n"
        . "پس از خروج، برای استفاده مجدد باید از سایت دوباره «اتصال به ربات» را بزنید.";

    $buttons = [
        [
            ['text' => '✅ بله، خارج شو', 'callback_data' => 'blogout:yes'],
            ['text' => '❌ انصراف', 'callback_data' => 'blogout:no'],
        ],
    ];
    bale_send_message_with_buttons($chat_id, $text, $buttons);
}

function bale_present_faq($chat_id) {
    $default_intro = "در صورت پیدا نکردن پاسخ، از بخش تیکت پشتیبانی پیام بفرستید.";
    $default_faqs = [
        [
            'q' => 'آیا در سایت حتما باید اطلاعاتم را تکمیل کنم؟',
            'a' => 'خیر، لزومی به ثبت تمامی اطلاعات نیست مگر به اجبار مدیریت مجموعه.',
        ],
        [
            'q' => 'چرا در ثبت‌نام دوره هیچ دوره‌ای برای من نیست؟',
            'a' => 'ممکن است دوره در انتظار پرداخت باشد. فیلتر ثبت‌نام را روی «همه دوره‌ها» قرار دهید.',
        ],
        [
            'q' => 'صورتحساب را نمی‌توانم لغو کنم؛ چرا؟',
            'a' => 'در حال حاضر فقط مدیریت می‌تواند صورتحساب را لغو کند.',
        ],
    ];

    $site_faqs = sc_bot_get_faq_items(15);
    $lines = ["<b>سوالات متداول</b>", '', $default_intro, ''];

    if (!empty($site_faqs)) {
        $lines[] = '<b>📋 سوالات باشگاه:</b>';
        $n = 1;
        foreach ($site_faqs as $faq) {
            $q = sc_bot_truncate(wp_strip_all_tags($faq->question), 200);
            $a = sc_bot_truncate(wp_strip_all_tags($faq->answer), 300);
            if ($q === '') {
                continue;
            }
            $lines[] = '';
            $lines[] = '<b>' . $n . '. ' . esc_html($q) . '</b>';
            if ($a !== '') {
                $lines[] = esc_html($a);
            }
            $n++;
        }
        $lines[] = '';
    }

    $lines[] = '<b>📌 راهنمای عمومی:</b>';
    foreach ($default_faqs as $i => $faq) {
        $lines[] = '';
        $lines[] = '<b>' . ($i + 1) . '. ' . esc_html($faq['q']) . '</b>';
        $lines[] = esc_html($faq['a']);
    }

    $buttons = [
        [bale_make_link_button('سوالات متداول در سایت', '/my-account/sc-faq')],
        [bale_make_link_button('ثبت تیکت پشتیبانی', '/my-account/sc-support-tickets')],
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_invoices($chat_id, $page = 1) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $data = sc_bot_get_member_invoices($ctx['member_id'], $page);
    $lines = ['<b>صورتحساب‌ها</b>', ''];

    if (empty($data['items'])) {
        $lines[] = 'صورتحسابی ثبت نشده است.';
    } else {
        foreach ($data['items'] as $inv) {
            $total = (float) $inv->amount + (float) ($inv->penalty_amount ?? 0);
            $lines[] = '▫️ <b>' . esc_html(sc_bot_invoice_item_name($inv)) . '</b>';
            $lines[] = '   ' . sc_bot_invoice_status_label($inv->status) . ' — ' . sc_bot_format_amount($total);
            $lines[] = '   📅 ' . sc_bot_format_date($inv->created_at);
            $lines[] = '';
        }
        $lines[] = 'صفحه ' . $data['page'] . ' از ' . $data['total_pages'] . ' (کل: ' . $data['total'] . ')';
    }

    $detail_buttons = [];
    foreach ($data['items'] as $inv) {
        if (in_array($inv->status, ['pending', 'under_review'], true)) {
            $detail_buttons[] = [
                ['text' => '💳 ' . sc_bot_truncate(sc_bot_invoice_item_name($inv), 28), 'callback_data' => 'binv:d:' . (int) $inv->id],
            ];
        }
    }

    $buttons = sc_bot_pagination_buttons('binv', $data['page'], $data['total_pages'], $detail_buttons);
    $buttons[] = sc_bot_site_link_button('مشاهده در سایت', '/my-account/sc-invoices');
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_invoice_detail($chat_id, $invoice_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $inv = sc_bot_get_invoice_detail($invoice_id, $ctx['member_id']);
    if (!$inv) {
        bale_send_message($chat_id, 'صورتحساب یافت نشد.');
        return;
    }

    $total = (float) $inv->amount + (float) ($inv->penalty_amount ?? 0);
    $lines = [
        '<b>جزئیات صورتحساب</b>',
        '',
        '📌 ' . esc_html(sc_bot_invoice_item_name($inv)),
        'وضعیت: ' . sc_bot_invoice_status_label($inv->status),
        'مبلغ: ' . sc_bot_format_amount($total),
        'تاریخ: ' . sc_bot_format_date($inv->created_at),
    ];
    if (!empty($inv->invoice_description)) {
        $lines[] = 'توضیح: ' . esc_html(sc_bot_truncate($inv->invoice_description, 200));
    }

    $buttons = [[['text' => '◀ بازگشت به لیست', 'callback_data' => 'binv:p1']]];
    $pay_url = sc_bot_get_invoice_payment_url($inv);
    if ($pay_url !== '') {
        $buttons[] = [bale_make_link_button('💳 پرداخت آنلاین', $pay_url)];
    }
    $buttons[] = sc_bot_site_link_button('مشاهده در سایت', '/my-account/sc-invoices');

    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_certificates($chat_id, $page = 1) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $data = sc_bot_get_certificates_list($ctx['member_id'], $page);
    $lines = ['<b>گواهینامه‌ها</b>', ''];

    if (empty($data['items'])) {
        $lines[] = 'گواهینامه‌ای صادر نشده است.';
    } else {
        foreach ($data['items'] as $cert) {
            $title = !empty($cert->title) ? $cert->title : 'گواهینامه';
            $lines[] = '▫️ <b>' . esc_html($title) . '</b>';
            $lines[] = '   📅 ' . sc_bot_format_date($cert->created_at);
            if (!empty($cert->tracking_code)) {
                $lines[] = '   🔖 ' . esc_html($cert->tracking_code);
            }
            $lines[] = '';
        }
        $lines[] = 'صفحه ' . $data['page'] . ' از ' . $data['total_pages'] . ' (کل: ' . $data['total'] . ')';
    }

    $buttons = sc_bot_pagination_buttons('bcert', $data['page'], $data['total_pages']);
    $buttons[] = sc_bot_site_link_button('دانلود در سایت', '/my-account/sc-my-certificates');
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_wallet($chat_id, $trans_page = 1) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        bale_send_message($chat_id, 'کیف پول در این مجموعه فعال نیست.');
        return;
    }

    $data = sc_bot_get_wallet_data($ctx['member_id'], $trans_page);
    $lines = [
        '<b>کیف پول</b>',
        '',
        '💰 موجودی: <b>' . esc_html(sc_bot_format_amount($data['balance'])) . '</b>',
        '',
        '<b>آخرین تراکنش‌ها:</b>',
    ];

    if (empty($data['transactions'])) {
        $lines[] = 'تراکنشی ثبت نشده است.';
    } else {
        foreach ($data['transactions'] as $tx) {
            $sign = in_array($tx->transaction_type, ['charge', 'refund'], true) ? '+' : '-';
            $lines[] = '▫️ ' . sc_bot_wallet_type_label($tx->transaction_type) . ' ' . $sign . sc_bot_format_amount($tx->amount);
            $lines[] = '   📅 ' . sc_bot_format_date($tx->created_at);
            if (!empty($tx->description)) {
                $lines[] = '   ' . esc_html(sc_bot_truncate($tx->description, 60));
            }
        }
        $lines[] = '';
        $lines[] = 'صفحه تراکنش ' . $data['trans_page'] . ' از ' . $data['total_pages'];
    }

    $buttons = sc_bot_pagination_buttons('bwal', $data['trans_page'], $data['total_pages']);
    $buttons[] = sc_bot_site_link_button('شارژ و مدیریت', '/my-account/sc-wallet');
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_notifications($chat_id, $page = 1) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    if (!function_exists('sc_is_pro_feature_notifications_enabled') || !sc_is_pro_feature_notifications_enabled()) {
        bale_send_message($chat_id, 'بخش اطلاعیه‌ها فعال نیست.');
        return;
    }

    $data = sc_bot_get_notifications_list($ctx['user_id'], $page);
    $unread = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications($ctx['user_id']) : 0;
    $lines = ['<b>اطلاعیه‌ها</b>', ''];
    if ($unread > 0) {
        $lines[] = '🔴 خوانده‌نشده: ' . $unread;
        $lines[] = '';
    }

    if (empty($data['items'])) {
        $lines[] = 'اطلاعیه‌ای وجود ندارد.';
    } else {
        foreach ($data['items'] as $n) {
            $read_icon = !empty($n->is_read) ? '✓' : '🔴';
            $lines[] = $read_icon . ' <b>' . esc_html(sc_bot_truncate($n->title, 50)) . '</b>';
            $lines[] = '   📅 ' . sc_bot_format_date($n->created_at);
        }
        $lines[] = '';
        $lines[] = 'صفحه ' . $data['page'] . ' از ' . $data['total_pages'];
    }

    $detail_buttons = [];
    foreach ($data['items'] as $n) {
        $detail_buttons[] = [
            ['text' => '📩 ' . sc_bot_truncate($n->title, 30), 'callback_data' => 'bnotif:d:' . (int) $n->id],
        ];
    }

    $buttons = sc_bot_pagination_buttons('bnotif', $data['page'], $data['total_pages'], $detail_buttons);
    $buttons[] = sc_bot_site_link_button('مشاهده در سایت', '/my-account/sc-notifications');
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_notification_detail($chat_id, $notification_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $n = function_exists('sc_get_user_notification_detail')
        ? sc_get_user_notification_detail((int) $notification_id, $ctx['user_id'])
        : null;

    if (!$n) {
        bale_send_message($chat_id, 'اطلاعیه یافت نشد.');
        return;
    }

    if (function_exists('sc_mark_notification_read')) {
        sc_mark_notification_read((int) $notification_id, $ctx['user_id']);
    }

    $lines = [
        '<b>' . esc_html($n->title) . '</b>',
        '',
        esc_html(sc_bot_truncate($n->content, 800)),
        '',
        '📅 ' . sc_bot_format_date($n->created_at),
    ];

    $buttons = [
        [['text' => '◀ بازگشت', 'callback_data' => 'bnotif:p1']],
        sc_bot_site_link_button('مشاهده در سایت', '/my-account/sc-notifications'),
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_orders($chat_id, $page = 1) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    if (!function_exists('sc_is_pro_feature_shop_enabled') || !sc_is_pro_feature_shop_enabled()) {
        bale_send_message($chat_id, 'فروشگاه فعال نیست.');
        return;
    }

    $data = sc_bot_get_orders_list($ctx['user_id'], $page);
    $lines = ['<b>سفارش‌های فروشگاه</b>', ''];

    if (empty($data['items'])) {
        $lines[] = 'سفارشی ثبت نشده است.';
    } else {
        foreach ($data['items'] as $order) {
            if (!is_a($order, 'WC_Order')) {
                continue;
            }
            $lines[] = '▫️ سفارش #' . $order->get_id();
            $lines[] = '   ' . sc_bot_wc_order_status_label($order->get_status());
            $lines[] = '   💰 ' . sc_bot_format_amount($order->get_total());
            $lines[] = '   📅 ' . sc_bot_format_date($order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '');
            $lines[] = '';
        }
        $lines[] = 'صفحه ' . $data['page'] . ' از ' . $data['total_pages'];
    }

    $buttons = sc_bot_pagination_buttons('bord', $data['page'], $data['total_pages']);
    $buttons[] = sc_bot_site_link_button('مشاهده در سایت', '/my-account/my-orders');
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_surveys($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    if (!function_exists('sc_is_pro_feature_surveys_enabled') || !sc_is_pro_feature_surveys_enabled()) {
        bale_send_message($chat_id, 'نظرسنجی فعال نیست.');
        return;
    }

    $list = sc_bot_get_surveys_list($ctx['user_id']);
    $lines = ['<b>نظرسنجی‌ها</b>', ''];

    if (empty($list)) {
        $lines[] = 'نظرسنجی فعالی برای شما نیست.';
    } else {
        foreach ($list as $row) {
            $survey = $row['survey'] ?? null;
            if (!$survey) {
                continue;
            }
            $done = !empty($row['completed']);
            $icon = $done ? '✅' : '📝';
            $lines[] = $icon . ' <b>' . esc_html(sc_bot_truncate($survey->title ?? '', 50)) . '</b>';
            if ($done && !empty($row['completed_at'])) {
                $lines[] = '   تکمیل: ' . sc_bot_format_date($row['completed_at']);
            }
        }
    }

    $buttons = [sc_bot_site_link_button('شرکت در نظرسنجی', '/my-account/sc-surveys')];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_private_notes($chat_id, $page = 1) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    if (!function_exists('sc_is_pro_feature_private_notes_enabled') || !sc_is_pro_feature_private_notes_enabled()) {
        bale_send_message($chat_id, 'یادداشت‌های خصوصی فعال نیست.');
        return;
    }

    $data = sc_bot_get_private_notes_list($ctx['user_id'], $page);
    $items = $data['rows'] ?? [];
    $lines = ['<b>یادداشت‌های من</b>', ''];

    if (empty($items)) {
        $lines[] = 'یادداشتی ثبت نشده است.';
    } else {
        foreach ($items as $thread) {
            $subject = !empty($thread->subject) ? $thread->subject : 'بدون عنوان';
            $lines[] = '▫️ <b>' . esc_html(sc_bot_truncate($subject, 45)) . '</b>';
            if (!empty($thread->last_message)) {
                $lines[] = '   ' . esc_html(sc_bot_truncate($thread->last_message, 80));
            }
            $lines[] = '   📅 ' . sc_bot_format_date($thread->updated_at ?? $thread->created_at ?? '');
        }
        $lines[] = '';
        $lines[] = 'صفحه ' . ($data['page'] ?? 1) . ' از ' . ($data['total_pages'] ?? 1);
    }

    $buttons = sc_bot_pagination_buttons('bnote', (int) ($data['page'] ?? 1), (int) ($data['total_pages'] ?? 1));
    $buttons[] = sc_bot_site_link_button('مشاهده در سایت', '/my-account/sc-private-notes');
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_private_classes($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $sessions = sc_bot_get_private_sessions($ctx['member_id']);
    $lines = ['<b>کلاس‌های خصوصی</b>', '', '<b>جلسات پیش‌رو:</b>'];

    if (empty($sessions)) {
        $lines[] = 'جلسه‌ای برنامه‌ریزی نشده است.';
    } else {
        foreach ($sessions as $s) {
            $status = function_exists('sc_private_session_status_label')
                ? sc_private_session_status_label($s->status)
                : $s->status;
            $lines[] = '▫️ <b>' . esc_html($s->course_title ?? 'کلاس خصوصی') . '</b>';
            $lines[] = '   📅 ' . sc_bot_format_date($s->session_date);
            if (!empty($s->time_start)) {
                $lines[] = '   🕐 ' . esc_html(substr($s->time_start, 0, 5)) . (!empty($s->time_end) ? ' - ' . esc_html(substr($s->time_end, 0, 5)) : '');
            }
            $lines[] = '   ' . esc_html($status);
            $lines[] = '';
        }
    }

    $buttons = [
        sc_bot_site_link_button('رزرو کلاس خصوصی', '/my-account/sc-private-classes'),
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_courses($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $courses = sc_bot_get_active_courses($ctx['member_id']);
    $lines = ['<b>دوره‌های من</b>', ''];

    if (empty($courses)) {
        $lines[] = 'دوره فعالی ندارید.';
    } else {
        foreach ($courses as $c) {
            $lines[] = '▫️ <b>' . esc_html($c->title) . '</b>';
            $lines[] = '   ' . sc_bot_course_status_label($c);
            if (!empty($c->start_date)) {
                $lines[] = '   📅 شروع: ' . sc_bot_format_date($c->start_date);
            }
            if (isset($c->remaining_sessions) && $c->remaining_sessions !== null && $c->remaining_sessions !== '') {
                $lines[] = '   🎯 جلسات باقی‌مانده: ' . (int) $c->remaining_sessions;
            }
        }
    }

    $buttons = [
        [bale_make_link_button('ثبت‌نام دوره جدید', '/my-account/sc-enroll-course')],
        [bale_make_link_button('دوره‌ها و برنامه هفتگی', '/my-account/sc-my-courses')],
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_attendances($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $rows = sc_bot_get_recent_attendances($ctx['member_id']);
    $lines = ['<b>حضور و غیاب</b>', '', '<b>آخرین موارد:</b>'];

    if (empty($rows)) {
        $lines[] = 'رکوردی ثبت نشده است.';
    } else {
        foreach ($rows as $r) {
            $title = $r->course_title ?: 'دوره';
            $lines[] = '▫️ ' . esc_html($title);
            $lines[] = '   ' . sc_bot_attendance_status_label($r->status) . ' — ' . sc_bot_format_date($r->attendance_date);
        }
    }

    $buttons = [[bale_make_link_button('مشاهده کامل', '/my-account/sc-my-attendances')]];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_honors($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $honors = sc_bot_get_member_honors($ctx['member_id']);
    $lines = ['<b>افتخارات من</b>', ''];

    if (empty($honors)) {
        $lines[] = 'افتخار تاییدشده‌ای ثبت نشده است.';
    } else {
        foreach ($honors as $h) {
            $title = !empty($h->name) ? $h->name : 'افتخار';
            $lines[] = '▫️ 🏆 <b>' . esc_html($title) . '</b>';
            $lines[] = '   📅 ' . sc_bot_format_date($h->created_at);
        }
    }

    $buttons = [
        [bale_make_link_button('افتخارات من', '/my-account/sc-my-honors')],
        [bale_make_link_button('ثبت افتخار جدید', '/my-account/sc-my-honors')],
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_events($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $events = sc_bot_get_member_events($ctx['member_id']);
    $lines = ['<b>رویدادهای من</b>', ''];

    if (empty($events)) {
        $lines[] = 'در رویدادی ثبت‌نام نکرده‌اید.';
    } else {
        foreach ($events as $e) {
            $lines[] = '▫️ <b>' . esc_html($e->name) . '</b>';
            if (!empty($e->holding_date_shamsi)) {
                $lines[] = '   📅 ' . esc_html($e->holding_date_shamsi);
            }
            if (!empty($e->event_location)) {
                $lines[] = '   📍 ' . esc_html(sc_bot_truncate($e->event_location, 40));
            }
            if (!empty($e->invoice_status)) {
                $lines[] = '   ' . sc_bot_invoice_status_label($e->invoice_status);
            }
        }
    }

    $buttons = [
        [bale_make_link_button('رویدادهای من', '/my-account/sc-my-events')],
        [bale_make_link_button('ثبت‌نام رویداد', '/my-account/sc-events')],
    ];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_present_support($chat_id) {
    $ctx = sc_bot_require_ctx_or_exit($chat_id);
    if (!$ctx) {
        return;
    }

    $pending = function_exists('sc_support_count_tickets_for_user')
        ? sc_support_count_tickets_for_user($ctx['user_id'], 'pending_reply')
        : 0;
    $tickets = function_exists('sc_support_get_tickets_for_user')
        ? sc_support_get_tickets_for_user($ctx['user_id'], ['per_page' => 5, 'offset' => 0])
        : [];

    $lines = ['<b>تیکت پشتیبانی</b>', ''];
    if ($pending > 0) {
        $lines[] = '🔴 در انتظار پاسخ: ' . $pending;
        $lines[] = '';
    }

    if (empty($tickets)) {
        $lines[] = 'تیکتی ثبت نشده است.';
    } else {
        foreach ($tickets as $t) {
            $lines[] = '▫️ <b>' . esc_html(sc_bot_truncate($t->subject ?? 'بدون موضوع', 40)) . '</b>';
            $lines[] = '   وضعیت: ' . esc_html($t->status ?? '-');
            $lines[] = '   📅 ' . sc_bot_format_date($t->updated_at ?? $t->created_at ?? '');
        }
    }

    $buttons = [[bale_make_link_button('ارسال / مشاهده تیکت', '/my-account/sc-support-tickets')]];
    bale_send_message_with_buttons($chat_id, implode("\n", $lines), $buttons);
}

function bale_bot_route_data_callback($chat_id, $data, $callback_query_id = '') {
    if ($callback_query_id !== '') {
        bale_answer_callback_query($callback_query_id);
    }

    if ($data === 'blogout:yes') {
        $ok = sc_unlink_bot_account($chat_id);
        if ($ok) {
            bale_send_message_with_keyboard(
                $chat_id,
                '✅ حساب شما با موفقیت از ربات خارج شد.',
                bale_get_connect_keyboard()
            );
        } else {
            bale_send_message($chat_id, 'شما قبلاً از حساب خارج شده‌اید یا حسابی متصل نیست.');
        }
        return true;
    }
    if ($data === 'blogout:no') {
        bale_send_message($chat_id, 'خروج لغو شد. همچنان به حساب متصل هستید.');
        return true;
    }

    if (preg_match('/^binv:d:(\d+)$/', $data, $m)) {
        return bale_present_invoice_detail($chat_id, (int) $m[1]);
    }
    if (preg_match('/^binv:p(\d+)$/', $data, $m)) {
        return bale_present_invoices($chat_id, (int) $m[1]);
    }
    if (preg_match('/^bcert:p(\d+)$/', $data, $m)) {
        return bale_present_certificates($chat_id, (int) $m[1]);
    }
    if (preg_match('/^bwal:p(\d+)$/', $data, $m)) {
        return bale_present_wallet($chat_id, (int) $m[1]);
    }
    if (preg_match('/^bnotif:d:(\d+)$/', $data, $m)) {
        return bale_present_notification_detail($chat_id, (int) $m[1]);
    }
    if (preg_match('/^bnotif:p(\d+)$/', $data, $m)) {
        return bale_present_notifications($chat_id, (int) $m[1]);
    }
    if (preg_match('/^bord:p(\d+)$/', $data, $m)) {
        return bale_present_orders($chat_id, (int) $m[1]);
    }
    if (preg_match('/^bnote:p(\d+)$/', $data, $m)) {
        return bale_present_private_notes($chat_id, (int) $m[1]);
    }

    return false;
}
