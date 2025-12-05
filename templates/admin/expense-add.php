<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$expense_categories_table = $wpdb->prefix . 'sc_expense_categories';

// دریافت لیست دسته‌بندی‌ها
$categories = $wpdb->get_results("SELECT id, name FROM $expense_categories_table ORDER BY name ASC");

// دریافت اطلاعات هزینه در صورت ویرایش
$expense = null;
$expense_id = isset($_GET['expense_id']) ? absint($_GET['expense_id']) : 0;
if ($expense_id > 0) {
    $expenses_table = $wpdb->prefix . 'sc_expenses';
    $expense = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $expenses_table WHERE id = %d",
        $expense_id
    ));
}

// مقادیر پیش‌فرض
$expense_name = $expense ? $expense->name : (isset($_POST['expense_name']) ? sanitize_text_field($_POST['expense_name']) : '');
$category_id = $expense ? $expense->category_id : (isset($_POST['category_id']) ? absint($_POST['category_id']) : 0);
$amount = $expense ? floatval($expense->amount) : (isset($_POST['amount']) ? floatval($_POST['amount']) : 0);
$description = $expense ? $expense->description : (isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '');

// تاریخ شمسی
$expense_date_shamsi = '';
if ($expense && !empty($expense->expense_date_shamsi)) {
    $expense_date_shamsi = $expense->expense_date_shamsi;
} elseif (isset($_POST['expense_date_shamsi']) && !empty($_POST['expense_date_shamsi'])) {
    $expense_date_shamsi = sanitize_text_field($_POST['expense_date_shamsi']);
} else {
    // تاریخ پیش‌فرض: امروز
    $today = new DateTime();
    $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
    $expense_date_shamsi = $today_jalali[0] . '/' . 
                           str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                           str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $expense_id > 0 ? 'ویرایش هزینه' : 'ثبت هزینه جدید'; ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=sc-expenses'); ?>" class="page-title-action">بازگشت به لیست هزینه‌ها</a>
    <?php if ($expense_id > 0) : ?>
        <a href="<?php echo admin_url('admin.php?page=sc-add-expense'); ?>" class="page-title-action">ثبت هزینه جدید</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <form method="POST" action="" style="max-width: 800px;">
        <?php wp_nonce_field('sc_add_expense', 'sc_expense_nonce'); ?>
        <?php if ($expense_id > 0) : ?>
            <input type="hidden" name="expense_id" value="<?php echo esc_attr($expense_id); ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="expense_name">نام هزینه <span style="color:red;">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="expense_name" 
                               id="expense_name" 
                               value="<?php echo esc_attr($expense_name); ?>" 
                               class="regular-text" 
                               placeholder="مثلاً: حقوق پرسنل، هزینه اجاره و..."
                               required
                               style="width: 100%;">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="category_id">دسته‌بندی</label>
                    </th>
                    <td>
                        <select name="category_id" id="category_id" style="width: 300px; padding: 5px;">
                            <option value="0">-- بدون دسته‌بندی --</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($category_id, $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?php echo admin_url('admin.php?page=sc-expenses&tab=categories'); ?>" class="button button-small" style="margin-right: 10px;">مدیریت دسته‌بندی‌ها</a>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="expense_date_shamsi">تاریخ (شمسی) <span style="color:red;">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="expense_date_shamsi" 
                               id="expense_date_shamsi" 
                               value="<?php echo esc_attr($expense_date_shamsi); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="مثلاً 1403/09/15" 
                               readonly
                               required
                               style="width: 200px;">
                        <input type="hidden" name="expense_date_gregorian" id="expense_date_gregorian" value="<?php echo $expense ? esc_attr($expense->expense_date_gregorian) : ''; ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید. تاریخ پیش‌فرض: امروز</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="amount">مبلغ (تومان) <span style="color:red;">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="amount" 
                               id="amount" 
                               value="<?php echo $amount > 0 ? number_format($amount, 0, '.', ',') : ''; ?>" 
                               class="regular-text" 
                               placeholder="0"
                               required
                               style="width: 300px;"
                               dir="ltr"
                               inputmode="numeric">
                        <input type="hidden" name="amount_raw" id="amount_raw" value="<?php echo esc_attr($amount); ?>">
                        <p class="description">مبلغ هزینه را به تومان وارد کنید.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="description">توضیحات تکمیلی</label>
                    </th>
                    <td>
                        <textarea name="description" 
                                  id="description" 
                                  rows="5" 
                                  class="large-text" 
                                  placeholder="توضیحات اضافی در مورد این هزینه..."><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit_expense" class="button button-primary" value="<?php echo $expense_id > 0 ? 'بروزرسانی هزینه' : 'ثبت هزینه'; ?>">
            <a href="<?php echo admin_url('admin.php?page=sc-expenses'); ?>" class="button">انصراف</a>
        </p>
    </form>
</div>




