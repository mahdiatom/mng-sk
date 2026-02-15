<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$list_url = admin_url('admin.php?page=sc-support-tickets');
$error_message = '';

$members_table = $wpdb->prefix . 'sc_members';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$members = $wpdb->get_results(
    "SELECT id AS member_id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name FROM $members_table ORDER BY name",
    ARRAY_A
);
$coaches = $wpdb->get_results(
    "SELECT id AS coach_id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name FROM $coaches_table WHERE is_active = 1 ORDER BY name",
    ARRAY_A
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_ticket_action']) && ($_POST['sc_ticket_action'] ?? '') === 'create') {
    if (!wp_verify_nonce($_POST['sc_ticket_nonce'] ?? '', 'sc_ticket_action')) {
        $error_message = 'خطای امنیتی.';
    } else {
        $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'member');
        if (!in_array($recipient_type, ['member', 'coach'], true)) {
            $recipient_type = 'member';
        }
        $recipient_id = ($recipient_type === 'member') ? absint($_POST['recipient_member_id'] ?? 0) : absint($_POST['recipient_coach_id'] ?? 0);
        $subject = sanitize_text_field($_POST['ticket_subject'] ?? '');
        $message = wp_kses_post($_POST['ticket_message'] ?? '');
        $attachments = function_exists('sc_support_handle_attachments') ? sc_support_handle_attachments('ticket_attachments') : [];
        if (empty($subject) || empty($message)) {
            $error_message = 'موضوع و متن پیام الزامی است.';
        } elseif ($recipient_id <= 0) {
            $error_message = $recipient_type === 'member' ? 'لطفاً یک کاربر را انتخاب کنید.' : 'لطفاً یک مربی را انتخاب کنید.';
        } else {
            $result = sc_support_create_ticket_by_admin(get_current_user_id(), $recipient_type, $recipient_id, $subject, $message, $attachments);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                wp_safe_redirect(admin_url('admin.php?page=sc-support-ticket-view&id=' . $result));
                exit;
            }
        }
    }
}
?>
<div class="wrap">
    <a href="<?php echo esc_url($list_url); ?>" class="button">← بازگشت به لیست تیکت‌ها</a>
    <h1>ارسال تیکت جدید</h1>
    <?php if ($error_message) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="sc-ticket-new-form" style="max-width:700px; margin-top:20px;">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="create">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="recipient_type">گیرنده</label></th>
                <td>
                    <select name="recipient_type" id="recipient_type">
                        <option value="member">کاربر (عضو)</option>
                        <option value="coach">مربی</option>
                    </select>
                </td>
            </tr>
            <tr class="recipient-member-row">
                <th scope="row"><label for="recipient_member_id">کاربر</label></th>
                <td>
                    <select name="recipient_member_id" id="recipient_member_id">
                        <option value="">— انتخاب کنید —</option>
                        <?php if (is_array($members)) : foreach ($members as $m) : ?>
                            <option value="<?php echo (int) $m['member_id']; ?>"><?php echo esc_html($m['name']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </td>
            </tr>
            <tr class="recipient-coach-row" style="display:none;">
                <th scope="row"><label for="recipient_coach_id">مربی</label></th>
                <td>
                    <select name="recipient_coach_id" id="recipient_coach_id">
                        <option value="">— انتخاب کنید —</option>
                        <?php if (is_array($coaches)) : foreach ($coaches as $c) : ?>
                            <option value="<?php echo (int) $c['coach_id']; ?>"><?php echo esc_html($c['name']); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="ticket_subject">موضوع <span class="required">*</span></label></th>
                <td><input type="text" name="ticket_subject" id="ticket_subject" class="regular-text" value="<?php echo esc_attr(isset($_POST['ticket_subject']) ? $_POST['ticket_subject'] : ''); ?>" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="ticket_message">متن پیام <span class="required">*</span></label></th>
                <td>
                    <textarea name="ticket_message" id="ticket_message" rows="6" class="large-text" required><?php echo esc_textarea(isset($_POST['ticket_message']) ? $_POST['ticket_message'] : ''); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>پیوست</label></th>
                <td>
                    <input type="file" name="ticket_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx" class="regular-text">
                    <p class="description">فرمت‌های مجاز: تصویر، PDF، ورد، اکسل. حداکثر ۵ فایل، هر کدام ۵ مگابایت.</p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary">ارسال تیکت</button>
        </p>
    </form>
</div>
<script>
document.getElementById('recipient_type').addEventListener('change', function() {
    var memberRow = document.querySelector('.recipient-member-row');
    var coachRow = document.querySelector('.recipient-coach-row');
    if (this.value === 'member') {
        memberRow.style.display = '';
        coachRow.style.display = 'none';
        document.getElementById('recipient_coach_id').value = '';
    } else {
        memberRow.style.display = 'none';
        coachRow.style.display = '';
        document.getElementById('recipient_member_id').value = '';
    }
});
document.getElementById('recipient_type').dispatchEvent(new Event('change'));
</script>
