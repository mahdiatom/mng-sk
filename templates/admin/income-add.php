<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$income_categories_table = $wpdb->prefix . 'sc_income_categories';
$chapter_categories_table = $wpdb->prefix . 'sc_chapter_categories';

$categories = $wpdb->get_results("SELECT id, name FROM $income_categories_table ORDER BY name ASC");
$chapters = $wpdb->get_results("SELECT name FROM $chapter_categories_table ORDER BY name ASC");
if (function_exists('sc_secretary_filter_chapters_list')) {
    $chapters = sc_secretary_filter_chapters_list($chapters);
}

$income = null;
$income_id = isset($_GET['income_id']) ? absint($_GET['income_id']) : 0;
if ($income_id > 0) {
    if (function_exists('sc_secretary_can_access_income') && !sc_secretary_can_access_income($income_id)) {
        wp_die('دسترسی غیرمجاز.');
    }
    $incomes_table = $wpdb->prefix . 'sc_incomes';
    $income = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $incomes_table WHERE id = %d",
        $income_id
    ));
}

$income_name = $income ? $income->name : (isset($_POST['income_name']) ? sanitize_text_field($_POST['income_name']) : '');
$category_id = $income ? $income->category_id : (isset($_POST['category_id']) ? absint($_POST['category_id']) : 0);
$amount = $income ? floatval($income->amount) : (isset($_POST['amount']) ? floatval($_POST['amount']) : 0);
$chapter = $income ? (string) $income->chapter : (isset($_POST['chapter']) ? sanitize_text_field($_POST['chapter']) : '');
$description = $income ? $income->description : (isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '');

$income_date_shamsi = '';
if ($income && !empty($income->income_date_shamsi)) {
    $income_date_shamsi = $income->income_date_shamsi;
} elseif (isset($_POST['income_date_shamsi']) && !empty($_POST['income_date_shamsi'])) {
    $income_date_shamsi = sanitize_text_field($_POST['income_date_shamsi']);
} else {
    $today = new DateTime();
    $today_jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
    $income_date_shamsi = $today_jalali[0] . '/' .
        str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
        str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
}
?>

<div class="wrap sc-expense-add-page-header sc-finance-page-header">
    <h1 class="wp-heading-inline">
        <?php echo $income_id > 0 ? 'ویرایش درآمد' : 'ثبت درآمد جدید'; ?>
    </h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes')); ?>" class="page-title-action">لیست درآمدها</a>
    <?php if ($income_id > 0) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-income')); ?>" class="page-title-action">ثبت درآمد جدید</a>
    <?php endif; ?>
    <hr class="wp-header-end">
    <p class="sc-expense-add-subtitle">مشخصات درآمد، شعبه، دسته‌بندی و تاریخ را ثبت کنید.</p>
</div>
<div class="wrap sc-expense-add-page-body sc-finance-page-body">
    <form method="POST" action="" class="sc-expense-add-form">
        <?php wp_nonce_field('sc_add_income', 'sc_income_nonce'); ?>
        <?php if ($income_id > 0) : ?>
            <input type="hidden" name="income_id" value="<?php echo esc_attr($income_id); ?>">
        <?php endif; ?>

        <div class="sc-expense-add-panel sc-finance-panel postbox">
            <div class="postbox-header"><h2>اطلاعات درآمد</h2></div>
            <div class="inside">
        <table class="form-table sc_form-table sc-expense-add-form-table">
            <tbody>
                <tr class="sc-expense-field-row">
                    <th scope="row">
                        <label for="income_name">نام درآمد <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               name="income_name"
                               id="income_name"
                               value="<?php echo esc_attr($income_name); ?>"
                               class="regular-text"
                               placeholder="مثلاً: اجاره سالن، اسپانسر و..."
                               required>
                    </td>
                </tr>

                <tr class="sc-expense-field-row">
                    <th scope="row">
                        <label for="chapter">شعبه <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="chapter" id="chapter" required>
                            <option value="">-- انتخاب شعبه --</option>
                            <?php foreach ($chapters as $chapter_item) : ?>
                                <option value="<?php echo esc_attr($chapter_item->name); ?>" <?php selected($chapter, $chapter_item->name); ?>>
                                    <?php echo esc_html($chapter_item->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr class="sc-expense-field-row">
                    <th scope="row">
                        <label for="category_id">دسته‌بندی</label>
                    </th>
                    <td>
                        <select name="category_id" id="category_id">
                            <option value="0">-- بدون دسته‌بندی --</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($category_id, $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=categories')); ?>" class="button button-small">مدیریت دسته‌بندی‌ها</a>
                    </td>
                </tr>

                <tr class="sc-expense-field-row">
                    <th scope="row">
                        <label for="income_date_shamsi">تاریخ (شمسی) <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               name="income_date_shamsi"
                               id="income_date_shamsi"
                               value="<?php echo esc_attr($income_date_shamsi); ?>"
                               class="regular-text persian-date-input"
                               placeholder="مثلاً 1403/09/15"
                               readonly
                               required>
                        <input type="hidden" name="income_date_gregorian" id="income_date_gregorian" value="<?php echo $income ? esc_attr($income->income_date_gregorian) : ''; ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید. تاریخ پیش‌فرض: امروز</p>
                    </td>
                </tr>

                <tr class="sc-expense-field-row">
                    <th scope="row">
                        <label for="amount">مبلغ (تومان) <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text"
                               name="amount"
                               id="amount"
                               value="<?php echo $amount > 0 ? number_format($amount, 0, '.', ',') : ''; ?>"
                               class="regular-text"
                               placeholder="0"
                               required
                               dir="ltr"
                               inputmode="numeric">
                        <input type="hidden" name="amount_raw" id="amount_raw" value="<?php echo esc_attr($amount); ?>">
                        <p class="description">مبلغ درآمد را به تومان وارد کنید.</p>
                    </td>
                </tr>

                <tr class="sc-expense-field-row sc-expense-field-row--full">
                    <th scope="row">
                        <label for="description">توضیحات تکمیلی</label>
                    </th>
                    <td>
                        <textarea name="description"
                                  id="description"
                                  rows="5"
                                  class="large-text"
                                  placeholder="توضیحات اضافی در مورد این درآمد..."><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
            </div>
        </div>

        <p class="submit sc-expense-add-submit">
            <input type="submit" name="submit_income" class="button button-primary" value="<?php echo $income_id > 0 ? 'بروزرسانی درآمد' : 'ثبت درآمد'; ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes')); ?>" class="button button-secondary">انصراف</a>
        </p>
    </form>
</div>
