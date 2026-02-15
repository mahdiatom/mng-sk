<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$list_url = admin_url('admin.php?page=sc-support-tickets');
$error_message = '';

$members_table = $wpdb->prefix . 'sc_members';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$members = $wpdb->get_results(
    "SELECT id AS member_id, first_name, last_name, national_id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name FROM $members_table WHERE is_active = 1 ORDER BY name",
    ARRAY_A
);
$coaches = $wpdb->get_results(
    "SELECT id AS coach_id, first_name, last_name, national_id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name FROM $coaches_table WHERE is_active = 1 ORDER BY name",
    ARRAY_A
);

$selected_member_id = isset($_POST['recipient_member_id']) ? absint($_POST['recipient_member_id']) : 0;
$selected_coach_id = isset($_POST['recipient_coach_id']) ? absint($_POST['recipient_coach_id']) : 0;
$selected_member_text = '';
$selected_coach_text = '';
foreach ((array) $members as $m) {
    if ((int) $m['member_id'] === $selected_member_id) {
        $selected_member_text = $m['name'] . (isset($m['national_id']) && $m['national_id'] ? ' - ' . $m['national_id'] : '');
        break;
    }
}
foreach ((array) $coaches as $c) {
    if ((int) $c['coach_id'] === $selected_coach_id) {
        $selected_coach_text = $c['name'] . (isset($c['national_id']) && $c['national_id'] ? ' - ' . $c['national_id'] : '');
        break;
    }
}

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
<div class="wrap sc-coach-panel-wrap sc-ticket-new-admin-wrap">
    <div class="sc-coach-panel-header">
        <a href="<?php echo esc_url($list_url); ?>" class="sc-coach-panel-back">← بازگشت به لیست تیکت‌ها</a>
        <h1 class="sc-coach-panel-title">ارسال تیکت جدید</h1>
        <p class="sc-coach-panel-desc">تیکت را به کاربر (عضو) یا به یکی از مربیان ارسال کنید.</p>
    </div>
    <?php if ($error_message) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error_message); ?></p></div>
    <?php endif; ?>
    <div class="sc-coach-panel-card">
        <form method="post" class="sc-ticket-new-form" id="sc-admin-ticket-new-form">
            <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
            <input type="hidden" name="sc_ticket_action" value="create">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="recipient_type">گیرنده</label></th>
                    <td>
                        <select name="recipient_type" id="recipient_type" class="regular-text" style="max-width: 280px;">
                            <option value="member">کاربر (عضو)</option>
                            <option value="coach">مربی</option>
                        </select>
                    </td>
                </tr>
                <tr class="recipient-member-row">
                    <th scope="row"><label>کاربر (عضو)</label></th>
                    <td>
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
                                    if (is_array($members)) : foreach ($members as $m) :
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
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                        <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید.</p>
                    </td>
                </tr>
                <tr class="recipient-coach-row" style="display:none;">
                    <th scope="row"><label>مربی</label></th>
                    <td>
                        <div class="sc-searchable-dropdown sc-ticket-coach-dropdown">
                            <input type="hidden" name="recipient_coach_id" id="recipient_coach_id" value="<?php echo esc_attr($selected_coach_id); ?>">
                            <div class="sc-dropdown-toggle">
                                <span class="sc-dropdown-placeholder" style="display: <?php echo $selected_coach_id > 0 ? 'none' : 'inline'; ?>;">جستجو یا انتخاب مربی...</span>
                                <span class="sc-dropdown-selected" style="display: <?php echo $selected_coach_id > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_coach_text); ?></span>
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
                                    if (is_array($coaches)) : foreach ($coaches as $c) :
                                        $cid = (int) $c['coach_id'];
                                        $label = $c['name'] . (isset($c['national_id']) && $c['national_id'] ? ' - ' . $c['national_id'] : '');
                                        $search = strtolower($c['name'] . ' ' . (isset($c['national_id']) ? $c['national_id'] : ''));
                                        $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                        $opt_index++;
                                        $is_selected = ($selected_coach_id === $cid);
                                    ?>
                                        <div class="sc-dropdown-option <?php echo $vis; ?>" data-value="<?php echo esc_attr($cid); ?>" data-search="<?php echo esc_attr($search); ?>"
                                             onclick="scSelectMember(this, '<?php echo esc_js((string) $cid); ?>', '<?php echo esc_js($label); ?>')"
                                             style="<?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>">
                                            <?php if ($is_selected) : ?><span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span><?php endif; ?>
                                            <?php echo esc_html($label); ?>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                        <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید.</p>
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
    function resetDropdown($row) {
        var $dd = $row.find('.sc-searchable-dropdown');
        $dd.find('input[type="hidden"]').val('0');
        $dd.find('.sc-dropdown-placeholder').show();
        $dd.find('.sc-dropdown-selected').hide().text('');
        $dd.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '').find('.sc-option-check').remove();
    }
    $('#recipient_type').on('change', function() {
        var v = this.value;
        $('.recipient-member-row').toggle(v === 'member');
        $('.recipient-coach-row').toggle(v === 'coach');
        if (v === 'member') { resetDropdown($('.recipient-coach-row')); }
        else { resetDropdown($('.recipient-member-row')); }
    }).trigger('change');
});
</script>
