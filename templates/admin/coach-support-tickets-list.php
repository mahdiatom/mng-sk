<?php
if (!defined('ABSPATH')) exit;
$coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id(get_current_user_id()) : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}
$tickets = sc_support_get_tickets_for_coach($coach_id, ['per_page' => 50]);
$list_url = admin_url('admin.php?page=sc-coach-support-tickets');
?>
<div class="wrap">
    <h1>تیکت‌های پشتیبانی (ارسال‌شده به شما)</h1>
    <?php if (empty($tickets)) : ?>
        <p>هنوز تیکتی برای شما ارسال نشده است.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>شناسه</th>
                    <th>موضوع</th>
                    <th>ارسال‌کننده</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                $members_table = $wpdb->prefix . 'sc_members';
                foreach ($tickets as $t) :
                    $creator_name = '';
                    if ($t->user_id) {
                        $member = $wpdb->get_row($wpdb->prepare(
                            "SELECT first_name, last_name FROM $members_table WHERE user_id = %d",
                            $t->user_id
                        ));
                        if ($member) {
                            $creator_name = trim($member->first_name . ' ' . $member->last_name);
                        }
                        if (empty($creator_name)) {
                            $u = get_userdata($t->user_id);
                            $creator_name = $u ? $u->display_name : 'کاربر #' . $t->user_id;
                        }
                    }
                ?>
                <tr>
                    <td><?php echo (int) $t->id; ?></td>
                    <td><?php echo esc_html($t->subject); ?></td>
                    <td><?php echo esc_html($creator_name); ?></td>
                    <td><?php echo esc_html(sc_support_status_label($t->status)); ?></td>
                    <td><?php echo esc_html(sc_date_shamsi($t->updated_at, 'Y/m/d H:i')); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-support-ticket-view&id=' . $t->id)); ?>" class="button button-small">مشاهده و پاسخ</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
