<?php
if ( ! defined('ABSPATH') ) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Coaches_List_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'full_name' => 'نام و نام خانوادگی',
            'id' => 'شناسه',
            'national_id' => 'کد ملی',
            'mobile_phone' => 'شماره موبایل',
            'specialization' => 'تخصص',
            'coaching_level' => 'سطح مربیگری',
            'courses_count' => 'تعداد دوره‌ها',
            'is_active' => 'وضعیت'
        ];
    }

    protected function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'full_name' => ['last_name', false],
            'national_id' => ['national_id', false],
            'is_active' => ['is_active', false]
        ];
    }

    protected function get_bulk_actions() {
        return [
            'activate' => 'فعال کردن',
            'deactivate' => 'غیرفعال کردن',
            'delete' => 'حذف'
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="coach[]" value="%s" />', $item->id);
    }

    public function column_full_name($item) {
        $edit_url = admin_url('admin.php?page=sc-add-coach&coach_id=' . $item->id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=sc-coaches&action=delete&coach=' . $item->id),
            'delete_coach_' . $item->id
        );
        
        $actions = [
            'edit' => '<a href="' . $edit_url . '">ویرایش</a>',
            'delete' => '<a href="' . $delete_url . '" onclick="return scConfirmInline(event, { type: \'warning\', message: \'آیا مطمئن هستید؟\' });">حذف</a>',
            'view' => '<a href="#" class="view-coach" data-id="' . $item->id . '">مشاهده</a>'
        ];
        
        return sprintf(
            '<strong><a href="%s">%s %s</a></strong> %s',
            $edit_url,
            esc_html($item->first_name),
            esc_html($item->last_name),
            $this->row_actions($actions)
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item->id;
            case 'national_id':
                return esc_html($item->national_id);
            case 'mobile_phone':
                return esc_html($item->mobile_phone ?: '-');
            case 'specialization':
                return esc_html($item->specialization ?: '-');
            case 'coaching_level':
                return esc_html($item->coaching_level ?: '-');
            case 'courses_count':
                global $wpdb;
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}sc_course_coaches WHERE coach_id = %d",
                    $item->id
                ));
                return $count ?: 0;
            case 'is_active':
                return $item->is_active ? '<span style="color: green;">فعال</span>' : '<span style="color: red;">غیرفعال</span>';
            default:
                return print_r($item, true);
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_coaches';
        
        $per_page = $this->get_items_per_page('coaches_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // پردازش bulk actions
        $this->process_bulk_action();
        
        // پردازش حذف تکی
        if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['coach'])) {
            $coach_id = absint($_GET['coach']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_coach_' . $coach_id)) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name, national_id FROM $table_name WHERE id = %d", $coach_id), ARRAY_A);
                // حذف کاربر WordPress قبل از حذف از جدول
                sc_delete_wp_user_by_table_id($table_name, $coach_id);
                
                // حذف ارتباطات دوره
                $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
                $wpdb->delete($course_coaches_table, ['coach_id' => $coach_id], ['%d']);
                
                // حذف مربی از جدول
                $wpdb->delete($table_name, ['id' => $coach_id], ['%d']);
                if (function_exists('sc_log_activity') && $row) {
                    sc_log_activity('deleted', 'coach', $coach_id, 'مربی «' . ($row['first_name'] . ' ' . $row['last_name']) . '» حذف شد', $row, null);
                }
                wp_redirect(admin_url('admin.php?page=sc-coaches&sc_status=coach_deleted'));
                exit;
            }
        }
        
        // جستجو
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where = "1=1";
        if (!empty($search)) {
            $where .= $wpdb->prepare(
                " AND (first_name LIKE %s OR last_name LIKE %s OR national_id LIKE %s OR mobile_phone LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // فیلتر وضعیت
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        if ($status_filter === 'active') {
            $where .= " AND is_active = 1";
        } elseif ($status_filter === 'inactive') {
            $where .= " AND is_active = 0";
        }
        
        // مرتب‌سازی
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'id';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) == 'ASC' ? 'ASC' : 'DESC';
        
        // تعداد کل
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
        
        // دریافت داده‌ها
        $this->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }

    protected function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // دریافت ID های انتخاب شده از GET (مثل members-list)
        $coach_ids = isset($_GET['coach']) ? (array) $_GET['coach'] : [];
        if (empty($coach_ids) || !is_array($coach_ids)) {
            return;
        }
        
        $coach_ids = array_map('absint', $coach_ids);
        if (empty($coach_ids)) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_coaches';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        
        switch ($action) {
            case 'activate':
                foreach ($coach_ids as $coach_id) {
                    $wpdb->update($table_name, ['is_active' => 1], ['id' => $coach_id], ['%d'], ['%d']);
                }
                wp_redirect(admin_url('admin.php?page=sc-coaches&sc_status=coaches_activated'));
                exit;
                
            case 'deactivate':
                foreach ($coach_ids as $coach_id) {
                    $wpdb->update($table_name, ['is_active' => 0], ['id' => $coach_id], ['%d'], ['%d']);
                }
                wp_redirect(admin_url('admin.php?page=sc-coaches&sc_status=coaches_deactivated'));
                exit;
                
            case 'delete':
                foreach ($coach_ids as $coach_id) {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name, national_id FROM $table_name WHERE id = %d", $coach_id), ARRAY_A);
                    // حذف کاربر WordPress قبل از حذف از جدول
                    sc_delete_wp_user_by_table_id($table_name, $coach_id);
                    
                    // حذف ارتباطات دوره
                    $wpdb->delete($course_coaches_table, ['coach_id' => $coach_id], ['%d']);
                    
                    // حذف مربی از جدول
                    $wpdb->delete($table_name, ['id' => $coach_id], ['%d']);
                    if (function_exists('sc_log_activity') && $row) {
                        sc_log_activity('deleted', 'coach', $coach_id, 'مربی «' . ($row['first_name'] . ' ' . $row['last_name']) . '» حذف شد', $row, null);
                    }
                }
                wp_redirect(admin_url('admin.php?page=sc-coaches&sc_status=coaches_deleted'));
                exit;
        }
    }

    protected function get_views() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_coaches';
        
        $all = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
        $inactive = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 0");
        
        $current = isset($_GET['status']) ? $_GET['status'] : 'all';
        
        $views = [
            'all' => sprintf(
                '<a href="%s" class="%s">همه <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=sc-coaches'),
                $current == 'all' ? 'current' : '',
                $all
            ),
            'active' => sprintf(
                '<a href="%s" class="%s">فعال <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=sc-coaches&status=active'),
                $current == 'active' ? 'current' : '',
                $active
            ),
            'inactive' => sprintf(
                '<a href="%s" class="%s">غیرفعال <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=sc-coaches&status=inactive'),
                $current == 'inactive' ? 'current' : '',
                $inactive
            )
        ];
        
        return $views;
    }
}

