<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Courses_List_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'row' => 'ردیف',
            'id' => 'شناسه',
            'title' => 'عنوان دوره',
            'price' => 'قیمت',
            'capacity' => 'ظرفیت',
            'sessions_count' => 'تعداد جلسات',
            'enrolled' => 'ثبت‌نام شده',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
            'is_active' => 'وضعیت'
        ];
    }

    public function column_row($item) {
        static $row_number = 0;
        $page = $this->get_pagenum();
        $per_page = 10;
        $row_number++;
        return (($page - 1) * $per_page) + $row_number;
    }

    public function column_title($item) {
        $title = $item['title'];
        $actions = [];
        
        if ($item['deleted_at']) {
            // دوره در زباله‌دان است
            $actions['restore'] = '<a href="' . admin_url('admin.php?page=sc-courses&action=restore&course_id=') . $item['id'] . '">بازیابی</a>';
            $actions['delete'] = '<a href="' . admin_url('admin.php?page=sc-courses&action=delete_permanent&course_id=') . $item['id'] . '" onclick="return confirm(\'آیا مطمئن هستید؟ این عمل قابل بازگشت نیست.\')">حذف دائمی</a>';
        } else {
            // دوره فعال است
            $actions['edit'] = '<a href="' . admin_url('admin.php?page=sc-add-course&course_id=') . $item['id'] . '">ویرایش</a>';
            $actions['view_users'] = sprintf(
                '<p class="view-course-users" data-id="%s" style="cursor: pointer; display: inline; color: #2271b1; text-decoration: none;">مشاهده کاربران</p>',
                $item['id']
            );
            $actions['trash'] = '<a href="' . admin_url('admin.php?page=sc-courses&action=trash&course_id=') . $item['id'] . '">حذف</a>';
        }

        return $title . ' ' . $this->row_actions($actions);
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . $item['id'] . '" name="course[]" />';
    }
    protected function get_primary_column_name() {
    return 'title';
    }
    public function column_enrolled($item) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_member_courses';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE course_id = %d AND status = 'active'",
            $item['id']
        ));
        return $count ? $count : '0';
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'price':
                // استفاده از فرمت WooCommerce اگر فعال باشد، در غیر این صورت فرمت فارسی
                if (function_exists('wc_price')) {
                    return wc_price($item['price']);
                } else {
                    // فرمت فارسی: سه رقم سه رقم با جداکننده کاما
                    return number_format($item['price'], 0, '.', ',') . ' تومان';
                }
            case 'capacity':
                return $item['capacity'] ? $item['capacity'] : 'نامحدود';
            case 'sessions_count':
                return $item['sessions_count'] ? $item['sessions_count'] : '-';
            case 'start_date':
                if (empty($item['start_date'])) {
                    return '-';
                }
                // تبدیل تاریخ میلادی به شمسی
                return sc_date_shamsi_date_only($item['start_date']);
            case 'end_date':
                if (empty($item['end_date'])) {
                    return '-';
                }
                // تبدیل تاریخ میلادی به شمسی
                return sc_date_shamsi_date_only($item['end_date']);
            case 'is_active':
                return $item['is_active'] ? 'فعال' : 'غیرفعال';
            default:
                return "-";
        }
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    public function no_items() {
        if (isset($_GET['s'])) {
            echo "دوره‌ای با این مشخصات یافت نشد!";
        } elseif (isset($_GET['course_status']) && $_GET['course_status'] == 'trash') {
            echo "هیچ دوره‌ای در زباله‌دان نیست.";
        } else {
            echo "هنوز دوره‌ای ثبت نکرده‌اید. از بخش افزودن دوره اولین دوره خود را اضافه کنید.";
        }
    }

    public function get_sortable_columns() {
        return [
            'title' => ['title', true],
            'price' => ['price', true],
            'start_date' => ['start_date', true],
            'is_active' => ['is_active', true],
        ];
    }

    public function get_bulk_actions() {
        $actions = [];
        if (isset($_GET['course_status']) && $_GET['course_status'] == 'trash') {
            $actions['restore'] = 'بازیابی';
            $actions['delete_permanent'] = 'حذف دائمی';
        } else {
            $actions['activate'] = 'فعال کردن';
            $actions['deactivate'] = 'غیرفعال کردن';
            $actions['trash'] = 'حذف به زباله‌دان';
        }
        return $actions;
    }

    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_courses';

        // حذف به زباله‌دان (تک)
        if ($this->current_action() == 'trash' && isset($_GET['course_id'])) {
            $course_id = absint($_GET['course_id']);
            $wpdb->update(
                $table_name,
                ['deleted_at' => current_time('mysql')],
                ['id' => $course_id]
            );
            wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=course_deleted'));
            exit;
        }

        // بازیابی (تک)
        if ($this->current_action() == 'restore' && isset($_GET['course_id'])) {
            $course_id = absint($_GET['course_id']);
            $wpdb->update(
                $table_name,
                ['deleted_at' => NULL],
                ['id' => $course_id]
            );
            wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=course_restored'));
            exit;
        }

        // حذف دائمی (تک)
        if ($this->current_action() == 'delete_permanent' && isset($_GET['course_id'])) {
            $course_id = absint($_GET['course_id']);
            $wpdb->delete($table_name, ['id' => $course_id]);
            wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=course_deleted'));
            exit;
        }

        // عملیات دسته‌ای
        if ($this->current_action() == 'trash') {
            $courses = isset($_GET['course']) ? $_GET['course'] : [];
            foreach ($courses as $course_id) {
                $wpdb->update(
                    $table_name,
                    ['deleted_at' => current_time('mysql')],
                    ['id' => absint($course_id)]
                );
            }
            wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=course_bulk_deleted'));
            exit;
        }

        if ($this->current_action() == 'restore') {
            $courses = isset($_GET['course']) ? $_GET['course'] : [];
            foreach ($courses as $course_id) {
                $wpdb->update(
                    $table_name,
                    ['deleted_at' => NULL],
                    ['id' => absint($course_id)]
                );
            }
            wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=course_restored'));
            exit;
        }

        if ($this->current_action() == 'delete_permanent') {
            $courses = isset($_GET['course']) ? $_GET['course'] : [];
            foreach ($courses as $course_id) {
                $wpdb->delete($table_name, ['id' => absint($course_id)]);
            }
            wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=course_bulk_deleted'));
            exit;
        }

        // فعال کردن دوره‌ها (دسته‌ای)
        if ($this->current_action() == 'activate') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $courses = isset($_GET['course']) ? $_GET['course'] : [];
            if (!empty($courses)) {
                foreach ($courses as $course_id) {
                    $wpdb->update(
                        $table_name,
                        ['is_active' => 1, 'updated_at' => current_time('mysql')],
                        ['id' => absint($course_id), 'deleted_at' => NULL]
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=courses_activated'));
                exit;
            }
        }

        // غیرفعال کردن دوره‌ها (دسته‌ای)
        if ($this->current_action() == 'deactivate') {
            check_admin_referer('bulk-' . $this->_args['plural']);
            $courses = isset($_GET['course']) ? $_GET['course'] : [];
            if (!empty($courses)) {
                foreach ($courses as $course_id) {
                    $wpdb->update(
                        $table_name,
                        ['is_active' => 0, 'updated_at' => current_time('mysql')],
                        ['id' => absint($course_id), 'deleted_at' => NULL]
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-courses&sc_status=courses_deactivated'));
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
        if ($count) {
            $view .= sprintf("<span class='count'>(%d)</span>", $count);
        }
        return $view;
    }

    public function get_views() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_courses';
        
        // دریافت فیلتر فعال
        $course_status = isset($_GET['course_status']) ? sanitize_text_field($_GET['course_status']) : 'all';
        
        $count_all = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL");
        $count_active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL AND is_active = 1");
        $count_inactive = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NULL AND is_active = 0");
        $count_trash = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE deleted_at IS NOT NULL");

        $views = [
            'all' => $this->view_create(
                'all',
                'همه',
                admin_url('admin.php?page=sc-courses&course_status=all'),
                $count_all,
                $course_status === 'all'
            )
        ];
        
        // نمایش تب فعال فقط در صورت وجود آیتم
        if ($count_active > 0) {
            $views['active'] = $this->view_create(
                'active',
                'فعال',
                admin_url('admin.php?page=sc-courses&course_status=active'),
                $count_active,
                $course_status === 'active'
            );
        }
        
        // نمایش تب غیرفعال فقط در صورت وجود آیتم
        if ($count_inactive > 0) {
            $views['inactive'] = $this->view_create(
                'inactive',
                'غیرفعال',
                admin_url('admin.php?page=sc-courses&course_status=inactive'),
                $count_inactive,
                $course_status === 'inactive'
            );
        }
        
        // نمایش تب زباله‌دان فقط در صورت وجود آیتم
        if ($count_trash > 0) {
            $views['trash'] = $this->view_create(
                'trash',
                'زباله‌دان',
                admin_url('admin.php?page=sc-courses&course_status=trash'),
                $count_trash,
                $course_status === 'trash'
            );
        }
        
        return $views;
    }

    public function prepare_items() {
        $this->process_bulk_action();

        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_courses';

        $per_page = 10;
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $order_clause = "ORDER BY $orderby $order";

        $where = "1=1";
        $course_status = isset($_GET['course_status']) ? sanitize_text_field($_GET['course_status']) : 'all';

        // فیلتر زباله‌دان
        if ($course_status == 'trash') {
            $where .= " AND deleted_at IS NOT NULL";
        } else {
            $where .= " AND deleted_at IS NULL";
            
            // فیلتر فعال/غیرفعال
            if ($course_status == 'active') {
                $where .= " AND is_active = 1";
            } elseif ($course_status == 'inactive') {
                $where .= " AND is_active = 0";
            }
        }

        // جستجو
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(" AND (title LIKE %s OR description LIKE %s)", $search, $search);
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

