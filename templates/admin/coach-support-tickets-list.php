<?php
if (!defined('ABSPATH')) exit;
$coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id(get_current_user_id()) : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}
$tickets = sc_support_get_tickets_for_coach($coach_id, ['per_page' => 100]);
$list_url = admin_url('admin.php?page=sc-coach-support-tickets');
?>
<div class="wrap sc-coach-panel-wrap sc-support-coach-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">تیکت‌های پشتیبانی</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-support-ticket-new')); ?>" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-new">ارسال تیکت جدید</a>
    </div>
    <div class="sc-coach-panel-card">
    <?php if (empty($tickets)) : ?>
        <p class="sc-coach-panel-empty-text" style="padding: 2rem;">هنوز تیکتی برای شما ارسال نشده است.</p>
    <?php else : ?>
        <div class="back_table_list">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>شناسه</th>
                    <th>موضوع</th>
                    <th>طرف مقابل</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                $members_table = $wpdb->prefix . 'sc_members';
                $coaches_table = $wpdb->prefix . 'sc_coaches';
                foreach ($tickets as $t) :
                    $other_name = '';
                    if (!empty($t->created_by_coach_id) && (int) $t->created_by_coach_id === (int) $coach_id) {
                        if ((int) $t->user_id > 0) {
                            $member = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $members_table WHERE user_id = %d",
                                $t->user_id
                            ));
                            $other_name = $member ? trim($member->first_name . ' ' . $member->last_name) : '';
                            if (empty($other_name)) {
                                $u = get_userdata($t->user_id);
                                $other_name = $u ? $u->display_name : 'کاربر #' . $t->user_id;
                            }
                            $other_name = 'به: ' . $other_name;
                        } elseif ($t->department === 'accountant' && !empty($t->coach_id)) {
                            $acc = get_userdata((int) $t->coach_id);
                            $other_name = 'به: حسابدار' . ($acc ? (' (' . esc_html($acc->display_name) . ')') : '');
                        } else {
                            $other_name = 'به: مدیر باشگاه';
                        }
                    } else {
                        if ($t->user_id) {
                            $member = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, last_name FROM $members_table WHERE user_id = %d",
                                $t->user_id
                            ));
                            if ($member) {
                                $other_name = trim($member->first_name . ' ' . $member->last_name);
                            }
                            if (empty($other_name)) {
                                $u = get_userdata($t->user_id);
                                $other_name = $u ? $u->display_name : 'کاربر #' . $t->user_id;
                            }
                            $other_name = 'از: ' . $other_name;
                        } else {
                            $other_name = '—';
                        }
                    }
                ?>
                <tr>
                    <td><?php echo (int) $t->id; ?></td>
                    <td><?php echo esc_html($t->subject); ?></td>
                    <td><?php echo esc_html($other_name); ?></td>
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
    </div>
</div>
