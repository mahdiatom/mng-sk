<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Honors_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'honor',
            'plural' => 'honors',
            'ajax' => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'member_name' => 'نام بازیکن',
            'name' => 'نام افتخار',
            'category' => 'دسته',
            'description' => 'توضیحات',
            'file' => 'فایل',
            'created_at' => 'تاریخ ثبت'
        ];
    }

    public function column_file($item) {
        if (!empty($item['file_url'])) {
            return '<a href="' . esc_url($item['file_url']) . '" target="_blank" style="color: #2271b1; text-decoration: none;">📎 دانلود</a>';
        }
        return '-';
    }

    public function column_member_name($item) {
        global $wpdb;
        
        // اگر member_id وجود دارد، نام بازیکن را نمایش بده
        if (!empty($item['member_id'])) {
            $members_table = $wpdb->prefix . 'sc_members';
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT first_name, last_name FROM $members_table WHERE id = %d",
                $item['member_id']
            ));
            
            if ($member) {
                return esc_html($member->first_name . ' ' . $member->last_name);
            }
        }
        
        // اگر coach_id وجود دارد، نام مربی را نمایش بده
        if (!empty($item['coach_id'])) {
            $coaches_table = $wpdb->prefix . 'sc_coaches';
            $coach = $wpdb->get_row($wpdb->prepare(
                "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
                $item['coach_id']
            ));
            
            if ($coach) {
                return esc_html($coach->first_name . ' ' . $coach->last_name . ' <span style="color: #666;">(مربی)</span>');
            }
        }
        
        return '-';
    }

    public function column_name($item) {
        $name = esc_html($item['name']);
        $actions = [];
        
        $delete_url = wp_nonce_url(admin_url('admin.php?page=sc-honors&action=delete&honor_id=' . $item['id']), 'delete_honor_' . $item['id']);
        $actions['delete'] = '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'آیا مطمئن هستید؟\')">حذف</a>';

        return $name . ' ' . $this->row_actions($actions);
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . $item['id'] . '" name="honor[]" />';
    }

    public function column_category($item) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'sc_honor_categories';
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM $categories_table WHERE id = %d",
            $item['category_id']
        ));
        
        return $category ? esc_html($category->name) : '-';
    }

    public function column_description($item) {
        $description = esc_html($item['description']);
        if (mb_strlen($description) > 50) {
            return mb_substr($description, 0, 50) . '...';
        }
        return $description ?: '-';
    }

    public function column_created_at($item) {
        return esc_html(sc_date_shamsi($item['created_at'], 'Y/m/d H:i'));
    }

    public function column_default($item, $column_name) {
        return "-";
    }

    public function get_hidden_columns() {
        return [];
    }

    public function get_sortable_columns() {
        return [
            'created_at' => ['created_at', false],
            'name' => ['name', false]
        ];
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'حذف'
        ];
    }

    public function process_bulk_action() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ('delete' === $this->current_action()) {
            if (isset($_POST['honor']) && is_array($_POST['honor'])) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'sc_honors';
                $honor_ids = array_map('absint', $_POST['honor']);
                $placeholders = implode(',', array_fill(0, count($honor_ids), '%d'));
                
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN ($placeholders)",
                    ...$honor_ids
                ));
            }
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_honors';
        
        $this->process_bulk_action();
        
        // Handle single delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['honor_id'])) {
            check_admin_referer('delete_honor_' . $_GET['honor_id']);
            $honor_id = absint($_GET['honor_id']);
            $wpdb->delete($table_name, ['id' => $honor_id], ['%d']);
            wp_redirect(admin_url('admin.php?page=sc-honors'));
            exit;
        }

        $per_page = $this->get_items_per_page('honors_per_page', 10);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        $where = "1=1";

        // فیلتر دسته
        if (isset($_GET['filter_category']) && $_GET['filter_category'] != 'all') {
            $category_id = absint($_GET['filter_category']);
            $where .= $wpdb->prepare(" AND category_id = %d", $category_id);
        }

        // فیلتر نوع (بازیکن یا مربی)
        if (isset($_GET['filter_type']) && $_GET['filter_type'] != 'all') {
            $filter_type = sanitize_text_field($_GET['filter_type']);
            if ($filter_type === 'member') {
                $where .= " AND member_id IS NOT NULL AND member_id != 0";
            } elseif ($filter_type === 'coach') {
                $where .= " AND coach_id IS NOT NULL AND coach_id != 0";
            }
        }

        // جستجو
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(" AND (name LIKE %s OR description LIKE %s)", $search, $search);
        }

        $order_clause = "ORDER BY $orderby $order";

        $results = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name WHERE $where $order_clause LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        $this->set_pagination_args([
            'total_items' => $wpdb->get_var("SELECT FOUND_ROWS()"),
            'per_page' => $per_page
        ]);

        $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
        $this->items = $results;
    }

    public function extra_tablenav($which) {
        if ($which == 'top') {
            global $wpdb;
            $categories_table = $wpdb->prefix . 'sc_honor_categories';
            $categories = $wpdb->get_results("SELECT id, name FROM $categories_table ORDER BY name ASC");
            
            $selected_category = isset($_GET['filter_category']) ? absint($_GET['filter_category']) : 'all';
            $selected_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
            
            echo '<div class="alignleft actions">';
            echo '<select name="filter_type" id="filter_type" style="margin-left: 5px;">';
            echo '<option value="all"' . ($selected_type == 'all' ? ' selected' : '') . '>همه (بازیکن و مربی)</option>';
            echo '<option value="member"' . ($selected_type == 'member' ? ' selected' : '') . '>فقط بازیکنان</option>';
            echo '<option value="coach"' . ($selected_type == 'coach' ? ' selected' : '') . '>فقط مربیان</option>';
            echo '</select>';
            echo '<select name="filter_category" id="filter_category" style="margin-left: 5px;">';
            echo '<option value="all"' . ($selected_category == 'all' ? ' selected' : '') . '>همه دسته‌ها</option>';
            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->id) . '"' . ($selected_category == $category->id ? ' selected' : '') . '>' . esc_html($category->name) . '</option>';
            }
            echo '</select>';
            echo '<input type="submit" class="button" value="فیلتر">';
            echo '</div>';
        }
    }
}

// Process form submission
if (isset($_POST['filter_category']) || isset($_POST['filter_type'])) {
    $filter_category = isset($_POST['filter_category']) ? absint($_POST['filter_category']) : (isset($_GET['filter_category']) ? absint($_GET['filter_category']) : 'all');
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : (isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all');
    
    $args = [];
    if ($filter_category != 'all') {
        $args['filter_category'] = $filter_category;
    }
    if ($filter_type != 'all') {
        $args['filter_type'] = $filter_type;
    }
    
    wp_redirect(add_query_arg($args, admin_url('admin.php?page=sc-honors')));
    exit;
}

?>
<div class="wrap">
    <h1 class="wp-heading-inline">لیست افتخارات</h1>
    <hr class="wp-header-end">

    <?php
    $honors_list_table = new Honors_List_Table();
    $honors_list_table->prepare_items();
    ?>

    <form method="get">
        <input type="hidden" name="page" value="sc-honors">
        <?php $honors_list_table->search_box('جستجو', 'search_id'); ?>
    </form>

    <form method="post">
        <?php $honors_list_table->display(); ?>
    </form>
</div>
