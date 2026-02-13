<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');

sc_check_and_create_tables();
global $wpdb;
$notifications_table = $wpdb->prefix . 'sc_notifications';
$recipients_table = $wpdb->prefix . 'sc_notification_recipients';

$message = '';
$message_type = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('delete_notification_' . $_GET['id']);
    $id = absint($_GET['id']);
    $wpdb->delete($recipients_table, ['notification_id' => $id], ['%d']);
    $wpdb->delete($notifications_table, ['id' => $id], ['%d']);
    wp_safe_redirect(admin_url('admin.php?page=sc-notifications&deleted=1'));
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
<div class="wrap">
    <h1>لیست اطلاعیه‌ها</h1>
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-notification')); ?>" class="button button-primary">افزودن اطلاعیه جدید</a></p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px">ردیف</th>
                <th>عنوان</th>
                <th style="width:120px">نوع ارسال</th>
                <th style="width:80px">تعداد مخاطب</th>
                <th style="width:80px">پیامک</th>
                <th style="width:120px">تاریخ</th>
                <th style="width:120px">عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($notifications)) : ?>
                <tr><td colspan="7">اطلاعیه‌ای یافت نشد.</td></tr>
            <?php else :
                $i = $offset + 1;
                foreach ($notifications as $n) :
                    $target_labels = ['all' => 'همه', 'specific' => 'اشخاص خاص', 'course' => 'دوره خاص'];
                    $target_label = $target_labels[$n->target_type] ?? $n->target_type;
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><strong><?php echo esc_html($n->title); ?></strong></td>
                    <td><?php echo esc_html($target_label); ?></td>
                    <td><?php echo esc_html($n->recipients_count); ?></td>
                    <td><?php echo $n->send_sms ? 'بله' : 'خیر'; ?></td>
                    <td><?php echo esc_html(sc_date_shamsi($n->created_at, 'Y/m/d H:i')); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-notification&edit=' . $n->id)); ?>">ویرایش</a>
                        |
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sc-notifications&action=delete&id=' . $n->id), 'delete_notification_' . $n->id)); ?>" class="submitdelete" onclick="return confirm('آیا از حذف اطمینان دارید؟');">حذف</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                ]); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
