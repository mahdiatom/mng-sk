<?php
if ( ! defined('ABSPATH') ) exit;
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Player_List_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
        //    'row' => 'ردیف',
            'full_name' => 'نام و نام خانوادگی',
            'id' => 'شناسه',
            'birth_date_shamsi' => 'تاریخ تولد',
            'age' => 'سن',
            'national_id' => 'کد ملی ',
            'player_phone' => 'شماره تماس ',
            'insurance_status' => 'بیمه',
            'member_type' => 'نوع',
            'team_level' => 'تیم و سطح ',
            'profile_completed' => 'تکمیل پروفایل',
            'identity_verified' => 'احراز هویت',
            'is_active' => 'وضعیت '
        ];
    }

//     public function column_row($item) {
//   static $row_number = 0;

//     $page = $this->get_pagenum();
//     $per_page = $this->get_items_per_page('players_per_page', 50);

//     $row_number++;

//     return (($page - 1) * $per_page) + $row_number;
// }

public function column_full_name($item) {
        $full_name = trim($item['first_name'] . ' ' . $item['last_name']);
        $phone = !empty($item['player_phone']) ? $item['player_phone'] : '';
        $photo = !empty($item['personal_photo']) ? $item['personal_photo'] : '';

        $initials = '';
        $fn = trim((string) ($item['first_name'] ?? ''));
        $ln = trim((string) ($item['last_name'] ?? ''));
        if ($fn !== '') {
            $initials .= mb_substr($fn, 0, 1);
        }
        if ($ln !== '') {
            $initials .= mb_substr($ln, 0, 1);
        }
        if ($initials === '') {
            $initials = '؟';
        }

        if ($photo) {
            $avatar_html = '<span class="sc-member-avatar"><img src="' . esc_url($photo) . '" alt="" loading="lazy"></span>';
        } else {
            $avatar_html = '<span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true">' . esc_html($initials) . '</span>';
        }

        $meta_parts = [];
        if ($phone !== '') {
            $meta_parts[] = '<span class="sc-member-meta-item">' . esc_html($phone) . '</span>';
        }
        if (!empty($item['national_id'])) {
            $meta_parts[] = '<span class="sc-member-meta-item">' . esc_html($item['national_id']) . '</span>';
        }
        $meta_html = !empty($meta_parts)
            ? '<span class="sc-member-meta">' . implode('<span class="sc-member-meta-dot"></span>', $meta_parts) . '</span>'
            : '';

        $delete_url  = admin_url('admin.php?page=sc-members&action=delete&player_id=' . absint($item['id']));
        $delete_name = $full_name;
        $delete_msg  = $delete_name !== ''
            ? sprintf('آیا از حذف بازیکن «%s» اطمینان دارید؟ این عمل قابل بازگشت نیست.', $delete_name)
            : 'آیا از حذف این بازیکن اطمینان دارید؟ این عمل قابل بازگشت نیست.';

        $actions = [
            'view' => '<a href="' . admin_url('admin.php?page=sc-view-member&player_id=') . $item['id'] . '">مشاهده</a>',
            'edit' => '<a href="' . admin_url('admin.php?page=sc-add-member&player_id=') . $item['id'] . '">ویرایش</a>',
        ];
        if (!(function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only())) {
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return scConfirmInline(event, { type: \'danger\', message: \'%s\' });">حذف</a>',
                esc_url($delete_url),
                esc_js($delete_msg)
            );
        }

        $name_block = '<span class="sc-member-identity">'
            . $avatar_html
            . '<span class="sc-member-identity-text">'
            . '<span class="sc-member-name">' . esc_html($full_name) . '</span>'
            . $meta_html
            . '</span>'
            . '</span>';

        return $name_block . $this->row_actions($actions);
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . $item['id'] . '" name="player[]" />';
    }
    protected function get_primary_column_name() {
    return 'full_name';
    }
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'birth_date_shamsi':
                return $item['birth_date_shamsi'] ?: '-';
            case 'age':
                return sc_calculate_age($item['birth_date_shamsi']);
            case 'national_id':
                return $item['national_id'];
            case 'player_phone':
                return $item['player_phone'];
            case 'insurance_status':
                // بررسی وضعیت بیمه
                $insurance_expiry_date = isset($item['insurance_expiry_date_shamsi']) ? $item['insurance_expiry_date_shamsi'] : '';
                
                if (empty($insurance_expiry_date)) {
                    return '<span class="sc-badge sc-badge--muted">—</span>';
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
                        return '<span class="sc-badge sc-badge--danger">منقضی</span>';
                    } else {
                        return '<span class="sc-badge sc-badge--success">فعال</span>';
                    }
                }
                
                return '<span class="sc-badge sc-badge--muted">—</span>';
            case 'member_type':
                $type = isset($item['member_type']) ? $item['member_type'] : 'normal';
                return $type === 'team'
                    ? '<span class="sc-badge sc-badge--purple">بازیکن تیم</span>'
                    : '<span class="sc-badge sc-badge--soft">بازیکن عادی</span>';
            case 'profile_completed':
                return !empty($item['profile_completed'])
                    ? '<span class="sc-badge sc-badge--success">تکمیل شده</span>'
                    : '<span class="sc-badge sc-badge--danger">ناقص</span>';
            case 'identity_verified':
                return !empty($item['identity_verified'])
                    ? '<span class="sc-badge sc-badge--success">تایید شده</span>'
                    : '<span class="sc-badge sc-badge--warning">در انتظار بررسی</span>';
            case 'is_active':
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
        if (isset($_GET['s'])) {
            echo "بازیکنی با این مشخصات یافت نشد!";
        } elseif (isset($_GET['player_status']) && $_GET['player_status'] == 'inactive') {
            echo "هیچ بازیکن غیرفعالی وجود ندارد.";
        } else {
            echo "هنوز بازیکنی ثبت نکرده‌اید. از بخش کاربران اولین بازیکن خود را اضافه کنید.";
        }
    }

    public function get_sortable_columns() {
        return [
            'birth_date_shamsi' => ['birth_date_shamsi', true],
            'is_active' => ['is_active', true],
        ];
    }

    public function get_bulk_actions() {
        $actions = [
            'delete' => 'حذف بازیکن',
            'activate' => 'فعال کردن بازیکن',
            'deactivate' => 'غیرفعال کردن بازیکن',
            'verify_identity' => 'تایید احراز هویت',
        ];
        if (current_user_can('manage_options')) {
            $actions['send_sms'] = 'ارسال پیامک';
        }
        return $actions;
    }
    public function column_team_level($item) {

    $team  = !empty($item['team_player']) ? $item['team_player'] : '-';
    $level = !empty($item['skill_level']) ? $item['skill_level'] : '-';

    if ($team == '-' && $level == '-') {
        return '<span style="color:#999;">-</span>';
    }

    return '<span class="sc-team-level"><strong>' . esc_html($team) . '</strong><small>سطح: ' . esc_html($level) . '</small></span>';
    }


    public function process_bulk_action() {
         global $wpdb;
            $table_name = $wpdb->prefix . 'sc_members';

        if ($this->current_action() == 'delete' && isset($_GET['player_id'])) {
        $player_id = absint($_GET['player_id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name, national_id FROM $table_name WHERE id = %d", $player_id), ARRAY_A);
        // حذف کاربر WordPress قبل از حذف از جدول
        sc_delete_wp_user_by_table_id($table_name, $player_id);
        
        // حذف بازیکن از جدول
        $wpdb->delete($table_name, ['id' => $player_id]);
        if (function_exists('sc_log_activity') && $row) {
            sc_log_activity('deleted', 'member', $player_id, 'عضو «' . ($row['first_name'] . ' ' . $row['last_name']) . '» حذف شد', $row, null);
        }
        wp_redirect(admin_url('admin.php?page=sc-members&sc_status=deleted'));
            exit;
        
    }

        if ($this->current_action() == 'delete') {
            $players = isset($_GET['player']) ? $_GET['player'] : [];
           
            foreach ($players as $player_id) {
                $player_id = absint($player_id);
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name, national_id FROM $table_name WHERE id = %d", $player_id), ARRAY_A);
                // حذف کاربر WordPress قبل از حذف از جدول
                sc_delete_wp_user_by_table_id($table_name, $player_id);
                
                // حذف بازیکن از جدول
                $wpdb->delete($table_name, ['id' => $player_id]);
                if (function_exists('sc_log_activity') && $row) {
                    sc_log_activity('deleted', 'member', $player_id, 'عضو «' . ($row['first_name'] . ' ' . $row['last_name']) . '» حذف شد', $row, null);
                }
            }
            wp_redirect(admin_url('admin.php?page=sc-members&sc_status=bulk_deleted&sc_status2=deleted_player'));
            exit;
        }
        if ($this->current_action() == 'activate') {
            $players = isset($_GET['player']) ? $_GET['player'] : [];
            foreach ($players as $player_id) {
                $wpdb->update($table_name, ['is_active' => 1], ['id' => $player_id]);
            }
            wp_redirect(admin_url('admin.php?page=sc-members&sc_status=bulk_activated'));
            exit;
    }

    if ($this->current_action() == 'deactivate') {
            $players = isset($_GET['player']) ? $_GET['player'] : [];
            foreach ($players as $player_id) {
                $wpdb->update($table_name, ['is_active' => 0], ['id' => $player_id]);
            }
            wp_redirect(admin_url('admin.php?page=sc-members&sc_status=bulk_deactivated'));
            exit;
    }

    if ($this->current_action() == 'verify_identity') {
            $players = isset($_GET['player']) ? $_GET['player'] : [];
            foreach ($players as $player_id) {
                $player_id = absint($player_id);
                if (!$player_id) {
                    continue;
                }
                $old_status = (int) $wpdb->get_var($wpdb->prepare("SELECT identity_verified FROM $table_name WHERE id = %d", $player_id));
                $wpdb->update($table_name, ['identity_verified' => 1], ['id' => $player_id], ['%d'], ['%d']);
                if ($old_status !== 1 && function_exists('sc_send_identity_verified_notifications')) {
                    sc_send_identity_verified_notifications($player_id);
                }
            }
            wp_redirect(admin_url('admin.php?page=sc-members&sc_status=bulk_identity_verified'));
            exit;
    }

        if ($this->current_action() == 'send_sms' && current_user_can('manage_options')) {
            $players = isset($_GET['player']) ? (array) $_GET['player'] : [];
            $players = array_filter(array_map('absint', $players));
            if (!empty($players)) {
                $member_ids = implode(',', $players);
                $redirect = add_query_arg('member_ids', $member_ids, admin_url('admin.php?page=sc-add-notification'));
                wp_safe_redirect($redirect);
                exit;
            }
        }
    }

    

    protected function view_create($key, $label, $url, $count = 0, $current_param = 'player_status') {
        $current_val = 'all';
        if ($current_param === 'player_status') {
            $current_val = isset($_GET['player_status']) ? $_GET['player_status'] : 'all';
        } elseif ($current_param === 'filter_member_type') {
            $current_val = isset($_GET['filter_member_type']) ? $_GET['filter_member_type'] : 'all';
        }
        $class_view = $current_val == $key ? 'current' : '';
        if (isset($_GET['s'])) {
            $url .= "&s=" . sanitize_text_field($_GET['s']);
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
        $table_name = $wpdb->prefix . 'sc_members';
        $where = " 1=1 ";

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(
                " AND (m.first_name LIKE %s OR m.last_name LIKE %s OR m.player_phone LIKE %s OR m.birth_date_shamsi LIKE %s OR m.birth_date_gregorian LIKE %s)",
                $search, $search, $search, $search, $search
            );
        }

        // برای منشی: شمارنده‌ها فقط بازیکنان شعبه(های) خودش
        if (function_exists('sc_secretary_append_member_where')) {
            $where = sc_secretary_append_member_where($where, 'm');
        }
        $from_sql = "{$table_name} m";

        $count_all = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$from_sql} WHERE {$where}");
        $count_active = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$from_sql} WHERE {$where} AND m.is_active = 1");
        $count_inactive = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$from_sql} WHERE {$where} AND m.is_active = 0");
        $count_normal = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$from_sql} WHERE {$where} AND (COALESCE(m.member_type, 'normal') = 'normal')");
        $count_team = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$from_sql} WHERE {$where} AND (COALESCE(m.member_type, 'normal') = 'team')");

        $base_member_type = (isset($_GET['filter_member_type']) && in_array($_GET['filter_member_type'], ['normal', 'team'])) ? $_GET['filter_member_type'] : '';
        $url_active = admin_url('admin.php?page=sc-members&player_status=active');
        $url_inactive = admin_url('admin.php?page=sc-members&player_status=inactive');
        if ($base_member_type) {
            $url_active = add_query_arg('filter_member_type', $base_member_type, $url_active);
            $url_inactive = add_query_arg('filter_member_type', $base_member_type, $url_inactive);
        }

        $views = [
            'all' => $this->view_create(
                'all',
                'همه',
                admin_url('admin.php?page=sc-members&player_status=all&filter_member_type=all'),
                $count_all
            ),
            'active' => $this->view_create(
                'active',
                'فعال',
                $url_active,
                $count_active
            ),
            'inactive' => $this->view_create(
                'inactive',
                'غیرفعال',
                $url_inactive,
                $count_inactive
            ),
            'member_normal' => $this->view_create(
                'normal',
                'بازیکن عادی',
                admin_url('admin.php?page=sc-members&filter_member_type=normal'),
                $count_normal,
                'filter_member_type'
            ),
            'member_team' => $this->view_create(
                'team',
                'بازیکن تیم',
                admin_url('admin.php?page=sc-members&filter_member_type=team'),
                $count_team,
                'filter_member_type'
            ),
        ];

        return $views;
    }
    
    public function extra_tablenav($which) {
        // فیلترهای قدیمی حذف شدند. فرم فیلتر جدید در list_players.php قرار دارد.
        if ($which == 'top') {
            // فقط دکمه اکسل را نگه می‌داریم اگر لازم باشد (اختیاری)
            // در حال حاضر فرم فیلتر کامل در template قرار دارد.
        }
    }

    public function prepare_items() {
        $this->process_bulk_action();

        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_members';
        $per_page = $this->get_items_per_page('players_per_page', 50);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $allowed_orderby = ['id', 'first_name', 'last_name', 'created_at', 'national_id', 'is_active', 'profile_completed', 'identity_verified'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_at';
        }
        $order = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';
        $order_clause = "ORDER BY `$orderby` $order";

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
            $course_filter_sql = " AND id IN (
                    SELECT member_id
                    FROM $member_courses_table mc_f
                    WHERE mc_f.course_id = %d
                      AND mc_f.status = 'active'
                )";
            $course_filter_args = [$course_id];
            if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
                && function_exists('sc_secretary_member_enrollment_match_sql')) {
                $match = sc_secretary_member_enrollment_match_sql('mc_f');
                if ($match['sql'] !== '1=0') {
                    $course_filter_sql = " AND id IN (
                        SELECT member_id
                        FROM $member_courses_table mc_f
                        WHERE mc_f.course_id = %d
                          AND mc_f.status = 'active'
                          AND {$match['sql']}
                    )";
                    $course_filter_args = array_merge([$course_id], $match['args']);
                }
            } else {
                $course_filter_sql = " AND id IN (
                    SELECT member_id
                    FROM $member_courses_table
                    WHERE course_id = %d
                      AND status = 'active'
                      AND (course_status_flags IS NULL OR TRIM(course_status_flags) = '')
                )";
            }
            $where .= $wpdb->prepare($course_filter_sql, $course_filter_args);
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

        // فیلتر تیم (نام تیم در فیلد team_player)
        if (isset($_GET['filter_team']) && $_GET['filter_team'] !== '' && $_GET['filter_team'] !== 'all') {
            $team_val = sanitize_text_field(wp_unslash($_GET['filter_team']));
            if ($team_val !== '') {
                $where .= $wpdb->prepare(' AND team_player = %s', $team_val);
            }
        }

        // فیلتر سطح (skill_level)
        if (isset($_GET['filter_skill_level']) && $_GET['filter_skill_level'] !== '' && $_GET['filter_skill_level'] !== 'all') {
            $level_val = sanitize_text_field(wp_unslash($_GET['filter_skill_level']));
            if ($level_val !== '') {
                $where .= $wpdb->prepare(' AND skill_level = %s', $level_val);
            }
        }

        // فیلتر وضعیت احراز هویت
        if (isset($_GET['filter_identity']) && $_GET['filter_identity'] !== 'all') {
            $identity = sanitize_text_field($_GET['filter_identity']);
            if ($identity === 'verified') {
                $where .= ' AND identity_verified = 1';
            } elseif ($identity === 'pending') {
                $where .= ' AND (identity_verified = 0 OR identity_verified IS NULL)';
            }
        }

        // فیلتر وضعیت بیمه (تاریخ انقضای شمسی)
        if (isset($_GET['filter_insurance']) && $_GET['filter_insurance'] !== 'all') {
            $insurance_filter = sanitize_text_field($_GET['filter_insurance']);
            $today_shamsi_str = '';
            if (function_exists('sc_date_shamsi_date_only')) {
                $today_shamsi_str = sc_date_shamsi_date_only(current_time('Y-m-d'));
            }
            if (!$today_shamsi_str && function_exists('gregorian_to_jalali')) {
                $today_dt = new DateTime(current_time('Y-m-d'));
                $today_jalali = gregorian_to_jalali((int) $today_dt->format('Y'), (int) $today_dt->format('m'), (int) $today_dt->format('d'));
                $today_shamsi_str = $today_jalali[0] . '/'
                    . str_pad((string) $today_jalali[1], 2, '0', STR_PAD_LEFT) . '/'
                    . str_pad((string) $today_jalali[2], 2, '0', STR_PAD_LEFT);
            }
            if ($insurance_filter === 'none') {
                $where .= " AND (insurance_expiry_date_shamsi IS NULL OR insurance_expiry_date_shamsi = '')";
            } elseif ($insurance_filter === 'active' && $today_shamsi_str !== '') {
                $where .= $wpdb->prepare(
                    " AND insurance_expiry_date_shamsi IS NOT NULL AND insurance_expiry_date_shamsi <> '' AND insurance_expiry_date_shamsi >= %s",
                    $today_shamsi_str
                );
            } elseif ($insurance_filter === 'expired' && $today_shamsi_str !== '') {
                $where .= $wpdb->prepare(
                    " AND insurance_expiry_date_shamsi IS NOT NULL AND insurance_expiry_date_shamsi <> '' AND insurance_expiry_date_shamsi < %s",
                    $today_shamsi_str
                );
            }
        }

        // فیلتر بازیکن خاص (از searchable dropdown)
        if (isset($_GET['filter_player']) && absint($_GET['filter_player']) > 0) {
            $where .= $wpdb->prepare(" AND id = %d", absint($_GET['filter_player']));
        }

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where .= $wpdb->prepare(
                " AND (first_name LIKE %s OR last_name LIKE %s OR player_phone LIKE %s OR birth_date_shamsi LIKE %s OR birth_date_gregorian LIKE %s)",
                $search, $search, $search, $search, $search
            );
        }

        $members_from = $table_name . ' m';
        $where_sql = preg_replace('/(?<!\.)\bid\b/', 'm.id', $where);
        $where_sql = preg_replace('/\bis_active\b/', 'm.is_active', $where_sql);
        $where_sql = preg_replace('/\bprofile_completed\b/', 'm.profile_completed', $where_sql);
        $where_sql = preg_replace('/\bmember_type\b/', 'm.member_type', $where_sql);
        $where_sql = preg_replace('/\bteam_player\b/', 'm.team_player', $where_sql);
        $where_sql = preg_replace('/\bskill_level\b/', 'm.skill_level', $where_sql);
        $where_sql = preg_replace('/\bidentity_verified\b/', 'm.identity_verified', $where_sql);
        $where_sql = preg_replace('/\binsurance_expiry_date_shamsi\b/', 'm.insurance_expiry_date_shamsi', $where_sql);
        $where_sql = preg_replace('/\bfirst_name\b/', 'm.first_name', $where_sql);
        $where_sql = preg_replace('/\blast_name\b/', 'm.last_name', $where_sql);
        $where_sql = preg_replace('/\bplayer_phone\b/', 'm.player_phone', $where_sql);
        $where_sql = preg_replace('/\bbirth_date_shamsi\b/', 'm.birth_date_shamsi', $where_sql);
        $where_sql = preg_replace('/\bbirth_date_gregorian\b/', 'm.birth_date_gregorian', $where_sql);
        $where_sql = preg_replace('/\bcreated_at\b/', 'm.created_at', $where_sql);
        $where_sql = preg_replace('/\bnational_id\b/', 'm.national_id', $where_sql);

        if (function_exists('sc_secretary_append_member_where')) {
            $where_sql = sc_secretary_append_member_where($where_sql, 'm');
        }

        // COUNT جداگانه — SQL_CALC_FOUND_ROWS بعد از UPDATE پروفایل در حلقه زیر خراب می‌شد و total=1 می‌شد
        $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$members_from} WHERE {$where_sql}");

        $per_page = absint($per_page);
        $offset = absint($offset);
        $order_clause = preg_replace('/`([^`]+)`/', 'm.`$1`', $order_clause);
        // $where از قبل با prepare ساخته شده؛ دوباره prepare نکنید (LIKEهای % خراب می‌شوند)
        $results = $wpdb->get_results(
            "SELECT m.* FROM {$members_from} WHERE {$where_sql} {$order_clause} LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        if (!empty($results) && function_exists('sc_check_profile_completed')) {
            foreach ($results as &$row) {
                $computed = sc_check_profile_completed((int) $row['id'], $row) ? 1 : 0;
                if ((int) ($row['profile_completed'] ?? 0) !== $computed && function_exists('sc_update_profile_completed_status')) {
                    sc_update_profile_completed_status((int) $row['id'], $row);
                }
                $row['profile_completed'] = $computed;
            }
            unset($row);
        }

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil($total_items / $per_page) : 1,
        ]);

        $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
        $this->items = $results;
    }
}

add_filter('screen_options_show_per_page', '__return_true');

?>

