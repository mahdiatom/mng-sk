<?php
if (!defined('ABSPATH')) exit;

$note_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$note = $note_id > 0 ? sc_private_notes_get($note_id) : null;
if (!$note || !sc_private_notes_can_view($note, get_current_user_id())) {
    wp_die('یادداشت یافت نشد یا دسترسی ندارید.');
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, national_id FROM $members_table WHERE id = %d", (int) $note->member_id));
$member_name = $member ? trim((string) $member->first_name . ' ' . (string) $member->last_name) : ('کاربر #' . (int) $note->member_id);
$back_page = current_user_can('manage_options') ? 'sc-private-notes' : 'sc-coach-private-notes';
?>
<div class="wrap sc-private-notes-wrap">
    <h1>جزئیات یادداشت خصوصی</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $back_page)); ?>" class="button">بازگشت به لیست</a>
    <div class="sc-private-note-detail-card">
        <h2><?php echo esc_html($note->title); ?></h2>
        <p><strong>کاربر:</strong> <?php echo esc_html($member_name); ?></p>
        <p><strong>تاریخ:</strong> <?php echo esc_html(sc_date_shamsi($note->created_at, 'Y/m/d H:i')); ?></p>
        <div class="sc-private-note-body"><?php echo wp_kses_post(wpautop($note->content)); ?></div>
        <?php
        $attachment_ids = !empty($note->attachment_ids) ? json_decode($note->attachment_ids, true) : [];
        $attachment_ids = is_array($attachment_ids) ? array_map('absint', $attachment_ids) : [];
        if (!empty($attachment_ids)) :
        ?>
            <div class="sc-private-note-attachments">
                <strong>پیوست‌ها:</strong>
                <ul>
                    <?php foreach ($attachment_ids as $aid) : ?>
                        <?php if ($aid <= 0) continue; ?>
                        <?php $url = sc_private_notes_attachment_download_url($aid, (int) $note->id); ?>
                        <li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($aid) ?: ('فایل #' . $aid)); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
