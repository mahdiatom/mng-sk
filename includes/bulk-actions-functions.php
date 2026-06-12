<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_bulk_actions_get_course_flag_options() {
    $default_flags = array(
        'canceled' => 'لغو شده',
        'paused' => 'متوقف شده',
        'completed' => 'تکمیل شده',
    );
    return apply_filters('sc_bulk_actions_course_flags', $default_flags);
}

function sc_bulk_actions_get_members($target_type, $config = array()) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';

    $where = array('1=1');
    $params = array();

    $member_status = isset($config['member_status']) ? sanitize_text_field($config['member_status']) : 'all';
    if ($member_status === 'active') {
        $where[] = 'm.is_active = 1';
    } elseif ($member_status === 'inactive') {
        $where[] = 'm.is_active = 0';
    }

    if (!empty($config['member_type']) && in_array($config['member_type'], array('normal', 'team'), true)) {
        $where[] = "(COALESCE(m.member_type, 'normal') = %s)";
        $params[] = $config['member_type'];
    }

    if ($target_type === 'free_users') {
        // کاربرانی که تاکنون هیچ رکوردی در member_courses نداشته‌اند
        $where[] = "NOT EXISTS (
            SELECT 1
            FROM $member_courses_table mc
            WHERE mc.member_id = m.id
        )";
    } elseif ($target_type === 'specific') {
        $ids = isset($config['member_ids']) && is_array($config['member_ids']) ? array_filter(array_map('absint', $config['member_ids'])) : array();
        if (empty($ids)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $where[] = "m.id IN ($placeholders)";
        $params = array_merge($params, $ids);
    } elseif ($target_type === 'course') {
        $course_ids = isset($config['course_ids']) && is_array($config['course_ids']) ? array_filter(array_map('absint', $config['course_ids'])) : array();
        if (empty($course_ids)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        $where[] = "m.id IN (
            SELECT DISTINCT mc.member_id
            FROM $member_courses_table mc
            WHERE mc.course_id IN ($placeholders)
              AND mc.status = 'active'
              AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
        )";
        $params = array_merge($params, $course_ids);
    } elseif ($target_type === 'event') {
        $event_ids = isset($config['event_ids']) && is_array($config['event_ids']) ? array_filter(array_map('absint', $config['event_ids'])) : array();
        if (empty($event_ids)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
        $where[] = "m.id IN (
            SELECT DISTINCT er.member_id
            FROM $event_registrations_table er
            WHERE er.event_id IN ($placeholders)
        )";
        $params = array_merge($params, $event_ids);
    } elseif ($target_type === 'team') {
        $team_names = isset($config['team_names']) && is_array($config['team_names']) ? array_filter(array_map('sanitize_text_field', $config['team_names'])) : array();
        if (empty($team_names)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($team_names), '%s'));
        $where[] = "m.team_player IN ($placeholders)";
        $params = array_merge($params, $team_names);
    } elseif ($target_type === 'level') {
        $level_names = isset($config['level_names']) && is_array($config['level_names']) ? array_filter(array_map('sanitize_text_field', $config['level_names'])) : array();
        if (empty($level_names)) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($level_names), '%s'));
        $where[] = "m.skill_level IN ($placeholders)";
        $params = array_merge($params, $level_names);
    } elseif ($target_type === 'team_level') {
        $team_names = isset($config['team_names']) && is_array($config['team_names']) ? array_filter(array_map('sanitize_text_field', $config['team_names'])) : array();
        $level_names = isset($config['level_names']) && is_array($config['level_names']) ? array_filter(array_map('sanitize_text_field', $config['level_names'])) : array();
        if (empty($team_names) || empty($level_names)) {
            return array();
        }
        $team_placeholders = implode(',', array_fill(0, count($team_names), '%s'));
        $level_placeholders = implode(',', array_fill(0, count($level_names), '%s'));
        $where[] = "m.team_player IN ($team_placeholders)";
        $where[] = "m.skill_level IN ($level_placeholders)";
        $params = array_merge($params, $team_names, $level_names);
    }

    $query = "
        SELECT m.id, m.first_name, m.last_name, m.national_id, m.member_type, m.team_player, m.skill_level, m.is_active
        FROM $members_table m
        WHERE " . implode(' AND ', $where) . "
        ORDER BY m.last_name ASC, m.first_name ASC
    ";

    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    return $wpdb->get_results($query);
}

function sc_bulk_actions_collect_filter_config($request) {
    $target_type = isset($request['target_type']) ? sanitize_text_field(wp_unslash($request['target_type'])) : 'all';
    if (!in_array($target_type, sc_users_export_get_allowed_target_types(), true)) {
        $target_type = 'all';
    }

    return array(
        'target_type' => $target_type,
        'config' => array(
            'member_ids' => isset($request['member_ids']) ? array_map('absint', (array) $request['member_ids']) : array(),
            'course_ids' => isset($request['course_ids']) ? array_map('absint', (array) $request['course_ids']) : array(),
            'event_ids' => isset($request['event_ids']) ? array_map('absint', (array) $request['event_ids']) : array(),
            'team_names' => isset($request['team_names']) ? array_map('sanitize_text_field', (array) $request['team_names']) : array(),
            'level_names' => isset($request['level_names']) ? array_map('sanitize_text_field', (array) $request['level_names']) : array(),
            'member_type' => isset($request['member_type']) ? sanitize_text_field(wp_unslash($request['member_type'])) : 'all',
            'member_status' => isset($request['member_status']) ? sanitize_text_field(wp_unslash($request['member_status'])) : 'all',
        ),
    );
}

/**
 * نام نمایشی بازیکن برای گزارش دسته‌جمعی.
 */
function sc_bulk_actions_member_label($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    $t = $wpdb->prefix . 'sc_members';
    $mem = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$t} WHERE id = %d LIMIT 1", $member_id));
    if (!$mem) {
        return 'شناسه نامعتبر #' . $member_id;
    }
    $n = trim(($mem->first_name ?: '') . ' ' . ($mem->last_name ?: ''));
    return $n !== '' ? $n : ('کاربر #' . $member_id);
}

/**
 * عنوان فارسی عملیات برای گزارش.
 */
function sc_bulk_actions_action_title($action_key) {
    $map = array(
        'activate_members'       => 'فعال کردن کاربر',
        'deactivate_members'     => 'غیرفعال کردن کاربر',
        'verify_identity'        => 'تأیید احراز هویت',
        'send_sms_redirect'      => 'انتخاب برای ارسال پیامک',
        'enable_auto_invoice'    => 'فعال کردن صورت‌حساب خودکار',
        'disable_auto_invoice'   => 'غیرفعال کردن صورت‌حساب خودکار',
        'change_team'            => 'تغییر تیم',
        'change_level'           => 'تغییر سطح',
        'change_member_type'     => 'تغییر نوع بازیکن',
        'assign_course_coach'    => 'تخصیص مربی به ثبت‌نام دوره',
        'delete_members'         => 'حذف بازیکن',
        'course_activate'        => 'فعال کردن دوره',
        'course_deactivate'      => 'غیرفعال کردن دوره',
        'course_set_flag'        => 'افزودن فلگ به دوره',
        'remaining_sessions_adjust' => 'تغییر جلسات باقی‌مانده',
    );
    return isset($map[$action_key]) ? $map[$action_key] : (string) $action_key;
}

/**
 * @param array<int, array{line:string}|string> $lines
 * @return array<int, array{line:string}>
 */
function sc_bulk_actions_normalize_report_lines(array $lines) {
    $out = array();
    foreach ($lines as $it) {
        if (is_array($it) && isset($it['line'])) {
            $out[] = array('line' => (string) $it['line']);
        } else {
            $out[] = array('line' => (string) $it);
        }
    }
    return $out;
}

/**
 * ذخیره گزارش و بازگشت به صفحه کارهای دسته‌جمعی.
 */
function sc_bulk_actions_finish_with_report($action_key, array $success_lines, array $fail_lines, $filtered_member_count, array $extra = array()) {
    $succ = sc_bulk_actions_normalize_report_lines($success_lines);
    $fail = sc_bulk_actions_normalize_report_lines($fail_lines);
    $report_key = 'sc_bulk_rpt_' . get_current_user_id() . '_' . wp_generate_password(12, false, false);
    set_transient(
        $report_key,
        array_merge(
            array(
                'action_key'   => $action_key,
                'action_title' => sc_bulk_actions_action_title($action_key),
                'successes'    => $succ,
                'failures'     => $fail,
                'ok_count'     => count($succ),
                'fail_count'   => count($fail),
                'member_count' => (int) $filtered_member_count,
            ),
            $extra
        ),
        600
    );
    if (function_exists('sc_log_activity')) {
        sc_log_activity(
            'updated',
            'member_bulk',
            0,
            'کار دسته‌جمعی: ' . sc_bulk_actions_action_title($action_key) . ' — موفق: ' . count($succ) . '، ناموفق: ' . count($fail),
            null,
            array(
                'action'   => $action_key,
                'ok'       => count($succ),
                'fail'     => count($fail),
                'members'  => (int) $filtered_member_count,
            )
        );
    }
    wp_safe_redirect(
        add_query_arg(
            array(
                'page'             => 'sc-bulk-actions',
                'sc_bulk_notice'   => 'report',
                'sc_bulk_report'   => $report_key,
                'affected'         => count($succ),
                'total'            => (int) $filtered_member_count,
            ),
            admin_url('admin.php')
        )
    );
    exit;
}

/**
 * گزارش انتخاب پیامک + هدایت به صفحه افزودن اطلاعیه.
 */
function sc_bulk_actions_finish_sms_redirect(array $success_lines, array $fail_lines, array $member_ids) {
    $succ = sc_bulk_actions_normalize_report_lines($success_lines);
    $fail = sc_bulk_actions_normalize_report_lines($fail_lines);
    $report_key = 'sc_bulk_rpt_' . get_current_user_id() . '_' . wp_generate_password(12, false, false);
    set_transient(
        $report_key,
        array(
            'action_key'   => 'send_sms_redirect',
            'action_title' => sc_bulk_actions_action_title('send_sms_redirect'),
            'successes'    => $succ,
            'failures'     => $fail,
            'ok_count'     => count($succ),
            'fail_count'   => count($fail),
            'member_count' => count($member_ids),
        ),
        600
    );
    if (function_exists('sc_log_activity')) {
        sc_log_activity(
            'updated',
            'member_bulk',
            0,
            'انتخاب برای ارسال پیامک — تعداد: ' . count($member_ids),
            null,
            array('action' => 'send_sms_redirect', 'count' => count($member_ids))
        );
    }
    wp_safe_redirect(
        add_query_arg(
            array(
                'page'               => 'sc-add-notification',
                'member_ids'         => implode(',', array_map('absint', $member_ids)),
                'sc_bulk_sms_report' => $report_key,
            ),
            admin_url('admin.php')
        )
    );
    exit;
}

add_action('wp_ajax_sc_bulk_actions_preview', 'sc_bulk_actions_preview_ajax');
function sc_bulk_actions_preview_ajax() {
    check_ajax_referer('sc_bulk_actions_preview', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'دسترسی غیرمجاز.'));
    }

    $payload = sc_bulk_actions_collect_filter_config($_POST);
    $members = sc_bulk_actions_get_members($payload['target_type'], $payload['config']);
    $total = count($members);
    $preview_rows = array_slice($members, 0, 200);

    ob_start();
    if (empty($members)) {
        echo '<p class="description">هیچ کاربری با این فیلترها پیدا نشد.</p>';
    } else {
        echo '<div class="sc-bulk-preview-meta">تعداد کاربران فیلتر شده: <strong>' . esc_html((string) $total) . '</strong></div>';
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-bulk-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th><th>تیم</th><th>سطح</th><th>وضعیت</th></tr></thead><tbody>';
        foreach ($preview_rows as $member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $type_label = ($member->member_type === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
            $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-bulk-preview-member-check" data-member-id="' . (int) $member->id . '" checked></td>';
            echo '<td>' . esc_html($full_name !== '' ? $full_name : ('کاربر #' . (int) $member->id)) . '</td>';
            echo '<td>' . esc_html((string) ($member->national_id ?: '-')) . '</td>';
            echo '<td>' . esc_html($type_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->team_player ?: '-')) . '</td>';
            echo '<td>' . esc_html((string) ($member->skill_level ?: '-')) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if ($total > 200) {
            echo '<p class="description">فقط 200 مورد اول نمایش داده شد.</p>';
        }
    }
    $html = ob_get_clean();

    wp_send_json_success(
        array(
            'total' => $total,
            'html' => $html,
        )
    );
}

add_action('wp_ajax_sc_invoice_members_preview', 'sc_invoice_members_preview_ajax');
function sc_invoice_members_preview_ajax() {
    check_ajax_referer('sc_invoice_members_preview', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'دسترسی غیرمجاز.'));
    }

    $payload = sc_bulk_actions_collect_filter_config($_POST);
    if ($payload['config']['member_status'] === 'all') {
        $payload['config']['member_status'] = 'active';
    }
    $members = sc_bulk_actions_get_members($payload['target_type'], $payload['config']);
    $total = count($members);
    $preview_rows = array_slice($members, 0, 200);

    ob_start();
    if (empty($members)) {
        echo '<p class="description">هیچ کاربری با این فیلترها پیدا نشد.</p>';
    } else {
        echo '<div class="sc-bulk-preview-meta">تعداد کاربران فیلتر شده: <strong>' . esc_html((string) $total) . '</strong></div>';
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-invoice-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th><th>تیم</th><th>سطح</th><th>وضعیت</th></tr></thead><tbody>';
        foreach ($preview_rows as $member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $type_label = ($member->member_type === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
            $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-invoice-preview-member-check" data-member-id="' . (int) $member->id . '" checked></td>';
            echo '<td>' . esc_html($full_name !== '' ? $full_name : ('کاربر #' . (int) $member->id)) . '</td>';
            echo '<td>' . esc_html((string) ($member->national_id ?: '-')) . '</td>';
            echo '<td>' . esc_html($type_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->team_player ?: '-')) . '</td>';
            echo '<td>' . esc_html((string) ($member->skill_level ?: '-')) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if ($total > 200) {
            echo '<p class="description">فقط 200 مورد اول نمایش داده شد.</p>';
        }
    }
    $html = ob_get_clean();

    wp_send_json_success(
        array(
            'total' => $total,
            'html' => $html,
        )
    );
}

add_action('admin_post_sc_bulk_actions_execute', 'sc_bulk_actions_execute_handler');
function sc_bulk_actions_execute_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_bulk_actions_execute_action', 'sc_bulk_actions_execute_nonce');

    $payload = sc_bulk_actions_collect_filter_config($_POST);
    $members = sc_bulk_actions_get_members($payload['target_type'], $payload['config']);
    $member_ids = array_values(array_unique(array_map('absint', wp_list_pluck($members, 'id'))));

    if (empty($member_ids)) {
        wp_safe_redirect(add_query_arg(array('page' => 'sc-bulk-actions', 'sc_bulk_notice' => 'empty'), admin_url('admin.php')));
        exit;
    }

    $action_key = isset($_POST['bulk_action_type']) ? sanitize_text_field(wp_unslash($_POST['bulk_action_type'])) : '';
    $excluded_member_ids = isset($_POST['excluded_member_ids']) ? array_filter(array_map('absint', (array) $_POST['excluded_member_ids'])) : array();
    if (!empty($excluded_member_ids)) {
        $member_ids = array_values(array_diff($member_ids, $excluded_member_ids));
    }
    if (empty($member_ids)) {
        wp_safe_redirect(add_query_arg(array('page' => 'sc-bulk-actions', 'sc_bulk_notice' => 'empty'), admin_url('admin.php')));
        exit;
    }

    $filtered_count = count($member_ids);
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';

    if ($action_key === '') {
        sc_bulk_actions_finish_with_report('', array(), array(array('line' => 'نوع عملیات انتخاب نشده است.')), $filtered_count);
    }

    if ($action_key === 'send_sms_redirect') {
        $sms_succ = array();
        foreach ($member_ids as $mid) {
            $sms_succ[] = sc_bulk_actions_member_label($mid) . ' — برای ارسال اطلاعیه در لیست گیرندگان قرار گرفت.';
        }
        sc_bulk_actions_finish_sms_redirect($sms_succ, array(), $member_ids);
    }

    if ($action_key === 'activate_members') {
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, is_active FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((int) $cur->is_active === 1) {
                $success_lines[] = $label . ' — از قبل فعال بود (تغییری اعمال نشد).';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('is_active' => 1, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%d', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام فعال کردن.';
            } else {
                $success_lines[] = $label . ' — کاربر فعال شد.';
            }
        }
        sc_bulk_actions_finish_with_report('activate_members', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'deactivate_members') {
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, is_active FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((int) $cur->is_active === 0) {
                $success_lines[] = $label . ' — از قبل غیرفعال بود (تغییری اعمال نشد).';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('is_active' => 0, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%d', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام غیرفعال کردن.';
            } else {
                $success_lines[] = $label . ' — کاربر غیرفعال شد.';
            }
        }
        sc_bulk_actions_finish_with_report('deactivate_members', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'verify_identity') {
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, identity_verified FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((int) $cur->identity_verified === 1) {
                $success_lines[] = $label . ' — احراز هویت از قبل تأیید شده بود.';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('identity_verified' => 1, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%d', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام تأیید احراز هویت.';
            } else {
                $success_lines[] = $label . ' — احراز هویت تأیید شد.';
                if (function_exists('sc_send_identity_verified_notifications')) {
                    sc_send_identity_verified_notifications($member_id);
                }
            }
        }
        sc_bulk_actions_finish_with_report('verify_identity', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'enable_auto_invoice' || $action_key === 'disable_auto_invoice') {
        $value = ($action_key === 'enable_auto_invoice') ? 0 : 1;
        $verb_en = ($action_key === 'enable_auto_invoice') ? 'فعال' : 'غیرفعال';
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, disable_auto_invoice FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((int) $cur->disable_auto_invoice === (int) $value) {
                $success_lines[] = $label . ' — صورت‌حساب خودکار از قبل «' . $verb_en . '» بود.';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('disable_auto_invoice' => $value, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%d', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده.';
            } else {
                $success_lines[] = $label . ' — صورت‌حساب خودکار «' . $verb_en . '» شد.';
            }
        }
        sc_bulk_actions_finish_with_report($action_key, $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'change_team') {
        $team_player = isset($_POST['action_team_player']) ? sanitize_text_field(wp_unslash($_POST['action_team_player'])) : '';
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, team_player FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((string) $cur->team_player === (string) $team_player) {
                $success_lines[] = $label . ' — تیم بدون تغییر ماند («' . ($team_player !== '' ? $team_player : '-') . '»).';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('team_player' => $team_player, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%s', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام تغییر تیم.';
            } else {
                $success_lines[] = $label . ' — تیم به «' . ($team_player !== '' ? $team_player : '(خالی)') . '» تغییر کرد.';
            }
        }
        sc_bulk_actions_finish_with_report('change_team', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'change_level') {
        $skill_level = isset($_POST['action_skill_level']) ? sanitize_text_field(wp_unslash($_POST['action_skill_level'])) : '';
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, skill_level FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((string) $cur->skill_level === (string) $skill_level) {
                $success_lines[] = $label . ' — سطح بدون تغییر ماند («' . ($skill_level !== '' ? $skill_level : '-') . '»).';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('skill_level' => $skill_level, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%s', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام تغییر سطح.';
            } else {
                $success_lines[] = $label . ' — سطح به «' . ($skill_level !== '' ? $skill_level : '(خالی)') . '» تغییر کرد.';
            }
        }
        sc_bulk_actions_finish_with_report('change_level', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'change_member_type') {
        $member_type = isset($_POST['action_member_type']) ? sanitize_text_field(wp_unslash($_POST['action_member_type'])) : 'normal';
        if (!in_array($member_type, array('normal', 'team'), true)) {
            $member_type = 'normal';
        }
        $type_fa = ($member_type === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $cur = $wpdb->get_row($wpdb->prepare("SELECT id, member_type FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
            if (!$cur) {
                $fail_lines[] = $label . ' — در پایگاه داده یافت نشد.';
                continue;
            }
            if ((string) $cur->member_type === (string) $member_type) {
                $success_lines[] = $label . ' — نوع بازیکن بدون تغییر ماند («' . $type_fa . '»).';
                continue;
            }
            $res = $wpdb->update(
                $members_table,
                array('member_type' => $member_type, 'updated_at' => current_time('mysql')),
                array('id' => $member_id),
                array('%s', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام تغییر نوع بازیکن.';
            } else {
                $success_lines[] = $label . ' — نوع بازیکن به «' . $type_fa . '» تغییر کرد.';
            }
        }
        sc_bulk_actions_finish_with_report('change_member_type', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'assign_course_coach') {
        $assign_course_id = isset($_POST['assign_course_id']) ? absint($_POST['assign_course_id']) : 0;
        $assign_chapter_name = isset($_POST['assign_chapter_name']) ? sanitize_text_field(wp_unslash($_POST['assign_chapter_name'])) : '';
        $assign_coach_id = isset($_POST['assign_coach_id']) ? absint($_POST['assign_coach_id']) : 0;
        if ($assign_course_id <= 0 || $assign_chapter_name === '' || $assign_coach_id <= 0) {
            sc_bulk_actions_finish_with_report(
                'assign_course_coach',
                array(),
                array(array('line' => 'دوره، شعبه یا مربی انتخاب نشده است.')),
                $filtered_count
            );
        }
        if (!function_exists('sc_is_valid_course_chapter_coach') || !sc_is_valid_course_chapter_coach($assign_course_id, $assign_chapter_name, $assign_coach_id)) {
            sc_bulk_actions_finish_with_report(
                'assign_course_coach',
                array(),
                array(array('line' => 'مربی انتخاب‌شده برای این دوره و شعبه معتبر نیست یا غیرفعال است.')),
                $filtered_count
            );
        }
        $course_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$courses_table} WHERE id = %d", $assign_course_id));
        $course_title = $course_title ? (string) $course_title : ('#' . $assign_course_id);
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $coach_row = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$coaches_table} WHERE id = %d LIMIT 1", $assign_coach_id));
        $coach_label = $coach_row ? trim(($coach_row->first_name ?: '') . ' ' . ($coach_row->last_name ?: '')) : '';
        if ($coach_label === '') {
            $coach_label = 'مربی #' . $assign_coach_id;
        }

        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            $mc_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$member_courses_table} WHERE member_id = %d AND course_id = %d LIMIT 1",
                    $member_id,
                    $assign_course_id
                )
            );
            if (!$mc_id) {
                $fail_lines[] = $label . ' — ثبت‌نام برای دوره «' . $course_title . '» ندارد.';
                continue;
            }
            $res = $wpdb->update(
                $member_courses_table,
                array(
                    'coach_id'   => $assign_coach_id,
                    'chapter'    => $assign_chapter_name,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => (int) $mc_id),
                array('%d', '%s', '%s'),
                array('%d')
            );
            if ($res === false) {
                $fail_lines[] = $label . ' — خطای پایگاه داده هنگام تخصیص مربی.';
            } else {
                $success_lines[] = $label . ' — برای دوره «' . $course_title . '» در شعبه «' . $assign_chapter_name . '» به مربی «' . $coach_label . '» تخصیص داده شد.';
            }
        }
        sc_bulk_actions_finish_with_report('assign_course_coach', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'delete_members') {
        $success_lines = array();
        $fail_lines = array();
        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            sc_delete_wp_user_by_table_id($members_table, $member_id);
            $res = $wpdb->delete($members_table, array('id' => $member_id), array('%d'));
            if ($res) {
                $success_lines[] = $label . ' — حذف شد.';
            } else {
                $fail_lines[] = $label . ' — حذف انجام نشد (رکورد وجود نداشت یا خطای پایگاه داده).';
            }
        }
        sc_bulk_actions_finish_with_report('delete_members', $success_lines, $fail_lines, $filtered_count);
    }

    if ($action_key === 'course_activate') {
        if (!function_exists('sc_member_course_activate_one')) {
            sc_bulk_actions_finish_with_report(
                'course_activate',
                array(),
                array(array('line' => 'تابع فعال‌سازی دوره در دسترس نیست.')),
                $filtered_count
            );
        }
        $course_ids = isset($_POST['action_course_ids']) ? array_filter(array_map('absint', (array) $_POST['action_course_ids'])) : array();
        if (empty($course_ids)) {
            sc_bulk_actions_finish_with_report(
                'course_activate',
                array(),
                array(array('line' => 'هیچ دوره‌ای انتخاب نشده است.')),
                $filtered_count
            );
        }
        $course_titles = array();
        foreach ($course_ids as $cid) {
            $cid = absint($cid);
            if (!$cid) {
                continue;
            }
            $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$courses_table} WHERE id = %d", $cid));
            $course_titles[$cid] = $title ? $title : ('#' . $cid);
        }
        $report_success = array();
        $report_fail = array();
        foreach ($member_ids as $member_id) {
            $mem_name = sc_bulk_actions_member_label($member_id);
            foreach ($course_ids as $course_id) {
                $course_id = absint($course_id);
                if (!$course_id) {
                    continue;
                }
                $ctitle = isset($course_titles[$course_id]) ? $course_titles[$course_id] : ('#' . $course_id);
                $r = sc_member_course_activate_one($member_id, $course_id, array());
                if (is_wp_error($r)) {
                    $report_fail[] = $mem_name . ' — «' . $ctitle . '» — ' . $r->get_error_message();
                } else {
                    $report_success[] = $mem_name . ' — «' . $ctitle . '» — دوره فعال شد.';
                }
            }
        }
        sc_bulk_actions_finish_with_report(
            'course_activate',
            $report_success,
            $report_fail,
            $filtered_count,
            array('course_count' => count($course_ids))
        );
    }

    if (in_array($action_key, array('course_deactivate', 'course_set_flag'), true)) {
        $course_ids = isset($_POST['action_course_ids']) ? array_filter(array_map('absint', (array) $_POST['action_course_ids'])) : array();
        if (empty($course_ids)) {
            sc_bulk_actions_finish_with_report(
                $action_key,
                array(),
                array(array('line' => 'هیچ دوره‌ای انتخاب نشده است.')),
                $filtered_count
            );
        }

        $flag_key = isset($_POST['action_course_flag']) ? sanitize_text_field(wp_unslash($_POST['action_course_flag'])) : '';
        $available_flags = sc_bulk_actions_get_course_flag_options();
        if ($action_key === 'course_set_flag' && !isset($available_flags[$flag_key])) {
            sc_bulk_actions_finish_with_report(
                $action_key,
                array(),
                array(array('line' => 'فلگ انتخاب‌شده معتبر نیست.')),
                $filtered_count
            );
        }

        $course_titles = array();
        foreach ($course_ids as $cid) {
            $cid = absint($cid);
            if (!$cid) {
                continue;
            }
            $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$courses_table} WHERE id = %d", $cid));
            $course_titles[$cid] = $title ? $title : ('#' . $cid);
        }

        $success_lines = array();
        $fail_lines = array();

        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            foreach ($course_ids as $course_id) {
                $course_id = absint($course_id);
                if (!$course_id) {
                    continue;
                }
                $ctitle = isset($course_titles[$course_id]) ? $course_titles[$course_id] : ('#' . $course_id);
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, status, course_status_flags FROM {$member_courses_table} WHERE member_id = %d AND course_id = %d LIMIT 1",
                        $member_id,
                        $course_id
                    )
                );
                if (!$row) {
                    $fail_lines[] = $label . ' — «' . $ctitle . '» — ثبت‌نام برای این دوره وجود ندارد.';
                    continue;
                }

                if ($action_key === 'course_deactivate') {
                    if ((string) $row->status === 'inactive' && ($row->course_status_flags === '' || $row->course_status_flags === null)) {
                        $success_lines[] = $label . ' — «' . $ctitle . '» — از قبل غیرفعال بود.';
                        continue;
                    }
                    $res = $wpdb->update(
                        $member_courses_table,
                        array('status' => 'inactive', 'course_status_flags' => '', 'updated_at' => current_time('mysql')),
                        array('id' => (int) $row->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                    if ($res === false) {
                        $fail_lines[] = $label . ' — «' . $ctitle . '» — خطای پایگاه داده هنگام غیرفعال کردن.';
                    } else {
                        $success_lines[] = $label . ' — «' . $ctitle . '» — دوره غیرفعال شد.';
                    }
                } else {
                    $current_flags = !empty($row->course_status_flags) ? array_filter(array_map('trim', explode(',', (string) $row->course_status_flags))) : array();
                    if (in_array($flag_key, $current_flags, true)) {
                        $success_lines[] = $label . ' — «' . $ctitle . '» — فلگ «' . $available_flags[$flag_key] . '» از قبل وجود داشت.';
                        continue;
                    }
                    $current_flags[] = $flag_key;
                    $flags_string = implode(',', array_unique($current_flags));
                    $res = $wpdb->update(
                        $member_courses_table,
                        array('status' => 'active', 'course_status_flags' => $flags_string, 'updated_at' => current_time('mysql')),
                        array('id' => (int) $row->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                    if ($res === false) {
                        $fail_lines[] = $label . ' — «' . $ctitle . '» — خطای پایگاه داده هنگام افزودن فلگ.';
                    } else {
                        $success_lines[] = $label . ' — «' . $ctitle . '» — فلگ «' . $available_flags[$flag_key] . '» افزوده شد.';
                    }
                }
            }
        }

        sc_bulk_actions_finish_with_report($action_key, $success_lines, $fail_lines, $filtered_count, array('course_count' => count($course_ids)));
    }

    if ($action_key === 'remaining_sessions_adjust') {
        $course_ids = isset($_POST['action_course_ids']) ? array_filter(array_map('absint', (array) $_POST['action_course_ids'])) : array();
        if (empty($course_ids)) {
            sc_bulk_actions_finish_with_report(
                'remaining_sessions_adjust',
                array(),
                array(array('line' => 'هیچ دوره‌ای انتخاب نشده است.')),
                $filtered_count
            );
        }

        $mode = isset($_POST['remaining_sessions_mode']) ? sanitize_text_field(wp_unslash($_POST['remaining_sessions_mode'])) : '';
        if (!in_array($mode, array('set', 'add', 'subtract'), true)) {
            sc_bulk_actions_finish_with_report(
                'remaining_sessions_adjust',
                array(),
                array(array('line' => 'نوع تغییر جلسات نامعتبر است.')),
                $filtered_count
            );
        }

        $amt_raw = isset($_POST['remaining_sessions_amount']) ? trim(wp_unslash((string) $_POST['remaining_sessions_amount'])) : '';
        if ($amt_raw === '' || !preg_match('/^\d+$/', $amt_raw)) {
            sc_bulk_actions_finish_with_report(
                'remaining_sessions_adjust',
                array(),
                array(array('line' => 'مقدار باید عدد صحیح و بدون علامت منفی باشد.')),
                $filtered_count
            );
        }
        $amount = (int) $amt_raw;

        $mode_labels = array(
            'set'      => 'ثبت مقدار مشخص',
            'add'      => 'افزایش',
            'subtract' => 'کاهش',
        );
        $mode_fa = $mode_labels[$mode];

        $course_titles = array();
        foreach ($course_ids as $cid) {
            $cid = absint($cid);
            if (!$cid) {
                continue;
            }
            $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$courses_table} WHERE id = %d", $cid));
            $course_titles[$cid] = $title ? $title : ('#' . $cid);
        }

        $success_lines = array();
        $fail_lines = array();

        foreach ($member_ids as $member_id) {
            $label = sc_bulk_actions_member_label($member_id);
            foreach ($course_ids as $course_id) {
                $course_id = absint($course_id);
                if (!$course_id) {
                    continue;
                }
                $ctitle = isset($course_titles[$course_id]) ? $course_titles[$course_id] : ('#' . $course_id);
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, remaining_sessions FROM {$member_courses_table} WHERE member_id = %d AND course_id = %d LIMIT 1",
                        $member_id,
                        $course_id
                    )
                );
                if (!$row) {
                    $fail_lines[] = $label . ' — «' . $ctitle . '» — ثبت‌نام برای این دوره وجود ندارد.';
                    continue;
                }

                $old = (int) $row->remaining_sessions;
                if ($mode === 'set') {
                    $new = $amount;
                } elseif ($mode === 'add') {
                    $new = $old + $amount;
                } else {
                    $new = max(0, $old - $amount);
                }

                if ($new === $old) {
                    $success_lines[] = $label . ' — «' . $ctitle . '» — جلسات باقی‌مانده بدون تغییر ماند (' . $old . ')؛ (' . $mode_fa . ').';
                    continue;
                }

                $res = $wpdb->update(
                    $member_courses_table,
                    array(
                        'remaining_sessions' => $new,
                        'updated_at'         => current_time('mysql'),
                    ),
                    array('id' => (int) $row->id),
                    array('%d', '%s'),
                    array('%d')
                );
                if ($res === false) {
                    $fail_lines[] = $label . ' — «' . $ctitle . '» — خطای پایگاه داده هنگام به‌روزرسانی جلسات.';
                } else {
                    $success_lines[] = $label . ' — «' . $ctitle . '» — جلسات باقی‌مانده از ' . $old . ' به ' . $new . ' تغییر کرد (' . $mode_fa . ').';
                }
            }
        }

        sc_bulk_actions_finish_with_report(
            'remaining_sessions_adjust',
            $success_lines,
            $fail_lines,
            $filtered_count,
            array('course_count' => count($course_ids))
        );
    }

    sc_bulk_actions_finish_with_report(
        $action_key,
        array(),
        array(array('line' => 'عملیات نامعتبر است یا پشتیبانی نمی‌شود.')),
        $filtered_count
    );
}
