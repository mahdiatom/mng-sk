<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
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
    $wpdb->delete($recipients_table, ['notification_id' => $id], ['%d']);
    $wpdb->delete($notifications_table, ['id' => $id], ['%d']);
    wp_safe_redirect(add_query_arg('deleted', 1, $list_url));
    exit;
}
if (isset($_GET['deleted'])) {
    $message = 'اطلاعیه با موفقیت حذف شد.';
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

// تاریخ شمسی (پیش‌فرض: امروز)
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
if (empty($filter_date_from_shamsi) || empty($filter_date_to_shamsi)) {
    if (function_exists('gregorian_to_jalali')) {
        $today = new DateTime(current_time('Y-m-d'));
        $jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $today_shamsi = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
        if (empty($filter_date_from_shamsi)) $filter_date_from_shamsi = $today_shamsi;
        if (empty($filter_date_to_shamsi)) $filter_date_to_shamsi = $today_shamsi;
    }
}
$filter_date_from = '';
$filter_date_to = '';
if (!empty($filter_date_from_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if (!empty($filter_date_to_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}

$where = ['1=1'];
$where_values = [];

// مربی فقط اطلاعیه‌های خودش را می‌بیند
if ($is_coach && $current_coach_id > 0) {
    $where[] = "n.created_by_type = 'coach' AND n.created_by_entity_id = %d";
    $where_values[] = $current_coach_id;
}

// فیلتر ثبت‌کننده (فقط برای مدیر)
if (!$is_coach && $filter_creator_type === 'admin') {
    $where[] = "n.created_by_type = 'admin'";
} elseif (!$is_coach && $filter_creator_type === 'coach') {
    $where[] = "n.created_by_type = 'coach'";
}

// فیلتر نوع ارسال
if ($filter_target_type !== '' && in_array($filter_target_type, ['all', 'specific', 'course', 'phone', 'debtors', 'event', 'wallet_negative'])) {
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

$per_page = 20;
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

$total_pages = ceil($total / $per_page);
?>
<div class="wrap sc-notifications-list-wrap<?php echo $is_coach ? ' sc-coach-panel-wrap' : ''; ?>">
    <?php if ($is_coach) : ?>
        <div class="sc-coach-panel-header">
            <div class="sc-coach-panel-title-row">
                <h1 class="sc-coach-panel-title">لیست اطلاعیه‌ها</h1>
                <a href="<?php echo esc_url($add_url); ?>" class="button button-primary">افزودن اطلاعیه جدید</a>
            </div>
            <p class="sc-coach-panel-desc">اطلاعیه‌های ارسال‌شده توسط شما. می‌توانید ویرایش یا حذف کنید.</p>
        </div>
    <?php else : ?>
        <h1 class="wp-heading-inline">لیست اطلاعیه‌ها</h1>
        <a href="<?php echo esc_url($add_url); ?>" class="page-title-action">افزودن اطلاعیه جدید</a>
        <hr class="wp-header-end">
    <?php endif; ?>
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <div class="sc-notifications-filters-wrap" style="margin: 15px 0;">
        <form method="get" action="" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px;">
            <input type="hidden" name="page" value="<?php echo esc_attr($list_page); ?>">
            <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
            <?php if (!$is_coach) : ?>
            <label>ثبت‌کننده:</label>
            <select name="filter_creator" style="min-width: 120px;">
                <option value="all" <?php selected($filter_creator_type, 'all'); ?>>همه</option>
                <option value="admin" <?php selected($filter_creator_type, 'admin'); ?>>مدیر</option>
                <option value="coach" <?php selected($filter_creator_type, 'coach'); ?>>مربی</option>
            </select>
            <?php endif; ?>
            <label>نوع ارسال:</label>
            <select name="filter_target" style="min-width: 120px;">
                <option value="" <?php selected($filter_target_type, ''); ?>>همه</option>
                <option value="all" <?php selected($filter_target_type, 'all'); ?>>همه اعضا</option>
                <option value="specific" <?php selected($filter_target_type, 'specific'); ?>>اشخاص خاص</option>
                <option value="course" <?php selected($filter_target_type, 'course'); ?>>دوره خاص</option>
                <?php if (!$is_coach) : ?>
                <option value="debtors" <?php selected($filter_target_type, 'debtors'); ?>>بدهکاران</option>
                <option value="event" <?php selected($filter_target_type, 'event'); ?>>رویداد</option>
                <option value="wallet_negative" <?php selected($filter_target_type, 'wallet_negative'); ?>>موجودی منفی</option>
                <option value="phone" <?php selected($filter_target_type, 'phone'); ?>>شماره خاص</option>
                <?php endif; ?>
            </select>
            <label>پیامک:</label>
            <select name="filter_sms" style="min-width: 90px;">
                <option value="all" <?php selected($filter_sms, 'all'); ?>>همه</option>
                <option value="1" <?php selected($filter_sms, '1'); ?>>بله</option>
                <option value="0" <?php selected($filter_sms, '0'); ?>>خیر</option>
            </select>
            <label>از تاریخ:</label>
            <input type="text" name="filter_date_from_shamsi" class="persian-date-input" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" placeholder="۱۴۰۳/۰۱/۰۱" style="width: 110px;" readonly>
            <label>تا تاریخ:</label>
            <input type="text" name="filter_date_to_shamsi" class="persian-date-input" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" placeholder="۱۴۰۳/۱۲/۲۹" style="width: 110px;" readonly>
            <input type="submit" class="button" value="اعمال فیلتر">
        </form>
        <form method="get" action="" style="display: flex; align-items: center; gap: 8px;">
            <input type="hidden" name="page" value="<?php echo esc_attr($list_page); ?>">
            <input type="hidden" name="filter_creator" value="<?php echo esc_attr($filter_creator_type); ?>">
            <input type="hidden" name="filter_target" value="<?php echo esc_attr($filter_target_type); ?>">
            <input type="hidden" name="filter_sms" value="<?php echo esc_attr($filter_sms); ?>">
            <input type="hidden" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>">
            <input type="hidden" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن..." style="width: 260px;">
            <input type="submit" class="button" value="جستجو">
        </form>
    </div>

    <div class="sc-notifications-list-card<?php echo $is_coach ? ' sc-coach-panel-card' : ''; ?>">
    <table class="wp-list-table widefat fixed striped sc-notifications-admin-table">
        <thead>
            <tr>
                <th style="width:50px">ردیف</th>
                <th>عنوان</th>
                <th style="width:120px">ثبت‌کننده</th>
                <th style="width:120px">نوع ارسال</th>
                <th style="width:80px">تعداد مخاطب</th>
                <th style="width:80px">پیامک</th>
                <th style="width:120px">تاریخ</th>
                <th style="width:120px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($notifications)) : ?>
                <tr><td colspan="8">اطلاعیه‌ای یافت نشد.</td></tr>
            <?php else :
                $i = $offset + 1;
                foreach ($notifications as $n) :
                    $target_labels = ['all' => 'همه', 'specific' => 'اشخاص خاص', 'course' => 'دوره خاص', 'debtors' => 'بدهکاران', 'event' => 'رویداد', 'wallet_negative' => 'موجودی منفی', 'phone' => 'شماره خاص'];
                    $target_label = $target_labels[$n->target_type] ?? $n->target_type;
                    $creator_label = function_exists('sc_notification_creator_label') ? sc_notification_creator_label($n) : 'مدیر';
                    $can_edit_delete = !$is_coach || ($current_coach_id > 0 && isset($n->created_by_type) && $n->created_by_type === 'coach' && (int)$n->created_by_entity_id === $current_coach_id);
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><strong><?php echo esc_html($n->title); ?></strong></td>
                    <td><?php echo esc_html($creator_label); ?></td>
                    <td><?php echo esc_html($target_label); ?></td>
                    <td><?php echo esc_html($n->recipients_count); ?></td>
                    <td><?php echo $n->send_sms ? 'بله' : 'خیر'; ?></td>
                    <td><?php echo esc_html(sc_date_shamsi($n->created_at, 'Y/m/d H:i')); ?></td>
                    <td>
                        <?php if ($can_edit_delete) : ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $add_page . '&edit=' . $n->id)); ?>">ویرایش</a>
                            |
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=' . $list_page . '&action=delete&id=' . $n->id), 'delete_notification_' . $n->id)); ?>" class="submitdelete" onclick="return confirm('آیا از حذف اطمینان دارید؟');">حذف</a>
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
        <div class="tablenav bottom sc-notifications-tablenav">
            <div class="tablenav-pages">
                <?php
                $pagination_args = ['page' => $list_page];
                if ($filter_creator_type !== 'all') $pagination_args['filter_creator'] = $filter_creator_type;
                if ($filter_target_type !== '') $pagination_args['filter_target'] = $filter_target_type;
                if ($filter_sms !== 'all') $pagination_args['filter_sms'] = $filter_sms;
                if (!empty($filter_date_from_shamsi)) $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                if (!empty($filter_date_to_shamsi)) $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                if (!empty($search)) $pagination_args['s'] = $search;
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'add_args' => $pagination_args
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.sc-notifications-list-wrap .page-title-action { margin-right: 10px; }
.sc-notifications-list-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 15px; overflow: hidden; }
.sc-notifications-admin-table { margin: 0 !important; border: none !important; }
.sc-notifications-admin-table thead th { padding: 12px 14px; font-weight: 600; }
.sc-notifications-admin-table tbody td { padding: 12px 14px; }
.sc-notifications-admin-table .submitdelete { color: #b32d2e; }
.sc-notifications-admin-table .submitdelete:hover { color: #d63638; }
.sc-notifications-tablenav { margin-top: 15px; }
.sc-notifications-filters-wrap form label { font-weight: 500; margin-left: 4px; }
.sc-notifications-filters-wrap .persian-date-input { cursor: pointer; }
</style>
