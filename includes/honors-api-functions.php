<?php
/**
 * REST API افتخارات بازیکنان و مربیان (برای فراخوانی از سایت‌های خارجی)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function sc_get_honors_api_key() {
    return trim((string) sc_get_setting('honors_api_key', ''));
}

/**
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function sc_honors_api_permission_check(WP_REST_Request $request) {
    $configured = sc_get_honors_api_key();
    if ($configured === '') {
        return new WP_Error(
            'sc_honors_api_disabled',
            'API افتخارات فعال نیست. کلید API را در تنظیمات افزونه تعریف کنید.',
            ['status' => 503]
        );
    }

    $provided = '';
    $header_key = $request->get_header('x-api-key');
    if (is_string($header_key) && $header_key !== '') {
        $provided = trim($header_key);
    }
    if ($provided === '') {
        $auth = $request->get_header('authorization');
        if (is_string($auth) && stripos($auth, 'Bearer ') === 0) {
            $provided = trim(substr($auth, 7));
        }
    }
    if ($provided === '') {
        $query_key = $request->get_param('api_key');
        if (is_string($query_key) && $query_key !== '') {
            $provided = trim($query_key);
        }
    }

    if ($provided === '' || !hash_equals($configured, $provided)) {
        return new WP_Error(
            'sc_honors_api_unauthorized',
            'کلید API نامعتبر است.',
            ['status' => 401]
        );
    }

    return true;
}

/**
 * @return array<string,string>
 */
function sc_honors_api_status_labels() {
    return [
        'pending' => 'در انتظار بررسی',
        'approved' => 'تایید شده',
        'rejected' => 'عدم تایید',
    ];
}

/**
 * @param object $row
 * @return array<string,mixed>
 */
function sc_honors_api_format_item($row) {
    $labels = sc_honors_api_status_labels();
    $status = isset($row->status) ? (string) $row->status : 'pending';

    $member_id = !empty($row->member_id) ? (int) $row->member_id : 0;
    $coach_id = !empty($row->coach_id) ? (int) $row->coach_id : 0;

    $owner_type = 'unknown';
    $owner = null;
    $related_coach = null;

    if ($member_id > 0) {
        $owner_type = 'member';
        $owner = [
            'id' => $member_id,
            'first_name' => isset($row->member_first_name) ? (string) $row->member_first_name : '',
            'last_name' => isset($row->member_last_name) ? (string) $row->member_last_name : '',
            'full_name' => trim((string) ($row->member_first_name ?? '') . ' ' . (string) ($row->member_last_name ?? '')),
            'national_id' => isset($row->member_national_id) ? (string) $row->member_national_id : '',
        ];
        if ($coach_id > 0) {
            $related_coach = [
                'id' => $coach_id,
                'first_name' => isset($row->coach_first_name) ? (string) $row->coach_first_name : '',
                'last_name' => isset($row->coach_last_name) ? (string) $row->coach_last_name : '',
                'full_name' => trim((string) ($row->coach_first_name ?? '') . ' ' . (string) ($row->coach_last_name ?? '')),
            ];
        }
    } elseif ($coach_id > 0) {
        $owner_type = 'coach';
        $owner = [
            'id' => $coach_id,
            'first_name' => isset($row->coach_first_name) ? (string) $row->coach_first_name : '',
            'last_name' => isset($row->coach_last_name) ? (string) $row->coach_last_name : '',
            'full_name' => trim((string) ($row->coach_first_name ?? '') . ' ' . (string) ($row->coach_last_name ?? '')),
        ];
    }

    $created_at = isset($row->created_at) ? (string) $row->created_at : '';
    $created_at_shamsi = ($created_at !== '' && function_exists('sc_date_shamsi'))
        ? sc_date_shamsi($created_at, 'Y/m/d H:i')
        : '';

    return [
        'id' => (int) $row->id,
        'title' => isset($row->name) ? (string) $row->name : '',
        'description' => isset($row->description) ? (string) $row->description : '',
        'category' => [
            'id' => isset($row->category_id) ? (int) $row->category_id : 0,
            'name' => isset($row->category_name) ? (string) $row->category_name : '',
        ],
        'status' => $status,
        'status_label' => isset($labels[$status]) ? $labels[$status] : $status,
        'file_url' => !empty($row->file_url) ? (string) $row->file_url : null,
        'created_at' => $created_at,
        'created_at_shamsi' => $created_at_shamsi,
        'owner_type' => $owner_type,
        'owner' => $owner,
        'related_coach' => $related_coach,
    ];
}

/**
 * @param array<string,mixed> $args
 * @return array{items:array<int,object>,total:int}
 */
function sc_honors_api_query_rows(array $args) {
    global $wpdb;

    $honors_table = $wpdb->prefix . 'sc_honors';
    $categories_table = $wpdb->prefix . 'sc_honor_categories';
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $where = ['1=1'];
    $values = [];

    $owner_type = isset($args['owner_type']) ? (string) $args['owner_type'] : 'all';
    if ($owner_type === 'member') {
        $where[] = 'h.member_id IS NOT NULL AND h.member_id > 0';
    } elseif ($owner_type === 'coach') {
        $where[] = '(h.member_id IS NULL OR h.member_id = 0) AND h.coach_id IS NOT NULL AND h.coach_id > 0';
    }

    $status = isset($args['status']) ? (string) $args['status'] : 'approved';
    if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'h.status = %s';
        $values[] = $status;
    }

    if (!empty($args['member_id'])) {
        $where[] = 'h.member_id = %d';
        $values[] = absint($args['member_id']);
    }

    if (!empty($args['coach_id'])) {
        $where[] = 'h.coach_id = %d';
        $values[] = absint($args['coach_id']);
    }

    if (!empty($args['category_id'])) {
        $where[] = 'h.category_id = %d';
        $values[] = absint($args['category_id']);
    }

    if (!empty($args['honor_id'])) {
        $where[] = 'h.id = %d';
        $values[] = absint($args['honor_id']);
    }

    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM `$honors_table` h WHERE $where_sql";
    if (!empty($values)) {
        $count_sql = $wpdb->prepare($count_sql, $values);
    }
    $total = (int) $wpdb->get_var($count_sql);

    $page = max(1, isset($args['page']) ? absint($args['page']) : 1);
    $per_page = isset($args['per_page']) ? absint($args['per_page']) : 20;
    if ($per_page < 1) {
        $per_page = 20;
    }
    if ($per_page > 100) {
        $per_page = 100;
    }
    $offset = ($page - 1) * $per_page;

    $select_values = $values;
    $select_values[] = $per_page;
    $select_values[] = $offset;

    $sql = "SELECT h.*,
            c.name AS category_name,
            m.first_name AS member_first_name,
            m.last_name AS member_last_name,
            m.national_id AS member_national_id,
            co.first_name AS coach_first_name,
            co.last_name AS coach_last_name
        FROM `$honors_table` h
        LEFT JOIN `$categories_table` c ON c.id = h.category_id
        LEFT JOIN `$members_table` m ON m.id = h.member_id
        LEFT JOIN `$coaches_table` co ON co.id = h.coach_id
        WHERE $where_sql
        ORDER BY h.created_at DESC, h.id DESC
        LIMIT %d OFFSET %d";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $select_values));

    return [
        'items' => is_array($rows) ? $rows : [],
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
    ];
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_honors_api_list_handler(WP_REST_Request $request) {
    $owner_type = sanitize_key((string) $request->get_param('owner_type'));
    if ($owner_type === '') {
        $owner_type = 'all';
    }
    if (!in_array($owner_type, ['all', 'member', 'coach'], true)) {
        return new WP_Error('sc_honors_api_bad_request', 'owner_type باید all، member یا coach باشد.', ['status' => 400]);
    }

    $status = sanitize_key((string) $request->get_param('status'));
    if ($status === '') {
        $status = 'approved';
    }
    if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
        return new WP_Error('sc_honors_api_bad_request', 'status نامعتبر است.', ['status' => 400]);
    }

    $result = sc_honors_api_query_rows([
        'owner_type' => $owner_type,
        'status' => $status,
        'member_id' => absint($request->get_param('member_id')),
        'coach_id' => absint($request->get_param('coach_id')),
        'category_id' => absint($request->get_param('category_id')),
        'page' => absint($request->get_param('page')) ?: 1,
        'per_page' => absint($request->get_param('per_page')) ?: 20,
    ]);

    $items = [];
    foreach ($result['items'] as $row) {
        $items[] = sc_honors_api_format_item($row);
    }

    $total_pages = $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 0;

    return rest_ensure_response([
        'success' => true,
        'page' => $result['page'],
        'per_page' => $result['per_page'],
        'total' => $result['total'],
        'total_pages' => $total_pages,
        'items' => $items,
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sc_honors_api_single_handler(WP_REST_Request $request) {
    $honor_id = absint($request->get_param('id'));
    if (!$honor_id) {
        return new WP_Error('sc_honors_api_bad_request', 'شناسه افتخار نامعتبر است.', ['status' => 400]);
    }

    $result = sc_honors_api_query_rows([
        'honor_id' => $honor_id,
        'owner_type' => 'all',
        'status' => 'all',
        'page' => 1,
        'per_page' => 1,
    ]);

    if (empty($result['items'])) {
        return new WP_Error('sc_honors_api_not_found', 'افتخار یافت نشد.', ['status' => 404]);
    }

    return rest_ensure_response([
        'success' => true,
        'item' => sc_honors_api_format_item($result['items'][0]),
    ]);
}

add_action('rest_api_init', 'sc_register_honors_rest_routes');
function sc_register_honors_rest_routes() {
    register_rest_route('sportclub/v1', '/honors', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_honors_api_list_handler',
        'permission_callback' => 'sc_honors_api_permission_check',
        'args' => [
            'owner_type' => [
                'type' => 'string',
                'required' => false,
                'enum' => ['all', 'member', 'coach'],
            ],
            'status' => [
                'type' => 'string',
                'required' => false,
                'enum' => ['all', 'pending', 'approved', 'rejected'],
            ],
            'member_id' => ['type' => 'integer', 'required' => false],
            'coach_id' => ['type' => 'integer', 'required' => false],
            'category_id' => ['type' => 'integer', 'required' => false],
            'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
            'per_page' => ['type' => 'integer', 'required' => false, 'default' => 20],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);

    register_rest_route('sportclub/v1', '/honors/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'sc_honors_api_single_handler',
        'permission_callback' => 'sc_honors_api_permission_check',
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
            'api_key' => ['type' => 'string', 'required' => false],
        ],
    ]);
}

add_action('rest_api_init', 'sc_honors_api_register_cors', 15);
function sc_honors_api_register_cors() {
    add_filter('rest_pre_serve_request', 'sc_honors_api_send_cors_headers', 10, 4);
}

/**
 * @param bool              $served
 * @param WP_HTTP_Response  $result
 * @param WP_REST_Request   $request
 * @param WP_REST_Server    $server
 * @return bool
 */
function sc_honors_api_send_cors_headers($served, $result, $request, $server) {
    if (!($request instanceof WP_REST_Request)) {
        return $served;
    }
    $route = (string) $request->get_route();
    if (strpos($route, '/sportclub/v1/honors') !== 0) {
        return $served;
    }

    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: X-API-Key, Authorization, Content-Type');

    if ($request->get_method() === 'OPTIONS') {
        status_header(204);
        exit;
    }

    return $served;
}
