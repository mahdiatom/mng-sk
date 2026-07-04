<?php
if (!defined('ABSPATH')) exit;
$coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id(get_current_user_id()) : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}
$tickets = sc_support_get_tickets_for_coach($coach_id, ['per_page' => 100]);
$list_url = admin_url('admin.php?page=sc-coach-support-tickets');
$add_url = admin_url('admin.php?page=sc-coach-support-ticket-new');
$status_badge_map = [
    'pending_reply' => 'sc-badge--warning',
    'answered' => 'sc-badge--success',
    'closed' => 'sc-badge--muted',
];
?>
<div class="wrap sc-coach-panel-wrap sc-support-coach-wrap sc-ticket-list-wrap">
    <div class="sc-ticket-list-header">
        <div class="sc-ticket-list-header-text">
            <h1 class="sc-ticket-list-title">تیکت‌های پشتیبانی</h1>
            <p class="sc-ticket-list-desc">تیکت‌های مرتبط با شما و پاسخ به درخواست‌ها</p>
        </div>
        <div class="sc-ticket-list-header-actions">
            <a href="<?php echo esc_url($add_url); ?>" class="sc-ticket-list-add-btn">ارسال تیکت جدید</a>
        </div>
    </div>

    <div class="sc-ticket-list-table-card">
        <div class="sc-ticket-list-summary"><span><?php echo (int) count($tickets); ?> تیکت</span></div>
        <?php if (empty($tickets)) : ?>
            <p class="sc-ticket-empty">هنوز تیکتی برای شما ارسال نشده است.</p>
        <?php else : ?>
            <div class="sc-ticket-table-scroll">
                <table class="wp-list-table widefat striped sc-ticket-table">
                    <thead>
                        <tr>
                            <th class="manage-column">شناسه</th>
                            <th class="manage-column">موضوع</th>
                            <th class="manage-column">طرف مقابل</th>
                            <th class="manage-column">وضعیت</th>
                            <th class="manage-column">تاریخ</th>
                            <th class="manage-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $members_table = $wpdb->prefix . 'sc_members';
                        foreach ($tickets as $t) :
                            $other_name = '';
                            if (!empty($t->created_by_coach_id) && (int) $t->created_by_coach_id === (int) $coach_id) {
                                if ((int) $t->user_id > 0) {
                                    $member = $wpdb->get_row($wpdb->prepare(
                                        "SELECT first_name, last_name FROM $members_table WHERE user_id = %d",
                                        $t->user_id
                                    ));
                                    $other_name = $member ? trim($member->first_name . ' ' . $member->last_name) : '';
                                    if ($other_name === '') {
                                        $u = get_userdata($t->user_id);
                                        $other_name = $u ? $u->display_name : 'کاربر #' . $t->user_id;
                                    }
                                    $other_name = 'به: ' . $other_name;
                                } elseif ($t->department === 'accountant' && !empty($t->coach_id)) {
                                    $acc = get_userdata((int) $t->coach_id);
                                    $other_name = 'به: حسابدار' . ($acc ? (' (' . $acc->display_name . ')') : '');
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
                                    if ($other_name === '') {
                                        $u = get_userdata($t->user_id);
                                        $other_name = $u ? $u->display_name : 'کاربر #' . $t->user_id;
                                    }
                                    $other_name = 'از: ' . $other_name;
                                } else {
                                    $other_name = '—';
                                }
                            }
                            $status_key = (string) $t->status;
                            $status_badge = $status_badge_map[$status_key] ?? 'sc-badge--soft';
                            $view_url = admin_url('admin.php?page=sc-coach-support-ticket-view&id=' . (int) $t->id);
                            $other_label = preg_replace('/^(از|به):\s*/u', '', (string) $other_name);
                            $other_initials = '؟';
                            $other_parts = preg_split('/\s+/u', trim($other_label));
                            if (!empty($other_parts[0])) {
                                $other_initials = mb_substr($other_parts[0], 0, 1);
                                if (!empty($other_parts[1])) {
                                    $other_initials .= mb_substr($other_parts[1], 0, 1);
                                }
                            }
                            ?>
                            <tr>
                                <td data-label="شناسه"><span class="sc-ticket-id">#<?php echo (int) $t->id; ?></span></td>
                                <td data-label="موضوع"><a class="sc-ticket-subject-link" href="<?php echo esc_url($view_url); ?>"><strong><?php echo esc_html($t->subject); ?></strong></a></td>
                                <td data-label="طرف مقابل">
                                    <div class="sc-member-identity">
                                        <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($other_initials); ?></span>
                                        <div class="sc-member-identity-text">
                                            <span class="sc-member-name"><?php echo esc_html($other_label !== '' ? $other_label : $other_name); ?></span>
                                            <?php if ($other_label !== '' && $other_label !== $other_name) : ?>
                                                <span class="sc-member-meta"><?php echo esc_html(strpos((string) $other_name, 'به:') === 0 ? 'ارسال به' : 'دریافت از'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="وضعیت"><span class="sc-badge <?php echo esc_attr($status_badge); ?>"><?php echo esc_html(sc_support_status_label($t->status)); ?></span></td>
                                <td data-label="تاریخ"><?php echo esc_html(sc_date_shamsi($t->updated_at, 'Y/m/d H:i')); ?></td>
                                <td data-label="عملیات"><a href="<?php echo esc_url($view_url); ?>" class="sc-ticket-list-action">مشاهده و پاسخ</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
