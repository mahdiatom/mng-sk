<?php 

/**
 * ============================
 * Admin Menu
 * ============================
 */
add_action('admin_menu', 'sc_register_admin_menu');

function sc_register_admin_menu() {

    // Main menu
   add_menu_page(
        'SportClub Manager',        // Page title
        'SportClub Manager',        // Menu title
        'manage_options',           // Capability
        'sc-dashboard',             // Menu slug
        'sc_admin_dashboard_page',  // Callback
        'dashicons-universal-access-alt', // Icon
        26                          // Position
    );

    // Members list
    $list_member_sufix =  add_submenu_page(
        'sc-dashboard',
        'Members',
        'Members',
        'manage_options',
        'sc-members',
        'sc_admin_members_list_page'
    );

    // Add Member
    $add_member_sufix =  add_submenu_page(
        'sc-dashboard',
        'Add Member',
        'Add Member',
        'manage_options',
        'sc-add-member',
        'sc_admin_add_member_page'
    );

    // Courses list
    $list_courses_sufix = add_submenu_page(
        'sc-dashboard',
        'Courses',
        'Courses',
        'manage_options',
        'sc-courses',
        'sc_admin_courses_list_page'
    );

    // Add Course
    $add_course_sufix = add_submenu_page(
        'sc-dashboard',
        'Add Course',
        'Add Course',
        'manage_options',
        'sc-add-course',
        'sc_admin_add_course_page'
    );

    // setting
    $setting_sufix =  add_submenu_page(
        'sc-dashboard',
        'setting',
        'setting',
        'manage_options',
        'sc_setting',
        'sc_setting_callback'
    );

    // Attendance - Add
    add_submenu_page(
        'sc-dashboard',
        'ثبت حضور و غیاب',
        'ثبت حضور و غیاب',
        'manage_options',
        'sc-attendance-add',
        'sc_admin_attendance_add_page'
    );

    // Attendance - List
    add_submenu_page(
        'sc-dashboard',
        'لیست حضور و غیاب',
        'لیست حضور و غیاب',
        'manage_options',
        'sc-attendance-list',
        'sc_admin_attendance_list_page'
    );

    // Invoices - List
    $list_invoices_sufix = add_submenu_page(
        'sc-dashboard',
        'صورت حساب‌ها',
        'صورت حساب‌ها',
        'manage_options',
        'sc-invoices',
        'sc_admin_invoices_list_page'
    );

    // Invoices - Add
    $add_invoice_sufix = add_submenu_page(
        'sc-dashboard',
        'ایجاد صورت حساب',
        'ایجاد صورت حساب',
        'manage_options',
        'sc-add-invoice',
        'sc_admin_add_invoice_page'
    );

    // Expenses - Add
    $add_expense_sufix = add_submenu_page(
        'sc-dashboard',
        'ثبت هزینه',
        'ثبت هزینه',
        'manage_options',
        'sc-add-expense',
        'sc_admin_add_expense_page'
    );

    // Expenses - List
    $list_expenses_sufix = add_submenu_page(
        'sc-dashboard',
        'لیست هزینه‌ها',
        'لیست هزینه‌ها',
        'manage_options',
        'sc-expenses',
        'sc_admin_expenses_list_page'
    );

    // Reports Menu - Main
    add_menu_page(
        'گزارشات باشگاه',
        'گزارشات باشگاه',
        'manage_options',
        'sc-reports',
        'sc_admin_reports_active_users_page',
        'dashicons-chart-area',
        27
    );

    // Reports - Active Users
    add_submenu_page(
        'sc-reports',
        'کاربران فعال',
        'کاربران فعال',
        'manage_options',
        'sc-reports-active-users',
        'sc_admin_reports_active_users_page'
    );

    // Reports - Income and Expenses
    add_submenu_page(
        'sc-reports',
        'درآمد و هزینه‌ها',
        'درآمد و هزینه‌ها',
        'manage_options',
        'sc-reports-income-expenses',
        'sc_admin_reports_income_expenses_page'
    );

    // Reports - Debtors
    add_submenu_page(
        'sc-reports',
        'بدهکاران',
        'بدهکاران',
        'manage_options',
        'sc-reports-debtors',
        'sc_admin_reports_debtors_page'
    );

    // Reports - Payments
    add_submenu_page(
        'sc-reports',
        'پرداختی‌ها',
        'پرداختی‌ها',
        'manage_options',
        'sc-reports-payments',
        'sc_admin_reports_payments_page'
    );

    add_action('load-'. $add_member_sufix , 'callback_add_member_sufix');
    add_action('load-'. $add_invoice_sufix , 'callback_add_invoice_sufix');
    add_action('load-'. $add_expense_sufix , 'callback_add_expense_sufix');
    add_action('load-'. $list_invoices_sufix , 'process_invoices_table_data');
    add_action('load-'. $list_member_sufix , 'procces_table_data');
    add_action('load-'. $list_courses_sufix , 'procces_courses_table_data');
    add_action('load-'. $add_course_sufix , 'callback_add_course_sufix');
}

/**
 * Export Excel endpoints
 */
add_action('admin_init', 'sc_handle_excel_export');
function sc_handle_excel_export() {
    // بررسی اینکه آیا درخواست export است
    if (!isset($_GET['sc_export']) || $_GET['sc_export'] !== 'excel') {
        return;
    }
    
    // بررسی دسترسی
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    
    // بررسی nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sc_export_excel')) {
        wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
    }
    
    $export_type = isset($_GET['export_type']) ? sanitize_text_field($_GET['export_type']) : '';
    
    switch ($export_type) {
        case 'invoices':
            sc_export_invoices_to_excel();
            break;
        case 'attendance':
            sc_export_attendance_to_excel();
            break;
        case 'attendance_overall':
            sc_export_attendance_overall_to_excel();
            break;
        case 'members':
            sc_export_members_to_excel();
            break;
        case 'expenses':
            sc_export_expenses_to_excel();
            break;
        case 'debtors':
            sc_export_debtors_to_excel();
            break;
        case 'active_users':
            sc_export_active_users_to_excel();
            break;
        case 'payments':
            sc_export_payments_to_excel();
            break;
        case 'course_users':
            sc_export_course_users_to_excel();
            break;
        default:
            wp_die('نوع export معتبر نیست.');
    }
    
    exit;
}

/**
 * ذخیره تنظیمات screen option برای تعداد رکوردها در هر صفحه
 */
add_filter('set-screen-option', 'sc_set_invoices_screen_option', 10, 3);
function sc_set_invoices_screen_option($status, $option, $value) {
    if ('invoices_per_page' === $option) {
        return $value;
    }
    return $status;
}

/**
 * Placeholder functions for admin pages
 */
function sc_admin_dashboard_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'dashboard.php';
}

function sc_admin_members_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_players.php';
}

function sc_admin_add_member_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb ;
            $table_name = $wpdb->prefix . 'sc_members';
            $player=false;
                if( isset($_GET['player_id'] ) ){
                    $player_id = absint($_GET['player_id']);
                    if($player_id){
                        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",[$player_id]);
                    $player = $wpdb->get_row( $sql
                        
                    );
                }
            }
    include SC_TEMPLATES_ADMIN_DIR . 'member-add.php';
}
function sc_setting_callback(){
    include SC_TEMPLATES_ADMIN_DIR . 'settings.php';
    echo "تنظیمات افزونه";
}

/**
 * Attendance management pages
 */
function sc_admin_attendance_add_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'attendance-add.php';
}

function sc_admin_attendance_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'attendance-list.php';
}

/**
 * Invoices management page
 */
function sc_admin_invoices_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'invoices-list.php';
}

function process_invoices_table_data() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // افزودن screen option برای تعداد رکوردها در هر صفحه
    add_screen_option('per_page', [
        'default' => 20,
        'option' => 'invoices_per_page',
        'label' => 'تعداد صورت حساب‌ها در هر صفحه'
    ]);
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_invoices.php';
    $GLOBALS['invoices_list_table'] = new Invoices_List_Table();
    $GLOBALS['invoices_list_table']->prepare_items();
}

/**
 * Create invoice page
 */
function sc_admin_add_invoice_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'invoice-add.php';
}

/**
 * Reports pages
 */
function sc_admin_reports_active_users_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-active-users.php';
}

function sc_admin_reports_income_expenses_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-income-expenses.php';
}

function sc_admin_reports_debtors_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-debtors.php';
}

function sc_admin_reports_payments_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-payments.php';
}

/**
 * Expenses management pages
 */
function sc_admin_add_expense_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'expense-add.php';
}

function sc_admin_expenses_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'expenses-list.php';
}

/**
 * Process expense creation/update form
 */
function callback_add_expense_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-expense' && isset($_POST['submit_expense'])) {
        // بررسی nonce
        if (!isset($_POST['sc_expense_nonce']) || !wp_verify_nonce($_POST['sc_expense_nonce'], 'sc_add_expense')) {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }
        
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        global $wpdb;
        $expenses_table = $wpdb->prefix . 'sc_expenses';
        
        // اعتبارسنجی
        if (empty($_POST['expense_name'])) {
            wp_redirect(admin_url('admin.php?page=sc-add-expense&sc_status=expense_add_error'));
            exit;
        }
        
        $expense_name = sanitize_text_field($_POST['expense_name']);
        $category_id = !empty($_POST['category_id']) ? absint($_POST['category_id']) : NULL;
        
        // دریافت مبلغ (حذف کاماها در صورت وجود)
        $amount_value = '';
        if (!empty($_POST['amount_raw'])) {
            $amount_value = sanitize_text_field($_POST['amount_raw']);
        } elseif (!empty($_POST['amount'])) {
            $amount_value = preg_replace('/[^0-9.]/', '', sanitize_text_field($_POST['amount']));
        }
        $amount = !empty($amount_value) && is_numeric($amount_value) ? floatval($amount_value) : 0;
        
        if ($amount <= 0) {
            wp_redirect(admin_url('admin.php?page=sc-add-expense&sc_status=expense_add_error'));
            exit;
        }
        
        // پردازش تاریخ
        $expense_date_shamsi = !empty($_POST['expense_date_shamsi']) ? sanitize_text_field($_POST['expense_date_shamsi']) : '';
        $expense_date_gregorian = NULL;
        
        if (!empty($expense_date_shamsi)) {
            $expense_date_gregorian = sc_shamsi_to_gregorian_date($expense_date_shamsi);
        } elseif (!empty($_POST['expense_date_gregorian'])) {
            $expense_date_gregorian = sanitize_text_field($_POST['expense_date_gregorian']);
        }
        
        if (!$expense_date_gregorian) {
            // تاریخ پیش‌فرض: امروز
            $expense_date_gregorian = current_time('Y-m-d');
            $today = new DateTime();
            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
            $expense_date_shamsi = $today_jalali[0] . '/' . 
                                   str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                   str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        }
        
        $description = !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        $expense_id = isset($_POST['expense_id']) ? absint($_POST['expense_id']) : 0;
        
        // ذخیره یا بروزرسانی هزینه
        $expense_data = [
            'name' => $expense_name,
            'category_id' => $category_id,
            'expense_date_shamsi' => $expense_date_shamsi,
            'expense_date_gregorian' => $expense_date_gregorian,
            'amount' => $amount,
            'description' => $description,
            'updated_at' => current_time('mysql')
        ];
        
        if ($expense_id > 0) {
            // بروزرسانی
            $updated = $wpdb->update(
                $expenses_table,
                $expense_data,
                ['id' => $expense_id],
                ['%s', '%d', '%s', '%s', '%f', '%s', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                wp_redirect(admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense_id . '&sc_status=expense_updated'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense_id . '&sc_status=expense_update_error'));
                exit;
            }
        } else {
            // ایجاد جدید
            $expense_data['created_at'] = current_time('mysql');
            
            $inserted = $wpdb->insert(
                $expenses_table,
                $expense_data,
                ['%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s']
            );
            
            if ($inserted !== false) {
                $expense_id = $wpdb->insert_id;
                wp_redirect(admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense_id . '&sc_status=expense_add_true'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-expense&sc_status=expense_add_error'));
                exit;
            }
        }
    }
}

/**
 * Process invoice creation form
 */
function callback_add_invoice_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-invoice' && isset($_POST['submit_invoice'])) {
        // بررسی nonce
        if (!isset($_POST['sc_invoice_nonce']) || !wp_verify_nonce($_POST['sc_invoice_nonce'], 'sc_add_invoice')) {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }
        
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $courses_table = $wpdb->prefix . 'sc_courses';
        $members_table = $wpdb->prefix . 'sc_members';
        
        // اعتبارسنجی
        if (empty($_POST['member_id'])) {
            wp_redirect(admin_url('admin.php?page=sc-add-invoice&sc_status=invoice_add_error'));
            exit;
        }
        
        $member_id = absint($_POST['member_id']);
        $course_id = !empty($_POST['course_id']) ? absint($_POST['course_id']) : NULL;
        $expense_name = !empty($_POST['expense_name']) ? sanitize_text_field($_POST['expense_name']) : NULL;
        
        // دریافت مبلغ (حذف کاماها در صورت وجود)
        $amount_value = '';
        if (!empty($_POST['amount_raw'])) {
            $amount_value = sanitize_text_field($_POST['amount_raw']);
        } elseif (!empty($_POST['amount'])) {
            // حذف کاماها و کاراکترهای غیر عددی
            $amount_value = preg_replace('/[^0-9.]/', '', sanitize_text_field($_POST['amount']));
        }
        $manual_amount = !empty($amount_value) && is_numeric($amount_value) ? floatval($amount_value) : 0;
        
        // بررسی وجود کاربر
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $members_table WHERE id = %d AND is_active = 1",
            $member_id
        ));
        
        if (!$member) {
            wp_redirect(admin_url('admin.php?page=sc-add-invoice&sc_status=invoice_add_error'));
            exit;
        }
        
        // محاسبه مبلغ کل
        $total_amount = $manual_amount;
        
        // اگر دوره انتخاب شده باشد، هزینه دوره را اضافه کن
        if ($course_id) {
            $course = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
                $course_id
            ));
            
            if ($course) {
                $total_amount += floatval($course->price);
            } else {
                // اگر دوره معتبر نبود، فقط مبلغ دستی را استفاده کن
                $course_id = NULL;
            }
        }
        
        // بررسی member_course_id در صورت وجود دوره
        $member_course_id = NULL;
        if ($course_id) {
            $member_courses_table = $wpdb->prefix . 'sc_member_courses';
            $member_course = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $member_courses_table WHERE member_id = %d AND course_id = %d",
                $member_id,
                $course_id
            ));
            
            if ($member_course) {
                $member_course_id = $member_course->id;
            }
        }
        
        // ذخیره صورت حساب
        $invoice_data = [
            'member_id' => $member_id,
            'course_id' => $course_id ? $course_id : 0, // اگر دوره انتخاب نشده باشد، 0 می‌شود
            'member_course_id' => $member_course_id,
            'woocommerce_order_id' => NULL,
            'amount' => $total_amount,
            'expense_name' => $expense_name,
            'penalty_amount' => 0.00,
            'penalty_applied' => 0,
            'status' => 'pending',
            'payment_date' => NULL,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // آماده‌سازی format array برای insert
        $format_array = ['%d', '%d', '%d', '%d', '%f', '%s', '%f', '%d', '%s', '%s', '%s', '%s'];
        
        // اگر course_id یا member_course_id NULL باشد، format را تنظیم کن
        if (!$course_id) {
            $invoice_data['course_id'] = 0;
        }
        if (!$member_course_id) {
            $invoice_data['member_course_id'] = NULL;
            $format_array[2] = '%s'; // NULL برای member_course_id
        }
        if (!$expense_name) {
            $invoice_data['expense_name'] = NULL;
            $format_array[5] = '%s'; // NULL برای expense_name
        }
        
        // ابتدا صورت حساب را ایجاد کن
        $inserted = $wpdb->insert(
            $invoices_table,
            $invoice_data,
            $format_array
        );
        
        if ($inserted !== false) {
            $invoice_id = $wpdb->insert_id;
            
            // ایجاد WooCommerce order
            $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, $course_id, $total_amount, $expense_name);
            
            if ($order_result['success'] && !empty($order_result['order_id'])) {
                // بروزرسانی صورت حساب با order_id
                $wpdb->update(
                    $invoices_table,
                    ['woocommerce_order_id' => $order_result['order_id'], 'updated_at' => current_time('mysql')],
                    ['id' => $invoice_id],
                    ['%d', '%s'],
                    ['%d']
                );
            }
            
            wp_redirect(admin_url('admin.php?page=sc-add-invoice&sc_status=invoice_add_true&invoice_id=' . $invoice_id));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=sc-add-invoice&sc_status=invoice_add_error'));
            exit;
        }
    }
}

/**
 * Courses management pages
 */
function sc_admin_courses_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'courses-list.php';
}

function sc_admin_add_course_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_courses';
    $course = false;
    if (isset($_GET['course_id'])) {
        $course_id = absint($_GET['course_id']);
        if ($course_id) {
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND deleted_at IS NULL", [$course_id]);
            $course = $wpdb->get_row($sql);
        }
    }
    include SC_TEMPLATES_ADMIN_DIR . 'course-add.php';
}

function procces_courses_table_data() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_courses.php';
    $GLOBALS['courses_list_table'] = new Courses_List_Table();
    $GLOBALS['courses_list_table']->prepare_items();
}

function callback_add_course_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-course' && isset($_POST['submit_course'])) {
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_courses';
        
        // Validation
        if (empty($_POST['title']) || empty($_POST['price'])) {
            wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_add_error'));
            exit;
        }
        
        // پردازش تاریخ شمسی به میلادی
        $start_date = NULL;
        if (!empty($_POST['start_date_shamsi'])) {
            $start_date = sc_shamsi_to_gregorian_date(sanitize_text_field($_POST['start_date_shamsi']));
        } elseif (!empty($_POST['start_date'])) {
            $start_date = sanitize_text_field($_POST['start_date']);
        }
        
        $end_date = NULL;
        if (!empty($_POST['end_date_shamsi'])) {
            $end_date = sc_shamsi_to_gregorian_date(sanitize_text_field($_POST['end_date_shamsi']));
        } elseif (!empty($_POST['end_date'])) {
            $end_date = sanitize_text_field($_POST['end_date']);
        }
        
        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'description' => isset($_POST['description']) && !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : NULL,
            'price' => floatval($_POST['price']),
            'capacity' => !empty($_POST['capacity']) ? intval($_POST['capacity']) : NULL,
            'sessions_count' => !empty($_POST['sessions_count']) ? intval($_POST['sessions_count']) : NULL,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;

        // بروزرسانی
        if ($course_id) {
            $updated = $wpdb->update(
                $table_name,
                $data,
                ['id' => $course_id],
                ['%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%s'],
                ['%d']
            );

            if ($updated !== false) {
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_updated&course_id=' . $course_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_update_error&course_id=' . $course_id));
                exit;
            }
        } 
        // اضافه کردن جدید
        else {
            $data['created_at'] = current_time('mysql');
            $inserted = $wpdb->insert(
                $table_name, 
                $data,
                ['%s', '%s', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%s']
            );

            if ($inserted !== false) {
                $insert_id = $wpdb->insert_id;
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_add_true&course_id=' . $insert_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_add_error'));
                exit;
            }
        }
    }
}
//for save data in new member -> wpdb
function callback_add_member_sufix(){
    if(isset($_GET['page']) && $_GET['page'] == 'sc-add-member' && isset($_POST['submit_player'])) {
       // بررسی و ایجاد جداول در صورت عدم وجود
       sc_check_and_create_tables();
       
       global $wpdb;
       $table_name = $wpdb->prefix . 'sc_members';
       
       // Validation
       if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['national_id'])) {
           wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
           exit;
       }
       
       // آماده‌سازی داده‌ها
       $data = [
        'first_name'           => sanitize_text_field($_POST['first_name']),
        'last_name'            => sanitize_text_field($_POST['last_name']),
        'national_id'          => sanitize_text_field($_POST['national_id']),
        'health_verified'      => isset($_POST['health_verified']) ? 1 : 0,
        'info_verified'        => isset($_POST['info_verified']) ? 1 : 0,
        'is_active'            => isset($_POST['is_active']) ? 1 : 0,
        'created_at'           => current_time('mysql'),
        'updated_at'           => current_time('mysql'),
       ];
       
       // فیلدهای اختیاری - همیشه به‌روزرسانی می‌شوند (حتی اگر خالی باشند)
       // برای فیلدهای متنی: اگر خالی باشند، NULL ذخیره می‌شود
       $data['father_name'] = isset($_POST['father_name']) && !empty(trim($_POST['father_name'])) ? sanitize_text_field($_POST['father_name']) : NULL;
       $data['player_phone'] = isset($_POST['player_phone']) && !empty(trim($_POST['player_phone'])) ? sanitize_text_field($_POST['player_phone']) : NULL;
       $data['father_phone'] = isset($_POST['father_phone']) && !empty(trim($_POST['father_phone'])) ? sanitize_text_field($_POST['father_phone']) : NULL;
       $data['mother_phone'] = isset($_POST['mother_phone']) && !empty(trim($_POST['mother_phone'])) ? sanitize_text_field($_POST['mother_phone']) : NULL;
       $data['landline_phone'] = isset($_POST['landline_phone']) && !empty(trim($_POST['landline_phone'])) ? sanitize_text_field($_POST['landline_phone']) : NULL;
       $data['birth_date_shamsi'] = isset($_POST['birth_date_shamsi']) && !empty(trim($_POST['birth_date_shamsi'])) ? sanitize_text_field($_POST['birth_date_shamsi']) : NULL;
       $data['insurance_expiry_date_shamsi'] = isset($_POST['insurance_expiry_date_shamsi']) && !empty(trim($_POST['insurance_expiry_date_shamsi'])) ? sanitize_text_field($_POST['insurance_expiry_date_shamsi']) : NULL;
       $data['birth_date_gregorian'] = isset($_POST['birth_date_gregorian']) && !empty(trim($_POST['birth_date_gregorian'])) ? sanitize_text_field($_POST['birth_date_gregorian']) : NULL;
       $data['personal_photo'] = isset($_POST['personal_photo']) && !empty(trim($_POST['personal_photo'])) ? esc_url_raw($_POST['personal_photo']) : NULL;
       $data['id_card_photo'] = isset($_POST['id_card_photo']) && !empty(trim($_POST['id_card_photo'])) ? esc_url_raw($_POST['id_card_photo']) : NULL;
       $data['sport_insurance_photo'] = isset($_POST['sport_insurance_photo']) && !empty(trim($_POST['sport_insurance_photo'])) ? esc_url_raw($_POST['sport_insurance_photo']) : NULL;
       $data['medical_condition'] = isset($_POST['medical_condition']) && !empty(trim($_POST['medical_condition'])) ? sanitize_textarea_field($_POST['medical_condition']) : NULL;
       $data['sports_history'] = isset($_POST['sports_history']) && !empty(trim($_POST['sports_history'])) ? sanitize_textarea_field($_POST['sports_history']) : NULL;
       $data['additional_info'] = isset($_POST['additional_info']) && !empty(trim($_POST['additional_info'])) ? sanitize_textarea_field($_POST['additional_info']) : NULL;
                    
        $player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;

        // بروزرسانی
        if ($player_id) {
            // آماده‌سازی format برای update
            $format = [];
            foreach ($data as $key => $value) {
                if ($value === NULL) {
                    $format[] = '%s'; // NULL
                } elseif (in_array($key, ['health_verified', 'info_verified', 'is_active', 'user_id'])) {
                    $format[] = '%d'; // integer
                } elseif (in_array($key, ['price', 'capacity', 'sessions_count'])) {
                    $format[] = '%d'; // integer (برای دوره‌ها)
                } else {
                    $format[] = '%s'; // string
                }
            }
            
            $updated = $wpdb->update(
                $table_name,
                $data,
                ['id' => $player_id],
                $format,
                ['%d']
            );

            if ($updated !== false) {
                // ذخیره دوره‌های بازیکن
                $course_ids = isset($_POST['courses']) && is_array($_POST['courses']) ? array_map('absint', $_POST['courses']) : [];
                $course_flags_raw = isset($_POST['course_flags']) && is_array($_POST['course_flags']) ? $_POST['course_flags'] : [];
                $course_flags = [];
                foreach ($course_flags_raw as $course_id => $flags) {
                    $course_id_int = absint($course_id);
                    $flags_array = [];
                    if (isset($flags['paused']) && $flags['paused'] == '1') {
                        $flags_array[] = 'paused';
                    }
                    if (isset($flags['completed']) && $flags['completed'] == '1') {
                        $flags_array[] = 'completed';
                    }
                    if (isset($flags['canceled']) && $flags['canceled'] == '1') {
                        $flags_array[] = 'canceled';
                    }
                    $course_flags[$course_id_int] = $flags_array;
                }
                sc_save_member_courses($player_id, $course_ids, $course_flags);
                
                // به‌روزرسانی وضعیت تکمیل پروفایل
                sc_update_profile_completed_status($player_id);
                
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=updated&player_id=' . $player_id));
                exit;
            } else {
                // نمایش خطای دیتابیس برای دیباگ
                if ($wpdb->last_error) {
                    error_log('WP Update Error: ' . $wpdb->last_error);
                    error_log('WP Last Query: ' . $wpdb->last_query);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=update_error&player_id=' . $player_id));
                exit;
            }
        } 
        // اضافه کردن جدید
        else {
            // بررسی تکراری بودن کد ملی
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE national_id = %s",
                $data['national_id']
            ));
            
            if ($existing) {
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
                exit;
            }
            
            $inserted = $wpdb->insert($table_name, $data);

            if ($inserted !== false) {
                $insert_id = $wpdb->insert_id;
                
                // ذخیره دوره‌های بازیکن
                $course_ids = isset($_POST['courses']) && is_array($_POST['courses']) ? array_map('absint', $_POST['courses']) : [];
                $course_statuses_raw = isset($_POST['course_status']) && is_array($_POST['course_status']) ? $_POST['course_status'] : [];
                $course_statuses = [];
                foreach ($course_statuses_raw as $course_id => $status) {
                    $course_statuses[absint($course_id)] = sanitize_text_field($status);
                }
                sc_save_member_courses($insert_id, $course_ids, $course_statuses);
                
                // به‌روزرسانی وضعیت تکمیل پروفایل
                sc_update_profile_completed_status($insert_id);
                
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_true&player_id=' . $insert_id));
                exit;
            } else {
                // نمایش خطای دیتابیس برای دیباگ
                if ($wpdb->last_error) {
                    error_log('WP Insert Error: ' . $wpdb->last_error);
                    error_log('WP Last Query: ' . $wpdb->last_query);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
                exit;
            }
        }
    }

}

/**
 * Save member courses
 */
function sc_save_member_courses($member_id, $course_ids, $course_flags = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';
    
    // غیرفعال کردن دوره‌هایی که دیگر انتخاب نشده‌اند
    if (!empty($course_ids) && is_array($course_ids)) {
        $course_ids_safe = array_map('absint', $course_ids);
        $course_ids_imploded = implode(',', $course_ids_safe);
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'inactive', updated_at = %s 
             WHERE member_id = %d 
             AND course_id NOT IN ($course_ids_imploded)",
            current_time('mysql'),
            $member_id
        ));
    } else {
        // اگر هیچ دوره‌ای انتخاب نشده، همه را inactive کن
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'inactive', updated_at = %s 
             WHERE member_id = %d",
            current_time('mysql'),
            $member_id
        ));
    }
    
    // افزودن یا به‌روزرسانی دوره‌های جدید
    if (!empty($course_ids) && is_array($course_ids)) {
        foreach ($course_ids as $course_id) {
            $course_id = absint($course_id);
            if ($course_id) {
                // دریافت flags از آرایه course_flags
                $flags_array = isset($course_flags[$course_id]) && is_array($course_flags[$course_id]) 
                    ? $course_flags[$course_id] 
                    : [];
                
                // تبدیل flags به string (مثلاً "paused,completed")
                $flags_string = !empty($flags_array) ? implode(',', array_map('sanitize_text_field', $flags_array)) : NULL;
                
                // بررسی وجود قبلی
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE member_id = %d AND course_id = %d",
                    $member_id,
                    $course_id
                ));
                
                if ($existing) {
                    // به‌روزرسانی وضعیت
                    $update_result = $wpdb->update(
                        $table_name,
                        [
                            'status' => 'active',
                            'course_status_flags' => $flags_string,
                            'enrollment_date' => current_time('Y-m-d'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['id' => $existing],
                        ['%s', '%s', '%s', '%s'],
                        ['%d']
                    );
                    
                    if ($update_result === false && $wpdb->last_error) {
                        error_log('SC Update Member Course Error: ' . $wpdb->last_error);
                        error_log('SC Update Member Course Query: ' . $wpdb->last_query);
                    }
                } else {
                    // افزودن جدید
                    $insert_result = $wpdb->insert(
                        $table_name,
                        [
                            'member_id' => $member_id,
                            'course_id' => $course_id,
                            'enrollment_date' => current_time('Y-m-d'),
                            'status' => 'active',
                            'course_status_flags' => $flags_string,
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
                    );
                    
                    if ($insert_result === false && $wpdb->last_error) {
                        error_log('SC Insert Member Course Error: ' . $wpdb->last_error);
                        error_log('SC Insert Member Course Query: ' . $wpdb->last_query);
                    }
                }
            }
        }
    }
}
//callback display list member in 
function procces_table_data(){
  // بررسی و ایجاد جداول در صورت عدم وجود
  sc_check_and_create_tables();
  
  include SC_TEMPLATES_ADMIN_DIR . 'members-list.php';
  $GLOBALS['player_list_table'] = new Player_List_Table();
  $GLOBALS['player_list_table']->prepare_items();
}
add_action('admin_notices','sc_sprot_notices');
function sc_sprot_notices(){
        $type='';
        $messege='';
        if(isset($_GET['sc_status'])){
        $status=sanitize_text_field($_GET['sc_status']);
        if($status == 'add_true'){
            $type='success';
            $messege="بازیکن با موفقیت اضافه شد";

        }
        if($status == 'add_error'){
            $type='error';
            $messege=" اخطار:  بازیکن اضافه نشد لطفا فیلد های ورودی رو بررسی کنید و دوباره تلاش کنید.";

        }
        if($status == 'updated'){
            $type='success';
            $messege="اطلاعات بازیکن به درستی بروزرسانی شد.";

        }
        if($status == 'update_error'){
            $type='error';
            $messege="خطا در بروزرسانی اطلاعات بازیکن ";

        }
        if($status == 'deleted'){
            $type='success';
            $messege="بازیکن مورد نظر شما حذف شد";

        }
        if($status == 'delete_error'){
            $type='error';
            $messege="خطا در حذف بازیکن ";

        }
        if($status == 'bulk_deleted'){
            $type='success';
            $messege="رکورد های انتخابی مورد نظر با موفقیت حذف شد";

        }
        // Course messages
        if($status == 'course_add_true'){
            $type='success';
            $messege="دوره با موفقیت اضافه شد";
        }
        if($status == 'course_add_error'){
            $type='error';
            $messege="خطا: دوره اضافه نشد لطفا فیلدهای ورودی را بررسی کنید.";
        }
        if($status == 'course_updated'){
            $type='success';
            $messege="اطلاعات دوره به درستی بروزرسانی شد.";
        }
        if($status == 'course_update_error'){
            $type='error';
            $messege="خطا در بروزرسانی اطلاعات دوره";
        }
        if($status == 'course_deleted'){
            $type='success';
            $messege="دوره به زباله‌دان منتقل شد";
        }
        if($status == 'course_restored'){
            $type='success';
            $messege="دوره از زباله‌دان بازیابی شد";
        }
        if($status == 'course_bulk_deleted'){
            $type='success';
            $messege="دوره‌های انتخابی به زباله‌دان منتقل شدند";
        }
        if($status == 'courses_activated'){
            $type='success';
            $messege="دوره‌های انتخابی با موفقیت فعال شدند";
        }
        if($status == 'courses_deactivated'){
            $type='success';
            $messege="دوره‌های انتخابی با موفقیت غیرفعال شدند";
        }
        // Invoice messages
        if($status == 'bulk_status_updated'){
            $type='success';
            $messege="وضعیت صورت حساب‌های انتخابی با موفقیت به‌روزرسانی شد";
        }
        if($status == 'bulk_deleted'){
            $type='success';
            $messege="صورت حساب‌های انتخابی با موفقیت حذف شدند";
        }
        if($status == 'invoice_add_true'){
            $type='success';
            $messege="صورت حساب با موفقیت ایجاد شد";
        }
        if($status == 'invoice_add_error'){
            $type='error';
            $messege="خطا در ایجاد صورت حساب. لطفاً فیلدهای ورودی را بررسی کنید.";
        }
    }
        if($type && $messege){
            ?>
                <div class="notice notice-<?php echo $type; ?> is-dismissible">
                    <p><?php echo $messege; ?></p>
                </div>
            <?php
        }

}
add_action('wp_ajax_get_player_details', 'get_player_details');
function get_player_details(){
    $id = intval($_POST['id']);
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $player = $wpdb->get_row("SELECT * FROM $table WHERE id=$id", ARRAY_A);
    if(!$player) {
        echo "بازیکن یافت نشد.";
        wp_die();
    }
     wp_send_json_success($player);
}

/**
 * Get active course members (AJAX handler)
 */
add_action('wp_ajax_get_course_active_users', 'get_course_active_users');
function get_course_active_users() {
    $course_id = intval($_POST['course_id']);
    
    if (!$course_id) {
        wp_send_json_error(['message' => 'شناسه دوره معتبر نیست.']);
        return;
    }
    
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    
    // دریافت کاربران فعال دوره (status = 'active' و بدون flags)
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.first_name, m.last_name, m.national_id, m.player_phone, 
                m.father_name, m.father_phone, m.created_at, mc.enrollment_date
         FROM $member_courses_table mc
         INNER JOIN $members_table m ON mc.member_id = m.id
         WHERE mc.course_id = %d
         AND mc.status = 'active'
         AND (
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE '%%paused%%'
                 AND mc.course_status_flags NOT LIKE '%%completed%%'
                 AND mc.course_status_flags NOT LIKE '%%canceled%%'
             )
         )
         ORDER BY m.last_name ASC, m.first_name ASC",
        $course_id
    ), ARRAY_A);
    
    if (empty($users) || !is_array($users)) {
        wp_send_json_success([
            'users' => [],
            'count' => 0,
            'message' => 'هیچ کاربر فعالی در این دوره یافت نشد.'
        ]);
        return;
    }
    
    // تبدیل تاریخ‌ها به شمسی
    foreach ($users as &$user) {
        if (!empty($user['enrollment_date'])) {
            $user['enrollment_date_shamsi'] = sc_date_shamsi_date_only($user['enrollment_date']);
        } else {
            $user['enrollment_date_shamsi'] = '-';
        }
        if (!empty($user['created_at'])) {
            $user['created_at_shamsi'] = sc_date_shamsi_date_only($user['created_at']);
        } else {
            $user['created_at_shamsi'] = '-';
        }
    }
    unset($user);
    
    wp_send_json_success([
        'users' => $users,
        'count' => count($users),
        'course_id' => $course_id
    ]);
}




