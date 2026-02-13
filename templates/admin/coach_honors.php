<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

sc_check_and_create_tables();

global $wpdb;
$honors_table = $wpdb->prefix . 'sc_honors';
$categories_table = $wpdb->prefix . 'sc_honor_categories';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$members_table = $wpdb->prefix . 'sc_members';

// دریافت شناسه مربی لاگین شده
$current_user_id = get_current_user_id();
$coach = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
    $current_user_id
));

if (!$coach) {
    echo '<div class="wrap"><div class="notice notice-error"><p>شما به عنوان مربی ثبت نشده‌اید.</p></div></div>';
    return;
}

$coach_id = $coach->id;

// پردازش فرم افزودن افتخار
$message = '';
$message_type = '';

// پردازش حذف تکی افتخار مربی
if (isset($_POST['delete_single_honor']) && check_admin_referer('delete_single_coach_honor_nonce')) {
    $honor_id = isset($_POST['honor_id']) ? absint($_POST['honor_id']) : 0;
    
    if ($honor_id > 0) {
        // بررسی اینکه افتخار متعلق به این مربی است
        $honor = $wpdb->get_row($wpdb->prepare(
            "SELECT id, file_url FROM $honors_table WHERE id = %d AND coach_id = %d",
            $honor_id,
            $coach_id
        ));
        
        if ($honor) {
            // حذف فایل اگر وجود دارد
            if (!empty($honor->file_url)) {
                $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $honor->file_url);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            // حذف رکورد
            $wpdb->delete($honors_table, ['id' => $honor_id], ['%d']);
            $message = 'افتخار با موفقیت حذف شد.';
            $message_type = 'success';
        } else {
            $message = 'افتخار یافت نشد یا شما دسترسی به حذف آن را ندارید.';
            $message_type = 'error';
        }
        
        wp_safe_redirect(add_query_arg('honor_deleted', '1', admin_url('admin.php?page=sc-coach-honors')));
        exit;
    }
}

// نمایش پیام موفقیت پس از redirect
if (isset($_GET['honor_deleted']) && $_GET['honor_deleted'] == '1') {
    $message = 'افتخار با موفقیت حذف شد.';
    $message_type = 'success';
}

if (isset($_POST['save_honors']) && check_admin_referer('save_coach_honors_nonce')) {
    $honors_data = isset($_POST['honors']) && is_array($_POST['honors']) ? $_POST['honors'] : [];
    
    if (empty($honors_data)) {
        $message = 'لطفاً حداقل یک افتخار را وارد کنید.';
        $message_type = 'error';
    } else {
        $saved_count = 0;
        $errors = [];
        
        foreach ($honors_data as $index => $honor) {
            $honor_name = isset($honor['name']) ? sanitize_text_field($honor['name']) : '';
            $honor_category = isset($honor['category_id']) ? absint($honor['category_id']) : 0;
            $honor_description = isset($honor['description']) ? sanitize_textarea_field($honor['description']) : '';
            
            // اعتبارسنجی
            if (empty($honor_name)) {
                $errors[] = 'ردیف ' . ($index + 1) . ': عنوان افتخار الزامی است.';
                continue;
            }
            
            if (empty($honor_category)) {
                $errors[] = 'ردیف ' . ($index + 1) . ': انتخاب دسته الزامی است.';
                continue;
            }
            
            // بررسی وجود دسته
            $category_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $categories_table WHERE id = %d",
                $honor_category
            ));
            
            if (!$category_exists) {
                $errors[] = 'ردیف ' . ($index + 1) . ': دسته انتخاب شده معتبر نیست.';
                continue;
            }
            
            // پردازش آپلود فایل
            $file_url = null;
            $file_field_name = 'honor_file_' . $index;
            
            if (isset($_FILES[$file_field_name]) && $_FILES[$file_field_name]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$file_field_name];
                
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
                    $errors[] = 'ردیف ' . ($index + 1) . ': نوع فایل معتبر نیست.';
                    continue;
                }
                
                // بررسی اندازه فایل (حداکثر 1 مگابایت)
                $max_file_size = 1 * 1024 * 1024; // 1MB
                if ($file['size'] > $max_file_size) {
                    $errors[] = 'ردیف ' . ($index + 1) . ': حجم فایل بیش از 1 مگابایت است.';
                    continue;
                }
                
                // آپلود فایل
                $upload_dir = wp_upload_dir();
                $sc_upload_dir = $upload_dir['basedir'] . '/sportclub-honors';
                if (!file_exists($sc_upload_dir)) {
                    wp_mkdir_p($sc_upload_dir);
                }
                
                $unique_filename = wp_unique_filename($sc_upload_dir, sanitize_file_name($coach_id . '_coach_' . time() . '_' . $file['name']));
                $file_path = $sc_upload_dir . '/' . $unique_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $file_url = $upload_dir['baseurl'] . '/sportclub-honors/' . $unique_filename;
                } else {
                    $errors[] = 'ردیف ' . ($index + 1) . ': خطا در آپلود فایل.';
                    continue;
                }
            }
            
            // ذخیره افتخار برای مربی
            $insert_data = [
                'member_id' => null,
                'coach_id' => $coach_id,
                'name' => $honor_name,
                'category_id' => $honor_category,
                'description' => $honor_description ?: null,
                'file_url' => $file_url ?: null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // آماده‌سازی format array
            $insert_formats = [];
            foreach ($insert_data as $key => $value) {
                if ($value === null) {
                    $insert_formats[] = '%s'; // NULL values
                } elseif (in_array($key, ['member_id', 'coach_id', 'category_id'])) {
                    $insert_formats[] = '%d'; // integer
                } else {
                    $insert_formats[] = '%s'; // string
                }
            }
            
            $inserted = $wpdb->insert(
                $honors_table,
                $insert_data,
                $insert_formats
            );
            
            if ($inserted !== false) {
                $saved_count++;
            } else {
                $db_error = $wpdb->last_error ? $wpdb->last_error : 'خطای نامشخص دیتابیس';
                $errors[] = 'ردیف ' . ($index + 1) . ': خطا در ذخیره افتخار. ' . $db_error;
            }
        }
        
        if ($saved_count > 0 && empty($errors)) {
            // Redirect برای جلوگیری از ثبت تکراری
            wp_safe_redirect(add_query_arg('honor_saved', '1', admin_url('admin.php?page=sc-coach-honors')));
            exit;
        }
        
        if ($saved_count > 0) {
            $message = $saved_count . ' افتخار با موفقیت ذخیره شد.';
            $message_type = 'success';
        }
        
        if (!empty($errors)) {
            $message .= (!empty($message) ? '<br>' : '') . implode('<br>', $errors);
            if ($message_type !== 'success') {
                $message_type = 'error';
            }
        }
    }
}

// نمایش پیام موفقیت پس از redirect
if (isset($_GET['honor_saved']) && $_GET['honor_saved'] == '1') {
    $message = 'افتخار با موفقیت ذخیره شد.';
    $message_type = 'success';
}

// دریافت لیست دسته‌ها
$categories = $wpdb->get_results("SELECT id, name FROM $categories_table ORDER BY name ASC");

// Pagination برای افتخارات مربی
$per_page = 10;
$current_page = isset($_GET['honors_page']) ? max(1, absint($_GET['honors_page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// شمارش کل افتخارات مربی
$total_honors = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) 
     FROM $honors_table 
     WHERE coach_id = %d",
    $coach_id
));

// دریافت افتخارات مربی با صفحه‌بندی
$honors = $wpdb->get_results($wpdb->prepare(
    "SELECT h.*, c.name as category_name 
     FROM $honors_table h
     LEFT JOIN $categories_table c ON h.category_id = c.id
     WHERE h.coach_id = %d
     ORDER BY h.created_at DESC
     LIMIT %d OFFSET %d",
    $coach_id,
    $per_page,
    $offset
));

$total_pages = ceil($total_honors / $per_page);

?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">افتخارات من</h1>
        <p class="sc-coach-panel-desc">ثبت و مدیریت افتخارات و مدارک شما.</p>
    </div>
    <!-- فرم افزودن افتخارات -->
    <div class="sc-coach-panel-card card" style="padding: 24px; margin-top: 0;">
        <h2>افزودن افتخارات جدید</h2>
        
        <?php if ($message) : ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible" style="margin-top: 15px;">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" id="coach-honors-form">
            <?php wp_nonce_field('save_coach_honors_nonce'); ?>
            
            <div id="coach-honors-container">
                <div class="honor-row" style="display: grid; grid-template-columns: 2fr 1.5fr 1fr auto; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">عنوان  <span style="color: red;">*</span></label>
                        <input type="text" name="honors[0][name]" class="regular-text" required style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">دسته <span style="color: red;">*</span></label>
                        <select name="honors[0][category_id]" class="regular-text" required style="width: 100%;">
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">فایل</label>
                        <div class="sc-file-upload-wrapper" style="position: relative;">
                            <input type="file" 
                                   name="honor_file_0" 
                                   id="coach_honor_file_0" 
                                   class="sc-honor-file-input" 
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                   style="position: absolute; opacity: 0; width: 0; height: 0;">
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <button type="button" 
                                        class="sc-file-upload-btn" 
                                        data-index="0"
                                        style="padding: 8px 16px; font-size: 13px; background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: #fff; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap; flex: 1; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(34,113,177,0.2);">
                                    <span class="btn-text">📎 انتخاب فایل</span>
                                </button>
                                <button type="button" 
                                        class="sc-remove-file-btn" 
                                        data-index="0"
                                        style="padding: 8px 12px; font-size: 13px; background: #d63638; color: #fff; border: none; border-radius: 6px; cursor: pointer; display: none; transition: all 0.3s ease;">
                                    ✕ حذف
                                </button>
                            </div>
                        </div>
                        <p class="description" style="margin-top: 5px; font-size: 11px; color: #999;">حداکثر 1 مگابایت - تصاویر، PDF، Word، Excel</p>
                    </div>
                    <div style="display: flex;align-items: flex-start;margin-top: 14px;">
                        <button type="button" class="button remove-row" style="display: none;">حذف</button>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">توضیحات</label>
                        <textarea name="honors[0][description]" rows="3" class="regular-text" style="width: 100%; resize: vertical;"></textarea>
                    </div>
                </div>
            </div>
            
            <p class="submit">
                <button type="button" id="add-coach-honor-row" class="button">+ افزودن ردیف جدید</button>
                <input type="submit" name="save_honors" class="button button-primary" value="ذخیره">
            </p>
        </form>
    </div>
    
    <!-- لیست افتخارات -->
    <?php if (!empty($honors)) : ?>
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>افتخارات ثبت شده</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>عنوان افتخار</th>
                        <th>دسته</th>
                        <th>توضیحات</th>
                        <th>فایل</th>
                        <th>تاریخ ثبت</th>
                        <th style="width: 80px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($honors as $honor) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($honor->name); ?></strong></td>
                            <td><?php echo esc_html($honor->category_name ?: '-'); ?></td>
                            <td><?php echo esc_html($honor->description ?: '-'); ?></td>
                            <td>
                                <?php if (!empty($honor->file_url)) : ?>
                                    <a href="<?php echo esc_url($honor->file_url); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">📎 دانلود</a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(sc_date_shamsi($honor->created_at, 'Y/m/d')); ?></td>
                            <td>
                                <button type="button" class="button delete-single-coach-honor" data-honor-id="<?php echo esc_attr($honor->id); ?>" style="background: #d63638; color: #fff; border-color: #d63638; padding: 5px 10px; font-size: 12px;">
                                    حذف
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- صفحه‌بندی -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom" style="margin-top: 20px; text-align: center;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg(['honors_page' => '%#%']),
                            'format' => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >',
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                    <?php if ($total_honors > 0) : ?>
                        <div style="margin-top: 10px; color: #666; font-size: 14px;">
                            نمایش <?php echo (($current_page - 1) * $per_page + 1); ?> تا <?php echo min($current_page * $per_page, $total_honors); ?> از <?php echo $total_honors; ?> افتخار
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="notice notice-info" style="margin-top: 20px;">
            <p>هنوز افتخاری ثبت نشده است.</p>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    let rowIndex = 1;
    
    // افزودن ردیف جدید
    $('#add-coach-honor-row').on('click', function() {
        const newRow = `
            <div class="honor-row" style="display: grid; grid-template-columns: 2fr 1.5fr 1fr auto; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">عنوان افتخار <span style="color: red;">*</span></label>
                    <input type="text" name="honors[${rowIndex}][name]" class="regular-text" required style="width: 100%;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">دسته <span style="color: red;">*</span></label>
                    <select name="honors[${rowIndex}][category_id]" class="regular-text" required style="width: 100%;">
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">فایل</label>
                    <div class="sc-file-upload-wrapper" style="position: relative;">
                        <input type="file" 
                               name="honor_file_${rowIndex}" 
                               id="coach_honor_file_${rowIndex}" 
                               class="sc-honor-file-input" 
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                               style="position: absolute; opacity: 0; width: 0; height: 0;">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" 
                                    class="sc-file-upload-btn" 
                                    data-index="${rowIndex}"
                                    style="padding: 8px 16px; font-size: 13px; background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: #fff; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap; flex: 1; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(34,113,177,0.2);">
                                <span class="btn-text">📎 انتخاب فایل</span>
                            </button>
                            <button type="button" 
                                    class="sc-remove-file-btn" 
                                    data-index="${rowIndex}"
                                    style="padding: 8px 12px; font-size: 13px; background: #d63638; color: #fff; border: none; border-radius: 6px; cursor: pointer; display: none; transition: all 0.3s ease;">
                                ✕ حذف
                            </button>
                        </div>
                    </div>
                    <p class="description" style="margin-top: 5px; font-size: 11px; color: #999;">حداکثر 1 مگابایت - تصاویر، PDF، Word، Excel</p>
                </div>
                <div style="display: flex;align-items: flex-start;margin-top: 14px;">
                    <button type="button" class="button remove-row">حذف</button>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">توضیحات</label>
                    <textarea name="honors[${rowIndex}][description]" rows="3" class="regular-text" style="width: 100%; resize: vertical;"></textarea>
                </div>
            </div>
        `;
        
        $('#coach-honors-container').append(newRow);
        rowIndex++;
        
        // نمایش دکمه حذف برای همه ردیف‌ها
        $('.remove-row').show();
    });
    
    // کلیک روی دکمه فایل
    $(document).on('click', '.sc-file-upload-btn', function() {
        const index = $(this).data('index');
        $('#coach_honor_file_' + index).click();
    });
    
    // تغییر فایل انتخاب شده - بررسی اندازه و نمایش پیام انتخاب (بدون پیش نمایش)
    $(document).on('change', '.sc-honor-file-input', function() {
        const index = $(this).attr('id').replace('coach_honor_file_', '');
        const file = this.files[0];
        const wrapper = $(this).closest('.sc-file-upload-wrapper');
        const uploadBtn = wrapper.find('.sc-file-upload-btn[data-index="' + index + '"]');
        const removeBtn = wrapper.find('.sc-remove-file-btn[data-index="' + index + '"]');
        const btnText = uploadBtn.find('.btn-text');
        
        if (file) {
            // بررسی اندازه فایل (1MB)
            const maxSize = 1 * 1024 * 1024; // 1MB
            if (file.size > maxSize) {
                alert('حجم فایل بیش از 1 مگابایت است. لطفاً فایل کوچکتری انتخاب کنید.');
                $(this).val('');
                btnText.text('📎 انتخاب فایل');
                uploadBtn.css({
                    'background': 'linear-gradient(135deg, #2271b1 0%, #135e96 100%)',
                    'box-shadow': '0 2px 4px rgba(34,113,177,0.2)'
                });
                removeBtn.hide();
                return;
            }
            
            // نشان دادن انتخاب فایل (بدون پیش نمایش)
            btnText.text('✓ فایل انتخاب شد');
            uploadBtn.css({
                'background': 'linear-gradient(135deg, #00a32a 0%, #008a20 100%)',
                'box-shadow': '0 2px 4px rgba(0,163,42,0.2)'
            });
            removeBtn.show();
        }
    });
    
    // حذف فایل انتخاب شده
    $(document).on('click', '.sc-remove-file-btn', function() {
        const index = $(this).data('index');
        const fileInput = $('#coach_honor_file_' + index);
        const wrapper = fileInput.closest('.sc-file-upload-wrapper');
        const uploadBtn = wrapper.find('.sc-file-upload-btn[data-index="' + index + '"]');
        const removeBtn = wrapper.find('.sc-remove-file-btn[data-index="' + index + '"]');
        const btnText = uploadBtn.find('.btn-text');
        
        fileInput.val('');
        btnText.text('📎 انتخاب فایل');
        uploadBtn.css({
            'background': 'linear-gradient(135deg, #2271b1 0%, #135e96 100%)',
            'box-shadow': '0 2px 4px rgba(34,113,177,0.2)'
        });
        removeBtn.hide();
    });
    
    // حذف ردیف
    $(document).on('click', '.remove-row', function() {
        $(this).closest('.honor-row').remove();
        
        // اگر فقط یک ردیف باقی ماند، دکمه حذف را مخفی کن
        if ($('.honor-row').length <= 1) {
            $('.remove-row').hide();
        }
    });
    
    // اگر فقط یک ردیف وجود دارد، دکمه حذف را مخفی کن
    if ($('.honor-row').length <= 1) {
        $('.remove-row').hide();
    }
    
    // Hover effect برای دکمه فایل
    $(document).on('mouseenter', '.sc-file-upload-btn', function() {
        if (!$(this).find('.btn-text').text().includes('✓')) {
            $(this).css({
                'transform': 'translateY(-2px)',
                'box-shadow': '0 4px 8px rgba(34,113,177,0.3)'
            });
        }
    }).on('mouseleave', '.sc-file-upload-btn', function() {
        if (!$(this).find('.btn-text').text().includes('✓')) {
            $(this).css({
                'transform': 'translateY(0)',
                'box-shadow': '0 2px 4px rgba(34,113,177,0.2)'
            });
        }
    });
    
    // Hover effect برای دکمه حذف فایل
    $(document).on('mouseenter', '.sc-remove-file-btn', function() {
        $(this).css({
            'background': '#b32d2e',
            'transform': 'translateY(-1px)'
        });
    }).on('mouseleave', '.sc-remove-file-btn', function() {
        $(this).css({
            'background': '#d63638',
            'transform': 'translateY(0)'
        });
    });
    
    // حذف تکی افتخار مربی
    $(document).on('click', '.delete-single-coach-honor', function() {
        if (!confirm('آیا از حذف این افتخار اطمینان دارید؟')) {
            return;
        }
        
        const honorId = $(this).data('honor-id');
        const form = $('<form>', {
            method: 'POST',
            action: ''
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'delete_single_honor',
            value: '1'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'honor_id',
            value: honorId
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: '_wpnonce',
            value: '<?php echo wp_create_nonce("delete_single_coach_honor_nonce"); ?>'
        }));
        
        $('body').append(form);
        form.submit();
    });
});
</script>
