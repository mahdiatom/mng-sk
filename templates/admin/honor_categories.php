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

// دریافت لیست دسته‌ها
$categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name ASC");

?>
<div class="wrap">
    <h1>دسته‌بندی افتخارات</h1>
    
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        <!-- فرم افزودن دسته -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">افزودن دسته جدید</h2>
            </div>
            <div class="inside" style="padding: 20px;">
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
            </div>
        </div>
        
        <!-- لیست دسته‌ها -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">لیست دسته‌ها</h2>
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
                                        <?php
                                        $delete_url = wp_nonce_url(
                                            admin_url('admin.php?page=sc-honor-categories&action=delete&category_id=' . $category->id),
                                            'delete_category_' . $category->id
                                        );
                                        ?>
                                        <a href="<?php echo esc_url($delete_url); ?>" 
                                           onclick="return confirm('آیا مطمئن هستید؟')" 
                                           class="button button-small">حذف</a>
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
