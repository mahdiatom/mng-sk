<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

sc_check_and_create_tables();

global $wpdb;
$honors_table = $wpdb->prefix . 'sc_honors';
$categories_table = $wpdb->prefix . 'sc_honor_categories';
$members_table = $wpdb->prefix . 'sc_members';
$coaches_table = $wpdb->prefix . 'sc_coaches';

$message = '';
$message_type = '';

// پردازش حذف دسته‌جمعی
if (isset($_POST['bulk_delete']) && check_admin_referer('bulk_delete_honors')) {
    $honor_ids = isset($_POST['honor_ids']) && is_array($_POST['honor_ids']) ? array_map('absint', $_POST['honor_ids']) : [];
    
    if (!empty($honor_ids)) {
        $deleted_count = 0;
        
        // دریافت اطلاعات افتخارات برای حذف فایل‌ها
        $placeholders = implode(',', array_fill(0, count($honor_ids), '%d'));
        $honors = $wpdb->get_results($wpdb->prepare(
            "SELECT id, file_url FROM $honors_table WHERE id IN ($placeholders)",
            ...$honor_ids
        ));
        
        // حذف فایل‌ها
        foreach ($honors as $honor) {
            if (!empty($honor->file_url)) {
                $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $honor->file_url);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }
        
        // حذف رکوردها از دیتابیس
        foreach ($honor_ids as $honor_id) {
            $result = $wpdb->delete($honors_table, ['id' => $honor_id], ['%d']);
            if ($result !== false && $result > 0) {
                $deleted_count++;
            }
        }
        
        wp_cache_flush();
        
        if ($deleted_count > 0) {
            $message = $deleted_count . ' افتخار با موفقیت حذف شد.';
            $message_type = 'success';
        } else {
            $message = 'خطا در حذف افتخارات.';
            $message_type = 'error';
        }
        
        wp_safe_redirect(add_query_arg('deleted', $deleted_count, admin_url('admin.php?page=sc-honors')));
        exit;
    } else {
        $message = 'لطفاً حداقل یک افتخار را انتخاب کنید.';
        $message_type = 'error';
    }
}

// پردازش حذف تکی
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['honor_id'])) {
    check_admin_referer('delete_honor_' . $_GET['honor_id']);
    $honor_id = absint($_GET['honor_id']);
    
    // دریافت اطلاعات افتخار برای حذف فایل
    $honor = $wpdb->get_row($wpdb->prepare(
        "SELECT id, file_url FROM $honors_table WHERE id = %d",
        $honor_id
    ));
    
    if ($honor) {
        // حذف فایل اگر وجود دارد
        if (!empty($honor->file_url)) {
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $honor->file_url);
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        // حذف رکورد از دیتابیس
        $wpdb->delete($honors_table, ['id' => $honor_id], ['%d']);
        wp_cache_flush();
    }
    
    wp_safe_redirect(add_query_arg('deleted', '1', admin_url('admin.php?page=sc-honors')));
    exit;
}

// نمایش پیام موفقیت پس از redirect
if (isset($_GET['deleted'])) {
    $deleted_count = absint($_GET['deleted']);
    $message = sprintf(_n('%d افتخار با موفقیت حذف شد.', '%d افتخار با موفقیت حذف شدند.', $deleted_count), $deleted_count);
    $message_type = 'success';
}

// پردازش فیلترها
if (isset($_POST['filter_category']) || isset($_POST['filter_type'])) {
    $filter_category = isset($_POST['filter_category']) ? absint($_POST['filter_category']) : 'all';
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'all';
    
    $args = [];
    if ($filter_category != 'all') {
        $args['filter_category'] = $filter_category;
    }
    if ($filter_type != 'all') {
        $args['filter_type'] = $filter_type;
    }
    
    wp_redirect(add_query_arg($args, admin_url('admin.php?page=sc-honors')));
    exit;
}

// دریافت فیلترها
$filter_category_raw = isset($_GET['filter_category']) ? $_GET['filter_category'] : 'all';
$filter_category = ($filter_category_raw === 'all' || $filter_category_raw === '') ? 'all' : absint($filter_category_raw);
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Pagination (از screen option یا پیش‌فرض ۱۰)
$screen_per_page = get_user_meta(get_current_user_id(), 'honors_per_page', true);
$per_page = $screen_per_page ? max(1, (int) $screen_per_page) : 10;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// ساخت WHERE clause
$where = "1=1";
$where_values = [];

// فیلتر دسته (فقط وقتی عدد معتبر است)
if ($filter_category !== 'all' && $filter_category > 0) {
    $where .= " AND category_id = %d";
    $where_values[] = $filter_category;
}

// فیلتر نوع (بازیکن یا مربی)
if ($filter_type === 'member') {
    $where .= " AND member_id IS NOT NULL AND member_id != 0";
} elseif ($filter_type === 'coach') {
    $where .= " AND coach_id IS NOT NULL AND coach_id != 0";
}

// جستجو
if (!empty($search)) {
    $where .= " AND (name LIKE %s OR description LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = $search_like;
    $where_values[] = $search_like;
}

// شمارش کل رکوردها
$count_query = "SELECT COUNT(*) FROM $honors_table WHERE $where";
if (!empty($where_values)) {
    $count_query = $wpdb->prepare($count_query, $where_values);
}
$total_items = $wpdb->get_var($count_query);

// دریافت رکوردها
$query = "SELECT * FROM $honors_table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
$where_values[] = $per_page;
$where_values[] = $offset;

if (!empty($where_values)) {
    $query = $wpdb->prepare($query, $where_values);
}

$honors = $wpdb->get_results($query);

// دریافت دسته‌ها برای فیلتر
$categories = $wpdb->get_results("SELECT id, name FROM $categories_table ORDER BY name ASC");

$total_pages = ceil($total_items / $per_page);

?>
<div class="wrap">
    <h1 class="wp-heading-inline">لیست افتخارات</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honor-categories')); ?>" class="page-title-action">دسته‌بندی افتخارات</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-honor-for-member')); ?>" class="page-title-action">افزودن افتخار برای بازیکن</a>
    <hr class="wp-header-end">

    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
<div class="filter_search_honors">
    <!-- فیلترها -->
    <form method="get" action="" style="margin: 15px 0;">
        <input type="hidden" name="page" value="sc-honors">
        <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
        <select name="filter_type" id="filter_type" style="margin-left: 5px;">
            <option value="all" <?php selected($filter_type, 'all'); ?>>همه (بازیکن و مربی)</option>
            <option value="member" <?php selected($filter_type, 'member'); ?>>فقط بازیکنان</option>
            <option value="coach" <?php selected($filter_type, 'coach'); ?>>فقط مربیان</option>
        </select>
        <select name="filter_category" id="filter_category" style="margin-left: 5px;">
            <option value="all" <?php selected($filter_category, 'all'); ?>>همه دسته‌ها</option>
            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($filter_category, $category->id); ?>>
                    <?php echo esc_html($category->name); ?>
                </option>
            <?php endforeach; ?>
            </select>
        <input type="submit" class="button" value="اعمال فیلتر">
    </form>

    <!-- جستجو بالای جدول، سمت چپ -->
    <div class="tablenav top" style="margin-bottom: 0;">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="sc-honors">
                <input type="hidden" name="filter_category" value="<?php echo $filter_category === 'all' ? 'all' : esc_attr($filter_category); ?>">
                <input type="hidden" name="filter_type" value="<?php echo esc_attr($filter_type); ?>">
                <label class="screen-reader-text" for="search_id">جستجو:</label>
                <input type="search" id="search_id" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و توضیحات..." style="width: 220px;">
                <input type="submit" id="search-submit" class="button" value="جستجو">
            </form>
        </div>
    </div>
            </div>
    <!-- فرم حذف دسته‌جمعی و جدول -->
    <form method="post" id="honors-form">
        <?php wp_nonce_field('bulk_delete_honors'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector">
                    <option value="">عملیات دسته‌جمعی...</option>
                    <option value="delete">حذف</option>
                </select>
                <input type="submit" name="bulk_delete" id="doaction" class="button action" value="اجرا">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="manage-column">نام بازیکن/مربی</th>
                    <th class="manage-column">عنوان افتخار</th>
                    <th class="manage-column">دسته</th>
                    <th class="manage-column">توضیحات</th>
                    <th class="manage-column">فایل</th>
                    <th class="manage-column">تاریخ ثبت</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($honors)) : ?>
                    <?php foreach ($honors as $honor) : ?>
                        <?php
                        // دریافت نام بازیکن یا مربی
                        $member_name = '-';
                        if (!empty($honor->member_id)) {
                            $member = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $members_table WHERE id = %d",
                                $honor->member_id
                            ));
                            if ($member) {
                                $member_name = esc_html($member->first_name . ' ' . $member->last_name);
                            }
                        } elseif (!empty($honor->coach_id)) {
                            $coach = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
                                $honor->coach_id
                            ));
                            if ($coach) {
                                $member_name = esc_html($coach->first_name . ' ' . $coach->last_name) . ' <span style="color: #666;">(مربی)</span>';
                            }
                        }
                        
                        // دریافت نام دسته
                        $category_name = '-';
                        $category = $wpdb->get_row($wpdb->prepare(
                            "SELECT name FROM $categories_table WHERE id = %d",
                            $honor->category_id
                        ));
                        if ($category) {
                            $category_name = esc_html($category->name);
                        }
                        
                        // حذف تکی URL
                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=sc-honors&action=delete&honor_id=' . $honor->id),
                            'delete_honor_' . $honor->id
                        );
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="honor_ids[]" value="<?php echo esc_attr($honor->id); ?>">
                            </th>
                            <td>
                                <?php echo $member_name; ?>
                                <div class="row-actions">
                                    <span class="delete">
                                        <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                                    </span>
                                </div>
                            </td>
                            <td><strong><?php echo esc_html($honor->name); ?></strong></td>
                            <td><?php echo $category_name; ?></td>
                            <td>
                                <?php 
                                $description = esc_html($honor->description);
                                if (mb_strlen($description) > 50) {
                                    echo mb_substr($description, 0, 50) . '...';
                                } else {
                                    echo $description ?: '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($honor->file_url)) : ?>
                                    <a href="<?php echo esc_url($honor->file_url); ?>" target="_blank" style="color: #2271b1; text-decoration: none;">📎 دانلود</a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(sc_date_shamsi($honor->created_at, 'Y/m/d H:i')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            هنوز افتخاری ثبت نشده است.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination (دقیقاً مثل حضور و غیاب) -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
                <div class="tablenav-pages">
                    <?php
                    $pagination_args = ['page' => 'sc-honors'];
                    if ($filter_category !== 'all' && $filter_category > 0) {
                        $pagination_args['filter_category'] = $filter_category;
                    }
                    if ($filter_type !== 'all') {
                        $pagination_args['filter_type'] = $filter_type;
                    }
                    if (!empty($search)) {
                        $pagination_args['s'] = $search;
                    }
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '< قبلی ',
                        'next_text' => ' بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => $pagination_args
                    ]);
                    echo $page_links;
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // انتخاب/لغو انتخاب همه
    $('#cb-select-all').on('change', function() {
        $('input[name="honor_ids[]"]').prop('checked', $(this).prop('checked'));
    });
    
    $('input[name="honor_ids[]"]').on('change', function() {
        const total = $('input[name="honor_ids[]"]').length;
        const checked = $('input[name="honor_ids[]"]:checked').length;
        $('#cb-select-all').prop('checked', total === checked);
    });
    
    // اعتبارسنجی قبل از حذف دسته‌جمعی
    $('#doaction').on('click', function(e) {
        const action = $('#bulk-action-selector').val();
        if (action === 'delete') {
            const checked = $('input[name="honor_ids[]"]:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('لطفاً حداقل یک افتخار را انتخاب کنید.');
                return false;
            }
            if (!confirm('آیا از حذف ' + checked + ' افتخار انتخاب شده اطمینان دارید؟')) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
