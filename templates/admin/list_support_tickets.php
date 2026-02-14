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
            'id' => 'شناسه',
            'user' => 'ارسال‌کننده',
            'subject' => 'موضوع',
            'department' => 'بخش',
            'status' => 'وضعیت',
            'created_at' => 'تاریخ ایجاد',
            'updated_at' => 'آخرین به‌روزرسانی',
            'actions' => 'عملیات',
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
        $name = $item['user_name'];
        $uid = (int) $item['user_id'];
        if ($uid && current_user_can('edit_users')) {
            $url = admin_url('user-edit.php?user_id=' . $uid);
            return '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a>';
        }
        return esc_html($name);
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

        $base = ['1=1'];
        $params = [];
        if ($filter_department !== 'all') {
            $base[] = 'department = %s';
            $params[] = $filter_department;
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
        if ($search) $url = add_query_arg('s', $search, $url);

        $views = [];
        foreach (['all' => $count_all, 'pending_reply' => $count_pending, 'answered' => $count_answered, 'closed' => $count_closed] as $key => $count) {
            $labels = ['all' => 'همه', 'pending_reply' => 'در انتظار پاسخ', 'answered' => 'پاسخ داده شده', 'closed' => 'بسته شده'];
            $link = $key === 'all' ? $url : add_query_arg('filter_status', $key, $url);
            $class = ($filter_status === $key) ? 'current' : '';
            $views[$key] = '<a href="' . esc_url($link) . '" class="' . $class . '">' . esc_html($labels[$key]) . ' <span class="count">(' . (int) $count . ')</span></a>';
        }
        return $views;
    }

    public function extra_tablenav($which) {
        if ($which !== 'top') return;
        $filter_department = isset($_GET['filter_department']) ? sanitize_text_field($_GET['filter_department']) : 'all';
        $url = admin_url('admin.php?page=sc-support-tickets');
        if (isset($_GET['filter_status'])) $url = add_query_arg('filter_status', $_GET['filter_status'], $url);
        if (isset($_GET['s'])) $url = add_query_arg('s', $_GET['s'], $url);
        ?>
        <div class="alignleft actions">
            <label>بخش:</label>
            <select name="filter_department" onchange="location.href=this.value">
                <option value="<?php echo esc_url(add_query_arg('filter_department', 'all', $url)); ?>" <?php selected($filter_department, 'all'); ?>>همه</option>
                <option value="<?php echo esc_url(add_query_arg('filter_department', 'manager', $url)); ?>" <?php selected($filter_department, 'manager'); ?>>مدیر باشگاه</option>
                <option value="<?php echo esc_url(add_query_arg('filter_department', 'site_support', $url)); ?>" <?php selected($filter_department, 'site_support'); ?>>پشتیبانی سایت</option>
                <option value="<?php echo esc_url(add_query_arg('filter_department', 'coach', $url)); ?>" <?php selected($filter_department, 'coach'); ?>>مربی</option>
            </select>
        </div>
        <?php
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

        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        if ($filter_status !== 'all') {
            $where[] = 't.status = %s';
            $params[] = $filter_status;
        }
        $filter_department = isset($_GET['filter_department']) ? sanitize_text_field($_GET['filter_department']) : 'all';
        if ($filter_department !== 'all') {
            $where[] = 't.department = %s';
            $params[] = $filter_department;
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

        $sql = "SELECT t.id, t.user_id, t.department, t.coach_id, t.subject, t.status, t.created_at, t.updated_at,
                TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS user_name
                FROM $t t
                LEFT JOIN $m m ON m.user_id = t.user_id
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