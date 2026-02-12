<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

sc_check_and_create_tables();

global $wpdb;
$honors_table = $wpdb->prefix . 'sc_honors';
$categories_table = $wpdb->prefix . 'sc_honor_categories';
$members_table = $wpdb->prefix . 'sc_members';

// پردازش فرم افزودن افتخار
$message = '';
$message_type = '';

if (isset($_POST['save_honor']) && check_admin_referer('save_honor_for_member_nonce')) {
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $honor_name = isset($_POST['honor_name']) ? sanitize_text_field($_POST['honor_name']) : '';
    $honor_category = isset($_POST['honor_category']) ? absint($_POST['honor_category']) : 0;
    $honor_description = isset($_POST['honor_description']) ? sanitize_textarea_field($_POST['honor_description']) : '';
    
    if (empty($member_id)) {
        $message = 'لطفاً بازیکن را انتخاب کنید.';
        $message_type = 'error';
    } elseif (empty($honor_name)) {
        $message = 'لطفاً عنوان افتخار را وارد کنید.';
        $message_type = 'error';
    } elseif (empty($honor_category)) {
        $message = 'لطفاً دسته را انتخاب کنید.';
        $message_type = 'error';
    } else {
        // بررسی وجود دسته
        $category_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE id = %d",
            $honor_category
        ));
        
        if (!$category_exists) {
            $message = 'دسته انتخاب شده معتبر نیست.';
            $message_type = 'error';
        } else {
            // پردازش آپلود فایل
            $file_url = null;
            
            if (isset($_FILES['honor_file']) && $_FILES['honor_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['honor_file'];
                
                // اعتبارسنجی نوع فایل
                $allowed_mimes = [
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
                    'application/pdf',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ];
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
                
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $file_mime = $file['type'];
                
                // بررسی نوع فایل
                if (!in_array($file_mime, $allowed_mimes) && !in_array($file_ext, $allowed_extensions)) {
                    $message = 'نوع فایل معتبر نیست. فقط تصاویر، PDF، Word و Excel مجاز است.';
                    $message_type = 'error';
                } elseif ($file['size'] > 1 * 1024 * 1024) {
                    $message = 'حجم فایل بیش از 1 مگابایت است.';
                    $message_type = 'error';
                } else {
                    // آپلود فایل
                    $upload_dir = wp_upload_dir();
                    $sc_upload_dir = $upload_dir['basedir'] . '/sportclub-honors';
                    if (!file_exists($sc_upload_dir)) {
                        wp_mkdir_p($sc_upload_dir);
                    }
                    
                    $member = $wpdb->get_row($wpdb->prepare("SELECT id FROM $members_table WHERE id = %d", $member_id));
                    $unique_filename = wp_unique_filename($sc_upload_dir, sanitize_file_name($member_id . '_' . time() . '_' . $file['name']));
                    $file_path = $sc_upload_dir . '/' . $unique_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        $file_url = $upload_dir['baseurl'] . '/sportclub-honors/' . $unique_filename;
                    }
                }
            }
            
            if ($message_type !== 'error') {
                // ذخیره افتخار
                $inserted = $wpdb->insert(
                    $honors_table,
                    [
                        'member_id' => $member_id,
                        'coach_id' => null,
                        'name' => $honor_name,
                        'category_id' => $honor_category,
                        'description' => $honor_description ?: null,
                        'file_url' => $file_url,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
                );
                
                if ($inserted) {
                    $message = 'افتخار با موفقیت افزوده شد.';
                    $message_type = 'success';
                    // پاک کردن فرم
                    $_POST = [];
                } else {
                    $message = 'خطا در افزودن افتخار.';
                    $message_type = 'error';
                }
            }
        }
    }
}

// دریافت لیست دسته‌ها
$categories = $wpdb->get_results("SELECT id, name FROM $categories_table ORDER BY name ASC");

// دریافت لیست بازیکنان
$members = $wpdb->get_results("SELECT id, first_name, last_name FROM $members_table ORDER BY last_name ASC, first_name ASC");

?>
<div class="wrap">
    <h1>افزودن افتخار برای بازیکن</h1>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>افزودن افتخار برای بازیکن</h2>
        
        <?php if ($message) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible" style="margin-top: 15px;">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('save_honor_for_member_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="member_id">بازیکن <span style="color: red;">*</span></label>
                    </th>
                    <td>
                        <select name="member_id" id="member_id" class="regular-text" required style="width: 100%;">
                            <option value="">انتخاب بازیکن</option>
                            <?php foreach ($members as $member) : ?>
                                <option value="<?php echo esc_attr($member->id); ?>" <?php selected(isset($_POST['member_id']) ? $_POST['member_id'] : '', $member->id); ?>>
                                    <?php echo esc_html($member->first_name . ' ' . $member->last_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="honor_name">نام افتخار <span style="color: red;">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="honor_name" id="honor_name" class="regular-text" required style="width: 100%;" value="<?php echo isset($_POST['honor_name']) ? esc_attr($_POST['honor_name']) : ''; ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="honor_category">دسته <span style="color: red;">*</span></label>
                    </th>
                    <td>
                        <select name="honor_category" id="honor_category" class="regular-text" required style="width: 100%;">
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected(isset($_POST['honor_category']) ? $_POST['honor_category'] : '', $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="honor_description">توضیحات</label>
                    </th>
                    <td>
                        <textarea name="honor_description" id="honor_description" rows="4" class="large-text"><?php echo isset($_POST['honor_description']) ? esc_textarea($_POST['honor_description']) : ''; ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="honor_file">فایل</label>
                    </th>
                    <td>
                        <div class="sc-file-upload-wrapper-admin" style="position: relative;">
                            <input type="file" 
                                   name="honor_file" 
                                   id="honor_file" 
                                   class="sc-honor-file-input-admin" 
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                   style="position: absolute; opacity: 0; width: 0; height: 0;">
                            <button type="button" 
                                    class="sc-file-upload-btn-admin button"
                                    style="padding: 8px 16px; font-size: 13px; background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(34,113,177,0.2);">
                                <span class="btn-text-admin">📎 انتخاب فایل</span>
                            </button>
                        </div>
                        <p class="description">حداکثر 1 مگابایت - تصاویر، PDF، Word، Excel</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_honor" class="button button-primary" value="ذخیره افتخار">
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // کلیک روی دکمه فایل
    $('.sc-file-upload-btn-admin').on('click', function() {
        $('#honor_file').click();
    });
    
    // تغییر فایل انتخاب شده - فقط بررسی اندازه (بدون پیش نمایش)
    $('#honor_file').on('change', function() {
        const file = this.files[0];
        
        if (file) {
            // بررسی اندازه فایل (1MB)
            const maxSize = 1 * 1024 * 1024; // 1MB
            if (file.size > maxSize) {
                alert('حجم فایل بیش از 1 مگابایت است. لطفاً فایل کوچکتری انتخاب کنید.');
                $(this).val('');
                return;
            }
        }
    });
    
    // Hover effect
    $('.sc-file-upload-btn-admin').on('mouseenter', function() {
        $(this).css({
            'transform': 'translateY(-2px)',
            'box-shadow': '0 4px 8px rgba(34,113,177,0.3)'
        });
    }).on('mouseleave', function() {
        $(this).css({
            'transform': 'translateY(0)',
            'box-shadow': '0 2px 4px rgba(34,113,177,0.2)'
        });
    });
});
</script>
