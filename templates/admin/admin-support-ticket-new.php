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
$accountants = function_exists('sc_support_get_accountant_users') ? sc_support_get_accountant_users() : [];

$selected_member_id = isset($_POST['recipient_member_id']) ? absint($_POST['recipient_member_id']) : 0;
$selected_coach_id = isset($_POST['recipient_coach_id']) ? absint($_POST['recipient_coach_id']) : 0;
$selected_accountant_id = isset($_POST['recipient_accountant_id']) ? absint($_POST['recipient_accountant_id']) : 0;
$selected_recipient_type = isset($_POST['recipient_type']) ? sanitize_text_field(wp_unslash($_POST['recipient_type'])) : 'member';
if (!in_array($selected_recipient_type, ['member', 'coach', 'manager', 'accountant'], true)) {
    $selected_recipient_type = 'member';
}

$selected_member_text = '';
$selected_coach_text = '';
$selected_accountant_text = '';
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
foreach ((array) $accountants as $a) {
    if ((int) $a['user_id'] === $selected_accountant_id) {
        $selected_accountant_text = $a['name'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_ticket_action']) && ($_POST['sc_ticket_action'] ?? '') === 'create') {
    if (!wp_verify_nonce($_POST['sc_ticket_nonce'] ?? '', 'sc_ticket_action')) {
        $error_message = 'خطای امنیتی.';
    } else {
        $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'member');
        if (!in_array($recipient_type, ['member', 'coach', 'accountant', 'manager'], true)) {
            $recipient_type = 'member';
        }
        if ($recipient_type === 'member') {
            $recipient_id = absint($_POST['recipient_member_id'] ?? 0);
        } elseif ($recipient_type === 'coach') {
            $recipient_id = absint($_POST['recipient_coach_id'] ?? 0);
        } elseif ($recipient_type === 'accountant') {
            $recipient_id = absint($_POST['recipient_accountant_id'] ?? 0);
        } else {
            $recipient_id = 0;
        }
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
        } elseif ($recipient_id <= 0 && $recipient_type !== 'manager') {
            if ($recipient_type === 'member') {
                $error_message = 'لطفاً یک کاربر را انتخاب کنید.';
            } elseif ($recipient_type === 'coach') {
                $error_message = 'لطفاً یک مربی را انتخاب کنید.';
            } else {
                $error_message = 'لطفاً یک حسابدار را انتخاب کنید.';
            }
        } else {
            $created_by = (function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only())
                ? 'accountant'
                : 'admin';
            $result = sc_support_create_ticket_by_admin(get_current_user_id(), $recipient_type, $recipient_id, $subject, $message, $attachments, $created_by);
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
<div class="wrap sc-ticket-new-page-header sc-ticket-new-admin-wrap">
    <h1 class="wp-heading-inline">ارسال تیکت جدید</h1>
    <a href="<?php echo esc_url($list_url); ?>" class="page-title-action">لیست تیکت‌ها</a>
    <hr class="wp-header-end">
    <p class="sc-ticket-new-subtitle">تیکت را به کاربر (عضو)، مربی، مدیر باشگاه یا حسابدار ارسال کنید.</p>
</div>
<div class="wrap sc-ticket-new-page-body sc-ticket-new-admin-wrap sc-coach-panel-wrap">
    <?php if ($error_message) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error_message); ?></p></div>
    <?php endif; ?>

    <div class="sc-ticket-new-panel postbox sc-coach-panel-card">
        <div class="postbox-header">
            <h2>فرم ارسال تیکت</h2>
        </div>
        <div class="inside">
            <form method="post" class="sc-ticket-new-form" id="sc-admin-ticket-new-form">
                <?php wp_nonce_field('sc_ticket_action', 'sc_ticket_nonce'); ?>
                <input type="hidden" name="sc_ticket_action" value="create">

                <table class="form-table sc-ticket-new-form-table" role="presentation">
                    <tbody>
                        <tr class="sc-ticket-field-row sc-ticket-field-row--recipient-type">
                            <th scope="row"><label for="recipient_type">گیرنده</label></th>
                            <td>
                                <select name="recipient_type" id="recipient_type" class="regular-text">
                                    <option value="member" <?php selected($selected_recipient_type, 'member'); ?>>کاربر (عضو)</option>
                                    <option value="coach" <?php selected($selected_recipient_type, 'coach'); ?>>مربی</option>
                                    <option value="manager" <?php selected($selected_recipient_type, 'manager'); ?>>مدیر باشگاه</option>
                                    <option value="accountant" <?php selected($selected_recipient_type, 'accountant'); ?>>حسابدار</option>
                                </select>
                                <p class="description">نوع گیرنده را انتخاب کنید؛ فیلد مربوطه در زیر نمایش داده می‌شود.</p>
                            </td>
                        </tr>
                        <tr class="sc-ticket-field-row recipient-member-row sc-ticket-field-row--recipient-member">
                            <th scope="row"><label>کاربر (عضو)</label></th>
                            <td>
                                <div class="sc-searchable-dropdown sc-ticket-recipient-dropdown">
                                    <input type="hidden" name="recipient_member_id" id="recipient_member_id" value="<?php echo esc_attr($selected_member_id); ?>">
                                    <div class="sc-dropdown-toggle" tabindex="0" role="button" aria-haspopup="listbox">
                                        <span class="sc-dropdown-placeholder" style="display: <?php echo $selected_member_id > 0 ? 'none' : 'inline'; ?>;">جستجو یا انتخاب کاربر...</span>
                                        <span class="sc-dropdown-selected" style="display: <?php echo $selected_member_id > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                                        <span class="sc-dropdown-arrow">▼</span>
                                    </div>
                                    <div class="sc-dropdown-menu" role="listbox">
                                        <div class="sc-dropdown-search">
                                            <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی..." autocomplete="off">
                                        </div>
                                        <div class="sc-dropdown-options">
                                            <?php
                                            $opt_index = 0;
                                            $max_visible = 10;
                                            if (is_array($members)) :
                                                foreach ($members as $m) :
                                                    $mid = (int) $m['member_id'];
                                                    $label = $m['name'] . (isset($m['national_id']) && $m['national_id'] ? ' - ' . $m['national_id'] : '');
                                                    $search = strtolower($m['name'] . ' ' . (isset($m['national_id']) ? $m['national_id'] : ''));
                                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                                    $opt_index++;
                                                    $is_selected = ($selected_member_id === $mid);
                                                    ?>
                                                    <div class="sc-dropdown-option <?php echo esc_attr($vis); ?><?php echo $is_selected ? ' sc-selected' : ''; ?>"
                                                         data-value="<?php echo esc_attr($mid); ?>"
                                                         data-label="<?php echo esc_attr($label); ?>"
                                                         data-search="<?php echo esc_attr($search); ?>"
                                                         onclick="scSelectMember(this, '<?php echo esc_js((string) $mid); ?>', '<?php echo esc_js($label); ?>')">
                                                        <?php if ($is_selected) : ?><span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span><?php endif; ?>
                                                        <?php echo esc_html($label); ?>
                                                    </div>
                                                <?php endforeach;
                                            endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید.</p>
                            </td>
                        </tr>
                        <tr class="sc-ticket-field-row recipient-coach-row sc-ticket-field-row--recipient-coach" style="display:none;">
                            <th scope="row"><label>مربی</label></th>
                            <td>
                                <div class="sc-searchable-dropdown sc-ticket-coach-dropdown">
                                    <input type="hidden" name="recipient_coach_id" id="recipient_coach_id" value="<?php echo esc_attr($selected_coach_id); ?>">
                                    <div class="sc-dropdown-toggle" tabindex="0" role="button" aria-haspopup="listbox">
                                        <span class="sc-dropdown-placeholder" style="display: <?php echo $selected_coach_id > 0 ? 'none' : 'inline'; ?>;">جستجو یا انتخاب مربی...</span>
                                        <span class="sc-dropdown-selected" style="display: <?php echo $selected_coach_id > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_coach_text); ?></span>
                                        <span class="sc-dropdown-arrow">▼</span>
                                    </div>
                                    <div class="sc-dropdown-menu" role="listbox">
                                        <div class="sc-dropdown-search">
                                            <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی..." autocomplete="off">
                                        </div>
                                        <div class="sc-dropdown-options">
                                            <?php
                                            $opt_index = 0;
                                            $max_visible = 10;
                                            if (is_array($coaches)) :
                                                foreach ($coaches as $c) :
                                                    $cid = (int) $c['coach_id'];
                                                    $label = $c['name'] . (isset($c['national_id']) && $c['national_id'] ? ' - ' . $c['national_id'] : '');
                                                    $search = strtolower($c['name'] . ' ' . (isset($c['national_id']) ? $c['national_id'] : ''));
                                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                                    $opt_index++;
                                                    $is_selected = ($selected_coach_id === $cid);
                                                    ?>
                                                    <div class="sc-dropdown-option <?php echo esc_attr($vis); ?><?php echo $is_selected ? ' sc-selected' : ''; ?>"
                                                         data-value="<?php echo esc_attr($cid); ?>"
                                                         data-label="<?php echo esc_attr($label); ?>"
                                                         data-search="<?php echo esc_attr($search); ?>"
                                                         onclick="scSelectMember(this, '<?php echo esc_js((string) $cid); ?>', '<?php echo esc_js($label); ?>')">
                                                        <?php if ($is_selected) : ?><span class="sc-option-check" style="float: left; color: #2271b1; font-weight: bold;">✓</span><?php endif; ?>
                                                        <?php echo esc_html($label); ?>
                                                    </div>
                                                <?php endforeach;
                                            endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید.</p>
                            </td>
                        </tr>
                        <tr class="sc-ticket-field-row recipient-accountant-row sc-ticket-field-row--recipient-accountant" style="display:none;">
                            <th scope="row"><label>حسابدار</label></th>
                            <td>
                                <?php if (empty($accountants)) : ?>
                                    <p class="description">هیچ کاربری با نقش حسابدار در سایت ثبت نشده است.</p>
                                    <input type="hidden" name="recipient_accountant_id" value="0">
                                <?php else : ?>
                                    <div class="sc-searchable-dropdown sc-ticket-accountant-dropdown">
                                        <input type="hidden" name="recipient_accountant_id" id="recipient_accountant_id" value="<?php echo esc_attr($selected_accountant_id); ?>">
                                        <div class="sc-dropdown-toggle" tabindex="0" role="button" aria-haspopup="listbox">
                                            <span class="sc-dropdown-placeholder" style="display: <?php echo $selected_accountant_id > 0 ? 'none' : 'inline'; ?>;">جستجو یا انتخاب حسابدار...</span>
                                            <span class="sc-dropdown-selected" style="display: <?php echo $selected_accountant_id > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_accountant_text); ?></span>
                                            <span class="sc-dropdown-arrow">▼</span>
                                        </div>
                                        <div class="sc-dropdown-menu" role="listbox">
                                            <div class="sc-dropdown-search">
                                                <input type="text" class="sc-search-input" placeholder="جستجوی نام..." autocomplete="off">
                                            </div>
                                            <div class="sc-dropdown-options">
                                                <?php
                                                $opt_index = 0;
                                                $max_visible = 10;
                                                foreach ($accountants as $a) :
                                                    $aid = (int) $a['user_id'];
                                                    $label = $a['name'];
                                                    $search = strtolower($label);
                                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                                    $opt_index++;
                                                    $is_selected = ($selected_accountant_id === $aid);
                                                    ?>
                                                    <div class="sc-dropdown-option <?php echo esc_attr($vis); ?><?php echo $is_selected ? ' sc-selected' : ''; ?>"
                                                         data-value="<?php echo esc_attr($aid); ?>"
                                                         data-label="<?php echo esc_attr($label); ?>"
                                                         data-search="<?php echo esc_attr($search); ?>"
                                                         onclick="scSelectMember(this, '<?php echo esc_js((string) $aid); ?>', '<?php echo esc_js($label); ?>')">
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
                        <tr class="sc-ticket-field-row sc-ticket-field-row--subject">
                            <th scope="row"><label for="ticket_subject">موضوع <span class="required">*</span></label></th>
                            <td>
                                <input type="text" name="ticket_subject" id="ticket_subject" class="regular-text" value="<?php echo esc_attr(isset($_POST['ticket_subject']) ? $_POST['ticket_subject'] : ''); ?>" required>
                            </td>
                        </tr>
                        <tr class="sc-ticket-field-row sc-ticket-field-row--message">
                            <th scope="row"><label for="ticket_message">متن پیام <span class="required">*</span></label></th>
                            <td>
                                <textarea name="ticket_message" id="ticket_message" rows="6" class="large-text" required><?php echo esc_textarea(isset($_POST['ticket_message']) ? $_POST['ticket_message'] : ''); ?></textarea>
                            </td>
                        </tr>
                        <tr class="sc-ticket-field-row sc-ticket-field-row--attachment">
                            <th scope="row"><label>پیوست</label></th>
                            <td>
                                <div class="sc-ticket-attachment-zone" data-input-name="ticket_attachment_ids" data-nonce="<?php echo esc_attr(wp_create_nonce('sc_ticket_upload_attachment')); ?>">
                                    <div class="sc-file-upload-area sc-ticket-upload-area" tabindex="0">
                                        <input type="file" class="sc-ticket-file-input-hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.ico,.svg,.tiff,.tif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" multiple>
                                        <span class="sc-file-upload-icon">📎</span>
                                        <span class="sc-file-upload-text">فایل را اینجا رها کنید یا کلیک کنید</span>
                                        <span class="sc-file-upload-hint">حداکثر ۵ فایل، هر کدام ۵ مگابایت. فرمت‌های مجاز: تصویر، PDF، ورد، اکسل، ZIP و RAR</span>
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
                    </tbody>
                </table>

                <p class="submit sc-ticket-new-submit">
                    <button type="submit" class="button button-primary">ارسال تیکت</button>
                </p>
            </form>
        </div>
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
        $('.recipient-accountant-row').toggle(v === 'accountant');
        if (v === 'member') {
            resetDropdown($('.recipient-coach-row'));
            resetDropdown($('.recipient-accountant-row'));
        } else if (v === 'coach') {
            resetDropdown($('.recipient-member-row'));
            resetDropdown($('.recipient-accountant-row'));
        } else if (v === 'accountant') {
            resetDropdown($('.recipient-member-row'));
            resetDropdown($('.recipient-coach-row'));
        } else {
            resetDropdown($('.recipient-member-row'));
            resetDropdown($('.recipient-coach-row'));
            resetDropdown($('.recipient-accountant-row'));
        }
    }).trigger('change');
});
</script>
