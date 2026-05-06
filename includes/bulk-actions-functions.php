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
    $affected = 0;
    $redirect_to = add_query_arg(array('page' => 'sc-bulk-actions'), admin_url('admin.php'));

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';

    if ($action_key === 'send_sms_redirect') {
        $redirect_to = add_query_arg('member_ids', implode(',', $member_ids), admin_url('admin.php?page=sc-add-notification'));
        wp_safe_redirect($redirect_to);
        exit;
    }

    if ($action_key === 'activate_members') {
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('is_active' => 1, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%d', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif ($action_key === 'deactivate_members') {
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('is_active' => 0, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%d', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif ($action_key === 'verify_identity') {
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('identity_verified' => 1, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%d', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
                if (function_exists('sc_send_identity_verified_notifications')) {
                    sc_send_identity_verified_notifications($member_id);
                }
            }
        }
    } elseif ($action_key === 'enable_auto_invoice' || $action_key === 'disable_auto_invoice') {
        $value = ($action_key === 'enable_auto_invoice') ? 0 : 1;
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('disable_auto_invoice' => $value, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%d', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif ($action_key === 'change_team') {
        $team_player = isset($_POST['action_team_player']) ? sanitize_text_field(wp_unslash($_POST['action_team_player'])) : '';
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('team_player' => $team_player, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%s', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif ($action_key === 'change_level') {
        $skill_level = isset($_POST['action_skill_level']) ? sanitize_text_field(wp_unslash($_POST['action_skill_level'])) : '';
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('skill_level' => $skill_level, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%s', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif ($action_key === 'change_member_type') {
        $member_type = isset($_POST['action_member_type']) ? sanitize_text_field(wp_unslash($_POST['action_member_type'])) : 'normal';
        if (!in_array($member_type, array('normal', 'team'), true)) {
            $member_type = 'normal';
        }
        foreach ($member_ids as $member_id) {
            $res = $wpdb->update($members_table, array('member_type' => $member_type, 'updated_at' => current_time('mysql')), array('id' => $member_id), array('%s', '%s'), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif ($action_key === 'assign_course_coach') {
        $assign_course_id = isset($_POST['assign_course_id']) ? absint($_POST['assign_course_id']) : 0;
        $assign_coach_id = isset($_POST['assign_coach_id']) ? absint($_POST['assign_coach_id']) : 0;

        if ($assign_course_id > 0 && $assign_coach_id > 0) {
            $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
            $coaches_table = $wpdb->prefix . 'sc_coaches';

            // اعتبارسنجی: مربی انتخابی باید در همان دوره فعال باشد
            $is_valid_course_coach = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM $course_coaches_table cc
                 INNER JOIN $coaches_table c ON c.id = cc.coach_id
                 WHERE cc.course_id = %d AND cc.coach_id = %d AND c.is_active = 1",
                $assign_course_id,
                $assign_coach_id
            ));

            if ($is_valid_course_coach > 0) {
                $member_ids_sql = implode(',', array_map('absint', $member_ids));
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id
                     FROM $member_courses_table
                     WHERE member_id IN ($member_ids_sql)
                       AND course_id = %d",
                    $assign_course_id
                ));

                foreach ($rows as $row) {
                    $res = $wpdb->update(
                        $member_courses_table,
                        array(
                            'coach_id' => $assign_coach_id,
                            'updated_at' => current_time('mysql'),
                        ),
                        array('id' => (int) $row->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                    if ($res !== false) {
                        $affected++;
                    }
                }
            }
        }
    } elseif ($action_key === 'delete_members') {
        foreach ($member_ids as $member_id) {
            sc_delete_wp_user_by_table_id($members_table, $member_id);
            $res = $wpdb->delete($members_table, array('id' => $member_id), array('%d'));
            if ($res !== false) {
                $affected++;
            }
        }
    } elseif (in_array($action_key, array('course_activate', 'course_deactivate', 'course_set_flag'), true)) {
        $course_ids = isset($_POST['action_course_ids']) ? array_filter(array_map('absint', (array) $_POST['action_course_ids'])) : array();
        if (!empty($course_ids)) {
            $member_ids_sql = implode(',', array_map('absint', $member_ids));
            $course_ids_sql = implode(',', array_map('absint', $course_ids));
            $rows = $wpdb->get_results("SELECT id, course_status_flags FROM $member_courses_table WHERE member_id IN ($member_ids_sql) AND course_id IN ($course_ids_sql)");
            foreach ($rows as $row) {
                if ($action_key === 'course_activate') {
                    $res = $wpdb->update(
                        $member_courses_table,
                        array('status' => 'active', 'course_status_flags' => '', 'updated_at' => current_time('mysql')),
                        array('id' => (int) $row->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                } elseif ($action_key === 'course_deactivate') {
                    $res = $wpdb->update(
                        $member_courses_table,
                        array('status' => 'inactive', 'course_status_flags' => '', 'updated_at' => current_time('mysql')),
                        array('id' => (int) $row->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                } else {
                    $flag_key = isset($_POST['action_course_flag']) ? sanitize_text_field(wp_unslash($_POST['action_course_flag'])) : '';
                    $available_flags = sc_bulk_actions_get_course_flag_options();
                    if (!isset($available_flags[$flag_key])) {
                        continue;
                    }
                    $current_flags = !empty($row->course_status_flags) ? array_filter(array_map('trim', explode(',', (string) $row->course_status_flags))) : array();
                    if (!in_array($flag_key, $current_flags, true)) {
                        $current_flags[] = $flag_key;
                    }
                    $flags_string = implode(',', array_unique($current_flags));
                    $res = $wpdb->update(
                        $member_courses_table,
                        array('status' => 'active', 'course_status_flags' => $flags_string, 'updated_at' => current_time('mysql')),
                        array('id' => (int) $row->id),
                        array('%s', '%s', '%s'),
                        array('%d')
                    );
                }
                if ($res !== false) {
                    $affected++;
                }
            }
        }
    }

    if (function_exists('sc_log_activity')) {
        sc_log_activity(
            'updated',
            'member_bulk',
            0,
            'عملیات دسته جمعی اجرا شد: ' . $action_key . ' (' . $affected . ' مورد)',
            null,
            array(
                'action' => $action_key,
                'affected' => $affected,
                'member_count' => count($member_ids),
            )
        );
    }

    $redirect_to = add_query_arg(
        array(
            'page' => 'sc-bulk-actions',
            'sc_bulk_notice' => 'done',
            'affected' => $affected,
            'total' => count($member_ids),
        ),
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect_to);
    exit;
}
