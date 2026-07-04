<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!function_exists('sc_can_manage_honors_in_admin') || !sc_can_manage_honors_in_admin()) {
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

$honor_status_labels = [
    'pending' => 'در انتظار بررسی',
    'approved' => 'تایید شده',
    'rejected' => 'عدم تایید',
];

// پردازش عملیات دسته‌جمعی

if (isset($_POST['bulk_apply']) && check_admin_referer('bulk_delete_honors')) {
    $honor_ids = isset($_POST['honor_ids']) && is_array($_POST['honor_ids']) ? array_map('absint', $_POST['honor_ids']) : [];
    $bulk_action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';
    
    if (empty($honor_ids)) {
        $message = 'لطفاً حداقل یک افتخار را انتخاب کنید.';
        $message_type = 'error';
    } elseif ($bulk_action === 'delete') {
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
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, name, member_id, coach_id, status FROM $honors_table WHERE id = %d", $honor_id), ARRAY_A);
            $result = $wpdb->delete($honors_table, ['id' => $honor_id], ['%d']);
            if ($result !== false && $result > 0) {
                if (function_exists('sc_log_activity') && $row) {
                    sc_log_activity('deleted', 'honor', $honor_id, 'افتخار «' . ($row['name'] ?? '') . '» حذف شد', $row, null);
                }
                $deleted_count++;
            }
        }

        wp_cache_flush();
        wp_safe_redirect(add_query_arg('deleted', $deleted_count, admin_url('admin.php?page=sc-honors')));
        exit;
    } elseif (in_array($bulk_action, ['approve', 'reject'], true)) {
        $new_status = ($bulk_action === 'approve') ? 'approved' : 'rejected';
        $updated_count = 0;

        foreach ($honor_ids as $honor_id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, name, member_id, coach_id, status FROM $honors_table WHERE id = %d", $honor_id), ARRAY_A);
            if (!$row) {
                continue;
            }

            $result = $wpdb->update(
                $honors_table,
                ['status' => $new_status, 'updated_at' => current_time('mysql')],
                ['id' => $honor_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($result !== false) {
                if (function_exists('sc_log_activity')) {
                    sc_log_activity(
                        'updated',
                        'honor',
                        $honor_id,
                        'وضعیت افتخار «' . ($row['name'] ?? '') . '» به «' . $honor_status_labels[$new_status] . '» تغییر یافت',
                        $row,
                        ['status' => $new_status]
                    );
                }
                $updated_count++;
            }
        }

        wp_cache_flush();
        wp_safe_redirect(add_query_arg([
            'status_updated' => $updated_count,
            'new_status' => $new_status,
        ], admin_url('admin.php?page=sc-honors')));
        exit;
    } else {
        $message = 'لطفاً یک عملیات دسته‌جمعی معتبر انتخاب کنید.';
        $message_type = 'error';
    }
}

// پردازش حذف تکی
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['honor_id'])) {
    check_admin_referer('delete_honor_' . $_GET['honor_id']);
    $honor_id = absint($_GET['honor_id']);
    
    // دریافت اطلاعات افتخار برای حذف فایل
    $honor = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, file_url, member_id FROM $honors_table WHERE id = %d",
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
        if (function_exists('sc_log_activity')) {
            sc_log_activity('deleted', 'honor', $honor_id, 'افتخار «' . ($honor->name ?? '') . '» حذف شد', (array) $honor, null);
        }
        wp_cache_flush();
    }
    
    wp_safe_redirect(add_query_arg('deleted', '1', admin_url('admin.php?page=sc-honors')));
    exit;
}

// پردازش تایید/عدم تایید تکی
if (isset($_GET['action'], $_GET['honor_id']) && in_array($_GET['action'], ['approve', 'reject'], true)) {
    check_admin_referer('change_honor_status_' . $_GET['honor_id']);
    $honor_id = absint($_GET['honor_id']);
    $new_status = ($_GET['action'] === 'approve') ? 'approved' : 'rejected';

    $row = $wpdb->get_row($wpdb->prepare("SELECT id, name, member_id, coach_id, status FROM $honors_table WHERE id = %d", $honor_id), ARRAY_A);
    if ($row) {
        $wpdb->update(
            $honors_table,
            ['status' => $new_status, 'updated_at' => current_time('mysql')],
            ['id' => $honor_id],
            ['%s', '%s'],
            ['%d']
        );

        if (function_exists('sc_log_activity')) {
            sc_log_activity(
                'updated',
                'honor',
                $honor_id,
                'وضعیت افتخار «' . ($row['name'] ?? '') . '» به «' . $honor_status_labels[$new_status] . '» تغییر یافت',
                $row,
                ['status' => $new_status]
            );
        }
        wp_cache_flush();
    }

    wp_safe_redirect(add_query_arg([
        'status_updated' => 1,
        'new_status' => $new_status,
    ], admin_url('admin.php?page=sc-honors')));
    exit;
}

// نمایش پیام موفقیت پس از redirect
if (isset($_GET['deleted'])) {
    $deleted_count = absint($_GET['deleted']);
    $message = sprintf(_n('%d افتخار با موفقیت حذف شد.', '%d افتخار با موفقیت حذف شدند.', $deleted_count), $deleted_count);
    $message_type = 'success';
}

if (isset($_GET['status_updated'])) {
    $updated_count = absint($_GET['status_updated']);
    $new_status = isset($_GET['new_status']) ? sanitize_key($_GET['new_status']) : 'pending';
    if (!isset($honor_status_labels[$new_status])) {
        $new_status = 'pending';
    }
    $message = sprintf(_n('%d افتخار به وضعیت «%s» تغییر کرد.', '%d افتخار به وضعیت «%s» تغییر کردند.', $updated_count), $updated_count, $honor_status_labels[$new_status]);
    $message_type = 'success';
}

if (isset($_GET['honor_updated']) && $_GET['honor_updated'] === '1') {
    $message = 'افتخار با موفقیت ویرایش شد.';
    $message_type = 'success';
}

if (isset($_POST['update_honor']) && check_admin_referer('update_admin_honor_nonce')) {
    $honor_id = isset($_POST['honor_id']) ? absint($_POST['honor_id']) : 0;
    $honor_name = isset($_POST['honor_name']) ? sanitize_text_field(wp_unslash($_POST['honor_name'])) : '';
    $honor_category = isset($_POST['honor_category_id']) ? absint($_POST['honor_category_id']) : 0;
    $honor_description = isset($_POST['honor_description']) ? sanitize_textarea_field(wp_unslash($_POST['honor_description'])) : '';
    $honor_status = isset($_POST['honor_status']) ? sanitize_key(wp_unslash($_POST['honor_status'])) : 'pending';
    $honor_coach_id = isset($_POST['honor_coach_id']) ? absint($_POST['honor_coach_id']) : 0;

    if (!in_array($honor_status, ['pending', 'approved', 'rejected'], true)) {
        $honor_status = 'pending';
    }

    if ($honor_id <= 0 || $honor_name === '' || $honor_category <= 0) {
        $message = 'اطلاعات ویرایش افتخار ناقص است.';
        $message_type = 'error';
    } else {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $honors_table WHERE id = %d",
            $honor_id
        ), ARRAY_A);

        if (!$existing) {
            $message = 'افتخار یافت نشد.';
            $message_type = 'error';
        } else {
            $category_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $categories_table WHERE id = %d",
                $honor_category
            ));

            if (!$category_exists) {
                $message = 'دسته انتخاب‌شده معتبر نیست.';
                $message_type = 'error';
            } else {
                $update_data = [
                    'name' => $honor_name,
                    'category_id' => $honor_category,
                    'description' => $honor_description !== '' ? $honor_description : null,
                    'status' => $honor_status,
                    'updated_at' => current_time('mysql'),
                ];
                $update_formats = ['%s', '%d', '%s', '%s', '%s'];

                if (!empty($existing['member_id'])) {
                    $update_data['coach_id'] = $honor_coach_id > 0 ? $honor_coach_id : null;
                    $update_formats[] = $honor_coach_id > 0 ? '%d' : '%s';
                }

                $updated = $wpdb->update(
                    $honors_table,
                    $update_data,
                    ['id' => $honor_id],
                    $update_formats,
                    ['%d']
                );

                if ($updated !== false) {
                    if (function_exists('sc_log_activity')) {
                        sc_log_activity('updated', 'honor', $honor_id, 'افتخار «' . $honor_name . '» ویرایش شد', $existing, $update_data);
                    }
                    wp_safe_redirect(add_query_arg('honor_updated', '1', admin_url('admin.php?page=sc-honors')));
                    exit;
                }

                $message = 'خطا در ذخیره تغییرات افتخار.';
                $message_type = 'error';
            }
        }
    }
}

// پردازش فیلترها (فرم با method="get" است)
$filter_category_raw = isset($_GET['filter_category']) ? $_GET['filter_category'] : 'all';
$filter_category = ($filter_category_raw === 'all' || $filter_category_raw === '') ? 'all' : absint($filter_category_raw);
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
$filter_user = isset($_GET['filter_user']) ? sanitize_text_field($_GET['filter_user']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$filter_date_from = '';
$filter_date_to = '';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
if ($filter_date_from_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if ($filter_date_to_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}
if ($filter_date_from === '' && !empty($_GET['filter_date_from'])) {
    $filter_date_from = sanitize_text_field(wp_unslash($_GET['filter_date_from']));
}
if ($filter_date_to === '' && !empty($_GET['filter_date_to'])) {
    $filter_date_to = sanitize_text_field(wp_unslash($_GET['filter_date_to']));
}
$filter_status = isset($_GET['filter_status']) ? sanitize_key($_GET['filter_status']) : 'all';
if (!in_array($filter_status, ['all', 'pending', 'approved', 'rejected'], true)) {
    $filter_status = 'all';
}

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

// فیلتر وضعیت
if ($filter_status !== 'all') {
    $where .= " AND status = %s";
    $where_values[] = $filter_status;
}

// فیلتر نام کاربر (بازیکن یا مربی)
if (!empty($filter_user)) {
    if (preg_match('/^m_(\d+)$/', $filter_user, $m)) {
        $where .= " AND member_id = %d";
        $where_values[] = absint($m[1]);
    } elseif (preg_match('/^c_(\d+)$/', $filter_user, $m)) {
        $where .= " AND coach_id = %d";
        $where_values[] = absint($m[1]);
    }
}

// جستجو
if (!empty($search)) {
    $where .= " AND (name LIKE %s OR description LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = $search_like;
    $where_values[] = $search_like;
}

// بازه تاریخ (ثبت رکورد)
if ($filter_date_from !== '' && $filter_date_to !== '') {
    $where .= ' AND DATE(created_at) >= %s AND DATE(created_at) <= %s';
    $where_values[] = $filter_date_from;
    $where_values[] = $filter_date_to;
} elseif ($filter_date_from !== '') {
    $where .= ' AND DATE(created_at) >= %s';
    $where_values[] = $filter_date_from;
} elseif ($filter_date_to !== '') {
    $where .= ' AND DATE(created_at) <= %s';
    $where_values[] = $filter_date_to;
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

// دریافت لیست بازیکنان و مربیان برای فیلتر نام کاربر
$members_for_filter = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
$coaches_for_filter = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// متن انتخاب‌شده برای فیلتر کاربر
$filter_user_text = 'همه کاربران';
if (!empty($filter_user)) {
    if (preg_match('/^m_(\d+)$/', $filter_user, $um)) {
        $mid = absint($um[1]);
        foreach ($members_for_filter as $mem) {
            if ($mem->id == $mid) {
                $filter_user_text = $mem->first_name . ' ' . $mem->last_name . ' - ' . ($mem->national_id ?: $mem->id) . ' (بازیکن)';
                break;
            }
        }
    } elseif (preg_match('/^c_(\d+)$/', $filter_user, $uc)) {
        $cid = absint($uc[1]);
        foreach ($coaches_for_filter as $coach) {
            if ($coach->id == $cid) {
                $filter_user_text = $coach->first_name . ' ' . $coach->last_name . ' (مربی)';
                break;
            }
        }
    }
}

$total_pages = ceil($total_items / $per_page);

$edit_honor = null;
if (isset($_GET['edit_honor'])) {
    $edit_honor_id = absint($_GET['edit_honor']);
    if ($edit_honor_id > 0) {
        $edit_honor = $wpdb->get_row($wpdb->prepare(
            "SELECT h.*, c.name AS category_name,
                    m.first_name AS member_first_name, m.last_name AS member_last_name
             FROM $honors_table h
             LEFT JOIN $categories_table c ON h.category_id = c.id
             LEFT JOIN $members_table m ON h.member_id = m.id
             WHERE h.id = %d",
            $edit_honor_id
        ));
    }
}

$coaches_for_edit = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

$active_filters_count = 0;
if ($filter_category !== 'all' && (int) $filter_category > 0) {
    $active_filters_count++;
}
if ($filter_type !== 'all') {
    $active_filters_count++;
}
if ($filter_status !== 'all') {
    $active_filters_count++;
}
if (!empty($filter_user) && $filter_user !== '0') {
    $active_filters_count++;
}
if ($search !== '') {
    $active_filters_count++;
}
if ($filter_date_from !== '' || $filter_date_to !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$status_badge_map = [
    'pending' => 'sc-badge--warning',
    'approved' => 'sc-badge--success',
    'rejected' => 'sc-badge--danger',
];

?>
<?php if ($message) : ?>
    <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
<?php endif; ?>

<div class="wrap sc-honors-list-wrap">
    <div class="sc-honors-list-header">
        <div class="sc-honors-list-header-text">
            <h1 class="sc-honors-list-title">لیست افتخارات</h1>
            <p class="sc-honors-list-desc">مدیریت افتخارات بازیکنان و مربیان</p>
        </div>
        <div class="sc-honors-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honor-categories')); ?>" class="sc-honors-list-secondary-btn">دسته‌بندی افتخارات</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-honor-for-member')); ?>" class="sc-honors-list-add-btn">افزودن افتخار برای بازیکن</a>
        </div>
    </div>

    <?php if ($edit_honor) :
        $edit_player_name = '';
        if (!empty($edit_honor->member_id)) {
            $edit_player_name = trim(($edit_honor->member_first_name ?? '') . ' ' . ($edit_honor->member_last_name ?? ''));
        }
    ?>
        <div class="sc-honors-edit-card">
            <h2 class="sc-honors-edit-title">ویرایش افتخار</h2>
            <?php if ($edit_player_name !== '') : ?>
                <p><strong>بازیکن:</strong> <?php echo esc_html($edit_player_name); ?></p>
            <?php elseif (!empty($edit_honor->coach_id) && empty($edit_honor->member_id)) : ?>
                <?php
                $edit_coach = $wpdb->get_row($wpdb->prepare(
                    "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
                    $edit_honor->coach_id
                ));
                ?>
                <p><strong>مربی:</strong> <?php echo esc_html($edit_coach ? trim($edit_coach->first_name . ' ' . $edit_coach->last_name) : '-'); ?></p>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('update_admin_honor_nonce'); ?>
                <input type="hidden" name="honor_id" value="<?php echo esc_attr($edit_honor->id); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="honor_name">عنوان افتخار</label></th>
                        <td><input type="text" name="honor_name" id="honor_name" class="regular-text" value="<?php echo esc_attr($edit_honor->name); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="honor_category_id">دسته</label></th>
                        <td>
                            <select name="honor_category_id" id="honor_category_id" class="regular-text" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" <?php selected((int) $edit_honor->category_id, (int) $category->id); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php if (!empty($edit_honor->member_id)) : ?>
                    <tr>
                        <th scope="row"><label for="honor_coach_id">مربی مرتبط</label></th>
                        <td>
                            <select name="honor_coach_id" id="honor_coach_id" class="regular-text">
                                <option value="0">هیچ کدام</option>
                                <?php foreach ($coaches_for_edit as $coach_item) :
                                    $coach_label = trim($coach_item->first_name . ' ' . $coach_item->last_name);
                                    if ($coach_label === '') {
                                        $coach_label = 'مربی #' . $coach_item->id;
                                    }
                                ?>
                                    <option value="<?php echo esc_attr($coach_item->id); ?>" <?php selected((int) ($edit_honor->coach_id ?? 0), (int) $coach_item->id); ?>>
                                        <?php echo esc_html($coach_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><label for="honor_status">وضعیت</label></th>
                        <td>
                            <select name="honor_status" id="honor_status" class="regular-text">
                                <?php foreach ($honor_status_labels as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($edit_honor->status ?? 'pending', $status_key); ?>>
                                        <?php echo esc_html($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="honor_description">توضیحات</label></th>
                        <td>
                            <textarea name="honor_description" id="honor_description" rows="4" class="large-text"><?php echo esc_textarea($edit_honor->description ?? ''); ?></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit sc-honors-edit-actions">
                    <button type="submit" name="update_honor" class="button button-primary">ذخیره تغییرات</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honors')); ?>" class="button">انصراف</a>
                </p>
            </form>
        </div>
    <?php endif; ?>

    <div class="sc-honors-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-honors-list-filters-toolbar">
            <button type="button"
                    class="sc-honors-list-filters-toggle"
                    id="sc-honors-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-honors-filters-panel">
                <span class="sc-honors-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-honors-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-honors-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-honors-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honors')); ?>" class="sc-honors-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

    <form method="get" action="" class="honors-filter-form sc-honors-list-filters-panel" id="sc-honors-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
        <input type="hidden" name="page" value="sc-honors">

        <div class="sc-filter-grid">
            <div class="sc-filter-field">
                <label class="sc-filter-label">کاربر</label>
                <div class="sc-searchable-dropdown">
                    <input type="hidden" name="filter_user" id="filter_user" value="<?php echo esc_attr($filter_user); ?>">
                    <div class="sc-dropdown-toggle">
                        <span class="sc-dropdown-placeholder" <?php if (!empty($filter_user) && $filter_user !== '0') echo 'style="display:none"'; ?>>همه کاربران</span>
                        <span class="sc-dropdown-selected" <?php if (empty($filter_user) || $filter_user === '0') echo 'style="display:none"'; ?>><?php echo esc_html($filter_user_text); ?></span>
                        <span class="sc-dropdown-arrow">▼</span>
                    </div>
                    <div class="sc-dropdown-menu" >
                        <div class="sc-dropdown-search">
                            <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                        </div>
                        <div class="sc-dropdown-options">
                            <div class="sc-dropdown-option sc-visible"
                                 data-value="0"
                                 data-search="همه کاربران"
                                 onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                همه کاربران
                            </div>
                            <?php
                            $display_count = 0;
                            $max_display = 15;
                            foreach ($members_for_filter as $mem) :
                                $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                $display_count++;
                                $val = 'm_' . $mem->id;
                                $label = $mem->first_name . ' ' . $mem->last_name . ' - ' . ($mem->national_id ?: $mem->id) . ' (بازیکن)';
                                $search_txt = strtolower($mem->first_name . ' ' . $mem->last_name . ' ' . ($mem->national_id ?: ''));
                            ?>
                                <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                     data-value="<?php echo esc_attr($val); ?>"
                                     data-search="<?php echo esc_attr($search_txt); ?>"
                                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($val); ?>','<?php echo esc_js($label); ?>')">
                                    <?php echo esc_html($label); ?>
                                </div>
                            <?php endforeach;
                            foreach ($coaches_for_filter as $coach) :
                                $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                $display_count++;
                                $val = 'c_' . $coach->id;
                                $label = $coach->first_name . ' ' . $coach->last_name . ' (مربی)';
                                $search_txt = strtolower($coach->first_name . ' ' . $coach->last_name);
                            ?>
                                <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                     data-value="<?php echo esc_attr($val); ?>"
                                     data-search="<?php echo esc_attr($search_txt); ?>"
                                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($val); ?>','<?php echo esc_js($label); ?>')">
                                    <?php echo esc_html($label); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_type">نوع کاربر</label>
                <select name="filter_type" id="filter_type" class="sc-filter-control">
                    <option value="all" <?php selected($filter_type, 'all'); ?>>همه (بازیکن و مربی)</option>
                    <option value="member" <?php selected($filter_type, 'member'); ?>>فقط بازیکنان</option>
                    <option value="coach" <?php selected($filter_type, 'coach'); ?>>فقط مربیان</option>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_category">دسته افتخار</label>
                <select name="filter_category" id="filter_category" class="sc-filter-control">
                    <option value="all" <?php selected($filter_category, 'all'); ?>>همه دسته‌ها</option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->id); ?>" <?php selected($filter_category, $category->id); ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_status">وضعیت</label>
                <select name="filter_status" id="filter_status" class="sc-filter-control">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار بررسی</option>
                    <option value="approved" <?php selected($filter_status, 'approved'); ?>>تایید شده</option>
                    <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>عدم تایید</option>
                </select>
            </div>

            <div class="sc-filter-field">
                <label class="sc-filter-label" for="search_id">جستجو</label>
                <input type="search" id="search_id" name="s" class="sc-filter-control" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و توضیحات...">
            </div>

            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">بازه تاریخ ثبت (شمسی)</label>
                <div class="sc-honors-date-range">
                    <input type="text"
                           name="filter_date_from_shamsi"
                           id="honors_filter_date_from_shamsi"
                           value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                           class="persian-date-input sc-filter-control sc-no-default-date"
                           placeholder="از تاریخ"
                           readonly>
                    <input type="hidden" name="filter_date_from" id="honors_filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    <input type="text"
                           name="filter_date_to_shamsi"
                           id="honors_filter_date_to_shamsi"
                           value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                           class="persian-date-input sc-filter-control sc-no-default-date"
                           placeholder="تا تاریخ"
                           readonly>
                    <input type="hidden" name="filter_date_to" id="honors_filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
            </div>
        </div>

        <div class="sc-honors-list-filters-actions">
            <input type="submit" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-honors')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </div>
    </form>
    </div>

    <div class="sc-honors-list-table-card">
        <div class="sc-honors-list-summary"><span><?php echo (int) $total_items; ?> افتخار</span></div>
    <form method="post" id="honors-form">
        <?php wp_nonce_field('bulk_delete_honors'); ?>
        <input type="hidden" name="bulk_apply" value="1"><?php // JS form.submit() sends no submit-button name ?>

        <div class="tablenav top sc-honors-bulk-nav">
            <div class="alignleft actions bulkactions">
                <select name="bulk_action" id="bulk-action-selector">
                    <option value="">عملیات دسته‌جمعی...</option>
                    <option value="approve">تایید</option>
                    <option value="reject">عدم تایید</option>
                    <option value="delete">حذف</option>
                </select>
                <input type="submit" id="doaction" class="button action" value="اجرا">
            </div>
        </div>
        <div class="sc-honors-table-scroll">
        <table class="wp-list-table widefat striped sc-honors-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="manage-column">نام بازیکن/مربی</th>
                    <th class="manage-column">مربی مرتبط</th>
                    <th class="manage-column">عنوان افتخار</th>
                    <th class="manage-column">دسته</th>
                    <th class="manage-column sc-honor-description-col">توضیحات</th>
                    <th class="manage-column">فایل</th>
                    <th class="manage-column">وضعیت</th>
                    <th class="manage-column">تاریخ ثبت</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($honors)) : ?>
                    <?php foreach ($honors as $honor) : ?>
                        <?php
                        // دریافت نام بازیکن یا مربی
                        $member_name = '-';
                        $is_coach_honor = false;
                        if (!empty($honor->member_id)) {
                            $member = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $members_table WHERE id = %d",
                                $honor->member_id
                            ));
                            if ($member) {
                                $member_name = esc_html($member->first_name . ' ' . $member->last_name);
                            }
                        } elseif (!empty($honor->coach_id)) {
                            $is_coach_honor = true;
                            $coach = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
                                $honor->coach_id
                            ));
                            if ($coach) {
                                $member_name = esc_html($coach->first_name . ' ' . $coach->last_name);
                            }
                        }

                        // مربی مرتبط با افتخار بازیکن
                        $associated_coach_name = '-';
                        if (!empty($honor->member_id) && !empty($honor->coach_id)) {
                            $assoc_coach = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
                                $honor->coach_id
                            ));
                            if ($assoc_coach) {
                                $associated_coach_name = esc_html($assoc_coach->first_name . ' ' . $assoc_coach->last_name);
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

                        $delete_url = wp_nonce_url(
                            admin_url('admin.php?page=sc-honors&action=delete&honor_id=' . $honor->id),
                            'delete_honor_' . $honor->id
                        );
                        $approve_url = wp_nonce_url(
                            admin_url('admin.php?page=sc-honors&action=approve&honor_id=' . $honor->id),
                            'change_honor_status_' . $honor->id
                        );
                        $reject_url = wp_nonce_url(
                            admin_url('admin.php?page=sc-honors&action=reject&honor_id=' . $honor->id),
                            'change_honor_status_' . $honor->id
                        );
                        $edit_url = admin_url('admin.php?page=sc-honors&edit_honor=' . (int) $honor->id);
                        $status_key = isset($honor->status) ? $honor->status : 'pending';
                        $status_label = isset($honor_status_labels[$status_key]) ? $honor_status_labels[$status_key] : $honor_status_labels['pending'];
                        $status_badge = isset($status_badge_map[$status_key]) ? $status_badge_map[$status_key] : 'sc-badge--warning';
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="honor_ids[]" value="<?php echo esc_attr($honor->id); ?>">
                            </th>
                            <td data-label="نام بازیکن/مربی">
                                <strong><?php echo $member_name; ?></strong>
                                <?php if ($is_coach_honor) : ?>
                                    <span class="sc-badge sc-badge--soft">مربی</span>
                                <?php endif; ?>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo esc_url($edit_url); ?>">ویرایش</a> | </span>
                                    <?php if ($status_key !== 'approved') : ?>
                                        <span class="edit"><a href="<?php echo esc_url($approve_url); ?>">تایید</a> | </span>
                                    <?php endif; ?>
                                    <?php if ($status_key !== 'rejected') : ?>
                                        <span class="edit"><a href="<?php echo esc_url($reject_url); ?>">عدم تایید</a> | </span>
                                    <?php endif; ?>
                                    <span class="delete">
                                        <a href="<?php echo esc_url($delete_url); ?>" onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید؟' })">حذف</a>
                                    </span>
                                </div>
                            </td>
                            <td data-label="مربی مرتبط"><?php echo $associated_coach_name; ?></td>
                            <td data-label="عنوان افتخار"><strong><?php echo esc_html($honor->name); ?></strong></td>
                            <td data-label="دسته"><?php
                                echo $category_name !== '-'
                                    ? '<span class="sc-badge sc-badge--purple">' . $category_name . '</span>'
                                    : '<span class="sc-honors-muted">—</span>';
                            ?></td>
                            <td data-label="توضیحات" class="sc-honor-description-cell">
                                <?php
                                if (function_exists('sc_render_honor_description_cell')) {
                                    sc_render_honor_description_cell($honor->description, 100);
                                } else {
                                    echo esc_html($honor->description ?: '-');
                                }
                                ?>
                            </td>
                            <td data-label="فایل">
                                <?php if (!empty($honor->file_url)) : ?>
                                    <a href="<?php echo esc_url($honor->file_url); ?>" target="_blank" rel="noopener noreferrer" class="sc-honors-file-link">دانلود</a>
                                <?php else : ?>
                                    <span class="sc-honors-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="وضعیت"><span class="sc-badge <?php echo esc_attr($status_badge); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td data-label="تاریخ ثبت"><?php echo esc_html(sc_date_shamsi($honor->created_at, 'Y/m/d H:i')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="9" class="sc-honors-empty">هنوز افتخاری ثبت نشده است.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc-honors-pagination">
                <div class="tablenav-pages">
                    <?php
                    $pagination_args = ['page' => 'sc-honors'];
                    if ($filter_category !== 'all' && $filter_category > 0) {
                        $pagination_args['filter_category'] = $filter_category;
                    }
                    if ($filter_type !== 'all') {
                        $pagination_args['filter_type'] = $filter_type;
                    }
                    if ($filter_status !== 'all') {
                        $pagination_args['filter_status'] = $filter_status;
                    }
                    if (!empty($filter_user) && $filter_user !== '0') {
                        $pagination_args['filter_user'] = $filter_user;
                    }
                    if (!empty($search)) {
                        $pagination_args['s'] = $search;
                    }
                    if ($filter_date_from_shamsi !== '') {
                        $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                    }
                    if ($filter_date_to_shamsi !== '') {
                        $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                    }
                    if ($filter_date_from !== '') {
                        $pagination_args['filter_date_from'] = $filter_date_from;
                    }
                    if ($filter_date_to !== '') {
                        $pagination_args['filter_date_to'] = $filter_date_to;
                    }
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '‹',
                        'next_text' => '›',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => $pagination_args,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var $toggle = $('#sc-honors-filters-toggle');
    var $panel = $('#sc-honors-filters-panel');
    var $card = $toggle.closest('.sc-honors-list-filters-card');
    var $label = $toggle.find('.sc-honors-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });

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
        const checked = $('input[name="honor_ids[]"]:checked').length;
        const form = $(this).closest('form');
        if (checked === 0) {
            e.preventDefault();
            alert('لطفاً حداقل یک افتخار را انتخاب کنید.');
            return false;
        }

        if (action === 'delete') {
            e.preventDefault();
            scConfirm({ type: 'danger', message: 'آیا از حذف ' + checked + ' افتخار انتخاب شده اطمینان دارید؟' }).then(function(ok){
                if (ok) {
                    form.submit();
                }
            });
            return false;
        } else if (action === 'approve') {
            e.preventDefault();
            scConfirm({ type: 'warning', message: 'آیا از تایید ' + checked + ' افتخار انتخاب شده اطمینان دارید؟' }).then(function(ok){
                if (ok) {
                    form.submit();
                }
            });
            return false;
        } else if (action === 'reject') {
            e.preventDefault();
            scConfirm({ type: 'warning', message: 'آیا از عدم تایید ' + checked + ' افتخار انتخاب شده اطمینان دارید؟' }).then(function(ok){
                if (ok) {
                    form.submit();
                }
            });
            return false;
        } else {
            e.preventDefault();
            alert('لطفاً یک عملیات دسته‌جمعی انتخاب کنید.');
            return false;
        }
    });
});
</script>
