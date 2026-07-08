<?php
/**
 * REST API عمومی باشگاه (دوره‌ها، رویدادها، مربیان، بازیکنان، شعبه‌ها، برنامه هفتگی)
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/formatters.php';

/**
 * @param string $course_type group|private
 * @return string
 */
function sc_public_api_course_type_sql($course_type) {
    return $course_type === 'private' ? 'private' : 'group';
}

/**
 * @param WP_REST_Request $request
 * @param string          $course_type
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_courses_list_handler(WP_REST_Request $request, $course_type) {
    global $wpdb;

    $courses_table = $wpdb->prefix . 'sc_courses';
    $pagination = sc_public_api_pagination_args(
        $request->get_param('page'),
        $request->get_param('per_page')
    );

    $where = [
        'deleted_at IS NULL',
        'is_active = 1',
    ];
    $values = [];

    if ($course_type === 'private') {
        $where[] = "course_type = 'private'";
    } else {
        $where[] = "(course_type IS NULL OR course_type = '' OR course_type = 'group')";
    }

    $chapter = sanitize_text_field((string) $request->get_param('chapter'));
    if ($chapter !== '') {
        $chapters_table = $wpdb->prefix . 'sc_course_chapters';
        $where[] = "(c.chapter = %s OR EXISTS (
            SELECT 1 FROM $chapters_table ch
            WHERE ch.course_id = c.id AND ch.chapter_name = %s
        ))";
        $values[] = $chapter;
        $values[] = $chapter;
    }

    $coach_id = absint($request->get_param('coach_id'));
    if ($coach_id > 0) {
        $cc = $wpdb->prefix . 'sc_course_coaches';
        $where[] = "EXISTS (SELECT 1 FROM $cc cc WHERE cc.course_id = c.id AND cc.coach_id = %d)";
        $values[] = $coach_id;
    }

    $search = sanitize_text_field((string) $request->get_param('search'));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(c.title LIKE %s OR c.description LIKE %s)';
        $values[] = $like;
        $values[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $courses_table c WHERE $where_sql";
    $total = !empty($values)
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
        : (int) $wpdb->get_var($count_sql);

    $select_values = $values;
    $select_values[] = $pagination['per_page'];
    $select_values[] = $pagination['offset'];

    $sql = "SELECT c.* FROM $courses_table c
            WHERE $where_sql
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $select_values));

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = sc_public_api_format_course($row, $course_type, false);
    }

    return sc_public_api_list_response($items, $total, $pagination['page'], $pagination['per_page']);
}

/**
 * @param WP_REST_Request $request
 * @param string          $course_type
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_course_single_handler(WP_REST_Request $request, $course_type) {
    global $wpdb;

    $course_id = absint($request->get_param('id'));
    if (!$course_id) {
        return new WP_Error('sc_public_api_bad_request', 'شناسه دوره نامعتبر است.', ['status' => 400]);
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1 LIMIT 1",
        $course_id
    ));

    if (!$row) {
        return new WP_Error('sc_public_api_not_found', 'دوره یافت نشد.', ['status' => 404]);
    }

    $actual_type = isset($row->course_type) && (string) $row->course_type === 'private' ? 'private' : 'group';
    if ($actual_type !== $course_type) {
        return new WP_Error('sc_public_api_not_found', 'دوره در این دسته یافت نشد.', ['status' => 404]);
    }

    return sc_public_api_item_response(sc_public_api_format_course($row, $course_type, true));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_branches_list_handler(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_chapter_categories';
    $pagination = sc_public_api_pagination_args(
        $request->get_param('page'),
        $request->get_param('per_page')
    );

    $where = ['1=1'];
    $values = [];

    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'is_active'));
    if (!empty($col)) {
        $where[] = 'is_active = 1';
    }

    $search = sanitize_text_field((string) $request->get_param('search'));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(name LIKE %s OR description LIKE %s OR address LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $order = !empty($col) ? 'sort_order ASC, name ASC' : 'name ASC';

    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $total = !empty($values)
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
        : (int) $wpdb->get_var($count_sql);

    $select_values = $values;
    $select_values[] = $pagination['per_page'];
    $select_values[] = $pagination['offset'];

    $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $order LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $select_values));

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = sc_public_api_format_branch($row);
    }

    return sc_public_api_list_response($items, $total, $pagination['page'], $pagination['per_page']);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_branch_single_handler(WP_REST_Request $request) {
    global $wpdb;

    $id = absint($request->get_param('id'));
    if (!$id) {
        return new WP_Error('sc_public_api_bad_request', 'شناسه شعبه نامعتبر است.', ['status' => 400]);
    }

    $table = $wpdb->prefix . 'sc_chapter_categories';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $id));
    if (!$row) {
        return new WP_Error('sc_public_api_not_found', 'شعبه یافت نشد.', ['status' => 404]);
    }

    if (isset($row->is_active) && (int) $row->is_active !== 1) {
        return new WP_Error('sc_public_api_not_found', 'شعبه یافت نشد.', ['status' => 404]);
    }

    return sc_public_api_item_response(sc_public_api_format_branch($row));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_events_list_handler(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_events';
    $pagination = sc_public_api_pagination_args(
        $request->get_param('page'),
        $request->get_param('per_page')
    );

    $where = ['deleted_at IS NULL', 'is_active = 1'];
    $values = [];

    $chapter = sanitize_text_field((string) $request->get_param('chapter'));
    if ($chapter !== '') {
        $where[] = 'chapter = %s';
        $values[] = $chapter;
    }

    $event_type = sanitize_key((string) $request->get_param('event_type'));
    if (in_array($event_type, ['event', 'competition'], true)) {
        $where[] = 'event_type = %s';
        $values[] = $event_type;
    }

    $status = sanitize_key((string) $request->get_param('status'));
    $today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
    if ($status === 'upcoming' && $today_shamsi !== '' && function_exists('sc_compare_shamsi_dates')) {
        $where[] = "(holding_date_shamsi IS NOT NULL AND holding_date_shamsi != '' AND holding_date_shamsi >= %s)";
        $values[] = $today_shamsi;
    } elseif ($status === 'past' && $today_shamsi !== '') {
        $where[] = "(holding_date_shamsi IS NOT NULL AND holding_date_shamsi != '' AND holding_date_shamsi < %s)";
        $values[] = $today_shamsi;
    }

    $search = sanitize_text_field((string) $request->get_param('search'));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(name LIKE %s OR description LIKE %s OR event_location LIKE %s OR event_location_address LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $total = !empty($values)
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
        : (int) $wpdb->get_var($count_sql);

    $select_values = $values;
    $select_values[] = $pagination['per_page'];
    $select_values[] = $pagination['offset'];

    $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY holding_date_gregorian DESC, id DESC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $select_values));

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = sc_public_api_format_event($row, false);
    }

    return sc_public_api_list_response($items, $total, $pagination['page'], $pagination['per_page']);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_event_single_handler(WP_REST_Request $request) {
    global $wpdb;

    $id = absint($request->get_param('id'));
    if (!$id) {
        return new WP_Error('sc_public_api_bad_request', 'شناسه رویداد نامعتبر است.', ['status' => 400]);
    }

    $table = $wpdb->prefix . 'sc_events';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND deleted_at IS NULL AND is_active = 1 LIMIT 1",
        $id
    ));
    if (!$row) {
        return new WP_Error('sc_public_api_not_found', 'رویداد یافت نشد.', ['status' => 404]);
    }

    return sc_public_api_item_response(sc_public_api_format_event($row, true));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_coaches_list_handler(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_coaches';
    $pagination = sc_public_api_pagination_args(
        $request->get_param('page'),
        $request->get_param('per_page')
    );

    $where = ['is_active = 1'];
    $values = [];

    $chapter = sanitize_text_field((string) $request->get_param('chapter'));
    if ($chapter !== '') {
        $cc = $wpdb->prefix . 'sc_course_coaches';
        $where[] = "EXISTS (SELECT 1 FROM $cc cc WHERE cc.coach_id = c.id AND cc.chapter_name = %s)";
        $values[] = $chapter;
    }

    $search = sanitize_text_field((string) $request->get_param('search'));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.specialization LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $table c WHERE $where_sql";
    $total = !empty($values)
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
        : (int) $wpdb->get_var($count_sql);

    $select_values = $values;
    $select_values[] = $pagination['per_page'];
    $select_values[] = $pagination['offset'];

    $sql = "SELECT c.* FROM $table c WHERE $where_sql ORDER BY c.last_name ASC, c.first_name ASC LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $select_values));

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = sc_public_api_format_coach($row, false);
    }

    return sc_public_api_list_response($items, $total, $pagination['page'], $pagination['per_page']);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_coach_single_handler(WP_REST_Request $request) {
    global $wpdb;

    $id = absint($request->get_param('id'));
    if (!$id) {
        return new WP_Error('sc_public_api_bad_request', 'شناسه مربی نامعتبر است.', ['status' => 400]);
    }

    $table = $wpdb->prefix . 'sc_coaches';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND is_active = 1 LIMIT 1",
        $id
    ));
    if (!$row) {
        return new WP_Error('sc_public_api_not_found', 'مربی یافت نشد.', ['status' => 404]);
    }

    return sc_public_api_item_response(sc_public_api_format_coach($row, true));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_players_list_handler(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_members';
    $honors_table = $wpdb->prefix . 'sc_honors';
    $pagination = sc_public_api_pagination_args(
        $request->get_param('page'),
        $request->get_param('per_page')
    );

    $where = ['m.is_active = 1', 'm.identity_verified = 1'];
    $values = [];

    $has_honors = strtolower(sanitize_key((string) $request->get_param('has_honors')));
    if ($has_honors === 'yes') {
        $where[] = "EXISTS (SELECT 1 FROM $honors_table h WHERE h.member_id = m.id AND h.status = 'approved')";
    } elseif ($has_honors === 'no') {
        $where[] = "NOT EXISTS (SELECT 1 FROM $honors_table h WHERE h.member_id = m.id AND h.status = 'approved')";
    }

    $member_type = sanitize_key((string) $request->get_param('member_type'));
    if (in_array($member_type, ['normal', 'team'], true)) {
        $where[] = 'm.member_type = %s';
        $values[] = $member_type;
    }

    $team = sanitize_text_field((string) $request->get_param('team'));
    if ($team !== '') {
        $where[] = 'm.team_player = %s';
        $values[] = $team;
    }

    $skill_level = sanitize_text_field((string) $request->get_param('skill_level'));
    if ($skill_level !== '') {
        $where[] = 'm.skill_level = %s';
        $values[] = $skill_level;
    }

    $gender = sanitize_key((string) $request->get_param('gender'));
    if ($gender !== '') {
        $where[] = 'm.gender = %s';
        $values[] = $gender;
    }

    $search = sanitize_text_field((string) $request->get_param('search'));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(m.first_name LIKE %s OR m.last_name LIKE %s OR m.team_player LIKE %s OR m.skill_level LIKE %s OR m.sports_history LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $honors_case = "(CASE WHEN EXISTS (
        SELECT 1 FROM $honors_table h
        WHERE h.member_id = m.id AND h.status = 'approved'
    ) THEN 'yes' ELSE 'no' END) AS has_honors";

    $count_sql = "SELECT COUNT(*) FROM $table m WHERE $where_sql";
    $total = !empty($values)
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values))
        : (int) $wpdb->get_var($count_sql);

    $select_values = $values;
    $select_values[] = $pagination['per_page'];
    $select_values[] = $pagination['offset'];

    $sql = "SELECT m.*, $honors_case FROM $table m
            WHERE $where_sql
            ORDER BY m.last_name ASC, m.first_name ASC
            LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $select_values));

    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = sc_public_api_format_player($row, false);
    }

    return sc_public_api_list_response($items, $total, $pagination['page'], $pagination['per_page']);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_player_single_handler(WP_REST_Request $request) {
    global $wpdb;

    $id = absint($request->get_param('id'));
    if (!$id) {
        return new WP_Error('sc_public_api_bad_request', 'شناسه بازیکن نامعتبر است.', ['status' => 400]);
    }

    $table = $wpdb->prefix . 'sc_members';
    $honors_table = $wpdb->prefix . 'sc_honors';
    $honors_case = "(CASE WHEN EXISTS (
        SELECT 1 FROM $honors_table h
        WHERE h.member_id = m.id AND h.status = 'approved'
    ) THEN 'yes' ELSE 'no' END) AS has_honors";

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT m.*, $honors_case FROM $table m
         WHERE m.id = %d AND m.is_active = 1 AND m.identity_verified = 1
         LIMIT 1",
        $id
    ));
    if (!$row) {
        return new WP_Error('sc_public_api_not_found', 'بازیکن یافت نشد.', ['status' => 404]);
    }

    return sc_public_api_item_response(sc_public_api_format_player($row, true));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_honors_list_handler(WP_REST_Request $request) {
    if (!function_exists('sc_honors_api_query_rows')) {
        return new WP_Error('sc_public_api_unavailable', 'ماژول افتخارات در دسترس نیست.', ['status' => 503]);
    }

    $owner_type = sanitize_key((string) $request->get_param('owner_type'));
    if ($owner_type === '') {
        $owner_type = 'all';
    }
    if (!in_array($owner_type, ['all', 'member', 'coach'], true)) {
        return new WP_Error('sc_public_api_bad_request', 'owner_type باید all، member یا coach باشد.', ['status' => 400]);
    }

    $result = sc_honors_api_query_rows([
        'owner_type' => $owner_type,
        'status' => 'approved',
        'member_id' => absint($request->get_param('member_id')),
        'coach_id' => absint($request->get_param('coach_id')),
        'category_id' => absint($request->get_param('category_id')),
        'page' => absint($request->get_param('page')) ?: 1,
        'per_page' => absint($request->get_param('per_page')) ?: 20,
    ]);

    $items = [];
    foreach ($result['items'] as $row) {
        $items[] = sc_public_api_format_honor($row);
    }

    return sc_public_api_list_response(
        $items,
        $result['total'],
        $result['page'],
        $result['per_page']
    );
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_honor_single_handler(WP_REST_Request $request) {
    if (!function_exists('sc_honors_api_query_rows')) {
        return new WP_Error('sc_public_api_unavailable', 'ماژول افتخارات در دسترس نیست.', ['status' => 503]);
    }

    $honor_id = absint($request->get_param('id'));
    if (!$honor_id) {
        return new WP_Error('sc_public_api_bad_request', 'شناسه افتخار نامعتبر است.', ['status' => 400]);
    }

    $result = sc_honors_api_query_rows([
        'honor_id' => $honor_id,
        'owner_type' => 'all',
        'status' => 'approved',
        'page' => 1,
        'per_page' => 1,
    ]);

    if (empty($result['items'])) {
        return new WP_Error('sc_public_api_not_found', 'افتخار یافت نشد.', ['status' => 404]);
    }

    return sc_public_api_item_response(sc_public_api_format_honor($result['items'][0]));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_honor_categories_handler(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_honor_categories';
    $rows = $wpdb->get_results("SELECT id, name FROM $table ORDER BY name ASC");
    $items = [];
    foreach ((array) $rows as $row) {
        $items[] = [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
        ];
    }

    return sc_public_api_list_response($items, count($items), 1, max(1, count($items)));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function sc_public_api_lookups_handler(WP_REST_Request $request) {
    global $wpdb;

    $teams = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sc_team_categories ORDER BY name ASC");
    $levels = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sc_level_categories ORDER BY name ASC");

    $team_items = [];
    foreach ((array) $teams as $row) {
        $team_items[] = ['id' => (int) $row->id, 'name' => (string) $row->name];
    }

    $level_items = [];
    foreach ((array) $levels as $row) {
        $level_items[] = ['id' => (int) $row->id, 'name' => (string) $row->name];
    }

    $type_labels = sc_public_api_member_type_labels();
    $member_types = [];
    foreach ($type_labels as $key => $label) {
        $member_types[] = ['value' => $key, 'label' => $label];
    }

    return rest_ensure_response([
        'success' => true,
        'teams' => $team_items,
        'skill_levels' => $level_items,
        'member_types' => $member_types,
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_weekly_schedule_handler(WP_REST_Request $request) {
    if (!function_exists('sc_get_admin_weekly_schedule_report')) {
        return new WP_Error('sc_public_api_unavailable', 'ماژول برنامه هفتگی در دسترس نیست.', ['status' => 503]);
    }

    $filters = [
        'filter_course' => absint($request->get_param('course_id')),
        'filter_chapter' => sanitize_text_field((string) $request->get_param('chapter')),
        'filter_coach' => absint($request->get_param('coach_id')),
        'filter_group' => '',
    ];

    $course_type = sanitize_key((string) $request->get_param('course_type'));
    if (!in_array($course_type, ['all', 'group', 'private'], true)) {
        $course_type = 'all';
    }
    $report = sc_public_api_weekly_schedule_report($filters, $course_type);

    return rest_ensure_response([
        'success' => true,
        'days' => $report['days'],
        'sections' => $report['sections'],
        'slots' => $report['slots'],
    ]);
}

/**
 * @param array<string,mixed> $filters
 * @param string              $course_type all|group|private
 * @return array{days:array<int,string>,sections:array<int,array<string,mixed>>,slots:array<int,array<string,mixed>>}
 */
function sc_public_api_weekly_schedule_report(array $filters, $course_type = 'all') {
    global $wpdb;

    $labels = function_exists('sc_course_weekday_labels_ir')
        ? sc_course_weekday_labels_ir()
        : [];

    if (!function_exists('sc_course_weekly_schedule_table_ready') || !sc_course_weekly_schedule_table_ready()) {
        return ['days' => $labels, 'sections' => [], 'slots' => []];
    }

    $filter_course = isset($filters['filter_course']) ? absint($filters['filter_course']) : 0;
    $filter_chapter = isset($filters['filter_chapter']) ? sanitize_text_field((string) $filters['filter_chapter']) : '';
    $filter_coach = isset($filters['filter_coach']) ? absint($filters['filter_coach']) : 0;

    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $where = ['c.deleted_at IS NULL', 'c.is_active = 1'];
    $args = [];

    if ($course_type === 'private') {
        $where[] = "c.course_type = 'private'";
    } elseif ($course_type === 'group') {
        $where[] = "(c.course_type IS NULL OR c.course_type = '' OR c.course_type = 'group')";
    }

    if ($filter_course > 0) {
        $where[] = 's.course_id = %d';
        $args[] = $filter_course;
    }

    if ($filter_chapter !== '' && function_exists('sc_course_schedule_has_chapter_coach_columns') && sc_course_schedule_has_chapter_coach_columns()) {
        $where[] = "(s.chapter_name IS NULL OR s.chapter_name = '' OR s.chapter_name = %s)";
        $args[] = $filter_chapter;
    }

    if ($filter_coach > 0 && function_exists('sc_course_schedule_has_chapter_coach_columns') && sc_course_schedule_has_chapter_coach_columns()) {
        $where[] = '(s.coach_id IS NULL OR s.coach_id = 0 OR s.coach_id = %d)';
        $args[] = $filter_coach;
    }

    $extra_select = ', c.title AS course_title, c.course_type';
    if (function_exists('sc_course_schedule_has_chapter_coach_columns') && sc_course_schedule_has_chapter_coach_columns()) {
        $extra_select .= ', s.chapter_name, s.coach_id';
    }
    if (function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()) {
        $extra_select .= ', s.schedule_uses_group, s.group_name';
    }

    $sql = "SELECT s.id, s.course_id, s.weekday, s.time_start, s.time_end{$extra_select}
            FROM {$sch} s
            INNER JOIN {$courses_table} c ON c.id = s.course_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY c.title ASC, s.weekday ASC, s.time_start ASC, s.id ASC';

    $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
    if (empty($rows)) {
        return ['days' => $labels, 'sections' => [], 'slots' => []];
    }

    $coach_ids = [];
    foreach ($rows as $row) {
        if (!empty($row->coach_id)) {
            $coach_ids[(int) $row->coach_id] = true;
        }
    }

    $coach_names = [];
    if (!empty($coach_ids)) {
        $ids = array_keys($coach_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $coach_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name FROM {$coaches_table} WHERE id IN ($placeholders) AND is_active = 1",
            ...$ids
        ));
        foreach ($coach_rows as $coach_row) {
            $coach_names[(int) $coach_row->id] = trim((string) $coach_row->first_name . ' ' . (string) $coach_row->last_name);
        }
    }

    $sections = [];
    $slots = [];
    $type_labels = sc_public_api_course_type_labels();

    foreach ($rows as $row) {
        $course_id = (int) $row->course_id;
        if (!isset($sections[$course_id])) {
            $cells = [];
            foreach (range(1, 7) as $day_num) {
                $cells[$day_num] = [];
            }
            $ct = isset($row->course_type) && (string) $row->course_type === 'private' ? 'private' : 'group';
            $sections[$course_id] = [
                'course_id' => $course_id,
                'course_title' => (string) $row->course_title,
                'course_type' => $ct,
                'course_type_label' => isset($type_labels[$ct]) ? $type_labels[$ct] : $ct,
                'cells' => $cells,
            ];
        }

        $weekday = (int) $row->weekday;
        if ($weekday < 1 || $weekday > 7) {
            continue;
        }

        $chapter = (isset($row->chapter_name) && trim((string) $row->chapter_name) !== '')
            ? trim((string) $row->chapter_name) : '';
        $coach_id = isset($row->coach_id) ? (int) $row->coach_id : 0;
        $coach_name = ($coach_id > 0 && isset($coach_names[$coach_id])) ? $coach_names[$coach_id] : '';
        $group_name = (
            function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()
            && !empty($row->schedule_uses_group)
            && !empty($row->group_name)
        ) ? (string) $row->group_name : '';

        $ct = isset($row->course_type) && (string) $row->course_type === 'private' ? 'private' : 'group';
        $slot = [
            'schedule_id' => (int) $row->id,
            'course_id' => $course_id,
            'course_title' => (string) $row->course_title,
            'course_type' => $ct,
            'course_type_label' => isset($type_labels[$ct]) ? $type_labels[$ct] : $ct,
            'weekday' => $weekday,
            'weekday_label' => isset($labels[$weekday]) ? $labels[$weekday] : (string) $weekday,
            'time_start' => substr((string) $row->time_start, 0, 5),
            'time_end' => substr((string) $row->time_end, 0, 5),
            'chapter' => $chapter,
            'coach_id' => $coach_id,
            'coach_name' => $coach_name,
            'group_name' => $group_name,
        ];

        $sections[$course_id]['cells'][$weekday][] = $slot;
        $slots[] = $slot;
    }

    return [
        'days' => $labels,
        'sections' => array_values($sections),
        'slots' => $slots,
    ];
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_public_api_calendar_handler(WP_REST_Request $request) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_events';
    $from_shamsi = sanitize_text_field((string) $request->get_param('from_shamsi'));
    $to_shamsi = sanitize_text_field((string) $request->get_param('to_shamsi'));

    if ($from_shamsi === '' || $to_shamsi === '') {
        return new WP_Error(
            'sc_public_api_bad_request',
            'پارامترهای from_shamsi و to_shamsi الزامی هستند (مثال: 1404/07/01).',
            ['status' => 400]
        );
    }

    $from_norm = str_replace('/', '-', $from_shamsi);
    $to_norm = str_replace('/', '-', $to_shamsi);

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table
         WHERE deleted_at IS NULL AND is_active = 1
           AND holding_date_shamsi IS NOT NULL AND holding_date_shamsi != ''
           AND REPLACE(holding_date_shamsi, '/', '-') >= %s
           AND REPLACE(holding_date_shamsi, '/', '-') <= %s
         ORDER BY holding_date_gregorian ASC, id ASC",
        $from_norm,
        $to_norm
    ));

    $items = [];
    foreach ((array) $rows as $row) {
        $formatted = sc_public_api_format_event($row, false);
        $items[] = [
            'id' => $formatted['id'],
            'name' => $formatted['name'],
            'event_type' => $formatted['event_type'],
            'event_type_label' => $formatted['event_type_label'],
            'chapter' => $formatted['chapter'],
            'holding_shamsi' => $formatted['dates']['holding_shamsi'],
            'holding_gregorian' => $formatted['dates']['holding_gregorian'],
            'event_time' => $formatted['event_time'],
            'location' => $formatted['location'],
            'registration_url' => $formatted['registration_url'],
            'is_upcoming' => $formatted['is_upcoming'],
            'is_past' => $formatted['is_past'],
        ];
    }

    return rest_ensure_response([
        'success' => true,
        'from_shamsi' => $from_shamsi,
        'to_shamsi' => $to_shamsi,
        'total' => count($items),
        'items' => $items,
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function sc_public_api_meta_handler(WP_REST_Request $request) {
    $base = rest_url('sportclub/v1/public/');

    return rest_ensure_response([
        'success' => true,
        'api_version' => 'v1',
        'namespace' => 'sportclub/v1/public',
        'system_urls' => [
            'base' => sc_public_api_system_base_url(),
            'enrollment' => sc_public_api_enrollment_url(),
            'events' => sc_public_api_events_url(),
            'private_classes' => sc_public_api_private_classes_url(),
        ],
        'endpoints' => [
            'branches' => $base . 'branches',
            'courses_group' => $base . 'courses/group',
            'courses_private' => $base . 'courses/private',
            'events' => $base . 'events',
            'coaches' => $base . 'coaches',
            'players' => $base . 'players',
            'honors' => $base . 'honors',
            'honor_categories' => $base . 'honor-categories',
            'lookups' => $base . 'lookups',
            'schedules_weekly' => $base . 'schedules/weekly',
            'calendar' => $base . 'calendar',
        ],
    ]);
}

add_action('rest_api_init', 'sc_register_public_api_routes');
function sc_register_public_api_routes() {
    $permission = 'sc_public_api_permission_check';
    $ns = 'sportclub/v1/public';

    $common_pagination = [
        'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
        'per_page' => ['type' => 'integer', 'required' => false, 'default' => 20],
        'api_key' => ['type' => 'string', 'required' => false],
    ];

    register_rest_route($ns, '/meta', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_meta_handler',
        'permission_callback' => $permission,
    ]);

    register_rest_route($ns, '/branches', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_branches_list_handler',
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'search' => ['type' => 'string', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/branches/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_branch_single_handler',
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/courses/group', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            return sc_public_api_courses_list_handler($request, 'group');
        },
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'chapter' => ['type' => 'string', 'required' => false],
            'coach_id' => ['type' => 'integer', 'required' => false],
            'search' => ['type' => 'string', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/courses/group/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            return sc_public_api_course_single_handler($request, 'group');
        },
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/courses/private', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            return sc_public_api_courses_list_handler($request, 'private');
        },
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'chapter' => ['type' => 'string', 'required' => false],
            'coach_id' => ['type' => 'integer', 'required' => false],
            'search' => ['type' => 'string', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/courses/private/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            return sc_public_api_course_single_handler($request, 'private');
        },
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/events', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_events_list_handler',
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'chapter' => ['type' => 'string', 'required' => false],
            'event_type' => ['type' => 'string', 'required' => false, 'enum' => ['event', 'competition']],
            'status' => ['type' => 'string', 'required' => false, 'enum' => ['upcoming', 'past', 'all']],
            'search' => ['type' => 'string', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/events/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_event_single_handler',
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/coaches', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_coaches_list_handler',
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'chapter' => ['type' => 'string', 'required' => false],
            'search' => ['type' => 'string', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/coaches/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_coach_single_handler',
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/players', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_players_list_handler',
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'has_honors' => ['type' => 'string', 'required' => false, 'enum' => ['yes', 'no', 'all']],
            'member_type' => ['type' => 'string', 'required' => false, 'enum' => ['normal', 'team']],
            'team' => ['type' => 'string', 'required' => false],
            'skill_level' => ['type' => 'string', 'required' => false],
            'gender' => ['type' => 'string', 'required' => false],
            'search' => ['type' => 'string', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/players/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_player_single_handler',
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/honors', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_honors_list_handler',
        'permission_callback' => $permission,
        'args' => array_merge($common_pagination, [
            'owner_type' => ['type' => 'string', 'required' => false, 'enum' => ['all', 'member', 'coach']],
            'member_id' => ['type' => 'integer', 'required' => false],
            'coach_id' => ['type' => 'integer', 'required' => false],
            'category_id' => ['type' => 'integer', 'required' => false],
        ]),
    ]);

    register_rest_route($ns, '/honors/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_honor_single_handler',
        'permission_callback' => $permission,
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/honor-categories', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_honor_categories_handler',
        'permission_callback' => $permission,
        'args' => ['api_key' => ['type' => 'string', 'required' => false]],
    ]);

    register_rest_route($ns, '/lookups', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_lookups_handler',
        'permission_callback' => $permission,
        'args' => ['api_key' => ['type' => 'string', 'required' => false]],
    ]);

    register_rest_route($ns, '/schedules/weekly', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_weekly_schedule_handler',
        'permission_callback' => $permission,
        'args' => [
            'course_id' => ['type' => 'integer', 'required' => false],
            'chapter' => ['type' => 'string', 'required' => false],
            'coach_id' => ['type' => 'integer', 'required' => false],
            'course_type' => ['type' => 'string', 'required' => false, 'enum' => ['all', 'group', 'private']],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route($ns, '/calendar', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_public_api_calendar_handler',
        'permission_callback' => $permission,
        'args' => [
            'from_shamsi' => ['type' => 'string', 'required' => true],
            'to_shamsi' => ['type' => 'string', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);
}
