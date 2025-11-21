<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Player_List_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'row' => 'ردیف',
            'id' => 'شناسه',
            'full_name' => 'نام و نام خانوادگی',
            'birth_date_shamsi' => 'تاریخ تولد',
            'national_id' => 'کد ملی ',
            'player_phone' => 'شماره تماس ',
            'is_active' => 'وضعیت '
        ];
    }

    public function column_row($item) {

    static $row_number = 0; // شمارنده برای هر صفحه

    $page = $this->get_pagenum(); 
    $per_page = 10; // دقیقاً باید همان مقدار prepare_items باشد

    $row_number++;

    return (($page - 1) * $per_page) + $row_number;
}
    public function column_full_name($item) {
        $full_name = $item['first_name'] . ' ' . $item['last_name'];

        $actions = [
            'edit' => '<a href="' . admin_url('admin.php?page=sc-add-member&player_id=') . $item['id'] . '">ویرایش</a>',
            'delete' => '<a href="' . admin_url('admin.php?page=sc-members&action=delete&player_id=') . $item['id'] . '">حذف</a>',
            'view' => sprintf(
            '<p id="modal_custom_sc" class="view-player" data-id="%s">مشاهده اطلاعات</p>',
            $item['id']
        )
        ];

        return $full_name . ' ' . $this->row_actions($actions);
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . $item['id'] . '" name="player[]" />';
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'birth_date_shamsi':
                return $item['birth_date_shamsi'];
            case 'national_id':
                return $item['national_id'];
            case 'player_phone':
                return $item['player_phone'];
            case 'is_active':
                return $item['is_active'] ? "فعال" : "غیرفعال";
            default:
                return "-";
        }
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    public function no_items() {
        if (isset($_GET['s'])) {
            echo "بازیکنی با این مشخصات یافت نشد!";
        } else {
            echo "هنوز بازیکنی ثبت نکرده‌اید. از بخش افزودن بازیکن اولین بازیکن خود را اضافه کنید.";
        }
    }

    public function get_sortable_columns() {
        return [
            'birth_date_shamsi' => ['birth_date_shamsi', true],
            'is_active' => ['is_active', true],
        ];
    }

    public function get_bulk_actions() {
        return [
            'delete' => 'حذف بازیکن'
        ];
    }

    public function process_bulk_action() {
         global $wpdb;
            $table_name = $wpdb->prefix . 'sc_members';

        if ($this->current_action() == 'delete' && isset($_GET['player_id'])) {
        $player_id = absint($_GET['player_id']);
        $wpdb->delete($table_name, ['id' => $player_id]);
        wp_redirect(admin_url('admin.php?page=sc-members&sc_status=deleted'));
            exit;
        
    }

        if ($this->current_action() == 'delete') {
            $players = isset($_GET['player']) ? $_GET['player'] : [];
           
            foreach ($players as $player_id) {
                $wpdb->delete($table_name, ['id' => $player_id]);
            }
            wp_redirect(admin_url('admin.php?page=sc-members&sc_status=bulk_deleted'));
            exit;
        }
      
    }

    

    protected function view_create($key, $label, $url, $count = 0) {
        $current_status = isset($_GET['player_status']) ? $_GET['player_status'] : 'all';
        $class_view = $current_status == $key ? 'current' : '';
        if (isset($_GET['s'])) {
            $url .= "&s=" . sanitize_text_field($_GET['s']);
        }
        $view = sprintf("<a href='%s' class='%s'>%s</a>", $url, $class_view, $label);
        if ($count) {
            $view .= sprintf("<span class='count'>(%d)</span>", $count);
        }
        return $view;
    }

    public function get_views() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_members';
        $where = " 1=1 ";

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(
                " AND (first_name LIKE %s OR last_name LIKE %s OR player_phone LIKE %s OR birth_date_shamsi LIKE %s OR birth_date_gregorian LIKE %s)",
                $search, $search, $search, $search, $search
            );
        }

        $count_all = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");

        return [
            'all' => $this->view_create(
                'all',
                'همه',
                admin_url('admin.php?page=sc-members&player_status=all'),
                $count_all
            )
        ];
    }

    public function prepare_items() {
        $this->process_bulk_action();

        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_members';

        $per_page = 10; // تعداد نمایش در هر صفحه
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $order_clause = "ORDER BY $orderby $order";

        $where = " 1=1 ";

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(
                " AND (first_name LIKE %s OR last_name LIKE %s OR player_phone LIKE %s OR birth_date_shamsi LIKE %s OR birth_date_gregorian LIKE %s)",
                $search, $search, $search, $search, $search
            );
        }

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
}
?>


