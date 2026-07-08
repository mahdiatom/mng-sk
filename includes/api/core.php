<?php
/**
 * هسته API عمومی باشگاه (کلید دسترسی، CORS، پاسخ استاندارد)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function sc_get_public_api_key() {
    return trim((string) sc_get_setting('public_api_key', ''));
}

/**
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function sc_public_api_permission_check(WP_REST_Request $request) {
    $configured = sc_get_public_api_key();
    if ($configured === '') {
        return new WP_Error(
            'sc_public_api_disabled',
            'API عمومی فعال نیست. کلید API را در تنظیمات افزونه (تب API عمومی) تعریف کنید.',
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
            'sc_public_api_unauthorized',
            'کلید API نامعتبر است.',
            ['status' => 401]
        );
    }

    return true;
}

/**
 * @param int $page
 * @param int $per_page
 * @return array{page:int,per_page:int,offset:int}
 */
function sc_public_api_pagination_args($page, $per_page) {
    $page = max(1, absint($page) ?: 1);
    $per_page = absint($per_page) ?: 20;
    if ($per_page < 1) {
        $per_page = 20;
    }
    if ($per_page > 100) {
        $per_page = 100;
    }

    return [
        'page' => $page,
        'per_page' => $per_page,
        'offset' => ($page - 1) * $per_page,
    ];
}

/**
 * @param array<int,mixed> $items
 * @param int              $total
 * @param int              $page
 * @param int              $per_page
 * @return WP_REST_Response
 */
function sc_public_api_list_response(array $items, $total, $page, $per_page) {
    $total = max(0, (int) $total);
    $per_page = max(1, (int) $per_page);
    $total_pages = (int) ceil($total / $per_page);

    return rest_ensure_response([
        'success' => true,
        'page' => (int) $page,
        'per_page' => (int) $per_page,
        'total' => $total,
        'total_pages' => $total_pages,
        'items' => $items,
    ]);
}

/**
 * @param array<string,mixed> $item
 * @return WP_REST_Response
 */
function sc_public_api_item_response(array $item) {
    return rest_ensure_response([
        'success' => true,
        'item' => $item,
    ]);
}

/**
 * @return string
 */
function sc_public_api_system_base_url() {
    $configured = trim((string) sc_get_setting('public_api_system_url', ''));
    if ($configured !== '') {
        return untrailingslashit($configured);
    }
    if (function_exists('wc_get_page_permalink')) {
        $account = wc_get_page_permalink('myaccount');
        if (is_string($account) && $account !== '') {
            return untrailingslashit($account);
        }
    }
    return untrailingslashit(home_url('/my-account/'));
}

/**
 * @return string
 */
function sc_public_api_enrollment_url() {
    if (function_exists('wc_get_account_endpoint_url')) {
        $url = wc_get_account_endpoint_url('sc-enroll-course');
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }
    return trailingslashit(sc_public_api_system_base_url()) . 'sc-enroll-course/';
}

/**
 * @return string
 */
function sc_public_api_events_url() {
    if (function_exists('wc_get_account_endpoint_url')) {
        $url = wc_get_account_endpoint_url('sc-events');
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }
    return trailingslashit(sc_public_api_system_base_url()) . 'sc-events/';
}

/**
 * @return string
 */
function sc_public_api_private_classes_url() {
    if (function_exists('wc_get_account_endpoint_url')) {
        $url = wc_get_account_endpoint_url('sc-private-classes');
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }
    return trailingslashit(sc_public_api_system_base_url()) . 'sc-private-classes/';
}

add_action('rest_api_init', 'sc_public_api_register_cors', 15);
function sc_public_api_register_cors() {
    add_filter('rest_pre_serve_request', 'sc_public_api_send_cors_headers', 11, 4);
}

/**
 * @param bool              $served
 * @param WP_HTTP_Response  $result
 * @param WP_REST_Request   $request
 * @param WP_REST_Server    $server
 * @return bool
 */
function sc_public_api_send_cors_headers($served, $result, $request, $server) {
    if (!($request instanceof WP_REST_Request)) {
        return $served;
    }
    $route = (string) $request->get_route();
    if (strpos($route, '/sportclub/v1/public') !== 0) {
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
