<?php
if (!defined('ABSPATH')) {
    exit;
}
sc_check_and_create_tables();

global $wpdb;
$table = $wpdb->prefix . 'sc_surveys';

$notice = '';
$notice_type = 'success';

$filter_search = isset($_GET['filter_search']) ? sanitize_text_field(wp_unslash($_GET['filter_search'])) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';
$filter_public = isset($_GET['filter_public']) ? sanitize_text_field(wp_unslash($_GET['filter_public'])) : 'all';

$list_redirect_args = ['page' => 'sc-surveys'];
if ($filter_search !== '') {
    $list_redirect_args['filter_search'] = $filter_search;
}
if ($filter_status !== 'all') {
    $list_redirect_args['filter_status'] = $filter_status;
}
if ($filter_public !== 'all') {
    $list_redirect_args['filter_public'] = $filter_public;
}

if (isset($_GET['action'], $_GET['survey_id']) && $_GET['action'] === 'delete') {
    check_admin_referer('delete_survey_' . absint($_GET['survey_id']));
    $res = sc_survey_delete(absint($_GET['survey_id']));
    $redirect = add_query_arg(array_merge($list_redirect_args, [
        'sc_list_notice' => !empty($res['success']) ? 'deleted' : 'error',
    ]), admin_url('admin.php'));
    wp_safe_redirect($redirect);
    exit;
}

if (isset($_POST['sc_bulk_surveys_submit'], $_POST['bulk_action']) && check_admin_referer('sc_bulk_surveys', 'sc_bulk_surveys_nonce')) {
    $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action']));
    $survey_ids = isset($_POST['survey_ids']) ? array_map('absint', (array) $_POST['survey_ids']) : [];
    $survey_ids = array_values(array_filter($survey_ids));

    $post_filter_search = isset($_POST['filter_search']) ? sanitize_text_field(wp_unslash($_POST['filter_search'])) : '';
    $post_filter_status = isset($_POST['filter_status']) ? sanitize_text_field(wp_unslash($_POST['filter_status'])) : 'all';
    $post_filter_public = isset($_POST['filter_public']) ? sanitize_text_field(wp_unslash($_POST['filter_public'])) : 'all';
    $post_redirect_args = ['page' => 'sc-surveys'];
    if ($post_filter_search !== '') {
        $post_redirect_args['filter_search'] = $post_filter_search;
    }
    if ($post_filter_status !== 'all') {
        $post_redirect_args['filter_status'] = $post_filter_status;
    }
    if ($post_filter_public !== 'all') {
        $post_redirect_args['filter_public'] = $post_filter_public;
    }

    if ($bulk_action !== '' && !empty($survey_ids)) {
        $result = sc_survey_bulk_action_surveys($survey_ids, $bulk_action);
        $redirect = add_query_arg(array_merge($post_redirect_args, [
            'sc_list_notice' => !empty($result['success']) ? $bulk_action : 'error',
            'sc_list_count' => isset($result['count']) ? (int) $result['count'] : 0,
        ]), admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    $notice = empty($survey_ids) ? 'حداقل یک نظرسنجی را انتخاب کنید.' : 'عملیات دسته‌جمعی نامعتبر است.';
    $notice_type = 'error';
}

if (isset($_GET['sc_list_notice'])) {
    $code = sanitize_key(wp_unslash($_GET['sc_list_notice']));
    $count = isset($_GET['sc_list_count']) ? absint($_GET['sc_list_count']) : 0;
    if ($code === 'deleted') {
        $notice = 'نظرسنجی حذف شد.';
        $notice_type = 'success';
    } elseif ($code === 'delete') {
        $notice = $count > 0 ? sprintf('%d نظرسنجی حذف شد.', $count) : 'نظرسنجی‌ای حذف نشد.';
        $notice_type = $count > 0 ? 'success' : 'warning';
    } elseif ($code === 'activate') {
        $notice = $count > 0 ? sprintf('%d نظرسنجی فعال شد.', $count) : 'نظرسنجی‌ای فعال نشد.';
        $notice_type = $count > 0 ? 'success' : 'warning';
    } elseif ($code === 'deactivate') {
        $notice = $count > 0 ? sprintf('%d نظرسنجی غیرفعال شد.', $count) : 'نظرسنجی‌ای غیرفعال نشد.';
        $notice_type = $count > 0 ? 'success' : 'warning';
    } elseif ($code === 'error') {
        $notice = 'خطا در انجام عملیات.';
        $notice_type = 'error';
    }
}

$where = ['1=1'];
$params = [];
if ($filter_search !== '') {
    $where[] = 'title LIKE %s';
    $params[] = '%' . $wpdb->esc_like($filter_search) . '%';
}
if ($filter_status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where[] = 'is_active = 0';
}
if ($filter_public === 'yes') {
    $where[] = 'is_public = 1';
} elseif ($filter_public === 'no') {
    $where[] = 'is_public = 0';
}

$sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY id DESC";
$surveys = empty($params) ? $wpdb->get_results($sql) : $wpdb->get_results($wpdb->prepare($sql, ...$params));

$total = count($surveys);
$active_count = 0;
$public_count = 0;
foreach ($surveys as $s) {
    if ((int) $s->is_active) {
        $active_count++;
    }
    if ((int) $s->is_public) {
        $public_count++;
    }
}
?>
<div class="wrap sc-users-export-wrap sc-survey-list-wrap">
    <h1 class="wp-heading-inline">لیست نظرسنجی‌ها</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-survey')); ?>" class="page-title-action">افزودن نظرسنجی</a>
    <hr class="wp-header-end">
    <p class="description">مدیریت نظرسنجی‌ها، مشاهده داده‌ها و آمار.</p>

    <?php if ($notice !== '') : ?>
        <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="get" action="" class="form_fillter_list_player sc-survey-filter-form">
        <input type="hidden" name="page" value="sc-surveys">
        <div class="sc-users-export-card">
            <h2>فیلتر لیست</h2>
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_search">جستجوی عنوان</label>
                    <input type="text" name="filter_search" id="filter_search" class="sc-filter-control"
                           value="<?php echo esc_attr($filter_search); ?>" placeholder="عنوان نظرسنجی...">
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                        <option value="active" <?php selected($filter_status, 'active'); ?>>فعال</option>
                        <option value="inactive" <?php selected($filter_status, 'inactive'); ?>>غیرفعال</option>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_public">دسترسی</label>
                    <select name="filter_public" id="filter_public" class="sc-filter-control">
                        <option value="all" <?php selected($filter_public, 'all'); ?>>همه</option>
                        <option value="yes" <?php selected($filter_public, 'yes'); ?>>عمومی</option>
                        <option value="no" <?php selected($filter_public, 'no'); ?>>کاربران باشگاه</option>
                    </select>
                </div>
            </div>
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-surveys')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </p>
        </div>
    </form>

    <div class="sc-dashboard-stats">
        <div class="sc-stat-box">
            <h3>نمایش</h3>
            <div><?php echo (int) $total; ?></div>
        </div>
        <div class="sc-stat-box">
            <h3>فعال</h3>
            <div style="color:#00a32a;font-weight:bold;"><?php echo (int) $active_count; ?></div>
        </div>
        <div class="sc-stat-box">
            <h3>لینک عمومی</h3>
            <div><?php echo (int) $public_count; ?></div>
        </div>
    </div>

    <form method="post" id="sc-surveys-list-form" class="sc-surveys-bulk-form">
        <?php wp_nonce_field('sc_bulk_surveys', 'sc_bulk_surveys_nonce'); ?>
        <input type="hidden" name="sc_bulk_surveys_submit" value="1">
        <?php if ($filter_search !== '') : ?>
            <input type="hidden" name="filter_search" value="<?php echo esc_attr($filter_search); ?>">
        <?php endif; ?>
        <?php if ($filter_status !== 'all') : ?>
            <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
        <?php endif; ?>
        <?php if ($filter_public !== 'all') : ?>
            <input type="hidden" name="filter_public" value="<?php echo esc_attr($filter_public); ?>">
        <?php endif; ?>

        <div class="sc-users-export-card sc-survey-table-card">
            <div class="tablenav top sc-survey-bulk-nav">
                <div class="alignleft actions bulkactions">
                    <label for="sc-survey-bulk-action" class="screen-reader-text">عملیات دسته‌جمعی</label>
                    <select name="bulk_action" id="sc-survey-bulk-action">
                        <option value="">عملیات دسته‌جمعی...</option>
                        <option value="activate">فعال‌سازی</option>
                        <option value="deactivate">غیرفعال‌سازی</option>
                        <option value="delete">حذف</option>
                    </select>
                    <input type="submit" class="button action sc-survey-bulk-submit" value="اجرا">
                </div>
                <h2 class="sc-survey-data-table-title">نظرسنجی‌های ثبت‌شده</h2>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="sc-survey-cb-select-all">
                        </td>
                        <th>عنوان</th>
                        <th>وضعیت</th>
                        <th>دسترسی</th>
                        <th>بازه زمانی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($surveys)) : ?>
                    <tr><td colspan="6">نظرسنجی‌ای با فیلتر فعلی یافت نشد.</td></tr>
                <?php else : foreach ($surveys as $s) :
                    $start_txt = '—';
                    $end_txt = '—';
                    if (!empty($s->start_at) && function_exists('sc_date_shamsi_date_only')) {
                        $start_txt = sc_date_shamsi_date_only(substr($s->start_at, 0, 10));
                    }
                    if (!empty($s->end_at) && function_exists('sc_date_shamsi_date_only')) {
                        $end_txt = sc_date_shamsi_date_only(substr($s->end_at, 0, 10));
                    }
                    $has_responses = sc_survey_has_responses($s->id);
                    $delete_url = wp_nonce_url(
                        admin_url('admin.php?page=sc-surveys&action=delete&survey_id=' . (int) $s->id),
                        'delete_survey_' . (int) $s->id
                    );
                    ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" class="sc-survey-row-cb" name="survey_ids[]" value="<?php echo (int) $s->id; ?>">
                        </th>
                        <td>
                            <strong><?php echo esc_html($s->title); ?></strong>
                            <?php if (!empty($s->description)) : ?>
                                <div class="description"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($s->description), 12)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sc-survey-badge <?php echo (int) $s->is_active ? 'sc-survey-badge-active' : 'sc-survey-badge-inactive'; ?>">
                                <?php echo (int) $s->is_active ? 'فعال' : 'غیرفعال'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int) $s->is_public) : ?>
                                <span class="sc-survey-badge sc-survey-badge-public">عمومی</span>
                            <?php else : ?>
                                <span class="description">کاربران باشگاه</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($start_txt . ' — ' . $end_txt); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-survey&survey_id=' . (int) $s->id)); ?>">ویرایش</a> |
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-survey-data&survey_id=' . (int) $s->id)); ?>">داده‌ها</a> |
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-survey-stats&survey_id=' . (int) $s->id)); ?>">آمار</a> |
                            <a href="<?php echo esc_url($delete_url); ?>" class="delete sc-survey-delete-link"
                               onclick="return confirm('<?php echo esc_js($has_responses ? 'این نظرسنجی پاسخ دارد. با حذف، همه پاسخ‌ها هم پاک می‌شوند. ادامه می‌دهید؟' : 'نظرسنجی حذف شود؟'); ?>');">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
