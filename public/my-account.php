<?php

/**
 * WooCommerce My Account - اطلاعات بازیکن Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom tab to WooCommerce My Account
 */
add_filter('woocommerce_account_menu_items', 'sc_add_my_account_menu_item');
function sc_add_my_account_menu_item($items) {
    // مخفی کردن تب برای مدیران
    if (current_user_can('manage_options')) {
        return $items;
    }
    
    // Insert before logout
    $logout = $items['customer-logout'];
    unset($items['customer-logout']);
    
    $items['sc-submit-documents'] = 'اطلاعات بازیکن';
    $items['customer-logout'] = $logout;
    
    return $items;
}

/**
 * Register endpoint for custom tab
 */
add_action('init', 'sc_add_my_account_endpoint');
function sc_add_my_account_endpoint() {
    add_rewrite_endpoint('sc-submit-documents', EP_ROOT | EP_PAGES);
}

/**
 * Add query vars
 */
add_filter('query_vars', 'sc_add_my_account_query_vars', 0);
function sc_add_my_account_query_vars($vars) {
    $vars[] = 'sc-submit-documents';
    return $vars;
}

/**
 * Set endpoint title
 */
add_filter('woocommerce_endpoint_sc-submit-documents_title', 'sc_my_account_endpoint_title');
function sc_my_account_endpoint_title($title) {
    return 'اطلاعات بازیکن';
}

/**
 * نمایش پیام در بالای صفحه My Account برای کاربرانی که پروفایل ناقص دارند
 */
add_action('woocommerce_account_content', 'sc_display_incomplete_profile_message', 5);
function sc_display_incomplete_profile_message() {
    // بررسی لاگین بودن کاربر
    if (!is_user_logged_in()) {
        return;
    }
    
    // مخفی کردن پیام برای مدیران
    if (current_user_can('manage_options')) {
        return;
    }
    
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);
    
    // بررسی وجود اطلاعات بازیکن بر اساس user_id
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر پیدا نشد، بر اساس شماره تماس بررسی می‌کنیم
    if (!$player && $billing_phone) {
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
            $billing_phone
        ));
    }
    
    // بررسی تکمیل بودن پروفایل و نمایش پیام
    $should_show_message = false;
    if ($player) {
        $is_completed = sc_check_profile_completed($player->id);
        // به‌روزرسانی وضعیت در دیتابیس
        sc_update_profile_completed_status($player->id);
        
        if (!$is_completed) {
            $should_show_message = true;
        }
    } else {
        // اگر کاربر اصلاً در جدول اعضا وجود ندارد، هم پیام نمایش بده
        $should_show_message = true;
    }
    
    if ($should_show_message) {
        $profile_url = wc_get_account_endpoint_url('sc-submit-documents');
        ?>
        <div class="sc-incomplete-profile-message" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <strong style="display: block; margin-bottom: 8px;">⚠️ اطلاعات شما تکمیل نیست</strong>
            <p style="margin: 0;">
                لطفاً <a href="<?php echo esc_url($profile_url); ?>" style="color: #856404; text-decoration: underline; font-weight: bold;">اطلاعات پروفایل</a> را تکمیل کنید.
            </p>
        </div>
        <?php
    }
}

/**
 * Display content for custom tab
 */
add_action('woocommerce_account_sc-submit-documents_endpoint', 'sc_my_account_documents_content');
function sc_my_account_documents_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی لاگین بودن کاربر
    if (!is_user_logged_in()) {
        echo '<p>لطفاً ابتدا وارد حساب کاربری خود شوید.</p>';
        return;
    }
    
    // مخفی کردن محتوا برای مدیران
    if (current_user_can('manage_options')) {
        echo '<p>این بخش فقط برای کاربران عادی در دسترس است.</p>';
        return;
    }
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);
    
    // بررسی وجود اطلاعات بازیکن بر اساس user_id
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر پیدا نشد، بر اساس شماره تماس بررسی می‌کنیم
    if (!$player && $billing_phone) {
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
            $billing_phone
        ));
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'submit-documents.php';
}

/**
 * Handle form submission
 */
add_action('template_redirect', 'sc_handle_documents_submission');
function sc_handle_documents_submission() {
    if (!is_user_logged_in() || !isset($_POST['sc_submit_documents'])) {
        return;
    }
    
    // بررسی nonce
    if (!isset($_POST['sc_documents_nonce']) || !wp_verify_nonce($_POST['sc_documents_nonce'], 'sc_submit_documents')) {
        wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // Validation
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['national_id'])) {
        wc_add_notice('لطفاً فیلدهای اجباری را پر کنید.', 'error');
        return;
    }
    
    // آماده‌سازی داده‌ها
    $data = [
        'user_id'              => $current_user_id,
        'first_name'           => sanitize_text_field($_POST['first_name']),
        'last_name'            => sanitize_text_field($_POST['last_name']),
        'national_id'          => sanitize_text_field($_POST['national_id']),
        'health_verified'      => 0,
        'info_verified'        => 0,
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
    $data['medical_condition'] = isset($_POST['medical_condition']) && !empty(trim($_POST['medical_condition'])) ? sanitize_textarea_field($_POST['medical_condition']) : NULL;
    $data['sports_history'] = isset($_POST['sports_history']) && !empty(trim($_POST['sports_history'])) ? sanitize_textarea_field($_POST['sports_history']) : NULL;
    $data['additional_info'] = isset($_POST['additional_info']) && !empty(trim($_POST['additional_info'])) ? sanitize_textarea_field($_POST['additional_info']) : NULL;
    
    // برای checkbox ها: اگر تیک نخورده باشد، 0 ذخیره می‌شود
    $data['health_verified'] = isset($_POST['health_verified']) && !empty($_POST['health_verified']) ? 1 : 0;
    $data['info_verified'] = isset($_POST['info_verified']) && !empty($_POST['info_verified']) ? 1 : 0;
   
    // بررسی وجود اطلاعات قبلی بر اساس user_id یا کد ملی
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d OR national_id = %s LIMIT 1",
        $current_user_id,
        $data['national_id']
    ));
    
    // پردازش آپلود عکس‌ها با امنیت
    $uploaded_files = sc_handle_secure_file_upload($current_user_id);
    if ($uploaded_files) {
        if (isset($uploaded_files['personal_photo'])) {
            $data['personal_photo'] = $uploaded_files['personal_photo'];
        }
        if (isset($uploaded_files['id_card_photo'])) {
            $data['id_card_photo'] = $uploaded_files['id_card_photo'];
        }
        if (isset($uploaded_files['sport_insurance_photo'])) {
            $data['sport_insurance_photo'] = $uploaded_files['sport_insurance_photo'];
        }
    }
    // اگر فایلی آپلود نشده و در حالت update هستیم، فیلدهای عکس را در update_data اضافه نمی‌کنیم
    // تا عکس‌های قبلی حفظ شوند
    
    if ($existing) {
        // بروزرسانی - تمام فیلدها (حتی اگر خالی باشند)
        $update_data = $data;
        // حذف created_at از update
        unset($update_data['created_at']);
        $update_data['updated_at'] = current_time('mysql');
        
        // اگر user_id وجود نداشت، اضافه می‌کنیم
        if (!$existing->user_id) {
            $update_data['user_id'] = $current_user_id;
        }
        
        // آماده‌سازی format برای update
        $format = [];
        foreach ($update_data as $key => $value) {
            if ($value === NULL) {
                $format[] = '%s'; // NULL
            } elseif (in_array($key, ['health_verified', 'info_verified', 'is_active', 'user_id'])) {
                $format[] = '%d'; // integer
            } else {
                $format[] = '%s'; // string
            }
        }
        
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $existing->id],
            $format,
            ['%d']
        );
        
        if ($updated !== false) {
            // به‌روزرسانی وضعیت تکمیل پروفایل
            sc_update_profile_completed_status($existing->id);
            
            wc_add_notice('اطلاعات شما با موفقیت به روز شد.', 'success');
            // ریدایرکت برای جلوگیری از ارسال مجدد فرم
            wp_safe_redirect(wc_get_account_endpoint_url('sc-submit-documents'));
            exit;
        } else {
            if ($wpdb->last_error) {
                error_log('WP Update Error: ' . $wpdb->last_error);
            }
            wc_add_notice('خطا در بروزرسانی اطلاعات. لطفاً دوباره تلاش کنید.', 'error');
        }
    } else {
        // بررسی تکراری بودن کد ملی
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE national_id = %s",
            $data['national_id']
        ));
        
        if ($duplicate) {
            wc_add_notice('این کد ملی قبلاً ثبت شده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }
        
        // افزودن جدید
        $inserted = $wpdb->insert($table_name, $data);
        
        if ($inserted !== false) {
            $insert_id = $wpdb->insert_id;
            
            // به‌روزرسانی وضعیت تکمیل پروفایل
            sc_update_profile_completed_status($insert_id);
            
            wc_add_notice('اطلاعات شما با موفقیت ثبت شد.', 'success');
            // ریدایرکت برای جلوگیری از ارسال مجدد فرم
            wp_safe_redirect(wc_get_account_endpoint_url('sc-submit-documents'));
            exit;
        } else {
            if ($wpdb->last_error) {
                error_log('WP Insert Error: ' . $wpdb->last_error);
                error_log('WP Last Query: ' . $wpdb->last_query);
            }
            wc_add_notice('خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.', 'error');
        }
    }
}

/**
 * Handle secure file upload
 */
function sc_handle_secure_file_upload($user_id) {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $uploaded_files = [];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $file_fields = [
        'personal_photo' => 'عکس پرسنلی',
        'id_card_photo' => 'عکس کارت ملی',
        'sport_insurance_photo' => 'عکس بیمه ورزشی'
    ];
    
    foreach ($file_fields as $field_name => $field_label) {
        if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $file = $_FILES[$field_name];
        
        // بررسی نوع فایل
        $mime_type = $file['type'];
        
        if (!in_array($mime_type, $allowed_types)) {
            wc_add_notice("نوع فایل $field_label معتبر نیست. فقط تصاویر (JPG, PNG, GIF, WEBP) مجاز است.", 'error');
            continue;
        }
        
        // بررسی اندازه فایل
        if ($file['size'] > $max_file_size) {
            wc_add_notice("حجم فایل $field_label بیش از 5 مگابایت است.", 'error');
            continue;
        }
        
        // بررسی محتوای فایل (امنیت)
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            wc_add_notice("فایل $field_label یک تصویر معتبر نیست.", 'error');
            continue;
        }
        
        // تنظیمات آپلود
        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            ],
            'unique_filename_callback' => function($dir, $name, $ext) use ($user_id, $field_name) {
                // ایجاد نام فایل امن
                $safe_name = sanitize_file_name($user_id . '_' . $field_name . '_' . time() . $ext);
                return $safe_name;
            }
        ];
        
        // آپلود فایل
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            $uploaded_files[$field_name] = $movefile['url'];
        } else {
            wc_add_notice("خطا در آپلود $field_label: " . (isset($movefile['error']) ? $movefile['error'] : 'خطای ناشناخته'), 'error');
        }
    }
    
    return $uploaded_files;
}
