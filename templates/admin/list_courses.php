<?php
if ( ! defined('ABSPATH') ) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Courses_List_Table extends WP_List_Table {

    /** @var array<int, array<int, object>> */
    protected $packages_by_course = [];

    /**
     * اعداد لاتین → فارسی (برای نمایش جلسات و قیمت در لیست)
     */
    protected function sc_list_digits_to_persian($str) {
        return strtr((string) $str, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    /**
     * مبلغ با دو رقم اعشار، جداکننده هزارگان نقطه، ارقام فارسی (مثل ۲.۵۰۰.۰۰)
     */
    protected function sc_list_format_price_toman_persian($amount) {
        $en = number_format((float) $amount, 2, '.', '.');
        return $this->sc_list_digits_to_persian($en);
    }

    public function get_columns() {
        return [
           // 'row' => 'ردیف',
            'cb' => '<input type="checkbox" />',
            'title' => 'عنوان دوره',
            'chapter' => 'شعبه',
            'course_type' => 'نوع کلاس',
            'id' => 'شناسه',
            'price' => 'قیمت',
            'capacity' => 'ظرفیت',
            'sessions_count' => 'تعداد جلسات',
            'enrolled' => 'ثبت‌نام شده',
            'active_players' => 'تعداد بازیکنان فعال',
            'remaining_capacity' => 'ظرفیت باقی‌مانده',
            'start_date' => 'تاریخ شروع',
            'end_date' => 'تاریخ پایان',
            'is_active' => 'وضعیت'
        ];
    }

    // public function column_row($item) {
    //     static $row_number = 0;
    //     $page = $this->get_pagenum();
    //     $per_page = 10;
    //     $row_number++;
    //     return (($page - 1) * $per_page) + $row_number;
    // }

    public function column_title($item) {
        $title = $item['title'];
        $chapter = isset($item['chapter']) ? trim((string) $item['chapter']) : '';
        $type = isset($item['course_type']) ? (string) $item['course_type'] : 'group';
        $type_label = $type === 'private' ? 'خصوصی' : 'گروهی';

        $course_image = isset($item['image']) ? trim((string) $item['image']) : '';
        $avatar = function_exists('sc_render_course_avatar_html')
            ? sc_render_course_avatar_html($title, $course_image)
            : '<span class="sc-course-avatar sc-course-avatar--initials" aria-hidden="true">' . esc_html($title !== '' ? mb_substr($title, 0, 1) : 'د') . '</span>';

        $meta_parts = [];
        if ($chapter !== '') {
            $meta_parts[] = '<span class="sc-member-meta-item">' . esc_html($chapter) . '</span>';
        }
        $meta_parts[] = '<span class="sc-member-meta-item">' . esc_html($type_label) . '</span>';
        $meta_html = '<span class="sc-member-meta">' . implode('<span class="sc-member-meta-dot"></span>', $meta_parts) . '</span>';

        $actions = [];
        if ($item['deleted_at']) {
            $actions['restore'] = '<a href="' . admin_url('admin.php?page=sc-courses&action=restore&course_id=') . $item['id'] . '">بازیابی</a>';
            $actions['delete'] = '<a href="' . admin_url('admin.php?page=sc-courses&action=delete_permanent&course_id=') . $item['id'] . '" onclick="return scConfirmInline(event, { type: \'warning\', message: \'آیا مطمئن هستید؟ این عمل قابل بازگشت نیست.\' })">حذف دائمی</a>';
        } else {
            $actions['edit'] = '<a href="' . admin_url('admin.php?page=sc-add-course&course_id=') . $item['id'] . '">ویرایش</a>';
            $actions['view_users'] = sprintf(
                '<a href="#" class="view-course-users" data-id="%s">مشاهده کاربران</a>',
                esc_attr($item['id'])
            );
            $actions['trash'] = '<a href="' . admin_url('admin.php?page=sc-courses&action=trash&course_id=') . $item['id'] . '">حذف</a>';
        }

        $title_block = '<span class="sc-member-identity">'
            . $avatar
            . '<span class="sc-member-identity-text">'
            . '<span class="sc-member-name">' . esc_html($title) . '</span>'
            . $meta_html
            . '</span>'
            . '</span>';

        return $title_block . $this->row_actions($actions);
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
            "SELECT COUNT(*)
             FROM $table_name
             WHERE course_id = %d
               AND status = 'active'
               AND (
                   course_status_flags IS NULL
                   OR TRIM(course_status_flags) = ''
               )",
            $item['id']
        ));
        return $count ? $count : '0';
    }

    public function column_active_players($item) {
        global $wpdb;
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $members_table = $wpdb->prefix . 'sc_members';

        // دقیقاً مطابق کوئری «مشاهده کاربران» در همین صفحه
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id
             FROM $member_courses_table mc
             INNER JOIN $members_table m ON mc.member_id = m.id
             WHERE mc.course_id = %d
               AND mc.status = 'active'
               AND (
                   mc.course_status_flags IS NULL
                   OR TRIM(mc.course_status_flags) = ''
               )
             ORDER BY m.last_name ASC, m.first_name ASC",
            $item['id']
        ), ARRAY_A);

        return is_array($users) ? (string) count($users) : '0';
    }

    public function column_remaining_capacity($item) {
        $capacity = isset($item['capacity']) ? (int) $item['capacity'] : 0;
        if ($capacity <= 0) {
            return 'نامحدود';
        }

        $enrolled = (int) $this->column_enrolled($item);
        $remaining = $capacity - $enrolled;
        if ($remaining < 0) {
            $remaining = 0;
        }
        return $this->sc_list_digits_to_persian((string) $remaining);
    }

    public function column_chapter($item) {
        $ch = isset($item['chapter']) ? trim((string) $item['chapter']) : '';
        return $ch !== '' ? esc_html($ch) : '—';
    }

    public function column_course_type($item) {
        $t = isset($item['course_type']) ? (string) $item['course_type'] : 'group';
        if ($t === 'private') {
            return '<span class="sc-badge sc-badge--purple">خصوصی</span>';
        }
        return '<span class="sc-badge sc-badge--soft">گروهی</span>';
    }

    public function column_price($item) {
        $cid = isset($item['id']) ? (int) $item['id'] : 0;
        if ($cid && !empty($this->packages_by_course[$cid])) {
            $prices = [];
            foreach ($this->packages_by_course[$cid] as $p) {
                $prices[] = floatval($p->price);
            }
            if (!empty($prices)) {
                $min = min($prices);
                $max = max($prices);
                return 'از ' . $this->sc_list_format_price_toman_persian($min) . ' تومان تا ' . $this->sc_list_format_price_toman_persian($max) . ' تومان';
            }
        }
        if (function_exists('wc_price')) {
            return wc_price($item['price']);
        }
        return number_format((float) $item['price'], 0, '.', ',') . ' تومان';
    }

    public function column_sessions_count($item) {
        $cid = isset($item['id']) ? (int) $item['id'] : 0;
        if ($cid && !empty($this->packages_by_course[$cid])) {
            $sessions = [];
            foreach ($this->packages_by_course[$cid] as $p) {
                $sessions[] = (int) $p->sessions_count;
            }
            $sessions = array_values(array_unique(array_filter($sessions)));
            sort($sessions, SORT_NUMERIC);
            if (!empty($sessions)) {
                $parts = array_map(function ($n) {
                    return $this->sc_list_digits_to_persian((string) $n);
                }, $sessions);
                return implode(' - ', $parts);
            }
        }
        $n = isset($item['sessions_count']) ? (int) $item['sessions_count'] : 0;
        return $n > 0 ? $this->sc_list_digits_to_persian((string) $n) : '—';
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'capacity':
                return $item['capacity'] ? $this->sc_list_digits_to_persian((string) (int) $item['capacity']) : 'نامحدود';
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
                if (!empty($item['deleted_at'])) {
                    return '<span class="sc-badge sc-badge--muted">زباله‌دان</span>';
                }
                return !empty($item['is_active'])
                    ? '<span class="sc-badge sc-badge--success">فعال</span>'
                    : '<span class="sc-badge sc-badge--muted">غیرفعال</span>';
            default:
                return "-";
        }
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    public function no_items() {
        $narrow = false;
        if (!empty($_GET['filter_chapter'])) {
            $narrow = true;
        }
        if (!empty($_GET['filter_course_type']) && sanitize_text_field(wp_unslash($_GET['filter_course_type'])) !== 'all') {
            $narrow = true;
        }
        if (!empty($_GET['filter_capacity_status']) && sanitize_text_field(wp_unslash($_GET['filter_capacity_status'])) !== 'all') {
            $narrow = true;
        }

        if (isset($_GET['s']) && $_GET['s'] !== '') {
            echo 'دوره‌ای با این مشخصات یافت نشد!';
        } elseif ($narrow) {
            echo 'دوره‌ای با این فیلترها یافت نشد.';
        } elseif (isset($_GET['course_status']) && $_GET['course_status'] === 'trash') {
            echo 'هیچ دوره‌ای در زباله‌دان نیست.';
        } elseif (isset($_GET['course_status']) && ($_GET['course_status'] === 'active' || $_GET['course_status'] === 'inactive')) {
            echo 'دوره‌ای در این وضعیت یافت نشد.';
        } else {
            echo 'هنوز دوره‌ای ثبت نکرده‌اید. از بخش افزودن دوره اولین دوره خود را اضافه کنید.';
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
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM $table_name WHERE id = %d", $course_id), ARRAY_A);
            if (function_exists('sc_delete_course_weekly_schedule')) {
                sc_delete_course_weekly_schedule($course_id);
            }
            $wpdb->delete($table_name, ['id' => $course_id]);
            if (function_exists('sc_log_activity') && $row) {
                sc_log_activity('deleted', 'course', $course_id, 'دوره «' . ($row['title'] ?? '') . '» حذف شد', $row, null);
            }
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
                $course_id = absint($course_id);
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, title FROM $table_name WHERE id = %d", $course_id), ARRAY_A);
                if (function_exists('sc_delete_course_weekly_schedule')) {
                    sc_delete_course_weekly_schedule($course_id);
                }
                $wpdb->delete($table_name, ['id' => $course_id]);
                if (function_exists('sc_log_activity') && $row) {
                    sc_log_activity('deleted', 'course', $course_id, 'دوره «' . ($row['title'] ?? '') . '» حذف شد', $row, null);
                }
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
        if (isset($_GET['s']) && $_GET['s'] !== '') {
            $url .= '&s=' . rawurlencode(sanitize_text_field(wp_unslash($_GET['s'])));
        }
        foreach (['filter_chapter', 'filter_course_type', 'filter_capacity_status'] as $fk) {
            if (!empty($_GET[$fk]) && (string) $_GET[$fk] !== 'all') {
                $url .= '&' . rawurlencode($fk) . '=' . rawurlencode(sanitize_text_field(wp_unslash($_GET[$fk])));
            }
        }
        return sprintf(
            "<a href='%s' class='%s'>%s <span class='count'>(%d)</span></a>",
            esc_url($url),
            esc_attr($class_view),
            esc_html($label),
            (int) $count
        );
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
        
            $views['active'] = $this->view_create(
                'active',
                'فعال',
                admin_url('admin.php?page=sc-courses&course_status=active'),
                $count_active,
                $course_status === 'active'
            );
        
        
        // نمایش تب غیرفعال فقط در صورت وجود آیتم
       
            $views['inactive'] = $this->view_create(
                'inactive',
                'غیرفعال',
                admin_url('admin.php?page=sc-courses&course_status=inactive'),
                $count_inactive,
                $course_status === 'inactive'
            );
        
    
        // نمایش تب زباله‌دان فقط در صورت وجود آیتم
      
            $views['trash'] = $this->view_create(
                'trash',
                'زباله‌دان',
                admin_url('admin.php?page=sc-courses&course_status=trash'),
                $count_trash,
                $course_status === 'trash'
            );
        
        
        return $views;
    }

    public function prepare_items() {
        $this->process_bulk_action();

        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_courses';
        $member_courses = $wpdb->prefix . 'sc_member_courses';

        $this->packages_by_course = [];

        $per_page = $this->get_items_per_page('courses_per_page', 10);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'created_at';
        $allowed_orderby = ['created_at', 'title', 'price', 'start_date', 'is_active', 'id', 'chapter', 'course_type'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_at';
        }
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $order = $order === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = 'ORDER BY c.' . $orderby . ' ' . $order;

        $where = '1=1';
        $course_status = isset($_GET['course_status']) ? sanitize_text_field(wp_unslash($_GET['course_status'])) : 'all';

        if ($course_status === 'trash') {
            $where .= ' AND c.deleted_at IS NOT NULL';
        } else {
            $where .= ' AND c.deleted_at IS NULL';

            if ($course_status === 'active') {
                $where .= ' AND c.is_active = 1';
            } elseif ($course_status === 'inactive') {
                $where .= ' AND c.is_active = 0';
            }
        }

        if (isset($_GET['s']) && $_GET['s'] !== '') {
            $search = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_GET['s']))) . '%';
            $where .= $wpdb->prepare(' AND (c.title LIKE %s OR c.description LIKE %s)', $search, $search);
        }

        $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';
        if ($filter_chapter !== '') {
            $where .= $wpdb->prepare(' AND c.chapter = %s', $filter_chapter);
        }

        $filter_course_type = isset($_GET['filter_course_type']) ? sanitize_text_field(wp_unslash($_GET['filter_course_type'])) : 'all';
        if ($filter_course_type === 'group' || $filter_course_type === 'private') {
            $where .= $wpdb->prepare(' AND c.course_type = %s', $filter_course_type);
        }

        $filter_capacity_status = isset($_GET['filter_capacity_status']) ? sanitize_text_field(wp_unslash($_GET['filter_capacity_status'])) : 'all';
        if ($filter_capacity_status === 'full') {
            $where .= " AND c.capacity > 0 AND (
                SELECT COUNT(*) FROM `$member_courses` mc
                WHERE mc.course_id = c.id AND mc.status = 'active'
                AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
            ) >= c.capacity";
        } elseif ($filter_capacity_status === 'available') {
            $where .= " AND (c.capacity IS NULL OR c.capacity <= 0 OR (
                SELECT COUNT(*) FROM `$member_courses` mc
                WHERE mc.course_id = c.id AND mc.status = 'active'
                AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
            ) < c.capacity)";
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS c.* FROM `$table_name` c WHERE $where $order_clause LIMIT %d OFFSET %d";
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $per_page, $offset),
            ARRAY_A
        );

        $this->set_pagination_args([
            'total_items' => (int) $wpdb->get_var('SELECT FOUND_ROWS()'),
            'per_page' => $per_page,
        ]);

        if (is_array($results)) {
            foreach ($results as $row) {
                $cid = isset($row['id']) ? (int) $row['id'] : 0;
                if ($cid && function_exists('sc_course_has_packages') && sc_course_has_packages($cid)) {
                    $this->packages_by_course[$cid] = sc_get_course_packages($cid);
                }
            }
        }

        $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
        $this->items = is_array($results) ? $results : [];
    }

    public function extra_tablenav($which) {
    }
}



