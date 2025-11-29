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
            'profile_completed' => 'تکمیل پروفایل',
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
        
        // نمایش دوره‌های بازیکن
        global $wpdb;
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $courses_table = $wpdb->prefix . 'sc_courses';
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d AND mc.status = 'active' AND c.deleted_at IS NULL 
             LIMIT 3",
            $item['id']
        ));
        
        $course_names = [];
        if ($courses) {
            foreach ($courses as $course) {
                $course_names[] = $course->title;
            }
        }
        $courses_text = !empty($course_names) ? '<br><small style="color: #666;">دوره‌ها: ' . implode(', ', $course_names) . '</small>' : '';

        $actions = [
            'edit' => '<a href="' . admin_url('admin.php?page=sc-add-member&player_id=') . $item['id'] . '">ویرایش</a>',
            'delete' => '<a href="' . admin_url('admin.php?page=sc-members&action=delete&player_id=') . $item['id'] . '">حذف</a>',
            'view' => sprintf(
            '<p class="view-player" data-id="%s" style="cursor: pointer; display: inline; color: #2271b1; text-decoration: none;">مشاهده اطلاعات</p>',
            $item['id']
        )
        ];

        return $full_name . $courses_text . ' ' . $this->row_actions($actions);
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
            case 'profile_completed':
                // بررسی و به‌روزرسانی وضعیت تکمیل پروفایل
                $is_completed = sc_check_profile_completed($item['id']);
                // به‌روزرسانی در دیتابیس اگر تغییر کرده باشد
                $current_status = isset($item['profile_completed']) ? (int)$item['profile_completed'] : 0;
                if ($current_status != (int)$is_completed) {
                    sc_update_profile_completed_status($item['id']);
                }
                return $is_completed ? '<span style="color: #00a32a; font-weight: bold;">✓ تکمیل شده</span>' : '<span style="color: #d63638; font-weight: bold;">✗ ناقص</span>';
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
        } elseif (isset($_GET['player_status']) && $_GET['player_status'] == 'inactive') {
            echo "هیچ بازیکن غیرفعالی وجود ندارد.";
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
        $count_active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
        $count_inactive = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 0");

        $views = [
            'all' => $this->view_create(
                'all',
                'همه',
                admin_url('admin.php?page=sc-members&player_status=all'),
                $count_all
            ),
            'active' => $this->view_create(
                'active',
                'فعال',
                admin_url('admin.php?page=sc-members&player_status=active'),
                $count_active
            )
        ];
        
        // نمایش تب غیرفعال فقط در صورت وجود کاربر غیرفعال
        if ($count_inactive > 0) {
            $views['inactive'] = $this->view_create(
                'inactive',
                'غیرفعال',
                admin_url('admin.php?page=sc-members&player_status=inactive'),
                $count_inactive
            );
        }
        
        return $views;
    }
    
    public function extra_tablenav($which) {
        if ($which == 'top') {
            global $wpdb;
            $courses_table = $wpdb->prefix . 'sc_courses';
            $courses = $wpdb->get_results(
                "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
            );
            
            $selected_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
            $selected_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
            $selected_profile = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : 'all';
            
            echo '<div class="alignleft actions">';
            
            // فیلتر دوره
            if ($courses) {
                echo '<select name="filter_course" id="filter_course" style="margin-left: 5px;">';
                echo '<option value="0">همه دوره‌ها</option>';
                foreach ($courses as $course) {
                    $selected = ($selected_course == $course->id) ? 'selected' : '';
                    echo '<option value="' . esc_attr($course->id) . '" ' . $selected . '>' . esc_html($course->title) . '</option>';
                }
                echo '</select>';
            }
            
            // فیلتر وضعیت (active/inactive)
            echo '<select name="filter_status" id="filter_status" style="margin-left: 5px;">';
            echo '<option value="all"' . ($selected_status == 'all' ? ' selected' : '') . '>همه وضعیت‌ها</option>';
            echo '<option value="active"' . ($selected_status == 'active' ? ' selected' : '') . '>فعال</option>';
            echo '<option value="inactive"' . ($selected_status == 'inactive' ? ' selected' : '') . '>غیرفعال</option>';
            echo '</select>';
            
            // فیلتر تکمیل پروفایل
            echo '<select name="filter_profile" id="filter_profile" style="margin-left: 5px;">';
            echo '<option value="all"' . ($selected_profile == 'all' ? ' selected' : '') . '>همه پروفایل‌ها</option>';
            echo '<option value="completed"' . ($selected_profile == 'completed' ? ' selected' : '') . '>تکمیل شده</option>';
            echo '<option value="incomplete"' . ($selected_profile == 'incomplete' ? ' selected' : '') . '>ناقص</option>';
            echo '</select>';
            
            echo '<input type="submit" name="filter_action" id="doaction" class="button action" value="فیلتر" style="margin-left: 5px;">';
                echo '</div>';
        }
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
        
        // فیلتر وضعیت (از تب‌ها)
        if (isset($_GET['player_status']) && $_GET['player_status'] == 'active') {
            $where .= " AND is_active = 1";
        } elseif (isset($_GET['player_status']) && $_GET['player_status'] == 'inactive') {
            $where .= " AND is_active = 0";
        }
        
        // فیلتر وضعیت (از dropdown)
        if (isset($_GET['filter_status']) && $_GET['filter_status'] != 'all') {
            if ($_GET['filter_status'] == 'active') {
                $where .= " AND is_active = 1";
            } elseif ($_GET['filter_status'] == 'inactive') {
                $where .= " AND is_active = 0";
            }
        }
        
        // فیلتر تکمیل پروفایل
        if (isset($_GET['filter_profile']) && $_GET['filter_profile'] != 'all') {
            if ($_GET['filter_profile'] == 'completed') {
                $where .= " AND profile_completed = 1";
            } elseif ($_GET['filter_profile'] == 'incomplete') {
                $where .= " AND (profile_completed = 0 OR profile_completed IS NULL)";
            }
        }
        
        // فیلتر دوره
        if (isset($_GET['filter_course']) && !empty($_GET['filter_course'])) {
            $course_id = absint($_GET['filter_course']);
            $member_courses_table = $wpdb->prefix . 'sc_member_courses';
            $where .= $wpdb->prepare(
                " AND id IN (SELECT member_id FROM $member_courses_table WHERE course_id = %d AND status = 'active')",
                $course_id
            );
        }

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


