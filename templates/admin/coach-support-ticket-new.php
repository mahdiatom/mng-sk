<?php
if (!defined('ABSPATH')) exit;
$list_url = admin_url('admin.php?page=sc-coach-support-tickets');
$error_message = '';
$members = function_exists('sc_support_get_members_for_coach') ? sc_support_get_members_for_coach($coach_id) : [];

$selected_member_id = isset($_POST['recipient_member_id']) ? absint($_POST['recipient_member_id']) : 0;
$selected_member_text = '';
if ($selected_member_id > 0) {
    foreach ($members as $m) {
        if ((int) $m['member_id'] === $selected_member_id) {
            $selected_member_text = $m['name'] . (isset($m['national_id']) && $m['national_id'] ? ' - ' . $m['national_id'] : '');
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_ticket_action']) && ($_POST['sc_ticket_action'] ?? '') === 'create') {
    if (!wp_verify_nonce($_POST['sc_ticket_nonce'] ?? '', 'sc_ticket_action')) {
        $error_message = 'خطای امنیتی.';
    } else {
        $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'manager');
        if (!in_array($recipient_type, ['member', 'manager'], true)) {
            $recipient_type = 'manager';
        }
        $recipient_id = ($recipient_type === 'member') ? absint($_POST['recipient_member_id'] ?? 0) : 0;
        $subject = sanitize_text_field($_POST['ticket_subject'] ?? '');
        $message = wp_kses_post($_POST['ticket_message'] ?? '');
        $attachments = [];
        if (!empty($_POST['ticket_attachment_ids']) && function_exists('sc_support_validate_attachment_ids')) {
            $raw = is_array($_POST['ticket_attachment_ids']) ? $_POST['ticket_attachment_ids'] : explode(',', (string) $_POST['ticket_attachment_ids']);
            $attachments = sc_support_validate_attachment_ids($raw, 5);
        }
        if (empty($attachments) && function_exists('sc_support_handle_attachments')) {
            $attachments = sc_support_handle_attachments('ticket_attachments');
        }
        if (empty($subject) || empty($message)) {
            $error_message = 'موضوع و متن پیام الزامی است.';
        } elseif ($recipient_type === 'member' && $recipient_id <= 0) {
            $error_message = 'لطفاً یک کاربر را انتخاب کنید.';
        } else {
            $result = sc_support_create_ticket_by_coach($coach_id, $recipient_type, $recipient_id, $subject, $message, $attachments);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                wp_safe_redirect(admin_url('admin.php?page=sc-coach-support-ticket-view&id=' . $result));
                exit;
            }
        }
    }
}
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <a href="<?php echo esc_url($list_url); ?>" class="sc-coach-panel-back">← بازگشت به لیست تیکت‌ها</a>
        <h1 class="sc-coach-panel-title">ارسال تیکت جدید</h1>
        <p class="sc-coach-panel-desc">تیکت را به مدیر باشگاه یا به یکی از کاربران (اعضای دوره‌های شما) ارسال کنید.</p>
    </div>
    <?php if ($error_message) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error_message); ?></p></div>
    <?php endif; ?>
    <div class="sc-coach-panel-card">
        <form method="post" class="sc-ticket-new-form" id="sc-coach-ticket-new-form">
            <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
            <input type="hidden" name="sc_ticket_action" value="create">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="recipient_type">گیرنده</label></th>
                    <td>
                        <select name="recipient_type" id="recipient_type" class="regular-text" style="max-width: 280px;">
                            <option value="manager">مدیر باشگاه</option>
                            <option value="member">کاربر (عضو)</option>
                        </select>
                    </td>
                </tr>
                <tr class="recipient-member-row" style="display:none;">
                    <th scope="row"><label>کاربر (عضو)</label></th>
                    <td>
                        <?php if (empty($members)) : ?>
                            <p class="description">هیچ عضوی در دوره‌های شما ثبت‌نام نکرده است.</p>
                            <input type="hidden" name="recipient_member_id" value="0">
                        <?php else : ?>
                        <div class="sc-searchable-dropdown sc-ticket-recipient-dropdown">
                            <input type="hidden" name="recipient_member_id" id="recipient_member_id" value="<?php echo esc_attr($selected_member_id); ?>">
                            <div class="sc-dropdown-toggle">
                                <span class="sc-dropdown-placeholder" style="display: <?php echo $selected_member_id > 0 ? 'none' : 'inline'; ?>;">جستجو یا انتخاب کاربر...</span>
                                <span class="sc-dropdown-selected" style="display: <?php echo $selected_member_id > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                                <span class="sc-dropdown-arrow">▼</span>
                            </div>
                            <div class="sc-dropdown-menu">
                                <div class="sc-dropdown-search">
                                    <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                                </div>
                                <div class="sc-dropdown-options" style="max-height: 250px; overflow-y: auto;">
                                    <?php
                                    $opt_index = 0;
                                    $max_visible = 10;
                                    foreach ($members as $m) :
                                        $mid = (int) $m['member_id'];
                                        $label = $m['name'] . (isset($m['national_id']) && $m['national_id'] ? ' - ' . $m['national_id'] : '');
                                        $search = strtolower($m['name'] . ' ' . (isset($m['national_id']) ? $m['national_id'] : ''));
                                        $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                        $opt_index++;
                                        $is_selected = ($selected_member_id === $mid);
                                    ?>
                                        <div class="sc-dropdown-option <?php echo $vis; ?>" data-value="<?php echo esc_attr($mid); ?>" data-search="<?php echo esc_attr($search); ?>"
                                             onclick="scSelectMember(this, '<?php echo esc_js((string) $mid); ?>', '<?php echo esc_js($label); ?>')"
                                             style="<?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>">
                                            <?php if ($is_selected) : ?><span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span><?php endif; ?>
                                            <?php echo esc_html($label); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ticket_subject">موضوع <span style="color:#d63638;">*</span></label></th>
                    <td>
                        <input type="text" name="ticket_subject" id="ticket_subject" class="regular-text" style="max-width: 100%;" value="<?php echo esc_attr(isset($_POST['ticket_subject']) ? $_POST['ticket_subject'] : ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ticket_message">متن پیام <span style="color:#d63638;">*</span></label></th>
                    <td>
                        <textarea name="ticket_message" id="ticket_message" rows="6" class="large-text" style="width: 100%; max-width: 600px; border-radius: 8px; padding: 10px;" required><?php echo esc_textarea(isset($_POST['ticket_message']) ? $_POST['ticket_message'] : ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>پیوست</label></th>
                    <td>
                        <div class="sc-ticket-attachment-zone" data-input-name="ticket_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_ticket_upload_attachment')); ?>">
                            <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                                <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx" multiple>
                                <span class="sc-file-upload-icon">📎</span>
                                <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                                <span class="sc-file-upload-hint">حداکثر ۵ فایل، هر کدام ۵ مگابایت. فرمت: تصویر، PDF، ورد، اکسل</span>
                            </div>
                            <div class="sc-ticket-upload-progress-wrap" style="display:none;">
                                <div class="sc-upload-progress sc-ticket-upload-progress">
                                    <div class="sc-upload-bar"></div>
                                    <span class="sc-upload-text"></span>
                                </div>
                            </div>
                            <div class="sc-ticket-uploaded-list"></div>
                            <div class="sc-ticket-attachment-ids-hidden"></div>
                        </div>
                    </td>
                </tr>
            </table>
            <p class="submit" style="padding: 16px 20px; margin: 0; border-top: 1px solid var(--sc-panel-border, #e2e8f0);">
                <button type="submit" class="button button-primary">ارسال تیکت</button>
            </p>
        </form>
    </div>
</div>
<script>
jQuery(function($) {
    $('#recipient_type').on('change', function() {
        $('.recipient-member-row').toggle(this.value === 'member');
    }).trigger('change');
});
</script>
