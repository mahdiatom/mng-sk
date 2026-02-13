<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');

sc_check_and_create_tables();
global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$notifications_table = $wpdb->prefix . 'sc_notifications';

$edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$notification = null;
if ($edit_id) {
    $notification = $wpdb->get_row($wpdb->prepare("SELECT * FROM $notifications_table WHERE id = %d", $edit_id));
}

$message = '';
$message_type = '';

if (isset($_POST['save_notification']) && check_admin_referer('save_notification_nonce')) {
    $edit_id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $target_type = isset($_POST['target_type']) ? sanitize_text_field($_POST['target_type']) : 'all';
    $send_sms = isset($_POST['send_sms']) ? 1 : 0;

    $target_config = [];
    if ($target_type === 'all') {
        $target_config['user_type'] = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : 'all';
        $target_config['course_scope'] = isset($_POST['course_scope']) ? sanitize_text_field($_POST['course_scope']) : 'all';
        if (!empty($_POST['course_ids']) && is_array($_POST['course_ids'])) {
            $target_config['course_ids'] = array_map('absint', $_POST['course_ids']);
        }
    } elseif ($target_type === 'specific') {
        $rids = isset($_POST['recipient_ids_str']) ? sanitize_text_field($_POST['recipient_ids_str']) : '';
        $target_config['recipient_ids'] = $rids ? array_filter(array_map('trim', explode(',', $rids))) : [];
    } elseif ($target_type === 'course') {
        $target_config['course_ids'] = isset($_POST['course_ids']) && is_array($_POST['course_ids']) ? array_map('absint', $_POST['course_ids']) : [];
    }

    $data = [
        'title' => $title,
        'content' => $content,
        'target_type' => $target_type,
        'target_config' => $target_config,
        'send_sms' => $send_sms
    ];
    if ($edit_id) {
        $data['id'] = $edit_id;
    }

    $result = sc_save_notification($data);
    if ($result['success']) {
        $msg = 'اطلاعیه با موفقیت ذخیره شد. ';
        $msg .= $result['recipients_count'] . ' مخاطب دریافت کرد';
        if ($send_sms && $result['sms_sent'] > 0) {
            $msg .= ' و ' . $result['sms_sent'] . ' پیامک ارسال شد';
        }
        $msg .= '.';
        wp_safe_redirect(admin_url('admin.php?page=sc-notifications&saved=1&msg=' . urlencode($msg)));
        exit;
    } else {
        $message = $result['message'] ?? 'خطا در ذخیره.';
        $message_type = 'error';
    }
}

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id, user_id FROM $members_table WHERE user_id IS NOT NULL AND is_active = 1 ORDER BY last_name, first_name");
$coaches = $wpdb->get_results("SELECT id, first_name, last_name, national_id, user_id FROM $coaches_table WHERE user_id IS NOT NULL AND is_active = 1 ORDER BY last_name, first_name");
$courses_list = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");

if (isset($_GET['saved']) && isset($_GET['msg'])) {
    $message = sanitize_text_field(wp_unslash($_GET['msg']));
    $message_type = 'success';
}

$saved = $notification ? (array)json_decode($notification->target_config, true) : [];
?>
<div class="wrap">
    <h1><?php echo $edit_id ? 'ویرایش اطلاعیه' : 'افزودن اطلاعیه'; ?></h1>
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <form method="post" id="notification-form">
        <?php wp_nonce_field('save_notification_nonce'); ?>
        <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
        <table class="form-table">
            <tr>
                <th><label for="title">عنوان <span class="required">*</span></label></th>
                <td><input type="text" name="title" id="title" class="regular-text" required value="<?php echo esc_attr($notification ? $notification->title : ''); ?>" style="width:100%;max-width:500px;"></td>
            </tr>
            <tr>
                <th><label for="content">متن اطلاعیه <span class="required">*</span></label></th>
                <td>
                    <textarea name="content" id="content" rows="6" class="large-text" required style="width:100%;max-width:600px;"><?php echo esc_textarea($notification ? $notification->content : ''); ?></textarea>
                    <p class="description sc-sms-counter">
                        <span id="sms-char-count">0</span> کاراکتر تایپ شده | 
                        تا پایان پیام فعلی <span id="sms-remaining">70</span> کاراکتر | 
                        معادل <span id="sms-count">0</span> پیامک
                    </p>
                </td>
            </tr>
            <tr>
                <th>نوع ارسال</th>
                <td>
                    <label><input type="radio" name="target_type" value="all" <?php checked(($notification ? $notification->target_type : 'all'), 'all'); ?>> همه</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="target_type" value="specific" <?php checked($notification ? $notification->target_type : '', 'specific'); ?>> اشخاص خاص</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="target_type" value="course" <?php checked($notification ? $notification->target_type : '', 'course'); ?>> دوره خاص</label>
                </td>
            </tr>
            <tr id="row-target-all" class="target-row">
                <th>فیلتر مخاطبین (همه)</th>
                <td>
                    <p>
                        <strong>نوع کاربر:</strong>
                        <label><input type="radio" name="user_type" value="all" <?php checked(isset($saved['user_type']) ? $saved['user_type'] : 'all', 'all'); ?>> همه (بازیکن + مربی)</label>
                        <label><input type="radio" name="user_type" value="player" <?php checked(isset($saved['user_type']) ? $saved['user_type'] : '', 'player'); ?>> بازیکن</label>
                        <label><input type="radio" name="user_type" value="coach" <?php checked(isset($saved['user_type']) ? $saved['user_type'] : '', 'coach'); ?>> مربی</label>
                    </p>
                    <p>
                        <strong>محدوده دوره:</strong>
                        <label><input type="radio" name="course_scope" value="all" <?php checked(isset($saved['course_scope']) ? $saved['course_scope'] : 'all', 'all'); ?>> همه</label>
                        <label><input type="radio" name="course_scope" value="specific" <?php checked(isset($saved['course_scope']) ? $saved['course_scope'] : '', 'specific'); ?>> دوره خاص</label>
                    </p>
                    <p id="row-course-ids-all" class="course-ids-row" style="display:none;">
                        <strong>انتخاب دوره:</strong><br>
                        <select name="course_ids[]" multiple size="6" style="min-width:300px;">
                            <?php foreach ($courses_list as $c) : ?>
                                <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, $saved['course_ids'])) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><small>Ctrl+Click برای انتخاب چند دوره</small>
                    </p>
                </td>
            </tr>
            <tr id="row-target-specific" class="target-row" style="display:none;">
                <th>انتخاب اشخاص</th>
                <td>
                    <div id="recipient-list" style="margin-bottom:10px; min-height:40px; padding:10px; background:#f9f9f9; border-radius:4px;"></div>
                    <div class="sc-recipient-select-wrapper" style="max-width:400px;">
                        <select id="recipient-select" style="width:100%;">
                            <option value="">-- انتخاب بازیکن یا مربی --</option>
                            <optgroup label="بازیکن‌ها">
                                <?php foreach ($members as $m) : ?>
                                    <option value="member_<?php echo $m->id; ?>" data-label="<?php echo esc_attr($m->first_name . ' ' . $m->last_name . ' (بازیکن)'); ?>"><?php echo esc_html($m->first_name . ' ' . $m->last_name . ' - ' . ($m->national_id ?: $m->id)); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="مربی‌ها">
                                <?php foreach ($coaches as $c) : ?>
                                    <option value="coach_<?php echo $c->id; ?>" data-label="<?php echo esc_attr($c->first_name . ' ' . $c->last_name . ' (مربی)'); ?>"><?php echo esc_html($c->first_name . ' ' . $c->last_name . ' - ' . ($c->national_id ?: $c->id)); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <p class="description">انتخاب کنید تا به لیست اضافه شود.</p>
                    </div>
                    <input type="hidden" name="recipient_ids_str" id="recipient-ids-input" value="">
                </td>
            </tr>
            <tr id="row-target-course" class="target-row" style="display:none;">
                <th>انتخاب دوره</th>
                <td>
                    <select name="course_ids[]" id="course-ids-course" multiple size="8" style="min-width:350px;">
                        <?php foreach ($courses_list as $c) : ?>
                            <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, (array)($saved['course_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br><small>Ctrl+Click برای انتخاب چند دوره. ارسال به اعضای فعال دوره.</small>
                </td>
            </tr>
            <tr>
                <th>ارسال پیامک</th>
                <td>
                    <label><input type="checkbox" name="send_sms" id="send_sms" value="1" <?php checked($notification ? $notification->send_sms : 0, 1); ?>> ارسال پیامک به مخاطبین (متن اطلاعیه)</label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="save_notification" class="button button-primary">ذخیره و ارسال</button>
            <a href="<?php echo admin_url('admin.php?page=sc-notifications'); ?>" class="button">انصراف</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var SMS_CHARS = 70;
    function updateSmsCounter() {
        var title = $('#title').val() || '';
        var content = $('#content').val() || '';
        var full = title + "\n" + content;
        var len = full.length;
        var smsCount = Math.max(1, Math.ceil(len / SMS_CHARS));
        var currentSmsRemaining = SMS_CHARS - (len % SMS_CHARS);
        if (len === 0) currentSmsRemaining = SMS_CHARS;
        if (len > 0 && len % SMS_CHARS === 0) currentSmsRemaining = SMS_CHARS;
        $('#sms-char-count').text(len);
        $('#sms-remaining').text(currentSmsRemaining);
        $('#sms-count').text(smsCount);
    }
    $('#title, #content').on('input', updateSmsCounter);
    updateSmsCounter();

    var recipientIds = <?php echo json_encode(isset($saved['recipient_ids']) ? (array)$saved['recipient_ids'] : []); ?>;
    var recipientLabels = <?php
        $labels = [];
        if (!empty($saved['recipient_ids'])) {
            foreach ((array)$saved['recipient_ids'] as $rid) {
                if (preg_match('/^member_(\d+)$/', $rid, $m)) {
                    foreach ($members as $mm) { if ($mm->id == $m[1]) { $labels[$rid] = $mm->first_name . ' ' . $mm->last_name . ' (بازیکن)'; break; } }
                } elseif (preg_match('/^coach_(\d+)$/', $rid, $m)) {
                    foreach ($coaches as $cc) { if ($cc->id == $m[1]) { $labels[$rid] = $cc->first_name . ' ' . $cc->last_name . ' (مربی)'; break; } }
                }
            }
        }
        echo json_encode($labels);
    ?>;

    function renderRecipientList() {
        var html = '';
        recipientIds.forEach(function(id) {
            var lbl = recipientLabels[id] || id;
            html += '<span class="recipient-tag" data-id="' + id + '">' + lbl + ' <button type="button" class="recipient-remove">&times;</button></span> ';
        });
        $('#recipient-list').html(html || '<em style="color:#999;">هنوز کسی انتخاب نشده</em>');
        $('#recipient-ids-input').val(recipientIds.join(','));
    }
    $(document).on('click', '.recipient-remove', function() {
        var id = $(this).closest('.recipient-tag').data('id');
        recipientIds = recipientIds.filter(function(x) { return x !== id; });
        renderRecipientList();
    });
    $('#recipient-select').on('change', function() {
        var val = $(this).val();
        if (!val) return;
        var opt = $(this).find('option:selected');
        var lbl = opt.data('label') || opt.text();
        if (recipientIds.indexOf(val) === -1) {
            recipientIds.push(val);
            recipientLabels[val] = lbl;
        }
        renderRecipientList();
        $(this).val('');
    });
    renderRecipientList();

    $('#notification-form').on('submit', function() {
        $('#recipient-ids-input').val(recipientIds.join(','));
    });

    function toggleTargetRows() {
        var t = $('input[name="target_type"]:checked').val();
        $('.target-row').hide();
        $('#row-target-' + t).show();
        if (t === 'all') {
            var cs = $('input[name="course_scope"]:checked').val();
            $('#row-course-ids-all').toggle(cs === 'specific');
        }
    }
    $('input[name="target_type"], input[name="course_scope"]').on('change', toggleTargetRows);
    toggleTargetRows();
});
</script>
<style>
.recipient-tag { display:inline-block; background:#2271b1; color:#fff; padding:4px 8px; margin:2px; border-radius:4px; font-size:13px; }
.recipient-tag .recipient-remove { background:transparent; border:none; color:#fff; cursor:pointer; padding:0 4px; font-size:16px; line-height:1; }
.recipient-tag .recipient-remove:hover { color:#ffcc00; }
.sc-sms-counter { margin-top:8px; color:#646970; }
</style>
