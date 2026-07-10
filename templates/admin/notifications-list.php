<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('sc_manage_notifications') && !current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
$is_coach = !empty($GLOBALS['sc_notification_is_coach']);
$current_coach_id = $is_coach && function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
$list_page = $is_coach ? 'sc-coach-notifications-list' : 'sc-notifications';
$add_page = $is_coach ? 'sc-coach-add-notification' : 'sc-add-notification';
$list_url = admin_url('admin.php?page=' . $list_page);
$add_url = admin_url('admin.php?page=' . $add_page);

sc_check_and_create_tables();
global $wpdb;
$notifications_table = $wpdb->prefix . 'sc_notifications';
$recipients_table = $wpdb->prefix . 'sc_notification_recipients';

$message = '';
$message_type = '';

// حذف تکی
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('delete_notification_' . $_GET['id']);
    $id = absint($_GET['id']);
    if ($is_coach && $current_coach_id > 0) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT created_by_type, created_by_entity_id FROM $notifications_table WHERE id = %d",
            $id
        ));
        if (!$row || $row->created_by_type !== 'coach' || (int)$row->created_by_entity_id !== $current_coach_id) {
            wp_die('شما فقط می‌توانید اطلاعیه‌های خود را حذف کنید.');
        }
    }
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, title, target_type FROM $notifications_table WHERE id = %d", $id), ARRAY_A);
    $wpdb->delete($recipients_table, ['notification_id' => $id], ['%d']);
    $wpdb->delete($notifications_table, ['id' => $id], ['%d']);
    if (function_exists('sc_log_activity') && $row) {
        sc_log_activity('deleted', 'notification', (int) $id, 'اطلاعیه «' . ($row['title'] ?? '') . '» حذف شد', $row, null);
    }
    wp_safe_redirect(add_query_arg('deleted', 1, $list_url));
    exit;
}

// حذف دسته‌جمعی
if (isset($_POST['bulk_delete']) && isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    check_admin_referer('bulk_delete_notifications');
    $ids = isset($_POST['notification_ids']) && is_array($_POST['notification_ids']) ? array_map('absint', $_POST['notification_ids']) : [];
    $ids = array_filter($ids);
    $deleted = 0;
    foreach ($ids as $id) {
        if (!$id) continue;
        if ($is_coach && $current_coach_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, created_by_type, created_by_entity_id FROM $notifications_table WHERE id = %d",
                $id
            ));
            if (!$row || $row->created_by_type !== 'coach' || (int)$row->created_by_entity_id !== $current_coach_id) {
                continue;
            }
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, title, target_type FROM $notifications_table WHERE id = %d", $id), ARRAY_A);
        $wpdb->delete($recipients_table, ['notification_id' => $id], ['%d']);
        $wpdb->delete($notifications_table, ['id' => $id], ['%d']);
        if (function_exists('sc_log_activity') && $row) {
            sc_log_activity('deleted', 'notification', (int) $id, 'اطلاعیه «' . ($row['title'] ?? '') . '» حذف شد', $row, null);
        }
        $deleted++;
    }
    if ($deleted > 0) {
        $redirect = add_query_arg(['page' => $list_page, 'bulk_deleted' => $deleted], admin_url('admin.php'));
        if (!empty($_GET['paged'])) $redirect = add_query_arg('paged', max(1, absint($_GET['paged'])), $redirect);
        if (isset($_GET['filter_creator']) && $_GET['filter_creator'] !== '') $redirect = add_query_arg('filter_creator', sanitize_text_field($_GET['filter_creator']), $redirect);
        if (isset($_GET['filter_target']) && $_GET['filter_target'] !== '') $redirect = add_query_arg('filter_target', sanitize_text_field($_GET['filter_target']), $redirect);
        if (isset($_GET['filter_sms']) && $_GET['filter_sms'] !== '') $redirect = add_query_arg('filter_sms', sanitize_text_field($_GET['filter_sms']), $redirect);
        if (!empty($_GET['filter_date_from_shamsi'])) $redirect = add_query_arg('filter_date_from_shamsi', sanitize_text_field($_GET['filter_date_from_shamsi']), $redirect);
        if (!empty($_GET['filter_date_to_shamsi'])) $redirect = add_query_arg('filter_date_to_shamsi', sanitize_text_field($_GET['filter_date_to_shamsi']), $redirect);
        if (!empty($_GET['s'])) $redirect = add_query_arg('s', sanitize_text_field($_GET['s']), $redirect);
        wp_safe_redirect($redirect);
        exit;
    }
}

if (isset($_GET['deleted'])) {
    $message = 'اطلاعیه با موفقیت حذف شد.';
    $message_type = 'success';
}
if (isset($_GET['bulk_deleted'])) {
    $n = absint($_GET['bulk_deleted']);
    $message = $n > 0 ? sprintf('%d اطلاعیه حذف شد.', $n) : '';
    $message_type = 'success';
}
if (isset($_GET['saved']) && isset($_GET['msg'])) {
    $message = sanitize_text_field(wp_unslash($_GET['msg']));
    $message_type = 'success';
}

// دریافت فیلترها و جستجو
$filter_creator_type = isset($_GET['filter_creator']) ? sanitize_text_field($_GET['filter_creator']) : 'all';
$filter_target_type = isset($_GET['filter_target']) ? sanitize_text_field($_GET['filter_target']) : '';
$filter_sms = isset($_GET['filter_sms']) ? sanitize_text_field($_GET['filter_sms']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// تاریخ شمسی — فقط از GET (بدون اعمال پیش‌فرض در فیلتر)
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
$filter_date_from = '';
$filter_date_to   = '';
if (!empty($filter_date_from_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if (!empty($filter_date_to_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}
$display_date_from_shamsi = $filter_date_from_shamsi;
$display_date_to_shamsi   = $filter_date_to_shamsi;

$where = ['1=1'];
$where_values = [];

// مربی فقط اطلاعیه‌های خودش را می‌بیند
if ($is_coach && $current_coach_id > 0) {
    $where[] = "n.created_by_type = 'coach' AND n.created_by_entity_id = %d";
    $where_values[] = $current_coach_id;
}
// مدیر: اطلاعیه‌های سیستمی در این لیست نمایش داده نشوند
if (!$is_coach) {
    $where[] = "n.notification_type <> %s";
    $where_values[] = 'system';
}

// فیلتر ثبت‌کننده (فقط برای مدیر)
if (!$is_coach && $filter_creator_type === 'admin') {
    $where[] = "n.created_by_type = 'admin'";
} elseif (!$is_coach && $filter_creator_type === 'coach') {
    $where[] = "n.created_by_type = 'coach'";
}

// فیلتر نوع ارسال
if ($filter_target_type !== '' && in_array($filter_target_type, ['all', 'specific', 'course', 'phone', 'debtors', 'event', 'wallet_negative', 'team'])) {
    $where[] = "n.target_type = %s";
    $where_values[] = $filter_target_type;
}

// فیلتر پیامک
if ($filter_sms === '1') {
    $where[] = "n.send_sms = 1";
} elseif ($filter_sms === '0') {
    $where[] = "n.send_sms = 0";
}

// فیلتر بازه تاریخ
if (!empty($filter_date_from)) {
    $where[] = "DATE(n.created_at) >= %s";
    $where_values[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $where[] = "DATE(n.created_at) <= %s";
    $where_values[] = $filter_date_to;
}

// جستجو در عنوان و متن
if (!empty($search)) {
    $where[] = "(n.title LIKE %s OR n.content LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = $search_like;
    $where_values[] = $search_like;
}

$where_sql = implode(' AND ', $where);

$per_page = 10;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$count_query = "SELECT COUNT(*) FROM $notifications_table n WHERE $where_sql";
if (!empty($where_values)) {
    $count_query = $wpdb->prepare($count_query, $where_values);
}
$total = $wpdb->get_var($count_query);

$query = "SELECT n.*, 
        (SELECT COUNT(*) FROM $recipients_table WHERE notification_id = n.id) as recipients_count
 FROM $notifications_table n
 WHERE $where_sql
 ORDER BY n.created_at DESC
 LIMIT %d OFFSET %d";
$query_values = array_merge($where_values, [$per_page, $offset]);
$query = $wpdb->prepare($query, $query_values);
$notifications = $wpdb->get_results($query);

// برای نوع phone تعداد مخاطبین از target_config.phone_numbers خوانده می‌شود
foreach ($notifications as $n) {
    if (isset($n->target_type) && $n->target_type === 'phone' && !empty($n->target_config)) {
        $cfg = json_decode($n->target_config, true);
        $phones = isset($cfg['phone_numbers']) ? (array)$cfg['phone_numbers'] : [];
        $n->recipients_count = count($phones);
    }
}

$total_pages = max(1, (int) ceil($total / $per_page));

$active_filters_count = 0;
if ($search !== '') {
    $active_filters_count++;
}
if (!$is_coach && $filter_creator_type !== 'all') {
    $active_filters_count++;
}
if ($filter_target_type !== '') {
    $active_filters_count++;
}
if ($filter_sms !== 'all') {
    $active_filters_count++;
}
if ($filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$target_labels_map = [
    'all' => 'همه اعضا',
    'specific' => 'اشخاص خاص',
    'course' => 'دوره خاص',
    'debtors' => 'بدهکاران',
    'event' => 'رویداد',
    'wallet_negative' => 'موجودی منفی',
    'phone' => 'شماره خاص',
    'team' => 'تیم',
    'free_users' => 'کاربران آزاد',
    'identity_verified' => 'احراز شده',
    'identity_unverified' => 'احراز نشده',
    'registration_fee_unpaid' => 'بدون پرداخت عضویت',
    'level' => 'سطح',
    'team_level' => 'تیم + سطح',
];
?>

<?php if ($message) : ?>
    <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
<?php endif; ?>

<div class="wrap sc-notif-list-wrap<?php echo $is_coach ? ' sc-coach-panel-wrap' : ''; ?>">
    <div class="sc-notif-list-header">
        <div class="sc-notif-list-header-text">
            <h1 class="sc-notif-list-title">لیست اطلاعیه‌ها</h1>
            <p class="sc-notif-list-desc"><?php echo $is_coach ? 'اطلاعیه‌های ارسال‌شده توسط شما' : 'مدیریت اطلاعیه‌ها و پیامک‌های ارسال‌شده'; ?></p>
        </div>
        <div class="sc-notif-list-header-actions">
            <a href="<?php echo esc_url($add_url); ?>" class="sc-notif-list-add-btn">افزودن اطلاعیه جدید</a>
        </div>
    </div>

    <div class="sc-notif-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-notif-list-filters-toolbar">
            <button type="button"
                    class="sc-notif-list-filters-toggle"
                    id="sc-notif-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-notif-filters-panel">
                <span class="sc-notif-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-notif-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-notif-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-notif-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($list_url); ?>" class="sc-notif-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-notif-list-filters-panel" id="sc-notif-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="<?php echo esc_attr($list_page); ?>">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">جستجو</label>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن..." class="sc-filter-control">
                </div>

                <?php if (!$is_coach) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_creator">ثبت‌کننده</label>
                    <select name="filter_creator" id="filter_creator" class="sc-filter-control">
                        <option value="all" <?php selected($filter_creator_type, 'all'); ?>>همه</option>
                        <option value="admin" <?php selected($filter_creator_type, 'admin'); ?>>مدیر</option>
                        <option value="coach" <?php selected($filter_creator_type, 'coach'); ?>>مربی</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_target">نوع ارسال</label>
                    <select name="filter_target" id="filter_target" class="sc-filter-control">
                        <option value="" <?php selected($filter_target_type, ''); ?>>همه</option>
                        <option value="all" <?php selected($filter_target_type, 'all'); ?>>همه اعضا</option>
                        <option value="specific" <?php selected($filter_target_type, 'specific'); ?>>اشخاص خاص</option>
                        <option value="course" <?php selected($filter_target_type, 'course'); ?>>دوره خاص</option>
                        <option value="team" <?php selected($filter_target_type, 'team'); ?>>تیم</option>
                        <?php if (!$is_coach) : ?>
                        <option value="debtors" <?php selected($filter_target_type, 'debtors'); ?>>بدهکاران</option>
                        <option value="event" <?php selected($filter_target_type, 'event'); ?>>رویداد</option>
                        <option value="wallet_negative" <?php selected($filter_target_type, 'wallet_negative'); ?>>موجودی منفی</option>
                        <option value="phone" <?php selected($filter_target_type, 'phone'); ?>>شماره خاص</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_sms">پیامک</label>
                    <select name="filter_sms" id="filter_sms" class="sc-filter-control">
                        <option value="all" <?php selected($filter_sms, 'all'); ?>>همه</option>
                        <option value="1" <?php selected($filter_sms, '1'); ?>>بله</option>
                        <option value="0" <?php selected($filter_sms, '0'); ?>>خیر</option>
                    </select>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <div class="sc-date-range sc-notif-date-range">
                        <input type="text" name="filter_date_from_shamsi" class="persian-date-input sc-no-default-date sc-filter-control" value="<?php echo esc_attr($display_date_from_shamsi); ?>" placeholder="از تاریخ" readonly>
                        <input type="text" name="filter_date_to_shamsi" class="persian-date-input sc-no-default-date sc-filter-control" value="<?php echo esc_attr($display_date_to_shamsi); ?>" placeholder="تا تاریخ" readonly>
                    </div>
                </div>
            </div>

            <div class="sc-notif-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($list_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-notif-list-table-card">
        <div class="sc-notif-list-summary"><span><?php echo (int) $total; ?> اطلاعیه</span></div>
        <form method="post" id="sc-notifications-bulk-form">
            <?php wp_nonce_field('bulk_delete_notifications'); ?>
            <div class="tablenav top sc-notif-bulk-nav">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value="">عملیات دسته‌جمعی...</option>
                        <option value="delete">حذف</option>
                    </select>
                    <input type="submit" name="bulk_delete" id="doaction" class="button action" value="اجرا">
                </div>
            </div>

            <div class="sc-notif-table-scroll">
                <table class="wp-list-table widefat striped sc-notif-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th class="manage-column">ردیف</th>
                            <th class="manage-column">عنوان</th>
                            <th class="manage-column">ثبت‌کننده</th>
                            <th class="manage-column">نوع ارسال</th>
                            <th class="manage-column">تعداد مخاطب</th>
                            <th class="manage-column">پیامک</th>
                            <th class="manage-column">تاریخ</th>
                            <th class="manage-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)) : ?>
                            <tr><td colspan="9" class="sc-notif-empty">اطلاعیه‌ای یافت نشد.</td></tr>
                        <?php else :
                            $i = $offset + 1;
                            foreach ($notifications as $n) :
                                $target_label = $target_labels_map[$n->target_type] ?? $n->target_type;
                                $creator_label = function_exists('sc_notification_creator_label') ? sc_notification_creator_label($n) : 'مدیر';
                                $can_edit_delete = !$is_coach || ($current_coach_id > 0 && isset($n->created_by_type) && $n->created_by_type === 'coach' && (int) $n->created_by_entity_id === $current_coach_id);
                                $edit_url = admin_url('admin.php?page=' . $add_page . '&edit=' . (int) $n->id);
                                $delete_url = wp_nonce_url(admin_url('admin.php?page=' . $list_page . '&action=delete&id=' . (int) $n->id), 'delete_notification_' . (int) $n->id);
                                $creator_badge = (isset($n->created_by_type) && $n->created_by_type === 'coach') ? 'sc-badge--purple' : 'sc-badge--soft';
                        ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <?php if ($can_edit_delete) : ?>
                                        <input type="checkbox" name="notification_ids[]" value="<?php echo esc_attr($n->id); ?>" class="cb-notification">
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </th>
                                <td data-label="ردیف"><?php echo (int) $i++; ?></td>
                                <td data-label="عنوان"><strong class="sc-notif-title"><?php echo esc_html($n->title); ?></strong></td>
                                <td data-label="ثبت‌کننده"><span class="sc-badge <?php echo esc_attr($creator_badge); ?>"><?php echo esc_html($creator_label); ?></span></td>
                                <td data-label="نوع ارسال"><span class="sc-badge sc-badge--soft"><?php echo esc_html($target_label); ?></span></td>
                                <td data-label="تعداد مخاطب"><?php echo (int) $n->recipients_count; ?></td>
                                <td data-label="پیامک">
                                    <?php if (!empty($n->send_sms)) : ?>
                                        <span class="sc-badge sc-badge--success">بله</span>
                                    <?php else : ?>
                                        <span class="sc-badge sc-badge--muted">خیر</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="تاریخ"><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($n->created_at, 'Y/m/d H:i') : $n->created_at); ?></td>
                                <td data-label="عملیات" class="sc-notif-actions">
                                    <?php if ($can_edit_delete) : ?>
                                        <a href="<?php echo esc_url($edit_url); ?>">ویرایش</a>
                                        <a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا از حذف اطمینان دارید؟' });">حذف</a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = ['page' => $list_page];
                        if ($filter_creator_type !== 'all') {
                            $pagination_args['filter_creator'] = $filter_creator_type;
                        }
                        if ($filter_target_type !== '') {
                            $pagination_args['filter_target'] = $filter_target_type;
                        }
                        if ($filter_sms !== 'all') {
                            $pagination_args['filter_sms'] = $filter_sms;
                        }
                        if ($filter_date_from_shamsi !== '') {
                            $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                        }
                        if ($filter_date_to_shamsi !== '') {
                            $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                        }
                        if ($search !== '') {
                            $pagination_args['s'] = $search;
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

<script>
jQuery(function($) {
    var $toggle = $('#sc-notif-filters-toggle');
    var $panel = $('#sc-notif-filters-panel');
    var $card = $toggle.closest('.sc-notif-list-filters-card');
    var $label = $toggle.find('.sc-notif-list-filters-toggle-label');
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

    $('#cb-select-all').on('change', function() {
        $('.cb-notification').prop('checked', this.checked);
    });
    $('#sc-notifications-bulk-form').on('submit', function() {
        if ($('#bulk-action-selector').val() === 'delete' && $('.cb-notification:checked').length === 0) {
            alert('لطفاً حداقل یک اطلاعیه را انتخاب کنید.');
            return false;
        }
    });
});
</script>
