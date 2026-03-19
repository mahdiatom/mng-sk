<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$faq_table = $wpdb->prefix . 'sc_faq';

// پردازش افزودن پرسش / پاسخ 
$message = '';
$message_type = '';
$editing_faq = null;

if (isset($_POST['add_faq']) && check_admin_referer('add_faq_faq')) {
    $question = isset($_POST['question']) ? sanitize_text_field($_POST['question']) : '';
    $answer = isset($_POST['answer']) ? sanitize_text_field($_POST['answer']) : '';
    
    if (empty($question)) {
        $message = 'لطفاً نام پرسش / پاسخ  را وارد کنید.';
        $message_type = 'error';
    } else {
        // بررسی تکراری نبودن
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $faq_table WHERE name = %s",
            $question
        ));
        
        if ($existing) {
            $message = 'این پرسش / پاسخ  قبلاً ثبت شده است.';
            $message_type = 'error';
        } else {
            $inserted = $wpdb->insert(
                $faq_table,
                [
                    'question' => $question, 
                    'answer' => $answer, 
                    'created_at' => current_time('mysql'),
                
                ],
                ['%s', '%s', '%s']
            );
            
            if ($inserted) {
                $message = 'پرسش / پاسخ  با موفقیت افزوده شد.';
                $message_type = 'success';
            } else {
                $message = 'خطا در افزودن پرسش / پاسخ .';
                $message_type = 'error';
            }
        }
    }
}

// پردازش ویرایش پرسش / پاسخ 
$editing_faq = null;
if (isset($_POST['edit_faq']) && check_admin_referer('edit_faq_faq')) {
    $faq_id = isset($_POST['faq_id']) ? absint($_POST['faq_id']) : 0;
    $question = isset($_POST['question']) ? sanitize_text_field($_POST['question']) : '';
    
    if ($faq_id && !empty($question)) {
        // بررسی تکراری نبودن (به جز خود این پرسش / پاسخ )
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $faq_table WHERE name = %s AND id != %d",
            $question,
            $faq_id
        ));
        
        if ($existing) {
            $message = 'پرسش / پاسخ ‌ای با این نام قبلاً وجود دارد.';
            $message_type = 'error';
        } else {
            $updated = $wpdb->update(
                $faq_table,
                [
                    'name' => $question,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $faq_id],
                ['%s', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=sc_faq')));
                exit;
            } else {
                $message = 'خطا در ویرایش پرسش / پاسخ .';
                $message_type = 'error';
            }
        }
    } else {
        $message = 'لطفاً نام پرسش / پاسخ  را وارد کنید.';
        $message_type = 'error';
    }
}

// پردازش حذف پرسش / پاسخ 
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['faq_id'])) {
    check_admin_referer('delete_faq_' . $_GET['faq_id']);
    
    $faq_id = absint($_GET['faq_id']);
    
    // بررسی استفاده از پرسش / پاسخ  در افتخارات
    $faq_table = $wpdb->prefix . 'sc_faq';
    $used = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $faq_table WHERE faq_id = %d",
        $faq_id
    ));
    
    if ($used > 0) {
        $message = 'این پرسش / پاسخ  در ' . $used . ' افتخار استفاده شده و قابل حذف نیست.';
        $message_type = 'error';
    } else {
        $deleted = $wpdb->delete($faq_table, ['id' => $faq_id], ['%d']);
        
        if ($deleted) {
            $message = 'پرسش / پاسخ  با موفقیت حذف شد.';
            $message_type = 'success';
        } else {
            $message = 'خطا در حذف پرسش / پاسخ .';
            $message_type = 'error';
        }
    }
}

// دریافت پرسش / پاسخ  برای ویرایش (از GET)
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['faq_id'])) {
    $edit_id = absint($_GET['faq_id']);
    if ($edit_id) {
        $editing_faq = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $faq_table WHERE id = %d LIMIT 1",
            $edit_id
        ));
    }
}

// نمایش پیام پس از redirect (ویرایش)
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'پرسش / پاسخ  با موفقیت ویرایش شد.';
    $message_type = 'success';
}

// دریافت لیست پرسش / پاسخ ‌ها
$faqs = $wpdb->get_results("SELECT * FROM $faq_table ORDER BY id ASC");

?>
<div class="wrap">
    <h1>پرسش و پاسخ / سوالات متداول باشگاه</h1>
    
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sections_cat_faq">
        <!-- فرم افزودن یا ویرایش پرسش / پاسخ  -->
        <div class="postbox edit_cat_faq">
            <div class="postbox-header">
                <h2 class=""><?php echo $editing_faq ? 'ویرایش پرسش / پاسخ ' : 'افزودن پرسش / پاسخ  جدید'; ?></h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if ($editing_faq) : ?>
                    <form method="post">
                        <?php wp_nonce_field('edit_faq'); ?>
                        <!-- <input type="hidden" name="faq_id" value="<?php echo esc_attr($editing_faq->id); ?>"> -->
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    
                                    <label for="question">پرسش <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <?php wp_editor($editing_faq->question , 'question', ['textarea_rows' => 4 , 'media_buttons' => false]) ?>
                                    <!-- <input type="text" id="question" name="question" class="regular-text" value="<?php //echo esc_attr($editing_faq->question); ?>" required> -->
                                    <p class="description"> پرسش را ویرایش کنید</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="answer"> پاسخ  <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <?php wp_editor($editing_faq->answer , 'answer', ['textarea_rows' => 4 , 'media_buttons' => false]) ?>
                                    <!-- <input type="text" id="answer" name="answer" class="regular-text" value="<?php //echo esc_attr($editing_faq->answer); ?>" required> -->
                                    <p class="description"> پاسخ  را ویرایش کنید</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="edit_faq" class="button button-primary" value="ذخیره تغییرات">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc_faq')); ?>" class="button">انصراف</a>
                        </p>
                    </form>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field('add_faq_faq'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="question"> پرسش   <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <?php wp_editor('' , 'question' , ['textarea_rows' => 4 , 'media_buttons' => false]) ?>
                                    <!-- <input type="text" id="question" name="question" class="regular-text" required> -->
                                    <p class="description"> پرسش خود را را وارد کنید</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="answer"> پاسخ  <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <?php wp_editor('' , 'answer', ['textarea_rows' => 4 , 'media_buttons' => false]) ?>
                                    <!-- <input type="text" id="answer" name="answer" class="regular-text" required> -->
                                    <p class="description"> پاسخ خود را را وارد کنید</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="add_faq" class="button button-primary" value="افزودن پرسش / پاسخ ">
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- لیست پرسش / پاسخ ‌ها -->
        <div class="postbox list_cat_faqs">
            <div class="postbox-header">
                <h2 class="">لیست پرسش / پاسخ ‌ها</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                <?php if (!empty($faqs)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ردیف</th>
                                <th> پرسش  </th>
                                <th> پاسخ  </th>
                                <th style="width: 150px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs as $index => $faq) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo esc_html($faq->question); ?></strong></td>
                                    <td><strong><?php echo esc_html($faq->answer); ?></strong></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc_faq&action=edit&faq_id=' . $faq->id)); ?>" 
                                           class="button button-small">ویرایش</a>
                                        <?php
                                        $delete_url = wp_nonce_url(
                                            admin_url('admin.php?page=sc_faq&action=delete&faq_id=' . $faq->id),
                                            'delete_faq_' . $faq->id
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
                    <p>هیچ پرسش / پاسخ ‌ای ثبت نشده است.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


























