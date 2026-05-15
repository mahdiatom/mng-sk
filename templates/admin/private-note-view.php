<?php
if (!defined('ABSPATH')) exit;

$thread_id = isset($_GET['thread_id']) ? absint($_GET['thread_id']) : 0;
$thread = $thread_id > 0 ? sc_private_notes_get_thread($thread_id) : null;
$legacy_note_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$legacy_note = null;
$use_legacy = false;

if ((!$thread || !sc_private_notes_can_view_thread($thread, get_current_user_id())) && $legacy_note_id > 0) {
    $legacy_note = sc_private_notes_get($legacy_note_id);
    if (!$legacy_note || !sc_private_notes_can_view($legacy_note, get_current_user_id())) {
        wp_die('پرونده یادداشت یافت نشد یا دسترسی ندارید.');
    }
    $use_legacy = true;
} elseif (!$thread || !sc_private_notes_can_view_thread($thread, get_current_user_id())) {
    wp_die('پرونده یادداشت یافت نشد یا دسترسی ندارید.');
}

$message = '';
$message_type = '';
if (!$use_legacy && isset($_POST['sc_append_private_note_message']) && check_admin_referer('sc_append_private_note_message_action', 'sc_append_private_note_message_nonce')) {
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
$member_id_for_label = $use_legacy ? (int) $legacy_note->member_id : (int) $thread->member_id;
$member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, national_id FROM $members_table WHERE id = %d", $member_id_for_label));
$member_name = $member ? trim((string) $member->first_name . ' ' . (string) $member->last_name) : ('کاربر #' . $member_id_for_label);

$msg_s = isset($_GET['msg_s']) ? sanitize_text_field(wp_unslash($_GET['msg_s'])) : '';
$msg_date_from_shamsi = isset($_GET['msg_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['msg_date_from_shamsi'])) : '';
$msg_date_to_shamsi = isset($_GET['msg_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['msg_date_to_shamsi'])) : '';
$today_gregorian = current_time('Y-m-d');
$one_year_ago_gregorian = gmdate('Y-m-d', strtotime('-1 year', strtotime($today_gregorian)));
$today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_gregorian) : $today_gregorian;
$one_year_ago_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($one_year_ago_gregorian) : $one_year_ago_gregorian;
if ($msg_date_from_shamsi === '' && $msg_date_to_shamsi === '') {
    $msg_date_from_shamsi = $one_year_ago_shamsi;
    $msg_date_to_shamsi = $today_shamsi;
} elseif ($msg_date_from_shamsi === '') {
    $msg_date_from_shamsi = $msg_date_to_shamsi;
} elseif ($msg_date_to_shamsi === '') {
    $msg_date_to_shamsi = $msg_date_from_shamsi;
}
$msg_filter = [];
if ($msg_s !== '') {
    $msg_filter['search'] = $msg_s;
}
if ($msg_date_from_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $msg_filter['date_from'] = sc_shamsi_to_gregorian_date($msg_date_from_shamsi);
}
if ($msg_date_to_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
    $msg_filter['date_to'] = sc_shamsi_to_gregorian_date($msg_date_to_shamsi);
}

$messages = (!$use_legacy && $thread) ? sc_private_notes_get_thread_messages((int) $thread->id, $msg_filter) : [];
if (!$use_legacy && $thread) {
    sc_private_notes_set_last_selected_thread((int) $thread->member_id, (int) $thread->id, get_current_user_id());
}

$back_page = current_user_can('manage_options') ? 'sc-private-notes' : 'sc-coach-private-notes';
$view_base_args = ['page' => 'sc-private-notes-view'];
if (!$use_legacy && $thread) {
    $view_base_args['thread_id'] = (int) $thread->id;
} elseif ($use_legacy && $legacy_note) {
    $view_base_args['id'] = (int) $legacy_note->id;
}
$detail_url = add_query_arg($view_base_args, admin_url('admin.php'));
?>
<div class="wrap sc-private-notes-wrap">
    <h1 style="margin-bottom: 10px;">پرونده یادداشت خصوصی</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $back_page)); ?>" class="sc_button">بازگشت به لیست</a>

    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <div class="sc-private-note-detail-card">
        <p><strong>کاربر:</strong> <?php echo esc_html($member_name); ?></p>

        <?php if (!$use_legacy && $thread) : ?>
            <p><strong>نام پرونده:</strong> <?php echo esc_html(trim((string) $thread->subject) !== '' ? (string) $thread->subject : ('پرونده #' . (int) $thread->id)); ?></p>
            <p><strong>آخرین فعالیت:</strong> <?php echo esc_html(sc_date_shamsi($thread->updated_at, 'Y/m/d H:i')); ?></p>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="form_fillter_attendance form_fillter_attendance_tab1 sc-private-notes-msg-filters-admin">
                <input type="hidden" name="page" value="sc-private-notes-view">
                <input type="hidden" name="thread_id" value="<?php echo (int) $thread->id; ?>">
                <div class="sc-filter-grid">
                    <div class="sc-filter-field" style="grid-column: 1 / -1;">
                        <label class="sc-filter-label" for="sc-pn-admin-msg-s">جستجو در متن پیام‌ها</label>
                        <input type="search" name="msg_s" id="sc-pn-admin-msg-s" class="regular-text" value="<?php echo esc_attr($msg_s); ?>" placeholder="کلمه یا عبارت...">
                    </div>
                    <div class="sc-filter-field sc-filter-date">
                        <label class="sc-filter-label">بازه تاریخ پیام (شمسی)</label>
                        <div class="sc-date-range">
                            <input style="width: 50%;" type="text" name="msg_date_from_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($msg_date_from_shamsi); ?>" placeholder="از" readonly autocomplete="off">
                            <span class="sc-date-separator">تا</span>
                            <input style="width: 50%;" type="text" name="msg_date_to_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($msg_date_to_shamsi); ?>" placeholder="تا" readonly autocomplete="off">
                        </div>
                    </div>
                </div>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="اعمال فیلتر پیام‌ها">
                    <a href="<?php echo esc_url($detail_url); ?>" class="button">پاک کردن فیلتر</a>
                </p>
            </form>

            <?php if (empty($messages)) : ?>
                <p class="description">پیامی با این فیلترها یافت نشد.</p>
            <?php else : ?>
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
            <?php endif; ?>

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

        <?php else : ?>
            <p><strong>تاریخ:</strong> <?php echo esc_html(sc_date_shamsi($legacy_note->created_at, 'Y/m/d H:i')); ?></p>
            <div class="sc-private-note-body"><?php echo wp_kses_post(wpautop($legacy_note->content)); ?></div>
        <?php endif; ?>
    </div>
</div>
