<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

sc_check_and_create_tables();

global $wpdb;
$cert_table = $wpdb->prefix . 'sc_certificates';
$members_table = $wpdb->prefix . 'sc_members';

$notice = '';
$notice_type = 'success';

if (isset($_POST['sc_bulk_delete_certificates'])) {
    check_admin_referer('sc_bulk_delete_certificates_nonce');
    $ids = isset($_POST['certificate_ids']) ? array_map('absint', (array) $_POST['certificate_ids']) : [];
    $ids = array_values(array_filter($ids));

    if (empty($ids)) {
        $notice = 'حداقل یک گواهینامه را انتخاب کنید.';
        $notice_type = 'error';
    } else {
        $in = implode(',', $ids);
        $deleted = $wpdb->query("DELETE FROM {$cert_table} WHERE id IN ({$in})");
        $deleted_count = is_numeric($deleted) ? (int) $deleted : 0;
        $notice = sprintf('%d گواهینامه حذف شد.', $deleted_count);
        $notice_type = 'success';
    }
}

if (isset($_GET['action'], $_GET['certificate_id']) && $_GET['action'] === 'delete') {
    $certificate_id = absint($_GET['certificate_id']);
    check_admin_referer('sc_delete_certificate_' . $certificate_id);
    $wpdb->delete($cert_table, ['id' => $certificate_id], ['%d']);
    wp_safe_redirect(add_query_arg(['page' => 'sc-certificates-list', 'deleted' => 1], admin_url('admin.php')));
    exit;
}

if (isset($_GET['deleted']) && (int) $_GET['deleted'] === 1) {
    $notice = 'گواهینامه با موفقیت حذف شد.';
    $notice_type = 'success';
}

if (isset($_POST['sc_update_certificate'])) {
    $certificate_id = isset($_POST['certificate_id']) ? absint($_POST['certificate_id']) : 0;
    check_admin_referer('sc_update_certificate_' . $certificate_id);

    if ($certificate_id > 0) {
        $updated = $wpdb->update(
            $cert_table,
            [
                'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
                'message_text' => sanitize_textarea_field(wp_unslash($_POST['message_text'] ?? '')),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $certificate_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated !== false) {
            $notice = 'گواهینامه با موفقیت ویرایش شد.';
            $notice_type = 'success';
        } else {
            $notice = 'خطا در ویرایش گواهینامه.';
            $notice_type = 'error';
        }
    }
}

$filter_user = isset($_GET['filter_user']) ? sanitize_text_field(wp_unslash($_GET['filter_user'])) : '0';
$filter_template = isset($_GET['filter_template']) ? sanitize_text_field(wp_unslash($_GET['filter_template'])) : '';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

$filter_date_from = $filter_date_from_shamsi;
$filter_date_to = $filter_date_to_shamsi;
if (function_exists('sc_shamsi_to_gregorian_date')) {
    if (!empty($filter_date_from_shamsi)) {
        $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
    }
    if (!empty($filter_date_to_shamsi)) {
        $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
    }
}

$today_gregorian = current_time('Y-m-d');
$one_month_before_gregorian = gmdate('Y-m-d', strtotime('-1 month', strtotime($today_gregorian)));
$default_from_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($one_month_before_gregorian) : $one_month_before_gregorian;
$default_to_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_gregorian) : $today_gregorian;
$display_date_from_shamsi = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $default_from_display;
$display_date_to_shamsi = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $default_to_display;

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$where = '1=1';
$where_values = [];

if (!empty($filter_user) && preg_match('/^m_(\d+)$/', $filter_user, $um)) {
    $where .= ' AND c.member_id = %d';
    $where_values[] = absint($um[1]);
}
if (!empty($filter_template)) {
    $where .= ' AND c.template_key = %s';
    $where_values[] = $filter_template;
}
if (!empty($filter_date_from)) {
    $where .= ' AND DATE(c.created_at) >= %s';
    $where_values[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $where .= ' AND DATE(c.created_at) <= %s';
    $where_values[] = $filter_date_to;
}
if (!empty($search)) {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where .= ' AND (c.title LIKE %s OR c.message_text LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s)';
    $where_values[] = $like;
    $where_values[] = $like;
    $where_values[] = $like;
    $where_values[] = $like;
}

$count_sql = "SELECT COUNT(*)
FROM {$cert_table} c
LEFT JOIN {$members_table} m ON m.id = c.member_id
WHERE {$where}";

if (!empty($where_values)) {
    $count_sql = $wpdb->prepare($count_sql, $where_values);
}
$total_items = (int) $wpdb->get_var($count_sql);
$total_pages = max(1, (int) ceil($total_items / $per_page));

$rows_sql = "SELECT c.*, m.first_name, m.last_name, m.national_id
FROM {$cert_table} c
LEFT JOIN {$members_table} m ON m.id = c.member_id
WHERE {$where}
ORDER BY c.created_at DESC
LIMIT %d OFFSET %d";
$rows_values = $where_values;
$rows_values[] = $per_page;
$rows_values[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare($rows_sql, $rows_values));

$members_for_filter = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM {$members_table} WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
$templates = sc_certificates_get_saved_templates();
$template_titles = [];
foreach ($templates as $template_key => $template_item) {
    $template_titles[$template_key] = isset($template_item['title']) ? (string) $template_item['title'] : $template_key;
}

$edit_id = (isset($_GET['action'], $_GET['certificate_id']) && $_GET['action'] === 'edit') ? absint($_GET['certificate_id']) : 0;
$edit_row = null;
if ($edit_id > 0) {
    $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cert_table} WHERE id = %d", $edit_id));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گواهینامه ها</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-issue')); ?>" class="page-title-action">صدور گواهینامه</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-templates')); ?>" class="page-title-action">تعریف قالب گواهینامه</a>
    <hr class="wp-header-end">
</div>

<div class="wrap">
    <?php if (!empty($notice)) : ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ($edit_row) : ?>
        <div class="sc-users-export-card sc-certificate-edit-card" style="margin-bottom:16px;">
            <h2>ویرایش گواهینامه #<?php echo (int) $edit_row->id; ?></h2>
            <form method="post" class="sc-certificate-edit-form">
                <?php wp_nonce_field('sc_update_certificate_' . (int) $edit_row->id); ?>
                <input type="hidden" name="certificate_id" value="<?php echo (int) $edit_row->id; ?>">
                <div class="sc-row">
                    <label>عنوان گواهینامه</label>
                    <input type="text" name="title" value="<?php echo esc_attr($edit_row->title); ?>">
                </div>
                <div class="sc-row">
                    <label>متن گواهینامه</label>
                    <textarea name="message_text" rows="4"><?php echo esc_textarea($edit_row->message_text); ?></textarea>
                </div>
                <p>
                    <button type="submit" name="sc_update_certificate" class="button button-primary">ذخیره تغییرات</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-list')); ?>" class="button">انصراف</a>
                </p>
            </form>
        </div>
    <?php endif; ?>

    <div class="filter_search_honors">
        <form method="get" action="" class="filter_honors_list">
            <input type="hidden" name="page" value="sc-certificates-list">
            <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
            <label for="filter_user" style="margin-left: 5px; width: 100px;">نام کاربر:</label>
            <div class="sc-searchable-dropdown">
                <input type="hidden" name="filter_user" id="filter_user" value="<?php echo esc_attr($filter_user); ?>">
                <div class="sc-dropdown-toggle" style="width: 100%;">
                    <span class="sc-dropdown-placeholder" <?php if (!empty($filter_user) && $filter_user !== '0') echo 'style="display:none"'; ?>>همه کاربران</span>
                    <span class="sc-dropdown-selected" <?php if (empty($filter_user) || $filter_user === '0') echo 'style="display:none"'; ?>>کاربر انتخاب شده</span>
                    <span class="sc-dropdown-arrow">▼</span>
                </div>
                <div class="sc-dropdown-menu">
                    <div class="sc-dropdown-search">
                        <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                    </div>
                    <div class="sc-dropdown-options">
                        <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه کاربران" onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                        <?php foreach ($members_for_filter as $member_option) : ?>
                            <div class="sc-dropdown-option sc-visible"
                                 data-value="m_<?php echo esc_attr($member_option->id); ?>"
                                 data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                 onclick="scSelectMemberFilter(this,'<?php echo esc_js('m_' . $member_option->id); ?>','<?php echo esc_js($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>')">
                                <?php echo esc_html($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <select name="filter_template" style="margin-left:5px;">
                <option value="">همه قالب‌ها</option>
                <?php foreach ($template_titles as $t_key => $t_title) : ?>
                    <option value="<?php echo esc_attr($t_key); ?>" <?php selected($filter_template, $t_key); ?>><?php echo esc_html($t_title); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="filter_date_from_shamsi" class="persian-date-input" value="<?php echo esc_attr($display_date_from_shamsi); ?>" placeholder="از تاریخ" readonly>
            <input type="text" name="filter_date_to_shamsi" class="persian-date-input" value="<?php echo esc_attr($display_date_to_shamsi); ?>" placeholder="تا تاریخ" readonly>
            <input type="submit" class="button" value="اعمال فیلتر">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-list')); ?>" class="button">پاک کردن فیلترها</a>
        </form>

        <div class="tablenav top" style="margin-bottom: 0;">
            <div class="alignleft actions">
                <form method="get" action="">
                    <input type="hidden" name="page" value="sc-certificates-list">
                    <input type="hidden" name="filter_user" value="<?php echo esc_attr($filter_user); ?>">
                    <input type="hidden" name="filter_template" value="<?php echo esc_attr($filter_template); ?>">
                    <input type="hidden" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>">
                    <input type="hidden" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان/متن..." style="width:220px;">
                    <input type="submit" class="button" value="جستجو">
                </form>
            </div>
        </div>
    </div>

    <form method="post">
        <?php wp_nonce_field('sc_bulk_delete_certificates_nonce'); ?>
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <button type="submit" name="sc_bulk_delete_certificates" class="button action" onclick="return confirm('گواهینامه‌های انتخاب‌شده حذف شوند؟');">حذف انتخاب‌شده‌ها</button>
            </div>
        </div>

        <div class="back_table_list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></td>
                        <th>شناسه</th>
                        <th>نام کاربر</th>
                        <th>عنوان</th>
                        <th>قالب</th>
                        <th>تاریخ صدور</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)) : ?>
                        <?php foreach ($rows as $row) : ?>
                            <?php
                            $member_name = trim((string) ($row->first_name . ' ' . $row->last_name));
                            if ($member_name === '') {
                                $member_name = 'کاربر حذف شده';
                            }
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=sc-certificates-list&action=delete&certificate_id=' . (int) $row->id),
                                'sc_delete_certificate_' . (int) $row->id
                            );
                            $edit_url = admin_url('admin.php?page=sc-certificates-list&action=edit&certificate_id=' . (int) $row->id);
                            ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="certificate_ids[]" value="<?php echo (int) $row->id; ?>"></th>
                                <td><?php echo (int) $row->id; ?></td>
                                <td>
                                    <?php echo esc_html($member_name); ?>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url($edit_url); ?>">ویرایش</a> | </span>
                                        <span class="delete"><a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('این گواهینامه حذف شود؟');">حذف</a></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html((string) $row->title); ?></td>
                                <td><?php echo esc_html($template_titles[$row->template_key] ?? $row->template_key); ?></td>
                                <td><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($row->created_at, 'Y/m/d H:i') : $row->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="6" style="text-align:center;padding:20px;">هیچ گواهینامه‌ای یافت نشد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc_paginate" style="margin-top:20px;">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                    'format' => '',
                    'prev_text' => '< قبلی ',
                    'next_text' => ' بعدی >',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'add_args' => [
                        'page' => 'sc-certificates-list',
                        'filter_user' => $filter_user,
                        'filter_template' => $filter_template,
                        'filter_date_from_shamsi' => $filter_date_from_shamsi,
                        'filter_date_to_shamsi' => $filter_date_to_shamsi,
                        's' => $search,
                    ],
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.sc-certificate-edit-card {
    width: 100%;
    box-sizing: border-box;
}
.sc-certificate-edit-form .sc-row {
    display: block;
    margin-bottom: 14px;
}
.sc-certificate-edit-form .sc-row label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}
.sc-certificate-edit-form .sc-row input[type="text"],
.sc-certificate-edit-form .sc-row textarea {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
</style>

<script>
jQuery(function($){
    $('#cb-select-all').on('change', function(){
        $('input[name="certificate_ids[]"]').prop('checked', this.checked);
    });
});
</script>
