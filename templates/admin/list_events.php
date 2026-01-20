<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Events_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'event',
            'plural' => 'events',
            'ajax' => false
        ]);
    }

    public function get_columns() {
        return [
            //'row' => ' ',
            'cb' => '<input type="checkbox" />',
            'name' => 'نام رویداد / مسابقه',
            'event_time' => 'زمان',
            'date' => 'بازه ثبت نام',
            'date_2' => 'تاریخ برگزاری',
            'price' => 'قیمت',
            'is_active' => 'وضعیت'
        ];
    }

    // public function column_row($item) {
    //     static $row_number = 0;
    //     $page = $this->get_pagenum();
    //     $per_page = $this->get_items_per_page('events_per_page', 10);
    //     $row_number++;
    //     return (($page - 1) * $per_page) + $row_number;
    // }

    public function column_name($item) {
        $name = $item['name'];
        $actions = [];
        
        if ($item['deleted_at']) {
            $restore_url = wp_nonce_url(admin_url('admin.php?page=sc-events&action=restore&event_id=' . $item['id']), 'restore_event_' . $item['id']);
            $delete_url = wp_nonce_url(admin_url('admin.php?page=sc-events&action=delete_permanent&event_id=' . $item['id']), 'delete_permanent_event_' . $item['id']);
            $actions['restore'] = '<a href="' . esc_url($restore_url) . '">بازیابی</a>';
            $actions['delete'] = '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'آیا مطمئن هستید؟ این عمل قابل بازگشت نیست.\')">حذف دائمی</a>';
        } else {
            $edit_url = admin_url('admin.php?page=sc-add-event&event_id=' . $item['id']);
            $trash_url = wp_nonce_url(admin_url('admin.php?page=sc-events&action=trash&event_id=' . $item['id']), 'trash_event_' . $item['id']);
            $actions['edit'] = '<a href="' . esc_url($edit_url) . '">ویرایش</a>';
            $actions['trash'] = '<a href="' . esc_url($trash_url) . '">حذف</a>';
        }

        return $name . ' ' . $this->row_actions($actions);
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . $item['id'] . '" name="event[]" />';
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'event_time':
                return $item['event_time'] ? esc_html($item['event_time']) : '-';
            case 'date':
                $date_parts = [];
                if (!empty($item['start_date_gregorian'])) {
                    $date_parts[] = 'شروع: ' . sc_date_shamsi_date_only($item['start_date_gregorian']);
                }
                if (!empty($item['end_date_gregorian'])) {
                    $date_parts[] = 'پایان: ' . sc_date_shamsi_date_only($item['end_date_gregorian']);
                }
                return !empty($date_parts) ? implode('<br>', $date_parts) : '-';
            case 'date_2':
                $date_parts = [];
                if (!empty($item['holding_date_gregorian'])) {
                    $date_parts[] =  sc_date_shamsi_date_only($item['holding_date_gregorian']);
                }
                
                return !empty($date_parts) ? implode('<br>', $date_parts) : '-';
            case 'price':

                if (function_exists('wc_price')) {
                    $price =  number_format($item['price'], 0, '.', ',');
                if($price == 0 ){
                    $price = 'رایگان';
                }
                return $price ;
                } else {
                    return number_format($item['price'], 0, '.', ',') . ' تومان';

                }

            case 'is_active':
                return $item['is_active'] ? 'فعال' : 'غیرفعال';
            default:
                return "-";
        }
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }
    
public function extra_tablenav($which) {
    if ($which == 'top') {
        // دریافت فیلترهای GET
        $selected_status = isset($_GET['event_status']) ? sanitize_text_field($_GET['event_status']) : 'all';
        $selected_type   = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : 'all';
        $selected_fee    = isset($_GET['event_fee']) ? sanitize_text_field($_GET['event_fee']) : 'all';
        $start_default_shamsi = sc_date_shamsi_date_only(date('Y-m-d', strtotime('-10 days')));
        $end_default_shamsi   = sc_date_shamsi_date_only(date('Y-m-d', strtotime('+10 days')));

        $filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) 
        ? sanitize_text_field($_GET['filter_date_from_shamsi']) 
        : $start_default_shamsi;

        $filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) 
        ? sanitize_text_field($_GET['filter_date_to_shamsi']) 
        : $end_default_shamsi;

        ?>
        <div class="alignleft actions">
            <!-- وضعیت -->
            <label for="filter-event-status" class="screen-reader-text">فیلتر بر اساس وضعیت</label>
            <select name="event_status" id="filter-event-status">
                <option value="all" <?php selected($selected_status, 'all'); ?>>همه وضعیت‌ها</option>
                <option value="active" <?php selected($selected_status, 'active'); ?>>فعال</option>
                <option value="inactive" <?php selected($selected_status, 'inactive'); ?>>غیرفعال</option>
            </select>

            <!-- نوع رویداد -->
            <label for="filter-event-type" class="screen-reader-text">فیلتر نوع</label>
            <select name="event_type" id="filter-event-type">
                <option value="all" <?php selected($selected_type, 'all'); ?>>همه نوع ها</option>
                <option value="event" <?php selected($selected_type, 'event'); ?>>رویداد</option>
                <option value="competition" <?php selected($selected_type, 'competition'); ?>>مسابقه</option>
            </select>

            <!-- رایگان یا همه -->
            <label for="filter-event-fee" class="screen-reader-text">فیلتر قیمت</label>
            <select name="event_fee" id="filter-event-fee">
                <option value="all" <?php selected($selected_fee, 'all'); ?>>همه قیمت ها</option>
                <option value="free" <?php selected($selected_fee, 'free'); ?>>رایگان</option>
            </select>

            <!-- تاریخ برگزاری -->
            <label class="screen-reader-text">بازه تاریخ برگزاری</label>
            <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" class="persian-date-input" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" placeholder="از" style=" width: 15%;">
            <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" class="persian-date-input" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" placeholder="تا" style=" width: 15%;">

            <!-- hidden inputs میلادی -->
            <input type="hidden" name="filter_date_from" id="filter_date_from">
            <input type="hidden" name="filter_date_to" id="filter_date_to">

            <?php submit_button('فیلتر', 'secondary', 'filter_action', false); ?>
        </div>

        <?php
    }
}





    public function no_items() {
        if (isset($_GET['s'])) {
            echo "رویدادی با این مشخصات یافت نشد!";
        } elseif (isset($_GET['event_status']) && $_GET['event_status'] == 'trash') {
            echo "هیچ رویدادی در زباله‌دان نیست.";
        } else {
            echo "هنوز رویدادی ثبت نکرده‌اید. از بخش ثبت رویداد اولین رویداد خود را اضافه کنید.";
        }
    }

    public function get_sortable_columns() {
        return [
            'name' => ['name', true],
            'price' => ['price', true],
            'is_active' => ['is_active', true],
        ];
    }

    public function get_bulk_actions() {
        $actions = [];
        if (isset($_GET['event_status']) && $_GET['event_status'] == 'trash') {
            $actions['restore'] = 'بازیابی';
            $actions['delete_permanent'] = 'حذف دائمی';
        } else {
            $actions['activate'] = 'فعال کردن';
            $actions['deactivate'] = 'غیرفعال کردن';
            $actions['trash'] = 'حذف به زباله‌دان';
        }
        return $actions;
    }
    protected function get_primary_column_name(){
        return 'name';
    }
    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_events';

        // حذف به زباله‌دان (تک)
        if ($this->current_action() == 'trash' && isset($_GET['event_id'])) {
            check_admin_referer('trash_event_' . $_GET['event_id']);
            $event_id = absint($_GET['event_id']);
            $wpdb->update(
                $table_name,
                ['deleted_at' => current_time('mysql')],
                ['id' => $event_id]
            );
            wp_redirect(admin_url('admin.php?page=sc-events&sc_status=event_deleted'));
            exit;
        }

        // بازیابی (تک)
        if ($this->current_action() == 'restore' && isset($_GET['event_id'])) {
            check_admin_referer('restore_event_' . $_GET['event_id']);
            $event_id = absint($_GET['event_id']);
            $wpdb->update(
                $table_name,
                ['deleted_at' => NULL],
                ['id' => $event_id]
            );
            wp_redirect(admin_url('admin.php?page=sc-events&sc_status=event_restored'));
            exit;
        }

        // حذف دائمی (تک)
        if ($this->current_action() == 'delete_permanent' && isset($_GET['event_id'])) {
            check_admin_referer('delete_permanent_event_' . $_GET['event_id']);
            $event_id = absint($_GET['event_id']);
            $wpdb->delete($table_name, ['id' => $event_id]);
            wp_redirect(admin_url('admin.php?page=sc-events&sc_status=event_deleted'));
            exit;
        }

        // عملیات دسته‌ای
        if ($this->current_action() == 'trash') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $events = isset($_GET['event']) ? $_GET['event'] : [];
            foreach ($events as $event_id) {
                $wpdb->update(
                    $table_name,
                    ['deleted_at' => current_time('mysql')],
                    ['id' => absint($event_id)]
                );
            }
            wp_redirect(admin_url('admin.php?page=sc-events&sc_status=event_bulk_deleted'));
            exit;
        }

        if ($this->current_action() == 'restore') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $events = isset($_GET['event']) ? $_GET['event'] : [];
            foreach ($events as $event_id) {
                $wpdb->update(
                    $table_name,
                    ['deleted_at' => NULL],
                    ['id' => absint($event_id)]
                );
            }
            wp_redirect(admin_url('admin.php?page=sc-events&sc_status=event_restored'));
            exit;
        }

        if ($this->current_action() == 'delete_permanent') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $events = isset($_GET['event']) ? $_GET['event'] : [];
            foreach ($events as $event_id) {
                $wpdb->delete($table_name, ['id' => absint($event_id)]);
            }
            wp_redirect(admin_url('admin.php?page=sc-events&sc_status=event_bulk_deleted'));
            exit;
        }

        // فعال کردن رویدادها (دسته‌ای)
        if ($this->current_action() == 'activate') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $events = isset($_GET['event']) ? $_GET['event'] : [];
            if (!empty($events)) {
                foreach ($events as $event_id) {
                    $wpdb->update(
                        $table_name,
                        ['is_active' => 1, 'updated_at' => current_time('mysql')],
                        ['id' => absint($event_id), 'deleted_at' => NULL]
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-events&sc_status=events_activated'));
                exit;
            }
        }

        // غیرفعال کردن رویدادها (دسته‌ای)
        if ($this->current_action() == 'deactivate') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $events = isset($_GET['event']) ? $_GET['event'] : [];
            if (!empty($events)) {
                foreach ($events as $event_id) {
                    $wpdb->update(
                        $table_name,
                        ['is_active' => 0, 'updated_at' => current_time('mysql')],
                        ['id' => absint($event_id), 'deleted_at' => NULL]
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-events&sc_status=events_deactivated'));
                exit;
            }
        }
    }

    protected function view_create($key, $label, $url, $count = 0, $is_current = false) {
        $class_view = $is_current ? 'current' : '';
        if (isset($_GET['s'])) {
            $url .= "&s=" . sanitize_text_field($_GET['s']);
        }
        $view = sprintf("<a href='%s' class='%s'>%s</a>", $url, $class_view, $label);
        
            $view .= sprintf("<span class='count'>(%d)</span>", $count);
        
        return $view;
    }

    public function get_views() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_events';
    
    $event_status = isset($_GET['event_status']) ? sanitize_text_field($_GET['event_status']) : 'all';
    
    $count_all = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL");
    $count_active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL AND is_active = 1");
    $count_inactive = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL AND is_active = 0");
    $count_trash = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NOT NULL");

    $views = [
        'all' => $this->view_create('all', 'همه', admin_url('admin.php?page=sc-events&event_status=all'), $count_all, $event_status === 'all'),
        'active' => $this->view_create('active', 'فعال', admin_url('admin.php?page=sc-events&event_status=active'), $count_active, $event_status === 'active'),
        'inactive' => $this->view_create('inactive', 'غیرفعال', admin_url('admin.php?page=sc-events&event_status=inactive'), $count_inactive, $event_status === 'inactive'),
        'trash' => $this->view_create('trash', 'زباله‌دان', admin_url('admin.php?page=sc-events&event_status=trash'), $count_trash, $event_status === 'trash'),
    ];

    return $views;
}


    public function prepare_items() {
        $this->process_bulk_action();

        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_events';

        $per_page = $this->get_items_per_page('events_per_page', 10);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $order_clause = "ORDER BY $orderby $order";

        $where = "1=1";
        $event_status = isset($_GET['event_status']) ? sanitize_text_field($_GET['event_status']) : 'all';

        // فیلتر زباله‌دان
        if ($event_status == 'trash') {
            $where .= " AND deleted_at IS NOT NULL";
        } else {
            $where .= " AND deleted_at IS NULL";
            
            // فیلتر فعال/غیرفعال
            if ($event_status == 'active') {
                $where .= " AND is_active = 1";
            } elseif ($event_status == 'inactive') {
                $where .= " AND is_active = 0";
            }
        }

        // جستجو
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(" AND (name LIKE %s OR description LIKE %s)", $search, $search);
        }

        // فیلتر نوع
        if (isset($_GET['event_type']) && $_GET['event_type'] != 'all') {
            $event_type = sanitize_text_field($_GET['event_type']);
            $where .= $wpdb->prepare(" AND event_type = %s", $event_type);
        }

        
        $filter_type = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : 'all';
        $filter_fee  = isset($_GET['event_fee']) ? sanitize_text_field($_GET['event_fee']) : 'all';
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to   = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';

        // فیلتر نوع
        $filter_type = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : 'all';
        if($filter_type !== 'all'){
            $where .= $wpdb->prepare(" AND event_type = %s", $filter_type);
        }

        if($filter_fee === 'free'){
            $where .= " AND price = 0";
        }

        if($filter_date_from){
            $where .= $wpdb->prepare(" AND holding_date_gregorian >= %s", $filter_date_from);
        }

        if($filter_date_to){
            $where .= $wpdb->prepare(" AND holding_date_gregorian <= %s", $filter_date_to);
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

<div class="wrap">
    <div class="event_header_list">
        <h1 class="wp-heading-inline">لیست رویداد / مسابقه</h1>
        <a href="<?php echo admin_url('admin.php?page=sc-add-event'); ?>" class="page-title-action">افزودن رویداد جدید</a>
        <p class="descriotion">فیلتر تاریخ بر اساس تاریخ برگزاری می باشد.</p>
    </div>
    <?php
    // نمایش پیام‌های موفقیت/خطا
    if (isset($_GET['sc_status'])) {
        $status = sanitize_text_field($_GET['sc_status']);
        switch ($status) {
            case 'event_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>رویداد با موفقیت حذف شد.</p></div>';
                break;
            case 'event_restored':
                echo '<div class="notice notice-success is-dismissible"><p>رویداد با موفقیت بازیابی شد.</p></div>';
                break;
            case 'event_bulk_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>رویدادهای انتخاب شده با موفقیت حذف شدند.</p></div>';
                break;
            case 'events_activated':
                echo '<div class="notice notice-success is-dismissible"><p>رویدادهای انتخاب شده با موفقیت فعال شدند.</p></div>';
                break;
            case 'events_deactivated':
                echo '<div class="notice notice-success is-dismissible"><p>رویدادهای انتخاب شده با موفقیت غیرفعال شدند.</p></div>';
                break;
        }
    }
    
    $events_list_table = new Events_List_Table();
    $events_list_table->prepare_items();
    
    ?>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
        <?php $events_list_table->search_box('جستجو', 'search_id'); ?>
    </form>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
        <?php
        if (isset($_GET['event_status'])) {
            echo '<input type="hidden" name="event_status" value="' . esc_attr($_GET['event_status']) . '">';
        }
        if (isset($_GET['s'])) {
            echo '<input type="hidden" name="s" value="' . esc_attr($_GET['s']) . '">';
        }
        $events_list_table->views();
        $events_list_table->display();
        ?>
    </form>
</div>

