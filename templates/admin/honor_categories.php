<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$categories_table = $wpdb->prefix . 'sc_honor_categories';

// پردازش افزودن دسته
$message = '';
$message_type = '';
$editing_category = null;

if (isset($_POST['add_category']) && check_admin_referer('add_honor_category')) {
    $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
    
    if (empty($category_name)) {
        $message = 'لطفاً نام دسته را وارد کنید.';
        $message_type = 'error';
    } else {
        // بررسی تکراری نبودن
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE name = %s",
            $category_name
        ));
        
        if ($existing) {
            $message = 'این دسته قبلاً ثبت شده است.';
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
                $message = 'دسته با موفقیت افزوده شد.';
                $message_type = 'success';
            } else {
                $message = 'خطا در افزودن دسته.';
                $message_type = 'error';
            }
        }
    }
}

// پردازش ویرایش دسته
$editing_category = null;
if (isset($_POST['edit_category']) && check_admin_referer('edit_honor_category')) {
    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $category_name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
    
    if ($category_id && !empty($category_name)) {
        // بررسی تکراری نبودن (به جز خود این دسته)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $categories_table WHERE name = %s AND id != %d",
            $category_name,
            $category_id
        ));
        
        if ($existing) {
            $message = 'دسته‌ای با این نام قبلاً وجود دارد.';
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
                wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=sc-honor-categories')));
                exit;
            } else {
                $message = 'خطا در ویرایش دسته.';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'لطفاً نام دسته را وارد کنید.';
        $message_type = 'error';
    }
}

// پردازش حذف دسته
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['category_id'])) {
    check_admin_referer('delete_category_' . $_GET['category_id']);
    
    $category_id = absint($_GET['category_id']);
    
    // بررسی استفاده از دسته در افتخارات
    $honors_table = $wpdb->prefix . 'sc_honors';
    $used = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $honors_table WHERE category_id = %d",
        $category_id
    ));
    
    if ($used > 0) {
        $message = 'این دسته در ' . $used . ' افتخار استفاده شده و قابل حذف نیست.';
        $message_type = 'error';
    } else {
        $deleted = $wpdb->delete($categories_table, ['id' => $category_id], ['%d']);
        
        if ($deleted) {
            $message = 'دسته با موفقیت حذف شد.';
            $message_type = 'success';
        } else {
            $message = 'خطا در حذف دسته.';
            $message_type = 'error';
        }
    }
}

// دریافت دسته برای ویرایش (از GET)
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
    $message = 'دسته با موفقیت ویرایش شد.';
    $message_type = 'success';
}

// دریافت لیست دسته‌ها
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name ASC");

?>
<div class="wrap">
    <h1>دسته‌بندی افتخارات</h1>
    </div>
    <div class="wrap">
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sections_cat_honors">
        <!-- فرم افزودن یا ویرایش دسته -->
        <div class="postbox edit_cat_honors">
            <div class="postbox-header">
                <h2 class=""><?php echo $editing_category ? 'ویرایش دسته' : 'افزودن دسته جدید'; ?></h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if ($editing_category) : ?>
                    <form method="post">
                        <?php wp_nonce_field('edit_honor_category'); ?>
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($editing_category->id); ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name">نام دسته <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="category_name" class="regular-text" value="<?php echo esc_attr($editing_category->name); ?>" required>
                                    <p class="description">نام دسته را ویرایش کنید</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="edit_category" class="button button-primary" value="ذخیره تغییرات">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honor-categories')); ?>" class="button">انصراف</a>
                        </p>
                    </form>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field('add_honor_category'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="category_name">نام دسته <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" id="category_name" name="category_name" class="regular-text" required>
                                    <p class="description">نام دسته را وارد کنید</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="add_category" class="button button-primary" value="افزودن دسته">
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- لیست دسته‌ها -->
        <div class="postbox list_cat_honores">
            <div class="postbox-header">
                <h2 class="">لیست دسته‌ها</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if (!empty($categories)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ردیف</th>
                                <th>نام دسته</th>
                                <th style="width: 150px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $index => $category) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honor-categories&action=edit&category_id=' . $category->id)); ?>" 
                                           class="button button-small">ویرایش</a>
                                        <?php
                                        $delete_url = wp_nonce_url(
                                            admin_url('admin.php?page=sc-honor-categories&action=delete&category_id=' . $category->id),
                                            'delete_category_' . $category->id
                                        );
                                        ?>
                                        <a href="<?php echo esc_url($delete_url); ?>" 
                                           onclick="return confirm('آیا مطمئن هستید؟')" 
                                           class="button button-small btn_delete_action_admin">حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>هیچ دسته‌ای ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
