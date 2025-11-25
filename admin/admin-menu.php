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

    add_action('load-'. $add_member_sufix , 'callback_add_member_sufix');
    add_action('load-'. $list_member_sufix , 'procces_table_data');
    add_action('load-'. $list_courses_sufix , 'procces_courses_table_data');
    add_action('load-'. $add_course_sufix , 'callback_add_course_sufix');
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
    echo "تنظیمات افزونه";
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
        
        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'description' => isset($_POST['description']) && !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : NULL,
            'price' => floatval($_POST['price']),
            'capacity' => !empty($_POST['capacity']) ? intval($_POST['capacity']) : NULL,
            'sessions_count' => !empty($_POST['sessions_count']) ? intval($_POST['sessions_count']) : NULL,
            'start_date' => !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : NULL,
            'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : NULL,
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
                sc_save_member_courses($player_id, $course_ids);
                
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
                sc_save_member_courses($insert_id, $course_ids);
                
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
function sc_save_member_courses($member_id, $course_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';
    
    // غیرفعال کردن دوره‌های قبلی
    $wpdb->update(
        $table_name,
        ['status' => 'inactive', 'updated_at' => current_time('mysql')],
        ['member_id' => $member_id, 'status' => 'active'],
        ['%s', '%s'],
        ['%d', '%s']
    );
    
    // افزودن دوره‌های جدید
    if (!empty($course_ids) && is_array($course_ids)) {
        foreach ($course_ids as $course_id) {
            $course_id = absint($course_id);
            if ($course_id) {
                // بررسی وجود قبلی
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE member_id = %d AND course_id = %d",
                    $member_id,
                    $course_id
                ));
                
                if ($existing) {
                    // فعال کردن مجدد
                    $wpdb->update(
                        $table_name,
                        [
                            'status' => 'active',
                            'enrollment_date' => current_time('Y-m-d'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['id' => $existing],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                } else {
                    // افزودن جدید
                    $wpdb->insert(
                        $table_name,
                        [
                            'member_id' => $member_id,
                            'course_id' => $course_id,
                            'enrollment_date' => current_time('Y-m-d'),
                            'status' => 'active',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s', '%s']
                    );
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




