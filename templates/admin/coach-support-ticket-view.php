<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$current_user_id = get_current_user_id();
$coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id($current_user_id) : 0;
$list_url = admin_url('admin.php?page=sc-coach-support-tickets');

// Process POST: reply or close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_ticket_action'])) {
    if (!wp_verify_nonce($_POST['sc_ticket_nonce'] ?? '', 'sc_ticket_action')) {
        echo '<div class="notice notice-error"><p>خطای امنیتی.</p></div>';
    } else {
        $action = sanitize_text_field($_POST['sc_ticket_action']);
        if ($action === 'reply') {
            $tid = absint($_POST['ticket_id'] ?? 0);
            $t = sc_support_get_ticket($tid);
            if ($t && sc_support_can_view_ticket($t, $current_user_id)) {
                $msg = wp_kses_post($_POST['reply_message'] ?? '');
                $att = function_exists('sc_support_handle_attachments') ? sc_support_handle_attachments('reply_attachments') : [];
                sc_support_add_message($tid, 'coach', $coach_id, $msg, $att);
            }
            wp_safe_redirect(admin_url('admin.php?page=sc-coach-support-ticket-view&id=' . $tid));
            exit;
        } elseif ($action === 'close') {
            $tid = absint($_POST['ticket_id'] ?? 0);
            $t = sc_support_get_ticket($tid);
            if ($t && sc_support_can_close_ticket($t, $current_user_id)) {
                sc_support_close_ticket($tid, $current_user_id);
            }
            wp_safe_redirect($list_url);
            exit;
        }
    }
}
?>
<div class="wrap">
    <a href="<?php echo esc_url($list_url); ?>" class="button">← بازگشت به لیست تیکت‌ها</a>
    <h1>تیکت #<?php echo (int) $ticket->id; ?> – <?php echo esc_html($ticket->subject); ?></h1>
    <p>
        <strong>وضعیت:</strong> <?php echo esc_html(sc_support_status_label($ticket->status)); ?>
        <strong style="margin-right:15px;">طرف مقابل:</strong>
        <?php
        if (!empty($ticket->created_by_coach_id) && (int) $ticket->created_by_coach_id === (int) $coach_id) {
            if ((int) $ticket->user_id > 0) {
                $member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}sc_members WHERE user_id = %d", $ticket->user_id));
                echo $member ? esc_html(trim($member->first_name . ' ' . $member->last_name)) : 'کاربر #' . $ticket->user_id;
            } else {
                echo 'مدیر باشگاه';
            }
        } else {
            if ((int) $ticket->user_id > 0) {
                $member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}sc_members WHERE user_id = %d", $ticket->user_id));
                echo $member ? esc_html(trim($member->first_name . ' ' . $member->last_name)) : 'کاربر #' . $ticket->user_id;
            } else {
                echo '—';
            }
        }
        ?>
    </p>
    <div class="sc-ticket-messages" style="margin:20px 0; padding:15px; background:#f9f9f9;">
        <?php
        $messages = sc_support_get_messages($ticket->id);
        foreach ($messages as $msg) :
            $is_staff = ($msg->sender_type === 'admin' || $msg->sender_type === 'coach');
            $label = ($msg->sender_type === 'user') ? 'کاربر' : (($msg->sender_type === 'admin') ? 'مدیر' : 'شما (مربی)');
        ?>
        <div style="margin-bottom:15px; padding:10px; background:<?php echo $is_staff ? '#e8f4fc' : '#fff3e0'; ?>; border-radius:6px;">
            <strong><?php echo esc_html($label); ?></strong>
            <span style="color:#666; font-size:12px;"><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d H:i')); ?></span>
            <div style="margin-top:8px;"><?php echo wp_kses_post(wpautop($msg->message)); ?></div>
            <?php
            if (!empty($msg->attachment_ids)) {
                $ids = json_decode($msg->attachment_ids, true);
                if (is_array($ids)) {
                    echo '<div style="margin-top:8px;">';
                    foreach ($ids as $aid) {
                        $url = sc_support_attachment_download_url($aid, $ticket->id);
                        echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html(get_the_title($aid) ?: 'پیوست') . '</a> ';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($ticket->status !== 'closed') : ?>
    <form method="post" enctype="multipart/form-data" style="margin-top:20px;">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="reply">
        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
        <p>
            <label for="reply_message">پاسخ شما</label><br>
            <textarea name="reply_message" id="reply_message" rows="4" class="large-text" required></textarea>
        </p>
        <p>
            <label>پیوست (اختیاری)</label><br>
            <input type="file" name="reply_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
        </p>
        <p><button type="submit" class="button button-primary">ارسال پاسخ</button></p>
    </form>
    <form method="post" style="margin-top:10px;">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="close">
        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
        <button type="submit" class="button" onclick="return confirm('بستن این تیکت؟');">بستن تیکت</button>
    </form>
    <?php else : ?>
    <p><em>این تیکت بسته شده است. با ارسال پاسخ، تیکت مجدداً باز می‌شود.</em></p>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
        <input type="hidden" name="sc_ticket_action" value="reply">
        <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
        <p><textarea name="reply_message" rows="3" placeholder="متن پاسخ..."></textarea></p>
        <p><button type="submit" class="button">ارسال و باز کردن تیکت</button></p>
    </form>
    <?php endif; ?>
</div>
