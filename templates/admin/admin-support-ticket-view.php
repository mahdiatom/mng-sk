<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$current_user_id = get_current_user_id();
$list_url = admin_url('admin.php?page=sc-support-tickets');

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
                sc_support_add_message($tid, 'admin', $current_user_id, $msg, $att);
            }
            wp_safe_redirect(admin_url('admin.php?page=sc-support-ticket-view&id=' . $tid));
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
<div class="wrap sc-support-admin-wrap">
    <div class="sc-ticket-detail-card">
        <a href="<?php echo esc_url($list_url); ?>" class="sc-ticket-back-link">← بازگشت به لیست تیکت‌ها</a>
        <h1 class="sc-ticket-detail-title">تیکت #<?php echo (int) $ticket->id; ?> – <?php echo esc_html($ticket->subject); ?></h1>
        <div class="sc-ticket-detail-meta">
            <span class="sc-ticket-meta-badge sc-ticket-status-<?php echo esc_attr($ticket->status); ?>"><?php echo esc_html(sc_support_status_label($ticket->status)); ?></span>
            <span class="sc-ticket-meta-text">بخش: <?php echo esc_html(sc_support_department_label($ticket->department, $ticket->coach_id)); ?></span>
            <?php
            $created_by = isset($ticket->created_by_type) ? $ticket->created_by_type : 'user';
            if ($created_by === 'coach' && !empty($ticket->created_by_coach_id)) {
                $coach_row = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}sc_coaches WHERE id = %d", $ticket->created_by_coach_id));
                echo '<span class="sc-ticket-meta-text">ارسال توسط مربی: ' . ($coach_row ? esc_html(trim($coach_row->first_name . ' ' . $coach_row->last_name)) : '') . '</span>';
            } elseif ($created_by === 'admin') {
                echo '<span class="sc-ticket-meta-text">ارسال توسط مدیر</span>';
            } else {
                echo '<span class="sc-ticket-meta-text">ارسال توسط کاربر</span>';
            }
            if ((int) $ticket->user_id > 0) {
                $member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}sc_members WHERE user_id = %d", $ticket->user_id));
                echo '<span class="sc-ticket-meta-text">گیرنده: ' . ($member ? esc_html(trim($member->first_name . ' ' . $member->last_name)) : 'کاربر #' . $ticket->user_id) . '</span>';
            } elseif ($ticket->department === 'coach' && !empty($ticket->coach_id)) {
                $coach_row = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}sc_coaches WHERE id = %d", $ticket->coach_id));
                echo '<span class="sc-ticket-meta-text">گیرنده: مربی ' . ($coach_row ? esc_html(trim($coach_row->first_name . ' ' . $coach_row->last_name)) : '') . '</span>';
            } else {
                echo '<span class="sc-ticket-meta-text">گیرنده: مدیر باشگاه</span>';
            }
            ?>
            <span class="sc-ticket-meta-text">آخرین به‌روزرسانی: <?php echo esc_html(sc_date_shamsi($ticket->updated_at, 'Y/m/d H:i')); ?></span>
        </div>

        <div class="sc-ticket-messages">
            <?php
            $messages = sc_support_get_messages($ticket->id);
            foreach ($messages as $msg) :
                $is_staff = ($msg->sender_type === 'admin' || $msg->sender_type === 'coach');
                $label = ($msg->sender_type === 'user') ? 'کاربر' : (($msg->sender_type === 'admin') ? 'مدیر' : 'مربی');
            ?>
            <div class="sc-ticket-msg <?php echo $is_staff ? 'sc-ticket-msg-staff' : 'sc-ticket-msg-user'; ?>">
                <div class="sc-ticket-msg-header">
                    <strong><?php echo esc_html($label); ?></strong>
                    <span class="sc-ticket-meta-text"><?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d H:i')); ?></span>
                </div>
                <div class="sc-ticket-msg-body"><?php echo wp_kses_post(wpautop($msg->message)); ?></div>
                <?php
                $attachment_ids_raw = isset($msg->attachment_ids) ? $msg->attachment_ids : '';
                if ($attachment_ids_raw !== '' && $attachment_ids_raw !== null) {
                    $ids = json_decode($attachment_ids_raw, true);
                    if (is_array($ids) && count($ids) > 0) {
                        echo '<div class="sc-ticket-msg-attachments">';
                        foreach ($ids as $aid) {
                            $aid = (int) $aid;
                            if ($aid <= 0) continue;
                            $url = sc_support_attachment_download_url($aid, $ticket->id);
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="sc-ticket-attachment-link">' . esc_html(get_the_title($aid) ?: 'پیوست') . '</a>';
                        }
                        echo '</div>';
                    }
                }
                ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($ticket->status !== 'closed') : ?>
        <div class="sc-ticket-reply-form-wrap">
            <form method="post" enctype="multipart/form-data" class="sc-ticket-reply-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="reply">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                <p class="sc-ticket-field">
                    <label for="reply_message">پاسخ شما</label>
                    <textarea name="reply_message" id="reply_message" rows="4" required placeholder="متن پاسخ خود را بنویسید..."></textarea>
                </p>
                <p class="sc-ticket-field">
                    <label>پیوست (اختیاری)</label>
                    <input type="file" name="reply_attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                    <span class="sc-form-hint" style="display:block; margin-top:0.35rem; font-size:0.8125rem; color:#646970;">فرمت‌های مجاز: تصویر، PDF، ورد، اکسل. حداکثر ۵ فایل، هر کدام ۵ مگابایت.</span>
                </p>
                <div class="sc-form-actions">
                    <button type="submit" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-submit">ارسال پاسخ</button>
                </div>
            </form>
        </div>
        <div class="sc-ticket-close-form-wrap">
            <form method="post" class="sc-ticket-close-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="close">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                <div class="sc-form-actions">
                    <button type="submit" class="sc-ticket-btn sc-ticket-btn-close" onclick="return confirm('آیا از بستن این تیکت اطمینان دارید؟');">بستن تیکت</button>
                </div>
            </form>
        </div>
        <?php else : ?>
        <div class="sc-ticket-closed-notice">
            <p>این تیکت بسته شده است. با ارسال پاسخ جدید، تیکت مجدداً باز می‌شود.</p>
            <form method="post" enctype="multipart/form-data" class="sc-ticket-reopen-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="reply">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                <p class="sc-ticket-field"><textarea name="reply_message" rows="3" placeholder="متن پاسخ برای باز کردن تیکت..."></textarea></p>
                <div class="sc-form-actions"><button type="submit" class="sc-ticket-btn sc-ticket-btn-primary sc-ticket-btn-submit">ارسال و باز کردن تیکت</button></div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
