<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!class_exists('Support_Tickets_List_Table')) {
class Support_Tickets_List_Table extends WP_List_Table {

    public function __construct($args = []) {
        parent::__construct([
            'singular' => 'ticket',
            'plural' => 'tickets',
            'ajax' => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'subject' => 'موضوع',
             'id' => 'شناسه',
            'user' => 'ارسال‌کننده',
            'department' => 'بخش',
            'status' => 'وضعیت',
            'created_at' => 'تاریخ ایجاد',
            'updated_at' => 'آخرین به‌روزرسانی',
            'actions' => 'عملیات',
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="ticket[]" value="%d" />', $item['id']);
    }

    public function get_bulk_actions() {
        if (function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only()) {
            return [
                'close' => 'بستن تیکت',
            ];
        }
        return [
            'delete' => 'حذف',
            'close'  => 'بستن تیکت',
        ];
    }

    protected function get_primary_column_name() {
        return 'subject';
    }

    public function get_sortable_columns() {
        return [
            'id' => ['id', true],
            'status' => ['status', false],
            'created_at' => ['created_at', true],
            'updated_at' => ['updated_at', true],
        ];
    }

    public function column_id($item) {
        return (int) $item['id'];
    }

    public function column_user($item) {
        $name = isset($item['user_name']) ? trim($item['user_name']) : '';
        $uid = (int) $item['user_id'];
        $created_by = isset($item['created_by_type']) ? $item['created_by_type'] : 'user';
        $coach_name = isset($item['coach_name']) ? trim($item['coach_name']) : '';
        if ($uid && $name !== '') {
            $out = $name;
            if (current_user_can('edit_users')) {
                $url = admin_url('user-edit.php?user_id=' . $uid);
                $out = '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a>';
            } else {
                $out = esc_html($name);
            }
            if ($created_by !== 'user') {
                if ($created_by === 'coach') {
                    $out .= ' <span class="description">(ارسال توسط مربی)</span>';
                } elseif ($created_by === 'accountant') {
                    $out .= ' <span class="description">(ارسال توسط حسابدار)</span>';
                } else {
                    $out .= ' <span class="description">(ارسال توسط مدیر)</span>';
                }
            }
            return $out;
        }
        if ($uid) {
            return 'کاربر #' . $uid;
        }
        if (isset($item['department']) && $item['department'] === 'accountant' && (int) $item['coach_id'] > 0) {
            $u = get_userdata((int) $item['coach_id']);
            return $u ? (esc_html($u->display_name) . ' <span class="description">(حسابدار)</span>') : 'حسابدار باشگاه';
        }
        if ((int) $item['coach_id'] > 0 && $coach_name !== '') {
            return esc_html($coach_name) . ' <span class="description">(مربی)</span>';
        }
        return 'مدیر باشگاه';
    }

    public function column_subject($item) {
        $url = admin_url('admin.php?page=sc-support-ticket-view&id=' . $item['id']);
        return '<a href="' . esc_url($url) . '"><strong>' . esc_html($item['subject']) . '</strong></a>';
    }

    public function column_department($item) {
        return esc_html(sc_support_department_label($item['department'], $item['coach_id']));
    }

    public function column_status($item) {
        $status = $item['status'];
        $labels = [
            'pending_reply' => ['برچسب' => 'در انتظار پاسخ', 'رنگ' => '#f0a000'],
            'answered' => ['برچسب' => 'پاسخ داده شده', 'رنگ' => '#00a32a'],
            'closed' => ['برچسب' => 'بسته شده', 'رنگ' => '#666'],
        ];
        $info = isset($labels[$status]) ? $labels[$status] : ['برچسب' => $status, 'رنگ' => '#666'];
        return '<span style="color:' . esc_attr($info['رنگ']) . '; font-weight:bold;">' . esc_html($info['برچسب']) . '</span>';
    }

    public function column_created_at($item) {
        return esc_html(sc_date_shamsi($item['created_at'], 'Y/m/d H:i'));
    }

    public function column_updated_at($item) {
        return esc_html(sc_date_shamsi($item['updated_at'], 'Y/m/d H:i'));
    }

    public function column_actions($item) {
        $url = admin_url('admin.php?page=sc-support-ticket-view&id=' . $item['id']);
        return '<a href="' . esc_url($url) . '" class="button button-small">مشاهده و پاسخ</a>';
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }

    public function get_views() {
        global $wpdb;
        $t = $wpdb->prefix . 'sc_support_tickets';
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_department = isset($_GET['filter_department']) ? sanitize_text_field($_GET['filter_department']) : 'all';
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_user_id = isset($_GET['filter_user_id']) ? absint($_GET['filter_user_id']) : 0;
        $filter_created_by = isset($_GET['filter_created_by']) ? sanitize_text_field($_GET['filter_created_by']) : 'all';

        $base = ['1=1'];
        $params = [];
        $scope_accountant = function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only();
        if ($scope_accountant && function_exists('sc_support_apply_accountant_ticket_list_scope')) {
            sc_support_apply_accountant_ticket_list_scope($base, $params, '');
        } elseif ($filter_department !== 'all') {
            $base[] = 'department = %s';
            $params[] = $filter_department;
        }
        if ($filter_user_id > 0) {
            $base[] = 'user_id = %d';
            $params[] = $filter_user_id;
        }
        if ($filter_created_by === 'user') {
            $base[] = "created_by_type = 'user'";
        } elseif ($filter_created_by === 'coach') {
            $base[] = "created_by_type = 'coach'";
        } elseif ($filter_created_by === 'admin') {
            $base[] = "created_by_type = 'admin'";
        }

        // Date filters for views counts
        $date_from_sh = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
        $date_to_sh   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
        if ($date_from_sh && function_exists('sc_shamsi_to_gregorian_date')) {
            $dfrom = sc_shamsi_to_gregorian_date($date_from_sh);
            if ($dfrom) {
                $base[] = 'DATE(created_at) >= %s';
                $params[] = $dfrom;
            }
        }
        if ($date_to_sh && function_exists('sc_shamsi_to_gregorian_date')) {
            $dto = sc_shamsi_to_gregorian_date($date_to_sh);
            if ($dto) {
                $base[] = 'DATE(created_at) <= %s';
                $params[] = $dto;
            }
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if (is_numeric($search)) {
                $base[] = '(subject LIKE %s OR id = %d)';
                $params[] = $like;
                $params[] = (int) $search;
            } else {
                $base[] = 'subject LIKE %s';
                $params[] = $like;
            }
        }
        $where = implode(' AND ', $base);

        $run = function ($status = null) use ($wpdb, $t, $where, $params) {
            $w = $where;
            $p = $params;
            if ($status !== null) {
                $w .= ' AND status = %s';
                $p[] = $status;
            }
            return $p ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE $w", $p)) : (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE $w");
        };
        $count_all = $run(null);
        $count_pending = $run('pending_reply');
        $count_answered = $run('answered');
        $count_closed = $run('closed');

        $url = admin_url('admin.php?page=sc-support-tickets');
        if ($filter_department !== 'all') $url = add_query_arg('filter_department', $filter_department, $url);
        if ($filter_user_id > 0) $url = add_query_arg('filter_user_id', $filter_user_id, $url);
        if ($filter_created_by !== 'all') $url = add_query_arg('filter_created_by', $filter_created_by, $url);
        if ($search) $url = add_query_arg('s', $search, $url);
        if (!empty($_GET['filter_date_from_shamsi'])) $url = add_query_arg('filter_date_from_shamsi', sanitize_text_field($_GET['filter_date_from_shamsi']), $url);
        if (!empty($_GET['filter_date_to_shamsi'])) $url = add_query_arg('filter_date_to_shamsi', sanitize_text_field($_GET['filter_date_to_shamsi']), $url);

        $views = [];
        foreach (['all' => $count_all, 'pending_reply' => $count_pending, 'answered' => $count_answered, 'closed' => $count_closed] as $key => $count) {
            $labels = ['all' => 'همه', 'pending_reply' => 'در انتظار پاسخ', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته شده'];
            $link = $key === 'all' ? $url : add_query_arg('filter_status', $key, $url);
            $class = ($filter_status === $key) ? 'current' : '';
            $views[$key] = '<a href="' . esc_url($link) . '" class="' . $class . '">' . esc_html($labels[$key]) . ' <span class="count">(' . (int) $count . ')</span></a>';
        }
        return $views;
    }

    // Filters are now rendered directly in the page template (above bulk actions)
    public function extra_tablenav($which) {
        // No longer used for top filters
    }

    public function prepare_items() {
        global $wpdb;
        $t = $wpdb->prefix . 'sc_support_tickets';
        $m = $wpdb->prefix . 'sc_members';

        $per_page = $this->get_items_per_page('support_tickets_per_page', 20);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'updated_at';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
        if (!in_array($orderby, ['id', 'subject', 'status', 'created_at', 'updated_at'], true)) $orderby = 'updated_at';
        if ($order !== 'ASC') $order = 'DESC';

        $where = ['1=1'];
        $params = [];

        $scope_accountant = function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only();
        if ($scope_accountant && function_exists('sc_support_apply_accountant_ticket_list_scope')) {
            sc_support_apply_accountant_ticket_list_scope($where, $params, 't');
        }

        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        if ($filter_status !== 'all') {
            $where[] = 't.status = %s';
            $params[] = $filter_status;
        }
        if (!$scope_accountant) {
            $filter_department = isset($_GET['filter_department']) ? sanitize_text_field($_GET['filter_department']) : 'all';
            if ($filter_department !== 'all') {
                $where[] = 't.department = %s';
                $params[] = $filter_department;
            }
        }
        $filter_user_id = isset($_GET['filter_user_id']) ? absint($_GET['filter_user_id']) : 0;
        if ($filter_user_id > 0) {
            $where[] = 't.user_id = %d';
            $params[] = $filter_user_id;
        }

        // Created by filter
        $filter_created_by = isset($_GET['filter_created_by']) ? sanitize_text_field($_GET['filter_created_by']) : 'all';
        if ($filter_created_by === 'user') {
            $where[] = "t.created_by_type = 'user'";
        } elseif ($filter_created_by === 'coach') {
            $where[] = "t.created_by_type = 'coach'";
        } elseif ($filter_created_by === 'admin') {
            $where[] = "t.created_by_type = 'admin'";
        }

        // Date range filter (shamsi) — defaults to today are handled in extra_tablenav but still apply if present
        $date_from_sh = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
        $date_to_sh   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
        if ($date_from_sh && function_exists('sc_shamsi_to_gregorian_date')) {
            $dfrom = sc_shamsi_to_gregorian_date($date_from_sh);
            if ($dfrom) {
                $where[] = 'DATE(t.created_at) >= %s';
                $params[] = $dfrom;
            }
        }
        if ($date_to_sh && function_exists('sc_shamsi_to_gregorian_date')) {
            $dto = sc_shamsi_to_gregorian_date($date_to_sh);
            if ($dto) {
                $where[] = 'DATE(t.created_at) <= %s';
                $params[] = $dto;
            }
        }

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if (is_numeric($search)) {
                $where[] = '(t.subject LIKE %s OR t.id = %d)';
                $params[] = $like;
                $params[] = (int) $search;
            } else {
                $where[] = 't.subject LIKE %s';
                $params[] = $like;
            }
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM $t t WHERE $where_sql";
        $total = $params ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

        $c = $wpdb->prefix . 'sc_coaches';
        $sql = "SELECT t.id, t.user_id, t.department, t.coach_id, t.subject, t.status, t.created_at, t.updated_at,
                t.created_by_type, t.created_by_coach_id,
                TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS user_name,
                TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS coach_name
                FROM $t t
                LEFT JOIN $m m ON m.user_id = t.user_id
                LEFT JOIN $c c ON c.id = t.coach_id
                WHERE $where_sql
                ORDER BY t.$orderby $order
                LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $items = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        if (!$items) $items = [];

        $this->items = $items;
        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }

    public function no_items() {
        echo isset($_GET['s']) ? 'تیکتی با این جستجو یافت نشد.' : 'هنوز تیکتی ثبت نشده است.';
    }
}
}