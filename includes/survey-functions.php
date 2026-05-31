<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Survey system – core helpers, CRUD, audience, submission, stats, export.
 */

function sc_get_survey_question_types() {
    return [
        'text' => 'متن کوتاه',
        'textarea' => 'متن بلند',
        'number' => 'عدد',
        'single_choice' => 'تک‌گزینه‌ای',
        'multiple_choice' => 'چندگزینه‌ای',
        'yes_no' => 'بله / خیر',
        'rating' => 'امتیاز (ستاره)',
        'date' => 'تاریخ',
        'file' => 'آپلود فایل',
    ];
}

function sc_survey_decode_json($raw, $default = []) {
    if (is_array($raw)) {
        return $raw;
    }
    if ($raw === null || $raw === '') {
        return $default;
    }
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function sc_get_survey($survey_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_surveys';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", absint($survey_id)));
}

function sc_get_survey_questions($survey_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_survey_questions';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE survey_id = %d ORDER BY sort_order ASC, id ASC",
        absint($survey_id)
    ));
}

function sc_survey_public_url($survey) {
    if (!$survey || empty($survey->public_token)) {
        return '';
    }
    return add_query_arg('sc_public_survey', $survey->public_token, home_url('/'));
}

function sc_survey_account_fill_url($survey_id) {
    $survey_id = absint($survey_id);
    if (!$survey_id || !function_exists('wc_get_account_endpoint_url')) {
        return add_query_arg('sc_fill_survey', $survey_id, home_url('/'));
    }
    return add_query_arg('sc_fill_survey', $survey_id, wc_get_account_endpoint_url('sc-surveys'));
}

function sc_survey_is_account_surveys_context() {
    if (function_exists('is_account_page') && is_account_page()) {
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('sc-surveys')) {
            return true;
        }
        $req_uri = isset($_SERVER['REQUEST_URI']) ? rawurldecode((string) wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($req_uri !== '' && strpos($req_uri, 'sc-surveys') !== false) {
            return true;
        }
    }
    return false;
}

function sc_survey_generate_token() {
    return wp_generate_password(20, false, false);
}

function sc_survey_save($data, $questions = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_surveys';
    $now = current_time('mysql');
    $survey_id = isset($data['id']) ? absint($data['id']) : 0;

    $row = [
        'title' => isset($data['title']) ? sanitize_text_field($data['title']) : '',
        'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
        'is_active' => !empty($data['is_active']) ? 1 : 0,
        'is_public' => !empty($data['is_public']) ? 1 : 0,
        'audience_config' => wp_json_encode(isset($data['audience_config']) ? $data['audience_config'] : [], JSON_UNESCAPED_UNICODE),
        'activation_config' => wp_json_encode(isset($data['activation_config']) ? $data['activation_config'] : [], JSON_UNESCAPED_UNICODE),
        'thank_you_message' => isset($data['thank_you_message']) ? wp_kses_post($data['thank_you_message']) : '',
        'start_at' => !empty($data['start_at']) ? sanitize_text_field($data['start_at']) : null,
        'end_at' => !empty($data['end_at']) ? sanitize_text_field($data['end_at']) : null,
        'updated_at' => $now,
    ];

    if ($survey_id) {
        $existing = sc_get_survey($survey_id);
        if (!$existing) {
            return ['success' => false, 'message' => 'نظرسنجی یافت نشد.'];
        }
        if (!empty($data['is_public']) && empty($existing->public_token)) {
            $row['public_token'] = sc_survey_generate_token();
        } elseif (empty($data['is_public'])) {
            $row['public_token'] = null;
        }
        $wpdb->update($table, $row, ['id' => $survey_id]);
    } else {
        $row['public_token'] = !empty($data['is_public']) ? sc_survey_generate_token() : null;
        $row['created_by'] = get_current_user_id();
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        $survey_id = (int) $wpdb->insert_id;
    }

    if ($questions !== null && $survey_id) {
        sc_survey_save_questions($survey_id, $questions);
    }

    return ['success' => true, 'survey_id' => $survey_id];
}

function sc_survey_save_questions($survey_id, $questions) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_survey_questions';
    $survey_id = absint($survey_id);
    $now = current_time('mysql');

    $existing_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE survey_id = %d", $survey_id));
    $keep_ids = [];
    $order = 0;

    foreach ((array) $questions as $q) {
        $order++;
        $qid = isset($q['id']) ? absint($q['id']) : 0;
        $row = [
            'survey_id' => $survey_id,
            'question_type' => isset($q['question_type']) ? sanitize_key($q['question_type']) : 'text',
            'question_text' => isset($q['question_text']) ? wp_kses_post($q['question_text']) : '',
            'options_json' => wp_json_encode(isset($q['options']) ? $q['options'] : [], JSON_UNESCAPED_UNICODE),
            'settings_json' => wp_json_encode(isset($q['settings']) ? $q['settings'] : [], JSON_UNESCAPED_UNICODE),
            'sort_order' => $order,
            'updated_at' => $now,
        ];

        if ($qid && in_array((string) $qid, array_map('strval', $existing_ids), true)) {
            $wpdb->update($table, $row, ['id' => $qid]);
            $keep_ids[] = $qid;
        } else {
            $row['created_at'] = $now;
            $wpdb->insert($table, $row);
            $keep_ids[] = (int) $wpdb->insert_id;
        }
    }

    foreach ($existing_ids as $eid) {
        if (!in_array((int) $eid, $keep_ids, true)) {
            $wpdb->delete($table, ['id' => absint($eid)], ['%d']);
        }
    }
}

function sc_survey_has_responses($survey_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_survey_responses';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE survey_id = %d",
        absint($survey_id)
    )) > 0;
}

function sc_survey_delete($survey_id) {
    global $wpdb;
    $survey_id = absint($survey_id);
    if (!$survey_id) {
        return ['success' => false, 'message' => 'شناسه نامعتبر است.'];
    }

    $responses_table = $wpdb->prefix . 'sc_survey_responses';
    $answers_table = $wpdb->prefix . 'sc_survey_answers';
    $response_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM $responses_table WHERE survey_id = %d",
        $survey_id
    ));
    if (!empty($response_ids)) {
        $placeholders = implode(',', array_fill(0, count($response_ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $answers_table WHERE response_id IN ($placeholders)",
            ...array_map('absint', $response_ids)
        ));
        $wpdb->delete($responses_table, ['survey_id' => $survey_id], ['%d']);
    }

    $wpdb->delete($wpdb->prefix . 'sc_survey_questions', ['survey_id' => $survey_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'sc_survey_eligibility', ['survey_id' => $survey_id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'sc_surveys', ['id' => $survey_id], ['%d']);
    return ['success' => true];
}

function sc_survey_delete_responses($response_ids) {
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'دسترسی غیرمجاز.', 'deleted' => 0];
    }

    global $wpdb;
    $response_ids = array_values(array_filter(array_map('absint', (array) $response_ids)));
    if (empty($response_ids)) {
        return ['success' => false, 'message' => 'موردی انتخاب نشده است.', 'deleted' => 0];
    }

    $answers_table = $wpdb->prefix . 'sc_survey_answers';
    $responses_table = $wpdb->prefix . 'sc_survey_responses';
    $placeholders = implode(',', array_fill(0, count($response_ids), '%d'));

    $wpdb->query($wpdb->prepare(
        "DELETE FROM $answers_table WHERE response_id IN ($placeholders)",
        ...$response_ids
    ));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $responses_table WHERE id IN ($placeholders)",
        ...$response_ids
    ));

    return [
        'success' => true,
        'deleted' => is_numeric($deleted) ? (int) $deleted : 0,
        'message' => sprintf('%d پاسخ حذف شد.', is_numeric($deleted) ? (int) $deleted : 0),
    ];
}

function sc_survey_delete_response($response_id) {
    return sc_survey_delete_responses([absint($response_id)]);
}

function sc_survey_bulk_action_surveys($survey_ids, $action) {
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'دسترسی غیرمجاز.', 'count' => 0];
    }

    $survey_ids = array_values(array_filter(array_map('absint', (array) $survey_ids)));
    if (empty($survey_ids)) {
        return ['success' => false, 'message' => 'موردی انتخاب نشده است.', 'count' => 0];
    }

    $action = sanitize_key($action);
    $count = 0;
    global $wpdb;
    $table = $wpdb->prefix . 'sc_surveys';

    if ($action === 'delete') {
        foreach ($survey_ids as $survey_id) {
            $result = sc_survey_delete($survey_id);
            if (!empty($result['success'])) {
                $count++;
            }
        }
        return [
            'success' => true,
            'count' => $count,
            'message' => sprintf('%d نظرسنجی حذف شد.', $count),
        ];
    }

    if ($action === 'activate') {
        foreach ($survey_ids as $survey_id) {
            if (false !== $wpdb->update($table, ['is_active' => 1], ['id' => $survey_id], ['%d'], ['%d'])) {
                $count++;
            }
        }
        return [
            'success' => true,
            'count' => $count,
            'message' => sprintf('%d نظرسنجی فعال شد.', $count),
        ];
    }

    if ($action === 'deactivate') {
        foreach ($survey_ids as $survey_id) {
            if (false !== $wpdb->update($table, ['is_active' => 0], ['id' => $survey_id], ['%d'], ['%d'])) {
                $count++;
            }
        }
        return [
            'success' => true,
            'count' => $count,
            'message' => sprintf('%d نظرسنجی غیرفعال شد.', $count),
        ];
    }

    return ['success' => false, 'message' => 'عملیات نامعتبر است.', 'count' => 0];
}

function sc_survey_get_by_public_token($token) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_surveys';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE public_token = %s AND is_public = 1",
        sanitize_text_field($token)
    ));
}

function sc_survey_get_member_id_for_user($user_id) {
    global $wpdb;
    if (!$user_id) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_members WHERE user_id = %d LIMIT 1",
        absint($user_id)
    ));
}

function sc_survey_get_coach_id_for_user($user_id) {
    global $wpdb;
    if (!$user_id) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d LIMIT 1",
        absint($user_id)
    ));
}

function sc_survey_parse_audience_from_post($post) {
    $target_type = isset($post['target_type']) ? sanitize_text_field(wp_unslash($post['target_type'])) : 'all';
    $allowed_types = ['all', 'free_users', 'specific', 'course', 'event', 'team', 'level', 'team_level'];
    if (!in_array($target_type, $allowed_types, true)) {
        $target_type = 'all';
    }

    $target_config = [
        'member_status' => isset($post['member_status']) ? sanitize_text_field(wp_unslash($post['member_status'])) : 'all',
        'member_type' => isset($post['member_type']) ? sanitize_text_field(wp_unslash($post['member_type'])) : 'all',
    ];

    if ($target_type === 'specific') {
        $target_config['member_ids'] = isset($post['member_ids']) ? array_values(array_filter(array_map('absint', (array) $post['member_ids']))) : [];
    } elseif ($target_type === 'course') {
        $target_config['course_ids'] = isset($post['course_ids']) ? array_values(array_filter(array_map('absint', (array) $post['course_ids']))) : [];
    } elseif ($target_type === 'event') {
        $target_config['event_ids'] = isset($post['event_ids']) ? array_values(array_filter(array_map('absint', (array) $post['event_ids']))) : [];
    } elseif ($target_type === 'team') {
        $target_config['team_names'] = isset($post['team_names']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $post['team_names']))) : [];
    } elseif ($target_type === 'level') {
        $target_config['level_names'] = isset($post['level_names']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $post['level_names']))) : [];
    } elseif ($target_type === 'team_level') {
        $target_config['team_names'] = isset($post['team_names']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $post['team_names']))) : [];
        $target_config['level_names'] = isset($post['level_names']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $post['level_names']))) : [];
    }

    $restriction = [
        'enabled' => !empty($post['restriction_enabled']) ? 1 : 0,
        'allowed_gender' => isset($post['allowed_gender']) ? sanitize_text_field(wp_unslash($post['allowed_gender'])) : 'both',
        'allowed_teams' => isset($post['allowed_teams']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $post['allowed_teams']))) : [],
        'allowed_levels' => isset($post['allowed_levels']) ? array_values(array_filter(array_map('sanitize_text_field', (array) $post['allowed_levels']))) : [],
    ];

    return [
        'target_type' => $target_type,
        'target_config' => $target_config,
        'include_players' => !empty($post['include_players']) ? 1 : 0,
        'include_coaches' => !empty($post['include_coaches']) ? 1 : 0,
        'restriction' => $restriction,
    ];
}

function sc_survey_member_passes_restrictions($member_id, $restriction) {
    global $wpdb;
    $member_id = absint($member_id);
    if (!$member_id || empty($restriction['enabled'])) {
        return true;
    }
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT gender, team_player, skill_level FROM {$wpdb->prefix}sc_members WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        return false;
    }

    $gender = isset($restriction['allowed_gender']) ? $restriction['allowed_gender'] : 'both';
    if ($gender !== 'both' && !empty($member->gender) && $member->gender !== $gender) {
        return false;
    }

    $teams = isset($restriction['allowed_teams']) ? (array) $restriction['allowed_teams'] : [];
    if (!empty($teams) && !in_array((string) $member->team_player, $teams, true)) {
        return false;
    }

    $levels = isset($restriction['allowed_levels']) ? (array) $restriction['allowed_levels'] : [];
    if (!empty($levels) && !in_array((string) $member->skill_level, $levels, true)) {
        return false;
    }

    return true;
}

function sc_survey_user_in_audience($survey, $user_id) {
    if (!$survey || !$user_id) {
        return false;
    }
    $audience = sc_survey_decode_json($survey->audience_config);
    $target_type = isset($audience['target_type']) ? $audience['target_type'] : 'all';
    $target_config = isset($audience['target_config']) ? (array) $audience['target_config'] : [];
    $restriction = isset($audience['restriction']) ? (array) $audience['restriction'] : [];

    $include_players = !isset($audience['include_players']) || !empty($audience['include_players']);
    $include_coaches = !isset($audience['include_coaches']) || !empty($audience['include_coaches']);

    $member_id = sc_survey_get_member_id_for_user($user_id);
    $coach_id = sc_survey_get_coach_id_for_user($user_id);
    $is_player = $member_id > 0;
    $is_coach = $coach_id > 0;

    if ($is_coach && $include_coaches && !$is_player) {
        return $target_type === 'all';
    }
    if (!$is_player || !$include_players) {
        return false;
    }

    if (function_exists('sc_bulk_actions_get_members')) {
        $bulk_type = $target_type === 'all' ? 'all' : $target_type;
        $members = sc_bulk_actions_get_members($bulk_type, $target_config);
        $ids = array_map(function ($m) {
            return (int) $m->id;
        }, (array) $members);
        if (!in_array($member_id, $ids, true)) {
            return false;
        }
    }

    return sc_survey_member_passes_restrictions($member_id, $restriction);
}

function sc_survey_parse_response_filters($request) {
    $filters = [
        'filter_course' => isset($request['filter_course']) ? absint($request['filter_course']) : 0,
        'filter_player' => isset($request['filter_player']) ? absint($request['filter_player']) : 0,
        'filter_date_from' => '',
        'filter_date_to' => '',
    ];
    if (!empty($request['filter_date_from_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
        $filters['filter_date_from'] = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($request['filter_date_from_shamsi'])));
    }
    if (!empty($request['filter_date_to_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
        $filters['filter_date_to'] = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($request['filter_date_to_shamsi'])));
    }
    return $filters;
}

function sc_survey_apply_default_date_filters($request) {
    $request = is_array($request) ? $request : [];
    if (!function_exists('sc_get_today_shamsi')) {
        return $request;
    }

    $today = sc_get_today_shamsi();
    if (empty($request['filter_date_from_shamsi'])) {
        $request['filter_date_from_shamsi'] = $today;
    } else {
        $request['filter_date_from_shamsi'] = sanitize_text_field(wp_unslash($request['filter_date_from_shamsi']));
    }
    if (empty($request['filter_date_to_shamsi'])) {
        $request['filter_date_to_shamsi'] = $today;
    } else {
        $request['filter_date_to_shamsi'] = sanitize_text_field(wp_unslash($request['filter_date_to_shamsi']));
    }

    return $request;
}

function sc_survey_get_admin_response_filters($request) {
    return sc_survey_parse_response_filters(sc_survey_apply_default_date_filters($request));
}

function sc_survey_has_active_response_filters($request) {
    if (isset($request['filter_player']) && absint($request['filter_player']) > 0) {
        return true;
    }
    if (isset($request['filter_course']) && absint($request['filter_course']) > 0) {
        return true;
    }
    if (!empty($request['filter_date_from_shamsi']) || !empty($request['filter_date_to_shamsi'])) {
        return true;
    }
    return false;
}

function sc_survey_get_response_filters_for_query($request) {
    if (!sc_survey_has_active_response_filters($request)) {
        return [];
    }
    return sc_survey_parse_response_filters($request);
}

function sc_survey_resolve_page_survey_id($requested_id, $surveys) {
    $requested_id = absint($requested_id);
    if ($requested_id > 0) {
        foreach ((array) $surveys as $survey) {
            if ((int) $survey->id === $requested_id) {
                return $requested_id;
            }
        }
    }
    if (!empty($surveys)) {
        return (int) $surveys[0]->id;
    }
    return 0;
}

function sc_survey_response_display_name($response) {
    $name = trim((string) ($response->first_name ?? '') . ' ' . (string) ($response->last_name ?? ''));
    if ($name !== '') {
        return $name;
    }
    if (!empty($response->guest_data)) {
        $guest = json_decode($response->guest_data, true);
        if (is_array($guest) && !empty($guest['name'])) {
            return sanitize_text_field($guest['name']);
        }
    }
    return 'مهمان / بدون نام';
}

function sc_survey_response_display_phone($response) {
    if (!empty($response->player_phone)) {
        return (string) $response->player_phone;
    }
    if (!empty($response->guest_data)) {
        $guest = json_decode($response->guest_data, true);
        if (is_array($guest) && !empty($guest['phone'])) {
            return sanitize_text_field($guest['phone']);
        }
    }
    return '';
}

function sc_survey_apply_member_filters_sql($filters, $member_alias = 'm') {
    global $wpdb;
    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $parts = [];
    $params = [];

    if (!empty($filters['filter_player'])) {
        $parts[] = "$member_alias.id = %d";
        $params[] = absint($filters['filter_player']);
    }
    if (!empty($filters['filter_course'])) {
        $parts[] = "EXISTS (SELECT 1 FROM $mc_table mc_f WHERE mc_f.member_id = $member_alias.id AND mc_f.course_id = %d AND mc_f.status = 'active' AND (mc_f.course_status_flags IS NULL OR TRIM(mc_f.course_status_flags) = ''))";
        $params[] = absint($filters['filter_course']);
    }

    return ['parts' => $parts, 'params' => $params];
}

function sc_survey_is_eligible_member($survey, $member_id) {
    if (!$survey || !$member_id) {
        return false;
    }
    $activation = sc_survey_decode_json($survey->activation_config);
    $trigger = isset($activation['trigger_type']) ? $activation['trigger_type'] : 'manual';
    if ($trigger !== 'last_session') {
        return true;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_survey_eligibility';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE survey_id = %d AND member_id = %d",
        (int) $survey->id,
        absint($member_id)
    )) > 0;
}

function sc_survey_activation_date_reached($survey) {
    $activation = sc_survey_decode_json($survey->activation_config);
    $trigger = isset($activation['trigger_type']) ? $activation['trigger_type'] : 'manual';
    if ($trigger !== 'on_date') {
        return true;
    }
    $trigger_date = isset($activation['trigger_date']) ? trim((string) $activation['trigger_date']) : '';
    if ($trigger_date === '') {
        return true;
    }
    if (function_exists('sc_shamsi_to_gregorian_date')) {
        $greg = sc_shamsi_to_gregorian_date($trigger_date);
        if ($greg) {
            return strtotime($greg . ' 00:00:00') <= current_time('timestamp');
        }
    }
    return strtotime($trigger_date) <= current_time('timestamp');
}

function sc_survey_within_schedule($survey) {
    $now = current_time('timestamp');
    if (!empty($survey->start_at) && strtotime($survey->start_at) > $now) {
        return false;
    }
    if (!empty($survey->end_at) && strtotime($survey->end_at) < $now) {
        return false;
    }
    return true;
}

function sc_survey_is_available($survey, $user_id = 0, $member_id = 0) {
    if (!$survey || !(int) $survey->is_active) {
        return false;
    }
    if (!sc_survey_within_schedule($survey)) {
        return false;
    }
    if (!sc_survey_activation_date_reached($survey)) {
        return false;
    }

    $activation = sc_survey_decode_json($survey->activation_config);
    $trigger = isset($activation['trigger_type']) ? $activation['trigger_type'] : 'manual';

    if ($trigger === 'last_session') {
        if (!$member_id && $user_id) {
            $member_id = sc_survey_get_member_id_for_user($user_id);
        }
        if (!$member_id || !sc_survey_is_eligible_member($survey, $member_id)) {
            return false;
        }
    }

    if ((int) $survey->is_public && !$user_id) {
        return true;
    }

    if ($user_id && sc_survey_user_in_audience($survey, $user_id)) {
        return true;
    }

    return (int) $survey->is_public && $user_id;
}

function sc_survey_get_user_response($survey_id, $user_id = 0, $member_id = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_survey_responses';
    $survey_id = absint($survey_id);

    if ($user_id) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1",
            $survey_id,
            absint($user_id)
        ));
        if ($row) {
            return $row;
        }
    }
    if ($member_id) {
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_id = %d AND member_id = %d ORDER BY id DESC LIMIT 1",
            $survey_id,
            absint($member_id)
        ));
    }
    return null;
}

function sc_survey_list_for_user($user_id) {
    global $wpdb;
    if (!sc_is_pro_feature_surveys_enabled()) {
        return [];
    }
    $table = $wpdb->prefix . 'sc_surveys';
    $surveys = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    $member_id = sc_survey_get_member_id_for_user($user_id);
    $out = [];

    foreach ((array) $surveys as $survey) {
        if (!sc_survey_is_available($survey, $user_id, $member_id)) {
            continue;
        }
        if (!sc_survey_user_in_audience($survey, $user_id) && !(int) $survey->is_public) {
            continue;
        }
        $response = sc_survey_get_user_response($survey->id, $user_id, $member_id);
        $out[] = [
            'survey' => $survey,
            'completed' => $response && $response->status === 'completed',
            'completed_at' => $response ? $response->completed_at : null,
        ];
    }
    return $out;
}

function sc_survey_filter_user_list($list, $args = []) {
    $search = isset($args['search']) ? trim((string) $args['search']) : '';
    $status = isset($args['status']) ? sanitize_key($args['status']) : 'all';
    $date_from = isset($args['date_from']) ? sanitize_text_field($args['date_from']) : '';
    $date_to = isset($args['date_to']) ? sanitize_text_field($args['date_to']) : '';
    $filtered = [];

    foreach ((array) $list as $row) {
        $is_done = !empty($row['completed']);
        if ($status === 'pending' && $is_done) {
            continue;
        }
        if ($status === 'completed' && !$is_done) {
            continue;
        }

        if ($search !== '') {
            $haystack = ($row['survey']->title ?? '') . ' ' . wp_strip_all_tags($row['survey']->description ?? '');
            if (function_exists('mb_stripos')) {
                if (mb_stripos($haystack, $search) === false) {
                    continue;
                }
            } elseif (stripos($haystack, $search) === false) {
                continue;
            }
        }

        if ($date_from !== '' || $date_to !== '') {
            if ($is_done && !empty($row['completed_at'])) {
                $ref_date = substr($row['completed_at'], 0, 10);
            } elseif (!empty($row['survey']->start_at)) {
                $ref_date = substr($row['survey']->start_at, 0, 10);
            } else {
                $ref_date = '';
            }
            if ($ref_date === '') {
                continue;
            }
            if ($date_from !== '' && $ref_date < $date_from) {
                continue;
            }
            if ($date_to !== '' && $ref_date > $date_to) {
                continue;
            }
        }

        $filtered[] = $row;
    }

    return $filtered;
}

function sc_survey_get_response($response_id) {
    global $wpdb;
    $responses_table = $wpdb->prefix . 'sc_survey_responses';
    $members_table = $wpdb->prefix . 'sc_members';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, m.first_name, m.last_name, m.player_phone, m.national_id
         FROM $responses_table r
         LEFT JOIN $members_table m ON r.member_id = m.id
         WHERE r.id = %d",
        absint($response_id)
    ));
}

function sc_survey_get_response_answers_map($response_id) {
    global $wpdb;
    $answers_table = $wpdb->prefix . 'sc_survey_answers';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $answers_table WHERE response_id = %d",
        absint($response_id)
    ));
    $map = [];
    foreach ((array) $rows as $row) {
        $map[(int) $row->question_id] = $row;
    }
    return $map;
}

function sc_survey_answer_to_form_value($answer, $question) {
    if (!$answer) {
        return $question->question_type === 'multiple_choice' ? [] : '';
    }
    $type = $question->question_type;
    if ($answer->answer_json) {
        $decoded = json_decode($answer->answer_json, true);
        if ($type === 'file') {
            return !empty($decoded['attachment_id']) ? (int) $decoded['attachment_id'] : '';
        }
        if ($type === 'multiple_choice') {
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($decoded) ? implode('، ', $decoded) : (string) $decoded;
    }
    return $answer->answer_text ?? '';
}

function sc_survey_store_answer_row($response_id, $question, $raw) {
    global $wpdb;
    $answers_table = $wpdb->prefix . 'sc_survey_answers';
    $response_id = absint($response_id);
    $qid = (int) $question->id;
    $now = current_time('mysql');
    $answer_text = null;
    $answer_json = null;

    if ($question->question_type === 'file') {
        $attachment_id = absint($raw);
        if (!$attachment_id) {
            return false;
        }
        $answer_json = wp_json_encode(['attachment_id' => $attachment_id], JSON_UNESCAPED_UNICODE);
    } elseif ($question->question_type === 'multiple_choice') {
        $answer_json = wp_json_encode(array_values((array) $raw), JSON_UNESCAPED_UNICODE);
    } else {
        $answer_text = sc_survey_normalize_answer_value($question, $raw);
    }

    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $answers_table WHERE response_id = %d AND question_id = %d",
        $response_id,
        $qid
    ));

    $row = [
        'answer_text' => $answer_text,
        'answer_json' => $answer_json,
        'created_at' => $now,
    ];

    if ($existing_id) {
        return (bool) $wpdb->update($answers_table, $row, ['id' => $existing_id]);
    }

    $row['response_id'] = $response_id;
    $row['question_id'] = $qid;
    return (bool) $wpdb->insert($answers_table, $row);
}

function sc_survey_admin_update_response($response_id, $answers, $files = []) {
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'دسترسی غیرمجاز.'];
    }

    $response = sc_survey_get_response($response_id);
    if (!$response || $response->status !== 'completed') {
        return ['success' => false, 'message' => 'پاسخ یافت نشد.'];
    }

    $survey_id = (int) $response->survey_id;
    $answers_by_qid = [];
    foreach ((array) $answers as $qid => $val) {
        if ($qid === 'file_keep') {
            continue;
        }
        $answers_by_qid[absint($qid)] = $val;
    }

    $questions = sc_get_survey_questions($survey_id);
    foreach ((array) $questions as $q) {
        $qid = (int) $q->id;
        if ($q->question_type === 'file') {
            $file_key = 'survey_file_' . $qid;
            if (!empty($files[$file_key]['tmp_name'])) {
                $upload = sc_survey_handle_file_upload($files[$file_key]);
                if (empty($upload['success'])) {
                    return $upload;
                }
                sc_survey_store_answer_row($response_id, $q, $upload['attachment_id']);
            } elseif (isset($answers['file_keep'][$qid])) {
                sc_survey_store_answer_row($response_id, $q, absint($answers['file_keep'][$qid]));
            }
            continue;
        }

        if (!array_key_exists($qid, $answers_by_qid)) {
            continue;
        }

        $raw = $answers_by_qid[$qid];
        if ($q->question_type === 'multiple_choice' && !is_array($raw)) {
            $raw = [];
        }
        sc_survey_store_answer_row($response_id, $q, $raw);
    }

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'sc_survey_responses',
        ['completed_at' => current_time('mysql')],
        ['id' => absint($response_id)],
        ['%s'],
        ['%d']
    );

    return ['success' => true, 'message' => 'پاسخ‌ها ذخیره شد.'];
}

function sc_survey_question_settings($question) {
    return sc_survey_decode_json($question->settings_json);
}

function sc_survey_question_is_required($question) {
    $settings = sc_survey_question_settings($question);
    return !empty($settings['required']);
}

function sc_survey_normalize_answer_value($question, $raw) {
    $type = $question->question_type;
    if ($type === 'multiple_choice') {
        return is_array($raw) ? array_map('sanitize_text_field', $raw) : [];
    }
    if ($type === 'number' || $type === 'rating' || $type === 'scale') {
        return is_numeric($raw) ? (string) (float) $raw : '';
    }
    if ($type === 'yes_no') {
        return in_array((string) $raw, ['1', 'yes', 'بله'], true) ? 'yes' : (in_array((string) $raw, ['0', 'no', 'خیر'], true) ? 'no' : '');
    }
    if ($type === 'file') {
        return absint($raw);
    }
    return is_string($raw) ? sanitize_textarea_field($raw) : '';
}

function sc_survey_compare_conditional($dep_str, $operator, $expected) {
    $operator = sanitize_key($operator);
    $dep_str = (string) $dep_str;
    $expected = (string) $expected;

    switch ($operator) {
        case 'not_equals':
            return $dep_str !== $expected;
        case 'contains':
            return strpos($dep_str, $expected) !== false;
        case 'in':
            $parts = array_map('trim', explode(',', $expected));
            return in_array($dep_str, $parts, true);
        case 'gt':
        case 'gte':
        case 'lt':
        case 'lte':
            if (is_numeric($dep_str) && is_numeric($expected)) {
                $dep_num = (float) $dep_str;
                $exp_num = (float) $expected;
                if ($operator === 'gt') {
                    return $dep_num > $exp_num;
                }
                if ($operator === 'gte') {
                    return $dep_num >= $exp_num;
                }
                if ($operator === 'lt') {
                    return $dep_num < $exp_num;
                }
                return $dep_num <= $exp_num;
            }
            $dep_norm = str_replace('-', '/', $dep_str);
            $exp_norm = str_replace('-', '/', $expected);
            if ($operator === 'gt') {
                return $dep_norm > $exp_norm;
            }
            if ($operator === 'gte') {
                return $dep_norm >= $exp_norm;
            }
            if ($operator === 'lt') {
                return $dep_norm < $exp_norm;
            }
            return $dep_norm <= $exp_norm;
        case 'equals':
        default:
            return $dep_str === $expected;
    }
}

function sc_survey_evaluate_conditional($question, $answers_by_qid) {
    $settings = sc_survey_question_settings($question);
    if (empty($settings['conditional']['enabled'])) {
        return true;
    }
    $c = $settings['conditional'];
    $dep_id = isset($c['question_id']) ? absint($c['question_id']) : 0;
    if (!$dep_id || !isset($answers_by_qid[$dep_id])) {
        return false;
    }
    $dep_val = $answers_by_qid[$dep_id];
    $operator = isset($c['operator']) ? $c['operator'] : 'equals';
    $expected = isset($c['value']) ? (string) $c['value'] : '';

    if (is_array($dep_val)) {
        $dep_str = implode(',', $dep_val);
    } else {
        $dep_str = (string) $dep_val;
    }

    return sc_survey_compare_conditional($dep_str, $operator, $expected);
}

function sc_survey_get_visible_questions($survey_id, $answers_by_qid = []) {
    $questions = sc_get_survey_questions($survey_id);
    $visible = [];
    foreach ((array) $questions as $q) {
        if (sc_survey_evaluate_conditional($q, $answers_by_qid)) {
            $visible[] = $q;
        }
    }
    return $visible;
}

function sc_survey_handle_file_upload($file) {
    if (empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'فایلی انتخاب نشده است.'];
    }
    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $allowed = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $upload = wp_handle_upload($file, ['test_form' => false, 'test_type' => false, 'mimes' => $allowed]);
    if (isset($upload['error'])) {
        return ['success' => false, 'message' => $upload['error']];
    }
    $attachment = [
        'post_mime_type' => $upload['type'],
        'post_title' => sanitize_file_name(basename($upload['file'])),
        'post_content' => '',
        'post_status' => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if (!$attach_id) {
        return ['success' => false, 'message' => 'خطا در ذخیره فایل.'];
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    return ['success' => true, 'attachment_id' => $attach_id, 'url' => $upload['url']];
}

function sc_survey_submit_response($survey_id, $answers, $context = []) {
    global $wpdb;
    $survey = sc_get_survey($survey_id);
    if (!$survey) {
        return ['success' => false, 'message' => 'نظرسنجی یافت نشد.'];
    }

    $user_id = isset($context['user_id']) ? absint($context['user_id']) : get_current_user_id();
    $member_id = isset($context['member_id']) ? absint($context['member_id']) : sc_survey_get_member_id_for_user($user_id);
    $guest_data = isset($context['guest_data']) ? $context['guest_data'] : null;

    if (!sc_survey_is_available($survey, $user_id, $member_id)) {
        return ['success' => false, 'message' => 'این نظرسنجی در دسترس نیست.'];
    }

    if ($user_id || $member_id) {
        $existing = sc_survey_get_user_response($survey_id, $user_id, $member_id);
        if ($existing && $existing->status === 'completed') {
            return ['success' => false, 'message' => 'شما قبلاً در این نظرسنجی شرکت کرده‌اید.'];
        }
    }

    $answers_by_qid = [];
    foreach ((array) $answers as $qid => $val) {
        $answers_by_qid[absint($qid)] = $val;
    }

    $visible = sc_survey_get_visible_questions($survey_id, $answers_by_qid);
    foreach ($visible as $q) {
        if (!sc_survey_question_is_required($q)) {
            continue;
        }
        $val = isset($answers_by_qid[$q->id]) ? $answers_by_qid[$q->id] : '';
        if ($q->question_type === 'file') {
            if (empty($val)) {
                return ['success' => false, 'message' => 'لطفاً به سوالات اجباری پاسخ دهید.'];
            }
        } elseif ($val === '' || $val === null || (is_array($val) && empty($val))) {
            return ['success' => false, 'message' => 'لطفاً به سوالات اجباری پاسخ دهید.'];
        }
    }

    $responses_table = $wpdb->prefix . 'sc_survey_responses';
    $answers_table = $wpdb->prefix . 'sc_survey_answers';
    $now = current_time('mysql');

    $wpdb->insert($responses_table, [
        'survey_id' => absint($survey_id),
        'member_id' => $member_id ?: null,
        'user_id' => $user_id ?: null,
        'guest_data' => $guest_data ? wp_json_encode($guest_data, JSON_UNESCAPED_UNICODE) : null,
        'status' => 'completed',
        'completed_at' => $now,
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
        'created_at' => $now,
    ]);
    $response_id = (int) $wpdb->insert_id;

    foreach ($visible as $q) {
        if (!isset($answers_by_qid[$q->id])) {
            continue;
        }
        $raw = $answers_by_qid[$q->id];
        $answer_text = null;
        $answer_json = null;

        if ($q->question_type === 'file') {
            $answer_json = wp_json_encode(['attachment_id' => absint($raw)], JSON_UNESCAPED_UNICODE);
        } elseif ($q->question_type === 'multiple_choice') {
            $answer_json = wp_json_encode(array_values((array) $raw), JSON_UNESCAPED_UNICODE);
        } else {
            $answer_text = sc_survey_normalize_answer_value($q, $raw);
        }

        $wpdb->insert($answers_table, [
            'response_id' => $response_id,
            'question_id' => (int) $q->id,
            'answer_text' => $answer_text,
            'answer_json' => $answer_json,
            'created_at' => $now,
        ]);
    }

    if (function_exists('sc_send_survey_submission_sms')) {
        sc_send_survey_submission_sms($response_id);
    }

    return [
        'success' => true,
        'response_id' => $response_id,
        'thank_you_message' => $survey->thank_you_message ?: 'از مشارکت شما سپاسگزاریم.',
    ];
}

function sc_survey_grant_eligibility($survey_id, $member_id, $source = 'manual') {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_survey_eligibility';
    $survey_id = absint($survey_id);
    $member_id = absint($member_id);
    if (!$survey_id || !$member_id) {
        return false;
    }
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE survey_id = %d AND member_id = %d",
        $survey_id,
        $member_id
    ));
    if ($exists) {
        return true;
    }
    return (bool) $wpdb->insert($table, [
        'survey_id' => $survey_id,
        'member_id' => $member_id,
        'source' => sanitize_key($source),
        'eligible_at' => current_time('mysql'),
    ]);
}

function sc_survey_on_member_last_session($member_id, $course_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_surveys';
    $surveys = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1");
    foreach ((array) $surveys as $survey) {
        $activation = sc_survey_decode_json($survey->activation_config);
        if ((isset($activation['trigger_type']) ? $activation['trigger_type'] : '') !== 'last_session') {
            continue;
        }
        $course_ids = isset($activation['course_ids']) ? array_map('absint', (array) $activation['course_ids']) : [];
        if (!empty($course_ids) && !in_array(absint($course_id), $course_ids, true)) {
            continue;
        }
        sc_survey_grant_eligibility($survey->id, $member_id, 'last_session');
    }
}

add_action('sc_member_course_last_session', 'sc_survey_on_member_last_session', 10, 2);

function sc_survey_process_date_activation() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_surveys';
    $surveys = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 0");
    foreach ((array) $surveys as $survey) {
        $activation = sc_survey_decode_json($survey->activation_config);
        if ((isset($activation['trigger_type']) ? $activation['trigger_type'] : '') !== 'on_date') {
            continue;
        }
        if (!empty($activation['auto_enable']) && sc_survey_activation_date_reached($survey)) {
            $wpdb->update($table, [
                'is_active' => 1,
                'updated_at' => current_time('mysql'),
            ], ['id' => (int) $survey->id]);
        }
    }
}
add_action('admin_init', 'sc_survey_process_date_activation');
add_action('init', 'sc_survey_process_date_activation');

function sc_survey_build_responses_query($survey_id, $filters = []) {
    global $wpdb;
    $responses_table = $wpdb->prefix . 'sc_survey_responses';
    $members_table = $wpdb->prefix . 'sc_members';

    $where = ['r.survey_id = %d', "r.status = 'completed'"];
    $params = [absint($survey_id)];

    if (!empty($filters['filter_date_from'])) {
        $where[] = 'r.completed_at >= %s';
        $params[] = sanitize_text_field($filters['filter_date_from']) . ' 00:00:00';
    }
    if (!empty($filters['filter_date_to'])) {
        $where[] = 'r.completed_at <= %s';
        $params[] = sanitize_text_field($filters['filter_date_to']) . ' 23:59:59';
    }

    $member_filters = sc_survey_apply_member_filters_sql($filters, 'm');
    foreach ($member_filters['parts'] as $part) {
        $where[] = $part;
    }
    $params = array_merge($params, $member_filters['params']);

    $sql = "SELECT r.*, m.first_name, m.last_name, m.player_phone, m.national_id, m.team_player, m.skill_level
            FROM $responses_table r
            LEFT JOIN $members_table m ON r.member_id = m.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.completed_at DESC, r.id DESC";

    if (empty($params)) {
        return $wpdb->get_results($sql);
    }

    return $wpdb->get_results($wpdb->prepare($sql, ...$params));
}

function sc_survey_get_question_stats($survey_id, $filters = []) {
    $questions = sc_get_survey_questions($survey_id);
    $responses = sc_survey_build_responses_query($survey_id, $filters);
    $response_ids = array_map(function ($r) {
        return (int) $r->id;
    }, (array) $responses);

    $empty_stats = [];
    foreach ((array) $questions as $q) {
        $empty_stats[] = [
            'question' => $q,
            'total_answers' => 0,
            'distribution' => [],
            'text_answers' => [],
        ];
    }

    if (empty($response_ids)) {
        return ['total' => 0, 'questions' => $empty_stats];
    }

    global $wpdb;
    $answers_table = $wpdb->prefix . 'sc_survey_answers';
    $placeholders = implode(',', array_fill(0, count($response_ids), '%d'));
    $answers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $answers_table WHERE response_id IN ($placeholders)",
        ...$response_ids
    ));

    $by_response = [];
    foreach ((array) $answers as $a) {
        $by_response[(int) $a->response_id][(int) $a->question_id] = $a;
    }

    $stats = [];
    foreach ((array) $questions as $q) {
        $item = [
            'question' => $q,
            'total_answers' => 0,
            'distribution' => [],
            'text_answers' => [],
        ];
        foreach ($response_ids as $rid) {
            if (!isset($by_response[$rid][$q->id])) {
                continue;
            }
            $a = $by_response[$rid][$q->id];
            $item['total_answers']++;
            if (in_array($q->question_type, ['single_choice', 'yes_no', 'rating', 'multiple_choice'], true)) {
                $vals = [];
                if ($a->answer_json) {
                    $decoded = json_decode($a->answer_json, true);
                    $vals = is_array($decoded) ? $decoded : [$decoded];
                } elseif ($a->answer_text !== null && $a->answer_text !== '') {
                    $vals = [$a->answer_text];
                }
                foreach ($vals as $v) {
                    $key = (string) $v;
                    if (!isset($item['distribution'][$key])) {
                        $item['distribution'][$key] = 0;
                    }
                    $item['distribution'][$key]++;
                }
            } else {
                $text = $a->answer_text;
                if ($q->question_type === 'file' && $a->answer_json) {
                    $decoded = json_decode($a->answer_json, true);
                    if (!empty($decoded['attachment_id'])) {
                        $text = wp_get_attachment_url((int) $decoded['attachment_id']);
                    }
                }
                if ($text !== null && $text !== '') {
                    $item['text_answers'][] = $text;
                }
            }
        }
        $stats[] = $item;
    }

    return ['total' => count($response_ids), 'questions' => $stats];
}

add_action('admin_init', function () {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    if (empty($_GET['survey_id']) || !sc_is_pro_feature_surveys_enabled()) {
        return;
    }

    $survey_id = absint($_GET['survey_id']);

    if (!empty($_GET['sc_export_survey_pdf'])) {
        check_admin_referer('sc_export_survey_' . $survey_id);
        if (function_exists('sc_export_survey_responses_to_pdf')) {
            $response_id = isset($_GET['response_id']) ? absint($_GET['response_id']) : 0;
            sc_export_survey_responses_to_pdf($survey_id, $response_id);
        }
        return;
    }

    if (empty($_GET['sc_export_survey'])) {
        return;
    }
    check_admin_referer('sc_export_survey_' . $survey_id);
    if (function_exists('sc_export_survey_responses_to_excel')) {
        sc_export_survey_responses_to_excel($survey_id);
    }
});

add_action('wp_ajax_sc_submit_survey_wizard', 'sc_ajax_submit_survey_wizard');
add_action('wp_ajax_nopriv_sc_submit_survey_wizard', 'sc_ajax_submit_survey_wizard');
function sc_ajax_submit_survey_wizard() {
    check_ajax_referer('sc_survey_wizard', 'nonce');
    if (!sc_is_pro_feature_surveys_enabled()) {
        wp_send_json_error(['message' => 'نظرسنجی غیرفعال است.']);
    }
    $survey_id = isset($_POST['survey_id']) ? absint($_POST['survey_id']) : 0;
    $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? $_POST['answers'] : [];
    $user_id = get_current_user_id();
    $member_id = sc_survey_get_member_id_for_user($user_id);

    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'survey_file_') !== 0 || empty($file['tmp_name'])) {
            continue;
        }
        $qid = absint(str_replace('survey_file_', '', $key));
        $upload = sc_survey_handle_file_upload($file);
        if (!$upload['success']) {
            wp_send_json_error(['message' => $upload['message']]);
        }
        $answers[$qid] = $upload['attachment_id'];
    }

    $guest_data = null;
    if (!$user_id && !empty($_POST['guest_name'])) {
        $guest_data = [
            'name' => sanitize_text_field(wp_unslash($_POST['guest_name'])),
            'phone' => isset($_POST['guest_phone']) ? sanitize_text_field(wp_unslash($_POST['guest_phone'])) : '',
        ];
    }

    $result = sc_survey_submit_response($survey_id, $answers, [
        'user_id' => $user_id,
        'member_id' => $member_id,
        'guest_data' => $guest_data,
    ]);
    if (empty($result['success'])) {
        wp_send_json_error(['message' => $result['message'] ?? 'خطا']);
    }
    wp_send_json_success([
        'thank_you_message' => $result['thank_you_message'],
        'response_id' => $result['response_id'],
    ]);
}

add_action('template_redirect', 'sc_render_survey_fill_pages', 1);
function sc_render_survey_fill_pages() {
    if (is_admin()) {
        return;
    }
    if (!sc_is_pro_feature_surveys_enabled()) {
        return;
    }

    $public_token = isset($_GET['sc_public_survey']) ? sanitize_text_field(wp_unslash($_GET['sc_public_survey'])) : '';
    $fill_id = isset($_GET['sc_fill_survey']) ? absint($_GET['sc_fill_survey']) : 0;

    if ($public_token === '' && !$fill_id) {
        return;
    }

    // Logged-in users fill surveys inside WooCommerce account panel.
    if ($fill_id && is_user_logged_in()) {
        if (sc_survey_is_account_surveys_context()) {
            return;
        }
        if (function_exists('wc_get_account_endpoint_url')) {
            wp_safe_redirect(sc_survey_account_fill_url($fill_id));
            exit;
        }
    }

    sc_check_and_create_tables();
    $survey = null;
    if ($public_token !== '') {
        $survey = sc_survey_get_by_public_token($public_token);
        if (!$survey || !sc_survey_is_available($survey, get_current_user_id())) {
            wp_die('این نظرسنجی در دسترس نیست.');
        }
    } else {
        $survey = sc_get_survey($fill_id);
        $user_id = get_current_user_id();
        if (!$survey || !$user_id) {
            wp_die('برای شرکت در نظرسنجی باید وارد شوید.');
        }
        $member_id = sc_survey_get_member_id_for_user($user_id);
        if (!sc_survey_is_available($survey, $user_id, $member_id) || !sc_survey_user_in_audience($survey, $user_id)) {
            wp_die('این نظرسنجی برای شما فعال نیست.');
        }
    }

    $questions = sc_get_survey_questions($survey->id);
    status_header(200);
    nocache_headers();
    include SC_TEMPLATES_PUBLIC_DIR . 'survey-wizard.php';
    exit;
}
