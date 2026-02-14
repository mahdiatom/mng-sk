<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
$is_coach = !empty($GLOBALS['sc_notification_is_coach']);
$current_coach_id = $is_coach && function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
$list_url = $is_coach ? admin_url('admin.php?page=sc-coach-notifications-list') : admin_url('admin.php?page=sc-notifications');
$add_url = $is_coach ? admin_url('admin.php?page=sc-coach-add-notification') : admin_url('admin.php?page=sc-add-notification');

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
    if ($notification && $is_coach && $current_coach_id > 0) {
        $creator_type = isset($notification->created_by_type) ? $notification->created_by_type : 'admin';
        $creator_entity_id = isset($notification->created_by_entity_id) ? (int)$notification->created_by_entity_id : 0;
        if ($creator_type !== 'coach' || $creator_entity_id !== $current_coach_id) {
            $notification = null;
            wp_die('شما فقط می‌توانید اطلاعیه‌های خود را ویرایش کنید.');
        }
    }
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
        wp_safe_redirect(add_query_arg(['saved' => 1, 'msg' => $msg], $list_url));
        exit;
    } else {
        $message = $result['message'] ?? 'خطا در ذخیره.';
        $message_type = 'error';
    }
}

if ($is_coach && $current_coach_id > 0) {
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $coach_course_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
        $current_coach_id
    ));
    $coach_course_ids = array_map('absint', (array)$coach_course_ids);
    $courses_list = [];
    if (!empty($coach_course_ids)) {
        $placeholders = implode(',', array_fill(0, count($coach_course_ids), '%d'));
        $courses_list = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title FROM $courses_table WHERE id IN ($placeholders) AND deleted_at IS NULL AND is_active = 1 ORDER BY title",
            ...$coach_course_ids
        ));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT m.id, m.first_name, m.last_name, m.national_id, m.user_id
             FROM $members_table m
             INNER JOIN $member_courses_table mc ON mc.member_id = m.id
             WHERE mc.course_id IN ($placeholders) AND mc.status = 'active'
             AND m.user_id IS NOT NULL AND m.is_active = 1
             ORDER BY m.last_name, m.first_name",
            ...$coach_course_ids
        ));
    } else {
        $members = [];
    }
    $coaches = [];
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id, user_id FROM $members_table WHERE user_id IS NOT NULL AND is_active = 1 ORDER BY last_name, first_name");
    $coaches = $wpdb->get_results("SELECT id, first_name, last_name, national_id, user_id FROM $coaches_table WHERE user_id IS NOT NULL AND is_active = 1 ORDER BY last_name, first_name");
    $courses_list = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");
}

if (isset($_GET['saved']) && isset($_GET['msg'])) {
    $message = sanitize_text_field(wp_unslash($_GET['msg']));
    $message_type = 'success';
}

$saved = $notification ? (array)json_decode($notification->target_config, true) : [];
?>
<div class="wrap sc-notification-add-wrap<?php echo $is_coach ? ' sc-coach-panel-wrap' : ''; ?>">
    <?php if ($is_coach) : ?>
        <div class="sc-coach-panel-header">
            <a href="<?php echo esc_url($list_url); ?>" class="sc-coach-panel-back">← بازگشت به لیست اطلاعیه‌ها</a>
            <h1 class="sc-coach-panel-title"><?php echo $edit_id ? 'ویرایش اطلاعیه' : 'افزودن اطلاعیه'; ?></h1>
            <p class="sc-coach-panel-desc"><?php echo $edit_id ? 'اطلاعیه خود را ویرایش کنید.' : 'اطلاعیه جدید برای بازیکنان یا دوره‌ها ارسال کنید.'; ?></p>
        </div>
    <?php else : ?>
        <h1 class="sc-notification-add-title"><?php echo $edit_id ? 'ویرایش اطلاعیه' : 'افزودن اطلاعیه'; ?></h1>
    <?php endif; ?>
    <?php if ($is_coach && empty($courses_list)) : ?>
        <div class="notice notice-warning"><p>شما به هیچ دوره‌ای اختصاص داده نشده‌اید. برای ارسال اطلاعیه به بازیکنان، ابتدا از طریق مدیر به دوره‌ها اضافه شوید.</p></div>
    <?php endif; ?>
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <div class="sc-notification-form-card<?php echo $is_coach ? ' sc-coach-panel-card' : ''; ?>">
    <form method="post" id="notification-form" class="sc-notification-form">
        <?php wp_nonce_field('save_notification_nonce'); ?>
        <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
        <table class="form-table sc-notification-form-table">
            <tr>
                <th scope="row"><label for="title">عنوان <span class="required">*</span></label></th>
                <td><input type="text" name="title" id="title" class="regular-text sc-notification-input" required value="<?php echo esc_attr($notification ? $notification->title : ''); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="content">متن اطلاعیه <span class="required">*</span></label></th>
                <td>
                    <textarea name="content" id="content" rows="6" class="large-text sc-notification-textarea" required><?php echo esc_textarea($notification ? $notification->content : ''); ?></textarea>
                    <p class="description sc-sms-counter">
                        <span id="sms-char-count">0</span> کاراکتر تایپ شده |
                        تا پایان پیام فعلی <span id="sms-remaining">70</span> کاراکتر |
                        معادل <span id="sms-count">0</span> پیامک
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">نوع ارسال</th>
                <td>
                    <select name="target_type" id="target_type" class="sc-notification-select" style="min-width: 200px;">
                        <option value="all" <?php selected($notification ? $notification->target_type : 'all', 'all'); ?>>همه</option>
                        <option value="specific" <?php selected($notification ? $notification->target_type : '', 'specific'); ?>>اشخاص خاص</option>
                        <option value="course" <?php selected($notification ? $notification->target_type : '', 'course'); ?>>دوره خاص</option>
                    </select>
                </td>
            </tr>
            <tr id="row-target-all" class="target-row">
                <th scope="row"><?php echo $is_coach ? 'بازیکنان دوره‌های من' : 'فیلتر مخاطبین (همه)'; ?></th>
                <td>
                    <?php if (!$is_coach) : ?>
                    <p>
                        <strong>نوع کاربر:</strong>
                        <select name="user_type" id="user_type" class="sc-notification-select" style="min-width: 180px; margin-right: 12px;">
                            <option value="all" <?php selected(isset($saved['user_type']) ? $saved['user_type'] : 'all', 'all'); ?>>همه (بازیکن + مربی)</option>
                            <option value="player" <?php selected(isset($saved['user_type']) ? $saved['user_type'] : '', 'player'); ?>>بازیکن</option>
                            <option value="coach" <?php selected(isset($saved['user_type']) ? $saved['user_type'] : '', 'coach'); ?>>مربی</option>
                        </select>
                    </p>
                    <p>
                        <strong>محدوده دوره:</strong>
                        <select name="course_scope" id="course_scope" class="sc-notification-select" style="min-width: 180px; margin-right: 12px;">
                            <option value="all" <?php selected(isset($saved['course_scope']) ? $saved['course_scope'] : 'all', 'all'); ?>>همه</option>
                            <option value="specific" <?php selected(isset($saved['course_scope']) ? $saved['course_scope'] : '', 'specific'); ?>>دوره خاص</option>
                        </select>
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
                    <?php else : ?>
                    <p>
                        <strong>انتخاب دوره (فقط دوره‌های خودتان):</strong><br>
                        <select name="course_ids[]" multiple size="6" style="min-width:300px;">
                            <?php foreach ($courses_list as $c) : ?>
                                <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, (array)($saved['course_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><small>Ctrl+Click برای انتخاب چند دوره. ارسال به بازیکنان فعال آن دوره‌ها.</small>
                    </p>
                    <input type="hidden" name="user_type" value="player">
                    <input type="hidden" name="course_scope" value="specific">
                    <?php endif; ?>
                </td>
            </tr>
            <tr id="row-target-specific" class="target-row" style="display:none;">
                <th scope="row">انتخاب اشخاص</th>
                <td>
                    <div id="recipient-list" class="sc-notification-recipient-tags"></div>
                    <div class="sc-searchable-dropdown sc-notification-recipient-dropdown">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder"><?php echo $is_coach ? 'جستجو یا انتخاب بازیکن برای افزودن...' : 'جستجو یا انتخاب بازیکن / مربی برای افزودن...'; ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option-group">بازیکن‌ها</div>
                                <?php
                                $opt_index = 0;
                                $max_visible = 10;
                                foreach ($members as $m) :
                                    $val = 'member_' . $m->id;
                                    $label = $m->first_name . ' ' . $m->last_name . ' (بازیکن)';
                                    $search = strtolower($m->first_name . ' ' . $m->last_name . ' ' . ($m->national_id ?: ''));
                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                    $opt_index++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo $vis; ?>" data-value="<?php echo esc_attr($val); ?>" data-label="<?php echo esc_attr($label); ?>" data-search="<?php echo esc_attr($search); ?>"><?php echo esc_html($m->first_name . ' ' . $m->last_name . ' - ' . ($m->national_id ?: $m->id)); ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($coaches)) : ?>
                                <div class="sc-dropdown-option-group">مربی‌ها</div>
                                <?php foreach ($coaches as $c) :
                                    $val = 'coach_' . $c->id;
                                    $label = $c->first_name . ' ' . $c->last_name . ' (مربی)';
                                    $search = strtolower($c->first_name . ' ' . $c->last_name . ' ' . ($c->national_id ?: ''));
                                    $vis = ($opt_index < $max_visible) ? 'sc-visible' : 'sc-hidden';
                                    $opt_index++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo $vis; ?>" data-value="<?php echo esc_attr($val); ?>" data-label="<?php echo esc_attr($label); ?>" data-search="<?php echo esc_attr($search); ?>"><?php echo esc_html($c->first_name . ' ' . $c->last_name . ' - ' . ($c->national_id ?: $c->id)); ?></div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <p class="description">با تایپ جستجو کنید یا از لیست انتخاب کنید تا به مخاطبین اضافه شود.</p>
                    <input type="hidden" name="recipient_ids_str" id="recipient-ids-input" value="">
                </td>
            </tr>
            <tr id="row-target-course" class="target-row" style="display:none;">
                <th scope="row">انتخاب دوره</th>
                <td>
                    <select name="course_ids[]" id="course-ids-course" multiple size="8" style="min-width:350px;">
                        <?php foreach ($courses_list as $c) : ?>
                            <option value="<?php echo $c->id; ?>" <?php echo (isset($saved['course_ids']) && in_array($c->id, (array)($saved['course_ids'] ?? []))) ? 'selected' : ''; ?>><?php echo esc_html($c->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <br><small>Ctrl+Click برای انتخاب چند دوره. ارسال به اعضای فعال دوره.</small>
                </td>
            </tr>
            <?php if (!$is_coach) : ?>
            <tr>
                <th scope="row">ارسال پیامک</th>
                <td>
                    <label><input type="checkbox" name="send_sms" id="send_sms" value="1" <?php checked($notification ? $notification->send_sms : 0, 1); ?>> ارسال پیامک به مخاطبین (متن اطلاعیه)</label>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php if (!$is_coach) : ?>
        <div id="sc-sms-summary" class="sc-sms-summary" style="display: none; margin: 20px 0; padding: 16px; background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 8px;">
            <strong>خلاصه ارسال پیامک:</strong>
            <p style="margin: 8px 0 0 0; color: #1d2327;">
                تعداد مخاطبین: <span id="sms-recipients-count">0</span> نفر |
                تعداد پیامک: <span id="sms-total-count">0</span> عدد |
                هزینه حدودی: <span id="sms-estimated-cost">0</span> تومان
            </p>
        </div>
        <?php endif; ?>
        <p class="submit">
            <button type="submit" name="save_notification" id="btn-save-notification" class="button button-primary">ذخیره و ارسال</button>
            <a href="<?php echo esc_url($list_url); ?>" class="button">انصراف</a>
        </p>
    </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var SMS_CHARS = 70;
    var smsCostPerMessage = <?php echo json_encode(floatval(sc_get_setting('sms_cost_per_message', '200'))); ?>;
    var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
    var isCoach = <?php echo $is_coach ? 'true' : 'false'; ?>;

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
        return smsCount;
    }
    $('#title, #content').on('input', updateSmsCounter);
    updateSmsCounter();

    function getTargetConfig() {
        var targetType = $('#target_type').val();
        var config = {};
        if (targetType === 'all') {
            config.user_type = $('#user_type').val() || 'all';
            config.course_scope = $('#course_scope').val() || 'all';
            if (config.course_scope === 'specific') {
                config.course_ids = ($('select[name="course_ids[]"]').val() || []).map(Number);
            }
        } else if (targetType === 'specific') {
            config.recipient_ids = recipientIds;
        } else if (targetType === 'course') {
            config.course_ids = ($('#course-ids-course').val() || []).map(Number);
        }
        return config;
    }

    function updateSmsSummary() {
        if (isCoach || !$('#send_sms').length || !$('#send_sms').is(':checked')) {
            $('#sc-sms-summary').hide();
            return;
        }
        $('#sc-sms-summary').show();
        var smsPerMsg = updateSmsCounter();
        var cfg = getTargetConfig();
        if (cfg.recipient_ids && Array.isArray(cfg.recipient_ids)) {
            cfg.recipient_ids = cfg.recipient_ids.join(',');
        }
        if (cfg.course_ids && Array.isArray(cfg.course_ids)) {
            cfg.course_ids = cfg.course_ids.join(',');
        }
        $.post(ajaxUrl, {
            action: 'sc_notification_recipients_count',
            target_type: $('#target_type').val(),
            target_config: cfg
        }, function(res) {
            if (res.success && res.data && typeof res.data.count !== 'undefined') {
                var recipients = res.data.count;
                var totalSms = smsPerMsg * recipients;
                var cost = Math.round(totalSms * smsCostPerMessage);
                $('#sms-recipients-count').text(recipients);
                $('#sms-total-count').text(totalSms);
                $('#sms-estimated-cost').text(cost.toLocaleString('fa-IR'));
            } else {
                $('#sms-recipients-count').text('0');
                $('#sms-total-count').text('0');
                $('#sms-estimated-cost').text('0');
            }
        }).fail(function() {
            $('#sms-recipients-count').text('?');
            $('#sms-total-count').text('?');
            $('#sms-estimated-cost').text('?');
        });
    }

    $('#target_type, #user_type, #course_scope').on('change', function() {
        toggleTargetRows();
        if (!$('#send_sms').length || $('#send_sms').is(':checked')) updateSmsSummary();
    });
    $(document).on('change', 'select[name="course_ids[]"]', function() {
        if (!$('#send_sms').length || $('#send_sms').is(':checked')) updateSmsSummary();
    });
    var summaryDebounce;
    $(document).on('scRecipientListChanged', function() {
        if (!$('#send_sms').length || $('#send_sms').is(':checked')) {
            clearTimeout(summaryDebounce);
            summaryDebounce = setTimeout(updateSmsSummary, 300);
        }
    });

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
        $(document).trigger('scRecipientListChanged');
    }
    $(document).on('click', '.recipient-remove', function() {
        var id = $(this).closest('.recipient-tag').data('id');
        recipientIds = recipientIds.filter(function(x) { return x !== id; });
        renderRecipientList();
    });
    // دراپ‌داون جستجو: با کلیک روی گزینه به لیست مخاطبین اضافه شود (استفاده از capture تا قبل از stopPropagation در admin.js اجرا شود)
    document.addEventListener('click', function(e) {
        if (!e.target || !e.target.closest) return;
        var opt = e.target.closest('.sc-dropdown-option');
        if (!opt || !opt.closest('.sc-notification-recipient-dropdown') || !jQuery(opt).length) return;
        e.preventDefault();
        e.stopPropagation();
        var $opt = jQuery(opt);
        var val = $opt.data('value');
        var lbl = $opt.data('label') || $opt.text().trim();
        if (val && recipientIds.indexOf(val) === -1) {
            recipientIds.push(val);
            recipientLabels[val] = lbl;
        }
        renderRecipientList();
        var $menu = $opt.closest('.sc-dropdown-menu');
        $menu.slideUp(200);
        $menu.find('.sc-search-input').val('').trigger('input');
    }, true);
    renderRecipientList();

    $('#notification-form').on('submit', function() {
        $('#recipient-ids-input').val(recipientIds.join(','));
    });

    function toggleTargetRows() {
        var t = $('#target_type').val();
        $('.target-row').hide();
        $('#row-target-' + t).show();
        if (t === 'all') {
            var cs = $('#course_scope').val();
            $('#row-course-ids-all').toggle(cs === 'specific');
        }
    }
    $('#target_type, #course_scope').on('change', toggleTargetRows);
    toggleTargetRows();

    var formSubmitted = false;
    $('#notification-form').on('submit', function(e) {
        $('#recipient-ids-input').val(recipientIds.join(','));
        if (!formSubmitted && !isCoach && $('#send_sms').length && $('#send_sms').is(':checked')) {
            e.preventDefault();
            var rc = $('#sms-recipients-count').text();
            var total = $('#sms-total-count').text();
            if (confirm('شما در حال ارسال ' + total + ' پیامک به ' + rc + ' مخاطب هستید. آیا مطمئن هستید؟')) {
                formSubmitted = true;
                $(this).submit();
            }
            return false;
        }
    });
    if (!isCoach && $('#send_sms').length) {
        $('#send_sms').on('change', updateSmsSummary);
        if ($('#send_sms').is(':checked')) updateSmsSummary();
    }
});
</script>
<style>
/* کارت فرم - تمام عرض و ظاهر مدرن */
.sc-notification-add-wrap { max-width: none; }
.sc-notification-add-title { margin-bottom: 0; padding-bottom: 10px; }
.sc-notification-form-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    padding: 28px 32px;
    margin-top: 20px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
.sc-notification-form-table { margin: 0; width: 100%; max-width: 100%; }
.sc-notification-form-table th { padding: 14px 16px 14px 0; width: 200px; font-weight: 600; color: #1d2327; vertical-align: top; }
.sc-notification-form-table td { padding: 14px 0; }
.sc-notification-input { width: 100%; max-width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #8c8f94; font-size: 14px; box-sizing: border-box; }
.sc-notification-input:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
.sc-notification-textarea { width: 100%; max-width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid #8c8f94; font-size: 14px; line-height: 1.6; resize: vertical; box-sizing: border-box; }
.sc-notification-textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
.sc-notification-form-table .target-row td { padding-top: 16px; padding-bottom: 16px; }
.sc-notification-form-table label { margin-left: 12px; margin-right: 0; cursor: pointer; }
.sc-notification-form-table label:first-of-type { margin-left: 0; }
.sc-notification-form-table p.submit { margin-top: 24px; margin-bottom: 0; padding-top: 20px; border-top: 1px solid #e5e7eb; }
.sc-notification-form-table .button-primary { padding: 10px 24px; border-radius: 8px; font-weight: 600; }
/* مخاطبین و دراپ‌داون */
.sc-notification-recipient-tags { min-height: 48px; padding: 12px 14px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 14px; }
.sc-notification-recipient-tags .recipient-tag { display: inline-block; background: #2271b1; color: #fff; padding: 6px 12px; margin: 4px 4px 4px 0; border-radius: 8px; font-size: 13px; }
.sc-notification-recipient-tags .recipient-tag .recipient-remove { background: transparent; border: none; color: #fff; cursor: pointer; padding: 0 4px; font-size: 16px; line-height: 1; margin-right: 4px; }
.sc-notification-recipient-tags .recipient-tag .recipient-remove:hover { color: #ffcc00; }
.sc-notification-recipient-tags em { color: #646970; font-style: normal; }
.sc-notification-recipient-dropdown { max-width: 100%; width: 100%; }
.sc-notification-recipient-dropdown .sc-dropdown-toggle { min-height: 44px; padding: 10px 36px 10px 14px; display: flex; align-items: center; border-radius: 8px; border: 1px solid #8c8f94; background: #fff; }
.sc-notification-recipient-dropdown .sc-dropdown-placeholder { color: #646970; }
.sc-notification-recipient-dropdown .sc-dropdown-menu { border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.12); }
.sc-dropdown-option-group { padding: 10px 12px; font-weight: 600; color: #1d2327; background: #f0f0f1; font-size: 12px; border-bottom: 1px solid #dcdcde; }
.sc-sms-counter { margin-top: 10px; color: #646970; font-size: 13px; }
.sc-notification-form-table select[multiple] { min-width: 100%; max-width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #8c8f94; }
.sc-notification-select { padding: 8px 12px; border-radius: 8px; border: 1px solid #8c8f94; font-size: 14px; }
.sc-notification-select:focus { border-color: #2271b1; outline: none; }
.sc-notification-form-table .course-ids-row select { min-height: 140px; }
.sc-notification-form-table #course-ids-course { min-height: 180px; }
</style>
