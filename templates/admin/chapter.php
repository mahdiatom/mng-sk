<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$categories_table = $wpdb->prefix . 'sc_chapter_categories';

// پردازش افزودن شعبه
$message = '';
$message_type = '';
$editing_category = null;

if (isset($_POST['add_category']) && check_admin_referer('add_chapter_category')) {
    $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
    
    if (empty($category_name)) {
        $message = 'لطفاً نام شعبه را وارد کنید.';
        $message_type = 'error';
    } else {
        // بررسی تکراری نبودن
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE name = %s",
            $category_name
        ));
        
        if ($existing) {
            $message = 'این شعبه قبلاً ثبت شده است.';
            $message_type = 'error';
        } else {
            $inserted = $wpdb->insert(
                $categories_table,
                [
                    'name' => $category_name,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s']
            );
            
            if ($inserted) {
                $message = 'شعبه با موفقیت افزوده شد.';
                $message_type = 'success';
            } else {
                $message = 'خطا در افزودن شعبه.';
                $message_type = 'error';
            }
        }
    }
}

// پردازش ویرایش شعبه
$editing_category = null;
if (isset($_POST['edit_category']) && check_admin_referer('edit_chapter_category')) {
    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
    
    if ($category_id && !empty($category_name)) {
        // بررسی تکراری نبودن (به جز خود این شعبه)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE name = %s AND id != %d",
            $category_name,
            $category_id
        ));
        
        if ($existing) {
            $message = 'شعبه‌ای با این نام قبلاً وجود دارد.';
            $message_type = 'error';
        } else {
            $updated = $wpdb->update(
                $categories_table,
                [
                    'name' => $category_name,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $category_id],
                ['%s', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=sc_chapter')));
                exit;
            } else {
                $message = 'خطا در ویرایش شعبه.';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'لطفاً نام شعبه را وارد کنید.';
        $message_type = 'error';
    }
}

// پردازش حذف شعبه
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['category_id'])) {
    check_admin_referer('delete_category_' . $_GET['category_id']);
    
    $category_id = absint($_GET['category_id']);
    
    // بررسی استفاده از شعبه در افتخارات
    $chapter_table = $wpdb->prefix . 'sc_chapter';
    $used = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $chapter_table WHERE category_id = %d",
        $category_id
    ));
    
    if ($used > 0) {
        $message = 'این شعبه در ' . $used . ' افتخار استفاده شده و قابل حذف نیست.';
        $message_type = 'error';
    } else {
        $deleted = $wpdb->delete($categories_table, ['id' => $category_id], ['%d']);
        
        if ($deleted) {
            $message = 'شعبه با موفقیت حذف شد.';
            $message_type = 'success';
        } else {
            $message = 'خطا در حذف شعبه.';
            $message_type = 'error';
        }
    }
}

// دریافت شعبه برای ویرایش (از GET)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['category_id'])) {
    $edit_id = absint($_GET['category_id']);
    if ($edit_id) {
        $editing_category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $categories_table WHERE id = %d LIMIT 1",
            $edit_id
        ));
    }
}

// نمایش پیام پس از redirect (ویرایش)
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'شعبه با موفقیت ویرایش شد.';
    $message_type = 'success';
}

// دریافت لیست شعبه‌ها
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name ASC");

?>
<div class="wrap">
    <h1>شعبه های باشگاه  </h1>
 </div>
<div class="wrap">  
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    </div>
    
    <?php endif; ?>
    
    <div class="sections_cat_chapter">
        <!-- فرم افزودن یا ویرایش شعبه -->
        <div class="postbox edit_cat_chapter">
            <div class="postbox-header">
                <h2 class=""><?php echo $editing_category ? 'ویرایش شعبه' : 'افزودن شعبه جدید'; ?></h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if ($editing_category) : ?>
                    <form method="post">
                        <?php wp_nonce_field('edit_chapter_category'); ?>
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($editing_category->id); ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name">نام شعبه <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="category_name" class="regular-text" value="<?php echo esc_attr($editing_category->name); ?>" required>
                                    <p class="description">نام شعبه را ویرایش کنید</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="edit_category" class="button button-primary" value="ذخیره تغییرات">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc_chapter')); ?>" class="button">انصراف</a>
                        </p>
                    </form>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field('add_chapter_category'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name">نام شعبه <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="category_name" class="regular-text" required>
                                    <p class="description">نام شعبه را وارد کنید</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="add_category" class="button button-primary" value="افزودن شعبه">
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- لیست شعبه‌ها -->
        <div class="postbox list_cat_chapter">
            <div class="postbox-header">
                <h2 class="">لیست  شعبه ها</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if (!empty($categories)) : ?>
                    <table class="wp-list-table widefat fixed striped " style="border-radius: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 50px; padding:20px;">ردیف</th>
                                <th>نام شعبه</th>
                                <th style="width: 150px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $index => $category) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc_chapter&action=edit&category_id=' . $category->id)); ?>" 
                                           class="button button-small">ویرایش</a>
                                        <?php
                                        $delete_url = wp_nonce_url(
                                            admin_url('admin.php?page=sc_chapter&action=delete&category_id=' . $category->id),
                                            'delete_category_' . $category->id
                                        );
                                        ?>
                                        <a href="<?php echo esc_url($delete_url); ?>" 
                                           onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید؟' })" 
                                           class="button button-small btn_delete_action_admin">حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>هیچ شعبه‌ای ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
