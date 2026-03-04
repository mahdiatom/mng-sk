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
            'full_name' => 'نام و نام خانوادگی',
            'id' => 'شناسه',
            'birth_date_shamsi' => 'تاریخ تولد',
            'age' => 'سن',
            'national_id' => 'کد ملی ',
            'player_phone' => 'شماره تماس ',
            'insurance_status' => 'بیمه',
            'member_type' => 'نوع',
            'profile_completed' => 'تکمیل پروفایل',
            'is_active' => 'وضعیت '
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
public function column_full_name($item) {
        $full_name = $item['first_name'] . ' ' . $item['last_name'];
        
        // نمایش دوره‌های بازیکن
        global $wpdb;
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d 
             AND mc.status = 'active' 
             AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '')
             AND c.deleted_at IS NULL 
             AND c.is_active = 1
             LIMIT 10",
            $item['id']
        ));

        
        $course_names = [];
        if ($courses) {
            foreach ($courses as $course) {
                $course_names[] = $course->title;
            }
        }
        $courses_text = !empty($course_names) ? '<br><small class="courses_member_table" style="color: #666;">دوره‌ها: ' . implode(', ', $course_names) . '<br>' . '</small>' : '';

        // بررسی فعال بودن کیف پول (امکانات پرو + تنظیم کیف پول)
        $wallet_enabled = function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet();
        $wallet_balance = $wallet_enabled ? sc_get_wallet_balance($item['id']) : 0;
        
        $actions = [
            'view' => '<a href="' . admin_url('admin.php?page=sc-view-member&player_id=') . $item['id'] . '">مشاهده</a>',
            'edit' => '<a href="' . admin_url('admin.php?page=sc-add-member&player_id=') . $item['id'] . '">ویرایش</a>',
            'delete' => '<a href="' . admin_url('admin.php?page=sc-members&action=delete&player_id=') . $item['id'] . '">حذف</a>',
        ];
        
        // اضافه کردن دکمه مدیریت کیف پول
        if ($wallet_enabled) {
            $wallet_url = admin_url('admin.php?page=sc-wallet&filter_member=' . $item['id']);
            $actions['wallet'] = '<a href="' . esc_url($wallet_url) . '" style="color: #28a745;">💰 کیف پول (' . sc_format_amount_display($wallet_balance) . ' تومان)</a>';
        }

        return $full_name . $courses_text . ' ' . $this->row_actions($actions);
    }
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'age':
                 return sc_calculate_age($item['birth_date_shamsi']);
            case 'last_name':
                return esc_html($item['last_name'] ?? '');
            case 'national_id':
                return esc_html(!empty($item['national_id']) ? $item['national_id'] : '-');
            case 'player_phone':
                return esc_html(!empty($item['player_phone']) ? $item['player_phone'] : '-');
            case 'is_active':
                return !empty($item['is_active']) ? 'فعال' : 'غیرفعال';
            case 'member_type':
                $type = isset($item['member_type']) ? $item['member_type'] : 'normal';
                return $type === 'team' ? 'بازیکن تیم' : 'بازیکن عادی';
            case 'profile_completed':
                return $item['profile_completed']
        ? '<span style="color:#00a32a;font-weight:bold;">✓ تکمیل شده</span>'
        : '<span style="color:#d63638;font-weight:bold;">✗ ناقص</span>';
            case 'insurance_status':
                // بررسی وضعیت بیمه
                $insurance_expiry_date = isset($item['insurance_expiry_date_shamsi']) ? $item['insurance_expiry_date_shamsi'] : '';
                
                if (empty($insurance_expiry_date)) {
                    return '<span style="color: #999;">-</span>';
                }
                
                // دریافت تاریخ امروز به شمسی
                $today = new DateTime();
                $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                $today_shamsi = $today_jalali[0] . '/' . 
                               str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                               str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                
                // تبدیل تاریخ‌ها به آرایه برای مقایسه
                $expiry_parts = explode('/', $insurance_expiry_date);
                $today_parts = explode('/', $today_shamsi);
                
                if (count($expiry_parts) === 3 && count($today_parts) === 3) {
                    $expiry_year = (int)$expiry_parts[0];
                    $expiry_month = (int)$expiry_parts[1];
                    $expiry_day = (int)$expiry_parts[2];
                    
                    $today_year = (int)$today_parts[0];
                    $today_month = (int)$today_parts[1];
                    $today_day = (int)$today_parts[2];
                    
                    // مقایسه تاریخ‌ها
                    $is_expired = false;
                    if ($expiry_year < $today_year) {
                        $is_expired = true;
                    } elseif ($expiry_year == $today_year) {
                        if ($expiry_month < $today_month) {
                            $is_expired = true;
                        } elseif ($expiry_month == $today_month) {
                            if ($expiry_day < $today_day) {
                                $is_expired = true;
                            }
                        }
                    }
                    
                    if ($is_expired) {
                        return '<span style="color: #d63638; font-weight: bold;">✗ منقضی</span>';
                    } else {
                        return '<span style="color: #00a32a; font-weight: bold;">✓ فعال</span>';
                    }
                }
                
                return '<span style="color: #999;">-</span>';
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
     public function extra_tablenav($which) {
        $coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;    
        if ($which == 'top') {
            global $wpdb;
            $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
            $courses_table = $wpdb->prefix . 'sc_courses';
            
            $courses = $wpdb->get_results($wpdb->prepare(
    "SELECT c.id, c.title, c.description, c.price, c.price_per_session, c.capacity, c.sessions_count, c.start_date, c.end_date, c.is_active
     FROM $courses_table c
     INNER JOIN $course_coaches_table cc ON cc.course_id = c.id AND cc.coach_id = %d
     WHERE c.deleted_at IS NULL
     ORDER BY c.title",
    $coach_id
));
    //                "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"        
            $selected_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
            $selected_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
            $selected_profile = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : 'all';
            $selected_member_type = isset($_GET['filter_member_type']) ? sanitize_text_field($_GET['filter_member_type']) : 'all';
            
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

            // فیلتر نوع بازیکن
            echo '<select name="filter_member_type" id="filter_member_type" style="margin-left: 5px;">';
            echo '<option value="all"' . ($selected_member_type == 'all' ? ' selected' : '') . '>همه انواع</option>';
            echo '<option value="normal"' . ($selected_member_type == 'normal' ? ' selected' : '') . '>بازیکن عادی</option>';
            echo '<option value="team"' . ($selected_member_type == 'team' ? ' selected' : '') . '>بازیکن تیم</option>';
            echo '</select>';
            
            echo '<input type="submit" name="filter_action" id="doaction" class="button action" value="فیلتر" style="margin-left: 5px;">';
            
            // دکمه خروجی Excel
            $export_url = admin_url('admin.php?page=sc-coach-my-players&sc_export=excel&export_type=members_coach');
            if (isset($_GET['player_status']) && $_GET['player_status'] !== 'all') {
                $export_url = add_query_arg('player_status', $_GET['player_status'], $export_url);
            }
            if (isset($_GET['filter_course']) && !empty($_GET['filter_course'])) {
                $export_url = add_query_arg('filter_course', $_GET['filter_course'], $export_url);
            }
            if (isset($_GET['filter_status']) && $_GET['filter_status'] !== 'all') {
                $export_url = add_query_arg('filter_status', $_GET['filter_status'], $export_url);
            }
            if (isset($_GET['s']) && !empty($_GET['s'])) {
                $export_url = add_query_arg('s', $_GET['s'], $export_url);
            }
            if (isset($_GET['filter_profile']) && $_GET['filter_profile'] !== 'all') {
                $export_url = add_query_arg('filter_profile', $_GET['filter_profile'], $export_url);
            }
            if (isset($_GET['filter_member_type']) && $_GET['filter_member_type'] !== 'all') {
                $export_url = add_query_arg('filter_member_type', $_GET['filter_member_type'], $export_url);
            }
            $export_url = wp_nonce_url($export_url, 'sc_export_excel');
            echo '<a href="' . esc_url($export_url) . '" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff; margin-left: 5px;">📊 خروجی Excel</a>';
 

                echo '</div>';
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
                " AND m.id IN (SELECT member_id FROM $member_courses_table WHERE course_id = %d AND status = 'active')",
                $course_id
            );
        }

        // فیلتر نوع بازیکن
        if (isset($_GET['filter_member_type']) && $_GET['filter_member_type'] !== 'all') {
            $member_type = sanitize_text_field($_GET['filter_member_type']);
            if (in_array($member_type, ['normal', 'team'])) {
                $where .= $wpdb->prepare(
                    " AND (COALESCE(member_type, 'normal') = %s)",
                    $member_type
                );
            }
        }

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(
                " AND (first_name LIKE %s OR last_name LIKE %s OR player_phone LIKE %s OR birth_date_shamsi LIKE %s OR birth_date_gregorian LIKE %s)",
                $search, $search, $search, $search, $search
            );
        }
        $sql = "SELECT SQL_CALC_FOUND_ROWS DISTINCT m.id, m.first_name, m.last_name, m.national_id, m.player_phone, m.is_active , m.birth_date_shamsi , m.insurance_expiry_date_shamsi , m.member_type , m.profile_completed
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
