<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

global $wpdb;
$expenses_table = $wpdb->prefix . 'sc_expenses';
$expense_categories_table = $wpdb->prefix . 'sc_expense_categories';

// دریافت تب فعال
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';

// پردازش حذف هزینه
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['expense_id']) && $active_tab === 'list') {
    check_admin_referer('delete_expense_' . $_GET['expense_id']);
    
    $expense_id = absint($_GET['expense_id']);
    $deleted = $wpdb->delete(
        $expenses_table,
        ['id' => $expense_id],
        ['%d']
    );
    
    if ($deleted) {
        echo '<div class="notice notice-success is-dismissible"><p>هزینه با موفقیت حذف شد.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>خطا در حذف هزینه.</p></div>';
    }
}

// پردازش دسته‌بندی‌ها
if ($active_tab === 'categories') {
    // پردازش افزودن دسته‌بندی
    if (isset($_POST['add_category']) && isset($_POST['category_name'])) {
        check_admin_referer('sc_add_category');
        $category_name = sanitize_text_field($_POST['category_name']);
        $category_description = !empty($_POST['category_description']) ? sanitize_textarea_field($_POST['category_description']) : NULL;
        
        if (!empty($category_name)) {
            $inserted = $wpdb->insert(
                $expense_categories_table,
                [
                    'name' => $category_name,
                    'description' => $category_description,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s']
            );
            
            if ($inserted) {
                echo '<div class="notice notice-success is-dismissible"><p>دسته‌بندی با موفقیت اضافه شد.</p></div>';
            }
        }
    }
    
    // پردازش ویرایش دسته‌بندی
    if (isset($_POST['update_category']) && isset($_POST['category_id'])) {
        check_admin_referer('sc_update_category');
        $category_id = absint($_POST['category_id']);
        $category_name = sanitize_text_field($_POST['category_name']);
        $category_description = !empty($_POST['category_description']) ? sanitize_textarea_field($_POST['category_description']) : NULL;
        
        if (!empty($category_name)) {
            $updated = $wpdb->update(
                $expense_categories_table,
                [
                    'name' => $category_name,
                    'description' => $category_description,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $category_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                echo '<div class="notice notice-success is-dismissible"><p>دسته‌بندی با موفقیت بروزرسانی شد.</p></div>';
            }
        }
    }
    
    // پردازش حذف دسته‌بندی
    if (isset($_GET['action']) && $_GET['action'] === 'delete_category' && isset($_GET['category_id'])) {
        check_admin_referer('delete_category_' . $_GET['category_id']);
        
        $category_id = absint($_GET['category_id']);
        
        // بررسی اینکه آیا هزینه‌ای با این دسته‌بندی وجود دارد
        $expenses_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $expenses_table WHERE category_id = %d",
            $category_id
        ));
        
        if ($expenses_count > 0) {
            echo '<div class="notice notice-error is-dismissible"><p>این دسته‌بندی قابل حذف نیست چون ' . $expenses_count . ' هزینه با آن مرتبط است.</p></div>';
        } else {
            $deleted = $wpdb->delete(
                $expense_categories_table,
                ['id' => $category_id],
                ['%d']
            );
            
            if ($deleted) {
                echo '<div class="notice notice-success is-dismissible"><p>دسته‌بندی با موفقیت حذف شد.</p></div>';
            }
        }
    }
}

// دریافت لیست دسته‌بندی‌ها
$categories = $wpdb->get_results("SELECT id, name, description FROM $expense_categories_table ORDER BY name ASC");

// ==================== تب 1: لیست هزینه‌ها ====================
if ($active_tab === 'list') {
    // دریافت فیلترها
    $filter_category = isset($_GET['filter_category']) ? absint($_GET['filter_category']) : 0;
    
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    }
    
    // تاریخ پیش‌فرض: امروز
    if (empty($filter_date_from)) {
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $filter_date_from_shamsi_default = $today_jalali[0] . '/' . 
                                           str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                           str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
    } else {
        $filter_date_from_shamsi_default = sc_date_shamsi_date_only($filter_date_from);
    }
    
    if (empty($filter_date_to)) {
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $filter_date_to_shamsi_default = $today_jalali[0] . '/' . 
                                         str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                         str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
    } else {
        $filter_date_to_shamsi_default = sc_date_shamsi_date_only($filter_date_to);
    }
    
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_category > 0) {
        $where_conditions[] = "e.category_id = %d";
        $where_values[] = $filter_category;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "e.expense_date_gregorian >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "e.expense_date_gregorian <= %s";
        $where_values[] = $filter_date_to;
    }
    
    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_conditions[] = "(e.name LIKE %s OR e.description LIKE %s)";
        $where_values[] = $search_like;
        $where_values[] = $search_like;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت تعداد کل رکوردها برای pagination
    $total_query = "SELECT COUNT(*) FROM $expenses_table e WHERE $where_clause";
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($total_query);
    }
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // دریافت لیست هزینه‌ها
    $query_values = $where_values;
    $query = "SELECT SQL_CALC_FOUND_ROWS e.*, 
                     ec.name as category_name
              FROM $expenses_table e
              LEFT JOIN $expense_categories_table ec ON e.category_id = ec.id
              WHERE $where_clause
              ORDER BY e.expense_date_gregorian DESC, e.created_at DESC
              LIMIT %d OFFSET %d";
    
    $query_values[] = $per_page;
    $query_values[] = $offset;
    
    if (!empty($query_values)) {
        $expenses = $wpdb->get_results($wpdb->prepare($query, $query_values));
    } else {
        $expenses = $wpdb->get_results($query);
    }
    
    $total_items = $wpdb->get_var("SELECT FOUND_ROWS()");
    $total_pages = ceil($total_items / $per_page);
}

// ==================== تب 2: مدیریت دسته‌بندی‌ها ====================
if ($active_tab === 'categories') {
    $editing_category = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit_category' && isset($_GET['category_id'])) {
        $category_id = absint($_GET['category_id']);
        $editing_category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $expense_categories_table WHERE id = %d",
            $category_id
        ));
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست هزینه‌ها</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-expense'); ?>" class="page-title-action">ثبت هزینه جدید</a>
    <hr class="wp-header-end">
    
    <!-- تب‌ها -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=sc-expenses&tab=list" class="nav-tab <?php echo $active_tab === 'list' ? 'nav-tab-active' : ''; ?>">
            لیست هزینه‌ها
        </a>
        <a href="?page=sc-expenses&tab=categories" class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">
            مدیریت دسته‌بندی‌ها
        </a>
    </h2>
    
    <?php if ($active_tab === 'list') : ?>
        <!-- تب 1: لیست هزینه‌ها -->
        <!-- فیلترها -->
        <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <input type="hidden" name="page" value="sc-expenses">
            <input type="hidden" name="tab" value="list">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="filter_category">دسته‌بندی</label>
                    </th>
                    <td>
                        <select name="filter_category" id="filter_category" style="width: 300px; padding: 5px;">
                            <option value="0">همه دسته‌بندی‌ها</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($filter_category, $category->id); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>بازه تاریخ (شمسی)</label>
                    </th>
                    <td>
                        <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" 
                               value="<?php echo esc_attr($filter_date_from_shamsi_default); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="از تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                        <span>تا</span>
                        <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" 
                               value="<?php echo esc_attr($filter_date_to_shamsi_default); ?>" 
                               class="regular-text persian-date-input" 
                               placeholder="تا تاریخ (شمسی)" 
                               style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                        <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="s">جستجو</label>
                    </th>
                    <td>
                        <input type="text" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در نام هزینه و توضیحات..." style="width: 400px; padding: 5px;">
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <?php
                // ساخت URL برای export Excel
                $export_url = admin_url('admin.php?page=sc-expenses&sc_export=excel&export_type=expenses');
                $export_url = add_query_arg('filter_category', isset($_GET['filter_category']) ? $_GET['filter_category'] : 0, $export_url);
                if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
                    $export_url = add_query_arg('filter_date_from', $_GET['filter_date_from'], $export_url);
                }
                if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
                    $export_url = add_query_arg('filter_date_to', $_GET['filter_date_to'], $export_url);
                }
                if (isset($_GET['s']) && !empty($_GET['s'])) {
                    $export_url = add_query_arg('s', $_GET['s'], $export_url);
                }
                $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                    📊 خروجی Excel
                </a>
                <a href="<?php echo admin_url('admin.php?page=sc-expenses&tab=list'); ?>" class="button">پاک کردن فیلترها</a>
            </p>
        </form>
        
        <!-- لیست هزینه‌ها -->
        <?php if (empty($expenses)) : ?>
            <div class="notice notice-info">
                <p>هیچ هزینه‌ای یافت نشد.</p>
            </div>
        <?php else : ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ردیف</th>
                            <th>نام هزینه</th>
                            <th>دسته‌بندی</th>
                            <th>تاریخ</th>
                            <th>مبلغ</th>
                            <th>توضیحات</th>
                            <th style="width: 150px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $start_number = ($current_page - 1) * $per_page;
                        foreach ($expenses as $index => $expense) : 
                            $row_number = $start_number + $index + 1;
                        ?>
                            <tr>
                                <td><?php echo $row_number; ?></td>
                                <td><strong><?php echo esc_html($expense->name); ?></strong></td>
                                <td><?php echo $expense->category_name ? esc_html($expense->category_name) : '-'; ?></td>
                                <td>
                                    <strong><?php echo esc_html($expense->expense_date_shamsi); ?></strong>
                                </td>
                                <td>
                                    <strong style="color: #d63638;">
                                        <?php echo number_format($expense->amount, 0, '.', ','); ?> تومان
                                    </strong>
                                </td>
                                <td><?php echo $expense->description ? esc_html(wp_trim_words($expense->description, 20)) : '-'; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense->id); ?>" 
                                       class="button button-small">ویرایش</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sc-expenses&tab=list&action=delete&expense_id=' . $expense->id), 'delete_expense_' . $expense->id); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('آیا مطمئن هستید که می‌خواهید این هزینه را حذف کنید؟');"
                                       style="background-color: #d63638; color: #fff; border-color: #d63638;">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom" style="margin-top: 20px;">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links([
                                'base' => add_query_arg(['paged' => '%#%', 'tab' => 'list']),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ]);
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($active_tab === 'categories') : ?>
        <!-- تب 2: مدیریت دسته‌بندی‌ها -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
            <!-- فرم افزودن/ویرایش دسته‌بندی -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h2><?php echo $editing_category ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید'; ?></h2>
                
                <?php if ($editing_category) : ?>
                    <p><a href="<?php echo admin_url('admin.php?page=sc-expenses&tab=categories'); ?>" class="button">افزودن دسته‌بندی جدید</a></p>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php if ($editing_category) : ?>
                        <?php wp_nonce_field('sc_update_category'); ?>
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($editing_category->id); ?>">
                        <input type="hidden" name="update_category" value="1">
                    <?php else : ?>
                        <?php wp_nonce_field('sc_add_category'); ?>
                        <input type="hidden" name="add_category" value="1">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="category_name">نام دسته‌بندی <span style="color:red;">*</span></label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="category_name" 
                                       id="category_name" 
                                       value="<?php echo $editing_category ? esc_attr($editing_category->name) : ''; ?>" 
                                       class="regular-text" 
                                       required
                                       style="width: 100%;">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_description">توضیحات</label>
                            </th>
                            <td>
                                <textarea name="category_description" 
                                          id="category_description" 
                                          rows="3" 
                                          class="large-text"><?php echo $editing_category ? esc_textarea($editing_category->description) : ''; ?></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php echo $editing_category ? 'بروزرسانی' : 'افزودن'; ?>">
                        <?php if ($editing_category) : ?>
                            <a href="<?php echo admin_url('admin.php?page=sc-expenses&tab=categories'); ?>" class="button">انصراف</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <!-- لیست دسته‌بندی‌ها -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h2>لیست دسته‌بندی‌ها</h2>
                
                <?php if (empty($categories)) : ?>
                    <p>هیچ دسته‌بندی‌ای ثبت نشده است.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>توضیحات</th>
                                <th style="width: 150px;">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                    <td><?php echo $category->description ? esc_html($category->description) : '-'; ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=sc-expenses&tab=categories&action=edit_category&category_id=' . $category->id); ?>" 
                                           class="button button-small">ویرایش</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sc-expenses&tab=categories&action=delete_category&category_id=' . $category->id), 'delete_category_' . $category->id); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('آیا مطمئن هستید؟');"
                                           style="background-color: #d63638; color: #fff; border-color: #d63638;">حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 20px;
}
.nav-tab {
    padding: 10px 15px;
    text-decoration: none;
    border: 1px solid #ccc;
    border-bottom: none;
    background: #f1f1f1;
    color: #555;
}
.nav-tab-active {
    background: #fff;
    border-bottom-color: #fff;
    color: #000;
    font-weight: bold;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // تابع تبدیل تاریخ شمسی به میلادی (برای ارسال به سرور)
    function jalaliToGregorian(jy, jm, jd) {
        var gy = (jy <= 979) ? 621 : 1600;
        jy -= (jy <= 979) ? 0 : 979;
        var days = (365 * jy) + (parseInt(jy / 33) * 8) + (parseInt(((jy % 33) + 3) / 4)) + 
                   78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        gy += 400 * (parseInt(days / 146097));
        days = days % 146097;
        if (days > 36524) {
            gy += 100 * (parseInt(--days / 36524));
            days = days % 36524;
            if (days >= 365) days++;
        }
        gy += 4 * (parseInt(days / 1461));
        days = days % 1461;
        if (days > 365) {
            gy += parseInt((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var gd = days + 1;
        var sal_a = [0, 31, ((gy % 4 == 0 && gy % 100 != 0) || (gy % 400 == 0)) ? 29 : 28,
                     31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        while (gm < 13 && gd > sal_a[gm]) {
            gd -= sal_a[gm];
            gm++;
        }
        return [gy, gm, gd];
    }
    
    // تبدیل تاریخ شمسی به میلادی برای فیلتر
    function convertShamsiToGregorian(shamsiDate) {
        if (!shamsiDate || shamsiDate === '') return '';
        var parts = shamsiDate.split('/');
        if (parts.length !== 3) return '';
        var jy = parseInt(parts[0]);
        var jm = parseInt(parts[1]);
        var jd = parseInt(parts[2]);
        var gregorian = jalaliToGregorian(jy, jm, jd);
        return gregorian[0] + '-' + 
               (gregorian[1] < 10 ? '0' + gregorian[1] : gregorian[1]) + '-' + 
               (gregorian[2] < 10 ? '0' + gregorian[2] : gregorian[2]);
    }
    
    // تبدیل تاریخ شمسی به میلادی هنگام تغییر
    function updateGregorianDate($shamsiInput) {
        var shamsiValue = $shamsiInput.val();
        var gregorianValue = convertShamsiToGregorian(shamsiValue);
        
        // پیدا کردن hidden input مربوطه
        var inputId = $shamsiInput.attr('id');
        if (inputId === 'filter_date_from_shamsi') {
            $('#filter_date_from').val(gregorianValue);
        } else {
            $('#filter_date_to').val(gregorianValue);
        }
    }
    
    $(document).on('change', '#filter_date_from_shamsi, #filter_date_to_shamsi', function() {
        updateGregorianDate($(this));
    });
});
</script>



