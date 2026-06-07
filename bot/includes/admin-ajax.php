<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_sc_bale_set_webhook', 'sc_ajax_bale_set_webhook');
add_action('wp_ajax_sc_bale_delete_webhook', 'sc_ajax_bale_delete_webhook');
add_action('wp_ajax_sc_bale_get_webhook_info', 'sc_ajax_bale_get_webhook_info');
add_action('wp_ajax_sc_bale_preview_recipients', 'sc_ajax_bale_preview_recipients');

function sc_ajax_bale_set_webhook() {
    check_ajax_referer('sc_bale_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!bale_is_configured()) {
        wp_send_json_error(['message' => 'ابتدا توکن و نام کاربری ربات را ذخیره کنید.']);
    }

    $response = bale_set_webhook();
    $parsed = bale_parse_api_response($response);

    if ($parsed['ok']) {
        wp_send_json_success(['message' => 'وب‌هوک با موفقیت تنظیم شد.', 'url' => bale_get_webhook_url()]);
    }

    wp_send_json_error(['message' => $parsed['message']]);
}

function sc_ajax_bale_delete_webhook() {
    check_ajax_referer('sc_bale_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $response = bale_delete_webhook();
    $parsed = bale_parse_api_response($response);

    if ($parsed['ok']) {
        wp_send_json_success(['message' => 'وب‌هوک حذف شد.']);
    }

    wp_send_json_error(['message' => $parsed['message']]);
}

function sc_ajax_bale_get_webhook_info() {
    check_ajax_referer('sc_bale_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!bale_is_configured()) {
        wp_send_json_error(['message' => 'ربات پیکربندی نشده است.']);
    }

    $response = bale_get_webhook_info();
    $parsed = bale_parse_api_response($response);

    if ($parsed['ok']) {
        wp_send_json_success(['info' => $parsed['data']['result'] ?? $parsed['data']]);
    }

    wp_send_json_error(['message' => $parsed['message']]);
}

function sc_bale_normalize_ajax_target_config($target_config) {
    $target_config = (array) $target_config;
    if (isset($target_config['recipient_ids']) && is_string($target_config['recipient_ids'])) {
        $target_config['recipient_ids'] = array_filter(array_map('trim', explode(',', $target_config['recipient_ids'])));
    }
    if (isset($target_config['exclude_recipient_ids']) && is_string($target_config['exclude_recipient_ids'])) {
        $target_config['exclude_recipient_ids'] = array_filter(array_map('trim', explode(',', $target_config['exclude_recipient_ids'])));
    }
    if (isset($target_config['course_ids']) && is_string($target_config['course_ids'])) {
        $target_config['course_ids'] = array_map('absint', array_filter(explode(',', $target_config['course_ids'])));
    }
    if (isset($target_config['event_ids']) && is_string($target_config['event_ids'])) {
        $target_config['event_ids'] = array_map('absint', array_filter(explode(',', $target_config['event_ids'])));
    }
    if (isset($target_config['phone_numbers']) && is_string($target_config['phone_numbers'])) {
        $target_config['phone_numbers'] = array_filter(array_map('trim', explode(',', $target_config['phone_numbers'])));
    }
    return $target_config;
}

function sc_ajax_bale_preview_recipients() {
    check_ajax_referer('sc_bale_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    $target_config = sc_bale_normalize_ajax_target_config(isset($_POST['target_config']) ? $_POST['target_config'] : []);
    $delivery_mode = isset($_POST['delivery_mode']) ? sanitize_text_field(wp_unslash($_POST['delivery_mode'])) : 'bot_only';
    if (!in_array($delivery_mode, ['bot_only', 'safir_only', 'both'], true)) {
        $delivery_mode = 'bot_only';
    }

    $exclude_ids = isset($target_config['exclude_recipient_ids']) ? (array) $target_config['exclude_recipient_ids'] : [];
    $all = sc_bale_apply_excluded_recipients(sc_bale_resolve_recipients($target_type, $target_config), $target_config);
    $stats = sc_bale_count_recipients_by_chat($all);
    $sendable = sc_bale_filter_recipients_for_send($all, $delivery_mode);
    $expected = sc_bale_expected_send_counts($sendable, $delivery_mode);

    $max_rows = 200;
    $items = array_slice($all, 0, $max_rows);
    $total_all = count($all);
    $total_sendable = count($sendable);

    ob_start();
    echo '<div class="sc-bale-preview-stats">';
    echo '<div class="sc-bale-stats" id="sc-bale-preview-stats-box">';
    echo '<div class="sc-bale-stat"><strong id="sc-bale-preview-total">' . esc_html((string) $stats['total']) . '</strong><span>کل مخاطبین فیلتر</span></div>';
    echo '<div class="sc-bale-stat sc-bale-stat--free"><strong id="sc-bale-preview-with">' . esc_html((string) $stats['with_chat_id']) . '</strong><span>دارای Chat ID (رایگان)</span></div>';
    echo '<div class="sc-bale-stat sc-bale-stat--paid"><strong id="sc-bale-preview-without">' . esc_html((string) $stats['without_chat_id']) . '</strong><span>بدون Chat ID (سفیر)</span></div>';
    echo '<div class="sc-bale-stat"><strong id="sc-bale-preview-send">' . esc_html((string) $expected['total']) . '</strong><span>انتخاب‌شده برای ارسال</span></div>';
    echo '</div>';
    echo '<p class="description" style="margin-top:12px;" id="sc-bale-preview-send-desc">با حالت ارسال «' . esc_html(sc_bale_delivery_mode_label($delivery_mode)) . '» → ';
    echo '<strong id="sc-bale-preview-bot">' . esc_html((string) $expected['bot']) . '</strong> ارسال رایگان (ربات) و ';
    echo '<strong id="sc-bale-preview-safir">' . esc_html((string) $expected['safir']) . '</strong> ارسال هزینه‌دار (سفیر)';
    echo ' — تیک برداشتن = حذف از ارسال</p>';
    echo '</div>';

    if (empty($all)) {
        echo '<p class="description">هیچ مخاطبی با این فیلترها یافت نشد.</p>';
    } else {
        echo '<div class="sc-bulk-preview-meta">تعداد مخاطبین فیلترشده: <strong>' . esc_html((string) $total_all) . '</strong>';
        echo ' — قابل ارسال با حالت فعلی: <strong>' . esc_html((string) $total_sendable) . '</strong></div>';
        if ($total_sendable === 0) {
            echo '<p class="description" style="color:#b45309;">با حالت ارسال «' . esc_html(sc_bale_delivery_mode_label($delivery_mode)) . '» هیچ‌کدام از این مخاطبین قابل ارسال نیستند. حالت ارسال را تغییر دهید یا مخاطبین را بررسی کنید.</p>';
        }
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        echo '<thead><tr>';
        echo '<th style="width:64px;"><label><input type="checkbox" id="sc-bale-preview-select-all" checked> انتخاب</label></th>';
        echo '<th>نام</th><th>کد ملی</th><th>نوع</th><th>Chat ID</th><th>هزینه</th><th>ارسال</th>';
        echo '</tr></thead><tbody>';
        foreach ($items as $item) {
            $has_chat = !empty($item['has_chat_id']);
            $has_phone = !empty($item['phone']);
            $can_send = sc_bale_recipient_sendable_in_mode($item, $delivery_mode);
            $rid = isset($item['recipient_id']) ? (string) $item['recipient_id'] : '';
            $checked = $rid !== '' && !in_array($rid, $exclude_ids, true);
            echo '<tr data-has-chat="' . ($has_chat ? '1' : '0') . '" data-has-phone="' . ($has_phone ? '1' : '0') . '" data-can-send="' . ($can_send ? '1' : '0') . '">';
            echo '<td><input type="checkbox" class="sc-bale-preview-member-check" data-recipient-id="' . esc_attr($rid) . '" ' . ($checked ? 'checked' : '') . '></td>';
            echo '<td>' . esc_html($item['full_name']) . '</td>';
            echo '<td>' . esc_html($item['national_id']) . '</td>';
            echo '<td>' . esc_html($item['type']) . '</td>';
            echo '<td>' . ($has_chat ? '<code>' . esc_html((string) $item['bot_id']) . '</code>' : '<span style="color:#b45309;">ندارد</span>') . '</td>';
            echo '<td>' . ($has_chat ? '<span class="sc-bale-badge sc-bale-badge--free">رایگان</span>' : '<span class="sc-bale-badge sc-bale-badge--paid">سفیر</span>') . '</td>';
            echo '<td>' . ($can_send
                ? '<span style="color:#15803d;">✓ قابل ارسال</span>'
                : '<span style="color:#9ca3af;">— در این حالت</span>') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if ($total_all > $max_rows) {
            echo '<p class="description">فقط ' . esc_html((string) $max_rows) . ' مورد اول نمایش داده شد. (کل: ' . esc_html((string) $total_all) . ')</p>';
        }
    }
    $html = ob_get_clean();

    wp_send_json_success([
        'total'           => $stats['total'],
        'with_chat_id'    => $stats['with_chat_id'],
        'without_chat_id' => $stats['without_chat_id'],
        'expected_bot'    => $expected['bot'],
        'expected_safir'  => $expected['safir'],
        'send_total'      => $expected['total'],
        'preview_total'   => $total_all,
        'sendable_total'  => $total_sendable,
        'html'            => $html,
    ]);
}
