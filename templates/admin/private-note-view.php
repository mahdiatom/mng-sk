<?php
if (!defined('ABSPATH')) exit;

$thread_id = isset($_GET['thread_id']) ? absint($_GET['thread_id']) : 0;
$thread = $thread_id > 0 ? sc_private_notes_get_thread($thread_id) : null;
$legacy_note_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$legacy_note = null;
if ((!$thread || !sc_private_notes_can_view_thread($thread, get_current_user_id())) && $legacy_note_id > 0) {
    $legacy_note = sc_private_notes_get($legacy_note_id);
    if (!$legacy_note || !sc_private_notes_can_view($legacy_note, get_current_user_id())) {
        wp_die('پرونده یادداشت یافت نشد یا دسترسی ندارید.');
    }
} elseif (!$thread || !sc_private_notes_can_view_thread($thread, get_current_user_id())) {
    wp_die('پرونده یادداشت یافت نشد یا دسترسی ندارید.');
}

$message = '';
$message_type = '';
if (isset($_POST['sc_append_private_note_message']) && check_admin_referer('sc_append_private_note_message_action', 'sc_append_private_note_message_nonce')) {
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $attachment_ids = !empty($_POST['private_note_attachment_ids']) ? sc_private_notes_validate_attachment_ids($_POST['private_note_attachment_ids'], 5) : [];
    $created = sc_private_notes_append_message_to_thread((int) $thread->id, $content, $attachment_ids);
    if (is_wp_error($created)) {
        $message = $created->get_error_message();
        $message_type = 'error';
    } else {
        $message = 'پیام با موفقیت اضافه شد.';
        $message_type = 'success';
        $thread = sc_private_notes_get_thread((int) $thread->id);
    }
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, national_id FROM $members_table WHERE id = %d", (int) $thread->member_id));
$member_name = $member ? trim((string) $member->first_name . ' ' . (string) $member->last_name) : ('کاربر #' . (int) $thread->member_id);
$messages = $thread ? sc_private_notes_get_thread_messages((int) $thread->id) : [];
if ($thread) {
    sc_private_notes_set_last_selected_thread((int) $thread->member_id, (int) $thread->id, get_current_user_id());
}
$back_page = current_user_can('manage_options') ? 'sc-private-notes' : 'sc-coach-private-notes';
?>
<div class="wrap sc-private-notes-wrap">
    <h1>پرونده یادداشت خصوصی</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $back_page)); ?>" class="button">بازگشت به لیست</a>
    <?php if ($message) : ?><div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
    <div class="sc-private-note-detail-card">
        <p><strong>کاربر:</strong> <?php echo esc_html($member_name); ?></p>
        <?php if ($thread) : ?>
        <p><strong>آخرین فعالیت:</strong> <?php echo esc_html(sc_date_shamsi($thread->updated_at, 'Y/m/d H:i')); ?></p>
        <?php foreach ((array) $messages as $msg) : ?>
            <div class="sc-private-note-body">
                <p><strong><?php echo esc_html($msg->author_type === 'coach' ? ('مربی: ' . ($msg->coach_name ?: '-')) : 'مدیر'); ?></strong> - <?php echo esc_html(sc_date_shamsi($msg->created_at, 'Y/m/d H:i')); ?></p>
                <div><?php echo wp_kses_post(wpautop($msg->content)); ?></div>
                <?php $attachment_ids = !empty($msg->attachment_ids) ? json_decode($msg->attachment_ids, true) : []; $attachment_ids = is_array($attachment_ids) ? array_map('absint', $attachment_ids) : []; ?>
                <?php if (!empty($attachment_ids)) : ?>
                    <div class="sc-private-note-attachments"><strong>پیوست‌ها:</strong><ul>
                    <?php foreach ($attachment_ids as $aid) : if ($aid <= 0) continue; ?>
                        <li><a href="<?php echo esc_url(sc_private_notes_attachment_download_url($aid, (int) $msg->id)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($aid) ?: ('فایل #' . $aid)); ?></a></li>
                    <?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>
            </div>
            <hr>
        <?php endforeach; ?>
        <?php else : ?>
            <p><strong>تاریخ:</strong> <?php echo esc_html(sc_date_shamsi($legacy_note->created_at, 'Y/m/d H:i')); ?></p>
            <div class="sc-private-note-body"><?php echo wp_kses_post(wpautop($legacy_note->content)); ?></div>
        <?php endif; ?>
        <?php if ($thread) : ?>
        <h3>افزودن پیام جدید</h3>
        <form method="post">
            <?php wp_nonce_field('sc_append_private_note_message_action', 'sc_append_private_note_message_nonce'); ?>
            <textarea name="content" rows="5" class="large-text" required></textarea>
            <div class="sc-ticket-attachment-zone" data-input-name="private_note_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_private_note_upload_attachment')); ?>" data-action="sc_upload_private_note_attachment" data-nonce-key="sc_private_note_upload_nonce">
                <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                    <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.ico,.svg,.tiff,.tif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" multiple>
                    <span class="sc-file-upload-icon">📎</span><span class="sc-file-upload-text">افزودن پیوست</span><span class="sc-file-upload-hint">حداکثر ۵ فایل</span>
                </div>
                <div class="sc-ticket-upload-progress-wrap" style="display:none;"><div class="sc-upload-progress sc-ticket-upload-progress"><div class="sc-upload-bar"></div><span class="sc-upload-text"></span></div></div>
                <div class="sc-ticket-uploaded-list"></div><div class="sc-ticket-attachment-ids-hidden"></div>
            </div>
            <p class="submit"><button class="button button-primary" type="submit" name="sc_append_private_note_message">ثبت پیام</button></p>
        </form>
        <?php endif; ?>
    </div>
</div>
