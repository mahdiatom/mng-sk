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

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;
$total = $wpdb->get_var("SELECT COUNT(*) FROM $notifications_table");
$notifications = $wpdb->get_results($wpdb->prepare(
    "SELECT n.*, 
            (SELECT COUNT(*) FROM $recipients_table WHERE notification_id = n.id) as recipients_count
     FROM $notifications_table n
     ORDER BY n.created_at DESC
     LIMIT %d OFFSET %d",
    $per_page, $offset
));
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
                    $target_labels = ['all' => 'همه', 'specific' => 'اشخاص خاص', 'course' => 'دوره خاص'];
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
                <?php echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                ]); ?>
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
</style>
