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

if (isset($_POST['certificate_ids'], $_POST['bulk_action']) && check_admin_referer('sc_bulk_certificates_nonce')) {
    $ids = isset($_POST['certificate_ids']) ? array_map('absint', (array) $_POST['certificate_ids']) : [];
    $ids = array_values(array_filter($ids));
    $bulk_action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';

    if (empty($ids)) {
        $notice = 'حداقل یک گواهینامه را انتخاب کنید.';
        $notice_type = 'error';
    } elseif ($bulk_action === 'delete') {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$cert_table} WHERE id IN ({$placeholders})", $ids));
        $deleted_count = is_numeric($deleted) ? (int) $deleted : 0;
        $notice = sprintf('%d گواهینامه حذف شد.', $deleted_count);
        $notice_type = 'success';
    } elseif ($bulk_action === 'physical_invoice' && function_exists('sc_create_physical_certificate_invoice')) {
        $templates_all = sc_certificates_get_saved_templates();
        $created = 0;
        $already_exists = 0;
        $price_not_set = 0;
        $invalid = 0;
        $failed = 0;

        foreach ($ids as $cid) {
            $certificate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cert_table} WHERE id = %d", $cid));
            if (!$certificate || (int) $certificate->member_id <= 0) {
                $invalid++;
                continue;
            }
            $template_item = isset($templates_all[$certificate->template_key]) ? $templates_all[$certificate->template_key] : [];
            $template = function_exists('sc_certificates_normalize_template')
                ? sc_certificates_normalize_template($template_item, (string) $certificate->template_key)
                : $template_item;
            $price = (float) ($template['physical_copy_price'] ?? 0);
            if ($price <= 0) {
                $price_not_set++;
                continue;
            }
            $result = sc_create_physical_certificate_invoice($certificate, (int) $certificate->member_id, $price);
            $code = isset($result['code']) ? (string) $result['code'] : '';
            if ($code === 'created') {
                $created++;
            } elseif ($code === 'exists') {
                $already_exists++;
            } elseif ($code === 'invalid') {
                $invalid++;
            } else {
                $failed++;
            }
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = sprintf('%d صورتحساب جدید ایجاد شد', $created);
        }
        if ($already_exists > 0) {
            $parts[] = sprintf('%d مورد قبلاً صورتحساب داشتند', $already_exists);
        }
        if ($price_not_set > 0) {
            $parts[] = sprintf('%d مورد بدون مبلغ نسخه فیزیکی در قالب', $price_not_set);
        }
        if ($invalid > 0) {
            $parts[] = sprintf('%d مورد نامعتبر یا بدون عضو', $invalid);
        }
        if ($failed > 0) {
            $parts[] = sprintf('%d مورد با خطا', $failed);
        }
        $notice = !empty($parts) ? implode('؛ ', $parts) : 'عملیاتی انجام نشد.';
        if ($created > 0 && $failed === 0) {
            $notice_type = ($already_exists > 0 || $price_not_set > 0 || $invalid > 0) ? 'warning' : 'success';
        } elseif ($created === 0 && $failed === 0 && ($already_exists > 0 || $price_not_set > 0 || $invalid > 0)) {
            $notice_type = 'warning';
        } else {
            $notice_type = 'error';
        }
    } elseif ($bulk_action === '') {
        $notice = 'لطفاً یک عملیات دسته‌جمعی انتخاب کنید.';
        $notice_type = 'error';
    } elseif ($bulk_action !== '') {
        $notice = 'عملیات دسته‌جمعی نامعتبر است.';
        $notice_type = 'error';
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

$phys_status = isset($_GET['sc_phys_status']) ? sanitize_text_field(wp_unslash($_GET['sc_phys_status'])) : '';
if ($phys_status === 'created') {
    $notice = 'صورتحساب نسخه فیزیکی با موفقیت ایجاد شد.';
    $notice_type = 'success';
} elseif ($phys_status === 'exists') {
    $notice = 'برای این گواهینامه قبلا صورتحساب نسخه فیزیکی ثبت شده است.';
    $notice_type = 'error';
} elseif ($phys_status === 'price_not_set') {
    $notice = 'مبلغ نسخه فیزیکی برای قالب این گواهینامه تنظیم نشده است.';
    $notice_type = 'error';
} elseif (in_array($phys_status, ['invalid', 'db_error', 'error'], true)) {
    $notice = 'ایجاد صورتحساب نسخه فیزیکی با خطا مواجه شد.';
    $notice_type = 'error';
}

if (isset($_POST['sc_update_certificate'])) {
    $certificate_id = isset($_POST['certificate_id']) ? absint($_POST['certificate_id']) : 0;
    check_admin_referer('sc_update_certificate_' . $certificate_id);

    if ($certificate_id > 0) {
        $updated = $wpdb->update(
            $cert_table,
            [
                'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
                'message_text' => wp_kses_post(wp_unslash($_POST['message_text'] ?? '')),
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
    $where .= ' AND (c.title LIKE %s OR c.message_text LIKE %s OR c.tracking_code LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s)';
    $where_values[] = $like;
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
                    <?php
                    wp_editor(
                        (string) $edit_row->message_text,
                        'sc_cert_admin_edit_message',
                        [
                            'textarea_name' => 'message_text',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                        ]
                    );
                    ?>
                </div>
                <p>
                    <button type="submit" name="sc_update_certificate" class="button button-primary">ذخیره تغییرات</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-list')); ?>" class="button">انصراف</a>
                </p>
            </form>
        </div>
    <?php endif; ?>

    <div class="filter_search_certificate">
        <!-- فیلترها (همان ساختار حضور و غیاب / لیست افتخارات) -->
        <form method="get" action="" class="form_fillter_attendance form_fillter_attendance_tab1 certificates-filter-form">
            <input type="hidden" name="page" value="sc-certificates-list">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_user" id="filter_user" value="<?php echo esc_attr($filter_user); ?>">
                        <?php
                        $selected_user_text = 'همه کاربران';
                        if (!empty($filter_user) && preg_match('/^m_(\d+)$/', $filter_user, $um)) {
                            $mid = absint($um[1]);
                            foreach ($members_for_filter as $mem) {
                                if ((int) $mem->id === $mid) {
                                    $selected_user_text = $mem->first_name . ' ' . $mem->last_name . ' - ' . ($mem->national_id ?: $mem->id);
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if (!empty($filter_user) && $filter_user !== '0') echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (empty($filter_user) || $filter_user === '0') echo 'style="display:none"'; ?>><?php echo esc_html($selected_user_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
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
                                foreach ($members_for_filter as $member_option) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                    $val = 'm_' . $member_option->id;
                                    $label = $member_option->first_name . ' ' . $member_option->last_name . ' - ' . ($member_option->national_id ?: $member_option->id);
                                    $search_txt = strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . ($member_option->national_id ?: ''));
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
                    <label class="sc-filter-label" for="filter_template">قالب گواهینامه</label>
                    <select name="filter_template" id="filter_template" class="sc-filter-control">
                        <option value="">همه قالب‌ها</option>
                        <?php foreach ($template_titles as $t_key => $t_title) : ?>
                            <option value="<?php echo esc_attr($t_key); ?>" <?php selected($filter_template, $t_key); ?>><?php echo esc_html($t_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="search_id">جستجو</label>
                    <input type="search" id="search_id" name="s" class="sc-filter-control" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان/متن/کد رهگیری...">
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ صدور (شمسی)</label>
                    <div class="sc-date-range">
                        <input type="text"
                               name="filter_date_from_shamsi"
                               id="certificates_filter_date_from_shamsi"
                               value="<?php echo esc_attr($display_date_from_shamsi); ?>"
                               class="persian-date-input sc-filter-control sc-no-default-date"
                               placeholder="از تاریخ"
                               readonly>
                        <input type="text"
                               name="filter_date_to_shamsi"
                               id="certificates_filter_date_to_shamsi"
                               value="<?php echo esc_attr($display_date_to_shamsi); ?>"
                               class="persian-date-input sc-filter-control sc-no-default-date"
                               placeholder="تا تاریخ"
                               readonly>
                    </div>
                </div>
            </div>

            <p class="submit">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-certificates-list')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </p>
        </form>
    </div>

    <form method="post" id="certificates-list-form" class="list_certificate">
        <?php wp_nonce_field('sc_bulk_certificates_nonce'); ?>
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector" class="screen-reader-text">عملیات دسته‌جمعی</label>
                <select name="bulk_action" id="bulk-action-selector">
                    <option value="">عملیات دسته‌جمعی...</option>
                    <option value="physical_invoice">ایجاد صورتحساب نسخه فیزیکی</option>
                    <option value="delete">حذف</option>
                </select>
                <input type="submit" name="bulk_apply" id="doaction" class="button action" value="اجرا">
            </div>
        </div>

        <div class="back_table_list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td style="width: 10px;" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></td>
                        <th style="width: 20px;">شناسه</th>
                        <th style="width: 140px;">نام کاربر</th>
                        <th style="width: 40px;">عنوان</th>
                        <th style="width: 100px;">کد رهگیری</th>
                        <th style="width: 80px;">قالب</th>
                        <th style="width: 80px;">تاریخ صدور</th>
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
                            $view_url = add_query_arg(
                                [
                                    'action' => 'sc_download_certificate',
                                    'certificate_id' => (int) $row->id,
                                    'sc_cert_admin' => '1',
                                    'nonce' => wp_create_nonce('sc_admin_download_certificate_' . (int) $row->id),
                                ],
                                admin_url('admin-post.php')
                            );
                            $physical_invoice_url = wp_nonce_url(
                                add_query_arg(
                                    [
                                        'action' => 'sc_admin_create_certificate_physical_invoice',
                                        'certificate_id' => (int) $row->id,
                                    ],
                                    admin_url('admin-post.php')
                                ),
                                'sc_admin_create_certificate_physical_invoice_' . (int) $row->id
                            );
                            ?>
                            <tr>
                                <th scope="row" class="check-column"><input type="checkbox" name="certificate_ids[]" value="<?php echo (int) $row->id; ?>"></th>
                                <td><?php echo (int) $row->id; ?></td>
                                <td>
                                    <?php echo esc_html($member_name); ?>
                                    <div class="row-actions">
                                        <span class="view"><a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer">مشاهده و دانلود</a> | </span>
                                        <span class="edit"><a href="<?php echo esc_url($physical_invoice_url); ?>">ایجاد صورتحساب نسخه فیزیکی</a> | </span>
                                        <span class="edit"><a href="<?php echo esc_url($edit_url); ?>">ویرایش</a> | </span>
                                        <span class="delete"><a href="<?php echo esc_url($delete_url); ?>" onclick="return scConfirmInline(event, { type: 'warning', message: 'این گواهینامه حذف شود؟' });">حذف</a></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html((string) $row->title); ?></td>
                                <td><?php echo esc_html((string) ($row->tracking_code ?: '-')); ?></td>
                                <td><?php echo esc_html($template_titles[$row->template_key] ?? $row->template_key); ?></td>
                                <td><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($row->created_at, 'Y/m/d H:i') : $row->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="7" style="text-align:center;padding:20px;">هیچ گواهینامه‌ای یافت نشد.</td></tr>
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
.sc-certificate-edit-form .sc-row textarea,
.sc-certificate-edit-form .sc-row .wp-editor-wrap {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
</style>

<script>
jQuery(function($){
    var $form = $('#certificates-list-form');
    $('#cb-select-all').on('change', function(){
        $('input[name="certificate_ids[]"]').prop('checked', this.checked);
    });
    $('input[name="certificate_ids[]"]').on('change', function(){
        var total = $('input[name="certificate_ids[]"]').length;
        var checked = $('input[name="certificate_ids[]"]:checked').length;
        $('#cb-select-all').prop('checked', total > 0 && total === checked);
    });

    $('#doaction').on('click', function(e){
        var action = $('#bulk-action-selector').val();
        var checked = $('input[name="certificate_ids[]"]:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('لطفاً حداقل یک گواهینامه را انتخاب کنید.');
            return false;
        }
        if (!action) {
            e.preventDefault();
            alert('لطفاً یک عملیات دسته‌جمعی انتخاب کنید.');
            return false;
        }
        if (action === 'delete') {
            e.preventDefault();
            if (typeof scConfirm === 'function') {
                scConfirm({ type: 'danger', message: 'آیا از حذف ' + checked + ' گواهینامه انتخاب‌شده اطمینان دارید؟' }).then(function(ok){
                    if (ok) {
                        $form.submit();
                    }
                });
            } else if (confirm('آیا از حذف گواهینامه‌های انتخاب‌شده اطمینان دارید؟')) {
                $form.submit();
            }
            return false;
        }
        if (action === 'physical_invoice') {
            e.preventDefault();
            var msg = 'برای ' + checked + ' گواهینامه انتخاب‌شده، در صورت امکان صورتحساب نسخه فیزیکی ایجاد شود؟ (موارد بدون مبلغ قالب یا دارای صورتحساب قبلی رد می‌شوند.)';
            if (typeof scConfirm === 'function') {
                scConfirm({ type: 'warning', message: msg }).then(function(ok){
                    if (ok) {
                        $form.submit();
                    }
                });
            } else if (confirm(msg)) {
                $form.submit();
            }
            return false;
        }
    });
});
</script>
