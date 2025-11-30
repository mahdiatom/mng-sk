<?php
global $invoices_list_table;

// دریافت لیست دوره‌ها برای فیلتر
global $wpdb;
$courses_table = $wpdb->prefix . 'sc_courses';
$courses = $wpdb->get_results(
    "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">صورت حساب‌ها</h1>
</div>

<!-- فیلترها -->
<div class="wrap" style="margin-top: 20px;">
    <form method="GET" action="" style="padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
        <input type="hidden" name="page" value="sc-invoices">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="filter_course">دوره</label>
                </th>
                <td>
                    <select name="filter_course" id="filter_course" style="width: 300px; padding: 5px;">
                        <option value="0">همه دوره‌ها</option>
                        <?php 
                        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
                        foreach ($courses as $course) : 
                        ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label>بازه تاریخ</label>
                </th>
                <td>
                    <?php 
                    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
                    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
                    ?>
                    <input type="date" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" style="padding: 5px; margin-left: 10px;">
                    <span>تا</span>
                    <input type="date" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" style="padding: 5px; margin-left: 10px;">
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="button">پاک کردن فیلترها</a>
        </p>
    </form>
</div>

<?php
echo '<div class="wrap">';
    echo '<form Method="get">';
        echo '<input type="hidden" name="page" value="sc-invoices">';
        
        // حفظ فیلترها در فرم جستجو
        if (isset($_GET['filter_course'])) {
            echo '<input type="hidden" name="filter_course" value="' . esc_attr($_GET['filter_course']) . '">';
        }
        if (isset($_GET['filter_date_from'])) {
            echo '<input type="hidden" name="filter_date_from" value="' . esc_attr($_GET['filter_date_from']) . '">';
        }
        if (isset($_GET['filter_date_to'])) {
            echo '<input type="hidden" name="filter_date_to" value="' . esc_attr($_GET['filter_date_to']) . '">';
        }
        if (isset($_GET['filter_status'])) {
            echo '<input type="hidden" name="filter_status" value="' . esc_attr($_GET['filter_status']) . '">';
        }
        
        $invoices_list_table->search_box('جستجو صورت حساب (نام، نام خانوادگی، کد ملی، شماره سفارش)', 'search_invoice');
        $invoices_list_table->views();
        $invoices_list_table->display();
    echo '</form>';
echo '</div>';
?>

