<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$honors_table = $wpdb->prefix . 'sc_honors';
$categories_table = $wpdb->prefix . 'sc_honor_categories';

// پردازش حذف تکی افتخار
if (isset($_POST['delete_single_honor']) && check_admin_referer('delete_single_honor_nonce')) {
    $honor_id = isset($_POST['honor_id']) ? absint($_POST['honor_id']) : 0;
    
    if ($honor_id > 0) {
        // دریافت اطلاعات بازیکن
        $current_user_id = get_current_user_id();
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_members WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
        
        if ($player) {
            // بررسی اینکه افتخار متعلق به این بازیکن است
            $honor = $wpdb->get_row($wpdb->prepare(
                "SELECT id, file_url FROM $honors_table WHERE id = %d AND member_id = %d",
                $honor_id,
                $player->id
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
                wc_add_notice('افتخار با موفقیت حذف شد.', 'success');
            } else {
                wc_add_notice('افتخار یافت نشد یا شما دسترسی به حذف آن را ندارید.', 'error');
            }
        } else {
            wc_add_notice('خطا: اطلاعات بازیکن یافت نشد.', 'error');
        }
        
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-honors'));
        exit;
    }
}

// پردازش فرم افزودن افتخار
if (isset($_POST['save_honors']) && check_admin_referer('save_honors_nonce')) {
    $honors_data = isset($_POST['honors']) && is_array($_POST['honors']) ? $_POST['honors'] : [];
    
    if (empty($honors_data)) {
        wc_add_notice('لطفاً حداقل یک افتخار را وارد کنید.', 'error');
        // Redirect برای جلوگیری از duplicate submission
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-honors'));
        exit;
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
                    $errors[] = 'ردیف ' . ($index + 1) . ': نوع فایل معتبر نیست. فقط تصاویر، PDF، Word و Excel مجاز است.';
                    continue;
                }
                
                // بررسی اندازه فایل (حداکثر 1 مگابایت)
                $max_file_size = 1 * 1024 * 1024; // 1MB
                if ($file['size'] > $max_file_size) {
                    $errors[] = 'ردیف ' . ($index + 1) . ': حجم فایل بیش از 1 مگابایت است.';
                    continue;
                }
                
                // آپلود فایل
                if (!function_exists('wp_handle_upload')) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                }
                
                $upload_dir = wp_upload_dir();
                $sc_upload_dir = $upload_dir['basedir'] . '/sportclub-honors';
                if (!file_exists($sc_upload_dir)) {
                    wp_mkdir_p($sc_upload_dir);
                }
                
                $unique_filename = wp_unique_filename($sc_upload_dir, sanitize_file_name($player->id . '_' . time() . '_' . $file['name']));
                $file_path = $sc_upload_dir . '/' . $unique_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $file_url = $upload_dir['baseurl'] . '/sportclub-honors/' . $unique_filename;
                } else {
                    $errors[] = 'ردیف ' . ($index + 1) . ': خطا در آپلود فایل.';
                    continue;
                }
            }
            
            // ذخیره افتخار
            $inserted = $wpdb->insert(
                $honors_table,
                [
                    'member_id' => $player->id,
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
                $saved_count++;
            }
        }
        
        // نمایش پیام‌ها و redirect
        if ($saved_count > 0) {
            wc_add_notice($saved_count . ' افتخار با موفقیت ذخیره شد.', 'success');
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                wc_add_notice($error, 'error');
            }
        }
        
        // Redirect برای جلوگیری از duplicate submission (POST-Redirect-GET pattern)
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-honors'));
        exit;
    }
}

// دریافت لیست دسته‌ها
$categories = $wpdb->get_results("SELECT id, name FROM $categories_table ORDER BY name ASC");

// Pagination برای افتخارات
$per_page = 10;
$current_page = isset($_GET['honors_page']) ? max(1, absint($_GET['honors_page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// شمارش کل افتخارات
$total_honors = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) 
     FROM $honors_table 
     WHERE member_id = %d",
    $player->id
));

// دریافت افتخارات بازیکن با صفحه‌بندی
$honors = $wpdb->get_results($wpdb->prepare(
    "SELECT h.*, c.name as category_name 
     FROM $honors_table h
     LEFT JOIN $categories_table c ON h.category_id = c.id
     WHERE h.member_id = %d
     ORDER BY h.created_at DESC
     LIMIT %d OFFSET %d",
    $player->id,
    $per_page,
    $offset
));

$total_pages = ceil($total_honors / $per_page);

?>
<div class="woocommerce-MyAccount-content sc-honors-content" style="max-width: 100%; padding: 0; margin: 0;">
    <h2>افتخارات من</h2>
    
    <!-- نمایش پیام‌های WooCommerce (اگر از redirect آمده باشیم) -->
    <?php wc_print_notices(); ?>
    
    <!-- فرم افزودن افتخارات -->
    <div class="sc-honors-form-wrapper">
        <h3>افزودن افتخارات جدید</h3>
        
        <form method="post" id="honors-form" enctype="multipart/form-data">
            <?php wp_nonce_field('save_honors_nonce'); ?>
            
            <div id="honors-container">
                <div class="honor-row">
                    <div>
                        <label>عنوان افتخار <span style="color: #d63638;">*</span></label>
                        <input type="text" name="honors[0][name]" class="regular-text" required>
                    </div>
                    <div>
                        <label>دسته <span style="color: #d63638;">*</span></label>
                        <select name="honors[0][category_id]" class="regular-text" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>فایل</label>
                        <div class="sc-file-upload-wrapper" style="position: relative;">
                            <input type="file" 
                                   name="honor_file_0" 
                                   id="honor_file_0" 
                                   class="sc-honor-file-input" 
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <button type="button" 
                                        class="sc-file-upload-btn" 
                                        data-index="0">
                                    <span class="btn-text" style="color:white;">📎 انتخاب فایل</span>
                                </button>
                                <button type="button" 
                                        class="sc-remove-file-btn" 
                                        data-index="0">
                                    ✕
                                </button>
                            </div>
                        </div>
                        <p class="description">حداکثر 1 مگابایت - تصاویر، PDF، Word، Excel</p>
                    </div>
                    <div>
                        <button type="button" class="button remove-row" style="display: none;">
حذف                        </button>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>توضیحات</label>
                        <textarea name="honors[0][description]" rows="3" class="regular-text"></textarea>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="add-honor-row" class="button">+ افزودن ردیف جدید</button>
                <button type="submit" name="save_honors" class="button button-primary">
                    <span>✓</span>
                    ذخیره
                </button>
            </div>
        </form>
    </div>
    
    <!-- لیست افتخارات -->
    <?php if (!empty($honors)) : ?>
        <div class="sc-honors-list-wrapper">
            <h3>افتخارات ثبت شده</h3>
            
            <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
                <thead>
                    <tr>
                        <th>عنوان افتخار</th>
                        <th>دسته</th>
                        <th>توضیحات</th>
                        <th>فایل</th>
                        <th>تاریخ ثبت</th>
                        <th style="width: 100px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($honors as $honor) : ?>
                        <tr>
                            <td data-title="عنوان افتخار">
                                <strong><?php echo esc_html($honor->name); ?></strong>
                            </td>
                            <td data-title="دسته">
                                <?php echo esc_html($honor->category_name ?: '-'); ?>
                            </td>
                            <td data-title="توضیحات">
                                <?php echo esc_html($honor->description ?: '-'); ?>
                            </td>
                            <td data-title="فایل">
                                <?php if (!empty($honor->file_url)) : ?>
                                    <a href="<?php echo esc_url($honor->file_url); ?>" target="_blank">
                                        📎 دانلود فایل
                                    </a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-title="تاریخ ثبت">
                                <?php echo esc_html(sc_date_shamsi($honor->created_at, 'Y/m/d')); ?>
                            </td>
                            <td data-title="عملیات">
                                <button type="button" class="button delete-single-honor" data-honor-id="<?php echo esc_attr($honor->id); ?>">
                                    حذف
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- صفحه‌بندی -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin-top: 20px; text-align: center;">
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
        <div class="woocommerce-message woocommerce-message--info woocommerce-info" style="margin-top: 20px;">
            هنوز افتخاری ثبت نشده است.
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    let rowIndex = 1;
    
    // افزودن ردیف جدید
    $('#add-honor-row').on('click', function() {
        const newRow = `
            <div class="honor-row">
                <div>
                    <label>عنوان افتخار <span style="color: #d63638;">*</span></label>
                    <input type="text" name="honors[${rowIndex}][name]" class="regular-text" required>
                </div>
                <div>
                    <label>دسته <span style="color: #d63638;">*</span></label>
                    <select name="honors[${rowIndex}][category_id]" class="regular-text" required>
                        <option value="">انتخاب کنید</option>
                        <?php foreach ($categories as $category) : ?>
                            <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>فایل</label>
                    <div class="sc-file-upload-wrapper" style="position: relative;">
                        <input type="file" 
                               name="honor_file_${rowIndex}" 
                               id="honor_file_${rowIndex}" 
                               class="sc-honor-file-input" 
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" 
                                    class="sc-file-upload-btn" 
                                    data-index="${rowIndex}">
                                <span class="btn-text"  style="color:white;">📎 انتخاب فایل</span>
                            </button>
                            <button type="button" 
                                    class="sc-remove-file-btn" 
                                    data-index="${rowIndex}">
                                ✕
                            </button>
                        </div>
                    </div>
                    <p class="description">حداکثر 1 مگابایت - تصاویر، PDF، Word، Excel</p>
                </div>
                <div>
                    <button type="button" class="button remove-row"> حذف
                   </button>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label>توضیحات</label>
                    <textarea name="honors[${rowIndex}][description]" rows="3" class="regular-text"></textarea>
                </div>
            </div>
        `;
        
        $('#honors-container').append(newRow);
        rowIndex++;
        
        // نمایش دکمه حذف برای همه ردیف‌ها
        $('.remove-row').show();
    });
    
    // کلیک روی دکمه فایل
    $(document).on('click', '.sc-file-upload-btn', function() {
        const index = $(this).data('index');
        $('#honor_file_' + index).click();
    });
    
    // تغییر فایل انتخاب شده - فقط بررسی اندازه و نشان دادن انتخاب (بدون پیش نمایش)
    $(document).on('change', '.sc-honor-file-input', function() {
        const index = $(this).attr('id').replace('honor_file_', '');
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
                    'background': 'linear-gradient(135deg, #2271b1 0%, #135e96 100%)'
                });
                removeBtn.hide();
                return;
            }
            
            // نشان دادن انتخاب فایل (بدون پیش نمایش)
            btnText.text('✓ فایل انتخاب شد');
            uploadBtn.css({
                'background': 'linear-gradient(135deg, #00a32a 0%, #008a20 100%)',
                'box-shadow': '0 2px 6px rgba(0,163,42,0.25)'
            });
            removeBtn.show();
        }
    });
    
    // حذف فایل انتخاب شده
    $(document).on('click', '.sc-remove-file-btn', function() {
        const index = $(this).data('index');
        const fileInput = $('#honor_file_' + index);
        const wrapper = fileInput.closest('.sc-file-upload-wrapper');
        const uploadBtn = wrapper.find('.sc-file-upload-btn[data-index="' + index + '"]');
        const removeBtn = wrapper.find('.sc-remove-file-btn[data-index="' + index + '"]');
        const btnText = uploadBtn.find('.btn-text');
        
        fileInput.val('');
        btnText.text('📎 انتخاب فایل');
        uploadBtn.css({
            'background': 'linear-gradient(135deg, #2271b1 0%, #135e96 100%)',
            'box-shadow': '0 2px 6px rgba(34,113,177,0.25)'
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
    
    // حذف تکی افتخار
    $(document).on('click', '.delete-single-honor', function() {
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
            value: '<?php echo wp_create_nonce("delete_single_honor_nonce"); ?>'
        }));
        
        $('body').append(form);
        form.submit();
    });
    
});

document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-honors-content h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>

<style>
/* اصلاح فاصله از راست برای صفحه افتخارات */
.sc-honors-content {
    padding-right: 0 !important;
    padding-left: 0 !important;
    margin-right: 0 !important;
    margin-left: 0 !important;
    width: 100% !important;
}

.sc-honors-content h2 {
    padding-right: 0 !important;
    margin-right: 0 !important;
}

.sc-honors-form-wrapper,
.sc-honors-list-wrapper {
    padding-right: 20px !important;
    padding-left: 20px !important;
    margin-right: 0 !important;
    margin-left: 0 !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

.sc-honors-content .woocommerce-message,
.sc-honors-content .woocommerce-info,
.sc-honors-content .woocommerce-error {
    margin-right: 0 !important;
    padding-right: 20px !important;
}

/* جلوگیری از نمایش پیش‌نمایش فایل */
.sc-file-upload-wrapper input[type="file"]::file-selector-button {
    display: none !important;
    visibility: hidden !important;
}

.sc-file-upload-wrapper input[type="file"]::-webkit-file-upload-button {
    display: none !important;
    visibility: hidden !important;
}

/* input file مخفی است اما هنوز قابل کلیک است */
.sc-honor-file-input {
    position: absolute !important;
    opacity: 0 !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    z-index: -1 !important;
}
</style>
