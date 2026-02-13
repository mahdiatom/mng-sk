<?php
/**
 * لیست بازیکنان مربی با WP_List_Table
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Coach_Players_List_Table extends WP_List_Table {

    protected $coach_id;

    public function __construct($coach_id = 0) {
        $this->coach_id = (int) $coach_id;
        parent::__construct([
            'singular' => 'بازیکن',
            'plural'   => 'بازیکنان',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'first_name'   => 'نام',
            'last_name'    => 'نام خانوادگی',
            'national_id'  => 'کد ملی',
            'player_phone' => 'تلفن',
            'is_active'    => 'وضعیت',
            'actions'      => 'عملیات',
        ];
    }

    public function get_sortable_columns() {
        return [
            'first_name'  => ['first_name', false],
            'last_name'   => ['last_name', false],
            'national_id' => ['national_id', false],
            'is_active'   => ['is_active', false],
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'first_name':
                return esc_html($item['first_name'] ?? '');
            case 'last_name':
                return esc_html($item['last_name'] ?? '');
            case 'national_id':
                return esc_html(!empty($item['national_id']) ? $item['national_id'] : '-');
            case 'player_phone':
                return esc_html(!empty($item['player_phone']) ? $item['player_phone'] : '-');
            case 'is_active':
                return !empty($item['is_active']) ? 'فعال' : 'غیرفعال';
            case 'actions':
                $edit_url = admin_url('admin.php?page=sc-coach-edit-player&player_id=' . (int) $item['id']);
                return '<a href="' . esc_url($edit_url) . '" class="button button-small">ویرایش</a>';
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '-';
        }
    }

    public function no_items() {
        if (!empty($_GET['s'])) {
            echo 'بازیکنی با این جستجو در دوره‌های شما یافت نشد.';
        } else {
            echo 'هنوز بازیکنی در دوره‌های شما ثبت‌نام نکرده است.';
        }
    }

    public function prepare_items() {
        global $wpdb;
        if ($this->coach_id <= 0) {
            $this->items = [];
            return;
        }

        $members_table = $wpdb->prefix . 'sc_members';
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';

        $per_page = $this->get_items_per_page('coach_players_per_page', 20);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        if (!in_array($orderby, ['first_name', 'last_name', 'national_id', 'player_phone', 'is_active'], true)) {
            $orderby = 'last_name';
        }
        $order_clause = 'ORDER BY m.' . preg_replace('/[^a-z_]/', '', $orderby) . ' ' . ($order === 'ASC' ? 'ASC' : 'DESC');

        $where = '1=1';
        $prepare_args = [$this->coach_id];
        if (!empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= " AND (m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s OR m.player_phone LIKE %s)";
            $prepare_args[] = $search;
            $prepare_args[] = $search;
            $prepare_args[] = $search;
            $prepare_args[] = $search;
        }
        $prepare_args[] = $per_page;
        $prepare_args[] = $offset;

        $sql = "SELECT SQL_CALC_FOUND_ROWS DISTINCT m.id, m.first_name, m.last_name, m.national_id, m.player_phone, m.is_active
                FROM {$members_table} m
                INNER JOIN {$member_courses_table} mc ON mc.member_id = m.id AND mc.status = 'active'
                INNER JOIN {$course_coaches_table} cc ON cc.course_id = mc.course_id AND cc.coach_id = %d
                WHERE {$where}
                {$order_clause}
                LIMIT %d OFFSET %d";

        $this->items = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_args), ARRAY_A);

        $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }
}
