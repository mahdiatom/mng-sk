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
$incomes_table = $wpdb->prefix . 'sc_incomes';
$income_categories_table = $wpdb->prefix . 'sc_income_categories';

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['income_id']) && $active_tab === 'list') {
    check_admin_referer('delete_income_' . $_GET['income_id']);

    $income_id = absint($_GET['income_id']);
    if (function_exists('sc_secretary_can_access_income') && !sc_secretary_can_access_income($income_id)) {
        wp_die('دسترسی غیرمجاز.');
    }
    $deleted = $wpdb->delete(
        $incomes_table,
        ['id' => $income_id],
        ['%d']
    );

    if ($deleted) {
        echo '<div class="notice notice-success is-dismissible"><p>درآمد با موفقیت حذف شد.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>خطا در حذف درآمد.</p></div>';
    }
}

if ($active_tab === 'categories') {
    if (isset($_POST['add_category']) && isset($_POST['category_name'])) {
        check_admin_referer('sc_add_income_category');
        $category_name = sanitize_text_field($_POST['category_name']);
        $category_description = !empty($_POST['category_description']) ? sanitize_textarea_field($_POST['category_description']) : null;

        if (!empty($category_name)) {
            $inserted = $wpdb->insert(
                $income_categories_table,
                [
                    'name' => $category_name,
                    'description' => $category_description,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s']
            );

            if ($inserted) {
                echo '<div class="notice notice-success is-dismissible"><p>دسته‌بندی با موفقیت اضافه شد.</p></div>';
            }
        }
    }

    if (isset($_POST['update_category']) && isset($_POST['category_id'])) {
        check_admin_referer('sc_update_income_category');
        $category_id = absint($_POST['category_id']);
        $category_name = sanitize_text_field($_POST['category_name']);
        $category_description = !empty($_POST['category_description']) ? sanitize_textarea_field($_POST['category_description']) : null;

        if (!empty($category_name)) {
            $updated = $wpdb->update(
                $income_categories_table,
                [
                    'name' => $category_name,
                    'description' => $category_description,
                    'updated_at' => current_time('mysql'),
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

    if (isset($_GET['action']) && $_GET['action'] === 'delete_category' && isset($_GET['category_id'])) {
        check_admin_referer('delete_income_category_' . $_GET['category_id']);

        $category_id = absint($_GET['category_id']);

        $incomes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $incomes_table WHERE category_id = %d",
            $category_id
        ));

        if ($incomes_count > 0) {
            echo '<div class="notice notice-error is-dismissible"><p>این دسته‌بندی قابل حذف نیست چون ' . $incomes_count . ' درآمد با آن مرتبط است.</p></div>';
        } else {
            $deleted = $wpdb->delete(
                $income_categories_table,
                ['id' => $category_id],
                ['%d']
            );

            if ($deleted) {
                echo '<div class="notice notice-success is-dismissible"><p>دسته‌بندی با موفقیت حذف شد.</p></div>';
            }
        }
    }
}

$categories = $wpdb->get_results("SELECT id, name, description FROM $income_categories_table ORDER BY name ASC");

if ($active_tab === 'list') {
    $filter_category = isset($_GET['filter_category']) ? absint($_GET['filter_category']) : 0;

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

    if (empty($filter_date_from)) {
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
        $filter_date_from_shamsi_default = $today_jalali[0] . '/' .
            str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
            str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
    } else {
        $filter_date_from_shamsi_default = sc_date_shamsi_date_only($filter_date_from);
    }

    if (empty($filter_date_to)) {
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
        $filter_date_to_shamsi_default = $today_jalali[0] . '/' .
            str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
            str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
    } else {
        $filter_date_to_shamsi_default = sc_date_shamsi_date_only($filter_date_to);
    }

    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $where_conditions = ['1=1'];
    $where_values = [];

    if ($filter_category > 0) {
        $where_conditions[] = 'i.category_id = %d';
        $where_values[] = $filter_category;
    }

    if ($filter_date_from) {
        $where_conditions[] = 'i.income_date_gregorian >= %s';
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = 'i.income_date_gregorian <= %s';
        $where_values[] = $filter_date_to;
    }

    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_conditions[] = '(i.name LIKE %s OR i.description LIKE %s)';
        $where_values[] = $search_like;
        $where_values[] = $search_like;
    }

    if (function_exists('sc_secretary_merge_income_where')) {
        sc_secretary_merge_income_where($where_conditions, $where_values, 'i');
    }

    $where_clause = implode(' AND ', $where_conditions);

    $total_query = "SELECT COUNT(*) FROM $incomes_table i WHERE $where_clause";
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($total_query);
    }

    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $query_values = $where_values;
    $query = "SELECT SQL_CALC_FOUND_ROWS i.*,
                     ic.name as category_name
              FROM $incomes_table i
              LEFT JOIN $income_categories_table ic ON i.category_id = ic.id
              WHERE $where_clause
              ORDER BY i.income_date_gregorian DESC, i.created_at DESC
              LIMIT %d OFFSET %d";

    $query_values[] = $per_page;
    $query_values[] = $offset;

    if (!empty($query_values)) {
        $incomes = $wpdb->get_results($wpdb->prepare($query, $query_values));
    } else {
        $incomes = $wpdb->get_results($query);
    }

    $total_items = $wpdb->get_var('SELECT FOUND_ROWS()');
    $total_pages = ceil($total_items / $per_page);
}

if ($active_tab === 'categories') {
    $editing_category = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit_category' && isset($_GET['category_id'])) {
        $category_id = absint($_GET['category_id']);
        $editing_category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $income_categories_table WHERE id = %d",
            $category_id
        ));
    }
}

$active_filters_count = 0;
if ($active_tab === 'list') {
    if (!empty($filter_category)) {
        $active_filters_count++;
    }
    if (!empty($filter_date_from)) {
        $active_filters_count++;
    }
    if (!empty($filter_date_to)) {
        $active_filters_count++;
    }
    if (!empty($search)) {
        $active_filters_count++;
    }
}
$filters_open = $active_filters_count > 0;
$export_url = admin_url('admin.php?page=sc-incomes&sc_export=excel&export_type=incomes');
if ($active_tab === 'list') {
    $export_url = add_query_arg('filter_category', $filter_category, $export_url);
    if (!empty($filter_date_from)) {
        $export_url = add_query_arg('filter_date_from', $filter_date_from, $export_url);
    }
    if (!empty($filter_date_to)) {
        $export_url = add_query_arg('filter_date_to', $filter_date_to, $export_url);
    }
    if (!empty($search)) {
        $export_url = add_query_arg('s', $search, $export_url);
    }
    $export_url = wp_nonce_url($export_url, 'sc_export_excel');
}
?>
<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">لیست درآمدها</h1>
            <p class="sc-reports-list-desc">درآمدهای ثبت‌شده باشگاه را فیلتر، مشاهده و مدیریت کنید.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-income')); ?>" class="sc-reports-list-add-btn">ثبت درآمد جدید</a>
            <?php if ($active_tab === 'list') : ?>
                <a href="<?php echo esc_url($export_url); ?>" class="sc-reports-list-export-btn">خروجی Excel</a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="nav-tab-wrapper sc-reports-nav-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=list')); ?>" class="nav-tab <?php echo $active_tab === 'list' ? 'nav-tab-active' : ''; ?>">لیست درآمدها</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=categories')); ?>" class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">مدیریت دسته‌بندی‌ها</a>
    </nav>

    <?php if ($active_tab === 'list') : ?>
        <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
            <div class="sc-reports-list-filters-toolbar">
                <button type="button" class="sc-reports-list-filters-toggle" id="sc-incomes-filters-toggle" aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>" aria-controls="sc-incomes-filters-panel">
                    <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها"><?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?></span>
                    <?php if ($active_filters_count > 0) : ?><span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span><?php endif; ?>
                    <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
                </button>
                <?php if ($active_filters_count > 0) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=list')); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
                <?php endif; ?>
            </div>
            <form method="GET" action="" class="sc-reports-list-filters-panel" id="sc-incomes-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
                <input type="hidden" name="page" value="sc-incomes">
                <input type="hidden" name="tab" value="list">
                <div class="sc-filter-grid sc-expenses-filter-grid">
                    <div class="sc-filter-field">
                        <label class="sc-filter-label" for="filter_category">دسته‌بندی</label>
                        <select name="filter_category" id="filter_category" class="sc-filter-control">
                            <option value="0">همه دسته‌بندی‌ها</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->id); ?>" <?php selected($filter_category, $category->id); ?>><?php echo esc_html($category->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label">از تاریخ</label>
                        <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi_default); ?>" class="sc-filter-control persian-date-input" readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    </div>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label">تا تاریخ</label>
                        <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi_default); ?>" class="sc-filter-control persian-date-input" readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                    <div class="sc-filter-field">
                        <label class="sc-filter-label" for="s">جستجو</label>
                        <input type="text" name="s" id="s" class="sc-filter-control" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در نام درآمد و توضیحات...">
                    </div>
                </div>
                <div class="sc-reports-list-filters-actions">
                    <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                    <a href="<?php echo esc_url($export_url); ?>" class="button button_export">خروجی Excel</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=list')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                </div>
            </form>
        </div>

        <div class="sc-reports-list-table-card">
        <?php if (empty($incomes)) : ?>
            <div class="sc-reports-empty">هیچ درآمدی یافت نشد.</div>
        <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ردیف</th>
                            <th>نام درآمد</th>
                            <th>شعبه</th>
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
                        foreach ($incomes as $index => $income) :
                            $row_number = $start_number + $index + 1;
                            $initials = $income->name !== '' ? mb_substr($income->name, 0, 1) : 'د';
                        ?>
                            <tr>
                                <td><?php echo (int) $row_number; ?></td>
                                <td>
                                    <span class="sc-member-identity">
                                        <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                        <span class="sc-member-identity-text"><span class="sc-member-name"><?php echo esc_html($income->name); ?></span></span>
                                    </span>
                                </td>
                                <td><?php echo $income->chapter ? '<span class="sc-badge sc-badge--soft">' . esc_html($income->chapter) . '</span>' : '<span class="sc-badge sc-badge--muted">—</span>'; ?></td>
                                <td><?php echo $income->category_name ? '<span class="sc-badge sc-badge--soft">' . esc_html($income->category_name) . '</span>' : '<span class="sc-badge sc-badge--muted">—</span>'; ?></td>
                                <td><?php echo esc_html($income->income_date_shamsi); ?></td>
                                <td><span class="sc-reports-amount-credit"><?php echo esc_html(number_format($income->amount, 0, '.', ',')); ?> تومان</span></td>
                                <td><?php echo $income->description ? esc_html(wp_trim_words($income->description, 20)) : '—'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-income&income_id=' . $income->id)); ?>" class="sc-reports-action-btn">ویرایش</a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sc-incomes&tab=list&action=delete&income_id=' . $income->id), 'delete_income_' . $income->id)); ?>" class="sc-reports-action-btn" style="color:#dc2626;border-color:#fecaca;" onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید که می‌خواهید این درآمد را حذف کنید؟' });">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom sc_paginate">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg(['paged' => '%#%', 'tab' => 'list']),
                                'format' => '',
                                'prev_text' => '< قبلی ',
                                'next_text' => ' بعدی >',
                                'total' => $total_pages,
                                'current' => $current_page,
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
        <?php endif; ?>
        </div>
        <script type="text/javascript">
        jQuery(function ($) {
            var $toggle = $('#sc-incomes-filters-toggle');
            var $panel = $('#sc-incomes-filters-panel');
            var $card = $toggle.closest('.sc-reports-list-filters-card');
            var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
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
        });
        </script>

    <?php elseif ($active_tab === 'categories') : ?>
        <div class="sc-expenses-categories-wrap">
        <div class="admin_category">
            <div class="back_form_cat sc-expenses-category-form-panel sc-finance-panel postbox">
                <div class="postbox-header"><h2><?php echo $editing_category ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید'; ?></h2></div>
                <div class="inside">
                <?php if ($editing_category) : ?>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=categories')); ?>" class="button">افزودن دسته‌بندی جدید</a></p>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php if ($editing_category) : ?>
                        <?php wp_nonce_field('sc_update_income_category'); ?>
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($editing_category->id); ?>">
                        <input type="hidden" name="update_category" value="1">
                    <?php else : ?>
                        <?php wp_nonce_field('sc_add_income_category'); ?>
                        <input type="hidden" name="add_category" value="1">
                    <?php endif; ?>

                    <table class="form-table sc_form-table">
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
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=categories')); ?>" class="button">انصراف</a>
                        <?php endif; ?>
                    </p>
                </form>
                </div>
            </div>

            <div class="back_form_cat sc-expenses-category-list-panel sc-finance-panel postbox">
                <div class="postbox-header"><h2>لیست دسته‌بندی‌ها</h2></div>
                <div class="inside">
                <?php if (empty($categories)) : ?>
                    <p>هیچ دسته‌بندی‌ای ثبت نشده است.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>توضیحات</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                    <td><?php echo $category->description ? esc_html($category->description) : '-'; ?></td>
                                    <td style="padding:5px !important;">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-incomes&tab=categories&action=edit_category&category_id=' . $category->id)); ?>"
                                           class="button button-small">ویرایش</a>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sc-incomes&tab=categories&action=delete_category&category_id=' . $category->id), 'delete_income_category_' . $category->id)); ?>"
                                           class="button button-small btn_delete_action_admin"
                                           onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید؟' });">حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
